<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\App;
use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Database\Repositories\ProjectRepository;
use EmojiMaker\Database\Repositories\UserRepository;
use EmojiMaker\Telegram\Keyboard;

/** Shared helpers of the bot controllers. */
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** Main menu reply keyboard. */
    protected function menu(): array
    {
        return Keyboard::reply(Texts::MENU, true, true, 'یک گزینه را انتخاب کن…');
    }

    /** A project of this user (not deleted) or null. */
    protected function project(Context $ctx, int $id): ?array
    {
        return $id > 0 ? $this->app->projects()->findForUser($id, $ctx->userId()) : null;
    }

    /** Archives old empty drafts before a new project is created (max_projects_per_user). */
    protected function makeRoom(Context $ctx): void
    {
        $this->app->projects()->pruneDrafts($ctx->userId(), max(5, $this->app->settings()->int('max_projects_per_user', 200)));
    }

    /** @return array<string,mixed> */
    protected function config(array $project): array
    {
        return ProjectRepository::config($project);
    }

    protected function save(array $project, array $config): void
    {
        $this->app->projects()->saveConfig((int) $project['id'], $config);
    }

    protected function expired(Context $ctx): void
    {
        $ctx->toast(Texts::EXPIRED, true);
    }

    /** @return array<string,mixed> */
    protected function perms(Context $ctx): array
    {
        return UserRepository::permissions($ctx->user);
    }

    protected function canTgs(Context $ctx): bool
    {
        return $this->app->settings()->bool('allow_tgs', true) && ($this->perms($ctx)['tgs'] ?? true) !== false;
    }

    protected function maxBatch(Context $ctx): int
    {
        $global = max(1, $this->app->settings()->int('max_batch_count', 100));
        $user = $ctx->user['max_batch'] ?? null;
        $perm = $this->perms($ctx)['max_batch'] ?? null;
        $limit = $user !== null ? (int) $user : ($perm !== null ? (int) $perm : $global);
        return max(1, $ctx->isAdmin() ? max($limit, $global) : min($limit, 200));
    }

    /**
     * Queues a job after the per-user checks (queued jobs, daily quota for
     * generate jobs). Returns the job id or null (the user was told why).
     */
    protected function pushJob(Context $ctx, string $type, array $payload, ?int $projectId, int $units = 0, int $priority = 5): ?int
    {
        $queue = $this->app->queue();
        $s = $this->app->settings();
        if (!$ctx->isAdmin()) {
            $active = $queue->activeCountForUser($ctx->userId());
            $maxQueued = max(1, $s->int('max_queued_jobs_per_user', 3));
            if ($active >= $maxQueued) {
                $ctx->toast();
                $ctx->reply(sprintf(Texts::TOO_MANY_JOBS, Texts::fa($active)));
                return null;
            }
            if ($units > 0) {
                $limit = $ctx->user['daily_limit'] !== null ? (int) $ctx->user['daily_limit'] : $s->int('daily_limit', 300);
                $used = $this->app->misc()->todayUnits($ctx->userId());
                if ($limit > 0 && $used + $units > $limit) {
                    $ctx->toast();
                    $ctx->reply(sprintf(Texts::QUOTA, Texts::fa($used), Texts::fa($limit)));
                    return null;
                }
            }
        }
        $id = $queue->push($type, $payload, $ctx->userId(), $projectId, $priority, $ctx->chatId);
        return $id;
    }

    /**
     * Usage units of a generate payload: one per template item, the effect
     * cost (animation_presets.cost_units) per design item.
     */
    protected function jobUnits(array $payload): int
    {
        $items = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));
        if (($payload['kind'] ?? 'design') === 'template') {
            return count($items);
        }
        $units = 0;
        foreach ($items as $item) {
            $effects = array_values(array_filter(array_map(
                static fn ($e) => is_array($e) ? (string) ($e['type'] ?? '') : '',
                (array) ($item['config']['animation']['effects'] ?? [])
            )));
            try {
                $units += max(1, $this->app->presets()->costFor($effects));
            } catch (\Throwable) {
                $units++;
            }
        }
        return $units;
    }

    /** Persian name of a format. */
    protected static function formatName(string $format): string
    {
        return match ($format) {
            'tgs' => 'TGS (برداری)',
            'png' => 'PNG (ثابت)',
            default => 'WEBM (ویدیویی)',
        };
    }

    /** Colour presets used by the pickers: hex => Persian name. */
    protected const COLORS = [
        'FFFFFF' => '⚪️ سفید', '111111' => '⚫️ مشکی', 'FACC15' => '🟡 زرد', 'F59E0B' => '🟠 کهربایی',
        'EF4444' => '🔴 قرمز', 'EC4899' => '🩷 صورتی', 'A855F7' => '🟣 بنفش', '3B82F6' => '🔵 آبی',
        '06B6D4' => '🩵 فیروزه‌ای', '22C55E' => '🟢 سبز', '94A3B8' => '🩶 نقره‌ای', 'D4A017' => '🏅 طلایی',
    ];

    /** Validates "#RRGGBB" / "RRGGBB" input. */
    protected static function parseHex(string $s): ?string
    {
        $s = strtoupper(trim(str_replace(['#', ' '], '', $s)));
        return preg_match('/^[0-9A-F]{6}$/', $s) ? '#' . $s : null;
    }
}
