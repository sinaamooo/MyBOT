<?php
declare(strict_types=1);

namespace Nikto\Render;

use GdImage;
use Nikto\Text\Persian;

/**
 * لایه‌ی رسم روی GD با کیفیت بالا.
 * همه‌ی مختصات «منطقی» هستند و داخلی در ضریب سوپرسمپلینگ ضرب می‌شوند،
 * بنابراین خروجی نهایی لبه‌های نرم و تمیز دارد.
 */
final class Canvas
{
    public const W_REGULAR  = 'Regular';
    public const W_MEDIUM   = 'Medium';
    public const W_SEMIBOLD = 'SemiBold';
    public const W_BOLD     = 'Bold';
    public const W_EXTRA    = 'ExtraBold';
    public const W_BLACK    = 'Black';

    private GdImage $im;
    private int $scale;
    private int $width;
    private int $height;
    private float $outputScale = 1.0;

    public function __construct(int $width, int $height, int $scale = 2, ?string $background = null)
    {
        $this->width  = $width;
        $this->height = $height;
        $this->scale  = max(1, min(4, $scale));

        $im = imagecreatetruecolor($width * $this->scale, $height * $this->scale);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefilledrectangle(
            $im,
            0,
            0,
            imagesx($im),
            imagesy($im),
            imagecolorallocatealpha($im, 0, 0, 0, 127)
        );
        imagealphablending($im, true);
        $this->im = $im;

        if ($background !== null) {
            $this->fill($background);
        }
    }

