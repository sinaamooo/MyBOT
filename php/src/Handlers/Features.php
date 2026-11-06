<?php

final class Waiter
{
    const GAP = 0.45;
    private $chat;
    private ?int $mid = null;
    private array $frames = [];
    private int $i = 0;
    private float $last = 0.0;
    private bool $busy = true;
    private bool $done = false;
    private array $reply = [];
    private ?string $restore = null;

    public static function start(array $m, string $key, ?int $prompt = null): self
    {
        $w = new self();
        $w->chat = $m['chat']['id'];
        $w->frames = [trim(UI::t($key))];
        $w->reply = !Bot::isPrivate($m) && isset($m['message_id']) ? ['reply_parameters' => Bot::replyParams($m)] : [];
        if ($prompt && (TG::api('editMessageText', ['chat_id' => $w->chat, 'message_id' => $prompt, 'text' => $w->frames[0], 'reply_markup' => UI::footer()]) !== null
            || TG::api('editMessageText', ['chat_id' => $w->chat, 'message_id' => $prompt, 'text' => $w->frames[0]]) !== null)) {
            $w->mid = $prompt;
            $w->restore = UI::t('gift_ask');
        } else {
            $r = TG::api('sendMessage', ['chat_id' => $w->chat, 'text' => $w->frames[0], 'reply_markup' => UI::footer()] + $w->reply);
            $w->mid = isset($r['message_id']) ? (int)$r['message_id'] : null;
        }
        $w->busy = false;
        $w->last = microtime(true);
        return $w;
    }

    public function tick(): void
    {
        Http::pump();
        if ($this->done || $this->busy || !$this->mid || $this->i >= count($this->frames) - 1 || microtime(true) - $this->last < self::GAP) {
            return;
        }
        $this->i++;
        $this->busy = true;
        $this->last = microtime(true);
        TG::async('editMessageText', ['chat_id' => $this->chat, 'message_id' => $this->mid, 'text' => $this->frames[$this->i], 'reply_markup' => UI::footer()], function () {
            $this->busy = false;
        });
    }

    public function finish(?string $html = null): void
    {
        if ($this->done) {
            return;
        }
        $this->done = true;
        Http::settle(2);
        if (!$this->mid) {
            if ($html !== null) {
                TG::async('sendMessage', ['chat_id' => $this->chat, 'text' => $html, 'reply_markup' => UI::footer()] + $this->reply);
            }
            return;
        }
        if ($html !== null || $this->restore !== null) {
            TG::async('editMessageText', ['chat_id' => $this->chat, 'message_id' => $this->mid, 'text' => $html ?? $this->restore] + ($this->restore === null ? ['reply_markup' => UI::footer()] : []));
            return;
        }
        TG::async('deleteMessage', ['chat_id' => $this->chat, 'message_id' => $this->mid]);
    }
}

final class GiftsH
{
    public static ?Waiter $wait = null;

    private static function tick(): void
    {
        self::$wait?->tick();
    }

