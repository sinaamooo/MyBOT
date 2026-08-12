<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminService;
use App\Services\SignalService;
use App\Services\StatisticsService;
use App\Telegram\BotContext;

final class StartHandler
{
    private const WELCOME = "👋 به ربات سیگنال فیوچرز خوش آمدید.\n\n"
        . "این ربات بازار را اسکن می‌کند و سیگنال‌های LONG/SHORT فیوچرز را در "
        . "کانال تنظیم‌شده منتشر می‌کند. هیچ‌وقت معامله واقعی انجام نمی‌دهد.\n\n"
        . "⚠️ فقط جنبه آموزشی/سیگنال دارد - ریسک خودتان را مدیریت کنید.\n\n"
        . 'دستورات: /help /status /signals /stats';

    private const HELP = "<b>دستورات</b>\n"
        . "/start - پیام خوش‌آمدگویی\n"
        . "/help - همین پیام\n"
        . "/status - وضعیت ربات و اسکنر\n"
        . "/signals - سیگنال‌های فعال فعلی\n"
        . "/stats - آمار امروز\n\n"
        . 'ادمین‌ها: /admin - باز کردن پنل مدیریت';

    public static function start(int $chatId, int $userId, BotContext $ctx): void
    {
        $text = self::WELCOME;
        if (AdminService::isAdmin($userId)) {
            $text .= "\n\n🔐 شما ادمین هستید. برای باز کردن پنل مدیریت /admin را بفرستید.";
        }
        $ctx->telegram->sendMessage($chatId, $text);
    }

    public static function help(int $chatId, BotContext $ctx): void
    {
        $ctx->telegram->sendMessage($chatId, self::HELP);
    }

    public static function status(int $chatId, BotContext $ctx): void
    {
        $params = \App\Services\SettingsService::getAll();
        $active = SignalService::countActive();
        $state = empty($params['scanner_running']) ? 'متوقف' : 'در حال اجرا';
        $ctx->telegram->sendMessage(
            $chatId,
            "🖥 ربات: 🟢 آنلاین\n📡 اسکنر: {$state}\n📋 سیگنال‌های فعال: {$active}"
        );
    }

    public static function signals(int $chatId, BotContext $ctx): void
    {
        $signals = SignalService::getActive();
        if ($signals === []) {
            $ctx->telegram->sendMessage($chatId, 'در حال حاضر سیگنال فعالی وجود ندارد.');
            return;
        }
        $lines = array_map(
            fn($s) => "• {$s['symbol']} {$s['side']} - {$s['status']} (امتیاز " . round((float) $s['score']) . ')',
            array_slice($signals, 0, 20)
        );
        $ctx->telegram->sendMessage($chatId, "📋 <b>سیگنال‌های فعال</b>\n\n" . implode("\n", $lines));
    }

    public static function stats(int $chatId, BotContext $ctx): void
    {
        $stats = StatisticsService::refreshToday();
        $ctx->telegram->sendMessage(
            $chatId,
            "📈 <b>امروز</b>\n\n"
            . "سیگنال‌ها: {$stats['total_signals']}\n"
            . "TP1: {$stats['tp1_count']}  TP2: {$stats['tp2_count']}  TP3: {$stats['tp3_count']}\n"
            . "استاپ‌خورده: {$stats['stopped_count']}  ریسک‌فری: {$stats['risk_free_count']}\n"
            . "نرخ برد: {$stats['win_rate']}%"
        );
    }

    public static function denied(int $chatId, BotContext $ctx): void
    {
        $ctx->telegram->sendMessage($chatId, '⛔ دسترسی رد شد. این پنل فقط برای ادمین‌هاست.');
    }

    public static function sigDetails(string $callbackQueryId, int $userId, int $signalId, BotContext $ctx): void
    {
        $signal = SignalService::get($signalId);
        if ($signal === null) {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, 'سیگنال یافت نشد.', true);
            return;
        }
        $reasons = $signal['reasons'] ? json_decode((string) $signal['reasons'], true) : [];
        $text = "📋 {$signal['symbol']} {$signal['side']}\n"
            . "امتیاز: " . round((float) $signal['score']) . "%  |  ریسک: {$signal['risk_score']}  |  ریسک‌ریوارد 1:{$signal['rr']}\n"
            . "ورود: {$signal['entry']}\nحد ضرر: {$signal['stop_loss']}\n"
            . "TP1: {$signal['tp1']}  TP2: {$signal['tp2']}  TP3: {$signal['tp3']}\n"
            . "وضعیت: {$signal['status']}\n\n"
            . "دلایل:\n" . implode("\n", array_map(fn($r) => "• {$r}", $reasons));

        $sent = $ctx->telegram->sendMessage($userId, $text);
        if ($sent !== null) {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, 'به پیام خصوصی شما ارسال شد ✅');
        } else {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, mb_substr($text, 0, 200), true);
        }
    }
}
