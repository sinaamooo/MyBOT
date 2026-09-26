<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Settings;
use Nikto\Data\CoinGlass;
use Nikto\Data\Exchanges;
use Nikto\Data\FuturesProvider;
use Nikto\Render\Card;
use Nikto\Render\MoversCard;
use Nikto\Text\Persian;

final class MoversJob extends Job
{
    public const KEY = 'movers';

    public const COUNTS = [3, 4, 5, 6];

    public const VOLUMES = [1_000_000, 5_000_000, 20_000_000, 50_000_000];

    public function title(): string
    {
        return 'کارت برترین‌های فیوچرز';
    }

    public function icon(): string
    {
        return '🚀';
    }

    public function description(): string
    {
        return 'بیشترین رشد و بیشترین افت ۲۴ ساعته‌ی قراردادهای فیوچرز بایننس';
    }

    public function defaultOptions(): array
    {
        return [
            'headline'   => 'برترین‌های فیوچرز بایننس',
            'count'      => 5,
            'min_volume' => 5_000_000,
            'sparkline'  => true,
        ];
    }

    public function defaultCaption(): string
    {
        return "🚀 <b>برترین‌های فیوچرز بایننس در ۲۴ ساعت گذشته</b>\n\n{summary}\n\n🗓 {date} — ⏰ {time}\n{link}";
    }

    public function count(): int
    {
        $n = (int) $this->option('count', 5);

        return in_array($n, self::COUNTS, true) ? $n : 5;
    }

    public function minVolume(): float
    {
        return (float) $this->option('min_volume', 5_000_000);
    }

    public function fetch(): mixed
    {
        return FuturesProvider::movers($this->count(), $this->minVolume(), (bool) $this->option('sparkline', true));
    }

    public function card(mixed $data): Card
    {
        return new MoversCard((array) $data, $this->theme(), [
            'title' => $this->withSource((string) $this->option('headline', 'برترین‌های فیوچرز بایننس'), $data),
        ]);
    }

    public function renderCaption(mixed $data): string
    {
        return $this->withSource(parent::renderCaption($data), $data);
    }

    private function withSource(string $text, mixed $data): string
    {
        $exchange = (string) (((array) $data)['exchange'] ?? 'Binance');
        $name = $exchange === 'CoinGlass' ? CoinGlass::NAME_FA : (Exchanges::NAMES_FA[$exchange] ?? $exchange);
        if ($exchange === 'Binance') {
            return $text;
        }

        return str_replace('فیوچرز بایننس', 'فیوچرز ' . $name, $text);
    }

    public function summary(mixed $data): string
    {
        $data = (array) $data;
        $lines = ['🟢 <b>بیشترین رشد</b>'];
        foreach ((array) ($data['gainers'] ?? []) as $i => $row) {
            $lines[] = $this->line($i + 1, $row);
        }
        $lines[] = '';
        $lines[] = '🔴 <b>بیشترین افت</b>';
        foreach ((array) ($data['losers'] ?? []) as $i => $row) {
            $lines[] = $this->line($i + 1, $row);
        }

        $b = (array) ($data['breadth'] ?? []);
        if (isset($b['total'])) {
            $lines[] = '';
            $lines[] = sprintf(
                '📊 %s صعودی · %s نزولی از %s قرارداد',
                Persian::faDigits((string) (int) $b['up']),
                Persian::faDigits((string) (int) $b['down']),
                Persian::faDigits((string) (int) $b['total'])
            );
        }

        return implode("\n", $lines);
    }

    private function line(int $rank, array $row): string
    {
        $pct = (float) $row['change_pct'];
        $text = sprintf('%d. <b>%s</b> %s%s%%', $rank, htmlspecialchars((string) $row['symbol']), $pct > 0 ? '+' : '', number_format($pct, 2));

        return Settings::get('digits_data') === 'fa' ? Persian::faDigits($text) : $text;
    }
}
