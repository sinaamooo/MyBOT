<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;
use MyBot\Support\Text;

/** Builds the HTML for every statistics screen in the panel. */
final class Stats
{
    public function __construct(
        private readonly Database $db,
        private readonly Users $users,
        private readonly Targets $targets,
        private readonly Banners $banners,
        private readonly Campaigns $campaigns,
        private readonly Activity $activity,
    ) {
    }

    public function overview(): string
    {
        $day  = time() - 86400;
        $week = time() - 7 * 86400;

        $sent24  = $this->deliveredSince($day);
        $sent7d  = $this->deliveredSince($week);
        $failed7 = $this->failedSince($week);
        $rate    = $sent7d + $failed7;

        $lines = [
            '📊 <b>آمار کلی</b>',
            '',
            '👥 <b>مخاطبان</b>',
            '• عضو فعال ربات: <b>' . Text::num($this->users->countSubscribers()) . '</b>',
            '• کل کاربران ثبت‌شده: <b>' . Text::num($this->users->countAll()) . '</b>',
            '• لغو عضویت: <b>' . Text::num($this->users->countUnsubscribed()) . '</b>',
            '• بلاک‌کرده: <b>' . Text::num($this->users->countBlocked()) . '</b>',
            '• کاربر جدید (۲۴ ساعت): <b>' . Text::num($this->users->countNewSince($day)) . '</b>',
            '',
            '👥 <b>گروه‌ها و کانال‌ها</b>',
            '• فعال: <b>' . Text::num($this->targets->countActive()) . '</b>'
                . ' از ' . Text::num($this->targets->countAll()),
            '• مجموع اعضا: <b>' . Text::num($this->targets->totalMembers()) . '</b>',
            '',
            '📣 <b>ارسال</b>',
            '• بنر ذخیره‌شده: <b>' . Text::num($this->banners->countAll()) . '</b>',
            '• ارسال موفق (۲۴ ساعت): <b>' . Text::num($sent24) . '</b>',
            '• ارسال موفق (۷ روز): <b>' . Text::num($sent7d) . '</b>',
            '• ناموفق (۷ روز): <b>' . Text::num($failed7) . '</b>',
            '• نرخ موفقیت: <b>' . Text::percent($sent7d, $rate) . '</b> '
                . Text::bar($sent7d, max(1, $rate)),
            '',
            '⚙️ <b>صف</b>',
            '• در انتظار: <b>' . Text::num($this->queueCount('pending')) . '</b>',
            '• کمپین در حال اجرا: <b>' . Text::num($this->campaigns->countByStatus(Campaigns::STATUS_RUNNING)) . '</b>',
            '• کمپین زمان‌بندی‌شده: <b>' . Text::num($this->campaigns->countByStatus(Campaigns::STATUS_QUEUED)) . '</b>',
            '',
            '💬 <b>فعالیت گروه‌ها (۷ روز)</b>',
            '• پیام شمرده‌شده: <b>' . Text::num($this->activity->totalMessages(7)) . '</b>',
            '• عضو فعال: <b>' . Text::num($this->activity->activeMemberCount(7)) . '</b>',
        ];

        return implode("\n", $lines);
    }

