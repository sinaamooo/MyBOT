<?php

final class Chain
{
    const B58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    const TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    const PATTERNS = [
        'evm' => '/^0x[0-9a-fA-F]{40}$/',
        'hex64x' => '/^0x[0-9a-fA-F]{64}$/',
        'tron' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
        'ton' => '/^(?:[EUk0][Qf][A-Za-z0-9_-]{46}|-?[01]:[0-9a-fA-F]{64})$/',
        'btc' => '/^(?:bc1[ac-hj-np-z02-9]{25,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/',
        'ltc' => '/^(?:ltc1[ac-hj-np-z02-9]{25,87}|[LM][a-km-zA-HJ-NP-Z1-9]{26,33})$/',
        'doge' => '/^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/',
        'dash' => '/^X[1-9A-HJ-NP-Za-km-z]{33}$/',
        'bch' => '/^(?:bitcoincash:)?[qp][a-z0-9]{41}$/',
        'xrp' => '/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/',
        'sol' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
        'hex64' => '/^[0-9a-fA-F]{64}$/',
        'b64hash' => '/^[A-Za-z0-9+\/_-]{43}=?$/',
        'solsig' => '/^[1-9A-HJ-NP-Za-km-z]{86,90}$/',
    ];
    const ORDER = ['evm', 'hex64x', 'tron', 'ton', 'bch', 'btc', 'ltc', 'doge', 'dash', 'xrp', 'hex64', 'solsig', 'sol', 'b64hash'];
    const ADDRESS_KINDS = ['evm', 'tron', 'ton', 'btc', 'ltc', 'doge', 'dash', 'bch', 'xrp', 'sol'];
    const HASH_KINDS = ['hex64x', 'hex64', 'b64hash', 'solsig'];

    public static function evm(): array
    {
        return Config::data('chains')['evm'];
    }

    public static function trc20(): array
    {
        return Config::data('chains')['trc20'];
    }

    public static function solTokens(): array
    {
        return Config::data('chains')['sol_tokens'];
    }

    public static function rpcs(string $k): array
    {
        return Config::data('chains')[$k];
    }

    public static function tronHeaders(): array
    {
        $k = Config::get('TRONGRID_API_KEY');
        return $k ? ['TRON-PRO-API-KEY' => $k] : [];
    }

    public static function tronscanHeaders(): array
    {
        $k = Config::get('TRONSCAN_API_KEY') ?: Config::get('TRONGRID_API_KEY');
        return $k ? ['TRON-PRO-API-KEY' => $k] : [];
    }

    public static function tonapiHeaders(): array
    {
        $k = Config::get('TONAPI_KEY');
        return $k ? ['Authorization' => "Bearer $k"] : [];
    }

    public static function toncenterParams(): array
    {
        $k = Config::get('TONCENTER_API_KEY');
        return $k ? ['api_key' => $k] : [];
    }

