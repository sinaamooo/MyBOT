<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Coins;

/**
 * لوگوی ارزها؛ اگر فایل لوگو نبود، دایره‌ای با نماد ارز رسم می‌شود.
 */
final class CoinLogo
{
    /** @var array<string,bool> */
    private static array $preferWhite = [];

    public static function path(string $symbol, bool $white = false): ?string
    {
        $file = APP_ASSETS . '/coins/' . ($white ? 'white' : 'color') . '/' . strtolower($symbol) . '.png';

        return is_file($file) ? $file : null;
    }

    public static function exists(string $symbol): bool
    {
        return self::path($symbol) !== null;
    }

    /**
     * رسم لوگو داخل یک دایره‌ی شیشه‌ای.
     * $size قطر کل دایره است.
     */
    public static function draw(Canvas $c, string $symbol, float $cx, float $cy, float $size, bool $plate = true): void
    {
        $symbol = strtoupper($symbol);
        $radius = $size / 2;

        if ($plate) {
            $c->circle($cx, $cy, $radius, '#FFFFFF', 0.08);
            $c->ring($cx, $cy, $radius - 0.5, 1, '#FFFFFF', 0, 360, 0.16);
        }

        $path = self::path($symbol);
        if ($path !== null) {
            // لوگوهای خیلی تیره روی پس‌زمینه‌ی مشکی دیده نمی‌شوند
            if (!isset(self::$preferWhite[$symbol])) {
                self::$preferWhite[$symbol] = Canvas::imageLuminance($path) < 0.22
                    && self::path($symbol, true) !== null;
            }
            if (self::$preferWhite[$symbol]) {
                $path = self::path($symbol, true) ?? $path;
            }
            $inner = $size * 0.72;
            $c->image($path, $cx - $inner / 2, $cy - $inner / 2, $inner, $inner);

            return;
        }

        // جایگزین: حرف اول نماد
        $c->circle($cx, $cy, $radius - 1, Coins::color($symbol), 0.22);
        $c->text(
            mb_substr($symbol, 0, min(3, mb_strlen($symbol))),
            $cx,
            $cy - $size * 0.21,
            $size * 0.34,
            '#FFFFFF',
            Canvas::W_BOLD,
            'center',
            0.92
        );
    }
}
