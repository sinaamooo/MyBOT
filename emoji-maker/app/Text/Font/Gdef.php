<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

/**
 * GDEF table: glyph classes (base / ligature / mark / component) and mark
 * attachment classes. Needed to honour lookup flags such as IgnoreMarks.
 */
final class Gdef
{
    public const BASE = 1;
    public const LIGATURE = 2;
    public const MARK = 3;
    public const COMPONENT = 4;

    /** @var array<int,int> */
    private array $glyphClass = [];
    /** @var array<int,int> */
    private array $markAttachClass = [];
    /** @var list<array<int,bool>> */
    private array $markSets = [];

    public function __construct(BinaryReader $r, int $offset)
    {
        $major = $r->u16($offset);
        $minor = $r->u16($offset + 2);
        $gcd = $r->u16($offset + 4);
        $macd = $r->u16($offset + 10);
        if ($gcd) {
            $this->glyphClass = Layout::classDef($r, $offset + $gcd);
        }
        if ($macd) {
            $this->markAttachClass = Layout::classDef($r, $offset + $macd);
        }
        if ($major === 1 && $minor >= 2) {
            $msd = $r->u16($offset + 12);
            if ($msd) {
                $base = $offset + $msd;
                $count = $r->u16($base + 2);
                for ($i = 0; $i < $count; $i++) {
                    $cov = $r->u32($base + 4 + $i * 4);
                    $this->markSets[] = array_fill_keys(array_keys(Layout::coverage($r, $base + $cov)), true);
                }
            }
        }
    }

    public function glyphClass(int $gid): int
    {
        return $this->glyphClass[$gid] ?? 0;
    }

    public function isMark(int $gid): bool
    {
        return ($this->glyphClass[$gid] ?? 0) === self::MARK;
    }

    public function markAttachClass(int $gid): int
    {
        return $this->markAttachClass[$gid] ?? 0;
    }

    public function inMarkSet(int $set, int $gid): bool
    {
        return isset($this->markSets[$set][$gid]);
    }
}
