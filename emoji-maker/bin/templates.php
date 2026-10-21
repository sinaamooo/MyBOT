<?php

declare(strict_types=1);

/*
 * Animated emoji template tool.
 *
 *   php bin/templates.php import <zip|dir> --pack=slug --name="نام بسته" [--into=resources|storage] [--sort=10]
 *   php bin/templates.php list [--pack=slug]
 *   php bin/templates.php inspect <slug> [--deep]
 *   php bin/templates.php strip <slug> --out=file.png [--size=180] [--frames=6] [--text=LOGO] [--caption=BRAND]
 *                                      [--logo=file.png] [--recolor=#hex] [--recolor-mode=main|all|gray]
 *   php bin/templates.php compose <slug> --format=webm|tgs|png --out=dir [--text=..] [--caption=..] [--logo=png]
 *                                      [--font=slug] [--color=#hex] [--outline=#hex --outline-width=0.08]
 *                                      [--recolor=#hex] [--recolor-mode=main|all|gray]
 *   php bin/templates.php box <slug> --host=path --paths=p1,p2      slot box of elements in host coordinates
 *   php bin/templates.php check [--pack=slug] [--tgs]              spec checks (+ TGS size/validation with sample content)
 *   php bin/templates.php sheet --out=file.png [--pack=slug] [--mode=original|removed|sample] [--size=150]
 *   php bin/templates.php previews [--pack=slug] [--slug=slug]     gallery previews (thumb + animation)
 *   php bin/templates.php palette <slug>                           main colour + colours (recolour review)
 */

use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Process\Tools;
use EmojiMaker\Rendering\ImageProcessor;
use EmojiMaker\Support\Env;
use EmojiMaker\Templates\LogoDetector;
use EmojiMaker\Templates\LottieDoc;
use EmojiMaker\Templates\PackImporter;
use EmojiMaker\Templates\TemplateComposer;
use EmojiMaker\Templates\TemplateException;
use EmojiMaker\Templates\TemplateLibrary;
use EmojiMaker\Templates\TemplateRecolor;
use EmojiMaker\Templates\TemplateRenderer;
use EmojiMaker\Templates\TemplateSpec;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Vector\LottiePlayer;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/app/autoload.php';
if (is_file($root . '/.env')) {
    Env::load($root . '/.env');
}
$config = require $root . '/config/app.php';
ini_set('memory_limit', '1024M');

[$cmd, $args, $opts] = parseArgs(array_slice($argv, 1));
$resourcesDir = $root . '/resources/templates';
$storageDir = rtrim((string) (($config['storage']['path'] ?? '') ?: ($root . '/storage')), '/') . '/templates';
$library = new TemplateLibrary([$resourcesDir, $storageDir]);
$fonts = new FontRegistry($root . '/resources/fonts');
$tools = new Tools(new ProcessRunner(300), $root . '/storage/cache/tools.json', (string) ($config['tools']['ffmpeg'] ?? ''), (string) ($config['tools']['ffprobe'] ?? ''));
$renderer = new TemplateRenderer($fonts, $tools, $config['telegram_formats'], ['preview_bg' => '#1C2733', 'preview_size' => 256, 'webm_crf' => 30, 'vector_max_colors' => 6]);

