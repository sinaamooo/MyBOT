<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Bot\Uploads;
use EmojiMaker\Rendering\ImageException;
use EmojiMaker\Rendering\ImageProcessor;
use EmojiMaker\Telegram\Keyboard;
use EmojiMaker\Telegram\TelegramException;
use EmojiMaker\Templates\LottieDoc;
use EmojiMaker\Templates\TemplateException;
use EmojiMaker\Templates\TemplateRecolor;
use EmojiMaker\Templates\TemplateRenderer;
use EmojiMaker\Templates\TemplateSpec;
use EmojiMaker\Vector\LottiePlayer;

/**
 * Premium templates ("t"): packs → paged gallery (numbered contact sheet +
 * select buttons, animated preview per template) → content (logo, text,
 * caption, font, text colour) → colour (buyer's brand colour; original
 * colours only when the admin allows it) → format → preview → generate.
 */
final class TemplateController extends Controller
{
    private const PER_PAGE = 9;
    private const CELL = 240;

    // ------------------------------------------------------------- entries

    public function packs(Context $ctx): void
    {
        $ctx->clearState();
        if (!$this->app->settings()->bool('templates_enabled', true)) {
            $ctx->reply('قالب‌های آماده فعلاً غیرفعال است.', $this->menu());
            return;
        }
        $packs = $this->app->templates()->packs();
        if (!$packs) {
            $ctx->reply('فعلاً قالبی در دسترس نیست.', $this->menu());
            return;
        }
        $rows = [];
        foreach ($packs as $slug => $p) {
            $rows[] = [['✨ ' . $p['name'] . ' (' . Texts::fa($p['count']) . ' قالب)', 't:pk:' . $slug . ':1']];
        }
        $total = array_sum(array_column($packs, 'count'));
        $text = "✨ <b>قالب‌های آماده پریمیوم</b>\n" . Texts::fa($total) . " ایموجی متحرک حرفه‌ای؛ لوگو یا متن و <b>رنگ برند خودت</b> را رویشان بگذار.\n"
            . "حرکت‌ها دقیقاً مثل نسخه‌ی اصلی است. یک بسته را باز کن، هر چند قالب که خواستی انتخاب کن و یک‌جا بساز.";
        $ctx->isCallback() ? $ctx->edit($text, Keyboard::inline($rows)) : $ctx->reply($text, Keyboard::inline($rows));
        $ctx->toast();
    }

    /** Deep link /start t_<slug>: a project with that template selected. */
    public function openTemplate(Context $ctx, string $slug): void
    {
        $spec = $this->app->templates()->get($slug);
        if ($spec === null || !$this->app->templates()->isEnabled($spec)) {
            $this->packs($ctx);
            return;
        }
        $project = $this->newProject($ctx, $spec->pack);
        $cfg = $this->config($project);
        $cfg['templates'] = [$spec->slug];
        $this->save($project, $cfg);
        $this->dashboard($ctx, $this->reload($project), false);
    }

    /** @return array<string,mixed> */
    private function newProject(Context $ctx, string $pack): array
    {
        $last = (array) ($ctx->stateData()['last_template'] ?? []);
        $config = [
            'kind' => 'template',
            'pack' => $pack,
            'templates' => [],
            'content' => [
                'logo' => null, 'text' => '', 'caption' => '',
                'font' => is_string($last['font'] ?? null) ? $last['font'] : null,
                'color' => null, 'caption_color' => null,
                'outline' => null, 'outline_width' => 0,
            ],
            'recolor' => ['color' => is_string($last['recolor'] ?? null) ? $last['recolor'] : null, 'mode' => 'main'],
            'format' => $this->defaultFormat(),
        ];
        $this->makeRoom($ctx);
        $id = $this->app->projects()->create($ctx->userId(), 'template', $config, null);
        $this->app->users()->setCurrentProject($ctx->userId(), $id);
        return (array) $this->app->projects()->find($id);
    }

    private function defaultFormat(): string
    {
        return $this->app->tools()->canWebm() ? 'webm' : 'tgs';
    }

    private function reload(array $project): array
    {
        return (array) $this->app->projects()->find((int) $project['id']);
    }

    /** Draft template project of this user for $pack, or a new one. */
    private function projectForPack(Context $ctx, string $pack): array
    {
        $id = (int) ($ctx->user['current_project_id'] ?? 0);
        $p = $id > 0 ? $this->project($ctx, $id) : null;
        if ($p !== null && $p['input_type'] === 'template' && in_array($p['status'], ['draft', 'previewed', 'previewing'], true)) {
            return $p;
        }
        return $this->newProject($ctx, $pack);
    }

    private function mayKeepOriginal(Context $ctx): bool
    {
        $policy = $this->app->settings()->string('templates_original_colors', 'admin');
        return $policy === 'all' || ($policy === 'admin' && $ctx->isAdmin());
    }

    // --------------------------------------------------------------- input

