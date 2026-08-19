<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\ApiException;
use MyBot\Support\TelegramClient;
use MyBot\Support\Text;

/**
 * Turns a stored banner into an actual Bot API call.
 *
 * Two Telegram features get special handling here:
 *
 *  - Premium (custom) emoji: sent by replaying the stored `entities` array
 *    verbatim instead of using parse_mode. `entities` and `parse_mode` are
 *    mutually exclusive in the Bot API, so we never set both.
 *
 *  - Quote: Bot API 7.0's `reply_parameters` lets a reply quote a specific
 *    fragment of the message it answers, with its own entities and an offset
 *    into the original text.
 */
final class Sender
{
    public function __construct(private readonly TelegramClient $api)
    {
    }

    /**
     * @param array<string, mixed> $banner
     * @return int the sent message_id
     * @throws ApiException
     */
    public function send(
        array $banner,
        int $chatId,
        ?int $replyToMessageId = null,
        ?int $threadId = null,
        bool $silent = false,
    ): int {
        [$method, $params] = $this->build($banner, $chatId, $replyToMessageId, $threadId, $silent);

        $result = $this->api->call($method, $params);

        return \is_array($result) && isset($result['message_id']) ? (int) $result['message_id'] : 0;
    }

    /**
     * Builds the method name and parameters without sending — used by the
     * preview screen and by the tests.
     *
     * @param array<string, mixed> $banner
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function build(
        array $banner,
        int $chatId,
        ?int $replyToMessageId = null,
        ?int $threadId = null,
        bool $silent = false,
    ): array {
        $text       = (string) ($banner['text'] ?? '');
        $entities   = Text::json($banner['entities'] ?? null);
        $mediaType  = \is_string($banner['media_type'] ?? null) ? $banner['media_type'] : null;
        $fileId     = \is_string($banner['media_file_id'] ?? null) ? $banner['media_file_id'] : null;
        $buttons    = Text::json($banner['buttons'] ?? null);

        $params = [
            'chat_id'              => $chatId,
            'disable_notification' => $silent ?: null,
            'protect_content'      => ((int) ($banner['protect_content'] ?? 0)) === 1 ? true : null,
            'message_thread_id'    => $threadId,
        ];

        if ($buttons !== null) {
            $params['reply_markup'] = ['inline_keyboard' => $buttons];
        }

        $replyParameters = $this->buildReplyParameters($banner, $replyToMessageId);
        if ($replyParameters !== null) {
            $params['reply_parameters'] = $replyParameters;
        }

        if ($mediaType !== null && $fileId !== null && isset(Banners::MEDIA_METHODS[$mediaType])) {
            $method                 = Banners::MEDIA_METHODS[$mediaType];
            $params[$mediaType]     = $fileId;

            if ($text !== '') {
                $params['caption'] = $text;
                if ($entities !== null) {
                    $params['caption_entities'] = $entities;
                }
            }

            return [$method, array_filter($params, static fn (mixed $v): bool => $v !== null)];
        }

        $params['text'] = $text;

        if ($entities !== null) {
            $params['entities'] = $entities;
        }

        if (((int) ($banner['disable_preview'] ?? 0)) === 1) {
            $params['link_preview_options'] = ['is_disabled' => true];
        }

        return ['sendMessage', array_filter($params, static fn (mixed $v): bool => $v !== null)];
    }

    /**
     * @param array<string, mixed> $banner
     * @return array<string, mixed>|null
     */
    private function buildReplyParameters(array $banner, ?int $replyToMessageId): ?array
    {
        if ($replyToMessageId === null || $replyToMessageId <= 0) {
            return null;
        }

        $parameters = [
            'message_id'                  => $replyToMessageId,
            'allow_sending_without_reply' => true,
        ];

        $quoteText = $banner['quote_text'] ?? null;

        if (\is_string($quoteText) && $quoteText !== '') {
            $parameters['quote'] = $quoteText;

            $quoteEntities = Text::json($banner['quote_entities'] ?? null);
            if ($quoteEntities !== null) {
                $parameters['quote_entities'] = $quoteEntities;
            }

            $position = $banner['quote_position'] ?? null;
            if (is_numeric($position)) {
                $parameters['quote_position'] = (int) $position;
            }
        }

        return $parameters;
    }

    /**
     * Sends a plain HTML notice (panel screens, errors, confirmations).
     *
     * @param array<string, mixed>|null $keyboard
     */
    public function notice(
        int $chatId,
        string $html,
        ?array $keyboard = null,
        ?int $replyToMessageId = null,
    ): int {
        $params = [
            'chat_id'              => $chatId,
            'text'                 => $html,
            'parse_mode'           => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = $keyboard;
        }

        if ($replyToMessageId !== null) {
            $params['reply_parameters'] = [
                'message_id'                  => $replyToMessageId,
                'allow_sending_without_reply' => true,
            ];
        }

        $result = $this->api->tryCall('sendMessage', $params);

        return \is_array($result) && isset($result['message_id']) ? (int) $result['message_id'] : 0;
    }

    /** @param array<string, mixed>|null $keyboard */
    public function edit(int $chatId, int $messageId, string $html, ?array $keyboard = null): void
    {
        $params = [
            'chat_id'              => $chatId,
            'message_id'           => $messageId,
            'text'                 => $html,
            'parse_mode'           => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = $keyboard;
        }

        try {
            $this->api->call('editMessageText', $params);
        } catch (ApiException $e) {
            // Editing a message into identical content is a no-op error we can ignore;
            // anything else means the panel message is gone, so post a fresh one.
            if (str_contains($e->getMessage(), 'message is not modified')) {
                return;
            }

            $this->notice($chatId, $html, $keyboard);
        }
    }
}
