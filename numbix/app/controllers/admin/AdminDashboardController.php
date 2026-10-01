<?php

final class AdminDashboardController
{
    public const P = ['area' => 'admin'];

    /** Daily revenue + order count for the last $days days (chart.js payload). */
    public static function series(int $days): array
    {
        $rows = DB::all("SELECT DATE(created_at) d, COUNT(*) cnt, SUM(price - discount - refunded) amt, SUM(price - discount - refunded - cost) profit
            FROM orders WHERE status NOT IN ('unpaid','canceled') AND created_at >= (CURDATE() - INTERVAL $days DAY) GROUP BY DATE(created_at)");
        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = $r;
        }
        $out = ['labels' => [], 'count' => [], 'amount' => [], 'profit' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out['labels'][] = jdate('j F', $d, false);
            $out['count'][] = (int)($map[$d]['cnt'] ?? 0);
            $out['amount'][] = (int)($map[$d]['amt'] ?? 0);
            $out['profit'][] = (int)($map[$d]['profit'] ?? 0);
        }
        return $out;
    }

    public static function trend(float $now, float $prev): string
    {
        if ($prev <= 0) {
            return $now > 0 ? '<span class="trend up">' . icon('arrow-up') . 'جدید</span>' : '';
        }
        $p = round(($now - $prev) * 100 / $prev);
        return '<span class="trend ' . ($p >= 0 ? 'up' : 'down') . '">' . icon($p >= 0 ? 'arrow-up' : 'arrow-down') . '٪' . fa(abs($p)) . '</span>';
    }

