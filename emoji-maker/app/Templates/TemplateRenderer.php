<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

use EmojiMaker\Encoding\EncodingException;
use EmojiMaker\Encoding\PreviewEncoder;
use EmojiMaker\Encoding\WebmEncoder;
use EmojiMaker\Process\Tools;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Rendering\ImageProcessor;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Validation\StaticValidator;
use EmojiMaker\Validation\TgsValidator;
use EmojiMaker\Validation\WebmValidator;
use EmojiMaker\Vector\LottiePlayer;
use EmojiMaker\Vector\Vectorizer;

/**
 * Template + user content -> Telegram ready files.
 *
 *   tgs   the original animation with the logo replaced by vector content
 *         (traced logo / glyph outlines), size budget 64 KB
 *   webm  every frame rendered by the PHP Lottie player (rlottie semantics)
 *         at 100x100, VP9 + alpha, <= 30 FPS, <= 3 s, <= 256 KB
 *   png   one still frame at 100x100
 *
 * The result array has the same shape as EmojiRenderer::render() so jobs,
 * delivery and pack publishing treat both kinds of emoji the same way.
 *
 * Content keys:
 *   logo           absolute path of a normalised PNG (transparent background)
 *   text           main text (used in the main slot when there is no logo)
 *   caption        text for the caption slot (defaults to "text" when a logo fills the main slot)
 *   font, color, outline, outline_width, size, letter_spacing   text style
 *   caption_color  caption text colour (defaults to the slot's original colour)
 *   recolor        target colour (#hex) for the template artwork, null = original colours
 *   recolor_mode   main | all | gray
 */
final class TemplateRenderer
{
    /**
     * @param array<string,mixed> $formats config telegram_formats
     * @param array<string,mixed> $options webm_crf, preview_size, preview_bg, vector_max_colors
     */
    public function __construct(
        private FontRegistry $fonts,
        private Tools $tools,
        private array $formats,
        private array $options = [],
    ) {
    }

    public function canRender(string $format): bool
    {
        return match ($format) {
            'webm' => $this->tools->canWebm(),
            'tgs', 'png' => true,
            default => false,
        };
    }

