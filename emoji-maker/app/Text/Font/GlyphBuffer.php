<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

/**
 * Glyph sequence being shaped (logical order).
 * Item keys:
 *  g  glyph id            cl cluster (index of source character)
 *  m  feature mask        xa x advance (font units)
 *  xo x offset            yo y offset
 *  att index of the glyph a mark is attached to (-1 = none)
 *  join  true when the glyph connects to the following (logical) letter
 */
final class GlyphBuffer
{
    /** @var list<array<string,mixed>> */
    public array $items = [];

    public function count(): int
    {
        return count($this->items);
    }

    /** @param array<string,mixed> $item */
    public function push(array $item): void
    {
        $this->items[] = $item + ['m' => 0, 'xa' => 0, 'xo' => 0, 'yo' => 0, 'att' => -1, 'join' => false];
    }

    /** @param list<int> $glyphs */
    public function replaceWithSequence(int $i, array $glyphs): void
    {
        $base = $this->items[$i];
        $new = [];
        foreach ($glyphs as $g) {
            $copy = $base;
            $copy['g'] = $g;
            $new[] = $copy;
        }
        array_splice($this->items, $i, 1, $new);
    }

    /** @param list<int> $indices sorted ascending */
    public function removeIndices(array $indices): void
    {
        rsort($indices);
        foreach ($indices as $idx) {
            array_splice($this->items, $idx, 1);
        }
    }
}
