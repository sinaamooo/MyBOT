<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * پالت رنگ کارت‌ها. هر Job می‌تواند تم خودش را داشته باشد.
 */
final class Theme
{
    public const PALETTES = [
        'aurora' => [
            'label'     => 'آرورا (بنفش/آبی نئونی)',
            'bg_from'   => '#0B0A1E',
            'bg_to'     => '#170B2B',
            'glow_a'    => '#7C3AED',
            'glow_b'    => '#22D3EE',
            'glow_c'    => '#E946C8',
            'neon'      => '#38BDF8',
            'neon_alt'  => '#E946C8',
            'plate'     => '#140F2E',
            'panel'     => '#F5F7FC',
            'panel_alt' => '#EAEEF7',
            'ink'       => '#111834',
            'ink_soft'  => '#5C6784',
            'value'     => '#1D6FE0',
            'line'      => '#D8DFEC',
            'up'        => '#0FA36B',
            'down'      => '#E23A45',
            'flat'      => '#7A8598',
            'brand_ink' => '#EAF2FF',
        ],
        'crimson' => [
            'label'     => 'کریمسون (قرمز/مشکی)',
            'bg_from'   => '#0B0B0E',
            'bg_to'     => '#1A0A0E',
            'glow_a'    => '#FF1E3C',
            'glow_b'    => '#7A0A18',
            'glow_c'    => '#FF6B3D',
            'neon'      => '#FF2340',
            'neon_alt'  => '#FF8A5B',
            'plate'     => '#150609',
            'panel'     => '#F7F7F9',
            'panel_alt' => '#ECEDF2',
            'ink'       => '#15161C',
            'ink_soft'  => '#5E6270',
            'value'     => '#C81E2E',
            'line'      => '#DCDEE6',
            'up'        => '#12A05F',
            'down'      => '#E01E33',
            'flat'      => '#79808F',
            'brand_ink' => '#FFE8EA',
        ],
        'emerald' => [
            'label'     => 'امرالد (سبز/فیروزه‌ای)',
            'bg_from'   => '#04120F',
            'bg_to'     => '#07211C',
            'glow_a'    => '#10E08F',
            'glow_b'    => '#22D3EE',
            'glow_c'    => '#A3E635',
            'neon'      => '#14E1A0',
            'neon_alt'  => '#22D3EE',
            'plate'     => '#04160F',
            'panel'     => '#F4F9F7',
            'panel_alt' => '#E6F0EC',
            'ink'       => '#0B1F1A',
            'ink_soft'  => '#4E6B63',
            'value'     => '#0E8F63',
            'line'      => '#D3E2DC',
            'up'        => '#0FA36B',
            'down'      => '#E23A45',
            'flat'      => '#75887F',
            'brand_ink' => '#E6FFF6',
        ],
        'gold' => [
            'label'     => 'گلد (طلایی/شب)',
            'bg_from'   => '#0B0A12',
            'bg_to'     => '#1B1406',
            'glow_a'    => '#F5C542',
            'glow_b'    => '#FF8A3D',
            'glow_c'    => '#7C3AED',
            'neon'      => '#F7C948',
            'neon_alt'  => '#FF9F45',
            'plate'     => '#140F05',
            'panel'     => '#FAF8F3',
            'panel_alt' => '#F0ECE1',
            'ink'       => '#1A1608',
            'ink_soft'  => '#6B6350',
            'value'     => '#B7791F',
            'line'      => '#E4DDCB',
            'up'        => '#12A05F',
            'down'      => '#DE3B2F',
            'flat'      => '#8A8270',
            'brand_ink' => '#FFF4D6',
        ],
        'ocean' => [
            'label'     => 'اوشن (آبی عمیق)',
            'bg_from'   => '#050C1C',
            'bg_to'     => '#07172F',
            'glow_a'    => '#2563EB',
            'glow_b'    => '#22D3EE',
            'glow_c'    => '#4F46E5',
            'neon'      => '#38BDF8',
            'neon_alt'  => '#60A5FA',
            'plate'     => '#061029',
            'panel'     => '#F4F8FD',
            'panel_alt' => '#E6EEF9',
            'ink'       => '#0A1B33',
            'ink_soft'  => '#51648A',
            'value'     => '#1B62D6',
            'line'      => '#D5E0F0',
            'up'        => '#0EA06A',
            'down'      => '#E03A47',
            'flat'      => '#71809B',
            'brand_ink' => '#E4F1FF',
        ],
    ];

    /** @var array<string,string> */
    private array $palette;
    private string $name;

    public function __construct(string $name = 'aurora')
    {
        $this->name = isset(self::PALETTES[$name]) ? $name : 'aurora';
        $this->palette = self::PALETTES[$this->name];
    }

    public function name(): string { return $this->name; }

    public function label(): string { return $this->palette['label']; }

    public function c(string $key, string $fallback = '#FFFFFF'): string
    {
        return $this->palette[$key] ?? $fallback;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::PALETTES as $key => $p) {
            $out[$key] = $p['label'];
        }
        return $out;
    }
}
