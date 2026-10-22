<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Batch\BatchPlanner;
use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Bot\Uploads;
use EmojiMaker\Geometry\Shapes;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Rendering\DesignSpec;
use EmojiMaker\Rendering\ImageException;
use EmojiMaker\Telegram\Keyboard;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Text\TextLayout;

/**
 * Design wizard ("w") and batch ("b"): one control message per project with
 * inline buttons for every setting (text, image, font, colours, outline,
 * effects, frame, size, animation with parameters, format), preview and
 * generation. Text / image / custom colour inputs use conversation states.
 */
final class DesignController extends Controller
{
    private const FIELDS = ['tc' => 'رنگ متن', 'oc' => 'رنگ دورخط', 'bg' => 'رنگ قاب', 'gl' => 'رنگ درخشش', 'bd' => 'رنگ حاشیه‌ی قاب', 'sc' => 'رنگ زیرنویس'];

    // ------------------------------------------------------------ entries

    public function startNew(Context $ctx): void
    {
        $ctx->clearState();
        $ctx->reply("🎨 <b>ساخت ایموجی جدید</b>\nایموجی از چه چیزی ساخته شود؟", Keyboard::inline([
            [['🔤 فقط متن', 'w:new:text'], ['🖼 فقط لوگو یا عکس', 'w:new:image']],
            [['🔤 + 🖼 لوگو و متن با هم', 'w:new:combo']],
        ]));
    }

    public function startWith(Context $ctx, string $input): void
    {
        $project = $this->createProject($ctx, $input);
        $this->askFor($ctx, $project, $input === 'image' ? 'image' : 'text');
    }

    public function startFromImage(Context $ctx): void
    {
        $project = $this->createProject($ctx, 'image');
        $this->acceptImage($ctx, $project);
    }

    /** "🎞 انتخاب انیمیشن": animation of the current design, or a new text design. */
    public function animationEntry(Context $ctx): void
    {
        $project = $this->current($ctx);
        if ($project === null) {
            $project = $this->createProject($ctx, 'text');
            $this->askFor($ctx, $project, 'text', 'anim');
            return;
        }
        $this->animationMenu($ctx, $project, false);
    }

    /** "📦 ساخت پک چندتایی": batch of the current design, or start one. */
    public function batchEntry(Context $ctx): void
    {
        $project = $this->current($ctx);
        if ($project === null) {
            $project = $this->createProject($ctx, 'text');
            $this->askFor($ctx, $project, 'text', 'batch');
            return;
        }
        $this->batchMenu($ctx, $project, false);
    }

    private function current(Context $ctx): ?array
    {
        $id = (int) ($ctx->user['current_project_id'] ?? 0);
        $p = $id > 0 ? $this->project($ctx, $id) : null;
        return $p !== null && $p['input_type'] !== 'template' ? $p : null;
    }

    /** @return array<string,mixed> */
    private function createProject(Context $ctx, string $input): array
    {
        $s = $this->app->settings();
        $last = $ctx->stateData()['last_design'] ?? [];
        $config = [
            'input' => $input,
            'text' => '',
            'font' => is_string($last['font'] ?? null) ? $last['font'] : 'auto',
            'text_color' => is_string($last['text_color'] ?? null) ? $last['text_color'] : '#FFFFFF',
            'text_size' => 'l',
            'outline' => ['width' => 'medium', 'color' => '#111111'],
            'shadow' => ['enabled' => true],
            'glow' => ['enabled' => false, 'color' => '#FFE066'],
            'text_depth' => 'none',
            'background' => ['type' => 'none', 'color' => '#1E293B', 'border' => ['width' => 0, 'color' => '#FFFFFF']],
            'image' => ['scale' => 0.92, 'remove_bg' => false, 'crop' => 'none'],
            'animation' => ['effects' => [['type' => 'pulse']], 'mode' => 'parallel', 'duration' => (float) $s->float('default_duration', 2.0)],
            'format' => $this->defaultFormat(),
            'batch' => ['count' => 1, 'mode' => 'auto_combo', 'colors' => [], 'effects' => [], 'words' => []],
        ];
        $this->makeRoom($ctx);
        $id = $this->app->projects()->create($ctx->userId(), $input, $config);
        $this->app->users()->setCurrentProject($ctx->userId(), $id);
        $ctx->user['current_project_id'] = $id;
        return (array) $this->app->projects()->find($id);
    }

    private function defaultFormat(): string
    {
        $f = $this->app->settings()->string('default_format', 'webm');
        if ($f === 'webm' && !$this->app->tools()->canWebm()) {
            return 'tgs';
        }
        return in_array($f, DesignSpec::FORMATS, true) ? $f : 'webm';
    }

    /** Puts the user in an input state and explains what to send. */
    private function askFor(Context $ctx, array $project, string $what, string $after = ''): void
    {
        $pid = (int) $project['id'];
        $max = $this->app->settings()->int('max_text_length', 32);
        if ($what === 'text') {
            $ctx->setState('w_text', ['pid' => $pid, 'after' => $after] + $this->keepLast($ctx));
            $ctx->reply("🔤 متن ایموجی را بفرست (فارسی یا انگلیسی، حداکثر " . Texts::fa($max) . " حرف، تا ۳ خط).\nمثال: <code>سود</code> یا <code>BTC</code>", Keyboard::inline([[['✖️ انصراف', 'w:dash:' . $pid]]]));
        } else {
            $ctx->setState('w_image', ['pid' => $pid, 'after' => $after] + $this->keepLast($ctx));
            $ctx->reply("🖼 لوگو یا عکس را بفرست.\nبهترین نتیجه: فایل <b>PNG با پس‌زمینه‌ی شفاف</b> (به‌صورت «فایل» بفرست تا فشرده نشود). JPG و WebP هم قبول است.", Keyboard::inline([[['✖️ انصراف', 'w:dash:' . $pid]]]));
        }
    }

    /** Keeps the remembered style between projects inside state data. */
    private function keepLast(Context $ctx): array
    {
        $d = $ctx->stateData();
        $out = [];
        foreach (['last_design', 'last_template'] as $k) {
            if (isset($d[$k])) {
                $out[$k] = $d[$k];
            }
        }
        return $out;
    }

    // -------------------------------------------------------------- input

