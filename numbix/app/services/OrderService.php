<?php

/**
 * Order lifecycle:
 *   unpaid -> pending -> (provider) processing -> in_progress -> completed
 *                                              \-> partial / canceled (auto refund to wallet)
 */
final class OrderService
{
    public const OPEN = ['pending', 'processing', 'in_progress'];
    public const PROVIDER_MAP = [
        'pending' => 'processing',
        'processing' => 'processing',
        'in progress' => 'in_progress',
        'inprogress' => 'in_progress',
        'completed' => 'completed',
        'complete' => 'completed',
        'partial' => 'partial',
        'canceled' => 'canceled',
        'cancelled' => 'canceled',
        'refunded' => 'canceled',
        'fail' => 'canceled',
    ];

    public static function validateLine(array $s, int $qty, string $link): ?string
    {
        if ($qty < (int)$s['min_qty']) {
            return 'حداقل تعداد سفارش ' . num($s['min_qty']) . ' عدد است.';
        }
        if ($qty > (int)$s['max_qty']) {
            return 'حداکثر تعداد سفارش ' . num($s['max_qty']) . ' عدد است.';
        }
        if ($s['stock'] !== null && (int)$s['stock'] < $qty) {
            return (int)$s['stock'] < (int)$s['min_qty'] ? 'این سرویس فعلاً ناموجود است.' : 'موجودی فعلی این سرویس ' . num($s['stock']) . ' عدد است.';
        }
        $link = trim($link);
        if ($link === '') {
            return 'لینک یا آیدی مقصد را وارد کنید.';
        }
        if (mb_strlen($link) > 500) {
            return 'لینک وارد شده بیش از حد طولانی است.';
        }
        return null;
    }

    /**
     * Create orders in "unpaid" state (no stock / money touched yet).
     * @param array $lines each: ['service' => row, 'qty' => int, 'link' => string, 'discount' => int]
     * @return int[] order ids
     */
    public static function createUnpaid(int $userId, array $lines, ?array $coupon = null, ?int $paymentId = null, string $source = 'web'): array
    {
        if (!$lines) {
            throw new DomainException('سبد خرید خالی است.');
        }
        return DB::transaction(static function () use ($userId, $lines, $coupon, $paymentId, $source) {
            $ids = [];
            foreach ($lines as $l) {
                $s = $l['service'];
                if ($err = self::validateLine($s, (int)$l['qty'], (string)$l['link'])) {
                    throw new DomainException('«' . $s['title'] . '»: ' . $err);
                }
                $price = service_price($s, (int)$l['qty']);
                $ids[] = DB::insert('orders', [
                    'user_id' => $userId,
                    'service_id' => (int)$s['id'],
                    'payment_id' => $paymentId,
                    'link' => trim((string)$l['link']),
                    'quantity' => (int)$l['qty'],
                    'price' => $price,
                    'discount' => min($price, (int)($l['discount'] ?? 0)),
                    'cost' => (int)ceil((int)$s['cost'] * (int)$l['qty'] / 1000),
                    'status' => 'unpaid',
                    'coupon_id' => $coupon['id'] ?? null,
                    'provider_id' => $s['provider_id'] ?: null,
                    'source' => $source,
                ]);
            }
            return $ids;
        });
    }

    public static function amountDue(array $orderIds): int
    {
        if (!$orderIds) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        return (int)DB::value("SELECT COALESCE(SUM(price - discount), 0) FROM orders WHERE status = 'unpaid' AND id IN ($in)", array_values($orderIds));
    }

