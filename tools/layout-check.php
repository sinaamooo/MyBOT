<?php
/**
 * بازرسی چیدمان کارت‌ها: هیچ متنی نباید روی متن دیگر بیفتد یا از قاب بیرون بزند.
 *
 *   php tools/layout-check.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Data\CalendarProvider;
use Nikto\Data\FearGreedProvider;
use Nikto\Data\Mock;
use Nikto\Data\PriceProvider;
use Nikto\Render\CalendarCard;
use Nikto\Render\Canvas;
use Nikto\Render\FearGreedCard;
use Nikto\Render\PriceCard;

Mock::enable();

/** @return array<string,callable():Canvas> */
$cards = [
    'قیمت‌ها (۶ ارز)' => function (): Canvas {
        $coins = PriceProvider::fetch(['BTC', 'ETH', 'XRP', 'BNB', 'SOL', 'TRX']);
        $coins[1]['change_pct'] = 2.34;
        $coins[4]['change_pct'] = 5.12;

        return (new PriceCard($coins))->render();
    },
    'قیمت‌ها (مقادیر حدی)' => function (): Canvas {
        $rows = [];
        foreach ([['BTC', 121345.67, -3.08], ['SHIB', 0.00001842, -8.7], ['PEPE', 0.0000078, 145.2],
            ['ETC', 28.1234, 0.004], ['ICP', 7.65, 5.43], ['BCH', 512.34, -0.6],
            ['ETH', 4123.45, 12.34], ['XRP', 1.33, -1.26], ['TON', 5.13, 1.18]] as [$sym, $price, $pct]) {
            $spark = [];
            for ($i = 0; $i < 24; $i++) {
                $spark[] = $price * (1 + sin($i / 3) * 0.02);
            }
            $rows[] = PriceProvider::row($sym, $price, $pct, $price * $pct / 100, $price * 1.05, $price * 0.95, 1e9, $spark);
        }

        return (new PriceCard($rows))->render();
    },
    'ترس و طمع' => function (): Canvas {
        $series = [];
        foreach ([71, 72, 68, 65, 70, 73, 61, 60, 66, 69, 74, 70, 67, 63, 58, 55, 62, 64, 68, 71,
            73, 70, 66, 60, 57, 59, 63, 68, 72, 72, 71, 72] as $i => $v) {
            $series[] = ['value' => $v, 'class' => '', 'timestamp' => time() - $i * 86400];
        }

        return (new FearGreedCard(FearGreedProvider::build($series)))->render();
    },
    'ترس و طمع (ترس شدید)' => function (): Canvas {
        $series = [];
        foreach (range(1, 32) as $i) {
            $series[] = ['value' => max(3, 14 - $i % 9), 'class' => '', 'timestamp' => time() - $i * 86400];
        }

        return (new FearGreedCard(FearGreedProvider::build($series)))->render();
    },
    'تقویم اقتصادی' => fn (): Canvas => (new CalendarCard(CalendarProvider::forDay()))->render(),
    'تقویم (عنوان بلند)' => function (): Canvas {
        $rows = CalendarProvider::forDay();
        $rows[4]['title_fa'] = 'آلمان — مقدماتی — شاخص قیمت مصرف‌کننده هماهنگ‌شده اتحادیه اروپا برای ماه آپریل';
        $rows[4]['previous'] = '-12.345M';
        $rows[4]['forecast'] = '+134.567M';

        return (new CalendarCard($rows))->render();
    },
];

$problems = 0;
echo "\n🔍 بازرسی چیدمان کارت‌ها\n" . str_repeat('─', 52) . "\n";

foreach ($cards as $name => $factory) {
    Canvas::$trace = true;
    Canvas::$traceBoxes = [];

    $canvas = $factory();
    $boxes = Canvas::$traceBoxes;
    Canvas::$trace = false;
    Canvas::$traceBoxes = [];

    $width = $canvas->width();
    $height = $canvas->height();
    $issues = [];

    // ۱) بیرون‌زدگی از کارت
    foreach ($boxes as $b) {
        if ($b['x'] < -1 || $b['y'] < -1 || $b['x'] + $b['w'] > $width + 1 || $b['y'] + $b['h'] > $height + 1) {
            $issues[] = sprintf('«%s» بیرون از کارت (%.0f,%.0f)', $b['text'], $b['x'], $b['y']);
        }
    }

    // ۲) هم‌پوشانی متن‌ها
    $count = count($boxes);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $a = $boxes[$i];
            $b = $boxes[$j];
            // کمی رواداری برای دنباله‌ی حروف فارسی
            $pad = 1.5;
            $ox = min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']) - $pad;
            $oy = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']) - $pad;
            if ($ox <= 0 || $oy <= 0) {
                continue;
            }
            $area = $ox * $oy;
            $smallest = min($a['w'] * $a['h'], $b['w'] * $b['h']);
            if ($smallest > 0 && $area / $smallest > 0.12) {
                $issues[] = sprintf(
                    '«%s» روی «%s» افتاده (%.0f%% هم‌پوشانی)',
                    $a['text'],
                    $b['text'],
                    $area / $smallest * 100
                );
            }
        }
    }

    $issues = array_values(array_unique($issues));
    printf("%-26s %3d متن — %s\n", $name, $count, $issues === [] ? 'سالم' : count($issues) . ' مشکل');
    foreach (array_slice($issues, 0, 6) as $issue) {
        echo '    • ' . $issue . "\n";
    }
    $problems += count($issues);
}

echo str_repeat('─', 52) . "\n";
echo $problems === 0 ? "چیدمان همه‌ی کارت‌ها سالم است.\n\n" : "{$problems} مشکل چیدمان پیدا شد.\n\n";

exit($problems === 0 ? 0 : 1);
