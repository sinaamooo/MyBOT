<?php

declare(strict_types=1);

namespace MyBot\Handlers;

use MyBot\App;
use MyBot\Service\Campaigns;
use MyBot\Support\Keyboards;
use MyBot\Support\Text;

/**
 * Renders panel screens. Every method either edits the message the user tapped
 * ($messageId given) or posts a fresh one, so flows and callbacks share the
 * exact same screens.
 */
final class Views
{
    private const PER_PAGE = 8;

    public function __construct(private readonly App $app)
    {
    }

    public function main(int $chatId, ?int $messageId = null): void
    {
        $text = "🛠 <b>پنل مدیریت</b>\n\n"
            . '🖼 بنر: <b>' . Text::num($this->app->banners->countAll()) . '</b>'
            . ' · 👥 گروه فعال: <b>' . Text::num($this->app->targets->countActive()) . '</b>'
            . ' · 👤 عضو: <b>' . Text::num($this->app->users->countSubscribers()) . '</b>';

        $this->render($chatId, $messageId, $text, Keyboards::main());
    }

    public function bannerList(int $chatId, int $page, ?int $messageId = null): void
    {
        $banners = $this->app->banners->page($page, self::PER_PAGE);
        $hasNext = \count($this->app->banners->page($page + 1, self::PER_PAGE)) > 0;

        $text = "🖼 <b>بنرها</b>\n\n";

        if ($banners === []) {
            $text .= $page === 0
                ? "هنوز بنری نساخته‌اید.\nبا دکمه «بنر جدید» شروع کنید."
                : 'این صفحه خالی است.';
        } else {
            $text .= 'مجموع: <b>' . Text::num($this->app->banners->countAll()) . "</b>\n"
                . '🖼 = دارای مدیا · 💬 = دارای نقل‌قول';
        }

        $this->render($chatId, $messageId, $text, Keyboards::bannerList($banners, $page, $hasNext));
    }

    public function bannerView(int $chatId, int $bannerId, ?int $messageId = null): void
    {
        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            $this->render($chatId, $messageId, '❌ این بنر دیگر وجود ندارد.', Keyboards::back('bn:list:0'));

            return;
        }

        $emojiCount = $this->app->banners->customEmojiCount($banner);
        $buttons    = Text::json($banner['buttons'] ?? null);
        $buttonRows = $buttons === null ? 0 : \count($buttons);

        $lines = [
            '🖼 <b>' . Text::esc((string) $banner['name']) . '</b>',
            '<i>شناسه: ' . (int) $banner['id'] . '</i>',
            '',
            '📝 <b>متن</b>',
            '<code>' . Text::esc(Text::shorten((string) $banner['text'], 160)) . '</code>',
            '',
            '📎 مدیا: ' . ($banner['media_type'] !== null
                ? '<b>' . Text::esc((string) $banner['media_type']) . '</b>'
                : '—'),
            '✨ ایموجی پریمیوم: <b>' . Text::num($emojiCount) . '</b>',
            '💬 نقل‌قول: ' . ($this->app->banners->hasQuote($banner)
                ? '<b>«' . Text::esc(Text::shorten((string) $banner['quote_text'], 40)) . '»</b>'
                : '—'),
            '🔘 ردیف دکمه: <b>' . Text::num($buttonRows) . '</b>',
            '🔗 پیش‌نمایش لینک: ' . (((int) $banner['disable_preview']) === 1 ? 'خاموش' : 'روشن'),
            '🔒 قفل فوروارد: ' . (((int) $banner['protect_content']) === 1 ? 'بله' : 'خیر'),
            '🕒 آخرین ویرایش: ' . Text::esc(Text::datetime((int) $banner['updated_at'])),
        ];

