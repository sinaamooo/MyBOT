<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Imports a pack of original animated emoji (a ZIP of .tgs / Lottie .json
 * files, or a directory) as templates: stores every animation unchanged,
 * runs the logo detector over the whole pack and writes a spec per
 * animation (status "auto" until an admin reviews it).
 *
 * The ZIP is never extracted to disk: entries are read in memory after
 * checking their names, count and uncompressed size (zip-slip / zip-bomb safe).
 */
final class PackImporter
{
    public function __construct(
        private string $baseDir,
        private LogoDetector $detector,
        private int $maxFiles = 300,
        private int $maxEntryBytes = 4 * 1024 * 1024,
    ) {
    }

    /** @return array{pack:string,dir:string,files:list<array<string,mixed>>,cluster:int} */
    public function importZip(string $zipFile, string $pack, string $packName, int $sort = 100): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new TemplateException('افزونه‌ی ZIP در PHP فعال نیست.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) {
            throw new TemplateException('فایل ZIP باز نشد.');
        }
        $entries = [];
        try {
            if ($zip->numFiles > $this->maxFiles * 4) {
                throw new TemplateException('تعداد فایل‌های داخل ZIP بیش از حد است.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = (string) $stat['name'];
                if (str_ends_with($name, '/') || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\') || str_starts_with(basename($name), '.')) {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['tgs', 'json'], true)) {
                    continue;
                }
                if ((int) $stat['size'] > $this->maxEntryBytes) {
                    continue;
                }
                $data = $zip->getFromIndex($i, $this->maxEntryBytes + 1);
                if ($data === false || strlen($data) > $this->maxEntryBytes) {
                    continue;
                }
                $entries[$name] = $data;
                if (count($entries) > $this->maxFiles) {
                    throw new TemplateException('تعداد انیمیشن‌های این بسته بیش از حد مجاز است.');
                }
            }
        } finally {
            $zip->close();
        }
        return $this->importEntries($entries, $pack, $packName, $sort);
    }

    /** @return array{pack:string,dir:string,files:list<array<string,mixed>>,cluster:int} */
    public function importDir(string $dir, string $pack, string $packName, int $sort = 100): array
    {
        $entries = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $ext = strtolower($f->getExtension());
            if ($f->isFile() && in_array($ext, ['tgs', 'json'], true) && $f->getSize() <= $this->maxEntryBytes) {
                $rel = substr($f->getPathname(), strlen(rtrim($dir, '/')) + 1);
                $entries[$rel] = (string) file_get_contents($f->getPathname());
            }
        }
        if (count($entries) > $this->maxFiles) {
            throw new TemplateException('تعداد انیمیشن‌های این بسته بیش از حد مجاز است.');
        }
        return $this->importEntries($entries, $pack, $packName, $sort);
    }

    /**
     * @param array<string,string> $entries name => raw bytes
     * @return array{pack:string,dir:string,files:list<array<string,mixed>>,cluster:int}
     */
    private function importEntries(array $entries, string $pack, string $packName, int $sort): array
    {
        $pack = TemplateSpec::slug($pack);
        if ($pack === '') {
            throw new TemplateException('شناسه‌ی بسته نامعتبر است (فقط حروف انگلیسی کوچک، عدد و خط تیره).');
        }
        ksort($entries, SORT_NATURAL);
        $docs = [];
        $raw = [];
        $report = [];
        $n = 0;
        foreach ($entries as $name => $bytes) {
            try {
                $doc = LottieDoc::decode($bytes);
            } catch (TemplateException $e) {
                $report[] = ['file' => $name, 'status' => 'skipped', 'error' => $e->getMessage()];
                continue;
            }
            // a pack may contain the same animation twice (e.g. .tgs and .json)
            $hash = sha1((string) json_encode($doc));
            if (isset($raw[$hash])) {
                $report[] = ['file' => $name, 'status' => 'duplicate'];
                continue;
            }
            $n++;
            $slug = sprintf('%s-%02d', $pack, $n);
            $docs[$slug] = $doc;
            $raw[$hash] = [$slug, str_starts_with($bytes, "\x1f\x8b") ? $bytes : (string) gzencode($bytes, 9), $name];
        }
        if (!$docs) {
            throw new TemplateException('هیچ انیمیشن معتبری (TGS یا Lottie JSON) در بسته پیدا نشد.');
        }
        $dir = rtrim($this->baseDir, '/') . '/' . $pack;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new TemplateException('پوشه‌ی قالب‌ها قابل نوشتن نیست.');
        }
        file_put_contents($dir . '/pack.json', json_encode(['name' => $packName, 'sort' => $sort], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        foreach ($raw as [$slug, $tgs]) {
            file_put_contents($dir . '/' . $slug . '.tgs', $tgs);
        }
        $analysis = $this->detector->analyze($docs, $pack);
        $sources = [];
        foreach ($raw as [$slug, , $name]) {
            $sources[$slug] = $name;
        }
        $i = 0;
        foreach ($analysis['specs'] as $slug => $specArr) {
            $i++;
            $diag = $specArr['diagnostics'] ?? [];
            unset($specArr['diagnostics']);
            $specArr['name'] = sprintf('%s %d', $packName, $i);
            $specArr['sort'] = $i * 10;
            $specArr['meta']['imported_from'] = basename((string) $sources[$slug]);
            $specArr['meta']['detector'] = $diag;
            $spec = TemplateSpec::fromArray($specArr, $dir);
            $existing = $dir . '/' . $slug . '.json';
            if (is_file($existing)) {
                $old = json_decode((string) file_get_contents($existing), true);
                if (is_array($old) && ($old['status'] ?? '') === 'reviewed') {
                    $report[] = ['file' => $sources[$slug], 'slug' => $slug, 'status' => 'kept-reviewed'];
                    continue; // never overwrite a reviewed spec
                }
            }
            file_put_contents($existing, $spec->toJson());
            $report[] = [
                'file' => $sources[$slug], 'slug' => $slug, 'status' => $spec->status,
                'removed' => count($spec->remove), 'slots' => $spec->roles(), 'notes' => $diag['notes'] ?? [],
            ];
        }
        return ['pack' => $pack, 'dir' => $dir, 'files' => $report, 'cluster' => count($analysis['cluster'])];
    }
}
