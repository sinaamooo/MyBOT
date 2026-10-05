<?php

declare(strict_types=1);

namespace App;

/**
 * Every message the bot sends, as an HTML template editable from /panel.
 * {placeholders} are replaced with already-escaped values.
 */
final class Texts
{
    public const DEFS = [
        'welcome' => [
            'title' => 'خوش‌آمد (/start)',
            'vars' => '{name}',
            'default' => "👋 سلام {name}، خوش آمدید!\n\n🤖 من ربات تحلیلگر هستم. نام ارز را بفرستید تا تحلیل تکنیکال کامل با چارت دریافت کنید.\n\nمثال: <code>تحلیل BTC</code>\nراهنما: /help",
        ],
        'help' => [
            'title' => 'راهنما',
            'vars' => '{days} {quota}',
            'default' => "🤖 <b>ربات تحلیلگر</b>\n\nبرای دریافت تحلیل، نام ارز را بفرستید:\n• <code>تحلیل BTC</code>\n• <code>تحلیل ETH 1h</code>\n• <code>تحلیل سولانا روزانه</code>\n\nتایم‌فریم‌ها: 15m، 30m، 1h، 4h (پیش‌فرض)، 1d، 1w\n📅 روزهای تحلیل: {days}\n🎟 سهمیه هر کاربر: {quota} تحلیل در هفته\nبرای دیدن سهمیه بنویسید: <code>سهمیه</code>",
        ],
        'waiting' => [
            'title' => 'در حال تحلیل',
            'vars' => '{symbol} {tf}',
            'default' => "⏳ در حال تحلیل <b>{symbol}</b> در تایم {tf}…\nبررسی ساختار، اوردر بلاک‌ها و نقدینگی کمی زمان می‌برد.",
        ],
        'published' => [
            'title' => 'اطلاع انتشار (دایرکت)',
            'vars' => '{symbol} {tf} {post_link} {remaining}',
            'default' => "✅ تحلیل <b>{symbol}</b> ({tf}) انجام شد و در کانال منتشر شد.\n👈 {post_link}\n🎟 تحلیل باقی‌مانده شما در این هفته: {remaining}",
        ],
        'quota_left' => [
            'title' => 'سهمیه باقی‌مانده',
            'vars' => '{left} {limit}',
            'default' => '🎟 این هفته {left} تحلیل دیگر می‌توانید بگیرید (از {limit}).',
        ],
        'quota_done' => [
            'title' => 'سهمیه تمام شده',
            'vars' => '{limit}',
            'default' => "🎟 سهمیه تحلیل شما این هفته تمام شده است ({limit} تحلیل).\nاز شنبه آینده دوباره می‌توانید درخواست بدهید.",
        ],
        'closed' => [
            'title' => 'روز غیر تحلیل',
            'vars' => '{days} {next_day} {wait}',
            'default' => "⏰ تحلیل ارزها فقط روزهای <b>{days}</b> انجام می‌شود.\nروز تحلیل بعدی: {next_day} ({wait} روز دیگر)\nهمان روز پیام بدهید، مثلاً: <b>تحلیل BTC</b>",
        ],
        'paused' => [
            'title' => 'ربات متوقف',
            'vars' => '',
            'default' => '⏸ ربات تحلیل موقتاً غیرفعال است. لطفاً بعداً دوباره پیام بدهید.',
        ],
        'not_found' => [
            'title' => 'ارز پیدا نشد',
            'vars' => '{symbol}',
            'default' => '❓ ارز <b>{symbol}</b> در صرافی‌ها پیدا نشد. نماد را بررسی کنید (مثلاً BTC، ETH، SOL).',
        ],
        'error' => [
            'title' => 'خطا',
            'vars' => '',
            'default' => '⚠️ در انجام تحلیل خطایی رخ داد. لطفاً چند دقیقه دیگر دوباره امتحان کنید.',
        ],
        'private_hint' => [
            'title' => 'راهنمای پیام نامفهوم',
            'vars' => '',
            'default' => "برای تحلیل بنویسید مثلاً: <b>تحلیل BTC</b> یا <b>تحلیل ETH 1h</b>\nراهنما: /help",
        ],
        'caption' => [
            'title' => 'کپشن تحلیل',
            'vars' => '{side_icon} {base} {quote} {tf} {scenario} {confidence} {price} {change} {entry_low} {entry_high} {stop} {stop_pct} {tp1} {tp1_pct} {tp1_rr} {tp2} {tp2_pct} {tp2_rr} {tp3} {tp3_pct} {tp3_rr} {rr} {summary} {summary_text} {invalidation} {reasons} {liquidity}',
            'default' => "{side_icon} <b>#{base} / {quote}</b> | تایم {tf}\n<b>{scenario}</b> | اعتبار: {confidence}٪\n💵 قیمت فعلی: <code>{price}</code> ({change})\n\n"
                . "🎯 <b>ورود:</b> <code>{entry_low}</code> تا <code>{entry_high}</code>\n⛔️ <b>حد ضرر:</b> <code>{stop}</code> ({stop_pct})\n"
                . "✅ <b>تارگت ۱:</b> <code>{tp1}</code> ({tp1_pct} | R {tp1_rr})\n✅ <b>تارگت ۲:</b> <code>{tp2}</code> ({tp2_pct} | R {tp2_rr})\n✅ <b>تارگت ۳:</b> <code>{tp3}</code> ({tp3_pct} | R {tp3_rr})\n"
                . "⚖️ ریسک به ریوارد: <b>1:{rr}</b>\n\n{summary}\n\n📌 <b>دلایل:</b>\n<blockquote expandable>{reasons}</blockquote>\n\n{liquidity}\n\n⚠️ <i>تحلیل آموزشی است، نه توصیه مالی. مدیریت سرمایه را رعایت کنید.</i>",
        ],
        'exchange_label' => [
            'title' => 'نام صرافی روی چارت',
            'vars' => '',
            'plain' => true,
            'default' => 'OURBIT',
        ],
    ];

