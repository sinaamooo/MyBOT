<?php

final class GiftsH
{
    public static function link(array $m, string $slug, int $n): void
    {
        $meta = Gifts::meta($slug, $n);
        $name = $meta['name'] ?? Gifts::splitName($slug);
        $v = Gifts::valueOf($name, $meta['model'] ?? null, $meta['backdrop'] ?? null, $meta['symbol'] ?? null);
        if (!$meta && !$v['collection']) {
            Bot::send($m, UI::t('gift_not_found'));
            return;
        }
        $s = Prices::snapshot();
        $tonUsd = $s['ton'];
        $tonToman = $tonUsd && $s['toman'] ? $tonUsd * $s['toman'] : null;
        $est = $v['estimate'];
        $link = "https://t.me/nft/$slug-$n";
        $r = fn($k) => !empty($meta[$k . '_rarity']) ? UI::esc('(' . $meta[$k . '_rarity'] . ')') : '';
        $text = UI::t('gift_link', [
            'name' => UI::esc($v['collection'] ?? $name), 'number' => Fmt::int($n),
            'model' => !empty($meta['model']) ? UI::esc($meta['model']) : null, 'model_rarity' => $r('model'),
            'backdrop' => !empty($meta['backdrop']) ? UI::esc($meta['backdrop']) : null, 'backdrop_rarity' => $r('backdrop'),
            'symbol' => !empty($meta['symbol']) ? UI::esc($meta['symbol']) : null, 'symbol_rarity' => $r('symbol'),
            'owner' => !empty($meta['owner']) ? UI::esc($meta['owner']) : null, 'quantity' => !empty($meta['quantity']) ? UI::esc($meta['quantity']) : null,
            'floor_ton' => $v['floor'] ? Fmt::num($v['floor']) : null, 'model_floor' => $v['model'] ? Fmt::num($v['model']) : null,
            'backdrop_floor' => $v['backdrop'] ? Fmt::num($v['backdrop']) : null, 'symbol_floor' => $v['symbol'] ? Fmt::num($v['symbol']) : null,
            'est_ton' => $est ? Fmt::num($est) : null, 'est_usd' => $est && $tonUsd ? Fmt::usd($est * $tonUsd) : null,
            'est_toman' => $est && $tonToman ? Fmt::toman($est * $tonToman) : null,
            'est_stars' => $est && $tonUsd ? Fmt::int($est * $tonUsd / Prices::starUsd()) : null, 'link' => $link,
        ]);
        Bot::send($m, $text, UI::footer([[UI::button('btn_gift_open', ['url' => $link])]]), null, true, $link);
    }

    private static function target(array $m, string $args): ?array
    {
        $rf = $m['reply_to_message']['from'] ?? null;
        if ($rf && $args === '') {
            return ['kind' => 'user', 'id' => $rf['id'], 'name' => UI::fullName($rf), 'username' => $rf['username'] ?? null];
        }
        $arg = $args !== '' ? preg_split('/\s+/', $args)[0] : '';
        $me = $m['from'];
        if ($arg === '') {
            return ['kind' => 'user', 'id' => $me['id'], 'name' => UI::fullName($me), 'username' => $me['username'] ?? null];
        }
        if (preg_match('/^-?\d+$/', $arg)) {
            return ['kind' => (int)$arg < 0 ? 'chat' : 'user', 'id' => (int)$arg, 'name' => $arg, 'username' => null];
        }
        $arg = str_replace(['https://t.me/', 't.me/'], '', $arg);
        if (!empty($me['username']) && strcasecmp(ltrim($arg, '@'), $me['username']) === 0) {
            return ['kind' => 'user', 'id' => $me['id'], 'name' => UI::fullName($me), 'username' => $me['username']];
        }
        return Resolver::resolve($arg);
    }

    public static function cmd(array $m, string $args): void
    {
        if ($links = Gifts::findLinks($args)) {
            self::link($m, ...$links[0]);
            return;
        }
        $t = self::target($m, $args);
        if (!$t) {
            Bot::send($m, UI::t('gift_user_unknown'));
            return;
        }
        $wait = Bot::send($m, UI::t('loading'), null);
        try {
            self::portfolio($m, $t);
        } finally {
            Bot::deleteMsg($wait);
        }
    }

