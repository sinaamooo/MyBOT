<?php

final class Txs
{
    const OK = '✅ موفق';
    const FAIL = '❌ ناموفق';
    const PENDING = '⏳ در انتظار تایید';

    private static function res(string $network, string $status, string $hash, string $explorer, array $x = []): array
    {
        return array_merge(['network' => $network, 'status' => $status, 'hash' => $hash, 'explorer' => $explorer, 'amount' => null,
            'symbol' => null, 'sender' => null, 'receiver' => null, 'fee' => null, 'block' => null, 'confirmations' => null,
            'timestamp' => null, 'usd' => null, 'token_type' => null, 'token_name' => null, 'contract' => null], $x);
    }

    private static function g($v): string
    {
        return rtrim(rtrim(sprintf('%.8f', $v), '0'), '.') ?: '0';
    }

    private static function tokenMeta(array $chain, string $contract): array
    {
        foreach ($chain['tokens'] as $sym => [$addr, $dec]) {
            if (strtolower($addr) === strtolower($contract)) {
                return [$sym, $dec];
            }
        }
        return Cache::remember('tok:' . $chain['key'] . ':' . strtolower($contract), 86400 * 7, function () use ($chain, $contract) {
            $r = Chain::batch($chain['rpcs'], [['eth_call', [['to' => $contract, 'data' => '0x95d89b41'], 'latest']], ['eth_call', [['to' => $contract, 'data' => '0x313ce567'], 'latest']]]);
            return [Chain::abiString($r[0]) ?? 'TOKEN', ($r[1] && $r[1] !== '0x') ? (int)Chain::hexInt($r[1]) : 18];
        });
    }

    public static function evm(string $h): ?array
    {
        $chains = Chain::evm();
        $reqs = [];
        foreach ($chains as $i => $c) {
            $reqs[$i] = Chain::rpcReq($c['rpcs'][0], 'eth_getTransactionByHash', [$h]);
        }
        $res = Http::multiJson($reqs);
        $hit = null;
        foreach ($chains as $i => $c) {
            if (!empty($res[$i]['result'])) {
                $hit = [$c, $res[$i]['result']];
                break;
            }
        }
        if (!$hit) {
            $reqs = [];
            foreach ($chains as $i => $c) {
                if (!is_array($res[$i]) && isset($c['rpcs'][1])) {
                    $reqs[$i] = Chain::rpcReq($c['rpcs'][1], 'eth_getTransactionByHash', [$h]);
                }
            }
            foreach ($reqs ? Http::multiJson($reqs) : [] as $i => $d) {
                if (!empty($d['result'])) {
                    $hit = [$chains[$i], $d['result']];
                    break;
                }
            }
        }
        if (!$hit) {
            return null;
        }
        [$c, $tx] = $hit;
        [$receipt, $head] = Chain::batch($c['rpcs'], [['eth_getTransactionReceipt', [$h]], ['eth_blockNumber', []]]);
        $block = !empty($tx['blockNumber']) ? (int)Chain::hexInt($tx['blockNumber']) : null;
        $status = self::PENDING;
        $fee = null;
        if ($receipt) {
            $status = (int)Chain::hexInt($receipt['status'] ?? '0x0') === 1 ? self::OK : self::FAIL;
            $gp = Chain::hexInt($receipt['effectiveGasPrice'] ?? $tx['gasPrice'] ?? '0x0');
            $fee = self::g(Chain::hexInt($receipt['gasUsed'] ?? '0x0') * $gp / 1e18) . ' ' . $c['symbol'];
        }
        $ts = null;
        if ($block !== null) {
            $blk = Chain::rpc($c['rpcs'], 'eth_getBlockByNumber', ['0x' . dechex($block), false]);
            $ts = $blk ? (int)Chain::hexInt($blk['timestamp'] ?? '0x0') : null;
        }
        $amount = Chain::hexInt($tx['value'] ?? '0x0') / 1e18;
        $symbol = $c['symbol'];
        $sender = $tx['from'] ?? null;
        $receiver = $tx['to'] ?? null;
        $tokenType = 'COIN';
        $contract = null;
        $found = false;
        foreach ($receipt['logs'] ?? [] as $log) {
            $topics = $log['topics'] ?? [];
            if (count($topics) === 3 && strtolower($topics[0]) === Chain::TRANSFER) {
                [$sym, $dec] = self::tokenMeta($c, $log['address']);
                $amount = Chain::hexInt($log['data'] ?? '0x0') / 10 ** $dec;
                $symbol = $sym;
                $sender = Chain::topicAddr($topics[1]);
                $receiver = Chain::topicAddr($topics[2]);
                $tokenType = 'TOKEN';
                $contract = $log['address'];
                $found = true;
                break;
            }
        }
        $input = $tx['input'] ?? '';
        if (!$found && !$receipt && str_starts_with($input, '0xa9059cbb') && strlen($input) >= 138) {
            [$sym, $dec] = self::tokenMeta($c, $tx['to']);
            $receiver = '0x' . substr($input, 34, 40);
            $amount = Chain::hexInt('0x' . substr($input, 74, 64)) / 10 ** $dec;
            $symbol = $sym;
            $tokenType = 'TOKEN';
            $contract = $tx['to'];
        }
        $conf = ($head && $block !== null) ? (int)(Chain::hexInt($head) - $block + 1) : 0;
        return self::res($c['name'], $status, $h, $c['explorer'] . '/tx/' . $h, ['amount' => $amount, 'symbol' => $symbol, 'sender' => $sender,
            'receiver' => $receiver, 'fee' => $fee, 'block' => $block, 'confirmations' => $conf, 'timestamp' => $ts,
            'token_type' => $tokenType, 'token_name' => $symbol, 'contract' => $contract]);
    }

