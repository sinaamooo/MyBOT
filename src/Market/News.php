<?php

declare(strict_types=1);

namespace App\Market;

use App\Support\Http;

/**
 * Recent crypto headlines from public RSS feeds, filtered by coin symbol / name.
 */
final class News
{
    private const NAMES = [
        'BTC' => ['Bitcoin'], 'ETH' => ['Ethereum', 'Ether'], 'SOL' => ['Solana'], 'BNB' => ['BNB', 'Binance Coin'],
        'XRP' => ['XRP', 'Ripple'], 'DOGE' => ['Dogecoin'], 'TON' => ['Toncoin', 'TON'], 'ADA' => ['Cardano'],
        'AVAX' => ['Avalanche'], 'LINK' => ['Chainlink'], 'TRX' => ['Tron'], 'DOT' => ['Polkadot'],
        'SHIB' => ['Shiba Inu'], 'PEPE' => ['Pepe'], 'LTC' => ['Litecoin'], 'BCH' => ['Bitcoin Cash'],
        'NEAR' => ['NEAR Protocol'], 'APT' => ['Aptos'], 'SUI' => ['Sui'], 'ARB' => ['Arbitrum'], 'OP' => ['Optimism'],
        'ATOM' => ['Cosmos'], 'UNI' => ['Uniswap'], 'XLM' => ['Stellar'], 'ETC' => ['Ethereum Classic'],
        'FIL' => ['Filecoin'], 'INJ' => ['Injective'], 'TIA' => ['Celestia'], 'WIF' => ['dogwifhat'],
        'HBAR' => ['Hedera'], 'XMR' => ['Monero'], 'AAVE' => ['Aave'], 'MKR' => ['Maker'], 'RNDR' => ['Render'],
        'TAO' => ['Bittensor'], 'ONDO' => ['Ondo'], 'ENA' => ['Ethena'], 'HYPE' => ['Hyperliquid'], 'TRUMP' => ['TRUMP memecoin'],
    ];

    public function __construct(private array $config, private string $cacheDir)
    {
    }

    /** @return array<int, array{title: string, link: string, source: string, time: int}> */
    public function forCoin(string $base): array
    {
        if (empty($this->config['enabled'])) {
            return [];
        }
        $base = strtoupper($base);
        $terms = array_merge([$base], self::NAMES[$base] ?? []);
        // Short tickers like "OP" or "TON" are only matched as whole uppercase words.
        $patterns = [];
        foreach ($terms as $t) {
            $patterns[] = strlen($t) <= 4 && strtoupper($t) === $t
                ? '/(?<![A-Za-z$])\$?' . preg_quote($t, '/') . '(?![A-Za-z])/'
                : '/\b' . preg_quote($t, '/') . '\b/i';
        }
        $maxAge = (int) ($this->config['max_age_hours'] ?? 72) * 3600;
        $out = [];
        foreach ($this->items() as $item) {
            if ($item['time'] < time() - $maxAge) {
                continue;
            }
            foreach ($patterns as $p) {
                if (preg_match($p, $item['title'])) {
                    $out[] = $item;
                    break;
                }
            }
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);
        return array_slice($out, 0, 4);
    }

    private function items(): array
    {
        $cache = $this->cacheDir . '/news.json';
        if (is_file($cache) && filemtime($cache) > time() - 900) {
            $data = json_decode((string) file_get_contents($cache), true);
            if (is_array($data)) {
                return $data;
            }
        }
        $items = [];
        foreach ($this->config['feeds'] ?? [] as $url) {
            $res = Http::request('GET', $url, ['timeout' => 8]);
            if ($res['status'] !== 200 || $res['body'] === '') {
                continue;
            }
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($res['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
            libxml_use_internal_errors($prev);
            if ($xml === false) {
                continue;
            }
            $host = parse_url($url, PHP_URL_HOST) ?: $url;
            foreach ($xml->channel->item ?? [] as $it) {
                $title = trim(html_entity_decode((string) $it->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($title === '') {
                    continue;
                }
                $items[] = [
                    'title' => $title,
                    'link' => (string) $it->link,
                    'source' => preg_replace('/^www\./', '', $host),
                    'time' => strtotime((string) $it->pubDate) ?: time(),
                ];
            }
        }
        if ($items) {
            @file_put_contents($cache, json_encode($items, JSON_UNESCAPED_UNICODE));
        }
        return $items;
    }
}