    /**
     * Builds the composed animation document.
     * @param array<string,mixed> $content
     * @return array{doc:array<string,mixed>,images:array<string,\GdImage>,warnings:list<string>,assigned:array<string,string>}
     */
    public function compose(TemplateSpec $spec, array $content, string $target, int $logoColors = 6, int $logoVertices = 1500): array
    {
        $doc = LottieDoc::load($spec->sourcePath());
        $problems = $spec->check($doc);
        if ($problems) {
            throw new TemplateException('این قالب نیاز به بازبینی مدیر دارد و فعلاً قابل استفاده نیست.');
        }
        $warnings = [];
        $recolor = $content['recolor'] ?? null;
        $mode = (string) ($content['recolor_mode'] ?? 'main');
        $slotColor = static fn (?string $hex): ?string => $hex;
        if ($mode === 'gray' || (is_string($recolor) && Color::isHex($recolor))) {
            $target = is_string($recolor) && Color::isHex($recolor) ? strtoupper($recolor) : '#808080';
            $rc = new TemplateRecolor();
            $original = $doc;
            $enabled = $spec->recolor['enabled'];
            // Default text colours come from the original brand artwork. Once the
            // buyer picks a colour, a colourful default never keeps the original
            // brand hue: it follows the recolouring, or becomes the buyer's colour.
            $slotColor = static function (?string $hex) use ($rc, $original, $target, $mode, $spec, $enabled): ?string {
                $rgb = TemplateRecolor::hexToRgb($hex);
                if ($hex === null || $rgb === null || TemplateRecolor::toOklch($rgb)[1] < 0.05) {
                    return $hex; // black / white / gray captions stay as designed
                }
                if ($mode === 'gray') {
                    [$L] = TemplateRecolor::toOklch($rgb);
                    return TemplateRecolor::toHex(TemplateRecolor::fromOklch($L, 0.0, 0.0));
                }
                $mapped = $enabled ? $rc->mapHex($original, $hex, $target, $mode, $spec->recolor) : $hex;
                return strtoupper($mapped) === strtoupper($hex) ? $target : $mapped;
            };
            if ($enabled) {
                $doc = $rc->apply($doc, $target, $mode, $spec->recolor);
            }
        }
        $assign = $this->assign($spec, $content);
        $builder = new TemplateContent($this->fonts);
        $images = [];
        $insertions = [];
        $trace = null;
        $logoImg = null;
        foreach ($spec->slots as $slot) {
            $role = (string) $slot['role'];
            $what = $assign[$role] ?? null;
            if ($what === null) {
                continue;
            }
            $targets = [['host' => $slot['host'], 'index' => $slot['index'], 'box' => $slot['box']], ...$slot['also']];
            foreach ($targets as $t) {
                if ($what === 'logo') {
                    $logoImg ??= $this->loadLogo((string) $content['logo']);
                    if ($target === 'tgs') {
                        $trace ??= (new Vectorizer(220, $logoVertices))->trace($logoImg, $logoColors);
                        $group = $builder->vectorLogo($role, $trace, $t['box'], (float) $slot['pad']);
                    } else {
                        $images['logo'] = $logoImg;
                        $group = $builder->rasterLogo($role, 'logo', imagesx($logoImg), imagesy($logoImg), $t['box'], (float) $slot['pad']);
                    }
                } else {
                    $text = $what === 'caption' ? (string) $content['caption'] : (string) $content['text'];
                    $color = $role === 'caption'
                        ? ($content['caption_color'] ?? $slotColor($slot['color']) ?? $content['color'] ?? '#FFFFFF')
                        : ($content['color'] ?? $slotColor($slot['color']) ?? '#FFFFFF');
                    $res = $builder->text($role, $text, $t['box'], [
                        'font' => $content['font'] ?? null,
                        'color' => $color,
                        'outline' => $content['outline'] ?? null,
                        'outline_width' => (float) ($content['outline_width'] ?? 0.0),
                        'size' => (float) ($content['size'] ?? 1.0),
                        'letter_spacing' => (float) ($content['letter_spacing'] ?? 0.0),
                    ], (float) $slot['pad']);
                    if ($res['missing']) {
                        $warnings[] = 'بعضی از حروف در فونت انتخابی نبودند و نمایش داده نمی‌شوند.';
                    }
                    $group = $res['group'];
                }
                $insertions[] = ['host' => (string) $t['host'], 'index' => (int) $t['index'], 'item' => $group];
            }
        }
        $doc = (new TemplateComposer())->apply($doc, $spec->remove, $insertions);
        $doc['tgs'] = 1;
        return ['doc' => $doc, 'images' => $images, 'warnings' => array_values(array_unique($warnings)), 'assigned' => $assign];
    }

    /**
     * Decides what goes into each slot.
     * @param array<string,mixed> $content
     * @return array<string,string> role => logo|text|caption
     */
    public function assign(TemplateSpec $spec, array $content): array
    {
        $hasLogo = is_string($content['logo'] ?? null) && $content['logo'] !== '' && is_file((string) $content['logo']);
        $text = trim((string) ($content['text'] ?? ''));
        $caption = trim((string) ($content['caption'] ?? ''));
        $out = [];
        $main = $spec->slot('main');
        if ($main !== null) {
            if ($hasLogo && in_array('logo', $main['accepts'], true)) {
                $out['main'] = 'logo';
            } elseif ($text !== '' && in_array('text', $main['accepts'], true)) {
                $out['main'] = 'text';
            }
        }
        $cap = $spec->slot('caption');
        if ($cap !== null) {
            if ($caption !== '') {
                $out['caption'] = 'caption';
            } elseif (($out['main'] ?? null) === 'logo' && $text !== '') {
                $out['caption'] = 'text';
            }
        }
        if (!$out) {
            throw new TemplateException('برای این قالب لوگو یا متن لازم است.');
        }
        return $out;
    }

    private function loadLogo(string $file): \GdImage
    {
        $img = ImageProcessor::load($file);
        $img = ImageProcessor::trim($img, 120);
        return ImageProcessor::resizeToFit($img, 512);
    }

