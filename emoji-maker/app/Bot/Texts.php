<?php

declare(strict_types=1);

namespace EmojiMaker\Bot;

/** Persian user interface texts and small formatting helpers. */
final class Texts
{
    public const MENU_NEW = '🎨 ساخت ایموجی جدید';
    public const MENU_TEMPLATES = '✨ قالب‌های آماده پریمیوم';
    public const MENU_IMAGE = '🖼 ساخت از روی عکس یا لوگو';
    public const MENU_TEXT = '🔤 ساخت از روی متن';
    public const MENU_ANIM = '🎞 انتخاب انیمیشن';
    public const MENU_BATCH = '📦 ساخت پک چندتایی';
    public const MENU_PROJECTS = '🗂 پروژه‌ها و خروجی‌های من';
    public const MENU_HELP = '❓ راهنما';

    public const MENU = [
        [self::MENU_NEW, self::MENU_TEMPLATES],
        [self::MENU_IMAGE, self::MENU_TEXT],
        [self::MENU_ANIM, self::MENU_BATCH],
        [self::MENU_PROJECTS, self::MENU_HELP],
    ];

    public const WELCOME = "سلام %s 👋\n\nبه ربات ساخت <b>ایموجی پریمیوم تلگرام</b> خوش آمدی.\n"
        . "اینجا می‌توانی از متن، لوگو یا قالب‌های آماده‌ی حرفه‌ای، ایموجی متحرک واقعی (WEBM و TGS) بسازی و همین‌جا پک ایموجی خودت را منتشر کنی.\n\n"
        . "از منوی پایین شروع کن 👇";

    public const HELP = "<b>راهنمای ربات</b>\n\n"
        . "✨ <b>قالب‌های آماده پریمیوم:</b> ایموجی‌های متحرک حرفه‌ای؛ لوگو یا متن و رنگ برندت را بگذار.\n"
        . "🎨 <b>ساخت ایموجی جدید:</b> متن و/یا لوگو + رنگ، فونت، دورخط، جلوه، قاب و انیمیشن دلخواه.\n"
        . "🖼 <b>از روی عکس یا لوگو:</b> عکس را به‌صورت فایل (PNG شفاف بهتر است) یا عکس معمولی بفرست.\n"
        . "🔤 <b>از روی متن:</b> فارسی و انگلیسی کامل پشتیبانی می‌شود (حروف چسبیده و راست‌به‌چپ).\n"
        . "🎞 <b>انیمیشن:</b> تپش، چرخش، درخشش، لرزش، پرش، تایپ، نمودار، فلش، مدار و … و ترکیب آن‌ها.\n"
        . "📦 <b>پک چندتایی:</b> ۱ تا ۱۰۰ ایموجی متنوع بدون تکرار، با پیش‌نمایش شبکه‌ای.\n"
        . "🗂 <b>پروژه‌ها:</b> دیدن، ساخت دوباره، دانلود ZIP و انتشار.\n\n"
        . "<b>فرمت‌ها:</b> WEBM (ویدیویی ۱۰۰×۱۰۰، تا ۳ ثانیه، حداکثر ۲۵۶KB)، TGS (برداری ۵۱۲، ۶۰ فریم، حداکثر ۶۴KB) و PNG ثابت.\n"
        . "هر خروجی با استاندارد تلگرام بررسی می‌شود.\n\n"
        . "<b>انتشار پک:</b> بعد از ساخت، دکمه‌ی «📤 انتشار به‌صورت پک ایموجی» را بزن؛ لینک افزودن پک برایت فرستاده می‌شود.\n"
        . "استفاده از ایموجی سفارشی در پیام‌ها به تلگرام پریمیوم نیاز دارد.\n\n"
        . "دستورها: /start /menu /templates /projects /cancel /help";

    public const BLOCKED = '⛔️ دسترسی شما به ربات محدود شده است.';
    public const RATE_LIMIT = '⏳ کمی آهسته‌تر 🙂 چند ثانیه بعد دوباره امتحان کن.';
    public const CANCELLED = 'لغو شد. از منوی پایین ادامه بده.';
    public const UNKNOWN = 'متوجه نشدم 🤔 از منوی پایین یکی از گزینه‌ها را انتخاب کن.';
    public const EXPIRED = 'این دکمه قدیمی است یا متعلق به شما نیست.';
    public const GROUP_ONLY_PRIVATE = 'این ربات فقط در گفت‌وگوی خصوصی کار می‌کند.';
    public const QUEUED = "⏳ درخواستت در صف ساخت قرار گرفت (کار #%s).\nپیشرفت کار همین‌جا نمایش داده می‌شود.";
    public const QUOTA = '⚠️ سهمیه‌ی ساخت امروزت تمام شده است (%s از %s). فردا دوباره امتحان کن.';
    public const TOO_MANY_JOBS = '⚠️ الان %s کار در صف داری؛ صبر کن تا یکی تمام شود.';
    public const BACK = '⬅️ بازگشت';
    public const DONE = '✅ تأیید';

    /** Western digits → Persian digits. */
    public static function fa(int|float|string $s): string
    {
        return strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }

    /** HTML escaping for user supplied strings in parse_mode HTML messages. */
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function cut(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }

    /** "2026-10-22 13:05:00" → "۱۴۰۵/۰۷/۳۰" (Solar Hijri calendar). */
    public static function date(?string $datetime, bool $withTime = false): string
    {
        $ts = $datetime !== null && $datetime !== '' ? strtotime($datetime) : false;
        if ($ts === false) {
            return '';
        }
        [$y, $m, $d] = self::jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        $out = sprintf('%04d/%02d/%02d', $y, $m, $d) . ($withTime ? ' ' . date('H:i', $ts) : '');
        return strtr($out, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /**
     * Gregorian → Solar Hijri (33-year cycle arithmetic, valid for the
     * Gregorian years 1600..3000).
     * @return array{0:int,1:int,2:int}
     */
    public static function jalali(int $gy, int $gm, int $gd): array
    {
        $before = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $before[$gm - 1];
        $jy = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + $days % 31];
        }
        return [$jy, 7 + intdiv($days - 186, 30), 1 + ($days - 186) % 30];
    }
}
