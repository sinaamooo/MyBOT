<?php

final class Payments
{
    public const GATEWAYS = [
        'zarinpal' => 'درگاه زرین‌پال',
        'card' => 'کارت به کارت',
        'test' => 'درگاه آزمایشی',
        'admin' => 'ثبت توسط مدیریت',
    ];

    public static function create(int $userId, int $amount, string $gateway, string $purpose = 'wallet', array $meta = []): int
    {
        return DB::insert('payments', [
            'user_id' => $userId,
            'amount' => $amount,
            'gateway' => $gateway,
            'purpose' => $purpose,
            'status' => 'pending',
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public static function meta(array $p): array
    {
        return $p['meta'] ? (json_decode($p['meta'], true) ?: []) : [];
    }

    /** Returns the URL the user should be sent to, or throws DomainException. */
    public static function start(int $paymentId, array $user): string
    {
        $p = DB::row('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if ($p['gateway'] === 'test') {
            return url('payment/test/' . $p['id']);
        }
        if (!Zarinpal::enabled()) {
            throw new DomainException('درگاه پرداخت آنلاین هنوز پیکربندی نشده است.');
        }
        $r = Zarinpal::request(
            (int)$p['amount'],
            abs_url('payment/callback/' . $p['id']),
            ($p['purpose'] === 'order' ? 'پرداخت سفارش' : 'شارژ کیف پول') . ' — ' . site_name(),
            $user['mobile'] ?? null,
            $user['email'] ?? null
        );
        if (!$r['ok']) {
            DB::update('payments', ['status' => 'failed', 'admin_note' => mb_substr($r['message'], 0, 250)], 'id = ?', [$p['id']]);
            throw new DomainException($r['message']);
        }
        DB::update('payments', ['authority' => $r['authority']], 'id = ?', [$p['id']]);
        return $r['url'];
    }

    /**
     * Mark a payment as paid, credit the wallet and — when it was a checkout — pay the linked orders.
     * Idempotent: a payment is only ever completed once.
     */
    public static function complete(int $paymentId, ?string $refId = null, ?string $cardPan = null): array
    {
        $done = DB::transaction(static function () use ($paymentId, $refId, $cardPan) {
            $p = DB::row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || !in_array($p['status'], ['pending', 'review'], true)) {
                return null;
            }
            DB::update('payments', [
                'status' => 'paid',
                'ref_id' => $refId,
                'card_pan' => $cardPan ?: $p['card_pan'],
                'paid_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$paymentId]);
            Wallet::credit((int)$p['user_id'], (int)$p['amount'], 'deposit', 'شارژ کیف پول — ' . (self::GATEWAYS[$p['gateway']] ?? $p['gateway']) . ($refId ? ' (کد پیگیری ' . $refId . ')' : ''), 'payment', $paymentId);
            return $p;
        });
        if (!$done) {
            return ['ok' => false, 'already' => true, 'paid' => [], 'errors' => []];
        }
        Notifier::user((int)$done['user_id'], 'پرداخت موفق', money_text($done['amount']) . ' به کیف پول شما اضافه شد.', 'dashboard/wallet', 'wallet');
        Notifier::admin('پرداخت جدید #' . $paymentId, money_text($done['amount']) . ' — ' . (self::GATEWAYS[$done['gateway']] ?? ''), 'admin/payments', 'wallet');

        $result = ['ok' => true, 'paid' => [], 'errors' => [], 'payment' => $done];
        $orders = self::meta($done)['orders'] ?? [];
        if ($orders) {
            $r = OrderService::pay(array_map('intval', $orders));
            $result['paid'] = $r['paid'];
            $result['errors'] = $r['errors'];
        }
        return $result;
    }

    public static function fail(int $paymentId, string $note = ''): void
    {
        DB::update('payments', ['status' => 'failed', 'admin_note' => mb_substr($note, 0, 250) ?: null], "id = ? AND status = 'pending'", [$paymentId]);
    }
}