    public static function fromFile(string $path, int $scale = 1): self
    {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (!$src) {
            throw new \RuntimeException('Unable to read image: ' . $path);
        }
        $canvas = new self((int) (imagesx($src) / $scale), (int) (imagesy($src) / $scale), $scale);
        imagecopy($canvas->im, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imagedestroy($src);

        return $canvas;
    }

    /**
     * ضریب بزرگ‌نمایی خروجی نهایی.
     * مثلاً ۲ یعنی کارت ۱۲۰۰ پیکسلی با عرض ۲۴۰۰ ذخیره می‌شود.
     */
    public function setOutputScale(float $scale): void
    {
        $this->outputScale = max(0.25, min(4.0, $scale));
    }

    public function gd(): GdImage { return $this->im; }
    public function width(): int  { return $this->width; }
    public function height(): int { return $this->height; }
    public function scale(): int  { return $this->scale; }

    private function s(float $v): int
    {
        return (int) round($v * $this->scale);
    }

    // ---------------------------------------------------------------- رنگ‌ها

    /** @return array{0:int,1:int,2:int,3:int} */
    public static function parseColor(string $hex, float $alpha = 1.0): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) === 8) { // RRGGBBAA
            $alpha *= hexdec(substr($hex, 6, 2)) / 255;
            $hex = substr($hex, 0, 6);
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            $hex = '000000';
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
            (int) round(127 * (1 - max(0.0, min(1.0, $alpha)))),
        ];
    }

    public function color(string $hex, float $alpha = 1.0): int
    {
        [$r, $g, $b, $a] = self::parseColor($hex, $alpha);
        return imagecolorallocatealpha($this->im, $r, $g, $b, $a);
    }

    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::parseColor($a);
        [$r2, $g2, $b2] = self::parseColor($b);
        $t = max(0.0, min(1.0, $t));
        return sprintf(
            '#%02X%02X%02X',
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t)
        );
    }

    // ------------------------------------------------------------- شکل‌ها

    public function fill(string $hex, float $alpha = 1.0): void
    {
        imagealphablending($this->im, true);
        imagefilledrectangle($this->im, 0, 0, imagesx($this->im), imagesy($this->im), $this->color($hex, $alpha));
    }

    public function rect(float $x, float $y, float $w, float $h, string $hex, float $alpha = 1.0): void
    {
        imagefilledrectangle(
            $this->im,
            $this->s($x),
            $this->s($y),
            $this->s($x + $w) - 1,
            $this->s($y + $h) - 1,
            $this->color($hex, $alpha)
        );
    }

    /**
     * مستطیل گِرد.
     * وقتی رنگ نیمه‌شفاف است، شکل روی یک لایه‌ی جدا ساخته و یک‌باره ترکیب می‌شود
     * تا محل هم‌پوشانی گوشه‌ها دوبار رنگ نگیرد.
     */
    public function roundRect(float $x, float $y, float $w, float $h, float $r, string $hex, float $alpha = 1.0): void
    {
        [$red, $green, $blue, $a] = self::parseColor($hex, $alpha);
        if ($a <= 0) {
            $this->roundRectColor($this->im, $this->s($x), $this->s($y), $this->s($w), $this->s($h), $this->s($r), $this->color($hex, $alpha));
            return;
        }
        if ($a >= 127) {
            return; // کاملاً شفاف
        }

        $lw = max(1, $this->s($w));
        $lh = max(1, $this->s($h));
        $layer = imagecreatetruecolor($lw, $lh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $lw, $lh, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        $col = imagecolorallocatealpha($layer, $red, $green, $blue, $a);
        $this->roundRectColor($layer, 0, 0, $lw, $lh, $this->s($r), $col);

        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $this->s($x), $this->s($y), 0, 0, $lw, $lh);
        imagedestroy($layer);
    }

    private function roundRectColor(GdImage $im, int $x, int $y, int $w, int $h, int $rs, int $col): void
    {
        $x2 = $x + $w - 1;
        $y2 = $y + $h - 1;
        $rs = max(0, min($rs, (int) (min($w, $h) / 2)));

        if ($rs <= 0) {
            imagefilledrectangle($im, $x, $y, $x2, $y2, $col);
            return;
        }
        imagefilledrectangle($im, $x + $rs, $y, $x2 - $rs, $y2, $col);
        imagefilledrectangle($im, $x, $y + $rs, $x2, $y2 - $rs, $col);
        $d = $rs * 2;
        imagefilledellipse($im, $x + $rs, $y + $rs, $d, $d, $col);
        imagefilledellipse($im, $x2 - $rs, $y + $rs, $d, $d, $col);
        imagefilledellipse($im, $x + $rs, $y2 - $rs, $d, $d, $col);
        imagefilledellipse($im, $x2 - $rs, $y2 - $rs, $d, $d, $col);
    }

    /** خط دور مستطیل گِرد (یک‌بار ترکیب می‌شود تا گوشه‌ها پررنگ‌تر نشوند) */
    public function strokeRoundRect(
        float $x,
        float $y,
        float $w,
        float $h,
        float $r,
        string $hex,
        float $thickness = 1,
        float $alpha = 1.0
    ): void {
        [$red, $green, $blue, $a] = self::parseColor($hex, $alpha);
        if ($a >= 127) {
            return;
        }
        $t = max(1, $this->s($thickness));
        $pad = $t + 2;
        $lw = max(1, $this->s($w) + $pad * 2);
        $lh = max(1, $this->s($h) + $pad * 2);

        $layer = imagecreatetruecolor($lw, $lh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $lw, $lh, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        $col = imagecolorallocatealpha($layer, $red, $green, $blue, $a);

        $x1 = $pad;
        $y1 = $pad;
        $x2 = $pad + $this->s($w) - 1;
        $y2 = $pad + $this->s($h) - 1;
        $rs = max(0, min($this->s($r), (int) (min($this->s($w), $this->s($h)) / 2)));

        imagesetthickness($layer, $t);
        imageline($layer, $x1 + $rs, $y1, $x2 - $rs, $y1, $col);
        imageline($layer, $x1 + $rs, $y2, $x2 - $rs, $y2, $col);
        imageline($layer, $x1, $y1 + $rs, $x1, $y2 - $rs, $col);
        imageline($layer, $x2, $y1 + $rs, $x2, $y2 - $rs, $col);
        if ($rs > 0) {
            $d = $rs * 2;
            imagearc($layer, $x1 + $rs, $y1 + $rs, $d, $d, 180, 270, $col);
            imagearc($layer, $x2 - $rs, $y1 + $rs, $d, $d, 270, 360, $col);
            imagearc($layer, $x1 + $rs, $y2 - $rs, $d, $d, 90, 180, $col);
            imagearc($layer, $x2 - $rs, $y2 - $rs, $d, $d, 0, 90, $col);
        }
        imagesetthickness($layer, 1);

        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $this->s($x) - $pad, $this->s($y) - $pad, 0, 0, $lw, $lh);
        imagedestroy($layer);
    }

    /** گرادیان خطی (dir: v عمودی، h افقی، d مورب) با ماسک مستطیل گِرد */
    public function gradient(
        float $x,
        float $y,
        float $w,
        float $h,
        string $from,
        string $to,
        string $dir = 'v',
        float $radius = 0,
        float $alpha = 1.0
    ): void {
        [$r1, $g1, $b1] = self::parseColor($from);
        [$r2, $g2, $b2] = self::parseColor($to);
        $a = (int) round(127 * (1 - max(0.0, min(1.0, $alpha))));

        $x1 = $this->s($x);
        $y1 = $this->s($y);
        $w1 = max(1, $this->s($w));
        $h1 = max(1, $this->s($h));

        $layer = imagecreatetruecolor($w1, $h1);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        $steps = $dir === 'h' ? $w1 : $h1;
        for ($i = 0; $i < $steps; $i++) {
            $t = $steps > 1 ? $i / ($steps - 1) : 0.0;
            $col = imagecolorallocatealpha(
                $layer,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t),
                $a
            );
            if ($dir === 'h') {
                imagefilledrectangle($layer, $i, 0, $i, $h1, $col);
            } elseif ($dir === 'd') {
                imagefilledrectangle($layer, 0, $i, $w1, $i, $col);
            } else {
                imagefilledrectangle($layer, 0, $i, $w1, $i, $col);
            }
        }
        if ($dir === 'd') {
            $rot = imagerotate($layer, -20, imagecolorallocatealpha($layer, 0, 0, 0, 127));
            if ($rot) {
                $cx = (int) ((imagesx($rot) - $w1) / 2);
                $cy = (int) ((imagesy($rot) - $h1) / 2);
                $tmp = imagecreatetruecolor($w1, $h1);
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
                imagecopy($tmp, $rot, 0, 0, max(0, $cx), max(0, $cy), $w1, $h1);
                imagedestroy($rot);
                imagedestroy($layer);
                $layer = $tmp;
            }
        }

        if ($radius > 0) {
            $this->applyRoundMask($layer, $this->s($radius));
        }
        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $x1, $y1, 0, 0, $w1, $h1);
        imagedestroy($layer);
    }

    /** گرادیان شعاعی (درخشش) */
    public function radialGlow(float $cx, float $cy, float $radius, string $hex, float $alpha = 0.6, int $steps = 72): void
    {
        $steps = max(8, $steps);
        $perStep = 28 / $steps; // تعداد گام بیشتر = گرادیان نرم‌تر بدون پررنگ‌شدن
        for ($i = $steps; $i > 0; $i--) {
            $t = $i / $steps;
            $rr = $radius * $t;
            $a = $alpha * (1 - $t) ** 1.7 * $perStep;
            if ($a <= 0.004) {
                continue;
            }
            imagefilledellipse(
                $this->im,
                $this->s($cx),
                $this->s($cy),
                $this->s($rr * 2),
                $this->s($rr * 2),
                $this->color($hex, $a)
            );
        }
    }

    /** سایه‌ی نرم زیر یک مستطیل گِرد */
    public function shadow(
        float $x,
        float $y,
        float $w,
        float $h,
        float $radius,
        string $hex = '#000000',
        float $alpha = 0.35,
        float $blur = 18,
        float $offsetY = 6
    ): void {
        $pad = (int) ($blur * 2);
        $lw = max(4, $this->s($w + $pad * 2));
        $lh = max(4, $this->s($h + $pad * 2));
        $down = 4; // سایه در ابعاد کوچک‌تر محو می‌شود (سریع‌تر و نرم‌تر)
        $sw = max(4, (int) ($lw / $down));
        $sh = max(4, (int) ($lh / $down));

        $layer = imagecreatetruecolor($sw, $sh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $sw, $sh, imagecolorallocatealpha($layer, 0, 0, 0, 127));

        [$r, $g, $b] = self::parseColor($hex);
        $col = imagecolorallocatealpha($layer, $r, $g, $b, (int) round(127 * (1 - $alpha)));
        $rx = (int) ($this->s($pad) / $down);
        $rw = (int) ($this->s($w) / $down);
        $rh = (int) ($this->s($h) / $down);
        $rr = max(1, (int) ($this->s($radius) / $down));
        $this->roundRectColor($layer, $rx, $rx, $rw, $rh, $rr, $col);
        imagealphablending($layer, true);

        $passes = max(1, (int) round($blur / 3));
        for ($i = 0; $i < $passes; $i++) {
            imagefilter($layer, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagealphablending($this->im, true);
        imagecopyresampled(
            $this->im,
            $layer,
            $this->s($x - $pad),
            $this->s($y - $pad + $offsetY),
            0,
            0,
            $lw,
            $lh,
            $sw,
            $sh
        );
        imagedestroy($layer);
    }

    /**
     * جعبه‌ی شیشه‌ای: پس‌زمینه را محو می‌کند و روی آن لایه‌ی نیمه‌شفاف می‌کشد.
     *
     * @param array{fill?:float,border?:float,blur?:int,highlight?:bool,tint?:string,shadow?:bool} $options
     */
    public function glass(float $x, float $y, float $w, float $h, float $radius = 16, array $options = []): void
    {
        $fill      = (float) ($options['fill'] ?? 0.055);
        $border    = (float) ($options['border'] ?? 0.13);
        $blur      = (int) ($options['blur'] ?? 3);
        $highlight = (bool) ($options['highlight'] ?? true);
        $tint      = (string) ($options['tint'] ?? '#FFFFFF');
        $shadow    = (bool) ($options['shadow'] ?? true);

        if ($shadow) {
            $this->shadow($x, $y, $w, $h, $radius, '#000000', 0.30, 14, 5);
        }
        if ($blur > 0) {
            $this->backdropBlur($x, $y, $w, $h, $radius, $blur);
        }

        $this->roundRect($x, $y, $w, $h, $radius, $tint, $fill);
        if ($highlight) {
            // بازتاب نور در بالای جعبه (محوشونده، بدون لبه‌ی تیز)
            $band = min($h * 0.5, 56.0);
            for ($i = 0; $i < $band; $i += 2) {
                $a = 0.05 * (1 - $i / $band) ** 2;
                if ($a < 0.003) {
                    break;
                }
                $inset = $i < $radius ? ($radius - sqrt(max(0.0, $radius ** 2 - ($radius - $i) ** 2))) : 0.0;
                $this->rect($x + $inset + 1, $y + $i + 1, $w - ($inset + 1) * 2, 2, '#FFFFFF', $a);
            }
            $this->line($x + $radius * 0.8, $y + 0.9, $x + $w - $radius * 0.8, $y + 0.9, '#FFFFFF', 1, 0.20);
        }
        if ($border > 0) {
            $this->strokeRoundRect($x, $y, $w, $h, $radius, '#FFFFFF', 1, $border);
        }
    }

    /** محو کردن ناحیه‌ای از خود بوم (افکت شیشه‌ی مات) */
    public function backdropBlur(float $x, float $y, float $w, float $h, float $radius, int $passes = 3): void
    {
        $sx = max(0, $this->s($x));
        $sy = max(0, $this->s($y));
        $sw = min($this->s($w), imagesx($this->im) - $sx);
        $sh = min($this->s($h), imagesy($this->im) - $sy);
        if ($sw < 4 || $sh < 4) {
            return;
        }

        $down = 4;
        $tw = max(2, (int) ($sw / $down));
        $th = max(2, (int) ($sh / $down));

        $small = imagecreatetruecolor($tw, $th);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $this->im, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
        imagealphablending($small, true);
        for ($i = 0; $i < max(1, $passes); $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $layer = imagecreatetruecolor($sw, $sh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagecopyresampled($layer, $small, 0, 0, 0, 0, $sw, $sh, $tw, $th);
        imagedestroy($small);

        $this->applyRoundMask($layer, $this->s($radius));
        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $sx, $sy, 0, 0, $sw, $sh);
        imagedestroy($layer);
    }

    /** رسم فایل تصویری (با کش) */
    public function image(string $path, float $x, float $y, float $w, float $h, float $alpha = 1.0): bool
    {
        $src = self::loadImage($path);
        if ($src === null) {
            return false;
        }
        $dw = $this->s($w);
        $dh = $this->s($h);

        $scaled = imagecreatetruecolor($dw, $dh);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefilledrectangle($scaled, 0, 0, $dw, $dh, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $dw, $dh, imagesx($src), imagesy($src));

        imagealphablending($this->im, true);
        if ($alpha >= 1.0) {
            imagecopy($this->im, $scaled, $this->s($x), $this->s($y), 0, 0, $dw, $dh);
        } else {
            imagecopymerge($this->im, $scaled, $this->s($x), $this->s($y), 0, 0, $dw, $dh, (int) round($alpha * 100));
        }
        imagedestroy($scaled);

        return true;
    }

    /** @var array<string,\GdImage|null> */
    private static array $imageCache = [];

    public static function loadImage(string $path): ?GdImage
    {
        if (array_key_exists($path, self::$imageCache)) {
            return self::$imageCache[$path];
        }
        $image = null;
        if (is_file($path)) {
            $data = @file_get_contents($path);
            if ($data !== false) {
                $loaded = @imagecreatefromstring($data);
                if ($loaded !== false) {
                    imagealphablending($loaded, false);
                    imagesavealpha($loaded, true);
                    $image = $loaded;
                }
            }
        }

        return self::$imageCache[$path] = $image;
    }

    /** میانگین روشنایی بخش‌های مات یک تصویر (۰ تا ۱) */
    public static function imageLuminance(string $path): float
    {
        $src = self::loadImage($path);
        if ($src === null) {
            return 0.5;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $sum = 0.0;
        $count = 0;
        $step = max(1, (int) ($w / 24));

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgba = imagecolorat($src, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                if ($a > 90) {
                    continue; // تقریباً شفاف
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $sum += (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : 0.5;
    }

    /** خطی که رنگش از یک سر به سر دیگر تغییر می‌کند */
    public function gradientLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        string $from,
        string $to,
        float $thickness = 2,
        float $alpha = 1.0
    ): void {
        $steps = max(8, (int) (abs($x2 - $x1) + abs($y2 - $y1)) / 4);
        for ($i = 0; $i < $steps; $i++) {
            $t1 = $i / $steps;
            $t2 = ($i + 1) / $steps;
            $this->rect(
                $x1 + ($x2 - $x1) * $t1,
                $y1 + ($y2 - $y1) * $t1 - $thickness / 2,
                ($x2 - $x1) * ($t2 - $t1) + 0.8,
                $thickness,
                self::mix($from, $to, $t1),
                $alpha
            );
        }
    }

    /**
     * ناحیه‌ی زیر نمودار با محوشدگی عمودی.
     *
     * @param array<int,array{0:float,1:float}> $points نقاط منحنی
     */
    public function areaGradient(array $points, float $x, float $y, float $w, float $h, string $hex, float $alphaTop = 0.35): void
    {
        if (count($points) < 2 || $w <= 0 || $h <= 0) {
            return;
        }
        $lw = max(2, $this->s($w));
        $lh = max(2, $this->s($h));

        $layer = imagecreatetruecolor($lw, $lh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        [$r, $g, $b] = self::parseColor($hex);
        for ($row = 0; $row < $lh; $row++) {
            $t = $row / max(1, $lh - 1);
            $a = $alphaTop * (1 - $t) ** 1.35;
            imagefilledrectangle(
                $layer,
                0,
                $row,
                $lw,
                $row,
                imagecolorallocatealpha($layer, $r, $g, $b, (int) round(127 * (1 - $a)))
            );
        }

        // هرچه بالای منحنی است پاک می‌شود
        $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        $count = count($points);
        for ($i = 0; $i < $count - 1; $i++) {
            $ax = $this->s($points[$i][0] - $x);
            $ay = $this->s($points[$i][1] - $y);
            $bx = $this->s($points[$i + 1][0] - $x);
            $by = $this->s($points[$i + 1][1] - $y);
            $span = max(1, $bx - $ax);
            for ($col = $ax; $col <= $bx; $col++) {
                if ($col < 0 || $col >= $lw) {
                    continue;
                }
                $curve = (int) round($ay + ($by - $ay) * (($col - $ax) / $span));
                if ($curve > 0) {
                    imagefilledrectangle($layer, $col, 0, $col, min($lh - 1, $curve), $clear);
                }
            }
        }
        // دو طرف بیرون از منحنی
        $firstX = $this->s($points[0][0] - $x);
        $lastX = $this->s($points[$count - 1][0] - $x);
        if ($firstX > 0) {
            imagefilledrectangle($layer, 0, 0, $firstX - 1, $lh, $clear);
        }
        if ($lastX < $lw - 1) {
            imagefilledrectangle($layer, $lastX + 1, 0, $lw, $lh, $clear);
        }

        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $this->s($x), $this->s($y), 0, 0, $lw, $lh);
        imagedestroy($layer);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $hex, float $thickness = 1, float $alpha = 1.0): void
    {
        imagesetthickness($this->im, max(1, $this->s($thickness)));
        imageline($this->im, $this->s($x1), $this->s($y1), $this->s($x2), $this->s($y2), $this->color($hex, $alpha));
        imagesetthickness($this->im, 1);
    }

    public function dashedLine(float $x1, float $y1, float $x2, float $y2, string $hex, float $thickness = 1, float $dash = 6, float $gap = 5, float $alpha = 1.0): void
    {
        $len = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        if ($len <= 0) {
            return;
        }
        $dx = ($x2 - $x1) / $len;
        $dy = ($y2 - $y1) / $len;
        for ($p = 0.0; $p < $len; $p += $dash + $gap) {
            $e = min($len, $p + $dash);
            $this->line($x1 + $dx * $p, $y1 + $dy * $p, $x1 + $dx * $e, $y1 + $dy * $e, $hex, $thickness, $alpha);
        }
    }

    public function circle(float $cx, float $cy, float $radius, string $hex, float $alpha = 1.0): void
    {
        imagefilledellipse($this->im, $this->s($cx), $this->s($cy), $this->s($radius * 2), $this->s($radius * 2), $this->color($hex, $alpha));
    }

    public function ring(float $cx, float $cy, float $radius, float $thickness, string $hex, float $start = 0, float $end = 360, float $alpha = 1.0): void
    {
        $col = $this->color($hex, $alpha);
        $steps = max(24, (int) (($end - $start) * 2));
        $inner = $radius - $thickness / 2;
        $outer = $radius + $thickness / 2;
        $pts = [];
        for ($i = 0; $i <= $steps; $i++) {
            $ang = deg2rad($start + ($end - $start) * $i / $steps);
            $pts[] = $this->s($cx + cos($ang) * $outer);
            $pts[] = $this->s($cy + sin($ang) * $outer);
        }
        for ($i = $steps; $i >= 0; $i--) {
            $ang = deg2rad($start + ($end - $start) * $i / $steps);
            $pts[] = $this->s($cx + cos($ang) * $inner);
            $pts[] = $this->s($cy + sin($ang) * $inner);
        }
        imagefilledpolygon($this->im, $pts, $col);
    }

    /** @param array<int,array{0:float,1:float}> $points */
    public function polygon(array $points, string $hex, float $alpha = 1.0): void
    {
        $flat = [];
        foreach ($points as $p) {
            $flat[] = $this->s($p[0]);
            $flat[] = $this->s($p[1]);
        }
        if (count($flat) < 6) {
            return;
        }
        imagefilledpolygon($this->im, $flat, $this->color($hex, $alpha));
    }

    /** @param array<int,array{0:float,1:float}> $points */
    public function polyline(array $points, string $hex, float $thickness = 2, float $alpha = 1.0): void
    {
        $n = count($points);
        for ($i = 1; $i < $n; $i++) {
            $this->line($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $hex, $thickness, $alpha);
            if ($thickness > 2 && $i < $n - 1) {
                $this->circle($points[$i][0], $points[$i][1], $thickness / 2, $hex, $alpha);
            }
        }
    }

    /** مثلث جهت‌دار (برای فلش صعودی/نزولی) */
    public function triangle(float $cx, float $cy, float $size, bool $up, string $hex, float $alpha = 1.0): void
    {
        $h = $size * 0.86;
        $points = $up
            ? [[$cx, $cy - $h / 2], [$cx - $size / 2, $cy + $h / 2], [$cx + $size / 2, $cy + $h / 2]]
            : [[$cx, $cy + $h / 2], [$cx - $size / 2, $cy - $h / 2], [$cx + $size / 2, $cy - $h / 2]];
        $this->polygon($points, $hex, $alpha);
    }

    /** فلش صعودی/نزولی با دنباله */
    public function arrow(float $cx, float $cy, float $size, bool $up, string $hex, float $alpha = 1.0): void
    {
        $this->triangle($cx, $cy - ($up ? $size * 0.18 : -$size * 0.18), $size * 0.82, $up, $hex, $alpha);
        $this->rect(
            $cx - $size * 0.13,
            $up ? $cy + $size * 0.08 : $cy - $size * 0.46,
            $size * 0.26,
            $size * 0.38,
            $hex,
            $alpha
        );
    }

    // ------------------------------------------------------------- متن

    public static function fontPath(string $weight = self::W_BOLD): string
    {
        $file = APP_ASSETS . '/fonts/Vazirmatn-' . $weight . '.ttf';
        if (is_file($file)) {
            return $file;
        }
        $fallback = APP_ASSETS . '/fonts/Vazirmatn-Regular.ttf';
        if (is_file($fallback)) {
            return $fallback;
        }
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }

    /** @var array<string,array{top:float,height:float}> */
    private static array $metricsCache = [];

    /**
     * سنجه‌ی ثابت خط برای یک فونت و اندازه.
     *
     * از یک رشته‌ی مرجع استفاده می‌شود تا همه‌ی متن‌ها با هر حرفی که دارند
     * دقیقاً روی یک خط بنشینند؛ وگرنه ارتفاع هر متن به حروف خودش وابسته می‌شد.
     *
     * @return array{top:float,height:float} نسبت به خط پایه (top منفی است)
     */
    public function metrics(float $size, string $weight = self::W_BOLD): array
    {
        $fs = $size * $this->scale;
        $key = $weight . '|' . $fs;
        if (isset(self::$metricsCache[$key])) {
            return self::$metricsCache[$key];
        }

        $box = imagettfbbox($fs, 0, self::fontPath($weight), 'الکجMg0');
        $top = $box === false ? -$fs : (float) $box[7];
        $bottom = $box === false ? $fs * 0.25 : (float) $box[1];

        return self::$metricsCache[$key] = ['top' => $top, 'height' => $bottom - $top];
    }

    /** فاصله‌ی مرکزِ حروفِ یک رشته تا خط پایه (برای وسط‌چین کردن نوری) */
    private function inkCenter(string $visual, float $fs, string $font): float
    {
        $box = imagettfbbox($fs, 0, $font, $visual);
        if ($box === false) {
            return -$fs * 0.35;
        }

        return ((float) $box[7] + (float) $box[1]) / 2;
    }

    /** ارتفاع یک خط متن در این اندازه (پیکسل منطقی) */
    public function lineHeight(float $size, string $weight = self::W_BOLD): float
    {
        return $this->metrics($size, $weight)['height'] / $this->scale;
    }

    /** @return array{w:float,h:float,top:float} */
    public function measure(string $text, float $size, string $weight = self::W_BOLD, bool $prepared = false): array
    {
        $visual = $prepared ? $text : Persian::prepare($text);
        if ($visual === '') {
            return ['w' => 0.0, 'h' => 0.0, 'top' => 0.0];
        }
        $box = imagettfbbox($size * $this->scale, 0, self::fontPath($weight), $visual);
        if ($box === false) {
            return ['w' => 0.0, 'h' => 0.0, 'top' => 0.0];
        }
        $metrics = $this->metrics($size, $weight);

        return [
            'w'   => abs($box[2] - $box[0]) / $this->scale,
            'h'   => $metrics['height'] / $this->scale,
            'top' => $metrics['top'] / $this->scale,
        ];
    }

    public function textWidth(string $text, float $size, string $weight = self::W_BOLD): float
    {
        return $this->measure($text, $size, $weight)['w'];
    }

    /**
     * رسم متن.
     *
     * $align  : right | center | left
     * $valign :
     *   top      — y بالای خط (برای ردیف‌های پشت‌سرهم)
     *   middle   — y وسط خطِ فونت (برای هم‌ترازی چند متن در یک ردیف)
     *   ink      — y وسط خودِ حروف (برای متنی که داخل دایره یا قرص می‌نشیند)
     *   baseline — y خط پایه
     */
    public function text(
        string $text,
        float $x,
        float $y,
        float $size,
        string $hex,
        string $weight = self::W_BOLD,
        string $align = 'right',
        float $alpha = 1.0,
        string $valign = 'top',
        float $letterSpacing = 0.0
    ): float {
        if (trim($text) === '') {
            return 0.0;
        }
        $visual = Persian::prepare($text);
        $font = self::fontPath($weight);
        $col  = $this->color($hex, $alpha);
        $fs   = $size * $this->scale;
        $metrics = $this->metrics($size, $weight);

        $drawY = match ($valign) {
            'baseline' => $this->s($y),
            'middle'   => $this->s($y) - (int) round($metrics['height'] / 2 + $metrics['top']),
            'ink'      => $this->s($y) - (int) round($this->inkCenter($visual, $fs, $font)),
            default    => $this->s($y) - (int) round($metrics['top']),
        };

        if ($letterSpacing > 0.0) {
            return $this->textSpaced($visual, $x, $drawY, $size, $col, $font, $align, $letterSpacing);
        }

        $box = imagettfbbox($fs, 0, $font, $visual);
        $w = abs($box[2] - $box[0]);
        $drawX = match ($align) {
            'center' => $this->s($x) - (int) round($w / 2),
            'left'   => $this->s($x),
            default  => $this->s($x) - $w,
        };

        imagettftext($this->im, $fs, 0, (int) $drawX - $box[0], $drawY, $col, $font, $visual);

        return $w / $this->scale;
    }

    private function textSpaced(
        string $visual,
        float $x,
        int $drawY,
        float $size,
        int $col,
        string $font,
        string $align,
        float $spacing
    ): float {
        $chars = preg_split('//u', $visual, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fs = $size * $this->scale;
        $sp = $spacing * $this->scale;

        $total = 0.0;
        $widths = [];
        foreach ($chars as $ch) {
            $b = imagettfbbox($fs, 0, $font, $ch);
            $cw = $b[2] - $b[0];
            if ($ch === ' ') {
                $cw = max($cw, (int) ($fs * 0.32));
            }
            $widths[] = $cw;
            $total += $cw + $sp;
        }
        $total -= $sp;

        $cursor = match ($align) {
            'center' => $this->s($x) - $total / 2,
            'left'   => $this->s($x),
            default  => $this->s($x) - $total,
        };

        foreach ($chars as $i => $ch) {
            if (trim($ch) !== '') {
                imagettftext($this->im, $fs, 0, (int) round($cursor), $drawY, $col, $font, $ch);
            }
            $cursor += $widths[$i] + $sp;
        }

        return $total / $this->scale;
    }

    /** متن با اندازه‌ی خودکارِ کوچک‌شونده تا جا شود */
    public function textFit(
        string $text,
        float $x,
        float $y,
        float $maxWidth,
        float $size,
        string $hex,
        string $weight = self::W_BOLD,
        string $align = 'right',
        float $minSize = 9,
        float $alpha = 1.0,
        string $valign = 'top'
    ): float {
        $s = $size;
        while ($s > $minSize && $this->textWidth($text, $s, $weight) > $maxWidth) {
            $s -= 0.5;
        }
        if ($this->textWidth($text, $s, $weight) > $maxWidth) {
            $text = $this->ellipsize($text, $maxWidth, $s, $weight);
        }
        // اندازه هرچه باشد، متن روی همان خطِ اندازه‌ی اصلی می‌نشیند
        $offset = $valign === 'top'
            ? ($this->lineHeight($size, $weight) - $this->lineHeight($s, $weight)) / 2
            : 0.0;
        $this->text($text, $x, $y + $offset, $s, $hex, $weight, $align, $alpha, $valign);

        return $s;
    }

    public function ellipsize(string $text, float $maxWidth, float $size, string $weight = self::W_BOLD): string
    {
        if ($this->textWidth($text, $size, $weight) <= $maxWidth) {
            return $text;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach ($chars as $ch) {
            if ($this->textWidth($out . $ch . '…', $size, $weight) > $maxWidth) {
                break;
            }
            $out .= $ch;
        }
        return rtrim($out) . '…';
    }

    /** @return string[] */
    public function wrap(string $text, float $maxWidth, float $size, string $weight = self::W_REGULAR, int $maxLines = 3): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($this->textWidth($try, $size, $weight) <= $maxWidth || $cur === '') {
                $cur = $try;
            } else {
                $lines[] = $cur;
                $cur = $word;
                if (count($lines) >= $maxLines) {
                    break;
                }
            }
        }
        if ($cur !== '' && count($lines) < $maxLines) {
            $lines[] = $cur;
        }
        if (count($lines) === $maxLines) {
            $last = array_pop($lines);
            $lines[] = $this->ellipsize((string) $last, $maxWidth, $size, $weight);
        }
        return $lines;
    }

    // ------------------------------------------------------------- تصویر

    public function paste(GdImage $src, float $x, float $y, ?float $w = null, ?float $h = null): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $dw = $w !== null ? $this->s($w) : $sw;
        $dh = $h !== null ? $this->s($h) : $sh;
        imagealphablending($this->im, true);
        imagecopyresampled($this->im, $src, $this->s($x), $this->s($y), 0, 0, $dw, $dh, $sw, $sh);
    }

    public function pasteCanvas(self $other, float $x, float $y): void
    {
        imagealphablending($this->im, true);
        imagecopy($this->im, $other->im, $this->s($x), $this->s($y), 0, 0, imagesx($other->im), imagesy($other->im));
    }

    public function noise(float $intensity = 6.0): void
    {
        $w = imagesx($this->im);
        $h = imagesy($this->im);
        $step = max(2, $this->scale);
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (random_int(0, 6) !== 0) {
                    continue;
                }
                $a = (int) round(127 - $intensity * random_int(2, 10) / 10);
                imagefilledrectangle(
                    $this->im,
                    $x,
                    $y,
                    $x + $step - 1,
                    $y + $step - 1,
                    imagecolorallocatealpha($this->im, 255, 255, 255, max(100, min(127, $a)))
                );
            }
        }
    }

    // ------------------------------------------------------------- خروجی

    public function savePng(string $path): string
    {
        $out = $this->flatten();
        imagepng($out, $path, 4);
        imagedestroy($out);
        return $path;
    }

    public function saveJpeg(string $path, int $quality = 92): string
    {
        $out = $this->flatten('#0B0E17');
        imagejpeg($out, $path, $quality);
        imagedestroy($out);
        return $path;
    }

    private function flatten(?string $bg = null): GdImage
    {
        $outW = (int) round($this->width * $this->outputScale);
        $outH = (int) round($this->height * $this->outputScale);

        $out = imagecreatetruecolor($outW, $outH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        if ($bg !== null) {
            [$r, $g, $b] = self::parseColor($bg);
            imagefilledrectangle($out, 0, 0, $outW, $outH, imagecolorallocate($out, $r, $g, $b));
            imagealphablending($out, true);
        } else {
            imagefilledrectangle($out, 0, 0, $outW, $outH, imagecolorallocatealpha($out, 0, 0, 0, 127));
        }
        imagecopyresampled(
            $out,
            $this->im,
            0,
            0,
            0,
            0,
            $outW,
            $outH,
            imagesx($this->im),
            imagesy($this->im)
        );
        imagesavealpha($out, true);

        return $out;
    }

    public function __destruct()
    {
        if (isset($this->im)) {
            @imagedestroy($this->im);
        }
    }

    // ------------------------------------------------------------- کمکی

    /** گوشه‌های یک لایه را شفاف می‌کند */
    private function applyRoundMask(GdImage $layer, int $radius): void
    {
        $w = imagesx($layer);
        $h = imagesy($layer);
        $radius = max(0, min($radius, (int) (min($w, $h) / 2)));
        if ($radius <= 0) {
            return;
        }
        imagealphablending($layer, false);
        $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        $corners = [[0, 0, $radius, $radius], [$w - $radius, 0, $w - $radius - 1, $radius],
            [0, $h - $radius, $radius, $h - $radius - 1], [$w - $radius, $h - $radius, $w - $radius - 1, $h - $radius - 1]];
        foreach ($corners as [$sx, $sy, $cx, $cy]) {
            for ($y = $sy; $y < $sy + $radius; $y++) {
                for ($x = $sx; $x < $sx + $radius; $x++) {
                    if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
                        continue;
                    }
                    if ((($x - $cx) ** 2 + ($y - $cy) ** 2) > $radius ** 2) {
                        imagesetpixel($layer, $x, $y, $clear);
                    }
                }
            }
        }
        imagealphablending($layer, true);
    }
}
