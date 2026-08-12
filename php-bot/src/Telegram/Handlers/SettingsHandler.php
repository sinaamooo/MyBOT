<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Config;
use App\Services\AdminStateService;
use App\Services\SettingsService;
use App\Signals\Formatter;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class SettingsHandler
{
    // -- Indicators -----------------------------------------------------
    public static function showIndicators(int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        $ctx->telegram->editMessageText($chatId, $messageId, "📊 <b>Indicators</b>\n\nToggle indicators on/off:", Keyboards::indicators($params));
    }

    public static function toggleIndicator(string $key, int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        SettingsService::set($key, empty($params[$key]));
        self::showIndicators($chatId, $messageId, $ctx);
    }

    // -- Settings menu ----------------------------------------------------
    public static function showMenu(int $chatId, int $messageId, BotContext $ctx): void
    {
        $ctx->telegram->editMessageText($chatId, $messageId, "⚙️ <b>Settings</b>\n\nChoose a category:", Keyboards::settingsMenu());
    }

    public static function showCategory(string $category, int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        $title = Keyboards::categoryTitles()[$category] ?? $category;
        $ctx->telegram->editMessageText($chatId, $messageId, "⚙️ <b>{$title}</b>", Keyboards::settingsCategory($category, $params));
    }

    public static function toggleBool(string $key, int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        SettingsService::set($key, empty($params[$key]));
        $params = SettingsService::getAll(true);
        $category = self::categoryFor($key);
        $title = Keyboards::categoryTitles()[$category] ?? $category;
        $ctx->telegram->editMessageText($chatId, $messageId, "⚙️ <b>{$title}</b>", Keyboards::settingsCategory($category, $params));
    }

    public static function startEdit(string $key, int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        $category = self::categoryFor($key);
        $current = SettingsService::get($key);
        AdminStateService::set($userId, 'setting_edit', ['key' => $key, 'category' => $category]);
        $ctx->telegram->editMessageText(
            $chatId,
            $messageId,
            "Current value of <b>{$key}</b>: {$current}\n\nSend the new numeric value:",
            Keyboards::cancel('settings')
        );
    }

    public static function receiveSettingValue(int $chatId, string $text, array $context, BotContext $ctx, int $userId): void
    {
        AdminStateService::clear($userId);
        $key = $context['key'];
        $category = $context['category'];
        $valueType = SettingsService::valueType($key);

        $trimmed = trim($text);
        $parsed = match ($valueType) {
            'boolean' => in_array(strtolower($trimmed), ['1', 'true', 'yes', 'on'], true),
            'integer' => is_numeric($trimmed) ? (int) round((float) $trimmed) : null,
            'double' => is_numeric($trimmed) ? (float) $trimmed : null,
            default => $trimmed,
        };

        if ($parsed === null) {
            $ctx->telegram->sendMessage($chatId, "Invalid value for a {$valueType}. Try again or press Back.");
            return;
        }

        SettingsService::set($key, $parsed);
        $params = SettingsService::getAll(true);
        $title = Keyboards::categoryTitles()[$category] ?? $category;
        $ctx->telegram->sendMessage($chatId, "✅ {$key} = {$parsed}", Keyboards::settingsCategory($category, $params));
    }

    private static function categoryFor(string $key): string
    {
        foreach (Keyboards::settingsCategories() as $cat => $items) {
            foreach ($items as [, $itemKey]) {
                if ($itemKey === $key) {
                    return $cat;
                }
            }
        }
        return 'scoring';
    }

    // -- Message templates --------------------------------------------------
    public static function showTemplates(int $chatId, int $messageId, BotContext $ctx): void
    {
        $template = SettingsService::get('signal_message_template');
        $ctx->telegram->editMessageText(
            $chatId,
            $messageId,
            "📝 <b>Message Templates</b>\n\nCurrent template:\n<code>" . htmlspecialchars((string) $template) . '</code>',
            Keyboards::templates()
        );
    }

    public static function startEditTemplate(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'template_edit');
        $placeholders = implode(' ', array_map(fn($p) => "{{$p}}", Formatter::knownSignalPlaceholders()));
        $ctx->telegram->editMessageText(
            $chatId,
            $messageId,
            "Send the new signal template. Use these placeholders:\n{$placeholders}",
            Keyboards::cancel('templates')
        );
    }

    public static function receiveTemplate(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        if (!Formatter::validateSignalTemplate($text)) {
            $ctx->telegram->sendMessage($chatId, 'Template has an invalid/unknown placeholder. Not saved.');
            return;
        }
        SettingsService::set('signal_message_template', $text);
        $ctx->telegram->sendMessage($chatId, '✅ Template saved.', Keyboards::templates());
    }

    public static function resetTemplate(int $chatId, int $messageId, BotContext $ctx): void
    {
        SettingsService::set('signal_message_template', Config::defaultSignalMessageTemplate());
        $ctx->telegram->editMessageText(
            $chatId,
            $messageId,
            "📝 <b>Message Templates</b>\n\nCurrent template:\n<code>" . htmlspecialchars(Config::defaultSignalMessageTemplate()) . '</code>',
            Keyboards::templates()
        );
    }
}
