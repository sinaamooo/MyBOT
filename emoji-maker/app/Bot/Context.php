<?php

declare(strict_types=1);

namespace EmojiMaker\Bot;

use EmojiMaker\App;
use EmojiMaker\Database\Repositories\UserRepository;
use EmojiMaker\Telegram\TelegramClient;
use EmojiMaker\Telegram\TelegramException;

/**
 * One incoming update: the sender (users row), chat, message or callback,
 * the conversation state, and helpers to answer. All answers use HTML parse
 * mode; user supplied strings must be escaped with Texts::e().
 */
final class Context
{
    /** @var array<string,mixed> */
    public array $user;
    public int $chatId;
    /** @var array<string,mixed>|null */
    public ?array $message;
    /** @var array<string,mixed>|null */
    public ?array $callback;
    private bool $answered = false;
    private TelegramClient $tg;

    /**
     * @param array<string,mixed> $update
     * @param array<string,mixed> $user
     */
    public function __construct(private App $app, public array $update, array $user, int $chatId)
    {
        $this->user = $user;
        $this->chatId = $chatId;
        $this->message = is_array($update['message'] ?? null) ? $update['message'] : null;
        $this->callback = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
        $this->tg = $app->telegram();
    }

    public function app(): App
    {
        return $this->app;
    }

    public function tg(): TelegramClient
    {
        return $this->tg;
    }

    public function userId(): int
    {
        return (int) $this->user['id'];
    }

    public function telegramId(): int
    {
        return (int) $this->user['telegram_id'];
    }

    public function isAdmin(): bool
    {
        return ($this->user['role'] ?? '') === 'admin' || in_array($this->telegramId(), $this->app->adminIds(), true);
    }

    public function isCallback(): bool
    {
        return $this->callback !== null;
    }

    /** @return list<string> callback data parts ("ns:action:arg...") */
    public function data(): array
    {
        return explode(':', (string) ($this->callback['data'] ?? ''));
    }

    public function text(): string
    {
        return trim((string) ($this->message['text'] ?? ''));
    }

    /** Message id of the message carrying the pressed button. */
    public function callbackMessageId(): ?int
    {
        $id = $this->callback['message']['message_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function reply(string $text, ?array $markup = null): ?int
    {
        $opts = ['parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
        if ($markup !== null) {
            $opts['reply_markup'] = $markup;
        }
        try {
            $res = $this->tg->sendMessage($this->chatId, $text, $opts);
            return isset($res['message_id']) ? (int) $res['message_id'] : null;
        } catch (TelegramException $e) {
            $this->app->logger('bot')->warning('sendMessage failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Edits the message whose button was pressed (keeps wizards in one
     * message); falls back to a new message.
     */
    public function edit(string $text, ?array $markup = null): ?int
    {
        $mid = $this->callbackMessageId();
        $isMedia = isset($this->callback['message']['photo']) || isset($this->callback['message']['animation']) || isset($this->callback['message']['document']) || isset($this->callback['message']['video']);
        if ($mid !== null && !$isMedia) {
            try {
                $opts = ['parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
                if ($markup !== null) {
                    $opts['reply_markup'] = $markup;
                }
                $this->tg->editMessageText($this->chatId, $mid, $text, $opts);
                return $mid;
            } catch (TelegramException $e) {
                // message too old / deleted: send a new one
            }
        }
        return $this->reply($text, $markup);
    }

    /** Answers the callback query once (removes the spinner on the button). */
    public function toast(string $text = '', bool $alert = false): void
    {
        if ($this->callback === null || $this->answered) {
            return;
        }
        $this->answered = true;
        try {
            $this->tg->answerCallbackQuery((string) $this->callback['id'], mb_substr($text, 0, 190), $alert);
        } catch (TelegramException) {
            // an expired callback cannot be answered anymore
        }
    }

    public function answered(): bool
    {
        return $this->answered;
    }

    public function state(): string
    {
        return (string) ($this->user['state'] ?? 'idle');
    }

    /** @return array<string,mixed> */
    public function stateData(): array
    {
        return UserRepository::stateData($this->user);
    }

    /** @param array<string,mixed> $data */
    public function setState(string $state, array $data = []): void
    {
        $this->app->users()->setState($this->userId(), $state, $data);
        $this->user['state'] = $state;
        $this->user['state_data'] = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    }

    public function clearState(): void
    {
        $this->setState('idle');
    }

    public function chatAction(string $action = 'typing'): void
    {
        try {
            $this->tg->sendChatAction($this->chatId, $action);
        } catch (TelegramException) {
            // purely cosmetic
        }
    }
}