    private static function owned(array $t): ?array
    {
        if ($t['kind'] === 'mt') {
            return MTProto::gifts($t);
        }
        $items = [];
        $total = 0;
        $offset = null;
        for ($i = 0; $i < 6; $i++) {
            $p = ['limit' => 100] + ($offset ? ['offset' => $offset] : []);
            $r = $t['kind'] === 'chat' ? TG::api('getChatGifts', ['chat_id' => $t['id']] + $p) : TG::api('getUserGifts', ['user_id' => $t['id']] + $p);
            if ($r === null) {
                if (!$i && !empty($t['username']) && MTProto::enabled() && ($mt = MTProto::resolve($t['username']))) {
                    return MTProto::gifts($mt);
                }
                return $i ? [$items, $total] : null;
            }
            $items = array_merge($items, $r['gifts'] ?? []);
            $total = $r['total_count'] ?? count($items);
            $offset = $r['next_offset'] ?? null;
            if (!$offset) {
                break;
            }
        }
        return [$items, $total];
    }

    private static function rgb(?int $v, array $d): array
    {
        return $v === null ? $d : [($v >> 16) & 255, ($v >> 8) & 255, $v & 255];
    }

    private static function portfolio(array $m, array $t): void
    {
        $res = self::owned($t);
        if ($res === null) {
            Bot::send($m, UI::t('gift_user_unknown'));
            return;
        }
        [$owned, $count] = $res;
        if (!$owned) {
            Bot::send($m, UI::t('gift_user_none', ['name' => UI::esc($t['name'])]));
            return;
        }
        $s = Prices::snapshot();
        $tonUsd = $s['ton'];
        $tonToman = $tonUsd && $s['toman'] ? $tonUsd * $s['toman'] : null;
        $ds = Gifts::dataset();
        $starTon = $tonUsd ? Prices::starUsd() / $tonUsd : null;
        $tiles = [];
        $links = [];
        $now = 0.0;
        $then = 0.0;
        $unique = 0;
        foreach ($owned as $og) {
            $g = $og['gift'] ?? [];
            if (($og['type'] ?? '') === 'unique') {
                $unique++;
                $v = Gifts::valueOf($g['base_name'] ?? '', $g['model']['name'] ?? null, $g['backdrop']['name'] ?? null, $g['symbol']['name'] ?? null);
                $val = $v['estimate'];
                if (!$val && !empty($og['value_usd']) && $tonUsd) {
                    $val = $og['value_usd'] / $tonUsd;
                }
                if ($val) {
                    $now += $val;
                    $then += $val * (($v['floor'] && $v['floor_7d']) ? $v['floor_7d'] / $v['floor'] : 1.0);
                }
                $c = $g['backdrop']['colors'] ?? [];
                $tiles[] = ['title' => ($g['base_name'] ?? 'Gift') . ' #' . ($g['number'] ?? ''), 'subtitle' => implode(' • ', array_filter([$g['model']['name'] ?? null, $g['backdrop']['name'] ?? null])),
                    'value' => $val, 'center' => self::rgb($c['center_color'] ?? null, [90, 110, 200]), 'edge' => self::rgb($c['edge_color'] ?? null, [30, 30, 60]), 'name' => $g['name'] ?? null];
                $links[] = 'https://t.me/nft/' . ($g['name'] ?? '');
            } else {
                $info = $ds['unupgraded'][(string)($g['id'] ?? '')] ?? null;
                $val = !empty($info['price_ton']) ? (float)$info['price_ton'] : null;
                if ($val === null && $starTon) {
                    $stars = $og['convert_star_count'] ?? $g['star_count'] ?? 0;
                    $val = $stars ? $stars * $starTon : null;
                }
                if ($val) {
                    $now += $val;
                    $then += $val;
                }
                $tiles[] = ['title' => $info['full_name'] ?? 'Star Gift', 'subtitle' => ($g['star_count'] ?? 0) . ' Stars' . (!empty($g['total_count']) ? ' • Limited' : ''), 'value' => $val, 'name' => null];
            }
        }
        usort($tiles, fn($a, $b) => ($b['value'] ?? 0) <=> ($a['value'] ?? 0));
        $imgs = Gifts::images(array_map(fn($x) => $x['name'], array_slice($tiles, 0, 20)));
        foreach ($imgs as $i => $bytes) {
            $tiles[$i]['image'] = $bytes;
        }
        $pl = $then ? $now - $then : null;
        $pct = ($pl !== null && $then) ? $pl / $then * 100 : null;
        $last = DB::lastGiftSnapshot((int)$t['id']);
        DB::addGiftSnapshot((int)$t['id'], $now);
        $plShow = ($pl !== null && abs($pl) > 0.0001) ? $pl : null;
        $png = PriceCard::gift($t['name'], $t['username'], $tiles, $now, $tonUsd ? $now * $tonUsd : null, $tonToman ? $now * $tonToman : null, $plShow, $pct, (int)$count, TG::username())
            ?? Cards::gift($t['name'], $t['username'], $tiles, $now, $tonUsd ? $now * $tonUsd : null, $tonToman ? $now * $tonToman : null, $plShow, $pct, (int)$count, TG::username());
        $plLine = $plShow !== null ? UI::fill(UI::template($pl >= 0 ? 'gift_profit' : 'gift_loss'), ['ton' => Fmt::num(abs($pl)), 'percent' => sprintf('%.2f', abs($pct ?? 0)), 'toman' => $tonToman ? Fmt::toman(abs($pl) * $tonToman) : '—']) : null;
        $change = null;
        if ($last && (float)$last['value_ton']) {
            $diff = $now - (float)$last['value_ton'];
            $change = UI::fill(UI::template('gift_since_last'), ['sign' => $diff >= 0 ? '+' : '-', 'ton' => Fmt::num(abs($diff)), 'percent' => sprintf('%.2f', abs($diff / (float)$last['value_ton'] * 100))]);
        }
        $cap = function (int $n) use ($links, $t, $count, $unique, $now, $tonUsd, $tonToman, $plLine, $change) {
            $shown = array_slice($links, 0, $n);
            $extra = count($links) > $n ? "\n👥 " . (count($links) - $n) . ' لینک دیگر' : '';
            return UI::t('gift_user_caption', ['name' => UI::esc($t['name']), 'count' => Fmt::int($count), 'unique_count' => Fmt::int($unique),
                'total_ton' => Fmt::num($now), 'total_usd' => $tonUsd ? Fmt::usd($now * $tonUsd) : '—', 'total_toman' => $tonToman ? Fmt::toman($now * $tonToman) : '—',
                'pl_line' => $plLine, 'change_line' => $change, 'links' => $shown ? implode("\n", array_map(fn($l) => "🔗 $l", $shown)) . $extra : null]);
        };
        $n = count($links);
        $caption = $cap($n);
        while (mb_strlen($caption) > 1024 && $n > 0) {
            $caption = $cap(--$n);
        }
        $extra = Bot::isPrivate($m) ? [] : ['reply_parameters' => Bot::replyParams($m)];
        TG::photo($m['chat']['id'], $png, mb_substr($caption, 0, 1024), UI::footer(), $extra);
        if ($n < count($links)) {
            $chunk = [];
            foreach (array_slice($links, $n) as $l) {
                $chunk[] = "🔗 $l";
                if (mb_strlen(implode("\n", $chunk)) > 3800) {
                    $lastItem = array_pop($chunk);
                    TG::send($m['chat']['id'], implode("\n", $chunk));
                    $chunk = [$lastItem];
                }
            }
            if ($chunk) {
                TG::send($m['chat']['id'], implode("\n", $chunk));
            }
        }
    }
}

