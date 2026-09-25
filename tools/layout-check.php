<?php
/**
 * بازرسی چیدمان کارت‌ها: هیچ متنی نباید روی متن دیگر بیفتد یا از قاب بیرون بزند.
 *
 *   php tools/layout-check.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Settings;
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

/** بلندترین کادری که متنش باید دقیقاً وسط باشد (قرص، برچسب، پلاک) */
const CENTER_MAX_H = 44.0;
/** بیشترین جابه‌جایی مجاز از وسط (پیکسل منطقی؛ در خروجی ×۲٫۵) */
const CENTER_TOLERANCE = 0.8;
/** کمترین فاصله‌ی متن از لبه‌ی کادر */
const MIN_SIDE_PAD = 5.0;
/** بیشترین اختلاف خط پایه‌ی متن‌های هم‌ردیف */
const BASELINE_TOLERANCE = 0.8;
/** کمترین فاصله‌ی دو متنِ زیر هم */
const MIN_STACK_GAP = 5.0;

$problems = 0;
echo "\n🔍 بازرسی چیدمان کارت‌ها\n" . str_repeat('─', 52) . "\n";

// هر سناریو یک بار با ارقام لاتین و یک بار با ارقام فارسی بررسی می‌شود
$digitsBefore = Settings::get('digits_data');
$runs = [];
foreach (['en' => '', 'fa' => ' [ارقام فارسی]'] as $digits => $suffix) {
    foreach ($cards as $name => $factory) {
        $runs[$name . $suffix] = [$digits, $factory];
    }
}

foreach ($runs as $name => [$digits, $factory]) {
    Settings::set('digits_data', $digits);
    Canvas::$trace = true;
    Canvas::$traceBoxes = [];
    Canvas::$traceShapes = [];

    $canvas = $factory();
    $boxes = Canvas::$traceBoxes;
    $shapes = Canvas::$traceShapes;
    Canvas::$trace = false;
    Canvas::$traceBoxes = [];
    Canvas::$traceShapes = [];

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

    // کوچک‌ترین کادری که هر متن داخلش نشسته
    $holders = [];
    foreach ($boxes as $k => $b) {
        [$ix0, $iy0, $ix1, $iy1] = $b['ink'];
        $holders[$k] = null;
        foreach ($shapes as $si => $sh) {
            $inside = $ix0 >= $sh['x'] - 0.5 && $ix1 <= $sh['x'] + $sh['w'] + 0.5
                && $iy0 >= $sh['y'] - 0.5 && $iy1 <= $sh['y'] + $sh['h'] + 0.5;
            $best = $holders[$k] === null ? null : $shapes[$holders[$k]];
            if ($inside && ($best === null || $sh['w'] * $sh['h'] < $best['w'] * $best['h'])) {
                $holders[$k] = $si;
            }
        }
    }
    $inPill = static fn (int $k): bool => $holders[$k] !== null && $shapes[$holders[$k]]['h'] <= CENTER_MAX_H;

    // ۳) متن داخل قرص/برچسب/کادر کوچک باید دقیقاً وسط باشد (نه بالا، نه پایین)
    foreach ($boxes as $k => $b) {
        [$ix0, $iy0, $ix1, $iy1] = $b['ink'];
        $holder = $holders[$k] === null ? null : $shapes[$holders[$k]];
        if ($holder === null || $holder['h'] > CENTER_MAX_H) {
            continue;
        }
        // مرکزی که چشم می‌بیند (بدنه‌ی ارقام برای عددها، جوهر برای نوشته‌ی تنها)
        $offset = $b['optical'] - ($holder['y'] + $holder['h'] / 2);
        if (abs($offset) > CENTER_TOLERANCE) {
            $issues[] = sprintf(
                '«%s» داخل کادرش %.1f پیکسل %s از وسط است',
                $b['text'],
                abs($offset),
                $offset < 0 ? 'بالاتر' : 'پایین‌تر'
            );
        }
        $side = min($ix0 - $holder['x'], $holder['x'] + $holder['w'] - $ix1);
        if ($side < MIN_SIDE_PAD) {
            $issues[] = sprintf('«%s» به لبه‌ی کادرش چسبیده (%.1f پیکسل)', $b['text'], $side);
        }
    }

    // ۴) دو متنِ روی هم (عنوان و عدد) باید فاصله‌ی واقعی داشته باشند
    for ($i = 0; $i < $count; $i++) {
        for ($j = 0; $j < $count; $j++) {
            if ($i === $j) {
                continue;
            }
            [$ax0, $ay0, $ax1, $ay1] = $boxes[$i]['ink'];
            [$bx0, $by0, $bx1, $by1] = $boxes[$j]['ink'];
            $overlapX = min($ax1, $bx1) - max($ax0, $bx0);
            if ($overlapX < 0.3 * min($ax1 - $ax0, $bx1 - $bx0)) {
                continue; // روی هم نیستند
            }
            $gap = $by0 - $ay1; // j زیر i
            if ($gap >= 0 && $gap < MIN_STACK_GAP) {
                $issues[] = sprintf(
                    '«%s» و «%s» زیر هم خیلی نزدیک‌اند (%.1f پیکسل)',
                    $boxes[$i]['text'],
                    $boxes[$j]['text'],
                    $gap
                );
            }
        }
    }

    // ۵) متن‌های هم‌اندازه و هم‌وزن در یک ردیف باید دقیقاً روی یک خط پایه باشند
    //    (مثلاً نام ارزها در کاشی‌های کنار هم، برچسب‌های تاریخچه، عددهای محور)
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $a = $boxes[$i];
            $b = $boxes[$j];
            if ($a['size'] !== $b['size'] || $a['weight'] !== $b['weight']) {
                continue;
            }
            // متنِ داخل قرص با قرص خودش وسط‌چین می‌شود، نه با متن‌های بیرون
            if (($inPill($i) || $inPill($j)) && $holders[$i] !== $holders[$j]) {
                continue;
            }
            // هم‌ردیف: جعبه‌ی فونتشان تقریباً کامل روی هم می‌افتد
            $oy = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']);
            if ($oy < 0.6 * min($a['h'], $b['h'])) {
                continue;
            }
            // کنار هم، نه روی هم
            if (min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']) > 0) {
                continue;
            }
            $delta = abs($a['base'] - $b['base']);
            if ($delta > BASELINE_TOLERANCE) {
                $issues[] = sprintf(
                    '«%s» و «%s» روی یک خط نیستند (%.1f پیکسل اختلاف)',
                    $a['text'],
                    $b['text'],
                    $delta
                );
            }
        }
    }

    $issues = array_values(array_unique($issues));
    printf("%-40s %3d متن — %s\n", $name, $count, $issues === [] ? 'سالم' : count($issues) . ' مشکل');
    foreach (array_slice($issues, 0, 40) as $issue) {
        echo '    • ' . $issue . "\n";
    }
    $problems += count($issues);
}

Settings::set('digits_data', $digitsBefore);

echo str_repeat('─', 52) . "\n";
echo $problems === 0 ? "چیدمان همه‌ی کارت‌ها سالم است.\n\n" : "{$problems} مشکل چیدمان پیدا شد.\n\n";

exit($problems === 0 ? 0 : 1);