    private static function esploraParse($tx, string $h, string $base, string $name, string $sym, string $explorer): ?array
    {
        if (!is_array($tx) || ($tx['txid'] ?? null) !== $h) {
            return null;
        }
        $st = $tx['status'] ?? [];
        $tip = Http::text("$base/api/blocks/tip/height", ['timeout' => 8]);
        $conf = !empty($st['confirmed']) && $tip !== null && ctype_digit(trim($tip)) ? (int)trim($tip) - (int)$st['block_height'] + 1 : 0;
        $senders = [];
        foreach ($tx['vin'] ?? [] as $v) {
            if (!empty($v['prevout']['scriptpubkey_address'])) {
                $senders[$v['prevout']['scriptpubkey_address']] = 1;
            }
        }
        $vout = $tx['vout'] ?? [];
        $total = array_sum(array_map(fn($o) => $o['value'] ?? 0, $vout)) / 1e8;
        usort($vout, fn($a, $b) => ($b['value'] ?? 0) <=> ($a['value'] ?? 0));
        $sender = array_key_first($senders);
        if (count($senders) > 1) {
            $sender .= ' (+' . (count($senders) - 1) . ')';
        }
        return self::res($name, !empty($st['confirmed']) ? self::OK : self::PENDING, $h, "$explorer/tx/$h", ['amount' => $total, 'symbol' => $sym,
            'sender' => $sender, 'receiver' => $vout[0]['scriptpubkey_address'] ?? null, 'fee' => self::g(($tx['fee'] ?? 0) / 1e8) . " $sym",
            'block' => $st['block_height'] ?? null, 'confirmations' => $conf, 'timestamp' => $st['block_time'] ?? null, 'token_type' => 'COIN', 'token_name' => $name]);
    }

    private static function blockcypherParse($tx, string $h, string $name, string $sym, string $explorer): ?array
    {
        if (!is_array($tx) || !empty($tx['error']) || ($tx['hash'] ?? null) !== $h) {
            return null;
        }
        $ins = [];
        foreach ($tx['inputs'] ?? [] as $i) {
            foreach ($i['addresses'] ?? [] as $a) {
                $ins[] = $a;
            }
        }
        $outs = $tx['outputs'] ?? [];
        usort($outs, fn($a, $b) => ($b['value'] ?? 0) <=> ($a['value'] ?? 0));
        $conf = (int)($tx['confirmations'] ?? 0);
        return self::res($name, $conf ? self::OK : self::PENDING, $h, $explorer, ['amount' => ($tx['total'] ?? 0) / 1e8, 'symbol' => $sym,
            'sender' => $ins[0] ?? null, 'receiver' => $outs[0]['addresses'][0] ?? null, 'fee' => self::g(($tx['fees'] ?? 0) / 1e8) . " $sym",
            'block' => ($tx['block_height'] ?? -1) > 0 ? $tx['block_height'] : null, 'confirmations' => $conf, 'token_type' => 'COIN', 'token_name' => $name]);
    }

