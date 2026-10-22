<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Animation\Timeline;
use EmojiMaker\Encoding\EncodingException;
use EmojiMaker\Encoding\PreviewEncoder;
use EmojiMaker\Encoding\WebmEncoder;
use EmojiMaker\Process\Tools;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Validation\StaticValidator;
use EmojiMaker\Validation\TgsValidator;
use EmojiMaker\Validation\WebmValidator;
use EmojiMaker\Vector\LottieWriter;

/**
 * High level renderer: one design configuration -> Telegram ready files.
 *
 * Output per tile (a banner has several tiles, a normal emoji one):
 *   main      .webm (VP9 + alpha, 100x100) | .tgs (Lottie 512, 60 FPS) | .png (100x100)
 *   validation report against Telegram's limits
 * Plus for the whole design: preview animation (.mp4 or .gif), thumbnail (.png)
 * and optional small frames for animated contact sheets (grids).
 */
final class EmojiRenderer
{
    /**
     * @param array<string,mixed> $formats config telegram_formats
     * @param array<string,mixed> $options webm_fps, webm_crf, work_res, preview_size, preview_bg, vector_max_colors
     */
    public function __construct(
        private FontRegistry $fonts,
        private EffectRegistry $effects,
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
     * @param array<string,mixed> $config project/variant configuration
     * @param array{preview?:bool,grid_dir?:string|null,grid_fps?:int,name?:string} $opts
     * @return array<string,mixed>
     */
    public function render(array $config, string $format, string $workDir, array $opts = []): array
    {
        if (!in_array($format, DesignSpec::FORMATS, true)) {
            throw new RenderException('فرمت خروجی نامعتبر است.');
        }
        if (!$this->canRender($format)) {
            throw new RenderException('ساخت WEBM روی این سرور ممکن نیست (FFmpeg با کدک VP9 نصب نیست). فرمت TGS یا PNG را انتخاب کنید.');
        }
        if (!is_dir($workDir) && !@mkdir($workDir, 0750, true)) {
            throw new RenderException('Work directory not writable');
        }
        $maxDuration = (float) $this->formats[$format === 'tgs' ? 'animated' : 'video']['max_duration'];
        $design = DesignSpec::fromArray($config, $this->fonts);
        if ($design->animation->duration > $maxDuration) {
            $design->animation->duration = $maxDuration;
        }
        if ($format === 'tgs' && $design->isBanner()) {
            throw new RenderException('بنر چندتکه فقط با فرمت WEBM (ویدیویی) ساخته می‌شود؛ در TGS اشکال نباید از بوم خارج شوند.');
        }
        $mode = $format === 'tgs' ? 'vector' : 'raster';
        $builder = new SceneBuilder($this->fonts, $this->effects, (int) ($this->options['vector_max_colors'] ?? 6));
        $scene = $builder->build($design, $mode);
        $fps = $format === 'tgs' ? 30 : max(10, min(30, (int) ($this->options['webm_fps'] ?? 30)));
        $timeline = new Timeline($design->animation, $this->effects, $scene->groupBounds, $scene->elementBounds(), $scene->units(), $scene->rtl, $mode, crc32($design->hash()));
        $renderer = new RasterRenderer($scene, $timeline, $this->effects, (int) ($this->options['work_res'] ?? 400));
        $renderer->prepare($fps);
        $frames = $design->animation->isStatic() ? 1 : $timeline->frameCount($fps);
        $name = $opts['name'] ?? 'emoji';
        $tiles = $scene->tiles;
        $result = [
            'format' => $format,
            'tiles' => [],
            'warnings' => $scene->warnings,
            'fps' => $format === 'tgs' ? LottieWriter::FPS : $fps,
            'duration' => $design->animation->isStatic() ? 0.0 : $design->animation->duration,
            'tile_count' => $tiles,
            'design_hash' => $design->hash(),
        ];

        if ($format === 'webm') {
            $size = (int) $this->formats['video']['emoji_size'];
            $dirs = [];
            for ($t = 0; $t < $tiles; $t++) {
                $dirs[$t] = $workDir . '/t' . $t;
                @mkdir($dirs[$t], 0750, true);
            }
            $n = max(1, $frames);
            if ($n === 1) {
                $n = (int) round(1.0 * $fps); // static design: 1 s still video
            }
            for ($i = 0; $i < $n; $i++) {
                $frame = $renderer->renderFrame($frames > 1 ? $i : 0, max(1, $frames), $size);
                foreach (RasterRenderer::sliceTiles($frame, $tiles) as $t => $tileImg) {
                    imagepng($tileImg, sprintf('%s/f%04d.png', $dirs[$t], $i), 1);
                }
            }
            $encoder = new WebmEncoder($this->tools, $this->tools->runner());
            $validator = new WebmValidator($this->formats['video'], $this->tools);
            for ($t = 0; $t < $tiles; $t++) {
                $out = sprintf('%s/%s%s.webm', $workDir, $name, $tiles > 1 ? '_' . ($t + 1) : '');
                $enc = $encoder->encode($dirs[$t] . '/f%04d.png', $n, $fps, $out, (int) $this->formats['video']['max_bytes'], (int) ($this->options['webm_crf'] ?? 30));
                $report = $validator->validate($out, 'emoji');
                $result['tiles'][] = [
                    'path' => $out, 'width' => $size, 'height' => $size, 'frames' => $n, 'bytes' => (int) filesize($out),
                    'crf' => $enc['crf'], 'validation' => $report->toArray(), 'status' => $report->status(),
                ];
            }
        } elseif ($format === 'tgs') {
            $result['tiles'][] = $this->renderTgs($design, $scene, $timeline, $builder, $workDir . '/' . $name . '.tgs', $renderer);
        } else {
            $size = (int) $this->formats['static']['emoji_size'];
            $frame = $renderer->renderFrame(0, max(1, $frames), $size);
            $validator = new StaticValidator($this->formats['static']);
            foreach (RasterRenderer::sliceTiles($frame, $tiles) as $t => $tileImg) {
                $out = sprintf('%s/%s%s.png', $workDir, $name, $tiles > 1 ? '_' . ($t + 1) : '');
                imagesavealpha($tileImg, true);
                imagepng($tileImg, $out, 9);
                $report = $validator->validate($out, 'emoji');
                $result['tiles'][] = ['path' => $out, 'width' => $size, 'height' => $size, 'frames' => 1, 'bytes' => (int) filesize($out), 'validation' => $report->toArray(), 'status' => $report->status()];
            }
        }

        if ($opts['preview'] ?? true) {
            // 'thumb' = only the still (batch generation: the grid shows the motion)
            $result += $this->preview($renderer, $frames, $fps, $workDir, $name, ($opts['preview'] ?? true) === 'thumb');
        }
        if (!empty($opts['grid_dir'])) {
            $this->gridFrames($renderer, $frames, $fps, (string) $opts['grid_dir'], (int) ($opts['grid_fps'] ?? 15), $tiles);
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private function renderTgs(DesignSpec $design, Scene $scene, Timeline $timeline, SceneBuilder $builder, string $out, RasterRenderer $renderer): array
    {
        $writer = new LottieWriter($this->effects);
        $validator = new TgsValidator($this->formats['animated']);
        $max = (int) $this->formats['animated']['max_bytes'];
        $attempts = [
            ['keyTolerance' => 0.6, 'glowRings' => 3, 'shadow' => true],
            ['keyTolerance' => 1.5, 'glowRings' => 2, 'shadow' => true],
            ['keyTolerance' => 3.0, 'glowRings' => 1, 'shadow' => false],
        ];
        $bin = '';
        foreach ($attempts as $opts) {
            $bin = LottieWriter::encode($writer->build($scene, $timeline, $opts));
            if (strlen($bin) <= $max) {
                break;
            }
        }
        if (strlen($bin) > $max && $design->hasImage()) {
            // simplify the traced logo
            foreach ([4, 3, 2] as $colors) {
                $b2 = new SceneBuilder($this->fonts, $this->effects, $colors);
                $scene2 = $b2->build($design, 'vector');
                $scene2->fit = $scene->fit;
                $bin = LottieWriter::encode($writer->build($scene2, $timeline, end($attempts)));
                if (strlen($bin) <= $max) {
                    break;
                }
            }
        }
        file_put_contents($out, $bin);
        $report = $validator->validate($out);
        return [
            'path' => $out, 'width' => 512, 'height' => 512, 'frames' => (int) ($report->info['frames'] ?? 0),
            'bytes' => strlen($bin), 'validation' => $report->toArray(), 'status' => $report->status(),
        ];
    }

    /** @return array{preview:?string,preview_format:?string,thumb:string} */
    private function preview(RasterRenderer $renderer, int $frames, int $fps, string $workDir, string $name, bool $thumbOnly = false): array
    {
        $size = (int) ($this->options['preview_size'] ?? 256);
        $bg = Color::hexToRgb((string) ($this->options['preview_bg'] ?? '#1C2733'));
        $tiles = $renderer->scene()->tiles;
        $h = $tiles > 1 ? max(96, min($size, intdiv(720, $tiles))) : $size;
        $h -= $h % 2;
        $thumbImg = $renderer->renderFrame(0, max(1, $frames), $h, $bg);
        $thumb = $workDir . '/' . $name . '_thumb.png';
        imagepng($thumbImg, $thumb, 6);
        if ($frames <= 1 || $thumbOnly) {
            return ['preview' => null, 'preview_format' => null, 'thumb' => $thumb];
        }
        $previewFps = min($fps, 30);
        $encoder = new PreviewEncoder($this->tools, $this->tools->runner());
        if ($encoder->supportsMp4()) {
            $dir = $workDir . '/preview';
            @mkdir($dir, 0750, true);
            for ($i = 0; $i < $frames; $i++) {
                imagepng($renderer->renderFrame($i, $frames, $h, $bg), sprintf('%s/p%04d.png', $dir, $i), 1);
            }
            $out = $workDir . '/' . $name . '_preview.mp4';
            try {
                $encoder->mp4($dir . '/p%04d.png', $frames, $previewFps, $out, $frames / $previewFps < 2 ? 2 : 1);
                return ['preview' => $out, 'preview_format' => 'mp4', 'thumb' => $thumb];
            } catch (EncodingException) {
                // fall through to GIF
            }
        }
        $gifFrames = [];
        $step = max(1, (int) round($frames / 40));
        $gifH = min($h, 200);
        for ($i = 0; $i < $frames; $i += $step) {
            $gifFrames[] = $renderer->renderFrame($i, $frames, $gifH, $bg);
        }
        $out = $workDir . '/' . $name . '_preview.gif';
        $encoder->gif($gifFrames, (int) max(1, round($previewFps / $step)), $out);
        return ['preview' => $out, 'preview_format' => 'gif', 'thumb' => $thumb];
    }

    /** Small frames (100 px, checker-free dark bg) for animated batch grids. */
    private function gridFrames(RasterRenderer $renderer, int $frames, int $fps, string $dir, int $gridFps, int $tiles): void
    {
        @mkdir($dir, 0750, true);
        $bg = Color::hexToRgb((string) ($this->options['preview_bg'] ?? '#1C2733'));
        $count = $frames <= 1 ? 1 : max(1, (int) round($frames * $gridFps / $fps));
        for ($k = 0; $k < $count; $k++) {
            $i = $frames <= 1 ? 0 : (int) floor($k * $frames / $count);
            $img = $renderer->renderFrame($i, max(1, $frames), 100, $bg);
            if ($tiles > 1) {
                // keep the banner readable inside one grid cell
                $cell = ImageProcessor::canvas(100, 100);
                imagefilledrectangle($cell, 0, 0, 99, 99, imagecolorallocate($cell, $bg[0], $bg[1], $bg[2]));
                $w = imagesx($img);
                $hh = (int) round(100 * 100 / $w);
                imagecopyresampled($cell, $img, 0, (int) ((100 - $hh) / 2), 0, 0, 100, $hh, $w, 100);
                $img = $cell;
            }
            imagejpeg($img, sprintf('%s/g%03d.jpg', $dir, $k), 82);
        }
    }
}
