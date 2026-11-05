<?php

final class Tick
{
    public static function run(): bool
    {
        $lock = @fopen(sys_get_temp_dir() . '/mybot_cron_' . md5(Config::$root) . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return false;
        }
        $t0 = microtime(true);
        $left = fn() => microtime(true) - $t0 < (PHP_SAPI === 'cli' ? 90 : 25);
        try {
            DB::kvSet('last_tick', time());
            if ((int)DB::kvGet('commands_v', 0) < Start::COMMANDS_V) {
                Start::registerCommands();
            }
            Bot::processDeletes();
            try {
                Games::tick();
            } catch (Throwable $e) {
                error_log('tick games: ' . $e->getMessage());
            }
            try {
                Market::refresh([], true, true);
                if (time() - (int)DB::kvGet('prerender_at', 0) >= 120) {
                    Convert::prerender();
                    DB::kvSet('prerender_at', time());
                }
            } catch (Throwable $e) {
                error_log('tick market: ' . $e->getMessage());
            }
            try {
                FileCache::$inline = $left();
                if (!FileCache::has('gift_dataset', 540)) {
                    FileCache::forget();
                    Gifts::dataset();
                    Gifts::floorsDaysAgo();
                }
                if ($left()) {
                    $names = array_values(array_unique(array_filter(array_map(fn($it) => $it['full_name'] ?? null, Gifts::dataset()['index'] ?? []))));
                    Gifts::prefetch($names, 16, false);
                }
            } catch (Throwable $e) {
                error_log('tick gifts: ' . $e->getMessage());
            } finally {
                FileCache::$inline = false;
            }
            if ($left() && (time() - (int)DB::kvGet('emoji_checked_at', 0) > 86400 || !is_array(DB::kvGet('emoji_auto')))) {
                UI::validateEmoji();
            }
            if ($left() && SpotDir::due() && !Cache::throttled('spot_try', 1800)) {
                try {
                    SpotDir::refresh();
                } catch (Throwable $e) {
                    error_log('tick spot: ' . $e->getMessage());
                }
            }
            if (time() - (int)DB::kvGet('last_cleanup', 0) > 6 * 3600) {
                DB::cleanup();
                DB::kvSet('last_cleanup', time());
            }
            Bot::processDeletes();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return true;
    }

    public static function canDefer(): bool
    {
        return PHP_SAPI === 'cli' || function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    public static function due(int $every = 60): bool
    {
        return time() - (int)DB::kvGet('last_tick', 0) >= $every && !Cache::throttled('tick_try', $every);
    }
}