try {
    switch ($cmd) {
        case 'import':
            $src = $args[0] ?? usage();
            $pack = (string) ($opts['pack'] ?? usage());
            $into = ($opts['into'] ?? 'resources') === 'storage' ? $storageDir : $resourcesDir;
            $importer = new PackImporter($into, new LogoDetector());
            $res = is_dir($src)
                ? $importer->importDir($src, $pack, (string) ($opts['name'] ?? $pack), (int) ($opts['sort'] ?? 100))
                : $importer->importZip($src, $pack, (string) ($opts['name'] ?? $pack), (int) ($opts['sort'] ?? 100));
            printf("pack %s -> %s (logo cluster: %d shapes)\n", $res['pack'], $res['dir'], $res['cluster']);
            foreach ($res['files'] as $f) {
                printf("  %-12s %-10s %s removed=%s slots=%s %s\n", $f['slug'] ?? '-', $f['status'], $f['file'], $f['removed'] ?? '-', implode('+', $f['slots'] ?? []), implode('; ', $f['notes'] ?? []));
            }
            break;

        case 'list':
            foreach ($library->all() as $spec) {
                if (isset($opts['pack']) && $spec->pack !== $opts['pack']) {
                    continue;
                }
                printf("%-14s %-9s slots=%-13s remove=%-3d recolor=%s protect=%d  %s\n", $spec->slug, $spec->status, implode('+', $spec->roles()) ?: '-', count($spec->remove), $spec->recolor['enabled'] ? 'on' : 'off', count($spec->recolor['protect']), $spec->name);
            }
            break;

        case 'inspect':
            $spec = needSpec($library, $args[0] ?? '');
            inspect($spec, $library, isset($opts['deep']));
            break;

        case 'strip':
            $spec = needSpec($library, $args[0] ?? '');
            $out = (string) ($opts['out'] ?? usage());
            strip($spec, $renderer, $out, (int) ($opts['size'] ?? 180), (int) ($opts['frames'] ?? 6), $opts, $root);
            echo "written $out\n";
            break;

        case 'compose':
            $spec = needSpec($library, $args[0] ?? '');
            $outDir = (string) ($opts['out'] ?? usage());
            $content = contentFromOpts($opts, $root);
            $res = $renderer->render($spec, $content, (string) ($opts['format'] ?? 'webm'), $outDir, ['preview' => !isset($opts['no-preview'])]);
            foreach ($res['tiles'] as $t) {
                printf("%s  %d bytes  %d frames  %s\n", $t['path'], $t['bytes'], $t['frames'], $t['status']);
                foreach ($t['validation']['errors'] ?? [] as $e) {
                    echo "  error: $e\n";
                }
                foreach ($t['validation']['warnings'] ?? [] as $w) {
                    echo "  warning: $w\n";
                }
            }
            foreach ($res['warnings'] as $w) {
                echo "warning: $w\n";
            }
            if (!empty($res['preview'])) {
                echo "preview: {$res['preview']}\nthumb: {$res['thumb']}\n";
            }
            break;

        case 'box':
            $spec = needSpec($library, $args[0] ?? '');
            $doc = LottieDoc::load($spec->sourcePath());
            $paths = array_values(array_filter(array_map('trim', explode(',', (string) ($opts['paths'] ?? '')))));
            $res = (new LogoDetector())->slotBox($doc, (string) ($opts['host'] ?? usage()), $paths);
            echo json_encode($res, JSON_UNESCAPED_SLASHES) . "\n";
            break;

        case 'check':
            $bad = 0;
            foreach ($library->all() as $spec) {
                if (isset($opts['pack']) && $spec->pack !== $opts['pack']) {
                    continue;
                }
                $doc = LottieDoc::load($spec->sourcePath());
                $problems = $spec->check($doc);
                $line = sprintf('%-14s %-9s', $spec->slug, $spec->status);
                if ($problems) {
                    $bad++;
                    echo $line . ' SPEC: ' . implode(' | ', $problems) . "\n";
                    continue;
                }
                if (isset($opts['tgs']) && $spec->status !== 'disabled') {
                    $tmp = sys_get_temp_dir() . '/tplcheck_' . getmypid();
                    $sizes = [];
                    foreach ([['text' => 'LOGO', 'caption' => 'BRAND'], ['logo' => sampleLogo($root), 'text' => 'BRAND']] as $c) {
                        try {
                            $r = $renderer->render($spec, $c, 'tgs', $tmp, ['preview' => false]);
                            $sizes[] = round($r['tiles'][0]['bytes'] / 1024, 1) . 'KB ' . $r['tiles'][0]['status'];
                        } catch (TemplateException $e) {
                            $bad++;
                            $sizes[] = 'FAIL ' . $e->getMessage();
                        }
                    }
                    $line .= ' tgs: ' . implode(', ', $sizes);
                }
                echo $line . "\n";
            }
            exit($bad > 0 ? 2 : 0);

        case 'sheet':
            sheet($library, $renderer, (string) ($opts['out'] ?? usage()), $opts['pack'] ?? null, (string) ($opts['mode'] ?? 'removed'), (int) ($opts['size'] ?? 150), $root);
            echo "written {$opts['out']}\n";
            break;

        case 'previews':
            foreach ($library->all() as $spec) {
                if ((isset($opts['pack']) && $spec->pack !== $opts['pack']) || (isset($opts['slug']) && $spec->slug !== $opts['slug']) || $spec->status === 'disabled') {
                    continue;
                }
                $dir = $spec->dir . '/previews';
                @mkdir($dir, 0750, true);
                $res = $renderer->render($spec, ['text' => 'LOGO', 'caption' => 'BRAND'], 'png', sys_get_temp_dir() . '/tplprev_' . getmypid(), ['preview' => true, 'name' => $spec->slug, 'preview_size' => 256]);
                copy($res['thumb'], $dir . '/' . $spec->slug . '_thumb.png');
                if (!empty($res['preview'])) {
                    $ext = pathinfo($res['preview'], PATHINFO_EXTENSION);
                    foreach (['mp4', 'gif'] as $old) {
                        @unlink($dir . '/' . $spec->slug . '_preview.' . $old);
                    }
                    copy($res['preview'], $dir . '/' . $spec->slug . '_preview.' . $ext);
                }
                echo "{$spec->slug} ok\n";
            }
            break;

        case 'palette':
            $spec = needSpec($library, $args[0] ?? '');
            $doc = LottieDoc::load($spec->sourcePath());
            $rc = new TemplateRecolor();
            $p = $rc->primary($doc, $spec->recolor['protect']);
            echo 'primary: ' . ($p['hex'] ?? 'none') . ($p ? sprintf(' (L=%.2f C=%.3f h=%.0f)', $p['L'], $p['C'], $p['h']) : '') . "\n";
            echo 'spec primary: ' . ($spec->recolor['primary'] ?? 'auto') . ', enabled: ' . ($spec->recolor['enabled'] ? 'yes' : 'no') . ', protect: ' . implode(', ', $spec->recolor['protect']) . "\n";
            break;

        default:
            usage();
    }
} catch (TemplateException $e) {
    fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");
    exit(1);
}