    /**
     * @param array<string,mixed> $content
     * @param array{preview?:bool|'thumb',grid_dir?:string|null,grid_fps?:int,name?:string,preview_size?:int} $opts
     * @return array<string,mixed>
     */
    public function render(TemplateSpec $spec, array $content, string $format, string $workDir, array $opts = []): array
    {
        if (!in_array($format, ['webm', 'tgs', 'png'], true)) {
            throw new TemplateException('فرمت خروجی نامعتبر است.');
        }
        if (!$this->canRender($format)) {
            throw new TemplateException('ساخت WEBM روی این سرور ممکن نیست (FFmpeg با کدک VP9 نصب نیست). فرمت TGS یا PNG را انتخاب کنید.');
        }
        if (!is_dir($workDir) && !@mkdir($workDir, 0750, true)) {
            throw new TemplateException('Work directory not writable');
        }
        $name = $opts['name'] ?? $spec->slug;
        $result = ['format' => $format, 'tiles' => [], 'warnings' => [], 'tile_count' => 1, 'template' => $spec->slug];
        if ($format === 'tgs') {
            [$tile, $composed] = $this->renderTgs($spec, $content, $workDir . '/' . $name . '.tgs');
            $result['tiles'][] = $tile;
        } else {
            $composed = $this->compose($spec, $content, 'webm');
            $player = $this->player($composed);
            if ($format === 'webm') {
                $result['tiles'][] = $this->renderWebm($player, $workDir, $name);
                $framesDir = $workDir . '/frames_' . $name;
            } else {
                $result['tiles'][] = $this->renderPng($spec, $player, $workDir . '/' . $name . '.png');
            }
        }
        $result['warnings'] = $composed['warnings'];
        $doc = $composed['doc'];
        $fr = (float) ($doc['fr'] ?? 60);
        $result['fps'] = $format === 'tgs' ? $fr : min(30, (int) round($fr));
        $result['duration'] = round(((float) $doc['op'] - (float) $doc['ip']) / max(1.0, $fr), 3);
        $result['design_hash'] = sha1($spec->slug . '|' . json_encode($content));
        if (($opts['preview'] ?? true) || !empty($opts['grid_dir'])) {
            // previews always show the raster logo (exact colours), like the WEBM
            $previewDoc = $format === 'tgs' ? $this->compose($spec, $content, 'webm') : $composed;
            $player = $this->player($previewDoc);
            if ($opts['preview'] ?? true) {
                // 'thumb' = only the still (batch generation: the grid shows the motion)
                $result += $this->preview($spec, $player, $workDir, $name, (int) ($opts['preview_size'] ?? $this->options['preview_size'] ?? 256), ($opts['preview'] ?? true) === 'thumb');
            }
            if (!empty($opts['grid_dir'])) {
                $gridDir = (string) $opts['grid_dir'];
                $gridFps = (int) ($opts['grid_fps'] ?? 15);
                if (!isset($framesDir) || !$this->gridFromFrames($player, $framesDir, $gridDir, $gridFps)) {
                    $this->gridFrames($player, $gridDir, $gridFps);
                }
            }
        }
        return $result;
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} [tile, composed] */
    private function renderTgs(TemplateSpec $spec, array $content, string $out): array
    {
        $max = (int) $this->formats['animated']['max_bytes'];
        $colors = (int) ($this->options['vector_max_colors'] ?? 6);
        $attempts = [[$colors, 1500, false], [$colors, 1500, true], [min(4, $colors), 1000, true], [3, 700, true], [2, 500, true]];
        $bin = '';
        $composed = null;
        $hasLogo = is_string($content['logo'] ?? null) && $content['logo'] !== '';
        foreach ($attempts as $i => [$c, $vertices, $round]) {
            if ($i > 1 && !$hasLogo) {
                break; // only a traced logo can be simplified further
            }
            $composed = $this->compose($spec, $content, 'tgs', $c, $vertices);
            $doc = LottieDoc::stripMetadata($composed['doc']);
            if ($round) {
                $doc = LottieDoc::roundFloats($doc, 3);
            }
            $bin = LottieDoc::encodeTgs($doc);
            if (strlen($bin) <= $max) {
                break;
            }
        }
        if (strlen($bin) > $max) {
            throw new TemplateException(sprintf(
                'فایل TGS این قالب با محتوای شما %s کیلوبایت می‌شود (حداکثر %d). لوگوی ساده‌تر، متن کوتاه‌تر یا فرمت WEBM را انتخاب کنید.',
                round(strlen($bin) / 1024, 1),
                intdiv($max, 1024)
            ));
        }
        file_put_contents($out, $bin);
        $report = (new TgsValidator($this->formats['animated'], false))->validate($out);
        return [[
            'path' => $out, 'width' => 512, 'height' => 512, 'frames' => (int) ($report->info['frames'] ?? 0),
            'bytes' => strlen($bin), 'validation' => $report->toArray(), 'status' => $report->status(),
        ], $composed];
    }

