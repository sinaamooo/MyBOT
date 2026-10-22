<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;

/**
 * "preview" job: renders one item and sends its preview animation (MP4 /
 * GIF, photo fallback) with the "generate / edit / another preview" keyboard.
 *
 * Previews of WEBM designs / templates are rendered through the PNG path:
 * same raster scene and animation, but no VP9 encoding (several seconds
 * saved per preview). TGS is rendered for real because its 64 KB budget is
 * a real constraint the user should learn about before generating.
 *
 * Payload: {"project_id", "kind": design|template, "format": webm|tgs|png,
 *           "item": {"config"} | {"template", "content"}, "reply_to"}
 */
final class PreviewHandler implements JobHandler
{
    public function handle(JobContext $ctx): array
    {
        $app = $ctx->app();
        $p = $ctx->payload();
        $kind = GenerateHandler::kind($p);
        $format = GenerateHandler::format($p);
        $item = is_array($p['item'] ?? null) ? $p['item'] : null;
        if ($item === null) {
            throw new JobFailed('چیزی برای پیش‌نمایش انتخاب نشده است.');
        }
        $project = GenerateHandler::project($app, (int) ($p['project_id'] ?? $ctx->projectId() ?? 0), $ctx->userId());
        GenerateHandler::assertCanRender($app, $kind, $format);
        $projectId = $project !== null ? (int) $project['id'] : 0;
        if ($project !== null) {
            $app->projects()->setStatus($projectId, 'previewing');
        }
        $ctx->progress(0, 1, 'در حال ساخت پیش‌نمایش…');
        $renderFormat = $format === 'tgs' ? 'tgs' : 'png';
        $cache = [];
        $result = GenerateHandler::renderItem($app, $kind, $item, $renderFormat, $ctx->workDir() . '/preview', [
            'preview' => true,
            'name' => 'preview',
        ], $ctx->userId(), $cache);
        $ctx->checkCancelled();

        $tile = is_array($result['tiles'][0] ?? null) ? $result['tiles'][0] : [];
        $caption = self::caption($app->templates()->get((string) ($item['template'] ?? ''))?->name ?? '', $kind, $format, $result, $tile);
        $sender = $ctx->sender();
        $sent = $sender->sendPreview($result, $kind, $projectId, $caption, isset($p['reply_to']) ? (int) $p['reply_to'] : null);
        if (!$sent && $sender->nothingDelivered()) {
            throw new JobFailed('ارسال پیش‌نمایش به تلگرام ممکن نشد. کمی بعد دوباره تلاش می‌شود.', true);
        }
        $ctx->clearProgress();
        if ($project !== null) {
            $app->projects()->setStatus($projectId, 'previewed');
        }
        return [
            'kind' => $kind,
            'format' => $format,
            'preview_format' => $result['preview_format'] ?? null,
            'sent' => $sent,
            'warnings' => array_values((array) ($result['warnings'] ?? [])),
            'validation' => $renderFormat === $format ? ($tile['status'] ?? null) : null,
            'bytes' => $renderFormat === $format ? (int) ($tile['bytes'] ?? 0) : null,
        ];
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed> $tile
     */
    private static function caption(string $templateName, string $kind, string $format, array $result, array $tile): string
    {
        $lines = ['👀 پیش‌نمایش' . ($kind === 'template' && $templateName !== '' ? ' «' . $templateName . '»' : '')];
        $lines[] = 'فرمت خروجی: ' . OutputSender::formatLabel($format);
        if ((int) ($result['tile_count'] ?? 1) > 1) {
            $lines[] = '🧩 این طرح بنر ' . OutputSender::fa((int) $result['tile_count']) . ' تکه‌ای است.';
        }
        if ($format === 'tgs' && $tile) {
            $kb = round(((int) ($tile['bytes'] ?? 0)) / 1024, 1);
            $status = OutputSender::validationLine((string) ($tile['status'] ?? ''));
            $lines[] = 'حجم TGS: ' . OutputSender::fa(str_replace('.', '٫', (string) $kb)) . ' از ۶۴ کیلوبایت' . ($status !== '' ? ' — ' . $status : '');
            foreach (array_slice((array) ($tile['validation']['errors'] ?? []), 0, 2) as $err) {
                $lines[] = '• ' . $err;
            }
        }
        foreach (array_slice(array_values(array_unique((array) ($result['warnings'] ?? []))), 0, 3) as $w) {
            $lines[] = '⚠️ ' . $w;
        }
        $lines[] = '';
        $lines[] = 'اگر راضی هستید «ساخت نهایی» را بزنید.';
        return implode("\n", $lines);
    }
}
