<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Countries;

/**
 * کارت تقویم اقتصادی روز (جدول راست‌چین).
 */
final class CalendarCard extends Card
{
    private const ROW_H = 42.0;
    private const MAX_ROWS = 18;

    /** @var array<int,array<string,mixed>> */
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
        $hasActual = false;
        foreach ($rows as $row) {
            if (($row['actual'] ?? '') !== '') {
                $hasActual = true;
                break;
            }
        }

        $tableH = 48 + max(1, count($rows)) * self::ROW_H;
        $legendH = 56.0;
        $noteH = $extra > 0 ? 30.0 : 0.0;
        $height = (int) round(72 + 110 + $tableH + $noteH + $legendH + 24 + 72);
        $height = max(560, $height);

        $c = new Canvas(1200, $height, $this->quality);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['brand' => $this->brand, 'footer' => $this->footer]);
        $top = Frame::panelHeader(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            $this->dnum((string) count($this->rows)) . ' رویداد'
        );

        $padX = 26.0;
        $tableX = $rect['x'] + $padX;
        $tableW = $rect['w'] - $padX * 2;

        if ($rows === []) {
            $c->roundRect($tableX, $top + 40, $tableW, 90, 16, $t->c('panel_alt'));
            $c->text('امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است', $tableX + $tableW / 2, $top + 72, 20, $t->c('ink_soft'), Canvas::W_BOLD, 'center');
            $this->legend($c, $t, $tableX, $top + 150, $tableW);

            return $c;
        }

        $cols = $this->columns($tableW, $hasActual);
        $y = $this->table($c, $t, $rows, $cols, $tableX, $top, $tableW);

        if ($extra > 0) {
            $c->text(
                '+ ' . $this->dnum((string) $extra) . ' رویداد دیگر در تقویم امروز',
                $tableX + $tableW - 6,
                $y + 6,
                15,
                $t->c('ink_soft'),
                Canvas::W_MEDIUM,
                'right'
            );
            $y += $noteH;
        }

        $this->legend($c, $t, $tableX, $y + 12, $tableW);

        return $c;
    }

    /**
     * ستون‌ها از راست به چپ چیده می‌شوند.
     * @return array<int,array{key:string,label:string,w:float,x:float}>
     */
    private function columns(float $tableW, bool $hasActual): array
    {
        $fixed = [
            ['key' => 'time',     'label' => 'زمان',        'w' => 74.0],
            ['key' => 'currency', 'label' => 'ارز',         'w' => 78.0],
            ['key' => 'country',  'label' => 'کشور',        'w' => 66.0],
            ['key' => 'impact',   'label' => 'اهمیت',       'w' => 96.0],
            ['key' => 'title',    'label' => 'شرح رویداد',  'w' => 0.0],
            ['key' => 'previous', 'label' => 'قبلی',        'w' => 92.0],
            ['key' => 'forecast', 'label' => 'پیش‌بینی',    'w' => 96.0],
        ];
        if ($hasActual) {
            $fixed[] = ['key' => 'actual', 'label' => 'واقعی', 'w' => 92.0];
        }

        $used = 0.0;
        foreach ($fixed as $col) {
            $used += $col['w'];
        }
        $flex = max(220.0, $tableW - $used);

        $cursor = $tableW;
        $out = [];
        foreach ($fixed as $col) {
            $w = $col['key'] === 'title' ? $flex : $col['w'];
            $cursor -= $w;
            $col['w'] = $w;
            $col['x'] = $cursor; // نسبت به ابتدای جدول
            $out[] = $col;
        }

        return $out;
    }

    private function table(Canvas $c, Theme $t, array $rows, array $cols, float $x, float $y, float $w): float
    {
        $headH = 48.0;

        // سربرگ جدول
        $c->roundRect($x, $y, $w, $headH, 12, $t->c('ink'));
        $c->rect($x, $y + $headH - 12, $w, 12, $t->c('ink'));
        foreach ($cols as $col) {
            $c->text(
                $col['label'],
                $x + $col['x'] + $col['w'] / 2,
                $y + 14,
                16,
                '#FFFFFF',
                Canvas::W_BOLD,
                'center',
                0.95
            );
        }

        $rowY = $y + $headH;
        foreach ($rows as $i => $row) {
            $this->row($c, $t, $row, $cols, $x, $rowY, $w, $i % 2 === 1);
            $rowY += self::ROW_H;
        }

        // قاب جدول
        $c->strokeRoundRect($x, $y, $w, $rowY - $y, 12, $t->c('line'), 1.3, 1.0);
        foreach ($cols as $i => $col) {
            if ($i === 0) {
                continue;
            }
            $lineX = $x + $col['x'] + $col['w'];
            $c->line($lineX, $y + $headH, $lineX, $rowY, $t->c('line'), 1, 0.85);
        }

        return $rowY + 14;
    }

    private function row(Canvas $c, Theme $t, array $row, array $cols, float $x, float $y, float $w, bool $alt): void
    {
        $impact = (int) $row['impact'];
        $isHoliday = $impact === 0 || (bool) $row['all_day'];

        if ($alt) {
            $c->rect($x + 1, $y, $w - 2, self::ROW_H, $t->c('panel_alt'), 0.85);
        }
        if ($isHoliday) {
            $c->rect($x + 1, $y, $w - 2, self::ROW_H, $t->c('flat'), 0.10);
        }
        $c->line($x + 1, $y, $x + $w - 1, $y, $t->c('line'), 1, 0.7);

        $mid = $y + self::ROW_H / 2;

        foreach ($cols as $col) {
            $cx = $x + $col['x'] + $col['w'] / 2;
            $cw = $col['w'];

            switch ($col['key']) {
                case 'time':
                    $time = $isHoliday && $row['time'] === '' ? 'تعطیل' : (string) $row['time'];
                    $c->text(
                        $row['time'] === '' ? $time : $this->dnum($time),
                        $cx,
                        $mid - 11,
                        $row['time'] === '' ? 15 : 17,
                        $isHoliday ? $t->c('flat') : $t->c('ink'),
                        Canvas::W_BOLD,
                        'center'
                    );
                    break;

                case 'currency':
                    $pillW = min($cw - 18, 62.0);
                    $c->roundRect($cx - $pillW / 2, $mid - 13, $pillW, 26, 8, $t->c('value'), 0.12);
                    $c->text((string) $row['currency'], $cx, $mid - 10, 15, $t->c('value'), Canvas::W_BOLD, 'center');
                    break;

                case 'country':
                    $fw = 34.0;
                    $fh = 23.0;
                    Flags::draw($c, Countries::code((string) $row['currency']), $cx - $fw / 2, $mid - $fh / 2, $fw, $fh);
                    break;

                case 'impact':
                    if ($isHoliday) {
                        $c->text('—', $cx, $mid - 10, 15, $t->c('flat'), Canvas::W_BOLD, 'center');
                    } else {
                        $this->impactIcon($c, $t, $cx, $mid, $impact);
                    }
                    if ((bool) $row['speech']) {
                        $this->micIcon($c, $t, $x + $col['x'] + 14, $mid);
                    }
                    break;

                case 'title':
                    $text = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
                    $c->textFit(
                        $text,
                        $x + $col['x'] + $cw - 14,
                        $mid - 11,
                        $cw - 26,
                        16.5,
                        $isHoliday ? $t->c('ink_soft') : $t->c('ink'),
                        Canvas::W_SEMIBOLD,
                        'right',
                        11.5
                    );
                    break;

                default:
                    $value = (string) ($row[$col['key']] ?? '');
                    $color = $t->c('ink_soft');
                    if ($col['key'] === 'actual' && $value !== '') {
                        $color = $t->c('ink');
                    }
                    $c->textFit(
                        $value === '' ? '-' : $this->dnum($value),
                        $cx,
                        $mid - 10,
                        $cw - 14,
                        15.5,
                        $color,
                        Canvas::W_SEMIBOLD,
                        'center',
                        10
                    );
            }
        }
    }

    /** نشانگر اهمیت رویداد (۳ میله) */
    private function impactIcon(Canvas $c, Theme $t, float $cx, float $cy, int $impact): void
    {
        $colors = [1 => '#F3C13A', 2 => '#F0762B', 3 => '#E1273E'];
        $color = $colors[$impact] ?? $t->c('flat');
        $barW = 6.0;
        $gap = 4.0;
        $totalW = $barW * 3 + $gap * 2;
        $startX = $cx - $totalW / 2;

        for ($i = 0; $i < 3; $i++) {
            $barH = 9.0 + $i * 5.0;
            $filled = $i < $impact;
            $c->roundRect(
                $startX + $i * ($barW + $gap),
                $cy + 9 - $barH,
                $barW,
                $barH,
                2.4,
                $filled ? $color : $t->c('line'),
                $filled ? 1.0 : 0.9
            );
        }
    }

    /** آیکون سخنرانی */
    private function micIcon(Canvas $c, Theme $t, float $cx, float $cy): void
    {
        $col = '#E1273E';
        $c->roundRect($cx - 3.4, $cy - 11, 6.8, 12, 3.4, $col);
        $c->ring($cx, $cy - 2, 6.4, 1.8, $col, 10, 170, 1.0);
        $c->rect($cx - 1, $cy + 4, 2, 5, $col);
        $c->rect($cx - 4.5, $cy + 9, 9, 2, $col);
    }

    private function legend(Canvas $c, Theme $t, float $x, float $y, float $w): void
    {
        $h = 44.0;
        $c->roundRect($x, $y, $w, $h, 12, $t->c('panel_alt'));
        $c->strokeRoundRect($x, $y, $w, $h, 12, $t->c('line'), 1.2, 0.9);

        $mid = $y + $h / 2;
        $cursor = $x + $w - 18;

        $c->text('راهنما:', $cursor, $mid - 10, 15, $t->c('ink'), Canvas::W_BOLD, 'right');
        $cursor -= $c->textWidth('راهنما:', 15, Canvas::W_BOLD) + 22;

        foreach ([[3, 'اهمیت زیاد'], [2, 'اهمیت متوسط'], [1, 'اهمیت کم']] as [$impact, $label]) {
            $this->impactIcon($c, $t, $cursor - 17, $mid, $impact);
            $cursor -= 40;
            $c->text($label, $cursor, $mid - 9, 14, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'right');
            $cursor -= $c->textWidth($label, 14, Canvas::W_SEMIBOLD) + 24;
        }

        $this->micIcon($c, $t, $cursor - 8, $mid);
        $cursor -= 22;
        $c->text('سخنرانی مقام‌ها', $cursor, $mid - 9, 14, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'right');
        $cursor -= $c->textWidth('سخنرانی مقام‌ها', 14, Canvas::W_SEMIBOLD) + 24;

        $c->text('زمان‌ها به وقت ایران', $x + 18, $mid - 9, 14, $t->c('value'), Canvas::W_BOLD, 'left');
    }
}