    public function input(Context $ctx): void
    {
        $state = $ctx->state();
        $d = $ctx->stateData();
        $project = $this->project($ctx, (int) ($d['pid'] ?? 0));
        if ($project === null) {
            $ctx->clearState();
            $ctx->reply(Texts::EXPIRED, $this->menu());
            return;
        }
        $cfg = $this->config($project);
        $text = $ctx->text();
        switch (true) {
            case $state === 'w_text':
                if ($ctx->message !== null && Uploads::hasImage($ctx->message) && $cfg['input'] !== 'text') {
                    $this->acceptImage($ctx, $project);
                    return;
                }
                $clean = $this->validateText($ctx, $text);
                if ($clean === null) {
                    return;
                }
                $cfg['text'] = $clean;
                $this->save($project, $cfg);
                $this->afterInput($ctx, $project, $cfg, (string) ($d['after'] ?? ''));
                return;
            case $state === 'w_sub':
                $cfg['subtitle'] = ['text' => mb_substr(trim(str_replace("\n", ' ', $text)), 0, 32)];
                $this->save($project, $cfg);
                $ctx->setState('idle', $this->keepLast($ctx));
                $this->dashboard($ctx, $this->reload($project), false);
                return;
            case $state === 'w_image':
                $this->acceptImage($ctx, $project);
                return;
            case str_starts_with($state, 'w_hex'):
                $hex = self::parseHex($text);
                $field = (string) ($d['field'] ?? 'tc');
                if ($hex === null) {
                    $ctx->reply('کد رنگ نامعتبر است. مثل <code>#FF8800</code> بفرست.');
                    return;
                }
                $cfg = $this->setColor($cfg, $field, $hex);
                $this->save($project, $cfg);
                $ctx->setState('idle', $this->keepLast($ctx));
                $this->dashboard($ctx, $this->reload($project), false);
                return;
            case $state === 'b_count':
                $n = (int) strtr($text, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
                $max = $this->maxBatch($ctx);
                if ($n < 1 || $n > $max) {
                    $ctx->reply('یک عدد بین ۱ تا ' . Texts::fa($max) . ' بفرست.');
                    return;
                }
                $cfg['batch']['count'] = $n;
                $this->save($project, $cfg);
                $ctx->setState('idle', $this->keepLast($ctx));
                $this->batchMenu($ctx, $this->reload($project), false);
                return;
            case $state === 'b_words':
                $words = array_values(array_filter(array_map(static fn ($w) => mb_substr(trim($w), 0, 32), preg_split('/[\n،,]+/u', $text) ?: [])));
                if (!$words) {
                    $ctx->reply('حداقل یک کلمه بفرست (هر کلمه در یک خط یا با ویرگول جدا شود).');
                    return;
                }
                $cfg['batch']['words'] = array_slice($words, 0, 100);
                $cfg['batch']['mode'] = 'words';
                $cfg['batch']['count'] = max((int) ($cfg['batch']['count'] ?? 1), min(count($words), $this->maxBatch($ctx)));
                $this->save($project, $cfg);
                $ctx->setState('idle', $this->keepLast($ctx));
                $this->batchMenu($ctx, $this->reload($project), false);
                return;
        }
        $ctx->clearState();
        $ctx->reply(Texts::UNKNOWN, $this->menu());
    }

    private function validateText(Context $ctx, string $text): ?string
    {
        $text = str_replace("\r", '', $text);
        $max = $this->app->settings()->int('max_text_length', 32);
        $maxLines = $this->app->settings()->int('max_text_lines', 3);
        if ($text === '') {
            $ctx->reply('متن خالی است؛ یک متن کوتاه بفرست.');
            return null;
        }
        if (mb_strlen($text) > $max) {
            $ctx->reply('متن طولانی است؛ حداکثر ' . Texts::fa($max) . ' حرف مجاز است.');
            return null;
        }
        $lines = explode("\n", $text);
        if (count($lines) > $maxLines) {
            $ctx->reply('حداکثر ' . Texts::fa($maxLines) . ' خط مجاز است.');
            return null;
        }
        $fonts = $this->app->fonts();
        $layout = new TextLayout($fonts->shaper($fonts->autoSlug($text)), $text);
        if ($layout->isEmpty()) {
            $ctx->reply('هیچ‌کدام از حروف این متن قابل نمایش نیستند؛ متن دیگری بفرست.');
            return null;
        }
        if ($layout->missingCodepoints()) {
            $ctx->reply('⚠️ بعضی نویسه‌ها (مثلاً ایموجی‌ها) در فونت‌ها نیستند و نمایش داده نمی‌شوند.');
        }
        return $text;
    }

    private function acceptImage(Context $ctx, array $project): void
    {
        if ($ctx->message === null || !Uploads::hasImage($ctx->message)) {
            $ctx->reply('لطفاً عکس یا فایل تصویری (PNG، JPG یا WebP) بفرست.');
            return;
        }
        $ctx->chatAction('upload_photo');
        try {
            $asset = (new Uploads($this->app))->store($ctx->message, $ctx->userId(), (int) $project['id'], 'image');
        } catch (ImageException $e) {
            $ctx->reply('⚠️ ' . Texts::e($e->getMessage()));
            return;
        }
        $cfg = $this->config($project);
        $cfg['image'] = array_replace((array) ($cfg['image'] ?? []), ['asset_id' => $asset['id']]);
        if ($cfg['input'] === 'text') {
            $cfg['input'] = 'combo';
        }
        $this->app->projects()->saveConfig((int) $project['id'], $cfg, $cfg['input']);
        $after = (string) ($ctx->stateData()['after'] ?? '');
        if (!$asset['has_alpha']) {
            $ctx->reply('ℹ️ تصویر پس‌زمینه‌ی شفاف ندارد. می‌توانی از «🖼 تنظیم تصویر» حذف پس‌زمینه یا برش دایره را روشن کنی.');
        }
        if ($cfg['input'] === 'combo' && trim((string) $cfg['text']) === '') {
            $this->askFor($ctx, $this->reload($project), 'text', $after);
            return;
        }
        $this->afterInput($ctx, $this->reload($project), $cfg, $after);
    }

    private function afterInput(Context $ctx, array $project, array $cfg, string $after): void
    {
        $ctx->setState('idle', $this->keepLast($ctx));
        $project = $this->reload($project);
        if ($cfg['input'] === 'combo' && empty($cfg['image']['asset_id'])) {
            $this->askFor($ctx, $project, 'image', $after);
            return;
        }
        match ($after) {
            'anim' => $this->animationMenu($ctx, $project, false),
            'batch' => $this->batchMenu($ctx, $project, false),
            default => $this->dashboard($ctx, $project, false),
        };
    }

    private function reload(array $project): array
    {
        return (array) $this->app->projects()->find((int) $project['id']);
    }

    // ----------------------------------------------------------- callback

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        if (($p[0] ?? '') === 'w' && ($p[1] ?? '') === 'new') {
            $input = in_array($p[2] ?? '', ['text', 'image', 'combo'], true) ? $p[2] : 'text';
            $ctx->toast();
            $this->startWith($ctx, $input);
            return;
        }
        $project = $this->project($ctx, (int) ($p[2] ?? 0));
        if ($project === null || $project['input_type'] === 'template') {
            $this->expired($ctx);
            return;
        }
        $this->app->users()->setCurrentProject($ctx->userId(), (int) $project['id']);
        $cfg = $this->config($project);
        $pid = (int) $project['id'];
        $ns = $p[0];
        $a = $p[1] ?? '';
        if ($ns === 'b') {
            $this->batchCallback($ctx, $project, $cfg, $a, $p);
            return;
        }
        switch ($a) {
            case 'dash':
            case 'edit':
                $ctx->clearState();
                $this->dashboard($ctx, $project);
                return;
            case 'txt':
                $ctx->toast();
                $this->askFor($ctx, $project, 'text');
                return;
            case 'sub':
                $ctx->toast();
                $ctx->setState('w_sub', ['pid' => $pid] + $this->keepLast($ctx));
                $ctx->reply('✍️ یک زیرنویس کوتاه (حداکثر ۳۲ حرف) برای پایین ایموجی بفرست، یا «-» برای حذف.');
                return;
            case 'in':
                $input = in_array($p[3] ?? '', ['text', 'image', 'combo'], true) ? $p[3] : 'text';
                $cfg['input'] = $input;
                $this->app->projects()->saveConfig($pid, $cfg, $input);
                $project = $this->reload($project);
                if (($input !== 'image' && trim((string) $cfg['text']) === '')) {
                    $ctx->toast();
                    $this->askFor($ctx, $project, 'text');
                    return;
                }
                if ($input !== 'text' && empty($cfg['image']['asset_id'])) {
                    $ctx->toast();
                    $this->askFor($ctx, $project, 'image');
                    return;
                }
                $this->dashboard($ctx, $project);
                return;
            case 'img':
                $this->imageMenu($ctx, $project, $cfg);
                return;
            case 'newimg':
                $ctx->toast();
                $this->askFor($ctx, $project, 'image');
                return;
            case 'rbg':
                $cfg['image']['remove_bg'] = empty($cfg['image']['remove_bg']);
                $this->save($project, $cfg);
                $this->imageMenu($ctx, $project, $cfg);
                return;
            case 'crop':
                $cfg['image']['crop'] = in_array($p[3] ?? '', ['none', 'circle', 'rounded'], true) ? $p[3] : 'none';
                $this->save($project, $cfg);
                $this->imageMenu($ctx, $project, $cfg);
                return;
            case 'isc':
                $cfg['image']['scale'] = max(0.4, min(1.2, ((int) ($p[3] ?? 92)) / 100));
                $this->save($project, $cfg);
                $this->imageMenu($ctx, $project, $cfg);
                return;
            case 'font':
                $this->fontMenu($ctx, $project, $cfg);
                return;
            case 'sf':
                $slug = (string) ($p[3] ?? 'auto');
                $cfg['font'] = $slug === 'auto' || $this->app->fonts()->has($slug) ? $slug : 'auto';
                $this->save($project, $cfg);
                $this->remember($ctx, 'last_design', ['font' => $cfg['font']]);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'col':
                $this->colorMenu($ctx, $project, (string) ($p[3] ?? 'tc'));
                return;
            case 'sc':
                $field = (string) ($p[3] ?? 'tc');
                $hex = self::parseHex((string) ($p[4] ?? ''));
                if ($hex !== null && isset(self::FIELDS[$field])) {
                    $cfg = $this->setColor($cfg, $field, $hex);
                    $this->save($project, $cfg);
                    if ($field === 'tc') {
                        $this->remember($ctx, 'last_design', ['text_color' => $hex]);
                    }
                }
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'hex':
                $ctx->toast();
                $ctx->setState('w_hex', ['pid' => $pid, 'field' => (string) ($p[3] ?? 'tc')] + $this->keepLast($ctx));
                $ctx->reply('🎨 کد رنگ دلخواه را بفرست، مثل <code>#FF8800</code>');
                return;
            case 'gr':
                $field = (string) ($p[3] ?? 'tc');
                $name = (string) ($p[4] ?? 'none');
                $value = isset(Color::GRADIENTS[$name]) ? $name : null;
                if ($field === 'bg') {
                    $cfg['background']['gradient'] = $value;
                    if ($value !== null && ($cfg['background']['type'] ?? 'none') === 'none') {
                        $cfg['background']['type'] = 'circle';
                    }
                } else {
                    $cfg['text_gradient'] = $value;
                }
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'ol':
                $this->outlineMenu($ctx, $project, $cfg);
                return;
            case 'ow':
                $cfg['outline']['width'] = isset(DesignSpec::OUTLINE_WIDTHS[$p[3] ?? '']) ? $p[3] : 'medium';
                $this->save($project, $cfg);
                $this->outlineMenu($ctx, $project, $cfg);
                return;
            case 'fx':
                $this->styleMenu($ctx, $project, $cfg);
                return;
            case 'tog':
                $what = (string) ($p[3] ?? '');
                if ($what === 'shadow') {
                    $cfg['shadow']['enabled'] = empty($cfg['shadow']['enabled']);
                } elseif ($what === 'glow') {
                    $cfg['glow']['enabled'] = empty($cfg['glow']['enabled']);
                } elseif ($what === 'gloss') {
                    $cfg['text_gloss'] = empty($cfg['text_gloss']);
                } elseif ($what === 'bgloss') {
                    $cfg['background']['gloss'] = empty($cfg['background']['gloss']);
                } elseif ($what === 'fadigits') {
                    $cfg['persian_digits'] = empty($cfg['persian_digits']);
                }
                $this->save($project, $cfg);
                $this->styleMenu($ctx, $project, $cfg);
                return;
            case 'dp':
                $cfg['text_depth'] = isset(DesignSpec::DEPTHS[$p[3] ?? '']) ? $p[3] : 'none';
                $cfg['background']['depth'] = $cfg['text_depth'];
                $this->save($project, $cfg);
                $this->styleMenu($ctx, $project, $cfg);
                return;
            case 'sz':
                $cfg['text_size'] = isset(DesignSpec::SIZES[$p[3] ?? '']) ? $p[3] : 'l';
                $this->save($project, $cfg);
                $this->styleMenu($ctx, $project, $cfg);
                return;
            case 'bg':
                $this->backgroundMenu($ctx, $project, $cfg);
                return;
            case 'sb':
                $cfg['background']['type'] = isset(Shapes::TYPES[$p[3] ?? '']) ? $p[3] : 'none';
                $this->save($project, $cfg);
                $this->backgroundMenu($ctx, $project, $cfg);
                return;
            case 'bw':
                $cfg['background']['border']['width'] = isset(DesignSpec::OUTLINE_WIDTHS[$p[3] ?? '']) ? $p[3] : 'none';
                $this->save($project, $cfg);
                $this->backgroundMenu($ctx, $project, $cfg);
                return;
            case 'anim':
                $this->animationMenu($ctx, $project);
                return;
            case 'tf':
                $cfg = $this->toggleEffect($ctx, $cfg, (string) ($p[3] ?? ''));
                $this->save($project, $cfg);
                $this->animationMenu($ctx, $this->reload($project));
                return;
            case 'am':
                $cfg['animation']['mode'] = ($cfg['animation']['mode'] ?? 'parallel') === 'parallel' ? 'sequence' : 'parallel';
                $this->save($project, $cfg);
                $this->animationMenu($ctx, $this->reload($project));
                return;
            case 'dur':
                $cfg['animation']['duration'] = max(0.5, min(3.0, ((int) ($p[3] ?? 20)) / 10));
                $this->save($project, $cfg);
                $this->animationMenu($ctx, $this->reload($project));
                return;
            case 'ep':
                $this->effectParams($ctx, $project, $cfg, (int) ($p[3] ?? 0));
                return;
            case 'pv':
                $cfg = $this->setEffectParam($cfg, (int) ($p[3] ?? 0), (int) ($p[4] ?? 0), (int) ($p[5] ?? 0));
                $this->save($project, $cfg);
                $this->effectParams($ctx, $this->reload($project), $cfg, (int) ($p[3] ?? 0));
                return;
            case 'fmt':
                $this->formatMenu($ctx, $project, $cfg, $p[3] ?? null);
                return;
            case 'prev':
                $this->preview($ctx, $project, $cfg);
                return;
            case 'gen':
                $this->generate($ctx, $project, $cfg);
                return;
        }
        $ctx->toast(Texts::EXPIRED);
    }

