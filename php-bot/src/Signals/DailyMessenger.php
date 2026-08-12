<?php

declare(strict_types=1);

namespace App\Signals;

use App\Services\LogService;
use App\Services\SettingsService;

/**
 * Sends the daily good-morning/good-night channel messages. Checked once
 * per minute by cron/monitor.php (there's no long-lived process to run a
 * precise scheduler) - fires the first time the local clock reaches the
 * configured time on a day it hasn't already fired, so a slow/late cron
 * tick still catches it instead of silently skipping the day.
 */
final class DailyMessenger
{
    public function __construct(private readonly Publisher $publisher)
    {
    }

    /** @param array<string, mixed> $params */
    public function checkOnce(array $params): void
    {
        try {
            $tz = new \DateTimeZone((string) ($params['notification_timezone'] ?: 'Asia/Tehran'));
        } catch (\Throwable) {
            $tz = new \DateTimeZone('UTC');
        }
        $now = new \DateTime('now', $tz);
        $today = $now->format('Y-m-d');
        $nowHm = $now->format('H:i');

        $this->maybeSend($params, $today, $nowHm, 'morning');
        $this->maybeSend($params, $today, $nowHm, 'night');
    }

    /** @param array<string, mixed> $params */
    private function maybeSend(array $params, string $today, string $nowHm, string $slot): void
    {
        $enabledKey = "{$slot}_message_enabled";
        $timeKey = "{$slot}_message_time";
        $textKey = "{$slot}_message_text";
        $lastSentKey = "last_{$slot}_sent_date";

        if (empty($params[$enabledKey])) {
            return;
        }
        $targetTime = (string) $params[$timeKey];
        if ($nowHm < $targetTime) {
            return;
        }
        if ((string) $params[$lastSentKey] === $today) {
            return;
        }

        $text = (string) $params[$textKey];
        if ($text === '') {
            return;
        }

        $this->publisher->publishUpdate($text);
        SettingsService::set($lastSentKey, $today);
        LogService::log('INFO', 'daily_messages', "Sent {$slot} message for {$today}");
    }
}
