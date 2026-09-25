<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Settings;
use Nikto\Data\CalendarProvider;
use Nikto\Render\CalendarCard;
use Nikto\Render\Card;
use Nikto\Render\Theme;
use Nikto\Text\Persian;

final class CalendarJob extends Job
{
    public const KEY = 'calendar';

    public function title(): string
    {
        return 'تقویم اقتصادی';
    }

    public function icon(): string
    {
        return '📅';
    }

    public function description(): string
    {
        return 'رویدادهای اقتصادی امروز با اهمیت، مقدار قبلی و پیش‌بینی';
    }

    public function defaultTheme(): string
    {
        return Theme::DEFAULT;
    }

    public function defaultOptions(): array
    {
        return [
            'headline'  => 'تقویم اقتصادی امروز',
            'day_shift' => 0,
        ];
    }

    public function defaultCaption(): string
    {
        return "📅 <b>تقویم اقتصادی امروز</b>\n\n{summary}\n\n🗓 {date}\n{link}";
    }

    public function fetch(): mixed
    {
        $shift = (int) $this->option('day_shift', 0);
        $day = Settings::now();
        if ($shift !== 0) {
            $day = $day->modify(sprintf('%+d day', $shift));
        }

        return CalendarProvider::forDay($day);
    }

    public function card(mixed $data): Card
    {
        $shift = (int) $this->option('day_shift', 0);
        $now = Settings::now();
        if ($shift !== 0) {
            $now = $now->modify(sprintf('%+d day', $shift));
        }

        return new CalendarCard((array) $data, $this->theme(), [
            'title' => (string) $this->option('headline', 'تقویم اقتصادی امروز'),
            'now'   => $now,
        ]);
    }

    public function summary(mixed $data): string
    {
        $rows = (array) $data;
        if ($rows === []) {
            return 'امروز رویداد مهمی در تقویم اقتصادی ثبت نشده است.';
        }

        $important = array_values(array_filter($rows, static fn ($r) => (int) $r['impact'] >= 3));
        usort($important, static fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        $important = array_slice($important, 0, 5);

        $lines = ['🔔 مهم‌ترین رویدادهای امروز:'];
        foreach ($important as $row) {
            $time = $row['all_day'] ? 'تعطیل' : (string) $row['time'];
            $title = (string) ($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']);
            $lines[] = sprintf('• <b>%s</b> | %s — %s', $time, $row['currency'], $title);
        }
        $lines[] = '';
        $lines[] = sprintf('مجموع رویدادها: %d — زمان‌ها به وقت ایران', count($rows));

        $text = implode("\n", $lines);

        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : $text;
    }
}
