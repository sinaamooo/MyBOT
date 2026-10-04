<?php
defined('NB_ROOT') || exit;

function arDefaults() {
    return [
        'on'       => true,
        'cooldown' => 30,
        'rules'    => [],
    ];
}

function arCfg() {
    $c = cfg()['autoreply'] ?? null;
    return is_array($c) ? array_replace(arDefaults(), $c) : arDefaults();
}

function arSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['autoreply'] ?? null)) $c['autoreply'] = arDefaults();
        if (!is_array($c['autoreply']['rules'] ?? null)) $c['autoreply']['rules'] = [];
        $fn($c['autoreply']);
    });
}

function arRules() {
    return array_values(array_filter((array)(arCfg()['rules'] ?? []), 'is_array'));
}

function arRule($id) {
    foreach (arRules() as $r) if ((string)($r['id'] ?? '') === (string)$id) return $r;
    return null;
}

function arRuleSet($id, callable $fn) {
    arSet(function (&$a) use ($id, $fn) {
        foreach ($a['rules'] as $i => $r) {
            if (!is_array($r) || (string)($r['id'] ?? '') !== (string)$id) continue;
            $fn($a['rules'][$i]);
            return;
        }
    });
}

function arModes() {
    return ['contains' => 'شامل عبارت', 'all' => 'همه‌ی کلمه‌ها (به هر ترتیبی)', 'exact' => 'دقیقا همین متن'];
}

function arNorm($t) {
    $t = mb_strtolower((string)$t, 'UTF-8');
    $t = strtr($t, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ؤ' => 'و']);
    $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$t);
    return trim((string)preg_replace('/\s+/u', ' ', (string)$t));
}

function arWords(array $r) {
    $out = [];
    foreach (preg_split('/\R/u', (string)($r['words'] ?? '')) as $w) {
        $w = arNorm($w);
        if ($w !== '' && !in_array($w, $out, true)) $out[] = $w;
    }
    return $out;
}

function arMatch(array $r, $norm) {
    $mode = (string)($r['mode'] ?? 'contains');
    $hay  = ' ' . $norm . ' ';
    foreach (arWords($r) as $w) {
        if ($mode === 'exact') { if ($norm === $w) return true; continue; }
        if ($mode === 'all') {
            $ok = true;
            foreach (explode(' ', $w) as $p) if (!str_contains($hay, ' ' . $p . ' ')) { $ok = false; break; }
            if ($ok) return true;
            continue;
        }
        if (str_contains($hay, ' ' . $w . ' ')) return true;
    }
    return false;
}

function arCool($chat, $uid, $rid, $secs) {
    $k  = $chat . ':' . $uid . ':' . $rid;
    $ck = '#' . $chat;
    $ok = false;
    mutate('ar_cool', function (&$m) use ($k, $ck, $secs, &$ok) {
        if (!is_array($m)) $m = [];
        $now = time();
        if ($secs > 0 && $now - (int)($m[$k] ?? 0) < $secs) return;
        $w = is_array($m[$ck] ?? null) ? $m[$ck] : [0, 0];
        if ($now - (int)$w[0] >= 60) $w = [$now, 0];
        if ((int)$w[1] >= 12) return;
        $m[$ck] = [(int)$w[0], (int)$w[1] + 1];
        if ($secs > 0) $m[$k] = $now;
        $ok = true;
        if (count($m) > 500) {
            $keep = max(60, $secs);
            foreach ($m as $kk => $t) if ($now - (int)(is_array($t) ? $t[0] : $t) > $keep) unset($m[$kk]);
        }
    });
    return $ok;
}

function arBtn(array $r) {
    $b = (array)($r['btn'] ?? []);
    if (empty($b['on'])) return null;
    $url = trim((string)($b['url'] ?? ''));
    if ($url === '' && function_exists('botUsername') && botUsername() !== '') $url = 'https://t.me/' . botUsername();
    if (!preg_match('#^https?://#i', $url)) return null;
    $label = trim((string)($b['text'] ?? '')) !== '' ? (string)$b['text'] : 'ورود به ربات';
    $btn = btnApplyLabel(['url' => $url], $label, (string)($b['icon'] ?? ''));
    if (isStyle((string)($b['color'] ?? ''))) $btn['style'] = (string)$b['color'];
    return $btn;
}

function arSend($chatId, array $r, $replyTo = null) {
    $btn = arBtn($r);
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo, 'allow_sending_without_reply' => 'true'] : [];
    return sendMsg(BOT_TOKEN, $chatId, (string)$r['text'], $btn ? inlineKb([[$btn]]) : null, $extra);
}

