<?php

declare(strict_types=1);

namespace App\Signals;

use App\Support\Num;

final class Formatter
{
    private const SIDE_EMOJI = ['LONG' => '🟢', 'SHORT' => '🔴'];

    private const UPDATE_TEMPLATES = [
        'TP1' => "✅ {symbol} {side}\n\nTP1 زده شد 🎯\n\nهدف سود اول محقق شد.\nقیمت: {price}",
        'TP2' => "🔥 TP2 زده شد\n\n{symbol} {side}\n\nهدف سود دوم محقق شد.\nقیمت: {price}",
        'TP3' => "🏆 TP3 زده شد\n\n{symbol} {side}\n\nمعامله تکمیل شد.\nقیمت: {price}",
        'STOPPED' => "❌ حد ضرر خورد\n\n{symbol} {side}\n\nمعامله بسته شد.\nقیمت: {price}",
        'RISK_FREE' => "🛡 ریسک‌فری\n\n{symbol} {side}\n\nحد ضرر → نقطه ورود\nحد ضرر اولیه محافظت شد.",
        'TRAILING_STOP' => "🔧 تریلینگ استاپ به‌روزرسانی شد\n\n{symbol} {side}\n\nحد ضرر جدید: {price}",
        'CANCELLED' => "⚪️ سیگنال لغو شد\n\n{symbol} {side}",
    ];

    /** Persian motivational/discipline quotes for the {quote} template placeholder. */
    private const QUOTES = [
        'صبر و انضباط، سرمایه اصلی یک معامله‌گر است.',
        'ریسک را مدیریت کن، سود خودش می‌آید.',
        'بازار همیشه هست؛ سرمایه‌ات را حفظ کن تا فردا هم باشی.',
        'یک معامله بد، پایان راه نیست؛ نداشتن مدیریت ریسک هست.',
        'حد ضرر رعایت نشده، یک ضرر ساده را به فاجعه تبدیل می‌کند.',
        'در بازارهای مالی، بازنده‌ها عجله دارند و برنده‌ها صبر.',
        'اهرم بالا فقط ریسک را بزرگ‌تر می‌کند، نه سود تضمینی را.',
        'نظم در اجرا، مهم‌تر از دقت در تحلیل است.',
        'هیچ سیگنالی ۱۰۰٪ قطعی نیست؛ همیشه ریسک خود را کنترل کن.',
        'معامله‌گر حرفه‌ای، ضررهایش را کوچک و سودهایش را بزرگ نگه می‌دارد.',
        'قبل از ورود به معامله، نقطه خروج را بشناس.',
        'ثبات در رعایت قوانین، از هر استراتژی مهم‌تر است.',
    ];

    public static function randomQuote(): string
    {
        return self::QUOTES[array_rand(self::QUOTES)];
    }

    /**
     * Bakes any custom/premium emoji the admin pasted into a template-edit
     * message into <tg-emoji> HTML tags at their exact position, so it's
     * preserved wherever they put it (front, back, middle) - no separate
     * settings screen needed, and it degrades to the plain fallback emoji
     * automatically for anyone without Telegram Premium reading it.
     *
     * @param array<int, array<string, mixed>> $entities Telegram message entities
     */
    public static function embedCustomEmoji(string $text, array $entities): string
    {
        $customEmoji = array_values(array_filter(
            $entities,
            fn(array $e) => ($e['type'] ?? '') === 'custom_emoji' && isset($e['custom_emoji_id'])
        ));
        if ($customEmoji === []) {
            return $text;
        }

        // Entity offsets are UTF-16 code units; round-trip through UTF-16LE to
        // get the matching UTF-8 byte offset so preceding surrogate-pair
        // characters (most emoji) don't throw off the position.
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $byteOffset = static function (int $utf16Offset) use ($utf16): int {
            $prefix = substr($utf16, 0, $utf16Offset * 2);
            return strlen((string) mb_convert_encoding($prefix, 'UTF-8', 'UTF-16LE'));
        };

        // Splice from the end backward so earlier byte offsets stay valid.
        usort($customEmoji, fn(array $a, array $b) => (int) $b['offset'] <=> (int) $a['offset']);

        foreach ($customEmoji as $entity) {
            $start = $byteOffset((int) $entity['offset']);
            $end = $byteOffset((int) $entity['offset'] + (int) $entity['length']);
            $fallback = substr($text, $start, $end - $start);
            $id = htmlspecialchars((string) $entity['custom_emoji_id'], ENT_QUOTES);
            $tag = '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>';
            $text = substr($text, 0, $start) . $tag . substr($text, $end);
        }

        return $text;
    }

    /** @param array<string, mixed> $data */
    public static function formatSignalMessage(string $template, array $data): string
    {
        $data['side_emoji'] ??= self::SIDE_EMOJI[$data['side'] ?? ''] ?? '';
        return self::render($template, $data);
    }

    public static function formatUpdateMessage(string $updateType, string $symbol, string $side, ?float $price = null): string
    {
        $template = self::UPDATE_TEMPLATES[$updateType] ?? '{symbol} {side}: ' . $updateType;
        $priceText = $price !== null ? Num::price($price) : '';
        return self::render($template, ['symbol' => $symbol, 'side' => $side, 'price' => $priceText]);
    }

    /** @return string[] */
    public static function knownSignalPlaceholders(): array
    {
        return ['symbol', 'side', 'side_emoji', 'score', 'entry', 'stop_loss', 'tp1', 'tp2', 'tp3', 'leverage', 'rr', 'timeframe', 'trend', 'regime', 'quote'];
    }

    /**
     * The {placeholder} names in $template that aren't recognized (e.g. a
     * typo), original casing preserved for a useful error message. Matching
     * itself is case-insensitive - {tp2}, {Tp2}, {TP2} are all the same
     * placeholder, so a template shouldn't get silently rejected (or worse,
     * saved with a dead unreplaced {TP2} sitting in the live message) just
     * because of casing.
     *
     * @return string[]
     */
    public static function findUnknownPlaceholders(string $template): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $template, $matches);
        $known = array_flip(array_map('strtolower', self::knownSignalPlaceholders()));
        $unknown = [];
        foreach ($matches[1] as $name) {
            if (!isset($known[strtolower($name)])) {
                $unknown[$name] = true;
            }
        }
        return array_keys($unknown);
    }

    /** Rejects a template that references a placeholder we don't provide (e.g. a typo). */
    public static function validateSignalTemplate(string $template): bool
    {
        return self::findUnknownPlaceholders($template) === [];
    }

    /** @param array<string, mixed> $data */
    private static function render(string $template, array $data): string
    {
        $lookup = [];
        foreach ($data as $key => $value) {
            $lookup[strtolower((string) $key)] = (string) $value;
        }
        return (string) preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn(array $m) => $lookup[strtolower($m[1])] ?? $m[0],
            $template
        );
    }
}
