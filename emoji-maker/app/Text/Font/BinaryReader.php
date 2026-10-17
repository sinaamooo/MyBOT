<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

/**
 * Big-endian binary helpers over an in-memory font file.
 */
class BinaryReader
{
    private string $uid;

    public function __construct(protected string $data)
    {
        $this->uid = bin2hex(random_bytes(6));
    }

    /** Unique id of this reader instance (cache key for parsed sub tables). */
    final public function uid(): string
    {
        return $this->uid;
    }

    public function length(): int
    {
        return strlen($this->data);
    }

    final public function u8(int $o): int
    {
        return ord($this->data[$o] ?? "\0");
    }

    final public function s8(int $o): int
    {
        $v = ord($this->data[$o] ?? "\0");
        return $v >= 0x80 ? $v - 0x100 : $v;
    }

    final public function u16(int $o): int
    {
        $d = $this->data;
        if (!isset($d[$o + 1])) {
            return 0;
        }
        return (ord($d[$o]) << 8) | ord($d[$o + 1]);
    }

    final public function s16(int $o): int
    {
        $v = $this->u16($o);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    final public function u32(int $o): int
    {
        $d = $this->data;
        if (!isset($d[$o + 3])) {
            return 0;
        }
        return (ord($d[$o]) << 24) | (ord($d[$o + 1]) << 16) | (ord($d[$o + 2]) << 8) | ord($d[$o + 3]);
    }

    final public function s32(int $o): int
    {
        $v = $this->u32($o);
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }

    final public function f2dot14(int $o): float
    {
        return $this->s16($o) / 16384.0;
    }

    final public function tag(int $o): string
    {
        return substr($this->data, $o, 4);
    }

    final public function bytes(int $o, int $len): string
    {
        return substr($this->data, $o, $len);
    }

    /** @return list<int> */
    final public function u16Array(int $o, int $count): array
    {
        if ($count <= 0) {
            return [];
        }
        $chunk = substr($this->data, $o, $count * 2);
        if (strlen($chunk) < $count * 2) {
            $chunk = str_pad($chunk, $count * 2, "\0");
        }
        return array_values(unpack('n*', $chunk) ?: []);
    }
}