    public static function rpcReq(string $url, string $method, array $params, int $timeout = 10): array
    {
        return ['url' => $url, 'method' => 'POST', 'json' => ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], 'timeout' => $timeout];
    }

    public static function rpc(array $urls, string $method, array $params, int $timeout = 8)
    {
        $reqs = [];
        foreach (array_slice($urls, 0, 3) as $i => $u) {
            $reqs[$i] = self::rpcReq($u, $method, $params, $timeout);
        }
        $hit = Http::race($reqs, fn($k, $d) => is_array($d) && array_key_exists('result', $d) ? ['r' => $d['result']] : null, $timeout + 1);
        return $hit ? $hit[1]['r'] : null;
    }

    public static function batchReq(string $url, array $calls, int $timeout = 12): array
    {
        $body = [];
        foreach ($calls as $i => [$m, $p]) {
            $body[] = ['jsonrpc' => '2.0', 'id' => $i, 'method' => $m, 'params' => $p];
        }
        return ['url' => $url, 'method' => 'POST', 'json' => $body, 'timeout' => $timeout];
    }

    public static function batchParse($d, int $n): ?array
    {
        if (!is_array($d) || !array_is_list($d) || !$d) {
            return null;
        }
        $by = [];
        foreach ($d as $item) {
            if (is_array($item) && isset($item['id'])) {
                $by[(int)$item['id']] = $item;
            }
        }
        if (count($by) !== $n) {
            return null;
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $by[$i]['result'] ?? null;
        }
        return $out;
    }

    public static function batch(array $urls, array $calls, int $timeout = 8): array
    {
        $reqs = [];
        foreach (array_slice($urls, 0, 3) as $i => $u) {
            $reqs[$i] = self::batchReq($u, $calls, $timeout);
        }
        $hit = Http::race($reqs, fn($k, $d) => self::batchParse($d, count($calls)), $timeout + 1);
        if ($hit) {
            return $hit[1];
        }
        $reqs = [];
        foreach ($calls as $i => [$m, $p]) {
            $reqs[$i] = self::rpcReq($urls[0] ?? '', $m, $p, $timeout);
        }
        $out = [];
        foreach ($urls ? Http::multiJson($reqs) : [] as $i => $d) {
            $out[$i] = is_array($d) ? ($d['result'] ?? null) : null;
        }
        return array_replace(array_fill(0, count($calls), null), $out);
    }

    public static function hexInt($v): float
    {
        if ($v === null || $v === '' || $v === '0x') {
            return 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $h = ltrim(preg_replace('/^0x/i', '', (string)$v), '0');
        if ($h === '') {
            return 0.0;
        }
        if (strlen($h) <= 15) {
            return (float)hexdec($h);
        }
        $r = 0.0;
        foreach (str_split($h) as $c) {
            $r = $r * 16 + hexdec($c);
        }
        return $r;
    }

    public static function balanceOfData(string $owner): string
    {
        return '0x70a08231' . str_pad(strtolower(preg_replace('/^0x/i', '', $owner)), 64, '0', STR_PAD_LEFT);
    }

    public static function abiString(?string $hex): ?string
    {
        if (!$hex || $hex === '0x') {
            return null;
        }
        $raw = @hex2bin(preg_replace('/^0x/', '', $hex));
        if ($raw === false) {
            return null;
        }
        if (strlen($raw) >= 64) {
            $off = (int)hexdec(bin2hex(substr($raw, 24, 8)));
            if ($off === 32) {
                $len = (int)hexdec(bin2hex(substr($raw, 56, 8)));
                $s = trim(substr($raw, 64, $len), "\0");
                return $s !== '' ? $s : null;
            }
        }
        $s = trim(substr($raw, 0, 32), "\0");
        return $s !== '' && mb_check_encoding($s, 'UTF-8') ? $s : null;
    }

    public static function topicAddr(string $t): string
    {
        return '0x' . substr($t, -40);
    }

    public static function b58encode(string $bin): string
    {
        $bytes = array_values(unpack('C*', $bin) ?: []);
        $zeros = 0;
        while ($zeros < count($bytes) && $bytes[$zeros] === 0) {
            $zeros++;
        }
        $digits = [];
        foreach ($bytes as $b) {
            $carry = $b;
            foreach ($digits as $j => $d) {
                $carry += $d << 8;
                $digits[$j] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }
        $s = str_repeat('1', $zeros);
        for ($j = count($digits) - 1; $j >= 0; $j--) {
            $s .= self::B58[$digits[$j]];
        }
        return $s;
    }

    public static function b58decode(string $text): ?string
    {
        $bytes = [];
        foreach (str_split($text) as $ch) {
            $v = strpos(self::B58, $ch);
            if ($v === false) {
                return null;
            }
            $carry = $v;
            foreach ($bytes as $j => $b) {
                $carry += $b * 58;
                $bytes[$j] = $carry & 0xff;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xff;
                $carry >>= 8;
            }
        }
        $pad = strlen($text) - strlen(ltrim($text, '1'));
        return str_repeat("\0", $pad) . pack('C*', ...array_reverse($bytes));
    }

    public static function b58checkValid(string $text): bool
    {
        $raw = self::b58decode($text);
        if ($raw === null || strlen($raw) < 5) {
            return false;
        }
        return substr(hash('sha256', hash('sha256', substr($raw, 0, -4), true), true), 0, 4) === substr($raw, -4);
    }

    public static function tronB58(string $hex): string
    {
        $h = strtolower(preg_replace('/^0x/i', '', $hex));
        if (strlen($h) === 40) {
            $h = '41' . $h;
        }
        $bin = @hex2bin($h);
        if ($bin === false || $h === '') {
            return $hex;
        }
        return self::b58encode($bin . substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4));
    }

    public static function classify(string $t): ?string
    {
        $t = trim($t);
        foreach (self::ORDER as $k) {
            if (preg_match(self::PATTERNS[$k], $t)) {
                if ($k === 'tron' && !self::b58checkValid($t)) {
                    return null;
                }
                return $k;
            }
        }
        return null;
    }

    public static function extract(string $text, bool $strict): ?array
    {
        $text = trim($text);
        if ($text === '' || strlen($text) > 400) {
            return null;
        }
        if (preg_match('~(?:/(?:address|addr|account|tx|transaction|txs|wallet)/|tonviewer\.com/|tonscan\.org/(?:address|tx)/|#/(?:address|transaction)/)(?:transaction/)?([A-Za-z0-9:_+/=-]{25,100})~', $text, $m)) {
            $tok = explode('?', rtrim($m[1], '/'))[0];
            if ($k = self::classify($tok)) {
                return [$k, $tok];
            }
        }
        if ($strict) {
            return ($k = self::classify($text)) ? [$k, $text] : null;
        }
        preg_match_all('/[A-Za-z0-9:_+\/=-]{25,100}/', $text, $mm);
        foreach ($mm[0] as $tok) {
            if ($k = self::classify($tok)) {
                return [$k, $tok];
            }
        }
        return null;
    }
}

