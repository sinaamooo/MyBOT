<?php

declare(strict_types=1);

namespace MyBot\Handlers;

use MyBot\App;
use MyBot\Support\Keyboards;
use MyBot\Support\Text;

final class Commands
{
    public function __construct(
        private readonly App $app,
        private readonly Flows $flows,
    ) {
    }

    /** @param array<string, mixed> $message */
    public function handle(array $message, string $text, int $userId): void
    {
        [$command, $argument] = $this->split($text);

        match ($command) {
            'start'  => $this->start($userId),
            'help'   => $this->help($userId),
            'stop'   => $this->stop($userId),
            'panel'  => $this->panel($userId),
            'stats'  => $this->stats($userId),
            'id'     => $this->id($userId, $userId),
            'cancel' => $this->cancel($userId),
            'send'   => $this->send($userId, $argument),
            default  => $this->unknown($userId),
        };
    }

    /** @param array<string, mixed> $message */
    public function handleInGroup(array $message, string $text, int $userId, int $chatId): void
    {
        [$command] = $this->split($text);

        if (!\in_array($command, ['id', 'addgroup', 'settopic', 'activity'], true)) {
            return;
        }

        if ($command === 'id') {
            $this->app->sender->notice(
                $chatId,
                '🆔 آیدی این چت: <code>' . $chatId . '</code>',
                null,
                (int) ($message['message_id'] ?? 0) ?: null,
            );

            return;
        }

        if (!$this->app->isAdmin($userId)) {
            return;
        }

        $replyTo = (int) ($message['message_id'] ?? 0) ?: null;

        match ($command) {
            'addgroup' => $this->addGroup($message, $chatId, $userId, $replyTo),
            'settopic' => $this->setTopic($message, $chatId, $replyTo),
            'activity' => $this->groupActivity($chatId, $replyTo),
            default    => null,
        };
    }

    private function start(int $userId): void
    {
        $this->app->users->subscribe($userId);

        $text = "👋 <b>خوش آمدید!</b>\n\n"
            . "از این پس اطلاعیه‌ها و بنرهای ما را دریافت می‌کنید.\n"
            . "هر وقت خواستید با دستور /stop اشتراک را لغو کنید.\n\n"
            . '📋 راهنما: /help';

        $keyboard = $this->app->isAdmin($userId) ? Keyboards::main() : null;

        if ($keyboard !== null) {
            $text .= "\n\n" . '🛠 شما ادمین هستید — پنل مدیریت پایین است.';
        }

        $this->app->sender->notice($userId, $text, $keyboard);
    }

    private function help(int $userId): void
    {
        $lines = [
            '📋 <b>راهنما</b>',
            '',
            '/start — عضویت و دریافت بنرها',
            '/stop — لغو عضویت',
            '/id — نمایش آیدی عددی شما',
            '/help — همین راهنما',
        ];

        if ($this->app->isAdmin($userId)) {
            $lines[] = '';
            $lines[] = '🛠 <b>دستورهای ادمین</b>';
            $lines[] = '/panel — پنل مدیریت';
            $lines[] = '/stats — آمار کامل';
            $lines[] = '/send &lt;شناسه بنر&gt; — ارسال سریع یک بنر';
            $lines[] = '/cancel — لغو عملیات نیمه‌تمام';
            $lines[] = '';
            $lines[] = '<b>در گروه</b>';
            $lines[] = '/addgroup — ثبت گروه فعلی برای ارسال';
            $lines[] = '/settopic — ارسال در همین تاپیک انجام شود';
            $lines[] = '/activity — فعال‌ترین اعضای همین گروه';
        }

        $this->app->sender->notice($userId, implode("\n", $lines));
    }

    private function stop(int $userId): void
    {
        $this->app->users->unsubscribe($userId);

        $this->app->sender->notice(
            $userId,
            "🔕 اشتراک شما لغو شد.\n" . 'هر زمان خواستید با /start دوباره عضو شوید.',
        );
    }

    private function panel(int $userId): void
    {
        if (!$this->guard($userId)) {
            return;
        }

        $this->app->sender->notice($userId, $this->panelHeader(), Keyboards::main());
    }

    private function panelHeader(): string
    {
        return "🛠 <b>پنل مدیریت</b>\n\n"
            . '🖼 بنر: <b>' . Text::num($this->app->banners->countAll()) . '</b>'
            . ' · 👥 گروه فعال: <b>' . Text::num($this->app->targets->countActive()) . '</b>'
            . ' · 👤 عضو: <b>' . Text::num($this->app->users->countSubscribers()) . '</b>';
    }

    private function stats(int $userId): void
    {
        if (!$this->guard($userId)) {
            return;
        }

        $this->app->sender->notice($userId, $this->app->stats->overview(), Keyboards::stats());
    }

