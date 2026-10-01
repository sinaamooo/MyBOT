<?php

/**
 * Reseller API — compatible with the common "SMM panel API v2" format,
 * so other panels and Telegram bots can connect to this site out of the box.
 */
final class ApiController
{
    private const STATUS = [
        'unpaid' => 'Pending', 'pending' => 'Pending', 'processing' => 'Processing', 'in_progress' => 'In progress',
        'completed' => 'Completed', 'partial' => 'Partial', 'canceled' => 'Canceled', 'refunded' => 'Canceled',
    ];

    public function handle(): array
    {
        if (setting('api_enabled', '1') !== '1') {
            return ['error' => 'API is disabled'];
        }
        $key = (string)input('key', '');
        $u = $key !== '' ? DB::row("SELECT * FROM users WHERE api_key = ? AND status = 'active'", [$key]) : null;
        if (!$u) {
            http_response_code(401);
            return ['error' => 'Invalid API key'];
        }
        $currency = setting('api_currency', 'IRT');
        return match ((string)input('action')) {
            'services' => $this->services(),
            'add' => $this->add($u),
            'status' => $this->status($u, $currency),
            'balance' => ['balance' => number_format((float)$u['balance'], 2, '.', ''), 'currency' => $currency],
            default => ['error' => 'Incorrect request'],
        };
    }

    private function services(): array
    {
        $rows = DB::all('SELECT s.*, c.name AS category FROM services s JOIN categories c ON c.id = s.category_id WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.sort, s.sort, s.id');
        return array_map(fn($s) => [
            'service' => (int)$s['id'],
            'name' => $s['title'],
            'type' => 'Default',
            'category' => $s['category'],
            'rate' => number_format((float)$s['price'], 2, '.', ''),
            'min' => (int)$s['min_qty'],
            'max' => (int)min($s['max_qty'], $s['stock'] ?? PHP_INT_MAX),
            'refill' => false,
            'cancel' => false,
        ], $rows);
    }

    private function add(array $u): array
    {
        $s = DB::row('SELECT s.* FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.is_active = 1 AND c.is_active = 1', [input_int('service')]);
        if (!$s) {
            return ['error' => 'Incorrect service ID'];
        }
        $qty = input_int('quantity');
        $link = trim((string)input('link', ''));
        if ($err = OrderService::validateLine($s, $qty, $link)) {
            return ['error' => $err];
        }
        if ((int)$u['balance'] < service_price($s, $qty)) {
            return ['error' => 'Not enough funds on balance'];
        }
        try {
            $ids = OrderService::createUnpaid((int)$u['id'], [['service' => $s, 'qty' => $qty, 'link' => $link, 'discount' => 0]], null, null, 'api');
            $r = OrderService::pay($ids);
        } catch (DomainException $e) {
            return ['error' => $e->getMessage()];
        }
        if (!$r['paid']) {
            return ['error' => $r['errors'][0] ?? 'Order failed'];
        }
        return ['order' => $r['paid'][0]];
    }

    private function status(array $u, string $currency): array
    {
        $fmt = fn($o) => [
            'charge' => number_format((float)($o['price'] - $o['discount'] - $o['refunded']), 2, '.', ''),
            'start_count' => (string)($o['start_count'] ?? 0),
            'status' => self::STATUS[$o['status']] ?? 'Pending',
            'remains' => (string)($o['remains'] ?? ($o['status'] === 'completed' ? 0 : $o['quantity'])),
            'currency' => $currency,
        ];
        if ($single = input('order')) {
            $o = DB::row('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)$single, $u['id']]);
            return $o ? $fmt($o) : ['error' => 'Incorrect order ID'];
        }
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string)input('orders', '')))), 0, 100);
        if (!$ids) {
            return ['error' => 'Incorrect request'];
        }
        $rows = [];
        foreach (DB::all('SELECT * FROM orders WHERE user_id = ? AND id IN (' . implode(',', $ids) . ')', [$u['id']]) as $o) {
            $rows[(int)$o['id']] = $o;
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = isset($rows[$id]) ? $fmt($rows[$id]) : ['error' => 'Incorrect order ID'];
        }
        return $out;
    }

    public function cron(string $key): never
    {
        if (!hash_equals((string)config('cron_key', ''), $key)) {
            abort(403);
        }
        @set_time_limit(300);
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", Automation::run()), "\n";
        exit;
    }
}