final class Scam
{
    private static function label(array $r): string
    {
        $p = [];
        if (!empty($r['target_name'])) {
            $p[] = UI::esc($r['target_name']);
        }
        if (!empty($r['target_username'])) {
            $p[] = '@' . UI::esc($r['target_username']);
        }
        if (!empty($r['target_id']) && !$p) {
            $p[] = '<a href="tg://user?id=' . $r['target_id'] . '">' . $r['target_id'] . '</a>';
        }
        return $p ? implode(' ', $p) : '—';
    }

    private static function kb(int $id): array
    {
        return UI::kb([[UI::button('btn_scam_submit', ['cb' => "scam:submit:$id"]), UI::button('btn_scam_cancel', ['cb' => "scam:cancel:$id"])]]);
    }

    private static function target(array $m, string $args): ?array
    {
        $rf = $m['reply_to_message']['from'] ?? null;
        if ($rf && $args === '') {
            return !empty($rf['is_bot']) ? null : [$rf['id'], $rf['username'] ?? null, UI::fullName($rf)];
        }
        $arg = $args !== '' ? preg_split('/\s+/', $args)[0] : '';
        if ($arg === '') {
            return null;
        }
        if (ctype_digit($arg)) {
            return [(int)$arg, null, null];
        }
        $u = ltrim(str_replace('https://t.me/', '', $arg), '@');
        $r = Resolver::resolve($u);
        return ($r && ($r['kind'] === 'user' || ($r['kind'] === 'mt' && $r['type'] === 'user'))) ? [$r['id'], $u, $r['name']] : [null, $u, null];
    }

