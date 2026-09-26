<?php
declare(strict_types=1);

namespace Nikto\Render;

final class Theme
{
    public const DEFAULT = 'glass';

    public const PALETTES = [
        'glass' => [
            'label'       => 'شیشه‌ای (سفید و گرافیتی)',
            'bg_from'     => '#F8F8F6',
            'bg_to'       => '#ECECE9',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#EFEFEC',
            'glass'       => '0.86',
            'smoke'       => '#1E1E1E',
            'glow'        => '#FFFFFF',
            'glow_alt'    => '#1E1E1E',
            'accent'      => '#111111',
            'accent_alt'  => '#6E6E6B',
            'ink'         => '#111111',
            'ink_dim'     => '#4A4A48',
            'ink_faint'   => '#767673',
            'line'        => '#111111',
            'line_soft'   => '#D6D6D2',
            'shadow'      => '#141414',
            'up'          => '#0F6B42',
            'down'        => '#E0605A',
            'flat'        => '#9A9A96',
            'tint'        => '#111111',
        ],
        'mono' => [
            'label'       => 'مونو (فقط سیاه و سفید)',
            'bg_from'     => '#F9F9F9',
            'bg_to'       => '#EBEBEB',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#EEEEEE',
            'glass'       => '0.86',
            'smoke'       => '#151515',
            'glow'        => '#FFFFFF',
            'glow_alt'    => '#151515',
            'accent'      => '#0D0D0D',
            'accent_alt'  => '#707070',
            'ink'         => '#0D0D0D',
            'ink_dim'     => '#474747',
            'ink_faint'   => '#767676',
            'line'        => '#0D0D0D',
            'line_soft'   => '#D4D4D4',
            'shadow'      => '#101010',
            'up'          => '#0D0D0D',
            'down'        => '#8A8A8A',
            'flat'        => '#B5B5B5',
            'tint'        => '#0D0D0D',
        ],
        'vivid' => [
            'label'       => 'شیشه‌ای سبز و قرمز',
            'bg_from'     => '#FFFFFF',
            'bg_to'       => '#E8ECEA',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F1F4F2',
            'glass'       => '0.86',
            'smoke'       => '#1B2622',
            'glow'        => '#FFFFFF',
            'glow_alt'    => '#1B2622',
            'accent'      => '#0A0F0D',
            'accent_alt'  => '#F0364F',
            'ink'         => '#0A0F0D',
            'ink_dim'     => '#4C5A56',
            'ink_faint'   => '#7A8A86',
            'line'        => '#0A0F0D',
            'line_soft'   => '#D5DEDA',
            'shadow'      => '#16332A',
            'up'          => '#008A4B',
            'down'        => '#F0364F',
            'flat'        => '#8A9794',
            'tint'        => '#0A0F0D',
        ],
    ];

    private array $palette;
    private string $name;

    public function __construct(?string $name = null)
    {
        $name ??= self::DEFAULT;
        $this->name = isset(self::PALETTES[$name]) ? $name : self::DEFAULT;
        $this->palette = self::PALETTES[$this->name];
    }

    public function name(): string { return $this->name; }

    public function label(): string { return $this->palette['label']; }

    public function isMono(): bool { return $this->name === 'mono'; }

    public function glassAlpha(): float
    {
        return (float) ($this->palette['glass'] ?? 0.6);
    }

    public function c(string $key, string $fallback = '#000000'): string
    {
        return $this->palette[$key] ?? $fallback;
    }

    public function trend(float $change): string
    {
        if (abs($change) < 0.005) {
            return $this->c('flat');
        }

        return $change > 0 ? $this->c('up') : $this->c('down');
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::PALETTES as $key => $palette) {
            $out[$key] = $palette['label'];
        }

        return $out;
    }
}
