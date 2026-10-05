<?php

declare(strict_types=1);

namespace App;

/**
 * Loads the banner (Nikto) and signal modules on demand and hands them Telegram updates.
 * Each module keeps its own code, database, panel and settings; only the bot token,
 * admins and timezone are shared through the main config.php.
 */
final class Modules
{
    public const ANALYSIS = 'analysis';
    public const BANNER = 'banner';
    public const SIGNAL = 'signal';

    public static function bannerFile(): string
    {
        return HUB_ROOT . '/modules/banner/nikto-bot.php';
    }

    public static function signalDir(): string
    {
        return HUB_ROOT . '/modules/signal';
    }

    public static function installed(string $module): bool
    {
        return match ($module) {
            self::ANALYSIS => true,
            self::BANNER => is_file(self::bannerFile()),
            self::SIGNAL => is_file(self::signalDir() . '/bot.php') && PHP_VERSION_ID >= 80100,
            default => false,
        };
    }

    // ------------------------------------------------------------------ banner

    public static function bannerConfig(array $config): array
    {
        $admins = array_values(array_map('intval', (array) ($config['admin_ids'] ?? [])));
        $banner = (array) ($config['banner'] ?? []);
        return [
            'bot_token' => (string) ($config['bot_token'] ?? ''),
            'owner_id' => $admins[0] ?? 0,
            'admins' => $admins,
            'webhook_url' => (string) ($config['webhook_url'] ?? ''),
            'webhook_secret' => (string) ($config['webhook_secret'] ?? ''),
            'timezone' => (string) ($config['timezone'] ?? 'Asia/Tehran'),
            'brand' => (string) ($banner['brand'] ?? 'NIKTO CRYPTO'),
            'http_proxy' => (string) ($config['proxy'] ?? ''),
            'data_proxy' => (string) ($banner['data_proxy'] ?? ''),
            'coinglass_key' => (string) ($banner['coinglass_key'] ?? ''),
            'coingecko_key' => (string) ($banner['coingecko_key'] ?? ''),
            // Module data lives inside the hub's protected data folder.
            'root' => app_storage('banner'),
        ];
    }

    public static function loadBanner(array $config): void
    {
        if (class_exists(\Nikto\Telegram\Panel::class, false)) {
            return;
        }
        if (!defined('NIKTO_LIBRARY')) {
            define('NIKTO_LIBRARY', true);
        }
        if (!defined('NIKTO_CONFIG')) {
            define('NIKTO_CONFIG', self::bannerConfig($config));
        }
        require self::bannerFile();
        \Nikto\Core\Db::migrate();
        \Nikto\Jobs\Registry::all();
    }

    public static function dispatchBanner(array $config, array $update): void
    {
        self::loadBanner($config);
        date_default_timezone_set('UTC'); // the module works in UTC internally
        (new \Nikto\Telegram\Panel(new \Nikto\Telegram\Api()))->handleUpdate($update);
    }

    public static function bannerAwaitingInput(array $config, int $userId): bool
    {
        self::loadBanner($config);
        return \Nikto\Telegram\State::get($userId)['action'] !== '';
    }

    /** Runs the banner scheduler once (cron). */
    public static function bannerTick(array $config): int
    {
        self::loadBanner($config);
        date_default_timezone_set('UTC');
        return \Nikto\Bundle\Runtime::handleCli(['cron', 'cron']);
    }

    // ------------------------------------------------------------------ signal

    public static function loadSignal(): void
    {
        if (class_exists('BotApplication', false)) {
            return;
        }
        require_once self::signalDir() . '/bot.php';
        \Database::migrate();
    }

    public static function dispatchSignal(array $update): void
    {
        self::loadSignal();
        date_default_timezone_set(\Config::appTimezone());
        (new \BotApplication(new \TelegramClient(), new \ExchangeManager()))->handleUpdate($update);
    }

    public static function signalAwaitingInput(int $userId): bool
    {
        self::loadSignal();
        return (new \AdminStateStore())->get($userId) !== null;
    }

    /** Runs the signal worker for one cron cycle (CLI only, ~50 seconds). */
    public static function signalWorker(): void
    {
        if (!defined('WORKER_BOOTSTRAP_ONLY')) {
            define('WORKER_BOOTSTRAP_ONLY', true);
        }
        require_once self::signalDir() . '/worker.php';
        date_default_timezone_set(\Config::appTimezone());
        (new \Worker())->run();
    }
}
