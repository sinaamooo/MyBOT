<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\App;
use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Delivery\ZipBuilder;
use EmojiMaker\Encoding\PreviewEncoder;
use EmojiMaker\Queue\JobCancelled;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Rendering\GridComposer;
use EmojiMaker\Rendering\RenderException;
use EmojiMaker\Templates\TemplateException;

/**
 * "generate" job: renders every planned item (designs with EmojiRenderer,
 * templates with TemplateRenderer), stores main files / previews / thumbs,
 * builds the batch grid and the ZIP, delivers everything to the chat and
 * does the usage accounting.
 *
 * Robustness:
 *  - every item is rendered in its own try/catch; failures are recorded and
 *    the job continues (the job only fails when no item succeeded)
 *  - cancellation is checked and a heartbeat sent between items
 *  - all rows of one item are written in one transaction after its files
 *    were imported, so an item is either complete or absent; a retried job
 *    keeps complete items, removes partial / job level rows and only renders
 *    what is missing (idempotent re-runs)
 *  - frames are deleted after each item, the work dir after the job
 *
 * Payload: {"project_id", "kind": design|template, "format": webm|tgs|png,
 *   "items": [{"label", "config"} | {"label", "template", "content"}],
 *   "grid": bool, "send": auto|files|zip}
 * Design configs may reference their image as "image": {"asset_id": n} (an
 * asset of the job's user) or a storage relative "image": {"path": "uploads/…"}.
 */
final class GenerateHandler implements JobHandler
{
    public const MAX_ITEMS = 200;
    public const FORMATS = ['webm', 'tgs', 'png'];
    private const GRID_FPS = 15;
    private const PREVIEW_BG = [28, 39, 51];

    public function handle(JobContext $ctx): array
    {
        $app = $ctx->app();
        $p = $ctx->payload();
        $kind = self::kind($p);
        $format = self::format($p);
        $items = array_values(array_filter((array) ($p['items'] ?? []), 'is_array'));
        if (!$items) {
            throw new JobFailed('هیچ موردی برای ساخت انتخاب نشده است.');
        }
        if (count($items) > self::MAX_ITEMS) {
            $app->logger('queue')->warning('Generate job truncated', ['job' => $ctx->id(), 'items' => count($items)]);
            $items = array_slice($items, 0, self::MAX_ITEMS);
        }
        $project = self::project($app, (int) ($p['project_id'] ?? $ctx->projectId() ?? 0), $ctx->userId());
        self::assertCanRender($app, $kind, $format);
        $jobId = $ctx->id();
        $total = count($items);
        $wantGrid = !empty($p['grid']) && $total > 1;
        if ($project !== null) {
            $app->projects()->setStatus((int) $project['id'], 'generating', $jobId);
        }

        $complete = $this->prepareRetry($app, $jobId, $total);
        $done = 0;
        $failed = [];
        $gridDirs = [];
        $cache = [];
        $ctx->progress(0, $total, 'در حال ساخت ایموجی‌ها…');
        foreach ($items as $i => $item) {
            $ctx->checkCancelled();
            $label = self::label($app, $kind, $item, $i);
            $gridDir = $wantGrid ? $ctx->workDir() . '/g' . $i : null;
            if (isset($complete[$i])) {
                if ($gridDir !== null && $this->gridFromThumb($app, $complete[$i], $gridDir)) {
                    $gridDirs[$i + 1] = $gridDir;
                }
                $done++;
                continue;
            }
            $itemDir = $ctx->workDir() . '/i' . $i;
            try {
                $result = self::renderItem($app, $kind, $item, $format, $itemDir, [
                    'preview' => 'thumb',
                    'grid_dir' => $gridDir,
                    'grid_fps' => self::GRID_FPS,
                    'name' => 'item' . ($i + 1),
                ], $ctx->userId(), $cache);
                $this->storeItem($app, $ctx->job(), $i, $item, $label, $kind, $format, $result);
                if ($gridDir !== null && is_dir($gridDir)) {
                    $gridDirs[$i + 1] = $gridDir;
                }
                if ($kind === 'template') {
                    self::countTemplateUse($app, (string) ($item['template'] ?? ''));
                }
            } catch (JobCancelled $e) {
                throw $e;
            } catch (\Throwable $e) {
                $failed[] = ['index' => $i, 'label' => $label, 'error' => self::userMessage($e)];
                $app->logger('queue')->warning('Item failed', [
                    'job' => $jobId, 'item' => $i, 'exception' => get_class($e), 'error' => $e->getMessage(),
                    'at' => basename($e->getFile()) . ':' . $e->getLine(),
                ]);
            } finally {
                $app->storage()->removeDir($itemDir);
                unset($result);
                gc_collect_cycles();
            }
            $done++;
            $ctx->progress($done, $total, 'ساخت ایموجی ' . OutputSender::fa($done) . ' از ' . OutputSender::fa($total), count($failed));
        }

        $mains = $app->files()->forJob($jobId, 'main');
        if (!$mains) {
            throw new JobFailed($failed ? 'هیچ ایموجی‌ای ساخته نشد: ' . $failed[0]['error'] : 'هیچ ایموجی‌ای ساخته نشد.');
        }
        $succeeded = count(array_unique(array_map(static fn ($r) => (int) $r['item_index'], $mains)));

        $grids = [];
        $sheets = [];
        if ($wantGrid && count($gridDirs) > 1) {
            $ctx->progress($total, $total, 'در حال ساخت پیش‌نمایش شبکه‌ای…', count($failed));
            [$grids, $sheets] = $this->buildGrids($ctx, $gridDirs);
        }

        $ctx->checkCancelled();
        $ctx->progress($total, $total, 'در حال ساخت فایل ZIP…', count($failed));
        $zip = self::buildZip($app, $ctx->job(), $mains, $failed, $kind, $format, $ctx->workDir());

        $ctx->progress($total, $total, 'در حال ارسال فایل‌ها…', count($failed));
        $sender = $ctx->sender();
        $delivery = $sender->deliverGenerated(
            $jobId,
            (string) ($p['send'] ?? 'auto'),
            $mains,
            $grids,
            $sheets,
            $zip,
            $failed,
            $format,
            $app->settings()->bool('allow_publish', true),
            $total,
        );
        if ($sender->nothingDelivered()) {
            // Telegram unreachable: retry later; the rendered items are kept
            throw new JobFailed('ارسال فایل‌ها به تلگرام ممکن نشد. کمی بعد دوباره تلاش می‌شود.', true);
        }
        $ctx->clearProgress();

        $units = $this->account($app, $ctx, $kind, $items, $mains);
        if ($project !== null) {
            $app->projects()->setStatus((int) $project['id'], 'completed', $jobId);
            $app->projects()->addOutputs((int) $project['id'], $succeeded);
        }
        $validation = ['valid' => 0, 'warning' => 0, 'invalid' => 0, 'unchecked' => 0];
        foreach ($mains as $row) {
            $validation[(string) $row['validation_status']] = ($validation[(string) $row['validation_status']] ?? 0) + 1;
        }
        return [
            'kind' => $kind,
            'format' => $format,
            'items' => $total,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'files' => count($mains),
            'resumed' => count($complete),
            'zip_file_id' => (int) $zip['id'],
            'grid_file_ids' => array_map(static fn ($r) => (int) $r['id'], $grids ?: $sheets),
            'validation' => $validation,
            'delivery' => $delivery,
            'units' => $units,
        ];
    }

