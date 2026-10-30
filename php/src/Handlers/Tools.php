<?php

final class Tools
{
    const MARKET = [['💰', 'BTC'], ['💎', 'ETH'], ['💠', 'TON'], ['🔴', 'TRX'], ['🪙', 'BNB'], ['🌟', 'SOL'], ['🐸', 'DOGE'], ['🔲', 'NOT']];

    public static function market(array $m): void
    {
        $rate = Prices::usdtToman();
        $lines = [];
        foreach (self::MARKET as [$icon, $sym]) {
            $q = Prices::quote($sym);
            if (!$q) {
                continue;
            }
            $lines[] = UI::fill(UI::template('market_line'), ['icon' => $icon, 'symbol' => $sym, 'usd' => Fmt::usd($q['price']),
                'toman' => $rate ? Fmt::toman($q['price'] * $rate) : '—', 'change' => $sym !== 'USDT' ? Fmt::badge($q['change']) : '']);
        }
        if (!$lines) {
            Bot::send($m, UI::t('price_unavailable'));
            return;
        }
        Bot::send($m, UI::t('market', ['lines' => implode("\n", $lines), 'usdt_toman' => $rate ? Fmt::toman($rate) : null, 'time' => Fmt::now()]));
    }

    public static function answerAmount(array $m, float $amount, string $unit, bool $silent): bool
    {
        $r = Currencies::resolve($unit);
        if (!$r) {
            return false;
        }
        $a = Convert::run($amount, $r[0], $r[1]);
        if (!$a) {
            if (!$silent) {
                Bot::send($m, UI::t('currency_not_found', ['query' => UI::esc($unit)]));
                return true;
            }
            return false;
        }
        self::sendPriced($m, $a);
        return true;
    }

    public static function sendPriced(array $m, array $a): void
    {
        $photo = null;
        $fk = !empty($a['key']) ? 'fid:' . md5($a['key']) : null;
        if ($fk && ($fid = Cache::get($fk))) {
            $photo = $fid;
        } elseif (!empty($a['card'])) {
            try {
                $photo = ($a['card'])();
            } catch (Throwable $e) {
                error_log('card: ' . $e->getMessage());
            }
        }
        $sent = Bot::send($m, $a['text'], false, $photo ?: null);
        if ($fk && !empty($sent['photo']) && !isset($fid)) {
            $big = end($sent['photo']);
            if (!empty($big['file_id'])) {
                Cache::set($fk, $big['file_id'], 3 * 86400);
            }
        }
    }

    public static function premium(array $m): void
    {
        Bot::send($m, Convert::premiumText() ?? UI::t('price_unavailable'));
    }

    public static function starsBoard(array $m): void
    {
        Bot::send($m, Convert::starsText() ?? UI::t('price_unavailable'));
    }

    public static function price(array $m, string $args): void
    {
        if ($args === '') {
            self::market($m);
            return;
        }
        $p = Parse::amount($args);
        $p ? self::answerAmount($m, $p[0], $p[1], false) : self::answerAmount($m, 1, $args, false);
    }

    public static function walletCmd(array $m, string $args): void
    {
        $f = Chain::extract($args, false);
        if (!$f) {
            Bot::send($m, UI::t('help'));
            return;
        }
        self::runChecker($m, $f[0], $f[1]);
    }

    public static function runChecker(array $m, string $kind, string $token, ?int $owner = null): void
    {
        $owner = $owner ?? ($m['from']['id'] ?? 0);
        $isHash = in_array($kind, Chain::HASH_KINDS, true);
        $wait = Bot::send($m, UI::t($isHash ? 'tx_checking' : 'wallet_checking'), null);
        try {
            if ($isHash) {
                $tx = Txs::check($kind, $token);
                if ($tx) {
                    self::sendTx($m, $tx, $owner);
                    return;
                }
                if ($kind === 'hex64x' && ($w = Wallets::check($kind, $token))) {
                    self::sendWallet($m, $token, $w, $owner);
                    return;
                }
                Bot::send($m, UI::t('tx_not_found', ['hash' => UI::esc($token)]));
                return;
            }
            $w = Wallets::check($kind, $token);
            if (!$w && $kind === 'sol' && in_array(strlen($token), [43, 44], true) && ($tx = Txs::check('sol', $token))) {
                self::sendTx($m, $tx, $owner);
                return;
            }
            $w ? self::sendWallet($m, $token, $w, $owner) : Bot::send($m, UI::t('wallet_not_found', ['address' => UI::esc($token)]));
        } finally {
            Bot::deleteMsg($wait);
        }
    }