    private function remember(Context $ctx, string $key, array $values): void
    {
        $d = $ctx->stateData();
        $d[$key] = array_replace((array) ($d[$key] ?? []), $values);
        $ctx->setState($ctx->state(), $d);
    }

    // ---------------------------------------------------------- dashboard

    public function dashboard(Context $ctx, array $project, bool $edit = true): void
    {
        $cfg = $this->config($project);
        $pid = (int) $project['id'];
        $rows = [];
        $input = (string) ($cfg['input'] ?? 'text');
        $inputRow = [];
        if ($input !== 'image') {
            $inputRow[] = ['✏️ متن', 'w:txt:' . $pid];
        }
        if ($input !== 'text') {
            $inputRow[] = ['🖼 تنظیم تصویر', 'w:img:' . $pid];
        }
        $inputRow[] = ['🔁 نوع ورودی', 'w:in:' . $pid . ':' . ($input === 'text' ? 'combo' : ($input === 'combo' ? 'image' : 'text'))];
        $rows[] = $inputRow;
        if ($input !== 'image') {
            $rows[] = [['🔤 فونت', 'w:font:' . $pid], ['🎨 رنگ متن', 'w:col:' . $pid . ':tc']];
            $rows[] = [['⭕️ دورخط', 'w:ol:' . $pid], ['✍️ زیرنویس', 'w:sub:' . $pid]];
        }
        $rows[] = [['✨ جلوه‌ها و اندازه', 'w:fx:' . $pid], ['🟦 قاب و پس‌زمینه', 'w:bg:' . $pid]];
        $rows[] = [['🎞 انیمیشن', 'w:anim:' . $pid], ['🗂 فرمت: ' . strtoupper((string) $cfg['format']), 'w:fmt:' . $pid]];
        $rows[] = [['👁 پیش‌نمایش', 'w:prev:' . $pid], ['✅ ساخت نهایی', 'w:gen:' . $pid]];
        $rows[] = [['📦 ساخت پک چندتایی از این طرح', 'b:menu:' . $pid]];
        $text = $this->summary($project) . "\n\nهر بخش را با دکمه‌ها تنظیم کن، بعد «پیش‌نمایش» یا «ساخت نهایی» را بزن.";
        $edit && $ctx->isCallback() ? $ctx->edit($text, Keyboard::inline($rows)) : $ctx->reply($text, Keyboard::inline($rows));
        $ctx->toast();
    }

