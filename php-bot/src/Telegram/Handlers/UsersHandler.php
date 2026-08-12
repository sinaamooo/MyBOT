<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminService;
use App\Services\AdminStateService;
use App\Services\LogService;
use App\Services\UserService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class UsersHandler
{
    private const PAGE_SIZE = 10;

    public static function showUsers(int $chatId, int $messageId, BotContext $ctx): void
    {
        self::renderUsers(0, $chatId, $messageId, $ctx);
    }

    public static function paginateUsers(int $page, int $chatId, int $messageId, BotContext $ctx): void
    {
        self::renderUsers($page, $chatId, $messageId, $ctx);
    }

    private static function renderUsers(int $page, int $chatId, int $messageId, BotContext $ctx): void
    {
        $total = UserService::count();
        $users = UserService::list(self::PAGE_SIZE, $page * self::PAGE_SIZE);
        $lines = array_map(fn($u) => "• {$u['first_name']} (@{$u['username']}) - {$u['telegram_id']}", $users);
        $text = "👥 <b>کاربران</b> (مجموع: {$total})\n\n" . ($lines !== [] ? implode("\n", $lines) : 'هنوز کاربری وجود ندارد.');
        $hasMore = ($page + 1) * self::PAGE_SIZE < $total;
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::users($page, $hasMore));
    }

    // -- Admins -----------------------------------------------------------------
    public static function showAdmins(int $chatId, int $messageId, BotContext $ctx): void
    {
        $admins = AdminService::list();
        $text = "🔐 <b>ادمین‌ها</b>\n\nبرای حذف روی هرکدام بزنید.\n\n(شناسه‌های تنظیم‌شده در ADMIN_IDS اینجا نمایش داده نمی‌شوند و از طریق پنل قابل حذف نیستند.)";
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::admins($admins));
    }

    public static function startAddAdmin(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'admin_add');
        $ctx->telegram->editMessageText($chatId, $messageId, 'شناسه عددی تلگرام ادمین جدید را بفرستید:', Keyboards::cancel('admins'));
    }

    public static function receiveNewAdmin(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $trimmed = trim($text);
        if (!ctype_digit(ltrim($trimmed, '-'))) {
            $ctx->telegram->sendMessage($chatId, 'این یک شناسه عددی معتبر تلگرام به نظر نمی‌رسد.');
            return;
        }
        $newAdminId = (int) $trimmed;
        AdminService::add($newAdminId, null, $userId);
        $admins = AdminService::list();
        $ctx->telegram->sendMessage($chatId, "✅ ادمین {$newAdminId} اضافه شد.", Keyboards::admins($admins));
    }

    public static function removeAdmin(int $telegramId, int $chatId, int $messageId, BotContext $ctx): void
    {
        AdminService::remove($telegramId);
        $admins = AdminService::list();
        $ctx->telegram->editMessageText($chatId, $messageId, "🔐 <b>ادمین‌ها</b>\n\nبرای حذف روی هرکدام بزنید.", Keyboards::admins($admins));
    }

    // -- Logs ---------------------------------------------------------------------
    public static function showLogs(int $chatId, int $messageId, BotContext $ctx): void
    {
        $logs = LogService::recent(20);
        if ($logs === []) {
            $text = "📜 <b>لاگ‌ها</b>\n\nهنوز لاگی وجود ندارد.";
        } else {
            $lines = array_map(fn($l) => "[{$l['level']}] {$l['source']}: {$l['message']}", $logs);
            $text = "📜 <b>لاگ‌ها</b> (۲۰ مورد آخر)\n\n" . implode("\n", $lines);
        }
        $ctx->telegram->editMessageText($chatId, $messageId, mb_substr($text, 0, 4000), Keyboards::logs());
    }
}
