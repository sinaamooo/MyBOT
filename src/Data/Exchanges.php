<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;

final class Exchanges
{
    public const SPOT    = ['OKX', 'Bybit', 'MEXC', 'KuCoin', 'Gate.io', 'Nobitex'];
    public const FUTURES = ['Bybit', 'OKX', 'Bitget', 'Gate.io', 'MEXC', 'KuCoin', 'BingX', 'CoinEx', 'HTX'];
    public const BOOKS   = [
        'Hyperliquid' => 'Hyperliquid',
        'OKX'         => 'OKX Spot',
        'MEXC'        => 'MEXC Spot',
        'Bybit'       => 'Bybit Futures',
        'Bitget'      => 'Bitget Spot',
        'KuCoin'      => 'KuCoin Spot',
        'Nobitex'     => 'Nobitex',
    ];

    public const NAMES_FA = [
        'Binance' => 'بایننس',
        'Bybit'   => 'بای‌بیت',
        'OKX'     => 'اوکی‌ایکس',
        'Gate.io' => 'گیت',
        'MEXC'    => 'مکسی',
        'KuCoin'  => 'کوکوین',
        'Nobitex' => 'نوبیتکس',
        'Bitget'  => 'بیت‌گت',
        'BingX'   => 'بینگ‌ایکس',
        'CoinEx'  => 'کوینکس',
        'HTX'     => 'اچ‌تی‌ایکس',
        'Hyperliquid' => 'هایپرلیکوئید',
    ];

    private const OKX      = 'https://www.okx.com/api/v5/market';
    private const BYBIT    = 'https://api.bybit.com/v5/market';
    private const MEXC     = 'https://api.mexc.com/api/v3';
    private const MEXC_F   = 'https://contract.mexc.com/api/v1/contract';
    private const KUCOIN   = 'https://api.kucoin.com/api/v1';
    private const KUCOIN_F = 'https://api-futures.kucoin.com/api/v1';
    private const GATE     = 'https://api.gateio.ws/api/v4';
    private const NOBITEX  = 'https://api.nobitex.ir';
    private const BITGET   = 'https://api.bitget.com/api/v2/mix/market';
    private const BINGX    = 'https://open-api.bingx.com/openApi/swap';
    private const COINEX   = 'https://api.coinex.com/v2/futures';
    private const HTX      = 'https://api.hbdm.com/linear-swap-ex/market';
    private const BITGET_SPOT = 'https://api.bitget.com/api/v2/spot/market';
    private const HYPERLIQUID = 'https://api.hyperliquid.xyz/info';

    public static function ticker(string $pair, float $last, float $changePct, float $high, float $low, float $quoteVolume): array
    {
        $prev = $changePct > -100 ? $last / (1 + $changePct / 100) : 0.0;

        return [
            'symbol'             => $pair,
            'lastPrice'          => $last,
            'priceChangePercent' => $changePct,
            'priceChange'        => $last - $prev,
            'highPrice'          => $high,
            'lowPrice'           => $low,
            'quoteVolume'        => $quoteVolume,
            'count'              => 1,
        ];
    }

    public static function spotTickers(string $exchange, array $bases = []): array
    {
        $rows = match ($exchange) {
            'OKX'     => self::okx('SPOT'),
            'Bybit'   => self::bybit('spot'),
            'MEXC'    => self::mexc(),
            'KuCoin'  => self::kucoin(),
            'Gate.io' => self::gate('/spot/tickers', 'currency_pair', 'quote_volume'),
            'Nobitex' => self::nobitex($bases),
            default   => [],
        };

        return array_column($rows, null, 'symbol');
    }

    public static function futuresTickers(string $exchange): array
    {
        return match ($exchange) {
            'Bybit'   => self::bybit('linear'),
            'OKX'     => self::okx('SWAP'),
            'Gate.io' => self::gate('/futures/usdt/tickers', 'contract', 'volume_24h_quote'),
            'MEXC'    => self::mexcFutures(),
            'KuCoin'  => self::kucoinFutures(),
            'Bitget'  => self::bitget(),
            'BingX'   => self::bingx(),
            'CoinEx'  => self::coinex(),
            'HTX'     => self::htx(),
            default   => [],
        };
    }

