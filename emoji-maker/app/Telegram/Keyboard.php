<?php

declare(strict_types=1);

namespace EmojiMaker\Telegram;

/**
 * Reply markup builders. Validation happens here (callback data <= 64 bytes,
 * one action per button) so a bad keyboard fails in our code with a clear
 * message instead of a "BUTTON_DATA_INVALID" from Telegram at runtime.
 */
final class Keyboard
{
    public const MAX_CALLBACK_BYTES = 64;

    /** Button fields that make an inline button do something (exactly one is allowed). */
    private const INLINE_ACTIONS = [
        'callback_data', 'url', 'web_app', 'login_url', 'switch_inline_query',
        'switch_inline_query_current_chat', 'switch_inline_query_chosen_chat', 'copy_text', 'callback_game', 'pay',
    ];

    /**
     * Inline keyboard. Each row is a list of buttons; a button is [text, callback_data]
     * or an array like ['text' => .., 'url' => ..]. A row may also be a single button.
     * @param list<mixed> $rows
     * @return array{inline_keyboard:list<list<array<string,mixed>>>}
     */
    public static function inline(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Inline keyboard row must be an array');
            }
            if (self::isSingleButton($row)) {
                $row = [$row];
            }
            $buttons = [];
            foreach ($row as $button) {
                $buttons[] = self::inlineButton($button);
            }
            if ($buttons !== []) {
                $out[] = $buttons;
            }
        }
        return ['inline_keyboard' => $out];
    }

    /** @return array{text:string,callback_data:string} */
    public static function button(string $text, string $data): array
    {
        self::assertText($text);
        self::assertData($data);
        return ['text' => $text, 'callback_data' => $data];
    }

    /** @return array{text:string,url:string} */
    public static function url(string $text, string $url): array
    {
        self::assertText($text);
        if (!preg_match('#^(https?|tg)://[^\s]+$#i', $url)) {
            throw new \InvalidArgumentException('Button URL must be http(s):// or tg://');
        }
        return ['text' => $text, 'url' => $url];
    }

    /**
     * Reply (bottom) keyboard; buttons are strings or ['text' => ..] arrays.
     * @param list<mixed> $rows
     * @return array<string,mixed>
     */
    public static function reply(array $rows, bool $resize = true, bool $persistent = true, ?string $placeholder = null): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_string($row) || (is_array($row) && isset($row['text']))) {
                $row = [$row];
            }
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Reply keyboard row must be an array');
            }
            $buttons = [];
            foreach ($row as $button) {
                if (is_string($button)) {
                    $button = ['text' => $button];
                }
                if (!is_array($button) || !is_string($button['text'] ?? null)) {
                    throw new \InvalidArgumentException('Reply keyboard button needs a text');
                }
                self::assertText($button['text']);
                $buttons[] = $button;
            }
            if ($buttons !== []) {
                $out[] = $buttons;
            }
        }
        $markup = ['keyboard' => $out, 'resize_keyboard' => $resize, 'is_persistent' => $persistent];
        if ($placeholder !== null && $placeholder !== '') {
            $markup['input_field_placeholder'] = mb_substr($placeholder, 0, 64);
        }
        return $markup;
    }

    /** @return array{remove_keyboard:true} */
    public static function remove(): array
    {
        return ['remove_keyboard' => true];
    }

    /**
     * Callback data "<ns>:<action>[:<arg>...]". Parts must not contain ':'
     * (the receiver splits on it).
     * @throws \LengthException when the result is longer than 64 bytes
     */
    public static function data(string ...$parts): string
    {
        if ($parts === []) {
            throw new \InvalidArgumentException('Callback data needs at least one part');
        }
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                throw new \InvalidArgumentException('Callback data parts must not contain ":"');
            }
        }
        $data = implode(':', $parts);
        self::assertData($data);
        return $data;
    }

    /**
     * Splits callback data produced by data().
     * @return list<string>
     */
    public static function parse(string $data): array
    {
        return explode(':', $data);
    }

    /**
     * Groups a flat list of buttons into rows of $perRow (galleries, colour pickers).
     * @param list<mixed> $buttons
     * @return list<list<mixed>>
     */
    public static function rows(array $buttons, int $perRow): array
    {
        return array_chunk(array_values($buttons), max(1, $perRow));
    }

    /** @param array<mixed> $row */
    private static function isSingleButton(array $row): bool
    {
        if (isset($row['text'])) {
            return true;
        }
        return array_is_list($row) && count($row) === 2 && is_string($row[0]) && is_string($row[1]);
    }

    /** @return array<string,mixed> */
    private static function inlineButton(mixed $button): array
    {
        if (is_array($button) && array_is_list($button) && count($button) === 2 && is_string($button[0]) && is_string($button[1])) {
            return self::button($button[0], $button[1]);
        }
        if (!is_array($button) || !is_string($button['text'] ?? null)) {
            throw new \InvalidArgumentException('Inline button must be [text, data] or have a text');
        }
        self::assertText($button['text']);
        $actions = array_values(array_intersect(array_keys($button), self::INLINE_ACTIONS));
        if (count($actions) !== 1) {
            throw new \InvalidArgumentException('Inline button needs exactly one action (callback_data, url, ...)');
        }
        if ($actions[0] === 'callback_data') {
            if (!is_string($button['callback_data'])) {
                throw new \InvalidArgumentException('callback_data must be a string');
            }
            self::assertData($button['callback_data']);
        }
        if ($actions[0] === 'url') {
            self::url($button['text'], (string) $button['url']);
        }
        return $button;
    }

    private static function assertText(string $text): void
    {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Button text must not be empty');
        }
    }

    private static function assertData(string $data): void
    {
        $len = strlen($data);
        if ($len === 0 || $len > self::MAX_CALLBACK_BYTES) {
            throw new \LengthException(sprintf('Callback data must be 1-%d bytes, got %d', self::MAX_CALLBACK_BYTES, $len));
        }
    }
}