    public static function tronscanReq(string $h): array
    {
        return ['url' => 'https://apilist.tronscanapi.com/api/transaction-info', 'params' => ['hash' => $h], 'headers' => Chain::tronscanHeaders(), 'timeout' => 10];
    }

    public static function tronscanParse($info, string $h): ?array
    {
        if (!is_array($info) || empty($info['hash']) || !array_key_exists('contractRet', $info) || $info['contractRet'] === null) {
            return null;
        }
        $confirmed = !empty($info['confirmed']);
        $status = $confirmed ? ($info['contractRet'] === 'SUCCESS' ? self::OK : self::FAIL) : self::PENDING;
        $cd = $info['contractData'] ?? [];
        $amount = null;
        $symbol = 'TRX';
        $type = 'COIN';
        $name = 'TRON';
        $contract = null;
        $sender = $cd['owner_address'] ?? $info['ownerAddress'] ?? null;
        $receiver = $cd['to_address'] ?? $info['toAddress'] ?? null;
        $t20 = $info['trc20TransferInfo'] ?? $info['tokenTransferInfo'] ?? null;
        if (is_array($t20) && array_is_list($t20)) {
            $t20 = $t20[0] ?? null;
        }
        if (is_array($t20) && isset($t20['amount_str'])) {
            $dec = (int)($t20['decimals'] ?? 6);
            $amount = (float)$t20['amount_str'] / 10 ** $dec;
            $symbol = $t20['symbol'] ?? 'TRC20';
            $type = 'TRC20';
            $name = $t20['name'] ?? $symbol;
            $contract = $t20['contract_address'] ?? $cd['contract_address'] ?? null;
            $sender = $t20['from_address'] ?? $sender;
            $receiver = $t20['to_address'] ?? $receiver;
        } elseif (!empty($cd['asset_name']) && isset($cd['amount'])) {
            $ti = $cd['tokenInfo'] ?? [];
            $amount = (float)$cd['amount'] / 10 ** (int)($ti['tokenDecimal'] ?? 0);
            $symbol = $ti['tokenAbbr'] ?? (string)$cd['asset_name'];
            $type = 'TRC10';
            $name = $ti['tokenName'] ?? $symbol;
            $contract = (string)($ti['tokenId'] ?? $cd['asset_name']);
        } elseif (isset($cd['amount'])) {
            $amount = (float)$cd['amount'] / 1e6;
        }
        $cost = $info['cost'] ?? [];
        $fee = self::g(($cost['fee'] ?? 0) / 1e6) . ' TRX';
        if (!empty($cost['energy_usage_total'])) {
            $fee .= ' • ⚡️ ' . number_format($cost['energy_usage_total']) . ' Energy';
        }
        return self::res('TRON', $status, $h, "https://tronscan.org/#/transaction/$h", ['amount' => $amount, 'symbol' => $symbol, 'sender' => $sender,
            'receiver' => $receiver, 'fee' => $fee, 'block' => $info['block'] ?? null, 'confirmations' => $info['confirmations'] ?? null,
            'timestamp' => isset($info['timestamp']) ? intdiv((int)$info['timestamp'], 1000) : null, 'token_type' => $type, 'token_name' => $name, 'contract' => $contract]);
    }

