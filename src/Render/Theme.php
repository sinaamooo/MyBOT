<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * پالت رنگ کارت‌ها — پایه‌ی مشکی با شیشه‌ی سفید و تأکید سبز/آبی.
 */
final class Theme
{
    public const PALETTES = [
        'neo' => [
            'label'     => 'نئو (سبز و آبی)',
            'bg_from'   => '#03060A',
            'bg_to'     => '#071118',
            'glow'      => '#10E39B',
            'glow_alt'  => '#2F86FF',
            'accent'    => '#10E39B',
            'accent_alt'=> '#2F86FF',
            'ink'       => '#EDF6F2',
            'ink_dim'   => '#8EA3AC',
            'ink_faint' => '#5A6D77',
            'up'        => '#10E39B',
            'down'      => '#2F86FF',
            'flat'      => '#6B7C85',
            'tint'      => '#FFFFFF',
        ],
        'emerald' => [
            'label'     => 'امرالد (سبز)',
            'bg_from'   => '#020705',
            'bg_to'     => '#06150F',
            'glow'      => '#10E39B',
            'glow_alt'  => '#7BF7C4',
            'accent'    => '#10E39B',
            'accent_alt'=> '#7BF7C4',
            'ink'       => '#EDFBF4',
            'ink_dim'   => '#89A99A',
            'ink_faint' => '#557266',
            'up'        => '#10E39B',
            'down'      => '#5FB8FF',
            'flat'      => '#68867A',
            'tint'      => '#FFFFFF',
        ],
        'ocean' => [
            'label'     => 'اوشن (آبی)',
            'bg_from'   => '#03060D',
            'bg_to'     => '#07121F',
            'glow'      => '#2F86FF',
            'glow_alt'  => '#10E39B',
            'accent'    => '#2F86FF',
            'accent_alt'=> '#66D3FF',
            'ink'       => '#EAF2FB',
            'ink_dim'   => '#8B9DB4',
            'ink_faint' => '#586A82',
            'up'        => '#10E39B',
            'down'      => '#4DA3FF',
            'flat'      => '#6B7E94',
            'tint'      => '#FFFFFF',
        ],
        'mono' => [
            'label'     => 'مونو (سیاه و سفید)',
            'bg_from'   => '#050506',
            'bg_to'     => '#101214',
            'glow'      => '#FFFFFF',
            'glow_alt'  => '#C9D2D6',
            'accent'    => '#FFFFFF',
            'accent_alt'=> '#C9D2D6',
            'ink'       => '#F4F7F8',
            'ink_dim'   => '#9BA5AA',
            'ink_faint' => '#646E73',
            'up'        => '#FFFFFF',
            'down'      => '#7E888F',
            'flat'      => '#646E73',
            'tint'      => '#FFFFFF',
        ],
        'aurora' => [
            'label'     => 'آرورا (سبز تا آبی)',
            'bg_from'   => '#03080C',
            'bg_to'     => '#04161C',
            'glow'      => '#12E3B0',
            'glow_alt'  => '#3F7BFF',
            'accent'    => '#12E3B0',
            'accent_alt'=> '#3F7BFF',
            'ink'       => '#EAF7F6',
            'ink_dim'   => '#86A3A8',
            'ink_faint' => '#546E74',
            'up'        => '#12E3B0',
            'down'      => '#3F7BFF',
            'flat'      => '#657E84',
            'tint'      => '#FFFFFF',
        ],
    ];

    /** @var array<string,string> */
    private array $palette;
    private string $name;

    public function __construct(string $name = 'neo')
    {
        $this->name = isset(self::PALETTES[$name]) ? $name : 'neo';
        $this->palette = self::PALETTES[$this->name];
    }

    public function name(): string { return $this->name; }

    public function label(): string { return $this->palette['label']; }

    public function isMono(): bool { return $this->name === 'mono'; }

    public function c(string $key, string $fallback = '#FFFFFF'): string
    {
        return $this->palette[$key] ?? $fallback;
    }

    /** رنگ روند: سبز برای رشد، آبی برای افت */
    public function trend(float $change): string
    {
        if (abs($change) < 0.005) {
            return $this->c('flat');
        }

        return $change > 0 ? $this->c('up') : $this->c('down');
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::PALETTES as $key => $palette) {
            $out[$key] = $palette['label'];
        }

        return $out;
    }
}
