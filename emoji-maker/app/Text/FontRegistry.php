<?php

declare(strict_types=1);

namespace EmojiMaker\Text;

use EmojiMaker\Text\Font\TrueTypeFont;
use EmojiMaker\Text\Shaping\TextShaper;

/**
 * Known fonts (bundled OFL fonts + admin uploaded TrueType fonts).
 * Every shaper gets a fallback chain so Persian letters and symbols always
 * render even when the chosen font is Latin only.
 */
final class FontRegistry
{
    public const BUILTIN = [
        'vazirmatn-black' => ['name' => 'وزیرمتن مشکی', 'file' => 'Vazirmatn-Black.ttf', 'persian' => true],
        'vazirmatn-bold' => ['name' => 'وزیرمتن ضخیم', 'file' => 'Vazirmatn-Bold.ttf', 'persian' => true],
        'vazirmatn' => ['name' => 'وزیرمتن معمولی', 'file' => 'Vazirmatn-Regular.ttf', 'persian' => true],
        'lalezar' => ['name' => 'لاله‌زار (نمایشی)', 'file' => 'Lalezar-Regular.ttf', 'persian' => true],
        'anton' => ['name' => 'Anton (لاتین فشرده)', 'file' => 'Anton-Regular.ttf', 'persian' => false],
        'lilita' => ['name' => 'Lilita One (لاتین گرد)', 'file' => 'LilitaOne-Regular.ttf', 'persian' => false],
        'russo' => ['name' => 'Russo One (لاتین اسپرت)', 'file' => 'RussoOne-Regular.ttf', 'persian' => false],
        'bebas' => ['name' => 'Bebas Neue (لاتین بلند)', 'file' => 'BebasNeue-Regular.ttf', 'persian' => false],
        'bungee' => ['name' => 'Bungee (لاتین تابلویی)', 'file' => 'Bungee-Regular.ttf', 'persian' => false],
        'luckiest' => ['name' => 'Luckiest Guy (لاتین کارتونی)', 'file' => 'LuckiestGuy-Regular.ttf', 'persian' => false],
        'blackops' => ['name' => 'Black Ops One (لاتین نظامی)', 'file' => 'BlackOpsOne-Regular.ttf', 'persian' => false],
    ];

    /** Default font for English-only text (when the user did not choose one). */
    public const DEFAULT_LATIN = 'russo';

    private const PERSIAN_FALLBACK = 'vazirmatn-black';
    private const SYMBOL_FALLBACK_FILE = 'DejaVuSans-Bold.ttf';

    /** @var array<string,array{name:string,file:string,persian:bool}> */
    private array $fonts = [];
    /** @var array<string,TrueTypeFont> */
    private array $loaded = [];
    private static ?self $default = null;

    /**
     * @param array<string,array{name:string,file:string,persian:bool}> $extra fonts from the database (absolute paths)
     */
    public function __construct(private string $resourceDir, array $extra = [])
    {
        foreach (self::BUILTIN as $slug => $def) {
            $this->fonts[$slug] = ['name' => $def['name'], 'file' => $resourceDir . '/' . $def['file'], 'persian' => $def['persian']];
        }
        foreach ($extra as $slug => $def) {
            if (preg_match('/^[a-z0-9-]{2,48}$/', $slug) && is_file($def['file'])) {
                $this->fonts[$slug] = $def;
            }
        }
    }

    public static function default(): self
    {
        return self::$default ??= new self(dirname(__DIR__, 2) . '/resources/fonts');
    }

    public static function setDefault(self $registry): void
    {
        self::$default = $registry;
    }

    public function has(string $slug): bool
    {
        return isset($this->fonts[$slug]);
    }

    /** @return array<string,array{name:string,file:string,persian:bool}> */
    public function all(): array
    {
        return $this->fonts;
    }

    public function defaultSlug(): string
    {
        return self::PERSIAN_FALLBACK;
    }

    /** Picks a font for a text: Latin display font for English text, Persian font otherwise. */
    public function autoSlug(string $text): string
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FEFF}]/u', $text) ? self::PERSIAN_FALLBACK : self::DEFAULT_LATIN;
    }

    public function load(string $slug): TrueTypeFont
    {
        if (!isset($this->fonts[$slug])) {
            $slug = self::PERSIAN_FALLBACK;
        }
        return $this->loaded[$slug] ??= new TrueTypeFont($this->fonts[$slug]['file']);
    }

    /** @return list<TrueTypeFont> primary + fallbacks */
    public function chain(string $slug): array
    {
        $chain = [$this->load($slug)];
        if ($slug !== self::PERSIAN_FALLBACK) {
            $chain[] = $this->load(self::PERSIAN_FALLBACK);
        }
        $symbol = $this->resourceDir . '/' . self::SYMBOL_FALLBACK_FILE;
        if (is_file($symbol)) {
            $chain[] = $this->loaded['__symbols'] ??= new TrueTypeFont($symbol);
        }
        return $chain;
    }

    public function shaper(string $slug): TextShaper
    {
        return new TextShaper($this->chain($slug));
    }

    public function name(string $slug): string
    {
        return $this->fonts[$slug]['name'] ?? $slug;
    }
}
