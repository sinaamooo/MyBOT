<?php

final class Coupon
{
    public static function find(?string $code): ?array
    {
        $code = strtoupper(trim(en_digits((string)$code)));
        return $code === '' ? null : DB::row('SELECT * FROM coupons WHERE code = ?', [$code]);
    }

    /**
     * Validate a coupon for a set of cart lines ([service, qty, link, amount]).
     * Returns the discount amount, or an error string.
     */
    public static function evaluate(?array $c, ?int $userId, array $lines): int|string
    {
        if (!$c || !$c['is_active']) {
            return 'کد تخفیف معتبر نیست.';
        }
        $now = time();
        if ($c['starts_at'] && strtotime($c['starts_at']) > $now) {
            return 'زمان استفاده از این کد تخفیف هنوز نرسیده است.';
        }
        if ($c['expires_at'] && strtotime($c['expires_at']) < $now) {
            return 'مهلت استفاده از این کد تخفیف به پایان رسیده است.';
        }
        if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return 'ظرفیت استفاده از این کد تخفیف تکمیل شده است.';
        }
        if ($userId && (int)$c['per_user'] > 0) {
            $used = (int)DB::value('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND user_id = ?', [$c['id'], $userId]);
            if ($used >= (int)$c['per_user']) {
                return 'شما قبلاً از این کد تخفیف استفاده کرده‌اید.';
            }
        }
        $eligible = self::eligibleAmount($c, $lines);
        if ($eligible <= 0) {
            return 'این کد تخفیف برای سرویس‌های سبد شما قابل استفاده نیست.';
        }
        if ($eligible < (int)$c['min_amount']) {
            return 'حداقل مبلغ خرید برای این کد ' . money_text($c['min_amount']) . ' است.';
        }
        return self::discount($c, $eligible);
    }

    public static function eligibleAmount(array $c, array $lines): int
    {
        $sum = 0;
        foreach ($lines as $l) {
            if (!$c['category_id'] || (int)$l['service']['category_id'] === (int)$c['category_id']) {
                $sum += $l['amount'];
            }
        }
        return $sum;
    }

    public static function discount(array $c, int $amount): int
    {
        if ($c['type'] === 'percent') {
            $d = (int)floor($amount * min(100, (int)$c['value']) / 100);
            if ($c['max_discount']) {
                $d = min($d, (int)$c['max_discount']);
            }
        } else {
            $d = (int)$c['value'];
        }
        return max(0, min($d, $amount));
    }

    /** Spread a total discount over eligible lines proportionally. Mutates $lines['discount']. */
    public static function distribute(array $c, array &$lines, int $discount): void
    {
        foreach ($lines as &$l) {
            $l['discount'] = 0;
        }
        unset($l);
        $eligible = self::eligibleAmount($c, $lines);
        if ($discount <= 0 || $eligible <= 0) {
            return;
        }
        $left = $discount;
        $idx = [];
        foreach ($lines as $k => $l) {
            if (!$c['category_id'] || (int)$l['service']['category_id'] === (int)$c['category_id']) {
                $idx[] = $k;
            }
        }
        foreach ($idx as $n => $k) {
            $share = $n === count($idx) - 1 ? $left : (int)floor($discount * $lines[$k]['amount'] / $eligible);
            $share = min($share, $lines[$k]['amount']);
            $lines[$k]['discount'] = $share;
            $left -= $share;
        }
    }

    public static function markUsed(array $c, int $userId, int $amount): void
    {
        DB::query('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$c['id']]);
        DB::insert('coupon_uses', ['coupon_id' => $c['id'], 'user_id' => $userId, 'amount' => $amount]);
    }
}