    public static function tronGrid(string $h): ?array
    {
        $tx = Http::postJson('https://api.trongrid.io/wallet/gettransactionbyid', ['value' => $h], ['headers' => Chain::tronHeaders(), 'timeout' => 10]);
        if (!is_array($tx) || empty($tx['txID'])) {
            return null;
        }
        $info = Http::postJson('https://api.trongrid.io/wallet/gettransactioninfobyid', ['value' => $h], ['headers' => Chain::tronHeaders(), 'timeout' => 10]) ?: [];
        $c = $tx['raw_data']['contract'][0] ?? [];
        $v = $c['parameter']['value'] ?? [];
        $amount = null;
        $symbol = 'TRX';
        $type = 'COIN';
        $name = 'TRON';
        $contract = null;
        $sender = Chain::tronB58($v['owner_address'] ?? '');
        $receiver = !empty($v['to_address']) ? Chain::tronB58($v['to_address']) : null;
        if (($c['type'] ?? '') === 'TransferContract') {
            $amount = ($v['amount'] ?? 0) / 1e6;
        } elseif (($c['type'] ?? '') === 'TransferAssetContract') {
            $asset = @hex2bin($v['asset_name'] ?? '') ?: ($v['asset_name'] ?? '');
            $amount = (float)($v['amount'] ?? 0);
            $symbol = $name = $contract = (string)$asset;
            $type = 'TRC10';
        } elseif (($c['type'] ?? '') === 'TriggerSmartContract') {
            $data = $v['data'] ?? '';
            $contract = Chain::tronB58($v['contract_address'] ?? '');
            $type = 'TRC20';
            if (str_starts_with($data, 'a9059cbb') && strlen($data) >= 136) {
                [$sym, $dec] = Chain::trc20()[$contract] ?? ['TRC20', 6];
                $receiver = Chain::tronB58('41' . substr($data, 32, 40));
                $amount = Chain::hexInt(substr($data, 72, 64)) / 10 ** $dec;
                $symbol = $name = $sym;
            }
        }
        $ret = $tx['ret'][0]['contractRet'] ?? null;
        $status = ($ret === 'SUCCESS' && !empty($info['blockNumber'])) ? self::OK : (($ret && $ret !== 'SUCCESS') ? self::FAIL : self::PENDING);
        return self::res('TRON', $status, $h, "https://tronscan.org/#/transaction/$h", ['amount' => $amount, 'symbol' => $symbol, 'sender' => $sender,
            'receiver' => $receiver, 'fee' => self::g(($info['fee'] ?? 0) / 1e6) . ' TRX', 'block' => $info['blockNumber'] ?? null,
            'timestamp' => !empty($info['blockTimeStamp']) ? intdiv((int)$info['blockTimeStamp'], 1000) : null, 'token_type' => $type, 'token_name' => $name, 'contract' => $contract]);
    }

    public static function tron(string $h): ?array
    {
        return self::tronscanParse(Http::decode(Http::request(self::tronscanReq($h))), $h) ?? self::tronGrid($h);
    }

    private static function tonVariants(string $h): array
    {
        $out = [$h];
        if (strlen($h) === 64 && ctype_xdigit($h)) {
            $out[] = rtrim(strtr(base64_encode(hex2bin($h)), '+/', '-_'), '=');
        } else {
            $raw = base64_decode(strtr($h, '-_', '+/'), true);
            if ($raw !== false) {
                $out[] = bin2hex($raw);
            }
        }
        return $out;
    }

    private static function tonEventParse($ev, string $h, string $hex): ?array
    {
        if (!is_array($ev) || empty($ev['actions'])) {
            return null;
        }
        $a = $ev['actions'][0];
        $kind = $a['type'] ?? '';
        $b = $a[$kind] ?? [];
        $amount = null;
        $symbol = 'TON';
        if ($kind === 'TonTransfer') {
            $amount = ($b['amount'] ?? 0) / 1e9;
        } elseif ($kind === 'JettonTransfer') {
            $j = $b['jetton'] ?? [];
            $amount = (float)($b['amount'] ?? 0) / 10 ** (int)($j['decimals'] ?? 9);
            $symbol = $j['symbol'] ?? 'JETTON';
        } elseif ($kind === 'NftItemTransfer') {
            $symbol = 'NFT';
        }
        $status = !empty($ev['in_progress']) ? self::PENDING : (($a['status'] ?? '') === 'ok' ? self::OK : self::FAIL);
        $r = self::res('TON', $status, $h, "https://tonviewer.com/transaction/$hex", ['amount' => $amount, 'symbol' => $symbol,
            'sender' => $b['sender']['address'] ?? ($a['simple_preview']['accounts'][0]['address'] ?? null), 'receiver' => $b['recipient']['address'] ?? null,
            'timestamp' => $ev['timestamp'] ?? null, 'token_type' => $kind === 'JettonTransfer' ? 'JETTON' : 'COIN', 'token_name' => $symbol]);
        if ($amount === null && !empty($a['simple_preview']['description'])) {
            $r['symbol'] = $a['simple_preview']['description'];
        }
        return $r;
    }