    private static function say(array $m, string $html): void
    {
        if (self::$wait) {
            self::$wait->finish($html);
            self::$wait = null;
            return;
        }
        Bot::send($m, $html);
    }
    public static function link(array $m, string $slug, int $n): void
    {
        TG::warm();
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
        TG::send($m['chat']['id'], $text, UI::footer([[UI::button('btn_gift_open', ['url' => $link])]]), ['__quick' => true,
            'link_preview_options' => ['url' => $link, 'prefer_large_media' => true, 'show_above_text' => true]] + (!Bot::isPrivate($m) ? ['reply_parameters' => Bot::replyParams($m)] : []));
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
        if (in_array(mb_strtolower($arg), ['من', 'me'], true)) {
            return ['kind' => 'user', 'id' => $me['id'], 'name' => UI::fullName($me), 'username' => $me['username'] ?? null];
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
        if ($args === '' && empty($m['reply_to_message']['from'])) {
            Bot::ask($m, 'gift', 'gift_ask', '@username');
            return;
        }
        if (Cache::throttled('giftcmd:' . ($m['from']['id'] ?? 0), 8)) {
            return;
        }
        TG::api('sendChatAction', ['chat_id' => $m['chat']['id'], 'action' => 'upload_photo', '__quick' => true]);
        TG::warm();
        Perf::start();
        self::$wait = !empty($m['__prompt']) ? Waiter::start($m, 'gift_wait', (int)$m['__prompt']) : null;
        try {
            $t = self::target($m, $args);
            self::tick();
            Perf::mark('resolve');
            if (!$t) {
                Perf::flush('gift');
                self::say($m, UI::t('gift_user_unknown'));
                return;
            }
            self::portfolio($m, $t);
        } catch (Throwable $e) {
            error_log('gift: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            Perf::flush('gift-error');
            self::say($m, UI::t('error'));
        } finally {
            self::$wait?->finish();
            self::$wait = null;
        }
    }

    private static function owned(array $t): ?array
    {
        if ($t['kind'] === 'mt') {
            $api = self::owned(['kind' => $t['type'] === 'channel' ? 'chat' : 'user', 'id' => $t['type'] === 'channel' ? (int)('-100' . $t['id']) : $t['id'], 'username' => null]);
            return $api ?? MTProto::gifts($t, 400, fn() => self::tick());
        }
        $items = [];
        $total = 0;
        $offset = null;
        for ($i = 0; $i < 4; $i++) {
            $p = ['limit' => 100, 'exclude_unlimited' => true, 'exclude_limited_upgradable' => true, 'exclude_limited_non_upgradable' => true] + ($offset ? ['offset' => $offset] : []);
            $r = $t['kind'] === 'chat' ? TG::api('getChatGifts', ['chat_id' => $t['id']] + $p) : TG::api('getUserGifts', ['user_id' => $t['id']] + $p);
            self::tick();
            if ($r === null) {
                if (!$i && !empty($t['username']) && MTProto::enabled() && ($mt = MTProto::resolve($t['username']))) {
                    return MTProto::gifts($mt, 400, fn() => self::tick());
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
        $extra = Bot::isPrivate($m) ? [] : ['reply_parameters' => Bot::replyParams($m)];
        self::$wait ??= Waiter::start($m, 'gift_wait');
        $res = self::owned($t);
        self::tick();
        Perf::mark('owned:' . count($res[0] ?? []));
        if ($res === null) {
            Perf::flush('gift');
            self::say($m, UI::t('gift_user_unknown'));
            return;
        }
        $owned = array_values(array_filter($res[0], fn($og) => ($og['type'] ?? '') === 'unique'));
        $count = count($owned);
        if (!$owned) {
            self::say($m, UI::t('gift_user_none', ['name' => UI::esc($t['name'])]));
            return;
        }
        $s = Prices::snapshot();
        $tonUsd = $s['ton'];
        $tonToman = $tonUsd && $s['toman'] ? $tonUsd * $s['toman'] : null;
        $ds = Gifts::dataset();
        self::tick();
        Perf::mark('dataset');
        Gifts::prefetch(array_map(fn($og) => (string)($og['gift']['base_name'] ?? ''), $owned));
        self::tick();
        Perf::mark('floors');
        $tiles = [];
        $now = 0.0;
        $then = 0.0;
        $live = GiftMarket::fragmentFloors(array_map(fn($og) => (string)($og['gift']['base_name'] ?? ''), $owned));
        self::tick();
        Perf::mark('fragment:' . count($live));
        foreach ($owned as $og) {
            $g = $og['gift'] ?? [];
            $v = Gifts::valueOf($g['base_name'] ?? '', null, null, null);
            $val = $live[Gifts::norm($g['base_name'] ?? '')] ?? $v['floor'];
            if (!$val && !empty($og['value_usd']) && $tonUsd) {
                $val = $og['value_usd'] / $tonUsd;
            }
            if ($val) {
                $now += $val;
                $then += $v['floor'] && $v['floor_7d'] ? $val * $v['floor_7d'] / $v['floor'] : $val;
            }
            $c = $g['backdrop']['colors'] ?? [];
            $tiles[] = ['title' => ($g['base_name'] ?? 'Gift') . ' #' . ($g['number'] ?? ''), 'subtitle' => implode(' • ', array_filter([$g['model']['name'] ?? null, $g['backdrop']['name'] ?? null])),
                'value' => $val, 'center' => self::rgb($c['center_color'] ?? null, [90, 110, 200]), 'edge' => self::rgb($c['edge_color'] ?? null, [30, 30, 60]), 'name' => $g['name'] ?? null];
        }
        usort($tiles, fn($a, $b) => ($b['value'] ?? 0) <=> ($a['value'] ?? 0));
        Perf::mark('values');
        $imgs = Gifts::images(array_map(fn($x) => $x['name'], array_slice($tiles, 0, 12)));
        self::tick();
        Perf::mark('images');
        foreach ($imgs as $i => $bytes) {
            $tiles[$i]['image'] = $bytes;
        }
        $ck = 'gpc:' . md5(json_encode([$t['id'] ?? 0, array_map(fn($x) => [$x['title'], round((float)$x['value'], 3)], $tiles), date('YmdHi'), UI::$lang]));
        $hit = Cache::get($ck);
        if (!empty($hit['f']) && TG::photo($m['chat']['id'], $hit['f'], $hit['c'], UI::footer(), $extra) !== null) {
            Perf::mark('cached');
            Perf::flush('gift');
            return;
        }
        $pl = $then ? $now - $then : null;
        $pct = ($pl !== null && $then) ? $pl / $then * 100 : null;
        $last = DB::lastGiftSnapshot((int)$t['id']);
        DB::addGiftSnapshot((int)$t['id'], $now);
        $plShow = ($pl !== null && abs($pl) > 0.0001) ? $pl : null;
        $png = PriceCard::gift($t['name'], $t['username'], $tiles, $now, $tonUsd ? $now * $tonUsd : null, $tonToman ? $now * $tonToman : null, $plShow, $pct, (int)$count, TG::username())
            ?? Cards::gift($t['name'], $t['username'], $tiles, $now, $tonUsd ? $now * $tonUsd : null, $tonToman ? $now * $tonToman : null, $plShow, $pct, (int)$count, TG::username());
        self::tick();
        Perf::mark('render');
        $plLine = $plShow !== null ? UI::fill(UI::template($pl >= 0 ? 'gift_profit' : 'gift_loss'), ['ton' => Fmt::num(abs($pl)), 'percent' => sprintf('%.2f', abs($pct ?? 0)), 'toman' => $tonToman ? Fmt::toman(abs($pl) * $tonToman) : '—']) : null;
        $change = null;
        if ($last && (float)$last['value_ton']) {
            $diff = $now - (float)$last['value_ton'];
            $change = UI::fill(UI::template('gift_since_last'), ['sign' => $diff >= 0 ? '+' : '-', 'ton' => Fmt::num(abs($diff)), 'percent' => sprintf('%.2f', abs($diff / (float)$last['value_ton'] * 100))]);
        }
        $top = [];
        foreach ($tiles as $x) {
            if (!empty($x['name']) && count($top) < 3) {
                $top[] = 'https://t.me/nft/' . $x['name'];
            }
        }
        for ($n = count($top); $n >= 0; $n--) {
            $links = array_slice($top, 0, $n);
            $caption = UI::t('gift_user_caption', ['name' => UI::esc($t['name']), 'count' => Fmt::int($count),
                'total_ton' => Fmt::num($now), 'total_usd' => $tonUsd ? Fmt::usd($now * $tonUsd) : '—', 'total_toman' => $tonToman ? Fmt::toman($now * $tonToman) : '—',
                'pl_line' => $plLine, 'change_line' => $change, 'links' => $links ? implode("\n", array_map(fn($l) => "🔗 $l", $links)) : null, 'time' => Fmt::now()]);
            if (UI::textLen($caption) <= 1000) {
                break;
            }
        }
        $sent = TG::photo($m['chat']['id'], $png, $caption, UI::footer(), $extra);
        $ph = $sent['photo'] ?? [];
        if ($ph && !empty(end($ph)['file_id'])) {
            Cache::set($ck, ['f' => end($ph)['file_id'], 'c' => $caption], 120);
        }
        Perf::mark('sent');
        Perf::flush('gift');
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
            TG::answer($cq['id'], UI::plain('not_your_button'), true);
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
        if ($action === 'revoke') {
            if ($r['status'] === 'approved') {
                DB::updateScam((int)$id, ['status' => 'revoked']);
                TG::answer($cq['id'], '✅');
                TG::edit($cq['message']['chat']['id'], $cq['message']['message_id'], UI::esc($cq['message']['text'] ?? '') . "\n\n<b>♻️ تایید لغو شد</b>", null);
            } else {
                TG::answer($cq['id'], 'status: ' . $r['status'], true);
            }
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
        TG::edit($cq['message']['chat']['id'], $cq['message']['message_id'], UI::esc($cq['message']['text'] ?? '') . "\n\n<b>$status</b>", $ok ? UI::kb([[UI::raw('♻️ لغو تایید', "scamadm:revoke:$id", 'danger')]]) : null);
        TG::send($r['reporter_id'], UI::t($ok ? 'scam_approved_user' : 'scam_rejected_user', ['id' => $r['id']]));
        if ($ok) {
            self::broadcast($r);
        }
    }

    public static function broadcast(array $r): int
    {
        $texts = [];
        $sent = 0;
        $was = UI::$lang;
        foreach (DB::activeGroups() as $g) {
            if (!Settings::on((int)$g['id'], 'anti_scam')) {
                continue;
            }
            Lang::use($g['id']);
            $text = $texts[UI::$lang] ??= UI::t('scam_broadcast', ['target' => self::label($r), 'target_id' => $r['target_id'] ?: '—', 'reason' => UI::esc($r['reason'] ?: '—'), 'id' => $r['id']]);
            if (TG::send($g['id'], $text, UI::footer())) {
                $sent++;
            } elseif (preg_match('/kicked|not found|forbidden/i', (string)TG::$lastError)) {
                DB::setChatActive((int)$g['id'], false);
            }
            usleep(50000);
        }
        UI::$lang = $was;
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
    public static function pinLink(array $m, string $link): void
    {
        TG::warm();
        TG::api('sendChatAction', ['chat_id' => $m['chat']['id'], 'action' => 'upload_video', '__quick' => true]);
        $p = Pinterest::pin($link);
        $items = $p ? Pinterest::media($p) : [];
        if (!$items) {
            Bot::send($m, UI::t('pin_not_found'), null);
            return;
        }
        $x = Pinterest::info($p);
        $text = trim(preg_replace('/\s+/u', ' ', UI::stripEmoji($x['text'])));
        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 300) . '…';
        }
        $title = UI::stripEmoji($x['title']);
        $cap = UI::t('pin_caption', [
            'title' => $title !== '' ? UI::esc(mb_substr($title, 0, 100)) : null,
            'saves' => $x['saves'] !== null ? Fmt::int((int)$x['saves']) : null, 'date' => $x['date'] ? Fmt::jalali((int)$x['date']) : null,
            'count' => count($items) > 1 ? Fmt::int(count($items)) : null, 'text' => $text !== '' ? UI::esc($text) : null, 'bot' => TG::username(),
        ]);
        $items = self::pinResolve($items);
        if (!$items) {
            Bot::send($m, UI::t('pin_not_found'), null);
            return;
        }
        $extra = !Bot::isPrivate($m) ? ['reply_parameters' => Bot::replyParams($m)] : [];
        if (count($items) === 1) {
            $it = $items[0];
            $p = ['caption' => $cap, 'reply_markup' => UI::footer()] + $extra;
            if (!isset($it['bytes']) && self::upload($m, $it, $p) !== null) {
                return;
            }
            if (!isset($it['bytes']) && !empty($it['hls']) && ($v = Hls::fetch($it['hls']))) {
                $it = $v + $it;
            }
            if (isset($it['bytes']) && self::sendBytes($m, $it, $p) !== null) {
                return;
            }
            Bot::send($m, UI::t('pin_not_found'), null);
            return;
        }
        $group = [];
        $files = [];
        foreach ($items as $i => $it) {
            $g = ['type' => $it['kind'] === 'video' ? 'video' : 'photo', 'media' => $it['url'] ?? null];
            if (isset($it['bytes'])) {
                $files["f$i"] = ['__bytes' => $it['bytes'], 'name' => "f$i.mp4", 'mime' => 'video/mp4'];
                $g = ['type' => 'video', 'media' => "attach://f$i"] + array_filter(['width' => $it['width'] ?: null, 'height' => $it['height'] ?: null, 'duration' => $it['duration'] ?: null]);
            }
            if ($g['type'] === 'video') {
                $g['supports_streaming'] = true;
            }
            $group[] = $g;
        }
        if (TG::api('sendMediaGroup', ['chat_id' => $m['chat']['id'], 'media' => $group] + $files + $extra) === null) {
            $ok = 0;
            foreach ($items as $it) {
                if ((isset($it['bytes']) ? self::sendBytes($m, $it, $extra) : self::upload($m, $it, $extra)) !== null) {
                    $ok++;
                }
            }
            if (!$ok) {
                Bot::send($m, UI::t('pin_not_found'), null);
                return;
            }
        }
        Bot::send($m, $cap);
    }

    private static function pinResolve(array $items): array
    {
        $checks = [];
        foreach ($items as $i => $it) {
            if ($it['kind'] === 'video' && empty($it['url'])) {
                foreach ($it['alts'] ?? [] as $j => $u) {
                    $checks["$i:$j"] = ['url' => $u, 'timeout' => 5, 'headers' => ['Range' => 'bytes=0-1', 'User-Agent' => Hls::UA, 'Referer' => 'https://www.pinterest.com/']];
                }
            }
        }
        $res = $checks ? Http::multi($checks, 6) : [];
        $out = [];
        foreach ($items as $i => $it) {
            $it['ref'] = 'https://www.pinterest.com/';
            if ($it['kind'] === 'video' && empty($it['url'])) {
                foreach ($it['alts'] ?? [] as $j => $u) {
                    if (in_array($res["$i:$j"]['status'] ?? 0, [200, 206], true)) {
                        $it['url'] = $u;
                        break;
                    }
                }
                if (empty($it['url']) && !empty($it['hls']) && ($v = Hls::fetch($it['hls']))) {
                    $it = $v + $it;
                    $it['hls'] = null;
                }
                if (empty($it['url']) && !isset($it['bytes'])) {
                    continue;
                }
            }
            $out[] = $it;
        }
        return array_slice($out, 0, 10);
    }

    private static function sendBytes(array $m, array $it, array $p): ?array
    {
        return TG::api('sendVideo', ['chat_id' => $m['chat']['id'], 'video' => ['__bytes' => $it['bytes'], 'name' => 'video.mp4', 'mime' => 'video/mp4'],
            'supports_streaming' => true] + array_filter(['width' => $it['width'] ?? null, 'height' => $it['height'] ?? null, 'duration' => $it['duration'] ?? null]) + $p);
    }

    private static function err(int $code): string
    {
        return [451 => 'insta_private', 452 => 'insta_no_story', 453 => 'insta_no_highlight'][$code] ?? 'insta_not_found';
    }

    private static function upload(array $m, array $it, array $p): ?array
    {
        $method = $it['kind'] === 'video' ? 'sendVideo' : 'sendPhoto';
        $field = $it['kind'] === 'video' ? 'video' : 'photo';
        $p = ['chat_id' => $m['chat']['id']] + $p;
        if ($it['kind'] === 'video') {
            $p['supports_streaming'] = true;
        }
        if (empty($it['url'])) {
            return null;
        }
        if (($r = TG::api($method, [$field => $it['url']] + $p)) !== null) {
            return $r;
        }
        $d = Http::request(['url' => $it['url'], 'timeout' => 90, 'headers' => ['User-Agent' => Http::UA, 'Accept' => '*/*', 'Referer' => $it['ref'] ?? 'https://www.instagram.com/']]);
        if ($d['status'] >= 200 && $d['status'] < 300 && strlen($d['body']) > 0 && strlen($d['body']) < 49 * 1048576) {
            return TG::api($method, [$field => ['__bytes' => $d['body'], 'name' => $it['kind'] === 'video' ? 'media.mp4' : 'media.jpg']] + $p);
        }
        return null;
    }

    private static function instaCaption(array $x, int $count): string
    {
        $dur = isset($x['duration']) && $x['duration'] > 0 ? sprintf('%d:%02d', intdiv((int)round($x['duration']), 60), (int)round($x['duration']) % 60) : null;
        $text = UI::stripEmoji((string)($x['text'] ?? ''));
        $text = preg_replace('/(?:[-_=~•*.·|]\s*){3,}/u', ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 300) . '…';
        }
        $n = fn($v) => $v === null ? null : Fmt::int($v);
        return UI::t('insta_media_caption', [
            'title' => UI::l(!empty($x['reel']) ? 'ریلز اینستاگرام' : 'پست اینستاگرام'), 'owner' => !empty($x['owner']) ? '@' . UI::esc($x['owner']) : null,
            'likes' => $n($x['likes'] ?? null), 'comments' => $n($x['comments'] ?? null), 'views' => $n($x['views'] ?? null), 'duration' => $dur,
            'date' => !empty($x['date']) ? Fmt::jalali((int)$x['date']) : null, 'count' => $count > 1 ? Fmt::int($count) : null,
            'text' => $text !== '' ? UI::esc($text) : null, 'bot' => TG::username(),
        ]);
    }

    private static function sendPost(array $m, array $items, string $cap): void
    {
        $extra = !Bot::isPrivate($m) ? ['reply_parameters' => Bot::replyParams($m)] : [];
        $items = array_slice($items, 0, 10);
        if (count($items) === 1) {
            self::upload($m, $items[0], ['caption' => $cap, 'reply_markup' => UI::footer()] + $extra);
            return;
        }
        $group = [];
        foreach ($items as $i => $it) {
            $group[] = ['type' => $it['kind'] === 'video' ? 'video' : 'photo', 'media' => $it['url']] + ($i === 0 ? ['caption' => $cap, 'parse_mode' => 'HTML'] : []);
        }
        if (TG::api('sendMediaGroup', ['chat_id' => $m['chat']['id'], 'media' => $group] + $extra) !== null) {
            return;
        }
        foreach ($items as $i => $it) {
            self::upload($m, $it, $i === 0 ? ['caption' => $cap, 'reply_markup' => UI::footer()] + $extra : $extra);
        }
    }

    private static function sendMedia(array $m, array $items): void
    {
        $n = count($items);
        foreach ($items as $i => $it) {
            $cap = UI::t('insta_story_caption', ['index' => $i + 1, 'count' => $n, 'bot' => TG::username()]);
            $method = $it['kind'] === 'video' ? 'sendVideo' : 'sendPhoto';
            $field = $it['kind'] === 'video' ? 'video' : 'photo';
            if (TG::api($method, ['chat_id' => $m['chat']['id'], $field => $it['url'], 'caption' => $cap]) !== null) {
                continue;
            }
            $r = Http::request(['url' => $it['url'], 'timeout' => 120]);
            if ($r['status'] >= 200 && $r['status'] < 300 && strlen($r['body']) > 0 && strlen($r['body']) < 49 * 1048576) {
                TG::api($method, ['chat_id' => $m['chat']['id'], $field => ['__bytes' => $r['body'], 'name' => $it['kind'] === 'video' ? 'media.mp4' : 'media.jpg'], 'caption' => $cap]);
            }
            usleep(300000);
        }
    }

    public static function instaLink(array $m, string $link): void
    {
        TG::warm();
        if (preg_match('~/stories/~i', $link) && !Instagram::enabled()) {
            if (Bot::isPrivate($m)) {
                Bot::send($m, UI::t('insta_no_key'), null);
            }
            return;
        }
        TG::api('sendChatAction', ['chat_id' => $m['chat']['id'], 'action' => 'upload_video', '__quick' => true]);
        $media = Instagram::post($link);
        if (!$media) {
            Bot::send($m, UI::t(self::err(Instagram::$lastStatus)), null);
            return;
        }
        self::sendPost($m, $media, self::instaCaption(Instagram::$meta, count($media)));
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
        if (!empty($info['profile']) && UI::textLen($text) <= 1000) {
            if (TG::photo($m['chat']['id'], $info['profile'], $text, UI::footer($rows), Bot::isPrivate($m) ? [] : ['reply_parameters' => Bot::replyParams($m)])) {
                return;
            }
        }
        Bot::send($m, $text, UI::footer($rows));
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
        self::sendMedia($cq['message'], $items);
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
        self::sendMedia($cq['message'], $items);
    }
}

final class TextRouter
{
    const UID = ['uid', 'آیدی عددی', 'ایدی عددی', 'آیدی', 'ایدی', 'id', 'ایدیش', 'آیدیش', 'айди'];
    const TR = ['ترجمه', 'ترجمه کن', 'translate', 'tr', 'ترجمش کن', 'ترجمه شود', 'перевод', 'перевести'];

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
        if (!$priv && !empty($m['reply_to_message']) && Moderation::handle($m)) {
            return;
        }
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
        if ($on('games') && Games::text($m)) {
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
        if (($l = Pinterest::link($text)) && $on('pinterest')) {
            Extras::pinLink($m, $l);
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
        if ($priv && str_starts_with($text, '@') && Instagram::enabled() && preg_match('/^@?([A-Za-z0-9_.]{1,30})$/', $text, $um)) {
            Extras::instaProfile($m, $um[1]);
            return;
        }
        if (mb_strlen($text) > 60) {
            return;
        }
        $conv = $on('conversions');
        if ($conv && ($pq = Parse::pair($text)) && Tools::answerPair($m, ...$pq)) {
            return;
        }
        if ($conv && ($p = Parse::amount($text)) && Tools::answerAmount($m, $p[0], $p[1], !$priv)) {
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
        if ($conv && preg_match('/^(?:قیمت|نرخ|price|ارزش)\s+(.+)$/iu', $text, $pm)) {
            $t = trim($pm[1]);
            if (($pq = Parse::pair($t)) && Tools::answerPair($m, ...$pq)) {
                return;
            }
            ($s = Parse::amount($t)) ? Tools::answerAmount($m, $s[0], $s[1], !$priv, true) : Tools::answerAmount($m, 1, $t, !$priv, true);
            return;
        }
        if ($conv && (Currencies::knownCrypto($text) || Currencies::bareAsset($text) || ($priv && preg_match('/^[A-Za-z0-9]{2,15}$/', $text) && SpotDir::mask($text)))) {
            Tools::answerAmount($m, 1, $text, true);
            return;
        }
        if (($e = Parse::expression($text)) && $on('calculator')) {
            Tools::answerCalc($m, $e);
        }
    }
}

final class Moderation
{
    const WARN_MAX = 3;
    const WARN_MUTE = 840;
    const VIP_MUTE = 10;
    const VIP_WARNS = 1;
    const TITLE = '𝗔𝗗𝗠𝗜𝗡';
    const VIP_TAG = '𝗩𝗜𝗣';
    const WORDS = [
        'ban' => ['بن', 'ban', 'бан'],
        'unban' => ['رفع بن', 'آن بن', 'انبن', 'unban', 'разбан'],
        'mute' => ['سکوت', 'mute', 'мут'],
        'unmute' => ['حذف سکوت', 'رفع سکوت', 'unmute', 'размут'],
        'warn' => ['اخطار', 'warn', 'варн'],
        'unwarn' => ['حذف اخطار', 'unwarn', 'снять варн'],
        'admin' => ['تنظیم مدیر', 'تنظیم ادمین', 'ادمین', 'set admin', 'админ'],
        'unadmin' => ['حذف مدیر', 'حذف ادمین', 'remove admin', 'снять админ'],
        'vip' => ['تنظیم ویژه', 'set vip', 'вип'],
        'unvip' => ['حذف ویژه', 'remove vip', 'снять вип'],
    ];
    const VIP_CAN = ['mute', 'warn'];
    const OWNER_ONLY = ['admin', 'unadmin', 'vip', 'unvip'];
    const PUNISH = ['ban', 'mute', 'warn'];
    const UNDO = ['ban' => 'unban', 'mute' => 'unmute', 'warn' => 'unwarn', 'admin' => 'unadmin', 'vip' => 'unvip'];
    const RIGHTS = ['can_manage_chat', 'can_delete_messages', 'can_restrict_members', 'can_invite_users', 'can_pin_messages', 'can_manage_video_chats'];
    private static array $vips = [];

    public static function vips(int $chat): array
    {
        return self::$vips[$chat] ??= array_map('intval', DB::chatSettings($chat)['vip'] ?? []);
    }

    public static function allVips(): array
    {
        $out = [];
        foreach (DB::all("SELECT id, title, settings FROM chats WHERE settings LIKE '%\"vip\"%'") as $r) {
            $v = array_values(array_unique(array_map('intval', json_decode($r['settings'] ?: '{}', true)['vip'] ?? [])));
            if ($v) {
                $out[(int)$r['id']] = ['title' => (string)($r['title'] ?: $r['id']), 'vip' => $v];
            }
        }
        return $out;
    }

    public static function isVip(int $chat, int $user): bool
    {
        return $chat < 0 && in_array($user, self::vips($chat), true);
    }

    private static function setVip(int $chat, int $user, bool $on): void
    {
        $list = array_values(array_diff(self::vips($chat), [$user]));
        if ($on) {
            $list[] = $user;
        }
        DB::setChatSetting($chat, 'vip', $list);
        self::$vips[$chat] = $list;
    }

    private static function action(string $text): ?string
    {
        $t = Currencies::normalize($text);
        foreach (self::WORDS as $act => $words) {
            foreach ($words as $w) {
                if ($t === Currencies::normalize($w)) {
                    return $act;
                }
            }
        }
        return null;
    }

    public static function handle(array $m): bool
    {
        $target = $m['reply_to_message']['from'] ?? null;
        $by = $m['from'] ?? null;
        if (Bot::isPrivate($m) || !$target || !$by || !empty($m['sender_chat']) || !($act = self::action((string)($m['text'] ?? '')))) {
            return false;
        }
        $chat = (int)$m['chat']['id'];
        if (in_array($act, self::OWNER_ONLY, true) && !Config::isAdmin((int)$by['id'])) {
            if (Bot::isChatAdmin($chat, (int)$by['id'])) {
                Bot::reply($m, UI::t('mod_owner_only'), null);
                return true;
            }
            return false;
        }
        $admin = Bot::isChatAdmin($chat, (int)$by['id']);
        $vip = !$admin && self::isVip($chat, (int)$by['id']);
        if (!$admin && !($vip && in_array($act, self::VIP_CAN, true))) {
            return false;
        }
        $uid = (int)$target['id'];
        if (!empty($target['is_bot']) || $uid === (int)$by['id'] || !empty($m['reply_to_message']['sender_chat'])) {
            return true;
        }
        if (in_array($act, self::PUNISH, true) && (self::isVip($chat, $uid) || Bot::isChatAdmin($chat, $uid))) {
            Bot::reply($m, UI::t('mod_immune', ['target' => UI::mention($target)]), null);
            return true;
        }
        if ($vip && $act === 'warn') {
            $k = 'vw:' . (int)$by['id'];
            if ((int)(DB::one('SELECT count FROM strikes WHERE chat_id=? AND user_id=? AND kind=?', [$chat, $uid, $k])['count'] ?? 0) >= self::VIP_WARNS) {
                Bot::reply($m, UI::t('mod_vip_once', ['warns' => self::VIP_WARNS]), null);
                return true;
            }
            DB::q('INSERT INTO strikes(chat_id, user_id, kind, count, updated_at) VALUES(?, ?, ?, 1, ?) ON CONFLICT(chat_id, user_id, kind) DO UPDATE SET count=count+1, updated_at=excluded.updated_at',
                [$chat, $uid, $k, time()]);
        }
        [$key, $vars, $kb] = self::run($chat, $act, $uid, $vip);
        if ($key === null) {
            Bot::reply($m, UI::t('mod_failed'), null);
            return true;
        }
        Bot::reply($m, UI::t($key, ['target' => UI::mention($target), 'by' => UI::mention($by)] + $vars), $kb);
        return true;
    }

    private static function run(int $chat, string $act, int $uid, bool $vip): array
    {
        $undo = fn(string $btn, string $a) => UI::kb([[UI::button($btn, ['cb' => "md:$a:$uid"])]]);
        switch ($act) {
            case 'ban':
                return TG::api('banChatMember', ['chat_id' => $chat, 'user_id' => $uid]) !== null ? ['mod_ban', [], $undo('btn_md_unban', 'unban')] : [null, [], null];
            case 'unban':
                return TG::api('unbanChatMember', ['chat_id' => $chat, 'user_id' => $uid, 'only_if_banned' => true]) !== null ? ['mod_unban', [], null] : [null, [], null];
            case 'mute':
                $min = $vip ? self::VIP_MUTE : 0;
                return Bot::mute($chat, $uid, $min) ? ['mod_mute', ['duration' => Fmt::duration($min)], $undo('btn_md_unmute', 'unmute')] : [null, [], null];
            case 'unmute':
                return Bot::unmute($chat, $uid) ? ['mod_unmute', [], null] : [null, [], null];
            case 'warn':
                DB::q('INSERT INTO strikes(chat_id, user_id, kind, count, updated_at) VALUES(?, ?, ?, 1, ?) ON CONFLICT(chat_id, user_id, kind) DO UPDATE SET count=count+1, updated_at=excluded.updated_at',
                    [$chat, $uid, 'warn', time()]);
                $n = (int)(DB::one('SELECT count FROM strikes WHERE chat_id=? AND user_id=? AND kind=?', [$chat, $uid, 'warn'])['count'] ?? 1);
                if ($n < self::WARN_MAX) {
                    return ['mod_warn', ['count' => $n, 'max' => self::WARN_MAX], $undo('btn_md_unwarn', 'unwarn')];
                }
                DB::resetStrikes($chat, $uid, 'warn');
                return Bot::mute($chat, $uid, self::WARN_MUTE)
                    ? ['mod_warn_mute', ['max' => self::WARN_MAX, 'duration' => Fmt::duration(self::WARN_MUTE)], $undo('btn_md_unmute', 'unmute')] : [null, [], null];
            case 'unwarn':
                DB::q("DELETE FROM strikes WHERE chat_id=? AND user_id=? AND (kind='warn' OR kind LIKE 'vw:%')", [$chat, $uid]);
                return ['mod_unwarn', [], null];
            case 'admin':
                if (TG::api('promoteChatMember', ['chat_id' => $chat, 'user_id' => $uid] + array_fill_keys(self::RIGHTS, true)) === null) {
                    return [null, [], null];
                }
                $title = self::TITLE;
                if (TG::api('setChatAdministratorCustomTitle', ['chat_id' => $chat, 'user_id' => $uid, 'custom_title' => $title]) === null) {
                    $title = 'ADMIN';
                    TG::api('setChatAdministratorCustomTitle', ['chat_id' => $chat, 'user_id' => $uid, 'custom_title' => $title]);
                }
                Cache::forget("admins:$chat");
                return ['mod_admin', ['title' => $title], $undo('btn_md_unadmin', 'unadmin')];
            case 'unadmin':
                $ok = TG::api('promoteChatMember', ['chat_id' => $chat, 'user_id' => $uid] + array_fill_keys(self::RIGHTS, false)) !== null;
                Cache::forget("admins:$chat");
                return $ok ? ['mod_unadmin', [], null] : [null, [], null];
            case 'vip':
                self::setVip($chat, $uid, true);
                $tag = null;
                foreach ([self::VIP_TAG, 'VIP'] as $t) {
                    if (TG::api('setChatMemberTag', ['chat_id' => $chat, 'user_id' => $uid, 'tag' => $t]) !== null) {
                        $tag = $t;
                        break;
                    }
                }
                return ['mod_vip', ['minutes' => self::VIP_MUTE, 'warns' => self::VIP_WARNS, 'title' => $tag], $undo('btn_md_unvip', 'unvip')];
            case 'unvip':
                self::setVip($chat, $uid, false);
                TG::api('setChatMemberTag', ['chat_id' => $chat, 'user_id' => $uid, 'tag' => '']);
                return ['mod_unvip', [], null];
        }
        return [null, [], null];
    }

    public static function cb(array $cq, string $arg): void
    {
        [$act, $uid] = explode(':', $arg) + [1 => 0];
        $chat = (int)($cq['message']['chat']['id'] ?? 0);
        $uid = (int)$uid;
        if ($chat >= 0 || !in_array($act, self::UNDO, true) || !Bot::isChatAdmin($chat, (int)$cq['from']['id'])) {
            TG::answer($cq['id'], UI::plain('only_group_admins'), true);
            return;
        }
        if (in_array($act, self::OWNER_ONLY, true) && !Config::isAdmin((int)$cq['from']['id'])) {
            TG::answer($cq['id'], UI::plain('mod_owner_only'), true);
            return;
        }
        [$key, $vars] = self::run($chat, $act, $uid, false);
        if ($key === null) {
            TG::answer($cq['id'], UI::plain('mod_failed'), true);
            return;
        }
        TG::answer($cq['id'], '✅');
        $mem = TG::api('getChatMember', ['chat_id' => $chat, 'user_id' => $uid]);
        $target = $mem ? UI::mention($mem['user']) : "<code>$uid</code>";
        TG::edit($chat, $cq['message']['message_id'], UI::t($key, ['target' => $target, 'by' => UI::mention($cq['from'])] + $vars), null);
    }
}

final class Usernames
{
    const STATUS = ['available' => 'آزاد برای حراج', 'avail' => 'آزاد برای حراج', 'on auction' => 'در حراج', 'auction' => 'در حراج', 'for sale' => 'برای فروش', 'sale' => 'برای فروش',
        'sold' => 'فروخته شده', 'taken' => 'گرفته شده', 'not available' => 'غیرقابل خرید', 'unavailable' => 'غیرقابل خرید', 'unavail' => 'غیرقابل خرید'];
    const LABELS = ['highest bid' => 'بالاترین پیشنهاد', 'auction' => 'بالاترین پیشنهاد', 'minimum bid' => 'حداقل پیشنهاد', 'avail' => 'حداقل پیشنهاد', 'price' => 'قیمت خرید',
        'sale' => 'قیمت خرید', 'buy now' => 'خرید فوری', 'sold for' => 'فروخته شده به', 'sold' => 'فروخته شده به'];

    public static function cmd(array $m, string $args): void
    {
        $name = Fragment::name($args !== '' ? $args : (string)($m['reply_to_message']['from']['username'] ?? ''));
        if (!$name) {
            Bot::reply($m, UI::t('user_usage'), null);
            return;
        }
        TG::warm();
        $f = Fragment::lookup($name);
        $at = '@' . UI::esc($name);
        if (!$f) {
            Bot::reply($m, UI::t('user_failed', ['username' => $at]), null);
            return;
        }
        $ton = Prices::usd('TON');
        $usdt = Prices::usdtToman();
        $lines = [];
        foreach (array_slice($f['prices'], 0, 3) as $p) {
            if (!$p['ton']) {
                continue;
            }
            $usd = $p['usd'] ?: ($ton ? $p['ton'] * $ton : null);
            $label = self::LABELS[strtolower(trim($p['label']))] ?? ($p['label'] !== '' ? $p['label'] : 'قیمت خرید');
            $lines[] = UI::fill(UI::template('user_line'), ['label' => UI::esc(UI::l($label)), 'ton' => Fmt::num($p['ton']),
                'usd' => $usd ? Fmt::usd($usd) : null, 'toman' => $usd && $usdt ? Fmt::toman($usd * $usdt) : null]);
        }
        $st = strtolower(trim((string)$f['status']));
        $status = self::STATUS[$st] ?? self::STATUS[(string)$f['kind']] ?? ($f['status'] ?: null);
        $text = UI::t('user_price', ['username' => $at, 'status' => $status ? UI::esc(UI::l($status)) : null, 'lines' => $lines ? implode("\n", $lines) : null,
            'ends' => $f['ends'] && $f['ends'] > time() ? Fmt::jalali((int)$f['ends']) : null, 'owner' => $f['owner'] ? UI::esc($f['owner']) : null, 'time' => Fmt::now()]);
        Bot::reply($m, $text, UI::footer([[UI::button('btn_fragment', ['url' => Fragment::BASE . '/username/' . $name])]]));
    }
}

final class NftH
{
    const MARKETS = [['portals', 'Portals', '🔵', 'https://t.me/portals'], ['mrkt', 'MRKT', '🟡', 'https://t.me/mrkt'], ['tonnel', 'Tonnel', '🟠', 'https://t.me/tonnel_network_bot'],
        ['fragment', 'Fragment', '💠', null], ['getgems', 'GetGems', '🔷', 'https://getgems.io/']];

    public static function cmd(array $m, string $args): void
    {
        $q = trim($args);
        if ($q === '') {
            Bot::reply($m, UI::t('nft_usage'), null);
            return;
        }
        Tools::react($m);
        TG::warm();
        $it = GiftMarket::find($q);
        if (!$it) {
            Bot::reply($m, UI::t('nft_not_found', ['query' => UI::esc(mb_substr($q, 0, 40))]), null);
            return;
        }
        TG::api('sendChatAction', ['chat_id' => $m['chat']['id'], 'action' => 'upload_photo', '__quick' => true]);
        $name = $it['full_name'];
        $col = Gifts::norm($name);
        $slug = GiftMarket::slug($name);
        $f = GiftMarket::floors($it);
        GiftMarket::hot($it['short_name']);
        $ton = Prices::usd('TON');
        $usdt = Prices::usdtToman();
        $usd = $f['floor'] && $ton ? $f['floor'] * $ton : null;
        $old = Gifts::floorsDaysAgo()[Gifts::norm($name)] ?? null;
        $chg = $f['floor'] && $old ? ($f['floor'] - $old) / $old * 100 : null;
        $fa = UI::$lang === 'fa' ? (Config::data('gifts_fa')[$name][0] ?? '') : '';
        $rows = [];
        foreach (self::MARKETS as [$k, $label, $icon, $url]) {
            if (!$f[$k]) {
                continue;
            }
            $num = $f[$k . '_num'] ?? null;
            $url = $num ? "https://t.me/nft/$slug-$num" : ($url ?? Fragment::BASE . "/gifts/$col?sort=price_asc&filter=sale");
            $rows[] = ['ton' => $f[$k], 'link' => '<a href="' . UI::attr($url) . '">' . $label . ($num ? " #$num" : '') . '</a>', 'icon' => $icon];
        }
        usort($rows, fn($x, $y) => $x['ton'] <=> $y['ton']);
        $top = $rows ? array_shift($rows) : null;
        $lines = array_map(fn($r) => $r['icon'] . ' ' . $r['link'] . ': <code>' . Fmt::num($r['ton']) . '</code> TON', $rows);
        $emoji = !empty($it['custom_emoji_id']) ? '<tg-emoji emoji-id="' . UI::attr($it['custom_emoji_id']) . '">🎁</tg-emoji>' : '🎁';
        $cap = UI::t('nft_caption', ['emoji' => $emoji, 'name' => UI::esc($name), 'fa' => UI::esc($fa),
            'floor' => $f['floor'] ? Fmt::num($f['floor']) : null, 'usd' => $usd ? Fmt::usd($usd) : null, 'toman' => $usd && $usdt ? Fmt::toman($usd * $usdt) : null,
            'market' => $top ? $top['link'] : '', 'markets' => $lines ? implode("\n", $lines) : null,
            'change' => $chg !== null ? ($chg >= 0 ? '+' : '') . sprintf('%.1f', $chg) . '%' : null, 'time' => Fmt::now()]);
        $fk = 'nftf:' . md5($col . '|' . json_encode($f) . '|' . date('YmdHi') . '|' . round((float)$usdt) . '|' . UI::$lang);
        if ($fid = Cache::get($fk)) {
            Bot::reply($m, $cap, false, $fid);
            return;
        }
        $photo = null;
        if (PriceCard::ready()) {
            try {
                $img = Gifts::images(['g' => $col . '-' . ($f['fragment_num'] ?: 1)])['g'] ?? Gifts::images(['g' => $col . '-1'])['g'] ?? null;
                $photo = PriceCard::giftFloor(['title' => mb_strtoupper(str_replace('’', "'", $name)), 'image' => $img, 'floor' => $f['floor'], 'usd' => $usd,
                    'toman' => $usd && $usdt ? $usd * $usdt : null, 'chg' => $chg, 'bot' => TG::username()]);
            } catch (Throwable $e) {
                error_log('nft card: ' . $e->getMessage());
            }
        }
        $sent = Bot::send($m, $cap, false, $photo);
        if (!empty($sent['photo']) && ($fid = end($sent['photo'])['file_id'] ?? null)) {
            Cache::set($fk, $fid, 120);
        }
    }
}
