# Implementation contract (internal)

This file fixes the interfaces between the modules that are implemented in
parallel. Everything here is binding: class names, file locations, method
signatures, payload formats, callback data, settings keys. If a module needs
something that is not here, implement it inside its own files and keep the
public surface listed here intact.

General rules (all modules)
* PHP 8.1+, `declare(strict_types=1);`, namespace `EmojiMaker\…`, PSR-4 under `app/`.
  No Composer packages: only PHP extensions (curl, gd, json, mbstring, pdo_mysql, zlib, optional zip/sodium).
* Match the existing code style: `final class`, typed properties/params, short
  docblocks that explain *why*, no TODO / stub / pseudo code / placeholder.
* Database: always through `EmojiMaker\Database\Database` (PDO prepared
  statements, native prepares: **never reuse a named parameter twice in one
  statement**). Schema in `database/schema.sql` (MariaDB 10.3+/MySQL 5.7+;
  `SKIP LOCKED` is NOT available everywhere — do not use it).
* User facing text is Persian (fa), RTL, friendly and short. Developer
  messages / logs in English. Never put a secret (bot token, password, APP_KEY,
  webhook secret) in output, logs, exceptions shown to users or URLs.
* Files: never build a filesystem path from user input; use `Storage` (paths
  relative to the storage root, validated). Uploaded / generated files live in
  private storage outside the web root and are only served by `public/download.php`
  after authorization.
* External processes only through `EmojiMaker\Process\ProcessRunner` (argument
  arrays, no shell).
* Every service is obtained from `EmojiMaker\App` (`app/App.php`, already
  written — read it). `App::boot()` loads `.env` + `config/app.php`.

Existing building blocks (read their code, do not change their behaviour
without a strong reason):
`app/Support/{Config,Env,Logger,Settings}`, `app/Security/{Crypto,Redactor}`,
`app/Database/Database`, `app/Database/Repositories/*`, `app/Storage/Storage`,
`app/Rendering/EmojiRenderer` (design → files), `app/Rendering/GridComposer`
(batch overview), `app/Batch/BatchPlanner` (variants), `app/Templates/*`
(animated templates: `TemplateLibrary`, `TemplateRenderer`, `TemplateRecolor`),
`app/Validation/*`, `app/Encoding/*`, `app/Rendering/ImageProcessor` (upload
inspection / normalisation), `config/app.php` (`telegram_formats`, `defaults`).

---------------------------------------------------------------------------

## 1. Telegram client — `app/Telegram/` (module A)

```php
namespace EmojiMaker\Telegram;

final class TelegramException extends \RuntimeException {
    public function __construct(string $description, int $errorCode = 0, public readonly ?int $retryAfter = null,
                                public readonly ?int $migrateToChatId = null, ?\Throwable $previous = null);
    public function errorCode(): int;            // Telegram error_code (400, 403, 429 ...) or 0 for network errors
    public function isBlockedByUser(): bool;     // 403 "bot was blocked by the user" / user is deactivated
}

final class TelegramClient {
    public function __construct(string $token, string $apiBase, Logger $logger, int $timeout = 30);
    public function hasToken(): bool;
    /** Raw call. $files: field => absolute local path (multipart). Retries 429 (sleep retry_after, max $maxRetries)
     *  and network errors (2x). Returns the "result" member. Throws TelegramException. */
    public function call(string $method, array $params = [], array $files = [], ?int $timeout = null, int $maxRetries = 2): mixed;

    public function getMe(): array;
    public function sendMessage(int|string $chatId, string $text, array $opts = []): array;     // opts: reply_markup(array), parse_mode, reply_to_message_id, disable_web_page_preview, link_preview_options
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $opts = []): array|bool;  // ignores "message is not modified"
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, ?array $markup): array|bool;
    public function deleteMessage(int|string $chatId, int $messageId): bool;                    // never throws for "message to delete not found"
    public function answerCallbackQuery(string $id, string $text = '', bool $alert = false): bool;
    public function sendDocument(int|string $chatId, string $path, string $caption = '', array $opts = [], ?string $filename = null): array;
    public function sendPhoto(int|string $chatId, string $path, string $caption = '', array $opts = []): array;
    public function sendAnimation(int|string $chatId, string $path, string $caption = '', array $opts = []): array;   // mp4/gif previews
    public function sendVideo(int|string $chatId, string $path, string $caption = '', array $opts = []): array;
    public function sendMediaGroup(int|string $chatId, array $media, array $files): array;     // media: InputMedia arrays using attach://<field>
    public function sendChatAction(int|string $chatId, string $action = 'typing'): void;
    public function getFile(string $fileId): array;                                            // {file_id,file_unique_id,file_size,file_path}
    /** Downloads a file by file_path into $dest (atomic, size limited). */
    public function downloadFile(string $filePath, string $dest, int $maxBytes): int;
    public function setWebhook(string $url, string $secret, array $allowedUpdates = ['message', 'callback_query', 'my_chat_member'], int $maxConnections = 20, bool $dropPending = false): bool;
    public function deleteWebhook(bool $dropPending = false): bool;
    public function getWebhookInfo(): array;
    public function setMyCommands(array $commands, ?array $scope = null): bool;
    // custom emoji sticker sets (Bot API 7.2+ InputSticker: {sticker, format, emoji_list, keywords?})
    public function uploadStickerFile(int $userId, string $path, string $format): array;      // format: static|animated|video → File
    public function createNewStickerSet(int $userId, string $name, string $title, array $stickers, string $stickerType = 'custom_emoji', bool $needsRepainting = false, array $files = []): bool;
    public function addStickerToSet(int $userId, string $name, array $sticker, array $files = []): bool;
    public function getStickerSet(string $name): array;
    public function deleteStickerFromSet(string $sticker): bool;
    public function setStickerSetTitle(string $name, string $title): bool;
    public function deleteStickerSet(string $name): bool;
}
```
* JSON params (arrays) are JSON-encoded automatically (reply_markup, stickers, media, …).
* Logging: method name, error code/description, duration — redacted, never the token or full URL.

