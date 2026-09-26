<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\Coins;

final class CoinLogo
{
    public static bool $lightSurface = true;

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

    public static function draw(Canvas $c, string $symbol, float $cx, float $cy, float $size, bool $plate = true): void
    {
        $symbol = strtoupper($symbol);
        $radius = $size / 2;

        if ($plate) {
            if (self::$lightSurface) {
                $c->circle($cx, $cy, $radius, '#F4F7F5', 1.0);
                $c->ring($cx, $cy, $radius - 0.5, 1.1, '#0A0F0D', 0, 360, 0.16);
            } else {
                $c->circle($cx, $cy, $radius, '#FFFFFF', 0.08);
                $c->ring($cx, $cy, $radius - 0.5, 1, '#FFFFFF', 0, 360, 0.16);
            }
        }

        $path = self::path($symbol);
        if ($path !== null) {
            if (!self::$lightSurface) {
                if (!isset(self::$preferWhite[$symbol])) {
                    self::$preferWhite[$symbol] = Canvas::imageLuminance($path) < 0.22
                        && self::path($symbol, true) !== null;
                }
                if (self::$preferWhite[$symbol]) {
                    $path = self::path($symbol, true) ?? $path;
                }
            }
            $inner = $plate ? $size * 0.72 : $size;
            $c->image($path, $cx - $inner / 2, $cy - $inner / 2, $inner, $inner, 1.0, !$plate);

            return;
        }

        $color = Coins::color($symbol);
        $ink = '#FFFFFF';
        if (self::$lightSurface) {
            $c->circle($cx, $cy, $radius - 1, '#E4E4E1', 1.0);
            $ink = '#3A3A38';
        } else {
            $c->circle($cx, $cy, $radius - 1, $color, 0.22);
        }
        $mark = mb_strlen($symbol) <= 3 ? $symbol : mb_substr($symbol, 0, 1);
        $c->text(
            $mark,
            $cx,
            $cy,
            $size * (mb_strlen($mark) === 1 ? 0.42 : 0.32),
            $ink,
            Canvas::W_BOLD,
            'center',
            0.96,
            'ink'
        );
    }
}