    public static function cmd(array $m, string $args): void
    {
        $t = self::target($m, $args);
        if (!$t || $t[0] === $m['from']['id']) {
            Bot::send($m, UI::t('scam_usage'), null);
            return;
        }
        $id = DB::createScam($t[0], $t[1], $t[2], $m['from']['id'], Bot::isPrivate($m) ? null : (int)$m['chat']['id']);
        if (Bot::isPrivate($m)) {
            self::beginEvidence($m, $id);
            return;
        }
        $url = 'https://t.me/' . TG::username() . "?start=scam_$id";
        Bot::send($m, UI::t('scam_go_private', ['target' => self::label(DB::scam($id))]), UI::kb([[UI::button('btn_scam_private', ['url' => $url])]]));
    }

    public static function beginEvidence(array $m, int $id): void
    {
        $r = $id ? DB::scam($id) : null;
        if (!$r || (int)$r['reporter_id'] !== (int)$m['from']['id'] || $r['status'] !== 'draft') {
            TG::send($m['chat']['id'], UI::t('scam_usage'));
            return;
        }
        State::set($m['from']['id'], ['mode' => 'scam', 'scam_id' => $id, 'evidence' => [], 'reason' => '']);
        TG::send($m['chat']['id'], UI::t('scam_ask_evidence', ['target' => self::label($r)]), self::kb($id));
    }

    public static function collect(array $m, array $st): void
    {
        $text = $m['text'] ?? $m['caption'] ?? '';
        if (isset($m['text']) && str_starts_with($m['text'], '/')) {
            State::clear($m['from']['id']);
            TG::send($m['chat']['id'], UI::t('scam_cancelled'));
            return;
        }
        $st['evidence'][] = $m['message_id'];
        if ($text !== '') {
            $st['reason'] = mb_substr(trim($st['reason'] . ' ' . $text), 0, 500);
        }
        State::set($m['from']['id'], $st);
        TG::send($m['chat']['id'], UI::t('scam_evidence_saved', ['count' => count($st['evidence'])]), self::kb((int)$st['scam_id']));
    }

    public static function buttons(array $cq, string $arg): void
    {
        [$action, $id] = explode(':', $arg) + [1 => 0];
        $id = (int)$id;
        $r = DB::scam($id);
        $uid = $cq['from']['id'];
        if (!$r || (int)$r['reporter_id'] !== $uid) {
            TG::answer($cq['id'], UI::plain('translate_not_yours'), true);
            return;
        }
        $chat = $cq['message']['chat']['id'];
        $mid = $cq['message']['message_id'];
        $st = State::get($uid) ?? [];
        if ($action === 'cancel') {
            DB::updateScam($id, ['status' => 'cancelled']);
            State::clear($uid);
            TG::answer($cq['id']);
            TG::edit($chat, $mid, UI::t('scam_cancelled'));
            return;
        }
        if ($r['status'] !== 'draft') {
            TG::answer($cq['id'], UI::plain('translate_used'), true);
            return;
        }
        $ev = (($st['scam_id'] ?? 0) === $id) ? ($st['evidence'] ?? []) : [];
        if (!$ev) {
            TG::answer($cq['id'], UI::plain('scam_need_evidence'), true);
            return;
        }
        DB::updateScam($id, ['status' => 'pending', 'evidence' => $ev, 'reason' => ($st['reason'] ?? '') ?: '—']);
        State::clear($uid);
        TG::answer($cq['id']);
        TG::edit($chat, $mid, UI::t('scam_submitted', ['id' => $id]));
        $r = DB::scam($id);
        $text = UI::t('scam_admin', ['id' => $id, 'target' => self::label($r), 'target_id' => $r['target_id'] ?: '—', 'reporter' => UI::mention($cq['from']),
            'count' => count($r['evidence']), 'reason' => UI::esc($r['reason'] ?: '—')]);
        $kb = UI::kb([[UI::button('btn_approve', ['cb' => "scamadm:approve:$id"]), UI::button('btn_reject', ['cb' => "scamadm:reject:$id"])]]);
        foreach (Config::admins() as $a) {
            TG::send($a, $text);
            foreach (array_slice($r['evidence'], 0, 30) as $mid2) {
                TG::api('copyMessage', ['chat_id' => $a, 'from_chat_id' => $uid, 'message_id' => $mid2]);
            }
            TG::send($a, $text, $kb);
        }
    }

