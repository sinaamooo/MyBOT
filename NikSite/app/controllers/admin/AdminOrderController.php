<?php

final class AdminOrderController
{
    private const P = ['area' => 'admin'];

    private function filters(): array
    {
        $where = ['1=1'];
        $params = [];
        $status = input('status', '');
        if ($status !== '' && isset(order_statuses()[$status])) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($sid = input_int('service')) {
            $where[] = 'o.service_id = ?';
            $params[] = $sid;
        }
        if ($cid = input_int('cat')) {
            $where[] = 's.category_id = ?';
            $params[] = $cid;
        }
        if ($uid = input_int('user')) {
            $where[] = 'o.user_id = ?';
            $params[] = $uid;
        }
        if ($from = jalali_to_date(input('from'))) {
            $where[] = 'o.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to = jalali_to_date(input('to'))) {
            $where[] = 'o.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        if (input('source') === 'api') {
            $where[] = "o.source = 'api'";
        }
        if ($q = trim((string)input('q', ''))) {
            $num = (int)preg_replace('/\D/', '', en_digits($q));
            $where[] = '(o.id = ? OR o.link LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR s.title LIKE ? OR o.provider_order_id = ?)';
            array_push($params, $num, "%$q%", "%$q%", '%' . en_digits($q) . '%', "%$q%", "%$q%", $q);
        }
        return [implode(' AND ', $where), $params];
    }

    private const FROM = 'FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id LEFT JOIN users u ON u.id = o.user_id';

    public function index(): string
    {
        [$where, $params] = $this->filters();
        $per = in_array(input_int('per', 15), [15, 30, 50, 100], true) ? input_int('per', 15) : 15;
        $page = paginate('o.*, s.title, s.provider_service_id, c.icon, c.color, c.color2, u.first_name, u.last_name, u.email, u.username, u.avatar, u.id AS uid',
            self::FROM . " WHERE $where", $params, $per, 'ORDER BY o.id DESC');
        $k = DB::row("SELECT COUNT(*) total,
            SUM(status = 'completed') completed,
            SUM(status IN ('pending','processing','in_progress')) active,
            SUM(status IN ('canceled','refunded')) canceled,
            SUM(created_at >= " . jmonth_sql(0) . ") month_,
            SUM(created_at >= " . jmonth_sql(-1) . " AND created_at < " . jmonth_sql(0) . ") prev
            FROM orders");
        return view('admin/orders', self::P + [
            'title' => 'سفارشات کاربران',
            'page' => $page,
            'k' => $k,
            'per' => $per,
            'services' => DB::all('SELECT id, title FROM services ORDER BY title'),
        ], 'panel');
    }

    public function export(): never
    {
        [$where, $params] = $this->filters();
        $rows = DB::all('SELECT o.*, s.title, u.first_name, u.last_name, u.email, u.mobile ' . self::FROM . " WHERE $where ORDER BY o.id DESC LIMIT 20000", $params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders-' . jdate('Y-m-d', null, false) . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['شماره', 'کاربر', 'ایمیل', 'موبایل', 'سرویس', 'لینک', 'تعداد', 'مبلغ', 'تخفیف', 'بازگشتی', 'هزینه', 'وضعیت', 'شروع', 'باقی‌مانده', 'شناسه API', 'تاریخ']);
        foreach ($rows as $o) {
            fputcsv($out, [$o['id'], trim($o['first_name'] . ' ' . $o['last_name']), $o['email'], $o['mobile'], $o['title'], $o['link'], $o['quantity'], $o['price'], $o['discount'], $o['refunded'], $o['cost'],
                order_statuses()[$o['status']][0] ?? $o['status'], $o['start_count'], $o['remains'], $o['provider_order_id'], jdate('Y/m/d H:i', $o['created_at'], false)]);
        }
        exit;
    }

    public function create(): string
    {
        return view('admin/order_create', self::P + [
            'title' => 'ثبت سفارش جدید',
            'services' => DB::all('SELECT s.*, c.name AS category_name FROM services s JOIN categories c ON c.id = s.category_id WHERE s.is_active = 1 ORDER BY c.sort, s.sort'),
            'users' => DB::all('SELECT id, first_name, last_name, email, mobile, balance FROM users ORDER BY id DESC LIMIT 500'),
        ], 'panel');
    }

    public function store(): never
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [input_int('user_id')]);
        $s = DB::row('SELECT * FROM services WHERE id = ?', [input_int('service_id')]);
        if (!$u || !$s) {
            fail('کاربر و سرویس را انتخاب کنید.');
        }
        $qty = input_int('qty');
        $link = trim((string)input('link'));
        if ($err = OrderService::validateLine($s, $qty, $link)) {
            fail($err);
        }
        $charge = input('charge') === '1';
        try {
            $ids = OrderService::createUnpaid((int)$u['id'], [['service' => $s, 'qty' => $qty, 'link' => $link, 'discount' => 0]], null, null, 'admin');
            $r = OrderService::pay($ids, $charge);
        } catch (DomainException $e) {
            fail($e->getMessage());
        }
        if (!$r['paid']) {
            fail($r['errors'][0] ?? 'ثبت سفارش ناموفق بود.', 'admin/orders/' . $ids[0]);
        }
        Notifier::user((int)$u['id'], 'سفارش جدید برای شما ثبت شد', $s['title'] . ' — ' . num($qty) . ' عدد', 'dashboard/orders/' . $r['paid'][0], 'gift');
        flash('success', 'سفارش #' . fa($r['paid'][0]) . ' ثبت شد' . ($charge ? ' و از کیف پول کاربر کسر شد.' : ' (رایگان).'));
        redirect('admin/orders/' . $r['paid'][0]);
    }

