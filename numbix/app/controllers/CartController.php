<?php

final class CartController
{
    public function index(): string
    {
        $u = auth();
        $sum = Cart::summary($u['id'] ?? null);
        return view('site/cart', [
            'title' => 'سبد خرید',
            'sum' => $sum,
            'u' => $u,
            'online' => Zarinpal::enabled(),
            'testGateway' => self::testGatewayAllowed($u),
            'noindex' => true,
        ], 'site');
    }

    public static function testGatewayAllowed(?array $u): bool
    {
        return $u && setting('test_gateway', '0') === '1' && ($u['role'] === 'admin' || setting('test_gateway_public', '0') === '1');
    }

    public function add(): array|string
    {
        $s = DB::row('SELECT s.* FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.is_active = 1 AND c.is_active = 1', [input_int('service_id')]);
        if (!$s) {
            fail('سرویس مورد نظر در دسترس نیست.');
        }
        $qty = input_int('qty');
        $link = trim((string)input('link', ''));
        if ($err = OrderService::validateLine($s, $qty, $link)) {
            fail($err);
        }
        if (Cart::count() >= 20) {
            fail('حداکثر ۲۰ سفارش در سبد خرید مجاز است.');
        }
        Cart::add((int)$s['id'], $qty, $link);
        $msg = '«' . $s['title'] . '» به سبد خرید اضافه شد.';
        if (is_ajax()) {
            return ['ok' => true, 'message' => $msg, 'count' => Cart::count()];
        }
        if (input('go') === 'cart') {
            redirect('cart');
        }
        flash('success', $msg);
        back('cart');
    }

    public function update(): never
    {
        $key = (string)input('key');
        $raw = Cart::raw()[$key] ?? null;
        if ($raw) {
            $s = DB::row('SELECT * FROM services WHERE id = ?', [$raw['service_id']]);
            $qty = input_int('qty');
            $link = trim((string)input('link', $raw['link']));
            if ($s && ($err = OrderService::validateLine($s, $qty, $link))) {
                fail($err, 'cart');
            }
            Cart::update($key, $qty, $link);
            flash('success', 'سبد خرید بروزرسانی شد.');
        }
        redirect('cart');
    }

    public function remove(): array|string
    {
        Cart::remove((string)input('key'));
        if (is_ajax()) {
            return ['ok' => true, 'count' => Cart::count(), 'reload' => true];
        }
        flash('info', 'آیتم از سبد خرید حذف شد.');
        redirect('cart');
    }

    public function coupon(): never
    {
        $code = trim((string)input('code', ''));
        if ($code === '' || input('remove')) {
            Cart::setCoupon(null);
            flash('info', 'کد تخفیف حذف شد.');
            redirect('cart');
        }
        $c = Coupon::find($code);
        $res = Coupon::evaluate($c, Auth::id(), Cart::lines());
        if (is_string($res)) {
            fail($res, 'cart');
        }
        Cart::setCoupon($c['code']);
        flash('success', 'کد تخفیف اعمال شد: ' . money_text($res) . ' تخفیف');
        redirect('cart');
    }

    public function checkout(): never
    {
        $u = auth();
        $sum = Cart::summary((int)$u['id']);
        if (!$sum['lines']) {
            fail('سبد خرید شما خالی است.', 'cart');
        }
        if ($sum['has_errors']) {
            fail('برخی از آیتم‌های سبد خرید نیاز به اصلاح دارند.', 'cart');
        }
        if ($sum['coupon_error']) {
            Cart::setCoupon(null);
            fail($sum['coupon_error'], 'cart');
        }
        $method = input('method', 'wallet');
        $total = $sum['total'];
        $balance = (int)$u['balance'];

        try {
            if ($method === 'wallet') {
                if ($balance < $total) {
                    fail('موجودی کیف پول کافی نیست. روش پرداخت آنلاین را انتخاب کنید.', 'cart');
                }
                $ids = OrderService::createUnpaid((int)$u['id'], array_values($sum['lines']), $sum['coupon']);
                $r = OrderService::pay($ids);
                Cart::clear();
                foreach ($r['errors'] as $e) {
                    flash('error', $e);
                }
                if ($r['paid']) {
                    flash('success', fa(count($r['paid'])) . ' سفارش با موفقیت ثبت و پرداخت شد.');
                }
                redirect(count($r['paid']) === 1 ? 'dashboard/orders/' . $r['paid'][0] : 'dashboard/orders');
            }

            if ($method !== 'online' && $method !== 'test') {
                fail('روش پرداخت نامعتبر است.', 'cart');
            }
            if ($method === 'online' && !Zarinpal::enabled()) {
                fail('درگاه پرداخت آنلاین فعال نیست.', 'cart');
            }
            if ($method === 'test' && !self::testGatewayAllowed($u)) {
                fail('درگاه آزمایشی فعال نیست.', 'cart');
            }
            $useWallet = input('use_wallet') === '1';
            $amount = $useWallet ? max(1000, $total - $balance) : max(1000, $total);
            $ids = OrderService::createUnpaid((int)$u['id'], array_values($sum['lines']), $sum['coupon']);
            $pid = Payments::create((int)$u['id'], $amount, $method === 'test' ? 'test' : 'zarinpal', 'order', ['orders' => $ids]);
            DB::query('UPDATE orders SET payment_id = ? WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')', [$pid]);
            Cart::clear();
            redirect(Payments::start($pid, $u), true);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect(isset($ids) ? 'dashboard/orders' : 'cart');
        }
    }
}
