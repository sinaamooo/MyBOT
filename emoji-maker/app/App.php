<?php

declare(strict_types=1);

namespace EmojiMaker;

use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Database\Database;
use EmojiMaker\Database\Repositories\AssetRepository;
use EmojiMaker\Database\Repositories\FileRepository;
use EmojiMaker\Database\Repositories\MiscRepository;
use EmojiMaker\Database\Repositories\PresetRepository;
use EmojiMaker\Database\Repositories\ProjectRepository;
use EmojiMaker\Database\Repositories\UserRepository;
use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Process\Tools;
use EmojiMaker\Queue\JobQueue;
use EmojiMaker\Rendering\EmojiRenderer;
use EmojiMaker\Security\Crypto;
use EmojiMaker\Security\Redactor;
use EmojiMaker\Storage\Storage;
use EmojiMaker\Support\Config;
use EmojiMaker\Support\Env;
use EmojiMaker\Support\Logger;
use EmojiMaker\Support\Settings;
use EmojiMaker\Telegram\TelegramClient;
use EmojiMaker\Templates\TemplateLibrary;
use EmojiMaker\Templates\TemplateRenderer;
use EmojiMaker\Text\FontRegistry;

/**
 * Composition root: builds every service lazily from .env + config/app.php.
 * One instance per request / CLI process (App::boot()).
 *
 * Secrets: the bot token comes from TELEGRAM_BOT_TOKEN (.env) or, when set in
 * the admin panel, from the encrypted "telegram_token" setting; it is
 * registered with the Redactor so it never appears in logs.
 */
final class App
{
    private static ?self $instance = null;

    private ?Database $db = null;
    private ?Settings $settings = null;
    private ?Crypto $crypto = null;
    private ?Storage $storage = null;
    private ?Logger $logger = null;
    private ?ProcessRunner $runner = null;
    private ?Tools $tools = null;
    private ?FontRegistry $fonts = null;
    private ?EmojiRenderer $renderer = null;
    private ?TemplateLibrary $templates = null;
    private ?TemplateRenderer $templateRenderer = null;
    private ?TelegramClient $telegram = null;
    private ?JobQueue $queue = null;
    /** @var array<string,object> */
    private array $repos = [];
    private ?bool $dbOk = null;

    private function __construct(private string $root, private Config $config)
    {
    }