    private function summary(array $project): string
    {
        $cfg = $this->config($project);
        $fonts = $this->app->fonts();
        $inputs = ['text' => 'متن', 'image' => 'لوگو/عکس', 'combo' => 'لوگو + متن'];
        $lines = ['🎨 <b>طرح #' . Texts::fa((int) $project['id']) . '</b> — ' . ($inputs[$cfg['input']] ?? 'متن')];
        if ($cfg['input'] !== 'image') {
            $text = trim((string) $cfg['text']);
            $font = $cfg['font'] === 'auto' ? 'خودکار' : $fonts->name((string) $cfg['font']);
            $lines[] = 'متن: ' . ($text !== '' ? '«' . Texts::e(str_replace("\n", ' / ', $text)) . '»' : '—') . ' | فونت: ' . Texts::e($font);
            $color = !empty($cfg['text_gradient']) ? (Color::GRADIENTS[$cfg['text_gradient']][2] ?? '') : self::colorName((string) $cfg['text_color']);
            $lines[] = 'رنگ: ' . $color . ' | دورخط: ' . self::outlineName((string) ($cfg['outline']['width'] ?? 'medium'));
        }
        if ($cfg['input'] !== 'text') {
            $lines[] = 'تصویر: ' . (!empty($cfg['image']['asset_id']) ? '✅ دریافت شد' : '❌ هنوز ارسال نشده');
        }
        $fx = [];
        if (!empty($cfg['shadow']['enabled'])) {
            $fx[] = 'سایه';
        }
        if (!empty($cfg['glow']['enabled'])) {
            $fx[] = 'درخشش';
        }
        if (($cfg['text_depth'] ?? 'none') !== 'none') {
            $fx[] = 'سه‌بعدی';
        }
        $lines[] = 'قاب: ' . (Shapes::TYPES[$cfg['background']['type'] ?? 'none'] ?? 'بدون قاب') . ' | جلوه: ' . ($fx ? implode('، ', $fx) : '—');
        $lines[] = 'انیمیشن: ' . $this->animationName($cfg);
        $lines[] = 'فرمت: ' . self::formatName((string) $cfg['format']);
        $count = (int) ($cfg['batch']['count'] ?? 1);
        if ($count > 1) {
            $lines[] = 'پک چندتایی: ' . Texts::fa($count) . ' ایموجی (' . (BatchPlanner::MODES[$cfg['batch']['mode'] ?? ''] ?? '') . ')';
        }
        return implode("\n", $lines);
    }

    private function animationName(array $cfg): string
    {
        $names = [];
        foreach ((array) ($cfg['animation']['effects'] ?? []) as $e) {
            if ($this->app->effects()->has((string) ($e['type'] ?? ''))) {
                $ef = $this->app->effects()->get((string) $e['type']);
                $names[] = $ef->icon() . ' ' . self::shortName($ef);
            }
        }
        if (!$names) {
            return 'بدون حرکت (ثابت)';
        }
        $dur = Texts::fa(rtrim(rtrim(number_format((float) ($cfg['animation']['duration'] ?? 2), 1), '0'), '.'));
        $mode = ($cfg['animation']['mode'] ?? 'parallel') === 'sequence' ? 'پشت‌سرهم' : 'هم‌زمان';
        return implode(' + ', $names) . ' (' . $dur . ' ثانیه' . (count($names) > 1 ? '، ' . $mode : '') . ')';
    }

