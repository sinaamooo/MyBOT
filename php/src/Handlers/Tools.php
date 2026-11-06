<?php

final class Tools
{
    const MARKET = [['💰', 'BTC'], ['💎', 'ETH'], ['💠', 'TON'], ['🔴', 'TRX'], ['🪙', 'BNB'], ['🌟', 'SOL'], ['🐸', 'DOGE'], ['🔲', 'NOT'], ['💵', 'USDT']];

    public static function market(array $m): void
    {
        self::react($m);
        $rate = Prices::usdtToman();
        $lines = [];
        foreach (self::MARKET as [$icon, $sym]) {
            $q = Prices::quote($sym);
            if (!$q) {
                continue;
            }
            $lines[] = UI::fill(UI::template('market_line'), ['icon' => UI::badge($sym, $icon), 'symbol' => $sym, 'usd' => Fmt::usd($q['price']),
                'toman' => $rate ? Fmt::toman($q['price'] * $rate) : '—', 'change' => $sym !== 'USDT' ? Fmt::badge($q['change']) : '']);
        }
        if (!$lines) {
            Bot::reply($m, UI::t('price_unavailable'));
            return;
        }
        Bot::reply($m, UI::t('market', ['lines' => implode("\n", $lines), 'usdt_icon' => UI::badge('USDT', '💵'), 'usdt_toman' => $rate ? Fmt::toman($rate) : null, 'time' => Fmt::now()]));
    }

    public static function answerAmount(array $m, float $amount, string $unit, bool $silent, ?bool $guess = null): bool
    {
        $r = Currencies::resolve($unit);
        $loose = !($guess ?? !$silent);
        if (!$r || ($r[0] === 'crypto_guess' && $loose && !Market::coin($r[1]))
            || ($r[0] === 'crypto' && $loose && in_array($r[1], SpotDir::CHAT_WORDS, true) && !Currencies::knownCrypto($r[1]))) {
            return false;
        }
        self::react($m);
        $a = Convert::run($amount, $r[0], $r[1]);
        if (!$a) {
            if (!$silent) {
                Bot::reply($m, UI::t('currency_not_found', ['query' => UI::esc($unit)]));
                return true;
            }
            return false;
        }
        self::sendPriced($m, $a);
        return true;
    }

    public static function answerPair(array $m, float $amount, array $from, array $to): bool
    {
        self::react($m);
        $a = $to[0] === 'toman' ? Convert::run($amount, $from[0], $from[1]) : Convert::pair($amount, $from, $to);
        if (!$a) {
            return false;
        }
        self::sendPriced($m, $a);
        return true;
    }

    public static function sendPriced(array $m, array $a): void
    {
        if (!empty($a['key']) && UI::$lang !== 'fa') {
            $a['key'] .= '|' . UI::$lang;
        }
        self::react($m);
        $photo = null;
        $fk = !empty($a['key']) ? 'fid:' . md5(Config::get('BOT_TOKEN') . '|' . $a['key']) : null;
        if ($fk && ($fid = Cache::get($fk))) {
            $photo = $fid;
        } elseif (!empty($a['card'])) {
            try {
                $photo = !empty($a['key']) ? PriceCard::cached($a['key'], $a['card']) : ($a['card'])();
            } catch (Throwable $e) {
                error_log('card: ' . $e->getMessage());
            }
        }
        if (!empty($fid)) {
            Bot::reply($m, $a['text'], false, $fid);
            return;
        }
        $sent = Bot::send($m, $a['text'], false, $photo ?: null);
        if ($fk && !empty($sent['photo'])) {
            $big = end($sent['photo']);
            if (!empty($big['file_id'])) {
                Cache::set($fk, $big['file_id'], 3 * 86400);
            }
        }
    }

    const REACTIONS = ['price' => ['💱', '⚡'], 'wallet' => ['🔰', '👌'], 'hash' => ['🔍', '👀'], 'board' => ['🏆']];

    private static array $reacted = [];