    /**
     * Pay unpaid orders from the owner's wallet. Each order is paid atomically; stock is reserved here.
     * @param bool $charge false = admin gift (no wallet debit)
     * @return array{paid:int[], errors:string[]}
     */
    public static function pay(array $orderIds, bool $charge = true): array
    {
        $paid = [];
        $errors = [];
        $couponsDone = [];
        foreach ($orderIds as $id) {
            try {
                $o = DB::transaction(static function () use ($id, $charge) {
                    $o = DB::row("SELECT * FROM orders WHERE id = ? AND status = 'unpaid' FOR UPDATE", [(int)$id]);
                    if (!$o) {
                        return null;
                    }
                    $s = DB::row('SELECT * FROM services WHERE id = ? FOR UPDATE', [$o['service_id']]);
                    if (!$s || !$s['is_active']) {
                        DB::update('orders', ['status' => 'canceled', 'admin_note' => 'سرویس غیرفعال شد'], 'id = ?', [$o['id']]);
                        return ['error' => 'سفارش #' . fa($o['id']) . ': این سرویس دیگر در دسترس نیست و سفارش لغو شد.'];
                    }
                    if ($s['stock'] !== null && (int)$s['stock'] < (int)$o['quantity']) {
                        DB::update('orders', ['status' => 'canceled', 'admin_note' => 'عدم موجودی هنگام پرداخت'], 'id = ?', [$o['id']]);
                        return ['error' => 'سفارش #' . fa($o['id']) . ': موجودی «' . $s['title'] . '» کافی نبود و سفارش لغو شد.'];
                    }
                    $due = (int)$o['price'] - (int)$o['discount'];
                    if ($charge && !Wallet::debit((int)$o['user_id'], $due, 'order', 'پرداخت سفارش #' . $o['id'] . ' — ' . $s['title'], 'order', (int)$o['id'])) {
                        throw new DomainException('موجودی کیف پول برای پرداخت سفارش #' . fa($o['id']) . ' کافی نیست.');
                    }
                    if ($s['stock'] !== null) {
                        DB::query('UPDATE services SET stock = stock - ? WHERE id = ?', [(int)$o['quantity'], $s['id']]);
                    }
                    DB::query('UPDATE services SET sales_count = sales_count + 1 WHERE id = ?', [$s['id']]);
                    DB::update('orders', ['status' => 'pending', 'provider_id' => $s['provider_id'] ?: null], 'id = ?', [$o['id']]);
                    $o['service_title'] = $s['title'];
                    return $o;
                });
            } catch (DomainException $e) {
                $errors[] = $e->getMessage();
                continue;
            }
            if (!$o) {
                continue;
            }
            if (isset($o['error'])) {
                $errors[] = $o['error'];
                continue;
            }
            $paid[] = (int)$o['id'];
            if ($o['coupon_id'] && !isset($couponsDone[$o['coupon_id'] . '-' . $o['payment_id']])) {
                $couponsDone[$o['coupon_id'] . '-' . $o['payment_id']] = true;
                if ($c = DB::row('SELECT * FROM coupons WHERE id = ?', [$o['coupon_id']])) {
                    Coupon::markUsed($c, (int)$o['user_id'], (int)$o['discount']);
                }
            }
            self::dispatch((int)$o['id']);
        }
        if ($paid) {
            $total = self::sumPaid($paid);
            Notifier::admin(
                count($paid) > 1 ? count($paid) . ' سفارش جدید ثبت شد' : 'سفارش جدید #' . $paid[0],
                'مبلغ: ' . money_text($total),
                'admin/orders' . (count($paid) === 1 ? '/' . $paid[0] : ''),
                'cart'
            );
            Automation::checkLowStock();
        }
        return ['paid' => $paid, 'errors' => $errors];
    }

    private static function sumPaid(array $ids): int
    {
        $in = implode(',', array_fill(0, count($ids), '?'));
        return (int)DB::value("SELECT COALESCE(SUM(price - discount), 0) FROM orders WHERE id IN ($in)", $ids);
    }