final class Wallets
{
    private static function result(string $chain, string $symbol, float $balance, string $explorer, array $extra = []): array
    {
        return array_merge(['chain' => $chain, 'symbol' => $symbol, 'balance' => $balance, 'explorer' => $explorer, 'usd' => null,
            'tokens' => [], 'txs' => null, 'nfts' => null, 'status' => null, 'created' => null, 'kind' => null], $extra);
    }

    private static function priceOf(string $sym): ?float
    {
        $s = strtoupper(str_replace('.E', '', strtoupper($sym)));
        if (in_array($s, Prices::STABLES, true) || in_array(strtoupper($sym), ['USDC.E', 'BUSD'], true)) {
            return 1.0;
        }
        return Prices::usd($s === 'WBTC' ? 'BTC' : $s);
    }

    private static function fill(array &$r): void
    {
        $p = self::priceOf($r['symbol']);
        $r['usd'] = $p !== null ? $r['balance'] * $p : null;
        foreach ($r['tokens'] as &$t) {
            if ($t['usd'] === null) {
                $tp = self::priceOf($t['symbol']);
                $t['usd'] = $tp !== null ? $t['amount'] * $tp : null;
            }
        }
    }

    public static function totalUsd(array $r): float
    {
        return ($r['usd'] ?? 0) + array_sum(array_map(fn($t) => $t['usd'] ?? 0, $r['tokens']));
    }

    private static function active(array $r): bool
    {
        return $r['balance'] > 0 || $r['tokens'] || !in_array($r['txs'], [null, 0, '0', 0.0], true) || $r['nfts'];
    }

    private static function tok(string $s, float $a, ?float $usd = null): array
    {
        return ['symbol' => $s, 'amount' => $a, 'usd' => $usd];
    }

    public static function evm(string $address): array
    {
        $chains = Chain::evm();
        $callsBy = [];
        foreach ($chains as $i => $c) {
            $calls = [['eth_getBalance', [$address, 'latest']], ['eth_getTransactionCount', [$address, 'latest']], ['eth_getCode', [$address, 'latest']]];
            foreach ($c['tokens'] as [$contract]) {
                $calls[] = ['eth_call', [['to' => $contract, 'data' => Chain::balanceOfData($address)], 'latest']];
            }
            $callsBy[$i] = $calls;
        }
        $got = [];
        foreach ([[0, 2, 6], [2, 3, 5]] as [$from, $len, $wait]) {
            $reqs = [];
            foreach ($chains as $i => $c) {
                if (!isset($got[$i])) {
                    foreach (array_slice($c['rpcs'], $from, $len) as $j => $u) {
                        $reqs["$i|$j"] = Chain::batchReq($u, $callsBy[$i], $wait);
                    }
                }
            }
            if (!$reqs) {
                break;
            }
            $got += Http::collect($reqs, fn($k) => (int)strtok($k, '|'), fn($k, $d) => Chain::batchParse($d, count($callsBy[(int)strtok($k, '|')])), $wait + 0.5);
        }
        $out = [];
        foreach ($chains as $i => $c) {
            $r = $got[$i] ?? null;
            if (!$r || ($r[0] === null && $r[1] === null)) {
                continue;
            }
            $tokens = [];
            $j = 3;
            foreach ($c['tokens'] as $sym => [, $dec]) {
                $raw = $r[$j++] ?? null;
                $amt = (is_string($raw) && strlen($raw) <= 66 && $raw !== '0x') ? Chain::hexInt($raw) / 10 ** $dec : 0;
                if ($amt > 0) {
                    $tokens[] = self::tok($sym, $amt);
                }
            }
            $code = $r[2] ?? '0x';
            $out[] = self::result($c['name'], $c['symbol'], Chain::hexInt($r[0]) / 1e18, $c['explorer'] . '/address/' . $address, [
                'tokens' => $tokens, 'txs' => (int)Chain::hexInt($r[1]),
                'status' => !in_array($code, ['0x', '0x0', null], true) ? '📋 قرارداد هوشمند' : null,
            ]);
        }
        return $out;
    }

