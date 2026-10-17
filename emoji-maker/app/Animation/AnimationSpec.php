<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

/**
 * Validated animation description: a list of effect instances plus how they
 * are combined (parallel or one after another) and the loop duration.
 */
final class AnimationSpec
{
    public const MAX_EFFECTS = 6;

    /**
     * @param list<array{type:string,params:array<string,mixed>,target:string}> $effects
     */
    public function __construct(
        public array $effects = [],
        public string $mode = 'parallel',
        public float $duration = 2.0,
    ) {
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a, ?EffectRegistry $registry = null, float $maxDuration = 3.0): self
    {
        $registry ??= EffectRegistry::instance();
        $effects = [];
        foreach ((array) ($a['effects'] ?? []) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $type = (string) ($e['type'] ?? '');
            if (!$registry->has($type)) {
                continue;
            }
            $effect = $registry->get($type);
            $target = (string) ($e['target'] ?? 'all');
            if (!in_array($target, $effect->targets(), true)) {
                $target = $effect->targets()[0];
            }
            $effects[] = [
                'type' => $type,
                'params' => $effect->normalize((array) ($e['params'] ?? [])),
                'target' => $target,
            ];
            if (count($effects) >= self::MAX_EFFECTS) {
                break;
            }
        }
        $mode = ($a['mode'] ?? 'parallel') === 'sequence' ? 'sequence' : 'parallel';
        $duration = is_numeric($a['duration'] ?? null) ? (float) $a['duration'] : 2.0;
        $duration = max(0.5, min($maxDuration, $duration));
        return new self($effects, $mode, $duration);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['effects' => $this->effects, 'mode' => $this->mode, 'duration' => $this->duration];
    }

    public function isStatic(): bool
    {
        return $this->effects === [];
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_map(static fn ($e) => $e['type'], $this->effects);
    }
}