`app/Telegram/Keyboard.php` — helpers to build markup:
```php
final class Keyboard {
    public static function inline(array $rows): array;                       // rows of [text, callback_data] or ['text'=>..,'url'=>..]
    public static function button(string $text, string $data): array;
    public static function url(string $text, string $url): array;
    public static function reply(array $rows, bool $resize = true, bool $persistent = true, ?string $placeholder = null): array;
    public static function remove(): array;
    /** Throws \LengthException when callback data is longer than 64 bytes. */
    public static function data(string ...$parts): string;                   // joins with ':'
}
```

`app/Telegram/StickerPublisher.php` (module B uses it):
```php
final class StickerPublisher {
    public function __construct(TelegramClient $tg, string $botUsername, array $packLimits /* telegram_formats.pack */);
    /** Valid set name for this bot: "<base>_by_<bot>", [a-z0-9_], starts with a letter, <= 64 chars. */
    public function makeName(string $base, int $userId): string;
    /** Creates the set with the first <=50 files, adds the rest (<=200 total), reports progress via $onProgress(done,total).
     *  $items: list of ['path'=>abs file, 'format'=>'video|animated|static', 'emoji'=>['⭐'], 'keywords'=>[]].
     *  Returns ['name'=>..,'link'=>'https://t.me/addemoji/<name>','added'=>n,'failed'=>[[index, reason]...], 'custom_emoji_ids'=>[index=>id]]. */
    public function publish(int $userId, string $name, string $title, array $items, bool $needsRepainting = false, ?callable $onProgress = null, bool $existing = false): array;
}
```

## 2. Webhook & update processing — `public/webhook.php`, `app/Bot/Webhook.php` (module A)

`public/webhook.php`: boots App, calls `(new \EmojiMaker\Bot\Webhook(App::instance()))->handle()`.

`Webhook::handle()`:
1. Only `POST`, `Content-Type: application/json`, body <= `telegram.max_webhook_body`.
2. Constant-time compare header `X-Telegram-Bot-Api-Secret-Token` with `App::webhookSecret()`;
   missing secret configuration → 503 and log; mismatch → 403 (no detail in body).
3. Optional source IP check (`telegram.ip_check`, ranges in config) incl. `X-Forwarded-For` only when
   `TRUSTED_PROXIES` env lists the proxy.
4. Decode JSON, `update_id` int required. `MiscRepository::storeUpdate()` → duplicate → 200 "ok" and stop.
5. Reply `200 ok` and close the connection *before* processing (`fastcgi_finish_request()` when available,
   else `ignore_user_abort(true)` + `Content-Length` + flush).
6. `WEBHOOK_MODE=inline`: process now with `UpdateProcessor`; `queue`: leave it (`received`) — the worker
   claims pending updates (`MiscRepository::claimPendingUpdates`) and processes them.
7. Mark the update processed / failed (`markUpdate`). Exceptions are logged (redacted), never echoed.

