<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Countries;

/**
 * کارت تقویم اقتصادی — جدول شیشه‌ای راست‌چین با متن ریز و خوانا.
 */
final class CalendarCard extends Card
{
    private const ROW_H    = 31.0;
    private const HEAD_H   = 32.0;
    private const MAX_ROWS = 20;

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

        $tableH = self::HEAD_H + max(1, count($rows)) * self::ROW_H + 10;
        $noteH  = $extra > 0 ? 28.0 : 0.0;
        $height = (int) round(76 + Frame::HEADER_H + $tableH + $noteH + 12 + 34);
        $height = max(430, $height);

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
            $c->glass($rect['x'], $top, $rect['w'], 70, 16, ['tint' => $t->c('tint')]);
            $c->text(
                'امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است',
                $rect['x'] + $rect['w'] / 2,
                $top + 35,
                13,
                $t->c('ink_dim'),
                Canvas::W_SEMIBOLD,
                'center',
                1.0,
                'middle'
            );
            $this->legend($c, $t, $rect['x'], $top + 88, $rect['w']);

            return $c;
        }

        $cols = $this->columns($rect['w'], $hasActual);
        $y = $this->table($c, $t, $rows, $cols, $rect['x'], $top, $rect['w']);

        if ($extra > 0) {
            $c->text(
                '+ ' . $this->tnum((string) $extra) . ' رویداد دیگر در تقویم امروز',
                $rect['x'] + $rect['w'],
                $y + 7,
                10,
                $t->c('ink_faint'),
                Canvas::W_MEDIUM,
                'right'
            );
            $y += $noteH;
        }

        $this->legend($c, $t, $rect['x'], $y + 10, $rect['w']);

        return $c;
    }

    /** @return array<int,array{key:string,label:string,w:float,x:float}> */
    private function columns(float $tableW, bool $hasActual): array
    {
        $fixed = [
            ['key' => 'time',     'label' => 'زمان',       'w' => 58.0],
            ['key' => 'currency', 'label' => 'ارز',        'w' => 60.0],
            ['key' => 'country',  'label' => 'کشور',       'w' => 52.0],
            ['key' => 'impact',   'label' => 'اهمیت',      'w' => 78.0],
            ['key' => 'title',    'label' => 'شرح رویداد', 'w' => 0.0],
            ['key' => 'previous', 'label' => 'قبلی',       'w' => 76.0],
            ['key' => 'forecast', 'label' => 'پیش‌بینی',   'w' => 80.0],
        ];
        if ($hasActual) {
            $fixed[] = ['key' => 'actual', 'label' => 'واقعی', 'w' => 76.0];
        }

        $used = 0.0;
        foreach ($fixed as $col) {
            $used += $col['w'];
        }
        $flex = max(200.0, $tableW - $used);

        $cursor = $tableW;
        $out = [];
        foreach ($fixed as $col) {
            $w = $col['key'] === 'title' ? $flex : $col['w'];
            $cursor -= $w;
            $col['w'] = $w;
            $col['x'] = $cursor;
            $out[] = $col;
        }

        return $out;
    }

    private function table(Canvas $c, Theme $t, array $rows, array $cols, float $x, float $y, float $w): float
    {
        $tableH = self::HEAD_H + count($rows) * self::ROW_H + 8;
        $c->glass($x, $y, $w, $tableH, 16, ['fill' => 0.05, 'border' => 0.12, 'tint' => $t->c('tint')]);

        // سربرگ جدول
        $c->roundRect($x + 1, $y + 1, $w - 2, self::HEAD_H, 14, '#FFFFFF', 0.07);
        $c->rect($x + 1, $y + self::HEAD_H - 6, $w - 2, 6, '#FFFFFF', 0.07);
        $c->rect($x + 10, $y + self::HEAD_H, $w - 20, 1, '#FFFFFF', 0.12);

        foreach ($cols as $col) {
            $c->text(
                $col['label'],
                $x + $col['x'] + $col['w'] / 2,
                $y + self::HEAD_H / 2,
                10.5,
                $t->c('ink_dim'),
                Canvas::W_BOLD,
                'center',
                1.0,
                'middle'
            );
        }

        $rowY = $y + self::HEAD_H + 4;
        foreach ($rows as $i => $row) {
            $this->row($c, $t, $row, $cols, $x, $rowY, $w, $i % 2 === 1);
            $rowY += self::ROW_H;
        }

        // خطوط عمودی ستون‌ها
        foreach ($cols as $i => $col) {
            if ($i === 0) {
                continue;
            }
            $lineX = $x + $col['x'] + $col['w'];
            $c->line($lineX, $y + self::HEAD_H + 4, $lineX, $rowY, '#FFFFFF', 1, 0.05);
        }

        return $y + $tableH + 6;
    }

    private function row(Canvas $c, Theme $t, array $row, array $cols, float $x, float $y, float $w, bool $alt): void
    {
        $impact = (int) $row['impact'];
        $isHoliday = $impact === 0 || (bool) $row['all_day'];

        if ($alt) {
            $c->rect($x + 6, $y, $w - 12, self::ROW_H, '#FFFFFF', 0.022);
        }
        $mid = $y + self::ROW_H / 2;

        foreach ($cols as $col) {
            $cx = $x + $col['x'] + $col['w'] / 2;
            $cw = $col['w'];

            switch ($col['key']) {
                case 'time':
                    $isAllDay = (string) $row['time'] === '';
                    $c->text(
                        $isAllDay ? 'تعطیل' : $this->dnum((string) $row['time']),
                        $cx,
                        $mid,
                        $isAllDay ? 10 : 11.5,
                        $isHoliday ? $t->c('ink_faint') : $t->c('ink'),
                        Canvas::W_BOLD,
                        'center',
                        1.0,
                        'middle'
                    );
                    break;

                case 'currency':
                    $pillW = min($cw - 12, 46.0);
                    $c->roundRect($cx - $pillW / 2, $mid - 9, $pillW, 18, 6, '#FFFFFF', 0.08);
                    $c->text((string) $row['currency'], $cx, $mid, 9.5, $t->c('ink_dim'), Canvas::W_BOLD, 'center', 1.0, 'middle');
                    break;

                case 'country':
                    $fw = 24.0;
                    $fh = 16.0;
                    Flags::draw($c, Countries::code((string) $row['currency']), $cx - $fw / 2, $mid - $fh / 2, $fw, $fh);
                    break;

                case 'impact':
                    if ($isHoliday) {
                        $c->text('—', $cx, $mid, 10, $t->c('ink_faint'), Canvas::W_BOLD, 'center', 1.0, 'middle');
                    } else {
                        $this->impactIcon($c, $t, $cx + 7, $mid, $impact);
                    }
                    if ((bool) $row['speech']) {
                        $this->micIcon($c, $t, $x + $col['x'] + 14, $mid);
                    }
                    break;

                case 'title':
                    $text = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
                    $c->textFit(
                        $text,
                        $x + $col['x'] + $cw - 12,
                        $mid,
                        $cw - 22,
                        11.5,
                        $isHoliday ? $t->c('ink_dim') : $t->c('ink'),
                        Canvas::W_MEDIUM,
                        'right',
                        8.5,
                        1.0,
                        'middle'
                    );
                    break;

                default:
                    $value = (string) ($row[$col['key']] ?? '');
                    $c->textFit(
                        $value === '' ? '–' : $this->dnum($value),
                        $cx,
                        $mid,
                        $cw - 10,
                        10.5,
                        $col['key'] === 'actual' && $value !== '' ? $t->c('ink') : $t->c('ink_dim'),
                        Canvas::W_SEMIBOLD,
                        'center',
                        8,
                        1.0,
                        'middle'
                    );
            }
        }
    }

    /** نشانگر اهمیت: سه میله (پر = اهمیت بیشتر) */
    private function impactIcon(Canvas $c, Theme $t, float $cx, float $cy, int $impact): void
    {
        $shades = $t->isMono()
            ? [1 => '#7E8695', 2 => '#C3C9D3', 3 => '#FFFFFF']
            : [1 => '#F3C13A', 2 => '#F0762B', 3 => '#E1273E'];
        $color = $shades[$impact] ?? $t->c('flat');

        $barW = 4.0;
        $gap = 3.0;
        $totalW = $barW * 3 + $gap * 2;
        $startX = $cx - $totalW / 2;

        for ($i = 0; $i < 3; $i++) {
            $barH = 6.0 + $i * 3.5;
            $filled = $i < $impact;
            $c->roundRect(
                $startX + $i * ($barW + $gap),
                $cy + 6.5 - $barH,
                $barW,
                $barH,
                1.6,
                $filled ? $color : '#FFFFFF',
                $filled ? 1.0 : 0.16
            );
        }
    }

    /** آیکون سخنرانی */
    private function micIcon(Canvas $c, Theme $t, float $cx, float $cy): void
    {
        $col = $t->isMono() ? $t->c('ink_dim') : '#E1273E';
        $c->roundRect($cx - 2.3, $cy - 7.5, 4.6, 8.4, 2.3, $col);
        $c->ring($cx, $cy - 1.6, 4.4, 1.2, $col, 10, 170, 1.0);
        $c->rect($cx - 0.7, $cy + 2.8, 1.4, 3.4, $col);
        $c->rect($cx - 3.2, $cy + 6.2, 6.4, 1.4, $col);
    }

    private function legend(Canvas $c, Theme $t, float $x, float $y, float $w): void
    {
        $h = 30.0;
        $c->glass($x, $y, $w, $h, 10, ['fill' => 0.04, 'border' => 0.10, 'blur' => 2, 'tint' => $t->c('tint')]);

        $mid = $y + $h / 2;
        $cursor = $x + $w - 14;
        $size = 9.5;

        foreach ([[3, 'اهمیت زیاد'], [2, 'اهمیت متوسط'], [1, 'اهمیت کم']] as [$impact, $label]) {
            $this->impactIcon($c, $t, $cursor - 12, $mid, $impact);
            $cursor -= 28;
            $c->text($label, $cursor, $mid, $size, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');
            $cursor -= $c->textWidth($label, $size, Canvas::W_SEMIBOLD) + 18;
        }

        $this->micIcon($c, $t, $cursor - 7, $mid);
        $cursor -= 18;
        $c->text('سخنرانی مقام‌ها', $cursor, $mid, $size, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');

        $c->text('زمان‌ها به وقت ایران', $x + 14, $mid, $size, $t->c('ink_dim'), Canvas::W_BOLD, 'left', 1.0, 'middle');
    }
}
