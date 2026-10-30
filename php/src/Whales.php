<?php

final class Whales
{
    const USDT_TRON = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    const LABELS = [
        '0x28c6c06298d514db089934071355e5743bf21d60' => 'Binance 14',
        '0xf977814e90da44bfa03b6295a0616a897441acec' => 'Binance 8',
        '0x21a31ee1afc51d94c2efccaa2092ad1028285549' => 'Binance 15',
        '0xdfd5293d8e347dfe59e90efd55b2956a1343963d' => 'Binance 16',
        '0x5754284f345afc66a98fbb0a0afe71e0f007b949' => 'Tether Treasury',
        '0x8894e0a0c962cb723c1976a4421c95949be2d4e3' => 'Binance (BSC)',
        'TKHuVq1oKVruCGLvqVexFs6dawKv6fQgFs' => 'Tether Treasury',
    ];

    private static function minUsd(): float
    {
        return (float)(Settings::get('whale_min_usd') ?: 5000000);
    }

    private static function label(?string $a): string
    {
        if (!$a) {
            return 'نامشخص';
        }
        $n = self::LABELS[$a] ?? self::LABELS[strtolower($a)] ?? (DB::kvGet('whale_labels') ?: [])[$a] ?? null;
        $s = Fmt::short($a, 6, 4);
        return $n ? '<b>' . UI::esc($n) . '</b> (<code>' . $s . '</code>)' : '<code>' . $s . '</code>';
    }

    private static function alert(string $uid, string $net, string $sym, float $amount, float $usd, ?string $from, ?string $to, string $link): array
    {
        return compact('uid', 'net', 'sym', 'amount', 'usd', 'from', 'to', 'link');
    }

    public static function evmTokens(string $key): array
    {
        $chain = null;
        foreach (Chain::evm() as $c) {
            if ($c['key'] === $key) {
                $chain = $c;
            }
        }
        $head = (int)Chain::hexInt(Chain::rpc($chain['rpcs'], 'eth_blockNumber', []));
        if (!$head) {
            return [];
        }
        $last = DB::kvGet("whale_$key");
        if ($last === null || $head - $last > 400) {
            DB::kvSet("whale_$key", $head);
            return [];
        }
        if ($head <= $last) {
            return [];
        }
        $to = min($head, $last + ($key === 'bsc' ? 150 : 40));
        $tokens = [];
        foreach ($chain['tokens'] as $sym => [$addr, $dec]) {
            if (in_array($sym, ['USDT', 'USDC', 'BUSD'], true)) {
                $tokens[strtolower($addr)] = [$sym, $dec];
            }
        }
        $logs = Chain::rpc($chain['rpcs'], 'eth_getLogs', [['fromBlock' => '0x' . dechex($last + 1), 'toBlock' => '0x' . dechex($to), 'address' => array_keys($tokens), 'topics' => [Chain::TRANSFER]]], 20);
        if (!is_array($logs)) {
            return [];
        }
        DB::kvSet("whale_$key", $to);
        $out = [];
        foreach ($logs as $l) {
            if (count($l['topics'] ?? []) !== 3) {
                continue;
            }
            [$sym, $dec] = $tokens[strtolower($l['address'])] ?? ['TOKEN', 18];
            $amt = Chain::hexInt($l['data'] ?? '0x0') / 10 ** $dec;
            if ($amt >= self::minUsd()) {
                $h = $l['transactionHash'];
                $out[] = self::alert("$key:$h:" . ($l['logIndex'] ?? ''), $chain['name'], $sym, $amt, $amt, Chain::topicAddr($l['topics'][1]), Chain::topicAddr($l['topics'][2]), $chain['explorer'] . "/tx/$h");
            }
        }
        return $out;
    }