    private static function first(array $reqs, callable $parse): ?array
    {
        $hit = Http::race($reqs, $parse, 9);
        return $hit ? $hit[1] : null;
    }

    private static function esploraParse($d, string $address, string $name, string $sym, string $explorer): ?array
    {
        if (!is_array($d) || !isset($d['chain_stats'])) {
            return null;
        }
        $cs = $d['chain_stats'];
        $ms = $d['mempool_stats'] ?? [];
        $sat = ($cs['funded_txo_sum'] ?? 0) - ($cs['spent_txo_sum'] ?? 0);
        $pend = ($ms['funded_txo_sum'] ?? 0) - ($ms['spent_txo_sum'] ?? 0);
        return self::result($name, $sym, $sat / 1e8, "$explorer/address/$address", [
            'txs' => ($cs['tx_count'] ?? 0) + ($ms['tx_count'] ?? 0),
            'status' => $pend ? '⏳ در انتظار: ' . sprintf('%+g', $pend / 1e8) . " $sym" : null,
        ]);
    }

    private static function cypherParse($d, string $name, string $sym, string $explorer): ?array
    {
        if (!is_array($d) || !isset($d['address'])) {
            return null;
        }
        return self::result($name, $sym, ($d['final_balance'] ?? $d['balance'] ?? 0) / 1e8, $explorer, ['txs' => $d['final_n_tx'] ?? $d['n_tx'] ?? null]);
    }

    private static function utxo(string $a, array $esplora, ?string $cypher, string $name, string $sym, string $explorer): array
    {
        $reqs = [];
        foreach ($esplora as $i => $base) {
            $reqs["e$i"] = ['url' => "$base/api/address/$a", 'timeout' => 8];
        }
        if ($cypher) {
            $reqs['c'] = ['url' => "https://api.blockcypher.com/v1/$cypher/main/addrs/$a/balance", 'timeout' => 8];
        }
        $r = self::first($reqs, fn($k, $d) => $k === 'c' ? self::cypherParse($d, $name, $sym, "$explorer/address/$a") : self::esploraParse($d, $a, $name, $sym, $explorer));
        return $r ? [$r] : [];
    }

    public static function btc(string $a): array
    {
        return self::utxo($a, ['https://mempool.space', 'https://blockstream.info'], 'btc', 'Bitcoin', 'BTC', 'https://mempool.space');
    }

    public static function ltc(string $a): array
    {
        return self::utxo($a, ['https://litecoinspace.org'], 'ltc', 'Litecoin', 'LTC', 'https://litecoinspace.org');
    }

    public static function doge(string $a): array
    {
        $ex = "https://blockchair.com/dogecoin/address/$a";
        $r = self::first([
            'c' => ['url' => "https://api.blockcypher.com/v1/doge/main/addrs/$a/balance", 'timeout' => 8],
            'd' => ['url' => "https://dogechain.info/api/v1/address/balance/$a", 'timeout' => 8],
        ], fn($k, $d) => $k === 'c' ? self::cypherParse($d, 'Dogecoin', 'DOGE', $ex) : (!empty($d['success']) ? self::result('Dogecoin', 'DOGE', (float)($d['balance'] ?? 0), $ex) : null));
        return $r ? [$r] : [];
    }

    public static function dash(string $a): array
    {
        $d = Http::json("https://api.blockcypher.com/v1/dash/main/addrs/$a/balance", ['timeout' => 8]);
        $r = self::cypherParse($d, 'Dash', 'DASH', "https://blockchair.com/dash/address/$a");
        return $r ? [$r] : [];
    }

