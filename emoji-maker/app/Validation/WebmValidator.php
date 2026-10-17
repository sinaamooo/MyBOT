<?php

declare(strict_types=1);

namespace EmojiMaker\Validation;

use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Process\Tools;

/**
 * Validates video emoji / stickers against Telegram's requirements:
 * WEBM container, VP9 codec, no audio, exact size (100x100 for emoji),
 * duration <= 3 s, <= 30 FPS, size <= 256 KB, transparency (alpha) present.
 * Uses the built-in EBML parser and, when installed, ffprobe as a second opinion.
 */
final class WebmValidator
{
    /** @param array<string,mixed> $limits telegram_formats.video */
    public function __construct(private array $limits, private ?Tools $tools = null)
    {
    }

    public function validate(string $file, string $kind = 'emoji'): ValidationReport
    {
        $r = new ValidationReport('webm', basename($file));
        if (!is_file($file)) {
            $r->error('فایل وجود ندارد.');
            return $r;
        }
        $size = (int) filesize($file);
        $r->info['bytes'] = $size;
        $maxBytes = (int) $this->limits['max_bytes'];
        if ($size > $maxBytes) {
            $r->error(sprintf('حجم فایل %s کیلوبایت است؛ حداکثر مجاز %d کیلوبایت.', round($size / 1024, 1), intdiv($maxBytes, 1024)));
        }
        $data = (string) file_get_contents($file);
        try {
            $info = (new EbmlReader($data))->parse();
        } catch (\Throwable) {
            $r->error('فایل WEBM معتبر نیست (ساختار EBML پیدا نشد). تغییر پسوند فایل کافی نیست.');
            return $r;
        }
        if ($info['doctype'] !== 'webm') {
            $r->error('نوع سند فایل «webm» نیست (DocType=' . ($info['doctype'] ?? '?') . ').');
        }
        $video = array_values(array_filter($info['tracks'], static fn ($t) => $t['type'] === 1));
        $audio = array_values(array_filter($info['tracks'], static fn ($t) => $t['type'] === 2));
        if (count($video) !== 1) {
            $r->error('فایل باید دقیقاً یک ترک ویدیویی داشته باشد.');
        }
        if ($audio) {
            $r->error('فایل نباید صدا داشته باشد.');
        }
        if ($video) {
            $v = $video[0];
            $r->info['codec'] = $v['codec'];
            $r->info['width'] = $v['width'];
            $r->info['height'] = $v['height'];
            $r->info['alpha_mode'] = $v['alpha_mode'];
            if ($v['codec'] !== 'V_VP9') {
                $r->error('کدک ویدیو باید VP9 باشد (کدک فعلی: ' . $v['codec'] . ').');
            }
            if ($kind === 'emoji') {
                $need = (int) $this->limits['emoji_size'];
                if ($v['width'] !== $need || $v['height'] !== $need) {
                    $r->error(sprintf('ابعاد ایموجی باید دقیقاً %d×%d باشد (فعلی: %d×%d).', $need, $need, $v['width'], $v['height']));
                }
            } else {
                $s = (int) $this->limits['sticker_size'];
                if (max($v['width'], $v['height']) !== $s || min($v['width'], $v['height']) > $s) {
                    $r->error(sprintf('برای استیکر یک ضلع باید دقیقاً %d پیکسل و دیگری حداکثر %d باشد.', $s, $s));
                }
            }
            if ($v['alpha_mode'] !== 1) {
                $r->warning('کانال شفافیت (Alpha) در فایل علامت‌گذاری نشده است؛ پس‌زمینه ممکن است شفاف نباشد.');
            }
        }
        $duration = $info['duration_seconds'];
        $frames = (int) $info['frames'];
        $r->info['frames'] = $frames;
        $fps = null;
        $tcs = $info['frame_timecodes'];
        if (count($tcs) >= 2) {
            sort($tcs);
            $span = (end($tcs) - $tcs[0]) * $info['timecode_scale'] / 1e9;
            if ($span > 0) {
                $fps = (count($tcs) - 1) / $span;
            }
            if ($duration === null) {
                $duration = $span + ($fps ? 1 / $fps : 0);
            }
        }
        if ($video && $video[0]['default_duration'] > 0) {
            $fps = 1e9 / $video[0]['default_duration'];
        }
        $r->info['duration'] = $duration !== null ? round($duration, 3) : null;
        $r->info['fps'] = $fps !== null ? round($fps, 2) : null;
        $maxDur = (float) $this->limits['max_duration'];
        if ($duration === null) {
            $r->warning('مدت ویدیو قابل تشخیص نبود.');
        } elseif ($duration > $maxDur + 0.001) {
            $r->error(sprintf('مدت انیمیشن %.2f ثانیه است؛ حداکثر %.0f ثانیه مجاز است.', $duration, $maxDur));
        }
        if ($fps !== null && $fps > (float) $this->limits['max_fps'] + 0.5) {
            $r->error(sprintf('نرخ فریم %.1f است؛ حداکثر %d فریم بر ثانیه مجاز است.', $fps, (int) $this->limits['max_fps']));
        }
        if ($frames < 1) {
            $r->error('هیچ فریم ویدیویی در فایل پیدا نشد.');
        }
        $this->probe($file, $r);
        return $r;
    }

    private function probe(string $file, ValidationReport $r): void
    {
        $ffprobe = $this->tools?->ffprobe();
        if ($ffprobe === null) {
            $r->info['ffprobe'] = 'unavailable';
            return;
        }
        try {
            $res = $this->tools->runner()->run([$ffprobe, '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $file], null, 30);
        } catch (\Throwable) {
            $r->info['ffprobe'] = 'failed';
            return;
        }
        $j = json_decode($res->stdout, true);
        if (!$res->ok() || !is_array($j)) {
            $r->error('ffprobe قادر به خواندن فایل نیست: فایل ویدیویی خراب است.');
            return;
        }
        $r->info['ffprobe'] = 'ok';
        $streams = $j['streams'] ?? [];
        foreach ($streams as $s) {
            if (($s['codec_type'] ?? '') === 'video' && ($s['codec_name'] ?? '') !== 'vp9') {
                $r->error('ffprobe: کدک ویدیو VP9 نیست.');
            }
            if (($s['codec_type'] ?? '') === 'audio') {
                $r->error('ffprobe: ترک صوتی پیدا شد.');
            }
        }
        $dur = (float) ($j['format']['duration'] ?? 0);
        if ($dur > 0) {
            $r->info['ffprobe_duration'] = round($dur, 3);
            if ($dur > (float) $this->limits['max_duration'] + 0.001) {
                $r->error(sprintf('ffprobe: مدت %.2f ثانیه بیشتر از حد مجاز است.', $dur));
            }
        }
    }
}
