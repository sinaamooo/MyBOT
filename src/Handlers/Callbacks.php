<?php

declare(strict_types=1);

namespace MyBot\Handlers;

use MyBot\App;
use MyBot\Service\Campaigns;
use MyBot\Support\ApiException;
use MyBot\Support\Keyboards;
use MyBot\Support\Text;

/** Handles every inline-button tap in the admin panel. */
final class Callbacks
{
    private readonly Views $views;

    public function __construct(
        private readonly App $app,
        private readonly Flows $flows,
    ) {
        $this->views = $flows->views();
    }

    /** @param array<string, mixed> $query */
    public function handle(array $query): void
    {
        $from    = $query['from'] ?? null;
        $message = $query['message'] ?? null;
        $data    = \is_string($query['data'] ?? null) ? $query['data'] : '';
        $queryId = (string) ($query['id'] ?? '');

        if (!\is_array($from) || !\is_array($message)) {
            return;
        }

        $userId    = (int) $from['id'];
        $chatId    = (int) (($message['chat'] ?? [])['id'] ?? 0);
        $messageId = (int) ($message['message_id'] ?? 0);

        $this->app->users->touch($from);

        if (!$this->app->isAdmin($userId)) {
            $this->answer($queryId, '⛔️ این پنل فقط برای ادمین‌هاست.', true);

            return;
        }

        $parts   = explode(':', $data);
        $section = $parts[0] ?? '';
        $action  = $parts[1] ?? '';
        $arg     = $parts[2] ?? '';
        $extra   = $parts[3] ?? '';

        $this->answer($queryId);

        match ($section) {
            'menu' => $this->views->main($chatId, $messageId),
            'bn'   => $this->banners($chatId, $messageId, $userId, $action, $arg, $extra, $queryId),
            'tg'   => $this->targets($chatId, $messageId, $userId, $action, $arg),
            'cp'   => $this->campaigns($chatId, $messageId, $userId, $parts),
            'st'   => $this->stats($chatId, $messageId, $action),
            'sy'   => $this->system($chatId, $messageId),
            'fl'   => $this->cancelFlow($chatId, $messageId, $userId),
            default => null,
        };
    }

    // ------------------------------------------------------------------ banners

    private function banners(
        int $chatId,
        int $messageId,
        int $userId,
        string $action,
        string $arg,
        string $extra,
        string $queryId,
    ): void {
        $id = (int) $arg;

        match ($action) {
            'list'    => $this->views->bannerList($chatId, max(0, $id), $messageId),
            'view'    => $this->views->bannerView($chatId, $id, $messageId),
            'new'     => $this->flows->startBannerCreate($userId),
            'edit'    => $this->flows->startBannerEdit($userId, $id),
            'rename'  => $this->flows->startBannerRename($userId, $id),
            'buttons' => $this->flows->startBannerButtons($userId, $id),
            'quote'   => $this->flows->startBannerQuote($userId, $id),
            'preview' => $this->preview($chatId, $id),
            'send'    => $this->chooseAudience($chatId, $messageId, $id),
            'flag'    => $this->toggleFlag($chatId, $messageId, $id, $extra),
            'del'     => $this->confirmDeleteBanner($chatId, $messageId, $id),
            'delok'   => $this->deleteBanner($chatId, $messageId, $id),
            default   => null,
        };
    }

    private function preview(int $chatId, int $bannerId): void
    {
        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            return;
        }

        $this->app->sender->notice($chatId, '👁 <b>پیش‌نمایش:</b>');

