<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Settings;
use Nikto\Data\PriceProvider;
use Nikto\Render\Card;
use Nikto\Render\PriceCard;
use Nikto\Text\Persian;

/**
 * کارت نوسان ۲۴ ساعته‌ی ارزهای منتخب.
 */
final class PricesJob extends Job
{
    public const KEY = 'prices';

    public function title(): string
    {
        return 'کارت نوسان ارزها';
    }

    public function icon(): string
    {
        return '📈';
    }

    public function description(): string
    {
        return 'قیمت و درصد تغییر ۲۴ ساعته‌ی ارزهای انتخابی همراه با نمودار کوچک';
    }

    public function defaultTheme(): string
    {
        return 'mono';
    }

    public function defaultOptions(): array
    {
        return [
            'coins'     => Settings::get('coins'),
            'headline'  => 'نوسان ۲۴ ساعته بازار',
            'sparkline' => true,
        ];
    }

    public function defaultCaption(): string
    {
        return "📊 <b>نوسان ۲۴ ساعته بازار رمزارز</b>\n\n{summary}\n\n🗓 {date} — ⏰ {time}\n{link}";
    }

    /** @return string[] */
    public function coins(): array
    {
        $raw = (string) $this->option('coins', Settings::get('coins'));
        $list = array_values(array_filter(array_map(
            static fn ($s) => strtoupper(trim($s)),
            explode(',', $raw)
        )));

        return array_slice($list ?: Settings::coins(), 0, 9);
    }

    public function fetch(): mixed
    {
        $rows = PriceProvider::fetch($this->coins(), (bool) $this->option('sparkline', true));

        return $rows === [] ? null : $rows;
    }

    public function card(mixed $data): Card
    {
        return new PriceCard((array) $data, $this->theme(), [
            'title' => (string) $this->option('headline', 'نوسان ۲۴ ساعته بازار'),
        ]);
    }

    public function summary(mixed $data): string
    {
        $lines = [];
        foreach ((array) $data as $coin) {
            $pct = (float) $coin['change_pct'];
            $icon = $pct > 0.005 ? '🟢' : ($pct < -0.005 ? '🔴' : '⚪️');
            $sign = $pct > 0 ? '+' : '';
            $lines[] = sprintf(
                '%s <b>%s</b> — $%s (%s%s%%)',
                $icon,
                $coin['symbol'],
                PriceProvider::formatPrice((float) $coin['price']),
                $sign,
                number_format($pct, 2)
            );
        }
        $text = implode("\n", $lines);

        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : $text;
    }
}
