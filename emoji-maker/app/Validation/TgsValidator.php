<?php

declare(strict_types=1);

namespace EmojiMaker\Validation;

/**
 * Validates animated (Lottie based) .TGS stickers / emoji:
 * gzip compressed Lottie JSON, "tgs":1, 512x512 canvas, 60 FPS, <= 3 s,
 * <= 64 KB, and none of the After Effects features Telegram forbids
 * (expressions, masks, layer effects, images, solids, texts, 3D layers,
 * merge paths, star shapes, gradient strokes, repeaters, time stretching,
 * time remapping, auto-oriented layers).
 */
final class TgsValidator
{
    private const FORBIDDEN_SHAPES = ['sr' => 'Star Shape', 'mm' => 'Merge Paths', 'rp' => 'Repeater', 'gs' => 'Gradient Stroke'];
    private const FORBIDDEN_LAYERS = [1 => 'Solid', 2 => 'Image', 5 => 'Text'];

    /** @param array<string,mixed> $limits telegram_formats.animated */
    public function __construct(private array $limits)
    {
    }

    public function validate(string $file): ValidationReport
    {
        $r = new ValidationReport('tgs', basename($file));
        if (!is_file($file)) {
            $r->error('فایل وجود ندارد.');
            return $r;
        }
        $raw = (string) file_get_contents($file);
        $size = strlen($raw);
        $r->info['bytes'] = $size;
        $max = (int) $this->limits['max_bytes'];
        if ($size > $max) {
            $r->error(sprintf('حجم فایل TGS %s کیلوبایت است؛ حداکثر %d کیلوبایت مجاز است.', round($size / 1024, 1), intdiv($max, 1024)));
        }
        if (!str_starts_with($raw, "\x1f\x8b")) {
            $r->error('فایل TGS باید با gzip فشرده شده باشد (تغییر پسوند فایل JSON کافی نیست).');
            return $r;
        }
        $json = @gzdecode($raw, 4 * 1024 * 1024);
        if ($json === false) {
            $r->error('فایل gzip خراب است.');
            return $r;
        }
        $r->info['json_bytes'] = strlen($json);
        $doc = json_decode($json, true);
        if (!is_array($doc)) {
            $r->error('محتوای فایل JSON معتبر Lottie نیست.');
            return $r;
        }
        $this->checkDocument($doc, $r);
        return $r;
    }

    /** @param array<string,mixed> $doc */
    public function checkDocument(array $doc, ValidationReport $r): void
    {
        if (($doc['tgs'] ?? null) !== 1) {
            $r->error('کلید "tgs": 1 در فایل وجود ندارد.');
        }
        $canvas = (int) $this->limits['canvas'];
        if ((int) ($doc['w'] ?? 0) !== $canvas || (int) ($doc['h'] ?? 0) !== $canvas) {
            $r->error(sprintf('اندازه بوم باید %d×%d باشد (فعلی: %s×%s).', $canvas, $canvas, $doc['w'] ?? '?', $doc['h'] ?? '?'));
        }
        $fr = (float) ($doc['fr'] ?? 0);
        if (abs($fr - (float) $this->limits['fps']) > 0.01) {
            $r->error(sprintf('نرخ فریم باید %d باشد (فعلی: %s).', (int) $this->limits['fps'], $doc['fr'] ?? '?'));
        }
        $ip = (float) ($doc['ip'] ?? 0);
        $op = (float) ($doc['op'] ?? 0);
        $duration = $fr > 0 ? ($op - $ip) / $fr : 0;
        $r->info['duration'] = round($duration, 3);
        $r->info['frames'] = (int) ($op - $ip);
        if ($duration <= 0) {
            $r->error('مدت انیمیشن صفر است.');
        } elseif ($duration > (float) $this->limits['max_duration'] + 0.0001) {
            $r->error(sprintf('مدت انیمیشن %.2f ثانیه است؛ حداکثر %.0f ثانیه.', $duration, $this->limits['max_duration']));
        }
        if ((int) ($doc['ddd'] ?? 0) !== 0) {
            $r->error('لایه‌های سه‌بعدی (3D) مجاز نیستند.');
        }
        foreach ((array) ($doc['assets'] ?? []) as $asset) {
            if (isset($asset['p']) || isset($asset['u'])) {
                $r->error('استفاده از تصویر (Image asset) در TGS مجاز نیست.');
            }
            foreach ((array) ($asset['layers'] ?? []) as $layer) {
                $this->checkLayer($layer, $r);
            }
        }
        $layers = (array) ($doc['layers'] ?? []);
        if (!$layers) {
            $r->error('فایل هیچ لایه‌ای ندارد.');
        }
        foreach ($layers as $layer) {
            $this->checkLayer($layer, $r);
        }
        $r->errors = array_values(array_unique($r->errors));
    }

    /** @param array<string,mixed> $layer */
    private function checkLayer(array $layer, ValidationReport $r): void
    {
        $ty = (int) ($layer['ty'] ?? -1);
        if (isset(self::FORBIDDEN_LAYERS[$ty])) {
            $r->error('لایه‌ی ' . self::FORBIDDEN_LAYERS[$ty] . ' در TGS مجاز نیست.');
        }
        if ((int) ($layer['ddd'] ?? 0) !== 0) {
            $r->error('لایه‌های سه‌بعدی (3D) مجاز نیستند.');
        }
        if (!empty($layer['ef'])) {
            $r->error('افکت لایه (Layer Effects) مجاز نیست.');
        }
        if (!empty($layer['hasMask']) || !empty($layer['masksProperties'])) {
            $r->error('ماسک (Masks) مجاز نیست.');
        }
        if (isset($layer['tm'])) {
            $r->error('Time Remapping مجاز نیست.');
        }
        if ((int) ($layer['ao'] ?? 0) !== 0) {
            $r->error('Auto-Orient مجاز نیست.');
        }
        if (isset($layer['sr']) && abs((float) $layer['sr'] - 1.0) > 1e-6) {
            $r->error('Time Stretching مجاز نیست.');
        }
        if ($this->hasExpression($layer)) {
            $r->error('Expressions مجاز نیست.');
        }
        foreach ((array) ($layer['shapes'] ?? []) as $shape) {
            $this->checkShape($shape, $r);
        }
    }

    /** @param array<string,mixed> $shape */
    private function checkShape(array $shape, ValidationReport $r): void
    {
        $ty = (string) ($shape['ty'] ?? '');
        if (isset(self::FORBIDDEN_SHAPES[$ty])) {
            $r->error(self::FORBIDDEN_SHAPES[$ty] . ' در TGS مجاز نیست.');
        }
        foreach ((array) ($shape['it'] ?? []) as $child) {
            if (is_array($child)) {
                $this->checkShape($child, $r);
            }
        }
    }

    /** Expressions are stored as "x" string keys on animatable properties. */
    private function hasExpression(mixed $node, int $depth = 0): bool
    {
        if (!is_array($node) || $depth > 40) {
            return false;
        }
        if (isset($node['x']) && is_string($node['x']) && (isset($node['k']) || isset($node['a']))) {
            return true;
        }
        foreach ($node as $v) {
            if (is_array($v) && $this->hasExpression($v, $depth + 1)) {
                return true;
            }
        }
        return false;
    }
}
