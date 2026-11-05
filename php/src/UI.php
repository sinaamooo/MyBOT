<?php

final class UI
{
    const PH = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';

    public static function esc($v): string
    {
        return htmlspecialchars((string)$v, ENT_NOQUOTES, 'UTF-8');
    }

    public static function attr($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    private static function unbalanced(string $line): array
    {
        $opened = [];
        $closing = [];
        preg_match_all('/<[^>]+>/u', $line, $m);
        foreach ($m[0] as $tag) {
            if (!preg_match('/^<\/?\s*([a-zA-Z][a-zA-Z0-9-]*)/', $tag, $nm)) {
                continue;
            }
            $name = strtolower($nm[1]);
            if ($name === 'br') {
                continue;
            }
            if (str_starts_with($tag, '</')) {
                if ($opened && end($opened)[0] === $name) {
                    array_pop($opened);
                } else {
                    $closing[] = $tag;
                }
            } else {
                $opened[] = [$name, $tag];
            }
        }
        return [array_column($opened, 1), $closing];
    }

    const ICONS = ['toman' => '🇮🇷', 'usd' => '🇺🇸', 'usdt' => '💵', 'ton' => '💠', 'trx' => '🔴', 'btc' => '🪙', 'eth' => '💎', 'sol' => '🌟',
        'nft' => '🎞', 'premium' => '💫', 'gold' => '🏅', 'wallet' => '👛', 'irr' => '💰',
        'dorange' => '🟠', 'dpeach' => '🟠', 'dgreen' => '🟢', 'dblue' => '🔵', 'dbrown' => '🟤', 'dolive' => '🟤', 'dred' => '🔴', 'dblack' => '⚫️', 'dnavy' => '🔵'];

    public static function fill(string $template, array $values = []): string
    {
        if (str_contains($template, '_icon}') && preg_match_all('/\{([a-z]+)_icon\}/', $template, $im)) {
            foreach (array_unique($im[1]) as $k) {
                if (!array_key_exists($k . '_icon', $values) && isset(self::ICONS[$k])) {
                    $values[$k . '_icon'] = self::badge(strtoupper($k), self::ICONS[$k]);
                }
            }
        }
        $out = [];
        $carry = '';
        foreach (explode("\n", $template) as $line) {
            preg_match_all(self::PH, $line, $m);
            $drop = false;
            foreach ($m[1] as $name) {
                if (array_key_exists($name, $values) && $values[$name] === null) {
                    $drop = true;
                    break;
                }
            }
            if ($drop) {
                [$opening, $closing] = self::unbalanced($line);
                foreach ($closing as $ct) {
                    $name = strtolower(trim(substr($ct, 2, -1)));
                    if (preg_match_all('/<' . preg_quote($name, '/') . '\b[^>]*>/i', $carry, $cm, PREG_OFFSET_CAPTURE)) {
                        $last = end($cm[0]);
                        $carry = substr($carry, 0, $last[1]) . substr($carry, $last[1] + strlen($last[0]));
                    } elseif ($out) {
                        $out[count($out) - 1] .= $ct;
                    } else {
                        $carry .= $ct;
                    }
                }
                $carry .= implode('', $opening);
                continue;
            }
            $line = preg_replace_callback(self::PH, fn($mm) => array_key_exists($mm[1], $values) ? (string)$values[$mm[1]] : $mm[0], $line);
            $out[] = $carry . $line;
            $carry = '';
        }
        if ($carry !== '' && $out) {
            $out[count($out) - 1] .= $carry;
        }
        $text = implode("\n", $out);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    public static function template(string $key): string
    {
        $o = DB::textOverride($key);
        return self::kashida($o ?? (Config::data('texts')[$key]['text'] ?? $key));
    }

    public static function stretch(string $s): string
    {
        static $dual = null;
        $dual ??= array_flip(mb_str_split('بپتثجچحخسشصضطظعغفقکگلمنهیئيكە'));
        $join = $dual + array_flip(mb_str_split('اآأإدذرزژوؤة'));
        $chars = mb_str_split($s);
        $out = '';
        $n = count($chars);
        for ($i = 0; $i < $n; $i++) {
            $out .= $chars[$i];
            if ($i + 1 < $n && isset($dual[$chars[$i]]) && isset($join[$chars[$i + 1]])) {
                $out .= "\u{0640}";
            }
        }
        return $out;
    }

    public static function kashida(string $text): string
    {
        if (Config::get('KASHIDA', '1') === '0' || !preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }
        $parts = preg_split('/(<[^>]+>|\{[a-z_]+\})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $skip = 0;
        foreach ($parts as &$p) {
            if ($p === '' || $p[0] === '{') {
                continue;
            }
            if ($p[0] === '<') {
                if (preg_match('~^<(/?)(code|pre)\b~i', $p, $m)) {
                    $skip = max(0, $skip + ($m[1] === '/' ? -1 : 1));
                }
                continue;
            }
            if (!$skip) {
                $p = self::stretch($p);
            }
        }
        unset($p);
        return implode('', $parts);
    }

    public static function t(string $key, array $values = []): string
    {
        return self::emojiMap(self::fill(self::template($key), $values));
    }

    public static function plain(string $key, array $values = []): string
    {
        $t = html_entity_decode(strip_tags(self::fill(self::template($key), $values)), ENT_QUOTES, 'UTF-8');
        return mb_substr($t, 0, 200);
    }

    const SYN = [
        '⏱' => ['⏲', '⏳', '⌛', '🕒', '⏰'], '⌛' => ['⏳', '⏱'], '⏳' => ['⌛', '⏱'], '❤' => ['💖', '💗', '💕', '♥', '🩷', '❤‍🔥', '👍'],
        '👁' => ['👀'], '👀' => ['👁'], '🎬' => ['🎥', '📹', '🎞', '📽'], '📸' => ['📷', '🎞'], '🖼' => ['🏞', '🌄', '📷', '🎨'],
        '📝' => ['✏', '✍', '🖊', '📄', '📃'], '📖' => ['📚', '📘', '📕', '📗'], '🎂' => ['🎉', '🥳', '🎁'], '🟰' => ['✅', '⚖'],
        '📭' => ['📪', '📥', '📦'], '📍' => ['📌', '🗺'], '#⃣' => ['🔑', '🔢'], '🔢' => ['#⃣', '🔟'], '🧾' => ['📋', '📃', '📄', '🗒'],
        '🔎' => ['🔍'], '🔍' => ['🔎'], '🏦' => ['🏧', '🏛', '💳'], '📱' => ['📲', '☎', '📞'], '📞' => ['☎', '📱'], '🔇' => ['🔕', '⛔', '🚫'],
        '🔊' => ['🔔', '📢'], '📢' => ['📣', '🔊'], '📣' => ['📢'], '🎮' => ['🕹', '🎲', '🎯'], '💣' => ['🧨', '💥'], '💥' => ['💣', '🔥'],
        '🤑' => ['💰', '💸'], '💤' => ['😴', '⏳'], '👉' => ['➡', '⏩'], '✖' => ['❌'], '🆙' => ['⬆', '🚀', '📈'], '🪪' => ['🆔', '👤'],
        '🗣' => ['💬', '🌐'], '🖥' => ['💻', '🌐'], '📅' => ['🗓', '📆'], '🗓' => ['📅', '📆'], '📄' => ['📃', '📋', '🗒'], '🎉' => ['🥳', '🎊', '✨'],
        '🎨' => ['🖌'], '✨' => ['💫', '🌟', '⭐'], '💫' => ['✨', '🌟'], '🌟' => ['⭐', '✨'], '⭐' => ['🌟'], '🤖' => ['👾'], '😀' => ['😃', '😄', '🙂', '😊'],
        '💡' => ['ℹ', '❗', '❓'], '🗂' => ['📁', '📂', '🗄'], '📎' => ['🖇', '🔗'], '🪙' => ['💰', '🟡'], '💎' => ['💠', '🔷'], '🔷' => ['💎', '💠'],
        '👤' => ['🧑', '👨', '🙂'], '🧑' => ['👤'], '👥' => ['🫂', '👪'], '🛡' => ['🔒'], '🔒' => ['🔐', '🛡'], '✅' => ['✔', '☑'], '❌' => ['✖', '⛔'],
        '⛔' => ['🚫', '❌'], '⚠' => ['❗', '‼'], '📊' => ['📈', '💹'], '📈' => ['💹', '📊', '🚀'], '📉' => ['🔻', '📊'], '💱' => ['💹', '🔄', '💵'],
        '🔄' => ['♻', '🔁'], '🌍' => ['🌎', '🌏', '🌐'], '🌐' => ['🌍', '🌎', '🌏'], '💬' => ['🗨', '💭'], '🔗' => ['⛓', '📎'], '👛' => ['💼', '👝', '💳'],
        '💰' => ['💵', '💸', '🪙'], '💵' => ['💸', '💰', '💲'], '💸' => ['💵', '💰'], '🔔' => ['📢'], '🎁' => ['🎀'], '🏆' => ['🥇', '🏅', '👑'],
        '🏅' => ['🥇', '🎖', '🏆'], '🎖' => ['🏅'], '👑' => ['🏆'], '🔑' => ['🗝'], '🔥' => ['💥'], '⚙' => ['🛠', '🔧'], '🚨' => ['🆘', '⚠'],
        '📋' => ['🧾', '📄'], '🎯' => ['🏹'], '🏷' => ['🔖', '🎫'], '🗄' => ['🗂', '📦'], '📜' => ['📃', '📄', '🧾'], '📥' => ['📭', '⬇'], '📤' => ['⬆'],
    ];

    private static ?array $rx = null;

    public static function manualMap(): array
    {
        $db = DB::kvHas('emoji_map') ? DB::kvGet('emoji_map') : [];
        return array_merge(Settings::defaults()['emoji_map'] ?? [], is_array($db) ? $db : []);
    }

    public static function emojiTable(): array
    {
        if (self::$rx !== null) {
            return self::$rx;
        }
        $auto = DB::kvGet('emoji_auto');
        $map = array_merge(is_array($auto['map'] ?? null) ? $auto['map'] : [], Settings::get('emoji_pack') ?: [], self::manualMap());
        $bad = array_flip((array)(Settings::get('emoji_invalid') ?: []));
        $lookup = [];
        foreach ($map as $k => $id) {
            $bare = str_replace("\u{FE0F}", '', (string)$k);
            $id = (string)$id;
            if ($bare !== '' && $id !== '' && !isset($bad[$id])) {
                $lookup[$bare] = $id;
            }
        }
        foreach (self::SYN as $c => $alts) {
            if (!isset($lookup[$c])) {
                foreach ($alts as $a) {
                    if (isset($lookup[$a])) {
                        $lookup[$c] = $lookup[$a];
                        break;
                    }
                }
            }
        }
        $keys = array_keys($lookup);
        usort($keys, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        $parts = [];
        foreach ($keys as $k) {
            $parts[] = implode('', array_map(fn($c) => preg_quote($c, '/') . "\u{FE0F}?", mb_str_split((string)$k)));
        }
        return self::$rx = [$parts ? '/' . implode('|', $parts) . '/u' : null, $lookup];
    }

    public static function stripEmoji(?string $s): string
    {
        $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{2934}\x{2935}\x{3030}\x{303D}\x{3297}\x{3299}\x{203C}\x{2049}\x{2122}\x{2139}\x{FE0F}\x{FE0E}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', '', (string)$s);
        return trim(preg_replace('/[ \t]{2,}/u', ' ', (string)$s));
    }

    public static function emojiChar(string $id): ?string
    {
        [, $lookup] = self::emojiTable();
        $c = array_search($id, $lookup, true);
        return $c === false ? null : (string)$c;
    }

    public static function emojiId(string $emoji): ?string
    {
        [, $lookup] = self::emojiTable();
        return $lookup[str_replace("\u{FE0F}", '', $emoji)] ?? null;
    }

    public static function stickerAlts(array $ids): ?array
    {
        $alt = [];
        foreach (array_chunk(array_values(array_unique(array_map('strval', $ids))), 200) as $chunk) {
            $r = TG::api('getCustomEmojiStickers', ['custom_emoji_ids' => $chunk]);
            if ($r === null) {
                return null;
            }
            foreach ($r as $st) {
                $alt[(string)($st['custom_emoji_id'] ?? '')] = str_replace("\u{FE0F}", '', (string)($st['emoji'] ?? ''));
            }
        }
        return $alt;
    }

    public static function validateEmoji(): ?array
    {
        $pool = array_map('strval', Config::data('emoji_pool'));
        $ids = array_values(array_unique(array_merge($pool, array_map('strval', array_values(self::manualMap())),
            array_map('strval', array_values(Settings::get('emoji_pack') ?: [])), array_map('strval', array_values(Config::data('badges'))))));
        $alt = self::stickerAlts($ids);
        if ($alt === null) {
            return null;
        }
        $bad = array_values(array_filter($ids, fn($id) => !isset($alt[$id])));
        Settings::set('emoji_invalid', $bad);
        $auto = [];
        foreach ($pool as $id) {
            $a = $alt[$id] ?? '';
            if ($a !== '' && !isset($auto[$a])) {
                $auto[$a] = $id;
            }
        }
        $valid = count(array_filter($pool, fn($id) => isset($alt[$id])));
        if (count($auto) >= max(8, (int)($valid * 0.2))) {
            DB::kvSet('emoji_auto', ['map' => $auto, 'at' => time()]);
        }
        DB::kvSet('emoji_checked_at', time());
        self::resetEmoji();
        return [count($ids) - count($bad), count($bad), count($auto)];
    }

    public static function badge(string $sym, string $fallback = '🪙'): string
    {
        static $bad = null;
        $bad ??= array_flip(array_map('strval', (array)(Settings::get('emoji_invalid') ?: [])));
        $id = Config::data('badges')[strtoupper($sym)] ?? null;
        return $id && !isset($bad[$id]) ? '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>' : $fallback;
    }

    public static function resetEmoji(): void
    {
        self::$rx = null;
    }

    const EMO = '(?:\p{Regional_Indicator}{2}|[#*0-9]\x{FE0F}?\x{20E3}|[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{203C}\x{2049}\x{2139}\x{24C2}\x{3030}\x{303D}\x{3297}\x{3299}](?:\x{FE0F})?(?:\x{200D}[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}](?:\x{FE0F})?)*)';

    public static bool $strip = true;

    private static function dropPlain(string $c, array $lookup): string
    {
        if (!self::$strip) {
            return $c;
        }
        return preg_replace_callback('/(' . self::EMO . ')[ \t]*/u', function ($m) use ($lookup) {
            return isset($lookup[str_replace("\u{FE0F}", '', $m[1])]) ? $m[0] : '';
        }, $c);
    }

    public static function emojiMap(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        [$rx, $lookup] = self::emojiTable();
        $chunks = preg_split('/(<[^>]+>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $skip = 0;
        $quote = 0;
        $used = false;
        $out = '';
        foreach ($chunks as $ci => $c) {
            if ($c !== '' && $c[0] === '<' && str_ends_with($c, '>')) {
                if (preg_match('/^<(\/?)\s*([a-zA-Z][a-zA-Z0-9-]*)/', $c, $nm)) {
                    $tag = strtolower($nm[2]);
                    $close = $nm[1] === '/';
                    if (in_array($tag, ['tg-emoji', 'code', 'pre', 'a'], true)) {
                        $skip = max(0, $skip + ($close ? -1 : 1));
                    }
                    if ($tag === 'blockquote') {
                        $quote = max(0, $quote + ($close ? -1 : 1));
                        $used = false;
                    }
                    if ($tag === 'tg-emoji' && !$close && $quote) {
                        $used = true;
                    }
                }
                $out .= $c;
                continue;
            }
            if ($skip) {
                $out .= $c;
                continue;
            }
            $c = self::dropPlain($c, $lookup);
            if (!$rx) {
                $out .= $c;
                continue;
            }
            $follow = '';
            for ($j = $ci + 1; $j < count($chunks); $j++) {
                if ($chunks[$j] === '') {
                    continue;
                }
                if ($chunks[$j][0] === '<') {
                    if (preg_match('~^</?(blockquote)\b~i', $chunks[$j])) {
                        break;
                    }
                    continue;
                }
                $follow = $chunks[$j];
                break;
            }
            $out .= preg_replace_callback('/\n|' . substr($rx, 1, -2) . '/u', function ($m) use ($lookup, $quote, &$used, $c, $follow) {
                [$e, $at] = $m[0];
                if ($e === "\n") {
                    $used = false;
                    return $e;
                }
                $after = substr($c, $at + strlen($e));
                $sp = strspn($after, ' ');
                $next = substr($after, $sp, 1);
                if ($next === '') {
                    $sp += strspn($follow, ' ');
                    $next = substr($follow, strspn($follow, ' '), 1);
                }
                $pad = ($quote && $next !== '' && $next !== "\n") ? str_repeat(' ', max(0, 4 - $sp)) : '';
                $used = true;
                $id = $lookup[str_replace("\u{FE0F}", '', $e)] ?? '';
                return '<tg-emoji emoji-id="' . $id . '">' . $e . '</tg-emoji>' . $pad;
            }, $c, -1, $n, PREG_OFFSET_CAPTURE);
        }
        return $out;
    }

    public static function iconize(array $b): array
    {
        if (!empty($b['icon_custom_emoji_id']) || !isset($b['text'])) {
            return $b;
        }
        [$lead, $rest] = self::splitLeadingEmoji((string)$b['text']);
        if ($lead !== '' && ($id = self::emojiId($lead))) {
            $b['text'] = $rest;
            $b['icon_custom_emoji_id'] = $id;
        } elseif ($lead !== '' && self::$strip && preg_match('/^' . self::EMO . '$/u', $lead)) {
            $b['text'] = $rest;
        } elseif ($lead === '' && ($id = self::emojiId(trim((string)$b['text'])))) {
            $b['text'] = "\u{3164}";
            $b['icon_custom_emoji_id'] = $id;
        }
        return $b;
    }

    public static function quoteGaps(string $html): string
    {
        $html = preg_replace('/<\/blockquote>\n{2,}/', "</blockquote>\n", $html);
        $first = strpos($html, '<blockquote');
        if ($first === false || strpos($html, '<blockquote', $first + 1) === false) {
            return $html;
        }
        return substr($html, 0, $first + 1) . preg_replace('/\n{2,}(?=<blockquote)/', "\n", substr($html, $first + 1));
    }

    const DOTS = ['5143130625795951140' => 1, '5145802018144651327' => 1, '5143378316559911157' => 1, '5143282547379144501' => 1, '5143403880205256993' => 1,
        '5143315038806738992' => 1, '5143612160349309608' => 1, '5143573900780635800' => 1, '5143392099109964060' => 1];

    public static function align(string $html, int $quoteGap = 2, bool $caption = false): string
    {
        if (!str_contains($html, '<tg-emoji') && !str_contains($html, '</blockquote>')) {
            return $html;
        }
        $depth = 0;
        $lines = explode("\n", $html);
        $last = -1;
        foreach ($lines as $i => $line) {
            if (str_contains($line, '</blockquote>')) {
                $last = $i;
            }
        }
        if ($last < 0 && $caption) {
            for ($i = count($lines) - 1; $i > 0; $i--) {
                if (trim(strip_tags($lines[$i])) !== '') {
                    $last = $i - 1;
                    break;
                }
            }
        }
        foreach ($lines as $i => &$line) {
            $line = preg_replace('/^\x{200E}|\x{200F}$/u', '', $line);
            if (preg_match('/^((?:<(?!tg-emoji|\/)[a-z][^>]*>)*)(\x{200F}?)(<tg-emoji[^>]*>.*?<\/tg-emoji>)[ \t]*/u', $line, $m)) {
                $quote = $depth > 0 || str_contains($m[1], '<blockquote');
                $rest = substr($line, strlen($m[0]));
                $plain = html_entity_decode(strip_tags(preg_replace('/<tg-emoji[^>]*>.*?<\/tg-emoji>/u', '', $rest)), ENT_QUOTES, 'UTF-8');
                $rtl = $m[2] !== '' || (preg_match('/\p{L}/u', $plain, $lm) && preg_match('/\p{Arabic}/u', $lm[0]));
                $dot = preg_match('/emoji-id="(\d+)"/', $m[3], $em) && isset(self::DOTS[$em[1]]);
                $had = strlen($m[0]) - strlen(rtrim($m[0], " \t"));
                $gap = $rest !== '' && !str_starts_with($rest, '</blockquote') ? str_repeat(' ', $quote && $rtl ? ($dot ? $quoteGap : 4) : ($rtl ? max(1, min(2, $had)) : 1)) : '';
                $line = $m[1] . $m[2] . $m[3] . $gap . $rest;
            }
            $depth = max(0, $depth + preg_match_all('/<blockquote/', $line) - substr_count($line, '</blockquote>'));
            if ($last >= 0 && $i > $last && trim(strip_tags($line)) !== '') {
                $line = "\u{200E}" . $line . (preg_match('/\p{Arabic}/u', strip_tags(preg_replace('/<tg-emoji[^>]*>.*?<\/tg-emoji>/u', '', $line))) ? "\u{200F}" : '');
            }
        }
        unset($line);
        return implode("\n", $lines);
    }

    public static function stripPremium(string $text): string
    {
        return preg_replace('/<tg-emoji[^>]*>.*?<\/tg-emoji>[ \t]*/us', '', $text);
    }

    public static function splitLeadingEmoji(string $text): array
    {
        $text = trim($text);
        $pos = mb_strpos($text, ' ');
        if ($pos === false) {
            return ['', $text];
        }
        $head = mb_substr($text, 0, $pos);
        $rest = trim(mb_substr($text, $pos + 1));
        if ($rest !== '' && !preg_match('/[\p{L}\p{N}\p{P}]/u', $head)) {
            return [$head, $rest];
        }
        return ['', $text];
    }

    public static function button(string $key, array $o = [], array $values = []): array
    {
        $conf = (Settings::get('buttons') ?: [])[$key] ?? [];
        $def = Config::data('buttons')[$key] ?? ['text' => $key, 'style' => null];
        $text = self::fill(self::kashida(($conf['text'] ?? '') !== '' ? $conf['text'] : $def['text']), $values);
        $style = array_key_exists('style', $conf) ? $conf['style'] : $def['style'];
        if (in_array($style, ['', 'none', null], true)) {
            $style = null;
        }
        $emoji = $conf['emoji_id'] ?? null;
        [$lead, $rest] = self::splitLeadingEmoji($text);
        if (!$emoji && $lead !== '') {
            $emoji = self::emojiId($lead);
        }
        if ($emoji && $lead !== '') {
            $text = $rest;
        } elseif (!$emoji && $lead !== '' && self::$strip && preg_match('/^' . self::EMO . '$/u', $lead)) {
            $text = $rest;
        } elseif (!$emoji && ($solo = self::emojiId(trim($text)))) {
            $emoji = $solo;
            $text = "\u{3164}";
        }
        $b = ['text' => $text];
        if (!empty($o['url'])) {
            $b['url'] = $o['url'];
        } elseif (array_key_exists('copy', $o)) {
            $b['copy_text'] = ['text' => mb_substr((string)$o['copy'], 0, 256)];
        } else {
            $b['callback_data'] = $o['cb'] ?? 'noop';
        }
        if ($emoji) {
            $b['icon_custom_emoji_id'] = (string)$emoji;
        }
        if ($style) {
            $b['style'] = $style;
        }
        return $b;
    }

    public static function raw(string $text, string $cb, ?string $style = null): array
    {
        $b = ['text' => $text, 'callback_data' => $cb];
        if ($style) {
            $b['style'] = $style;
        }
        return $b;
    }

    public static function kb(array $rows): array
    {
        return ['inline_keyboard' => array_values(array_filter($rows, fn($r) => !empty($r)))];
    }

    public static function channelUrl(): string
    {
        return (string)(Settings::get('channel_url') ?: Config::get('CHANNEL_URL'));
    }

    public static function footerRows(): array
    {
        $rows = [];
        foreach (Settings::get('extra_buttons') ?: [] as $b) {
            if (!empty($b['text']) && !empty($b['url'])) {
                $btn = ['text' => (string)$b['text'], 'url' => (string)$b['url']];
                if (!empty($b['icon'])) {
                    $btn['icon_custom_emoji_id'] = (string)$b['icon'];
                }
                if (!empty($b['style']) && $b['style'] !== 'none') {
                    $btn['style'] = $b['style'];
                }
                $rows[] = [self::iconize($btn)];
            }
        }
        $row = [];
        $u = TG::username();
        if ($u !== '') {
            $row[] = self::button('btn_add_group', ['url' => "https://t.me/$u?startgroup=true&admin=change_info+delete_messages+restrict_members+invite_users+pin_messages+manage_chat"]);
        }
        if (($c = self::channelUrl()) !== '') {
            $row[] = self::button('btn_channel', ['url' => $c]);
        }
        if ($row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function footer(array $extra = []): ?array
    {
        $rows = array_merge($extra, self::footerRows());
        return $rows ? self::kb($rows) : null;
    }

    public static function fullName(array $u): string
    {
        return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['title'] ?? 'کاربر');
    }

    public static function mention(array $u): string
    {
        return '<a href="tg://user?id=' . $u['id'] . '">' . self::esc(self::fullName($u)) . '</a>';
    }

    public static function uname(?string $u): string
    {
        return $u ? '@' . self::esc($u) : '—';
    }
}
