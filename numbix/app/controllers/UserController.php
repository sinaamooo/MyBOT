<?php

final class UserController
{
    private const P = ['area' => 'user'];

    private static function me(): array
    {
        return auth();
    }

    public function dashboard(): string
    {
        $u = self::me();
        $id = (int)$u['id'];
        $stats = DB::row("SELECT COUNT(*) AS total,
            SUM(status IN ('pending','processing','in_progress')) AS active,
            SUM(status = 'completed') AS completed,
            SUM(status = 'unpaid') AS unpaid
            FROM orders WHERE user_id = ?", [$id]);
        $recent = DB::all('SELECT o.*, s.title, c.icon, c.color, c.color2 FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 6', [$id]);

        $days = 14;
        $rows = DB::all("SELECT DATE(created_at) d, SUM(price - discount - refunded) amt, COUNT(*) cnt FROM orders
            WHERE user_id = ? AND status NOT IN ('unpaid','canceled') AND created_at >= (CURDATE() - INTERVAL $days DAY) GROUP BY DATE(created_at)", [$id]);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = $r;
        }
        $labels = $amounts = $counts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $labels[] = jdate('j F', $d, false);
            $amounts[] = (int)($map[$d]['amt'] ?? 0);
            $counts[] = (int)($map[$d]['cnt'] ?? 0);
        }
        $suggest = DB::all('SELECT s.*, c.name AS category_name, c.slug AS category_slug, c.icon, c.color, c.color2 FROM services s JOIN categories c ON c.id = s.category_id WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY s.is_featured DESC, s.sales_count DESC LIMIT 4');

        return view('user/dashboard', self::P + [
            'title' => 'داشبورد',
            'u' => $u,
            'stats' => $stats,
            'recent' => $recent,
            'chart' => ['labels' => $labels, 'datasets' => [
                ['label' => 'تعداد سفارش', 'data' => $counts, 'color' => '#22D3EE', 'type' => 'bar'],
                ['label' => 'مبلغ خرید', 'data' => $amounts, 'color' => '#C084FC', 'type' => 'line', 'axis' => 'y1'],
            ]],
            'charts' => true,
            'suggest' => $suggest,
            'favs' => HomeController::favs(),
            'tickets' => (int)DB::value("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'answered'", [$id]),
        ], 'panel');
    }

    // ------------------------------------------------------------------ orders

