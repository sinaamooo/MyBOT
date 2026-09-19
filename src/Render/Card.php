<?php
declare(strict_types=1);

namespace Nikto\Render;

use DateTimeImmutable;
use Nikto\Core\Jalali;
use Nikto\Core\Settings;
use Nikto\Text\Persian;

/**
 * کلاس پایه‌ی کارت‌ها: تم، برند، تاریخ و کمک‌کننده‌های عددی.
 */
abstract class Card
{
    protected Theme $theme;
    protected string $footer;
    protected DateTimeImmutable $now;
    protected int $quality;

    public function __construct(?string $theme = null, array $options = [])
    {
        $this->theme  = new Theme($theme ?? 'mono');
        $this->footer = (string) ($options['footer'] ?? Settings::get('brand_link'));
        $this->now    = $options['now'] ?? Settings::now();
        $this->quality = Settings::get('quality') === 'normal' ? 1 : 2;
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

    /** ارقام متن و تاریخ */
    protected function tnum(string $text): string
    {
        return Settings::get('digits') === 'fa' ? Persian::faDigits($text) : Persian::enDigits($text);
    }

    /** ارقام داده (قیمت، درصد، مقادیر جدول) */
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
