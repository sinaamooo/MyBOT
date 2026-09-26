<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Settings;
use Nikto\Data\LiquidityProvider;
use Nikto\Render\Card;
use Nikto\Render\LiquidityCard;
use Nikto\Text\Persian;

final class LiquidityJob extends Job
{
    public const KEY = 'liquidity';

    public const LEVELS = [3, 4, 5, 6];

    public function title(): string
    {
        return 'کارت نقدینگی بیت‌کوین';
    }

    public function icon(): string
    {
        return '💧';
    }

    public function description(): string
    {
        return 'دیوارهای پرحجم خرید و فروش بیت‌کوین از دفتر سفارش صرافی‌ها (رایگان) روی نمودار ۴۸ ساعته';
    }

    public function defaultOptions(): array
    {
        return [
            'headline' => 'نقشه‌ی نقدینگی بیت‌کوین',
            'levels'   => 5,
        ];
    }

    public function defaultCaption(): string
    {
        return "💧 <b>نقشه‌ی نقدینگی بیت‌کوین</b>\n\n{summary}\n\n🗓 {date} — ⏰ {time}\n{link}";
    }

    public function levels(): int
    {
        $n = (int) $this->option('levels', 5);

        return in_array($n, self::LEVELS, true) ? $n : 5;
    }

    public function fetch(): mixed
    {
        return LiquidityProvider::btc($this->levels());
    }

    public function card(mixed $data): Card
    {
        return new LiquidityCard((array) $data, $this->theme(), [
            'title' => (string) $this->option('headline', 'نقشه‌ی نقدینگی بیت‌کوین'),
        ]);
    }

    public function summary(mixed $data): string
    {
        $data = (array) $data;
        $levels = static function (array $walls): string {
            $out = [];
            foreach (array_slice($walls, 0, 3) as $w) {
                $out[] = sprintf('%s (%s)', number_format((float) $w['price'], 0), LiquidityProvider::formatBtc((float) $w['qty']));
            }

            return $out === [] ? '—' : implode(' · ', $out);
        };

        $share = (float) ($data['imbalance'] ?? 0.5) * 100;
        $lines = [
            '💰 قیمت: <b>$' . number_format((float) ($data['price'] ?? 0), 1) . '</b>',
            '🟥 مقاومت‌ها: ' . $levels((array) ($data['ask_walls'] ?? [])),
            '🟩 حمایت‌ها: ' . $levels((array) ($data['bid_walls'] ?? [])),
            sprintf('⚖️ توازن: خرید %s%% / فروش %s%%', number_format($share, 1), number_format(100 - $share, 1)),
        ];
        $text = implode("\n", $lines);

        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : $text;
    }
}