    // ------------------------------------------------------------ payload

    public static function kind(array $payload): string
    {
        $kind = (string) ($payload['kind'] ?? 'design');
        if (!in_array($kind, ['design', 'template'], true)) {
            throw new JobFailed('نوع ساخت نامعتبر است.');
        }
        return $kind;
    }

    public static function format(array $payload): string
    {
        $format = (string) ($payload['format'] ?? 'webm');
        if (!in_array($format, self::FORMATS, true)) {
            throw new JobFailed('فرمت خروجی نامعتبر است.');
        }
        return $format;
    }

    /** Ownership checked project row (null when the job has none). @return array<string,mixed>|null */
    public static function project(App $app, int $projectId, ?int $userId): ?array
    {
        if ($projectId <= 0) {
            return null;
        }
        $project = $app->projects()->find($projectId);
        if ($project === null) {
            throw new JobFailed('این پروژه پیدا نشد یا حذف شده است.');
        }
        if ($userId !== null && (int) $project['user_id'] !== $userId) {
            throw new JobFailed('دسترسی به این پروژه ممکن نیست.');
        }
        return $project;
    }

    public static function assertCanRender(App $app, string $kind, string $format): void
    {
        $can = $kind === 'template' ? $app->templateRenderer()->canRender($format) : $app->renderer()->canRender($format);
        if (!$can) {
            throw new JobFailed('ساخت WEBM روی این سرور ممکن نیست (FFmpeg با کدک VP9 نصب نیست). فرمت TGS یا PNG را انتخاب کنید.');
        }
    }