    public static function react(array $m, string $kind = 'price'): void
    {
        if (empty($m['message_id']) || !empty($m['from']['is_bot'])) {
            return;
        }
        $chat = $m['chat']['id'];
        if (isset(self::$reacted["$chat:{$m['message_id']}"])) {
            return;
        }
        self::$reacted["$chat:{$m['message_id']}"] = true;
        $want = $kind === 'price' ? Config::get('PRICE_REACTION') : '';
        $list = [];
        foreach (array_merge(array_filter(array_map('trim', explode(',', $want))), self::REACTIONS[$kind] ?? self::REACTIONS['price'], ['🐳']) as $r) {
            $item = ctype_digit($r) ? ['type' => 'custom_emoji', 'custom_emoji_id' => $r] : ['type' => 'emoji', 'emoji' => $r];
            if (!in_array($item, $list, true)) {
                $list[] = $item;
            }
        }
        $key = 'react:' . $chat . ':' . md5(json_encode($list));
        $start = (int)(Cache::get($key, $known) ?? 0);
        if ($known && $start < count($list)) {
            TG::async('setMessageReaction', ['chat_id' => $chat, 'message_id' => $m['message_id'], 'reaction' => [$list[$start]]], function ($r) use ($key, $start) {
                if (is_array($r) && empty($r['ok']) && str_contains(strtolower((string)($r['description'] ?? '')), 'reaction')) {
                    Cache::set($key, $start + 1, 21600);
                }
            });
            return;
        }
        for ($i = $start; $i < count($list); $i++) {
            if (TG::api('setMessageReaction', ['chat_id' => $chat, 'message_id' => $m['message_id'], 'reaction' => [$list[$i]]]) !== null) {
                Cache::set($key, $i, 21600);
                return;
            }
            if (!str_contains(strtolower((string)TG::$lastError), 'reaction')) {
                return;
            }
        }
        Cache::set($key, count($list), 3600);
    }

    public static function premium(array $m): void
    {
        self::react($m);
        Bot::reply($m, Convert::premiumText() ?? UI::t('price_unavailable'));
    }

    public static function starsBoard(array $m): void
    {
        self::react($m);
        Bot::reply($m, Convert::starsText() ?? UI::t('price_unavailable'));
    }

