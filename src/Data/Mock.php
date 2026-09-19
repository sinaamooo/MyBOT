<?php
declare(strict_types=1);

namespace Nikto\Data;

use DateTimeImmutable;

/**
 * داده‌های نمونه برای پیش‌نمایش آفلاین (بدون نیاز به اینترنت).
 * با متغیر محیطی NIKTO_MOCK=1 یا سوییچ --mock فعال می‌شود.
 */
final class Mock
{
    private static ?bool $forced = null;

    public static function enable(bool $on = true): void
    {
        self::$forced = $on;
    }

    public static function enabled(): bool
    {
        if (self::$forced !== null) {
            return self::$forced;
        }
        return in_array(strtolower((string) getenv('NIKTO_MOCK')), ['1', 'true', 'yes'], true);
    }

    /** @return array<int,array<string,mixed>> */
    public static function prices(array $symbols): array
    {
        $fixture = self::load('prices.json');
        $out = [];
        foreach ($symbols as $i => $symbol) {
            $symbol = strtoupper($symbol);
            $data = $fixture[$symbol] ?? [
                'price'      => 12.5 + $i * 7.3,
                'change_pct' => ((($i * 37) % 11) - 5) / 1.7,
            ];
            $price = (float) $data['price'];
            $pct   = (float) $data['change_pct'];
            $abs   = $data['change_abs'] ?? ($price - $price / (1 + $pct / 100));

            $out[] = PriceProvider::row(
                $symbol,
                $price,
                $pct,
                (float) $abs,
                $price * 1.035,
                $price * 0.962,
                (float) ($data['volume'] ?? 1.2e9 / ($i + 1)),
                self::sparkline($price, $pct, crc32($symbol))
            );
        }

        return $out;
    }

    /** نمودار کوچک ۲۴ ساعته‌ی شبیه‌سازی‌شده */
    private static function sparkline(float $price, float $pct, int $seed): array
    {
        mt_srand($seed);
        $start = $price / (1 + $pct / 100);
        $points = [];
        for ($i = 0; $i < 24; $i++) {
            $t = $i / 23;
            $trend = $start + ($price - $start) * $t;
            $noise = $trend * (mt_rand(-45, 45) / 10000);
            $points[] = round($trend + $noise, 8);
        }
        $points[23] = $price;
        mt_srand();

        return $points;
    }

    /** @return array<string,mixed> */
    public static function fearGreed(): array
    {
        $fixture = self::load('feargreed.json');
        $series = [];
        foreach (($fixture['series'] ?? [27, 26, 24, 22, 19, 18, 17, 16]) as $i => $value) {
            $series[] = [
                'value'     => (int) $value,
                'class'     => '',
                'timestamp' => time() - $i * 86400,
            ];
        }
        while (count($series) < 32) {
            $series[] = ['value' => 11 + (count($series) % 7), 'class' => '', 'timestamp' => time() - count($series) * 86400];
        }

        return FearGreedProvider::build($series);
    }

    /** @return array<int,array<string,mixed>> */
    public static function calendar(DateTimeImmutable $day): array
    {
        $fixture = self::load('calendar.json');
        $rows = [];
        foreach ($fixture as $item) {
            $time = (string) ($item['time'] ?? '');
            $allDay = $time === '';
            $at = $allDay
                ? $day->setTime(0, 0)
                : $day->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));

            $title = (string) ($item['title'] ?? '');
            $currency = strtoupper((string) ($item['currency'] ?? 'USD'));

            $rows[] = [
                'timestamp' => $at->getTimestamp(),
                'time'      => $allDay ? '' : $at->format('H:i'),
                'all_day'   => $allDay,
                'currency'  => $currency,
                'country'   => Countries::code($currency),
                'impact'    => (int) ($item['impact'] ?? 2),
                'title_en'  => $title,
                'title_fa'  => EventTranslator::translate($title),
                'previous'  => (string) ($item['previous'] ?? ''),
                'forecast'  => (string) ($item['forecast'] ?? ''),
                'actual'    => (string) ($item['actual'] ?? ''),
                'speech'    => CalendarProvider::isSpeech($title),
            ];
        }

        return $rows;
    }

    /** @return array<mixed> */
    private static function load(string $file): array
    {
        $path = APP_FIXTURES . '/' . $file;
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
