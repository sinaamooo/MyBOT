<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;
use MyBot\Support\Text;

/**
 * A banner is captured from a real Telegram message the admin sends to the bot.
 *
 * Capturing the raw `entities` array (rather than re-parsing markdown) is what
 * preserves premium / custom emoji: each one is a `custom_emoji` entity carrying
 * a `custom_emoji_id`, and replaying the entity list reproduces it exactly.
 *
 * If the admin's message was itself a reply with a quoted fragment (the feature
 * Telegram added in Bot API 7.0), that quote is stored too and replayed via
 * `reply_parameters.quote` when the banner is posted.
 */
final class Banners
{
    public const MEDIA_METHODS = [
        'photo'     => 'sendPhoto',
        'video'     => 'sendVideo',
        'animation' => 'sendAnimation',
        'document'  => 'sendDocument',
        'audio'     => 'sendAudio',
        'voice'     => 'sendVoice',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Turns an incoming Telegram message into banner columns.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null null when the message carries nothing we can send
     */
    public function extract(array $message): ?array
    {
        $text     = null;
        $entities = null;

        if (\is_string($message['text'] ?? null)) {
            $text     = $message['text'];
            $entities = \is_array($message['entities'] ?? null) ? $message['entities'] : null;
        } elseif (\is_string($message['caption'] ?? null)) {
            $text     = $message['caption'];
            $entities = \is_array($message['caption_entities'] ?? null) ? $message['caption_entities'] : null;
        }

        [$mediaType, $fileId] = $this->extractMedia($message);

        if (($text === null || trim($text) === '') && $fileId === null) {
            return null;
        }

        $quote = $this->extractQuote($message);

        return [
            'text'           => $text ?? '',
            'entities'       => $entities === null ? null : json_encode($entities, \JSON_UNESCAPED_UNICODE),
            'media_type'     => $mediaType,
            'media_file_id'  => $fileId,
            'quote_text'     => $quote['text'],
            'quote_entities' => $quote['entities'],
            'quote_position' => $quote['position'],
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{0: string|null, 1: string|null}
     */
    private function extractMedia(array $message): array
    {
        if (\is_array($message['photo'] ?? null) && $message['photo'] !== []) {
            // The API returns sizes ascending; the last one is the largest.
            $largest = end($message['photo']);
            if (\is_array($largest) && \is_string($largest['file_id'] ?? null)) {
                return ['photo', $largest['file_id']];
            }
        }

        foreach (['video', 'animation', 'document', 'audio', 'voice'] as $type) {
            $payload = $message[$type] ?? null;
            if (\is_array($payload) && \is_string($payload['file_id'] ?? null)) {
                return [$type, $payload['file_id']];
            }
        }

        return [null, null];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{text: string|null, entities: string|null, position: int|null}
     */
    private function extractQuote(array $message): array
    {
        $quote = $message['quote'] ?? null;

        if (!\is_array($quote) || !\is_string($quote['text'] ?? null) || $quote['text'] === '') {
            return ['text' => null, 'entities' => null, 'position' => null];
        }

        $entities = \is_array($quote['entities'] ?? null) && $quote['entities'] !== []
            ? json_encode($quote['entities'], \JSON_UNESCAPED_UNICODE)
            : null;

        return [
            'text'     => $quote['text'],
            'entities' => $entities === false ? null : $entities,
            'position' => is_numeric($quote['position'] ?? null) ? (int) $quote['position'] : null,
        ];
    }

    /** @param array<string, mixed> $fields output of extract() */
    public function create(string $name, array $fields, int $createdBy): int
    {
        $now = time();

        return $this->db->insert('banners', [
            'name'            => $name,
            'text'            => (string) ($fields['text'] ?? ''),
            'entities'        => $fields['entities'] ?? null,
            'media_type'      => $fields['media_type'] ?? null,
            'media_file_id'   => $fields['media_file_id'] ?? null,
            'buttons'         => null,
            'disable_preview' => 0,
            'protect_content' => 0,
            'quote_text'      => $fields['quote_text'] ?? null,
            'quote_entities'  => $fields['quote_entities'] ?? null,
            'quote_position'  => $fields['quote_position'] ?? null,
            'created_by'      => $createdBy,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function updateContent(int $id, array $fields): void
    {
        $this->db->update('banners', [
            'text'           => (string) ($fields['text'] ?? ''),
            'entities'       => $fields['entities'] ?? null,
            'media_type'     => $fields['media_type'] ?? null,
            'media_file_id'  => $fields['media_file_id'] ?? null,
            'quote_text'     => $fields['quote_text'] ?? null,
            'quote_entities' => $fields['quote_entities'] ?? null,
            'quote_position' => $fields['quote_position'] ?? null,
            'updated_at'     => time(),
        ], 'id = :id', ['id' => $id]);
    }

    /**
     * Parses the admin-supplied button spec.
     * One button per line: "برچسب | https://example.com", blank line = new row.
     *
     * @return list<list<array{text: string, url: string}>>|null
     */
    public function parseButtons(string $spec): ?array
    {
        $rows    = [];
        $current = [];

        foreach (preg_split('/\R/u', $spec) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                if ($current !== []) {
                    $rows[]  = $current;
                    $current = [];
                }
                continue;
            }

            $parts = explode('|', $line, 2);
            if (\count($parts) !== 2) {
                return null;
            }

            $label = trim($parts[0]);
            $url   = trim($parts[1]);

            if ($label === '' || !preg_match('#^(https?://|tg://)#i', $url)) {
                return null;
            }

            $current[] = ['text' => $label, 'url' => $url];
        }

        if ($current !== []) {
            $rows[] = $current;
        }

        return $rows === [] ? null : $rows;
    }

    /** @param list<list<array{text: string, url: string}>>|null $buttons */
    public function setButtons(int $id, ?array $buttons): void
    {
        $this->db->update('banners', [
            'buttons'    => $buttons === null ? null : json_encode($buttons, \JSON_UNESCAPED_UNICODE),
            'updated_at' => time(),
        ], 'id = :id', ['id' => $id]);
    }

    public function toggleFlag(int $id, string $column): bool
    {
        if (!\in_array($column, ['disable_preview', 'protect_content'], true)) {
            return false;
        }

        $banner = $this->find($id);
        if ($banner === null) {
            return false;
        }

        $next = ((int) $banner[$column]) === 1 ? 0 : 1;
        $this->db->update('banners', [$column => $next, 'updated_at' => time()], 'id = :id', ['id' => $id]);

        return $next === 1;
    }

    public function clearQuote(int $id): void
    {
        $this->db->update('banners', [
            'quote_text'     => null,
            'quote_entities' => null,
            'quote_position' => null,
            'updated_at'     => time(),
        ], 'id = :id', ['id' => $id]);
    }

    public function rename(int $id, string $name): void
    {
        $this->db->update('banners', ['name' => $name, 'updated_at' => time()], 'id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM banners WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function page(int $page, int $perPage = 8): array
    {
        return $this->db->all(
            'SELECT * FROM banners ORDER BY id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => max(0, $page) * $perPage],
        );
    }

    public function countAll(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM banners');
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM banners WHERE id = :id', ['id' => $id]);
    }

    /** How many premium / custom emoji this banner carries. */
    public function customEmojiCount(array $banner): int
    {
        $entities = Text::json($banner['entities'] ?? null) ?? [];
        $count    = 0;

        foreach ($entities as $entity) {
            if (\is_array($entity) && ($entity['type'] ?? null) === 'custom_emoji') {
                $count++;
            }
        }

        return $count;
    }

    public function hasQuote(array $banner): bool
    {
        return \is_string($banner['quote_text'] ?? null) && $banner['quote_text'] !== '';
    }
}
