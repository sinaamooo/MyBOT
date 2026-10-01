<?php

/**
 * Zarinpal payment gateway (REST v4). Amounts are passed in Toman and sent as Rial.
 */
final class Zarinpal
{
    private static function base(): string
    {
        return setting('zarinpal_sandbox') === '1' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    public static function enabled(): bool
    {
        return (bool)setting('zarinpal_merchant');
    }

    private static function post(string $path, array $body): array
    {
        $res = http_post(self::base() . $path, json_encode($body), ['Content-Type: application/json', 'Accept: application/json'], 20);
        $data = json_decode($res['body'], true);
        return is_array($data) ? $data : ['errors' => ['message' => 'عدم ارتباط با درگاه (' . ($res['error'] ?: 'HTTP ' . $res['code']) . ')']];
    }

    public static function request(int $amountToman, string $callback, string $description, ?string $mobile = null, ?string $email = null): array
    {
        $meta = array_filter(['mobile' => $mobile, 'email' => $email]);
        $r = self::post('/pg/v4/payment/request.json', array_filter([
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $amountToman * 10,
            'callback_url' => $callback,
            'description' => $description,
            'metadata' => $meta ?: null,
        ]));
        if ((int)($r['data']['code'] ?? 0) === 100 && !empty($r['data']['authority'])) {
            return ['ok' => true, 'authority' => $r['data']['authority'], 'url' => self::base() . '/pg/StartPay/' . $r['data']['authority']];
        }
        return ['ok' => false, 'message' => self::error($r)];
    }

    public static function verify(int $amountToman, string $authority): array
    {
        $r = self::post('/pg/v4/payment/verify.json', [
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $amountToman * 10,
            'authority' => $authority,
        ]);
        $code = (int)($r['data']['code'] ?? 0);
        if ($code === 100 || $code === 101) {
            return ['ok' => true, 'ref_id' => (string)($r['data']['ref_id'] ?? ''), 'card_pan' => (string)($r['data']['card_pan'] ?? ''), 'code' => $code];
        }
        return ['ok' => false, 'message' => self::error($r)];
    }

    private static function error(array $r): string
    {
        $code = (int)($r['errors']['code'] ?? $r['data']['code'] ?? 0);
        $map = [
            -9 => 'خطای اعتبارسنجی اطلاعات ارسالی به درگاه',
            -10 => 'آی‌پی یا مرچنت کد پذیرنده صحیح نیست',
            -11 => 'مرچنت کد فعال نیست',
            -12 => 'تلاش بیش از حد در یک بازه زمانی کوتاه',
            -50 => 'مبلغ پرداخت شده با مبلغ تراکنش مغایرت دارد',
            -51 => 'پرداخت ناموفق بود',
            -54 => 'اتوریتی نامعتبر است',
        ];
        return $map[$code] ?? (is_string($r['errors']['message'] ?? null) ? $r['errors']['message'] : 'خطا در ارتباط با درگاه پرداخت');
    }
}