function arHandleGroup($msg, $uid, $chatId) {
    $c = arCfg();
    if (empty($c['on']) || !empty($msg['from']['is_bot'])) return false;
    $text = (string)($msg['text'] ?? $msg['caption'] ?? '');
    if ($text === '' || mb_strlen($text) > 500) return false;
    $norm = arNorm($text);
    if ($norm === '') return false;
    foreach (arRules() as $r) {
        if (empty($r['on']) || trim((string)($r['text'] ?? '')) === '' || !arMatch($r, $norm)) continue;
        if (arCool($chatId, $uid, (string)($r['id'] ?? ''), (int)($c['cooldown'] ?? 30))) arSend($chatId, $r, $msg['message_id'] ?? null);
        return true;
    }
    return false;
}


function arAdminHome($chatId, $msgId = null) {
    $c = arCfg();
    $rules = arRules();
    $t  = "💬 <b>پاسخ خودکار گروه</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '⏱ فاصله‌ی تکرار برای هر نفر: <b>' . (int)$c['cooldown'] . "</b> ثانیه\n\n";
    $rows = [[btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'arp_x', 'info'), btnCb('⏱ فاصله‌ی تکرار', 'arp_cd', 'admin')]];
    if (!$rules) $t .= "قانونی نیست\n";
    foreach ($rules as $i => $r) {
        $w = arWordsRaw($r);
        $first = $w[0] ?? '—';
        $t .= ($i + 1) . '. ' . (!empty($r['on']) ? '✅' : '⏸') . ' <code>' . h(mb_substr($first, 0, 40)) . '</code>' .
              (count($w) > 1 ? ' (+' . (count($w) - 1) . ')' : '') . "\n";
        $rows[] = [btnCb(($i + 1) . '. ' . mb_substr($first, 0, 28), 'arp_r_' . $r['id'], 'admin')];
    }
    $rows[] = [btnCb('➕ قانونِ تازه', 'arp_add', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'ag_games', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function arWordsRaw(array $r) {
    $out = [];
    foreach (preg_split('/\R/u', (string)($r['words'] ?? '')) as $w) if (trim($w) !== '') $out[] = trim($w);
    return $out;
}

function arAdminRule($chatId, $msgId, $id) {
    $r = arRule($id);
    if (!$r) { arAdminHome($chatId, $msgId); return; }
    $b = (array)($r['btn'] ?? []);
    $t  = "💬 <b>قانونِ پاسخ خودکار</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($r['on']) ? '✅ روشن' : '⏸ خاموش') . "\n";
    $t .= '🎯 نوعِ تطبیق: <b>' . h(arModes()[$r['mode'] ?? 'contains'] ?? '') . "</b>\n\n";
    $t .= "🔎 <b>عبارت‌ها</b> (هر خط یکی):\n";
    foreach (arWordsRaw($r) as $w) $t .= '• <code>' . h($w) . "</code>\n";
    $t .= "\n💬 <b>جواب</b>:\n" . (trim((string)($r['text'] ?? '')) !== '' ? (string)$r['text'] : '<i>هنوز خالی است</i>') . "\n\n";
    $t .= '🔘 دکمه: ' . (!empty($b['on']) ? '✅ ' . h((string)($b['text'] ?? 'ورود به ربات')) : '❌ ندارد');
    if (!empty($b['on'])) {
        $t .= "\n🎨 رنگ: " . h(styleMap()[(string)($b['color'] ?? 'none')] ?? '—');
        $t .= "\n🔗 لینک: " . (trim((string)($b['url'] ?? '')) !== '' ? h((string)$b['url']) : 'خودِ ربات');
    }
    $rows = [
        [btnCb('🔎 عبارت‌ها', 'arp_w_' . $id, 'admin'), btnCb('💬 متنِ جواب', 'arp_t_' . $id, 'admin')],
        [btnCb('🎯 نوعِ تطبیق', 'arp_m_' . $id, 'admin')],
        [btnCb(!empty($b['on']) ? '🔘 دکمه: روشن' : '🔘 دکمه: خاموش', 'arp_bx_' . $id, 'info')],
    ];
    if (!empty($b['on'])) {
        $rows[] = [btnCb('✏️ متن و ایموجیِ دکمه', 'arp_bt_' . $id, 'admin'), btnCb('🎨 رنگِ دکمه', 'arp_bc_' . $id, 'admin')];
        $rows[] = [btnCb('🔗 لینکِ دکمه', 'arp_bu_' . $id, 'admin')];
    }
    $rows[] = [btnCb('👁 پیش‌نمایش', 'arp_p_' . $id, 'info')];
    $rows[] = [btnCb(!empty($r['on']) ? '⏸ خاموش کن' : '✅ روشن کن', 'arp_rx_' . $id, 'info'), btnCb('🗑 حذف', 'arp_d_' . $id, 'reject')];
    $rows[] = [btnCb(UT('back'), 'arp_home', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, mb_substr($t, 0, 3900), inlineKb($rows));
}

function arAsk($chatId, $state, $data, $text, $back) {
    setState(admStateUid($chatId), $state, $data);
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[btnCb('انصراف', $back, 'cancel')]]));
}

function arAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'arp_')) return false;
    $d = substr((string)$data, 4);

    if ($d === 'home') { answerCb(BOT_TOKEN, $cbId); arAdminHome($chatId, $msgId); return true; }
    if ($d === 'x') {
        arSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); arAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'cd') {
        answerCb(BOT_TOKEN, $cbId);
        arAsk($chatId, 'ar_cd', [], "⏱ هر نفر بعد از گرفتنِ جواب، تا چند ثانیه دوباره همان جواب را نگیرد؟ (۰ تا ۳۶۰۰)", 'arp_home');
        return true;
    }
    if ($d === 'add') {
        answerCb(BOT_TOKEN, $cbId);
        arAsk($chatId, 'ar_new', [],
            "🔎 <b>عبارت‌های حساس</b> — هر خط یک عبارت.\n\nمثال:\n<code>شماره مجازی کجا بخرم\nشماره مجازی از کجا بخرم</code>",
            'arp_home');
        return true;
    }
    if (!preg_match('/^(r|w|t|m|bx|bt|bc|bcv|bu|p|rx|d|dy)_(r[0-9a-f]+)(?:_(\w+))?$/', $d, $m)) return false;
    [, $op, $id] = $m;
    $arg = $m[3] ?? '';
    $r = arRule($id);
    if (!$r) { answerCb(BOT_TOKEN, $cbId, 'این قانون پیدا نشد.', true); arAdminHome($chatId, $msgId); return true; }

    switch ($op) {
        case 'r':
            answerCb(BOT_TOKEN, $cbId); arAdminRule($chatId, $msgId, $id); return true;
        case 'w':
            answerCb(BOT_TOKEN, $cbId);
            arAsk($chatId, 'ar_words', ['id' => $id],
                "🔎 <b>عبارت‌ها</b> · هر خط یکی\n\nالان:\n<code>" . h(implode("\n", arWordsRaw($r))) . '</code>', 'arp_r_' . $id);
            return true;
        case 't':
            answerCb(BOT_TOKEN, $cbId);
            arAsk($chatId, 'ar_text', ['id' => $id],
                "💬 <b>متنِ جواب</b>", 'arp_r_' . $id);
            return true;
        case 'm':
            $keys = array_keys(arModes());
            $cur  = array_search((string)($r['mode'] ?? 'contains'), $keys, true);
            $next = $keys[((int)$cur + 1) % count($keys)];
            arRuleSet($id, function (&$x) use ($next) { $x['mode'] = $next; });
            answerCb(BOT_TOKEN, $cbId, '🎯 ' . arModes()[$next]); arAdminRule($chatId, $msgId, $id); return true;
        case 'bx':
            arRuleSet($id, function (&$x) {
                if (!is_array($x['btn'] ?? null)) $x['btn'] = ['text' => 'ورود به ربات', 'icon' => '', 'color' => 'success', 'url' => ''];
                $x['btn']['on'] = empty($x['btn']['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅'); arAdminRule($chatId, $msgId, $id); return true;
        case 'bt':
            answerCb(BOT_TOKEN, $cbId);
            arAsk($chatId, 'ar_btext', ['id' => $id],
                "✏️ <b>متنِ دکمه</b>\n\nالان: <code>" . h((string)($r['btn']['text'] ?? '')) . '</code>', 'arp_r_' . $id);
            return true;
        case 'bc':
            answerCb(BOT_TOKEN, $cbId);
            $cur = (string)($r['btn']['color'] ?? 'none');
            $rows = [];
            foreach (styleMap() as $k => $lbl) $rows[] = [btnCb(($k === $cur ? '✅ ' : '') . $lbl, 'arp_bcv_' . $id . '_' . $k, 'info')];
            $rows[] = [btnCb(UT('back'), 'arp_r_' . $id, 'nav')];
            editMsg(BOT_TOKEN, $chatId, $msgId, '🎨 <b>رنگِ دکمه</b>', inlineKb($rows));
            return true;
        case 'bcv':
            if (!isset(styleMap()[$arg])) { answerCb(BOT_TOKEN, $cbId); return true; }
            arRuleSet($id, function (&$x) use ($arg) { $x['btn']['color'] = $arg; });
            answerCb(BOT_TOKEN, $cbId, '✅'); arAdminRule($chatId, $msgId, $id); return true;
        case 'bu':
            answerCb(BOT_TOKEN, $cbId);
            arAsk($chatId, 'ar_burl', ['id' => $id],
                "🔗 <b>لینکِ دکمه</b>\n\n<code>https://…</code> · <code>ربات</code>", 'arp_r_' . $id);
            return true;
        case 'p':
            answerCb(BOT_TOKEN, $cbId);
            if (trim((string)($r['text'] ?? '')) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متنِ جواب هنوز خالی است.'); return true; }
            arSend($chatId, $r);
            return true;
        case 'rx':
            arRuleSet($id, function (&$x) { $x['on'] = empty($x['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅'); arAdminRule($chatId, $msgId, $id); return true;
        case 'd':
            answerCb(BOT_TOKEN, $cbId);
            editMsg(BOT_TOKEN, $chatId, $msgId, '🗑 این قانون حذف شود؟', inlineKb([
                [btnCb('🗑 بله، حذف شود', 'arp_dy_' . $id, 'reject')], [btnCb(UT('back'), 'arp_r_' . $id, 'nav')]]));
            return true;
        case 'dy':
            arSet(function (&$c) use ($id) {
                $c['rules'] = array_values(array_filter($c['rules'], fn($x) => !is_array($x) || (string)($x['id'] ?? '') !== (string)$id));
            });
            answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد'); arAdminHome($chatId, $msgId); return true;
    }
    return false;
}

function arStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ar_') || !isAdmin($uid)) return false;
    $sd   = getState($uid)['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $id   = (string)($sd['id'] ?? '');
    $back = fn($rid = '') => inlineKb([[btnCb('💬 ' . ($rid !== '' ? 'برگشت به قانون' : 'پاسخ خودکار'), $rid !== '' ? 'arp_r_' . $rid : 'arp_home', 'admin')]]);

    if ($action === 'ar_cd') {
        $v = (int)norm_fa_digits($text);
        if ($text === '' || $v < 0 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ۰ تا ۳۶۰۰'); return true; }
        arSet(function (&$c) use ($v) { $c['cooldown'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ فاصله‌ی تکرار: ' . $v . ' ثانیه', $back());
        return true;
    }
    if ($action === 'ar_new' || $action === 'ar_words') {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $w) if (trim($w) !== '' && arNorm($w) !== '') $lines[] = trim($w);
        if (!$lines) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عبارتی نیست'); return true; }
        $words = implode("\n", array_slice($lines, 0, 40));
        if ($action === 'ar_words') {
            arRuleSet($id, function (&$x) use ($words) { $x['words'] = $words; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '✅ عبارت‌ها ذخیره شد.', $back($id));
            return true;
        }
        $nid = 'r' . bin2hex(random_bytes(3));
        arSet(function (&$c) use ($nid, $words) {
            $c['rules'][] = ['id' => $nid, 'on' => true, 'mode' => 'contains', 'words' => $words, 'text' => '',
                             'btn' => ['on' => false, 'text' => 'ورود به ربات', 'icon' => '', 'color' => 'success', 'url' => '']];
        });
        setState($uid, 'ar_text', ['id' => $nid]);
        sendMsg(BOT_TOKEN, $chatId, "✅ عبارت‌ها ثبت شد\n\n💬 <b>متنِ جواب</b>",
            inlineKb([[btnCb('انصراف', 'arp_r_' . $nid, 'cancel')]]));
        return true;
    }
    if ($action === 'ar_text') {
        $html = msgHtml($msg);
        if (trim(strip_tags($html)) === '' && !customEmojiIds($msg)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        arRuleSet($id, function (&$x) use ($html) { $x['text'] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ متنِ جواب ذخیره شد.', $back($id));
        return true;
    }
    if ($action === 'ar_btext') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        $ids  = customEmojiIds($msg);
        $icon = $ids ? (string)$ids[0] : '';
        $clean = $icon !== '' ? textWithoutCustomEmoji($msg) : $text;
        if ($clean === '') $clean = $text;
        arRuleSet($id, function (&$x) use ($clean, $icon) { $x['btn']['text'] = $clean; $x['btn']['icon'] = $icon; });
        clearState($uid);
        $r = arRule($id);
        $b = $r ? arBtn($r) : null;
        sendMsg(BOT_TOKEN, $chatId, '✅ دکمه ذخیره شد',
            $b ? inlineKb([[$b]]) : null);
        sendMsg(BOT_TOKEN, $chatId, '👆', $back($id));
        return true;
    }
    if ($action === 'ar_burl') {
        $url = in_array($text, ['ربات', 'bot', '-'], true) ? '' : $text;
        if ($url !== '' && !preg_match('#^https?://\S+$#i', $url)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ لینک باید با https:// شروع شود.'); return true; }
        arRuleSet($id, function (&$x) use ($url) { $x['btn']['url'] = $url; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ لینکِ دکمه ذخیره شد.', $back($id));
        return true;
    }
    clearState($uid);
    return true;
}