    public static function ton(string $h): ?array
    {
        $hex = null;
        foreach (self::tonVariants($h) as $v) {
            if (strlen($v) === 64 && ctype_xdigit($v)) {
                $hex = $v;
            }
        }
        if ($hex) {
            $r = self::tonEventParse(Http::json("https://tonapi.io/v2/events/$hex", ['headers' => Chain::tonapiHeaders(), 'timeout' => 10]), $h, $hex);
            if ($r) {
                return $r;
            }
        }
        foreach (self::tonVariants($h) as $v) {
            $d = Http::json('https://toncenter.com/api/v3/transactions', ['params' => ['hash' => $v, 'limit' => 1] + Chain::toncenterParams(), 'timeout' => 10]);
            $tx = $d['transactions'][0] ?? null;
            if ($tx) {
                $book = $d['address_book'] ?? [];
                $fr = fn($x) => $x ? ($book[$x]['user_friendly'] ?? $x) : null;
                $in = $tx['in_msg'] ?? [];
                return self::res('TON', !empty($tx['description']['aborted']) ? self::FAIL : self::OK, $h, 'https://tonviewer.com/transaction/' . ($tx['hash'] ?? $h), [
                    'amount' => ($in['value'] ?? 0) / 1e9, 'symbol' => 'TON', 'sender' => $fr($in['source'] ?? null), 'receiver' => $fr($in['destination'] ?? null),
                    'fee' => self::g(($tx['total_fees'] ?? 0) / 1e9) . ' TON', 'block' => $tx['mc_block_seqno'] ?? null, 'timestamp' => $tx['now'] ?? null, 'token_type' => 'COIN', 'token_name' => 'TON']);
            }
        }
        return null;
    }