    public static function bch(string $a): array
    {
        $addr = str_starts_with($a, 'bitcoincash:') ? $a : "bitcoincash:$a";
        $d = Http::json("https://api.blockchair.com/bitcoin-cash/dashboards/address/$addr", ['timeout' => 12]);
        $info = is_array($d['data'] ?? null) ? (array_values($d['data'])[0]['address'] ?? null) : null;
        if (!$info) {
            return [];
        }
        return [self::result('Bitcoin Cash', 'BCH', ($info['balance'] ?? 0) / 1e8, "https://blockchair.com/bitcoin-cash/address/$addr", ['txs' => $info['transaction_count'] ?? null])];
    }

    public static function tronTokensReq(string $a): array
    {
        return ['url' => 'https://apilist.tronscanapi.com/api/account/tokens', 'params' => ['address' => $a, 'start' => 0, 'limit' => 1000], 'headers' => Chain::tronscanHeaders(), 'timeout' => 9];
    }

    public static function tronTokensParse($d, string $a): ?array
    {
        if (!is_array($d) || !isset($d['data'])) {
            return null;
        }
        $tokens = [];
        foreach ($d['data'] as $it) {
            $type = strtolower((string)($it['tokenType'] ?? ''));
            $e = ['Amount' => $it['quantity'] ?? null, 'Name' => $it['tokenName'] ?? null, 'Symbol' => $it['tokenAbbr'] ?? null,
                'TokenType' => ($it['tokenId'] ?? '') === '_' ? 'COIN' : strtoupper($type)];
            if ($type === 'trc20') {
                $e['Contract_address'] = $it['tokenId'] ?? null;
            } else {
                $e['TokenId'] = $it['tokenId'] ?? null;
            }
            if (isset($it['amountInUsd'])) {
                $e['AmountInUsd'] = $it['amountInUsd'];
            }
            $tokens[] = $e;
        }
        return ['total' => $d['total'] ?? count($tokens), 'wallet' => $a, 'token' => $tokens];
    }

    public static function tronHistory(string $a, int $limit = 50): ?array
    {
        $hit = Cache::get('th:' . $a, $found);
        if ($found && is_array($hit)) {
            return $hit;
        }
        $h = Chain::tronHeaders();
        $base = "https://api.trongrid.io/v1/accounts/$a";
        $res = Http::multiJson([
            't' => ['url' => "$base/transactions/trc20", 'params' => ['limit' => $limit, 'only_confirmed' => 'true'], 'headers' => $h, 'timeout' => 10],
            'x' => ['url' => "$base/transactions", 'params' => ['limit' => $limit, 'only_confirmed' => 'true', 'search_internal' => 'false'], 'headers' => $h, 'timeout' => 10],
            's' => ['url' => 'https://apilist.tronscanapi.com/api/new/token_trc20/transfers', 'params' => ['limit' => $limit, 'start' => 0, 'relatedAddress' => $a], 'headers' => Chain::tronscanHeaders(), 'timeout' => 10],
        ]);
        $items = [];
        $ok = false;
        $addr = fn($v) => is_string($v) && strlen($v) === 42 && str_starts_with($v, '41') ? Chain::tronB58($v) : $v;
        if (!empty($res['t']['success'])) {
            $ok = true;
            foreach ($res['t']['data'] ?? [] as $it) {
                $dec = (int)($it['token_info']['decimals'] ?? 0);
                $items[] = ['amount' => is_numeric($it['value'] ?? null) ? $it['value'] / 10 ** $dec : 0, 'symbol' => (string)($it['token_info']['symbol'] ?? 'TRC20'),
                    'from' => $it['from'] ?? null, 'to' => $it['to'] ?? null, 'ts' => intdiv((int)($it['block_timestamp'] ?? 0), 1000), 'hash' => $it['transaction_id'] ?? null];
            }
        }
        if (!empty($res['x']['success'])) {
            $ok = true;
            foreach ($res['x']['data'] ?? [] as $it) {
                $c = $it['raw_data']['contract'][0] ?? [];
                if (($c['type'] ?? '') !== 'TransferContract') {
                    continue;
                }
                $v = $c['parameter']['value'] ?? [];
                $items[] = ['amount' => ($v['amount'] ?? 0) / 1e6, 'symbol' => 'TRX', 'from' => $addr($v['owner_address'] ?? null), 'to' => $addr($v['to_address'] ?? null),
                    'ts' => intdiv((int)($it['block_timestamp'] ?? $it['raw_data']['timestamp'] ?? 0), 1000), 'hash' => $it['txID'] ?? null];
            }
        }
        if (!$ok && is_array($res['s'] ?? null) && isset($res['s']['token_transfers'])) {
            $ok = true;
            foreach ($res['s']['token_transfers'] as $it) {
                $dec = (int)($it['tokenInfo']['tokenDecimal'] ?? 0);
                $items[] = ['amount' => is_numeric($it['quant'] ?? null) ? $it['quant'] / 10 ** $dec : 0, 'symbol' => (string)($it['tokenInfo']['tokenAbbr'] ?? 'TRC20'),
                    'from' => $it['from_address'] ?? null, 'to' => $it['to_address'] ?? null, 'ts' => intdiv((int)($it['block_ts'] ?? 0), 1000), 'hash' => $it['transaction_id'] ?? null];
            }
        }
        if (!$ok) {
            return null;
        }
        usort($items, fn($x, $y) => $y['ts'] <=> $x['ts']);
        $items = array_slice($items, 0, 2 * $limit);
        Cache::set('th:' . $a, $items, 300);
        return $items;
    }