// ----------------------------------------------------------------------

function usage(): never
{
    $src = (string) file_get_contents(__FILE__);
    preg_match('~/\*(.*?)\*/~s', $src, $m);
    fwrite(STDERR, trim($m[1] ?? '') . "\n");
    exit(64);
}

/** @return array{0:string,1:list<string>,2:array<string,string|bool>} */
function parseArgs(array $argv): array
{
    $cmd = (string) array_shift($argv);
    $args = [];
    $opts = [];
    foreach ($argv as $a) {
        if (str_starts_with($a, '--')) {
            $kv = explode('=', substr($a, 2), 2);
            $opts[$kv[0]] = $kv[1] ?? true;
        } else {
            $args[] = $a;
        }
    }
    return [$cmd, $args, $opts];
}

function needSpec(TemplateLibrary $library, string $slug): TemplateSpec
{
    $spec = $library->get($slug);
    if ($spec === null) {
        fwrite(STDERR, "unknown template: $slug\n");
        exit(1);
    }
    return $spec;
}

/** Bright, asymmetric test logo so placement, rotation and scaling are easy to see. */
function sampleLogo(string $root): string
{
    $file = $root . '/storage/cache/sample_logo.png';
    if (is_file($file)) {
        return $file;
    }
    @mkdir(dirname($file), 0750, true);
    $im = ImageProcessor::canvas(400, 300);
    imagealphablending($im, true);
    imagefilledellipse($im, 150, 150, 280, 280, imagecolorallocate($im, 255, 45, 149));
    imagefilledpolygon($im, [150, 40, 380, 150, 150, 260], imagecolorallocate($im, 255, 214, 0));
    imagefilledrectangle($im, 60, 120, 150, 180, imagecolorallocate($im, 0, 200, 255));
    imagesavealpha($im, true);
    imagepng($im, $file);
    return $file;
}

