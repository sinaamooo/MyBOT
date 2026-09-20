<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Countries;

/**
 * کارت تقویم اقتصادی — هر رویداد یک ردیف شیشه‌ای مستقل.
 */
final class CalendarCard extends Card
{
    private const ROW_H    = 46.0;
    private const ROW_GAP  = 7.0;
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

        $listH = max(1, count($rows)) * self::ROW_H + max(0, count($rows) - 1) * self::ROW_GAP;
        $noteH = $extra > 0 ? 26.0 : 0.0;
        $height = (int) round(76 + Frame::HEADER_H + 22 + $listH + $noteH + 18 + 38);
        $height = max(420, $height);

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
            $c->glass($rect['x'], $top, $rect['w'], 74, 16, ['tint' => $t->c('tint')]);
            $c->text(
                'امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است',
                $rect['x'] + $rect['w'] / 2,
                $top + 37,
                13,
                $t->c('ink_dim'),
                Canvas::W_SEMIBOLD,
                'center',
                1.0,
                'ink'
            );
            $this->legend($c, $t, $rect['x'], $top + 92, $rect['w']);

            return $c;
        }

        $y = $this->columnLabels($c, $t, $rect['x'], $top, $rect['w']);

        foreach ($rows as $row) {
            $this->row($c, $t, $row, $rect['x'], $y, $rect['w']);
            $y += self::ROW_H + self::ROW_GAP;
        }
        $y -= self::ROW_GAP;

        if ($extra > 0) {
            $c->text(
                '+ ' . $this->tnum((string) $extra) . ' رویداد دیگر در تقویم امروز',
                $rect['x'] + $rect['w'],
                $y + 14,
                10,
                $t->c('ink_faint'),
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

    /** برچسب ستون‌ها بالای فهرست */
    private function columnLabels(Canvas $c, Theme $t, float $x, float $y, float $w): float
    {
        $right = $x + $w;
        $labels = [
            ['زمان', $right - 45, 'center'],
            ['ارز', $right - 114, 'center'],
            ['اهمیت', $right - 168, 'center'],
            ['شرح رویداد', $right - 212, 'right'],
            ['پیش‌بینی', $x + 60, 'center'],
            ['قبلی', $x + 154, 'center'],
        ];

        foreach ($labels as [$text, $lx, $align]) {
            $c->text($text, $lx, $y + 6, 9.5, $t->c('ink_faint'), Canvas::W_SEMIBOLD, $align, 0.85, 'ink');
        }

        return $y + 20;
    }

    private function row(Canvas $c, Theme $t, array $row, float $x, float $y, float $w): void
    {
        $impact = (int) $row['impact'];
        $isHoliday = $impact === 0 || (bool) $row['all_day'];
        $accent = $this->impactColor($t, $impact, $isHoliday);
        $right = $x + $w;
        $mid = $y + self::ROW_H / 2;

        $c->roundRect($x, $y, $w, self::ROW_H, 14, '#FFFFFF', $isHoliday ? 0.028 : 0.05);
        $c->strokeRoundRect($x, $y, $w, self::ROW_H, 14, '#FFFFFF', 1, 0.07);

        // نوار اهمیت در لبه‌ی راست
        $c->roundRect($right - 8, $y + 9, 4, self::ROW_H - 18, 2, $accent, $isHoliday ? 0.4 : 0.95);

        // ── زمان
        $timeText = $isHoliday && (string) $row['time'] === '' ? 'تعطیل' : (string) $row['time'];
        $badgeW = 58.0;
        $badgeX = $right - 16 - $badgeW;
        $c->roundRect($badgeX, $mid - 13, $badgeW, 26, 9, $accent, $isHoliday ? 0.10 : 0.14);
        $c->text(
            (string) $row['time'] === '' ? $timeText : $this->dnum($timeText),
            $badgeX + $badgeW / 2,
            $mid,
            (string) $row['time'] === '' ? 10 : 13,
            $isHoliday ? $t->c('ink_dim') : $t->c('ink'),
            (string) $row['time'] === '' ? Canvas::W_SEMIBOLD : Canvas::W_NUM_BOLD,
            'center',
            1.0,
            'ink'
        );

        // ── پرچم و ارز
        $flagW = 24.0;
        $flagX = $badgeX - 14 - $flagW;
        Flags::draw($c, Countries::code((string) $row['currency']), $flagX, $mid - 8, $flagW, 16);
        $c->text(
            (string) $row['currency'],
            $flagX - 8,
            $mid,
            11,
            $t->c('ink_dim'),
            Canvas::W_NUM_BOLD,
            'right',
            1.0,
            'ink'
        );

        // ── اهمیت و آیکون سخنرانی
        $impactCx = $flagX - 56;
        if ($isHoliday) {
            $c->text('—', $impactCx, $mid, 10, $t->c('ink_faint'), Canvas::W_BOLD, 'center', 1.0, 'ink');
        } else {
            $this->impactIcon($c, $t, $impactCx, $mid, $impact);
        }
        if ((bool) $row['speech']) {
            $this->micIcon($c, $t, $impactCx - 26, $mid);
        }

        // ── مقدارهای قبلی و پیش‌بینی
        $forecast = (string) ($row['forecast'] ?? '');
        $previous = (string) ($row['previous'] ?? '');
        $this->stat($c, $t, $x + 18, $mid, 84, $forecast, $forecast !== '' ? $t->c('ink') : $t->c('ink_faint'));
        $this->stat($c, $t, $x + 114, $mid, 80, $previous, $t->c('ink_dim'));

        // ── شرح رویداد
        $titleRight = $impactCx - 44;
        $titleLeft = $x + 206;
        $text = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
        $c->textFit(
            $text,
            $titleRight,
            $mid,
            max(160.0, $titleRight - $titleLeft),
            12.5,
            $isHoliday ? $t->c('ink_dim') : $t->c('ink'),
            Canvas::W_MEDIUM,
            'right',
            9,
            1.0,
            'ink'
        );
    }

    /** خانه‌ی مقدار (برچسب‌ها فقط بالای فهرست نوشته می‌شوند) */
    private function stat(Canvas $c, Theme $t, float $x, float $mid, float $w, string $value, string $color): void
    {
        $c->textFit(
            $value === '' ? '–' : $this->dnum($value),
            $x + $w / 2,
            $mid,
            $w,
            12.5,
            $color,
            Canvas::W_NUM_BOLD,
            'center',
            8,
            1.0,
            'ink'
        );
    }

    private function impactColor(Theme $t, int $impact, bool $isHoliday): string
    {
        if ($isHoliday) {
            return $t->c('flat');
        }

        return match ($impact) {
            3       => $t->c('accent'),
            2       => $t->c('accent_alt'),
            default => $t->c('ink_faint'),
        };
    }

    /** سه میله‌ی اهمیت */
    private function impactIcon(Canvas $c, Theme $t, float $cx, float $cy, int $impact): void
    {
        $color = $this->impactColor($t, $impact, false);
        $barW = 4.0;
        $gap = 3.5;
        $totalW = $barW * 3 + $gap * 2;
        $startX = $cx - $totalW / 2;

        for ($i = 0; $i < 3; $i++) {
            $barH = 6.5 + $i * 3.8;
            $filled = $i < $impact;
            $c->roundRect(
                $startX + $i * ($barW + $gap),
                $cy + 7 - $barH,
                $barW,
                $barH,
                1.6,
                $filled ? $color : '#FFFFFF',
                $filled ? 1.0 : 0.15
            );
        }
    }

    private function micIcon(Canvas $c, Theme $t, float $cx, float $cy): void
    {
        $col = $t->c('accent_alt');
        $c->roundRect($cx - 2.3, $cy - 7.5, 4.6, 8.4, 2.3, $col);
        $c->ring($cx, $cy - 1.6, 4.4, 1.2, $col, 10, 170, 1.0);
        $c->rect($cx - 0.7, $cy + 2.8, 1.4, 3.4, $col);
        $c->rect($cx - 3.2, $cy + 6.2, 6.4, 1.4, $col);
    }

    private function legend(Canvas $c, Theme $t, float $x, float $y, float $w): void
    {
        $h = 32.0;
        $c->roundRect($x, $y, $w, $h, 12, '#FFFFFF', 0.035);
        $c->strokeRoundRect($x, $y, $w, $h, 12, '#FFFFFF', 1, 0.07);

        $mid = $y + $h / 2;
        $cursor = $x + $w - 16;
        $size = 9.5;

        foreach ([[3, 'اهمیت زیاد'], [2, 'اهمیت متوسط'], [1, 'اهمیت کم']] as [$impact, $label]) {
            $this->impactIcon($c, $t, $cursor - 12, $mid, $impact);
            $cursor -= 28;
            $c->text($label, $cursor, $mid, $size, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'right', 1.0, 'ink');
            $cursor -= $c->textWidth($label, $size, Canvas::W_SEMIBOLD) + 20;
        }

        $this->micIcon($c, $t, $cursor - 7, $mid);
        $cursor -= 18;
        $c->text('سخنرانی مقام‌ها', $cursor, $mid, $size, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'right', 1.0, 'ink');

        $c->text('زمان‌ها به وقت ایران', $x + 16, $mid, $size, $t->c('ink_dim'), Canvas::W_BOLD, 'left', 1.0, 'ink');
    }
}