    private function player(array $composed): LottiePlayer
    {
        $player = new LottiePlayer($composed['doc']);
        foreach ($composed['images'] as $ref => $img) {
            $player->addImage($ref, $img);
        }
        return $player;
    }

    /** @return list<float> source frame times sampled at <= $fps */
    public static function frameTimes(LottiePlayer $player, int $fps, float $maxSeconds = 3.0): array
    {
        $fr = max(1.0, $player->fps());
        $fps = max(1, min($fps, (int) round($fr)));
        $step = $fr / $fps;
        $ip = $player->inPoint();
        $op = $player->outPoint();
        $n = (int) floor(($op - $ip) / $step + 1e-6);
        $n = max(1, min($n, (int) floor($maxSeconds * $fps + 1e-6)));
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $out[] = $ip + $k * $step;
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function renderWebm(LottiePlayer $player, string $workDir, string $name): array
    {
        $size = (int) $this->formats['video']['emoji_size'];
        $fps = min(30, (int) $this->formats['video']['max_fps']);
        $times = self::frameTimes($player, $fps, (float) $this->formats['video']['max_duration']);
        $fps = max(1, min($fps, (int) round($player->fps()))); // same rate frameTimes() sampled at
        $dir = $workDir . '/frames_' . $name;
        @mkdir($dir, 0750, true);
        foreach ($times as $i => $t) {
            $img = $player->render($t, $size, $size);
            imagesavealpha($img, true);
            imagepng($img, sprintf('%s/f%04d.png', $dir, $i), 1);
            imagedestroy($img);
        }
        $out = $workDir . '/' . $name . '.webm';
        $enc = (new WebmEncoder($this->tools, $this->tools->runner()))
            ->encode($dir . '/f%04d.png', count($times), $fps, $out, (int) $this->formats['video']['max_bytes'], (int) ($this->options['webm_crf'] ?? 30));
        $report = (new WebmValidator($this->formats['video'], $this->tools))->validate($out, 'emoji');
        return [
            'path' => $out, 'width' => $size, 'height' => $size, 'frames' => count($times), 'bytes' => (int) filesize($out),
            'crf' => $enc['crf'], 'validation' => $report->toArray(), 'status' => $report->status(),
        ];
    }

    /** Frame that shows the slot content best (stored by the detector, else the middle). */
    public static function stillFrame(TemplateSpec $spec, LottiePlayer $player): float
    {
        $still = $spec->meta['still'] ?? null;
        if (is_numeric($still) && (float) $still >= $player->inPoint() && (float) $still < $player->outPoint()) {
            return (float) $still;
        }
        return floor(($player->inPoint() + $player->outPoint()) / 2);
    }

    /** @return array<string,mixed> */
    private function renderPng(TemplateSpec $spec, LottiePlayer $player, string $out): array
    {
        $size = (int) $this->formats['static']['emoji_size'];
        $img = $player->render(self::stillFrame($spec, $player), $size, $size);
        imagesavealpha($img, true);
        imagepng($img, $out, 9);
        $report = (new StaticValidator($this->formats['static']))->validate($out, 'emoji');
        return ['path' => $out, 'width' => $size, 'height' => $size, 'frames' => 1, 'bytes' => (int) filesize($out), 'validation' => $report->toArray(), 'status' => $report->status()];
    }

    /** @return array{preview:?string,preview_format:?string,thumb:string} */
    private function preview(TemplateSpec $spec, LottiePlayer $player, string $workDir, string $name, int $size, bool $thumbOnly = false): array
    {
        $size -= $size % 2;
        $bg = Color::hexToRgb((string) ($this->options['preview_bg'] ?? '#1C2733'));
        $thumb = $workDir . '/' . $name . '_thumb.png';
        imagepng($player->render(self::stillFrame($spec, $player), $size, $size, $bg), $thumb, 6);
        if ($thumbOnly) {
            return ['preview' => null, 'preview_format' => null, 'thumb' => $thumb];
        }
        $encoder = new PreviewEncoder($this->tools, $this->tools->runner());
        $fps = 30;
        $times = self::frameTimes($player, $fps, 3.0);
        if ($encoder->supportsMp4()) {
            $dir = $workDir . '/preview_' . $name;
            @mkdir($dir, 0750, true);
            foreach ($times as $i => $t) {
                imagepng($player->render($t, $size, $size, $bg), sprintf('%s/p%04d.png', $dir, $i), 1);
            }
            $out = $workDir . '/' . $name . '_preview.mp4';
            try {
                $encoder->mp4($dir . '/p%04d.png', count($times), $fps, $out, 1);
                return ['preview' => $out, 'preview_format' => 'mp4', 'thumb' => $thumb];
            } catch (EncodingException) {
                // GIF fallback below
            }
        }
        $gifFps = 15;
        $gifTimes = self::frameTimes($player, $gifFps, 3.0);
        $gifSize = min($size, 200);
        $frames = [];
        foreach ($gifTimes as $t) {
            $frames[] = $player->render($t, $gifSize, $gifSize, $bg);
        }
        $out = $workDir . '/' . $name . '_preview.gif';
        $encoder->gif($frames, $gifFps, $out);
        return ['preview' => $out, 'preview_format' => 'gif', 'thumb' => $thumb];
    }

    /**
     * Grid frames from the frames the WEBM was encoded from (same times when
     * the WEBM rate is a multiple of the grid rate): composited on the
     * preview background instead of rendering every frame again.
     */
    private function gridFromFrames(LottiePlayer $player, string $framesDir, string $dir, int $gridFps): bool
    {
        $fr = (int) round($player->fps());
        $webmFps = max(1, min(min(30, (int) $this->formats['video']['max_fps']), $fr));
        $gridFps = max(1, min($gridFps, $fr));
        if ($webmFps % $gridFps !== 0) {
            return false;
        }
        $ratio = intdiv($webmFps, $gridFps);
        $count = count(self::frameTimes($player, $gridFps, 3.0));
        $bg = Color::hexToRgb((string) ($this->options['preview_bg'] ?? '#1C2733'));
        @mkdir($dir, 0750, true);
        for ($k = 0; $k < $count; $k++) {
            $src = @imagecreatefrompng(sprintf('%s/f%04d.png', $framesDir, $k * $ratio));
            if ($src === false) {
                return false;
            }
            $canvas = imagecreatetruecolor(100, 100);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]));
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, 100, 100, imagesx($src), imagesy($src));
            imagejpeg($canvas, sprintf('%s/g%03d.jpg', $dir, $k), 82);
            imagedestroy($src);
            imagedestroy($canvas);
        }
        return true;
    }

    /** 100 px JPEG frames for animated batch grids (GridComposer). */
    private function gridFrames(LottiePlayer $player, string $dir, int $gridFps): void
    {
        @mkdir($dir, 0750, true);
        $bg = Color::hexToRgb((string) ($this->options['preview_bg'] ?? '#1C2733'));
        foreach (self::frameTimes($player, max(1, $gridFps), 3.0) as $k => $t) {
            imagejpeg($player->render($t, 100, 100, $bg), sprintf('%s/g%03d.jpg', $dir, $k), 82);
        }
    }
}
