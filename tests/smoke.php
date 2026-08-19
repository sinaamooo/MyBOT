<?php

declare(strict_types=1);

/**
 * Offline smoke test — no network, no token needed.
 *
 *   php tests/smoke.php
 *
 * Covers the parts that are easy to get subtly wrong: entity preservation for
 * premium emoji, quote replay, media vs text dispatch, queue expansion,
 * error classification and the rate limiter.
 */

use MyBot\Service\Activity;
use MyBot\Service\Banners;
use MyBot\Service\Campaigns;
use MyBot\Service\Sender;
use MyBot\Service\Stats;
use MyBot\Service\States;
use MyBot\Service\Targets;
use MyBot\Service\Users;
use MyBot\Support\ApiException;
use MyBot\Support\Database;
use MyBot\Support\Keyboards;
use MyBot\Support\Logger;
use MyBot\Support\RateLimiter;
use MyBot\Support\TelegramClient;
use MyBot\Support\Text;

$root = \dirname(__DIR__);
require_once $root . '/src/Autoload.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  \033[32m✔\033[0m {$label}\n";

        return;
    }

    $failed++;
    echo "  \033[31m✘\033[0m {$label}\n";
}

function section(string $title): void
{
    echo "\n\033[1m{$title}\033[0m\n";
}

$dbFile = sys_get_temp_dir() . '/mybot-smoke-' . getmypid() . '.sqlite';
@unlink($dbFile);

$db = new Database($dbFile);
$db->migrate($root . '/schema.sql');

$logger    = new Logger(null, 'error');
$api       = new TelegramClient('0:test', $logger);
$users     = new Users($db);
$targets   = new Targets($db);
$banners   = new Banners($db);
$campaigns = new Campaigns($db, $targets, $users);
$sender    = new Sender($api);
$activity  = new Activity($db);
$states    = new States($db);
$stats     = new Stats($db, $users, $targets, $banners, $campaigns, $activity);

// ---------------------------------------------------------------------------
section('۱) استخراج بنر از پیام (ایموجی پریمیوم + نقل‌قول)');

$incoming = [
    'message_id' => 10,
    'text'       => 'تخفیف ویژه 🔥 امروز',
    'entities'   => [
        ['type' => 'bold', 'offset' => 0, 'length' => 5],
        ['type' => 'custom_emoji', 'offset' => 12, 'length' => 2, 'custom_emoji_id' => '5368324170671202286'],
    ],
    'quote' => [
        'text'     => 'قیمت چند است؟',
        'position' => 4,
        'entities' => [['type' => 'italic', 'offset' => 0, 'length' => 5]],
    ],
];

$fields = $banners->extract($incoming);

check('محتوا استخراج شد', $fields !== null);
check('متن حفظ شد', $fields['text'] === 'تخفیف ویژه 🔥 امروز');
check('entity ها ذخیره شدند', \is_string($fields['entities']));
check('نقل‌قول استخراج شد', $fields['quote_text'] === 'قیمت چند است؟');
check('موقعیت نقل‌قول درست است', $fields['quote_position'] === 4);

$bannerId = $banners->create('بنر تست', $fields, 8213021584);
$banner   = $banners->find($bannerId);

check('بنر ذخیره شد', $banner !== null);
check('شمارش ایموجی پریمیوم درست است', $banners->customEmojiCount($banner) === 1);
check('بنر دارای نقل‌قول شناسایی شد', $banners->hasQuote($banner));

// ---------------------------------------------------------------------------
section('۲) ساخت درخواست ارسال');

[$method, $params] = $sender->build($banner, -100123);

check('متد sendMessage است', $method === 'sendMessage');
check('entities ارسال می‌شود', isset($params['entities']));
check('parse_mode همزمان ست نشده', !isset($params['parse_mode']));
check(
    'custom_emoji_id حفظ شد',
    ($params['entities'][1]['custom_emoji_id'] ?? null) === '5368324170671202286',
);
check('بدون ریپلای، reply_parameters وجود ندارد', !isset($params['reply_parameters']));

[, $replyParams] = $sender->build($banner, -100123, 55);
$reply = $replyParams['reply_parameters'] ?? [];

