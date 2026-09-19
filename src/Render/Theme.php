<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * پالت رنگ کارت‌ها — همه‌ی تم‌ها تیره و شیشه‌ای هستند.
 * تم پیش‌فرض «مونو» کاملاً سیاه‌وسفید است.
 */
final class Theme
{
    public const PALETTES = [
        'mono' => [
            'label'    => 'مونو (سیاه و سفید)',
            'bg_from'  => '#070709',
            'bg_to'    => '#111319',
            'glow'     => '#FFFFFF',
            'glow_alt' => '#C8CCD4',
            'accent'   => '#FFFFFF',
            'ink'      => '#F4F6FA',
            'ink_dim'  => '#9EA5B2',
            'ink_faint'=> '#646B78',
            'up'       => '#FFFFFF',
            'down'     => '#7E8695',
            'flat'     => '#646B78',
            'tint'     => '#FFFFFF',
        ],
        'aurora' => [
            'label'    => 'آرورا (بنفش/آبی)',
            'bg_from'  => '#07060F',
            'bg_to'    => '#130E24',
            'glow'     => '#7C5CFF',
            'glow_alt' => '#22D3EE',
            'accent'   => '#A78BFA',
            'ink'      => '#F3F2FB',
            'ink_dim'  => '#A3A0BE',
            'ink_faint'=> '#6B6889',
            'up'       => '#3DDC97',
            'down'     => '#FF5C7A',
            'flat'     => '#7A7796',
            'tint'     => '#C9C4FF',
        ],
        'crimson' => [
            'label'    => 'کریمسون (قرمز/مشکی)',
            'bg_from'  => '#080607',
            'bg_to'    => '#18090D',
            'glow'     => '#FF2340',
            'glow_alt' => '#FF7A4D',
            'accent'   => '#FF4D63',
            'ink'      => '#FAF3F4',
            'ink_dim'  => '#B0A0A4',
            'ink_faint'=> '#726367',
            'up'       => '#35D08A',
            'down'     => '#FF5062',
            'flat'     => '#7A6E71',
            'tint'     => '#FFD9DE',
        ],
        'emerald' => [
            'label'    => 'امرالد (سبز/فیروزه‌ای)',
            'bg_from'  => '#040A09',
            'bg_to'    => '#0A1D1A',
            'glow'     => '#12E29A',
            'glow_alt' => '#22D3EE',
            'accent'   => '#3DE9AE',
            'ink'      => '#F0FBF7',
            'ink_dim'  => '#95B2AA',
            'ink_faint'=> '#5F7B74',
            'up'       => '#3DE9AE',
            'down'     => '#FF6076',
            'flat'     => '#6E8A83',
            'tint'     => '#C7FFEE',
        ],
        'gold' => [
            'label'    => 'گلد (طلایی/شب)',
            'bg_from'  => '#08070A',
            'bg_to'    => '#1A1408',
            'glow'     => '#F7C948',
            'glow_alt' => '#FF9F45',
            'accent'   => '#F7C948',
            'ink'      => '#FBF7EE',
            'ink_dim'  => '#B3A992',
            'ink_faint'=> '#7A725F',
            'up'       => '#4ADE9B',
            'down'     => '#FF6262',
            'flat'     => '#7E765F',
            'tint'     => '#FFE9B8',
        ],
    ];

    /** @var array<string,string> */
    private array $palette;
    private string $name;

    public function __construct(string $name = 'mono')
    {
        $this->name = isset(self::PALETTES[$name]) ? $name : 'mono';
        $this->palette = self::PALETTES[$this->name];
    }

    public function name(): string { return $this->name; }

    public function label(): string { return $this->palette['label']; }

    public function isMono(): bool { return $this->name === 'mono'; }

    public function c(string $key, string $fallback = '#FFFFFF'): string
    {
        return $this->palette[$key] ?? $fallback;
    }

    /** رنگ روند بر اساس تغییر قیمت */
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
