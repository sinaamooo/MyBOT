<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Services\AdminService;
use App\Services\AdminStateService;
use App\Services\LogService;
use App\Services\UserService;
use App\Telegram\Handlers\BacktestHandler;
use App\Telegram\Handlers\DashboardHandler;
use App\Telegram\Handlers\MenuHandler;
use App\Telegram\Handlers\SettingsHandler;
use App\Telegram\Handlers\SignalControlHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\StatsHandler;
use App\Telegram\Handlers\SymbolsHandler;
use App\Telegram\Handlers\UsersHandler;

/**
 * Routes a single incoming Telegram update (message or callback_query) to
 * the right handler. Replaces aiogram's Router/Dispatcher - PHP webhook
 * requests are one-shot, so there is no persistent event loop to register
 * handlers on; this class dispatches by simple string matching instead.
 */
final class UpdateHandler
{
    private const PUBLIC_COMMANDS = ['/start', '/help', '/status', '/signals', '/stats'];
    private const ADMIN_COMMANDS = ['/admin', '/start_scanner', '/stop_scanner', '/test_signal'];

    public function __construct(private readonly BotContext $ctx)
    {
    }

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            }
        } catch (\Throwable $e) {
            LogService::log('ERROR', 'webhook', 'Update handling failed: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $message */
    private function handleMessage(array $message): void
    {
        $chatId = (int) $message['chat']['id'];
        $from = $message['from'] ?? null;
        if ($from === null) {
            return;
        }
        $userId = (int) $from['id'];
        $text = trim((string) ($message['text'] ?? ''));

        UserService::register($userId, $from['username'] ?? null, $from['first_name'] ?? null);

        if ($text === '') {
            return;
        }

        if (str_starts_with($text, '/')) {
            $this->routeCommand($chatId, $userId, $text);
            return;
        }

        $state = AdminStateService::get($userId);
        if ($state !== null) {
            if (!AdminService::isAdmin($userId)) {
                AdminStateService::clear($userId);
                return;
            }
            $this->routeState($chatId, $userId, $text, $state);
        }
    }

    private function routeCommand(int $chatId, int $userId, string $text): void
    {
        $command = strtolower((string) strtok($text, " \n"));
        $command = (string) strtok($command, '@'); // strip @BotName suffix

        if (in_array($command, self::PUBLIC_COMMANDS, true)) {
            match ($command) {
                '/start' => StartHandler::start($chatId, $userId, $this->ctx),
                '/help' => StartHandler::help($chatId, $this->ctx),
                '/status' => StartHandler::status($chatId, $this->ctx),
                '/signals' => StartHandler::signals($chatId, $this->ctx),
                '/stats' => StartHandler::stats($chatId, $this->ctx),
                default => null,
            };
            return;
        }

        if (in_array($command, self::ADMIN_COMMANDS, true)) {
            if (!AdminService::isAdmin($userId)) {
                StartHandler::denied($chatId, $this->ctx);
                return;
            }
            match ($command) {
                '/admin' => MenuHandler::root($chatId, $this->ctx),
                '/start_scanner' => DashboardHandler::startScannerCmd($chatId, $this->ctx),
                '/stop_scanner' => DashboardHandler::stopScannerCmd($chatId, $this->ctx),
                '/test_signal' => SignalControlHandler::test($chatId, null, $this->ctx),
                default => null,
            };
        }
    }

    /** @param array{state:string, context:array<string,mixed>} $state */
    private function routeState(int $chatId, int $userId, string $text, array $state): void
    {
        $context = $state['context'];
        match ($state['state']) {
            'manual_signal_symbol' => SignalControlHandler::receiveManualSymbol($chatId, $userId, $text, $context, $this->ctx),
            'symbol_add' => SymbolsHandler::receiveNewSymbol($chatId, $userId, $text, $this->ctx),
            'setting_edit' => SettingsHandler::receiveSettingValue($chatId, $text, $context, $this->ctx, $userId),
            'template_edit' => SettingsHandler::receiveTemplate($chatId, $userId, $text, $this->ctx),
            'admin_add' => UsersHandler::receiveNewAdmin($chatId, $userId, $text, $this->ctx),
            'backtest_input' => BacktestHandler::run($chatId, $userId, $text, $this->ctx),
            default => AdminStateService::clear($userId),
        };
    }

    /** @param array<string, mixed> $callback */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) $callback['id'];
        $from = $callback['from'] ?? null;
        if ($from === null) {
            return;
        }
        $userId = (int) $from['id'];
        $data = (string) ($callback['data'] ?? '');
        $message = $callback['message'] ?? null;

        UserService::register($userId, $from['username'] ?? null, $from['first_name'] ?? null);

        // Public callback: the "Details" button on channel signal posts is visible to everyone.
        if (str_starts_with($data, 'sig_details:')) {
            $signalId = (int) explode(':', $data, 2)[1];
            StartHandler::sigDetails($callbackId, $userId, $signalId, $this->ctx);
            return;
        }

        if (!AdminService::isAdmin($userId)) {
            $this->ctx->telegram->answerCallbackQuery($callbackId, '⛔ دسترسی غیرمجاز', true);
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;
        if ($chatId === null || $messageId === null) {
            $this->ctx->telegram->answerCallbackQuery($callbackId);
            return;
        }

        try {
            $this->routeCallback($data, (int) $chatId, (int) $messageId, $userId);
        } catch (\Throwable $e) {
            LogService::log('ERROR', 'webhook', "Callback '{$data}' failed: " . $e->getMessage());
        }
        $this->ctx->telegram->answerCallbackQuery($callbackId);
    }

    private function routeCallback(string $data, int $chatId, int $messageId, int $userId): void
    {
        $parts = explode(':', $data);
        $prefix = $parts[0];

        if ($prefix === 'menu') {
            match ($parts[1] ?? '') {
                'root' => MenuHandler::rootEdit($chatId, $messageId, $this->ctx),
                'dashboard' => DashboardHandler::show($chatId, $messageId, $this->ctx),
                'signal_control' => SignalControlHandler::show($chatId, $messageId, $this->ctx),
                'symbols' => SymbolsHandler::show($chatId, $messageId, $this->ctx),
                'indicators' => SettingsHandler::showIndicators($chatId, $messageId, $this->ctx),
                'settings' => SettingsHandler::showMenu($chatId, $messageId, $this->ctx),
                'statistics' => StatsHandler::showStatistics($chatId, $messageId, $this->ctx),
                'active' => SignalControlHandler::showActive($chatId, $messageId, $this->ctx),
                'history' => StatsHandler::showHistory($chatId, $messageId, $this->ctx),
                'templates' => SettingsHandler::showTemplates($chatId, $messageId, $this->ctx),
                'backtest' => BacktestHandler::show($chatId, $messageId, $this->ctx),
                'users' => UsersHandler::showUsers($chatId, $messageId, $this->ctx),
                'admins' => UsersHandler::showAdmins($chatId, $messageId, $this->ctx),
                'logs' => UsersHandler::showLogs($chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'scanner') {
            DashboardHandler::control($parts[1] ?? '', $chatId, $messageId, $this->ctx);
            return;
        }

        if ($prefix === 'signals') {
            match ($parts[1] ?? '') {
                'enable' => SignalControlHandler::toggle(true, $chatId, $messageId, $this->ctx),
                'disable' => SignalControlHandler::toggle(false, $chatId, $messageId, $this->ctx),
                'manual' => SignalControlHandler::startManual($parts[2] ?? 'LONG', $chatId, $messageId, $userId, $this->ctx),
                'test' => SignalControlHandler::test($chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'sym') {
            $action = $parts[1] ?? '';
            $symbol = $parts[2] ?? '';
            match ($action) {
                'toggle' => SymbolsHandler::toggle($symbol, $chatId, $messageId, $this->ctx),
                'remove' => SymbolsHandler::remove($symbol, $chatId, $messageId, $this->ctx),
                'add' => SymbolsHandler::startAdd($chatId, $messageId, $userId, $this->ctx),
                'sync' => SymbolsHandler::sync($chatId, $messageId, $this->ctx),
                'page' => SymbolsHandler::paginate((int) $symbol, $chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'ind') {
            if (isset($parts[2])) {
                SettingsHandler::toggleIndicator($parts[2], $chatId, $messageId, $this->ctx);
            }
            return;
        }

        if ($prefix === 'set') {
            match ($parts[1] ?? '') {
                'cat' => SettingsHandler::showCategory($parts[2] ?? 'scoring', $chatId, $messageId, $this->ctx),
                'bool' => SettingsHandler::toggleBool($parts[2] ?? '', $chatId, $messageId, $this->ctx),
                'edit' => SettingsHandler::startEdit($parts[2] ?? '', $chatId, $messageId, $userId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'sig') {
            $id = (int) ($parts[2] ?? 0);
            match ($parts[1] ?? '') {
                'view' => SignalControlHandler::view($id, $chatId, $messageId, $this->ctx),
                'cancel' => SignalControlHandler::cancel($id, $chatId, $messageId, $this->ctx),
                'close' => SignalControlHandler::close($id, $chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'hist' && ($parts[1] ?? '') === 'page') {
            StatsHandler::paginateHistory((int) ($parts[2] ?? 0), $chatId, $messageId, $this->ctx);
            return;
        }

        if ($prefix === 'tpl') {
            match ($parts[1] ?? '') {
                'edit' => SettingsHandler::startEditTemplate($chatId, $messageId, $userId, $this->ctx),
                'reset' => SettingsHandler::resetTemplate($chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'usr' && ($parts[1] ?? '') === 'page') {
            UsersHandler::paginateUsers((int) ($parts[2] ?? 0), $chatId, $messageId, $this->ctx);
            return;
        }

        if ($prefix === 'adm') {
            match ($parts[1] ?? '') {
                'add' => UsersHandler::startAddAdmin($chatId, $messageId, $userId, $this->ctx),
                'remove' => UsersHandler::removeAdmin((int) ($parts[2] ?? 0), $chatId, $messageId, $this->ctx),
                default => null,
            };
            return;
        }

        if ($prefix === 'bt' && ($parts[1] ?? '') === 'start') {
            BacktestHandler::startInput($chatId, $messageId, $userId, $this->ctx);
        }
    }
}
