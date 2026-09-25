<?php
declare(strict_types=1);

namespace Nikto\Core;

final class Jalali
{
    public const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public const WEEKDAYS = [
        0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
        4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه',
    ];

    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $jdn = self::gregorianToJdn($gy, $gm, $gd);
        return self::jdnToJalali($jdn);
    }

    public static function format(\DateTimeInterface $date, string $pattern = 'l j F Y'): string
    {
        [$jy, $jm, $jd] = self::fromGregorian(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );
        $weekday = self::WEEKDAYS[(int) $date->format('w')] ?? '';

        $map = [
            'Y' => (string) $jy,
            'y' => substr((string) $jy, -2),
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'F' => self::MONTHS[$jm] ?? '',
            'l' => $weekday,
            'H' => $date->format('H'),
            'i' => $date->format('i'),
        ];

        $out = '';
        $len = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            $ch = $pattern[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $out .= $pattern[++$i];
                continue;
            }
            $out .= $map[$ch] ?? $ch;
        }
        return $out;
    }

    private static function gregorianToJdn(int $gy, int $gm, int $gd): int
    {
        $d = (int) ((($gy + (int) (($gm - 8) / 6) + 100100) * 1461) / 4)
            + (int) ((153 * (($gm + 9) % 12) + 2) / 5)
            + $gd - 34840408;
        $d = $d - (int) (((int) (($gy + 100100 + (int) (($gm - 8) / 6)) / 100) * 3) / 4) + 752;
        return $d;
    }

    private static function jdnToJalali(int $jdn): array
    {
        $gy = self::jdnToGregorianYear($jdn);
        $jy = $gy - 621;
        $march = self::firstMarchJdn($jy);
        $k = $jdn - $march;

        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + (int) ($k / 31);
                $jd = ($k % 31) + 1;
                return [$jy, $jm, $jd];
            }
            $k -= 186;
        } else {
            $jy--;
            $k += 179;
            if (self::isLeap($jy)) {
                $k++;
            }
        }
        $jm = 7 + (int) ($k / 30);
        $jd = ($k % 30) + 1;

        return [$jy, $jm, $jd];
    }

    private static function jdnToGregorianYear(int $jdn): int
    {
        $j = 4 * $jdn + 139361631 + (int) (((int) ((4 * $jdn + 183187720) / 146097) * 3) / 4) * 4 - 3908;
        $i = (int) ((($j % 1461) / 4) * 5) + 308;
        return (int) ($j / 1461) - 100100 + (int) ((8 - $i / 25) / 100);
    }

    private static function firstMarchJdn(int $jy): int
    {
        $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
            1635, 1701, 1866, 2020, 2369, 2394, 2456, 3178];
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;
        for ($i = 1; $i < count($breaks); $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ += (int) ($jump / 33) * 8 + (int) ((($jump % 33)) / 4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leapJ += (int) ($n / 33) * 8 + (int) ((($n % 33) + 3) / 4);
        if (($jump % 33) === 4 && $jump - $n === 4) {
            $leapJ++;
        }
        $leapG = (int) ($gy / 4) - (int) (((int) ($gy / 100) + 1) * 3 / 4) - 150;
        $march = 20 + $leapJ - $leapG;

        return self::gregorianToJdn($gy, 3, $march);
    }

    public static function isLeap(int $jy): bool
    {
        $a = $jy - 474;
        $b = (($a % 2820) + 2820) % 2820 + 474;
        return ((($b + 38) * 682) % 2816) < 682;
    }
}