    public static function runChecker(array $m, string $kind, string $token, ?int $owner = null): void
    {
        $owner = $owner ?? ($m['from']['id'] ?? 0);
        $isHash = in_array($kind, Chain::HASH_KINDS, true);
        Perf::start();
        self::react($m, $isHash ? 'hash' : 'wallet');
        TG::warm();
        TG::closeHook();
        Perf::mark('react');
        try {
            if ($isHash) {
                $tx = Txs::check($kind, $token);
                Perf::mark('lookup');
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
            Perf::mark('lookup');
            if ($kind === 'evm') {
                $w = array_slice($w, 0, 1);
            }
            if (!$w && $kind === 'sol' && in_array(strlen($token), [43, 44], true) && ($tx = Txs::check('sol', $token))) {
                self::sendTx($m, $tx, $owner);
                return;
            }
            $w ? self::sendWallet($m, $token, $w, $owner) : Bot::send($m, UI::t('wallet_not_found', ['address' => UI::esc($token)]));
        } catch (Throwable $e) {
            error_log('checker: ' . $e->getMessage());
            Bot::send($m, UI::t('error'));
        } finally {
            Perf::flush('checker:' . $kind);
        }
    }

    private static function statusDot(?string $s): string
    {
        $s = (string)$s;
        $k = match (true) {
            $s === '' => 'DNAVY',
            str_contains($s, 'ناموفق') || str_contains($s, 'نشده') || str_contains($s, 'شکست') => 'DRED',
            str_contains($s, 'انتظار') || str_contains($s, 'فریز') => 'DORANGE',
            str_contains($s, 'موفق') || str_contains($s, 'فعال') => 'DGREEN',
            default => 'DNAVY',
        };
        return UI::badge($k, UI::ICONS[strtolower($k)] ?? '🔵');
    }

    public static function sendWallet(array $m, string $address, array $found, int $owner): void
    {
        $rate = Prices::usdtToman();
        $parts = [UI::t('wallet_header', ['address' => UI::esc($address), 'count' => count($found)])];
        $total = 0.0;
        $blocks = [];
        foreach ($found as $r) {
            $total += Wallets::totalUsd($r);
            $blocks[] = UI::t('wallet_chain', [
                'chain_icon' => UI::badge($r['symbol'], '🔷'), 'chain' => UI::esc($r['chain']), 'balance' => Fmt::num($r['balance']), 'symbol' => $r['symbol'],
                'usd' => $r['usd'] !== null ? Fmt::usd($r['usd']) : null, 'toman' => $r['usd'] !== null && $rate ? Fmt::toman($r['usd'] * $rate) : null,
                'txs' => is_numeric($r['txs']) ? Fmt::int($r['txs']) : ($r['txs'] ?: null),
                'nft_icon' => UI::badge('NFT', '🎞'), 'nfts' => $r['nfts'] ? Fmt::int($r['nfts']) : null, 'created' => $r['created'] ? Fmt::jalali($r['created']) : null,
                'status' => $r['status'] ? (UI::lx(UI::stripEmoji($r['status'])) ?: null) : null, 'status_icon' => self::statusDot($r['status'] ? UI::stripEmoji($r['status']) : null), 'explorer' => UI::attr($r['explorer']),
            ]);
        }
        $footer = UI::t('wallet_footer', ['total_usd' => Fmt::usd($total), 'total_toman' => $rate ? Fmt::toman($total * $rate) : null, 'time' => Fmt::now()]);
        $room = 3800 - UI::textLen($parts[0]) - UI::textLen($footer);
        foreach ($blocks as $i => $b) {
            $more = count($blocks) - $i - 1;
            $need = UI::textLen($b) + 2 + ($more ? 60 : 0);
            if ($need > $room) {
                $parts[] = UI::t('wallet_more', ['count' => count($blocks) - $i]);
                break;
            }
            $parts[] = $b;
            $room -= $need - ($more ? 60 : 0);
        }
        $parts[] = $footer;
        $text = implode("\n\n", $parts);
        $rows = [];
        foreach ($found as $r) {
            if ($r['kind'] === 'tron') {
                $k = Store::put(['addr' => $address, 'owner' => $owner]);
                $rows[] = [UI::button('btn_trc20_history', ['cb' => "tt:$k:1"])];
                break;
            }
        }
        Bot::send($m, $text, UI::footer($rows));
        Perf::mark('sent');
    }

    public static function sendTx(array $m, array $tx, int $owner): void
    {
        $rate = Prices::usdtToman();
        $amount = $tx['amount'] !== null ? Fmt::num($tx['amount']) . ' ' . UI::esc($tx['symbol'] ?? '') : ($tx['symbol'] ? UI::esc($tx['symbol']) : null);
        $text = UI::t('tx_result', [
            'network' => UI::esc($tx['network']), 'status' => UI::lx(UI::stripEmoji($tx['status'])) ?: null, 'status_icon' => self::statusDot(UI::stripEmoji($tx['status'])), 'amount' => $amount,
            'usd' => $tx['usd'] ? Fmt::usd($tx['usd']) : null, 'toman' => $tx['usd'] && $rate ? Fmt::toman($tx['usd'] * $rate) : null,
            'token' => $tx['token_type'] ? UI::esc(($tx['token_name'] ?: $tx['symbol']) . ' (' . $tx['token_type'] . ')') : null,
            'sender' => $tx['sender'] ? UI::esc($tx['sender']) : null, 'receiver' => $tx['receiver'] ? UI::esc($tx['receiver']) : null,
            'fee' => $tx['fee'] ? UI::esc($tx['fee']) : null, 'block' => $tx['block'],
            'confirmations' => is_int($tx['confirmations']) ? Fmt::int($tx['confirmations']) : ($tx['confirmations'] ?: null),
            'time' => $tx['timestamp'] ? Fmt::jalali((int)$tx['timestamp']) : null, 'hash' => UI::esc($tx['hash']), 'explorer' => UI::attr($tx['explorer']),
        ]);
        $row = [];
        foreach ([[$tx['receiver'], 'btn_receiver_wallet']] as [$addr, $key]) {
            $f = Chain::extract(explode(' ', (string)$addr)[0], true);
            if ($f && in_array($f[0], Chain::ADDRESS_KINDS, true)) {
                $row[] = UI::button($key, ['cb' => 'wl:' . Store::put(['addr' => $f[1], 'kind' => $f[0], 'owner' => $owner])]);
            }
        }
        Bot::send($m, $text, UI::footer($row ? [$row] : []));
        Perf::mark('sent');
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

    const TX_PAGE = 3;

    public static function transfers(array $cq, string $arg): void
    {
        [$key, $page] = array_pad(explode(':', $arg), 2, '1');
        $edit = str_starts_with($cq['data'], 'tp:');
        $d = Store::get($key);
        if ($err = self::owned($cq, $d)) {
            TG::answer($cq['id'], UI::plain($err), true);
            return;
        }
        $all = Wallets::tronHistory($d['addr']);
        if ($all === null) {
            TG::answer($cq['id'], UI::plain('price_unavailable'), true);
            return;
        }
        if (!$all) {
            TG::answer($cq['id'], UI::plain('tron_transfers_empty'), true);
            return;
        }
        $pages = (int)ceil(count($all) / self::TX_PAGE);
        $page = min(max(1, (int)$page), $pages);
        TG::answer($cq['id']);
        $items = [];
        foreach (array_slice($all, ($page - 1) * self::TX_PAGE, self::TX_PAGE) as $it) {
            $in = ($it['to'] ?? '') === $d['addr'];
            $items[] = UI::fill(UI::template('tron_transfer_item'), ['dir_icon' => $in ? UI::badge('DGREEN', '🟢') : UI::badge('DRED', '🔴'), 'direction' => $in ? '📥' : '📤', 'kind' => UI::l($in ? 'دریافت' : 'ارسال'),
                'amount' => Fmt::num($it['amount']), 'symbol' => UI::esc($it['symbol']), 'time' => $it['ts'] ? Fmt::jalali($it['ts']) : '—',
                'side' => UI::l($in ? 'از' : 'به'), 'party' => UI::esc((string)($in ? $it['from'] : $it['to'])), 'hash' => UI::esc($it['hash'] ?? '')]);
        }
        $text = UI::t('tron_transfers', ['address' => UI::esc($d['addr']), 'page' => $page, 'pages' => $pages, 'items' => implode("\n", $items)]);
        $nav = [];
        if ($page > 1) {
            $nav[] = UI::button('btn_prev', ['cb' => "tp:$key:" . ($page - 1)]);
        }
        if ($page < $pages) {
            $nav[] = UI::button('btn_next', ['cb' => "tp:$key:" . ($page + 1)]);
        }
        $kb = $nav ? UI::kb([$nav]) : null;
        $chat = $cq['message']['chat']['id'];
        if ($edit) {
            TG::edit($chat, $cq['message']['message_id'], $text, $kb, ['__quick' => true]);
            return;
        }
        TG::send($chat, $text, $kb, ['__quick' => true]);
    }

    public static function uid(array $m, string $args = ''): void
    {
        $r = $m['reply_to_message'] ?? null;
        if ($r && isset($r['sender_chat'])) {
            $c = $r['sender_chat'];
            Bot::reply($m, UI::t('uid', ['name' => UI::esc($c['title'] ?? ''), 'id' => $c['id'], 'username' => UI::uname($c['username'] ?? null)]),
                UI::kb([[UI::button('btn_copy_id', ['copy' => (string)$c['id']])]]));
            return;
        }
        $t = $r['from'] ?? $m['from'] ?? null;
        if (!$t) {
            return;
        }
        Bot::reply($m, UI::t('uid', ['name' => UI::mention($t), 'id' => $t['id'], 'username' => UI::uname($t['username'] ?? null)]),
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
        if (!$items && empty($m['reply_to_message']) && trim($args) === '' && preg_match('/^\//', $m['text'] ?? '')) {
            Bot::ask($m, 'emoji', 'emoji_ask', UI::l('ایموجی پریمیوم'));
            return;
        }
        if (!$items) {
            Bot::reply($m, UI::t('emoji_none'));
            return;
        }
        $parts = [];
        foreach ($items as $id => $char) {
            $parts[] = UI::t('emoji_item', ['emoji' => '<tg-emoji emoji-id="' . $id . '">' . UI::esc($char) . '</tg-emoji>', 'id' => $id]);
        }
        Bot::reply($m, mb_substr(implode("\n", $parts), 0, 4000));
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
        ['info' => $info, 'photos' => $photos, 'gifts' => $gifts] = TG::multi(['info' => ['getChat', ['chat_id' => $t['id']]],
            'photos' => ['getUserProfilePhotos', ['user_id' => $t['id'], 'limit' => 1]], 'gifts' => ['getUserGifts', ['user_id' => $t['id'], 'limit' => 1]]]);
        $photo = !empty($photos['total_count']) ? end($photos['photos'][0])['file_id'] : null;
        $dc = $photo ? Account::dc($photo) : null;
        $level = null;
        if (!empty($info['rating'])) {
            $r = $info['rating'];
            $level = '⭐️  ' . $r['level'] . (!empty($r['next_level_rating']) ? ' (' . Fmt::int($r['rating'] ?? 0) . '/' . Fmt::int($r['next_level_rating']) . ')' : '');
        }
        $bd = null;
        if (!empty($info['birthdate'])) {
            $b = $info['birthdate'];
            $bd = !empty($b['year']) ? sprintf('%d/%02d/%02d', $b['year'], $b['month'], $b['day']) : sprintf('%02d/%02d', $b['month'], $b['day']);
        }
        $text = UI::t('account', [
            'name' => UI::mention($t), 'id' => $t['id'], 'username' => UI::uname($t['username'] ?? null),
            'premium_icon' => UI::badge('PREMIUM', '💫'), 'premium' => UI::l(!empty($t['is_premium']) ? '✅   دارد' : '❌   ندارد'), 'level' => $level,
            'created' => Fmt::jalaliLong(Account::created((int)$t['id'])), 'dc' => $dc ? "DC$dc • " . (Account::DC[$dc] ?? '') : null,
            'lang' => !empty($t['language_code']) ? strtoupper($t['language_code']) : null, 'birthdate' => $bd,
            'bio' => !empty($info['bio']) ? UI::esc($info['bio']) : null, 'gifts' => $gifts ? Fmt::int($gifts['total_count'] ?? 0) : null,
            'diamonds' => Fmt::int($pts = Diamonds::points((int)$t['id'])), 'dlevel' => Diamonds::level($pts),
        ]);
        if ($photo && UI::textLen($text) <= 1000) {
            if (TG::photo($m['chat']['id'], $photo, $text, UI::footer())) {
                return;
            }
        }
        Bot::reply($m, $text);
    }

    public static function answerCalc(array $m, string $expr): bool
    {
        try {
            $v = Calc::evaluate($expr);
        } catch (Throwable $e) {
            return false;
        }
        $shown = str_replace(['**', '*', '/'], ['^', '×', '÷'], $expr);
        Bot::reply($m, UI::t('calc_result', ['expr' => UI::esc($shown), 'result' => Calc::format($v)]), null);
        return true;
    }

    public static function askTranslate(array $m, string $args = ''): void
    {
        $src = $m['reply_to_message'] ?? null;
        $text = $src ? ($src['text'] ?? $src['caption'] ?? '') : '';
        if (trim($text) === '') {
            Bot::reply($m, UI::t('translate_need_reply'), null);
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
        $text = UI::t('translate_result', ['lang' => UI::l(Translate::LANGS[$lang][0]), 'text' => UI::esc(mb_substr($res, 0, 3700))]);
        if (TG::edit($chat, $mid, $text, UI::footer()) === null) {
            TG::send($chat, $text, UI::footer());
        }
    }
}
