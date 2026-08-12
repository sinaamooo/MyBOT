<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class MenuHandler
{
    public const TITLE = "🖥 <b>پنل مدیریت</b>\n\nیک بخش را انتخاب کنید:";

    public static function root(int $chatId, BotContext $ctx): void
    {
        $ctx->telegram->sendMessage($chatId, self::TITLE, Keyboards::mainMenu());
    }

    public static function rootEdit(int $chatId, int $messageId, BotContext $ctx): void
    {
        $ctx->telegram->editMessageText($chatId, $messageId, self::TITLE, Keyboards::mainMenu());
    }
}