`app/Bot/UpdateProcessor.php` (module C owns the bot logic behind it):
```php
final class UpdateProcessor {
    public function __construct(App $app);
    /** Routes one decoded update to the bot. Must not throw for user errors (answers the user instead). */
    public function process(array $update): void;
}
```

## 3. Queue & worker — `app/Queue/` (module B)

Job types (column `generation_jobs.type`): `preview`, `generate`, `publish`, `deliver`, `update`, `maintenance`.

```php
namespace EmojiMaker\Queue;

final class JobQueue {
    public function __construct(Database $db, Logger $logger);
    /** Returns the job id (existing id when $idempotencyKey was already used). */
    public function push(string $type, array $payload, ?int $userId = null, ?int $projectId = null, int $priority = 5,
                         ?int $chatId = null, ?string $idempotencyKey = null, int $maxAttempts = 3, int $delaySeconds = 0): int;
    /** Atomically claims the next runnable job (status queued, available_at <= now), honouring
     *  max parallel running jobs and at most one running job per user. Uses an UPDATE ... WHERE status='queued'
     *  with rowCount check (optimistic), no SKIP LOCKED. */
    public function claim(string $workerId, int $maxRunning, ?array $types = null): ?array;
    public function heartbeat(int $jobId): void;
    public function progress(int $jobId, int $done, int $total, ?string $text = null, int $failed = 0): void;
    public function setProgressMessage(int $jobId, int $chatId, int $messageId): void;
    public function complete(int $jobId, array $result): void;
    /** Retryable failures go back to queued with exponential backoff while attempts < max_attempts. */
    public function fail(int $jobId, string $error, bool $retryable = true): void;
    public function cancel(int $jobId, ?int $userId = null): bool;          // queued → cancelled, running → cancel_requested=1
    public function isCancelRequested(int $jobId): bool;
    public function retry(int $jobId): bool;                                 // failed/cancelled → queued (admin)
    public function recoverStale(int $staleSeconds): int;                    // running without heartbeat → queued/failed
    public function find(int $jobId): ?array;
    public static function payload(array $job): array;                       // decoded payload
    public function activeCountForUser(int $userId): int;                    // queued + running
    public function listForAdmin(int $page, int $perPage, array $filters): array;   // ['rows'=>..,'total'=>..]
    public function stats(): array;                                          // counts by status/type, avg duration, last finished
    public function purgeFinished(int $days): int;
}

final class JobContext {   // given to handlers
    public function __construct(App $app, JobQueue $queue, array $job);
    public function job(): array; public function id(): int; public function payload(): array;
    public function userId(): ?int; public function chatId(): ?int;
    public function app(): App;
    /** Progress for DB + throttled (>= 3 s) edit of the Telegram progress message with a Persian bar. */
    public function progress(int $done, int $total, string $text = ''): void;
    public function heartbeat(): void;
    /** Throws JobCancelled when the user / admin cancelled. Call between items. */
    public function checkCancelled(): void;
    public function workDir(): string;        // private temp dir for this job (storage tmp), removed after the job
}
final class JobCancelled extends \RuntimeException {}
final class JobFailed extends \RuntimeException {   // user facing Persian message
    public function __construct(string $userMessage, public readonly bool $retryable = false, ?\Throwable $previous = null);
}

interface JobHandler { public function handle(JobContext $ctx): array; /* result summary */ }
```
Handlers in `app/Queue/Handlers/`: `PreviewHandler`, `GenerateHandler`, `PublishHandler`,
`DeliverHandler`, `UpdateHandler` (processes a stored update with `UpdateProcessor`),
`MaintenanceHandler`.

`app/Queue/Worker.php` + `bin/worker.php`:
* `php bin/worker.php --once [--time-limit=55]` (cron, cPanel: every minute) — loops claim/handle until the
  time limit or no job; `--daemon` (systemd/supervisor: runs forever, sleeps 1 s when idle, SIGTERM/SIGINT
  graceful via pcntl when available, restarts itself after `--max-jobs` or memory > limit);
  `--slots=N` daemon spawns N child workers with `proc_open` (fallback: run sequentially).
* Each loop also: recover stale jobs, process pending updates in queue mode, writes
  `storage/cache/worker.json` heartbeat {pid, host, at, jobs_done, version} (admin dashboard shows it).
* Respects settings `bot_enabled` (still processes), `WORKER_MAX_PARALLEL`, memory limit.
* `bin/cron.php`: single cron entry for shared hosting: runs worker `--once` and, once per hour (lock file),
  the maintenance task (cleanup).

