<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

use EmojiMaker\Animation\Effects;

/**
 * Registry of available effect engines. New effects are added by appending
 * their class to EFFECTS - nothing else in the system has to change.
 */
final class EffectRegistry
{
    /** @var list<class-string<Effect>> */
    public const EFFECTS = [
        Effects\Pulse::class,
        Effects\Spin::class,
        Effects\Glow::class,
        Effects\Shake::class,
        Effects\Fade::class,
        Effects\Rise::class,
        Effects\Slide::class,
        Effects\Typewriter::class,
        Effects\Bounce::class,
        Effects\Flash::class,
        Effects\ColorShift::class,
        Effects\Arrow::class,
        Effects\Chart::class,
        Effects\Orbit::class,
        Effects\Pop::class,
        Effects\Swing::class,
        Effects\FloatEffect::class,
        Effects\Heartbeat::class,
        Effects\Jelly::class,
        Effects\Flip3D::class,
        Effects\Tilt3D::class,
    ];

    /** @var array<string,Effect> */
    private array $effects = [];

    private static ?self $instance = null;

    public function __construct()
    {
        foreach (self::EFFECTS as $class) {
            $e = new $class();
            $this->effects[$e->key()] = $e;
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function has(string $key): bool
    {
        return isset($this->effects[$key]);
    }

    public function get(string $key): Effect
    {
        if (!isset($this->effects[$key])) {
            throw new \InvalidArgumentException('Unknown effect: ' . $key);
        }
        return $this->effects[$key];
    }

    /** @return array<string,Effect> */
    public function all(): array
    {
        return $this->effects;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->effects);
    }
}
