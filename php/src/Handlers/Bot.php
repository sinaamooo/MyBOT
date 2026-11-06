<?php

final class Bot
{
    const COMMANDS = [
        'start' => ['Start', 'start'], 'help' => ['Start', 'help'], 'gift' => ['GiftsH', 'cmd'], 'account' => ['Tools', 'account'], 'emoji' => ['Tools', 'emoji'],
        'panel' => ['Admin', 'panel'], 'check' => ['Scam', 'check'], 'scam' => ['Scam', 'cmd'], 'user' => ['Usernames', 'cmd'],
    ];
    const CALLBACKS = [
        'fj:' => ['Bot', 'forceJoinCheck'], 'unmute:' => ['Bot', 'unmuteCb'], 'tr:' => ['Tools', 'translateCb'], 'gs:' => ['Groups', 'toggle'], 'ul:' => ['Start', 'langCb'], 'md:' => ['Moderation', 'cb'],
        'scam:' => ['Scam', 'buttons'], 'scamadm:' => ['Scam', 'decision'], 'adm:' => ['Admin', 'callback'], 'wl:' => ['Tools', 'walletButton'],
        'tt:' => ['Tools', 'transfers'], 'tp:' => ['Tools', 'transfers'], 'is:' => ['Extras', 'story'],
        'ih:' => ['Extras', 'highlights'], 'hi:' => ['Extras', 'highlightItems'], 'gd:' => ['Games', 'doozCb'], 'gn:' => ['Games', 'mineCb'], 'gj:' => ['Diamonds', 'jailCb'], 'gb:' => ['Diamonds', 'accountCb'],
    ];