### Job payloads (JSON in `generation_jobs.payload`)

`preview` — quick look before generating (first item, or a template with content):
```json
{"project_id": 12, "kind": "design|template", "format": "webm|tgs|png",
 "item": {"config": {…design config…}} | {"template": "tofan-05", "content": {…template content…}},
 "reply_to": 345}
```
Result: sends the preview animation (sendAnimation mp4/gif; fallback photo) with the inline keyboard
`[✅ ساخت نهایی] [✏️ ویرایش] [🔁 پیش‌نمایش دیگر]` (callbacks `w:gen:<project>` / `w:edit:<project>` / `w:prev:<project>`
for designs, `t:gen:<project>` / `t:edit:<project>` / `t:prev:<project>` for templates).

`generate` — final files:
```json
{"project_id": 12, "kind": "design|template", "format": "webm|tgs|png",
 "items": [{"label": "پالس + درخشش", "config": {…}} | {"label": "قالب ۵", "template": "tofan-05", "content": {…}}],
 "grid": true, "send": "auto|files|zip"}
```
* Items are planned by the bot (designs: `BatchPlanner::plan()`; templates: one item per selected template).
* For every item: render (EmojiRenderer / TemplateRenderer) into `JobContext::workDir()`, import the main
  file(s) (all tiles of a banner), preview and thumb into storage (`Storage::import`), create
  `generated_files` rows (`kind` main/preview/thumb, `item_index`, `variant` JSON {label, template?, effects?,
  colors?}, `variant_hash`, validation status/report, size, dims, fps, frame_count, duration_ms, sha1).
  A failing item is recorded (`failed_items`) and the job continues.
* `grid: true` and >1 item: GridComposer animation + contact sheet (kind grid / sheet).
* Always a ZIP (`kind` zip, item_index -1) with every main file + `manifest.json` (label, file, format,
  validation) + `README.txt` (Persian: how to publish).
* Delivery: `send=auto` → ≤10 items: each main file as document (caption: label + validation status) plus the
  grid; >10: grid + ZIP. Keyboard after delivery:
  `[📤 انتشار به‌صورت پک ایموجی (pub:new:<job>)] [⬇️ دریافت ZIP (j:zip:<job>)] [🔗 لینک دانلود (j:link:<job>)] [🔁 ساخت دوباره (j:again:<job>)]`.
* Usage accounting (`MiscRepository::addUsage`, `UserRepository::addGenerated`), project status / outputs.

`publish`:
```json
{"generate_job_id": 55, "file_ids": [301, 302], "set_name": "abc_by_mybot" | null, "title": "…",
 "emoji": "⭐", "needs_repainting": false, "sticker_set_id": null | 7}
```
Creates (or extends) the custom emoji set with `StickerPublisher`, stores `sticker_sets` + updates
`generated_files.sticker_set_id / telegram_file_id / custom_emoji_id`, sends the `https://t.me/addemoji/<name>` link.

`deliver`: `{"generate_job_id": 55, "mode": "zip|files|link"}` — re-sends outputs.
`update`: `{"update_id": 123}`.  `maintenance`: `{"task": "cleanup|tools|purge"}`.

## 4. Downloads — `app/Delivery/` + `public/download.php` (module B)

```php
namespace EmojiMaker\Delivery;
final class ZipBuilder {           // ZipArchive when available, otherwise a pure PHP ZIP writer (store + deflate, CRC32)
    public function __construct(?bool $forcePure = null);
    public function add(string $nameInZip, string $absPath): void;    // names sanitised (no ../, no absolute)
    public function addString(string $nameInZip, string $data): void;
    public function write(string $destAbsPath): int;                  // bytes
}
final class DownloadLinks {
    public function __construct(Crypto $crypto, string $baseUrl);
    /** https://…/download.php?f=<fileId>&u=<userId>&e=<expires>&s=<hmac> */
    public function make(int $fileId, int $userId, int $ttlSeconds): string;
    /** Returns [fileId, userId] or null (bad signature / expired). Constant time. */
    public function verify(array $query): ?array;
}
```
`public/download.php`: verify link, load file row, check `user_id` matches and not deleted, resolve via
`Storage::path()`, stream with `Content-Type`, `Content-Disposition: attachment; filename*=UTF-8''…`,
`X-Content-Type-Options: nosniff`, `Cache-Control: private`, range support not required. Admin
sessions may download any file (`?admin=1` + valid admin session + CSRF-free GET is acceptable only for
admins with role >= operator).

