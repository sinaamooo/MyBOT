<?php

declare(strict_types=1);

namespace EmojiMaker\Telegram;

/**
 * Publishes generated files as a custom emoji set.
 *
 * Every file is first uploaded with uploadStickerFile, the set is created with
 * InputSticker objects that reference the returned file_id (first <= 50) and
 * the rest is added one by one (<= 200 in total). One bad file must not cost
 * the whole pack: when Telegram rejects the initial batch for a sticker
 * related reason, the set is created from a single sticker and the others are
 * added individually so the faulty ones are identified and reported.
 * Finally getStickerSet maps every published item to its custom_emoji_id.
 */
final class StickerPublisher
{
    public const DEFAULT_EMOJI = '⭐';

    private const FORMATS = ['static', 'animated', 'video'];
    /** Bot API upload limits per sticker format. */
    private const MAX_BYTES = ['static' => 512 * 1024, 'animated' => 64 * 1024, 'video' => 256 * 1024];
    private const MAX_FLOOD_WAIT = 60;   // seconds for one wait (the client already handled shorter ones)
    private const FLOOD_BUDGET = 300;    // seconds of flood waiting per publish (worker stale limit is 600 s)
    private const MAX_TITLE_UNITS = 64;  // UTF-16 code units, as counted by Telegram
    private const MAX_KEYWORD_CHARS = 64;
    private const MAX_KEYWORDS = 20;
    /** Set level problems: retrying with other stickers cannot help. */
    private const FATAL_PATTERN = '/USER_ID_INVALID|PEER_ID_INVALID|user not found|STICKERSET_INVALID|STICKERS_TOO_MUCH|sticker set name|name is already occupied|set name|title|not enough rights|user is deactivated|bot was blocked/i';

    private string $bot;
    private int $maxInitial;
    private int $maxSet;
    private int $maxEmoji;
    private \Closure $sleep;
    private int $floodWaited = 0;

