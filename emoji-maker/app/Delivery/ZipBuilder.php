<?php

declare(strict_types=1);

namespace EmojiMaker\Delivery;

/**
 * Builds ZIP archives with ZipArchive when the zip extension exists and with
 * a small pure PHP writer otherwise (shared hosts without ext-zip).
 *
 * The pure writer produces standard PKZIP 2.0 archives: stored or raw
 * deflate entries, CRC32, UTF-8 names (general purpose flag bit 11), no
 * ZIP64 (outputs are far below 4 GB). Already compressed media (webm, tgs,
 * png, mp4, gif, zip) are stored, text is deflated.
 *
 * Entry names are sanitised: no absolute paths, no "..", no control
 * characters, unique inside the archive.
 */
final class ZipBuilder
{
    private const STORE_EXT = ['webm', 'tgs', 'png', 'mp4', 'gif', 'jpg', 'jpeg', 'webp', 'zip', 'gz'];
    private const MAX_ENTRIES = 65000;
    private const MAX_BYTES = 0xFFFFFFFF - 1024 * 1024;

    private bool $pure;
    /** @var list<array{name:string,file:?string,data:?string}> */
    private array $entries = [];
    /** @var array<string,true> */
    private array $names = [];

    public function __construct(?bool $forcePure = null)
    {
        $this->pure = $forcePure ?? !class_exists(\ZipArchive::class);
    }

    public function isPure(): bool
    {
        return $this->pure;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function add(string $nameInZip, string $absPath): void
    {
        if (!is_file($absPath) || !is_readable($absPath)) {
            throw new \RuntimeException('File for ZIP is missing: ' . basename($absPath));
        }
        $this->entries[] = ['name' => $this->uniqueName(self::cleanName($nameInZip)), 'file' => $absPath, 'data' => null];
        $this->guardCount();
    }

    public function addString(string $nameInZip, string $data): void
    {
        $this->entries[] = ['name' => $this->uniqueName(self::cleanName($nameInZip)), 'file' => null, 'data' => $data];
        $this->guardCount();
    }

    /** Writes the archive atomically (temp file + rename); returns its size in bytes. */
    public function write(string $destAbsPath): int
    {
        if (!$this->entries) {
            throw new \RuntimeException('ZIP has no entries');
        }
        $dir = dirname($destAbsPath);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('ZIP destination is not writable');
        }
        $tmp = $destAbsPath . '.part' . bin2hex(random_bytes(3));
        try {
            $this->pure ? $this->writePure($tmp) : $this->writeZipArchive($tmp);
            if (!@rename($tmp, $destAbsPath)) {
                throw new \RuntimeException('Unable to move ZIP into place');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        clearstatcache(true, $destAbsPath);
        return (int) filesize($destAbsPath);
    }

    /** Safe relative entry name (forward slashes, no traversal, valid UTF-8). */
    public static function cleanName(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }
        $name = str_replace('\\', '/', $name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = (string) preg_replace('/^[A-Za-z]:/', '', $name);
        $parts = [];
        foreach (explode('/', $name) as $p) {
            $p = trim($p);
            if ($p === '' || $p === '.' || $p === '..') {
                continue;
            }
            $parts[] = mb_substr($p, 0, 120);
        }
        $clean = implode('/', array_slice($parts, -4));
        if ($clean === '') {
            throw new \InvalidArgumentException('Empty ZIP entry name');
        }
        return $clean;
    }

    private function uniqueName(string $name): string
    {
        $candidate = $name;
        $dot = strrpos($name, '.');
        $base = $dot !== false && $dot > 0 ? substr($name, 0, $dot) : $name;
        $ext = $dot !== false && $dot > 0 ? substr($name, $dot) : '';
        for ($i = 2; isset($this->names[mb_strtolower($candidate)]); $i++) {
            $candidate = $base . '_' . $i . $ext;
        }
        $this->names[mb_strtolower($candidate)] = true;
        return $candidate;
    }

    private function guardCount(): void
    {
        if (count($this->entries) > self::MAX_ENTRIES) {
            throw new \LengthException('Too many ZIP entries');
        }
    }

    private static function stored(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::STORE_EXT, true);
    }

    private function writeZipArchive(string $tmp): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZipArchive cannot create the archive');
        }
        foreach ($this->entries as $e) {
            $ok = $e['file'] !== null ? $zip->addFile($e['file'], $e['name']) : $zip->addFromString($e['name'], (string) $e['data']);
            if (!$ok) {
                $zip->close();
                throw new \RuntimeException('ZipArchive cannot add an entry');
            }
            if (self::stored($e['name']) && method_exists($zip, 'setCompressionName')) {
                $zip->setCompressionName($e['name'], \ZipArchive::CM_STORE);
            }
        }
        if (!$zip->close()) {
            throw new \RuntimeException('ZipArchive cannot write the archive');
        }
    }

    private function writePure(string $tmp): void
    {
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open ZIP for writing');
        }
        try {
            $central = '';
            $offset = 0;
            [$dosTime, $dosDate] = self::dosTime(time());
            foreach ($this->entries as $e) {
                $data = $e['file'] !== null ? file_get_contents($e['file']) : (string) $e['data'];
                if ($data === false) {
                    throw new \RuntimeException('Cannot read file for ZIP');
                }
                $size = strlen($data);
                $crc = crc32($data);
                $method = 0;
                $payload = $data;
                if (!self::stored($e['name']) && $size > 64) {
                    $deflated = gzdeflate($data, 6);
                    if ($deflated !== false && strlen($deflated) < $size) {
                        $method = 8;
                        $payload = $deflated;
                    }
                }
                unset($data);
                $csize = strlen($payload);
                if ($offset + $csize > self::MAX_BYTES) {
                    throw new \LengthException('ZIP would exceed 4 GB');
                }
                $name = $e['name'];
                $flags = 0x0800; // names are UTF-8
                $local = pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, $method, $dosTime, $dosDate, $crc, $csize, $size, strlen($name), 0) . $name;
                self::fwriteAll($fh, $local);
                self::fwriteAll($fh, $payload);
                $central .= pack(
                    'VvvvvvvVVVvvvvvVV',
                    0x02014b50,
                    (3 << 8) | 20,      // made by: UNIX, spec 2.0
                    20,
                    $flags,
                    $method,
                    $dosTime,
                    $dosDate,
                    $crc,
                    $csize,
                    $size,
                    strlen($name),
                    0,
                    0,
                    0,
                    0,
                    (0100644 << 16),     // -rw-r--r--
                    $offset
                ) . $name;
                $offset += strlen($local) + $csize;
                unset($payload);
            }
            self::fwriteAll($fh, $central);
            $n = count($this->entries);
            self::fwriteAll($fh, pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), $offset, 0));
        } finally {
            fclose($fh);
        }
    }

    /** @param resource $fh */
    private static function fwriteAll($fh, string $data): void
    {
        $len = strlen($data);
        $written = 0;
        while ($written < $len) {
            $w = fwrite($fh, $written === 0 ? $data : substr($data, $written));
            if ($w === false || $w === 0) {
                throw new \RuntimeException('Writing the ZIP failed (disk full?)');
            }
            $written += $w;
        }
    }

    /** @return array{0:int,1:int} MS-DOS time and date */
    private static function dosTime(int $ts): array
    {
        $d = getdate($ts);
        $year = max(1980, $d['year']);
        return [
            ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
            (($year - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
        ];
    }
}