check('reply_parameters ساخته شد', ($reply['message_id'] ?? null) === 55);
check('quote منتقل شد', ($reply['quote'] ?? null) === 'قیمت چند است؟');
check('quote_position منتقل شد', ($reply['quote_position'] ?? null) === 4);
check('quote_entities منتقل شد', isset($reply['quote_entities']));
check('allow_sending_without_reply روشن است', ($reply['allow_sending_without_reply'] ?? null) === true);

$mediaId = $banners->create('بنر عکس‌دار', [
    'text'          => 'کپشن عکس',
    'entities'      => json_encode([['type' => 'bold', 'offset' => 0, 'length' => 5]]),
    'media_type'    => 'photo',
    'media_file_id' => 'FILEID123',
    'quote_text'    => null,
    'quote_entities' => null,
    'quote_position' => null,
], 1);

[$mediaMethod, $mediaParams] = $sender->build($banners->find($mediaId), 42);

check('مدیا به sendPhoto می‌رود', $mediaMethod === 'sendPhoto');
check('file_id ست شد', ($mediaParams['photo'] ?? null) === 'FILEID123');
check('caption ست شد', ($mediaParams['caption'] ?? null) === 'کپشن عکس');
check('caption_entities ست شد', isset($mediaParams['caption_entities']));
check('text برای مدیا ست نشده', !isset($mediaParams['text']));

// ---------------------------------------------------------------------------
section('۳) دکمه‌ها و پرچم‌ها');

$parsed = $banners->parseButtons("خرید | https://example.com\nکانال | https://t.me/x\n\nپشتیبانی | https://t.me/y");

check('دو ردیف دکمه ساخته شد', $parsed !== null && \count($parsed) === 2);
check('ردیف اول دو دکمه دارد', \count($parsed[0]) === 2);
check('قالب غلط رد می‌شود', $banners->parseButtons('بدون لینک') === null);
check('لینک نامعتبر رد می‌شود', $banners->parseButtons('متن | ftp://x') === null);

$banners->setButtons($bannerId, $parsed);
[, $withButtons] = $sender->build($banners->find($bannerId), 1);

check('reply_markup ساخته شد', isset($withButtons['reply_markup']['inline_keyboard']));

check('پرچم preview تغییر کرد', $banners->toggleFlag($bannerId, 'disable_preview') === true);
[, $noPreview] = $sender->build($banners->find($bannerId), 1);
check('link_preview_options اعمال شد', ($noPreview['link_preview_options']['is_disabled'] ?? null) === true);
check('ستون نامعتبر رد می‌شود', $banners->toggleFlag($bannerId, 'drop table') === false);

$banners->clearQuote($bannerId);
check('نقل‌قول پاک شد', !$banners->hasQuote($banners->find($bannerId)));

// ---------------------------------------------------------------------------
section('۴) گروه‌ها و کاربران');

$targets->upsert(['id' => -1001, 'type' => 'supergroup', 'title' => 'گروه یک']);
$targets->upsert(['id' => -1002, 'type' => 'supergroup', 'title' => 'گروه دو']);
$targets->upsert(['id' => -1001, 'type' => 'supergroup', 'title' => 'گروه یک (ویرایش)']);

check('گروه تکراری دوباره ساخته نشد', $targets->countAll() === 2);
check('عنوان بروزرسانی شد', $targets->findByChatId(-1001)['title'] === 'گروه یک (ویرایش)');
check('هر دو گروه فعال‌اند', $targets->countActive() === 2);

$targets->disablePosting(-1002, 'kicked');
check('گروه غیرقابل ارسال کنار گذاشته شد', $targets->countActive() === 1);

$users->touch(['id' => 501, 'first_name' => 'علی']);
$users->touch(['id' => 502, 'first_name' => 'رضا']);
$users->subscribe(501);
$users->subscribe(502);
check('دو مشترک ثبت شد', $users->countSubscribers() === 2);

$users->unsubscribe(502);
check('لغو عضویت اعمال شد', $users->countSubscribers() === 1);
check('کاربر لغو‌کرده مشترک نیست', !$users->isSubscribed(502));

$users->markBlocked(501);
check('کاربر بلاک‌کننده حذف شد', $users->countSubscribers() === 0);
check('شمارش بلاک درست است', $users->countBlocked() === 1);

