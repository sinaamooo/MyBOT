<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

/**
 * Evaluates the animation for every frame: produces the group state (whole
 * composition) and the state of each element (logo, text).
 */
final class Timeline
{
    /**
     * @param array{0:float,1:float,2:float,3:float} $groupBounds
     * @param array<string,array{0:float,1:float,2:float,3:float}> $elementBounds
     * @param array<string,int> $units reveal units per element
     */
    public function __construct(
        private AnimationSpec $spec,
        private EffectRegistry $registry,
        private array $groupBounds,
        private array $elementBounds,
        private array $units,
        private bool $rtl,
        private string $mode,
        private int $seed = 0,
    ) {
    }

    public function frameCount(int $fps): int
    {
        return max(1, (int) round($this->spec->duration * $fps));
    }

    public function spec(): AnimationSpec
    {
        return $this->spec;
    }

    /** Element that receives a reveal coming from a group-level typewriter. */
    public function revealElement(): ?string
    {
        if (isset($this->elementBounds['text'])) {
            return 'text';
        }
        return isset($this->elementBounds['logo']) ? 'logo' : null;
    }

    /** @return array{group:FrameState,elements:array<string,FrameState>} */
    public function evaluate(int $frame, int $frames): array
    {
        $group = new FrameState();
        $elements = [];
        foreach ($this->elementBounds as $id => $_) {
            $elements[$id] = new FrameState();
        }
        $u = $frames > 0 ? $frame / $frames : 0.0;
        $n = count($this->spec->effects);
        foreach ($this->spec->effects as $index => $inst) {
            $effect = $this->registry->get($inst['type']);
            $params = $inst['params'];
            if ($this->spec->mode === 'sequence' && $n > 1) {
                $start = $index / $n;
                $end = ($index + 1) / $n;
                if ($u < $start || $u >= $end) {
                    if ($effect->isDecoration()) {
                        // decorations stay at their resting pose outside their slot
                        $p = 0.0;
                    } else {
                        continue;
                    }
                } else {
                    $p = ($u - $start) * $n;
                }
            } else {
                $delay = (float) ($params['delay'] ?? 0.0);
                $p = Easing::frac($u - $delay / max(0.1, $this->spec->duration));
            }
            $target = $inst['target'];
            if ($target !== 'all' && !isset($elements[$target])) {
                $target = 'all';
            }
            $bounds = $target === 'all' ? $this->groupBounds : $this->elementBounds[$target];
            $unitsKey = $target === 'all' ? $this->revealElement() : $target;
            $ctx = new EffectContext(
                $frame,
                $frames,
                $u,
                $p,
                $this->spec->duration,
                $this->mode,
                $params,
                $bounds,
                $this->groupBounds,
                $index,
                $target,
                $unitsKey !== null ? ($this->units[$unitsKey] ?? 0) : 0,
                $this->rtl,
                $this->seed,
            );
            $effect->apply($ctx, $target === 'all' ? $group : $elements[$target]);
        }
        return ['group' => $group, 'elements' => $elements];
    }
}
