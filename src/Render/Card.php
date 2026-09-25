<?php
declare(strict_types=1);

namespace Nikto\Render;

use DateTimeImmutable;
use Nikto\Core\Jalali;
use Nikto\Core\Settings;
use Nikto\Text\Persian;

abstract class Card
{
    protected Theme $theme;
    protected string $footer;
    protected DateTimeImmutable $now;
    protected int $quality;
    protected float $outputScale;

    public function __construct(?string $theme = null, array $options = [])
    {
        $this->theme  = new Theme($theme);
        $this->footer = (string) ($options['footer'] ?? Settings::get('brand_link'));
        $this->now    = $options['now'] ?? Settings::now();
        [$this->quality, $this->outputScale] = self::qualityProfile();
    }

    protected static function qualityProfile(): array
    {
        $profiles = [
            'normal' => [2, 1.5],
            'high'   => [3, 2.5],
            'ultra'  => [4, 3.0],
        ];
        $key = Settings::get('quality');
        [$scale, $output] = $profiles[$key] ?? $profiles['high'];

        $limit = self::memoryLimitBytes();
        if ($limit > 0) {
            while ($scale > 2 && (1200 * $scale) * (900 * $scale) * 5 > $limit) {
                $scale--;
            }
        }

        return [$scale, $output];
    }

    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return 0;
        }
        $unit = strtolower(substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    abstract public function render(): Canvas;

    public function save(?string $path = null): string
    {
        $path ??= APP_STORAGE . '/cards/' . static::slug() . '-' . $this->now->format('Ymd-His') . '.png';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $this->render()->savePng($path);
    }

    public static function slug(): string
    {
        $parts = explode('\\', static::class);

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', end($parts)) ?? 'card');
    }

    protected function tnum(string $text): string
    {
        return Settings::get('digits') === 'fa' ? Persian::faDigits($text) : Persian::enDigits($text);
    }

    protected function dnum(string $text): string
    {
        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : Persian::enDigits($text);
    }

    protected function jalaliDate(string $pattern = 'l j F Y'): string
    {
        return $this->tnum(Jalali::format($this->now, $pattern));
    }

    protected function clockText(): string
    {
        return $this->tnum($this->now->format('H:i'));
    }

    protected function dateLine(): string
    {
        return $this->jalaliDate('l j F Y') . ' — ساعت ' . $this->clockText();
    }
}
