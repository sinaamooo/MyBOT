<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * پالت رنگ کارت‌ها — پایه‌ی سفید با تأکید سبز و قرمز و حاشیه‌های مشکی.
 *
 * همه‌ی پالت‌ها روشن هستند تا کارت‌ها در حالت روشن تلگرام هم کاملاً خوانا باشند.
 */
final class Theme
{
    public const DEFAULT = 'light';

    public const PALETTES = [
        'light' => [
            'label'       => 'روشن (سفید، سبز، قرمز)',
            'bg_from'     => '#FFFFFF',
            'bg_to'       => '#F1F5F3',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F6F9F7',
            'glow'        => '#00A85C',
            'glow_alt'    => '#E11D3C',
            'accent'      => '#00A85C',
            'accent_alt'  => '#E11D3C',
            'ink'         => '#0A0F0D',
            'ink_dim'     => '#4C5A56',
            'ink_faint'   => '#7A8A86',
            'line'        => '#0A0F0D',
            'line_soft'   => '#D5DEDA',
            'shadow'      => '#16332A',
            'up'          => '#00A85C',
            'down'        => '#E11D3C',
            'flat'        => '#8A9794',
            'tint'        => '#0A0F0D',
        ],
        'snow' => [
            'label'       => 'برفی (سفید و طوسی)',
            'bg_from'     => '#FFFFFF',
            'bg_to'       => '#ECEFF4',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F4F6FA',
            'glow'        => '#00A05C',
            'glow_alt'    => '#E0233B',
            'accent'      => '#00A05C',
            'accent_alt'  => '#E0233B',
            'ink'         => '#090C11',
            'ink_dim'     => '#4A525F',
            'ink_faint'   => '#79828F',
            'line'        => '#090C11',
            'line_soft'   => '#D6DCE6',
            'shadow'      => '#1B2433',
            'up'          => '#00A05C',
            'down'        => '#E0233B',
            'flat'        => '#858E9C',
            'tint'        => '#090C11',
        ],
        'mint' => [
            'label'       => 'نعنایی (سفید و سبز)',
            'bg_from'     => '#FFFFFF',
            'bg_to'       => '#E8F5EE',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F0F9F4',
            'glow'        => '#009E57',
            'glow_alt'    => '#D91F39',
            'accent'      => '#009E57',
            'accent_alt'  => '#D91F39',
            'ink'         => '#06120C',
            'ink_dim'     => '#41564C',
            'ink_faint'   => '#72897E',
            'line'        => '#06120C',
            'line_soft'   => '#CCE2D7',
            'shadow'      => '#0C3A26',
            'up'          => '#009E57',
            'down'        => '#D91F39',
            'flat'        => '#7E9389',
            'tint'        => '#06120C',
        ],
        'paper' => [
            'label'       => 'کاغذی (سفید گرم)',
            'bg_from'     => '#FFFFFF',
            'bg_to'       => '#F3F0E9',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F8F5EE',
            'glow'        => '#0B9E5A',
            'glow_alt'    => '#D62839',
            'accent'      => '#0B9E5A',
            'accent_alt'  => '#D62839',
            'ink'         => '#12100B',
            'ink_dim'     => '#575143',
            'ink_faint'   => '#8A8271',
            'line'        => '#12100B',
            'line_soft'   => '#E0DACB',
            'shadow'      => '#3A3222',
            'up'          => '#0B9E5A',
            'down'        => '#D62839',
            'flat'        => '#968D7C',
            'tint'        => '#12100B',
        ],
    ];

    /** @var array<string,string> */
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

    public function isMono(): bool { return $this->name === 'snow'; }

    public function c(string $key, string $fallback = '#000000'): string
    {
        return $this->palette[$key] ?? $fallback;
    }

    /** رنگ روند: سبز برای رشد، قرمز برای افت */
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