    public static function ethNative(): array
    {
        $c = Chain::evm()[0];
        $head = (int)Chain::hexInt(Chain::rpc($c['rpcs'], 'eth_blockNumber', []));
        $last = DB::kvGet('whale_eth_native');
        if (!$head || $last === null || $head - $last > 30) {
            if ($head) {
                DB::kvSet('whale_eth_native', $head);
            }
            return [];
        }
        $px = Prices::usd('ETH');
        if (!$px) {
            return [];
        }
        $out = [];
        for ($n = $last + 1; $n <= min($head, $last + 6); $n++) {
            $b = Chain::rpc($c['rpcs'], 'eth_getBlockByNumber', ['0x' . dechex($n), true], 20);
            if (!$b) {
                break;
            }
            DB::kvSet('whale_eth_native', $n);
            foreach ($b['transactions'] ?? [] as $tx) {
                $amt = Chain::hexInt($tx['value'] ?? '0x0') / 1e18;
                if ($amt * $px >= self::minUsd()) {
                    $out[] = self::alert('eth:' . $tx['hash'], $c['name'], 'ETH', $amt, $amt * $px, $tx['from'] ?? null, $tx['to'] ?? null, $c['explorer'] . '/tx/' . $tx['hash']);
                }
            }
        }
        return $out;
    }

    public static function tron(): array
    {
        $now = (int)(microtime(true) * 1000);
        $ts = DB::kvGet('whale_tron_ts');
        if ($ts === null || $now - $ts > 300000) {
            $ts = $now - 60000;
        }
        $params = ['event_name' => 'Transfer', 'only_confirmed' => 'true', 'limit' => 200, 'min_block_timestamp' => $ts, 'order_by' => 'block_timestamp,asc'];
        $out = [];
        $pages = Config::get('TRONGRID_API_KEY') ? 20 : 6;
        for ($p = 0; $p < $pages; $p++) {
            $d = Http::json('https://api.trongrid.io/v1/contracts/' . self::USDT_TRON . '/events', ['params' => $params, 'headers' => Chain::tronHeaders(), 'timeout' => 15]);
            if (!is_array($d)) {
                break;
            }
            $rows = $d['data'] ?? [];
            foreach ($rows as $ev) {
                $r = $ev['result'] ?? [];
                $amt = (float)($r['value'] ?? $r['2'] ?? 0) / 1e6;
                $ts = max($ts, (int)($ev['block_timestamp'] ?? 0) + 1);
                if ($amt >= self::minUsd()) {
                    $f = (string)($r['from'] ?? $r['0'] ?? '');
                    $t = (string)($r['to'] ?? $r['1'] ?? '');
                    $f = str_starts_with($f, 'T') ? $f : Chain::tronB58($f);
                    $t = str_starts_with($t, 'T') ? $t : Chain::tronB58($t);
                    $h = $ev['transaction_id'] ?? '';
                    $out[] = self::alert("tron:$h:" . ($ev['event_index'] ?? ''), 'TRON (TRC20)', 'USDT', $amt, $amt, $f, $t, "https://tronscan.org/#/transaction/$h");
                }
            }
            $fp = $d['meta']['fingerprint'] ?? null;
            if (!$fp || count($rows) < 200) {
                break;
            }
            $params['fingerprint'] = $fp;
        }
        DB::kvSet('whale_tron_ts', $ts);
        return $out;
    }

    public static function btc(): array
    {
        $tip = trim((string)Http::text('https://mempool.space/api/blocks/tip/hash', ['timeout' => 10]));
        $last = DB::kvGet('whale_btc_tip');
        if ($tip === '' || $tip === $last) {
            return [];
        }
        DB::kvSet('whale_btc_tip', $tip);
        if ($last === null) {
            return [];
        }
        $b = Http::json("https://blockchain.info/rawblock/$tip", ['params' => ['format' => 'json'], 'timeout' => 30]);
        $px = Prices::usd('BTC') ?? 0;
        $min = (float)(Settings::get('whale_btc_min') ?: 500);
        $out = [];
        foreach (array_slice($b['tx'] ?? [], 1) as $tx) {
            $outs = $tx['out'] ?? [];
            $total = array_sum(array_map(fn($o) => $o['value'] ?? 0, $outs)) / 1e8;
            if ($total < $min) {
                continue;
            }
            usort($outs, fn($a, $c) => ($c['value'] ?? 0) <=> ($a['value'] ?? 0));
            $out[] = self::alert('btc:' . $tx['hash'], 'Bitcoin', 'BTC', $total, $total * $px, $tx['inputs'][0]['prev_out']['addr'] ?? null, $outs[0]['addr'] ?? null, 'https://mempool.space/tx/' . $tx['hash']);
        }
        return $out;
    }

