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
        'nft' => '🎞', 'premium' => '💫', 'gold' => '🏅', 'wallet' => '👛', 'irr' => '💰'];

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
        if ($o !== null) {
            return $o;
        }
        return Config::data('texts')[$key]['text'] ?? $key;
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

    private static ?array $rx = null;

    public static function emojiTable(): array
    {
        if (self::$rx !== null) {
            return self::$rx;
        }
        $map = Settings::get('emoji_map') ?: [];
        $bad = array_flip((array)(Settings::get('emoji_invalid') ?: []));
        $keys = array_keys($map);
        usort($keys, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $lookup = [];
        $parts = [];
        foreach ($keys as $k) {
            $bare = str_replace("\u{FE0F}", '', (string)$k);
            $id = (string)$map[$k];
            if ($bare === '' || $id === '' || isset($bad[$id])) {
                continue;
            }
            $lookup[$bare] = $id;
            $parts[] = implode('', array_map(fn($c) => preg_quote($c, '/') . "\u{FE0F}?", mb_str_split($bare)));
        }
        return self::$rx = [$parts ? '/' . implode('|', $parts) . '/u' : null, $lookup];
    }

    public static function validateEmoji(): ?array
    {
        $ids = array_values(array_unique(array_map('strval', array_merge(array_values(Settings::get('emoji_map') ?: []), array_values(Config::data('badges'))))));
        $valid = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $r = TG::api('getCustomEmojiStickers', ['custom_emoji_ids' => $chunk]);
            if ($r === null) {
                return null;
            }
            foreach ($r as $st) {
                $valid[(string)($st['custom_emoji_id'] ?? '')] = true;
            }
        }
        $bad = array_values(array_filter($ids, fn($id) => !isset($valid[$id])));
        Settings::set('emoji_invalid', $bad);
        DB::kvSet('emoji_checked_at', time());
        return [count($ids) - count($bad), count($bad)];
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

    public static function emojiId(string $emoji): ?string
    {
        [, $lookup] = self::emojiTable();
        return $lookup[str_replace("\u{FE0F}", '', $emoji)] ?? null;
    }

    public static function emojiMap(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        [$rx, $lookup] = self::emojiTable();
        if (!$rx) {
            return $text;
        }
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
        }
        return $b;
    }

    public static function stripPremium(string $text): string
    {
        return preg_replace('/<tg-emoji[^>]*>(.*?)<\/tg-emoji>/us', '$1', $text);
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
        $text = self::fill(($conf['text'] ?? '') !== '' ? $conf['text'] : $def['text'], $values);
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
        foreach (Settings::get('extra_buttons') ?: [] as $b) {
            if (!empty($b['text']) && !empty($b['url'])) {
                $rows[] = [self::iconize(['text' => (string)$b['text'], 'url' => (string)$b['url']] + (!empty($b['style']) ? ['style' => $b['style']] : []))];
            }
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