    public function campaignReport(int $limit = 8): string
    {
        $rows = $this->campaigns->recent($limit);

        if ($rows === []) {
            return '📣 <b>کمپین‌ها</b>' . "\n\n" . 'هنوز هیچ کمپینی ساخته نشده است.';
        }

        $lines = ['📣 <b>آخرین کمپین‌ها</b>', ''];

        foreach ($rows as $row) {
            $total  = (int) $row['total'];
            $sent   = (int) $row['sent'];
            $failed = (int) $row['failed'];

            $lines[] = sprintf(
                '<b>#%s</b> — %s',
                Text::num((int) $row['id']),
                Text::esc(Text::shorten(\is_string($row['banner_name'] ?? null) ? $row['banner_name'] : 'بنر حذف‌شده', 28)),
            );
            $lines[] = '   ' . $this->campaigns->statusLabel((string) $row['status'])
                . ' · ' . Text::esc($this->campaigns->audienceLabel($row));
            $lines[] = '   ' . Text::bar($sent, max(1, $total))
                . ' ' . Text::num($sent) . '/' . Text::num($total)
                . ($failed > 0 ? ' · ناموفق ' . Text::num($failed) : '');
            $lines[] = '   🕒 ' . Text::esc(Text::datetime((int) $row['created_at']));
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    public function targetReport(int $limit = 10): string
    {
        $rows = $this->targets->allWithActivity($limit);

        if ($rows === []) {
            return '👥 <b>گروه‌ها</b>' . "\n\n" . 'هنوز گروهی اضافه نشده است.'
                . "\n" . 'ربات را در گروه اضافه کنید تا خودکار اینجا ثبت شود.';
        }

        $lines = ['👥 <b>گروه‌ها بر اساس فعالیت</b>', ''];

        foreach ($rows as $index => $row) {
            $delivered = $this->deliveredToChat((int) $row['chat_id']);

            $lines[] = sprintf(
                '%s. %s %s',
                Text::num($index + 1),
                ((int) $row['active']) === 1 && ((int) $row['can_post']) === 1 ? '🟢' : '🔴',
                Text::esc(Text::shorten($this->targets->title($row), 30)),
            );
            $lines[] = '   💬 ' . Text::num((int) $row['msg_count']) . ' پیام'
                . ' · 📤 ' . Text::num($delivered) . ' ارسال'
                . ' · 👤 ' . Text::num((int) $row['members']) . ' عضو';
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    public function activityReport(int $days = 7): string
    {
        $groups = $this->activity->topGroups($days, 5);
        $daily  = $this->activity->dailyTotals($days);

        $lines = ['🔥 <b>فعال‌ترین اعضا و گروه‌ها (' . Text::num($days) . ' روز)</b>', ''];

        if ($groups === []) {
            $lines[] = 'هنوز فعالیتی ثبت نشده است.';
            $lines[] = 'ربات باید در گروه عضو باشد و پیام‌ها را ببیند.';

            return implode("\n", $lines);
        }

        $peak = max(array_map(static fn (array $d): int => $d['total'], $daily ?: [['total' => 1]]));

        $lines[] = '📈 <b>روند روزانه</b>';
        foreach ($daily as $point) {
            $lines[] = '<code>' . Text::esc($point['day']) . '</code> '
                . Text::bar($point['total'], max(1, $peak))
                . ' ' . Text::num($point['total']);
        }
        $lines[] = '';

        foreach ($groups as $group) {
            $chatId = (int) $group['chat_id'];
            $title  = \is_string($group['title'] ?? null) && $group['title'] !== ''
                ? $group['title']
                : ('چت ' . $chatId);

            $lines[] = '👥 <b>' . Text::esc(Text::shorten($title, 30)) . '</b>'
                . ' — ' . Text::num((int) $group['total']) . ' پیام از '
                . Text::num((int) $group['members']) . ' نفر';

            foreach ($this->activity->topMembers($chatId, $days, 5) as $rank => $member) {
                $lines[] = '   ' . self::MEDALS[$rank] . ' '
                    . Text::esc(Text::shorten(\is_string($member['name'] ?? null) ? $member['name'] : 'ناشناس', 24))
                    . ' — ' . Text::num((int) $member['total']);
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    private const MEDALS = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];

    private function deliveredSince(int $timestamp): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM queue WHERE status = :status AND sent_at >= :t',
            ['status' => 'sent', 't' => $timestamp],
        );
    }

    private function failedSince(int $timestamp): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM queue q
             JOIN campaigns c ON c.id = q.campaign_id
             WHERE q.status = :status AND c.created_at >= :t',
            ['status' => 'failed', 't' => $timestamp],
        );
    }

    private function deliveredToChat(int $chatId): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM queue WHERE chat_id = :chat_id AND status = :status',
            ['chat_id' => $chatId, 'status' => 'sent'],
        );
    }

    private function queueCount(string $status): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM queue WHERE status = :status',
            ['status' => $status],
        );
    }
}