    public static function tron(string $a): array
    {
        $res = Http::multi([
            'grid' => ['url' => "https://api.trongrid.io/v1/accounts/$a", 'headers' => Chain::tronHeaders(), 'timeout' => 10],
            'scan' => ['url' => 'https://apilist.tronscanapi.com/api/accountv2', 'params' => ['address' => $a], 'headers' => Chain::tronscanHeaders(), 'timeout' => 10],
            'tokens' => self::tronTokensReq($a),
        ]);
        $grid = Http::decode($res['grid']);
        $scan = Http::decode($res['scan']);
        $all = self::tronTokensParse(Http::decode($res['tokens']), $a);
        $tokens = [];
        $trx = null;
        $status = null;
        $txs = null;
        $created = null;
        $acc = $grid['data'] ?? [];
        if ($acc) {
            $x = $acc[0];
            $trx = ($x['balance'] ?? 0) / 1e6;
            $created = isset($x['create_time']) ? intdiv((int)$x['create_time'], 1000) : null;
            foreach ($x['trc20'] ?? [] as $item) {
                foreach ($item as $contract => $raw) {
                    if (isset(Chain::trc20()[$contract])) {
                        [$sym, $dec] = Chain::trc20()[$contract];
                        $amt = (float)$raw / 10 ** $dec;
                        if ($amt > 0) {
                            $tokens[$sym] = self::tok($sym, $amt);
                        }
                    }
                }
            }
        } elseif (is_array($grid) && !empty($grid['success'])) {
            $status = '⛔️ اکانت فعال نشده';
        }
        if (is_array($scan) && $scan) {
            $txs = $scan['totalTransactionCount'] ?? $scan['transactions'] ?? null;
            if ($created === null && !empty($scan['date_created'])) {
                $created = intdiv((int)$scan['date_created'], 1000);
            }
            if ($trx === null && isset($scan['balance'])) {
                $trx = $scan['balance'] / 1e6;
            }
            $trxUsd = null;
            foreach ($scan['withPriceTokens'] ?? [] as $t) {
                $sym = strtoupper((string)($t['tokenAbbr'] ?? ''));
                if ($sym === '' || $sym === 'TRX' || isset($tokens[$sym]) || ($t['tokenType'] ?? '') !== 'trc20') {
                    continue;
                }
                $amt = (float)($t['amount'] ?? 0) ?: (float)($t['balance'] ?? 0) / 10 ** (int)($t['tokenDecimal'] ?? 0);
                if ($amt > 0) {
                    $usd = null;
                    if (!empty($t['tokenPriceInTrx'])) {
                        $trxUsd ??= Prices::usd('TRX');
                        $usd = $trxUsd ? $amt * (float)$t['tokenPriceInTrx'] * $trxUsd : null;
                    }
                    $tokens[$sym] = self::tok($sym, $amt, $usd);
                }
                if (count($tokens) > 12) {
                    break;
                }
            }
            if (($scan['activated'] ?? null) === false) {
                $status = '⛔️ اکانت فعال نشده';
            }
        }
        foreach ($all['token'] ?? [] as $it) {
            $sym = strtoupper((string)($it['Symbol'] ?? ''));
            $amt = (float)($it['Amount'] ?? 0);
            if (($it['TokenType'] ?? '') === 'COIN' || $sym === 'TRX') {
                $trx ??= $amt;
                continue;
            }
            if ($sym === '' || isset($tokens[$sym]) || count($tokens) > 15 || $amt <= 0) {
                continue;
            }
            $tokens[$sym] = self::tok($sym, $amt, isset($it['AmountInUsd']) ? (float)$it['AmountInUsd'] : null);
        }
        if ($trx === null && !$tokens) {
            return [];
        }
        return [self::result('TRON', 'TRX', (float)$trx, "https://tronscan.org/#/address/$a", ['tokens' => array_values($tokens), 'txs' => $txs, 'status' => $status, 'created' => $created, 'kind' => 'tron'])];
    }