    public function newOrder(): string
    {
        $services = DB::all('SELECT s.*, c.name AS category_name, c.icon, c.color, c.color2 FROM services s JOIN categories c ON c.id = s.category_id
            WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.sort, c.id, s.sort, s.id');
        return view('user/new_order', self::P + [
            'title' => 'ثبت سفارش جدید',
            'u' => self::me(),
            'categories' => HomeController::categories(),
            'services' => $services,
            'selected' => input_int('service'),
            'online' => Zarinpal::enabled(),
            'testGateway' => CartController::testGatewayAllowed(self::me()),
        ], 'panel');
    }

    public function placeOrder(): never
    {
        $u = self::me();
        $s = DB::row('SELECT s.* FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.is_active = 1 AND c.is_active = 1', [input_int('service_id')]);
        if (!$s) {
            fail('سرویس را انتخاب کنید.');
        }
        $qty = input_int('qty');
        $link = trim((string)input('link', ''));
        if ($err = OrderService::validateLine($s, $qty, $link)) {
            fail($err);
        }
        $line = ['service' => $s, 'qty' => $qty, 'link' => $link, 'amount' => service_price($s, $qty), 'discount' => 0];
        $coupon = null;
        if ($code = trim((string)input('coupon', ''))) {
            $coupon = Coupon::find($code);
            $res = Coupon::evaluate($coupon, (int)$u['id'], [$line]);
            if (is_string($res)) {
                fail($res);
            }
            $line['discount'] = $res;
        }
        $due = $line['amount'] - $line['discount'];
        $method = input('method', 'wallet');
        try {
            $ids = OrderService::createUnpaid((int)$u['id'], [$line], $coupon);
            if ($method === 'wallet' || (int)$u['balance'] >= $due) {
                $r = OrderService::pay($ids);
                if ($r['paid']) {
                    flash('success', 'سفارش شما با موفقیت ثبت شد و در صف انجام قرار گرفت. 🚀');
                    redirect('dashboard/orders/' . $r['paid'][0]);
                }
                flash('error', $r['errors'][0] ?? 'ثبت سفارش ناموفق بود.');
                redirect('dashboard/orders/' . $ids[0]);
            }
            $this->gatewayForOrders($u, $ids, $method);
        } catch (DomainException $e) {
            fail($e->getMessage());
        }
    }

    private function gatewayForOrders(array $u, array $ids, string $method): never
    {
        $due = OrderService::amountDue($ids);
        $gw = $method === 'test' && CartController::testGatewayAllowed($u) ? 'test' : 'zarinpal';
        if ($gw === 'zarinpal' && !Zarinpal::enabled()) {
            flash('warning', 'موجودی کیف پول کافی نیست. ابتدا کیف پول را شارژ کنید؛ سفارش در وضعیت «در انتظار پرداخت» ذخیره شد.');
            redirect('dashboard/wallet');
        }
        $amount = max(1000, $due - (int)$u['balance']);
        $pid = Payments::create((int)$u['id'], $amount, $gw, 'order', ['orders' => $ids]);
        DB::query('UPDATE orders SET payment_id = ? WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')', [$pid]);
        redirect(Payments::start($pid, $u), true);
    }

    public function orders(): string
    {
        $u = self::me();
        $where = 'o.user_id = ?';
        $params = [(int)$u['id']];
        $status = input('status', '');
        if ($status === 'active') {
            $where .= " AND o.status IN ('pending','processing','in_progress')";
        } elseif ($status !== '' && isset(order_statuses()[$status])) {
            $where .= ' AND o.status = ?';
            $params[] = $status;
        }
        if ($q = trim((string)input('q', ''))) {
            $where .= ' AND (o.id = ? OR o.link LIKE ? OR s.title LIKE ?)';
            array_push($params, (int)en_digits($q), "%$q%", "%$q%");
        }
        $page = paginate('o.*, s.title, s.slug, c.icon, c.color, c.color2', "FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE $where", $params, 15, 'ORDER BY o.id DESC');
        $counts = DB::row("SELECT COUNT(*) AS all_, SUM(status IN ('pending','processing','in_progress')) AS active, SUM(status='completed') AS completed, SUM(status='unpaid') AS unpaid, SUM(status IN ('canceled','partial','refunded')) AS canceled FROM orders WHERE user_id = ?", [$u['id']]);
        return view('user/orders', self::P + ['title' => 'سفارش‌های من', 'page' => $page, 'status' => $status, 'counts' => $counts], 'panel');
    }

    private static function ownOrder(string $id): array
    {
        $o = DB::row('SELECT o.*, s.title, s.slug, s.delivery_time, c.name AS category_name, c.icon, c.color, c.color2 FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE o.id = ? AND o.user_id = ?', [(int)$id, Auth::id()]);
        if (!$o) {
            abort(404, 'سفارش پیدا نشد.');
        }
        return $o;
    }

    public function order(string $id): string
    {
        $o = self::ownOrder($id);
        return view('user/order', self::P + [
            'title' => 'سفارش #' . fa($o['id']),
            'o' => $o,
            'u' => self::me(),
            'online' => Zarinpal::enabled(),
            'testGateway' => CartController::testGatewayAllowed(self::me()),
        ], 'panel');
    }

    public function payOrder(string $id): never
    {
        $o = self::ownOrder($id);
        if ($o['status'] !== 'unpaid') {
            fail('این سفارش قبلاً پرداخت شده یا لغو شده است.');
        }
        $u = self::me();
        $due = (int)$o['price'] - (int)$o['discount'];
        if ((int)$u['balance'] >= $due) {
            $r = OrderService::pay([(int)$o['id']]);
            $r['paid'] ? flash('success', 'سفارش پرداخت شد و در صف انجام قرار گرفت.') : flash('error', $r['errors'][0] ?? 'پرداخت ناموفق بود.');
            redirect('dashboard/orders/' . $o['id']);
        }
        try {
            $this->gatewayForOrders($u, [(int)$o['id']], input('method', 'online'));
        } catch (DomainException $e) {
            fail($e->getMessage());
        }
    }

    public function cancelOrder(string $id): never
    {
        $o = self::ownOrder($id);
        if ($o['status'] !== 'unpaid') {
            fail('فقط سفارش‌های پرداخت‌نشده قابل لغو هستند. برای سایر موارد تیکت ثبت کنید.');
        }
        OrderService::setStatus((int)$o['id'], 'canceled', ['admin_note' => 'لغو توسط کاربر'], false);
        flash('info', 'سفارش لغو شد.');
        redirect('dashboard/orders');
    }

    // ------------------------------------------------------------------ wallet

    public function wallet(): string
    {
        $u = self::me();
        $tx = paginate('*', 'FROM transactions WHERE user_id = ?', [(int)$u['id']], 12, 'ORDER BY id DESC');
        $payments = DB::all('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$u['id']]);
        $sum = DB::row("SELECT COALESCE(SUM(CASE WHEN amount > 0 AND type IN ('deposit','admin_add','bonus') THEN amount END),0) AS deposits,
            COALESCE(SUM(CASE WHEN type = 'refund' THEN amount END),0) AS refunds FROM transactions WHERE user_id = ?", [$u['id']]);
        return view('user/wallet', self::P + [
            'title' => 'کیف پول',
            'u' => $u,
            'tx' => $tx,
            'payments' => $payments,
            'sum' => $sum,
            'online' => Zarinpal::enabled(),
            'testGateway' => CartController::testGatewayAllowed($u),
            'orderId' => input_int('order'),
            'presetAmount' => input_int('amount'),
        ], 'panel');
    }

    // ------------------------------------------------------------------ favorites

    public function toggleFavorite(string $id): array
    {
        $sid = (int)$id;
        if (!DB::value('SELECT id FROM services WHERE id = ?', [$sid])) {
            return ['ok' => false, 'message' => 'سرویس یافت نشد.'];
        }
        $uid = Auth::id();
        if (DB::value('SELECT 1 FROM favorites WHERE user_id = ? AND service_id = ?', [$uid, $sid])) {
            DB::delete('favorites', 'user_id = ? AND service_id = ?', [$uid, $sid]);
            return ['ok' => true, 'on' => false, 'message' => 'از علاقه‌مندی‌ها حذف شد.'];
        }
        DB::insert('favorites', ['user_id' => $uid, 'service_id' => $sid]);
        return ['ok' => true, 'on' => true, 'message' => 'به علاقه‌مندی‌ها اضافه شد.'];
    }

    public function favorites(): string
    {
        $items = DB::all('SELECT s.*, c.name AS category_name, c.slug AS category_slug, c.icon, c.color, c.color2 FROM favorites f JOIN services s ON s.id = f.service_id JOIN categories c ON c.id = s.category_id
            WHERE f.user_id = ? AND s.is_active = 1 ORDER BY f.created_at DESC', [Auth::id()]);
        return view('user/favorites', self::P + ['title' => 'علاقه‌مندی‌ها', 'items' => $items, 'favs' => array_map(fn($s) => (int)$s['id'], $items)], 'panel');
    }

    // ------------------------------------------------------------------ tickets

    public static function departments(): array
    {
        return ['support' => 'پشتیبانی سفارش', 'finance' => 'مالی و پرداخت', 'sales' => 'فروش و همکاری', 'technical' => 'فنی و API'];
    }

    public function tickets(): string
    {
        $page = paginate('t.*, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS msgs', 'FROM tickets t WHERE t.user_id = ?', [Auth::id()], 15, 'ORDER BY t.updated_at DESC');
        return view('user/tickets', self::P + ['title' => 'تیکت‌های پشتیبانی', 'page' => $page], 'panel');
    }

    public function ticketCreate(): string
    {
        $orders = DB::all('SELECT o.id, s.title FROM orders o JOIN services s ON s.id = o.service_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 30', [Auth::id()]);
        return view('user/ticket_new', self::P + ['title' => 'تیکت جدید', 'orders' => $orders, 'orderId' => input_int('order')], 'panel');
    }

    public function ticketStore(): never
    {
        $subject = mb_substr(trim((string)input('subject')), 0, 200);
        $message = trim((string)input('message'));
        $dept = array_key_exists(input('department'), self::departments()) ? input('department') : 'support';
        $priority = in_array(input('priority'), ['low', 'normal', 'high'], true) ? input('priority') : 'normal';
        if (mb_strlen($subject) < 3 || mb_strlen($message) < 5) {
            fail('موضوع و متن پیام را کامل وارد کنید.');
        }
        $orderId = input_int('order_id') ?: null;
        if ($orderId && !DB::value('SELECT id FROM orders WHERE id = ? AND user_id = ?', [$orderId, Auth::id()])) {
            $orderId = null;
        }
        $att = $this->attachment();
        $tid = DB::insert('tickets', ['user_id' => Auth::id(), 'subject' => $subject, 'department' => $dept, 'priority' => $priority, 'order_id' => $orderId, 'status' => 'open']);
        DB::insert('ticket_messages', ['ticket_id' => $tid, 'user_id' => Auth::id(), 'message' => mb_substr($message, 0, 5000), 'attachment' => $att]);
        Notifier::admin('تیکت جدید #' . $tid . ': ' . $subject, user_name(auth()), 'admin/tickets/' . $tid, 'message');
        flash('success', 'تیکت شما ثبت شد. به‌زودی پاسخ داده می‌شود.');
        redirect('dashboard/tickets/' . $tid);
    }

    private function attachment(): ?string
    {
        if (!Uploader::has('attachment')) {
            return null;
        }
        try {
            return Uploader::file('attachment', 'tickets', 4096);
        } catch (RuntimeException $e) {
            fail($e->getMessage());
        }
    }

    private static function ownTicket(string $id): array
    {
        $t = DB::row('SELECT * FROM tickets WHERE id = ? AND user_id = ?', [(int)$id, Auth::id()]);
        if (!$t) {
            abort(404, 'تیکت پیدا نشد.');
        }
        return $t;
    }

    public function ticket(string $id): string
    {
        $t = self::ownTicket($id);
        $msgs = DB::all('SELECT m.*, u.first_name, u.last_name, u.avatar, u.id AS uid FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.id', [$t['id']]);
        return view('user/ticket', self::P + ['title' => 'تیکت #' . fa($t['id']), 't' => $t, 'msgs' => $msgs], 'panel');
    }

    public function ticketReply(string $id): never
    {
        $t = self::ownTicket($id);
        $message = trim((string)input('message'));
        if (mb_strlen($message) < 2) {
            fail('متن پیام را وارد کنید.');
        }
        $att = $this->attachment();
        DB::insert('ticket_messages', ['ticket_id' => $t['id'], 'user_id' => Auth::id(), 'message' => mb_substr($message, 0, 5000), 'attachment' => $att]);
        DB::update('tickets', ['status' => 'customer_reply', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
        Notifier::admin('پاسخ جدید در تیکت #' . $t['id'], str_limit($message, 80), 'admin/tickets/' . $t['id'], 'message');
        flash('success', 'پیام شما ارسال شد.');
        redirect('dashboard/tickets/' . $t['id']);
    }

    public function ticketClose(string $id): never
    {
        $t = self::ownTicket($id);
        DB::update('tickets', ['status' => 'closed'], 'id = ?', [$t['id']]);
        flash('info', 'تیکت بسته شد.');
        redirect('dashboard/tickets');
    }

    // ------------------------------------------------------------------ profile / api / notifications

    public function profile(): string
    {
        return view('user/profile', self::P + ['title' => 'پروفایل و امنیت', 'u' => self::me()], 'panel');
    }

    public function profileUpdate(): never
    {
        $u = self::me();
        $first = mb_substr(trim((string)input('first_name')), 0, 80);
        $email = mb_strtolower(trim((string)input('email')));
        $mobile = normalize_mobile((string)input('mobile'));
        $username = trim((string)input('username', '')) ?: null;
        if ($first === '') {
            fail('نام را وارد کنید.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('ایمیل معتبر نیست.');
        }
        if ($mobile && !preg_match('/^09\d{9}$/', $mobile)) {
            fail('شماره موبایل معتبر نیست.');
        }
        if ($username !== null && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{3,30}$/', $username)) {
            fail('نام کاربری باید با حرف انگلیسی شروع شود و ۴ تا ۳۱ کاراکتر (حروف، عدد، _) باشد.');
        }
        foreach (['email' => $email ?: null, 'mobile' => $mobile, 'username' => $username] as $col => $val) {
            if ($val !== null && DB::value("SELECT id FROM users WHERE `$col` = ? AND id <> ?", [$val, $u['id']])) {
                fail(['email' => 'این ایمیل', 'mobile' => 'این موبایل', 'username' => 'این نام کاربری'][$col] . ' قبلاً توسط کاربر دیگری ثبت شده است.');
            }
        }
        $data = ['first_name' => $first, 'last_name' => mb_substr(trim((string)input('last_name')), 0, 80), 'email' => $email ?: null, 'mobile' => $mobile, 'username' => $username];
        if (Uploader::has('avatar')) {
            try {
                $data['avatar'] = Uploader::image('avatar', 'avatars', 2048);
                Uploader::delete($u['avatar']);
            } catch (RuntimeException $e) {
                fail($e->getMessage());
            }
        }
        DB::update('users', $data, 'id = ?', [$u['id']]);
        flash('success', 'اطلاعات حساب بروزرسانی شد.');
        redirect('dashboard/profile');
    }

    public function passwordUpdate(): never
    {
        $u = self::me();
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        if ($u['password'] && !password_verify($cur, $u['password'])) {
            fail('رمز عبور فعلی صحیح نیست.');
        }
        if (mb_strlen($new) < 8 || !preg_match('/\d/', $new) || !preg_match('/\p{L}/u', $new)) {
            fail('رمز عبور جدید باید حداقل ۸ کاراکتر و شامل عدد و حرف باشد.');
        }
        if ($new !== ($_POST['password_confirmation'] ?? '')) {
            fail('تکرار رمز عبور مطابقت ندارد.');
        }
        DB::update('users', ['password' => password_hash($new, PASSWORD_DEFAULT), 'remember_token' => null], 'id = ?', [$u['id']]);
        flash('success', 'رمز عبور تغییر کرد.');
        redirect('dashboard/profile');
    }

    public function api(): string
    {
        return view('user/api', self::P + ['title' => 'وب‌سرویس API', 'u' => self::me()], 'panel');
    }

    public function apiRegenerate(): never
    {
        DB::update('users', ['api_key' => bin2hex(random_bytes(20))], 'id = ?', [Auth::id()]);
        flash('success', 'کلید API جدید ساخته شد. کلید قبلی دیگر کار نمی‌کند.');
        redirect('dashboard/api');
    }

    public function notifications(): string
    {
        $page = paginate('*', 'FROM notifications WHERE user_id = ?', [Auth::id()], 20, 'ORDER BY id DESC');
        DB::query('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [Auth::id()]);
        return view('user/notifications', self::P + ['title' => 'اعلان‌ها', 'page' => $page], 'panel');
    }

    public function notificationsRead(): array
    {
        DB::query('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [Auth::id()]);
        return ['ok' => true];
    }
}
