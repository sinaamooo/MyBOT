<?php

final class AdminUserController
{
    private const P = ['area' => 'admin'];

    public function index(): string
    {
        $where = ['1=1'];
        $params = [];
        if ($q = trim((string)input('q', ''))) {
            $num = (int)preg_replace('/\D/', '', en_digits($q));
            $where[] = '(u.id = ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.username LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
            array_push($params, $num, "%$q%", '%' . en_digits($q) . '%', "%$q%", "%$q%");
        }
        if (in_array(input('role'), ['user', 'admin'], true)) {
            $where[] = 'u.role = ?';
            $params[] = input('role');
        }
        if (input('status') === 'banned') {
            $where[] = "u.status = 'banned'";
        }
        $sorts = ['new' => 'u.id DESC', 'spent' => 'u.total_spent DESC', 'balance' => 'u.balance DESC', 'login' => 'u.last_login_at DESC'];
        $sort = $sorts[input('sort')] ?? $sorts['new'];
        $page = paginate('u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count', 'FROM users u WHERE ' . implode(' AND ', $where), $params, 20, "ORDER BY $sort");
        $k = DB::row("SELECT COUNT(*) total, SUM(created_at >= CURDATE()) today, SUM(created_at >= " . jmonth_sql(0) . ") month_,
            SUM(last_login_at >= (NOW() - INTERVAL 7 DAY)) active7, COALESCE(SUM(balance),0) wallets FROM users");
        return view('admin/users', self::P + ['title' => 'مدیریت کاربران', 'page' => $page, 'k' => $k], 'panel');
    }

    public function store(): never
    {
        $email = mb_strtolower(trim((string)input('email')));
        $pass = (string)($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 8) {
            fail('ایمیل معتبر و رمز عبور حداقل ۸ کاراکتری وارد کنید.');
        }
        if (DB::value('SELECT id FROM users WHERE email = ?', [$email])) {
            fail('این ایمیل قبلاً ثبت شده است.');
        }
        $id = DB::insert('users', [
            'first_name' => mb_substr(trim((string)input('first_name')), 0, 80),
            'last_name' => mb_substr(trim((string)input('last_name')), 0, 80),
            'email' => $email,
            'mobile' => normalize_mobile((string)input('mobile')),
            'password' => password_hash($pass, PASSWORD_DEFAULT),
            'role' => input('role') === 'admin' ? 'admin' : 'user',
        ]);
        flash('success', 'کاربر ساخته شد.');
        redirect('admin/users/' . $id);
    }

    public function show(string $id): string
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if (!$u) {
            abort(404, 'کاربر پیدا نشد.');
        }
        $stats = DB::row("SELECT COUNT(*) total, SUM(status = 'completed') completed, SUM(status IN ('pending','processing','in_progress')) active,
            COALESCE(SUM(CASE WHEN status NOT IN ('unpaid','canceled') THEN price - discount - refunded END),0) spent FROM orders WHERE user_id = ?", [$u['id']]);
        $orders = DB::all('SELECT o.*, s.title, c.icon, c.color, c.color2 FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 10', [$u['id']]);
        $tx = DB::all('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$u['id']]);
        $tickets = DB::all('SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$u['id']]);
        return view('admin/user', self::P + ['title' => user_name($u), 'u' => $u, 'stats' => $stats, 'orders' => $orders, 'tx' => $tx, 'tickets' => $tickets], 'panel');
    }

    public function update(string $id): never
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if (!$u) {
            abort(404);
        }
        $email = mb_strtolower(trim((string)input('email'))) ?: null;
        $mobile = normalize_mobile((string)input('mobile'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('ایمیل معتبر نیست.');
        }
        foreach (['email' => $email, 'mobile' => $mobile] as $col => $val) {
            if ($val && DB::value("SELECT id FROM users WHERE `$col` = ? AND id <> ?", [$val, $u['id']])) {
                fail('این ' . ($col === 'email' ? 'ایمیل' : 'موبایل') . ' متعلق به کاربر دیگری است.');
            }
        }
        $role = input('role') === 'admin' ? 'admin' : 'user';
        $status = input('status') === 'banned' ? 'banned' : 'active';
        if ((int)$u['id'] === Auth::id() && ($role !== 'admin' || $status !== 'active')) {
            fail('نمی‌توانید نقش یا وضعیت حساب خودتان را تغییر دهید.');
        }
        $data = [
            'first_name' => mb_substr(trim((string)input('first_name')), 0, 80),
            'last_name' => mb_substr(trim((string)input('last_name')), 0, 80),
            'email' => $email,
            'mobile' => $mobile,
            'role' => $role,
            'status' => $status,
            'admin_note' => mb_substr((string)input('admin_note', ''), 0, 2000) ?: null,
        ];
        if (($p = (string)($_POST['password'] ?? '')) !== '') {
            if (mb_strlen($p) < 8) {
                fail('رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
            }
            $data['password'] = password_hash($p, PASSWORD_DEFAULT);
            $data['remember_token'] = null;
        }
        DB::update('users', $data, 'id = ?', [$u['id']]);
        flash('success', 'اطلاعات کاربر ذخیره شد.');
        redirect('admin/users/' . $u['id']);
    }

    public function balance(string $id): never
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
        $amount = input_int('amount');
        $reason = trim((string)input('reason', '')) ?: 'تغییر موجودی توسط مدیریت';
        if (!$u || $amount <= 0) {
            fail('مبلغ معتبر وارد کنید.');
        }
        if (input('mode') === 'sub') {
            if (!Wallet::debit((int)$u['id'], $amount, 'admin_sub', $reason)) {
                fail('موجودی کاربر کمتر از این مبلغ است.');
            }
        } else {
            Wallet::credit((int)$u['id'], $amount, 'admin_add', $reason);
            Notifier::user((int)$u['id'], 'کیف پول شما شارژ شد', money_text($amount) . ' — ' . $reason, 'dashboard/wallet', 'wallet');
        }
        flash('success', 'موجودی کیف پول بروزرسانی شد.');
        redirect('admin/users/' . $u['id']);
    }

    public function impersonate(string $id): never
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if (!$u || $u['role'] === 'admin') {
            fail('امکان ورود به این حساب وجود ندارد.');
        }
        Auth::impersonate((int)$u['id']);
        flash('info', 'شما اکنون حساب «' . user_name($u) . '» را می‌بینید.');
        redirect('dashboard');
    }
}