    public static function sendWallet(array $m, string $address, array $found, int $owner): void
    {
        $rate = Prices::usdtToman();
        $parts = [UI::t('wallet_header', ['address' => UI::esc($address), 'count' => count($found)])];
        $total = 0.0;
        foreach ($found as $r) {
            $total += Wallets::totalUsd($r);
            $tokens = implode("\n", array_map(fn($t) => '   • <code>' . Fmt::num($t['amount']) . '</code> ' . UI::esc($t['symbol']) . ($t['usd'] ? ' (≈ ' . Fmt::usd($t['usd']) . '$)' : ''), array_slice($r['tokens'], 0, 12)));
            $parts[] = UI::t('wallet_chain', [
                'chain' => UI::esc($r['chain']), 'balance' => Fmt::num($r['balance']), 'symbol' => $r['symbol'],
                'usd' => $r['usd'] !== null ? Fmt::usd($r['usd']) : null, 'toman' => $r['usd'] !== null && $rate ? Fmt::toman($r['usd'] * $rate) : null,
                'tokens' => $tokens !== '' ? $tokens : null,
                'txs' => is_numeric($r['txs']) ? Fmt::int($r['txs']) : ($r['txs'] ?: null),
                'nfts' => $r['nfts'] ? Fmt::int($r['nfts']) : null, 'created' => $r['created'] ? Fmt::jalali($r['created']) : null,
                'status' => $r['status'], 'explorer' => UI::attr($r['explorer']),
            ]);
        }
        $parts[] = UI::t('wallet_footer', ['total_usd' => Fmt::usd($total), 'total_toman' => $rate ? Fmt::toman($total * $rate) : null, 'time' => Fmt::now()]);
        $text = implode("\n", $parts);
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990);
            $text = mb_substr($text, 0, mb_strrpos($text, "\n"));
        }
        $rows = [];
        foreach ($found as $r) {
            if ($r['kind'] === 'tron') {
                $k = Store::put(['addr' => $address, 'owner' => $owner]);
                $rows[] = [UI::button('btn_trc20_history', ['cb' => "tt:$k:1"]), UI::button('btn_tokens_file', ['cb' => "tj:$k"])];
                break;
            }
        }
        Bot::send($m, $text, UI::footer($rows));
    }

    public static function sendTx(array $m, array $tx, int $owner): void
    {
        $rate = Prices::usdtToman();
        $amount = $tx['amount'] !== null ? Fmt::num($tx['amount']) . ' ' . UI::esc($tx['symbol'] ?? '') : ($tx['symbol'] ? UI::esc($tx['symbol']) : null);
        $text = UI::t('tx_result', [
            'network' => UI::esc($tx['network']), 'status' => $tx['status'], 'amount' => $amount,
            'usd' => $tx['usd'] ? Fmt::usd($tx['usd']) : null, 'toman' => $tx['usd'] && $rate ? Fmt::toman($tx['usd'] * $rate) : null,
            'token' => $tx['token_type'] ? UI::esc(($tx['token_name'] ?: $tx['symbol']) . ' (' . $tx['token_type'] . ')') : null,
            'contract' => $tx['contract'] ? UI::esc($tx['contract']) : null,
            'sender' => $tx['sender'] ? UI::esc($tx['sender']) : null, 'receiver' => $tx['receiver'] ? UI::esc($tx['receiver']) : null,
            'fee' => $tx['fee'] ? UI::esc($tx['fee']) : null, 'block' => $tx['block'],
            'confirmations' => is_int($tx['confirmations']) ? Fmt::int($tx['confirmations']) : ($tx['confirmations'] ?: null),
            'time' => $tx['timestamp'] ? Fmt::jalali((int)$tx['timestamp']) : null, 'hash' => UI::esc($tx['hash']), 'explorer' => UI::attr($tx['explorer']),
        ]);
        $row = [];
        foreach ([[$tx['sender'], 'btn_sender_wallet'], [$tx['receiver'], 'btn_receiver_wallet']] as [$addr, $key]) {
            $f = Chain::extract(explode(' ', (string)$addr)[0], true);
            if ($f && in_array($f[0], Chain::ADDRESS_KINDS, true)) {
                $row[] = UI::button($key, ['cb' => 'wl:' . Store::put(['addr' => $f[1], 'kind' => $f[0], 'owner' => $owner])]);
            }
        }
        Bot::send($m, $text, UI::footer($row ? [$row] : []));
    }

    private static function owned(array $cq, ?array $d): ?string
    {
        if (!$d) {
            return 'expired';
        }
        return (!empty($d['owner']) && $d['owner'] !== $cq['from']['id']) ? 'not_your_button' : null;
    }

    public static function walletButton(array $cq, string $key): void
    {
        $d = Store::get($key);
        if ($err = self::owned($cq, $d)) {
            TG::answer($cq['id'], UI::plain($err), true);
            return;
        }
        TG::answer($cq['id']);
        self::runChecker($cq['message'], $d['kind'], $d['addr'], $cq['from']['id']);
    }

    public static function transfers(array $cq, string $arg): void
    {
        [$key, $page] = array_pad(explode(':', $arg), 2, '1');
        $edit = str_starts_with($cq['data'], 'tp:');
        $d = Store::get($key);
        if ($err = self::owned($cq, $d)) {
            TG::answer($cq['id'], UI::plain($err), true);
            return;
        }
        $page = max(1, (int)$page);
        $res = Wallets::tronTransfers($d['addr'], $page);
        if (!$res) {
            TG::answer($cq['id'], UI::plain('price_unavailable'), true);
            return;
        }
        if (!$res['items']) {
            TG::answer($cq['id'], UI::plain('tron_transfers_empty'), true);
            return;
        }
        TG::answer($cq['id']);
        $items = [];
        foreach ($res['items'] as $it) {
            $in = ($it['to'] ?? '') === $d['addr'];
            $items[] = UI::fill(UI::template('tron_transfer_item'), ['direction' => $in ? '📥' : '📤', 'amount' => Fmt::num($it['amount']),
                'symbol' => UI::esc($it['symbol']), 'time' => $it['ts'] ? Fmt::jalali($it['ts']) : '—',
                'party' => UI::esc($in ? $it['from'] : $it['to']), 'hash' => UI::esc($it['hash'] ?? '')]);
        }
        $text = UI::t('tron_transfers', ['address' => UI::esc($d['addr']), 'page' => $res['page'], 'pages' => $res['pages'], 'total' => Fmt::int($res['total']), 'items' => implode("\n", $items)]);
        $nav = [];
        if ($page > 1) {
            $nav[] = UI::button('btn_prev', ['cb' => "tp:$key:" . ($page - 1)]);
        }
        if ($page < $res['pages']) {
            $nav[] = UI::button('btn_next', ['cb' => "tp:$key:" . ($page + 1)]);
        }
        $kb = $nav ? UI::kb([$nav]) : null;
        $chat = $cq['message']['chat']['id'];
        if ($edit && TG::edit($chat, $cq['message']['message_id'], $text, $kb) !== null) {
            return;
        }
        TG::send($chat, $text, $kb);
    }

    public static function tokensFile(array $cq, string $key): void
    {
        $d = Store::get($key);
        if ($err = self::owned($cq, $d)) {
            TG::answer($cq['id'], UI::plain($err), true);
            return;
        }
        $res = Wallets::tronAllTokens($d['addr']);
        if (!$res) {
            TG::answer($cq['id'], UI::plain('price_unavailable'), true);
            return;
        }
        TG::answer($cq['id']);
        TG::document($cq['message']['chat']['id'], json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'tokens_' . substr($d['addr'], 0, 8) . '.json', UI::t('tron_tokens_file', ['total' => $res['total'], 'address' => UI::esc($d['addr'])]));
    }

    public static function uid(array $m, string $args = ''): void
    {
        $r = $m['reply_to_message'] ?? null;
        if ($r && isset($r['sender_chat'])) {
            $c = $r['sender_chat'];
            Bot::send($m, UI::t('uid', ['name' => UI::esc($c['title'] ?? ''), 'id' => $c['id'], 'username' => UI::uname($c['username'] ?? null)]),
                UI::kb([[UI::button('btn_copy_id', ['copy' => (string)$c['id']])]]));
            return;
        }
        $t = $r['from'] ?? $m['from'] ?? null;
        if (!$t) {
            return;
        }
        Bot::send($m, UI::t('uid', ['name' => UI::mention($t), 'id' => $t['id'], 'username' => UI::uname($t['username'] ?? null)]),
            UI::kb([[UI::button('btn_copy_id', ['copy' => (string)$t['id']])]]));
    }

    private static function entityText(string $text, array $e): string
    {
        $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        return mb_convert_encoding(substr($u16, $e['offset'] * 2, $e['length'] * 2), 'UTF-8', 'UTF-16LE');
    }

    public static function emoji(array $m, string $args = ''): void
    {
        $items = [];
        foreach (array_filter([$m, $m['reply_to_message'] ?? null]) as $x) {
            $text = $x['text'] ?? $x['caption'] ?? '';
            foreach ($x['entities'] ?? $x['caption_entities'] ?? [] as $e) {
                if ($e['type'] === 'custom_emoji' && !empty($e['custom_emoji_id'])) {
                    $items[$e['custom_emoji_id']] = self::entityText($text, $e);
                }
            }
            if (!empty($x['sticker']['custom_emoji_id'])) {
                $items[$x['sticker']['custom_emoji_id']] = $x['sticker']['emoji'] ?? '⭐️';
            }
        }
        $items = array_slice($items, 0, 25, true);
        if (!$items) {
            Bot::send($m, UI::t('emoji_none'));
            return;
        }
        $parts = [];
        foreach ($items as $id => $char) {
            $parts[] = UI::t('emoji_item', ['emoji' => '<tg-emoji emoji-id="' . $id . '">' . UI::esc($char) . '</tg-emoji>', 'id' => $id]);
        }
        Bot::send($m, mb_substr(implode("\n", $parts), 0, 4000));
    }

    public static function account(array $m, string $args = ''): void
    {
        $t = $m['from'];
        if (!empty($m['reply_to_message']['from'])) {
            $t = $m['reply_to_message']['from'];
        } elseif (preg_match('/^-?\d+$/', $args)) {
            $mem = TG::api('getChatMember', ['chat_id' => $m['chat']['id'], 'user_id' => (int)$args]);
            if ($mem) {
                $t = $mem['user'];
            }
        }
        $info = TG::api('getChat', ['chat_id' => $t['id']]);
        $photos = TG::api('getUserProfilePhotos', ['user_id' => $t['id'], 'limit' => 1]);
        $gifts = TG::api('getUserGifts', ['user_id' => $t['id'], 'limit' => 1]);
        $row = DB::user($t['id']) ?? [];
        $photo = !empty($photos['total_count']) ? end($photos['photos'][0])['file_id'] : null;
        $dc = $photo ? Account::dc($photo) : null;
        $level = null;
        if (!empty($info['rating'])) {
            $r = $info['rating'];
            $level = '⭐️ ' . $r['level'] . (!empty($r['next_level_rating']) ? ' (' . Fmt::int($r['rating'] ?? 0) . '/' . Fmt::int($r['next_level_rating']) . ')' : '');
        }
        $bd = null;
        if (!empty($info['birthdate'])) {
            $b = $info['birthdate'];
            $bd = !empty($b['year']) ? sprintf('%d/%02d/%02d', $b['year'], $b['month'], $b['day']) : sprintf('%02d/%02d', $b['month'], $b['day']);
        }
        $xp = (int)($row['xp'] ?? 0);
        $text = UI::t('account', [
            'name' => UI::mention($t), 'id' => $t['id'], 'username' => UI::uname($t['username'] ?? null),
            'premium' => !empty($t['is_premium']) ? '✅ دارد' : '❌ ندارد', 'level' => $level,
            'created' => Fmt::jalaliLong(Account::created((int)$t['id'])), 'dc' => $dc ? "DC$dc • " . (Account::DC[$dc] ?? '') : null,
            'lang' => !empty($t['language_code']) ? strtoupper($t['language_code']) : null, 'birthdate' => $bd,
            'bio' => !empty($info['bio']) ? UI::esc($info['bio']) : null, 'gifts' => $gifts ? Fmt::int($gifts['total_count'] ?? 0) : null,
            'bot_level' => Account::level($xp), 'xp' => Fmt::int($xp), 'msgs' => Fmt::int($row['msg_count'] ?? 0),
            'balance' => Fmt::num($row['balance'] ?? 0), 'joined' => !empty($row['first_seen']) ? Fmt::jalali((int)$row['first_seen'], false) : null,
        ]);
        if ($photo && mb_strlen($text) <= 1024) {
            $extra = Bot::isPrivate($m) ? [] : ['reply_parameters' => Bot::replyParams($m)];
            if (TG::photo($m['chat']['id'], $photo, $text, UI::footer(), $extra)) {
                return;
            }
        }
        Bot::send($m, $text);
    }

    public static function answerCalc(array $m, string $expr): bool
    {
        try {
            $v = Calc::evaluate($expr);
        } catch (Throwable $e) {
            return false;
        }
        $shown = str_replace(['**', '*', '/'], ['^', '×', '÷'], $expr);
        Bot::send($m, UI::t('calc_result', ['expr' => UI::esc($shown), 'result' => Calc::format($v)]), null);
        return true;
    }

    public static function calcCmd(array $m, string $args): void
    {
        $e = Parse::expression($args);
        if (!$e || !self::answerCalc($m, $e)) {
            Bot::send($m, UI::t('calc_result', ['expr' => UI::esc($args), 'result' => '؟']), null);
        }
    }

    public static function askTranslate(array $m, string $args = ''): void
    {
        $src = $m['reply_to_message'] ?? null;
        $text = $src ? ($src['text'] ?? $src['caption'] ?? '') : '';
        if (trim($text) === '') {
            Bot::send($m, UI::t('translate_need_reply'), null);
            return;
        }
        $rows = [];
        foreach ([['fa', 'en', 'ru'], ['zh', 'ar']] as $r) {
            $rows[] = array_map(fn($c) => UI::button("lang_$c", ['cb' => "tr:$c"]), $r);
        }
        $sent = TG::send($m['chat']['id'], UI::t('translate_choose'), UI::kb($rows), ['reply_parameters' => ['message_id' => $src['message_id'], 'allow_sending_without_reply' => true]]);
        if ($sent) {
            DB::saveTranslation((int)$sent['chat']['id'], (int)$sent['message_id'], (int)$m['from']['id'], mb_substr($text, 0, 3800));
        }
    }

    public static function translateCb(array $cq, string $lang): void
    {
        $msg = $cq['message'] ?? null;
        if (!isset(Translate::LANGS[$lang]) || !$msg) {
            TG::answer($cq['id']);
            return;
        }
        $chat = (int)$msg['chat']['id'];
        $mid = (int)$msg['message_id'];
        $row = DB::translation($chat, $mid);
        if (!$row) {
            TG::answer($cq['id'], UI::plain('translate_used'), true);
            return;
        }
        if ((int)$row['requester_id'] !== (int)$cq['from']['id']) {
            TG::answer($cq['id'], UI::plain('translate_not_yours'), true);
            return;
        }
        if (!DB::claimTranslation($chat, $mid)) {
            TG::answer($cq['id'], UI::plain('translate_used'), true);
            return;
        }
        $res = Translate::run($row['text'], $lang);
        if ($res === null) {
            DB::releaseTranslation($chat, $mid);
            TG::answer($cq['id'], UI::plain('translate_failed'), true);
            return;
        }
        TG::answer($cq['id'], '✅');
        $text = UI::t('translate_result', ['lang' => Translate::LANGS[$lang][0], 'text' => UI::esc(mb_substr($res, 0, 3700))]);
        if (TG::edit($chat, $mid, $text, UI::footer()) === null) {
            TG::send($chat, $text, UI::footer());
        }
    }
}
