<?php

final class Fmt
{
    const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    public static function en(string $s): string
    {
        return str_replace(array_merge(self::FA, self::AR), array_merge(range(0, 9), range(0, 9)), $s);
    }

    private static function fa(string $s): string
    {
        return Settings::get('fa_digits') ? str_replace(range(0, 9), self::FA, $s) : $s;
    }

    private static function strip(string $s): string
    {
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    public static function num($x, ?int $decimals = null): string
    {
        if ($x === null || !is_numeric($x)) {
            return $x === null ? '—' : (string)$x;
        }
        $x = (float)$x;
        if (is_nan($x) || is_infinite($x)) {
            return '—';
        }
        $ax = abs($x);
        if ($decimals !== null) {
            $s = self::strip(number_format($x, $decimals));
        } elseif ($ax == 0) {
            $s = '0';
        } elseif ($ax >= 100000) {
            $s = number_format($x, 0);
        } elseif ($ax >= 1000) {
            $s = self::strip(number_format($x, 2));
        } elseif ($ax >= 1) {
            $s = self::strip(number_format($x, 4));
        } else {
            $d = min(12, max(2, (int)(-floor(log10($ax))) + 3));
            $s = self::strip(sprintf("%.{$d}f", $x));
        }
        return self::fa($s);
    }

    public static function toman($x): string
    {
        if ($x === null) {
            return '—';
        }
        return abs($x) < 10 ? self::num($x, 2) : self::fa(number_format(round($x)));
    }

    public static function usd($x): string
    {
        if ($x === null) {
            return '—';
        }
        return abs($x) >= 1 ? self::fa(self::strip(number_format($x, 2))) : self::num($x);
    }

    public static function int($x): string
    {
        return $x === null ? '—' : self::fa(number_format(round((float)$x)));
    }

    public static function percent($x): string
    {
        return $x === null ? '—' : self::fa(($x > 0 ? '+' : '') . number_format($x, 2) . '%');
    }

    public static function badge($x): ?string
    {
        if ($x === null) {
            return null;
        }
        return ($x >= 0 ? '🟢' : '🔴') . ' <b>' . self::percent($x) . '</b>';
    }

    public static function jalaliParts(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
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

    private static function tehran(?int $ts): DateTime
    {
        $d = new DateTime('@' . ($ts ?? time()));
        $d->setTimezone(new DateTimeZone('Asia/Tehran'));
        return $d;
    }

    public static function jalali(?int $ts = null, bool $time = true): string
    {
        $d = self::tehran($ts);
        [$y, $m, $dd] = self::jalaliParts((int)$d->format('Y'), (int)$d->format('n'), (int)$d->format('j'));
        $s = sprintf('%d/%02d/%02d', $y, $m, $dd);
        if ($time) {
            $s .= ' - ' . $d->format('H:i');
        }
        return self::fa($s);
    }

    public static function jalaliLong(?int $ts = null): string
    {
        $d = self::tehran($ts);
        [$y, $m, $dd] = self::jalaliParts((int)$d->format('Y'), (int)$d->format('n'), (int)$d->format('j'));
        return self::fa("$dd " . self::MONTHS[$m - 1] . " $y");
    }

    public static function now(): string
    {
        return self::jalali();
    }

    public static function duration(int $minutes): string
    {
        if (!$minutes) {
            return 'دائمی';
        }
        if ($minutes < 60) {
            return self::fa("$minutes دقیقه");
        }
        if ($minutes < 1440) {
            return self::fa(intdiv($minutes, 60) . ' ساعت');
        }
        return self::fa(intdiv($minutes, 1440) . ' روز');
    }

    public static function short(?string $s, int $head = 6, int $tail = 6): string
    {
        if (!$s || strlen($s) <= $head + $tail + 3) {
            return $s ?: '—';
        }
        return substr($s, 0, $head) . '…' . substr($s, -$tail);
    }
}

final class Parse
{
    const MULT = ['k' => 1e3, 'هزار' => 1e3, 'm' => 1e6, 'میلیون' => 1e6, 'ملیون' => 1e6, 'b' => 1e9, 'میلیارد' => 1e9, 'ملیارد' => 1e9, 'همت' => 1e12, 'تریلیون' => 1e12];

    private static function multRx(): string
    {
        $k = array_keys(self::MULT);
        usort($k, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return implode('|', array_map(fn($x) => preg_quote($x, '/'), $k));
    }

    public static function cleanNumber(string $text): string
    {
        $t = trim(Fmt::en($text));
        $t = str_replace(['٫', '٬', '،'], ['.', '', ''], $t);
        $t = preg_replace('/(?<=\d)[,_](?=\d{3}\b)/u', '', $t);
        $t = preg_replace('/(?<=\d),(?=\d)/u', '', $t);
        return $t;
    }

    public static function amount(string $text): ?array
    {
        $trim = trim($text);
        if ($trim === '' || mb_strlen($text) > 48 || str_contains($trim, "\n")) {
            return null;
        }
        $t = self::cleanNumber($text);
        $m = self::multRx();
        $num = '\d+(?:\.\d+)?';
        if (!preg_match("/^(?P<num>$num)\s*(?:(?P<mult>$m)(?![a-z]))?\s*(?P<unit>[^\d]{1,30})$/iu", $t, $mm)
            && !preg_match("/^(?P<unit>[^\d]{1,30}?)\s*(?P<num>$num)\s*(?P<mult>$m)?$/iu", $t, $mm)) {
            return null;
        }
        $amount = (float)$mm['num'];
        $mult = mb_strtolower($mm['mult'] ?? '');
        if ($mult !== '') {
            $amount *= self::MULT[$mult];
        }
        $unit = trim($mm['unit'], " .:=-");
        if ($unit === '' || isset(self::MULT[mb_strtolower($unit)])) {
            return null;
        }
        return [$amount, $unit];
    }

    public static function expression(string $text): ?string
    {
        if ($text === '' || mb_strlen($text) > 120) {
            return null;
        }
        $t = trim(rtrim(trim(self::cleanNumber($text)), '='));
        if (!preg_match('/^[\d\s.+\-*\/×÷x^%()=]+$/iu', $t)) {
            return null;
        }
        if (preg_match('/^\d{2,4}[\/\-]\d{1,2}[\/\-]\d{1,4}$/', $t) || preg_match('/^(?:\+?\d{1,3}[\s-]?)?0?\d{3}[\s-]\d{3,4}[\s-]?\d{0,4}$/', $t)) {
            return null;
        }
        if (!preg_match('/\d/', $t)) {
            return null;
        }
        $e = str_replace(['×', 'x', 'X', '÷', '^'], ['*', '*', '*', '/', '**'], $t);
        if (!preg_match('/[\d)]\s*(\*\*|[+\-*\/%])\s*[\d(.-]/', $e)) {
            return null;
        }
        if (preg_match('/^0\d{2,}(\s*-\s*\d+)+$/', $e)) {
            return null;
        }
        return $e;
    }
}

final class Calc
{
    private array $tok = [];
    private int $i = 0;

    public static function evaluate(string $expr): float
    {
        $c = new self();
        preg_match_all('/\d+(?:\.\d+)?|\.\d+|\*\*|[+\-*\/%()]|\S/', $expr, $m);
        $c->tok = $m[0];
        $v = $c->sum();
        if ($c->i !== count($c->tok) || is_nan($v) || is_infinite($v)) {
            throw new RuntimeException('syntax');
        }
        return $v;
    }

    private function peek(): ?string
    {
        return $this->tok[$this->i] ?? null;
    }

    private function sum(): float
    {
        $v = $this->term();
        while (in_array($this->peek(), ['+', '-'], true)) {
            $op = $this->tok[$this->i++];
            $r = $this->term();
            $v = $op === '+' ? $v + $r : $v - $r;
        }
        return $v;
    }

    private function term(): float
    {
        $v = $this->unary();
        while (in_array($this->peek(), ['*', '/', '%'], true)) {
            $op = $this->tok[$this->i++];
            $r = $this->unary();
            if (($op === '/' || $op === '%') && $r == 0) {
                throw new RuntimeException('zero');
            }
            $v = match ($op) {
                '*' => $v * $r,
                '/' => $v / $r,
                default => fmod($v, $r) + (fmod($v, $r) != 0 && (($v < 0) !== ($r < 0)) ? $r : 0),
            };
        }
        return $v;
    }

    private function unary(): float
    {
        $p = $this->peek();
        if ($p === '-' || $p === '+') {
            $this->i++;
            $v = $this->unary();
            return $p === '-' ? -$v : $v;
        }
        return $this->power();
    }

    private function power(): float
    {
        $v = $this->primary();
        if ($this->peek() === '**') {
            $this->i++;
            $r = $this->unary();
            if (abs($r) > 1000 || (abs($v) > 1 && abs($r) * log10(abs($v)) > 400)) {
                throw new RuntimeException('large');
            }
            $v = $v ** $r;
        }
        return $v;
    }

    private function primary(): float
    {
        $p = $this->peek();
        if ($p === null) {
            throw new RuntimeException('syntax');
        }
        if ($p === '(') {
            $this->i++;
            $v = $this->sum();
            if ($this->peek() !== ')') {
                throw new RuntimeException('syntax');
            }
            $this->i++;
            return $v;
        }
        if (is_numeric($p)) {
            $this->i++;
            return (float)$p;
        }
        throw new RuntimeException('syntax');
    }

    public static function format(float $v): string
    {
        if (floor($v) == $v && abs($v) < 1e15) {
            return number_format($v, 0);
        }
        if (abs($v) >= 1e30) {
            return sprintf('%.6E', $v);
        }
        $s = number_format($v, 10);
        return rtrim(rtrim($s, '0'), '.');
    }
}
