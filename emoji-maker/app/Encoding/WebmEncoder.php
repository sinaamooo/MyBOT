<?php

declare(strict_types=1);

namespace EmojiMaker\Encoding;

use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Process\Tools;

/**
 * Encodes a PNG frame sequence (with alpha) into a Telegram compatible video
 * emoji: WEBM container, VP9 codec, yuva420p (transparency), no audio,
 * <= 30 FPS, <= 3 s. The quality (CRF) is lowered step by step until the
 * file fits the size limit (256 KB by default).
 */
final class WebmEncoder
{
    public function __construct(private Tools $tools, private ProcessRunner $runner)
    {
    }

    /**
     * @param string $pattern printf style frame pattern, e.g. /tmp/x/f%04d.png
     * @return array{crf:int,bytes:int,attempts:int}
     */
    public function encode(string $pattern, int $frames, int $fps, string $output, int $maxBytes, int $crf = 30): array
    {
        $ffmpeg = $this->tools->ffmpeg();
        if ($ffmpeg === null || !$this->tools->canWebm()) {
            throw new EncodingException('FFmpeg with libvpx-vp9 is not available - WEBM output cannot be produced on this server.');
        }
        $fps = max(1, min(30, $fps));
        $ladder = array_values(array_unique([$crf, $crf + 6, $crf + 12, 48, 55, 63]));
        $attempts = 0;
        foreach ($ladder as $i => $q) {
            $q = min(63, $q);
            $attempts++;
            $args = [
                $ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
                '-framerate', (string) $fps,
                '-start_number', '0',
                '-i', $pattern,
                '-frames:v', (string) $frames,
                '-c:v', 'libvpx-vp9',
                '-pix_fmt', 'yuva420p',
                '-b:v', $i >= 4 ? '120k' : '0',
                '-crf', (string) $q,
                '-deadline', 'good',
                '-cpu-used', '2',
                '-row-mt', '1',
                '-threads', '1',
                '-an',
                '-metadata', 'title=Telegram custom emoji',
                '-f', 'webm',
                $output,
            ];
            $res = $this->runner->run($args, null, 180);
            if (!$res->ok()) {
                throw new EncodingException('FFmpeg failed: ' . trim(substr($res->stderr, 0, 400)) . ($res->timedOut ? ' (timeout)' : ''));
            }
            clearstatcache(true, $output);
            $bytes = (int) filesize($output);
            if ($bytes > 0 && $bytes <= $maxBytes) {
                return ['crf' => $q, 'bytes' => $bytes, 'attempts' => $attempts];
            }
        }
        throw new EncodingException(sprintf('WEBM is still larger than %d KB at the lowest quality.', intdiv($maxBytes, 1024)));
    }
}