    public static function ton(string $a): array
    {
        $h = Chain::tonapiHeaders();
        $res = Http::multiJson([
            'acc' => ['url' => "https://tonapi.io/v2/accounts/$a", 'headers' => $h, 'timeout' => 10],
            'jet' => ['url' => "https://tonapi.io/v2/accounts/$a/jettons", 'params' => ['currencies' => 'usd'], 'headers' => $h, 'timeout' => 10],
            'nft' => ['url' => "https://tonapi.io/v2/accounts/$a/nfts", 'params' => ['limit' => 1000], 'headers' => $h, 'timeout' => 9],
            'tc' => ['url' => 'https://toncenter.com/api/v2/getAddressInformation', 'params' => ['address' => $a] + Chain::toncenterParams(), 'timeout' => 10],
        ]);
        $bal = null;
        $status = null;
        if (is_array($res['acc']) && isset($res['acc']['balance'])) {
            $bal = $res['acc']['balance'] / 1e9;
            $status = ['active' => '🟢 فعال', 'uninit' => '⛔️ فعال نشده', 'frozen' => '💠 فریز'][$res['acc']['status'] ?? ''] ?? null;
            if (!empty($res['acc']['name'])) {
                $status = trim(($status ?? '') . ' • ' . $res['acc']['name'], ' •');
            }
        } elseif (!empty($res['tc']['ok'])) {
            $bal = ($res['tc']['result']['balance'] ?? 0) / 1e9;
            $status = ['active' => '🟢 فعال', 'uninitialized' => '⛔️ فعال نشده', 'frozen' => '💠 فریز'][$res['tc']['result']['state'] ?? ''] ?? null;
        }
        if ($bal === null) {
            return [];
        }
        $tokens = [];
        foreach ($res['jet']['balances'] ?? [] as $it) {
            $j = $it['jetton'] ?? [];
            $amt = (float)($it['balance'] ?? 0) / 10 ** (int)($j['decimals'] ?? 9);
            if ($amt <= 0) {
                continue;
            }
            $p = $it['price']['prices']['USD'] ?? null;
            $tokens[] = self::tok($j['symbol'] ?? 'JETTON', $amt, $p ? $amt * $p : null);
        }
        usort($tokens, fn($x, $y) => ($y['usd'] ?? 0) <=> ($x['usd'] ?? 0));
        $nfts = is_array($res['nft']) ? count($res['nft']['nft_items'] ?? []) : null;
        return [self::result('TON', 'TON', $bal, "https://tonviewer.com/$a", ['tokens' => array_slice($tokens, 0, 12), 'nfts' => $nfts, 'status' => $status])];
    }

