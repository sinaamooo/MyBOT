<?php

final class Uploader
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const FILE_TYPES = self::IMAGE_TYPES + ['application/pdf' => 'pdf', 'application/zip' => 'zip', 'text/plain' => 'txt'];

    public static function has(string $field): bool
    {
        return isset($_FILES[$field]) && is_array($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** @throws RuntimeException */
    public static function image(string $field, string $dir, int $maxKb = 3072): string
    {
        return self::store($field, $dir, self::IMAGE_TYPES, $maxKb, true);
    }

    /** @throws RuntimeException */
    public static function file(string $field, string $dir, int $maxKb = 5120): string
    {
        return self::store($field, $dir, self::FILE_TYPES, $maxKb, false);
    }

    private static function store(string $field, string $dir, array $types, int $maxKb, bool $mustBeImage): string
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new RuntimeException('آپلود فایل ناموفق بود.');
        }
        if ($f['size'] > $maxKb * 1024) {
            throw new RuntimeException('حجم فایل بیشتر از ' . fa(round($maxKb / 1024, 1)) . ' مگابایت است.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset($types[$mime])) {
            throw new RuntimeException('نوع فایل مجاز نیست.');
        }
        if (($mustBeImage || str_starts_with($mime, 'image/')) && @getimagesize($f['tmp_name']) === false) {
            throw new RuntimeException('فایل تصویر معتبر نیست.');
        }
        $dir = trim($dir, '/');
        $name = date('Ym') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
        $target = BASE_PATH . '/uploads/' . $dir;
        if (!is_dir($target) && !@mkdir($target, 0755, true)) {
            throw new RuntimeException('پوشه آپلود قابل نوشتن نیست.');
        }
        if (!move_uploaded_file($f['tmp_name'], $target . '/' . $name)) {
            throw new RuntimeException('ذخیره فایل ناموفق بود.');
        }
        return 'uploads/' . $dir . '/' . $name;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
            @unlink(BASE_PATH . '/' . $path);
        }
    }
}
