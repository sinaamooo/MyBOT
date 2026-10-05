<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Converts a Telegram message (text + entities) to HTML so formatting typed in the
 * Telegram app (bold, quote, spoiler, links, premium emoji, ...) can be stored and resent.
 */
final class Entities
{
    public static function toHtml(string $text, array $entities): string
    {
        // Entity offsets are in UTF-16 code units.
        $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $len = intdiv(strlen($u16), 2);
        $opens = [];
        $closes = [];
        usort($entities, static fn ($a, $b) => [$a['offset'], -$a['length']] <=> [$b['offset'], -$b['length']]);
        foreach ($entities as $i => $e) {
            $tags = self::tags($e);
            if ($tags === null) {
                continue;
            }
            $start = (int) $e['offset'];
            $end = min($len, $start + (int) $e['length']);
            $opens[$start][] = [$i, $tags[0]];
            $closes[$end][] = [$i, $tags[1]];
        }

        $html = '';
        $stack = [];
        $segStart = 0;
        for ($pos = 0; $pos <= $len; $pos++) {
            if (!isset($opens[$pos]) && !isset($closes[$pos])) {
                continue;
            }
            $html .= self::escape(self::slice($u16, $segStart, $pos));
            $segStart = $pos;
            if (isset($closes[$pos])) {
                // Close in reverse opening order; reopen anything that was closed only to keep nesting valid.
                $ending = array_column($closes[$pos], 1, 0);
                $reopen = [];
                while ($ending && $stack) {
                    [$id, $close, $open] = array_pop($stack);
                    $html .= $close;
                    if (isset($ending[$id])) {
                        unset($ending[$id]);
                    } else {
                        $reopen[] = [$id, $close, $open];
                    }
                }
                foreach (array_reverse($reopen) as $r) {
                    $html .= $r[2];
                    $stack[] = $r;
                }
            }
            foreach ($opens[$pos] ?? [] as [$id, $open]) {
                $html .= $open;
                $closeTag = self::closingFor($open);
                $stack[] = [$id, $closeTag, $open];
            }
        }
        $html .= self::escape(self::slice($u16, $segStart, $len));
        while ($stack) {
            $html .= array_pop($stack)[1];
        }
        return $html;
    }

    /** @return array{0: string, 1: string}|null opening and closing tag */
    private static function tags(array $e): ?array
    {
        $attr = static fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return match ($e['type'] ?? '') {
            'bold' => ['<b>', '</b>'],
            'italic' => ['<i>', '</i>'],
            'underline' => ['<u>', '</u>'],
            'strikethrough' => ['<s>', '</s>'],
            'spoiler' => ['<tg-spoiler>', '</tg-spoiler>'],
            'code' => ['<code>', '</code>'],
            'pre' => !empty($e['language'])
                ? ['<pre><code class="language-' . $attr((string) $e['language']) . '">', '</code></pre>']
                : ['<pre>', '</pre>'],
            'text_link' => ['<a href="' . $attr((string) ($e['url'] ?? '')) . '">', '</a>'],
            'text_mention' => ['<a href="tg://user?id=' . (int) ($e['user']['id'] ?? 0) . '">', '</a>'],
            'custom_emoji' => ['<tg-emoji emoji-id="' . $attr((string) ($e['custom_emoji_id'] ?? '')) . '">', '</tg-emoji>'],
            'blockquote' => ['<blockquote>', '</blockquote>'],
            'expandable_blockquote' => ['<blockquote expandable>', '</blockquote>'],
            default => null,
        };
    }

    private static function closingFor(string $open): string
    {
        if (str_starts_with($open, '<pre><code')) {
            return '</code></pre>';
        }
        preg_match('/^<([a-z\-]+)/', $open, $m);
        return '</' . $m[1] . '>';
    }

    private static function slice(string $u16, int $from, int $to): string
    {
        return $to > $from ? mb_convert_encoding(substr($u16, $from * 2, ($to - $from) * 2), 'UTF-8', 'UTF-16LE') : '';
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Removes premium emoji tags but keeps their fallback emoji. */
    public static function stripCustomEmoji(string $html): string
    {
        return preg_replace('~<tg-emoji[^>]*>(.*?)</tg-emoji>~su', '$1', $html) ?? $html;
    }
}