/** @param array<string,string|bool> $opts */
function contentFromOpts(array $opts, string $root): array
{
    $c = [];
    foreach (['text', 'caption', 'font', 'color', 'outline', 'caption_color' => 'caption-color', 'recolor', 'recolor_mode' => 'recolor-mode'] as $k => $o) {
        $key = is_int($k) ? $o : $k;
        if (isset($opts[$o]) && is_string($opts[$o])) {
            $c[$key] = $opts[$o];
        }
    }
    if (isset($opts['outline-width'])) {
        $c['outline_width'] = (float) $opts['outline-width'];
    }
    if (isset($opts['size'])) {
        $c['size'] = (float) $opts['size'];
    }
    if (isset($opts['logo'])) {
        $c['logo'] = $opts['logo'] === true || $opts['logo'] === 'sample' ? sampleLogo($root) : (string) $opts['logo'];
    }
    if (!isset($c['text']) && !isset($c['logo'])) {
        $c['text'] = 'LOGO';
    }
    return $c;
}

function inspect(TemplateSpec $spec, TemplateLibrary $library, bool $deep): void
{
    $doc = LottieDoc::load($spec->sourcePath());
    // pack wide fingerprint frequency
    $df = [];
    foreach ($library->all() as $other) {
        if ($other->pack !== $spec->pack) {
            continue;
        }
        $seen = [];
        LottieDoc::walkShapes(LottieDoc::load($other->sourcePath()), function (array $it) use (&$seen): void {
            if (($it['ty'] ?? '') === 'sh' && ($fp = LogoDetector::fingerprint($it)) !== null) {
                $seen[$fp['fp']] = true;
            }
        });
        foreach (array_keys($seen) as $fp) {
            $df[$fp] = ($df[$fp] ?? 0) + 1;
        }
    }
    $removed = array_fill_keys($spec->remove, true);
    $hosts = [];
    foreach ($spec->slots as $s) {
        $hosts[$s['host']] = $s['role'];
        foreach ($s['also'] as $a) {
            $hosts[$a['host']] = $s['role'] . '(copy)';
        }
    }
    $protect = array_fill_keys($spec->recolor['protect'], true);
    $still = (float) ($spec->meta['still'] ?? floor(((float) $doc['ip'] + (float) $doc['op']) / 2));
    $det = new LogoDetector();
    $flags = static function (string $p) use ($removed, $hosts, $protect): string {
        $f = [];
        if (isset($removed[$p])) {
            $f[] = 'REMOVE';
        }
        if (isset($hosts[$p])) {
            $f[] = 'HOST:' . $hosts[$p];
        }
        if (isset($protect[$p])) {
            $f[] = 'PROTECT';
        }
        return $f ? ' [' . implode(',', $f) . ']' : '';
    };
    printf("%s  %s  fr=%s ip=%s op=%s  still=%s  status=%s\n", $spec->slug, $spec->name, $doc['fr'], $doc['ip'], $doc['op'], $still, $spec->status);
    LottieDoc::walkLayers($doc, function (array $l, string $lp, string $list, int $i, ?string $asset) use ($doc, $det, $still, $flags, $df, $deep): void {
        $bb = $det->canvasBounds($doc, [$lp], [$still]);
        printf("%s ty=%d ind=%s nm=%s parent=%s %s%s ip=%s op=%s box=%s%s\n", $lp, $l['ty'] ?? -1, $l['ind'] ?? '-', json_encode($l['nm'] ?? '', JSON_UNESCAPED_UNICODE), $l['parent'] ?? '-',
            !empty($l['td']) ? 'td ' : '', !empty($l['tt']) ? 'tt=' . $l['tt'] . ' ' : '', $l['ip'] ?? '', $l['op'] ?? '', fmtBox($bb), $flags($lp));
        if ((int) ($l['ty'] ?? -1) === 0) {
            echo "    refId=" . ($l['refId'] ?? '') . "\n";
        }
        if ((int) ($l['ty'] ?? -1) !== 4) {
            return;
        }
        inspectItems($doc, (array) ($l['shapes'] ?? []), $lp . '/shapes', 1, $det, $still, $flags, $df, $deep);
    });
}