    public static function decision(array $cq, string $arg): void
    {
        if (!Config::isAdmin($cq['from']['id'])) {
            TG::answer($cq['id'], '⛔️', true);
            return;
        }
        [$action, $id] = explode(':', $arg) + [1 => 0];
        $r = DB::scam((int)$id);
        if (!$r) {
            TG::answer($cq['id'], '❌', true);
            return;
        }
        if ($r['status'] !== 'pending') {
            TG::answer($cq['id'], 'status: ' . $r['status'], true);
            return;
        }
        $ok = $action === 'approve';
        DB::updateScam((int)$id, ['status' => $ok ? 'approved' : 'rejected', 'decided_at' => time(), 'decided_by' => $cq['from']['id']]);
        TG::answer($cq['id'], '✅');
        $status = $ok ? '✅ تایید شد و در گروه‌ها اعلام می‌شود' : '❌ رد شد';
        TG::edit($cq['message']['chat']['id'], $cq['message']['message_id'], UI::esc($cq['message']['text'] ?? '') . "\n\n<b>$status</b>");
        TG::send($r['reporter_id'], UI::t($ok ? 'scam_approved_user' : 'scam_rejected_user', ['id' => $r['id']]));
        if ($ok) {
            self::broadcast($r);
        }
    }

    public static function broadcast(array $r): int
    {
        $text = UI::t('scam_broadcast', ['target' => self::label($r), 'target_id' => $r['target_id'] ?: '—', 'reason' => UI::esc($r['reason'] ?: '—'), 'id' => $r['id']]);
        $sent = 0;
        foreach (DB::activeGroups() as $g) {
            if (!Settings::on((int)$g['id'], 'anti_scam')) {
                continue;
            }
            if (TG::send($g['id'], $text, UI::footer())) {
                $sent++;
            } elseif (preg_match('/kicked|not found|forbidden/i', (string)TG::$lastError)) {
                DB::setChatActive((int)$g['id'], false);
            }
            usleep(50000);
        }
        return $sent;
    }

    public static function check(array $m, string $args): void
    {
        $t = self::target($m, $args);
        if (!$t) {
            Bot::send($m, UI::t('scam_usage'), null);
            return;
        }
        [$id, $u, $name] = $t;
        $label = implode(' ', array_filter([$name ? UI::esc($name) : null, $u ? '@' . UI::esc($u) : null, ($id && !$name && !$u) ? "<code>$id</code>" : null]));
        $r = DB::findScammer($id, $u);
        Bot::send($m, $r ? UI::t('scam_check_found', ['target' => $label, 'reason' => UI::esc($r['reason'] ?: '—'), 'id' => $r['id']]) : UI::t('scam_check_clean', ['target' => $label]));
    }
}

final class Extras
{
    const ARZ = ['USD', 'EUR', 'GBP', 'AED', 'TRY', 'CNY', 'IQD', 'AFN', 'CAD', 'AUD', 'RUB', 'SAR', 'KWD', 'OMR', 'QAR', 'CHF', 'JPY', 'INR', 'AMD', 'GEL', 'AZN'];

    public static function answerCard(array $m, string $d): void
    {
        $i = Banking::lookup($d);
        $extra = implode("\n", array_map(fn($k, $v) => '▫️ ' . UI::esc($k) . ': <code>' . UI::esc($v) . '</code>', array_keys($i['extra']), $i['extra']));
        Bot::send($m, UI::t('card_result', ['card' => Banking::format($d), 'bank' => $i['bank'] ? UI::esc($i['bank']) : null, 'owner' => $i['owner'] ? UI::esc($i['owner']) : null,
            'iban' => $i['iban'] ? UI::esc($i['iban']) : null, 'valid' => $i['valid'] ? '✅ معتبر' : '❌ نامعتبر', 'extra' => $extra !== '' ? $extra : null]),
            UI::footer([[UI::button('btn_copy_id', ['copy' => $d])]]));
    }

    public static function answerIban(array $m, string $iban): void
    {
        $b = Banking::bankOfIban($iban);
        Bot::send($m, UI::t('iban_result', ['iban' => $iban, 'bank' => $b ? UI::esc($b) : null, 'valid' => Banking::ibanOk($iban) ? '✅ معتبر' : '❌ نامعتبر']),
            UI::footer([[UI::button('btn_copy_id', ['copy' => $iban])]]));
    }

    public static function card(array $m, string $args): void
    {
        if ($d = Banking::card($args)) {
            self::answerCard($m, $d);
        } elseif ($i = Banking::iban($args)) {
            self::answerIban($m, $i);
        } else {
            Bot::send($m, UI::t('card_result', ['card' => '—', 'bank' => null, 'owner' => null, 'iban' => null, 'valid' => '❌ نامعتبر', 'extra' => null]));
        }
    }

