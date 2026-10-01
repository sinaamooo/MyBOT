<?php

/**
 * Session cart. Each line = one future order (service + quantity + target link).
 */
final class Cart
{
    public static function raw(): array
    {
        return $_SESSION['cart']['items'] ?? [];
    }

    public static function count(): int
    {
        return count(self::raw());
    }

    public static function add(int $serviceId, int $qty, string $link): string
    {
        $key = bin2hex(random_bytes(5));
        $_SESSION['cart']['items'][$key] = ['service_id' => $serviceId, 'qty' => $qty, 'link' => $link];
        return $key;
    }

    public static function update(string $key, int $qty, string $link): void
    {
        if (isset($_SESSION['cart']['items'][$key])) {
            $_SESSION['cart']['items'][$key]['qty'] = $qty;
            $_SESSION['cart']['items'][$key]['link'] = $link;
        }
    }

    public static function remove(string $key): void
    {
        unset($_SESSION['cart']['items'][$key]);
    }

    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    public static function coupon(): ?string
    {
        return $_SESSION['cart']['coupon'] ?? null;
    }

    public static function setCoupon(?string $code): void
    {
        $_SESSION['cart']['coupon'] = $code;
    }

    /** Lines hydrated with fresh service rows and prices. */
    public static function lines(): array
    {
        $raw = self::raw();
        if (!$raw) {
            return [];
        }
        $ids = array_unique(array_map(fn($r) => (int)$r['service_id'], $raw));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $services = [];
        foreach (DB::all("SELECT s.*, c.name AS category_name, c.icon, c.color, c.color2, c.is_active AS cat_active FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id IN ($in)", array_values($ids)) as $s) {
            $services[(int)$s['id']] = $s;
        }
        $lines = [];
        foreach ($raw as $key => $r) {
            $s = $services[(int)$r['service_id']] ?? null;
            if (!$s || !$s['is_active'] || !$s['cat_active']) {
                self::remove($key);
                continue;
            }
            $lines[$key] = [
                'key' => $key,
                'service' => $s,
                'qty' => (int)$r['qty'],
                'link' => $r['link'],
                'amount' => service_price($s, (int)$r['qty']),
                'discount' => 0,
                'error' => OrderService::validateLine($s, (int)$r['qty'], $r['link']),
            ];
        }
        return $lines;
    }

    public static function summary(?int $userId): array
    {
        $lines = self::lines();
        $subtotal = array_sum(array_column($lines, 'amount'));
        $discount = 0;
        $coupon = null;
        $couponError = null;
        if ($code = self::coupon()) {
            $coupon = Coupon::find($code);
            $res = Coupon::evaluate($coupon, $userId, $lines);
            if (is_string($res)) {
                $couponError = $res;
                $coupon = null;
            } else {
                $discount = $res;
                Coupon::distribute($coupon, $lines, $discount);
            }
        }
        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
            'coupon' => $coupon,
            'coupon_code' => $code,
            'coupon_error' => $couponError,
            'has_errors' => (bool)array_filter(array_column($lines, 'error')),
        ];
    }
}
