<?php

/**
 * Background automation. Runs from a real cron job (cron.php / /cron/{key})
 * and — as a fallback — piggy-backs on normal page views every few minutes.
 */
final class Automation
{
    public static function maybeTick(): void
    {
        if (setting('auto_cron', '1') !== '1') {
            return;
        }
        $interval = 60 * max(1, (int)setting('auto_cron_minutes', 5));
        $last = (int)setting('cron_last_run', 0);
        if (time() - $last < $interval) {
            return;
        }
        // Claim the run atomically so parallel requests don't run it twice.
        $claimed = DB::query(
            "UPDATE settings SET `value` = ? WHERE `key` = 'cron_last_run' AND (`value` IS NULL OR `value` = '' OR CAST(`value` AS UNSIGNED) <= ?)",
            [(string)time(), time() - $interval]
        )->rowCount();
        if (!$claimed) {
            if ($last === 0) {
                setting_set('cron_last_run', (string)time());
            }
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        @set_time_limit(120);
        self::run(false);
    }

    /** @return string[] log lines */
    public static function run(bool $stamp = true): array
    {
        $log = [];
        $tasks = [
            'dispatch' => fn() => self::dispatchPending(),
            'sync' => fn() => self::syncProviders(),
            'expire' => fn() => self::expireUnpaid(),
            'stock' => fn() => self::checkLowStock(),
            'tickets' => fn() => self::closeStaleTickets(),
            'balances' => fn() => self::refreshProviderBalances(),
            'cleanup' => fn() => self::cleanup(),
        ];
        foreach ($tasks as $name => $fn) {
            try {
                $log[] = $name . ': ' . $fn();
            } catch (Throwable $e) {
                $log[] = $name . ': ERROR ' . $e->getMessage();
                log_error("automation/$name: " . $e->getMessage());
            }
        }
        if ($stamp) {
            setting_set('cron_last_run', (string)time());
        }
        setting_set('cron_last_log', implode("\n", $log));
        return $log;
    }

    /** Orders connected to a provider that were not sent yet (or failed temporarily). */
    public static function dispatchPending(): string
    {
        $ids = DB::column("SELECT id FROM orders WHERE status = 'pending' AND provider_id IS NOT NULL AND provider_order_id IS NULL AND provider_attempts < 5 ORDER BY id LIMIT 50");
        $ok = 0;
        foreach ($ids as $id) {
            $ok += OrderService::dispatch((int)$id) ? 1 : 0;
        }
        return "$ok/" . count($ids) . ' sent';
    }

    /** Pull live statuses for open provider orders and apply them (with auto refunds). */
    public static function syncProviders(): string
    {
        $orders = DB::all("SELECT * FROM orders WHERE provider_order_id IS NOT NULL AND status IN ('pending','processing','in_progress') ORDER BY updated_at IS NULL DESC, updated_at ASC LIMIT 300");
        if (!$orders) {
            return '0 orders';
        }
        $byProvider = [];
        foreach ($orders as $o) {
            $byProvider[(int)$o['provider_id']][] = $o;
        }
        $updated = 0;
        foreach ($byProvider as $pid => $list) {
            $p = DB::row('SELECT * FROM providers WHERE id = ? AND is_active = 1', [$pid]);
            if (!$p) {
                continue;
            }
            $api = new Provider($p);
            foreach (array_chunk($list, 100) as $chunk) {
                $map = [];
                foreach ($chunk as $o) {
                    $map[(string)$o['provider_order_id']] = $o;
                }
                try {
                    $statuses = $api->statuses(array_keys($map));
                } catch (Throwable $e) {
                    DB::update('providers', ['last_error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$pid]);
                    continue;
                }
                foreach ($statuses as $poid => $st) {
                    if (isset($map[(string)$poid]) && is_array($st) && !isset($st['error'])) {
                        OrderService::applyProviderStatus($map[(string)$poid], $st);
                        $updated++;
                    }
                }
                // Touch rows so the next run rotates to other orders.
                $in = implode(',', array_fill(0, count($map), '?'));
                DB::query("UPDATE orders SET updated_at = NOW() WHERE provider_order_id IN ($in) AND provider_id = ?", array_merge(array_keys($map), [$pid]));
            }
        }
        return "$updated synced";
    }

    public static function expireUnpaid(): string
    {
        $failed = DB::query("UPDATE payments SET status = 'failed', admin_note = 'منقضی شد' WHERE status = 'pending' AND gateway IN ('zarinpal','test') AND created_at < (NOW() - INTERVAL 2 HOUR)")->rowCount();
        $hours = max(1, (int)setting('unpaid_order_hours', 24));
        $canceled = DB::query("UPDATE orders SET status = 'canceled', admin_note = 'عدم پرداخت در مهلت مقرر' WHERE status = 'unpaid' AND created_at < (NOW() - INTERVAL $hours HOUR)")->rowCount();
        return "$failed payments, $canceled orders expired";
    }

    public static function checkLowStock(): string
    {
        $default = (int)setting('low_stock_default', 500);
        $rows = DB::all('SELECT id, title, stock, stock_alert FROM services WHERE is_active = 1 AND stock IS NOT NULL AND stock <= IF(stock_alert > 0, stock_alert, ?)', [$default]);
        $notified = json_decode((string)setting('low_stock_notified', '[]'), true) ?: [];
        $current = array_map(fn($r) => (int)$r['id'], $rows);
        $new = 0;
        foreach ($rows as $r) {
            if (!in_array((int)$r['id'], $notified, true)) {
                $new++;
                Notifier::admin(
                    ((int)$r['stock'] <= 0 ? 'ناموجود شد: ' : 'موجودی کم: ') . $r['title'],
                    'موجودی فعلی: ' . num($r['stock']) . ' عدد',
                    'admin/stock?service=' . $r['id'],
                    'alert'
                );
            }
        }
        setting_set('low_stock_notified', json_encode(array_values($current)));
        return count($rows) . " low, $new new alerts";
    }

    public static function closeStaleTickets(): string
    {
        $days = max(1, (int)setting('ticket_autoclose_days', 7));
        $n = DB::query("UPDATE tickets SET status = 'closed' WHERE status = 'answered' AND updated_at < (NOW() - INTERVAL $days DAY)")->rowCount();
        return "$n closed";
    }

    public static function refreshProviderBalances(): string
    {
        $n = 0;
        foreach (DB::all('SELECT * FROM providers WHERE is_active = 1 AND (checked_at IS NULL OR checked_at < (NOW() - INTERVAL 1 HOUR))') as $p) {
            try {
                $b = (new Provider($p))->balance();
                DB::update('providers', ['balance' => $b['balance'], 'currency' => $b['currency'], 'checked_at' => date('Y-m-d H:i:s'), 'last_error' => null], 'id = ?', [$p['id']]);
                $n++;
            } catch (Throwable $e) {
                DB::update('providers', ['checked_at' => date('Y-m-d H:i:s'), 'last_error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$p['id']]);
            }
        }
        return "$n refreshed";
    }

    public static function cleanup(): string
    {
        DB::query('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        DB::query('DELETE FROM password_resets WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        DB::query('DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 60 DAY)');
        return 'ok';
    }
}