    public static function answerPhone(array $m, string $num): void
    {
        $wait = Bot::send($m, UI::t('loading'), null);
        $r = Phone::check($num);
        Bot::deleteMsg($wait);
        if (!$r) {
            Bot::send($m, UI::t('phone_failed'));
            return;
        }
        Bot::send($m, UI::t('phone_result', ['phone' => UI::esc($num), 'status' => $r[0], 'description' => $r[1]]));
    }

    public static function phone(array $m, string $args): void
    {
        $n = Phone::clean($args);
        $n ? self::answerPhone($m, $n) : Bot::send($m, UI::t('phone_invalid'), null);
    }

    public static function arz(array $m, string $args = ''): void
    {
        $lines = [];
        $market = false;
        foreach (self::ARZ as $code) {
            $f = Currencies::fiat($code);
            $px = Market::fiatToman($code);
            if (!$px) {
                $rates = Tgju::rates();
                $usdt = Prices::usdtToman();
                $cross = Prices::fiatRates();
                $ct = ($usdt && !empty($cross[$code])) ? $usdt / $cross[$code] : null;
                $v = $rates ? Tgju::toman($code, $ct) : null;
                $px = ($v ?? $ct) ? ['toman' => $v ?? $ct, 'change' => null, 'source' => $v ? 'market' : 'cross'] : null;
            }
            if (!$px) {
                continue;
            }
            $market = $market || $px['source'] === 'market';
            $lines[] = UI::fill(UI::template('arz_line'), ['flag' => Currencies::flag($f['cc']), 'name' => $f['fa'], 'toman' => Fmt::toman($px['toman']),
                'change' => $px['change'] !== null ? Fmt::badge($px['change']) : '']);
        }
        if (!$lines) {
            Bot::send($m, UI::t('price_unavailable'));
            return;
        }
        Bot::send($m, UI::t('arz_board', ['lines' => implode("\n", $lines), 'source' => $market ? 'tgju.org' : 'نرخ جهانی × تتر', 'time' => Fmt::now()]));
    }

    private static function err(int $code): string
    {
        return [451 => 'insta_private', 452 => 'insta_no_story', 453 => 'insta_no_highlight'][$code] ?? 'insta_not_found';
    }

    private static function sendMedia(array $m, array $items, bool $reply = true): void
    {
        $n = count($items);
        $extra = ($reply && !Bot::isPrivate($m)) ? ['reply_parameters' => Bot::replyParams($m)] : [];
        foreach ($items as $i => $it) {
            $cap = UI::t('insta_media_caption', ['index' => $i + 1, 'count' => $n, 'bot' => TG::username()]);
            $method = $it['kind'] === 'video' ? 'sendVideo' : 'sendPhoto';
            $field = $it['kind'] === 'video' ? 'video' : 'photo';
            if (TG::api($method, ['chat_id' => $m['chat']['id'], $field => $it['url'], 'caption' => $cap] + $extra) !== null) {
                continue;
            }
            $r = Http::request(['url' => $it['url'], 'timeout' => 120]);
            if ($r['status'] >= 200 && $r['status'] < 300 && strlen($r['body']) > 0 && strlen($r['body']) < 49 * 1048576) {
                TG::api($method, ['chat_id' => $m['chat']['id'], $field => ['__bytes' => $r['body'], 'name' => $it['kind'] === 'video' ? 'media.mp4' : 'media.jpg'], 'caption' => $cap] + $extra);
            }
            usleep(300000);
        }
    }

    public static function instaLink(array $m, string $link): void
    {
        if (!Instagram::enabled()) {
            if (Bot::isPrivate($m)) {
                Bot::send($m, UI::t('insta_no_key'), null);
            }
            return;
        }
        $wait = Bot::send($m, UI::t('insta_loading'), null);
        $media = Instagram::post($link);
        $chat = $m['chat']['id'];
        if (!$media) {
            TG::edit($chat, $wait['message_id'] ?? 0, UI::t(self::err(Instagram::$lastStatus)));
            return;
        }
        TG::edit($chat, $wait['message_id'] ?? 0, UI::t('insta_count', ['count' => count($media)]));
        self::sendMedia($m, $media);
    }