// ---------------------------------------------------------------------------
section('۵) کمپین و صف');

$users->subscribe(501);
$users->subscribe(502);

$groupCampaign = $campaigns->create($bannerId, Campaigns::AUDIENCE_GROUPS, null, 1);
$total         = $campaigns->expand($groupCampaign);

check('فقط گروه‌های فعال در صف رفتند', $total === 1);
check('کمپین در حال اجراست', $campaigns->find($groupCampaign)['status'] === Campaigns::STATUS_RUNNING);

$subCampaign = $campaigns->create($bannerId, Campaigns::AUDIENCE_SUBSCRIBERS, null, 1);
check('همه مشترکان در صف رفتند', $campaigns->expand($subCampaign) === 2);

$progress = $campaigns->progress($subCampaign);
check('صف اولیه همه pending است', $progress['pending'] === 2 && $progress['sent'] === 0);

$db->run('UPDATE queue SET status = :s, sent_at = :t WHERE campaign_id = :c', [
    's' => 'sent', 't' => time(), 'c' => $subCampaign,
]);
$campaigns->refresh($subCampaign);

check('کمپین بعد از خالی شدن صف بسته شد', $campaigns->find($subCampaign)['status'] === Campaigns::STATUS_DONE);
check('شمارنده ارسال بروز شد', (int) $campaigns->find($subCampaign)['sent'] === 2);

$dropped = $campaigns->cancel($groupCampaign);
check('لغو کمپین صف را خالی کرد', $dropped === 1);
check('وضعیت لغو ثبت شد', $campaigns->find($groupCampaign)['status'] === Campaigns::STATUS_CANCELLED);

$emptyCampaign = $campaigns->create($bannerId, Campaigns::AUDIENCE_TARGET, -999999, 1);
check('گروه ناموجود مخاطب صفر می‌دهد', $campaigns->expand($emptyCampaign) === 0);
check('کمپین خالی بلافاصله بسته شد', $campaigns->find($emptyCampaign)['status'] === Campaigns::STATUS_DONE);

// ---------------------------------------------------------------------------
section('۶) آمار فعالیت گروه‌ها');

foreach ([['u' => 601, 'n' => 'مریم', 'c' => 5], ['u' => 602, 'n' => 'سارا', 'c' => 2]] as $row) {
    for ($i = 0; $i < $row['c']; $i++) {
        $activity->record([
            'chat' => ['id' => -1001, 'type' => 'supergroup'],
            'from' => ['id' => $row['u'], 'first_name' => $row['n'], 'is_bot' => false],
        ]);
    }
}

$activity->record(['chat' => ['id' => -1001], 'from' => ['id' => 999, 'first_name' => 'ربات', 'is_bot' => true]]);
$activity->record(['chat' => ['id' => 700], 'from' => ['id' => 700, 'first_name' => 'خصوصی']]);

$top = $activity->topMembers(-1001, 7, 10);

check('دو عضو فعال ثبت شدند', \count($top) === 2);
check('پرفعالیت‌ترین اول است', (int) $top[0]['total'] === 5);
check('پیام ربات شمرده نشد', $activity->totalMessages(7) === 7);
check('چت خصوصی شمرده نشد', $activity->activeMemberCount(7) === 2);

// ---------------------------------------------------------------------------
section('۷) طبقه‌بندی خطاهای تلگرام');

$flood = new ApiException('Too Many Requests', 429, ['retry_after' => 12]);
check('flood شناسایی شد', $flood->isFlood() && $flood->retryAfter() === 12);

$blocked = new ApiException('Forbidden: bot was blocked by the user', 403);
check('بلاک کاربر شناسایی شد', $blocked->isBlockedByUser() && $blocked->isUnreachable());

$kicked = new ApiException('Forbidden: bot was kicked from the supergroup chat', 403);
check('اخراج از گروه شناسایی شد', $kicked->isUnreachable() && !$kicked->isBlockedByUser());

$server = new ApiException('Bad Gateway', 502);
check('خطای سرور موقتی است', $server->isTransient() && !$server->isUnreachable());

$badRequest = new ApiException('Bad Request: message text is empty', 400);
check('خطای 400 نه موقتی است نه دائمی‌غیرقابل‌دسترس', !$badRequest->isTransient() && !$badRequest->isUnreachable());

