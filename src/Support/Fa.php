<?php

declare(strict_types=1);

namespace App\Support;

/** Persian formatting helpers. */
final class Fa
{
    private const DIGITS_FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const DIGITS_AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function digits(string $s): string
    {
        return str_replace(range(0, 9), self::DIGITS_FA, $s);
    }

    public static function latinDigits(string $s): string
    {
        $latin = array_map('strval', range(0, 9));
        return str_replace(self::DIGITS_AR, $latin, str_replace(self::DIGITS_FA, $latin, $s));
    }

    public static function tf(string $tf): string
    {
        return match ($tf) {
            '1m' => '۱ دقیقه',
            '5m' => '۵ دقیقه',
            '15m' => '۱۵ دقیقه',
            '30m' => '۳۰ دقیقه',
            '1h' => '۱ ساعته',
            '2h' => '۲ ساعته',
            '4h' => '۴ ساعته',
            '6h' => '۶ ساعته',
            '12h' => '۱۲ ساعته',
            '1d' => 'روزانه',
            '1w' => 'هفتگی',
            default => $tf,
        };
    }

    public static function trend(string $trend): string
    {
        return match ($trend) {
            'bullish' => 'صعودی',
            'bearish' => 'نزولی',
            default => 'رنج',
        };
    }

    /** Price with precision that adapts to the magnitude (about 4 significant digits minimum). */
    public static function price(float $p): string
    {
        if ($p <= 0) {
            return '0';
        }
        $dec = max(2, 3 - (int) floor(log10($p)));
        $dec = min($dec, 10);
        return number_format($p, $dec, '.', ',');
    }

    public static function pct(float $v, int $dec = 2): string
    {
        return ($v >= 0 ? '+' : '') . number_format($v, $dec) . '%';
    }

    public static function compact(float $v): string
    {
        $abs = abs($v);
        return match (true) {
            $abs >= 1e9 => number_format($v / 1e9, 2) . 'B',
            $abs >= 1e6 => number_format($v / 1e6, 2) . 'M',
            $abs >= 1e3 => number_format($v / 1e3, 1) . 'K',
            default => number_format($v, 0),
        };
    }

    /** @return array{0:int,1:int,2:int} Jalali year, month, day */
    public static function jalali(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    public static function jalaliDate(?int $ts = null, bool $withTime = true): string
    {
        $ts ??= time();
        [$y, $m, $d] = self::jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $s = $d . ' ' . $months[$m - 1] . ' ' . $y;
        if ($withTime) {
            $s .= ' - ' . date('H:i', $ts);
        }
        return self::digits($s);
    }

    public static function weekday(int $w): string
    {
        return ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'][$w];
    }
}