    public static function instaProfile(array $m, string $username): void
    {
        if (!Instagram::enabled()) {
            Bot::send($m, UI::t('insta_no_key'), null);
            return;
        }
        $wait = Bot::send($m, UI::t('insta_loading'), null);
        $info = Instagram::profile($username);
        Bot::deleteMsg($wait);
        if (!$info) {
            Bot::send($m, UI::t('insta_not_found'));
            return;
        }
        $priv = trim((string)($info['is_private'] ?? ''));
        $isPriv = in_array($priv, ['✅', 'True', 'true', '1'], true);
        $text = UI::t('insta_info', ['id' => UI::esc($info['Id'] ?? $info['id'] ?? '—'), 'name' => UI::esc($info['name'] ?? '—'),
            'username' => UI::esc($info['username'] ?? $username), 'followers' => UI::esc($info['followers'] ?? 0), 'following' => UI::esc($info['following'] ?? 0),
            'posts' => UI::esc($info['post_count'] ?? 0), 'verified' => UI::esc($info['is_verified'] ?? '—'), 'private' => UI::esc($priv ?: '—'),
            'bio' => !empty($info['bio']) ? UI::esc($info['bio']) : null]);
        $rows = [];
        if (!$isPriv) {
            $k = Store::put(['username' => $info['username'] ?? $username, 'owner' => $m['from']['id']]);
            $rows = [[UI::button('btn_insta_story', ['cb' => "is:$k"])], [UI::button('btn_insta_highlight', ['cb' => "ih:$k"])]];
        }
        if (!empty($info['profile']) && mb_strlen($text) <= 1024) {
            $extra = Bot::isPrivate($m) ? [] : ['reply_parameters' => Bot::replyParams($m)];
            if (TG::photo($m['chat']['id'], $info['profile'], $text, UI::footer($rows), $extra)) {
                return;
            }
        }
        Bot::send($m, $text, UI::footer($rows));
    }

    public static function insta(array $m, string $args): void
    {
        if ($l = Instagram::link($args)) {
            self::instaLink($m, $l);
        } elseif (preg_match('/^@?([A-Za-z0-9_.]{1,30})$/', trim($args), $mm)) {
            self::instaProfile($m, $mm[1]);
        } else {
            Bot::send($m, UI::t('insta_not_found'), null);
        }
    }

    private static function own(array $cq, ?array $d): bool
    {
        $err = !$d ? 'expired' : ((!empty($d['owner']) && $d['owner'] !== $cq['from']['id']) ? 'not_your_button' : null);
        if ($err) {
            TG::answer($cq['id'], UI::plain($err), true);
            return false;
        }
        TG::answer($cq['id']);
        return true;
    }

    public static function story(array $cq, string $k): void
    {
        $d = Store::get($k);
        if (!self::own($cq, $d)) {
            return;
        }
        $chat = $cq['message']['chat']['id'];
        $wait = TG::send($chat, UI::t('insta_loading'));
        $items = Instagram::stories($d['username']);
        if (!$items) {
            TG::edit($chat, $wait['message_id'] ?? 0, UI::t($items === null && Instagram::$lastStatus !== 452 ? self::err(Instagram::$lastStatus) : 'insta_no_story'));
            return;
        }
        TG::edit($chat, $wait['message_id'] ?? 0, UI::t('insta_count', ['count' => count($items)]));
        self::sendMedia($cq['message'], $items, false);
    }

    public static function highlights(array $cq, string $k): void
    {
        $d = Store::get($k);
        if (!self::own($cq, $d)) {
            return;
        }
        $chat = $cq['message']['chat']['id'];
        $items = Instagram::highlights($d['username']);
        if (!$items) {
            TG::send($chat, UI::t($items === null && Instagram::$lastStatus !== 453 ? self::err(Instagram::$lastStatus) : 'insta_no_highlight'));
            return;
        }
        $rows = array_map(fn($h) => [['text' => mb_substr($h['title'], 0, 60), 'callback_data' => 'hi:' . Store::put(['id' => $h['id'], 'owner' => $cq['from']['id']])]], array_slice($items, 0, 40));
        TG::send($chat, UI::t('insta_highlights', ['username' => UI::esc($d['username'])]), UI::kb($rows));
    }

    public static function highlightItems(array $cq, string $k): void
    {
        $d = Store::get($k);
        if (!self::own($cq, $d)) {
            return;
        }
        $chat = $cq['message']['chat']['id'];
        $wait = TG::send($chat, UI::t('insta_loading'));
        $items = Instagram::highlightItems($d['id']);
        if (!$items) {
            TG::edit($chat, $wait['message_id'] ?? 0, UI::t('insta_not_found'));
            return;
        }
        TG::edit($chat, $wait['message_id'] ?? 0, UI::t('insta_count', ['count' => count($items)]));
        self::sendMedia($cq['message'], $items, false);
    }
}

