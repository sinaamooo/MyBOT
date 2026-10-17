<?php

declare(strict_types=1);

namespace EmojiMaker\Batch;

use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Geometry\Shapes;
use EmojiMaker\Rendering\Color;

/**
 * Plans the variants of a batch ("پک چندتایی").
 *
 * Every variant is a complete design configuration; variants differ in real
 * visual properties (effect, effect parameters, colours, frame shape, words)
 * and duplicates are rejected by a hash of the normalised configuration, so a
 * batch never contains two identical emoji.
 */
final class BatchPlanner
{
    public const MODES = [
        'single' => 'یک طرح با یک انیمیشن',
        'multi_anim' => 'یک طرح با چند انیمیشن مختلف',
        'multi_color' => 'یک متن با رنگ‌های مختلف',
        'logo_effects' => 'یک لوگو با افکت‌های متفاوت',
        'logo_frames' => 'یک لوگو در قاب‌های مختلف',
        'words' => 'هر کلمه یک ایموجی (متن‌های من)',
        'auto_combo' => 'ترکیب خودکار رنگ و حرکت',
        'manual' => 'انتخاب دستی افکت برای هر خروجی',
        'random' => 'انتخاب تصادفی از افکت‌های انتخابی',
    ];

    public const DEFAULT_COLORS = ['#22C55E', '#EF4444', '#3B82F6', '#FACC15', '#FFFFFF', '#A855F7', '#F97316', '#06B6D4'];
    public const DEFAULT_EFFECTS = ['pulse', 'glow', 'spin', 'bounce', 'shake', 'flip3d', 'float', 'heartbeat', 'pop', 'flash', 'tilt3d', 'swing'];
    public const FRAMES = ['circle', 'coin', 'rounded', 'shield', 'hexagon', 'seal', 'heart', 'diamond', 'square', 'bubble'];

    public function __construct(private EffectRegistry $effects)
    {
    }