    public function input(Context $ctx): void
    {
        $d = $ctx->stateData();
        $project = $this->project($ctx, (int) ($d['pid'] ?? 0));
        if ($project === null || $project['input_type'] !== 'template') {
            $ctx->clearState();
            $ctx->reply(Texts::EXPIRED, $this->menu());
            return;
        }
        $cfg = $this->config($project);
        $state = $ctx->state();
        $text = trim(str_replace("\r", '', $ctx->text()));
        $keep = array_intersect_key($d, array_flip(['last_design', 'last_template']));
        switch ($state) {
            case 't_logo':
                if ($ctx->message === null || !Uploads::hasImage($ctx->message)) {
                    $ctx->reply('لطفاً لوگو را به‌صورت عکس یا بهتر از آن فایل PNG شفاف بفرست.');
                    return;
                }
                $ctx->chatAction('upload_photo');
                try {
                    $asset = (new Uploads($this->app))->store($ctx->message, $ctx->userId(), (int) $project['id'], 'logo');
                } catch (ImageException $e) {
                    $ctx->reply('⚠️ ' . Texts::e($e->getMessage()));
                    return;
                }
                $cfg['content']['logo'] = $asset['id'];
                if (!$asset['has_alpha']) {
                    $ctx->reply('ℹ️ لوگو پس‌زمینه‌ی شفاف ندارد؛ برای نتیجه‌ی تمیزتر، PNG شفاف بفرست. (همین لوگو هم استفاده می‌شود.)');
                }
                break;
            case 't_text':
            case 't_caption':
                $max = $state === 't_text' ? 24 : 32;
                if ($text === '' || mb_strlen($text) > $max) {
                    $ctx->reply('یک متن کوتاه (حداکثر ' . Texts::fa($max) . ' حرف) بفرست' . ($state === 't_caption' ? '، یا «-» برای حذف.' : '.'));
                    if ($text === '') {
                        return;
                    }
                    if ($text !== '-') {
                        return;
                    }
                }
                $key = $state === 't_text' ? 'text' : 'caption';
                $cfg['content'][$key] = $text === '-' ? '' : $text;
                break;
            case 't_hex':
                $hex = self::parseHex($text);
                if ($hex === null) {
                    $ctx->reply('کد رنگ نامعتبر است. مثل <code>#FF8800</code> بفرست.');
                    return;
                }
                $field = (string) ($d['field'] ?? 'recolor');
                if ($field === 'recolor') {
                    $cfg['recolor']['color'] = $hex;
                    $keep['last_template'] = array_replace((array) ($keep['last_template'] ?? []), ['recolor' => $hex]);
                } else {
                    $cfg['content'][$field === 'cc' ? 'caption_color' : 'color'] = $hex;
                }
                break;
            default:
                $ctx->clearState();
                $ctx->reply(Texts::UNKNOWN, $this->menu());
                return;
        }
        $this->save($project, $cfg);
        $ctx->setState('idle', $keep);
        $this->dashboard($ctx, $this->reload($project), false);
    }

