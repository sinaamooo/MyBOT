<?php

final class AdminFinanceController
{
    private const P = ['area' => 'admin'];

    public function payments(): string
    {
        $where = '1=1';
        $params = [];
        $status = input('status', '');
        if (isset(payment_statuses()[$status])) {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        if (array_key_exists(input('gateway'), Payments::GATEWAYS)) {
            $where .= ' AND p.gateway = ?';
            $params[] = input('gateway');
        }
        if ($q = trim((string)input('q', ''))) {
            $where .= ' AND (p.id = ? OR p.ref_id = ? OR p.tracking_code = ? OR u.email LIKE ?)';
            array_push($params, (int)en_digits($q), $q, en_digits($q), "%$q%");
        }
        $page = paginate('p.*, u.first_name, u.last_name, u.email, u.avatar, u.id AS uid', "FROM payments p JOIN users u ON u.id = p.user_id WHERE $where", $params, 20, 'ORDER BY p.id DESC');
        $k = DB::row("SELECT
            COALESCE(SUM(CASE WHEN status = 'paid' AND DATE(paid_at) = CURDATE() THEN amount END),0) today,
            COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= " . jmonth_sql(0) . " THEN amount END),0) month_,
            SUM(status = 'review') review,
            SUM(status = 'paid') paid_count, COUNT(*) total FROM payments");
        return view('admin/payments', self::P + ['title' => 'پرداخت‌ها', 'page' => $page, 'k' => $k, 'status' => $status], 'panel');
    }

    public function approve(string $id): never
    {
        $p = DB::row('SELECT * FROM payments WHERE id = ?', [(int)$id]);
        if (!$p || !in_array($p['status'], ['review', 'pending'], true)) {
            fail('این پرداخت قابل تایید نیست.');
        }
        if (($amount = input_int('amount')) > 0 && $amount !== (int)$p['amount']) {
            DB::update('payments', ['amount' => $amount], 'id = ?', [$p['id']]);
        }
        DB::update('payments', ['admin_note' => 'تایید توسط ' . user_name(auth())], 'id = ?', [$p['id']]);
        Payments::complete((int)$p['id'], $p['tracking_code'] ?: null);
        flash('success', 'پرداخت تایید و کیف پول کاربر شارژ شد.');
        back('admin/payments');
    }

    public function reject(string $id): never
    {
        $p = DB::row('SELECT * FROM payments WHERE id = ?', [(int)$id]);
        if (!$p || !in_array($p['status'], ['review', 'pending'], true)) {
            fail('این پرداخت قابل رد نیست.');
        }
        $note = mb_substr(trim((string)input('note', '')), 0, 250) ?: 'رد توسط مدیریت';
        DB::update('payments', ['status' => 'rejected', 'admin_note' => $note], 'id = ?', [$p['id']]);
        Notifier::user((int)$p['user_id'], 'فیش واریزی شما تایید نشد', $note, 'dashboard/wallet', 'x-circle');
        flash('info', 'پرداخت رد شد.');
        back('admin/payments');
    }

    public function transactions(): string
    {
        $where = '1=1';
        $params = [];
        if (array_key_exists(input('type'), Wallet::TYPES)) {
            $where .= ' AND t.type = ?';
            $params[] = input('type');
        }
        if ($uid = input_int('user')) {
            $where .= ' AND t.user_id = ?';
            $params[] = $uid;
        }
        $page = paginate('t.*, u.first_name, u.last_name, u.email, u.avatar, u.id AS uid', "FROM transactions t JOIN users u ON u.id = t.user_id WHERE $where", $params, 25, 'ORDER BY t.id DESC');
        return view('admin/transactions', self::P + ['title' => 'تراکنش‌های کیف پول', 'page' => $page], 'panel');
    }

    public function coupons(): string
    {
        $rows = DB::all('SELECT c.*, cat.name AS category_name, (SELECT COALESCE(SUM(amount),0) FROM coupon_uses u WHERE u.coupon_id = c.id) AS total_discount FROM coupons c LEFT JOIN categories cat ON cat.id = c.category_id ORDER BY c.id DESC');
        return view('admin/coupons', self::P + [
            'title' => 'کدهای تخفیف',
            'rows' => $rows,
            'edit' => input_int('edit'),
            'categories' => DB::all('SELECT id, name FROM categories ORDER BY sort, id'),
        ], 'panel');
    }

    public function couponSave(): never
    {
        $id = input_int('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', en_digits((string)input('code', ''))));
        if ($code === '') {
            $code = random_code(8);
        }
        if (DB::value('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $id])) {
            fail('این کد قبلاً ساخته شده است.');
        }
        $type = input('type') === 'fixed' ? 'fixed' : 'percent';
        $value = input_int('value');
        if ($value <= 0 || ($type === 'percent' && $value > 100)) {
            fail('مقدار تخفیف نامعتبر است.');
        }
        $data = [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'max_discount' => input_int('max_discount') ?: null,
            'min_amount' => input_int('min_amount'),
            'max_uses' => input_int('max_uses'),
            'per_user' => input_int('per_user', 1),
            'category_id' => input_int('category_id') ?: null,
            'starts_at' => ($d = jalali_to_date(input('starts_at'))) ? $d . ' 00:00:00' : null,
            'expires_at' => ($d = jalali_to_date(input('expires_at'))) ? $d . ' 23:59:59' : null,
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];
        $id ? DB::update('coupons', $data, 'id = ?', [$id]) : DB::insert('coupons', $data);
        flash('success', 'کد تخفیف ' . $code . ' ذخیره شد.');
        redirect('admin/coupons');
    }

    public function couponDelete(string $id): never
    {
        DB::delete('coupons', 'id = ?', [(int)$id]);
        flash('success', 'کد تخفیف حذف شد.');
        redirect('admin/coupons');
    }
}
