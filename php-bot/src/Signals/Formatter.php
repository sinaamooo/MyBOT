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
     * Renders a premium/custom emoji via Telegram's <tg-emoji> HTML tag when
     * enabled and configured for this slot, falling back to a plain emoji
     * otherwise - including when the bot owner's account has no Telegram
     * Premium, since Telegram would otherwise reject the whole message.
     *
     * @param array<string, mixed> $params
     */
    public static function premiumEmoji(array $params, string $slot, string $fallback): string
    {
        if (empty($params['premium_emoji_enabled'])) {
            return $fallback;
        }
        $key = $slot === 'pump' ? 'premium_emoji_pump_id' : 'premium_emoji_signal_id';
        $id = (string) ($params[$key] ?? '');
        if ($id === '') {
            return $fallback;
        }
        return '<tg-emoji emoji-id="' . htmlspecialchars($id, ENT_QUOTES) . '">' . $fallback . '</tg-emoji>';
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
        return ['symbol', 'side', 'side_emoji', 'score', 'entry', 'stop_loss', 'tp1', 'tp2', 'tp3', 'leverage', 'rr', 'timeframe', 'trend', 'regime', 'quote', 'header_emoji'];
    }

    /** Rejects a template that references a placeholder we don't provide (e.g. a typo). */
    public static function validateSignalTemplate(string $template): bool
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $template, $matches);
        $known = array_flip(self::knownSignalPlaceholders());
        foreach ($matches[1] as $name) {
            if (!isset($known[$name])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, mixed> $data */
    private static function render(string $template, array $data): string
    {
        $search = [];
        $replace = [];
        foreach ($data as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = (string) $value;
        }
        return str_replace($search, $replace, $template);
    }
}