function inspectItems(array $doc, array $items, string $base, int $depth, LogoDetector $det, float $still, callable $flags, array $df, bool $deep): void
{
    foreach ($items as $i => $it) {
        if (!is_array($it)) {
            continue;
        }
        $p = $base . '/' . $i;
        $ty = (string) ($it['ty'] ?? '');
        $pad = str_repeat('  ', $depth);
        if ($ty === 'gr') {
            $bb = $det->canvasBounds($doc, [$p], [$still]);
            printf("%s%s gr nm=%s items=%d box=%s%s%s\n", $pad, $p, json_encode($it['nm'] ?? '', JSON_UNESCAPED_UNICODE), count((array) ($it['it'] ?? [])), fmtBox($bb), !empty($it['hd']) ? ' hidden' : '', $flags($p));
            inspectItems($doc, (array) ($it['it'] ?? []), $p . '/it', $depth + 1, $det, $still, $flags, $df, $deep);
            continue;
        }
        $extra = '';
        if ($ty === 'sh') {
            $fp = LogoDetector::fingerprint($it);
            $extra = $fp ? sprintf(' v=%d df=%d%s', $fp['vertices'], $df[$fp['fp']] ?? 0, !empty($it['ks']['a']) ? ' animated' : '') : '';
            if ($deep) {
                $extra .= ' box=' . fmtBox($det->canvasBounds($doc, [$p], [$still]));
            }
        } elseif (in_array($ty, ['fl', 'st'], true)) {
            $k = $it['c']['k'] ?? null;
            if (is_array($k) && isset($k[0]) && is_array($k[0])) {
                $k = $k[0]['s'] ?? null;
                $extra = ' animated';
            }
            if (is_array($k) && count($k) >= 3) {
                $extra = sprintf(' #%02X%02X%02X', (int) round($k[0] * 255), (int) round($k[1] * 255), (int) round($k[2] * 255)) . $extra;
            }
            if ($ty === 'st') {
                $extra .= ' w=' . json_encode($it['w']['k'] ?? null);
            }
        } elseif (in_array($ty, ['gf', 'gs'], true)) {
            $extra = ' t=' . ($it['t'] ?? 1);
        } elseif ($ty === 'tm') {
            $extra = ' s=' . json_encode($it['s']['k'] ?? null) . ' e=' . json_encode(is_array($it['e']['k'] ?? null) ? 'anim' : ($it['e']['k'] ?? null));
        }
        if ($ty !== 'tr' || $deep) {
            printf("%s%s %s nm=%s%s%s%s\n", $pad, $p, $ty, json_encode($it['nm'] ?? '', JSON_UNESCAPED_UNICODE), $extra, !empty($it['hd']) ? ' hidden' : '', $flags($p));
        }
    }
}

function fmtBox(?array $b): string
{
    return $b === null ? '-' : sprintf('[%d,%d,%d,%d]', $b[0], $b[1], $b[2], $b[3]);
}

