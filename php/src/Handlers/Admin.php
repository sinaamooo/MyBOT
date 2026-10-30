<?php

final class Entities
{
    private static function open(array $e): string
    {
        return match ($e['type']) {
            'bold' => '<b>', 'italic' => '<i>', 'underline' => '<u>', 'strikethrough' => '<s>', 'spoiler' => '<tg-spoiler>',
            'code' => '<code>', 'pre' => !empty($e['language']) ? '<pre><code class="language-' . UI::attr($e['language']) . '">' : '<pre>',
            'text_link' => '<a href="' . UI::attr($e['url']) . '">', 'text_mention' => '<a href="tg://user?id=' . $e['user']['id'] . '">',
            'custom_emoji' => '<tg-emoji emoji-id="' . $e['custom_emoji_id'] . '">', 'blockquote' => '<blockquote>',
            'expandable_blockquote' => '<blockquote expandable>', default => '',
        };
    }

    private static function close(array $e): string
    {
        return match ($e['type']) {
            'bold' => '</b>', 'italic' => '</i>', 'underline' => '</u>', 'strikethrough' => '</s>', 'spoiler' => '</tg-spoiler>', 'code' => '</code>',
            'pre' => !empty($e['language']) ? '</code></pre>' : '</pre>', 'text_link', 'text_mention' => '</a>', 'custom_emoji' => '</tg-emoji>',
            'blockquote', 'expandable_blockquote' => '</blockquote>', default => '',
        };
    }

    public static function html(string $text, array $entities): string
    {
        $ents = array_values(array_filter($entities, fn($e) => self::open($e) !== ''));
        usort($ents, fn($a, $b) => $a['offset'] <=> $b['offset'] ?: $b['length'] <=> $a['length']);
        $out = '';
        $pos = 0;
        $stack = [];
        $chars = mb_str_split($text);
        $n = count($chars);
        for ($i = 0; $i <= $n; $i++) {
            while ($stack && end($stack)['offset'] + end($stack)['length'] <= $pos) {
                $out .= self::close(array_pop($stack));
            }
            foreach ($ents as $e) {
                if ($e['offset'] === $pos && $e['length'] > 0) {
                    $out .= self::open($e);
                    $stack[] = $e;
                }
            }
            if ($i === $n) {
                break;
            }
            $ch = $chars[$i];
            $out .= htmlspecialchars($ch, ENT_NOQUOTES, 'UTF-8');
            $pos += mb_ord($ch) > 0xFFFF ? 2 : 1;
        }
        while ($stack) {
            $out .= self::close(array_pop($stack));
        }
        return $out;
    }
}