    /**
     * @param array<string,mixed> $base project configuration (design + animation)
     * @param array<string,mixed> $batch count, mode, colors, effects, frames, manual, words, seed
     * @param list<string>|null $allowedEffects effects enabled by the admin
     * @return list<array{config:array<string,mixed>,label:string,hash:string}>
     */
    public function plan(array $base, array $batch, ?array $allowedEffects = null): array
    {
        $count = max(1, min(200, (int) ($batch['count'] ?? 1)));
        $mode = isset(self::MODES[$batch['mode'] ?? '']) ? (string) $batch['mode'] : ($count > 1 ? 'auto_combo' : 'single');
        $seed = (int) ($batch['seed'] ?? crc32((string) json_encode($base)));
        mt_srand($seed);
        $allowed = $allowedEffects ?? $this->effects->keys();
        $effects = array_values(array_filter((array) ($batch['effects'] ?? []), fn ($e) => is_string($e) && $this->effects->has($e) && in_array($e, $allowed, true)));
        if (!$effects) {
            $baseEffects = array_map(static fn ($e) => $e['type'] ?? '', (array) ($base['animation']['effects'] ?? []));
            $effects = array_values(array_filter($baseEffects, fn ($e) => $this->effects->has($e) && in_array($e, $allowed, true)));
        }
        if (!$effects || in_array($mode, ['multi_anim', 'logo_effects', 'auto_combo'], true) && count($effects) < 2) {
            $effects = array_values(array_unique(array_merge($effects, array_values(array_intersect(self::DEFAULT_EFFECTS, $allowed)))));
        }
        $colors = array_values(array_filter(array_map(static fn ($c) => is_string($c) && Color::isHex($c) ? Color::normalizeHex($c) : null, (array) ($batch['colors'] ?? []))));
        if (!$colors) {
            $colors = in_array($mode, ['multi_color', 'auto_combo', 'logo_frames'], true) ? self::DEFAULT_COLORS : [];
        }
        $frames = array_values(array_filter((array) ($batch['frames'] ?? []), static fn ($f) => is_string($f) && isset(Shapes::TYPES[$f]) && $f !== 'none'));
        if (!$frames) {
            $frames = self::FRAMES;
        }
        $words = array_values(array_filter(array_map(static fn ($w) => trim((string) $w), (array) ($batch['words'] ?? [])), static fn ($w) => $w !== ''));

        $variants = [];
        $seen = [];
        $add = function (array $config, string $label) use (&$variants, &$seen, $count): bool {
            if (count($variants) >= $count) {
                return false;
            }
            $hash = self::hash($config);
            if (isset($seen[$hash])) {
                return false;
            }
            $seen[$hash] = true;
            $variants[] = ['config' => $config, 'label' => $label, 'hash' => $hash];
            return true;
        };

        $baseAnim = (array) ($base['animation'] ?? []);
        $baseEffects = (array) ($baseAnim['effects'] ?? []);
        $add($base, $this->label($base));
        $guard = 0;
        $i = 0;
        while (count($variants) < $count && $guard++ < $count * 40) {
            $cfg = $base;
            $i++;
            switch ($mode) {
                case 'single':
                    $cfg['animation']['effects'] = $this->varyParams($baseEffects ?: [['type' => $effects[0]]], $i);
                    break;
                case 'multi_anim':
                case 'logo_effects':
                    $cfg['animation']['effects'] = $this->effectCombo($effects, $i);
                    if ($mode === 'logo_effects' && $i > count($effects) * 2) {
                        $cfg = $this->applyFrame($cfg, $frames[$i % count($frames)], $colors[$i % max(1, count($colors))] ?? null);
                    }
                    break;
                case 'multi_color':
                    $color = $colors[$i % count($colors)];
                    $cfg = $this->applyColor($cfg, $color, intdiv($i, count($colors)));
                    if ($i >= count($colors)) {
                        $cfg['animation']['effects'] = $this->varyParams($baseEffects ?: [['type' => $effects[0]]], intdiv($i, count($colors)));
                    }
                    break;
                case 'logo_frames':
                    $frame = $frames[$i % count($frames)];
                    $color = $colors[intdiv($i, count($frames)) % count($colors)];
                    $cfg = $this->applyFrame($cfg, $frame, $color);
                    if ($i >= count($frames) * count($colors)) {
                        $cfg['animation']['effects'] = $this->effectCombo($effects, $i);
                    }
                    break;
                case 'words':
                    if (!$words) {
                        $words = [(string) ($base['text'] ?? '')];
                    }
                    $word = $words[$i % count($words)];
                    $cfg['text'] = $word;
                    $round = intdiv($i, count($words));
                    if ($colors && $round > 0) {
                        $cfg = $this->applyColor($cfg, $colors[($round - 1) % count($colors)], 0);
                    }
                    if ($round > max(1, count($colors))) {
                        $cfg['animation']['effects'] = $this->effectCombo($effects, $round);
                    }
                    break;
                case 'manual':
                    $manual = array_values((array) ($batch['manual'] ?? []));
                    $type = $manual[$i % max(1, count($manual))] ?? $effects[$i % count($effects)];
                    if (!is_string($type) || !$this->effects->has($type)) {
                        $type = $effects[$i % count($effects)];
                    }
                    $cfg['animation']['effects'] = $this->varyParams([['type' => $type]], intdiv($i, max(1, count($manual))));
                    break;
                case 'random':
                    $n = mt_rand(1, min(2, count($effects)));
                    $pick = (array) array_rand(array_flip($effects), $n);
                    $cfg['animation']['effects'] = array_map(fn ($t) => ['type' => $t, 'params' => $this->randomParams($t)], $pick);
                    if ($colors && mt_rand(0, 1)) {
                        $cfg = $this->applyColor($cfg, $colors[mt_rand(0, count($colors) - 1)], mt_rand(0, 3));
                    }
                    break;
                default: // auto_combo
                    $nc = max(1, count($colors));
                    $color = $colors[$i % $nc] ?? null;
                    $cfg['animation']['effects'] = $this->effectCombo($effects, intdiv($i, $nc) + $i);
                    if ($color !== null) {
                        $cfg = $this->applyColor($cfg, $color, intdiv($i, $nc * max(1, count($effects))));
                    }
                    if (!empty($base['image']) && $i % 3 === 2) {
                        $cfg = $this->applyFrame($cfg, $frames[$i % count($frames)], $color);
                    }
            }
            if (!$add($cfg, $this->label($cfg))) {
                // collision: perturb parameters until unique
                for ($k = 1; $k < 8; $k++) {
                    $cfg['animation']['effects'] = $this->varyParams((array) ($cfg['animation']['effects'] ?? [['type' => $effects[0]]]), $i * 7 + $k);
                    if ($add($cfg, $this->label($cfg))) {
                        break;
                    }
                }
            }
        }
        return $variants;
    }

