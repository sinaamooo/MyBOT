<?php
declare(strict_types=1);

namespace Nikto\Telegram;

/**
 * تبدیل قالب‌بندی تلگرام (entities) به HTML.
 *
 * وقتی کاربر متنی را با بولد یا نقل‌قول می‌فرستد، تلگرام متن خام و فهرست
 * قالب‌ها را جدا می‌فرستد. این کلاس آن‌ها را دوباره به HTML تبدیل می‌کند
 * تا همان قالب‌بندی در کپشن کارت‌ها دیده شود.
 */
final class Entities
{
    /** @param array<int,array<string,mixed>> $entities */
    public static function toHtml(string $text, array $entities): string
    {
        if ($text === '') {
            return '';
        }
        if ($entities === []) {
            return self::escape($text);
        }

        // تلگرام موقعیت‌ها را بر اساس UTF-16 می‌شمارد
        $chars = self::toUtf16Units($text);
        $length = count($chars);

        /** @var array<int,string[]> $opens */
        $opens = [];
        /** @var array<int,string[]> $closes */
        $closes = [];

        foreach ($entities as $entity) {
            $type = (string) ($entity['type'] ?? '');
            $offset = (int) ($entity['offset'] ?? 0);
            $len = (int) ($entity['length'] ?? 0);
            if ($len <= 0 || $offset < 0 || $offset >= $length) {
                continue;
            }
            $end = min($length, $offset + $len);

            [$open, $close] = self::tags($type, $entity);
            if ($open === '') {
                continue;
            }
            $opens[$offset][] = $open;
            // تگ‌های تودرتو باید به ترتیب معکوس بسته شوند
            $closes[$end] ??= [];
            array_unshift($closes[$end], $close);
        }

        $out = '';
        for ($i = 0; $i <= $length; $i++) {
            foreach ($closes[$i] ?? [] as $tag) {
                $out .= $tag;
            }
            if ($i === $length) {
                break;
            }
            foreach ($opens[$i] ?? [] as $tag) {
                $out .= $tag;
            }
            $out .= self::escape($chars[$i]);
        }

        return $out;
    }

    /** @return array{0:string,1:string} */
    private static function tags(string $type, array $entity): array
    {
        return match ($type) {
            'bold'                => ['<b>', '</b>'],
            'italic'              => ['<i>', '</i>'],
            'underline'           => ['<u>', '</u>'],
            'strikethrough'       => ['<s>', '</s>'],
            'spoiler'             => ['<tg-spoiler>', '</tg-spoiler>'],
            'code'                => ['<code>', '</code>'],
            'pre'                 => self::preTags($entity),
            'blockquote'          => ['<blockquote>', '</blockquote>'],
            'expandable_blockquote' => ['<blockquote expandable>', '</blockquote>'],
            'text_link'           => ['<a href="' . self::escape((string) ($entity['url'] ?? '')) . '">', '</a>'],
            default               => ['', ''],
        };
    }

    /** @return array{0:string,1:string} */
    private static function preTags(array $entity): array
    {
        $language = (string) ($entity['language'] ?? '');

        return $language !== ''
            ? ['<pre><code class="language-' . self::escape($language) . '">', '</code></pre>']
            : ['<pre>', '</pre>'];
    }

    /** شکستن متن به واحدهای UTF-16 (هم‌راستا با شمارش تلگرام) */
    private static function toUtf16Units(string $text): array
    {
        $units = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code !== false && $code > 0xFFFF) {
                // کاراکترهای خارج از BMP (مثل ایموجی) دو واحد حساب می‌شوند
                $units[] = $char;
                $units[] = '';
            } else {
                $units[] = $char;
            }
        }

        return $units;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** آیا متن از قبل شامل تگ HTML است؟ */
    public static function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('#</?(b|strong|i|em|u|s|code|pre|a|blockquote|tg-spoiler)\b[^>]*>#i', $text);
    }
}