    public function index(): string
    {
        $days = in_array(input_int('days', 30), [7, 30, 90], true) ? input_int('days', 30) : 30;
        $paidStates = "status NOT IN ('unpaid','canceled')";
        $k = DB::row("SELECT
            (SELECT COUNT(*) FROM orders) AS orders_total,
            (SELECT COUNT(*) FROM orders WHERE $paidStates AND created_at >= " . jmonth_sql(0) . ") AS orders_month,
            (SELECT COUNT(*) FROM orders WHERE $paidStates AND created_at >= " . jmonth_sql(-1) . " AND created_at < " . jmonth_sql(0) . ") AS orders_prev,
            (SELECT COALESCE(SUM(price - discount - refunded),0) FROM orders WHERE $paidStates AND DATE(created_at) = CURDATE()) AS revenue_today,
            (SELECT COALESCE(SUM(price - discount - refunded),0) FROM orders WHERE $paidStates AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY) AS revenue_yesterday,
            (SELECT COALESCE(SUM(price - discount - refunded),0) FROM orders WHERE $paidStates AND created_at >= " . jmonth_sql(0) . ") AS revenue_month,
            (SELECT COUNT(*) FROM users) AS users_total,
            (SELECT COUNT(*) FROM users WHERE created_at >= " . jmonth_sql(0) . ") AS users_month,
            (SELECT COUNT(*) FROM users WHERE created_at >= " . jmonth_sql(-1) . " AND created_at < " . jmonth_sql(0) . ") AS users_prev,
            (SELECT COUNT(*) FROM services WHERE is_active = 1) AS services_active,
            (SELECT COUNT(*) FROM services) AS services_total,
            (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending,
            (SELECT COUNT(*) FROM tickets WHERE status IN ('open','customer_reply')) AS tickets_open,
            (SELECT COUNT(*) FROM payments WHERE status = 'review') AS payments_review,
            (SELECT COUNT(*) FROM services WHERE is_active = 1 AND stock IS NOT NULL AND stock <= IF(stock_alert > 0, stock_alert, " . (int)setting('low_stock_default', 500) . ")) AS low_stock,
            (SELECT COALESCE(SUM(balance),0) FROM users) AS wallets");
        $series = self::series($days);
        $recent = DB::all('SELECT o.*, s.title, c.icon, c.color, c.color2, u.first_name, u.last_name, u.email, u.id AS uid FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 7');
        $top = DB::all("SELECT s.id, s.title, c.icon, c.color, c.color2, COUNT(o.id) cnt, COALESCE(SUM(o.price - o.discount - o.refunded),0) amt
            FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id
            WHERE o.status NOT IN ('unpaid','canceled') AND o.created_at >= (CURDATE() - INTERVAL 30 DAY) GROUP BY s.id ORDER BY cnt DESC LIMIT 5");
        $byStatus = DB::all('SELECT status, COUNT(*) n FROM orders GROUP BY status');

        $dbSize = (float)DB::value('SELECT COALESCE(SUM(data_length + index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()');
        $disk = @disk_free_space(BASE_PATH);
        $diskTotal = @disk_total_space(BASE_PATH);
        $last = (int)setting('cron_last_run', 0);
        $providers = DB::all('SELECT name, balance, currency, last_error, is_active FROM providers ORDER BY id LIMIT 3');

        return view('admin/dashboard', self::P + [
            'title' => 'داشبورد مدیریت',
            'k' => $k,
            'days' => $days,
            'chart' => ['labels' => $series['labels'], 'datasets' => [
                ['label' => 'تعداد سفارش', 'data' => $series['count'], 'color' => '#38BDF8', 'type' => 'bar'],
                ['label' => 'درآمد', 'data' => $series['amount'], 'color' => '#8B5CF6', 'type' => 'line', 'axis' => 'y1'],
            ]],
            'charts' => true,
            'recent' => $recent,
            'top' => $top,
            'byStatus' => $byStatus,
            'sys' => [
                'php' => PHP_VERSION,
                'db' => DB::value('SELECT VERSION()'),
                'db_size' => $dbSize,
                'disk_used_pct' => $diskTotal ? round(100 - $disk * 100 / $diskTotal) : null,
                'cron' => $last,
            ],
            'providers' => $providers,
        ], 'panel');
    }

    public function search(): array
    {
        $q = trim((string)input('q', ''));
        if (mb_strlen($q) < 1) {
            return ['groups' => []];
        }
        $num = (int)preg_replace('/\D/', '', en_digits($q));
        $orders = DB::all('SELECT o.id, o.status, s.title, u.first_name, u.last_name FROM orders o JOIN services s ON s.id = o.service_id LEFT JOIN users u ON u.id = o.user_id
            WHERE o.id = ? OR o.link LIKE ? OR o.provider_order_id = ? ORDER BY o.id DESC LIMIT 5', [$num, "%$q%", $q]);
        $users = DB::all('SELECT * FROM users WHERE id = ? OR email LIKE ? OR mobile LIKE ? OR username LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ? LIMIT 5', [$num, "%$q%", '%' . en_digits($q) . '%', "%$q%", "%$q%"]);
        $services = DB::all('SELECT s.id, s.title, c.icon FROM services s JOIN categories c ON c.id = s.category_id WHERE s.title LIKE ? OR s.id = ? LIMIT 5', ["%$q%", $num]);
        return ['groups' => [
            ['title' => 'سفارش‌ها', 'items' => array_map(fn($o) => ['title' => '#' . fa($o['id']) . ' — ' . $o['title'], 'sub' => trim($o['first_name'] . ' ' . $o['last_name']) . ' · ' . order_statuses()[$o['status']][0], 'url' => url('admin/orders/' . $o['id']), 'icon' => icon('cart')], $orders)],
            ['title' => 'کاربران', 'items' => array_map(fn($u) => ['title' => user_name($u), 'sub' => $u['email'] ?: $u['mobile'], 'url' => url('admin/users/' . $u['id']), 'icon' => icon('user')], $users)],
            ['title' => 'محصولات', 'items' => array_map(fn($s) => ['title' => $s['title'], 'sub' => 'ویرایش محصول', 'url' => url('admin/services/' . $s['id'] . '/edit'), 'icon' => brand($s['icon'])], $services)],
        ]];
    }

    public function reports(): string
    {
        $days = in_array(input_int('days', 30), [7, 30, 90, 365], true) ? input_int('days', 30) : 30;
        $series = self::series($days);
        $paid = "o.status NOT IN ('unpaid','canceled') AND o.created_at >= (CURDATE() - INTERVAL $days DAY)";
        $sum = DB::row("SELECT COUNT(*) orders, COALESCE(SUM(o.price - o.discount - o.refunded),0) revenue, COALESCE(SUM(o.cost),0) cost,
            COALESCE(SUM(o.discount),0) discounts, COALESCE(SUM(o.refunded),0) refunds, COUNT(DISTINCT o.user_id) buyers FROM orders o WHERE $paid");
        $deposits = (int)DB::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_at >= (CURDATE() - INTERVAL $days DAY)");
        $byCat = DB::all("SELECT c.name, c.color, COALESCE(SUM(o.price - o.discount - o.refunded),0) amt, COUNT(o.id) cnt FROM orders o
            JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE $paid GROUP BY c.id ORDER BY amt DESC");
        $topServices = DB::all("SELECT s.title, c.icon, c.color, c.color2, COUNT(o.id) cnt, SUM(o.quantity) qty, COALESCE(SUM(o.price - o.discount - o.refunded),0) amt, COALESCE(SUM(o.price - o.discount - o.refunded - o.cost),0) profit
            FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE $paid GROUP BY s.id ORDER BY amt DESC LIMIT 10");
        $topUsers = DB::all("SELECT u.*, COUNT(o.id) cnt, COALESCE(SUM(o.price - o.discount - o.refunded),0) amt FROM orders o JOIN users u ON u.id = o.user_id WHERE $paid GROUP BY u.id ORDER BY amt DESC LIMIT 8");
        return view('admin/reports', self::P + [
            'title' => 'گزارشات',
            'days' => $days,
            'sum' => $sum,
            'deposits' => $deposits,
            'byCat' => $byCat,
            'topServices' => $topServices,
            'topUsers' => $topUsers,
            'charts' => true,
            'chart' => ['labels' => $series['labels'], 'datasets' => [
                ['label' => 'درآمد', 'data' => $series['amount'], 'color' => '#8B5CF6', 'type' => 'bar'],
                ['label' => 'سود', 'data' => $series['profit'], 'color' => '#10B981', 'type' => 'line'],
            ]],
            'pie' => ['type' => 'doughnut', 'money' => true, 'labels' => array_column($byCat, 'name'), 'datasets' => [
                ['label' => 'فروش', 'data' => array_map('intval', array_column($byCat, 'amt')), 'type' => 'doughnut', 'colors' => array_column($byCat, 'color')],
            ]],
        ], 'panel');
    }

    public function notificationsRead(): array
    {
        DB::query('UPDATE notifications SET is_read = 1 WHERE for_admin = 1');
        return ['ok' => true];
    }

    public function runCron(): never
    {
        $log = Automation::run();
        flash('success', 'اتوماسیون اجرا شد: ' . implode(' | ', $log));
        back('admin');
    }
}