    public function __construct(private Storage $db)
    {
    }

    public function get(string $key): string
    {
        return (string) ($this->db->get('text_' . $key) ?? self::DEFS[$key]['default']);
    }

    public function isCustom(string $key): bool
    {
        return $this->db->get('text_' . $key) !== null;
    }

    public function set(string $key, ?string $html): void
    {
        $this->db->set('text_' . $key, $html);
    }

    /** @param array<string, string> $vars values must already be HTML-safe */
    public function render(string $key, array $vars = []): string
    {
        return self::fill($this->get($key), $vars);
    }

    public static function fill(string $template, array $vars): string
    {
        $out = strtr($template, array_combine(array_map(static fn ($k) => '{' . $k . '}', array_keys($vars)), array_values($vars)) ?: []);
        // Tidy up blank lines left by empty placeholders
        $out = preg_replace("/[ \t]+\n/u", "\n", $out);
        $out = preg_replace("/\n{3,}/u", "\n\n", $out);
        return trim($out);
    }

    /** Sample values used for previews in the panel. */
    public static function sampleVars(): array
    {
        return [
            'name' => 'علی', 'symbol' => 'BTC', 'tf' => '۴ ساعته', 'days' => 'شنبه و یکشنبه', 'quota' => '۲',
            'post_link' => '<a href="https://t.me/telegram">مشاهده تحلیل</a>', 'remaining' => '۱', 'left' => '۱', 'limit' => '۲',
            'next_day' => 'شنبه', 'wait' => '۳',
            'side_icon' => '🟢', 'base' => 'BTC', 'quote' => 'USDT', 'scenario' => 'سناریوی صعودی (Long)', 'confidence' => '۷۲',
            'price' => '98,450.00', 'change' => "\u{200E}+1.2%", 'entry_low' => '96,900.00', 'entry_high' => '97,700.00',
            'stop' => '95,750.00', 'stop_pct' => "\u{200E}-1.6%",
            'tp1' => '100,100.00', 'tp1_pct' => "\u{200E}+3.0%", 'tp1_rr' => '1.9',
            'tp2' => '103,900.00', 'tp2_pct' => "\u{200E}+6.9%", 'tp2_rr' => '4.3',
            'tp3' => '106,700.00', 'tp3_pct' => "\u{200E}+9.8%", 'tp3_rr' => '6.0', 'rr' => '4.3',
            'summary' => "🧠 <b>خلاصه:</b> روند در تایم‌های بالا صعودی است و انتظار ادامه حرکت پس از پولبک به زون حمایتی را داریم.\n❌ <b>ابطال:</b> تثبیت زیر 95,750",
            'summary_text' => 'روند در تایم‌های بالا صعودی است و انتظار ادامه حرکت پس از پولبک به زون حمایتی را داریم.',
            'invalidation' => 'تثبیت زیر 95,750',
            'reasons' => "• روند صعودی در تایم روزانه\n• شکست ساختار (BOS) صعودی\n• واکنش به اوردر بلاک صعودی\n• نقدینگی بالای سقف‌های برابر",
            'liquidity' => '💧 <b>نقدینگی:</b> BSL <code>103,980.43</code> | SSL <code>92,300.00</code>',
        ];
    }
}