final class Admin
{
    const PAGE = 8;
    const STYLES = ['primary' => 'آبی', 'success' => 'سبز', 'danger' => 'قرمز', 'none' => 'پیش‌فرض'];
    const SECTIONS = [
        'main' => ['🏠', 'عمومی', ['start_private', 'help', 'bot_added', 'bot_promoted', 'error', 'loading', 'bot_off', 'banned', 'not_your_button', 'expired', 'admin_message']],
        'price' => ['💱', 'قیمت و تبدیل', ['conv_crypto', 'conv_toman', 'conv_fiat', 'conv_gold', 'conv_stars', 'market', 'market_line', 'arz_board', 'arz_line', 'premium_prices', 'premium_line', 'stars_prices', 'stars_line', 'currency_not_found', 'price_unavailable']],
        'wallet' => ['👛', 'ولت و هش', ['wallet_checking', 'wallet_header', 'wallet_chain', 'wallet_footer', 'wallet_not_found', 'tx_checking', 'tx_result', 'tx_not_found', 'tron_transfers', 'tron_transfer_item', 'tron_transfers_empty', 'tron_tokens_file', 'whale_alert']],
        'gift' => ['👑', 'گیفت', ['gift_link', 'gift_user_caption', 'gift_profit', 'gift_loss', 'gift_since_last', 'gift_user_none', 'gift_user_unknown', 'gift_not_found']],
        'guard' => ['🛡', 'گروه و امنیت', ['force_join_private', 'force_join_group', 'force_join_ok', 'force_join_not_yet', 'self_warn', 'self_muted', 'tabchi_muted', 'unmuted', 'group_settings', 'only_group_admins']],
        'scam' => ['🚨', 'کلاهبرداری', ['scam_keyword_warn', 'scam_known', 'scam_usage', 'scam_go_private', 'scam_ask_evidence', 'scam_evidence_saved', 'scam_need_evidence', 'scam_submitted', 'scam_cancelled', 'scam_admin', 'scam_approved_user', 'scam_rejected_user', 'scam_broadcast', 'scam_check_clean', 'scam_check_found']],
        'tools' => ['🧩', 'ابزارها', ['uid', 'account', 'emoji_item', 'emoji_none', 'calc_result', 'translate_choose', 'translate_result', 'translate_not_yours', 'translate_used', 'translate_need_reply', 'translate_failed']],
        'bank' => ['💳', 'بانک و شماره', ['card_result', 'iban_result', 'phone_result', 'phone_invalid', 'phone_failed']],
        'insta' => ['🎞', 'اینستاگرام', ['insta_loading', 'insta_count', 'insta_media_caption', 'insta_not_found', 'insta_private', 'insta_no_story', 'insta_no_highlight', 'insta_no_key', 'insta_highlights', 'insta_info']],
    ];
    const NUMBERS = [
        'usdt_toman_manual' => 'نرخ دستی تتر (تومان، 0 = خودکار)', 'star_usd' => 'قیمت هر استار (دلار)',
        'whale_min_usd' => 'حداقل مبلغ هشدار جابجایی ($)', 'whale_btc_min' => 'حداقل BTC برای هشدار',
        'self_mute_minutes' => 'مدت سکوت سلف (دقیقه، 0 = دائمی)', 'tabchi_mute_minutes' => 'مدت سکوت تبچی (دقیقه، 0 = دائمی)',
    ];
    const STRINGS = ['channel_url' => 'لینک کانال ربات', 'channel_title' => 'نام کانال ربات'];
    const HOME = '⚙️ <b>پنل مدیریت ربات</b>';

    private static function toggles(): array
    {
        return Settings::groupFeatures() + ['force_join_private' => 'عضویت اجباری پیوی', 'auto_ban_scammers' => 'بن خودکار کلاهبردار', 'fa_digits' => 'اعداد فارسی'];
    }

    private static function back(string $to = 'adm:home'): array
    {
        return [UI::raw('🏠 بازگشت', $to)];
    }

    private static function grid(array $buttons, int $cols = 2): array
    {
        return array_chunk($buttons, $cols);
    }

    private static function short(string $desc): string
    {
        $d = trim(explode('(', $desc)[0]);
        $d = preg_replace('/^دکمه\s+/u', '', $d);
        return mb_strlen($d) > 28 ? mb_substr($d, 0, 27) . '…' : $d;
    }

    public static function homeKb(): array
    {
        $on = (bool)Settings::get('bot_enabled');
        return UI::kb([
            [UI::raw('📋 متن‌ها', 'adm:texts', 'primary'), UI::raw('🔲 دکمه‌ها', 'adm:btns', 'primary')],
            [UI::raw('⚙️ تنظیمات', 'adm:set:1'), UI::raw('🔒 عضویت اجباری', 'adm:fj')],
            [UI::raw('🚨 گزارش‌ها', 'adm:scams'), UI::raw('📊 آمار', 'adm:stats')],
            [UI::raw('📢 ارسال همگانی', 'adm:bcmenu'), UI::raw('📨 پیام به کاربر', 'adm:pm')],
            [UI::raw('⛔️ مسدود / آزاد', 'adm:ban')],
            [UI::raw($on ? '🟢 ربات روشن است' : '🔴 ربات خاموش است', 'adm:power', $on ? 'success' : 'danger')],
        ]);
    }

    public static function panel(array $m, string $args = ''): void
    {
        State::clear($m['from']['id']);
        TG::send($m['chat']['id'], self::HOME, self::homeKb());
    }

