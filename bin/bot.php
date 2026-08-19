<?php

declare(strict_types=1);

/**
 * Long-polling runner: receives updates and answers the panel.
 *
 *   php bin/bot.php
 *
 * Sending is handled by a separate process (bin/worker.php) so a large
 * broadcast can never make the panel unresponsive.
 */

use MyBot\App;
use MyBot\Handlers\Router;
use MyBot\Support\ApiException;

$root = \dirname(__DIR__);
require_once $root . '/src/Autoload.php';

$app    = App::boot($root);
$router = new Router($app);

$me = $app->api->call('getMe');
$app->logger->info('bot online', [
    'username' => \is_array($me) ? ($me['username'] ?? '?') : '?',
    'admins'   => \count($app->admins()),
]);

// Registering the command list makes them appear in Telegram's menu.
$app->api->tryCall('setMyCommands', [
    'commands' => [
        ['command' => 'start', 'description' => 'عضویت و دریافت بنرها'],
        ['command' => 'stop', 'description' => 'لغو عضویت'],
        ['command' => 'help', 'description' => 'راهنما'],
        ['command' => 'panel', 'description' => 'پنل مدیریت (ادمین)'],
        ['command' => 'stats', 'description' => 'آمار (ادمین)'],
    ],
]);

$offsetKey = 'update_offset';

$stored = $app->db->one('SELECT value FROM settings WHERE key = :key', ['key' => $offsetKey]);
$offset = $stored === null ? 0 : (int) $stored['value'];

$running = true;

if (\function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(\SIGINT, static function () use (&$running): void { $running = false; });
    pcntl_signal(\SIGTERM, static function () use (&$running): void { $running = false; });
}

$timeout = $app->config->int('bot.poll_timeout', 30);

while ($running) {
    try {
        $updates = $app->api->getUpdates($offset, $timeout, [
            'message',
            'callback_query',
            'my_chat_member',
            'channel_post',
        ]);
    } catch (ApiException $e) {
        $app->logger->warn('getUpdates failed', ['error' => $e->getMessage()]);
        sleep($e->isFlood() ? ($e->retryAfter() ?? 5) : 3);

        continue;
    }

    foreach ($updates as $update) {
        $updateId = (int) ($update['update_id'] ?? 0);

        $router->handle($update);

        if ($updateId >= $offset) {
            $offset = $updateId + 1;

            $app->db->run(
                'INSERT INTO settings (key, value) VALUES (:key, :value)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value',
                ['key' => $offsetKey, 'value' => (string) $offset],
            );
        }
    }
}

$app->logger->info('bot stopped');