        try {
            $this->app->sender->send($banner, $chatId);
        } catch (ApiException $e) {
            $hint = '';

            if (str_contains(mb_strtolower($e->getMessage()), 'custom emoji')) {
                $hint = "\n\n⚠️ ایموجی پریمیوم را فقط رباتی می‌تواند بفرستد که "
                    . 'یک یوزرنیم از Fragment خریده باشد.';
            }

            $this->app->sender->notice(
                $chatId,
                '❌ ارسال پیش‌نمایش ناموفق بود:' . "\n<code>"
                . Text::esc($e->getMessage()) . '</code>' . $hint,
            );
        }
    }

    private function chooseAudience(int $chatId, int $messageId, int $bannerId): void
    {
        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            return;
        }

        $text = '🚀 <b>ارسال «' . Text::esc((string) $banner['name']) . '»</b>' . "\n\n"
            . 'مخاطب را انتخاب کنید:' . "\n\n"
            . '👥 گروه‌های فعال: <b>' . Text::num($this->app->targets->countActive()) . '</b>' . "\n"
            . '👤 کاربران عضو ربات: <b>' . Text::num($this->app->users->countSubscribers()) . '</b>';

        $this->views->render(
            $chatId,
            $messageId,
            $text,
            Keyboards::audience($bannerId, $this->app->targets->allActive()),
        );
    }

    private function toggleFlag(int $chatId, int $messageId, int $bannerId, string $column): void
    {
        $this->app->banners->toggleFlag($bannerId, $column);
        $this->views->bannerView($chatId, $bannerId, $messageId);
    }

    private function confirmDeleteBanner(int $chatId, int $messageId, int $bannerId): void
    {
        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            return;
        }

        $this->views->render(
            $chatId,
            $messageId,
            '🗑 بنر «' . Text::esc((string) $banner['name']) . '» حذف شود؟' . "\n\n"
            . '<i>کمپین‌های مربوط به آن هم حذف می‌شوند.</i>',
            Keyboards::confirmDelete('bn', $bannerId),
        );
    }

    private function deleteBanner(int $chatId, int $messageId, int $bannerId): void
    {
        $this->app->banners->delete($bannerId);
        $this->views->bannerList($chatId, 0, $messageId);
    }

    // ------------------------------------------------------------------ targets

    private function targets(int $chatId, int $messageId, int $userId, string $action, string $arg): void
    {
        $id = (int) $arg;

        match ($action) {
            'list'   => $this->views->targetList($chatId, max(0, $id), $messageId),
            'view'   => $this->views->targetView($chatId, $id, $messageId),
            'add'    => $this->flows->startTargetAdd($userId),
            'toggle' => $this->toggleTarget($chatId, $messageId, $id),
            'sync'   => $this->syncTarget($chatId, $messageId, $id),
            'top'    => $this->targetTopMembers($chatId, $messageId, $id),
            'del'    => $this->confirmDeleteTarget($chatId, $messageId, $id),
            'delok'  => $this->deleteTarget($chatId, $messageId, $id),
            default  => null,
        };
    }

    private function toggleTarget(int $chatId, int $messageId, int $targetId): void
    {
        $this->app->targets->toggle($targetId);
        $this->views->targetView($chatId, $targetId, $messageId);
    }

    private function syncTarget(int $chatId, int $messageId, int $targetId): void
    {
        $target = $this->app->targets->find($targetId);

        if ($target === null) {
            return;
        }

        $telegramChatId = (int) $target['chat_id'];

        $chat = $this->app->api->tryCall('getChat', ['chat_id' => $telegramChatId]);
        if (\is_array($chat)) {
            $this->app->targets->upsert($chat);
        }

        $count = $this->app->api->tryCall('getChatMemberCount', ['chat_id' => $telegramChatId]);
        if (is_numeric($count)) {
            $this->app->targets->setMembers($telegramChatId, (int) $count);
        }

        $this->views->targetView($chatId, $targetId, $messageId);
    }

    private function targetTopMembers(int $chatId, int $messageId, int $targetId): void
    {
        $target = $this->app->targets->find($targetId);

        if ($target === null) {
            return;
        }

        $members = $this->app->activity->topMembers((int) $target['chat_id'], 7, 10);

        $lines = [
            '🔥 <b>فعال‌ترین اعضای ۷ روز اخیر</b>',
            '👥 ' . Text::esc($this->app->targets->title($target)),
            '',
        ];

        if ($members === []) {
            $lines[] = 'هنوز پیامی در این گروه شمرده نشده است.';
        } else {
            $top = (int) $members[0]['total'];

            foreach ($members as $rank => $member) {
                $name = \is_string($member['name'] ?? null) ? $member['name'] : 'ناشناس';

                $lines[] = sprintf(
                    '%s %s — <b>%s</b> %s',
                    Text::num($rank + 1) . '.',
                    Text::esc(Text::shorten($name, 22)),
                    Text::num((int) $member['total']),
                    Text::bar((int) $member['total'], max(1, $top), 8),
                );
            }
        }

        $this->views->render(
            $chatId,
            $messageId,
            implode("\n", $lines),
            Keyboards::back('tg:view:' . $targetId),
        );
    }

    private function confirmDeleteTarget(int $chatId, int $messageId, int $targetId): void
    {
        $target = $this->app->targets->find($targetId);

        if ($target === null) {
            return;
        }

        $this->views->render(
            $chatId,
            $messageId,
            '🗑 گروه «' . Text::esc($this->app->targets->title($target)) . '» از فهرست حذف شود؟'
            . "\n\n" . '<i>ربات از خود گروه خارج نمی‌شود؛ فقط دیگر برایش ارسال نمی‌کنیم.</i>',
            Keyboards::confirmDelete('tg', $targetId),
        );
    }

    private function deleteTarget(int $chatId, int $messageId, int $targetId): void
    {
        $this->app->targets->delete($targetId);
        $this->views->targetList($chatId, 0, $messageId);
    }

    // ---------------------------------------------------------------- campaigns

    /**
     * Campaign callbacks carry five segments: cp:go:{bannerId}:{audience}:{targetId}
     *
     * @param list<string> $parts
     */
    private function campaigns(int $chatId, int $messageId, int $userId, array $parts): void
    {
        $action = $parts[1] ?? '';
        $arg    = (int) ($parts[2] ?? 0);

        match ($action) {
            'list'   => $this->views->campaignList($chatId, $messageId),
            'view'   => $this->views->campaignView($chatId, $arg, $messageId),
            'go'     => $this->launch(
                $chatId,
                $messageId,
                $userId,
                $arg,
                (string) ($parts[3] ?? ''),
                (int) ($parts[4] ?? 0),
            ),
            'cancel' => $this->cancelCampaign($chatId, $messageId, $arg),
            default  => null,
        };
    }

    private function cancelCampaign(int $chatId, int $messageId, int $campaignId): void
    {
        $dropped = $this->app->campaigns->cancel($campaignId);

        $this->app->sender->notice(
            $chatId,
            '🛑 کمپین لغو شد. ' . Text::num($dropped) . ' ارسال باقی‌مانده حذف شد.',
        );

        $this->views->campaignView($chatId, $campaignId, $messageId);
    }

    private function launch(
        int $chatId,
        int $messageId,
        int $userId,
        int $bannerId,
        string $audience,
        int $targetId,
    ): void {
        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            return;
        }

        $targetChatId = null;

        if ($audience === Campaigns::AUDIENCE_TARGET) {
            $target = $this->app->targets->find($targetId);

            if ($target === null) {
                $this->views->render($chatId, $messageId, '❌ گروه پیدا نشد.', Keyboards::back('bn:list:0'));

                return;
            }

            $targetChatId = (int) $target['chat_id'];
        }

        if (!\in_array($audience, [
            Campaigns::AUDIENCE_GROUPS,
            Campaigns::AUDIENCE_TARGET,
            Campaigns::AUDIENCE_SUBSCRIBERS,
        ], true)) {
            return;
        }

        $campaignId = $this->app->campaigns->create($bannerId, $audience, $targetChatId, $userId);

        $this->app->logger->info('campaign queued', [
            'campaign' => $campaignId,
            'banner'   => $bannerId,
            'audience' => $audience,
        ]);

        $this->views->campaignView($chatId, $campaignId, $messageId);

        $this->app->sender->notice(
            $chatId,
            '✅ کمپین <b>#' . Text::num($campaignId) . '</b> در صف قرار گرفت.' . "\n"
            . '<i>ارسال توسط worker انجام می‌شود — مطمئن شوید <code>bin/worker.php</code> در حال اجراست.</i>',
        );
    }

    // -------------------------------------------------------------------- stats

    private function stats(int $chatId, int $messageId, string $action): void
    {
        match ($action) {
            'overview'  => $this->views->render($chatId, $messageId, $this->app->stats->overview(), Keyboards::stats()),
            'campaigns' => $this->views->render($chatId, $messageId, $this->app->stats->campaignReport(), Keyboards::statsBack()),
            'targets'   => $this->views->render($chatId, $messageId, $this->app->stats->targetReport(), Keyboards::statsBack()),
            'activity'  => $this->views->render($chatId, $messageId, $this->app->stats->activityReport(), Keyboards::statsBack()),
            default     => null,
        };
    }

    private function system(int $chatId, int $messageId): void
    {
        $me       = $this->app->api->tryCall('getMe');
        $username = \is_array($me) && \is_string($me['username'] ?? null) ? $me['username'] : '—';

        $pending = $this->app->db->count(
            'SELECT COUNT(*) FROM queue WHERE status = :status',
            ['status' => 'pending'],
        );

        $lines = [
            '⚙️ <b>وضعیت سیستم</b>',
            '',
            '🤖 ربات: @' . Text::esc($username),
            '🐘 PHP: <code>' . \PHP_VERSION . '</code>',
            '🗃 دیتابیس: <code>' . Text::esc(basename(
                $this->app->config->string('database.path', 'storage/bot.sqlite'),
            )) . '</code>',
            '',
            '⏳ در صف ارسال: <b>' . Text::num($pending) . '</b>',
            '🚀 کمپین در حال اجرا: <b>'
                . Text::num($this->app->campaigns->countByStatus(Campaigns::STATUS_RUNNING)) . '</b>',
            '',
            '<b>محدودیت‌های ارسال</b>',
            '• سراسری: ' . Text::num((int) $this->app->config->float('limits.global_per_second', 25.0)) . ' پیام بر ثانیه',
            '• هر گروه: هر ' . Text::num((int) $this->app->config->float('limits.group_interval', 3.0)) . ' ثانیه یک پیام',
            '• تلاش مجدد: ' . Text::num($this->app->config->int('limits.max_attempts', 3)) . ' بار',
        ];

        $this->views->render($chatId, $messageId, implode("\n", $lines), Keyboards::back());
    }

    private function cancelFlow(int $chatId, int $messageId, int $userId): void
    {
        $this->flows->cancel($userId);
        $this->views->main($chatId, $messageId);
    }

    private function answer(string $queryId, string $text = '', bool $alert = false): void
    {
        if ($queryId === '') {
            return;
        }

        $params = ['callback_query_id' => $queryId];

        if ($text !== '') {
            $params['text']       = $text;
            $params['show_alert'] = $alert;
        }

        $this->app->api->tryCall('answerCallbackQuery', $params);
    }
}