    /** Loads .env and the configuration, sets PHP defaults; idempotent. */
    public static function boot(?string $root = null): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $root ??= dirname(__DIR__);
        if (is_file($root . '/.env')) {
            Env::load($root . '/.env');
        }
        $config = new Config(require $root . '/config/app.php');
        date_default_timezone_set((string) $config->get('timezone', 'Asia/Tehran'));
        mb_internal_encoding('UTF-8');
        Redactor::registerSecret((string) $config->get('telegram.token', ''));
        Redactor::registerSecret((string) $config->get('telegram.webhook_secret', ''));
        Redactor::registerSecret((string) $config->get('db.pass', ''));
        Redactor::registerSecret((string) $config->get('key', ''));
        return self::$instance = new self($root, $config);
    }

    /** For tests: replaces the process wide instance. */
    public static function setInstance(?self $app): void
    {
        self::$instance = $app;
    }

    public static function instance(): self
    {
        return self::$instance ?? self::boot();
    }

    public function root(): string
    {
        return $this->root;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function isDebug(): bool
    {
        return (bool) $this->config->get('debug', false);
    }

    /** Public base URL of the "public" folder, no trailing slash. */
    public function url(string $path = ''): string
    {
        $base = rtrim((string) $this->config->get('url', ''), '/');
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    public function db(): Database
    {
        return $this->db ??= new Database((array) $this->config->get('db', []));
    }

    public function crypto(): Crypto
    {
        return $this->crypto ??= new Crypto((string) $this->config->get('key', ''));
    }

    public function hasKey(): bool
    {
        try {
            $this->crypto();
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->db(), (array) $this->config->get('defaults', []), $this->hasKey() ? $this->crypto() : null);
    }

    public function storage(): Storage
    {
        if ($this->storage === null) {
            $path = (string) $this->config->get('storage.path', '');
            $this->storage = new Storage($path !== '' ? $path : $this->root . '/storage');
        }
        return $this->storage;
    }

    public function logger(string $channel = 'app'): Logger
    {
        $this->logger ??= new Logger($this->storage()->root() . '/logs', 'app', $this->isDebug() ? 'debug' : 'info');
        return $channel === 'app' ? $this->logger : $this->logger->withChannel($channel);
    }

    public function runner(): ProcessRunner
    {
        return $this->runner ??= new ProcessRunner((int) $this->config->get('tools.timeout', 120), (int) $this->config->get('tools.nice', 10));
    }

    public function tools(): Tools
    {
        return $this->tools ??= new Tools(
            $this->runner(),
            $this->storage()->root() . '/cache/tools.json',
            (string) $this->config->get('tools.ffmpeg', ''),
            (string) $this->config->get('tools.ffprobe', ''),
        );
    }

    /** Built-in fonts plus fonts uploaded in the admin panel. */
    public function fonts(): FontRegistry
    {
        if ($this->fonts === null) {
            $extra = [];
            try {
                foreach ($this->presets()->fonts(true) as $f) {
                    $file = (string) $f['file'];
                    $abs = str_starts_with($file, 'fonts/') ? $this->storage()->root() . '/' . $file : $this->root . '/' . ltrim($file, '/');
                    $extra[(string) $f['slug']] = ['name' => (string) $f['name'], 'file' => $abs, 'persian' => (bool) $f['supports_persian']];
                }
            } catch (\Throwable) {
                $extra = [];
            }
            $this->fonts = new FontRegistry($this->root . '/resources/fonts', $extra);
            FontRegistry::setDefault($this->fonts);
        }
        return $this->fonts;
    }

    public function effects(): EffectRegistry
    {
        return EffectRegistry::instance();
    }

    /** @return array<string,mixed> renderer options from the runtime settings */
    private function renderOptions(): array
    {
        $s = $this->settings();
        return [
            'webm_fps' => $s->int('webm_fps', 30),
            'webm_crf' => $s->int('webm_crf', 30),
            'work_res' => 400,
            'preview_size' => 256,
            'preview_bg' => '#1C2733',
            'vector_max_colors' => $s->int('vectorize_max_colors', 6),
        ];
    }

    public function renderer(): EmojiRenderer
    {
        return $this->renderer ??= new EmojiRenderer($this->fonts(), $this->effects(), $this->tools(), (array) $this->config->get('telegram_formats'), $this->renderOptions());
    }

    public function templates(): TemplateLibrary
    {
        return $this->templates ??= new TemplateLibrary(
            [$this->root . '/resources/templates', $this->storage()->root() . '/templates'],
            $this->dbAvailable() ? $this->db() : null,
        );
    }

    public function templateRenderer(): TemplateRenderer
    {
        return $this->templateRenderer ??= new TemplateRenderer($this->fonts(), $this->tools(), (array) $this->config->get('telegram_formats'), $this->renderOptions());
    }

    /** Bot token: encrypted admin setting first, then .env. */
    public function botToken(): string
    {
        $token = '';
        if ($this->dbAvailable() && $this->hasKey()) {
            try {
                $token = (string) ($this->settings()->get('telegram_token') ?? '');
            } catch (\Throwable) {
                $token = '';
            }
        }
        if ($token === '') {
            $token = (string) $this->config->get('telegram.token', '');
        }
        Redactor::registerSecret($token);
        return $token;
    }

    public function webhookSecret(): string
    {
        $secret = '';
        if ($this->dbAvailable() && $this->hasKey()) {
            try {
                $secret = (string) ($this->settings()->get('webhook_secret') ?? '');
            } catch (\Throwable) {
                $secret = '';
            }
        }
        if ($secret === '') {
            $secret = (string) $this->config->get('telegram.webhook_secret', '');
        }
        Redactor::registerSecret($secret);
        return $secret;
    }

    /** Bot username without "@": .env, cached setting, else getMe. */
    public function botUsername(): string
    {
        $name = ltrim((string) $this->config->get('telegram.username', ''), '@');
        if ($name !== '') {
            return $name;
        }
        if ($this->dbAvailable()) {
            $cached = (string) $this->settings()->string('bot_username', '');
            if ($cached !== '') {
                return $cached;
            }
        }
        $me = $this->telegram()->getMe();
        $name = (string) ($me['username'] ?? '');
        if ($name !== '' && $this->dbAvailable()) {
            $this->settings()->set('bot_username', $name, 'string');
        }
        return $name;
    }

    /** @return list<int> Telegram ids with bot admin rights */
    public function adminIds(): array
    {
        $ids = (array) $this->config->get('telegram.admin_ids', []);
        if ($this->dbAvailable()) {
            $extra = (string) $this->settings()->string('bot_admin_ids', '');
            foreach (explode(',', $extra) as $v) {
                if (ctype_digit(trim($v))) {
                    $ids[] = (int) trim($v);
                }
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function telegram(): TelegramClient
    {
        return $this->telegram ??= new TelegramClient(
            $this->botToken(),
            (string) $this->config->get('telegram.api_base', 'https://api.telegram.org'),
            $this->logger('telegram'),
        );
    }

    public function queue(): JobQueue
    {
        return $this->queue ??= new JobQueue($this->db(), $this->logger('queue'));
    }

    /** True when the database is configured and reachable. */
    public function dbAvailable(): bool
    {
        if ($this->dbOk !== null) {
            return $this->dbOk;
        }
        if ((string) $this->config->get('db.name', '') === '') {
            return $this->dbOk = false;
        }
        try {
            $this->db()->pdo();
            return $this->dbOk = true;
        } catch (\Throwable) {
            return $this->dbOk = false;
        }
    }

    public function users(): UserRepository
    {
        return $this->repos['users'] ??= new UserRepository($this->db());
    }

    public function projects(): ProjectRepository
    {
        return $this->repos['projects'] ??= new ProjectRepository($this->db());
    }

    public function assets(): AssetRepository
    {
        return $this->repos['assets'] ??= new AssetRepository($this->db());
    }

    public function files(): FileRepository
    {
        return $this->repos['files'] ??= new FileRepository($this->db());
    }

    public function presets(): PresetRepository
    {
        return $this->repos['presets'] ??= new PresetRepository($this->db());
    }

    public function misc(): MiscRepository
    {
        return $this->repos['misc'] ??= new MiscRepository($this->db());
    }

    /** Installs error / exception handlers that log (redacted) instead of printing. */
    public function registerErrorHandlers(bool $cli): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $cli && $this->isDebug() ? '1' : '0');
        set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
        set_exception_handler(function (\Throwable $e) use ($cli): void {
            try {
                $this->logger()->exception($e);
            } catch (\Throwable) {
                // logging must never hide the original failure
            }
            if ($cli) {
                fwrite(STDERR, Redactor::text($e->getMessage()) . "\n");
                exit(1);
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo 'Internal error';
        });
    }
}
