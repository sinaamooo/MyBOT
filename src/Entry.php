<?php

declare(strict_types=1);

namespace App;

use App\Support\Fa;
use App\Support\Http;
use App\Telegram\Client;

/**
 * Entry points shared by webhook.php / poll.php / tools/* and the single-file build.
 */
final class Entry
{
    /** Handles a Telegram webhook request. */
    public static function webhook(array $config): void
    {
        $secret = (string) ($config['webhook_secret'] ?? '');
        if ($secret !== '' && !hash_equals($secret, (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
            http_response_code(403);
            exit('forbidden');
        }
        $update = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($update)) {
            exit('ok');
        }

        // Answer Telegram right away; an analysis can take up to a minute.
        ignore_user_abort(true);
        set_time_limit(300);
        http_response_code(200);
        header('Content-Type: text/plain');
        header('Content-Length: 2');
        header('Connection: close');
        echo 'ok';
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        try {
            (new Hub($config))->handle($update);
        } catch (\Throwable $e) {
            app_log('webhook error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /** Long polling loop (CLI). */
    public static function poll(array $config, bool $deleteWebhook): void
    {
        $tg = new Client((string) $config['bot_token']);
        if ($deleteWebhook) {
            $tg->call('deleteWebhook');
            echo "Webhook removed.\n";
        }
        $me = $tg->call('getMe');
        if ($me === null) {
            exit("Bot token is invalid or Telegram is unreachable.\n");
        }
        echo 'Running as @' . $me['username'] . "\n";
        $bot = new Hub($config, $tg);
        $offset = 0;
        while (true) {
            $updates = $tg->call('getUpdates', ['offset' => $offset, 'timeout' => 50, 'allowed_updates' => ['message', 'callback_query', 'my_chat_member']]);
            if ($updates === null) {
                sleep(3);
                continue;
            }
            foreach ($updates as $u) {
                $offset = $u['update_id'] + 1;
                try {
                    $bot->handle($u);
                } catch (\Throwable $e) {
                    app_log('poll error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                    echo 'Error: ', $e->getMessage(), "\n";
                }
            }
        }
    }

    /** @return string[] human readable check results */
    public static function check(array $config): array
    {
        $out = [];
        $ok = static function (bool $c, string $label, string $hint = '') use (&$out): void {
            $out[] = ($c ? '[OK]   ' : '[FAIL] ') . $label . ($c || $hint === '' ? '' : "  -> $hint");
        };
        $ok(PHP_VERSION_ID >= 80000, 'PHP ' . PHP_VERSION, 'PHP 8.0 or newer is required');
        foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite', 'json', 'simplexml'] as $ext) {
            $ok(extension_loaded($ext), "extension $ext", "enable php-$ext on the host");
        }
        $ok(function_exists('imagettftext'), 'GD FreeType support', 'GD must be built with FreeType');
        $dir = app_storage();
        $ok(is_writable($dir), 'storage writable (' . basename($dir) . ')', 'chmod 775 on the storage folder');
        $font = app_font('Bold');
        $ok(is_file($font) && filesize($font) > 50000, 'fonts', 'could not download Vazirmatn fonts; upload them to ' . dirname($font));

        $tg = new Client((string) $config['bot_token']);
        $me = $tg->call('getMe');
        $ok($me !== null, 'Telegram bot token' . ($me ? ' (@' . $me['username'] . ')' : ''), 'check bot_token / server access to api.telegram.org');
        if ($me) {
            $info = $tg->call('getWebhookInfo');
            $out[] = '       webhook: ' . (($info['url'] ?? '') ?: '(none)')
                . (!empty($info['pending_update_count']) ? ' | pending: ' . $info['pending_update_count'] : '')
                . (isset($info['last_error_message']) ? ' | last error: ' . $info['last_error_message'] : '');
        }

        $market = new Market\MarketData($config['market'] ?? [], app_storage('cache'));
        try {
            $s = $market->candles('BTC', '4h', 50);
            $ok(true, 'market data (' . $market->lastProvider . ', BTC ' . Fa::price($s->lastClose()) . ')');
        } catch (\Throwable $e) {
            $ok(false, 'market data', 'no exchange API reachable from this server; set "proxy" in config or use another host');
        }

        // Modules
        $ok(Modules::installed(Modules::BANNER), 'banner module (modules/banner/nikto-bot.php)', 'upload the modules/banner folder');
        $signalFiles = is_file(Modules::signalDir() . '/bot.php');
        $ok($signalFiles && PHP_VERSION_ID >= 80100, 'signal module (modules/signal)', $signalFiles ? 'the signal bot needs PHP 8.1 or newer' : 'upload the modules/signal folder');
        $last = is_file(app_storage() . '/cron.last') ? (string) file_get_contents(app_storage() . '/cron.last') : '';
        $lastTs = $last !== '' ? strtotime(substr($last, 0, 19)) : false;
        $ok($lastTs !== false && $lastTs > time() - 180, 'cron' . ($last !== '' ? ' (last run ' . substr($last, 0, 19) . ')' : ' (never ran)'),
            'add a cron job every minute: php ' . HUB_ROOT . '/cron.php');

        $key = (string) ($config['gemini']['api_key'] ?? '');
        if ($key === '') {
            $out[] = '[--]   Gemini disabled (no api_key)';
        } else {
            $res = Http::request('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1', ['headers' => ['x-goog-api-key: ' . $key]]);
            $ok($res['status'] === 200, 'Gemini API key', 'HTTP ' . $res['status'] . ' ' . substr($res['body'], 0, 150));
        }
        return $out;
    }

    public static function setWebhook(array $config, string $url): string
    {
        $tg = new Client((string) $config['bot_token']);
        $res = $tg->call('setWebhook', [
            'url' => $url,
            'secret_token' => $config['webhook_secret'],
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
            'max_connections' => 20,
        ]);
        return $res !== null ? "Webhook set: $url" : 'setWebhook failed (see logs in the storage folder)';
    }

    /** Public URL of the running script, e.g. https://example.com/bot/bot.php */
    public static function selfUrl(): string
    {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '/'), '?');
        return 'https://' . $host . $path;
    }
}
