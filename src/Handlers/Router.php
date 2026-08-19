<?php

declare(strict_types=1);

namespace MyBot\Handlers;

use MyBot\App;
use Throwable;

/** Entry point for every incoming update. */
final class Router
{
    private readonly Commands $commands;
    private readonly Callbacks $callbacks;
    private readonly Flows $flows;

    public function __construct(private readonly App $app)
    {
        $this->flows     = new Flows($app);
        $this->commands  = new Commands($app, $this->flows);
        $this->callbacks = new Callbacks($app, $this->flows);
    }

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        try {
            $this->dispatch($update);
        } catch (Throwable $e) {
            $this->app->logger->error('update handling failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /** @param array<string, mixed> $update */
    private function dispatch(array $update): void
    {
        if (\is_array($update['callback_query'] ?? null)) {
            $this->callbacks->handle($update['callback_query']);

            return;
        }

        if (\is_array($update['my_chat_member'] ?? null)) {
            $this->onChatMemberUpdate($update['my_chat_member']);

            return;
        }

        $message = $update['message'] ?? $update['channel_post'] ?? null;

        if (\is_array($message)) {
            $this->onMessage($message);
        }
    }

    /** @param array<string, mixed> $message */
    private function onMessage(array $message): void
    {
        $chat = $message['chat'] ?? null;
        if (!\is_array($chat)) {
            return;
        }

        $chatType = (string) ($chat['type'] ?? '');

        if ($chatType !== 'private') {
            $this->onGroupMessage($message, $chat);

            return;
        }

        $from = $message['from'] ?? null;
        if (!\is_array($from)) {
            return;
        }

        $this->app->users->touch($from);

        $userId = (int) $from['id'];
        $text   = \is_string($message['text'] ?? null) ? trim($message['text']) : '';

        if (str_starts_with($text, '/')) {
            $this->commands->handle($message, $text, $userId);

            return;
        }

        // Anything else in a private chat only means something inside a flow.
        if ($this->app->isAdmin($userId) && $this->flows->handle($message, $userId)) {
            return;
        }

        if ($text !== '') {
            $this->commands->fallback($userId);
        }
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $chat
     */
    private function onGroupMessage(array $message, array $chat): void
    {
        $this->app->activity->record($message);

        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId === 0) {
            return;
        }

        // Keep the stored title fresh; register the chat if we somehow missed
        // the my_chat_member event (e.g. the bot was added while offline).
        $this->app->targets->upsert($chat);

        $text = \is_string($message['text'] ?? null) ? trim($message['text']) : '';

        if ($text !== '' && str_starts_with($text, '/')) {
            $from = $message['from'] ?? null;
            $this->commands->handleInGroup(
                $message,
                $text,
                \is_array($from) ? (int) ($from['id'] ?? 0) : 0,
                $chatId,
            );
        }
    }

    /**
     * Fires when the bot itself is added to, promoted in, or removed from a chat.
     *
     * @param array<string, mixed> $event
     */
    private function onChatMemberUpdate(array $event): void
    {
        $chat      = $event['chat'] ?? null;
        $newMember = $event['new_chat_member'] ?? null;

        if (!\is_array($chat) || !\is_array($newMember)) {
            return;
        }

        $chatId = (int) ($chat['id'] ?? 0);
        $status = (string) ($newMember['status'] ?? '');
        $from   = $event['from'] ?? null;
        $actor  = \is_array($from) ? (int) ($from['id'] ?? 0) : null;

        if (\in_array($status, ['left', 'kicked'], true)) {
            $this->app->targets->disablePosting($chatId, 'bot removed: ' . $status);
            $this->app->logger->info('removed from chat', ['chat' => $chatId, 'status' => $status]);

            return;
        }

        if (!\in_array($status, ['member', 'administrator', 'creator'], true)) {
            return;
        }

        $id = $this->app->targets->upsert($chat, $actor);

        $this->app->db->update(
            'targets',
            ['active' => 1, 'can_post' => 1, 'updated_at' => time()],
            'id = :id',
            ['id' => $id],
        );

        $this->refreshMemberCount($chatId);

        $this->app->logger->info('added to chat', ['chat' => $chatId, 'status' => $status]);

        $this->notifyAdmins($chat, $status);
    }

    private function refreshMemberCount(int $chatId): void
    {
        $count = $this->app->api->tryCall('getChatMemberCount', ['chat_id' => $chatId]);

        if (is_numeric($count)) {
            $this->app->targets->setMembers($chatId, (int) $count);
        }
    }

    /** @param array<string, mixed> $chat */
    private function notifyAdmins(array $chat, string $status): void
    {
        $title = \is_string($chat['title'] ?? null) ? $chat['title'] : ('چت ' . (string) $chat['id']);

        $text = "✅ <b>گروه جدید ثبت شد</b>\n\n"
            . '👥 ' . \MyBot\Support\Text::esc($title) . "\n"
            . '🆔 <code>' . (string) $chat['id'] . "</code>\n"
            . '🎖 وضعیت ربات: ' . \MyBot\Support\Text::esc($status);

        foreach ($this->app->admins() as $adminId) {
            $this->app->sender->notice($adminId, $text);
        }
    }
}
