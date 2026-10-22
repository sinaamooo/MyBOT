<?php

declare(strict_types=1);

namespace EmojiMaker\Bot;

use EmojiMaker\App;
use EmojiMaker\Bot\Controllers\AdminController;
use EmojiMaker\Bot\Controllers\DesignController;
use EmojiMaker\Bot\Controllers\MenuController;
use EmojiMaker\Bot\Controllers\ProjectController;
use EmojiMaker\Bot\Controllers\PublishController;
use EmojiMaker\Bot\Controllers\TemplateController;
use EmojiMaker\Security\Redactor;

/**
 * Entry point of the bot for one Telegram update: registers / refreshes the
 * user, applies the guards (private chats only, blocked users, maintenance
 * mode, rate limit) and routes commands, menu buttons, conversation input and
 * inline button callbacks to the controllers.
 */
final class UpdateProcessor
{
    public function __construct(private App $app)
    {
    }

    public function process(array $update): void
    {
        if (isset($update['my_chat_member'])) {
            $this->chatMember((array) $update['my_chat_member']);
            return;
        }
        $msg = is_array($update['message'] ?? null) ? $update['message'] : null;
        $cb = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
        if ($msg === null && $cb === null) {
            return;
        }
        $from = $msg['from'] ?? $cb['from'] ?? null;
        $chat = $msg['chat'] ?? $cb['message']['chat'] ?? null;
        if (!is_array($from) || !empty($from['is_bot']) || !is_array($chat)) {
            return;
        }
        if (($chat['type'] ?? '') !== 'private') {
            return; // groups / channels are ignored on purpose
        }
        $user = $this->app->users()->upsertFromTelegram($from, $this->app->adminIds());
        $ctx = new Context($this->app, $update, $user, (int) $chat['id']);
        try {
            if (!$this->guards($ctx)) {
                return;
            }
            $cb !== null ? $this->callback($ctx) : $this->message($ctx);
        } catch (\Throwable $e) {
            $this->app->logger('bot')->exception($e, 'Update handling failed', ['user' => $ctx->userId()]);
            $ctx->toast('خطایی پیش آمد', false);
            $ctx->reply('⚠️ متأسفانه خطایی پیش آمد. دوباره امتحان کن یا /cancel را بزن.' . ($this->app->isDebug() ? "\n<code>" . Texts::e(Redactor::text($e->getMessage())) . '</code>' : ''));
        } finally {
            $ctx->toast();
        }
    }

    private function guards(Context $ctx): bool
    {
        $s = $this->app->settings();
        if ((int) ($ctx->user['is_blocked'] ?? 0) === 1) {
            $ctx->isCallback() ? $ctx->toast(Texts::BLOCKED, true) : $ctx->reply(Texts::BLOCKED);
            return false;
        }
        if (!$ctx->isAdmin() && !$s->bool('bot_enabled', true)) {
            $text = $s->string('maintenance_message', 'ربات در حال به‌روزرسانی است.');
            $ctx->isCallback() ? $ctx->toast($text, true) : $ctx->reply('🛠 ' . Texts::e($text));
            return false;
        }
        if (!$ctx->isAdmin()) {
            $limit = max(5, $s->int('rate_limit_per_minute', 40));
            if (!$this->app->misc()->hit('u:' . $ctx->telegramId(), $limit, 60)) {
                // tell the user once per minute, then stay silent
                if ($this->app->misc()->hit('rln:' . $ctx->telegramId(), 1, 60)) {
                    $ctx->isCallback() ? $ctx->toast(Texts::RATE_LIMIT, true) : $ctx->reply(Texts::RATE_LIMIT);
                }
                return false;
            }
        }
        return true;
    }

    private function message(Context $ctx): void
    {
        $text = $ctx->text();
        $menu = new MenuController($this->app);
        if ($text !== '' && $text[0] === '/') {
            $parts = preg_split('/\s+/', $text, 2) ?: [$text];
            $cmd = strtolower((string) preg_replace('/@.*$/', '', substr($parts[0], 1)));
            $arg = trim((string) ($parts[1] ?? ''));
            match ($cmd) {
                'start' => $menu->start($ctx, $arg),
                'menu' => $menu->showMenu($ctx),
                'help' => $menu->help($ctx),
                'cancel' => $menu->cancel($ctx),
                'templates' => (new TemplateController($this->app))->packs($ctx),
                'projects' => (new ProjectController($this->app))->list($ctx, 1),
                'admin' => (new AdminController($this->app))->show($ctx),
                default => $ctx->reply(Texts::UNKNOWN, $menu->menuKeyboard()),
            };
            return;
        }
        if ($text !== '' && $menu->isMenuButton($text)) {
            $ctx->clearState();
            $menu->menuButton($ctx, $text);
            return;
        }
        $state = $ctx->state();
        if (str_starts_with($state, 'w_') || str_starts_with($state, 'b_')) {
            (new DesignController($this->app))->input($ctx);
            return;
        }
        if (str_starts_with($state, 't_')) {
            (new TemplateController($this->app))->input($ctx);
            return;
        }
        if (str_starts_with($state, 'pub_')) {
            (new PublishController($this->app))->input($ctx);
            return;
        }
        if ($ctx->message !== null && Uploads::hasImage($ctx->message)) {
            // an image without context: start a design from it
            (new DesignController($this->app))->startFromImage($ctx);
            return;
        }
        $ctx->reply(Texts::UNKNOWN, $menu->menuKeyboard());
    }

    private function callback(Context $ctx): void
    {
        $parts = $ctx->data();
        match ($parts[0] ?? '') {
            'm' => (new MenuController($this->app))->callback($ctx, $parts),
            'w', 'b' => (new DesignController($this->app))->callback($ctx, $parts),
            't' => (new TemplateController($this->app))->callback($ctx, $parts),
            'p', 'j' => (new ProjectController($this->app))->callback($ctx, $parts),
            'pub' => (new PublishController($this->app))->callback($ctx, $parts),
            'adm' => (new AdminController($this->app))->callback($ctx, $parts),
            default => $ctx->toast(Texts::EXPIRED),
        };
    }

    private function chatMember(array $m): void
    {
        $status = (string) ($m['new_chat_member']['status'] ?? '');
        $tg = (int) ($m['from']['id'] ?? 0);
        if ($tg > 0 && in_array($status, ['kicked', 'member'], true)) {
            $this->app->logger('bot')->info('Chat member update', ['telegram_id' => $tg, 'status' => $status]);
        }
    }
}