    /** @param array<string,mixed> $config */
    public static function hash(array $config): string
    {
        $c = $config;
        unset($c['image']['path'], $c['batch']);
        return sha1((string) json_encode(self::sortKeys($c)));
    }

    private static function sortKeys(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        if (!array_is_list($v)) {
            ksort($v);
        }
        return array_map([self::class, 'sortKeys'], $v);
    }

    /**
     * Single effects first, then pairs (e.g. pulse+glow, spin+orbit).
     * @param list<string> $effects
     * @return list<array<string,mixed>>
     */
    private function effectCombo(array $effects, int $i): array
    {
        $n = count($effects);
        if ($i < $n) {
            return [['type' => $effects[$i], 'params' => []]];
        }
        $pairs = [];
        for ($a = 0; $a < $n; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                if ($this->compatible($effects[$a], $effects[$b])) {
                    $pairs[] = [$effects[$a], $effects[$b]];
                }
            }
        }
        $j = $i - $n;
        if ($pairs && $j < count($pairs)) {
            return [['type' => $pairs[$j][0], 'params' => []], ['type' => $pairs[$j][1], 'params' => []]];
        }
        // beyond pairs: single effects with parameter variations
        return $this->varyParams([['type' => $effects[$i % $n]]], intdiv($i, $n));
    }

    private function compatible(string $a, string $b): bool
    {
        $rotating = ['spin', 'flip3d', 'swing', 'tilt3d'];
        $entering = ['fade', 'rise', 'slide', 'pop', 'typewriter'];
        if (in_array($a, $rotating, true) && in_array($b, $rotating, true)) {
            return false;
        }
        if (in_array($a, $entering, true) && in_array($b, $entering, true)) {
            return false;
        }
        return true;
    }

    /**
     * Varies intensity / speed / direction / style of the given effects.
     * @param list<array<string,mixed>> $effects
     * @return list<array<string,mixed>>
     */
    private function varyParams(array $effects, int $round): array
    {
        $out = [];
        $intensities = [0.65, 1.0, 0.4, 0.85];
        $speeds = [1.0, 1.5, 0.75, 2.0];
        foreach ($effects as $e) {
            $type = (string) ($e['type'] ?? '');
            if (!$this->effects->has($type)) {
                continue;
            }
            $schema = $this->effects->get($type)->schema();
            $params = (array) ($e['params'] ?? []);
            $params['intensity'] = $intensities[$round % 4];
            $params['speed'] = $speeds[intdiv($round, 4) % 4];
            foreach (['direction', 'style'] as $key) {
                if (isset($schema[$key]['options'])) {
                    $opts = $schema[$key]['options'];
                    $params[$key] = $opts[($round + ($key === 'style' ? 1 : 0)) % count($opts)];
                }
            }
            $out[] = ['type' => $type, 'params' => $params, 'target' => $e['target'] ?? 'all'];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function randomParams(string $type): array
    {
        $schema = $this->effects->get($type)->schema();
        $p = ['intensity' => mt_rand(35, 100) / 100, 'speed' => [0.75, 1.0, 1.5, 2.0][mt_rand(0, 3)]];
        foreach (['direction', 'style'] as $key) {
            if (isset($schema[$key]['options'])) {
                $p[$key] = $schema[$key]['options'][mt_rand(0, count($schema[$key]['options']) - 1)];
            }
        }
        return $p;
    }

    /**
     * Applies a colour to the most visible part of the design.
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function applyColor(array $cfg, string $color, int $variant): array
    {
        $hasBg = ($cfg['background']['type'] ?? 'none') !== 'none' || ($cfg['layout'] ?? '') === 'banner';
        $rgb = Color::hexToRgb($color);
        if ($hasBg) {
            $cfg['background']['color'] = $color;
            $cfg['background']['gradient'] = null;
            if (!isset($cfg['text_color']) || Color::luminance(Color::hexToRgb((string) $cfg['text_color'])) > 0.5 === Color::luminance($rgb) > 0.6) {
                $cfg['text_color'] = Color::rgbToHex(Color::contrast($rgb));
            }
        } else {
            $cfg['text_color'] = $color;
            $cfg['text_gradient'] = null;
            $cfg['outline']['color'] = Color::rgbToHex(Color::contrast($rgb));
        }
        if (!empty($cfg['glow']['enabled'])) {
            $cfg['glow']['color'] = $color;
        }
        foreach ((array) ($cfg['animation']['effects'] ?? []) as $k => $e) {
            if (in_array($e['type'] ?? '', ['glow', 'arrow', 'chart', 'orbit'], true)) {
                $cfg['animation']['effects'][$k]['params']['color'] = $e['type'] === 'orbit' ? Color::rgbToHex(Color::mix($rgb, [255, 255, 255], 0.3)) : $color;
            }
        }
        if ($variant % 2 === 1) {
            $cfg['text_gloss'] = true;
            $cfg['background']['gloss'] = $hasBg;
        }
        if ($variant % 3 === 2) {
            $cfg['text_depth'] = 'low';
        }
        return $cfg;
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function applyFrame(array $cfg, string $frame, ?string $color): array
    {
        $color ??= '#1D4ED8';
        $rgb = Color::hexToRgb($color);
        $light = in_array($frame, ['shield', 'circle', 'rounded'], true) && Color::luminance($rgb) < 0.5;
        $cfg['background'] = [
            'type' => $frame,
            'color' => $light ? '#FFFFFF' : $color,
            'gloss' => true,
            'depth' => 'low',
            'border' => ['width' => 'thin', 'color' => $light ? $color : Color::rgbToHex(Color::mix($rgb, [255, 255, 255], 0.55))],
        ];
        $cfg['outline']['width'] = $cfg['outline']['width'] ?? 'thin';
        return $cfg;
    }

    /** @param array<string,mixed> $cfg */
    private function label(array $cfg): string
    {
        $parts = [];
        foreach ((array) ($cfg['animation']['effects'] ?? []) as $e) {
            if ($this->effects->has((string) ($e['type'] ?? ''))) {
                $parts[] = $this->effects->get($e['type'])->nameEn();
            }
        }
        $color = $cfg['background']['color'] ?? ($cfg['text_color'] ?? null);
        $label = $parts ? implode(' + ', $parts) : 'Static';
        if (($cfg['background']['type'] ?? 'none') !== 'none') {
            $label .= ' • ' . (Shapes::TYPES[$cfg['background']['type']] ?? '');
        }
        if (is_string($color)) {
            $label .= ' • ' . Color::label($color);
        }
        if (isset($cfg['text']) && $cfg['text'] !== '') {
            $label = $cfg['text'] . ' • ' . $label;
        }
        return mb_substr($label, 0, 120);
    }
}
