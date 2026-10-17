<?php

declare(strict_types=1);

namespace EmojiMaker\Process;

/**
 * Detects external tools and their capabilities (cached in storage/cache).
 *  - ffmpeg with libvpx-vp9  -> WEBM video emoji
 *  - ffmpeg with libx264     -> MP4 previews (otherwise GIF)
 *  - ffprobe                 -> deep validation of WEBM files
 */
final class Tools
{
    /** @var array<string,mixed>|null */
    private ?array $caps = null;

    public function __construct(
        private ProcessRunner $runner,
        private string $cacheFile,
        private string $ffmpegHint = '',
        private string $ffprobeHint = '',
    ) {
    }

    /** @return array{proc_open:bool,ffmpeg:?string,ffprobe:?string,ffmpeg_version:?string,vp9:bool,x264:bool,webp:bool,checked_at:int} */
    public function capabilities(bool $refresh = false): array
    {
        if ($this->caps !== null && !$refresh) {
            return $this->caps;
        }
        if (!$refresh && is_file($this->cacheFile) && filemtime($this->cacheFile) > time() - 3600) {
            $cached = json_decode((string) file_get_contents($this->cacheFile), true);
            if (is_array($cached) && ($cached['hints'] ?? '') === $this->ffmpegHint . '|' . $this->ffprobeHint) {
                return $this->caps = $cached;
            }
        }
        $caps = [
            'proc_open' => ProcessRunner::available(),
            'ffmpeg' => null,
            'ffprobe' => null,
            'ffmpeg_version' => null,
            'vp9' => false,
            'x264' => false,
            'webp' => false,
            'checked_at' => time(),
            'hints' => $this->ffmpegHint . '|' . $this->ffprobeHint,
        ];
        if ($caps['proc_open']) {
            $ffmpeg = ProcessRunner::which($this->ffmpegHint !== '' ? $this->ffmpegHint : 'ffmpeg');
            if ($ffmpeg !== null) {
                try {
                    $v = $this->runner->run([$ffmpeg, '-hide_banner', '-version'], null, 20);
                    if ($v->ok() && preg_match('/ffmpeg version (\S+)/', $v->stdout, $m)) {
                        $caps['ffmpeg'] = $ffmpeg;
                        $caps['ffmpeg_version'] = $m[1];
                        $enc = $this->runner->run([$ffmpeg, '-hide_banner', '-encoders'], null, 20)->stdout;
                        $caps['vp9'] = str_contains($enc, 'libvpx-vp9');
                        $caps['x264'] = str_contains($enc, 'libx264');
                        $caps['webp'] = str_contains($enc, 'libwebp');
                    }
                } catch (ProcessException) {
                    // not runnable
                }
            }
            $ffprobe = ProcessRunner::which($this->ffprobeHint !== '' ? $this->ffprobeHint : 'ffprobe');
            if ($ffprobe !== null) {
                try {
                    if ($this->runner->run([$ffprobe, '-hide_banner', '-version'], null, 20)->ok()) {
                        $caps['ffprobe'] = $ffprobe;
                    }
                } catch (ProcessException) {
                }
            }
        }
        @file_put_contents($this->cacheFile, json_encode($caps));
        return $this->caps = $caps;
    }

    public function canWebm(): bool
    {
        $c = $this->capabilities();
        return $c['ffmpeg'] !== null && $c['vp9'];
    }

    public function canMp4(): bool
    {
        $c = $this->capabilities();
        return $c['ffmpeg'] !== null && $c['x264'];
    }

    public function ffmpeg(): ?string
    {
        return $this->capabilities()['ffmpeg'];
    }

    public function ffprobe(): ?string
    {
        return $this->capabilities()['ffprobe'];
    }

    public function runner(): ProcessRunner
    {
        return $this->runner;
    }
}
