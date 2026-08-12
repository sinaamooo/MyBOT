<?php

declare(strict_types=1);

namespace App\Support;

final class Num
{
    /**
     * Formats a float as a plain decimal string, never scientific notation -
     * PHP's default (string) cast switches to "1.23E-7" style notation for
     * any value under ~0.0001, which is a very real price range for cheap
     * altcoins/meme-coins and would otherwise show up unreadable in signal
     * messages and cards.
     */
    public static function price(float $value): string
    {
        if ($value == 0.0) {
            return '0';
        }

        $magnitude = abs($value);
        $decimals = $magnitude < 1
            ? min(12, max(2, (int) ceil(-log10($magnitude)) + 4))
            : 8;

        $formatted = number_format($value, $decimals, '.', '');
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '' ? '0' : $formatted;
    }
}