    public static function book(string $exchange, string $pair): ?array
    {
        $base = substr($pair, 0, -4);
        [$bids, $asks] = match ($exchange) {
            'Hyperliquid' => self::hyperliquidBook($base),
            'OKX'     => self::sides(Http::getJson(self::OKX . '/books-full?instId=' . $base . '-USDT&sz=5000', [], 20, 1)['data'][0] ?? null, 'bids', 'asks'),
            'Bitget'  => self::sides(Http::getJson(self::BITGET_SPOT . '/orderbook?symbol=' . $pair . '&limit=150', [], 15, 1)['data'] ?? null, 'bids', 'asks'),
            'Bybit'   => self::sides(Http::getJson(self::BYBIT . '/orderbook?category=linear&symbol=' . $pair . '&limit=500', [], 15, 1)['result'] ?? null, 'b', 'a'),
            'MEXC'    => self::sides(Http::getJson(self::MEXC . '/depth?symbol=' . $pair . '&limit=5000', [], 20, 1), 'bids', 'asks'),
            'KuCoin'  => self::sides(Http::getJson(self::KUCOIN . '/market/orderbook/level2_100?symbol=' . $base . '-USDT', [], 15, 1)['data'] ?? null, 'bids', 'asks'),
            'Nobitex' => self::sides(Http::getJson(self::NOBITEX . '/v3/orderbook/' . $pair, [], 15, 1), 'bids', 'asks'),
            default   => [[], []],
        };

        return $bids !== [] && $asks !== [] ? ['bids' => $bids, 'asks' => $asks] : null;
    }

