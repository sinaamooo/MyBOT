<?php

declare(strict_types=1);

namespace EmojiMaker\Tests\Support;

use EmojiMaker\App;
use EmojiMaker\Bot\UpdateProcessor;
use EmojiMaker\Queue\Worker;
use EmojiMaker\Tests\TestCase;

/**
 * Drives the bot like a Telegram client: builds updates (messages, photos,
 * documents, button presses), feeds them to UpdateProcessor, runs the worker
 * and returns the Bot API calls the bot made (recorded by the mock server).
 * Needs TEST_DB_NAME (integration tests).
 */
final class BotDriver
{
    public const ADMIN = 900001;

    public App $app;
    /** @var array{base:string,log:string,control:string,state:string,fixtures:string,token:string,username:string} */
    public array $mock;
    private int $updateId = 1000;
    private int $messageId = 1;
    private int $mark = 0;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->mock = TestCase::telegramMock(['MOCK_FIXTURES' => dirname(__DIR__) . '/fixtures']);
        $this->app = TestCase::app($config + [
            'telegram.api_base' => $this->mock['base'],
            'telegram.token' => $this->mock['token'],
            'telegram.username' => $this->mock['username'],
            'telegram.admin_ids' => [self::ADMIN],
            'telegram.webhook_mode' => 'inline',
            'url' => 'https://emoji.example.test',
            'key' => 'base64:' . base64_encode(str_repeat('k', 32)),
        ]);
        $this->reset();
    }

    /** Empty database tables, mock state and call log. */
    public function reset(): void
    {
        $db = $this->app->db();
        $db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->fetchAll('SHOW TABLES') as $row) {
            $table = (string) array_values($row)[0];
            if (preg_match('/^[a-z_]+$/', $table)) {
                $db->pdo()->exec('TRUNCATE TABLE `' . $table . '`');
            }
        }
        $db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->app->settings()->refresh();
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'content' => '', 'timeout' => 5]]);
        @file_get_contents($this->mock['base'] . '/__mock/reset', false, $ctx);
        $this->mark = 0;
    }

    /** @param array<string,mixed> $extra */
    public function send(int $uid, string $text, array $extra = []): array
    {
        return $this->process(['message' => $this->message($uid, ['text' => $text] + $extra)]);
    }

    public function photo(int $uid, string $fixture): array
    {
        $size = (int) filesize($this->mock['fixtures'] . '/' . $fixture);
        return $this->process(['message' => $this->message($uid, ['photo' => [
            ['file_id' => $fixture, 'file_unique_id' => 'u' . md5($fixture), 'width' => 400, 'height' => 300, 'file_size' => $size],
        ]])]);
    }

    public function document(int $uid, string $fixture, string $mime = 'image/png'): array
    {
        $size = (int) filesize($this->mock['fixtures'] . '/' . $fixture);
        return $this->process(['message' => $this->message($uid, ['document' => [
            'file_id' => $fixture, 'file_unique_id' => 'd' . md5($fixture), 'file_name' => $fixture, 'mime_type' => $mime, 'file_size' => $size,
        ]])]);
    }

    /** Presses an inline button; the pressed message is the bot's last text message unless given. */
    public function press(int $uid, string $data, ?int $messageId = null, bool $media = false): array
    {
        $message = ['message_id' => $messageId ?? $this->lastBotMessage($uid), 'date' => time(), 'chat' => ['id' => $uid, 'type' => 'private', 'first_name' => 'U' . $uid]];
        $message += $media ? ['photo' => [['file_id' => 'x', 'file_unique_id' => 'x', 'width' => 1, 'height' => 1]]] : ['text' => '…'];
        return $this->process(['callback_query' => [
            'id' => 'cb' . (++$this->updateId),
            'from' => $this->from($uid),
            'chat_instance' => 'ci' . $uid,
            'data' => $data,
            'message' => $message,
        ]]);
    }

    /** Runs queued jobs until the queue is idle; returns the calls they made. */
    public function work(int $max = 20): array
    {
        $this->mark();
        $worker = new Worker($this->app, ['once' => true]);
        for ($i = 0; $i < $max && $worker->tick(); $i++) {
            // keep going
        }
        return $this->calls();
    }

    /** @param array<string,mixed> $update */
    public function process(array $update): array
    {
        $this->mark();
        $update['update_id'] = ++$this->updateId;
        (new UpdateProcessor($this->app))->process($update);
        return $this->calls();
    }

    /** Calls made since the last mark (all calls when $all). */
    public function calls(bool $all = false): array
    {
        $calls = TestCase::mockCalls($this->mock['log']);
        return $all ? $calls : array_slice($calls, $this->mark);
    }

    private function mark(): void
    {
        $this->mark = count(TestCase::mockCalls($this->mock['log']));
    }

    /** Last message id the bot sent (or edited) in the chat according to the mock state. */
    public function lastBotMessage(int $uid): int
    {
        $state = json_decode((string) @file_get_contents($this->mock['base'] . '/__mock/state'), true);
        $max = 1;
        foreach (array_keys((array) ($state['result']['messages'] ?? [])) as $key) {
            [$chat, $id] = array_pad(explode(':', (string) $key), 2, '0');
            if ((int) $chat === $uid) {
                $max = max($max, (int) $id);
            }
        }
        return $max;
    }

    /** Text of the calls that show something to the user (for assertions and debugging). */
    public static function texts(array $calls): array
    {
        $out = [];
        foreach ($calls as $c) {
            $p = (array) ($c['params'] ?? []);
            $t = $p['text'] ?? $p['caption'] ?? null;
            if (is_string($t)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** All callback_data values of the inline keyboards in the calls. */
    public static function buttons(array $calls): array
    {
        $out = [];
        foreach ($calls as $c) {
            foreach ((array) ($c['params']['reply_markup']['inline_keyboard'] ?? []) as $row) {
                foreach ((array) $row as $b) {
                    if (isset($b['callback_data'])) {
                        $out[(string) $b['callback_data']] = (string) $b['text'];
                    }
                }
            }
        }
        return $out;
    }

    /** First callback_data starting with $prefix (fails the test when absent). */
    public static function button(array $calls, string $prefix): string
    {
        foreach (array_keys(self::buttons($calls)) as $data) {
            if (str_starts_with($data, $prefix)) {
                return $data;
            }
        }
        TestCase::fail('no button starting with ' . $prefix . ' in: ' . implode(', ', array_keys(self::buttons($calls))));
    }

    /** @param list<array<string,mixed>> $calls */
    public static function methods(array $calls): array
    {
        return array_map(static fn ($c) => (string) $c['method'] . ($c['ok'] ? '' : '!' . ($c['error'] ?? '')), $calls);
    }

    /** Human readable transcript of calls (debugging). */
    public static function dump(array $calls): string
    {
        $out = '';
        foreach ($calls as $c) {
            $out .= '> ' . $c['method'] . ($c['ok'] ? '' : ' ERROR ' . ($c['error'] ?? '')) . "\n";
            foreach (self::texts([$c]) as $t) {
                $out .= '  ' . str_replace("\n", "\n  ", $t) . "\n";
            }
            foreach (self::buttons([$c]) as $data => $text) {
                $out .= '  [' . $text . '] ' . $data . "\n";
            }
        }
        return $out;
    }

    private function message(int $uid, array $fields): array
    {
        return [
            'message_id' => ++$this->messageId + 100000,
            'date' => time(),
            'chat' => ['id' => $uid, 'type' => 'private', 'first_name' => 'U' . $uid],
            'from' => $this->from($uid),
        ] + $fields;
    }

    private function from(int $uid): array
    {
        return ['id' => $uid, 'is_bot' => false, 'first_name' => 'U' . $uid, 'username' => 'user' . $uid, 'language_code' => 'fa'];
    }
}