// ---------------------------------------------------------------------------
section('۸) محدودکننده نرخ');

$limiter = new RateLimiter(1000.0, 0.25, 0.0);

$start = microtime(true);
$limiter->throttle(-500);
$limiter->throttle(-500);
$elapsed = microtime(true) - $start;

check('فاصله بین دو پیام یک گروه رعایت شد', $elapsed >= 0.2);

$start = microtime(true);
$limiter->throttle(-501);
check('گروه دیگر بی‌دلیل معطل نشد', (microtime(true) - $start) < 0.1);

// ---------------------------------------------------------------------------
section('۹) وضعیت گفتگو و ابزارها');

$states->set(77, 'banner:name', ['x' => 1]);
$state = $states->get(77);

check('وضعیت ذخیره و خوانده شد', $state['name'] === 'banner:name' && $state['payload']['x'] === 1);

$states->clear(77);
check('وضعیت پاک شد', $states->get(77) === null);

check('عدد فارسی', Text::num(12345) === '۱۲٬۳۴۵');
check('درصد', Text::percent(1, 4) === '۲۵٪');
check('کوتاه‌سازی', mb_strlen(Text::shorten(str_repeat('ا', 100), 20)) === 20);
check('escape HTML', Text::esc('<b>x</b>') === '&lt;b&gt;x&lt;/b&gt;');
check('نوار پیشرفت', Text::bar(5, 10, 10) === '▰▰▰▰▰▱▱▱▱▱');
check('json نامعتبر null می‌دهد', Text::json('نه-json') === null);

$keyboard = Keyboards::main();
check('کیبورد اصلی ساخته شد', isset($keyboard['inline_keyboard'][0][0]['callback_data']));

$longest = 0;
foreach (Keyboards::bannerView(['id' => 999999, 'disable_preview' => 0, 'protect_content' => 0])['inline_keyboard'] as $row) {
    foreach ($row as $button) {
        $longest = max($longest, \strlen($button['callback_data']));
    }
}
check('callback_data زیر ۶۴ بایت است', $longest < 64);

// ---------------------------------------------------------------------------
section('۱۰) صفحه‌های آماری');

$overview = $stats->overview();
check('آمار کلی رندر شد', str_contains($overview, 'آمار کلی'));
check('گزارش کمپین رندر شد', str_contains($stats->campaignReport(), 'کمپین'));
check('گزارش گروه‌ها رندر شد', str_contains($stats->targetReport(), 'گروه'));
check('گزارش فعالیت رندر شد', str_contains($stats->activityReport(), 'فعال‌ترین'));

// ---------------------------------------------------------------------------
section('۱۱) موتور ارسال (بدون شبکه)');

$dispatcher = new MyBot\Service\Dispatcher(
    $db, $sender, $banners, $campaigns, $targets, $users,
    new RateLimiter(1000.0, 0.0, 0.0), $logger, 3, 50,
);

// صف خالی است، پس هیچ درخواستی به تلگرام زده نمی‌شود.
check('چرخه روی صف خالی صفر برمی‌گرداند', $dispatcher->tick() === 0);
check('بازیابی صف بدون خطای SQL اجرا شد', $dispatcher->recoverStuck(0) === 0);

// یک ردیف جامانده در حالت sending شبیه‌سازی می‌کنیم.
$stuck = $campaigns->create($bannerId, Campaigns::AUDIENCE_SUBSCRIBERS, null, 1);
$campaigns->expand($stuck);
$db->run('UPDATE queue SET status = :s WHERE campaign_id = :c', ['s' => 'sending', 'c' => $stuck]);
$db->run('UPDATE campaigns SET started_at = :t WHERE id = :c', ['t' => time() - 9999, 'c' => $stuck]);

check('ردیف‌های جامانده بازیابی شدند', $dispatcher->recoverStuck(300) === 2);
check('وضعیت به pending برگشت', $campaigns->progress($stuck)['pending'] === 2);

$campaigns->cancel($stuck);

// ---------------------------------------------------------------------------
unset($db);
@unlink($dbFile);
@unlink($dbFile . '-wal');
@unlink($dbFile . '-shm');

echo "\n", str_repeat('─', 46), "\n";
printf("موفق: %d — ناموفق: %d\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