    private static function wait(array $cq, string $action, string $prompt, array $extra = []): void
    {
        State::set($cq['from']['id'], ['mode' => 'admin', 'action' => $action] + $extra);
        TG::answer($cq['id']);
        TG::send($cq['message']['chat']['id'], $prompt . "\n\n⛔️ لغو: /cancel");
    }

    private static function placeholders(string $desc): string
    {
        preg_match_all('/\{[a-z_]+\}/', $desc, $m);
        $u = array_unique($m[0]);
        return $u ? implode(' ', array_map(fn($p) => "<code>$p</code>", $u)) : '—';
    }

    private static function editOrSend(array $cq, string $text, array $kb): void
    {
        if (TG::edit($cq['message']['chat']['id'], $cq['message']['message_id'], $text, $kb) === null) {
            TG::send($cq['message']['chat']['id'], $text, $kb);
        }
    }

    public static function callback(array $cq, string $arg): void
    {
        $p = explode(':', $arg);
        $cmd = $p[0];
        $chat = $cq['message']['chat']['id'];
        $uid = $cq['from']['id'];
        switch ($cmd) {
            case 'home':
                State::clear($uid);
                TG::answer($cq['id']);
                self::editOrSend($cq, self::HOME, self::homeKb());
                return;
            case 'texts':
                $sec = $p[1] ?? '';
                TG::answer($cq['id']);
                if (!isset(self::SECTIONS[$sec])) {
                    $btns = [];
                    foreach (self::SECTIONS as $k => [$icon, $title, $keys]) {
                        $btns[] = UI::raw("$icon $title", "adm:texts:$k");
                    }
                    $rows = self::grid($btns);
                    $rows[] = self::back();
                    self::editOrSend($cq, "📋 <b>متن‌های ربات</b>\nبخش موردنظر را انتخاب کنید:", UI::kb($rows));
                    return;
                }
                [$icon, $title, $keys] = self::SECTIONS[$sec];
                $all = Config::data('texts');
                $btns = [];
                foreach ($keys as $k) {
                    if (isset($all[$k])) {
                        $btns[] = UI::raw((DB::textOverride($k) !== null ? '✅ ' : '') . self::short($all[$k]['desc']), "adm:text:$k:$sec");
                    }
                }
                $rows = self::grid($btns);
                $rows[] = self::back('adm:texts');
                self::editOrSend($cq, "$icon <b>متن‌های $title</b>\n✅ = ویرایش شده", UI::kb($rows));
                return;
            case 'text':
                $k = $p[1] ?? '';
                $sec = $p[2] ?? '';
                $t = Config::data('texts')[$k] ?? null;
                TG::answer($cq['id']);
                if (!$t) {
                    return;
                }
                $kb = UI::kb([[UI::raw('📋 ویرایش', "adm:tedit:$k", 'primary'), UI::raw('⛔️ پیش‌فرض', "adm:treset:$k", 'danger')], self::back($sec !== '' ? "adm:texts:$sec" : 'adm:texts')]);
                $head = '🔑 <b>' . UI::esc(self::short($t['desc'])) . "</b>\n🧩 متغیرها: " . self::placeholders($t['desc']);
                $body = UI::template($k);
                if (TG::edit($chat, $cq['message']['message_id'], $head . "\n\n" . $body, $kb) === null
                    && TG::send($chat, $head . "\n\n" . $body, $kb) === null) {
                    TG::send($chat, $head . "\n\n<pre>" . UI::esc($body) . '</pre>', $kb);
                }
                return;
            case 'tedit':
                $k = $p[1] ?? '';
                self::wait($cq, 'text', "📋 متن جدید <code>$k</code>\n🧩 " . self::placeholders(Config::data('texts')[$k]['desc'] ?? ''), ['key' => $k]);
                return;
            case 'treset':
                DB::resetText($p[1] ?? '');
                TG::answer($cq['id'], 'به پیش‌فرض برگشت', true);
                return;
            case 'btns':
                $conf = Settings::get('buttons') ?: [];
                $btns = [];
                foreach (Config::data('buttons') as $k => $b) {
                    $btns[] = UI::raw((isset($conf[$k]) ? '✅ ' : '') . self::short($b['desc']), "adm:btn:$k");
                }
                $rows = self::grid($btns);
                $rows[] = self::back();
                TG::answer($cq['id']);
                self::editOrSend($cq, "🔲 <b>دکمه‌های شیشه‌ای</b>\n✅ = ویرایش شده", UI::kb($rows));
                return;
            case 'btn':
                TG::answer($cq['id']);
                self::buttonView($chat, $p[1] ?? '', $cq);
                return;
            case 'bstyle':
                self::setButton($p[1], ['style' => $p[2] ?? 'none']);
                TG::answer($cq['id'], '✅');
                self::buttonView($chat, $p[1], $cq);
                return;
            case 'breset':
                $conf = Settings::get('buttons') ?: [];
                unset($conf[$p[1]]);
                Settings::set('buttons', $conf);
                TG::answer($cq['id'], '⛔️');
                self::buttonView($chat, $p[1], $cq);
                return;
            case 'btext':
                self::wait($cq, 'btn_text', '📋 متن جدید دکمه را بفرستید (متن ساده).', ['key' => $p[1]]);
                return;
            case 'fj':
                TG::answer($cq['id']);
                self::fjView($chat, $cq);
                return;
            case 'fjadd':
                self::wait($cq, 'fj_add', "📢 یوزرنیم، آیدی عددی یا فوروارد پیام کانال را بفرستید.\n<code>-100123456 https://t.me/+abc</code>");
                return;
            case 'fjdel':
                $ch = Settings::get('force_channels') ?: [];
                array_splice($ch, (int)($p[1] ?? -1), 1);
                Settings::set('force_channels', array_values($ch));
                TG::answer($cq['id'], '✅');
                self::fjView($chat, $cq);
                return;
            case 'set':
                TG::answer($cq['id']);
                self::settingsView($chat, $cq, (int)($p[1] ?? 1));
                return;
            case 'tog':
                Settings::set($p[1], !Settings::get($p[1]));
                TG::answer($cq['id'], Settings::get($p[1]) ? 'روشن شد' : 'خاموش شد');
                ($p[2] ?? '') === 'fj' ? self::fjView($chat, $cq) : self::settingsView($chat, $cq, 1);
                return;
            case 'val':
                $k = $p[1];
                if ($k === 'scam_extra_keywords') {
                    $cur = implode("\n", Settings::get($k) ?: []) ?: '—';
                    self::wait($cq, 'value', "📋 هر کلمه/عبارت در یک خط بفرستید (برای پاک کردن: <code>-</code>)\nفعلی:\n" . UI::esc($cur), ['key' => $k]);
                } else {
                    $label = self::NUMBERS[$k] ?? self::STRINGS[$k] ?? $k;
                    self::wait($cq, 'value', "📋 مقدار جدید «{$label}» را بفرستید.\nفعلی: <code>" . UI::esc(json_encode(Settings::get($k), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</code>', ['key' => $k]);
                }
                return;
            case 'scams':
                $pend = DB::listScams('pending', 15);
                $appr = DB::listScams('approved', 15);
                $fmt = fn($r) => '#' . $r['id'] . ' — ' . UI::esc($r['target_username'] ?: (string)$r['target_id']);
                $lines = array_merge(['🚨 <b>گزارش‌های در انتظار</b>'], $pend ? array_map($fmt, $pend) : ['—'], ['', '⛔️ <b>کلاهبرداران تایید شده</b>'], $appr ? array_map($fmt, $appr) : ['—'], ['', '💬 لغو تایید: <code>/unscam ID</code>']);
                $rows = array_map(fn($r) => [UI::raw('✅ #' . $r['id'], 'scamadm:approve:' . $r['id'], 'success'), UI::raw('❌ #' . $r['id'], 'scamadm:reject:' . $r['id'], 'danger')], array_slice($pend, 0, 8));
                $rows[] = self::back();
                TG::answer($cq['id']);
                self::editOrSend($cq, implode("\n", $lines), UI::kb($rows));
                return;
            case 'stats':
                $rate = Prices::usdtToman();
                TG::answer($cq['id']);
                self::editOrSend($cq, "📊 <b>آمار ربات</b>\n👤 کاربران: <b>" . Fmt::int(DB::countUsers()) . "</b>\n👥 گروه‌های فعال: <b>" . Fmt::int(DB::countGroups())
                    . "</b>\n🚨 گزارش در انتظار: <b>" . count(DB::listScams('pending', 1000)) . "</b>\n⛔️ کلاهبرداران ثبت شده: <b>" . count(DB::listScams('approved', 100000))
                    . "</b>\n💹 نرخ تتر: <b>" . ($rate ? Fmt::toman($rate) : '—') . '</b> تومان', UI::kb([self::back()]));
                return;
            case 'bcmenu':
                TG::answer($cq['id']);
                self::editOrSend($cq, "📢 <b>ارسال همگانی</b>\nمقصد را انتخاب کنید:", UI::kb([
                    [UI::raw('👤 کاربران', 'adm:bc:users'), UI::raw('👥 گروه‌ها', 'adm:bc:groups'), UI::raw('🌐 همه', 'adm:bc:all')],
                    [UI::raw('📩 فوروارد به کاربران', 'adm:bc:fusers'), UI::raw('📩 فوروارد به گروه‌ها', 'adm:bc:fgroups')], self::back()]));
                return;
            case 'bc':
                self::wait($cq, 'broadcast', '📢 پیام موردنظر را بفرستید (هر نوع پیامی).', ['target' => $p[1] ?? 'users']);
                return;
            case 'power':
                Settings::set('bot_enabled', !Settings::get('bot_enabled'));
                TG::answer($cq['id'], Settings::get('bot_enabled') ? '🟢' : '🔴');
                TG::api('editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $cq['message']['message_id'], 'reply_markup' => self::homeKb()]);
                return;
            case 'ban':
                self::wait($cq, 'ban', '⛔️ آیدی عددی کاربر را بفرستید (مسدود ↔ آزاد).');
                return;
            case 'pm':
                self::wait($cq, 'pm', "📨 به این شکل بفرستید:\n<code>آیدی عددی</code>\n<code>متن پیام</code>");
                return;
        }
        TG::answer($cq['id']);
    }

    private static function setButton(string $key, array $changes): void
    {
        $conf = Settings::get('buttons') ?: [];
        $item = $conf[$key] ?? [];
        foreach ($changes as $k => $v) {
            if ($v === null) {
                unset($item[$k]);
            } else {
                $item[$k] = $v;
            }
        }
        $conf[$key] = $item;
        Settings::set('buttons', $conf);
    }

    private static function buttonView(int|string $chat, string $key, ?array $cq = null): void
    {
        $def = Config::data('buttons')[$key] ?? null;
        if (!$def) {
            return;
        }
        $conf = (Settings::get('buttons') ?: [])[$key] ?? [];
        $style = array_key_exists('style', $conf) ? $conf['style'] : $def['style'];
        $text = '🔲 <b>' . UI::esc(self::short($def['desc'])) . "</b>\n📋 متن: <code>" . UI::esc(($conf['text'] ?? '') ?: $def['text']) . "</code>\n🧩 رنگ: "
            . (self::STYLES[$style ?: 'none'] ?? $style) . "\n\n⬇️ پیش‌نمایش:";
        $styles = [];
        foreach (self::STYLES as $s => $l) {
            $styles[] = UI::raw(($s === ($style ?: 'none') ? '✅ ' : '') . $l, "adm:bstyle:$key:$s", $s === 'none' ? null : $s);
        }
        $kb = UI::kb([[UI::button($key, ['cb' => 'noop'], ['title' => 'نمونه'])], [UI::raw('📋 ویرایش متن', "adm:btext:$key", 'primary')],
            $styles, [UI::raw('⛔️ پیش‌فرض', "adm:breset:$key", 'danger'), UI::raw('🏠 بازگشت', 'adm:btns')]]);
        $cq ? self::editOrSend($cq, $text, $kb) : TG::send($chat, $text, $kb);
    }

    private static function fjView(int|string $chat, ?array $cq = null): void
    {
        $ch = Settings::get('force_channels') ?: [];
        $lines = [];
        foreach ($ch as $i => $c) {
            $lines[] = ($i + 1) . '. ' . UI::esc($c['title'] ?? '') . ' — <code>' . $c['id'] . "</code>\n   " . UI::esc($c['url'] ?? '');
        }
        $rows = array_map(fn($i) => [UI::raw('❌ حذف ' . ($i + 1), "adm:fjdel:$i")], array_keys($ch));
        $rows[] = [UI::raw((Settings::get('force_join_groups') ? '✅' : '❌') . ' در گروه‌ها', 'adm:tog:force_join_groups:fj'),
            UI::raw((Settings::get('force_join_private') ? '✅' : '❌') . ' در پیوی', 'adm:tog:force_join_private:fj')];
        $rows[] = [UI::raw('👥 افزودن کانال', 'adm:fjadd', 'success'), ...self::back()];
        $text = "📢 <b>عضویت اجباری</b>\n\n" . ($lines ? implode("\n", $lines) : '— کانالی ثبت نشده —');
        $cq ? self::editOrSend($cq, $text, UI::kb($rows)) : TG::send($chat, $text, UI::kb($rows));
    }

    private static function settingsView(int|string $chat, ?array $cq = null, int $page = 1): void
    {
        if ($page === 2) {
            $rows = [];
            foreach (self::NUMBERS + self::STRINGS as $k => $l) {
                $v = Settings::get($k);
                $rows[] = [UI::raw("$l: " . (($v === null || $v === '') ? '—' : (is_scalar($v) ? $v : json_encode($v))), "adm:val:$k")];
            }
            $rows[] = [UI::raw('کلمات اضافه ضدکلاهبرداری', 'adm:val:scam_extra_keywords')];
            $rows[] = [UI::raw('⬆️ صفحه ۱: قابلیت‌ها', 'adm:set:1'), ...self::back()];
            $text = "⚙️ <b>تنظیمات کلی</b> · صفحه ۲ از ۲\nروی هر مقدار بزنید تا عوضش کنید.";
        } else {
            $btns = [];
            foreach (self::toggles() as $k => $l) {
                $btns[] = UI::raw((Settings::get($k) ? '🟢 ' : '🔴 ') . $l, "adm:tog:$k:set", Settings::get($k) ? 'success' : null);
            }
            $rows = self::grid($btns);
            $rows[] = [UI::raw('⬇️ صفحه ۲: مقادیر', 'adm:set:2'), ...self::back()];
            $text = "⚙️ <b>تنظیمات کلی</b> · صفحه ۱ از ۲\nروی هر قابلیت بزنید تا روشن/خاموش شود.";
        }
        $cq ? self::editOrSend($cq, $text, UI::kb($rows)) : TG::send($chat, $text, UI::kb($rows));
    }

    private static function messageHtml(array $m): string
    {
        $text = $m['text'] ?? $m['caption'] ?? '';
        $ents = $m['entities'] ?? $m['caption_entities'] ?? [];
        if (!$ents && preg_match('/<\/?(?:b|i|u|s|code|pre|a|blockquote|tg-emoji|tg-spoiler)\b/', $text)) {
            return $text;
        }
        return Entities::html($text, $ents);
    }

    public static function input(array $m, array $st): void
    {
        $chat = $m['chat']['id'];
        $uid = $m['from']['id'];
        $key = $st['key'] ?? '';
        $text = trim($m['text'] ?? '');
        switch ($st['action'] ?? '') {
            case 'text':
                $new = self::messageHtml($m);
                if (trim($new) === '') {
                    TG::send($chat, '⚠️ متن خالی است.');
                    return;
                }
                $preview = preg_replace('/href="\{[a-z_]+\}"/', 'href="https://t.me"', $new);
                if (TG::send($chat, "👁 پیش‌نمایش:\n\n" . UI::emojiMap($preview)) === null) {
                    TG::send($chat, '❌ متن نامعتبر است: <code>' . UI::esc((string)TG::$lastError) . '</code>');
                    return;
                }
                DB::setText($key, $new);
                State::clear($uid);
                TG::send($chat, '✅ متن ذخیره شد.', self::homeKb());
                return;
            case 'btn_text':
                self::setButton($key, ['text' => mb_substr($text, 0, 64) ?: null]);
                State::clear($uid);
                TG::send($chat, '✅ ذخیره شد.');
                self::buttonView($chat, $key);
                return;
            case 'btn_emoji':
                $id = null;
                if ($text !== '-') {
                    foreach ($m['entities'] ?? [] as $e) {
                        if ($e['type'] === 'custom_emoji') {
                            $id = $e['custom_emoji_id'];
                            break;
                        }
                    }
                    if (!$id && ctype_digit($text)) {
                        $id = $text;
                    }
                    if (!$id && !empty($m['sticker']['custom_emoji_id'])) {
                        $id = $m['sticker']['custom_emoji_id'];
                    }
                    if (!$id) {
                        TG::send($chat, '⚠️ ایموجی پریمیوم پیدا نشد. دوباره بفرستید.');
                        return;
                    }
                }
                self::setButton($key, ['emoji_id' => $id]);
                State::clear($uid);
                TG::send($chat, '✅ ذخیره شد.');
                self::buttonView($chat, $key);
                return;
            case 'fj_add':
                self::addChannel($m);
                return;
            case 'value':
                if ($key === 'scam_extra_keywords') {
                    $v = $text === '-' ? [] : array_values(array_filter(array_map('trim', explode("\n", $text))));
                } elseif (isset(self::NUMBERS[$key])) {
                    $n = str_replace(',', '', Fmt::en($text));
                    if (!is_numeric($n) || (float)$n < 0) {
                        TG::send($chat, '⚠️ عدد معتبر بفرستید.');
                        return;
                    }
                    $v = $key === 'star_usd' ? (float)$n : (int)$n;
                } else {
                    $v = $text;
                }
                Settings::set($key, $v);
                if ($key === 'usdt_toman_manual') {
                    Cache::forget('usdt_toman');
                }
                State::clear($uid);
                TG::send($chat, '✅ ذخیره شد.');
                self::settingsView($chat);
                return;
            case 'broadcast':
                State::clear($uid);
                $target = $st['target'] ?? 'users';
                $fwd = str_starts_with($target, 'f');
                $target = ltrim($target, 'f');
                $ids = [];
                if (in_array($target, ['users', 'all'], true)) {
                    $ids = array_merge($ids, DB::userIds());
                }
                if (in_array($target, ['groups', 'all'], true)) {
                    $ids = array_merge($ids, array_map(fn($g) => (int)$g['id'], DB::activeGroups()));
                }
                $report = TG::send($chat, '🔍 در حال ارسال...');
                $ok = $fail = 0;
                foreach ($ids as $id) {
                    $r = TG::api($fwd ? 'forwardMessage' : 'copyMessage', ['chat_id' => $id, 'from_chat_id' => $chat, 'message_id' => $m['message_id']]);
                    $r !== null ? $ok++ : $fail++;
                    usleep(40000);
                }
                $msg = "✅ ارسال همگانی تمام شد.\nموفق: $ok\nناموفق: $fail";
                $report ? TG::edit($chat, $report['message_id'], $msg) : TG::send($chat, $msg);
                return;
            case 'ban':
                $raw = Fmt::en($text);
                if (!ctype_digit($raw)) {
                    TG::send($chat, '⚠️ آیدی عددی معتبر بفرستید.');
                    return;
                }
                $id = (int)$raw;
                $ban = !DB::isBanned($id);
                DB::setBanned($id, $ban);
                State::clear($uid);
                TG::send($chat, ($ban ? '⛔️ کاربر مسدود شد' : '✅ کاربر آزاد شد') . ": <code>$id</code>", self::homeKb());
                return;
            case 'pm':
                [$first, $rest] = array_pad(explode("\n", $text, 2), 2, '');
                $id = Fmt::en(trim($first));
                if (!ctype_digit($id) || trim($rest) === '') {
                    TG::send($chat, '⚠️ ورودی نامعتبر.');
                    return;
                }
                $ok = TG::send((int)$id, UI::t('admin_message', ['text' => UI::esc(trim($rest))])) !== null;
                State::clear($uid);
                TG::send($chat, $ok ? '✅ پیام ارسال شد.' : '❌ ارسال نشد: <code>' . UI::esc((string)TG::$lastError) . '</code>', self::homeKb());
                return;
        }
        State::clear($uid);
    }

    private static function addChannel(array $m): void
    {
        $chat = $m['chat']['id'];
        $ref = null;
        $invite = null;
        if (!empty($m['forward_origin']['chat']['id'])) {
            $ref = $m['forward_origin']['chat']['id'];
        } else {
            $parts = preg_split('/\s+/', trim($m['text'] ?? ''));
            if ($parts && $parts[0] !== '') {
                $first = str_replace(['https://t.me/', 't.me/'], '@', $parts[0]);
                $ref = preg_match('/^-?\d+$/', $first) ? (int)$first : (str_starts_with($first, '@') ? $first : '@' . $first);
            }
            if (isset($parts[1]) && str_contains($parts[1], 't.me/')) {
                $invite = $parts[1];
            }
        }
        if ($ref === null) {
            TG::send($chat, '⚠️ ورودی نامعتبر.');
            return;
        }
        $c = TG::api('getChat', ['chat_id' => $ref]);
        if (!$c) {
            TG::send($chat, "❌ کانال پیدا نشد. ربات را در کانال عضو و ادمین کنید.\n<code>" . UI::esc((string)TG::$lastError) . '</code>');
            return;
        }
        $me = TG::api('getChatMember', ['chat_id' => $c['id'], 'user_id' => TG::botId()]);
        $isAdmin = in_array($me['status'] ?? '', ['administrator', 'creator'], true);
        $url = $invite ?: (!empty($c['username']) ? 'https://t.me/' . $c['username'] : ($c['invite_link'] ?? null));
        if (!$url && $isAdmin) {
            $url = TG::api('createChatInviteLink', ['chat_id' => $c['id'], 'name' => 'force-join'])['invite_link'] ?? null;
        }
        if (!$url) {
            TG::send($chat, '⚠️ لینک دعوت پیدا نشد. آیدی و لینک را با هم بفرستید: <code>-100123 https://t.me/+abc</code>');
            return;
        }
        $list = array_values(array_filter(Settings::get('force_channels') ?: [], fn($x) => $x['id'] != $c['id']));
        $list[] = ['id' => $c['id'], 'title' => $c['title'] ?? ($c['username'] ?? (string)$c['id']), 'url' => $url];
        Settings::set('force_channels', $list);
        State::clear($m['from']['id']);
        TG::send($chat, '✅ کانال «' . UI::esc($c['title'] ?? '') . '» اضافه شد.' . ($isAdmin ? '' : "\n⚠️ ربات در این کانال ادمین نیست! حتما ادمینش کنید."));
        self::fjView($chat);
    }

    public static function unscam(array $m, string $args): void
    {
        if (!ctype_digit(trim($args))) {
            TG::send($m['chat']['id'], '/unscam ID');
            return;
        }
        DB::updateScam((int)$args, ['status' => 'revoked']);
        TG::send($m['chat']['id'], '✅ لغو شد.');
    }

    public static function addBalance(array $m, string $args): void
    {
        $p = preg_split('/\s+/', trim($args));
        if (count($p) !== 2 || !ctype_digit($p[0]) || !is_numeric($p[1])) {
            TG::send($m['chat']['id'], '/addbalance USER_ID AMOUNT');
            return;
        }
        TG::send($m['chat']['id'], '✅ موجودی جدید: ' . Fmt::num(DB::addBalance((int)$p[0], (float)$p[1])));
    }

    public static function setRate(array $m, string $args): void
    {
        $raw = trim(str_replace(',', '', Fmt::en($args)));
        if (!is_numeric($raw)) {
            TG::send($m['chat']['id'], '/setrate 0  (0 = خودکار)');
            return;
        }
        Settings::set('usdt_toman_manual', (float)$raw);
        Cache::forget('usdt_toman');
        TG::send($m['chat']['id'], '✅ ذخیره شد.');
    }
}