## 5. Bot conversation — `app/Bot/` (module C)

Persian bot. Entry `UpdateProcessor::process()`. Design:
* `app/Bot/Context.php` (update, user row (upsert via `UserRepository::upsertFromTelegram($from, $app->adminIds())`),
  chat id, message, callback, helpers `reply()`, `edit()`, `toast()`, `state()`, `setState()`),
  `app/Bot/Router.php`, controllers in `app/Bot/Controllers/`, texts in `app/Bot/Texts.php`,
  conversation state in `users.state` + `users.state_data` (JSON), current project in `users.current_project_id`.
* Guards for every update: blocked user → short Persian notice; `bot_enabled=false` → maintenance message
  (admins bypass); rate limit `rate_limit_per_minute` (`MiscRepository::hit("u:<id>")`); private chats only.
* Main menu (reply keyboard, exactly these labels, in this order):
  `🎨 ساخت ایموجی جدید`, `✨ قالب‌های آماده پریمیوم`, `🖼 ساخت از روی عکس یا لوگو`, `🔤 ساخت از روی متن`,
  `🎞 انتخاب انیمیشن`, `📦 ساخت پک چندتایی`, `🗂 پروژه‌ها و خروجی‌های من`, `❓ راهنما`.
  Commands: `/start` (welcome + menu; deep link `/start t_<slug>` opens a template), `/menu`, `/help`,
  `/cancel` (resets state), `/templates`, `/projects`, `/admin` (bot admins: quick stats, queue, toggle bot).
* Design wizard (inline keyboards, each step editable, back / cancel buttons, state persisted):
  input (text / image / both) → text entry (validation: length `max_text_length`, lines `max_text_lines`,
  missing glyph warning) and/or image upload (photo or document PNG/JPG/WebP; size `max_upload_bytes`,
  pixels; `ImageProcessor::inspect` + `normalize`; optional background removal / crop) →
  style (font list from `FontRegistry`, text colour presets + custom hex, outline, shadow, glow, depth,
  background shape/colour/gradient/border, size) → animation (effects from enabled `animation_presets`,
  combos, duration, speed, intensity, direction, sequence/parallel) → format (WEBM / TGS (if `allow_tgs` and
  permission) / PNG) → preview job → generate job.
* Batch (`📦 ساخت پک چندتایی`): count presets from `preset_counts` + custom count (≤ `max_batch_count`,
  user `max_batch`), mode (`BatchPlanner::MODES`), colours/effects/frames/words selection, then a generate job
  with `grid: true`. Daily quota `daily_limit` (`MiscRepository::todayUnits`), queued jobs ≤ `max_queued_jobs_per_user`.
* Templates (`✨ قالب‌های آماده پریمیوم`, only when `templates_enabled`):
  pack list (`TemplateLibrary::packs()`, names) → template gallery (paged, 6–8 per page, each with a
  pre-rendered thumb/preview from `TemplateLibrary::previewPath()` — send an album or an animated preview,
  plus select buttons; "select all of this pack") → multi-select (up to `max_template_batch`) →
  content: send a logo (PNG with transparency recommended; same upload checks) and/or a text, optional
  caption text (shown only when a selected template has a caption slot), font + text colour →
  **colour**: presets `TemplateRecolor::PRESETS` + custom hex + mode (only brand colour `main` / all / gray).
  The original colours option is shown only when `templates_original_colors` allows it for this user
  (`admin` = bot admins only); otherwise a colour must be chosen (default suggestion: the user's last colour
  or `templates_demo_color`) → format → preview job (first selected template) → generate job with one item
  per template, `content` =
  `{"logo": <asset id or null>, "text": "...", "caption": "...", "font": "...", "color": "#..", "outline": null,
    "outline_width": 0, "recolor": "#..|null", "recolor_mode": "main|all|gray"}`
  (the handler resolves the logo asset id to its storage path and passes TemplateRenderer content keys).
* Projects (`🗂`): paged list of projects + last outputs, open → regenerate / duplicate / delete / get ZIP /
  download link / publish.
* Publish (`pub:*`): ask for a title (≤64), propose a set name, choose emoji (default
  `publish_default_emoji`), new set or add to one of the user's sets, needs_repainting toggle (only
  meaningful for single colour emoji) → `publish` job. Requires `allow_publish` and permission.