    public static function handle(array $u): void
    {
        $c = $u['message']['chat']['id'] ?? $u['edited_message']['chat']['id'] ?? $u['callback_query']['message']['chat']['id'] ?? $u['my_chat_member']['chat']['id'] ?? $u['chat_member']['chat']['id'] ?? 0;
        UI::$lang = 'fa';
        $t0 = microtime(true);
        try {
            if ($c < 0 && ($gc = $u['message']['chat'] ?? $u['callback_query']['message']['chat'] ?? null)) {
                Lang::adopt($gc);
            }
            Lang::use($c);
            if (isset($u['message'])) {
                self::message($u['message']);
            } elseif (isset($u['edited_message'])) {
                self::edited($u['edited_message']);
            } elseif (isset($u['callback_query'])) {
                self::callback($u['callback_query']);
            } elseif (isset($u['my_chat_member'])) {
                self::myChatMember($u['my_chat_member']);
            } elseif (isset($u['chat_member'])) {
                self::chatMember($u['chat_member']);
            }
        } catch (Throwable $e) {
            error_log('update failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
        $ms = (int)round((microtime(true) - $t0) * 1000);
        if ($ms >= 1500) {
            $txt = (string)($u['message']['text'] ?? $u['message']['caption'] ?? '');
            $what = isset($u['callback_query']) ? 'cb:' . strtok((string)($u['callback_query']['data'] ?? ''), ':') : (str_starts_with($txt, '/') ? strtok($txt, " @\n") : (isset($u['message']) ? 'text' : 'other'));
            Perf::line('slow ' . ($c < 0 ? 'group' : 'private') . ' ' . $what, $ms);
        }
    }

    public static function isPrivate(array $m): bool
    {
        return ($m['chat']['type'] ?? '') === 'private';
    }

    public static function replyParams(array $m): array
    {
        return ['message_id' => $m['message_id'], 'allow_sending_without_reply' => true];
    }

    public static function reply(array $m, string $text, $markup = false, ?string $photo = null): void
    {
        if ($markup === false) {
            $markup = UI::footer();
        }
        $extra = ['__quick' => true];
        if (!self::isPrivate($m) && isset($m['message_id'])) {
            $extra['reply_parameters'] = self::replyParams($m);
        }
        $chat = $m['chat']['id'];
        if ($photo !== null && UI::textLen($text) <= 1000) {
            TG::photo($chat, $photo, $text, $markup, $extra);
            return;
        }
        TG::send($chat, $text, $markup, $extra);
    }

    public static function ask(array $m, string $cmd, string $key, string $placeholder): void
    {
        Cache::set('ask:' . $m['chat']['id'] . ':' . ($m['from']['id'] ?? 0), $cmd, 600);
        $extra = ['__quick' => true];
        if (!self::isPrivate($m) && isset($m['message_id'])) {
            $extra['reply_parameters'] = self::replyParams($m);
        }
        TG::send($m['chat']['id'], UI::t($key), ['force_reply' => true, 'selective' => true, 'input_field_placeholder' => $placeholder], $extra);
    }

    private static function answered(array $m, string $text): bool
    {
        $r = $m['reply_to_message'] ?? null;
        if (!$r || $text === '' && empty($m['entities']) && empty($m['sticker']) || (int)($r['from']['id'] ?? 0) !== TG::botId()) {
            return false;
        }
        $k = 'ask:' . $m['chat']['id'] . ':' . ($m['from']['id'] ?? 0);
        $cmd = Cache::get($k);
        if (!$cmd) {
            return false;
        }
        if ($cmd === 'gift' && !Gifts::findLinks($text) && !preg_match('~^(?:@?[A-Za-z][A-Za-z0-9_]{3,31}|(?:https?://)?t\.me/[A-Za-z0-9_]{4,32}/?|-?\d{5,15}|من|me)$~iu', trim($text))) {
            return false;
        }
        if ($cmd === 'emoji' && empty($m['entities']) && empty($m['caption_entities']) && empty($m['sticker'])) {
            return false;
        }
        Cache::forget($k);
        $m['__prompt'] = (int)($r['message_id'] ?? 0);
        unset($m['reply_to_message']);
        if ($cmd === 'gift') {
            GiftsH::cmd($m, $text);
        } elseif ($cmd === 'gfj') {
            Groups::addChannel($m, $text);
        } else {
            Tools::emoji($m);
        }
        return true;
    }

    public static function send(array $m, string $text, $markup = false, ?string $photo = null, bool $reply = true, ?string $preview = null): ?array
    {
        if ($markup === false) {
            $markup = UI::footer();
        }
        $extra = [];
        if ($reply && !self::isPrivate($m) && isset($m['message_id'])) {
            $extra['reply_parameters'] = self::replyParams($m);
        }
        $chat = $m['chat']['id'];
        if ($photo !== null) {
            if (UI::textLen($text) > 1000) {
                $sent = TG::photo($chat, $photo, null, null, $extra);
                TG::send($chat, $text, $markup);
                return $sent;
            }
            return TG::photo($chat, $photo, $text, $markup, $extra);
        }
        if ($preview) {
            $extra['link_preview_options'] = ['url' => $preview, 'prefer_large_media' => true, 'show_above_text' => true];
        }
        return TG::send($chat, $text, $markup, $extra);
    }

    public static function deleteLater(?array $sent, int $seconds): void
    {
        if ($sent && isset($sent['message_id'])) {
            DB::q('INSERT INTO delete_queue(chat_id, message_id, at) VALUES(?, ?, ?)', [$sent['chat']['id'], $sent['message_id'], time() + $seconds]);
        }
    }

    public static function deleteMsg(?array $m): void
    {
        if ($m && isset($m['message_id'])) {
            TG::delete($m['chat']['id'], $m['message_id']);
        }
    }

    public static function chatAdmins(int $chat): ?array
    {
        $v = Cache::get("admins:$chat", $hit);
        if ($hit) {
            return $v;
        }
        $r = TG::api('getChatAdministrators', ['chat_id' => $chat]);
        if (!is_array($r)) {
            return null;
        }
        $ids = array_values(array_filter(array_map(fn($a) => (int)($a['user']['id'] ?? 0), $r)));
        Cache::set("admins:$chat", $ids, 600);
        return $ids;
    }

    public static function isChatAdmin(int $chat, int $user): bool
    {
        if (Config::isAdmin($user)) {
            return true;
        }
        if ($chat < 0 && ($ids = self::chatAdmins($chat)) !== null) {
            return in_array($user, $ids, true);
        }
        return Cache::remember("adm:$chat:$user", 300, function () use ($chat, $user) {
            $m = TG::api('getChatMember', ['chat_id' => $chat, 'user_id' => $user]);
            return in_array($m['status'] ?? '', ['administrator', 'creator'], true);
        });
    }

    public static function isMember($channel, int $user): bool
    {
        $k = "mem:$channel:$user";
        $v = Cache::get($k, $hit);
        if ($hit) {
            return (bool)$v;
        }
        return self::memberSave($channel, $user, TG::api('getChatMember', ['chat_id' => $channel, 'user_id' => $user]));
    }

    private static function memberOk(array $m): bool
    {
        $st = $m['status'] ?? 'left';
        return !in_array($st, ['left', 'kicked'], true) && !($st === 'restricted' && empty($m['is_member']));
    }

    private static function memberSave($channel, int $user, ?array $m): bool
    {
        if ($m === null) {
            if (!Cache::throttled("badch:$channel", 600)) {
                error_log("cannot check membership in $channel: " . TG::$lastError);
            }
            return true;
        }
        $ok = self::memberOk($m);
        Cache::set("mem:$channel:$user", $ok, $ok ? 21600 : 20);
        return $ok;
    }

    public static function groupChannels(int $chat): array
    {
        if ($chat >= 0 || !Settings::on($chat, 'force_join_groups')) {
            return [];
        }
        $out = [];
        foreach (array_merge(Settings::get('force_channels') ?: [], DB::chatSettings($chat)['fj_channels'] ?? []) as $ch) {
            $out[(string)$ch['id']] = $ch;
        }
        return array_values($out);
    }

    public static function missing(int $user, ?array $channels = null, int $chat = 0): array
    {
        $channels = array_values($channels ?? (Settings::get('force_channels') ?: []));
        $ok = [];
        $calls = [];
        foreach ($channels as $i => $ch) {
            $v = Cache::get("mem:{$ch['id']}:$user", $hit);
            if ($hit) {
                $ok[$i] = (bool)$v;
            } else {
                $calls[$i] = ['getChatMember', ['chat_id' => $ch['id'], 'user_id' => $user]];
            }
        }
        if (count($calls) === 1 && !$chat) {
            $i = array_key_first($calls);
            $ok[$i] = self::memberSave($channels[$i]['id'], $user, TG::api(...$calls[$i]));
        } elseif ($calls) {
            if ($chat) {
                Cache::get("adm:$chat:$user", $hit);
                if (!$hit) {
                    $calls['adm'] = ['getChatMember', ['chat_id' => $chat, 'user_id' => $user]];
                }
            }
            foreach (TG::multi($calls) as $i => $r) {
                if ($i === 'adm') {
                    if ($r !== null) {
                        Cache::set("adm:$chat:$user", in_array($r['status'] ?? '', ['administrator', 'creator'], true), 300);
                    }
                    continue;
                }
                $ok[$i] = self::memberSave($channels[$i]['id'], $user, $r);
            }
        }
        $out = [];
        foreach ($channels as $i => $ch) {
            if (!($ok[$i] ?? true)) {
                $out[] = $ch;
            }
        }
        return $out;
    }

    private static function fjWarn(array $m, array $missing): void
    {
        $chat = (int)$m['chat']['id'];
        $u = $m['from'];
        $uid = (int)$u['id'];
        $calls = ['del' => ['deleteMessage', ['chat_id' => $chat, 'message_id' => $m['message_id']]]];
        $old = (int)(Cache::get("fjw:$chat:$uid") ?? 0);
        if (Cache::throttled("fjt:$chat:$uid", 20) && $old) {
            TG::multi($calls);
            return;
        }
        if ($old) {
            $calls['old'] = ['deleteMessage', ['chat_id' => $chat, 'message_id' => $old]];
        }
        $p = ['chat_id' => $chat, 'text' => UI::t('force_join_group', ['mention' => UI::mention($u)]), 'reply_markup' => self::forceJoinKb($uid, $missing)];
        $calls['warn'] = ['sendMessage', TG::prep('sendMessage', $p)];
        $sent = TG::multi($calls)['warn'] ?? TG::api('sendMessage', $p);
        if (!isset($sent['message_id'])) {
            return;
        }
        Cache::set("fjw:$chat:$uid", (int)$sent['message_id'], 172800);
        $g = Cache::get("fjwu:$uid") ?: [];
        $g[(string)$chat] = 1;
        Cache::set("fjwu:$uid", $g, 172800);
    }

    private static function fjDone(int $chat, array $user, int $mid, array $chs): void
    {
        $uid = (int)$user['id'];
        Cache::forget("fjw:$chat:$uid");
        $names = array_map(fn($c) => !empty($c['url']) ? '<a href="' . UI::attr($c['url']) . '">' . UI::esc($c['title'] ?? '') . '</a>' : UI::esc($c['title'] ?? ''), $chs);
        TG::edit($chat, $mid, UI::t('force_join_done', ['mention' => UI::mention($user), 'channels' => implode(UI::$lang === 'fa' ? '، ' : ', ', $names)]), null);
        self::deleteLater(['chat' => ['id' => $chat], 'message_id' => $mid], 30);
    }

    private static function channelMember(array $e): void
    {
        $user = $e['new_chat_member']['user'] ?? null;
        if (!$user || !empty($user['is_bot'])) {
            return;
        }
        $uid = (int)$user['id'];
        $ok = self::memberOk($e['new_chat_member']);
        $ch = $e['chat'];
        foreach (array_filter([(string)$ch['id'], !empty($ch['username']) ? '@' . $ch['username'] : null]) as $id) {
            Cache::set("mem:$id:$uid", $ok, $ok ? 21600 : 20);
        }
        $groups = $ok ? (Cache::get("fjwu:$uid") ?: []) : [];
        if (!$groups) {
            return;
        }
        foreach (array_keys($groups) as $g) {
            $g = (int)$g;
            $mid = (int)(Cache::get("fjw:$g:$uid") ?? 0);
            $chs = $mid ? self::groupChannels($g) : [];
            if (!$chs) {
                unset($groups[$g]);
                continue;
            }
            Lang::use($g);
            if ($missing = self::missing($uid, $chs)) {
                TG::api('editMessageReplyMarkup', ['chat_id' => $g, 'message_id' => $mid, 'reply_markup' => self::forceJoinKb($uid, $missing)]);
                continue;
            }
            self::fjDone($g, $user, $mid, $chs);
            unset($groups[$g]);
        }
        $groups ? Cache::set("fjwu:$uid", $groups, 172800) : Cache::forget("fjwu:$uid");
    }

    public static function forceJoinKb(int $user, array $channels): array
    {
        $rows = array_map(fn($c) => [UI::button('btn_join', ['url' => $c['url']], ['title' => $c['title'] ?? UI::l('کانال')])], $channels);
        $rows[] = [UI::button('btn_check_join', ['cb' => "fj:$user"])];
        return UI::kb($rows);
    }

    public static function mute(int $chat, int $user, int $minutes): bool
    {
        return TG::api('restrictChatMember', ['chat_id' => $chat, 'user_id' => $user, 'permissions' => ['can_send_messages' => false],
            'use_independent_chat_permissions' => true, 'until_date' => $minutes ? time() + $minutes * 60 : 0]) !== null;
    }

    public static function unmute(int $chat, int $user): bool
    {
        $c = TG::api('getChat', ['chat_id' => $chat]);
        $perms = $c['permissions'] ?? array_fill_keys(['can_send_messages', 'can_send_audios', 'can_send_documents', 'can_send_photos', 'can_send_videos',
            'can_send_video_notes', 'can_send_voice_notes', 'can_send_polls', 'can_send_other_messages', 'can_add_web_page_previews'], true);
        return TG::api('restrictChatMember', ['chat_id' => $chat, 'user_id' => $user, 'permissions' => $perms, 'use_independent_chat_permissions' => true]) !== null;
    }

    private static function unmuteKb(int $user, string $kind): array
    {
        return UI::kb([[UI::button('btn_unmute', ['cb' => "unmute:$user:$kind"])]]);
    }

    public static function handleSelf(int $chat, array $user, int $score): bool
    {
        if ($score < Guard::SELF_THRESHOLD) {
            return false;
        }
        Guard::selfReset($chat, $user['id']);
        if (DB::addStrike($chat, $user['id'], 'self') <= 1) {
            self::deleteLater(TG::send($chat, UI::t('self_warn', ['mention' => UI::mention($user)])), 300);
            return false;
        }
        $min = (int)(Settings::get('self_mute_minutes') ?? 0);
        if (self::mute($chat, $user['id'], $min)) {
            DB::resetStrikes($chat, $user['id'], 'self');
            TG::send($chat, UI::t('self_muted', ['mention' => UI::mention($user), 'duration' => Fmt::duration($min)]), self::unmuteKb($user['id'], 'self'));
            return true;
        }
        return false;
    }

    private static function track(array $m): void
    {
        $f = $m['from'] ?? null;
        if (!$f || !empty($f['is_bot'])) {
            return;
        }
        if (self::isPrivate($m)) {
            DB::touchUser($f, false, true);
        } else {
            DB::seenChat($m['chat']);
        }
    }

    private static function forceJoinPrivate(array $m): bool
    {
        $u = $m['from'] ?? null;
        if (!$u || !Settings::get('force_channels') || !Settings::get('force_join_private') || Config::isAdmin($u['id'])) {
            return true;
        }
        $missing = self::missing($u['id']);
        if (!$missing) {
            return true;
        }
        TG::send($m['chat']['id'], UI::t('force_join_private', ['name' => UI::esc($u['first_name'] ?? '')]), self::forceJoinKb($u['id'], $missing));
        return false;
    }

    private static function guard(array $m): bool
    {
        $u = $m['from'] ?? null;
        if (!$u || !empty($u['is_bot']) || isset($m['sender_chat']) || !empty($m['is_automatic_forward']) || isset($m['new_chat_members']) || isset($m['left_chat_member'])) {
            return true;
        }
        $chat = (int)$m['chat']['id'];
        $adm = null;
        $admin = function () use (&$adm, $chat, $u) {
            return $adm ??= self::isChatAdmin($chat, $u['id']);
        };
        if (Config::isAdmin($u['id']) || Moderation::isVip($chat, (int)$u['id'])) {
            return true;
        }
        $text = $m['text'] ?? $m['caption'] ?? '';
        if (Settings::on($chat, 'anti_tabchi')) {
            [$score] = Guard::tabchi($m);
            if ($score >= Guard::TABCHI_THRESHOLD && !$admin()) {
                $min = (int)(Settings::get('tabchi_mute_minutes') ?? 0);
                self::deleteMsg($m);
                if (self::mute($chat, $u['id'], $min) && !Cache::throttled("$chat:{$u['id']}:tabchi", 60)) {
                    TG::send($chat, UI::t('tabchi_muted', ['mention' => UI::mention($u), 'duration' => Fmt::duration($min)]), self::unmuteKb($u['id'], 'tabchi'));
                }
                return false;
            }
        }
        $sc = DB::findScammer($u['id'], $u['username'] ?? null);
        if ($sc && !$admin()) {
            if (Settings::get('auto_ban_scammers')) {
                TG::api('banChatMember', ['chat_id' => $chat, 'user_id' => $u['id']]);
            }
            if (!Cache::throttled("$chat:{$u['id']}:scammer", 3600)) {
                self::send($m, UI::t('scam_known', ['mention' => UI::mention($u), 'reason' => UI::esc($sc['reason'] ?: '—')]), null);
            }
        }
        if ($fc = self::groupChannels($chat)) {
            $missing = self::missing($u['id'], $fc, $chat);
            if ($missing && !$admin()) {
                self::fjWarn($m, $missing);
                return false;
            }
        }
        if (Settings::on($chat, 'anti_self')) {
            $delta = null;
            $rt = $m['reply_to_message'] ?? null;
            if ($rt && isset($rt['from']['id'], $rt['date']) && $rt['from']['id'] !== $u['id']) {
                $delta = $m['date'] - $rt['date'];
            }
            $score = Guard::selfOnMessage($chat, $u, $text, $m['entities'] ?? $m['caption_entities'] ?? null, $delta);
            if ($score >= Guard::SELF_THRESHOLD && $admin()) {
                Guard::selfReset($chat, $u['id']);
            } elseif (self::handleSelf($chat, $u, $score)) {
                return false;
            }
        }
        if ($text !== '' && Settings::on($chat, 'anti_scam') && Guard::scammy($text) && !$admin()) {
            if (!Cache::throttled("$chat:{$u['id']}:scamwarn", 600) && !Cache::throttled("$chat:scamwarn", 30)) {
                self::deleteLater(self::send($m, UI::t('scam_keyword_warn', ['mention' => UI::mention($u)]), null), 600);
            }
        }
        return true;
    }

    private static function access(array $u, ?array $m, ?array $cq): bool
    {
        if (Config::isAdmin($u['id'])) {
            return true;
        }
        $priv = $m ? self::isPrivate($m) : (($cq['message']['chat']['type'] ?? '') === 'private');
        foreach ([[DB::isBanned($u['id']), 'banned', 3600], [!Settings::get('bot_enabled'), 'bot_off', 600]] as [$cond, $key, $ttl]) {
            if (!$cond) {
                continue;
            }
            if ($cq) {
                TG::answer($cq['id'], UI::plain($key), true);
            } elseif ($priv && !Cache::throttled("{$u['id']}:$key", $ttl)) {
                TG::send($m['chat']['id'], UI::t($key));
            }
            return false;
        }
        return true;
    }

    private static function message(array $m): void
    {
        self::track($m);
        $priv = self::isPrivate($m);
        if ($priv && !self::forceJoinPrivate($m)) {
            return;
        }
        if (!$priv && in_array($m['chat']['type'] ?? '', ['group', 'supergroup'], true) && !self::guard($m)) {
            return;
        }
        if (isset($m['from']) && !self::access($m['from'], $m, null)) {
            return;
        }
        if (isset($m['new_chat_members'])) {
            foreach ($m['new_chat_members'] as $nu) {
                self::onJoin((int)$m['chat']['id'], $nu);
            }
            return;
        }
        $text = trim($m['text'] ?? $m['caption'] ?? '');
        $uid = $m['from']['id'] ?? 0;
        if ($priv && $uid && ($st = State::get($uid))) {
            if (($st['mode'] ?? '') === 'admin' && Config::isAdmin($uid)) {
                if (preg_match('/^\/cancel\b/i', $text)) {
                    State::clear($uid);
                    TG::send($m['chat']['id'], '❌ لغو شد.', Admin::homeKb());
                    return;
                }
                if (!preg_match('/^\/(panel|admin|پنل)\b/iu', $text)) {
                    Admin::input($m, $st);
                    return;
                }
            } elseif (($st['mode'] ?? '') === 'scam') {
                Scam::collect($m, $st);
                return;
            }
        }
        if ($priv && Config::isAdmin($uid) && preg_match('~(?:t\.me|telegram\.me)/(?:addemoji|addstickers)/([A-Za-z0-9_]{1,64})~i', $text, $pk)) {
            Admin::emojiPack($m, $pk[1]);
            return;
        }
        if ($priv && Config::isAdmin($uid) && !State::get($uid) && !empty($m['entities']) && Admin::emojiPairs($m)) {
            return;
        }
        if (!$priv && $uid && Groups::pending($m, $text)) {
            return;
        }
        if ($uid && ($text === '' || $text[0] !== '/') && isset($m['reply_to_message']) && self::answered($m, $text)) {
            return;
        }
        if ($text !== '' && $text[0] === '/' && preg_match('/^\/([^\s@]+)(?:@(\S+))?(?:\s+(.*))?$/su', $text, $mm)) {
            $cmd = mb_strtolower($mm[1]);
            $target = $mm[2] ?? '';
            if ($target !== '' && strcasecmp($target, TG::username()) !== 0) {
                return;
            }
            $args = trim($mm[3] ?? '');
            if (isset(self::COMMANDS[$cmd])) {
                [$cls, $fn] = self::COMMANDS[$cmd];
                if ($cls === 'Admin' && $fn === 'panel' && !$priv) {
                    [$cls, $fn] = ['Groups', 'settings'];
                }
                if ($cls === 'Admin' && !(Config::isAdmin($uid) && $priv)) {
                    return;
                }
                $cls::$fn($m, $args);
            }
            return;
        }
        TextRouter::route($m);
    }

    private static function edited(array $m): void
    {
        $u = $m['from'] ?? null;
        if (!in_array($m['chat']['type'] ?? '', ['group', 'supergroup'], true) || !$u || !empty($u['is_bot']) || isset($m['sender_chat']) || empty($m['edit_date'])) {
            return;
        }
        $chat = (int)$m['chat']['id'];
        if (!Settings::on($chat, 'anti_self') || Config::isAdmin($u['id']) || Moderation::isVip($chat, (int)$u['id'])) {
            return;
        }
        $score = Guard::selfOnEdit($chat, $u['id'], $m['edit_date'] - $m['date']);
        if ($score >= Guard::SELF_THRESHOLD && self::isChatAdmin($chat, $u['id'])) {
            Guard::selfReset($chat, $u['id']);
            return;
        }
        self::handleSelf($chat, $u, $score);
    }

    private static function callback(array $cq): void
    {
        $u = $cq['from'];
        DB::touchUser($u);
        $data = (string)($cq['data'] ?? '');
        $msg = $cq['message'] ?? null;
        if ($msg && self::isPrivate($msg) && !str_starts_with($data, 'fj:') && !Config::isAdmin($u['id']) && Settings::get('force_channels') && Settings::get('force_join_private')) {
            if (self::missing($u['id'])) {
                TG::answer($cq['id'], UI::plain('force_join_not_yet'), true);
                return;
            }
        }
        if (!self::access($u, null, $cq)) {
            return;
        }
        if ($data === 'help') {
            TG::answer($cq['id']);
            TG::send($msg['chat']['id'], UI::t('help'), UI::footer());
            return;
        }
        foreach (self::CALLBACKS as $prefix => [$cls, $fn]) {
            if (str_starts_with($data, $prefix)) {
                if ($cls === 'Admin' && !Config::isAdmin($u['id'])) {
                    TG::answer($cq['id'], '⛔️', true);
                    return;
                }
                $cls::$fn($cq, substr($data, strlen($prefix)));
                return;
            }
        }
        TG::answer($cq['id']);
    }

    public static function forceJoinCheck(array $cq, string $arg): void
    {
        $owner = (int)$arg;
        if ($cq['from']['id'] !== $owner) {
            TG::answer($cq['id'], UI::plain('force_join_not_yours'), true);
            return;
        }
        $msg = $cq['message'];
        $gc = (int)($msg['chat']['id'] ?? 0);
        $chs = $gc < 0 ? self::groupChannels($gc) : (Settings::get('force_channels') ?: []);
        foreach ($chs as $ch) {
            Cache::forget("mem:{$ch['id']}:$owner");
        }
        if ($missing = self::missing($owner, $chs)) {
            TG::answer($cq['id'], UI::plain('force_join_not_yet'), true);
            if (count($missing) < count($chs)) {
                TG::api('editMessageReplyMarkup', ['chat_id' => $gc, 'message_id' => $msg['message_id'], 'reply_markup' => self::forceJoinKb($owner, $missing)]);
            }
            return;
        }
        TG::answer($cq['id'], UI::plain('force_join_ok'));
        if (self::isPrivate($msg)) {
            self::deleteMsg($msg);
            Start::welcome($gc, $cq['from']);
            return;
        }
        self::fjDone($gc, $cq['from'], (int)$msg['message_id'], $chs);
        $g = Cache::get("fjwu:$owner") ?: [];
        unset($g[(string)$gc]);
        $g ? Cache::set("fjwu:$owner", $g, 172800) : Cache::forget("fjwu:$owner");
    }

    public static function unmuteCb(array $cq, string $arg): void
    {
        [$uid] = explode(':', $arg);
        $chat = (int)$cq['message']['chat']['id'];
        if (!self::isChatAdmin($chat, $cq['from']['id'])) {
            TG::answer($cq['id'], UI::plain('only_group_admins'), true);
            return;
        }
        $uid = (int)$uid;
        if (!self::unmute($chat, $uid)) {
            TG::answer($cq['id'], '⚠️', true);
            return;
        }
        DB::resetStrikes($chat, $uid);
        Guard::selfReset($chat, $uid);
        TG::answer($cq['id'], '✅');
        $mem = TG::api('getChatMember', ['chat_id' => $chat, 'user_id' => $uid]);
        $mention = $mem ? UI::mention($mem['user']) : "<code>$uid</code>";
        TG::edit($chat, $cq['message']['message_id'], UI::t('unmuted', ['mention' => $mention]));
    }

    private static function myChatMember(array $e): void
    {
        $chat = $e['chat'];
        $st = $e['new_chat_member']['status'] ?? '';
        $old = $e['old_chat_member']['status'] ?? '';
        if (!in_array($chat['type'] ?? '', ['group', 'supergroup', 'channel'], true)) {
            return;
        }
        $active = in_array($st, ['member', 'administrator'], true);
        DB::upsertChat($chat, $active);
        if ($chat['type'] === 'channel' || !$active) {
            return;
        }
        $key = null;
        if ($st === 'administrator' && $old !== 'administrator') {
            $key = 'bot_promoted';
        } elseif (in_array($old, ['left', 'kicked'], true)) {
            $key = 'bot_added';
        }
        if (!$key) {
            return;
        }
        $by = (int)($e['from']['id'] ?? 0);
        if ($by > 0 && empty($e['from']['is_bot']) && ($key === 'bot_added' || Lang::owner((int)$chat['id']) <= 0)) {
            Lang::own((int)$chat['id'], $by);
        }
        Lang::use($chat['id']);
        TG::send($chat['id'], UI::t($key), UI::footer());
    }

    private static function chatMember(array $e): void
    {
        if (($e['chat']['type'] ?? '') === 'channel') {
            self::channelMember($e);
            return;
        }
        $old = $e['old_chat_member']['status'] ?? '';
        $new = $e['new_chat_member']['status'] ?? '';
        $user = $e['new_chat_member']['user'];
        if (in_array($old, ['left', 'kicked'], true) && in_array($new, ['member', 'restricted'], true)) {
            self::onJoin((int)$e['chat']['id'], $user);
        }
        Cache::forget("adm:{$e['chat']['id']}:{$user['id']}");
        if (in_array($old, ['administrator', 'creator'], true) || in_array($new, ['administrator', 'creator'], true)) {
            Cache::forget("admins:{$e['chat']['id']}");
        }
    }

    private static function onJoin(int $chat, array $user): void
    {
        if (!empty($user['is_bot'])) {
            return;
        }
        Guard::joined($chat, $user['id']);
        $sc = DB::findScammer($user['id'], $user['username'] ?? null);
        if ($sc && Settings::on($chat, 'anti_scam')) {
            if (Settings::get('auto_ban_scammers')) {
                TG::api('banChatMember', ['chat_id' => $chat, 'user_id' => $user['id']]);
            }
            if (!Cache::throttled("$chat:{$user['id']}:scammer", 3600)) {
                TG::send($chat, UI::t('scam_known', ['mention' => UI::mention($user), 'reason' => UI::esc($sc['reason'] ?: '—')]));
            }
        }
    }

    public static function processDeletes(): void
    {
        foreach (DB::all('SELECT rowid, chat_id, message_id FROM delete_queue WHERE at <= ? LIMIT 200', [time()]) as $r) {
            TG::delete($r['chat_id'], $r['message_id']);
            DB::q('DELETE FROM delete_queue WHERE rowid=?', [$r['rowid']]);
        }
    }
}

final class Start
{
    const COMMANDS_V = 7;

    public static function registerCommands(): bool
    {
        $list = [['command' => 'gift', 'description' => 'قیمت گیفت و کلکسیون گیفت'], ['command' => 'account', 'description' => 'اطلاعات اکانت'], ['command' => 'emoji', 'description' => 'کد ایموجی پریمیوم'], ['command' => 'help', 'description' => 'راهنمای ربات']];
        foreach (['default', 'all_private_chats', 'all_group_chats', 'all_chat_administrators'] as $scope) {
            foreach ([null, 'fa', 'en', 'ru'] as $lang) {
                TG::api('deleteMyCommands', ['scope' => ['type' => $scope], 'language_code' => $lang]);
            }
        }
        $ok = TG::api('setMyCommands', ['commands' => $list]) !== null
            && TG::api('setMyCommands', ['commands' => $list, 'scope' => ['type' => 'all_private_chats']]) !== null
            && TG::api('setMyCommands', ['commands' => $list, 'scope' => ['type' => 'all_group_chats']]) !== null;
        if ($ok) {
            DB::kvSet('commands_v', self::COMMANDS_V);
        }
        return $ok;
    }

    public static function start(array $m, string $args): void
    {
        if (!Bot::isPrivate($m)) {
            self::help($m, '');
            return;
        }
        if (str_starts_with($args, 'scam_')) {
            Scam::beginEvidence($m, (int)substr($args, 5));
            return;
        }
        self::welcome((int)$m['chat']['id'], $m['from']);
    }

    public static function welcome(int $chat, array $u): void
    {
        if (!Lang::chosen($chat)) {
            TG::send($chat, UI::t('lang_choose'), Groups::langKb($chat, 'ul:'), ['__quick' => true]);
            return;
        }
        Lang::use($chat);
        TG::send($chat, UI::t('start_private', ['name' => UI::esc($u['first_name'] ?? ''), 'bot_username' => TG::username()]), UI::footer(), ['__quick' => true]);
    }

    public static function langCb(array $cq, string $l): void
    {
        $chat = (int)($cq['message']['chat']['id'] ?? 0);
        if ($chat <= 0 || $chat !== (int)$cq['from']['id'] || !in_array($l, Lang::ALL, true)) {
            TG::answer($cq['id']);
            return;
        }
        Lang::set($chat, $l);
        UI::$lang = $l;
        TG::answer($cq['id'], '✅');
        TG::edit($chat, $cq['message']['message_id'], UI::t('lang_set'), null);
        self::welcome($chat, $cq['from']);
    }

    public static function help(array $m, string $args): void
    {
        TG::send($m['chat']['id'], UI::t('help'), UI::footer(), ['__quick' => true]);
    }
}

final class Groups
{
    const SHORT = [
        'force_join_groups' => 'عضویت', 'anti_self' => 'ضد سلف', 'anti_tabchi' => 'ضد تبچی', 'anti_scam' => 'ضد اسکم', 'conversions' => 'قیمت', 'checkers' => 'چکر',
        'gifts' => 'گیفت', 'calculator' => 'محاسبه', 'translate' => 'ترجمه', 'instagram' => 'اینستا', 'pinterest' => 'پینترست', 'games' => 'بازی',
    ];

    private static function kb(int $chat): array
    {
        $rows = [];
        $row = [];
        foreach (Settings::groupFeatures() as $k => $label) {
            $on = Settings::on($chat, $k);
            $row[] = UI::raw(($on ? '✅ ' : '❌ ') . UI::l(self::SHORT[$k] ?? $label), "gs:$k", $on ? 'success' : 'danger');
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        $rows[] = [UI::button('btn_gfj', ['cb' => 'gs:fj'])];
        $rows[] = [UI::raw(UI::l('⛔️ بستن'), 'gs:close')];
        return UI::kb($rows);
    }

    private static function channels(int $chat): array
    {
        return array_values(DB::chatSettings($chat)['fj_channels'] ?? []);
    }

    private static function fjView(int $chat): array
    {
        $list = self::channels($chat);
        $lines = array_map(fn($c) => UI::fill(UI::template('group_fj_line'), ['title' => UI::esc($c['title'] ?? ''), 'url' => UI::esc($c['url'] ?? '')]), $list);
        $rows = array_map(fn($i, $c) => [UI::raw('❌ ' . mb_substr((string)($c['title'] ?? ''), 0, 30), "gs:fjdel:$i", 'danger')], array_keys($list), $list);
        $rows[] = [UI::button('btn_gfj_add', ['cb' => 'gs:fjadd'])];
        $rows[] = [UI::button('btn_gfj_back', ['cb' => 'gs:back'])];
        return [UI::t('group_fj', ['lines' => $lines ? implode("\n", $lines) : UI::l('— کانالی ثبت نشده —')]), UI::kb($rows)];
    }

    private static function askView(array $from): array
    {
        return [UI::t('group_fj_ask', ['mention' => UI::mention($from)]), UI::kb([[UI::button('btn_gfj_cancel', ['cb' => 'gs:fj'])]])];
    }

    public static function pending(array $m, string $text): bool
    {
        if (($m['forward_origin']['type'] ?? '') !== 'channel' && !preg_match('~^(?:@[A-Za-z][A-Za-z0-9_]{3,31}|(?:https?://)?(?:t|telegram)\.me/\S+|-100\d{5,})(?:\s|$)~iu', $text)) {
            return false;
        }
        $panel = (int)(Cache::get('gfjp:' . $m['chat']['id'] . ':' . $m['from']['id']) ?? 0);
        if (!$panel) {
            return false;
        }
        self::addChannel($m, $text, $panel);
        return true;
    }

    private static function show(array $m, int $panel, string $text, array $kb): void
    {
        $chat = (int)$m['chat']['id'];
        $calls = ['del' => ['deleteMessage', ['chat_id' => $chat, 'message_id' => $m['message_id']]]];
        if (!empty($m['__prompt'])) {
            $calls['prompt'] = ['deleteMessage', ['chat_id' => $chat, 'message_id' => $m['__prompt']]];
        }
        if ($panel) {
            $calls['edit'] = ['editMessageText', TG::prep('editMessageText', ['chat_id' => $chat, 'message_id' => $panel, 'text' => $text, 'reply_markup' => $kb])];
        }
        $r = TG::multi($calls);
        if ($panel && (($r['edit'] ?? null) !== null || TG::edit($chat, $panel, $text, $kb) !== null || str_contains(TG::$lastError, 'not modified'))) {
            return;
        }
        TG::send($chat, $text, $kb);
    }

    public static function addChannel(array $m, string $text, int $panel = 0): void
    {
        $chat = (int)$m['chat']['id'];
        $uid = (int)$m['from']['id'];
        if ($chat >= 0 || !Bot::isChatAdmin($chat, $uid)) {
            return;
        }
        $k = 'gfjp:' . $chat . ':' . $uid;
        $fail = function (string $line) use ($m, $panel, $k) {
            if ($panel) {
                Cache::set($k, $panel, 600);
            }
            [$t, $kb] = self::askView($m['from']);
            self::show($m, $panel, $line . "\n\n" . $t, $kb);
        };
        $ref = null;
        $invite = null;
        if (!empty($m['forward_origin']['chat']['id'])) {
            $ref = $m['forward_origin']['chat']['id'];
        } else {
            $parts = preg_split('/\s+/', trim($text));
            $first = preg_replace('~^(?:https?://)?(?:t|telegram)\.me/~i', '@', $parts[0] ?? '');
            if ($first !== '' && $first !== '@') {
                $ref = preg_match('/^-?\d+$/', $first) ? (int)$first : (str_starts_with($first, '@') ? $first : '@' . $first);
            }
            if (isset($parts[1]) && str_contains($parts[1], 't.me/')) {
                $invite = $parts[1];
            }
        }
        $c = $ref !== null ? TG::api('getChat', ['chat_id' => $ref]) : null;
        if (!$c || !in_array($c['type'] ?? '', ['channel', 'supergroup'], true)) {
            $fail(UI::t('group_fj_not_found'));
            return;
        }
        $me = TG::api('getChatMember', ['chat_id' => $c['id'], 'user_id' => TG::botId()]);
        if (!in_array($me['status'] ?? '', ['administrator', 'creator'], true)) {
            $fail(UI::t('group_fj_not_admin', ['title' => UI::esc($c['title'] ?? '')]));
            return;
        }
        $url = $invite ?: (!empty($c['username']) ? 'https://t.me/' . $c['username'] : ($c['invite_link'] ?? null));
        $url ??= TG::api('createChatInviteLink', ['chat_id' => $c['id'], 'name' => 'force-join'])['invite_link'] ?? null;
        if (!$url) {
            $fail(UI::t('group_fj_not_found'));
            return;
        }
        Cache::forget($k);
        $list = array_values(array_filter(self::channels($chat), fn($x) => (string)$x['id'] !== (string)$c['id']));
        $list[] = ['id' => $c['id'], 'title' => $c['title'] ?? ($c['username'] ?? (string)$c['id']), 'url' => $url];
        DB::setChatSetting($chat, 'fj_channels', array_slice($list, -5));
        [$t, $kb] = self::fjView($chat);
        self::show($m, $panel, UI::t('group_fj_added', ['title' => UI::esc($c['title'] ?? '')]) . "\n\n" . $t, $kb);
    }

    public static function langKb(int $chat, string $prefix): array
    {
        $cur = Lang::chosen($chat) ? Lang::of($chat) : null;
        $row = [];
        foreach (Lang::ALL as $l) {
            $b = UI::button("lang_$l", ['cb' => $prefix . $l]);
            $b['style'] = $l === $cur ? 'success' : 'primary';
            $row[] = $b;
        }
        return UI::kb([$row]);
    }

    public static function settings(array $m, string $args): void
    {
        $chat = (int)$m['chat']['id'];
        if (!Bot::isChatAdmin($chat, $m['from']['id'])) {
            Bot::reply($m, UI::t('only_group_admins'), null);
            return;
        }
        Bot::reply($m, UI::t('group_settings', ['title' => UI::esc($m['chat']['title'] ?? '')]), self::kb($chat));
    }

    public static function toggle(array $cq, string $key): void
    {
        $chat = (int)$cq['message']['chat']['id'];
        if (!Bot::isChatAdmin($chat, $cq['from']['id'])) {
            TG::answer($cq['id'], UI::plain('only_group_admins'), true);
            return;
        }
        if ($key === 'close') {
            TG::answer($cq['id']);
            TG::delete($chat, $cq['message']['message_id']);
            return;
        }
        if ($key === 'fj' || str_starts_with($key, 'fjdel:')) {
            Cache::forget('gfjp:' . $chat . ':' . $cq['from']['id']);
            if (str_starts_with($key, 'fjdel:')) {
                $list = self::channels($chat);
                array_splice($list, (int)substr($key, 6), 1);
                DB::setChatSetting($chat, 'fj_channels', array_values($list));
            }
            TG::answer($cq['id']);
            [$t, $kb] = self::fjView($chat);
            TG::edit($chat, $cq['message']['message_id'], $t, $kb);
            return;
        }
        if ($key === 'fjadd') {
            TG::answer($cq['id']);
            Cache::set('gfjp:' . $chat . ':' . $cq['from']['id'], (int)$cq['message']['message_id'], 600);
            [$t, $kb] = self::askView($cq['from']);
            TG::edit($chat, $cq['message']['message_id'], $t, $kb);
            return;
        }
        if ($key === 'back') {
            Cache::forget('gfjp:' . $chat . ':' . $cq['from']['id']);
            TG::answer($cq['id']);
            TG::edit($chat, $cq['message']['message_id'], UI::t('group_settings', ['title' => UI::esc($cq['message']['chat']['title'] ?? '')]), self::kb($chat));
            return;
        }
        if (!isset(Settings::groupFeatures()[$key])) {
            TG::answer($cq['id']);
            return;
        }
        DB::setChatSetting($chat, $key, !Settings::on($chat, $key));
        TG::answer($cq['id'], '✅');
        TG::api('editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $cq['message']['message_id'], 'reply_markup' => self::kb($chat)]);
    }
}
