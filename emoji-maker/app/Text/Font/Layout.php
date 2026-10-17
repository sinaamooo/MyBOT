<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

/**
 * Shared OpenType layout structures: Coverage and ClassDef tables.
 */
final class Layout
{
    /** @var array<string,array<int,int>> */
    private static array $cache = [];

    /**
     * Coverage table -> map glyphId => coverageIndex
     * @return array<int,int>
     */
    public static function coverage(BinaryReader $r, int $o): array
    {
        $key = $r->uid() . ':c' . $o;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $map = [];
        $format = $r->u16($o);
        if ($format === 1) {
            $count = $r->u16($o + 2);
            foreach ($r->u16Array($o + 4, $count) as $i => $g) {
                $map[$g] = $i;
            }
        } elseif ($format === 2) {
            $count = $r->u16($o + 2);
            for ($i = 0; $i < $count; $i++) {
                $rec = $o + 4 + $i * 6;
                $start = $r->u16($rec);
                $end = $r->u16($rec + 2);
                $idx = $r->u16($rec + 4);
                if ($end - $start > 20000) {
                    continue;
                }
                for ($g = $start; $g <= $end; $g++) {
                    $map[$g] = $idx + ($g - $start);
                }
            }
        }
        return self::$cache[$key] = $map;
    }

    /**
     * ClassDef table -> map glyphId => class (missing = class 0)
     * @return array<int,int>
     */
    public static function classDef(BinaryReader $r, int $o): array
    {
        $key = $r->uid() . ':k' . $o;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $map = [];
        $format = $r->u16($o);
        if ($format === 1) {
            $start = $r->u16($o + 2);
            $count = $r->u16($o + 4);
            foreach ($r->u16Array($o + 6, $count) as $i => $cls) {
                if ($cls) {
                    $map[$start + $i] = $cls;
                }
            }
        } elseif ($format === 2) {
            $count = $r->u16($o + 2);
            for ($i = 0; $i < $count; $i++) {
                $rec = $o + 4 + $i * 6;
                $start = $r->u16($rec);
                $end = $r->u16($rec + 2);
                $cls = $r->u16($rec + 4);
                if ($end - $start > 20000 || !$cls) {
                    continue;
                }
                for ($g = $start; $g <= $end; $g++) {
                    $map[$g] = $cls;
                }
            }
        }
        return self::$cache[$key] = $map;
    }
}