    public function show(string $id): string
    {
        $o = DB::row('SELECT o.*, s.title, s.slug, s.provider_service_id, c.name AS category_name, c.icon, c.color, c.color2, u.first_name, u.last_name, u.email, u.mobile, u.balance, u.avatar, u.id AS uid, p.name AS provider_name, cp.code AS coupon_code
            FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN providers p ON p.id = o.provider_id LEFT JOIN coupons cp ON cp.id = o.coupon_id WHERE o.id = ?', [(int)$id]);
        if (!$o) {
            abort(404, 'سفارش پیدا نشد.');
        }
        $tx = DB::all("SELECT * FROM transactions WHERE ref_type = 'order' AND ref_id = ? ORDER BY id", [$o['id']]);
        $tickets = DB::all('SELECT id, subject, status FROM tickets WHERE order_id = ?', [$o['id']]);
        return view('admin/order', self::P + ['title' => 'سفارش #' . fa($o['id']), 'o' => $o, 'tx' => $tx, 'tickets' => $tickets], 'panel');
    }

    public function update(string $id): never
    {
        $o = DB::row('SELECT * FROM orders WHERE id = ?', [(int)$id]);
        if (!$o) {
            abort(404);
        }
        $status = (string)input('status', $o['status']);
        try {
            OrderService::setStatus((int)$o['id'], $status, [
                'start_count' => input('start_count'),
                'remains' => input('remains'),
                'admin_note' => (string)input('admin_note', ''),
            ], input('notify') === '1');
        } catch (DomainException $e) {
            fail($e->getMessage());
        }
        if (($link = trim((string)input('link', ''))) !== '' && $link !== $o['link']) {
            DB::update('orders', ['link' => mb_substr($link, 0, 500)], 'id = ?', [$o['id']]);
        }
        flash('success', 'سفارش بروزرسانی شد.');
        redirect('admin/orders/' . $o['id']);
    }

    public function bulk(): never
    {
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        $status = (string)input('status');
        if (!$ids || !isset(order_statuses()[$status])) {
            fail('سفارش‌ها و وضعیت جدید را انتخاب کنید.');
        }
        $n = 0;
        foreach ($ids as $id) {
            try {
                OrderService::setStatus($id, $status);
                $n++;
            } catch (Throwable) {
            }
        }
        flash('success', 'وضعیت ' . fa($n) . ' سفارش تغییر کرد.');
        back('admin/orders');
    }

    public function resend(string $id): never
    {
        $o = DB::row('SELECT * FROM orders WHERE id = ?', [(int)$id]);
        if (!$o || $o['status'] !== 'pending' || $o['provider_order_id']) {
            fail('این سفارش قابل ارسال مجدد نیست.');
        }
        if (!$o['provider_id']) {
            $pid = DB::value('SELECT provider_id FROM services WHERE id = ?', [$o['service_id']]);
            if (!$pid) {
                fail('سرویس این سفارش به API متصل نیست.');
            }
            DB::update('orders', ['provider_id' => $pid], 'id = ?', [$o['id']]);
        }
        DB::update('orders', ['provider_attempts' => 0], 'id = ?', [$o['id']]);
        OrderService::dispatch((int)$o['id'])
            ? flash('success', 'سفارش با موفقیت به API ارسال شد.')
            : flash('error', 'ارسال ناموفق بود: ' . DB::value('SELECT provider_error FROM orders WHERE id = ?', [$o['id']]));
        redirect('admin/orders/' . $o['id']);
    }

    public function sync(string $id): never
    {
        $o = DB::row('SELECT * FROM orders WHERE id = ?', [(int)$id]);
        $p = $o && $o['provider_id'] ? DB::row('SELECT * FROM providers WHERE id = ?', [$o['provider_id']]) : null;
        if (!$o || !$p || !$o['provider_order_id']) {
            fail('این سفارش به API ارسال نشده است.');
        }
        try {
            $st = (new Provider($p))->statuses([$o['provider_order_id']]);
            $row = reset($st);
            if (is_array($row) && !isset($row['error'])) {
                OrderService::applyProviderStatus($o, $row);
                flash('success', 'وضعیت از API دریافت شد: ' . ($row['status'] ?? '—'));
            } else {
                flash('error', 'API: ' . ($row['error'] ?? 'پاسخ نامعتبر'));
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . $o['id']);
    }
}
