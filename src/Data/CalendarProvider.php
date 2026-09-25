<?php
declare(strict_types=1);

namespace Nikto\Data;

use DateTimeImmutable;
use DateTimeZone;
use Nikto\Core\Http;
use Nikto\Core\Log;
use Nikto\Core\Settings;

final class CalendarProvider
{
    private const FF_WEEK = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';
    private const FF_NEXT = 'https://nfs.faireconomy.media/ff_calendar_nextweek.json';
    private const TV      = 'https://economic-calendar.tradingview.com/events';

    public static function forDay(?DateTimeImmutable $day = null): array
    {
        $tz = Settings::timezone();
        $day ??= new DateTimeImmutable('now', $tz);
        $day = $day->setTimezone($tz);

        if (Mock::enabled()) {
            return self::filter(Mock::calendar($day), $day);
        }

        $source = Settings::get('calendar_source');
        $rows = [];

        if ($source === 'forexfactory' || $source === 'auto') {
            $rows = self::fromForexFactory($day);
        }
        if ($rows === [] && ($source === 'tradingview' || $source === 'auto')) {
            $rows = self::fromTradingView($day);
        }
        if ($rows === []) {
            Log::warn('Economic calendar returned no rows', ['day' => $day->format('Y-m-d')]);
        }

        return self::filter($rows, $day);
    }

    private static function fromForexFactory(DateTimeImmutable $day): array
    {
        $tz = $day->getTimezone();
        $out = [];

        foreach ([self::FF_WEEK => 'ff_week', self::FF_NEXT => 'ff_next'] as $url => $cacheKey) {
            $data = Http::cachedJson($cacheKey, $url, 1800);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $row) {
                if (!isset($row['date'], $row['title'])) {
                    continue;
                }
                try {
                    $at = new DateTimeImmutable((string) $row['date']);
                } catch (\Throwable) {
                    continue;
                }
                $at = $at->setTimezone($tz);
                if ($at->format('Y-m-d') !== $day->format('Y-m-d')) {
                    continue;
                }
                $impactRaw = strtolower((string) ($row['impact'] ?? ''));
                $allDay = $impactRaw === 'holiday' || $at->format('H:i') === '00:00';

                $out[] = self::row(
                    $at,
                    (string) ($row['country'] ?? ''),
                    self::impactLevel($impactRaw),
                    (string) $row['title'],
                    (string) ($row['previous'] ?? ''),
                    (string) ($row['forecast'] ?? ''),
                    (string) ($row['actual'] ?? ''),
                    $allDay
                );
            }
            if ($out !== []) {
                break;
            }
        }

        return $out;
    }

    private static function fromTradingView(DateTimeImmutable $day): array
    {
        $tz = $day->getTimezone();
        $from = $day->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
        $to   = $day->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
        $countries = 'US,EU,GB,JP,CA,AU,NZ,CH,CN,DE,FR,IT,ES';

        $url = self::TV . '?from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
            . '&countries=' . rawurlencode($countries);
        $data = Http::cachedJson('tv_' . $day->format('Ymd'), $url, 1800, [
            'Origin: https://www.tradingview.com',
            'Referer: https://www.tradingview.com/',
        ]);

        if (!is_array($data) || !isset($data['result']) || !is_array($data['result'])) {
            return [];
        }

        $out = [];
        foreach ($data['result'] as $row) {
            if (!isset($row['date'], $row['title'])) {
                continue;
            }
            try {
                $at = (new DateTimeImmutable((string) $row['date']))->setTimezone($tz);
            } catch (\Throwable) {
                continue;
            }
            $importance = (int) ($row['importance'] ?? -1);
            $impact = match (true) {
                $importance >= 1  => 3,
                $importance === 0 => 2,
                default           => 1,
            };
            $currency = Countries::currencyOf((string) ($row['country'] ?? ''));

            $out[] = self::row(
                $at,
                $currency,
                $impact,
                (string) $row['title'],
                self::stringify($row['previous'] ?? null),
                self::stringify($row['forecast'] ?? null),
                self::stringify($row['actual'] ?? null),
                false
            );
        }

        return $out;
    }

    private static function row(
        DateTimeImmutable $at,
        string $currency,
        int $impact,
        string $title,
        string $previous,
        string $forecast,
        string $actual,
        bool $allDay
    ): array {
        $currency = strtoupper(trim($currency));

        return [
            'timestamp' => $at->getTimestamp(),
            'time'      => $allDay ? '' : $at->format('H:i'),
            'all_day'   => $allDay,
            'currency'  => $currency,
            'country'   => Countries::code($currency),
            'impact'    => $impact,
            'title_en'  => $title,
            'title_fa'  => EventTranslator::translate($title),
            'previous'  => self::clean($previous),
            'forecast'  => self::clean($forecast),
            'actual'    => self::clean($actual),
            'speech'    => self::isSpeech($title),
        ];
    }

    private static function filter(array $rows, DateTimeImmutable $day): array
    {
        $minImpact = max(0, min(3, Settings::int('calendar_min_impact', 2)));
        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', strtoupper(Settings::get('calendar_currencies')))
        )));

        $out = [];
        foreach ($rows as $row) {
            $impact = (int) $row['impact'];
            $isHoliday = $impact === 0;
            if (!$isHoliday && $impact < $minImpact) {
                continue;
            }
            if ($allowed !== [] && !in_array((string) $row['currency'], $allowed, true)) {
                continue;
            }
            $out[] = $row;
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['all_day'] !== $b['all_day']) {
                return $a['all_day'] ? -1 : 1;
            }
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $out;
    }

    public static function isSpeech(string $title): bool
    {
        return (bool) preg_match(
            '/(speaks?\b|speech|testif|press conference|presser|statement\b|meeting minutes)/i',
            $title
        );
    }

    private static function impactLevel(string $impact): int
    {
        return match (strtolower($impact)) {
            'high'   => 3,
            'medium' => 2,
            'low'    => 1,
            default  => 0,
        };
    }

    private static function clean(string $value): string
    {
        $value = trim($value);
        return in_array(strtolower($value), ['', 'null', 'n/a', '-'], true) ? '' : $value;
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_float($value) || is_int($value)) {
            $abs = abs((float) $value);
            if ($abs >= 1000000) {
                return round((float) $value / 1000000, 2) . 'M';
            }
            if ($abs >= 1000) {
                return round((float) $value / 1000, 1) . 'K';
            }
            return (string) round((float) $value, 2);
        }
        return (string) $value;
    }
}