        $this->render($chatId, $messageId, implode("\n", $lines), Keyboards::bannerView($banner));
    }

    public function targetList(int $chatId, int $page, ?int $messageId = null): void
    {
        $targets = $this->app->targets->page($page, self::PER_PAGE);
        $hasNext = \count($this->app->targets->page($page + 1, self::PER_PAGE)) > 0;

        $text = "👥 <b>گروه‌ها و کانال‌ها</b>\n\n";

        if ($targets === []) {
            $text .= "هنوز گروهی ثبت نشده است.\n\n"
                . "کافی است ربات را به گروه اضافه کنید؛ خودکار اینجا ثبت می‌شود.\n"
                . 'یا داخل گروه دستور /addgroup را بفرستید.';
        } else {
            $text .= 'فعال: <b>' . Text::num($this->app->targets->countActive()) . '</b>'
                . ' از ' . Text::num($this->app->targets->countAll()) . "\n"
                . '🟢 آماده ارسال · 🔴 غیرفعال';
        }

        $this->render($chatId, $messageId, $text, Keyboards::targetList($targets, $page, $hasNext));
    }

    public function targetView(int $chatId, int $targetId, ?int $messageId = null): void
    {
        $target = $this->app->targets->find($targetId);

        if ($target === null) {
            $this->render($chatId, $messageId, '❌ این گروه دیگر ثبت نیست.', Keyboards::back('tg:list:0'));

            return;
        }

        $threadId = $target['thread_id'] ?? null;

        $lines = [
            '👥 <b>' . Text::esc($this->app->targets->title($target)) . '</b>',
            '',
            '🆔 <code>' . (int) $target['chat_id'] . '</code>',
            '📂 نوع: ' . Text::esc((string) $target['type']),
            '👤 اعضا: <b>' . Text::num((int) $target['members']) . '</b>',
            '🔖 تاپیک: ' . (is_numeric($threadId) ? '<code>' . (int) $threadId . '</code>' : 'چت اصلی'),
            '📶 وضعیت: ' . (((int) $target['active']) === 1 && ((int) $target['can_post']) === 1
                ? '🟢 فعال'
                : '🔴 غیرفعال'),
            '🕒 افزوده شده: ' . Text::esc(Text::datetime((int) $target['added_at'])),
        ];

        $this->render($chatId, $messageId, implode("\n", $lines), Keyboards::targetView($target));
    }

    public function campaignList(int $chatId, ?int $messageId = null): void
    {
        $this->render($chatId, $messageId, $this->app->stats->campaignReport(), Keyboards::campaignList());
    }

    public function campaignView(int $chatId, int $campaignId, ?int $messageId = null): void
    {
        $campaign = $this->app->campaigns->find($campaignId);

        if ($campaign === null) {
            $this->render($chatId, $messageId, '❌ این کمپین پیدا نشد.', Keyboards::back('cp:list'));

            return;
        }

        $progress = $this->app->campaigns->progress($campaignId);
        $banner   = $this->app->banners->find((int) $campaign['banner_id']);
        $total    = (int) $campaign['total'];
        $status   = (string) $campaign['status'];

        $lines = [
            '📣 <b>کمپین #' . Text::num($campaignId) . '</b>',
            '',
            '🖼 بنر: <b>' . Text::esc($banner === null ? 'حذف‌شده' : (string) $banner['name']) . '</b>',
            '🎯 مخاطب: ' . Text::esc($this->app->campaigns->audienceLabel($campaign)),
            '📶 وضعیت: ' . $this->app->campaigns->statusLabel($status),
            '',
            Text::bar($progress['sent'], max(1, $total), 12),
            '✅ ارسال‌شده: <b>' . Text::num($progress['sent']) . '</b> از ' . Text::num($total),
            '⏳ باقی‌مانده: <b>' . Text::num($progress['pending']) . '</b>',
            '❌ ناموفق: <b>' . Text::num($progress['failed']) . '</b>',
            '',
            '🕒 ساخت: ' . Text::esc(Text::datetime((int) $campaign['created_at'])),
            '🕒 پایان: ' . Text::esc(Text::datetime(
                is_numeric($campaign['finished_at'] ?? null) ? (int) $campaign['finished_at'] : null,
            )),
        ];

        $cancellable = \in_array($status, [Campaigns::STATUS_QUEUED, Campaigns::STATUS_RUNNING], true);

        $this->render(
            $chatId,
            $messageId,
            implode("\n", $lines),
            Keyboards::campaignView($campaignId, $cancellable),
        );
    }

    /** @param array<string, mixed>|null $keyboard */
    public function render(int $chatId, ?int $messageId, string $html, ?array $keyboard): void
    {
        if ($messageId === null) {
            $this->app->sender->notice($chatId, $html, $keyboard);

            return;
        }

        $this->app->sender->edit($chatId, $messageId, $html, $keyboard);
    }
}