* Callback data: `<ns>:<action>[:<arg>…]`, ≤ 64 bytes, namespaces `m` (menu), `w` (wizard), `b` (batch),
  `t` (templates), `p` (projects), `pub` (publish), `j` (jobs: zip/link/again/cancel), `adm` (bot admin).
  Every callback re-checks ownership of the referenced project / job / file (user id) before acting.
* Uploads: download via `TelegramClient::getFile` + `downloadFile` into storage `uploads/`, register with
  `AssetRepository::create` (sha1, mime, dims, has_alpha), never trust the file name or the declared MIME
  (`ImageProcessor::sniff`).

## 6. Admin panel — `public/admin/index.php`, `app/Admin/` (module D)

Front controller with routes `?r=<page>[&a=<action>]`; Persian RTL responsive UI (own CSS in
`public/assets/admin.css`, small JS in `public/assets/admin.js`, no CDN), dark/light friendly.
* Auth: `admin_users` (password_hash/verify, rehash), login throttle (`failed_attempts`, `locked_until`,
  per-IP rate limit), session hardening (cookie HttpOnly, SameSite=Strict, Secure on HTTPS, regenerate id on
  login, idle timeout `admin.session_lifetime`, sessions stored in storage `sessions/`), CSRF token on every
  POST, roles: superadmin > admin > operator > viewer (viewer read only; operator: jobs/outputs/users block;
  admin: + presets/templates/settings except secrets/admins; superadmin: everything).
  Security headers (CSP default-src 'self', frame-ancestors 'none', nosniff, referrer-policy).
* Pages: dashboard (users, active users today, jobs by status, outputs today, storage usage, queue health,
  worker heartbeat, tool capabilities), users (search, view, block/unblock, role, limits, credits, permissions),
  projects & outputs (filters, preview thumbs via an admin-only image endpoint, download, delete),
  jobs (list, filters, cancel, retry, view payload/result/error), effects & presets (`animation_presets`
  enable/disable, names, params JSON validated, cost, premium, order), templates (packs and templates: thumbs,
  status, enable/disable (`lottie_templates`), rename, order, re-render previews, view spec, import a ZIP pack
  via `PackImporter` into storage/templates, delete imported pack), fonts (upload TTF/OTF with validation
  via `TrueTypeFont`, enable/disable), settings (all `defaults` keys grouped, typed inputs; bot token and
  webhook secret as encrypted `secret` settings — shown masked, replace only; "set webhook" / "webhook info" /
  "test getMe" buttons; storage path shown read-only; cleanup now), logs (tail of redacted log files),
  audit log, system (doctor checks), admins (superadmin: create/disable/reset password).
* Every state change writes `MiscRepository::audit()`.

## 7. Install & operations (module E)

* `bin/install.php` (CLI) and `public/install/index.php` (web installer, disabled after install by
  `storage/installed.lock`, and only reachable with an install token printed/stored on first run):
  checks (PHP version/extensions, proc_open, ffmpeg + libvpx-vp9, writable storage, storage outside web root),
  writes `.env` (APP_KEY via `Crypto::generateKey()`, DB credentials, URL, bot token, admin ids, webhook
  secret = random), imports `database/schema.sql` + `database/seed.sql`, creates the superadmin, syncs
  templates (`TemplateLibrary::syncDatabase()`), sets the webhook (`setWebhook` with secret) and bot commands.
* `database/seed.sql`: `animation_presets` for all 21 effects + useful combos (Persian names), `fonts` rows for
  built-in fonts are NOT needed (FontRegistry::BUILTIN), `design_templates` (a few ready design presets).
* `bin/`: `set-webhook.php`, `create-admin.php`, `doctor.php` (environment report, exit code), `cleanup.php`,
  `lint.php` (php -l all files), `build-zip.php` (release ZIP without .env / storage data / vendor),
  `validate.php <file>` (validate a webm/tgs/png against Telegram limits).
* `deploy/`: nginx site, Apache vhost, `.htaccess` files (root deny + public), systemd
  `emoji-worker.service` (+ `@` template), supervisor conf, crontab examples (cPanel and Linux), aaPanel notes.

## 8. Tests — `tests/` (module G)

`php tests/run.php` runs every `tests/*Test.php` (tiny assertion helper, no PHPUnit). Integration tests use a
real MariaDB database from `TEST_DB_*` env vars (skipped with a notice when absent) and a mock Telegram Bot
API server (`tests/Mock/telegram_mock.php` run with `php -S`, records calls to a JSON file, returns realistic
results) by pointing `TELEGRAM_API_BASE` at it.
