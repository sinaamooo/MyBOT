<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Queue\JobQueue;
use EmojiMaker\Telegram\Keyboard;
use EmojiMaker\Telegram\TelegramException;

/**
 * "🗂 پروژه‌ها و خروجی‌های من": paged project list, project page (edit,
 * repeat, duplicate, delete), the outputs of a generate job (ZIP, files,
 * signed link, publish, choose which emoji to publish) and job cancel.
 *
 * Callbacks: p:list:<page>, p:open:<pid>, p:dup:<pid>, p:del:<pid>, p:delok:<pid>,
 *            j:zip|files|link|again|cancel:<job>, j:sel:<job>:<page>,
 *            j:ts:<job>:<item>:<page>, j:sa:<job>:<0|1>:<page>
 */
final class ProjectController extends Controller
{
    private const PER_PAGE = 6;
    private const SEL_PER_PAGE = 24;

    public function list(Context $ctx, int $page): void
    {
        $ctx->clearState();
        $res = $this->app->projects()->listForUser($ctx->userId(), 1, 1);
        $total = (int) $res['total'];
        if ($total === 0) {
            $text = "🗂 <b>پروژه‌های من</b>\n\nهنوز پروژه‌ای نساخته‌ای. از اینجا شروع کن 👇";
            $kb = Keyboard::inline([
                [['✨ قالب‌های آماده', 't:packs'], ['🎨 طراحی جدید', 'w:new:text']],
            ]);
            $ctx->isCallback() ? $ctx->edit($text, $kb) : $ctx->reply($text, $kb);
            $ctx->toast();
            return;
        }
        $pages = (int) ceil($total / self::PER_PAGE);
        $page = max(1, min($pages, $page));
        $rows = $this->app->projects()->listForUser($ctx->userId(), $page, self::PER_PAGE)['rows'];
        $lines = ['🗂 <b>پروژه‌های من</b> (' . Texts::fa($total) . ')'];
        if ($pages > 1) {
            $lines[] = 'صفحه‌ی ' . Texts::fa($page) . ' از ' . Texts::fa($pages);
        }
        $lines[] = '';
        $buttons = [];
        foreach ($rows as $i => $p) {
            $n = ($page - 1) * self::PER_PAGE + $i + 1;
            $title = $this->title($p);
            $lines[] = Texts::fa($n) . '. ' . self::typeIcon((string) $p['input_type']) . ' <b>' . Texts::e(Texts::cut($title, 40)) . '</b>';
            $meta = [self::statusLabel((string) $p['status'])];
            if ((int) $p['outputs_count'] > 0) {
                $meta[] = Texts::fa((int) $p['outputs_count']) . ' خروجی';
            }
            $meta[] = Texts::date((string) $p['updated_at']);
            $lines[] = '    ' . implode(' · ', $meta);
            $buttons[] = [Texts::fa($n) . '. ' . Texts::cut($title, 22), 'p:open:' . (int) $p['id']];
        }
        $kb = Keyboard::rows($buttons, 2);
        $nav = [];
        if ($page > 1) {
            $nav[] = ['▶️ قبلی', 'p:list:' . ($page - 1)];
        }
        if ($page < $pages) {
            $nav[] = ['بعدی ◀️', 'p:list:' . ($page + 1)];
        }
        if ($nav) {
            $kb[] = $nav;
        }
        $text = implode("\n", $lines);
        $markup = Keyboard::inline($kb);
        $ctx->isCallback() ? $ctx->edit($text, $markup) : $ctx->reply($text, $markup);
        $ctx->toast();
    }

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        $ns = $p[0] ?? '';
        $a = $p[1] ?? '';
        if ($ns === 'p') {
            match ($a) {
                'list' => $this->list($ctx, max(1, (int) ($p[2] ?? 1))),
                'open' => $this->open($ctx, (int) ($p[2] ?? 0)),
                'dup' => $this->duplicate($ctx, (int) ($p[2] ?? 0)),
                'del' => $this->confirmDelete($ctx, (int) ($p[2] ?? 0)),
                'delok' => $this->delete($ctx, (int) ($p[2] ?? 0)),
                default => $this->expired($ctx),
            };
            return;
        }
        $jobId = (int) ($p[2] ?? 0);
        match ($a) {
            'zip' => $this->deliver($ctx, $jobId, 'zip'),
            'files' => $this->deliver($ctx, $jobId, 'files'),
            'link' => $this->deliver($ctx, $jobId, 'link'),
            'again' => $this->again($ctx, $jobId),
            'cancel' => $this->cancel($ctx, $jobId),
            'sel' => $this->selection($ctx, $jobId, max(1, (int) ($p[3] ?? 1))),
            'ts' => $this->toggle($ctx, $jobId, (int) ($p[3] ?? -1), max(1, (int) ($p[4] ?? 1))),
            'sa' => $this->selectAll($ctx, $jobId, ($p[3] ?? '1') === '1', max(1, (int) ($p[4] ?? 1))),
            default => $this->expired($ctx),
        };
    }

    // ------------------------------------------------------------ projects

    private function open(Context $ctx, int $pid): void
    {
        $project = $this->project($ctx, $pid);
        if ($project === null) {
            $this->expired($ctx);
            return;
        }
        $ctx->clearState();
        $this->app->users()->setCurrentProject($ctx->userId(), $pid);
        $type = (string) $project['input_type'];
        $lines = [
            '📂 <b>' . Texts::e(Texts::cut($this->title($project), 60)) . '</b>',
            '',
            'نوع: ' . self::typeIcon($type) . ' ' . self::typeName($type),
            'وضعیت: ' . self::statusLabel((string) $project['status']),
            'ساخته‌شده در: ' . Texts::date((string) $project['created_at']),
        ];
        if ($type === 'template') {
            $n = count((array) ($this->config($project)['templates'] ?? []));
            $lines[] = 'قالب‌های انتخاب‌شده: ' . Texts::fa($n);
        }
        $kb = [];
        $ns = $type === 'template' ? 't' : 'w';
        $kb[] = [['✏️ ویرایش و ساخت', $ns . ':dash:' . $pid]];
        $job = $this->lastGenerate($ctx, $project);
        $done = $job !== null && $job['status'] === 'completed' ? $job : $this->lastGenerate($ctx, $project, true);
        if ($job !== null) {
            $lines[] = '';
            $lines[] = '<b>آخرین ساخت</b> (کار #' . Texts::fa((int) $job['id']) . '):';
            $lines[] = $this->jobLine($job);
            if ($done !== null && $done !== $job) {
                $lines[] = 'آخرین خروجی آماده: کار #' . Texts::fa((int) $done['id']) . ' (' . Texts::date((string) $done['finished_at']) . ')';
            }
            if ($done !== null) {
                $id = (int) $done['id'];
                $kb[] = [['📨 ارسال فایل‌ها', 'j:files:' . $id], ['⬇️ دریافت ZIP', 'j:zip:' . $id]];
                $kb[] = [['🔗 لینک دانلود', 'j:link:' . $id], ['☑️ انتخاب برای انتشار', 'j:sel:' . $id . ':1']];
                if ($this->canPublish($ctx)) {
                    $kb[] = [['📤 انتشار به‌صورت پک ایموجی', 'pub:new:' . $id]];
                }
            }
            if (in_array($job['status'], ['queued', 'running'], true)) {
                $kb[] = [['✖️ لغو این ساخت', 'j:cancel:' . $job['id']]];
            } else {
                $kb[] = [['🔁 تکرار همین ساخت', 'j:again:' . $job['id']]];
            }
        } else {
            $lines[] = '';
            $lines[] = 'هنوز خروجی نهایی از این پروژه ساخته نشده است.';
        }
        $kb[] = [['📄 کپی پروژه', 'p:dup:' . $pid], ['🗑 حذف', 'p:del:' . $pid]];
        $kb[] = [[Texts::BACK, 'p:list:1']];
        $ctx->edit(implode("\n", $lines), Keyboard::inline($kb));
        $ctx->toast();
    }

    private function duplicate(Context $ctx, int $pid): void
    {
        if ($this->project($ctx, $pid) === null) {
            $this->expired($ctx);
            return;
        }
        $this->makeRoom($ctx);
        $id = $this->app->projects()->duplicate($pid, $ctx->userId());
        if ($id === null) {
            $this->expired($ctx);
            return;
        }
        $ctx->toast('📄 یک کپی ساخته شد');
        $this->open($ctx, $id);
    }

    private function confirmDelete(Context $ctx, int $pid): void
    {
        $project = $this->project($ctx, $pid);
        if ($project === null) {
            $this->expired($ctx);
            return;
        }
        $ctx->edit(
            '🗑 پروژه‌ی <b>' . Texts::e(Texts::cut($this->title($project), 60)) . "</b> حذف شود؟\nخروجی‌هایی که قبلاً فرستاده شده‌اند در گفت‌وگو می‌مانند.",
            Keyboard::inline([[['✅ بله، حذف کن', 'p:delok:' . $pid], ['✖️ نه', 'p:open:' . $pid]]])
        );
        $ctx->toast();
    }

    private function delete(Context $ctx, int $pid): void
    {
        $project = $this->project($ctx, $pid);
        if ($project === null) {
            $this->expired($ctx);
            return;
        }
        $job = $this->lastGenerate($ctx, $project);
        if ($job !== null && in_array($job['status'], ['queued', 'running'], true)) {
            $this->app->queue()->cancel((int) $job['id'], $ctx->userId());
        }
        $this->app->projects()->softDelete($pid, $ctx->userId());
        if ((int) ($ctx->user['current_project_id'] ?? 0) === $pid) {
            $this->app->users()->setCurrentProject($ctx->userId(), null);
        }
        $ctx->toast('🗑 حذف شد');
        $this->list($ctx, 1);
    }

    // ---------------------------------------------------------------- jobs

    /** Re-sends the outputs of a finished generate job (deliver job). */
    private function deliver(Context $ctx, int $jobId, string $mode): void
    {
        $job = $this->ownJob($ctx, $jobId, ['generate']);
        if ($job === null) {
            return;
        }
        if ($job['status'] !== 'completed') {
            $ctx->toast(in_array($job['status'], ['queued', 'running'], true) ? '⏳ این ساخت هنوز تمام نشده است.' : 'این ساخت خروجی ندارد؛ «تکرار همین ساخت» را بزن.', true);
            return;
        }
        if ($mode === 'link' && (!$this->app->hasKey() || !preg_match('#^https://#i', $this->app->url()))) {
            $ctx->toast('لینک دانلود روی این سرور فعال نیست؛ از «دریافت ZIP» استفاده کن.', true);
            return;
        }
        // one request per job and mode every 20 s (double taps)
        if (!$this->app->misc()->hit('dl:' . $jobId . ':' . $mode, 1, 20)) {
            $ctx->toast('⏳ در حال آماده‌سازی است…');
            return;
        }
        $id = $this->pushJob($ctx, 'deliver', ['generate_job_id' => $jobId, 'mode' => $mode], $job['project_id'] !== null ? (int) $job['project_id'] : null, 0, 7);
        if ($id !== null) {
            $ctx->toast(match ($mode) {
                'zip' => '📦 فایل ZIP آماده و ارسال می‌شود…',
                'link' => '🔗 لینک دانلود ساخته می‌شود…',
                default => '📨 فایل‌ها ارسال می‌شوند…',
            });
        }
    }

    /** Runs a preview / generate job again with exactly the same settings. */
    private function again(Context $ctx, int $jobId): void
    {
        $job = $this->ownJob($ctx, $jobId, ['generate', 'preview']);
        if ($job === null) {
            return;
        }
        if (in_array($job['status'], ['queued', 'running'], true)) {
            $ctx->toast('⏳ این کار هنوز در صف یا در حال اجراست.', true);
            return;
        }
        $payload = JobQueue::payload($job);
        $pid = $job['project_id'] !== null ? (int) $job['project_id'] : null;
        if ($pid !== null && $this->project($ctx, $pid) === null) {
            $ctx->toast('این پروژه حذف شده است.', true);
            return;
        }
        if (($payload['format'] ?? '') === 'tgs' && !$this->canTgs($ctx)) {
            $ctx->toast('ساخت TGS برای حساب شما فعال نیست.', true);
            return;
        }
        if (!$this->app->misc()->hit('again:' . $jobId, 1, 15)) {
            $ctx->toast('⏳ درخواست قبلی در حال ثبت است…');
            return;
        }
        if ($job['type'] === 'preview') {
            $id = $this->pushJob($ctx, 'preview', $payload, $pid, 0, 8);
            if ($id !== null) {
                $ctx->toast('در حال ساخت پیش‌نمایش…');
            }
            return;
        }
        $items = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));
        $limit = ($payload['kind'] ?? '') === 'template'
            ? max(1, $this->app->settings()->int('max_template_batch', 50))
            : $this->maxBatch($ctx);
        if (!$items || (!$ctx->isAdmin() && count($items) > $limit)) {
            $ctx->toast('این ساخت با محدودیت‌های فعلی قابل تکرار نیست؛ از «ویرایش و ساخت» استفاده کن.', true);
            return;
        }
        $id = $this->pushJob($ctx, 'generate', $payload, $pid, $this->jobUnits($payload));
        if ($id !== null) {
            if ($pid !== null) {
                $this->app->projects()->setStatus($pid, 'queued', $id);
            }
            $ctx->toast();
            $ctx->reply(sprintf(Texts::QUEUED, Texts::fa($id)) . (count($items) > 1 ? "\nتعداد: " . Texts::fa(count($items)) . ' ایموجی' : ''));
        }
    }

    private function cancel(Context $ctx, int $jobId): void
    {
        $job = $this->ownJob($ctx, $jobId, null);
        if ($job === null) {
            return;
        }
        if (!in_array($job['status'], ['queued', 'running'], true)) {
            $ctx->toast('این کار قبلاً تمام شده است.');
            $this->dropKeyboard($ctx);
            return;
        }
        if ($this->app->queue()->cancel($jobId, $ctx->userId())) {
            $ctx->toast($job['status'] === 'queued' ? '⛔️ لغو شد' : '⛔️ درخواست لغو ثبت شد؛ کار بعد از مورد فعلی متوقف می‌شود.', $job['status'] !== 'queued');
            if ($job['status'] === 'queued') {
                $ctx->edit('⛔️ کار #' . Texts::fa($jobId) . ' لغو شد.');
                $project = $job['project_id'] !== null ? $this->project($ctx, (int) $job['project_id']) : null;
                if ($project !== null && $job['type'] === 'generate') {
                    $this->app->projects()->setStatus((int) $project['id'], (int) $project['outputs_count'] > 0 ? 'completed' : 'draft');
                }
            }
            return;
        }
        $ctx->toast('لغو ممکن نشد؛ احتمالاً کار همین الان تمام شد.');
    }

    // ----------------------------------------------------------- selection

    /** Which emoji of a generate job are published (numbers match the grid preview). */
    private function selection(Context $ctx, int $jobId, int $page): void
    {
        $job = $this->ownJob($ctx, $jobId, ['generate']);
        if ($job === null) {
            return;
        }
        $items = $this->items($jobId);
        if (!$items) {
            $ctx->toast('فایل‌های این ساخت دیگر موجود نیستند.', true);
            return;
        }
        $total = count($items);
        $pages = (int) ceil($total / self::SEL_PER_PAGE);
        $page = max(1, min($pages, $page));
        $selected = count(array_filter($items, static fn ($i) => $i['selected']));
        $lines = [
            '☑️ <b>انتخاب ایموجی‌ها برای انتشار</b>',
            'شماره‌ها همان شماره‌های تصویر پیش‌نمایش هستند. روی هر شماره بزن تا انتخاب یا حذف شود.',
            '',
            'انتخاب‌شده: ' . Texts::fa($selected) . ' از ' . Texts::fa($total),
        ];
        $invalid = count(array_filter($items, static fn ($i) => $i['invalid']));
        if ($invalid > 0) {
            $lines[] = '❌ ' . Texts::fa($invalid) . ' مورد با استاندارد تلگرام سازگار نیست و منتشر نمی‌شود.';
        }
        if ($pages > 1) {
            $lines[] = 'صفحه‌ی ' . Texts::fa($page) . ' از ' . Texts::fa($pages);
        }
        $buttons = [];
        foreach (array_slice($items, ($page - 1) * self::SEL_PER_PAGE, self::SEL_PER_PAGE) as $it) {
            $mark = $it['invalid'] ? '❌' : ($it['selected'] ? '✅' : '▫️');
            $buttons[] = [$mark . ' ' . Texts::fa($it['index'] + 1), 'j:ts:' . $jobId . ':' . $it['index'] . ':' . $page];
        }
        $kb = Keyboard::rows($buttons, 4);
        $nav = [];
        if ($page > 1) {
            $nav[] = ['▶️ قبلی', 'j:sel:' . $jobId . ':' . ($page - 1)];
        }
        if ($page < $pages) {
            $nav[] = ['بعدی ◀️', 'j:sel:' . $jobId . ':' . ($page + 1)];
        }
        if ($nav) {
            $kb[] = $nav;
        }
        $kb[] = [['✅ همه', 'j:sa:' . $jobId . ':1:' . $page], ['▫️ هیچ‌کدام', 'j:sa:' . $jobId . ':0:' . $page]];
        if ($this->canPublish($ctx) && $selected > 0) {
            $kb[] = [['📤 انتشار ' . Texts::fa($selected) . ' ایموجی انتخاب‌شده', 'pub:new:' . $jobId]];
        }
        $ctx->edit(implode("\n", $lines), Keyboard::inline($kb));
        $ctx->toast();
    }

    private function toggle(Context $ctx, int $jobId, int $index, int $page): void
    {
        if ($index < 0 || $this->ownJob($ctx, $jobId, ['generate']) === null) {
            if ($index < 0) {
                $this->expired($ctx);
            }
            return;
        }
        $on = $this->app->files()->toggleSelected($jobId, $index);
        $ctx->toast(Texts::fa($index + 1) . ($on ? ' انتخاب شد' : ' برداشته شد'));
        $this->selection($ctx, $jobId, $page);
    }

    private function selectAll(Context $ctx, int $jobId, bool $on, int $page): void
    {
        if ($this->ownJob($ctx, $jobId, ['generate']) === null) {
            return;
        }
        $this->app->files()->selectAll($jobId, $on);
        $ctx->toast($on ? 'همه انتخاب شدند' : 'همه برداشته شدند');
        $this->selection($ctx, $jobId, $page);
    }

    // ------------------------------------------------------------- helpers

    /**
     * The job when it belongs to this user (and has one of the types);
     * otherwise the user is told and null is returned.
     * @param list<string>|null $types
     * @return array<string,mixed>|null
     */
    private function ownJob(Context $ctx, int $jobId, ?array $types): ?array
    {
        $job = $jobId > 0 ? $this->app->queue()->find($jobId) : null;
        if ($job === null || (int) $job['user_id'] !== $ctx->userId() || ($types !== null && !in_array($job['type'], $types, true))) {
            $this->expired($ctx);
            return null;
        }
        return $job;
    }

    /** Newest generate job of a project (only completed ones when $completed). */
    private function lastGenerate(Context $ctx, array $project, bool $completed = false): ?array
    {
        return $this->app->db()->fetch(
            "SELECT * FROM generation_jobs WHERE project_id = ? AND user_id = ? AND type = 'generate'"
            . ($completed ? " AND status = 'completed'" : '') . ' ORDER BY id DESC LIMIT 1',
            [(int) $project['id'], $ctx->userId()]
        );
    }

    /**
     * Items of a generate job: index, selected (all tiles), invalid (any tile).
     * @return list<array{index:int,selected:bool,invalid:bool}>
     */
    private function items(int $jobId): array
    {
        $out = [];
        foreach ($this->app->files()->forJob($jobId, 'main') as $row) {
            $i = (int) $row['item_index'];
            $out[$i] ??= ['index' => $i, 'selected' => true, 'invalid' => false];
            $out[$i]['selected'] = $out[$i]['selected'] && (int) $row['is_selected'] === 1;
            $out[$i]['invalid'] = $out[$i]['invalid'] || $row['validation_status'] === 'invalid';
        }
        ksort($out);
        return array_values($out);
    }

    private function jobLine(array $job): string
    {
        $payload = JobQueue::payload($job);
        $items = count((array) ($payload['items'] ?? []));
        $parts = [match ((string) $job['status']) {
            'queued' => '⏳ در صف',
            'running' => '⚙️ در حال ساخت (' . Texts::fa((int) $job['progress']) . '٪)',
            'completed' => '✅ تمام شد',
            'failed' => '❌ ناموفق',
            'cancelled' => '⛔️ لغو شد',
            default => (string) $job['status'],
        }];
        if ($items > 0) {
            $parts[] = Texts::fa($items) . ' ایموجی';
        }
        if (isset($payload['format'])) {
            $parts[] = self::formatName((string) $payload['format']);
        }
        $parts[] = Texts::date((string) ($job['finished_at'] ?? $job['created_at']), true);
        $line = implode(' · ', $parts);
        if ($job['status'] === 'failed' && !empty($job['error_message'])) {
            $line .= "\n" . Texts::e(Texts::cut((string) $job['error_message'], 200));
        }
        return $line;
    }

    private function title(array $project): string
    {
        $title = trim((string) ($project['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        $cfg = $this->config($project);
        if ($project['input_type'] === 'template') {
            $pack = (string) ($cfg['pack'] ?? '');
            $name = $pack !== '' ? $this->app->templates()->packName($pack) : '';
            return 'قالب‌های آماده' . ($name !== '' ? ' — ' . $name : '');
        }
        $text = trim(str_replace("\n", ' ', (string) ($cfg['text'] ?? '')));
        return $text !== '' ? $text : 'طرح #' . Texts::fa((int) $project['id']);
    }

    private function canPublish(Context $ctx): bool
    {
        return $this->app->settings()->bool('allow_publish', true) && ($this->perms($ctx)['publish'] ?? true) !== false;
    }

    /** Removes the inline keyboard of the pressed message (stale buttons). */
    private function dropKeyboard(Context $ctx): void
    {
        $mid = $ctx->callbackMessageId();
        if ($mid === null) {
            return;
        }
        try {
            $ctx->tg()->editMessageReplyMarkup($ctx->chatId, $mid, ['inline_keyboard' => []]);
        } catch (TelegramException) {
            // already without keyboard
        }
    }

    private static function typeIcon(string $type): string
    {
        return match ($type) {
            'template' => '✨',
            'image' => '🖼',
            'combo' => '🎨',
            default => '🔤',
        };
    }

    private static function typeName(string $type): string
    {
        return match ($type) {
            'template' => 'قالب آماده',
            'image' => 'عکس / لوگو',
            'combo' => 'متن و لوگو',
            default => 'متن',
        };
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => '📝 پیش‌نویس',
            'previewing' => '👀 در حال پیش‌نمایش',
            'previewed' => '👀 پیش‌نمایش شد',
            'queued' => '⏳ در صف ساخت',
            'generating' => '⚙️ در حال ساخت',
            'completed' => '✅ ساخته شد',
            'failed' => '❌ ناموفق',
            default => $status,
        };
    }
}