    public static function whaleAlert(): array
    {
        $key = Config::get('WHALE_ALERT_API_KEY');
        if ($key === '') {
            return [];
        }
        $start = (int)(DB::kvGet('whale_wa_ts') ?: time() - 120);
        $d = Http::json('https://api.whale-alert.io/v1/transactions', ['params' => ['api_key' => $key, 'min_value' => (int)self::minUsd(), 'start' => $start], 'timeout' => 15]);
        $out = [];
        $labels = DB::kvGet('whale_labels') ?: [];
        foreach ($d['transactions'] ?? [] as $tx) {
            $start = max($start, (int)($tx['timestamp'] ?? 0) + 1);
            foreach (['from', 'to'] as $s) {
                if (!empty($tx[$s]['owner']) && !empty($tx[$s]['address'])) {
                    $labels[$tx[$s]['address']] = ucwords($tx[$s]['owner']);
                }
            }
            $out[] = self::alert('wa:' . ($tx['blockchain'] ?? '') . ':' . ($tx['hash'] ?? ''), ucfirst((string)($tx['blockchain'] ?? '')), strtoupper((string)($tx['symbol'] ?? '')),
                (float)($tx['amount'] ?? 0), (float)($tx['amount_usd'] ?? 0), $tx['from']['address'] ?? null, $tx['to']['address'] ?? null,
                'https://whale-alert.io/transaction/' . ($tx['blockchain'] ?? '') . '/' . ($tx['hash'] ?? ''));
        }
        DB::kvSet('whale_wa_ts', $start);
        DB::kvSet('whale_labels', array_slice($labels, -500, null, true));
        return $out;
    }

    public static function run(): int
    {
        if (!Settings::get('whale_alerts')) {
            return 0;
        }
        $alerts = [];
        foreach ([fn() => self::evmTokens('eth'), fn() => self::evmTokens('bsc'), [self::class, 'ethNative'], [self::class, 'tron'], [self::class, 'btc'], [self::class, 'whaleAlert']] as $fn) {
            try {
                $alerts = array_merge($alerts, $fn());
            } catch (Throwable $e) {
                error_log('whale: ' . $e->getMessage());
            }
        }
        usort($alerts, fn($a, $b) => $b['usd'] <=> $a['usd']);
        $fresh = [];
        foreach ($alerts as $a) {
            if (DB::markAlert($a['uid'])) {
                $fresh[] = $a;
            }
        }
        $fresh = array_slice($fresh, 0, 4);
        if (!$fresh) {
            return 0;
        }
        $rate = Prices::usdtToman();
        $groups = DB::activeGroups();
        foreach ($fresh as $a) {
            $n = min(10, max(1, (int)($a['usd'] / max(self::minUsd(), 1))));
            $text = UI::t('whale_alert', [
                'bar' => str_repeat('💸', min($n, 5)), 'amount' => Fmt::num($a['amount']), 'symbol' => $a['sym'], 'usd' => Fmt::int($a['usd']),
                'toman' => $rate ? Fmt::toman($a['usd'] * $rate) : null, 'network' => $a['net'], 'sender' => self::label($a['from']),
                'receiver' => self::label($a['to']), 'link' => UI::attr($a['link']), 'time' => Fmt::now(),
            ]);
            foreach ($groups as $g) {
                if (!Settings::on((int)$g['id'], 'whale_alerts')) {
                    continue;
                }
                if (TG::send($g['id'], $text) === null && preg_match('/kicked|not found|forbidden/i', (string)TG::$lastError)) {
                    DB::setChatActive((int)$g['id'], false);
                }
                usleep(50000);
            }
        }
        return count($fresh);
    }
}
