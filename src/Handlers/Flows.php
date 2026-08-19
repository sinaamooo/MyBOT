<?php

declare(strict_types=1);

namespace MyBot\Handlers;

use MyBot\App;
use MyBot\Support\Keyboards;
use MyBot\Support\Text;

/**
 * Multi-step admin conversations (create a banner, set its buttons, …).
 *
 * Banner content is captured from a real message rather than parsed from
 * markup — that is what preserves premium emoji and the quote fragment.
 */
final class Flows
{
    public const BANNER_NAME    = 'banner:name';
    public const BANNER_CONTENT = 'banner:content';
    public const BANNER_BUTTONS = 'banner:buttons';
    public const BANNER_RENAME  = 'banner:rename';
    public const BANNER_QUOTE   = 'banner:quote';
    public const TARGET_ADD     = 'target:add';

    private readonly Views $views;

    public function __construct(private readonly App $app)
    {
        $this->views = new Views($app);
    }

    public function views(): Views
    {
        return $this->views;
    }

    /**
     * @param array<string, mixed> $message
     * @return bool true when the message was consumed by an active flow
     */
    public function handle(array $message, int $userId): bool
    {
        $state = $this->app->states->get($userId);

        if ($state === null) {
            return false;
        }

        $payload = $state['payload'];
        $text    = \is_string($message['text'] ?? null) ? trim($message['text']) : '';

        return match ($state['name']) {
            self::BANNER_NAME    => $this->onBannerName($userId, $text),
            self::BANNER_CONTENT => $this->onBannerContent($userId, $message, $payload),
            self::BANNER_BUTTONS => $this->onBannerButtons($userId, $text, $payload),
            self::BANNER_RENAME  => $this->onBannerRename($userId, $text, $payload),
            self::BANNER_QUOTE   => $this->onBannerQuote($userId, $message, $payload),
            self::TARGET_ADD     => $this->onTargetAdd($userId, $text),
            default              => false,
        };
    }

    // ---------------------------------------------------------------- starters

    public function startBannerCreate(int $userId): void
    {
        $this->app->states->set($userId, self::BANNER_NAME);

        $this->app->sender->notice(
            $userId,
            "🏷 <b>گام ۱ از ۲</b>\n\n" . 'یک نام برای بنر بفرستید (فقط برای شناسایی در پنل):',
            Keyboards::cancelFlow(),
        );
    }

    public function startBannerEdit(int $userId, int $bannerId): void
    {
        $this->app->states->set($userId, self::BANNER_CONTENT, ['banner_id' => $bannerId]);
        $this->askForContent($userId);
    }

    public function startBannerButtons(int $userId, int $bannerId): void
    {
        $this->app->states->set($userId, self::BANNER_BUTTONS, ['banner_id' => $bannerId]);

        $this->app->sender->notice(
            $userId,
            "🔘 <b>دکمه‌های شیشه‌ای</b>\n\n"
            . "هر خط یک دکمه، با این قالب:\n"
            . "<code>متن دکمه | https://example.com</code>\n\n"
            . "خط خالی یعنی شروع ردیف جدید.\n"
            . 'برای حذف همه دکمه‌ها بنویسید: <code>حذف</code>',
            Keyboards::cancelFlow(),
        );
    }

    public function startBannerRename(int $userId, int $bannerId): void
    {
        $this->app->states->set($userId, self::BANNER_RENAME, ['banner_id' => $bannerId]);

        $this->app->sender->notice($userId, '🏷 نام جدید بنر را بفرستید:', Keyboards::cancelFlow());
    }

    public function startBannerQuote(int $userId, int $bannerId): void
    {
        $this->app->states->set($userId, self::BANNER_QUOTE, ['banner_id' => $bannerId]);

        $this->app->sender->notice(
            $userId,
            "💬 <b>تنظیم نقل‌قول</b>\n\n"
            . "این قابلیت جدید تلگرام است: وقتی بنر به‌صورت ریپلای ارسال شود، "
            . "فقط همان تکه‌ی انتخابی از پیام مقابل نقل‌قول می‌شود.\n\n"
            . "<b>روش کار:</b> در همین چت روی یک پیام ریپلای بزنید، بخشی از متنش را "
            . "انتخاب کنید و گزینه «Quote» را بزنید، سپس پیام را بفرستید.\n\n"
            . 'برای پاک کردن نقل‌قول بنویسید: <code>حذف</code>',
            Keyboards::cancelFlow(),
        );
    }

