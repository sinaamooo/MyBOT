<?php

declare(strict_types=1);

namespace EmojiMaker\Bot;

use EmojiMaker\App;
use EmojiMaker\Rendering\ImageException;
use EmojiMaker\Rendering\ImageProcessor;
use EmojiMaker\Telegram\TelegramException;

/**
 * Receives a photo or an image document from Telegram: size checks from the
 * Telegram metadata first, download into private storage, type detection
 * from the file bytes (never the name or declared MIME), dimension / pixel
 * limits, re-encoding to PNG (keeps transparency) and registration as a
 * project asset.
 */
final class Uploads
{
    public function __construct(private App $app)
    {
    }

    /** True when the message carries something that could be an image. */
    public static function hasImage(array $message): bool
    {
        if (!empty($message['photo'])) {
            return true;
        }
        $doc = $message['document'] ?? null;
        return is_array($doc) && preg_match('~^image/(png|jpe?g|webp)$~', (string) ($doc['mime_type'] ?? '')) === 1;
    }

    /**
     * @return array{id:int,width:int,height:int,has_alpha:bool,path:string}
     * @throws ImageException with a Persian message
     */
    public function store(array $message, int $userId, int $projectId, string $type = 'image'): array
    {
        $s = $this->app->settings();
        $maxBytes = max(256 * 1024, $s->int('max_upload_bytes', 10 * 1024 * 1024));
        $doc = $message['document'] ?? null;
        if (!empty($message['photo'])) {
            $sizes = (array) $message['photo'];
            usort($sizes, static fn ($a, $b) => ((int) ($a['file_size'] ?? 0)) <=> ((int) ($b['file_size'] ?? 0)));
            $file = end($sizes);
            $name = 'photo.jpg';
        } elseif (is_array($doc)) {
            $file = $doc;
            $name = (string) ($doc['file_name'] ?? 'image');
        } else {
            throw new ImageException('لطفاً یک عکس یا فایل تصویری (PNG، JPG یا WebP) بفرست.');
        }
        if ((int) ($file['file_size'] ?? 0) > $maxBytes) {
            throw new ImageException(sprintf('حجم فایل زیاد است (حداکثر %s مگابایت).', Texts::fa(round($maxBytes / 1048576, 1))));
        }
        $storage = $this->app->storage();
        $tmpDir = $storage->makeTempDir('upload');
        $raw = $tmpDir . '/source';
        try {
            try {
                $info = $this->app->telegram()->getFile((string) $file['file_id']);
                if ((int) ($info['file_size'] ?? 0) > $maxBytes) {
                    throw new ImageException('حجم فایل زیاد است.');
                }
                $this->app->telegram()->downloadFile((string) ($info['file_path'] ?? ''), $raw, $maxBytes);
            } catch (TelegramException $e) {
                $this->app->logger('bot')->warning('Upload download failed', ['error' => $e->getMessage()]);
                throw new ImageException('دریافت فایل از تلگرام ممکن نشد. دوباره بفرست (فایل‌های بزرگ‌تر از ۲۰ مگابایت قابل دریافت نیستند).');
            }
            $meta = ImageProcessor::inspect($raw, $maxBytes, $s->int('max_image_pixels', 4096 * 4096), $s->int('max_image_side', 4096));
            $rel = $storage->newRelativePath('uploads', 'png');
            $norm = ImageProcessor::normalize($raw, $storage->path($rel), $meta['mime'], 1024);
            $abs = $storage->path($rel);
            $id = $this->app->assets()->create([
                'project_id' => $projectId,
                'user_id' => $userId,
                'type' => $type === 'logo' ? 'logo' : 'image',
                'original_name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255),
                'mime' => 'image/png',
                'size_bytes' => (int) filesize($abs),
                'width' => $norm['width'],
                'height' => $norm['height'],
                'has_alpha' => $norm['has_alpha'] ? 1 : 0,
                'path' => $rel,
                'sha1' => (string) sha1_file($abs),
                'telegram_file_id' => mb_substr((string) $file['file_id'], 0, 255),
                'telegram_file_unique_id' => isset($file['file_unique_id']) ? mb_substr((string) $file['file_unique_id'], 0, 64) : null,
                'meta' => json_encode(['source_mime' => $meta['mime'], 'source_w' => $meta['width'], 'source_h' => $meta['height']]),
            ]);
            return ['id' => $id, 'width' => $norm['width'], 'height' => $norm['height'], 'has_alpha' => $norm['has_alpha'], 'path' => $rel];
        } finally {
            $storage->removeDir($tmpDir);
        }
    }
}
