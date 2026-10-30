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

    public static function fill(string $template, array $values = []): string
    {
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
                if ($closing) {
                    if ($out) {
                        $out[count($out) - 1] .= implode('', $closing);
                    } else {
                        $carry .= implode('', $closing);
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

    public static function emojiMap(string $text): string
    {
        $map = Settings::get('emoji_map') ?: [];
        if (!$map || $text === '') {
            return $text;
        }
        $keys = array_keys($map);
        usort($keys, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $lookup = [];
        $parts = [];
        foreach ($keys as $k) {
            $bare = str_replace("\u{FE0F}", '', (string)$k);
            if ($bare === '') {
                continue;
            }
            $lookup[$bare] = $map[$k];
            $parts[] = implode('', array_map(fn($c) => preg_quote($c, '/') . "\u{FE0F}?", mb_str_split($bare)));
        }
        if (!$parts) {
            return $text;
        }
        $rx = '/' . implode('|', $parts) . '/u';
        $chunks = preg_split('/(<[^>]+>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $skip = 0;
        $out = '';
        foreach ($chunks as $c) {
            if ($c !== '' && $c[0] === '<' && str_ends_with($c, '>')) {
                if (preg_match('/^<\/?\s*([a-zA-Z][a-zA-Z0-9-]*)/', $c, $nm) && in_array(strtolower($nm[1]), ['tg-emoji', 'code', 'pre', 'a'], true)) {
                    $skip = max(0, $skip + (str_starts_with($c, '</') ? -1 : 1));
                }
                $out .= $c;
                continue;
            }
            if ($skip) {
                $out .= $c;
                continue;
            }
            $out .= preg_replace_callback($rx, function ($m) use ($lookup) {
                $id = $lookup[str_replace("\u{FE0F}", '', $m[0])] ?? '';
                return '<tg-emoji emoji-id="' . $id . '">' . $m[0] . '</tg-emoji>';
            }, $c);
        }
        return $out;
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
            $map = Settings::get('emoji_map') ?: [];
            $emoji = $map[$lead] ?? $map[str_replace("\u{FE0F}", '', $lead)] ?? null;
        }
        if ($emoji && $lead !== '') {
            $text = $rest;
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
        $row = [];
        $u = TG::username();
        if ($u !== '') {
            $row[] = self::button('btn_add_group', ['url' => "https://t.me/$u?startgroup=true"]);
        }
        if (($c = self::channelUrl()) !== '') {
            $row[] = self::button('btn_channel', ['url' => $c]);
        }
        return $row ? [$row] : [];
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