    public static function klines(string $exchange, string $market, string $pair, int $limit): array
    {
        $base = substr($pair, 0, -4);
        $futures = $market === 'futures';
        $out = [];

        switch ($exchange) {
            case 'OKX':
                $inst = $base . '-USDT' . ($futures ? '-SWAP' : '');
                $rows = Http::getJson(self::OKX . '/candles?instId=' . $inst . '&bar=1H&limit=' . $limit, [], 12, 0)['data'] ?? null;
                foreach (array_reverse(is_array($rows) ? $rows : []) as $k) {
                    $out[] = self::candle($k[0] ?? null, $k[1] ?? null, $k[2] ?? null, $k[3] ?? null, $k[4] ?? null);
                }
                break;
            case 'Bybit':
                $rows = Http::getJson(self::BYBIT . '/kline?category=' . ($futures ? 'linear' : 'spot') . '&symbol=' . $pair . '&interval=60&limit=' . $limit, [], 12, 0)['result']['list'] ?? null;
                foreach (array_reverse(is_array($rows) ? $rows : []) as $k) {
                    $out[] = self::candle($k[0] ?? null, $k[1] ?? null, $k[2] ?? null, $k[3] ?? null, $k[4] ?? null);
                }
                break;
            case 'MEXC':
                $rows = Http::getJson(self::MEXC . '/klines?symbol=' . $pair . '&interval=60m&limit=' . $limit, [], 12, 0);
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle($k[0] ?? null, $k[1] ?? null, $k[2] ?? null, $k[3] ?? null, $k[4] ?? null);
                }
                break;
            case 'KuCoin':
                $end = time();
                $rows = Http::getJson(self::KUCOIN . '/market/candles?type=1hour&symbol=' . $base . '-USDT&startAt=' . ($end - ($limit + 1) * 3600) . '&endAt=' . $end, [], 12, 0)['data'] ?? null;
                foreach (array_reverse(is_array($rows) ? $rows : []) as $k) {
                    $out[] = self::candle(isset($k[0]) ? (float) $k[0] * 1000 : null, $k[1] ?? null, $k[3] ?? null, $k[4] ?? null, $k[2] ?? null);
                }
                break;
            case 'Gate.io':
                if ($futures) {
                    $rows = Http::getJson(self::GATE . '/futures/usdt/candlesticks?contract=' . $base . '_USDT&interval=1h&limit=' . $limit, [], 12, 0);
                    foreach (is_array($rows) ? $rows : [] as $k) {
                        $out[] = self::candle(isset($k['t']) ? (float) $k['t'] * 1000 : null, $k['o'] ?? null, $k['h'] ?? null, $k['l'] ?? null, $k['c'] ?? null);
                    }
                } else {
                    $rows = Http::getJson(self::GATE . '/spot/candlesticks?currency_pair=' . $base . '_USDT&interval=1h&limit=' . $limit, [], 12, 0);
                    foreach (is_array($rows) ? $rows : [] as $k) {
                        $out[] = self::candle(isset($k[0]) ? (float) $k[0] * 1000 : null, $k[5] ?? null, $k[3] ?? null, $k[4] ?? null, $k[2] ?? null);
                    }
                }
                break;
            case 'Bitget':
                $rows = Http::getJson(self::BITGET . '/candles?symbol=' . $pair . '&productType=USDT-FUTURES&granularity=1H&limit=' . $limit, [], 12, 0)['data'] ?? null;
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle($k[0] ?? null, $k[1] ?? null, $k[2] ?? null, $k[3] ?? null, $k[4] ?? null);
                }
                break;
            case 'BingX':
                $rows = Http::getJson(self::BINGX . '/v3/quote/klines?symbol=' . $base . '-USDT&interval=1h&limit=' . $limit, [], 12, 0)['data'] ?? null;
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle($k['time'] ?? null, $k['open'] ?? null, $k['high'] ?? null, $k['low'] ?? null, $k['close'] ?? null);
                }
                break;
            case 'CoinEx':
                $rows = Http::getJson(self::COINEX . '/kline?market=' . $pair . '&period=1hour&limit=' . $limit, [], 12, 0)['data'] ?? null;
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle($k['created_at'] ?? null, $k['open'] ?? null, $k['high'] ?? null, $k['low'] ?? null, $k['close'] ?? null);
                }
                break;
            case 'HTX':
                $rows = Http::getJson(self::HTX . '/history/kline?contract_code=' . $base . '-USDT&period=60min&size=' . $limit, [], 12, 0)['data'] ?? null;
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle(isset($k['id']) ? (float) $k['id'] * 1000 : null, $k['open'] ?? null, $k['high'] ?? null, $k['low'] ?? null, $k['close'] ?? null);
                }
                break;
            case 'Hyperliquid':
                $end = time() * 1000;
                $rows = Http::postJson(self::HYPERLIQUID, [
                    'type' => 'candleSnapshot',
                    'req'  => ['coin' => $base, 'interval' => '1h', 'startTime' => $end - ($limit + 1) * 3600000, 'endTime' => $end],
                ], 12, 0);
                foreach (is_array($rows) ? $rows : [] as $k) {
                    $out[] = self::candle($k['t'] ?? null, $k['o'] ?? null, $k['h'] ?? null, $k['l'] ?? null, $k['c'] ?? null);
                }
                break;
            case 'Nobitex':
                $end = time();
                $data = Http::getJson(self::NOBITEX . '/market/udf/history?symbol=' . $pair . '&resolution=60&from=' . ($end - ($limit + 1) * 3600) . '&to=' . $end, [], 12, 0);
                foreach ((array) ($data['t'] ?? []) as $i => $t) {
                    $out[] = self::candle((float) $t * 1000, $data['o'][$i] ?? null, $data['h'][$i] ?? null, $data['l'][$i] ?? null, $data['c'][$i] ?? null);
                }
                break;
        }

        $out = array_values(array_filter($out));
        usort($out, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_slice($out, -$limit);
    }

    private static function hyperliquidBook(string $coin): array
    {
        $fetch = static function (?int $sigFigs) use ($coin): array {
            $body = ['type' => 'l2Book', 'coin' => $coin] + ($sigFigs !== null ? ['nSigFigs' => $sigFigs] : []);
            $levels = Http::postJson(self::HYPERLIQUID, $body, 15, 1)['levels'] ?? null;
            $side = static function (mixed $rows): array {
                $out = [];
                foreach (is_array($rows) ? $rows : [] as $r) {
                    if (is_array($r) && is_numeric($r['px'] ?? null) && is_numeric($r['sz'] ?? null)) {
                        $out[] = [(float) $r['px'], (float) $r['sz']];
                    }
                }
                return $out;
            };
            return is_array($levels) ? [$side($levels[0] ?? null), $side($levels[1] ?? null)] : [[], []];
        };

        [$bids, $asks] = $fetch(null);
        if ($bids === [] || $asks === []) {
            return [[], []];
        }
        $mid = (max(array_column($bids, 0)) + min(array_column($asks, 0))) / 2;
        $digits = (int) floor(log10(max($mid, 1))) + 1;
        $grouped = $fetch(max(2, min(5, $digits - 2)));

        return $grouped[0] !== [] && $grouped[1] !== [] ? $grouped : [$bids, $asks];
    }

    private static function candle(mixed $t, mixed $o, mixed $h, mixed $l, mixed $c): ?array
    {
        foreach ([$t, $o, $h, $l, $c] as $v) {
            if (!is_numeric($v)) {
                return null;
            }
        }

        return [(int) $t, (float) $o, (float) $h, (float) $l, (float) $c];
    }

    private static function sides(mixed $data, string $bidKey, string $askKey): array
    {
        $pick = static function (mixed $levels): array {
            $out = [];
            foreach (is_array($levels) ? $levels : [] as $level) {
                if (is_array($level) && isset($level[0], $level[1]) && is_numeric($level[0]) && is_numeric($level[1])) {
                    $out[] = [(float) $level[0], (float) $level[1]];
                }
            }
            return $out;
        };

        return is_array($data) ? [$pick($data[$bidKey] ?? null), $pick($data[$askKey] ?? null)] : [[], []];
    }

    private static function okx(string $type): array
    {
        $list = Http::getJson(self::OKX . '/tickers?instType=' . $type, [], 20, 1)['data'] ?? null;
        $pattern = $type === 'SWAP' ? '/^([A-Z0-9]+)-USDT-SWAP$/' : '/^([A-Z0-9]+)-USDT$/';
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match($pattern, (string) ($t['instId'] ?? ''), $m)) {
                continue;
            }
            $last = (float) ($t['last'] ?? 0);
            $open = (float) ($t['open24h'] ?? 0);
            $volume = (float) ($t['volCcy24h'] ?? 0) * ($type === 'SWAP' ? $last : 1);
            $out[] = self::ticker($m[1] . 'USDT', $last, $open > 0 ? ($last - $open) / $open * 100 : 0.0, (float) ($t['high24h'] ?? 0), (float) ($t['low24h'] ?? 0), $volume);
        }

        return $out;
    }

    private static function bybit(string $category): array
    {
        $list = Http::getJson(self::BYBIT . '/tickers?category=' . $category, [], 20, 1)['result']['list'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^[A-Z0-9]+USDT$/', (string) ($t['symbol'] ?? ''))) {
                continue;
            }
            $out[] = self::ticker(
                (string) $t['symbol'],
                (float) ($t['lastPrice'] ?? 0),
                (float) ($t['price24hPcnt'] ?? 0) * 100,
                (float) ($t['highPrice24h'] ?? 0),
                (float) ($t['lowPrice24h'] ?? 0),
                (float) ($t['turnover24h'] ?? 0)
            );
        }

        return $out;
    }

    private static function mexc(): array
    {
        $list = Http::getJson(self::MEXC . '/ticker/24hr', [], 25, 1);
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^[A-Z0-9]+USDT$/', (string) ($t['symbol'] ?? ''))) {
                continue;
            }
            $last = (float) ($t['lastPrice'] ?? 0);
            $open = (float) ($t['openPrice'] ?? 0) ?: (float) ($t['prevClosePrice'] ?? 0);
            if ($open <= 0) {
                continue;
            }
            $out[] = self::ticker((string) $t['symbol'], $last, ($last - $open) / $open * 100, (float) ($t['highPrice'] ?? 0), (float) ($t['lowPrice'] ?? 0), (float) ($t['quoteVolume'] ?? 0));
        }

        return $out;
    }

    private static function mexcFutures(): array
    {
        $list = Http::getJson(self::MEXC_F . '/ticker', [], 20, 1)['data'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^([A-Z0-9]+)_USDT$/', (string) ($t['symbol'] ?? ''), $m)) {
                continue;
            }
            $out[] = self::ticker(
                $m[1] . 'USDT',
                (float) ($t['lastPrice'] ?? 0),
                (float) ($t['riseFallRate'] ?? 0) * 100,
                (float) ($t['high24Price'] ?? 0),
                (float) ($t['lower24Price'] ?? 0),
                (float) ($t['amount24'] ?? 0)
            );
        }

        return $out;
    }

    private static function kucoin(): array
    {
        $list = Http::getJson(self::KUCOIN . '/market/allTickers', [], 25, 1)['data']['ticker'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^([A-Z0-9]+)-USDT$/', (string) ($t['symbol'] ?? ''), $m)) {
                continue;
            }
            $out[] = self::ticker(
                $m[1] . 'USDT',
                (float) ($t['last'] ?? 0),
                (float) ($t['changeRate'] ?? 0) * 100,
                (float) ($t['high'] ?? 0),
                (float) ($t['low'] ?? 0),
                (float) ($t['volValue'] ?? 0)
            );
        }

        return $out;
    }

    private static function kucoinFutures(): array
    {
        $list = Http::getJson(self::KUCOIN_F . '/contracts/active', [], 20, 1)['data'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || ($t['quoteCurrency'] ?? '') !== 'USDT' || ($t['settleCurrency'] ?? '') !== 'USDT'
                || !str_ends_with((string) ($t['symbol'] ?? ''), 'USDTM')
            ) {
                continue;
            }
            $base = strtoupper((string) ($t['baseCurrency'] ?? ''));
            $base = $base === 'XBT' ? 'BTC' : $base;
            if (!preg_match('/^[A-Z0-9]+$/', $base)) {
                continue;
            }
            $out[] = self::ticker(
                $base . 'USDT',
                (float) ($t['lastTradePrice'] ?? 0),
                (float) ($t['priceChgPct'] ?? 0) * 100,
                (float) ($t['highPrice'] ?? 0),
                (float) ($t['lowPrice'] ?? 0),
                (float) ($t['turnoverOf24h'] ?? 0)
            );
        }

        return $out;
    }

    private static function gate(string $path, string $key, string $volumeKey): array
    {
        $list = Http::getJson(self::GATE . $path, [], 25, 1);
        if (!is_array($list) || array_values($list) !== $list) {
            return [];
        }
        $out = [];
        foreach ($list as $t) {
            if (!is_array($t) || !preg_match('/^([A-Z0-9]+)_USDT$/', (string) ($t[$key] ?? ''), $m)) {
                continue;
            }
            $out[] = self::ticker(
                $m[1] . 'USDT',
                (float) ($t['last'] ?? 0),
                (float) ($t['change_percentage'] ?? 0),
                (float) ($t['high_24h'] ?? 0),
                (float) ($t['low_24h'] ?? 0),
                (float) ($t[$volumeKey] ?? 0)
            );
        }

        return $out;
    }

    private static function bitget(): array
    {
        $list = Http::getJson(self::BITGET . '/tickers?productType=USDT-FUTURES', [], 20, 1)['data'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^[A-Z0-9]+USDT$/', (string) ($t['symbol'] ?? ''))) {
                continue;
            }
            $out[] = self::ticker(
                (string) $t['symbol'],
                (float) ($t['lastPr'] ?? 0),
                (float) ($t['change24h'] ?? 0) * 100,
                (float) ($t['high24h'] ?? 0),
                (float) ($t['low24h'] ?? 0),
                (float) ($t['quoteVolume'] ?? ($t['usdtVolume'] ?? 0))
            );
        }

        return $out;
    }

    private static function bingx(): array
    {
        $list = Http::getJson(self::BINGX . '/v2/quote/ticker', [], 20, 1)['data'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^([A-Z0-9]+)-USDT$/', (string) ($t['symbol'] ?? ''), $m)) {
                continue;
            }
            $out[] = self::ticker(
                $m[1] . 'USDT',
                (float) ($t['lastPrice'] ?? 0),
                (float) rtrim((string) ($t['priceChangePercent'] ?? '0'), '%'),
                (float) ($t['highPrice'] ?? 0),
                (float) ($t['lowPrice'] ?? 0),
                (float) ($t['quoteVolume'] ?? 0)
            );
        }

        return $out;
    }

    private static function coinex(): array
    {
        $list = Http::getJson(self::COINEX . '/ticker', [], 20, 1)['data'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^[A-Z0-9]+USDT$/', (string) ($t['market'] ?? ''))) {
                continue;
            }
            $last = (float) ($t['last'] ?? 0);
            $open = (float) ($t['open'] ?? 0);
            $out[] = self::ticker((string) $t['market'], $last, $open > 0 ? ($last - $open) / $open * 100 : 0.0, (float) ($t['high'] ?? 0), (float) ($t['low'] ?? 0), (float) ($t['value'] ?? 0));
        }

        return $out;
    }

    private static function htx(): array
    {
        $list = Http::getJson(self::HTX . '/detail/batch_merged?business_type=swap', [], 20, 1)['ticks'] ?? null;
        $out = [];
        foreach (is_array($list) ? $list : [] as $t) {
            if (!is_array($t) || !preg_match('/^([A-Z0-9]+)-USDT$/', (string) ($t['contract_code'] ?? ''), $m)) {
                continue;
            }
            $last = (float) ($t['close'] ?? 0);
            $open = (float) ($t['open'] ?? 0);
            $out[] = self::ticker($m[1] . 'USDT', $last, $open > 0 ? ($last - $open) / $open * 100 : 0.0, (float) ($t['high'] ?? 0), (float) ($t['low'] ?? 0), (float) ($t['trade_turnover'] ?? 0));
        }

        return $out;
    }

    private static function nobitex(array $bases): array
    {
        $bases = array_values(array_filter(array_map('strtolower', $bases), static fn ($b) => preg_match('/^[a-z0-9]+$/', $b) === 1));
        if ($bases === []) {
            return [];
        }
        $stats = Http::getJson(self::NOBITEX . '/market/stats?srcCurrency=' . implode(',', $bases) . '&dstCurrency=usdt', [], 20, 1)['stats'] ?? null;
        $out = [];
        foreach (is_array($stats) ? $stats : [] as $market => $t) {
            if (!is_array($t) || !preg_match('/^([a-z0-9]+)-usdt$/', (string) $market, $m) || !empty($t['isClosed'])) {
                continue;
            }
            $out[] = self::ticker(
                strtoupper($m[1]) . 'USDT',
                (float) ($t['latest'] ?? 0),
                (float) ($t['dayChange'] ?? 0),
                (float) ($t['dayHigh'] ?? 0),
                (float) ($t['dayLow'] ?? 0),
                (float) ($t['volumeDst'] ?? 0)
            );
        }

        return $out;
    }
}