    /** Send an order to its API provider (if the service is connected to one). */
    public static function dispatch(int $orderId): bool
    {
        $o = DB::row('SELECT o.*, s.provider_service_id, s.title AS service_title FROM orders o JOIN services s ON s.id = o.service_id WHERE o.id = ?', [$orderId]);
        if (!$o || $o['status'] !== 'pending' || !$o['provider_id'] || $o['provider_order_id'] || !$o['provider_service_id']) {
            return false;
        }
        $p = DB::row('SELECT * FROM providers WHERE id = ? AND is_active = 1', [$o['provider_id']]);
        if (!$p) {
            return false;
        }
        $attempts = (int)$o['provider_attempts'] + 1;
        try {
            $pid = (new Provider($p))->add((string)$o['provider_service_id'], $o['link'], (int)$o['quantity']);
            DB::update('orders', [
                'provider_order_id' => $pid,
                'status' => 'processing',
                'provider_error' => null,
                'provider_attempts' => $attempts,
            ], 'id = ?', [$orderId]);
            return true;
        } catch (Throwable $e) {
            DB::update('orders', ['provider_attempts' => $attempts, 'provider_error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$orderId]);
            if ($attempts >= 3) {
                Notifier::admin('ارسال سفارش #' . $orderId . ' به API ناموفق بود', $e->getMessage(), 'admin/orders/' . $orderId, 'alert');
            }
            return false;
        }
    }

    /**
     * Change status with automatic refund + restock bookkeeping.
     * $data may contain: start_count, remains, admin_note
     */
    public static function setStatus(int $orderId, string $status, array $data = [], bool $notify = true): void
    {
        if (!isset(order_statuses()[$status])) {
            throw new DomainException('وضعیت نامعتبر است.');
        }
        $result = DB::transaction(static function () use ($orderId, $status, $data) {
            $o = DB::row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o) {
                throw new DomainException('سفارش یافت نشد.');
            }
            $old = $o['status'];
            $wasPaid = $old !== 'unpaid';
            $paidAmount = (int)$o['price'] - (int)$o['discount'];
            $remains = array_key_exists('remains', $data) && $data['remains'] !== null && $data['remains'] !== '' ? max(0, min((int)$o['quantity'], (int)$data['remains'])) : ($o['remains'] !== null ? (int)$o['remains'] : null);

            $target = 0;
            if ($wasPaid) {
                if (in_array($status, ['canceled', 'refunded'], true)) {
                    $target = $paidAmount;
                } elseif ($status === 'partial' && $remains) {
                    $target = (int)floor($paidAmount * $remains / max(1, (int)$o['quantity']));
                } else {
                    $target = (int)$o['refunded'];
                }
            }
            $refund = max(0, $target - (int)$o['refunded']);

            // Put undelivered units back in stock (only once, when leaving an open state).
            $restock = 0;
            if ($wasPaid && !in_array($old, ['canceled', 'refunded', 'partial'], true)) {
                if (in_array($status, ['canceled', 'refunded'], true)) {
                    $restock = (int)$o['quantity'];
                } elseif ($status === 'partial') {
                    $restock = (int)$remains;
                }
            }
            if ($restock > 0) {
                DB::query('UPDATE services SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$restock, $o['service_id']]);
            }

            $upd = ['status' => $status, 'refunded' => (int)$o['refunded'] + $refund];
            if ($status === 'completed' && !$o['completed_at']) {
                $upd['completed_at'] = date('Y-m-d H:i:s');
                $upd['remains'] = 0;
            }
            if (array_key_exists('start_count', $data) && $data['start_count'] !== '' && $data['start_count'] !== null) {
                $upd['start_count'] = (int)$data['start_count'];
            }
            if ($remains !== null && $status !== 'completed') {
                $upd['remains'] = $remains;
            }
            if (isset($data['admin_note'])) {
                $upd['admin_note'] = mb_substr((string)$data['admin_note'], 0, 255) ?: null;
            }
            DB::update('orders', $upd, 'id = ?', [$orderId]);
            if ($refund > 0) {
                Wallet::credit((int)$o['user_id'], $refund, 'refund', 'بازگشت وجه سفارش #' . $orderId, 'order', $orderId);
            }
            return ['old' => $old, 'refund' => $refund, 'user_id' => (int)$o['user_id']];
        });

        if ($notify && $result['old'] !== $status) {
            [$label] = order_statuses()[$status];
            $body = 'وضعیت جدید: ' . $label . ($result['refund'] > 0 ? ' — ' . money_text($result['refund']) . ' به کیف پول شما برگشت داده شد.' : '');
            Notifier::user($result['user_id'], 'سفارش #' . fa($orderId) . ' بروزرسانی شد', $body, 'dashboard/orders/' . $orderId, order_statuses()[$status][2]);
        }
    }

    /** Apply a status payload returned by a provider API. */
    public static function applyProviderStatus(array $o, array $st): void
    {
        $raw = strtolower(trim((string)($st['status'] ?? '')));
        $new = self::PROVIDER_MAP[$raw] ?? null;
        $data = [
            'start_count' => isset($st['start_count']) && is_numeric($st['start_count']) ? (int)$st['start_count'] : null,
            'remains' => isset($st['remains']) && is_numeric($st['remains']) ? (int)$st['remains'] : null,
        ];
        if ($new && $new !== $o['status']) {
            self::setStatus((int)$o['id'], $new, $data);
        } else {
            $upd = array_filter($data, fn($v) => $v !== null);
            if ($upd) {
                DB::update('orders', $upd, 'id = ?', [$o['id']]);
            }
        }
    }
}
