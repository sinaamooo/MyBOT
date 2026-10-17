<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

use EmojiMaker\Vector\LottieBuilder;

/**
 * Effects that draw extra artwork (arrow, chart, orbiting elements ...).
 * The same geometry is used by the raster renderer and the Lottie writer.
 */
interface DecorationEffect
{
    /**
     * Shapes for one frame, in design space before the group transform.
     * Each shape: path (Path), fill (rgb|null), opacity (0..1),
     * stroke (['w'=>float,'color'=>rgb]|null, centred on the path),
     * outline (['w'=>float,'color'=>rgb]|null, drawn outside filled shapes),
     * z ('front'|'back').
     * @param array<string,mixed> $data
     * @param array<string,mixed> $params
     * @param array{0:float,1:float,2:float,3:float} $groupBounds
     * @return list<array<string,mixed>>
     */
    public function shapes(array $data, array $params, array $groupBounds, float $canvasWidth = 512.0): array;

    /**
     * Lottie shape groups for this decoration built from per-frame samples.
     * @param list<array<string,mixed>> $samples data for every frame
     * @param array<string,mixed> $params
     * @param array{0:float,1:float,2:float,3:float} $groupBounds
     * @return array{front:list<array<string,mixed>>,back:list<array<string,mixed>>}
     */
    public function lottie(LottieBuilder $lb, array $samples, array $params, array $groupBounds, float $canvasWidth = 512.0): array;
}