final class TextRouter
{
    const UID = ['uid', 'آیدی عددی', 'ایدی عددی', 'آیدی', 'ایدی', 'id', 'ایدیش', 'آیدیش'];
    const TR = ['ترجمه', 'ترجمه کن', 'translate', 'tr', 'ترجمش کن', 'ترجمه شود'];

    public static function slash(array $m, string $text): bool
    {
        if (!preg_match('/^\/(\S+?)(?:@\w+)?(?:\s+(.*))?$/su', $text, $mm)) {
            return false;
        }
        $body = trim($mm[1] . ' ' . ($mm[2] ?? ''));
        if (mb_strlen($body) > 60) {
            return false;
        }
        $p = Parse::amount($body);
        if ($p) {
            [$amount, $unit] = $p;
        } elseif (Currencies::resolve($body)) {
            [$amount, $unit] = [1, $body];
        } else {
            return false;
        }
        $r = Currencies::resolve($unit);
        if (!$r || $r[0] !== 'fiat') {
            return false;
        }
        return Tools::answerAmount($m, $amount, $unit, !Bot::isPrivate($m));
    }

    public static function route(array $m): void
    {
        $text = trim($m['text'] ?? $m['caption'] ?? '');
        if ($text === '') {
            return;
        }
        $chat = (int)$m['chat']['id'];
        $priv = Bot::isPrivate($m);
        $low = Currencies::normalize($text);
        $on = fn($k) => $priv || Settings::on($chat, $k);
        if (!empty($m['reply_to_message']) && in_array($low, self::UID, true)) {
            Tools::uid($m);
            return;
        }
        if (!empty($m['reply_to_message']) && in_array($low, self::TR, true)) {
            if (Settings::on($chat, 'translate')) {
                Tools::askTranslate($m);
            }
            return;
        }
        if (($l = Gifts::parseLink($text)) && $on('gifts')) {
            GiftsH::link($m, ...$l);
            return;
        }
        if (($l = Instagram::link($text)) && $on('instagram')) {
            Extras::instaLink($m, $l);
            return;
        }
        if (preg_match('/^(?:wallet|ولت|کیف پول|check|چک|hash|هش|tx)\s+(.+)$/isu', $text, $mm) && $on('checkers')) {
            if ($f = Chain::extract(trim($mm[1]), false)) {
                Tools::runChecker($m, $f[0], $f[1]);
                return;
            }
        }
        if (($f = Chain::extract($text, !$priv)) && $on('checkers')) {
            Tools::runChecker($m, $f[0], $f[1]);
            return;
        }
        if ($d = Banking::card($text)) {
            if ((Banking::luhn($d) || $priv) && $on('cardinfo')) {
                Extras::answerCard($m, $d);
            }
            return;
        }
        if (($i = Banking::iban($text)) && $on('cardinfo')) {
            Extras::answerIban($m, $i);
            return;
        }
        $num = preg_match('/^(?:phone|چک شماره|شماره|number)\s+(.+)$/iu', $text, $pm) ? Phone::clean($pm[1]) : (($priv && str_starts_with($text, '+')) ? Phone::clean($text) : null);
        if ($num && $on('phone_check')) {
            Extras::answerPhone($m, $num);
            return;
        }
        if ($priv && str_starts_with($text, '@') && Instagram::enabled() && preg_match('/^@?([A-Za-z0-9_.]{1,30})$/', $text, $um)) {
            Extras::instaProfile($m, $um[1]);
            return;
        }
        if (mb_strlen($text) > 60) {
            return;
        }
        $conv = $on('conversions');
        if ($conv && ($p = Parse::amount($text)) && Tools::answerAmount($m, $p[0], $p[1], !$priv)) {
            return;
        }
        if ($conv && preg_match('/^(?:قیمت|نرخ|price|ارزش)\s+(.+)$/iu', $text, $pm)) {
            $t = trim($pm[1]);
            ($s = Parse::amount($t)) ? Tools::answerAmount($m, $s[0], $s[1], !$priv) : Tools::answerAmount($m, 1, $t, !$priv);
            return;
        }
        if ($conv && Currencies::word($text, 'market')) {
            Tools::market($m);
            return;
        }
        if ($conv && Currencies::word($text, 'premium')) {
            Tools::premium($m);
            return;
        }
        if ($conv && Currencies::word($text, 'stars')) {
            Tools::starsBoard($m);
            return;
        }
        if ($conv && (Currencies::knownCrypto($text) || Currencies::bareAsset($text))) {
            Tools::answerAmount($m, 1, $text, true);
            return;
        }
        if (($e = Parse::expression($text)) && $on('calculator')) {
            Tools::answerCalc($m, $e);
        }
    }
}
