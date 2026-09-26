<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Countries;

final class CalendarCard extends Card
{
    private const ROW_H     = 46.0;
    private const HEAD_H    = 32.0;
    private const MAX_ROWS  = 18;
    private const TABLE_PAD = 10.0;

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
        $noteH = $extra > 0 ? 30.0 : 0.0;
        $height = (int) round(84 + Frame::HEADER_H + $tableH + $noteH);

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
            $this->tnum((string) count($this->rows)) . ' رویداد · به وقت ایران'
        );

        Frame::panel($c, $t, $rect['x'], $top, $rect['w'], $tableH, 18);

        if ($rows === []) {
            $c->text(
                'امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است',
                $rect['x'] + $rect['w'] / 2,
                $top + $tableH / 2,
                13,
                $t->c('ink_dim'),
                Canvas::W_SEMIBOLD,
                'center',
                1.0,
                'ink'
            );

            return $c;
        }

        $cols = $this->columns($rect['x'], $rect['w']);
        $y = $this->headRow($c, $t, $rect['x'] + self::TABLE_PAD, $top + self::TABLE_PAD, $rect['w'] - self::TABLE_PAD * 2, $cols);

        foreach ($rows as $i => $row) {
            if ($i > 0) {
                $c->rect($rect['x'] + self::TABLE_PAD, $y, $rect['w'] - self::TABLE_PAD * 2, 1, $t->c('line'), 0.06);
            }
            $this->row($c, $t, $row, $rect['x'] + self::TABLE_PAD, $y, $rect['w'] - self::TABLE_PAD * 2, $cols);
            $y += self::ROW_H;
        }

        if ($extra > 0) {
            $c->text(
                '+ ' . $this->tnum((string) $extra) . ' رویداد دیگر',
                $rect['x'] + $rect['w'],
                $top + $tableH + 16,
                10.5,
                $t->c('ink_faint'),
                Canvas::W_MEDIUM,
                'right',
                1.0,
                'middle'
            );
        }

        return $c;
    }

    private function columns(float $x, float $w): array
    {
        $right = $x + $w;

        return [
            'time'     => $right - 44,
            'flag'     => $right - 102,
            'currency' => $right - 120,
            'impact'   => $right - 176,
            'title'    => $right - 218,
            'forecast' => $x + 142,
            'previous' => $x + 52,
            'head_cur' => $right - 124,
        ];
    }

    private function headRow(Canvas $c, Theme $t, float $x, float $y, float $w, array $cols): float
    {
        $mid = $y + self::HEAD_H / 2;
        $labels = [
            ['زمان', $cols['time'], 'center'],
            ['ارز', $cols['head_cur'], 'center'],
            ['اهمیت', $cols['impact'], 'center'],
            ['رویداد', $cols['title'], 'right'],
            ['پیش‌بینی', $cols['forecast'], 'center'],
            ['قبلی', $cols['previous'], 'center'],
        ];

        foreach ($labels as [$text, $lx, $align]) {
            $c->text($text, $lx, $mid, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, $align, 1.0, 'middle');
        }

        $c->rect($x, $y + self::HEAD_H, $w, 1, $t->c('line'), 0.10);

        return $y + self::HEAD_H;
    }

    private function row(Canvas $c, Theme $t, array $row, float $x, float $y, float $w, array $cols): void
    {
        $impact = (int) $row['impact'];
        $isHoliday = $impact === 0 || (bool) $row['all_day'];
        $mid = $y + self::ROW_H / 2;

        $time = (string) $row['time'];
        if ($time !== '') {
            $c->text($this->dnum($time), $cols['time'], $mid, 13, $t->c('ink'), Canvas::W_NUM_BOLD, 'center', 1.0, 'num');
        } else {
            $c->text('تعطیل', $cols['time'], $mid, 10.5, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle');
        }

        Flags::draw($c, Countries::code((string) $row['currency']), $cols['flag'] - 11, $mid - 7.5, 22, 15);
        $c->text((string) $row['currency'], $cols['currency'], $mid, 11, $t->c('ink_dim'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');

        if (!$isHoliday) {
            $this->impactDots($c, $t, $cols['impact'], $mid, $impact);
        }

        $forecast = (string) ($row['forecast'] ?? '');
        $previous = (string) ($row['previous'] ?? '');
        $this->stat($c, $cols['forecast'], $mid, 88, $forecast, $forecast === '' ? $t->c('ink_faint') : $t->c('ink'), Canvas::W_NUM_BOLD);
        $this->stat($c, $cols['previous'], $mid, 84, $previous, $t->c('ink_faint'), Canvas::W_NUM);

        $titleLeft = $cols['forecast'] + 56;
        $text = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
        $c->textFit(
            $text,
            $cols['title'],
            $mid,
            max(160.0, $cols['title'] - $titleLeft),
            12.5,
            $isHoliday ? $t->c('ink_faint') : $t->c('ink'),
            Canvas::W_MEDIUM,
            'right',
            9,
            1.0,
            'ink'
        );
    }

    private function stat(Canvas $c, float $cx, float $mid, float $w, string $value, string $color, string $weight): void
    {
        $c->textFit($value === '' ? '–' : $this->dnum($value), $cx, $mid, $w, 12, $color, $weight, 'center', 8, 1.0, 'num');
    }

    private function impactDots(Canvas $c, Theme $t, float $cx, float $cy, int $impact): void
    {
        $filled = $impact >= 3 ? $t->c('down') : $t->c('ink');
        for ($i = 0; $i < 3; $i++) {
            $c->circle($cx + ($i - 1) * 9, $cy, 3, $i < $impact ? $filled : $t->c('line_soft'));
        }
    }
}
