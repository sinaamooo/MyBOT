<?php

final class PaymentController
{
    public function charge(): never
    {
        $u = auth();
        $amount = input_int('amount');
        $min = (int)setting('min_deposit', 10000);
        $max = (int)setting('max_deposit', 100000000);
        if ($amount < $min || $amount > $max) {
            fail('مبلغ شارژ باید بین ' . money_text($min) . ' و ' . money_text($max) . ' باشد.', 'dashboard/wallet');
        }
        $method = input('method', 'online');
        if ($method === 'test' && !CartController::testGatewayAllowed($u)) {
            fail('درگاه آزمایشی فعال نیست.', 'dashboard/wallet');
        }
        if ($method === 'online' && !Zarinpal::enabled()) {
            fail('درگاه پرداخت آنلاین هنوز فعال نشده است. از روش کارت به کارت استفاده کنید.', 'dashboard/wallet');
        }
        $meta = [];
        if ($orderId = input_int('order')) {
            $meta['orders'] = DB::column("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'unpaid'", [$orderId, $u['id']]);
        }
        $pid = Payments::create((int)$u['id'], $amount, $method === 'test' ? 'test' : 'zarinpal', $meta ? 'order' : 'wallet', $meta);
        try {
            redirect(Payments::start($pid, $u), true);
        } catch (DomainException $e) {
            fail($e->getMessage(), 'dashboard/wallet');
        }
    }

    public function card(): never
    {
        $u = auth();
        if (!setting('card_number')) {
            fail('پرداخت کارت به کارت فعال نیست.', 'dashboard/wallet');
        }
        $amount = input_int('amount');
        $track = mb_substr(trim(en_digits((string)input('tracking_code'))), 0, 60);
        if ($amount < (int)setting('min_deposit', 10000)) {
            fail('مبلغ واریزی کمتر از حداقل مجاز است.', 'dashboard/wallet');
        }
        if ($track === '') {
            fail('شماره پیگیری / مرجع واریز را وارد کنید.', 'dashboard/wallet');
        }
        $pending = (int)DB::value("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'review'", [$u['id']]);
        if ($pending >= 3) {
            fail('شما ۳ فیش در انتظار بررسی دارید. لطفاً تا بررسی آن‌ها صبر کنید.', 'dashboard/wallet');
        }
        $receipt = null;
        if (Uploader::has('receipt')) {
            try {
                $receipt = Uploader::image('receipt', 'receipts', 4096);
            } catch (RuntimeException $e) {
                fail($e->getMessage(), 'dashboard/wallet');
            }
        }
        $id = DB::insert('payments', [
            'user_id' => $u['id'],
            'amount' => $amount,
            'gateway' => 'card',
            'purpose' => 'wallet',
            'status' => 'review',
            'tracking_code' => $track,
            'card_pan' => mb_substr(en_digits((string)input('card_pan')), 0, 30) ?: null,
            'receipt' => $receipt,
        ]);
        Notifier::admin('فیش واریزی جدید #' . $id, money_text($amount) . ' — ' . user_name($u), 'admin/payments?status=review', 'receipt');
        flash('success', 'فیش واریزی شما ثبت شد و پس از بررسی، کیف پولتان شارژ می‌شود.');
        redirect('dashboard/wallet');
    }

    public function callback(string $id): never
    {
        $p = DB::row('SELECT * FROM payments WHERE id = ?', [(int)$id]);
        if (!$p || $p['gateway'] !== 'zarinpal') {
            abort(404);
        }
        $back = $p['purpose'] === 'order' ? 'dashboard/orders' : 'dashboard/wallet';
        if ($p['status'] === 'paid') {
            flash('info', 'این پرداخت قبلاً تایید شده است.');
            redirect($back);
        }
        $authority = (string)input('Authority', '');
        if ($p['status'] !== 'pending' || $authority === '' || !hash_equals((string)$p['authority'], $authority)) {
            flash('error', 'اطلاعات بازگشتی از درگاه نامعتبر است.');
            redirect($back);
        }
        if (input('Status') !== 'OK') {
            Payments::fail((int)$p['id'], 'لغو توسط کاربر');
            flash('error', 'پرداخت لغو شد یا ناموفق بود.' . ($p['purpose'] === 'order' ? ' سفارش‌ها در لیست «در انتظار پرداخت» باقی ماندند.' : ''));
            redirect($back);
        }
        $v = Zarinpal::verify((int)$p['amount'], $authority);
        if (!$v['ok']) {
            Payments::fail((int)$p['id'], $v['message']);
            flash('error', 'تایید پرداخت ناموفق بود: ' . $v['message']);
            redirect($back);
        }
        $this->finish($p, Payments::complete((int)$p['id'], $v['ref_id'], $v['card_pan']), $v['ref_id']);
    }

    private function finish(array $p, array $r, string $ref): never
    {
        flash('success', 'پرداخت با موفقیت انجام شد. کد پیگیری: ' . fa($ref));
        foreach ($r['errors'] ?? [] as $e) {
            flash('error', $e);
        }
        if (!empty($r['paid'])) {
            flash('success', fa(count($r['paid'])) . ' سفارش ثبت و برای انجام ارسال شد.');
            redirect(count($r['paid']) === 1 ? 'dashboard/orders/' . $r['paid'][0] : 'dashboard/orders');
        }
        redirect($p['purpose'] === 'order' ? 'dashboard/orders' : 'dashboard/wallet');
    }

    public function testGateway(string $id): string
    {
        $p = $this->testPayment($id);
        return view('site/test_gateway', ['title' => 'درگاه آزمایشی', 'p' => $p], 'blank');
    }

    public function testGatewaySubmit(string $id): never
    {
        $p = $this->testPayment($id);
        if (input('result') !== 'ok') {
            Payments::fail((int)$p['id'], 'لغو در درگاه آزمایشی');
            flash('error', 'پرداخت آزمایشی لغو شد.');
            redirect($p['purpose'] === 'order' ? 'dashboard/orders' : 'dashboard/wallet');
        }
        $ref = 'TEST-' . random_code(8, '0123456789');
        $this->finish($p, Payments::complete((int)$p['id'], $ref, '6037********1234'), $ref);
    }

    private function testPayment(string $id): array
    {
        $p = DB::row("SELECT * FROM payments WHERE id = ? AND user_id = ? AND gateway = 'test' AND status = 'pending'", [(int)$id, Auth::id()]);
        if (!$p || !CartController::testGatewayAllowed(auth())) {
            abort(404, 'تراکنش پیدا نشد یا قبلاً پردازش شده است.');
        }
        return $p;
    }
}