    public static function sol(string $sig): ?array
    {
        $tx = Chain::rpc(Chain::rpcs('sol_rpcs'), 'getTransaction', [$sig, ['encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0]]);
        if (!$tx) {
            return null;
        }
        $meta = $tx['meta'] ?? [];
        $ins = $tx['transaction']['message']['instructions'] ?? [];
        foreach ($meta['innerInstructions'] ?? [] as $inner) {
            foreach ($inner['instructions'] ?? [] as $x) {
                $ins[] = $x;
            }
        }
        $amount = $symbol = $sender = $receiver = null;
        foreach ($ins as $i) {
            $p = $i['parsed'] ?? null;
            if (!is_array($p)) {
                continue;
            }
            $info = $p['info'] ?? [];
            if (($p['type'] ?? '') === 'transfer' && isset($info['lamports'])) {
                $amount = $info['lamports'] / 1e9;
                $symbol = 'SOL';
                $sender = $info['source'] ?? null;
                $receiver = $info['destination'] ?? null;
                break;
            }
            if (in_array($p['type'] ?? '', ['transfer', 'transferChecked'], true) && (isset($info['tokenAmount']) || isset($info['amount']))) {
                $amount = isset($info['tokenAmount']) ? (float)($info['tokenAmount']['uiAmount'] ?? 0) : null;
                $symbol = Chain::solTokens()[$info['mint'] ?? ''] ?? 'SPL';
                if ($amount === null) {
                    foreach ($meta['postTokenBalances'] ?? [] as $b) {
                        if (isset(Chain::solTokens()[$b['mint'] ?? ''])) {
                            $symbol = Chain::solTokens()[$b['mint']];
                            $amount = (float)($info['amount'] ?? 0) / 10 ** (int)($b['uiTokenAmount']['decimals'] ?? 6);
                            break;
                        }
                    }
                }
                $sender = $info['authority'] ?? $info['source'] ?? null;
                $receiver = $info['destination'] ?? null;
                break;
            }
        }
        return self::res('Solana', !empty($meta['err']) ? self::FAIL : self::OK, $sig, "https://solscan.io/tx/$sig", ['amount' => $amount, 'symbol' => $symbol,
            'sender' => $sender, 'receiver' => $receiver, 'fee' => self::g(($meta['fee'] ?? 0) / 1e9) . ' SOL', 'block' => $tx['slot'] ?? null,
            'timestamp' => $tx['blockTime'] ?? null, 'token_type' => $symbol === 'SOL' ? 'COIN' : 'SPL', 'token_name' => $symbol]);
    }

    private static function xrpParse($d, string $h): ?array
    {
        $r = $d['result'] ?? [];
        if (empty($r['hash'])) {
            return null;
        }
        $tj = $r['tx_json'] ?? $r;
        $amt = $r['meta']['delivered_amount'] ?? $tj['Amount'] ?? $tj['DeliverMax'] ?? null;
        if (is_array($amt)) {
            $amount = (float)($amt['value'] ?? 0);
            $symbol = $amt['currency'] ?? 'IOU';
        } else {
            $amount = $amt !== null ? $amt / 1e6 : null;
            $symbol = 'XRP';
        }
        $code = $r['meta']['TransactionResult'] ?? null;
        $status = empty($r['validated']) ? self::PENDING : ($code === 'tesSUCCESS' ? self::OK : self::FAIL);
        $date = $tj['date'] ?? $r['date'] ?? null;
        return self::res('XRP Ledger', $status, $h, "https://xrpscan.com/tx/$h", ['amount' => $amount, 'symbol' => $symbol, 'sender' => $tj['Account'] ?? null,
            'receiver' => $tj['Destination'] ?? null, 'fee' => self::g(($tj['Fee'] ?? 0) / 1e6) . ' XRP', 'block' => $r['ledger_index'] ?? null,
            'timestamp' => $date ? $date + 946684800 : null, 'token_type' => 'COIN', 'token_name' => $symbol]);
    }

    public static function hex64(string $h): ?array
    {
        $lower = strtolower($h);
        $res = Http::multiJson([
            'tron' => self::tronscanReq($lower),
            'btc' => ['url' => "https://mempool.space/api/tx/$lower", 'timeout' => 10],
            'ltc' => ['url' => "https://litecoinspace.org/api/tx/$lower", 'timeout' => 10],
            'doge' => ['url' => "https://api.blockcypher.com/v1/doge/main/txs/$lower", 'timeout' => 10],
            'dash' => ['url' => "https://api.blockcypher.com/v1/dash/main/txs/$lower", 'timeout' => 10],
            'xrp' => ['url' => Chain::rpcs('xrp_rpcs')[0], 'method' => 'POST', 'json' => ['method' => 'tx', 'params' => [['transaction' => strtoupper($h), 'binary' => false]]], 'timeout' => 10],
            'ton' => ['url' => "https://tonapi.io/v2/events/$lower", 'headers' => Chain::tonapiHeaders(), 'timeout' => 10],
        ]);
        return self::tronscanParse($res['tron'], $lower)
            ?? self::esploraParse($res['btc'], $lower, 'https://mempool.space', 'Bitcoin', 'BTC', 'https://mempool.space')
            ?? self::esploraParse($res['ltc'], $lower, 'https://litecoinspace.org', 'Litecoin', 'LTC', 'https://litecoinspace.org')
            ?? self::blockcypherParse($res['doge'], $lower, 'Dogecoin', 'DOGE', "https://blockchair.com/dogecoin/transaction/$lower")
            ?? self::blockcypherParse($res['dash'], $lower, 'Dash', 'DASH', "https://blockchair.com/dash/transaction/$lower")
            ?? self::xrpParse($res['xrp'], strtoupper($h))
            ?? self::tonEventParse($res['ton'], $h, $lower)
            ?? (is_array($res['tron']) ? null : self::tronGrid($lower))
            ?? self::evm('0x' . $lower);
    }

    public static function check(string $kind, string $h): ?array
    {
        $r = match ($kind) {
            'hex64x' => self::evm($h),
            'hex64' => self::hex64($h),
            'solsig' => self::sol($h),
            'b64hash', 'sol' => self::ton($h),
            default => null,
        };
        if ($r && $r['usd'] === null && $r['amount'] !== null && $r['symbol']) {
            $s = strtoupper($r['symbol']);
            if (in_array($s, Prices::STABLES, true) || in_array($s, ['USDC.E', 'BUSD'], true)) {
                $r['usd'] = $r['amount'];
            } elseif (preg_match('/^[A-Z0-9]{2,10}$/', $s)) {
                $p = Prices::usd($s === 'WBTC' ? 'BTC' : $s);
                $r['usd'] = $p ? $r['amount'] * $p : null;
            }
        }
        return $r;
    }
}