    /**
     * @param array<string,mixed> $packLimits config telegram_formats.pack (max_initial, max_emoji_set, max_emoji_per_sticker)
     * @param callable(int):void|null $sleep waits N seconds (replaceable in tests)
     */
    public function __construct(private TelegramClient $tg, string $botUsername, array $packLimits, ?callable $sleep = null)
    {
        $bot = strtolower(ltrim(trim($botUsername), '@'));
        if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $bot)) {
            throw new \InvalidArgumentException('Invalid bot username');
        }
        $this->bot = $bot;
        $this->maxInitial = max(1, min(50, (int) ($packLimits['max_initial'] ?? 50)));
        $this->maxSet = max(1, min(200, (int) ($packLimits['max_emoji_set'] ?? 200)));
        $this->maxEmoji = max(1, min(20, (int) ($packLimits['max_emoji_per_sticker'] ?? 20)));
        $this->sleep = $sleep !== null ? \Closure::fromCallable($sleep) : static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * Valid set name for this bot: "<base>_<random>_by_<bot>", [a-z0-9_], starts
     * with a letter, no "__", <= 64 chars. The random part keeps names unique
     * and unguessable without exposing the user's Telegram id in a public link.
     */
    public function makeName(string $base, int $userId): string
    {
        $slug = strtolower($base);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'emoji';
        }
        if (!ctype_alpha($slug[0])) {
            $slug = 'e' . $slug;
        }
        $suffix = '_by_' . $this->bot;
        $random = self::randomToken($userId, 6);
        $room = 64 - strlen($suffix) - 1 - strlen($random);
        $slug = rtrim(substr($slug, 0, max(1, $room)), '_');
        $name = $slug . '_' . $random . $suffix;
        if (!$this->isValidName($name)) {
            throw new \LogicException('Generated sticker set name is invalid');
        }
        return $name;
    }

    public function isValidName(string $name): bool
    {
        $suffix = '_by_' . $this->bot;
        return strlen($name) <= 64
            && strlen($name) > strlen($suffix)
            && preg_match('/^[a-z][a-z0-9_]*$/i', $name) === 1
            && !str_contains($name, '__')
            && str_ends_with(strtolower($name), $suffix);
    }

    public function link(string $name): string
    {
        return 'https://t.me/addemoji/' . $name;
    }

    /**
     * Creates the set with the first <= 50 files, adds the rest (<= 200 total),
     * reports progress via $onProgress(done, total) (exceptions thrown by the
     * callback, e.g. a cancellation, propagate).
     *
     * $items: list of ['path' => abs file, 'format' => 'video|animated|static', 'emoji' => ['⭐'], 'keywords' => []].
     * Returns ['name', 'link', 'added', 'failed' => [[index, reason]...], 'custom_emoji_ids' => [index => id],
     * 'file_ids' => [index => sticker file_id], 'created', 'count' (stickers in the set), 'warnings'].
     *
     * @param list<array<string,mixed>> $items
     * @param callable(int,int):void|null $onProgress
     * @return array<string,mixed>
     * @throws TelegramException when a new set could not be created at all (name taken, user invalid, every file rejected ...)
     */
    public function publish(int $userId, string $name, string $title, array $items, bool $needsRepainting = false, ?callable $onProgress = null, bool $existing = false): array
    {
        if (!$this->isValidName($name)) {
            throw new \InvalidArgumentException('Invalid sticker set name for this bot');
        }
        $title = self::cleanTitle($title);
        $items = array_values($items);
        $total = count($items);
        if ($total === 0) {
            throw new \InvalidArgumentException('No stickers to publish');
        }
        $this->floodWaited = 0;

        // every item makes two steps (upload + placement); progress is reported in items
        $steps = 0;
        $reported = -1;
        $tick = static function (int $add = 0, bool $force = false) use (&$steps, &$reported, $total, $onProgress): void {
            $steps += $add;
            $done = min($total, intdiv($steps, 2));
            if ($onProgress !== null && ($done !== $reported || $force)) {
                $reported = $done;
                $onProgress($done, $total);
            }
        };
        $tick();

        /** @var array<int,string> $failed */
        $failed = [];
        /** @var array<int,array<string,mixed>> $inputs */
        $inputs = [];
        foreach ($items as $i => $item) {
            $reason = '';
            $input = $this->prepare($item, $reason);
            if ($input === null) {
                $failed[$i] = $reason;
                $tick(2);
            } else {
                $inputs[$i] = $input;
            }
        }

        $before = 0;
        if ($existing) {
            $before = $this->countStickers($name, $tick, true) ?? 0;
        }
        $pending = array_keys($inputs);
        foreach (array_splice($pending, max(0, $this->maxSet - $before)) as $i) {
            $failed[$i] = sprintf('set is full (max %d stickers)', $this->maxSet);
            $tick(2);
        }

        /** @var array<int,array<string,mixed>> $uploaded */
        $uploaded = [];
        /** @var list<int> $placed item indexes in the order they entered the set */
        $placed = [];

        if (!$existing) {
            $round = $this->maxInitial;
            $chunkError = null;
            $strikes = 0;
            $created = false;
            while (!$created && $pending !== []) {
                $chunk = [];
                while ($pending !== [] && count($chunk) < $round) {
                    $i = array_shift($pending);
                    if (!isset($uploaded[$i])) {
                        try {
                            $uploaded[$i] = $this->upload($userId, $inputs[$i], $tick);
                            $tick(1);
                        } catch (TelegramException $e) {
                            if ($this->isFatal($e)) {
                                throw $e;
                            }
                            $failed[$i] = self::reason($e);
                            $tick(2);
                            continue;
                        }
                    }
                    $chunk[] = $i;
                }
                if ($chunk === []) {
                    break;
                }
                try {
                    $this->createSet($userId, $name, $title, $this->stickers($chunk, $inputs, $uploaded), $needsRepainting, $tick);
                    $created = true;
                    $placed = $chunk;
                    $tick(count($chunk));
                } catch (TelegramException $e) {
                    if ($this->isFatal($e)) {
                        throw $e;
                    }
                    if (count($chunk) > 1) {
                        // find the faulty sticker(s): start the set with one sticker, add the others singly
                        $chunkError = $e->getMessage();
                        $round = 1;
                        array_unshift($pending, ...$chunk);
                        continue;
                    }
                    $failed[$chunk[0]] = self::reason($e);
                    $tick(1);
                    if ($chunkError !== null && $e->getMessage() === $chunkError && ++$strikes >= 3) {
                        // the same rejection for every sticker: the request itself is the problem
                        throw new TelegramException('Sticker set was not created: ' . $e->getMessage(), $e->errorCode(), null, null, $e);
                    }
                }
            }
            if (!$created) {
                ksort($failed);
                $first = $failed !== [] ? (string) reset($failed) : 'no valid sticker';
                throw new TelegramException('Sticker set was not created: ' . $first, 400);
            }
        }

        $fatal = null;
        foreach ($pending as $i) {
            if ($fatal !== null) {
                $failed[$i] = $fatal;
                $tick(isset($uploaded[$i]) ? 1 : 2);
                continue;
            }
            if (!isset($uploaded[$i])) {
                try {
                    $uploaded[$i] = $this->upload($userId, $inputs[$i], $tick);
                    $tick(1);
                } catch (TelegramException $e) {
                    $failed[$i] = self::reason($e);
                    $tick(2);
                    if ($this->isFatal($e)) {
                        $fatal = self::reason($e);
                    }
                    continue;
                }
            }
            try {
                $sticker = $this->stickers([$i], $inputs, $uploaded)[0];
                $this->addSticker($userId, $name, $sticker, $before + count($placed), $tick);
                $placed[] = $i;
                $tick(1);
            } catch (TelegramException $e) {
                $failed[$i] = self::reason($e);
                $tick(1);
                if ($this->isFatal($e)) {
                    $fatal = self::reason($e);
                }
            }
        }

        $customIds = [];
        $fileIds = [];
        $warnings = [];
        $count = $before + count($placed);
        if ($placed !== []) {
            try {
                $set = $this->guard(fn () => $this->tg->getStickerSet($name), $tick);
                $stickers = array_values(array_filter((array) ($set['stickers'] ?? []), 'is_array'));
                $count = count($stickers);
                $byUnique = [];
                foreach ($stickers as $pos => $s) {
                    if (isset($s['file_unique_id']) && is_string($s['file_unique_id'])) {
                        $byUnique[$s['file_unique_id']] = $pos;
                    }
                }
                foreach ($placed as $k => $i) {
                    $unique = (string) ($uploaded[$i]['file_unique_id'] ?? '');
                    $pos = $unique !== '' && isset($byUnique[$unique]) ? $byUnique[$unique] : $before + $k;
                    $s = $stickers[$pos] ?? null;
                    if ($s === null) {
                        continue;
                    }
                    if (isset($s['custom_emoji_id'])) {
                        $customIds[$i] = (string) $s['custom_emoji_id'];
                    }
                    if (isset($s['file_id'])) {
                        $fileIds[$i] = (string) $s['file_id'];
                    }
                }
            } catch (TelegramException $e) {
                $warnings[] = 'custom emoji ids unavailable: ' . self::reason($e);
            }
        }

        ksort($failed);
        ksort($customIds);
        ksort($fileIds);
        $failedList = [];
        foreach ($failed as $i => $reason) {
            $failedList[] = [$i, $reason];
        }
        $tick();
        return [
            'name' => $name,
            'link' => $this->link($name),
            'added' => count($placed),
            'failed' => $failedList,
            'custom_emoji_ids' => $customIds,
            'file_ids' => $fileIds,
            'created' => !$existing,
            'count' => $count,
            'warnings' => $warnings,
        ];
    }

    // ------------------------------------------------------------ internals

    /**
     * Validates one item; null + $reason when it cannot be published.
     * @return array{path:string,format:string,emoji_list:list<string>,keywords?:list<string>}|null
     */
    private function prepare(mixed $item, string &$reason): ?array
    {
        if (!is_array($item)) {
            $reason = 'invalid item';
            return null;
        }
        $path = $item['path'] ?? null;
        if (!is_string($path) || $path === '' || str_contains($path, "\0") || !self::isAbsolute($path) || !is_file($path) || !is_readable($path)) {
            $reason = 'file not found';
            return null;
        }
        $format = $item['format'] ?? null;
        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $reason = 'unsupported sticker format';
            return null;
        }
        $size = (int) @filesize($path);
        if ($size <= 0) {
            $reason = 'empty file';
            return null;
        }
        if ($size > self::MAX_BYTES[$format]) {
            $reason = sprintf('file too large for a %s sticker (%d KB > %d KB)', $format, (int) ceil($size / 1024), intdiv(self::MAX_BYTES[$format], 1024));
            return null;
        }
        $input = ['path' => $path, 'format' => $format, 'emoji_list' => self::emojiList($item['emoji'] ?? [], $this->maxEmoji)];
        $keywords = self::keywords($item['keywords'] ?? []);
        if ($keywords !== []) {
            $input['keywords'] = $keywords;
        }
        return $input;
    }

    /**
     * InputSticker objects referencing the uploaded file_id.
     * @param list<int> $indexes
     * @param array<int,array<string,mixed>> $inputs
     * @param array<int,array<string,mixed>> $uploaded
     * @return list<array<string,mixed>>
     */
    private function stickers(array $indexes, array $inputs, array $uploaded): array
    {
        $out = [];
        foreach ($indexes as $i) {
            $sticker = [
                'sticker' => (string) $uploaded[$i]['file_id'],
                'format' => $inputs[$i]['format'],
                'emoji_list' => $inputs[$i]['emoji_list'],
            ];
            if (isset($inputs[$i]['keywords'])) {
                $sticker['keywords'] = $inputs[$i]['keywords'];
            }
            $out[] = $sticker;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> File
     */
    private function upload(int $userId, array $input, callable $tick): array
    {
        $file = $this->guard(fn () => $this->tg->uploadStickerFile($userId, (string) $input['path'], (string) $input['format']), $tick);
        if (!is_string($file['file_id'] ?? null) || $file['file_id'] === '') {
            throw new TelegramException('uploadStickerFile returned no file_id', 0);
        }
        return $file;
    }

    /**
     * A timed out create may have succeeded on Telegram's side: look before repeating it.
     * @param list<array<string,mixed>> $stickers
     */
    private function createSet(int $userId, string $name, string $title, array $stickers, bool $needsRepainting, callable $tick): void
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $this->guard(fn () => $this->tg->createNewStickerSet($userId, $name, $title, $stickers, 'custom_emoji', $needsRepainting), $tick);
                return;
            } catch (TelegramException $e) {
                if (!$e->isNetworkError() || $attempt >= 1) {
                    throw $e;
                }
                if ($this->countStickers($name, $tick) !== null) {
                    return;
                }
            }
        }
    }

    /** @param array<string,mixed> $sticker */
    private function addSticker(int $userId, string $name, array $sticker, int $countBefore, callable $tick): void
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $this->guard(fn () => $this->tg->addStickerToSet($userId, $name, $sticker), $tick);
                return;
            } catch (TelegramException $e) {
                if (!$e->isNetworkError() || $attempt >= 1) {
                    throw $e;
                }
                $count = $this->countStickers($name, $tick);
                if ($count !== null && $count > $countBefore) {
                    return;
                }
            }
        }
    }

    /** Number of stickers in the set, null when it does not exist / is unreachable ($throw: rethrow instead). */
    private function countStickers(string $name, callable $tick, bool $throw = false): ?int
    {
        try {
            $set = $this->guard(fn () => $this->tg->getStickerSet($name), $tick);
            return count((array) ($set['stickers'] ?? []));
        } catch (TelegramException $e) {
            if ($throw) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Waits out flood control that the client did not absorb (long retry_after),
     * within a per publish budget; reports progress meanwhile as a heartbeat.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private function guard(callable $fn, callable $tick): mixed
    {
        while (true) {
            try {
                return $fn();
            } catch (TelegramException $e) {
                if (!$e->isFlood() || $e->retryAfter === null) {
                    throw $e;
                }
                $wait = max(1, $e->retryAfter);
                if ($wait > self::MAX_FLOOD_WAIT || $this->floodWaited + $wait > self::FLOOD_BUDGET) {
                    throw $e;
                }
                $this->floodWaited += $wait;
                $tick(0, true);
                ($this->sleep)($wait);
            }
        }
    }

    /** Errors after which no further sticker can succeed. */
    private function isFatal(TelegramException $e): bool
    {
        if ($e->isNetworkError() || $e->isFlood()) {
            return true;
        }
        $code = $e->errorCode();
        if ($code === 401 || $code === 403 || $code === 404) {
            return true;
        }
        return preg_match(self::FATAL_PATTERN, $e->getMessage()) === 1;
    }

    private static function reason(TelegramException $e): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));
        return mb_substr($text !== '' ? $text : 'Telegram error ' . $e->errorCode(), 0, 200);
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /** 1..$max single emoji (grapheme clusters), letters / digits dropped; default ⭐. */
    private static function emojiList(mixed $emoji, int $max): array
    {
        $values = is_array($emoji) ? $emoji : [$emoji];
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (preg_match_all('/\X/u', $value, $m) === false) {
                continue;
            }
            foreach ($m[0] as $cluster) {
                if (trim($cluster) === '' || preg_match('/^[\p{L}\p{N}\p{P}\p{Z}\s]+$/u', $cluster) || strlen($cluster) > 48) {
                    continue;
                }
                if (!in_array($cluster, $out, true)) {
                    $out[] = $cluster;
                }
                if (count($out) >= $max) {
                    return $out;
                }
            }
        }
        return $out !== [] ? $out : [self::DEFAULT_EMOJI];
    }

    /** 0..20 search keywords, total length <= 64 characters. @return list<string> */
    private static function keywords(mixed $keywords): array
    {
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }
        if (!is_array($keywords)) {
            return [];
        }
        $out = [];
        $length = 0;
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $keyword = trim((string) preg_replace('/\s+/u', ' ', $keyword));
            if ($keyword === '' || in_array($keyword, $out, true)) {
                continue;
            }
            $len = mb_strlen($keyword);
            if ($length + $len > self::MAX_KEYWORD_CHARS || count($out) >= self::MAX_KEYWORDS) {
                break;
            }
            $out[] = $keyword;
            $length += $len;
        }
        return $out;
    }

    /** 1-64 characters (UTF-16 units like Telegram counts them), no control characters. */
    private static function cleanTitle(string $title): string
    {
        $title = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $title);
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        if ($title === '') {
            throw new \InvalidArgumentException('Sticker set title is empty');
        }
        $out = '';
        $units = 0;
        foreach (mb_str_split($title) as $char) {
            $u = mb_ord($char) > 0xFFFF ? 2 : 1;
            if ($units + $u > self::MAX_TITLE_UNITS) {
                break;
            }
            $out .= $char;
            $units += $u;
        }
        return rtrim($out);
    }

    /** [a-z0-9] token; the user id salts the CSPRNG output so parallel users never share a stream. */
    private static function randomToken(int $userId, int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $bytes = hash('sha256', $userId . ':' . random_bytes(16), true);
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % 36];
        }
        return $out;
    }
}
