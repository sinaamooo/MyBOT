<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Shaping;

/**
 * Unicode Bidirectional Algorithm (UAX #9) for a single paragraph without
 * explicit embeddings - sufficient for short emoji texts that mix Persian,
 * Latin, numbers and symbols. Implements rules P2-P3, W1-W7, N1-N2, I1-I2,
 * L1 (trailing whitespace) and L2 (reordering).
 */
final class Bidi
{
    /**
     * @param list<int> $cps
     * @return array{levels:list<int>,base:int}
     */
    public static function levels(array $cps, ?int $forceBase = null): array
    {
        $n = count($cps);
        $types = [];
        foreach ($cps as $cp) {
            $types[] = Unicode::bidiClass($cp);
        }
        $orig = $types;

        // P2/P3 paragraph level
        $base = 0;
        if ($forceBase !== null) {
            $base = $forceBase;
        } else {
            foreach ($types as $t) {
                if ($t === Unicode::L) {
                    $base = 0;
                    break;
                }
                if ($t === Unicode::R || $t === Unicode::AL) {
                    $base = 1;
                    break;
                }
            }
        }
        $sos = $base ? Unicode::R : Unicode::L;

        // BN and NSM take the type of the previous character (X9 + W1 approx.)
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === Unicode::NSM || $types[$i] === Unicode::BN) {
                $types[$i] = $i > 0 ? $types[$i - 1] : $sos;
                if ($orig[$i] === Unicode::BN && ($types[$i] === Unicode::B || $types[$i] === Unicode::S || $types[$i] === Unicode::WS)) {
                    $types[$i] = Unicode::ON;
                }
            }
        }
        // W2: EN after AL -> AN
        $lastStrong = $sos;
        for ($i = 0; $i < $n; $i++) {
            $t = $types[$i];
            if ($t === Unicode::L || $t === Unicode::R || $t === Unicode::AL) {
                $lastStrong = $t;
            } elseif ($t === Unicode::EN && $lastStrong === Unicode::AL) {
                $types[$i] = Unicode::AN;
            }
        }
        // W3: AL -> R
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === Unicode::AL) {
                $types[$i] = Unicode::R;
            }
        }
        // W4: single separators between numbers
        for ($i = 1; $i < $n - 1; $i++) {
            $t = $types[$i];
            if ($t === Unicode::ES && $types[$i - 1] === Unicode::EN && $types[$i + 1] === Unicode::EN) {
                $types[$i] = Unicode::EN;
            } elseif ($t === Unicode::CS) {
                if ($types[$i - 1] === Unicode::EN && $types[$i + 1] === Unicode::EN) {
                    $types[$i] = Unicode::EN;
                } elseif ($types[$i - 1] === Unicode::AN && $types[$i + 1] === Unicode::AN) {
                    $types[$i] = Unicode::AN;
                }
            }
        }
        // W5: ET adjacent to EN -> EN
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] !== Unicode::ET) {
                continue;
            }
            $j = $i;
            while ($j < $n && $types[$j] === Unicode::ET) {
                $j++;
            }
            $adjacentEn = ($i > 0 && $types[$i - 1] === Unicode::EN) || ($j < $n && $types[$j] === Unicode::EN);
            if ($adjacentEn) {
                for ($k = $i; $k < $j; $k++) {
                    $types[$k] = Unicode::EN;
                }
            }
            $i = $j - 1;
        }
        // W6: remaining separators -> ON
        for ($i = 0; $i < $n; $i++) {
            if (in_array($types[$i], [Unicode::ES, Unicode::ET, Unicode::CS], true)) {
                $types[$i] = Unicode::ON;
            }
        }
        // W7: EN after L -> L
        $lastStrong = $sos;
        for ($i = 0; $i < $n; $i++) {
            $t = $types[$i];
            if ($t === Unicode::L || $t === Unicode::R) {
                $lastStrong = $t;
            } elseif ($t === Unicode::EN && $lastStrong === Unicode::L) {
                $types[$i] = Unicode::L;
            }
        }
        // N1/N2: neutrals
        $isNeutral = static fn (string $t) => in_array($t, [Unicode::ON, Unicode::WS, Unicode::S, Unicode::B, Unicode::BN], true);
        $strongDir = static fn (string $t) => $t === Unicode::L ? Unicode::L : (in_array($t, [Unicode::R, Unicode::EN, Unicode::AN], true) ? Unicode::R : null);
        for ($i = 0; $i < $n; $i++) {
            if (!$isNeutral($types[$i])) {
                continue;
            }
            $j = $i;
            while ($j < $n && $isNeutral($types[$j])) {
                $j++;
            }
            $before = $i > 0 ? $strongDir($types[$i - 1]) : $sos;
            $after = $j < $n ? $strongDir($types[$j]) : $sos;
            $dir = ($before !== null && $before === $after) ? $before : ($base ? Unicode::R : Unicode::L);
            for ($k = $i; $k < $j; $k++) {
                $types[$k] = $dir;
            }
            $i = $j - 1;
        }
        // I1/I2: implicit levels
        $levels = [];
        for ($i = 0; $i < $n; $i++) {
            $t = $types[$i];
            if ($base % 2 === 0) {
                $levels[] = match ($t) {
                    Unicode::R => $base + 1,
                    Unicode::AN, Unicode::EN => $base + 2,
                    default => $base,
                };
            } else {
                $levels[] = match ($t) {
                    Unicode::L, Unicode::EN, Unicode::AN => $base + 1,
                    default => $base,
                };
            }
        }
        // L1: trailing whitespace -> paragraph level
        for ($i = $n - 1; $i >= 0; $i--) {
            $o = $orig[$i];
            if ($o === Unicode::WS || $o === Unicode::BN || $o === Unicode::S || $o === Unicode::B) {
                $levels[$i] = $base;
            } else {
                break;
            }
        }
        return ['levels' => $levels, 'base' => $base];
    }

    /**
     * L2: returns the visual order (list of logical indices) for given levels.
     * @param list<int> $levels
     * @return list<int>
     */
    public static function reorder(array $levels): array
    {
        $n = count($levels);
        $order = range(0, max(0, $n - 1));
        if ($n === 0) {
            return [];
        }
        $max = max($levels);
        $minOdd = PHP_INT_MAX;
        foreach ($levels as $l) {
            if ($l % 2 === 1 && $l < $minOdd) {
                $minOdd = $l;
            }
        }
        if ($minOdd === PHP_INT_MAX) {
            return $order;
        }
        for ($level = $max; $level >= $minOdd; $level--) {
            $i = 0;
            while ($i < $n) {
                if ($levels[$order[$i]] >= $level) {
                    $j = $i;
                    while ($j + 1 < $n && $levels[$order[$j + 1]] >= $level) {
                        $j++;
                    }
                    $slice = array_reverse(array_slice($order, $i, $j - $i + 1));
                    array_splice($order, $i, $j - $i + 1, $slice);
                    $i = $j + 1;
                } else {
                    $i++;
                }
            }
        }
        return $order;
    }
}
