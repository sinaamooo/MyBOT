<?php

declare(strict_types=1);

namespace EmojiMaker\Encoding;

use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Process\Tools;

/**
 * Preview animations that Telegram plays inline (sendAnimation):
 *  - MP4 / H.264 when FFmpeg with libx264 exists
 *  - animated GIF otherwise (pure PHP)
 */
final class PreviewEncoder
{
    public function __construct(private Tools $tools, private ProcessRunner $runner)
    {
    }

    public function supportsMp4(): bool
    {
        return $this->tools->canMp4();
    }

    /** Encodes opaque PNG frames to MP4. */
    public function mp4(string $pattern, int $frames, int $fps, string $output, int $loops = 1): void
    {
        $ffmpeg = $this->tools->ffmpeg();
        if ($ffmpeg === null || !$this->tools->canMp4()) {
            throw new EncodingException('libx264 is not available');
        }
        $args = [$ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y'];
        if ($loops > 1) {
            array_push($args, '-stream_loop', (string) ($loops - 1));
        }
        array_push($args,
            '-framerate', (string) $fps, '-start_number', '0', '-i', $pattern,
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '22',
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', '-movflags', '+faststart', '-an', '-threads', '1',
            $output
        );
        $res = $this->runner->run($args, null, 180);
        if (!$res->ok() || !is_file($output) || filesize($output) === 0) {
            throw new EncodingException('MP4 preview failed: ' . trim(substr($res->stderr, 0, 300)));
        }
    }

    /** @param list<\GdImage> $frames */
    public function gif(array $frames, int $fps, string $output): void
    {
        $delay = (int) max(2, round(100 / max(1, $fps)));
        file_put_contents($output, GifEncoder::encode($frames, $delay));
    }
}
