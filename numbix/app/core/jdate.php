<?php
// Jalali (Solar Hijri) calendar helpers.

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
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

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = ($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0);
    $months = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $months[$gm]; $gm++) {
        $gd -= $months[$gm];
    }
    return [$gy, $gm, $gd];
}

function jalali_month_name(int $m): string
{
    return ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'][$m] ?? '';
}

/**
 * Format a timestamp / datetime string in the Jalali calendar.
 * Supported tokens: Y y m n d j H i s F l
 */
function jdate(string $format, int|string|null $time = null, bool $persianDigits = true): string
{
    if ($time === null || $time === '') {
        $ts = time();
    } elseif (is_numeric($time)) {
        $ts = (int)$time;
    } else {
        $ts = strtotime($time) ?: time();
    }
    [$gy, $gm, $gd] = array_map('intval', explode('-', date('Y-n-j', $ts)));
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    $weekdays = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $c = $format[$i];
        if ($c === '\\' && $i + 1 < $len) {
            $out .= $format[++$i];
            continue;
        }
        $out .= match ($c) {
            'Y' => (string)$jy,
            'y' => substr((string)$jy, -2),
            'm' => str_pad((string)$jm, 2, '0', STR_PAD_LEFT),
            'n' => (string)$jm,
            'd' => str_pad((string)$jd, 2, '0', STR_PAD_LEFT),
            'j' => (string)$jd,
            'H' => date('H', $ts),
            'i' => date('i', $ts),
            's' => date('s', $ts),
            'F' => jalali_month_name($jm),
            'l' => $weekdays[(int)date('w', $ts)],
            default => $c,
        };
    }
    return $persianDigits ? fa($out) : $out;
}

/** Parse "1405/07/08" (Persian or Latin digits) into "Y-m-d" Gregorian, or null. */
function jalali_to_date(?string $jalali): ?string
{
    if (!$jalali) {
        return null;
    }
    $jalali = en_digits(trim($jalali));
    if (!preg_match('~^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$~', $jalali, $m)) {
        return null;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian((int)$m[1], (int)$m[2], (int)$m[3]);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

/** SQL literal for the first moment of the current Jalali month (+/- $offset months). */
function jmonth_sql(int $offset = 0): string
{
    [$jy, $jm] = gregorian_to_jalali((int)date('Y'), (int)date('n'), (int)date('j'));
    $jm += $offset;
    while ($jm < 1) {
        $jm += 12;
        $jy--;
    }
    while ($jm > 12) {
        $jm -= 12;
        $jy++;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, 1);
    return sprintf("'%04d-%02d-%02d 00:00:00'", $gy, $gm, $gd);
}