/** Rows: original / logo removed / sample text / sample logo, columns: frames. */
function strip(TemplateSpec $spec, TemplateRenderer $renderer, string $out, int $size, int $count, array $opts, string $root): void
{
    $doc = LottieDoc::load($spec->sourcePath());
    $frames = LogoDetector::sampleFrames($doc, $count);
    $rows = [];
    $rows['original'] = new LottiePlayer($doc);
    $rows['removed'] = new LottiePlayer((new TemplateComposer())->apply($doc, $spec->remove, []));
    $base = [];
    foreach (['recolor', 'recolor_mode' => 'recolor-mode'] as $k => $o) {
        $key = is_int($k) ? $o : $k;
        if (isset($opts[$o]) && is_string($opts[$o])) {
            $base[$key] = $opts[$o];
        }
    }
    $samples = [
        'text' => $base + ['text' => is_string($opts['text'] ?? null) ? $opts['text'] : 'LOGO', 'caption' => is_string($opts['caption'] ?? null) ? $opts['caption'] : 'BRAND', 'color' => '#FF2D95'],
        'logo' => $base + ['logo' => is_string($opts['logo'] ?? null) ? $opts['logo'] : sampleLogo($root), 'text' => is_string($opts['caption'] ?? null) ? $opts['caption'] : 'BRAND'],
    ];
    foreach ($samples as $label => $content) {
        try {
            $composed = $renderer->compose($spec, $content, 'webm');
            $p = new LottiePlayer($composed['doc']);
            foreach ($composed['images'] as $ref => $img) {
                $p->addImage($ref, $img);
            }
            $rows['sample ' . $label] = $p;
        } catch (TemplateException $e) {
            $rows['sample ' . $label . ' FAILED: ' . $e->getMessage()] = null;
        }
    }
    $label = 16;
    $w = count($frames) * $size;
    $h = count($rows) * ($size + $label);
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, imagecolorallocate($im, 12, 16, 24));
    $white = imagecolorallocate($im, 255, 255, 255);
    $r = 0;
    foreach ($rows as $name => $player) {
        $y = $r * ($size + $label);
        imagestring($im, 3, 4, $y + 1, $spec->slug . ' - ' . $name, $white);
        foreach ($frames as $c => $f) {
            if ($player !== null) {
                $cell = $player->render($f, $size, $size, [28, 39, 51]);
                imagecopy($im, $cell, $c * $size, $y + $label, 0, 0, $size, $size);
            }
            imagestring($im, 2, $c * $size + 3, $y + $label + 2, 'f' . (int) $f, $white);
        }
        $r++;
    }
    @mkdir(dirname($out), 0750, true);
    imagepng($im, $out, 6);
}

function sheet(TemplateLibrary $library, TemplateRenderer $renderer, string $out, ?string $pack, string $mode, int $size, string $root): void
{
    $specs = array_values(array_filter($library->all(), static fn ($s) => $pack === null || $s->pack === $pack));
    $cols = 8;
    $rows = (int) ceil(count($specs) / $cols);
    $im = imagecreatetruecolor($cols * $size, max(1, $rows) * $size);
    imagefilledrectangle($im, 0, 0, $cols * $size - 1, max(1, $rows) * $size - 1, imagecolorallocate($im, 28, 39, 51));
    $yellow = imagecolorallocate($im, 255, 230, 0);
    foreach ($specs as $i => $spec) {
        $doc = LottieDoc::load($spec->sourcePath());
        $player = null;
        try {
            if ($mode === 'original') {
                $player = new LottiePlayer($doc);
            } elseif ($mode === 'removed') {
                $player = new LottiePlayer((new TemplateComposer())->apply($doc, $spec->remove, []));
            } else {
                $c = $renderer->compose($spec, ['text' => 'LOGO', 'caption' => 'BRAND', 'color' => '#FF2D95'], 'webm');
                $player = new LottiePlayer($c['doc']);
            }
        } catch (TemplateException) {
            $player = null;
        }
        $x = ($i % $cols) * $size;
        $y = intdiv($i, $cols) * $size;
        if ($player !== null) {
            $still = (float) ($spec->meta['still'] ?? floor(((float) $doc['ip'] + (float) $doc['op']) / 2));
            imagecopy($im, $player->render($still, $size, $size, [28, 39, 51]), $x, $y, 0, 0, $size, $size);
        }
        imagestring($im, 2, $x + 3, $y + 2, $spec->slug . ($spec->status === 'disabled' ? ' (off)' : ''), $yellow);
    }
    @mkdir(dirname($out), 0750, true);
    imagepng($im, $out, 6);
}
