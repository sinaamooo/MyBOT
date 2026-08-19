<?php

declare(strict_types=1);

namespace MyBot\Support;

/**
 * Inline keyboard builders.
 *
 * Callback data format is "section:action:arg1:arg2" and must stay under
 * Telegram's 64-byte limit, so ids are used rather than names.
 */
final class Keyboards
{
    /** @return array{inline_keyboard: list<list<array<string, string>>>} */
    public static function main(): array
    {
        return self::wrap([
            [self::btn('🖼 بنرها', 'bn:list:0'), self::btn('👥 گروه‌ها', 'tg:list:0')],
            [self::btn('📣 کمپین‌ها', 'cp:list'), self::btn('📊 آمار', 'st:overview')],
            [self::btn('🔥 فعال‌ترین‌ها', 'st:activity'), self::btn('⚙️ تنظیمات', 'sy:info')],
        ]);
    }

    public static function back(string $target = 'menu:main'): array
    {
        return self::wrap([[self::btn('◀️ بازگشت', $target)]]);
    }

    /**
     * @param list<array<string, mixed>> $banners
     */
    public static function bannerList(array $banners, int $page, bool $hasNext): array
    {
        $rows = [];

        foreach ($banners as $banner) {
            $badges = '';
            if (($banner['media_type'] ?? null) !== null) {
                $badges .= ' 🖼';
            }
            if (\is_string($banner['quote_text'] ?? null) && $banner['quote_text'] !== '') {
                $badges .= ' 💬';
            }

            $rows[] = [self::btn(
                '#' . $banner['id'] . ' ' . Text::shorten((string) $banner['name'], 24) . $badges,
                'bn:view:' . $banner['id'],
            )];
        }

        $nav = [];
        if ($page > 0) {
            $nav[] = self::btn('◀️', 'bn:list:' . ($page - 1));
        }
        if ($hasNext) {
            $nav[] = self::btn('▶️', 'bn:list:' . ($page + 1));
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [self::btn('➕ بنر جدید', 'bn:new')];
        $rows[] = [self::btn('◀️ منوی اصلی', 'menu:main')];

        return self::wrap($rows);
    }

    /** @param array<string, mixed> $banner */
    public static function bannerView(array $banner): array
    {
        $id      = (int) $banner['id'];
        $preview = ((int) $banner['disable_preview']) === 1 ? '🔗 پیش‌نمایش: خاموش' : '🔗 پیش‌نمایش: روشن';
        $protect = ((int) $banner['protect_content']) === 1 ? '🔒 فوروارد: قفل' : '🔓 فوروارد: آزاد';

        $rows = [
            [self::btn('👁 پیش‌نمایش', 'bn:preview:' . $id), self::btn('🚀 ارسال', 'bn:send:' . $id)],
            [self::btn('✏️ ویرایش محتوا', 'bn:edit:' . $id), self::btn('🏷 تغییر نام', 'bn:rename:' . $id)],
            [self::btn('🔘 دکمه‌ها', 'bn:buttons:' . $id), self::btn('💬 نقل‌قول', 'bn:quote:' . $id)],
            [self::btn($preview, 'bn:flag:' . $id . ':disable_preview')],
            [self::btn($protect, 'bn:flag:' . $id . ':protect_content')],
            [self::btn('🗑 حذف', 'bn:del:' . $id), self::btn('◀️ بازگشت', 'bn:list:0')],
        ];

        return self::wrap($rows);
    }

    public static function confirmDelete(string $entity, int $id): array
    {
        return self::wrap([
            [
                self::btn('✅ بله، حذف کن', $entity . ':delok:' . $id),
                self::btn('❌ انصراف', $entity . ':view:' . $id),
            ],
        ]);
    }

    /** @param list<array<string, mixed>> $targets */
    public static function audience(int $bannerId, array $targets): array
    {
        $rows = [
            [self::btn('👥 همه گروه‌های فعال', 'cp:go:' . $bannerId . ':groups:0')],
            [self::btn('👤 کاربران عضو ربات', 'cp:go:' . $bannerId . ':subscribers:0')],
        ];

        foreach (\array_slice($targets, 0, 6) as $target) {
            $label = \is_string($target['title'] ?? null) && $target['title'] !== ''
                ? $target['title']
                : ('چت ' . $target['chat_id']);

            $rows[] = [self::btn(
                '📍 ' . Text::shorten($label, 26),
                'cp:go:' . $bannerId . ':target:' . $target['id'],
            )];
        }

        $rows[] = [self::btn('◀️ بازگشت', 'bn:view:' . $bannerId)];

        return self::wrap($rows);
    }

    /** @param list<array<string, mixed>> $targets */
    public static function targetList(array $targets, int $page, bool $hasNext): array
    {
        $rows = [];

        foreach ($targets as $target) {
            $dot   = ((int) $target['active']) === 1 && ((int) $target['can_post']) === 1 ? '🟢' : '🔴';
            $label = \is_string($target['title'] ?? null) && $target['title'] !== ''
                ? $target['title']
                : ('چت ' . $target['chat_id']);

            $rows[] = [self::btn($dot . ' ' . Text::shorten($label, 26), 'tg:view:' . $target['id'])];
        }

        $nav = [];
        if ($page > 0) {
            $nav[] = self::btn('◀️', 'tg:list:' . ($page - 1));
        }
        if ($hasNext) {
            $nav[] = self::btn('▶️', 'tg:list:' . ($page + 1));
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [self::btn('➕ افزودن با آیدی', 'tg:add')];
        $rows[] = [self::btn('◀️ منوی اصلی', 'menu:main')];

        return self::wrap($rows);
    }

    /** @param array<string, mixed> $target */
    public static function targetView(array $target): array
    {
        $id     = (int) $target['id'];
        $toggle = ((int) $target['active']) === 1 ? '⏸ غیرفعال کن' : '▶️ فعال کن';

        return self::wrap([
            [self::btn($toggle, 'tg:toggle:' . $id), self::btn('🔄 بروزرسانی', 'tg:sync:' . $id)],
            [self::btn('🔥 اعضای فعال', 'tg:top:' . $id)],
            [self::btn('🗑 حذف', 'tg:del:' . $id), self::btn('◀️ بازگشت', 'tg:list:0')],
        ]);
    }

    public static function campaignList(): array
    {
        return self::wrap([
            [self::btn('🔄 بروزرسانی', 'cp:list')],
            [self::btn('◀️ منوی اصلی', 'menu:main')],
        ]);
    }

    public static function campaignView(int $campaignId, bool $cancellable): array
    {
        $rows = [[self::btn('🔄 بروزرسانی', 'cp:view:' . $campaignId)]];

        if ($cancellable) {
            $rows[] = [self::btn('🛑 لغو کمپین', 'cp:cancel:' . $campaignId)];
        }

        $rows[] = [self::btn('◀️ بازگشت', 'cp:list')];

        return self::wrap($rows);
    }

    public static function stats(): array
    {
        return self::wrap([
            [self::btn('📣 کمپین‌ها', 'st:campaigns'), self::btn('👥 گروه‌ها', 'st:targets')],
            [self::btn('🔥 فعال‌ترین‌ها', 'st:activity'), self::btn('🔄 بروزرسانی', 'st:overview')],
            [self::btn('◀️ منوی اصلی', 'menu:main')],
        ]);
    }

    public static function statsBack(): array
    {
        return self::wrap([[self::btn('◀️ بازگشت به آمار', 'st:overview')]]);
    }

    public static function cancelFlow(): array
    {
        return self::wrap([[self::btn('❌ لغو', 'fl:cancel')]]);
    }

    /** @return array{text: string, callback_data: string} */
    public static function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    /**
     * @param list<list<array<string, string>>> $rows
     * @return array{inline_keyboard: list<list<array<string, string>>>}
     */
    public static function wrap(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }
}