    public function startTargetAdd(int $userId): void
    {
        $this->app->states->set($userId, self::TARGET_ADD);

        $this->app->sender->notice(
            $userId,
            "🆔 <b>افزودن گروه با آیدی</b>\n\n"
            . "آیدی عددی گروه یا کانال را بفرستید (مثل <code>-1001234567890</code>).\n\n"
            . '⚠️ ربات باید از قبل عضو آن چت باشد، وگرنه ثبت نمی‌شود.',
            Keyboards::cancelFlow(),
        );
    }

    public function cancel(int $userId): void
    {
        $this->app->states->clear($userId);
    }

    // ----------------------------------------------------------------- handlers

    private function onBannerName(int $userId, string $name): bool
    {
        if ($name === '') {
            $this->app->sender->notice($userId, '❌ لطفاً یک نام متنی بفرستید.');

            return true;
        }

        $this->app->states->set($userId, self::BANNER_CONTENT, [
            'name' => Text::shorten($name, 60),
        ]);

        $this->askForContent($userId);

        return true;
    }

    private function askForContent(int $userId): void
    {
        $this->app->sender->notice(
            $userId,
            "📝 <b>گام ۲ از ۲</b>\n\n"
            . "حالا خود بنر را دقیقاً همان‌طور که می‌خواهید ارسال شود بفرستید.\n\n"
            . "پشتیبانی می‌شود:\n"
            . "• متن با هر قالب‌بندی (بولد، لینک، اسپویلر…)\n"
            . "• ✨ ایموجی پریمیوم — عیناً حفظ می‌شود\n"
            . "• عکس، ویدیو، گیف، فایل، صدا (با کپشن)\n"
            . '• نقل‌قول (Quote) اگر پیام را به‌صورت ریپلای با انتخاب متن بفرستید',
            Keyboards::cancelFlow(),
        );
    }

