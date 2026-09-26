<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Countries;

final class CalendarCard extends Card
{
    private const ROW_H     = 50.0;
    private const HEAD_H    = 34.0;
    private const MAX_ROWS  = 18;
    private const TABLE_PAD = 8.0;

    private array $rows;
    private string $title;

    public function __construct(array $rows, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->rows = array_values($rows);
        $this->title = (string) ($options['title'] ?? 'تقویم اقتصادی امروز');
    }

    public function render(): Canvas
    {
        $rows = array_slice($this->rows, 0, self::MAX_ROWS);
        $extra = count($this->rows) - count($rows);

        $tableH = self::TABLE_PAD * 2 + self::HEAD_H + max(1, count($rows)) * self::ROW_H;
        $noteH = $extra > 0 ? 26.0 : 0.0;
        $height = (int) round(84 + Frame::HEADER_H + $tableH + $noteH + 14 + 38);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            $this->tnum((string) count($this->rows)) . ' رویداد'
        );

        if ($rows === []) {
            Frame::panel($c, $t, $rect['x'], $top, $rect['w'], 84, 18);
            $c->text(
                'امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است',
                $rect['x'] + $rect['w'] / 2,
                $top + 42,
                13,
                $t->c('ink_dim'),
                Canvas::W_SEMIBOLD,
                'center',
                1.0,
                'ink'
            );
            $this->legend($c, $t, $rect['x'], $top + 100, $rect['w']);

            return $c;
        }

        Frame::panel($c, $t, $rect['x'], $top, $rect['w'], $tableH, 18);

        $cols = $this->columns($rect['x'], $rect['w']);
        $y = $this->headRow($c, $t, $rect['x'], $top + self::TABLE_PAD, $rect['w'], $cols);

        foreach ($rows as $i => $row) {
            $this->row($c, $t, $row, $rect['x'] + self::TABLE_PAD, $y, $rect['w'] - self::TABLE_PAD * 2, $cols, $i);
            $y += self::ROW_H;
        }

        $y = $top + $tableH;

        if ($extra > 0) {
            $c->text(
                '+ ' . $this->tnum((string) $extra) . ' رویداد دیگر در تقویم امروز',
                $rect['x'] + $rect['w'],
                $y + 16,
                10.5,
                $t->c('ink_dim'),
                Canvas::W_MEDIUM,
                'right',
                1.0,
                'ink'
            );
            $y += $noteH;
        }

        $this->legend($c, $t, $rect['x'], $y + 14, $rect['w']);