    public static function sol(string $a): array
    {
        $u = Chain::rpcs('sol_rpcs')[0];
        $res = Http::multiJson([
            'bal' => Chain::rpcReq($u, 'getBalance', [$a]),
            't1' => Chain::rpcReq($u, 'getTokenAccountsByOwner', [$a, ['programId' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'], ['encoding' => 'jsonParsed']]),
            't2' => Chain::rpcReq($u, 'getTokenAccountsByOwner', [$a, ['programId' => 'TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb'], ['encoding' => 'jsonParsed']]),
            'sig' => Chain::rpcReq($u, 'getSignaturesForAddress', [$a, ['limit' => 1000]]),
        ]);
        $bal = $res['bal']['result'] ?? Chain::rpc(array_slice(Chain::rpcs('sol_rpcs'), 1), 'getBalance', [$a]);
        if ($bal === null) {
            return [];
        }
        $tokens = [];
        foreach (['t1', 't2'] as $k) {
            foreach ($res[$k]['result']['value'] ?? [] as $acc) {
                $info = $acc['account']['data']['parsed']['info'] ?? [];
                $amt = (float)($info['tokenAmount']['uiAmount'] ?? 0);
                $mint = $info['mint'] ?? null;
                if ($amt > 0 && isset(Chain::solTokens()[$mint])) {
                    $tokens[] = self::tok(Chain::solTokens()[$mint], $amt);
                }
            }
        }
        $sigs = $res['sig']['result'] ?? null;
        $txs = is_array($sigs) ? (count($sigs) >= 1000 ? count($sigs) . '+' : count($sigs)) : null;
        return [self::result('Solana', 'SOL', ($bal['value'] ?? 0) / 1e9, "https://solscan.io/account/$a", ['tokens' => $tokens, 'txs' => $txs])];
    }

    public static function xrp(string $a): array
    {
        $reqs = [];
        foreach (Chain::rpcs('xrp_rpcs') as $i => $u) {
            $reqs[$i] = ['url' => $u, 'method' => 'POST', 'json' => ['method' => 'account_info', 'params' => [['account' => $a, 'ledger_index' => 'validated']]], 'timeout' => 8];
        }
        $r = self::first($reqs, function ($k, $d) use ($a) {
            $x = $d['result'] ?? [];
            if (isset($x['account_data'])) {
                return self::result('XRP Ledger', 'XRP', ($x['account_data']['Balance'] ?? 0) / 1e6, "https://xrpscan.com/account/$a", ['txs' => $x['account_data']['Sequence'] ?? null]);
            }
            return null;
        });
        return $r ? [$r] : [];
    }

    public static function sui(string $a): array
    {
        $res = Chain::rpc(Chain::rpcs('sui_rpcs'), 'suix_getAllBalances', [$a]);
        if (!is_array($res) || !$res) {
            return [];
        }
        $sui = 0;
        $tokens = [];
        foreach ($res as $it) {
            $t = $it['coinType'] ?? '';
            $amt = (float)($it['totalBalance'] ?? 0);
            if ($t === '0x2::sui::SUI') {
                $sui = $amt / 1e9;
            } elseif (str_ends_with($t, '::usdc::USDC')) {
                $tokens[] = self::tok('USDC', $amt / 1e6);
            } elseif (str_ends_with($t, '::usdt::USDT') || str_ends_with($t, '::coin::COIN')) {
                $tokens[] = self::tok('USDT', $amt / 1e6);
            }
        }
        return [self::result('Sui', 'SUI', $sui, "https://suiscan.xyz/mainnet/account/$a", ['tokens' => $tokens])];
    }

    public static function aptos(string $a): array
    {
        $base = 'https://fullnode.mainnet.aptoslabs.com/v1';
        $res = Http::multiJson([
            'bal' => ['url' => "$base/accounts/$a/balance/0x1::aptos_coin::AptosCoin", 'timeout' => 8],
            'res' => ['url' => "$base/accounts/$a/resource/0x1::coin::CoinStore%3C0x1::aptos_coin::AptosCoin%3E", 'timeout' => 8],
            'acc' => ['url' => "$base/accounts/$a", 'timeout' => 8],
        ]);
        $raw = $res['bal'] ?? ($res['res']['data']['coin']['value'] ?? null);
        if ($raw === null || !is_numeric($raw)) {
            return [];
        }
        $acc = $res['acc'];
        return [self::result('Aptos', 'APT', $raw / 1e8, "https://explorer.aptoslabs.com/account/$a?network=mainnet", ['txs' => isset($acc['sequence_number']) ? (int)$acc['sequence_number'] : null])];
    }

    public static function check(string $kind, string $address): array
    {
        $ck = 'wlr:' . md5($kind . '|' . $address);
        $c = Cache::get($ck, $hit);
        if ($hit && is_array($c)) {
            return $c;
        }
        $found = self::lookup($kind, $address);
        if ($found) {
            Cache::set($ck, $found, 30);
        }
        return $found;
    }

    private static function lookup(string $kind, string $address): array
    {
        try {
            $found = match ($kind) {
            'hex64x' => array_merge(self::sui($address), self::aptos($address)),
            'evm' => self::evm($address),
            'btc' => self::btc($address),
            'ltc' => self::ltc($address),
            'doge' => self::doge($address),
            'dash' => self::dash($address),
            'bch' => self::bch($address),
            'tron' => self::tron($address),
            'ton' => self::ton($address),
            'sol' => self::sol($address),
            'xrp' => self::xrp($address),
            default => [],
            };
        } catch (Throwable $e) {
            error_log('wallet ' . $kind . ': ' . $e->getMessage());
            $found = [];
        }
        $found = array_values(array_filter($found, [self::class, 'active']));
        foreach ($found as &$r) {
            self::fill($r);
        }
        unset($r);
        usort($found, fn($x, $y) => self::totalUsd($y) <=> self::totalUsd($x));
        return $found;
    }
}
