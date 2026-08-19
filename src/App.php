<?php

declare(strict_types=1);

namespace MyBot;

use MyBot\Service\Activity;
use MyBot\Service\Banners;
use MyBot\Service\Campaigns;
use MyBot\Service\Dispatcher;
use MyBot\Service\Sender;
use MyBot\Service\Stats;
use MyBot\Service\States;
use MyBot\Service\Targets;
use MyBot\Service\Users;
use MyBot\Support\Config;
use MyBot\Support\Database;
use MyBot\Support\Logger;
use MyBot\Support\RateLimiter;
use MyBot\Support\TelegramClient;

/** Wires everything together. One instance per process. */
final class App
{
    public readonly Logger $logger;
    public readonly Database $db;
    public readonly TelegramClient $api;
    public readonly RateLimiter $limiter;

    public readonly Users $users;
    public readonly Targets $targets;
    public readonly Banners $banners;
    public readonly Campaigns $campaigns;
    public readonly Sender $sender;
    public readonly Activity $activity;
    public readonly Stats $stats;
    public readonly States $states;
    public readonly Dispatcher $dispatcher;

    /** @var list<int> */
    private readonly array $admins;

    public function __construct(public readonly Config $config, private readonly string $root)
    {
        $this->logger = new Logger(
            $this->path($config->string('logging.file', 'storage/bot.log')),
            $config->string('logging.level', 'info'),
        );

        $this->db = new Database($this->path($config->string('database.path', 'storage/bot.sqlite')));
        $this->db->migrate($this->root . '/schema.sql');

        $this->api = new TelegramClient(
            $config->require('bot.token'),
            $this->logger,
            $config->string('bot.api_base', 'https://api.telegram.org'),
            $config->int('bot.timeout', 60),
        );

        $this->limiter = new RateLimiter(
            $config->float('limits.global_per_second', 25.0),
            $config->float('limits.group_interval', 3.0),
            $config->float('limits.private_interval', 0.05),
        );

        $this->users     = new Users($this->db);
        $this->targets   = new Targets($this->db);
        $this->banners   = new Banners($this->db);
        $this->campaigns = new Campaigns($this->db, $this->targets, $this->users);
        $this->sender    = new Sender($this->api);
        $this->activity  = new Activity($this->db);
        $this->states    = new States($this->db);

        $this->stats = new Stats(
            $this->db,
            $this->users,
            $this->targets,
            $this->banners,
            $this->campaigns,
            $this->activity,
        );

        $this->dispatcher = new Dispatcher(
            $this->db,
            $this->sender,
            $this->banners,
            $this->campaigns,
            $this->targets,
            $this->users,
            $this->limiter,
            $this->logger,
            $config->int('limits.max_attempts', 3),
            $config->int('limits.batch_size', 50),
        );

        $this->admins = $config->ints('bot.admins');
    }

    public static function boot(string $root): self
    {
        require_once $root . '/src/Autoload.php';

        return new self(Config::load($root . '/config.php'), $root);
    }

    public function isAdmin(int $tgId): bool
    {
        return \in_array($tgId, $this->admins, true) || $this->users->isAdminInDb($tgId);
    }

    /** @return list<int> */
    public function admins(): array
    {
        return $this->admins;
    }

    public function path(string $relative): string
    {
        if (str_starts_with($relative, '/')) {
            return $relative;
        }

        return $this->root . '/' . ltrim($relative, '/');
    }

    public function root(): string
    {
        return $this->root;
    }
}