    private static function shortName(Effect $e): string
    {
        return trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $e->nameFa()));
    }

    private static function colorName(string $hex): string
    {
        $key = strtoupper(ltrim($hex, '#'));
        return self::COLORS[$key] ?? Texts::e(strtoupper($hex));
    }

    private static function outlineName(string $w): string
    {
        return ['none' => 'ندارد', 'thin' => 'نازک', 'medium' => 'متوسط', 'thick' => 'ضخیم'][$w] ?? 'متوسط';
    }

    // -------------------------------------------------------------- menus

    private function back(int $pid): array
    {
        return [[Texts::BACK, 'w:dash:' . $pid]];
    }

    private function imageMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $img = (array) ($cfg['image'] ?? []);
        $crop = (string) ($img['crop'] ?? 'none');
        $scale = (int) round(((float) ($img['scale'] ?? 0.92)) * 100);
        $rows = [
            [['📤 ارسال تصویر جدید', 'w:newimg:' . $pid]],
            [[(!empty($img['remove_bg']) ? '✅' : '⬜️') . ' حذف پس‌زمینه‌ی یکدست', 'w:rbg:' . $pid]],
            [
                [($crop === 'none' ? '🔘' : '⚪️') . ' بدون برش', 'w:crop:' . $pid . ':none'],
                [($crop === 'circle' ? '🔘' : '⚪️') . ' دایره', 'w:crop:' . $pid . ':circle'],
                [($crop === 'rounded' ? '🔘' : '⚪️') . ' گوشه‌گرد', 'w:crop:' . $pid . ':rounded'],
            ],
            array_map(static fn ($v) => [($scale === $v ? '🔘 ' : '') . Texts::fa($v) . '٪', 'w:isc:' . $pid . ':' . $v], [70, 85, 92, 100]),
            $this->back($pid),
        ];
        $ctx->edit("🖼 <b>تنظیم تصویر</b>\nلوگو به‌طور خودکار وسط‌چین و حاشیه‌اش برش می‌خورد. اندازه‌ی لوگو نسبت به بوم:", Keyboard::inline($rows));
        $ctx->toast();
    }

    private function fontMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $persian = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', (string) $cfg['text']);
        $buttons = [[($cfg['font'] === 'auto' ? '✅ ' : '') . 'خودکار', 'w:sf:' . $pid . ':auto']];
        foreach ($this->app->fonts()->all() as $slug => $def) {
            if ($persian && empty($def['persian'])) {
                continue;
            }
            $buttons[] = [($cfg['font'] === $slug ? '✅ ' : '') . $def['name'], 'w:sf:' . $pid . ':' . $slug];
        }
        $rows = Keyboard::rows($buttons, 2);
        $rows[] = $this->back($pid);
        $ctx->edit('🔤 <b>فونت</b>' . ($persian ? "\nبرای متن فارسی فقط فونت‌های فارسی نمایش داده می‌شوند." : ''), Keyboard::inline($rows));
        $ctx->toast();
    }

    private function colorMenu(Context $ctx, array $project, string $field): void
    {
        $pid = (int) $project['id'];
        if (!isset(self::FIELDS[$field])) {
            $field = 'tc';
        }
        $buttons = [];
        foreach (self::COLORS as $hex => $name) {
            $buttons[] = [$name, 'w:sc:' . $pid . ':' . $field . ':' . $hex];
        }
        $rows = Keyboard::rows($buttons, 3);
        if ($field === 'tc' || $field === 'bg') {
            $g = [];
            foreach (Color::GRADIENTS as $key => $def) {
                $g[] = ['🌈 ' . $def[2], 'w:gr:' . $pid . ':' . $field . ':' . $key];
            }
            $g[] = ['بدون گرادیان', 'w:gr:' . $pid . ':' . $field . ':none'];
            foreach (Keyboard::rows($g, 2) as $r) {
                $rows[] = $r;
            }
        }
        $rows[] = [['#️⃣ کد رنگ دلخواه', 'w:hex:' . $pid . ':' . $field]];
        $rows[] = $this->back($pid);
        $ctx->edit('🎨 <b>' . self::FIELDS[$field] . '</b> را انتخاب کن:', Keyboard::inline($rows));
        $ctx->toast();
    }

    private function setColor(array $cfg, string $field, string $hex): array
    {
        switch ($field) {
            case 'oc':
                $cfg['outline']['color'] = $hex;
                if (($cfg['outline']['width'] ?? 'medium') === 'none') {
                    $cfg['outline']['width'] = 'medium';
                }
                break;
            case 'bg':
                $cfg['background']['color'] = $hex;
                $cfg['background']['gradient'] = null;
                if (($cfg['background']['type'] ?? 'none') === 'none') {
                    $cfg['background']['type'] = 'circle';
                }
                break;
            case 'gl':
                $cfg['glow'] = ['enabled' => true, 'color' => $hex];
                break;
            case 'bd':
                $cfg['background']['border']['color'] = $hex;
                if (empty($cfg['background']['border']['width'])) {
                    $cfg['background']['border']['width'] = 'thin';
                }
                break;
            case 'sc':
                $cfg['subtitle']['color'] = $hex;
                break;
            default:
                $cfg['text_color'] = $hex;
                $cfg['text_gradient'] = null;
        }
        return $cfg;
    }

    private function outlineMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $w = (string) ($cfg['outline']['width'] ?? 'medium');
        $rows = [
            array_map(static fn ($k) => [($w === $k ? '🔘 ' : '') . self::outlineName($k), 'w:ow:' . $pid . ':' . $k], array_keys(DesignSpec::OUTLINE_WIDTHS)),
            [['🎨 رنگ دورخط', 'w:col:' . $pid . ':oc']],
            $this->back($pid),
        ];
        $ctx->edit('⭕️ <b>دورخط متن</b> (ضخامت و رنگ):', Keyboard::inline($rows));
        $ctx->toast();
    }

    private function styleMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $on = static fn (bool $v) => $v ? '✅' : '⬜️';
        $depth = (string) ($cfg['text_depth'] ?? 'none');
        $size = (string) ($cfg['text_size'] ?? 'l');
        $depthNames = ['none' => 'بدون', 'low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];
        $sizeNames = ['s' => 'کوچک', 'm' => 'متوسط', 'l' => 'بزرگ', 'xl' => 'خیلی بزرگ'];
        $rows = [
            [[$on(!empty($cfg['shadow']['enabled'])) . ' سایه', 'w:tog:' . $pid . ':shadow'], [$on(!empty($cfg['glow']['enabled'])) . ' درخشش', 'w:tog:' . $pid . ':glow']],
            [[$on(!empty($cfg['text_gloss'])) . ' براقی متن', 'w:tog:' . $pid . ':gloss'], ['🎨 رنگ درخشش', 'w:col:' . $pid . ':gl']],
            [[$on(!empty($cfg['persian_digits'])) . ' اعداد فارسی', 'w:tog:' . $pid . ':fadigits']],
            array_map(static fn ($k) => [($depth === $k ? '🔘 ' : '') . '۳D ' . $depthNames[$k], 'w:dp:' . $pid . ':' . $k], array_keys(DesignSpec::DEPTHS)),
            array_map(static fn ($k) => [($size === $k ? '🔘 ' : '') . $sizeNames[$k], 'w:sz:' . $pid . ':' . $k], array_keys(DesignSpec::SIZES)),
            $this->back($pid),
        ];
        $ctx->edit("✨ <b>جلوه‌ها و اندازه</b>\nسایه، درخشش، براقی، عمق سه‌بعدی و اندازه‌ی محتوا:", Keyboard::inline($rows));
        $ctx->toast();
    }

    private function backgroundMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $type = (string) ($cfg['background']['type'] ?? 'none');
        $buttons = [];
        foreach (Shapes::TYPES as $k => $name) {
            $buttons[] = [($type === $k ? '🔘 ' : '') . $name, 'w:sb:' . $pid . ':' . $k];
        }
        $rows = Keyboard::rows($buttons, 3);
        $bw = (string) ($cfg['background']['border']['width'] ?? 'none');
        $rows[] = [['🎨 رنگ قاب', 'w:col:' . $pid . ':bg'], ['🖍 رنگ حاشیه', 'w:col:' . $pid . ':bd']];
        $rows[] = array_map(static fn ($k) => [($bw === $k ? '🔘 ' : '') . 'حاشیه ' . self::outlineName($k), 'w:bw:' . $pid . ':' . $k], ['none', 'thin', 'medium']);
        $rows[] = [[(!empty($cfg['background']['gloss']) ? '✅' : '⬜️') . ' براقی قاب', 'w:tog:' . $pid . ':bgloss']];
        $rows[] = $this->back($pid);
        $ctx->edit("🟦 <b>قاب و پس‌زمینه</b>\nشکل قاب، رنگ/گرادیان و حاشیه:", Keyboard::inline($rows));
        $ctx->toast();
    }

    /** @return array<string,Effect> enabled effects */
    private function effects(Context $ctx): array
    {
        $all = $this->app->effects()->all();
        try {
            $enabled = $this->app->presets()->enabledEffectKeys();
            if (!$ctx->isAdmin()) {
                $premium = array_column(array_filter($this->app->presets()->all(), static fn ($r) => (int) $r['is_premium'] === 1 && $r['kind'] === 'effect'), 'effect_type');
                if (($ctx->user['role'] ?? 'user') === 'user') {
                    $enabled = array_values(array_diff($enabled, $premium));
                }
            }
        } catch (\Throwable) {
            $enabled = [];
        }
        if (!$enabled) {
            return $all;
        }
        return array_intersect_key($all, array_flip($enabled));
    }

    private function animationMenu(Context $ctx, array $project, bool $edit = true): void
    {
        $pid = (int) $project['id'];
        $cfg = $this->config($project);
        $selected = array_map(static fn ($e) => (string) ($e['type'] ?? ''), (array) ($cfg['animation']['effects'] ?? []));
        $buttons = [];
        foreach ($this->effects($ctx) as $key => $e) {
            $buttons[] = [(in_array($key, $selected, true) ? '✅ ' : '') . $e->icon() . ' ' . self::shortName($e), 'w:tf:' . $pid . ':' . $key];
        }
        $rows = Keyboard::rows($buttons, 3);
        $dur = (int) round(((float) ($cfg['animation']['duration'] ?? 2)) * 10);
        $rows[] = array_map(static fn ($d) => [($dur === $d ? '🔘 ' : '') . Texts::fa(rtrim(rtrim(number_format($d / 10, 1), '0'), '.')) . 'ث', 'w:dur:' . $pid . ':' . $d], [10, 15, 20, 25, 30]);
        if (count($selected) > 1) {
            $rows[] = [['🔀 حالت: ' . (($cfg['animation']['mode'] ?? 'parallel') === 'sequence' ? 'پشت‌سرهم' : 'هم‌زمان'), 'w:am:' . $pid]];
        }
        foreach ($selected as $i => $key) {
            if ($this->app->effects()->has($key)) {
                $rows[] = [['⚙️ تنظیم ' . self::shortName($this->app->effects()->get($key)), 'w:ep:' . $pid . ':' . $i]];
            }
        }
        $rows[] = [['👁 پیش‌نمایش', 'w:prev:' . $pid], ['✅ ساخت نهایی', 'w:gen:' . $pid]];
        $rows[] = $this->back($pid);
        $text = "🎞 <b>انیمیشن</b>\nتا " . Texts::fa(3) . " افکت را با هم ترکیب کن (هم‌زمان یا پشت‌سرهم). انتخاب فعلی: " . $this->animationName($cfg)
            . "\nحلقه‌ی انیمیشن بی‌درز است.";
        $edit && $ctx->isCallback() ? $ctx->edit($text, Keyboard::inline($rows)) : $ctx->reply($text, Keyboard::inline($rows));
        $ctx->toast();
    }

    private function toggleEffect(Context $ctx, array $cfg, string $key): array
    {
        if (!isset($this->effects($ctx)[$key])) {
            return $cfg;
        }
        $effects = array_values((array) ($cfg['animation']['effects'] ?? []));
        $types = array_map(static fn ($e) => (string) ($e['type'] ?? ''), $effects);
        $idx = array_search($key, $types, true);
        if ($idx !== false) {
            array_splice($effects, (int) $idx, 1);
        } else {
            if (count($effects) >= 3) {
                $ctx->toast('حداکثر ۳ افکت؛ اول یکی را بردار.', true);
                return $cfg;
            }
            $effects[] = ['type' => $key];
        }
        $cfg['animation']['effects'] = $effects;
        return $cfg;
    }

    /**
     * Preset choices for a parameter definition.
     * @return list<array{0:mixed,1:string}>
     */
    private static function paramOptions(string $name, array $def): array
    {
        $type = (string) ($def['type'] ?? 'float');
        if ($type === 'enum') {
            $labels = (array) ($def['labels'] ?? []);
            return array_map(static fn ($o) => [$o, (string) ($labels[$o] ?? $o)], (array) ($def['options'] ?? []));
        }
        if ($type === 'bool') {
            return [[true, 'روشن'], [false, 'خاموش']];
        }
        if ($type === 'color') {
            return array_map(static fn ($h) => ['#' . $h, self::COLORS[$h]], ['FFFFFF', 'FACC15', 'EF4444', '22C55E', '3B82F6', 'A855F7']);
        }
        $min = (float) ($def['min'] ?? 0);
        $max = (float) ($def['max'] ?? 1);
        $presets = match ($name) {
            'speed' => [[0.5, 'آهسته'], [1.0, 'معمولی'], [1.6, 'تند'], [2.5, 'خیلی تند']],
            'intensity' => [[0.3, 'کم'], [0.65, 'متوسط'], [1.0, 'زیاد']],
            'delay' => [[0.0, 'بدون'], [0.25, 'کم'], [0.5, 'متوسط'], [1.0, 'زیاد']],
            'repeat' => [[1, '۱'], [2, '۲'], [3, '۳'], [4, '۴']],
            default => [[$min, 'کم'], [(float) ($def['default'] ?? ($min + $max) / 2), 'متوسط'], [$max, 'زیاد']],
        };
        $out = [];
        foreach ($presets as [$v, $label]) {
            $v = max($min, min($max, (float) $v));
            $out[] = [$type === 'int' ? (int) round($v) : $v, $label];
        }
        return $out;
    }

    private function effectParams(Context $ctx, array $project, array $cfg, int $index): void
    {
        $pid = (int) $project['id'];
        $e = $cfg['animation']['effects'][$index] ?? null;
        if (!is_array($e) || !$this->app->effects()->has((string) ($e['type'] ?? ''))) {
            $this->animationMenu($ctx, $project);
            return;
        }
        $effect = $this->app->effects()->get((string) $e['type']);
        $current = $effect->normalize((array) ($e['params'] ?? []));
        $rows = [];
        $lines = [];
        $k = 0;
        foreach ($effect->schema() as $name => $def) {
            $opts = self::paramOptions((string) $name, $def);
            if (!$opts) {
                $k++;
                continue;
            }
            $lines[] = '• ' . Texts::e((string) ($def['label'] ?? $name));
            $row = [];
            foreach ($opts as $vi => [$value, $label]) {
                $isCur = is_float($value) || is_float($current[$name] ?? null) ? abs((float) ($current[$name] ?? -999) - (float) $value) < 1e-6 : ($current[$name] ?? null) === $value;
                $row[] = [($isCur ? '🔘 ' : '') . Texts::cut((string) $def['label'], 10) . ': ' . $label, 'w:pv:' . $pid . ':' . $index . ':' . $k . ':' . $vi];
            }
            foreach (Keyboard::rows($row, 2) as $r) {
                $rows[] = $r;
            }
            $k++;
        }
        $rows[] = [[Texts::BACK, 'w:anim:' . $pid]];
        $ctx->edit('⚙️ <b>تنظیمات ' . Texts::e(self::shortName($effect)) . "</b>\n" . Texts::e($effect->description()) . "\n" . implode("\n", $lines), Keyboard::inline($rows));
        $ctx->toast();
    }

    private function setEffectParam(array $cfg, int $index, int $paramIdx, int $valueIdx): array
    {
        $e = $cfg['animation']['effects'][$index] ?? null;
        if (!is_array($e) || !$this->app->effects()->has((string) ($e['type'] ?? ''))) {
            return $cfg;
        }
        $schema = $this->app->effects()->get((string) $e['type'])->schema();
        $names = array_keys($schema);
        $name = $names[$paramIdx] ?? null;
        if ($name === null) {
            return $cfg;
        }
        $opts = self::paramOptions((string) $name, $schema[$name]);
        if (!isset($opts[$valueIdx])) {
            return $cfg;
        }
        $params = (array) ($e['params'] ?? []);
        $params[$name] = $opts[$valueIdx][0];
        $cfg['animation']['effects'][$index]['params'] = $params;
        return $cfg;
    }

    private function formatMenu(Context $ctx, array $project, array $cfg, ?string $choice): void
    {
        $pid = (int) $project['id'];
        $canWebm = $this->app->tools()->canWebm();
        if ($choice !== null && in_array($choice, DesignSpec::FORMATS, true)) {
            if ($choice === 'webm' && !$canWebm) {
                $ctx->toast('ساخت WEBM روی این سرور فعال نیست.', true);
            } elseif ($choice === 'tgs' && !$this->canTgs($ctx)) {
                $ctx->toast('فرمت TGS برای شما فعال نیست.', true);
            } else {
                $cfg['format'] = $choice;
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            }
        }
        $f = (string) $cfg['format'];
        $rows = [];
        if ($canWebm) {
            $rows[] = [[($f === 'webm' ? '🔘 ' : '') . '🎬 WEBM — ویدیویی، کیفیت بالا', 'w:fmt:' . $pid . ':webm']];
        }
        if ($this->canTgs($ctx)) {
            $rows[] = [[($f === 'tgs' ? '🔘 ' : '') . '✒️ TGS — برداری، بسیار سبک', 'w:fmt:' . $pid . ':tgs']];
        }
        $rows[] = [[($f === 'png' ? '🔘 ' : '') . '🖼 PNG — ثابت', 'w:fmt:' . $pid . ':png']];
        $rows[] = $this->back($pid);
        $ctx->edit("🗂 <b>فرمت خروجی</b>\nWEBM: همه‌ی جلوه‌ها، ۱۰۰×۱۰۰، تا ۲۵۶KB.\nTGS: برداری Lottie، ۶۴KB، بعضی جلوه‌ها ساده‌تر می‌شوند.\nPNG: ثابت.", Keyboard::inline($rows));
        $ctx->toast();
    }

    // ---------------------------------------------------- preview / generate

    /** Missing inputs, or null when the design is complete. */
    private function missing(array $cfg): ?string
    {
        $input = (string) ($cfg['input'] ?? 'text');
        if ($input !== 'image' && trim((string) ($cfg['text'] ?? '')) === '') {
            return 'اول متن ایموجی را وارد کن (✏️ متن).';
        }
        if ($input !== 'text' && empty($cfg['image']['asset_id'])) {
            return 'اول لوگو یا عکس را بفرست (🖼 تنظیم تصویر).';
        }
        return null;
    }

    /** Project config → renderer config (the image asset is resolved by the job). */
    private function renderConfig(array $cfg): array
    {
        $out = $cfg;
        unset($out['batch']);
        if (($out['input'] ?? 'text') === 'text') {
            unset($out['image']);
        }
        if (isset($out['subtitle']['text']) && in_array(trim((string) $out['subtitle']['text']), ['', '-'], true)) {
            unset($out['subtitle']);
        }
        return $out;
    }

    private function preview(Context $ctx, array $project, array $cfg): void
    {
        if (($m = $this->missing($cfg)) !== null) {
            $ctx->toast($m, true);
            return;
        }
        $id = $this->pushJob($ctx, 'preview', [
            'project_id' => (int) $project['id'],
            'kind' => 'design',
            'format' => (string) $cfg['format'],
            'item' => ['config' => $this->renderConfig($cfg)],
        ], (int) $project['id'], 0, 8);
        if ($id !== null) {
            $ctx->toast('در حال ساخت پیش‌نمایش…');
        }
    }

    private function generate(Context $ctx, array $project, array $cfg): void
    {
        if (($m = $this->missing($cfg)) !== null) {
            $ctx->toast($m, true);
            return;
        }
        $base = $this->renderConfig($cfg);
        $batch = (array) ($cfg['batch'] ?? []);
        $count = max(1, min($this->maxBatch($ctx), (int) ($batch['count'] ?? 1)));
        $items = [];
        $units = 0;
        if ($count <= 1) {
            $items[] = ['label' => 'ایموجی', 'config' => $base];
            $units = $this->cost(array_map(static fn ($e) => (string) ($e['type'] ?? ''), (array) ($base['animation']['effects'] ?? [])));
        } else {
            $allowed = array_keys($this->effects($ctx));
            $planner = new BatchPlanner($this->app->effects());
            $batch['count'] = $count;
            foreach ($planner->plan($base, $batch, $allowed) as $v) {
                $items[] = ['label' => $v['label'], 'config' => $v['config']];
                $units += $this->cost(array_map(static fn ($e) => (string) ($e['type'] ?? ''), (array) ($v['config']['animation']['effects'] ?? [])));
            }
        }
        $id = $this->pushJob($ctx, 'generate', [
            'project_id' => (int) $project['id'],
            'kind' => 'design',
            'format' => (string) $cfg['format'],
            'items' => $items,
            'grid' => count($items) > 1,
            'send' => 'auto',
        ], (int) $project['id'], $units);
        if ($id !== null) {
            $this->app->projects()->setStatus((int) $project['id'], 'queued', $id);
            $ctx->toast();
            $ctx->reply(sprintf(Texts::QUEUED, Texts::fa($id)) . (count($items) > 1 ? "\nتعداد: " . Texts::fa(count($items)) . ' ایموجی' : ''));
        }
    }

    private function cost(array $effects): int
    {
        try {
            return $this->app->presets()->costFor(array_values(array_filter($effects)));
        } catch (\Throwable) {
            return 1;
        }
    }

    // --------------------------------------------------------------- batch

    private function batchMenu(Context $ctx, array $project, bool $edit = true): void
    {
        $pid = (int) $project['id'];
        $cfg = $this->config($project);
        $b = (array) ($cfg['batch'] ?? []);
        $count = (int) ($b['count'] ?? 1);
        $mode = (string) ($b['mode'] ?? 'auto_combo');
        $max = $this->maxBatch($ctx);
        $presets = array_values(array_filter(array_map('intval', explode(',', $this->app->settings()->string('preset_counts', '1,5,10,20,50,100'))), static fn ($n) => $n >= 1 && $n <= $max));
        $rows = Keyboard::rows(array_map(static fn ($n) => [($n === $count ? '🔘 ' : '') . Texts::fa($n), 'b:cnt:' . $pid . ':' . $n], $presets), 6);
        $rows[] = [['🔢 تعداد دلخواه (تا ' . Texts::fa($max) . ')', 'b:cc:' . $pid]];
        $modes = [];
        foreach (BatchPlanner::MODES as $k => $name) {
            if ($k === 'single') {
                continue;
            }
            $modes[] = [($mode === $k ? '🔘 ' : '') . $name, 'b:mode:' . $pid . ':' . $k];
        }
        foreach (Keyboard::rows($modes, 2) as $r) {
            $rows[] = $r;
        }
        $rows[] = [['🎨 رنگ‌های پک (' . Texts::fa(count((array) ($b['colors'] ?? []))) . ')', 'b:col:' . $pid], ['🎞 افکت‌های پک (' . Texts::fa(count((array) ($b['effects'] ?? []))) . ')', 'b:fx:' . $pid]];
        if ($mode === 'words') {
            $rows[] = [['📝 کلمه‌ها (' . Texts::fa(count((array) ($b['words'] ?? []))) . ')', 'b:words:' . $pid]];
        }
        $rows[] = [['✅ ساخت پک ' . Texts::fa($count) . 'تایی', 'b:go:' . $pid]];
        $rows[] = $this->back($pid);
        $text = "📦 <b>ساخت پک چندتایی</b>\n" . $this->summary($project)
            . "\n\nتعداد و نوع تنوع را انتخاب کن. هیچ دو ایموجی تکراری ساخته نمی‌شود و یک پیش‌نمایش شبکه‌ای شماره‌دار هم دریافت می‌کنی."
            . "\nرنگ/افکت انتخاب نکنی، ربات خودش ترکیب‌های زیبا می‌سازد.";
        $edit && $ctx->isCallback() ? $ctx->edit($text, Keyboard::inline($rows)) : $ctx->reply($text, Keyboard::inline($rows));
        $ctx->toast();
    }

    /** @param list<string> $p */
    private function batchCallback(Context $ctx, array $project, array $cfg, string $a, array $p): void
    {
        $pid = (int) $project['id'];
        $cfg['batch'] = array_replace(['count' => 1, 'mode' => 'auto_combo', 'colors' => [], 'effects' => [], 'words' => []], (array) ($cfg['batch'] ?? []));
        switch ($a) {
            case 'menu':
                $this->batchMenu($ctx, $project);
                return;
            case 'cnt':
                $cfg['batch']['count'] = max(1, min($this->maxBatch($ctx), (int) ($p[3] ?? 1)));
                break;
            case 'cc':
                $ctx->toast();
                $ctx->setState('b_count', ['pid' => $pid] + $this->keepLast($ctx));
                $ctx->reply('🔢 تعداد ایموجی‌های پک را بفرست (۱ تا ' . Texts::fa($this->maxBatch($ctx)) . ').');
                return;
            case 'mode':
                $cfg['batch']['mode'] = isset(BatchPlanner::MODES[$p[3] ?? '']) ? $p[3] : 'auto_combo';
                if ($cfg['batch']['mode'] === 'words' && !$cfg['batch']['words']) {
                    $this->save($project, $cfg);
                    $ctx->toast();
                    $ctx->setState('b_words', ['pid' => $pid] + $this->keepLast($ctx));
                    $ctx->reply('📝 کلمه‌ها را بفرست؛ هر کلمه یک ایموجی می‌شود (هر کدام در یک خط یا جدا با ویرگول).');
                    return;
                }
                break;
            case 'words':
                $ctx->toast();
                $ctx->setState('b_words', ['pid' => $pid] + $this->keepLast($ctx));
                $ctx->reply('📝 کلمه‌ها را بفرست؛ هر کلمه یک ایموجی می‌شود (هر کدام در یک خط یا جدا با ویرگول).');
                return;
            case 'col':
                $this->batchColors($ctx, $project, $cfg);
                return;
            case 'tc':
                $hex = self::parseHex((string) ($p[3] ?? ''));
                if ($hex !== null) {
                    $list = (array) $cfg['batch']['colors'];
                    $list = in_array($hex, $list, true) ? array_values(array_diff($list, [$hex])) : array_slice([...$list, $hex], 0, 8);
                    $cfg['batch']['colors'] = $list;
                    $this->save($project, $cfg);
                }
                $this->batchColors($ctx, $this->reload($project), $cfg);
                return;
            case 'fx':
                $this->batchEffects($ctx, $project, $cfg);
                return;
            case 'tf':
                $key = (string) ($p[3] ?? '');
                if (isset($this->effects($ctx)[$key])) {
                    $list = (array) $cfg['batch']['effects'];
                    $list = in_array($key, $list, true) ? array_values(array_diff($list, [$key])) : [...$list, $key];
                    $cfg['batch']['effects'] = $list;
                    $this->save($project, $cfg);
                }
                $this->batchEffects($ctx, $this->reload($project), $cfg);
                return;
            case 'go':
                $this->save($project, $cfg);
                $this->generate($ctx, $this->reload($project), $cfg);
                return;
            default:
                $ctx->toast(Texts::EXPIRED);
                return;
        }
        $this->save($project, $cfg);
        $this->batchMenu($ctx, $this->reload($project));
    }

    private function batchColors(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $sel = (array) ($cfg['batch']['colors'] ?? []);
        $buttons = [];
        foreach (self::COLORS as $hex => $name) {
            $buttons[] = [(in_array('#' . $hex, $sel, true) ? '✅ ' : '') . $name, 'b:tc:' . $pid . ':' . $hex];
        }
        $rows = Keyboard::rows($buttons, 3);
        $rows[] = [[Texts::BACK, 'b:menu:' . $pid]];
        $ctx->edit('🎨 رنگ‌های پک را انتخاب کن (تا ۸ رنگ):', Keyboard::inline($rows));
        $ctx->toast();
    }

    private function batchEffects(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $sel = (array) ($cfg['batch']['effects'] ?? []);
        $buttons = [];
        foreach ($this->effects($ctx) as $key => $e) {
            $buttons[] = [(in_array($key, $sel, true) ? '✅ ' : '') . $e->icon() . ' ' . self::shortName($e), 'b:tf:' . $pid . ':' . $key];
        }
        $rows = Keyboard::rows($buttons, 3);
        $rows[] = [[Texts::BACK, 'b:menu:' . $pid]];
        $ctx->edit('🎞 افکت‌هایی که در پک استفاده شوند (هیچ‌کدام = انتخاب خودکار):', Keyboard::inline($rows));
        $ctx->toast();
    }
}
