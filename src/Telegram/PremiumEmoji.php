<?php
declare(strict_types=1);

namespace Nikto\Telegram;

/**
 * ایموجی پریمیوم تلگرام.
 *
 * هر جای متن که الگوی [شناسه] بیاید به تگ <tg-emoji> تبدیل می‌شود و تلگرام
 * همان ایموجی پریمیوم را در کانال نشان می‌دهد. اگر درست کنار آن یک ایموجی
 * معمولی نوشته شده باشد، همان ایموجی به‌عنوان جایگزین (برای کاربران بدون
 * پریمیوم) استفاده و از متن حذف می‌شود؛ وگرنه ایموجی پیش‌فرض می‌نشیند.
 *
 *   🔥[5368324170671202286]  →  <tg-emoji emoji-id="5368324170671202286">🔥</tg-emoji>
 *   [5368324170671202286]    →  <tg-emoji emoji-id="5368324170671202286">😎</tg-emoji>
 */
final class PremiumEmoji
{
    /** ایموجی جایگزین وقتی کاربر خودش چیزی ننوشته باشد */
    public const FALLBACK = '😎';

    /** شناسه‌ی ایموجی پریمیوم عدد بلندی است؛ حداقل طول تا با عدد معمولی اشتباه نشود */
    private const MIN_DIGITS = 8;
    private const MAX_DIGITS = 24;

    /** تبدیل [شناسه] به تگ ایموجی پریمیوم */
    public static function apply(string $text): string
    {
        if ($text === '' || !str_contains($text, '[')) {
            return $text;
        }

        $pattern = '/(\X)?\[(\d{' . self::MIN_DIGITS . ',' . self::MAX_DIGITS . '})\]/u';

        $out = preg_replace_callback(
            $pattern,
            static function (array $m): string {
                $prefix = $m[1] ?? '';
                $id = $m[2];

                if ($prefix !== '' && self::isEmoji($prefix)) {
                    return self::tag($id, $prefix);
                }

                return $prefix . self::tag($id, self::FALLBACK);
            },
            $text
        );

        return $out ?? $text;
    }

    /** ساخت تگ ایموجی پریمیوم */
    public static function tag(string $emojiId, string $fallback = self::FALLBACK): string
    {
        $id = preg_replace('/\D+/', '', $emojiId) ?? '';
        if ($id === '') {
            return $fallback;
        }
        if ($fallback === '') {
            $fallback = self::FALLBACK;
        }

        return '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>';
    }

    /** آیا متن با یک ایموجی شروع می‌شود؟ */
    public static function isEmoji(string $grapheme): bool
    {
        if ($grapheme === '') {
            return false;
        }

        // Extended_Pictographic همه‌ی ایموجی‌ها را پوشش می‌دهد؛ کیبورد عددی و
        // پرچم‌ها با بازه‌های جداگانه اضافه می‌شوند.
        if (preg_match('/^[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}\x{20E3}\x{FE0F}]/u', $grapheme) === 1) {
            return true;
        }

        $code = mb_ord(mb_substr($grapheme, 0, 1, 'UTF-8'), 'UTF-8');

        return $code !== false && ($code >= 0x2190 && $code <= 0x2BFF || $code >= 0x1F000);
    }

    /** آیا متن شامل الگوی ایموجی پریمیوم است؟ */
    public static function has(string $text): bool
    {
        return preg_match('/\[\d{' . self::MIN_DIGITS . ',' . self::MAX_DIGITS . '}\]/', $text) === 1;
    }
}
