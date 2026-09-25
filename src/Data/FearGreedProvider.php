<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;
use Nikto\Core\Log;

final class FearGreedProvider
{
    private const URL = 'https://api.alternative.me/fng/?limit=32&format=json';

    public const ZONES = [
        ['max' => 24,  'fa' => 'ترس شدید',  'en' => 'Extreme Fear',  'color' => '#E1273E'],
        ['max' => 44,  'fa' => 'ترس',       'en' => 'Fear',          'color' => '#F0762B'],
        ['max' => 55,  'fa' => 'خنثی',      'en' => 'Neutral',       'color' => '#F3C13A'],
        ['max' => 75,  'fa' => 'طمع',       'en' => 'Greed',         'color' => '#93C846'],
        ['max' => 100, 'fa' => 'طمع شدید',  'en' => 'Extreme Greed', 'color' => '#15A05F'],
    ];

    public static function fetch(): ?array
    {
        if (Mock::enabled()) {
            return Mock::fearGreed();
        }

        $data = Http::cachedJson('fng', self::URL, 900);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data']) || $data['data'] === []) {
            Log::error('Fear & Greed fetch failed');
            return null;
        }

        $series = [];
        foreach ($data['data'] as $row) {
            $series[] = [
                'value'     => (int) ($row['value'] ?? 0),
                'class'     => (string) ($row['value_classification'] ?? ''),
                'timestamp' => (int) ($row['timestamp'] ?? 0),
            ];
        }

        return self::build($series);
    }

    public static function build(array $series): array
    {
        $today = $series[0] ?? ['value' => 50, 'class' => 'Neutral', 'timestamp' => time()];
        $pick = static function (array $series, int $index): ?array {
            return $series[$index] ?? null;
        };

        $history = [];
        foreach (['yesterday' => 1, 'week' => 7, 'month' => 30] as $key => $index) {
            $row = $pick($series, $index);
            if ($row === null) {
                continue;
            }
            $history[$key] = [
                'value' => (int) $row['value'],
                'zone'  => self::zone((int) $row['value']),
            ];
        }

        $value = (int) $today['value'];
        $zone = self::zone($value, (string) ($today['class'] ?? ''));

        return [
            'value'     => $value,
            'zone'      => $zone,
            'history'   => $history,
            'series'    => array_reverse(array_map(static fn ($r) => (int) $r['value'], array_slice($series, 0, 30))),
            'timestamp' => (int) ($today['timestamp'] ?? time()),
        ];
    }

    public static function zone(int $value, string $classification = ''): array
    {
        if ($classification !== '') {
            foreach (self::ZONES as $zone) {
                if (strcasecmp($zone['en'], $classification) === 0) {
                    return ['fa' => $zone['fa'], 'en' => $zone['en'], 'color' => $zone['color']];
                }
            }
        }
        foreach (self::ZONES as $zone) {
            if ($value <= $zone['max']) {
                return ['fa' => $zone['fa'], 'en' => $zone['en'], 'color' => $zone['color']];
            }
        }
        $last = self::ZONES[count(self::ZONES) - 1];

        return ['fa' => $last['fa'], 'en' => $last['en'], 'color' => $last['color']];
    }
}