    /** @param array<string, mixed> $payload */
    private function onBannerContent(int $userId, array $message, array $payload): bool
    {
        $fields = $this->app->banners->extract($message);

        if ($fields === null) {
            $this->app->sender->notice(
                $userId,
                '❌ این پیام محتوایی برای ارسال ندارد. یک متن یا مدیا بفرستید.',
            );

            return true;
        }

        $bannerId = is_numeric($payload['banner_id'] ?? null) ? (int) $payload['banner_id'] : 0;

        if ($bannerId > 0) {
            $this->app->banners->updateContent($bannerId, $fields);
            $this->app->states->clear($userId);
            $this->app->sender->notice($userId, '✅ محتوای بنر بروزرسانی شد.');
            $this->views->bannerView($userId, $bannerId);

            return true;
        }

        $name     = \is_string($payload['name'] ?? null) ? $payload['name'] : 'بنر بدون نام';
        $bannerId = $this->app->banners->create($name, $fields, $userId);

        $this->app->states->clear($userId);

        $emoji = $this->app->banners->customEmojiCount(
            $this->app->banners->find($bannerId) ?? [],
        );

        $summary = '✅ بنر ساخته شد.';
        if ($emoji > 0) {
            $summary .= "\n" . '✨ ' . Text::num($emoji) . ' ایموجی پریمیوم ثبت شد.';
        }
        if ($fields['quote_text'] !== null) {
            $summary .= "\n" . '💬 نقل‌قول ثبت شد.';
        }

        $this->app->sender->notice($userId, $summary);
        $this->views->bannerView($userId, $bannerId);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function onBannerButtons(int $userId, string $text, array $payload): bool
    {
        $bannerId = (int) ($payload['banner_id'] ?? 0);

        if ($bannerId === 0) {
            $this->app->states->clear($userId);

            return true;
        }

        if ($text === 'حذف') {
            $this->app->banners->setButtons($bannerId, null);
            $this->app->states->clear($userId);
            $this->app->sender->notice($userId, '✅ دکمه‌ها حذف شدند.');
            $this->views->bannerView($userId, $bannerId);

            return true;
        }

        $buttons = $this->app->banners->parseButtons($text);

        if ($buttons === null) {
            $this->app->sender->notice(
                $userId,
                "❌ قالب درست نیست.\n"
                . "هر خط باید این شکل باشد:\n"
                . '<code>متن دکمه | https://example.com</code>',
            );

            return true;
        }

        $this->app->banners->setButtons($bannerId, $buttons);
        $this->app->states->clear($userId);

        $count = array_sum(array_map(\count(...), $buttons));

        $this->app->sender->notice($userId, '✅ ' . Text::num($count) . ' دکمه ذخیره شد.');
        $this->views->bannerView($userId, $bannerId);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function onBannerRename(int $userId, string $text, array $payload): bool
    {
        $bannerId = (int) ($payload['banner_id'] ?? 0);

        if ($text === '') {
            $this->app->sender->notice($userId, '❌ یک نام متنی بفرستید.');

            return true;
        }

        $this->app->banners->rename($bannerId, Text::shorten($text, 60));
        $this->app->states->clear($userId);

        $this->app->sender->notice($userId, '✅ نام بنر تغییر کرد.');
        $this->views->bannerView($userId, $bannerId);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function onBannerQuote(int $userId, array $message, array $payload): bool
    {
        $bannerId = (int) ($payload['banner_id'] ?? 0);
        $text     = \is_string($message['text'] ?? null) ? trim($message['text']) : '';

        if ($text === 'حذف') {
            $this->app->banners->clearQuote($bannerId);
            $this->app->states->clear($userId);
            $this->app->sender->notice($userId, '✅ نقل‌قول حذف شد.');
            $this->views->bannerView($userId, $bannerId);

            return true;
        }

        $fields = $this->app->banners->extract($message);

        if ($fields === null || $fields['quote_text'] === null) {
            $this->app->sender->notice(
                $userId,
                "❌ در این پیام نقل‌قولی پیدا نشد.\n\n"
                . 'روی یک پیام ریپلای بزنید، متن موردنظر را انتخاب کنید و «Quote» را بزنید.',
            );

            return true;
        }

        $banner = $this->app->banners->find($bannerId);

        if ($banner === null) {
            $this->app->states->clear($userId);

            return true;
        }

        $this->app->banners->updateContent($bannerId, [
            'text'           => (string) $banner['text'],
            'entities'       => $banner['entities'],
            'media_type'     => $banner['media_type'],
            'media_file_id'  => $banner['media_file_id'],
            'quote_text'     => $fields['quote_text'],
            'quote_entities' => $fields['quote_entities'],
            'quote_position' => $fields['quote_position'],
        ]);

        $this->app->states->clear($userId);

        $this->app->sender->notice(
            $userId,
            '✅ نقل‌قول ثبت شد: «' . Text::esc(Text::shorten($fields['quote_text'], 60)) . '»',
        );
        $this->views->bannerView($userId, $bannerId);

        return true;
    }

    private function onTargetAdd(int $userId, string $text): bool
    {
        if (!preg_match('/^-?\d+$/', $text)) {
            $this->app->sender->notice($userId, '❌ آیدی باید عددی باشد. دوباره بفرستید.');

            return true;
        }

        $chatId = (int) $text;
        $chat   = $this->app->api->tryCall('getChat', ['chat_id' => $chatId]);

        if (!\is_array($chat)) {
            $this->app->sender->notice(
                $userId,
                "❌ به این چت دسترسی ندارم.\n" . 'مطمئن شوید ربات عضو آن است و آیدی درست است.',
            );

            return true;
        }

        $id = $this->app->targets->upsert($chat, $userId);
        $this->app->targets->setActive($id, true);

        $count = $this->app->api->tryCall('getChatMemberCount', ['chat_id' => $chatId]);
        if (is_numeric($count)) {
            $this->app->targets->setMembers($chatId, (int) $count);
        }

        $this->app->states->clear($userId);
        $this->app->sender->notice($userId, '✅ گروه ثبت شد.');
        $this->views->targetView($userId, $id);

        return true;
    }
}