        return $c;
    }

    private function columns(float $x, float $w): array
    {
        $right = $x + $w;

        return [
            'time'      => $right - 60,
            'flag'      => $right - 118,
            'currency'  => $right - 140,
            'impact'    => $right - 200,
            'mic'       => $right - 232,
            'title'     => $right - 256,
            'forecast'  => $x + 152,
            'previous'  => $x + 58,
            'head_cur'  => $right - 138,
        ];
    }

    private function headRow(Canvas $c, Theme $t, float $x, float $y, float $w, array $cols): float
    {
        $inner = $w - self::TABLE_PAD * 2;
        Frame::well($c, $t, $x + self::TABLE_PAD, $y, $inner, self::HEAD_H, 10, 1.3);

        $mid = $y + self::HEAD_H / 2;
        $labels = [
            ['زمان', $cols['time'], 'center'],
            ['ارز', $cols['head_cur'], 'center'],
            ['اهمیت', $cols['impact'], 'center'],
            ['شرح رویداد', $cols['title'], 'right'],
            ['پیش‌بینی', $cols['forecast'], 'center'],
            ['قبلی', $cols['previous'], 'center'],
        ];

        foreach ($labels as [$text, $lx, $align]) {
            $c->text($text, $lx, $mid, 10.5, $t->c('ink'), Canvas::W_BOLD, $align, 1.0, 'middle');
        }

        $c->rect($x + self::TABLE_PAD, $y + self::HEAD_H, $inner, 1.4, $t->c('line'), 0.55);

        return $y + self::HEAD_H;
    }

    private function row(Canvas $c, Theme $t, array $row, float $x, float $y, float $w, array $cols, int $index): void
    {
        $impact = (int) $row['impact'];
        $isHoliday = $impact === 0 || (bool) $row['all_day'];
        $accent = $this->impactColor($t, $impact, $isHoliday);
        $mid = $y + self::ROW_H / 2;

        if ($index % 2 === 1) {
            Frame::well($c, $t, $x, $y, $w, self::ROW_H, 8, 0.6);
        }
        if ($index > 0) {
            $c->rect($x + 6, $y, $w - 12, 1, $t->c('line_soft'), 1.0);
        }

        if ($impact >= 3 && !$isHoliday) {
            $c->roundRect($x + $w - 12, $y + 11, 4, self::ROW_H - 22, 2, $accent, 1.0);
        }

        $timeText = $isHoliday && (string) $row['time'] === '' ? 'تعطیل' : (string) $row['time'];
        $hasTime = (string) $row['time'] !== '';
        $badgeW = 60.0;
        $badgeH = 28.0;
        $badgeX = $cols['time'] - $badgeW / 2;

        if ($hasTime) {
            $c->roundRect($badgeX, $mid - $badgeH / 2, $badgeW, $badgeH, 8, $t->c('line'), 1.0);
        } else {
            Frame::well($c, $t, $badgeX, $mid - $badgeH / 2, $badgeW, $badgeH, 8, 1.2);
        }
        if (!$hasTime) {
            $c->strokeRoundRect($badgeX, $mid - $badgeH / 2, $badgeW, $badgeH, 8, $t->c('line'), 1.2, 0.45);
        }
        $c->text(
            $hasTime ? $this->dnum($timeText) : $timeText,
            $cols['time'],
            $mid,
            $hasTime ? 13 : 10.5,
            $hasTime ? '#FFFFFF' : $t->c('ink_dim'),
            $hasTime ? Canvas::W_NUM_BOLD : Canvas::W_SEMIBOLD,
            'center',
            1.0,
            $hasTime ? 'num' : 'ink'
        );

        Flags::draw($c, Countries::code((string) $row['currency']), $cols['flag'] - 13, $mid - 9, 26, 18);
        $c->text(
            (string) $row['currency'],
            $cols['currency'],
            $mid,
            11.5,
            $t->c('ink'),
            Canvas::W_NUM_BOLD,
            'right',
            1.0,
            'num'
        );

        if ($isHoliday) {
            $c->text('—', $cols['impact'], $mid, 11, $t->c('ink_faint'), Canvas::W_BOLD, 'center', 1.0, 'ink');
        } else {
            $this->impactIcon($c, $t, $cols['impact'], $mid, $impact);
        }
        if ((bool) $row['speech']) {
            $this->micIcon($c, $t, $cols['mic'], $mid);
        }

        $forecast = (string) ($row['forecast'] ?? '');
        $previous = (string) ($row['previous'] ?? '');
        $this->stat($c, $t, $cols['forecast'], $mid, 88, $forecast, $this->statColor($t, $forecast, $previous));
        $this->stat($c, $t, $cols['previous'], $mid, 84, $previous, $t->c('ink_dim'));

        $titleLeft = $cols['forecast'] + 56;
        $text = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
        $c->textFit(
            $text,
            $cols['title'],
            $mid,
            max(160.0, $cols['title'] - $titleLeft),
            12.5,
            $isHoliday ? $t->c('ink_dim') : $t->c('ink'),
            Canvas::W_MEDIUM,
            'right',
            9,
            1.0,
            'ink'
        );
    }

    private function statColor(Theme $t, string $forecast, string $previous): string
    {
        if ($forecast === '') {
            return $t->c('ink_faint');
        }
        $f = $this->numeric($forecast);
        $p = $this->numeric($previous);
        if ($f === null || $p === null || abs($f - $p) < 1e-9) {
            return $t->c('ink');
        }

        return $f > $p ? $t->c('up') : $t->c('down');
    }

    private function numeric(string $value): ?float
    {
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*([KMBT])?/i', str_replace(',', '', $value), $m) !== 1) {
            return null;
        }
        $scale = ['K' => 1e3, 'M' => 1e6, 'B' => 1e9, 'T' => 1e12][strtoupper($m[2] ?? '')] ?? 1.0;

        return (float) $m[1] * $scale;
    }

    private function stat(Canvas $c, Theme $t, float $cx, float $mid, float $w, string $value, string $color): void
    {
        $c->textFit(
            $value === '' ? '–' : $this->dnum($value),
            $cx,
            $mid,
            $w,
            12.5,
            $color,
            Canvas::W_NUM_BOLD,
            'center',
            8,
            1.0,
            'num'
        );
    }

    private function impactColor(Theme $t, int $impact, bool $isHoliday): string
    {
        if ($isHoliday) {
            return $t->c('flat');
        }

        return match ($impact) {
            3       => $t->c('down'),
            2       => $t->c('line'),
            default => $t->c('ink_faint'),
        };
    }

    private function impactIcon(Canvas $c, Theme $t, float $cx, float $cy, int $impact): void
    {
        $color = $this->impactColor($t, $impact, false);
        $barW = 4.2;
        $gap = 3.6;
        $totalW = $barW * 3 + $gap * 2;
        $startX = $cx - $totalW / 2;

        for ($i = 0; $i < 3; $i++) {
            $barH = 6.5 + $i * 4.0;
            $filled = $i < $impact;
            $c->roundRect(
                $startX + $i * ($barW + $gap),
                $cy + 8 - $barH,
                $barW,
                $barH,
                1.6,
                $filled ? $color : $t->c('line_soft'),
                1.0
            );
        }
    }

    private function micIcon(Canvas $c, Theme $t, float $cx, float $cy): void
    {
        $col = $t->c('line');
        $c->roundRect($cx - 2.4, $cy - 8, 4.8, 8.8, 2.4, $col, 0.85);
        $c->ring($cx, $cy - 1.6, 4.6, 1.3, $col, 10, 170, 0.85);
        $c->rect($cx - 0.75, $cy + 3.0, 1.5, 3.4, $col, 0.85);
        $c->rect($cx - 3.4, $cy + 6.4, 6.8, 1.5, $col, 0.85);
    }

    private function legend(Canvas $c, Theme $t, float $x, float $y, float $w): void
    {
        $h = 38.0;
        Frame::panel($c, $t, $x, $y, $w, $h, 12, ['fill' => $t->c('surface_alt'), 'border_alpha' => 0.14, 'shadow' => false]);

        $mid = $y + $h / 2;
        $cursor = $x + $w - 18;
        $size = 10;

        foreach ([[3, 'اهمیت زیاد'], [2, 'اهمیت متوسط'], [1, 'اهمیت کم']] as [$impact, $label]) {
            $this->impactIcon($c, $t, $cursor - 12, $mid, $impact);
            $cursor -= 28;
            $c->text($label, $cursor, $mid, $size, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');
            $cursor -= $c->textWidth($label, $size, Canvas::W_SEMIBOLD) + 22;
        }

        $this->micIcon($c, $t, $cursor - 8, $mid);
        $cursor -= 20;
        $c->text('سخنرانی مقام‌ها', $cursor, $mid, $size, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');

        $c->text('زمان‌ها به وقت ایران', $x + 18, $mid, $size, $t->c('ink'), Canvas::W_BOLD, 'left', 1.0, 'middle');
    }
}
