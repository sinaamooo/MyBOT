<?php

declare(strict_types=1);

namespace EmojiMaker\Text;

use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Text\Shaping\TextShaper;

/**
 * Turns shaped text into vector outlines positioned in design space.
 * Supports multi-line centring, auto-fit into a box and per-character
 * outlines (reading order) for typewriter style reveals.
 */
final class TextLayout
{
    /** @var list<array{line:int,cl:int,font:int,g:int,x:float,y:float,sx:float}> */
    private array $placed = [];
    /** @var array{0:float,1:float,2:float,3:float}|null ink bbox in em (y down) */
    private ?array $ink = null;
    /** @var list<int> */
    private array $missing = [];
    private bool $rtl = false;

    public function __construct(
        private TextShaper $shaper,
        string $text,
        float $letterSpacingEm = 0.0,
        float $lineHeight = 1.12,
    ) {
        $lines = $shaper->shape($text, $letterSpacingEm);
        $fonts = $shaper->fonts();
        $lineAdvance = 0.0;
        foreach ($lines as $l) {
            $lineAdvance = max($lineAdvance, ($l['ascent'] - $l['descent']) * $lineHeight);
        }
        $baseline = 0.0;
        foreach ($lines as $li => $line) {
            if ($li === 0) {
                $baseline = $line['ascent'];
            } else {
                $baseline += $lineAdvance;
            }
            if ($line['base'] === 1) {
                $this->rtl = true;
            }
            $this->missing = array_merge($this->missing, $line['missing']);
            // line ink extents
            $minX = INF;
            $maxX = -INF;
            $glyphs = [];
            foreach ($line['glyphs'] as $g) {
                $font = $fonts[$g['font']];
                $bb = $font->glyphBounds($g['g']);
                if ($bb === null) {
                    continue;
                }
                $upem = $font->unitsPerEm();
                $gx0 = $g['x'] + $bb[0] * $g['sx'] / $upem;
                $gx1 = $g['x'] + $bb[2] * $g['sx'] / $upem;
                $minX = min($minX, $gx0);
                $maxX = max($maxX, $gx1);
                $glyphs[] = [$g, $bb, $upem];
            }
            if (!$glyphs) {
                continue;
            }
            $shift = -($minX + $maxX) / 2; // centre each line on x = 0
            foreach ($glyphs as [$g, $bb, $upem]) {
                $x = $g['x'] + $shift;
                $y = $baseline - $g['y'];
                $this->placed[] = ['line' => $li, 'cl' => $g['cl'], 'font' => $g['font'], 'g' => $g['g'], 'x' => $x, 'y' => $y, 'sx' => $g['sx']];
                $ix0 = $x + $bb[0] * $g['sx'] / $upem;
                $ix1 = $x + $bb[2] * $g['sx'] / $upem;
                $iy0 = $y - $bb[3] / $upem;
                $iy1 = $y - $bb[1] / $upem;
                if ($this->ink === null) {
                    $this->ink = [$ix0, $iy0, $ix1, $iy1];
                } else {
                    $this->ink = [min($this->ink[0], $ix0), min($this->ink[1], $iy0), max($this->ink[2], $ix1), max($this->ink[3], $iy1)];
                }
            }
        }
        $this->missing = array_values(array_unique($this->missing));
    }

    public function isEmpty(): bool
    {
        return $this->ink === null;
    }

    /** @return list<int> code points that no font could render */
    public function missingCodepoints(): array
    {
        return $this->missing;
    }

    public function isRtl(): bool
    {
        return $this->rtl;
    }

    /** Ink size in em: [width, height] */
    public function inkSize(): array
    {
        if ($this->ink === null) {
            return [0.0, 0.0];
        }
        return [$this->ink[2] - $this->ink[0], $this->ink[3] - $this->ink[1]];
    }

    /**
     * Computes the em->design scale so that the text fits the box.
     * $sizeFactor 1.0 = fill the box, smaller = smaller text.
     */
    public function fitScale(float $boxW, float $boxH, float $sizeFactor = 1.0): float
    {
        [$w, $h] = $this->inkSize();
        if ($w <= 0 || $h <= 0) {
            return 0.0;
        }
        // never blow tiny glyphs (dots, dashes) up to the whole box:
        // the ink is measured at least as tall as typical capitals (0.62 em)
        $h = max($h, 0.62);
        $w = max($w, 0.3);
        return min($boxW / $w, $boxH / $h) * max(0.05, $sizeFactor);
    }

    /**
     * Glyph outlines in design space, ink centred at ($cx,$cy).
     * @return array{all:Path,units:list<Path>,bounds:array{0:float,1:float,2:float,3:float}}
     */
    public function paths(float $scale, float $cx, float $cy): array
    {
        $all = new Path();
        $units = [];
        if ($this->ink === null || $scale <= 0) {
            return ['all' => $all, 'units' => [], 'bounds' => [$cx, $cy, $cx, $cy]];
        }
        $inkCx = ($this->ink[0] + $this->ink[2]) / 2;
        $inkCy = ($this->ink[1] + $this->ink[3]) / 2;
        $fonts = $this->shaper->fonts();
        $byUnit = [];
        foreach ($this->placed as $p) {
            $font = $fonts[$p['font']];
            $upem = $font->unitsPerEm();
            $s = $scale / $upem;
            // font units (y up) -> design (y down)
            $m = new Matrix(
                $s * $p['sx'], 0.0, 0.0, -$s,
                $cx + ($p['x'] - $inkCx) * $scale,
                $cy + ($p['y'] - $inkCy) * $scale
            );
            $gp = $font->glyphPath($p['g'])->transform($m);
            $all->append($gp);
            $key = sprintf('%04d-%06d', $p['line'], $p['cl']);
            if (!isset($byUnit[$key])) {
                $byUnit[$key] = new Path();
            }
            $byUnit[$key]->append($gp);
        }
        ksort($byUnit);
        foreach ($byUnit as $u) {
            $units[] = $u;
        }
        $hw = ($this->ink[2] - $this->ink[0]) * $scale / 2;
        $hh = ($this->ink[3] - $this->ink[1]) * $scale / 2;
        return ['all' => $all, 'units' => $units, 'bounds' => [$cx - $hw, $cy - $hh, $cx + $hw, $cy + $hh]];
    }
}
