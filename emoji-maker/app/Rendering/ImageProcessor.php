<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

/**
 * Validation and preparation of user images / logos with GD.
 * Uploaded files are always decoded and re-encoded (metadata stripped, no
 * polyglot payload survives) before they are used.
 */
final class ImageProcessor
{
    public const ALLOWED_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * Checks a file before decoding it (size, type, dimensions).
     * @return array{mime:string,width:int,height:int}
     */
    public static function inspect(string $path, int $maxBytes, int $maxPixels, int $maxSide): array
    {
        if (!is_file($path)) {
            throw new ImageException('فایل تصویر پیدا نشد.');
        }
        $size = filesize($path) ?: 0;
        if ($size <= 0) {
            throw new ImageException('فایل ارسالی خالی است.');
        }
        if ($size > $maxBytes) {
            throw new ImageException(sprintf('حجم تصویر زیاد است (حداکثر %s مگابایت).', round($maxBytes / 1048576, 1)));
        }
        $head = (string) file_get_contents($path, false, null, 0, 32);
        $mime = self::sniff($head);
        if ($mime === null) {
            throw new ImageException('فرمت فایل پشتیبانی نمی‌شود. فقط PNG، JPG و WebP قابل قبول است.');
        }
        $info = @getimagesize($path);
        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            throw new ImageException('تصویر خراب است یا قابل خواندن نیست.');
        }
        [$w, $h] = $info;
        if ($w > $maxSide || $h > $maxSide || $w * $h > $maxPixels) {
            throw new ImageException(sprintf('ابعاد تصویر بیش از حد مجاز است (حداکثر %d×%d پیکسل).', $maxSide, $maxSide));
        }
        if ($w < 16 || $h < 16) {
            throw new ImageException('تصویر خیلی کوچک است (حداقل ۱۶×۱۶ پیکسل).');
        }
        return ['mime' => $mime, 'width' => (int) $w, 'height' => (int) $h];
    }

    /** Magic-byte based type detection (never trusts the file name). */
    public static function sniff(string $head): ?string
    {
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return null;
    }

    /**
     * Decodes, fixes orientation, downsizes and re-encodes to PNG (with alpha).
     * @return array{width:int,height:int,has_alpha:bool}
     */
    public static function normalize(string $src, string $dstPng, string $mime, int $maxSide = 1024): array
    {
        $needed = (int) (filesize($src) ?: 0) + 64 * 1024 * 1024;
        self::ensureMemory($needed);
        $im = match ($mime) {
            'image/png' => @imagecreatefrompng($src),
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if (!$im instanceof \GdImage) {
            throw new ImageException('تصویر خراب است یا این نوع فایل (مثلاً WebP متحرک) پشتیبانی نمی‌شود.');
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $im = self::orient($im, $orientation);
        }
        $im = self::resizeToFit($im, $maxSide);
        $hasAlpha = self::hasTransparency($im);
        imagesavealpha($im, true);
        if (!imagepng($im, $dstPng, 6)) {
            throw new ImageException('ذخیره‌ی تصویر ناموفق بود.');
        }
        $result = ['width' => imagesx($im), 'height' => imagesy($im), 'has_alpha' => $hasAlpha];
        imagedestroy($im);
        return $result;
    }

    private static function orient(\GdImage $im, int $o): \GdImage
    {
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        switch ($o) {
            case 3:
                $r = imagerotate($im, 180, $transparent);
                break;
            case 6:
                $r = imagerotate($im, -90, $transparent);
                break;
            case 8:
                $r = imagerotate($im, 90, $transparent);
                break;
            case 2:
                imageflip($im, IMG_FLIP_HORIZONTAL);
                return $im;
            case 4:
                imageflip($im, IMG_FLIP_VERTICAL);
                return $im;
            default:
                return $im;
        }
        if ($r instanceof \GdImage) {
            imagealphablending($r, false);
            imagesavealpha($r, true);
            return $r;
        }
        return $im;
    }

    public static function ensureMemory(int $bytes): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') {
            return;
        }
        $num = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));
        $max = match ($unit) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => $num,
        };
        if ($max > 0 && memory_get_usage(true) + $bytes > $max) {
            @ini_set('memory_limit', (string) (int) (memory_get_usage(true) + $bytes + 32 * 1024 * 1024));
        }
    }

    public static function canvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefilledrectangle($im, 0, 0, max(1, $w) - 1, max(1, $h) - 1, imagecolorallocatealpha($im, 0, 0, 0, 127));
        return $im;
    }

    public static function load(string $png): \GdImage
    {
        $im = @imagecreatefrompng($png);
        if (!$im instanceof \GdImage) {
            throw new ImageException('بارگذاری تصویر ناموفق بود.');
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        return $im;
    }

    public static function resizeToFit(\GdImage $im, int $maxSide): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w <= $maxSide && $h <= $maxSide) {
            return $im;
        }
        $s = $maxSide / max($w, $h);
        $nw = max(1, (int) round($w * $s));
        $nh = max(1, (int) round($h * $s));
        $out = self::canvas($nw, $nh);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }

    public static function hasTransparency(\GdImage $im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) floor(min($w, $h) / 64));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 8) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Crops away transparent (or near transparent) borders. */
    public static function trim(\GdImage $im, int $alphaThreshold = 120): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $x0 = $w;
        $y0 = $h;
        $x1 = -1;
        $y1 = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < $alphaThreshold) {
                    if ($x < $x0) { $x0 = $x; }
                    if ($x > $x1) { $x1 = $x; }
                    if ($y < $y0) { $y0 = $y; }
                    if ($y > $y1) { $y1 = $y; }
                }
            }
        }
        if ($x1 < 0) {
            throw new ImageException('تصویر کاملاً شفاف است و محتوایی برای ساخت ایموجی ندارد.');
        }
        if ($x0 === 0 && $y0 === 0 && $x1 === $w - 1 && $y1 === $h - 1) {
            return $im;
        }
        $nw = $x1 - $x0 + 1;
        $nh = $y1 - $y0 + 1;
        $out = self::canvas($nw, $nh);
        imagecopy($out, $im, 0, 0, $x0, $y0, $nw, $nh);
        return $out;
    }

    /**
     * Removes a uniform background connected to the image border (flood fill
     * with colour tolerance and soft edges). Ideal for logos on white.
     */
    public static function removeBackground(\GdImage $im, int $tolerance = 48): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        // reference colour = median of border samples
        $samples = [];
        for ($x = 0; $x < $w; $x += max(1, intdiv($w, 40))) {
            $samples[] = imagecolorat($im, $x, 0);
            $samples[] = imagecolorat($im, $x, $h - 1);
        }
        for ($y = 0; $y < $h; $y += max(1, intdiv($h, 40))) {
            $samples[] = imagecolorat($im, 0, $y);
            $samples[] = imagecolorat($im, $w - 1, $y);
        }
        $rs = $gs = $bs = [];
        foreach ($samples as $c) {
            if ((($c >> 24) & 0x7F) > 100) {
                continue; // already transparent
            }
            $rs[] = ($c >> 16) & 0xFF;
            $gs[] = ($c >> 8) & 0xFF;
            $bs[] = $c & 0xFF;
        }
        if (count($rs) < 4) {
            return $im;
        }
        sort($rs);
        sort($gs);
        sort($bs);
        $mid = intdiv(count($rs), 2);
        $ref = [$rs[$mid], $gs[$mid], $bs[$mid]];
        $tol2 = $tolerance * $tolerance;
        $soft2 = ($tolerance * 1.8) ** 2;

        $out = self::canvas($w, $h);
        imagecopy($out, $im, 0, 0, 0, 0, $w, $h);
        $visited = str_repeat("\0", $w * $h);
        $queue = [];
        $push = static function (int $x, int $y) use (&$queue, &$visited, $w): void {
            $i = $y * $w + $x;
            if ($visited[$i] === "\0") {
                $visited[$i] = "\1";
                $queue[] = $i;
            }
        };
        for ($x = 0; $x < $w; $x++) {
            $push($x, 0);
            $push($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y++) {
            $push(0, $y);
            $push($w - 1, $y);
        }
        $head = 0;
        $removed = 0;
        while ($head < count($queue)) {
            $i = $queue[$head++];
            $y = intdiv($i, $w);
            $x = $i - $y * $w;
            $c = imagecolorat($im, $x, $y);
            $a = ($c >> 24) & 0x7F;
            $dr = (($c >> 16) & 0xFF) - $ref[0];
            $dg = (($c >> 8) & 0xFF) - $ref[1];
            $db = ($c & 0xFF) - $ref[2];
            $d2 = $dr * $dr + $dg * $dg + $db * $db;
            if ($a < 120 && $d2 > $soft2) {
                continue; // foreground
            }
            if ($d2 <= $tol2 || $a >= 120) {
                imagesetpixel($out, $x, $y, 0x7F000000);
                $removed++;
                if ($x > 0) { $push($x - 1, $y); }
                if ($x < $w - 1) { $push($x + 1, $y); }
                if ($y > 0) { $push($x, $y - 1); }
                if ($y < $h - 1) { $push($x, $y + 1); }
            } else {
                // anti-aliased edge: partial transparency
                $f = (sqrt($d2) - $tolerance) / ($tolerance * 0.8);
                $f = max(0.0, min(1.0, $f));
                $na = 127 - (int) round((127 - $a) * $f);
                imagesetpixel($out, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
            }
        }
        if ($removed > $w * $h * 0.985) {
            throw new ImageException('حذف پس‌زمینه کل تصویر را پاک کرد؛ این گزینه را برای این تصویر خاموش کنید.');
        }
        return $out;
    }

    /** Applies a circular or rounded square alpha mask. */
    public static function cropShape(\GdImage $im, string $shape): \GdImage
    {
        if ($shape !== 'circle' && $shape !== 'rounded') {
            return $im;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $side = min($w, $h);
        $ox = intdiv($w - $side, 2);
        $oy = intdiv($h - $side, 2);
        $square = self::canvas($side, $side);
        imagecopy($square, $im, 0, 0, $ox, $oy, $side, $side);
        $ras = new Rasterizer(4);
        $path = $shape === 'circle'
            ? \EmojiMaker\Geometry\Path::ellipse($side / 2, $side / 2, $side / 2, $side / 2)
            : \EmojiMaker\Geometry\Path::roundedRect(0, 0, $side, $side, $side * 0.22);
        $mask = $ras->fillPath($path, $side, $side);
        for ($y = 0; $y < $side; $y++) {
            for ($x = 0; $x < $side; $x++) {
                $m = $mask->data[$y * $side + $x];
                $c = imagecolorat($square, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $na = 127 - (int) round((127 - $a) * $m);
                if ($na !== $a) {
                    imagesetpixel($square, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
                }
            }
        }
        return $square;
    }
}