    // ------------------------------------------------------------ callbacks

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        $a = $p[1] ?? '';
        if ($a === 'packs') {
            $this->packs($ctx);
            return;
        }
        if ($a === 'pk') {
            $pack = TemplateSpec::slug((string) ($p[2] ?? ''));
            if (!$this->app->templates()->available($pack)) {
                $ctx->toast(Texts::EXPIRED, true);
                return;
            }
            $project = $this->projectForPack($ctx, $pack);
            $this->gallery($ctx, $project, $pack, max(1, (int) ($p[3] ?? 1)), true);
            return;
        }
        $project = $this->project($ctx, (int) ($p[2] ?? 0));
        if ($project === null || $project['input_type'] !== 'template') {
            $this->expired($ctx);
            return;
        }
        $this->app->users()->setCurrentProject($ctx->userId(), (int) $project['id']);
        $cfg = $this->config($project);
        $pid = (int) $project['id'];
        switch ($a) {
            case 'pg':
                $this->gallery($ctx, $project, TemplateSpec::slug((string) ($p[3] ?? '')), max(1, (int) ($p[4] ?? 1)), true);
                return;
            case 'tg':
                $cfg = $this->toggle($ctx, $cfg, (string) ($p[3] ?? ''));
                $this->save($project, $cfg);
                $pack = (string) ($p[4] ?? ($cfg['pack'] ?? ''));
                $page = max(1, (int) ($p[5] ?? 1));
                if ($ctx->callbackMessageId() !== null && isset($ctx->callback['message']['photo'])) {
                    try {
                        $ctx->tg()->editMessageReplyMarkup($ctx->chatId, (int) $ctx->callbackMessageId(), $this->galleryKeyboard($ctx, $this->reload($project), $pack, $page));
                    } catch (TelegramException) {
                        // keyboard unchanged
                    }
                    $ctx->toast(in_array($p[3] ?? '', (array) $cfg['templates'], true) ? '✅ انتخاب شد' : 'برداشته شد');
                } else {
                    $ctx->toast(in_array($p[3] ?? '', (array) $cfg['templates'], true) ? '✅ انتخاب شد' : 'برداشته شد');
                }
                return;
            case 'all':
                $pack = TemplateSpec::slug((string) ($p[3] ?? ''));
                $max = max(1, $this->app->settings()->int('max_template_batch', 50));
                $slugs = array_map(static fn (TemplateSpec $s) => $s->slug, $this->app->templates()->available($pack));
                $cfg['templates'] = array_slice(array_values(array_unique([...(array) $cfg['templates'], ...$slugs])), 0, $max);
                $this->save($project, $cfg);
                $ctx->toast('✅ ' . Texts::fa(count($cfg['templates'])) . ' قالب انتخاب شد');
                $this->dashboard($ctx, $this->reload($project), false);
                return;
            case 'vw':
                $this->sendPreview($ctx, $project, (string) ($p[3] ?? ''));
                return;
            case 'dash':
                $ctx->clearState();
                $this->dashboard($ctx, $project);
                return;
            case 'logo':
                $ctx->toast();
                $ctx->setState('t_logo', ['pid' => $pid] + $this->keep($ctx));
                $ctx->reply("🖼 لوگوی خودت را بفرست.\nبهترین نتیجه: <b>فایل PNG با پس‌زمینه‌ی شفاف</b> (به‌صورت «فایل» تا فشرده نشود).", Keyboard::inline([[['✖️ انصراف', 't:dash:' . $pid]]]));
                return;
            case 'nologo':
                $cfg['content']['logo'] = null;
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'txt':
            case 'cap':
                $ctx->toast();
                $ctx->setState($a === 'txt' ? 't_text' : 't_caption', ['pid' => $pid] + $this->keep($ctx));
                $ctx->reply($a === 'txt'
                    ? "🔤 متن اصلی را بفرست (حداکثر ۲۴ حرف؛ فارسی یا انگلیسی).\nاگر لوگو هم بفرستی، لوگو در وسط و این متن زیر آن قرار می‌گیرد."
                    : "✍️ متن زیرنویس (مثلاً نام کانال یا برند، حداکثر ۳۲ حرف) را بفرست، یا «-» برای حذف.", Keyboard::inline([[['✖️ انصراف', 't:dash:' . $pid]]]));
                return;
            case 'font':
                $this->fontMenu($ctx, $project, $cfg);
                return;
            case 'sf':
                $slug = (string) ($p[3] ?? 'auto');
                $cfg['content']['font'] = $slug !== 'auto' && $this->app->fonts()->has($slug) ? $slug : null;
                $this->save($project, $cfg);
                $this->remember($ctx, ['font' => $cfg['content']['font']]);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'tc':
                $this->textColorMenu($ctx, $project, (string) ($p[3] ?? 'c'));
                return;
            case 'stc':
                $field = ($p[3] ?? 'c') === 'cc' ? 'caption_color' : 'color';
                $hex = ($p[4] ?? '') === 'auto' ? null : self::parseHex((string) ($p[4] ?? ''));
                $cfg['content'][$field] = $hex;
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'ol':
                $cfg['content']['outline_width'] = empty($cfg['content']['outline_width']) ? 0.06 : 0;
                $cfg['content']['outline'] = empty($cfg['content']['outline_width']) ? null : '#111111';
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'hex':
                $ctx->toast();
                $ctx->setState('t_hex', ['pid' => $pid, 'field' => (string) ($p[3] ?? 'recolor')] + $this->keep($ctx));
                $ctx->reply('🎨 کد رنگ دلخواه را بفرست، مثل <code>#FF8800</code>');
                return;
            case 'co':
                $this->colorMenu($ctx, $project, $cfg);
                return;
            case 'rc':
                $v = (string) ($p[3] ?? '');
                if ($v === 'orig') {
                    if (!$this->mayKeepOriginal($ctx)) {
                        $ctx->toast('رنگ اصلی قالب‌ها برای فروش قفل است؛ یک رنگ انتخاب کن.', true);
                        return;
                    }
                    $cfg['recolor']['color'] = null;
                } elseif (($hex = self::parseHex($v)) !== null) {
                    $cfg['recolor']['color'] = $hex;
                    $this->remember($ctx, ['recolor' => $hex]);
                }
                $this->save($project, $cfg);
                $this->colorMenu($ctx, $this->reload($project), $cfg);
                return;
            case 'rm':
                $cfg['recolor']['mode'] = in_array($p[3] ?? '', TemplateRecolor::MODES, true) ? $p[3] : 'main';
                $this->save($project, $cfg);
                $this->colorMenu($ctx, $this->reload($project), $cfg);
                return;
            case 'fm':
                $f = (string) ($p[3] ?? '');
                if ($f === 'webm' && !$this->app->tools()->canWebm()) {
                    $ctx->toast('ساخت WEBM روی این سرور فعال نیست.', true);
                    return;
                }
                if ($f === 'tgs' && !$this->canTgs($ctx)) {
                    $ctx->toast('فرمت TGS برای شما فعال نیست.', true);
                    return;
                }
                if (in_array($f, ['webm', 'tgs', 'png'], true)) {
                    $cfg['format'] = $f;
                    $this->save($project, $cfg);
                }
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'clr':
                $cfg['templates'] = [];
                $this->save($project, $cfg);
                $this->dashboard($ctx, $this->reload($project));
                return;
            case 'prev':
                $this->preview($ctx, $project, $cfg);
                return;
            case 'gen':
                $this->generate($ctx, $project, $cfg);
                return;
            case 'edit':
                $ctx->clearState();
                $this->dashboard($ctx, $project, false);
                return;
        }
        $ctx->toast(Texts::EXPIRED);
    }

    private function keep(Context $ctx): array
    {
        return array_intersect_key($ctx->stateData(), array_flip(['last_design', 'last_template']));
    }

    private function remember(Context $ctx, array $values): void
    {
        $d = $ctx->stateData();
        $d['last_template'] = array_replace((array) ($d['last_template'] ?? []), $values);
        $ctx->setState($ctx->state(), $d);
    }

    private function toggle(Context $ctx, array $cfg, string $slug): array
    {
        $spec = $this->app->templates()->get($slug);
        if ($spec === null || !$this->app->templates()->isEnabled($spec)) {
            return $cfg;
        }
        $list = array_values((array) ($cfg['templates'] ?? []));
        if (in_array($slug, $list, true)) {
            $list = array_values(array_diff($list, [$slug]));
        } else {
            $max = max(1, $this->app->settings()->int('max_template_batch', 50));
            if (count($list) >= $max) {
                $ctx->toast('حداکثر ' . Texts::fa($max) . ' قالب در یک ساخت.', true);
                return $cfg;
            }
            $list[] = $slug;
        }
        $cfg['templates'] = $list;
        return $cfg;
    }

    // -------------------------------------------------------------- gallery

    private function gallery(Context $ctx, array $project, string $pack, int $page, bool $replace): void
    {
        $specs = $this->app->templates()->available($pack);
        if (!$specs) {
            $ctx->toast(Texts::EXPIRED, true);
            return;
        }
        $pages = (int) ceil(count($specs) / self::PER_PAGE);
        $page = max(1, min($pages, $page));
        $slice = array_slice($specs, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $cfg = $this->config($project);
        $cfg['pack'] = $pack;
        $this->save($project, $cfg);
        $ctx->toast();
        $ctx->chatAction('upload_photo');
        $caption = '✨ <b>' . Texts::e($this->app->templates()->packName($pack)) . '</b> — صفحه‌ی ' . Texts::fa($page) . ' از ' . Texts::fa($pages)
            . "\nشماره‌ی هر قالب را بزن تا انتخاب شود (✅). «🎬» پیش‌نمایش متحرک را نشان می‌دهد.\nپیش‌نمایش‌ها با رنگ نمونه است؛ رنگ برند خودت را در مرحله‌ی بعد انتخاب می‌کنی.";
        $markup = $this->galleryKeyboard($ctx, $this->reload($project), $pack, $page);
        if ($replace && $ctx->callbackMessageId() !== null && isset($ctx->callback['message']['photo'])) {
            try {
                $ctx->tg()->deleteMessage($ctx->chatId, (int) $ctx->callbackMessageId());
            } catch (TelegramException) {
                // already gone
            }
        }
        try {
            $sheet = $this->sheet($pack, $page, $slice);
            $res = $ctx->tg()->sendPhoto($ctx->chatId, $sheet['file_id'] ?? $sheet['path'], $caption, ['parse_mode' => 'HTML', 'reply_markup' => $markup]);
            if (!isset($sheet['file_id']) && !empty($res['photo'])) {
                $sizes = (array) $res['photo'];
                $this->rememberFileId($sheet['key'], (string) (end($sizes)['file_id'] ?? ''));
            }
        } catch (\Throwable $e) {
            $this->app->logger('bot')->warning('Gallery sheet failed', ['pack' => $pack, 'error' => $e->getMessage()]);
            $ctx->reply($caption, $markup);
        }
    }

    private function galleryKeyboard(Context $ctx, array $project, string $pack, int $page): array
    {
        $specs = $this->app->templates()->available($pack);
        $pages = max(1, (int) ceil(count($specs) / self::PER_PAGE));
        $slice = array_slice($specs, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $selected = (array) ($this->config($project)['templates'] ?? []);
        $pid = (int) $project['id'];
        $buttons = [];
        foreach ($slice as $i => $spec) {
            $n = Texts::fa($i + 1);
            $buttons[] = [(in_array($spec->slug, $selected, true) ? '✅ ' : '⬜️ ') . $n, 't:tg:' . $pid . ':' . $spec->slug . ':' . $pack . ':' . $page];
            $buttons[] = ['🎬 ' . $n, 't:vw:' . $pid . ':' . $spec->slug];
        }
        $rows = Keyboard::rows($buttons, 6);
        $nav = [];
        if ($page > 1) {
            $nav[] = ['◀️ قبلی', 't:pg:' . $pid . ':' . $pack . ':' . ($page - 1)];
        }
        if ($page < $pages) {
            $nav[] = ['بعدی ▶️', 't:pg:' . $pid . ':' . $pack . ':' . ($page + 1)];
        }
        if ($nav) {
            $rows[] = $nav;
        }
        $rows[] = [['☑️ انتخاب همه‌ی این بسته', 't:all:' . $pid . ':' . $pack]];
        $rows[] = [['➡️ ادامه (' . Texts::fa(count($selected)) . ' قالب انتخاب‌شده)', 't:edit:' . $pid]];
        $rows[] = [['📚 بسته‌های دیگر', 't:packs']];
        return Keyboard::inline($rows);
    }

    /**
     * Numbered contact sheet of a gallery page (JPEG, cached on disk; the
     * Telegram file_id is cached after the first upload).
     * @param list<TemplateSpec> $specs
     * @return array{path:string,key:string,file_id?:string}
     */
    private function sheet(string $pack, int $page, array $specs): array
    {
        $demo = $this->app->settings()->string('templates_demo_color', '#8B5CF6');
        $sig = $pack . '|' . $page . '|' . $demo;
        foreach ($specs as $s) {
            $thumb = $this->app->templates()->previewPath($s, 'thumb');
            $sig .= '|' . $s->slug . ':' . ($thumb !== null ? filemtime($thumb) : filemtime($s->sourcePath()));
        }
        $key = sha1($sig);
        $dir = $this->app->storage()->root() . '/cache/gallery';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $path = $dir . '/' . $key . '.jpg';
        $ids = $this->fileIds();
        if (isset($ids[$key]) && is_file($path)) {
            return ['path' => $path, 'key' => $key, 'file_id' => $ids[$key]];
        }
        if (!is_file($path)) {
            $cols = 3;
            $rows = (int) ceil(count($specs) / $cols);
            $c = self::CELL;
            $im = imagecreatetruecolor($cols * $c, max(1, $rows) * $c);
            imagefilledrectangle($im, 0, 0, $cols * $c, $rows * $c, imagecolorallocate($im, 23, 33, 46));
            foreach ($specs as $i => $spec) {
                $cell = $this->thumb($spec, $demo, $c - 8);
                $x = ($i % $cols) * $c;
                $y = intdiv($i, $cols) * $c;
                if ($cell !== null) {
                    imagecopyresampled($im, $cell, $x + 4, $y + 4, 0, 0, $c - 8, $c - 8, imagesx($cell), imagesy($cell));
                }
                $badge = imagecolorallocate($im, 255, 214, 0);
                imagefilledellipse($im, $x + 26, $y + 26, 34, 34, $badge);
                $num = (string) ($i + 1);
                imagestring($im, 5, $x + 26 - (int) (imagefontwidth(5) * strlen($num) / 2), $y + 18, $num, imagecolorallocate($im, 20, 20, 20));
            }
            imagejpeg($im, $path, 86);
        }
        return ['path' => $path, 'key' => $key];
    }

    /** Gallery thumbnail: pre-rendered preview or a quick render in the demo colour. */
    private function thumb(TemplateSpec $spec, string $demo, int $size): ?\GdImage
    {
        $file = $this->app->templates()->previewPath($spec, 'thumb');
        if ($file !== null) {
            $img = @imagecreatefrompng($file);
            if ($img instanceof \GdImage) {
                return $img;
            }
        }
        try {
            $content = ['text' => 'LOGO', 'caption' => 'BRAND', 'color' => '#FFFFFF', 'outline' => '#111111', 'outline_width' => 0.06];
            if ($spec->recolor['enabled']) {
                $content += ['recolor' => $demo, 'recolor_mode' => 'main'];
            }
            $composed = $this->app->templateRenderer()->compose($spec, $content, 'webm');
            $player = new LottiePlayer($composed['doc']);
            return $player->render(TemplateRenderer::stillFrame($spec, $player), $size, $size, [28, 39, 51]);
        } catch (\Throwable $e) {
            $this->app->logger('bot')->warning('Template thumb failed', ['slug' => $spec->slug, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** @return array<string,string> */
    private function fileIds(): array
    {
        $f = $this->app->storage()->root() . '/cache/gallery/file_ids.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($d) ? $d : [];
    }

    private function rememberFileId(string $key, string $fileId): void
    {
        if ($fileId === '') {
            return;
        }
        $ids = $this->fileIds();
        $ids[$key] = $fileId;
        if (count($ids) > 500) {
            $ids = array_slice($ids, -400, null, true);
        }
        $f = $this->app->storage()->root() . '/cache/gallery/file_ids.json';
        @file_put_contents($f . '.tmp', (string) json_encode($ids));
        @rename($f . '.tmp', $f);
    }

    /** Animated preview of one template (pre-rendered, demo colour). */
    private function sendPreview(Context $ctx, array $project, string $slug): void
    {
        $spec = $this->app->templates()->get($slug);
        if ($spec === null || !$this->app->templates()->isEnabled($spec)) {
            $ctx->toast(Texts::EXPIRED, true);
            return;
        }
        $ctx->toast();
        $selected = in_array($slug, (array) ($this->config($project)['templates'] ?? []), true);
        $markup = Keyboard::inline([[[($selected ? '✅ انتخاب شده (برداشتن)' : '⬜️ انتخاب این قالب'), 't:tg:' . (int) $project['id'] . ':' . $slug]]]);
        $caption = '🎬 ' . Texts::e($spec->name) . ($spec->hasSlot('caption') ? "\nاین قالب جای زیرنویس هم دارد." : '');
        $file = $this->app->templates()->previewPath($spec, 'preview');
        try {
            if ($file !== null) {
                $ctx->chatAction('upload_video');
                $ctx->tg()->sendAnimation($ctx->chatId, $file, $caption, ['parse_mode' => 'HTML', 'reply_markup' => $markup]);
                return;
            }
            $thumb = $this->thumb($spec, $this->app->settings()->string('templates_demo_color', '#8B5CF6'), 256);
            if ($thumb !== null) {
                $tmp = $this->app->storage()->makeTempDir('tpl');
                imagepng($thumb, $tmp . '/t.png');
                $ctx->tg()->sendPhoto($ctx->chatId, $tmp . '/t.png', $caption, ['parse_mode' => 'HTML', 'reply_markup' => $markup]);
                $this->app->storage()->removeDir($tmp);
            }
        } catch (TelegramException $e) {
            $ctx->reply($caption, $markup);
        }
    }

    // ------------------------------------------------------------- dashboard

    public function dashboard(Context $ctx, array $project, bool $edit = true): void
    {
        $cfg = $this->config($project);
        $pid = (int) $project['id'];
        $selected = array_values(array_filter((array) ($cfg['templates'] ?? []), fn ($s) => $this->app->templates()->get((string) $s) !== null));
        $c = (array) ($cfg['content'] ?? []);
        $hasCaption = false;
        foreach ($selected as $slug) {
            if ($this->app->templates()->get($slug)?->hasSlot('caption')) {
                $hasCaption = true;
                break;
            }
        }
        $rc = (array) ($cfg['recolor'] ?? []);
        $colorName = $rc['color'] === null ? ($this->mayKeepOriginal($ctx) ? 'رنگ اصلی قالب' : 'هنوز انتخاب نشده') : self::recolorName((string) $rc['color']);
        $modes = ['main' => 'فقط رنگ برند', 'all' => 'همه‌ی رنگ‌ها', 'gray' => 'سیاه‌وسفید'];
        $lines = [
            '✨ <b>ساخت با قالب آماده</b>',
            'قالب‌های انتخاب‌شده: ' . Texts::fa(count($selected)) . ($selected ? ' (' . Texts::e(Texts::cut(implode('، ', array_map(fn ($s) => (string) $this->app->templates()->get($s)?->name, array_slice($selected, 0, 4))), 80)) . (count($selected) > 4 ? '…' : '') . ')' : ''),
            'لوگو: ' . (!empty($c['logo']) ? '✅ دریافت شد' : '—') . ' | متن: ' . (trim((string) ($c['text'] ?? '')) !== '' ? '«' . Texts::e((string) $c['text']) . '»' : '—'),
        ];
        if ($hasCaption) {
            $lines[] = 'زیرنویس: ' . (trim((string) ($c['caption'] ?? '')) !== '' ? '«' . Texts::e((string) $c['caption']) . '»' : '—');
        }
        $lines[] = 'رنگ قالب: ' . $colorName . ($rc['color'] !== null || ($rc['mode'] ?? 'main') === 'gray' ? ' (' . ($modes[$rc['mode'] ?? 'main'] ?? '') . ')' : '');
        $lines[] = 'فرمت: ' . self::formatName((string) $cfg['format']);
        $rows = [];
        $rows[] = [['🖼 ' . (!empty($c['logo']) ? 'تعویض لوگو' : 'ارسال لوگو'), 't:logo:' . $pid], ['🔤 متن', 't:txt:' . $pid]];
        $row = [['🔤 فونت', 't:font:' . $pid], ['🎨 رنگ متن', 't:tc:' . $pid . ':c']];
        if ($hasCaption) {
            $rows[] = [['✍️ زیرنویس', 't:cap:' . $pid], ['🎨 رنگ زیرنویس', 't:tc:' . $pid . ':cc']];
        }
        $rows[] = $row;
        $rows[] = [[(!empty($c['outline_width']) ? '✅' : '⬜️') . ' دورخط متن', 't:ol:' . $pid]];
        if (!empty($c['logo'])) {
            $rows[] = [['🗑 حذف لوگو', 't:nologo:' . $pid]];
        }
        $rows[] = [['🌈 رنگ قالب', 't:co:' . $pid]];
        $formats = [];
        if ($this->app->tools()->canWebm()) {
            $formats[] = [($cfg['format'] === 'webm' ? '🔘 ' : '') . 'WEBM', 't:fm:' . $pid . ':webm'];
        }
        if ($this->canTgs($ctx)) {
            $formats[] = [($cfg['format'] === 'tgs' ? '🔘 ' : '') . 'TGS', 't:fm:' . $pid . ':tgs'];
        }
        $formats[] = [($cfg['format'] === 'png' ? '🔘 ' : '') . 'PNG', 't:fm:' . $pid . ':png'];
        $rows[] = $formats;
        $pack = (string) ($cfg['pack'] ?? '');
        $rows[] = [['➕ انتخاب قالب‌های بیشتر', $pack !== '' ? 't:pg:' . $pid . ':' . $pack . ':1' : 't:packs'], ['🧹 پاک کردن انتخاب', 't:clr:' . $pid]];
        $rows[] = [['👁 پیش‌نمایش', 't:prev:' . $pid], ['✅ ساخت ' . Texts::fa(max(1, count($selected))) . ' ایموجی', 't:gen:' . $pid]];
        $text = implode("\n", $lines) . "\n\nلوگو یا متن، رنگ برندت و فرمت را تنظیم کن؛ بعد پیش‌نمایش بگیر یا مستقیم بساز.";
        $edit && $ctx->isCallback() && !isset($ctx->callback['message']['photo']) && !isset($ctx->callback['message']['animation'])
            ? $ctx->edit($text, Keyboard::inline($rows))
            : $ctx->reply($text, Keyboard::inline($rows));
        $ctx->toast();
    }

    private static function recolorName(string $hex): string
    {
        foreach (TemplateRecolor::PRESETS as [$h, $name]) {
            if (strcasecmp($h, $hex) === 0) {
                return $name;
            }
        }
        return Texts::e(strtoupper($hex));
    }

    private function fontMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $current = $cfg['content']['font'] ?? null;
        $buttons = [[($current === null ? '✅ ' : '') . 'خودکار', 't:sf:' . $pid . ':auto']];
        foreach ($this->app->fonts()->all() as $slug => $def) {
            $buttons[] = [($current === $slug ? '✅ ' : '') . $def['name'], 't:sf:' . $pid . ':' . $slug];
        }
        $rows = Keyboard::rows($buttons, 2);
        $rows[] = [[Texts::BACK, 't:dash:' . $pid]];
        $ctx->edit("🔤 <b>فونت متن</b>\nبرای متن فارسی، فونت‌های فارسی (وزیرمتن، لاله‌زار) را انتخاب کن.", Keyboard::inline($rows));
        $ctx->toast();
    }

    private function textColorMenu(Context $ctx, array $project, string $field): void
    {
        $pid = (int) $project['id'];
        $field = $field === 'cc' ? 'cc' : 'c';
        $buttons = [['🎯 خودکار (هم‌رنگ برند)', 't:stc:' . $pid . ':' . $field . ':auto']];
        foreach (self::COLORS as $hex => $name) {
            $buttons[] = [$name, 't:stc:' . $pid . ':' . $field . ':' . $hex];
        }
        $rows = Keyboard::rows($buttons, 3);
        $rows[] = [['#️⃣ کد رنگ دلخواه', 't:hex:' . $pid . ':' . $field]];
        $rows[] = [[Texts::BACK, 't:dash:' . $pid]];
        $ctx->edit('🎨 <b>' . ($field === 'cc' ? 'رنگ زیرنویس' : 'رنگ متن') . "</b>\n«خودکار» یعنی متن با رنگ برندی که برای قالب انتخاب می‌کنی هماهنگ می‌شود.", Keyboard::inline($rows));
        $ctx->toast();
    }

    private function colorMenu(Context $ctx, array $project, array $cfg): void
    {
        $pid = (int) $project['id'];
        $rc = (array) ($cfg['recolor'] ?? []);
        $buttons = [];
        foreach (TemplateRecolor::PRESETS as [$hex, $name]) {
            $buttons[] = [(strcasecmp((string) ($rc['color'] ?? ''), $hex) === 0 ? '✅ ' : '') . $name, 't:rc:' . $pid . ':' . ltrim($hex, '#')];
        }
        $rows = Keyboard::rows($buttons, 3);
        $rows[] = [['#️⃣ رنگ دلخواه (کد HEX)', 't:hex:' . $pid . ':recolor']];
        if ($this->mayKeepOriginal($ctx)) {
            $rows[] = [[($rc['color'] === null ? '✅ ' : '') . '↩️ رنگ اصلی قالب (فقط مدیر)', 't:rc:' . $pid . ':orig']];
        }
        $mode = (string) ($rc['mode'] ?? 'main');
        $rows[] = [
            [($mode === 'main' ? '🔘 ' : '') . 'فقط رنگ برند', 't:rm:' . $pid . ':main'],
            [($mode === 'all' ? '🔘 ' : '') . 'همه‌ی رنگ‌ها', 't:rm:' . $pid . ':all'],
            [($mode === 'gray' ? '🔘 ' : '') . 'سیاه‌وسفید', 't:rm:' . $pid . ':gray'],
        ];
        $rows[] = [['✅ تأیید', 't:dash:' . $pid]];
        $ctx->edit("🌈 <b>رنگ قالب</b>\nرنگ برند خودت را انتخاب کن. «فقط رنگ برند» فقط رنگ اصلی قالب را عوض می‌کند؛ «همه‌ی رنگ‌ها» کل ایموجی را.\nبخش‌های حساس (صورت، کاراکترها، طلا، آتش…) همیشه رنگ اصلی خودشان را نگه می‌دارند.", Keyboard::inline($rows));
        $ctx->toast();
    }

    // ------------------------------------------------- preview / generation

    /** Validates the project and builds the generation items; null + toast when incomplete. */
    private function items(Context $ctx, array $cfg): ?array
    {
        $slugs = array_values(array_filter((array) ($cfg['templates'] ?? []), fn ($s) => ($sp = $this->app->templates()->get((string) $s)) !== null && $this->app->templates()->isEnabled($sp)));
        if (!$slugs) {
            $ctx->toast('اول حداقل یک قالب انتخاب کن (➕ انتخاب قالب).', true);
            return null;
        }
        $c = (array) ($cfg['content'] ?? []);
        if (empty($c['logo']) && trim((string) ($c['text'] ?? '')) === '' && trim((string) ($c['caption'] ?? '')) === '') {
            $ctx->toast('لوگو یا متن خودت را اضافه کن.', true);
            return null;
        }
        $rc = (array) ($cfg['recolor'] ?? []);
        if (($rc['color'] ?? null) === null && ($rc['mode'] ?? 'main') !== 'gray' && !$this->mayKeepOriginal($ctx)) {
            $ctx->toast('رنگ برندت را انتخاب کن (🌈 رنگ قالب).', true);
            return null;
        }
        $content = [
            'logo' => !empty($c['logo']) ? (int) $c['logo'] : null,
            'text' => (string) ($c['text'] ?? ''),
            'caption' => (string) ($c['caption'] ?? ''),
            'font' => $c['font'] ?? null,
            'color' => $c['color'] ?? null,
            'caption_color' => $c['caption_color'] ?? null,
            'outline' => $c['outline'] ?? null,
            'outline_width' => (float) ($c['outline_width'] ?? 0),
            'recolor' => $rc['color'] ?? null,
            'recolor_mode' => (string) ($rc['mode'] ?? 'main'),
        ];
        $content = array_filter($content, static fn ($v) => $v !== null && $v !== '');
        $items = [];
        foreach ($slugs as $slug) {
            $items[] = ['label' => (string) $this->app->templates()->get($slug)?->name, 'template' => $slug, 'content' => $content];
        }
        return $items;
    }

    private function preview(Context $ctx, array $project, array $cfg): void
    {
        $items = $this->items($ctx, $cfg);
        if ($items === null) {
            return;
        }
        $id = $this->pushJob($ctx, 'preview', [
            'project_id' => (int) $project['id'],
            'kind' => 'template',
            'format' => (string) $cfg['format'],
            'item' => ['template' => $items[0]['template'], 'content' => $items[0]['content']],
        ], (int) $project['id'], 0, 8);
        if ($id !== null) {
            $ctx->toast('در حال ساخت پیش‌نمایش…');
        }
    }

    private function generate(Context $ctx, array $project, array $cfg): void
    {
        $items = $this->items($ctx, $cfg);
        if ($items === null) {
            return;
        }
        $id = $this->pushJob($ctx, 'generate', [
            'project_id' => (int) $project['id'],
            'kind' => 'template',
            'format' => (string) $cfg['format'],
            'items' => $items,
            'grid' => count($items) > 1,
            'send' => 'auto',
        ], (int) $project['id'], count($items));
        if ($id !== null) {
            $this->app->projects()->setStatus((int) $project['id'], 'queued', $id);
            $ctx->toast();
            $ctx->reply(sprintf(Texts::QUEUED, Texts::fa($id)) . "\nتعداد: " . Texts::fa(count($items)) . ' ایموجی');
        }
    }
}
