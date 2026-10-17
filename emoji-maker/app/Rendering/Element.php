<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Geometry\Path;

/**
 * One visual element of the composition (background, logo or text) in the
 * 512x512 design space, with its styling.
 */
final class Element
{
    public string $id;
    /** @var array{0:float,1:float,2:float,3:float} ink bounds */
    public array $bounds;
    /** Vector fill (text glyphs, background shape). */
    public ?Path $path = null;
    /** @var list<Path> reveal units (characters) */
    public array $units = [];
    /** @var list<array{path:Path,color:array{0:int,1:int,2:int}}> traced logo layers (vector mode) */
    public array $layers = [];
    /** Silhouette (union) used for outline/glow of traced logos. */
    public ?Path $silhouette = null;
    /** Raster source (logo) and its placement rectangle in design space. */
    public ?\GdImage $image = null;
    /** @var array{0:float,1:float,2:float,3:float}|null */
    public ?array $imageRect = null;
    /** @var array{0:int,1:int,2:int} */
    public array $fill = [255, 255, 255];
    /** @var array{0:array{0:int,1:int,2:int},1:array{0:int,1:int,2:int}}|null vertical gradient */
    public ?array $gradient = null;
    /** @var array{w:float,color:array{0:int,1:int,2:int}}|null */
    public ?array $outline = null;
    /** @var array{color:array{0:int,1:int,2:int},opacity:float,dx:float,dy:float,blur:float}|null */
    public ?array $shadow = null;
    /** @var array{color:array{0:int,1:int,2:int},radius:float,strength:float}|null */
    public ?array $glow = null;
    public int $fillRule = Rasterizer::NONZERO;
    public bool $wipeReveal = false;
    /** Circles look identical when rotated (skip the costly bitmap rotation). */
    public bool $rotationInvariant = false;
    /** @var list<array{0:float,1:float}>|null outline points used for canvas fitting */
    public ?array $hull = null;
    /** @var array{d:float,color:array{0:int,1:int,2:int}}|null 3D extrusion (thickness downwards) */
    public ?array $depth = null;
    /** Glossy 3D look: lighter top gradient + specular highlight. */
    public bool $gloss = false;
    /** @var array{path:Path,w:float,color:array{0:int,1:int,2:int}}|null extra stroke drawn over the fill (coin rim) */
    public ?array $extraStroke = null;

    /** @param array{0:float,1:float,2:float,3:float} $bounds */
    public function __construct(string $id, array $bounds)
    {
        $this->id = $id;
        $this->bounds = $bounds;
    }

    /** @return array{0:float,1:float} */
    public function center(): array
    {
        return [($this->bounds[0] + $this->bounds[2]) / 2, ($this->bounds[1] + $this->bounds[3]) / 2];
    }

    /** Extra margin needed around the ink for outline, glow and shadow. */
    public function styleMargin(): float
    {
        $m = ($this->outline['w'] ?? 0.0) + ($this->depth['d'] ?? 0.0);
        if ($this->glow) {
            $m += $this->glow['radius'] * 1.6;
        }
        if ($this->shadow) {
            $m = max($m, ($this->outline['w'] ?? 0.0) + max(abs($this->shadow['dx']), abs($this->shadow['dy'])) + $this->shadow['blur'] * 1.5);
        }
        return $m + 2;
    }

    /** @return list<array{0:float,1:float}> points (with style margin) used for fitting */
    public function hullPoints(float $margin): array
    {
        if ($this->hull !== null) {
            [$cx, $cy] = $this->center();
            $out = [];
            foreach ($this->hull as [$x, $y]) {
                $dx = $x - $cx;
                $dy = $y - $cy;
                $len = max(1e-6, sqrt($dx * $dx + $dy * $dy));
                $out[] = [$x + $dx / $len * $margin, $y + $dy / $len * $margin];
            }
            return $out;
        }
        $b = $this->bounds;
        return [[$b[0] - $margin, $b[1] - $margin], [$b[2] + $margin, $b[1] - $margin], [$b[0] - $margin, $b[3] + $margin], [$b[2] + $margin, $b[3] + $margin]];
    }

    public function unitCount(): int
    {
        if ($this->units) {
            return count($this->units);
        }
        if ($this->layers) {
            return 0;
        }
        return $this->wipeReveal ? 12 : 0;
    }
}
