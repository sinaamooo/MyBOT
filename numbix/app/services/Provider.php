<?php

/**
 * Client for the de-facto standard "SMM panel API v2"
 * (POST key + action = services | add | status | balance).
 */
final class Provider
{
    public function __construct(private array $p)
    {
    }

    public function call(array $params, int $timeout = 30): array
    {
        $res = http_post($this->p['api_url'], ['key' => $this->p['api_key']] + $params, [], $timeout);
        if (!$res['ok'] && $res['body'] === '') {
            throw new RuntimeException('عدم اتصال به API: ' . ($res['error'] ?: 'HTTP ' . $res['code']));
        }
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException('پاسخ نامعتبر از API (HTTP ' . $res['code'] . ')');
        }
        if (isset($data['error']) && !isset($data['order'])) {
            throw new RuntimeException('API: ' . (is_string($data['error']) ? $data['error'] : json_encode($data['error'], JSON_UNESCAPED_UNICODE)));
        }
        return $data;
    }

    public function services(): array
    {
        $list = $this->call(['action' => 'services'], 60);
        return array_values(array_filter($list, 'is_array'));
    }

    public function add(string $serviceId, string $link, int $quantity): string
    {
        $r = $this->call(['action' => 'add', 'service' => $serviceId, 'link' => $link, 'quantity' => $quantity]);
        if (empty($r['order'])) {
            throw new RuntimeException('API شماره سفارش برنگرداند.');
        }
        return (string)$r['order'];
    }

    /** @return array<string, array> keyed by provider order id */
    public function statuses(array $ids): array
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        if (count($ids) === 1) {
            return [$ids[0] => $this->call(['action' => 'status', 'order' => $ids[0]])];
        }
        $r = $this->call(['action' => 'status', 'orders' => implode(',', $ids)]);
        return array_filter($r, 'is_array');
    }

    public function balance(): array
    {
        $r = $this->call(['action' => 'balance'], 15);
        return ['balance' => (float)($r['balance'] ?? 0), 'currency' => (string)($r['currency'] ?? '')];
    }
}