    private function id(int $userId, int $chatId): void
    {
        $this->app->sender->notice($chatId, '🆔 آیدی عددی شما: <code>' . $userId . '</code>');
    }

    private function cancel(int $userId): void
    {
        $this->app->states->clear($userId);

        $this->app->sender->notice(
            $userId,
            '✅ عملیات لغو شد.',
            $this->app->isAdmin($userId) ? Keyboards::main() : null,
        );
    }

    private function send(int $userId, string $argument): void
    {
        if (!$this->guard($userId)) {
            return;
        }

        $bannerId = (int) trim($argument);
        $banner   = $bannerId > 0 ? $this->app->banners->find($bannerId) : null;

        if ($banner === null) {
            $this->app->sender->notice(
                $userId,
                '❌ بنری با این شناسه پیدا نشد.' . "\n" . 'فهرست بنرها: /panel',
            );

            return;
        }

        $this->app->sender->notice(
            $userId,
            '🚀 <b>ارسال بنر «' . Text::esc((string) $banner['name']) . '»</b>' . "\n\n"
            . 'مخاطب را انتخاب کنید:',
            Keyboards::audience($bannerId, $this->app->targets->allActive()),
        );
    }

    /** @param array<string, mixed> $message */
    private function addGroup(array $message, int $chatId, int $userId, ?int $replyTo): void
    {
        $chat = $message['chat'] ?? [];
        $id   = $this->app->targets->upsert(\is_array($chat) ? $chat : [], $userId);

        if ($id === 0) {
            return;
        }

        $this->app->targets->setActive($id, true);

        $count = $this->app->api->tryCall('getChatMemberCount', ['chat_id' => $chatId]);
        if (is_numeric($count)) {
            $this->app->targets->setMembers($chatId, (int) $count);
        }

        $this->app->sender->notice(
            $chatId,
            '✅ این گروه برای ارسال بنر ثبت و فعال شد.',
            null,
            $replyTo,
        );
    }

    /** @param array<string, mixed> $message */
    private function setTopic(array $message, int $chatId, ?int $replyTo): void
    {
        $target = $this->app->targets->findByChatId($chatId);

        if ($target === null) {
            $this->app->sender->notice(
                $chatId,
                '❌ ابتدا با /addgroup این گروه را ثبت کنید.',
                null,
                $replyTo,
            );

            return;
        }

        $threadId = is_numeric($message['message_thread_id'] ?? null)
            ? (int) $message['message_thread_id']
            : null;

        $this->app->targets->setThread((int) $target['id'], $threadId);

        $this->app->sender->notice(
            $chatId,
            $threadId === null
                ? '✅ ارسال‌ها در چت اصلی گروه انجام می‌شود.'
                : '✅ ارسال‌ها از این پس در همین تاپیک انجام می‌شود.',
            null,
            $replyTo,
        );
    }

    private function groupActivity(int $chatId, ?int $replyTo): void
    {
        $members = $this->app->activity->topMembers($chatId, 7, 10);

        if ($members === []) {
            $this->app->sender->notice($chatId, 'هنوز فعالیتی ثبت نشده است.', null, $replyTo);

            return;
        }

        $lines = ['🔥 <b>فعال‌ترین اعضای ۷ روز اخیر</b>', ''];
        $top   = (int) $members[0]['total'];

        foreach ($members as $rank => $member) {
            $name = \is_string($member['name'] ?? null) ? $member['name'] : 'ناشناس';

            $lines[] = sprintf(
                '%s %s — %s %s',
                Text::num($rank + 1) . '.',
                Text::esc(Text::shorten($name, 24)),
                Text::num((int) $member['total']),
                Text::bar((int) $member['total'], max(1, $top), 8),
            );
        }

        $this->app->sender->notice($chatId, implode("\n", $lines), null, $replyTo);
    }

    public function fallback(int $userId): void
    {
        $this->app->sender->notice(
            $userId,
            'دستور را متوجه نشدم. راهنما: /help',
            $this->app->isAdmin($userId) ? Keyboards::main() : null,
        );
    }

    private function unknown(int $userId): void
    {
        $this->app->sender->notice($userId, '❓ چنین دستوری وجود ندارد. راهنما: /help');
    }

    private function guard(int $userId): bool
    {
        if ($this->app->isAdmin($userId)) {
            return true;
        }

        $this->app->sender->notice($userId, '⛔️ این بخش فقط برای ادمین‌هاست.');

        return false;
    }

    /** @return array{0: string, 1: string} */
    private function split(string $text): array
    {
        $parts   = preg_split('/\s+/u', trim($text), 2) ?: [''];
        $command = ltrim($parts[0], '/');

        // Strip the @BotUsername suffix Telegram adds in groups.
        if (str_contains($command, '@')) {
            $command = strstr($command, '@', true) ?: $command;
        }

        return [mb_strtolower($command, 'UTF-8'), $parts[1] ?? ''];
    }
}
