<?php

declare(strict_types=1);

namespace EmojiMaker\Validation;

/**
 * Static emoji: PNG or WEBP, exactly 100x100 pixels (emoji) or one side of
 * 512 px (stickers).
 */
final class StaticValidator
{
    public const MAX_BYTES = 512 * 1024;

    /** @param array<string,mixed> $limits telegram_formats.static */
    public function __construct(private array $limits)
    {
    }

    public function validate(string $file, string $kind = 'emoji'): ValidationReport
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $r = new ValidationReport($ext ?: 'png', basename($file));
        if (!is_file($file)) {
            $r->error('فایل وجود ندارد.');
            return $r;
        }
        $head = (string) file_get_contents($file, false, null, 0, 16);
        $isPng = str_starts_with($head, "\x89PNG\r\n\x1a\n");
        $isWebp = str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP';
        if (!$isPng && !$isWebp) {
            $r->error('فرمت فایل باید PNG یا WEBP واقعی باشد.');
            return $r;
        }
        $info = @getimagesize($file);
        if ($info === false) {
            $r->error('تصویر قابل خواندن نیست.');
            return $r;
        }
        [$w, $h] = $info;
        $size = (int) filesize($file);
        $r->info = ['width' => $w, 'height' => $h, 'bytes' => $size, 'type' => $isPng ? 'png' : 'webp'];
        if ($kind === 'emoji') {
            $need = (int) $this->limits['emoji_size'];
            if ($w !== $need || $h !== $need) {
                $r->error(sprintf('ابعاد ایموجی ثابت باید دقیقاً %d×%d باشد.', $need, $need));
            }
        } elseif (max($w, $h) !== 512) {
            $r->error('برای استیکر یک ضلع باید دقیقاً ۵۱۲ پیکسل باشد.');
        }
        if ($size > self::MAX_BYTES) {
            $r->error('حجم تصویر بیش از ۵۱۲ کیلوبایت است.');
        }
        return $r;
    }
}
