<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Settings;
use Nikto\Data\FearGreedProvider;
use Nikto\Render\Card;
use Nikto\Render\Theme;
use Nikto\Render\FearGreedCard;
use Nikto\Text\Persian;

final class FearGreedJob extends Job
{
    public const KEY = 'feargreed';

    public function title(): string
    {
        return 'شاخص ترس و طمع';
    }

    public function icon(): string
    {
        return '😨';
    }

    public function description(): string
    {
        return 'سنجه‌ی شاخص ترس و طمع همراه با مقادیر دیروز، هفته و ماه گذشته';
    }

    public function defaultTheme(): string
    {
        return Theme::DEFAULT;
    }

    public function defaultCaption(): string
    {
        return "😨 <b>شاخص ترس و طمع بازار</b>\n\n{summary}\n\n🗓 {date} — ⏰ {time}\n{link}";
    }

    public function fetch(): mixed
    {
        return FearGreedProvider::fetch();
    }

    public function card(mixed $data): Card
    {
        return new FearGreedCard((array) $data, $this->theme());
    }

    public function summary(mixed $data): string
    {
        $data = (array) $data;
        $zone = $data['zone'] ?? ['fa' => '', 'en' => ''];
        $emoji = match (true) {
            $data['value'] <= 24 => '🟥',
            $data['value'] <= 44 => '🟧',
            $data['value'] <= 55 => '🟨',
            $data['value'] <= 75 => '🟩',
            default              => '🟢',
        };

        $lines = [sprintf('%s شاخص امروز: <b>%d</b> — %s', $emoji, $data['value'], $zone['fa'])];
        $labels = ['yesterday' => 'دیروز', 'week' => '۷ روز پیش', 'month' => '۱ ماه پیش'];
        foreach ($labels as $key => $label) {
            if (isset($data['history'][$key])) {
                $lines[] = sprintf(
                    '• %s: %d (%s)',
                    $label,
                    $data['history'][$key]['value'],
                    $data['history'][$key]['zone']['fa']
                );
            }
        }
        $text = implode("\n", $lines);

        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : $text;
    }
}