    public static function label(App $app, string $kind, array $item, int $index): string
    {
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '' && $kind === 'template') {
            $spec = $app->templates()->get((string) ($item['template'] ?? ''));
            $label = $spec !== null ? $spec->name : '';
        }
        return $label !== '' ? mb_substr($label, 0, 80) : 'ایموجی ' . OutputSender::fa($index + 1);
    }

    /** Persian message for the user: renderer messages are Persian already, everything else is generic. */
    public static function userMessage(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if (($e instanceof JobFailed || $e instanceof RenderException || $e instanceof TemplateException)
            && preg_match('/\p{Arabic}/u', $msg)) {
            return mb_substr($msg, 0, 300);
        }
        return 'خطای داخلی هنگام ساخت این مورد.';
    }

    // ----------------------------------------------------------- rendering

    /**
     * Renders one design / template item into $dir (shared with the preview job).
     * @param array<string,mixed> $item
     * @param array<string,mixed> $opts renderer options (preview, grid_dir, grid_fps, name)
     * @param array<string,mixed> $cache per job cache (resolved assets, user row)
     * @return array<string,mixed> renderer result (EmojiRenderer::render() shape)
     */
    public static function renderItem(App $app, string $kind, array $item, string $format, string $dir, array $opts, ?int $userId, array &$cache = []): array
    {
        if ($kind === 'template') {
            $slug = (string) ($item['template'] ?? '');
            $library = $app->templates();
            $spec = $slug !== '' ? $library->get($slug) : null;
            if ($spec === null || !$library->isEnabled($spec)) {
                throw new JobFailed('این قالب در دسترس نیست.');
            }
            $content = self::templateContent($app, is_array($item['content'] ?? null) ? $item['content'] : [], $userId, $cache);
            return $app->templateRenderer()->render($spec, $content, $format, $dir, $opts);
        }
        $config = is_array($item['config'] ?? null) ? $item['config'] : [];
        if (!$config) {
            throw new JobFailed('تنظیمات طرح ناقص است.');
        }
        return $app->renderer()->render(self::designConfig($app, $config, $userId, $cache), $format, $dir, $opts);
    }

    /**
     * Resolves the design image to an absolute path inside storage: an asset
     * id of the job's user, or a storage relative path. Anything else is dropped.
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function designConfig(App $app, array $config, ?int $userId, array &$cache = []): array
    {
        $img = $config['image'] ?? null;
        if (!is_array($img)) {
            unset($config['image']);
            return $config;
        }
        $wanted = isset($img['asset_id']) || isset($img['path']);
        $path = null;
        if (isset($img['asset_id']) && is_numeric($img['asset_id'])) {
            $path = self::assetPath($app, (int) $img['asset_id'], $userId, $cache);
        } elseif (isset($img['path']) && is_string($img['path'])) {
            $path = self::storagePath($app, $img['path']);
        }
        unset($img['asset_id'], $img['path']);
        if ($path !== null) {
            $img['path'] = $path;
        } elseif ($wanted && in_array($config['input'] ?? 'text', ['image', 'combo'], true)) {
            throw new JobFailed('تصویر این طرح پیدا نشد (ممکن است پاک شده باشد). لطفاً تصویر را دوباره بفرستید.');
        }
        $config['image'] = $img;
        return $config;
    }

    /**
     * Template content from the bot payload → TemplateRenderer content keys.
     * The logo asset must belong to the job's user. Original brand colours
     * are only kept when "templates_original_colors" allows it for this user;
     * otherwise the demo colour is applied (brand protection).
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    public static function templateContent(App $app, array $c, ?int $userId, array &$cache = []): array
    {
        $out = [];
        $logo = $c['logo'] ?? null;
        if ($logo !== null && $logo !== '' && $logo !== 0 && $logo !== false) {
            if (!is_int($logo) && !(is_string($logo) && ctype_digit($logo))) {
                throw new JobFailed('لوگوی انتخاب‌شده نامعتبر است.');
            }
            $path = self::assetPath($app, (int) $logo, $userId, $cache);
            if ($path === null) {
                throw new JobFailed('لوگوی شما پیدا نشد (ممکن است پاک شده باشد). لطفاً لوگو را دوباره بفرستید.');
            }
            $out['logo'] = $path;
        }
        foreach (['text', 'caption'] as $k) {
            if (isset($c[$k]) && is_scalar($c[$k]) && trim((string) $c[$k]) !== '') {
                $out[$k] = mb_substr(trim((string) $c[$k]), 0, 64);
            }
        }
        if (isset($c['font']) && is_string($c['font']) && preg_match('/^[a-z0-9_-]{1,48}$/', $c['font'])) {
            $out['font'] = $c['font'];
        }
        foreach (['color', 'outline', 'caption_color'] as $k) {
            if (isset($c[$k]) && is_string($c[$k]) && Color::isHex($c[$k])) {
                $out[$k] = Color::normalizeHex($c[$k]);
            }
        }
        $ranges = ['outline_width' => [0.0, 0.25], 'size' => [0.3, 1.0], 'letter_spacing' => [-0.1, 0.5]];
        foreach ($ranges as $k => [$min, $max]) {
            if (isset($c[$k]) && is_numeric($c[$k])) {
                $out[$k] = max($min, min($max, (float) $c[$k]));
            }
        }
        $mode = in_array($c['recolor_mode'] ?? 'main', ['main', 'all', 'gray'], true) ? (string) ($c['recolor_mode'] ?? 'main') : 'main';
        $recolor = isset($c['recolor']) && is_string($c['recolor']) && Color::isHex($c['recolor']) ? Color::normalizeHex($c['recolor']) : null;
        if ($mode !== 'gray' && $recolor === null && !self::mayKeepOriginalColors($app, $userId, $cache)) {
            $recolor = Color::normalizeHex($app->settings()->string('templates_demo_color', '#8B5CF6'), '#8B5CF6');
        }
        $out['recolor'] = $recolor;
        $out['recolor_mode'] = $mode;
        if (!isset($out['logo']) && !isset($out['text']) && !isset($out['caption'])) {
            throw new JobFailed('برای این قالب لوگو یا متن لازم است.');
        }
        return $out;
    }

    private static function mayKeepOriginalColors(App $app, ?int $userId, array &$cache): bool
    {
        $policy = $app->settings()->string('templates_original_colors', 'admin');
        if ($policy === 'all') {
            return true;
        }
        if ($policy !== 'admin' || $userId === null) {
            return false;
        }
        if (!array_key_exists('user', $cache)) {
            $cache['user'] = $app->users()->find($userId);
        }
        $user = $cache['user'];
        return is_array($user) && (($user['role'] ?? '') === 'admin' || in_array((int) $user['telegram_id'], $app->adminIds(), true));
    }

    /** Absolute path of an asset of this user, or null. */
    public static function assetPath(App $app, int $assetId, ?int $userId, array &$cache = []): ?string
    {
        if ($assetId <= 0 || $userId === null) {
            return null;
        }
        $key = 'asset:' . $assetId;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = null;
            $asset = $app->assets()->findForUser($assetId, $userId);
            if ($asset !== null) {
                try {
                    $abs = $app->storage()->path((string) $asset['path']);
                    $cache[$key] = is_file($abs) ? $abs : null;
                } catch (\InvalidArgumentException) {
                    $cache[$key] = null;
                }
            }
        }
        return $cache[$key];
    }

    /** Resolves a storage path (relative, or absolute inside the storage root). */
    public static function storagePath(App $app, string $path): ?string
    {
        $storage = $app->storage();
        if (str_starts_with($path, '/')) {
            $real = realpath($path);
            return $real !== false && is_file($real) && str_starts_with($real, $storage->root() . '/') ? $real : null;
        }
        try {
            $abs = $storage->path($path);
        } catch (\InvalidArgumentException) {
            return null;
        }
        return is_file($abs) ? $abs : null;
    }

    // ------------------------------------------------------------- storage

    /**
     * Removes what a previous attempt left behind except complete items.
     * @return array<int,list<array<string,mixed>>> item index => rows of complete items
     */
    private function prepareRetry(App $app, int $jobId, int $total): array
    {
        $byItem = [];
        foreach ($app->db()->fetchAll('SELECT * FROM generated_files WHERE job_id = ?', [$jobId]) as $row) {
            $byItem[(int) $row['item_index']][] = $row;
        }
        $complete = [];
        $storage = $app->storage();
        foreach ($byItem as $index => $rows) {
            $mains = array_filter($rows, static fn ($r) => $r['kind'] === 'main' && $r['deleted_at'] === null);
            $ok = $index >= 0 && $index < $total && $mains;
            foreach ($mains as $r) {
                $ok = $ok && $storage->exists((string) $r['path']);
            }
            if ($ok) {
                $complete[$index] = array_values(array_filter($rows, static fn ($r) => $r['deleted_at'] === null));
                continue;
            }
            foreach ($rows as $r) {
                $storage->delete((string) $r['path']);
                $app->db()->delete('generated_files', ['id' => (int) $r['id']]);
            }
        }
        if ($byItem) {
            $app->logger('queue')->info('Generate job resumed', ['job' => $jobId, 'complete_items' => count($complete)]);
        }
        return $complete;
    }

    /**
     * Imports the files of one rendered item and writes all its rows in one transaction.
     * @param array<string,mixed> $job
     * @param array<string,mixed> $result renderer result
     */
    private function storeItem(App $app, array $job, int $index, array $item, string $label, string $kind, string $format, array $result): void
    {
        $tiles = is_array($result['tiles'] ?? null) ? $result['tiles'] : [];
        if (!$tiles) {
            throw new RenderException('هیچ فایلی برای این مورد ساخته نشد.');
        }
        $storage = $app->storage();
        $imported = [];
        $rows = [];
        $jobId = (int) $job['id'];
        $base = [
            'job_id' => $jobId,
            'project_id' => isset($job['project_id']) ? (int) $job['project_id'] : null,
            'user_id' => isset($job['user_id']) ? (int) $job['user_id'] : null,
            'item_index' => $index,
            'is_selected' => 1,
        ];
        $variant = ['label' => $label, 'kind' => $kind, 'tiles' => count($tiles)];
        if ($kind === 'template') {
            $variant['template'] = (string) ($item['template'] ?? '');
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];
            $variant['colors'] = array_values(array_filter([$content['recolor'] ?? null, $content['color'] ?? null], 'is_string'));
            $variant['recolor_mode'] = $content['recolor_mode'] ?? null;
        } else {
            $config = is_array($item['config'] ?? null) ? $item['config'] : [];
            $variant['effects'] = array_values(array_filter(array_map(
                static fn ($e) => is_array($e) ? ($e['type'] ?? null) : null,
                (array) ($config['animation']['effects'] ?? [])
            ), 'is_string'));
            $colors = [$config['text_color'] ?? null];
            if (($config['background']['type'] ?? 'none') !== 'none') {
                $colors[] = $config['background']['color'] ?? null;
            }
            $variant['colors'] = array_values(array_filter($colors, 'is_string'));
        }
        if (!empty($result['warnings'])) {
            $variant['warnings'] = array_values(array_slice((array) $result['warnings'], 0, 5));
        }
        $variantJson = json_encode($variant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = (string) ($result['design_hash'] ?? sha1((string) json_encode($item)));
        $prefix = $kind === 'template' ? (preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($item['template'] ?? ''))) ?: 'emoji') : 'emoji';
        $num = sprintf('%03d', $index + 1);
        try {
            foreach (array_values($tiles) as $t => $tile) {
                $rel = $storage->import((string) $tile['path'], 'generated', $format);
                $imported[] = $rel;
                $abs = $storage->path($rel);
                $rows[] = $base + [
                    'kind' => 'main',
                    'tile' => $t,
                    'format' => $format,
                    'path' => $rel,
                    'filename' => $prefix . '_' . $num . (count($tiles) > 1 ? '_' . ($t + 1) : '') . '.' . $format,
                    'mime' => OutputSender::mimeFor($format),
                    'size_bytes' => (int) filesize($abs),
                    'width' => (int) ($tile['width'] ?? 0),
                    'height' => (int) ($tile['height'] ?? 0),
                    'duration_ms' => (int) round((float) ($result['duration'] ?? 0) * 1000),
                    'fps' => (int) round((float) ($result['fps'] ?? 0)),
                    'frame_count' => (int) ($tile['frames'] ?? 0),
                    'sha1' => (string) sha1_file($abs),
                    'variant' => $variantJson,
                    'variant_hash' => $hash,
                    'validation_status' => self::validationStatus((string) ($tile['status'] ?? '')),
                    'validation_report' => json_encode($tile['validation'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
                ];
            }
            $extras = [
                ['preview', $result['preview'] ?? null, (string) ($result['preview_format'] ?? '')],
                ['thumb', $result['thumb'] ?? null, 'png'],
            ];
            foreach ($extras as [$extraKind, $file, $ext]) {
                if (!is_string($file) || !is_file($file) || !in_array($ext, ['mp4', 'gif', 'png'], true)) {
                    continue;
                }
                [$w, $h] = self::dims($file);
                $rel = $storage->import($file, 'previews', $ext);
                $imported[] = $rel;
                $abs = $storage->path($rel);
                $rows[] = $base + [
                    'kind' => $extraKind,
                    'tile' => 0,
                    'format' => $ext,
                    'path' => $rel,
                    'filename' => $prefix . '_' . $num . '_' . $extraKind . '.' . $ext,
                    'mime' => OutputSender::mimeFor($ext),
                    'size_bytes' => (int) filesize($abs),
                    'width' => $w,
                    'height' => $h,
                    'duration_ms' => $extraKind === 'preview' ? (int) round((float) ($result['duration'] ?? 0) * 1000) : 0,
                    'sha1' => (string) sha1_file($abs),
                    'variant' => $variantJson,
                    'variant_hash' => $hash,
                ];
            }
            $files = $app->files();
            $app->db()->transaction(static function () use ($files, $rows): void {
                foreach ($rows as $row) {
                    $files->create($row);
                }
            });
        } catch (\Throwable $e) {
            foreach ($imported as $rel) {
                $storage->delete($rel);
            }
            throw $e;
        }
    }

    private static function validationStatus(string $status): string
    {
        return in_array($status, ['valid', 'warning', 'invalid'], true) ? $status : 'unchecked';
    }

    /** @return array{0:int,1:int} */
    private static function dims(string $file): array
    {
        $info = @getimagesize($file);
        return is_array($info) ? [(int) $info[0], (int) $info[1]] : [0, 0];
    }

    /** One static grid cell from the stored thumb of an item rendered in an earlier attempt. */
    private function gridFromThumb(App $app, array $rows, string $dir): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] !== 'thumb') {
                continue;
            }
            try {
                $abs = $app->storage()->path((string) $row['path']);
            } catch (\InvalidArgumentException) {
                return false;
            }
            $img = is_file($abs) ? @imagecreatefrompng($abs) : false;
            if ($img === false) {
                return false;
            }
            @mkdir($dir, 0750, true);
            $cell = imagecreatetruecolor(GridComposer::CELL, GridComposer::CELL);
            imagefilledrectangle($cell, 0, 0, GridComposer::CELL - 1, GridComposer::CELL - 1, imagecolorallocate($cell, ...self::PREVIEW_BG));
            imagecopyresampled($cell, $img, 0, 0, 0, 0, GridComposer::CELL, GridComposer::CELL, imagesx($img), imagesy($img));
            $ok = imagejpeg($cell, $dir . '/g000.jpg', 82);
            imagedestroy($img);
            imagedestroy($cell);
            return $ok;
        }
        return false;
    }

    /**
     * Numbered overview pages (25 items each): animated grid + static sheet.
     * A failing page is logged and skipped - the grid is a convenience.
     * @param array<int,string> $gridDirs item number (1-based) => frame dir
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private function buildGrids(JobContext $ctx, array $gridDirs): array
    {
        $app = $ctx->app();
        $storage = $app->storage();
        $job = $ctx->job();
        $composer = new GridComposer(new PreviewEncoder($app->tools(), $app->tools()->runner()));
        ksort($gridDirs);
        foreach (array_chunk($gridDirs, GridComposer::PER_PAGE, true) as $page => $dirs) {
            $ctx->checkCancelled();
            $imported = [];
            try {
                $res = $composer->compose($dirs, $ctx->workDir(), 'grid' . ($page + 1), self::GRID_FPS);
                $outputs = [['sheet', $res['sheet'], 'png']];
                if ($res['animation'] !== null && in_array($res['format'], ['mp4', 'gif'], true)) {
                    $outputs[] = ['grid', $res['animation'], (string) $res['format']];
                }
                foreach ($outputs as [$kind, $file, $ext]) {
                    if (!is_file($file)) {
                        continue;
                    }
                    [$w, $h] = self::dims($ext === 'mp4' ? $res['sheet'] : $file);
                    $rel = $storage->import($file, 'previews', $ext);
                    $imported[] = $rel;
                    $abs = $storage->path($rel);
                    $app->files()->create([
                        'job_id' => (int) $job['id'],
                        'project_id' => isset($job['project_id']) ? (int) $job['project_id'] : null,
                        'user_id' => isset($job['user_id']) ? (int) $job['user_id'] : null,
                        'item_index' => -1,
                        'kind' => $kind,
                        'tile' => $page,
                        'format' => $ext,
                        'path' => $rel,
                        'filename' => $kind . '_' . ($page + 1) . '.' . $ext,
                        'mime' => OutputSender::mimeFor($ext),
                        'size_bytes' => (int) filesize($abs),
                        'width' => $w,
                        'height' => $h,
                        'sha1' => (string) sha1_file($abs),
                        'variant' => json_encode(['page' => $page + 1, 'items' => array_keys($dirs)]),
                    ]);
                }
            } catch (JobCancelled $e) {
                throw $e;
            } catch (\Throwable $e) {
                foreach ($imported as $rel) {
                    $storage->delete($rel);
                }
                $app->logger('queue')->warning('Grid page failed', ['job' => (int) $job['id'], 'page' => $page + 1, 'error' => $e->getMessage()]);
            }
            gc_collect_cycles();
        }
        return [$app->files()->forJob((int) $job['id'], 'grid'), $app->files()->forJob((int) $job['id'], 'sheet')];
    }

    // ----------------------------------------------------------------- zip

    /**
     * Builds the pack ZIP (every main file + manifest.json + README.txt),
     * stores it and returns its generated_files row (kind zip, item_index -1).
     * Shared with the deliver job, which rebuilds a missing ZIP.
     * @param array<string,mixed> $job generate job row
     * @param list<array<string,mixed>> $mains
     * @param list<array{index:int,label:string,error:string}> $failed
     * @return array<string,mixed>
     */
    public static function buildZip(App $app, array $job, array $mains, array $failed, string $kind, string $format, string $workDir): array
    {
        $storage = $app->storage();
        $jobId = (int) $job['id'];
        $zip = new ZipBuilder();
        $items = [];
        $tilesPerItem = [];
        foreach ($mains as $row) {
            $tilesPerItem[(int) $row['item_index']] = ($tilesPerItem[(int) $row['item_index']] ?? 0) + 1;
        }
        foreach ($mains as $row) {
            try {
                $abs = $storage->path((string) $row['path']);
            } catch (\InvalidArgumentException) {
                continue;
            }
            if (!is_file($abs)) {
                continue;
            }
            $name = OutputSender::safeFilename((string) $row['filename']);
            $zip->add($name, $abs);
            $variant = json_decode((string) ($row['variant'] ?? ''), true);
            $variant = is_array($variant) ? $variant : [];
            $report = json_decode((string) ($row['validation_report'] ?? ''), true);
            $entry = [
                'number' => (int) $row['item_index'] + 1,
                'label' => (string) ($variant['label'] ?? ''),
                'file' => $name,
                'format' => (string) $row['format'],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
                'duration_ms' => (int) $row['duration_ms'],
                'fps' => (int) $row['fps'],
                'bytes' => (int) $row['size_bytes'],
                'sha1' => $row['sha1'],
                'validation' => [
                    'status' => (string) $row['validation_status'],
                    'errors' => is_array($report) ? array_values((array) ($report['errors'] ?? [])) : [],
                    'warnings' => is_array($report) ? array_values((array) ($report['warnings'] ?? [])) : [],
                ],
            ];
            if (($tilesPerItem[(int) $row['item_index']] ?? 1) > 1) {
                $entry['tile'] = (int) $row['tile'] + 1;
                $entry['tiles'] = $tilesPerItem[(int) $row['item_index']];
            }
            if (!empty($variant['template'])) {
                $entry['template'] = (string) $variant['template'];
            }
            if (!empty($variant['effects'])) {
                $entry['effects'] = $variant['effects'];
            }
            $items[] = $entry;
        }
        if (!$items) {
            throw new JobFailed('فایل‌های این ساخت دیگر موجود نیستند (خروجی‌ها پس از مدتی پاک می‌شوند). لطفاً دوباره بسازید.');
        }
        $manifest = [
            'generator' => (string) $app->config()->get('name', 'Emoji Maker'),
            'job_id' => $jobId,
            'created_at' => date('c'),
            'kind' => $kind,
            'format' => $format,
            'telegram_sticker_format' => self::stickerFormat($format),
            'sticker_type' => 'custom_emoji',
            'count' => count($items),
            'items' => $items,
            'failed' => array_map(static fn ($f) => ['number' => (int) $f['index'] + 1, 'label' => $f['label'], 'error' => $f['error']], $failed),
        ];
        $zip->addString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addString('README.txt', self::readme($format, count($items), count($failed)));
        $tmp = $workDir . '/pack-' . $jobId . '.zip';
        $zip->write($tmp);
        $old = $app->files()->forJob($jobId, 'zip');
        $rel = $storage->import($tmp, 'generated', 'zip');
        try {
            $abs = $storage->path($rel);
            $id = $app->files()->create([
                'job_id' => $jobId,
                'project_id' => isset($job['project_id']) ? (int) $job['project_id'] : null,
                'user_id' => isset($job['user_id']) ? (int) $job['user_id'] : null,
                'item_index' => -1,
                'kind' => 'zip',
                'tile' => 0,
                'format' => 'zip',
                'path' => $rel,
                'filename' => 'emoji-pack-' . $jobId . '.zip',
                'mime' => 'application/zip',
                'size_bytes' => (int) filesize($abs),
                'sha1' => (string) sha1_file($abs),
                'variant' => json_encode(['count' => count($items), 'format' => $format]),
            ]);
        } catch (\Throwable $e) {
            $storage->delete($rel);
            throw $e;
        }
        foreach ($old as $o) {
            if ($o['path'] !== $rel) {
                $storage->delete((string) $o['path']);
            }
        }
        $row = $app->files()->find($id);
        if ($row === null) {
            throw new \RuntimeException('ZIP row vanished');
        }
        return $row;
    }

    public static function stickerFormat(string $format): string
    {
        return match ($format) {
            'webm' => 'video',
            'tgs' => 'animated',
            default => 'static',
        };
    }

    /** README.txt (Persian): how to publish the files as a custom emoji pack. */
    public static function readme(string $format, int $count, int $failed): string
    {
        $type = match ($format) {
            'webm' => 'Video (ویدیویی)',
            'tgs' => 'Animated (متحرک)',
            default => 'Static (ثابت)',
        };
        $limits = match ($format) {
            'webm' => 'WEBM با کدک VP9، دقیقاً ۱۰۰×۱۰۰ پیکسل، حداکثر ۳ ثانیه، حداکثر ۳۰ فریم در ثانیه، بدون صدا، حداکثر ۲۵۶ کیلوبایت',
            'tgs' => 'TGS (انیمیشن Lottie)، بوم ۵۱۲×۵۱۲، ۶۰ فریم در ثانیه، حداکثر ۳ ثانیه، حداکثر ۶۴ کیلوبایت',
            default => 'PNG دقیقاً ۱۰۰×۱۰۰ پیکسل با پس‌زمینه‌ی شفاف',
        };
        $lines = [
            'راهنمای انتشار ایموجی‌های پریمیوم تلگرام',
            '==========================================',
            '',
            'این فایل شامل ' . OutputSender::fa($count) . ' ایموجی با فرمت ' . strtoupper($format) . ' است.'
                . ($failed > 0 ? ' (' . OutputSender::fa($failed) . ' مورد ساخته نشد.)' : ''),
            'فهرست فایل‌ها و نتیجه‌ی بررسی استاندارد تلگرام در manifest.json آمده است.',
            'مشخصات: ' . $limits . '.',
            '',
            'روش ۱ — ساده‌ترین راه (با همین ربات)',
            'در پیام نتیجه‌ی ساخت، دکمه‌ی «📤 انتشار به‌صورت پک ایموجی» را بزنید؛ ربات پک را می‌سازد و لینک افزودن آن را می‌فرستد.',
            '',
            'روش ۲ — با ربات رسمی تلگرام ‎@Stickers',
            '۱. به ‎@Stickers بروید و دستور ‎/newemojipack را بفرستید.',
            '۲. نوع پک را ' . $type . ' انتخاب کنید.',
            '۳. یک نام برای پک بفرستید.',
            '۴. فایل‌ها را یکی‌یکی به‌صورت «فایل» (Document) بفرستید و بعد از هر فایل، یک یا چند ایموجی مرتبط بفرستید.',
            '۵. در پایان ‎/publish را بزنید، آیکن پک را بفرستید (یا ‎/skip) و یک نام کوتاه برای لینک پک انتخاب کنید.',
            '۶. لینک https://t.me/addemoji/<نام-کوتاه> را با دیگران به اشتراک بگذارید.',
            '',
            'نکته‌ها',
            '• استفاده از ایموجی سفارشی در پیام‌ها نیاز به تلگرام پریمیوم دارد؛ دیدن آن برای همه ممکن است.',
            '• هر پک ایموجی حداکثر ۲۰۰ ایموجی دارد.',
            '• فایل‌هایی که در manifest.json وضعیت invalid دارند را تلگرام نمی‌پذیرد؛ آن‌ها را با تنظیمات ساده‌تر دوباره بسازید.',
            '• نام فایل‌ها را می‌توانید تغییر دهید، ولی پسوند فایل را تغییر ندهید.',
        ];
        return "\u{FEFF}" . implode("\r\n", $lines) . "\r\n";
    }

    // ---------------------------------------------------------- accounting

    /**
     * Daily usage, total counter and (when enabled) credits. Design outputs
     * cost the max cost_units of their effects, template outputs one unit.
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $mains
     */
    private function account(App $app, JobContext $ctx, string $kind, array $items, array $mains): int
    {
        $userId = $ctx->userId();
        if ($userId === null) {
            return 0;
        }
        $indexes = array_unique(array_map(static fn ($r) => (int) $r['item_index'], $mains));
        $units = 0;
        $costs = [];
        foreach ($indexes as $i) {
            if ($kind !== 'design') {
                $units++;
                continue;
            }
            $effects = array_values(array_filter(array_map(
                static fn ($e) => is_array($e) ? ($e['type'] ?? null) : null,
                (array) ($items[$i]['config']['animation']['effects'] ?? [])
            ), 'is_string'));
            sort($effects);
            $key = implode(',', $effects);
            try {
                $costs[$key] ??= $app->presets()->costFor($effects);
            } catch (\Throwable) {
                $costs[$key] = 1;
            }
            $units += $costs[$key];
        }
        $outputs = count($indexes);
        $app->misc()->addUsage($userId, $units, $outputs);
        $app->users()->addGenerated($userId, $outputs);
        $user = $ctx->user();
        if ($app->settings()->bool('credits_enabled', false) && $user !== null && ($user['role'] ?? '') !== 'admin') {
            $app->users()->adjustCredits($userId, -$units);
        }
        return $units;
    }

    private static function countTemplateUse(App $app, string $slug): void
    {
        try {
            $app->db()->query('UPDATE lottie_templates SET uses = uses + 1 WHERE slug = ?', [$slug]);
        } catch (\Throwable) {
            // statistics only
        }
    }
}
