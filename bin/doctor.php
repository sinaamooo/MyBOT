<?php

declare(strict_types=1);

/**
 * Pre-flight check:
 *
 *   php bin/doctor.php
 *
 * Verifies the config, the database, the token, and reports what the bot
 * can and cannot do before you start it for real.
 */

use MyBot\App;
use MyBot\Support\ApiException;

$root = \dirname(__DIR__);
require_once $root . '/src/Autoload.php';

$ok   = static fn (string $m): string => "\033[32m✔\033[0m {$m}";
$bad  = static fn (string $m): string => "\033[31m✘\033[0m {$m}";
$warn = static fn (string $m): string => "\033[33m!\033[0m {$m}";

$failures = 0;

echo "MyBOT — بررسی سلامت\n";
echo str_repeat('─', 46), "\n";

foreach (['curl', 'pdo_sqlite', 'mbstring', 'json'] as $extension) {
    if (\extension_loaded($extension)) {
        echo $ok("افزونه {$extension}"), "\n";
    } else {
        echo $bad("افزونه {$extension} نصب نیست"), "\n";
        $failures++;
    }
}

try {
    $app = App::boot($root);
    echo $ok('پیکربندی و دیتابیس'), "\n";
} catch (Throwable $e) {
    echo $bad('راه‌اندازی ناموفق: ' . $e->getMessage()), "\n";

    exit(1);
}

if ($app->admins() === []) {
    echo $warn('هیچ ادمینی تعریف نشده — پنل برای کسی باز نمی‌شود'), "\n";
} else {
    echo $ok('ادمین‌ها: ' . implode(', ', array_map(strval(...), $app->admins()))), "\n";
}

try {
    $me = $app->api->call('getMe');

    if (\is_array($me)) {
        echo $ok('اتصال به تلگرام — @' . ($me['username'] ?? '?')), "\n";

        if (($me['can_join_groups'] ?? false) !== true) {
            echo $warn('ربات اجازه عضویت در گروه ندارد (Group Privacy در BotFather)'), "\n";
        }

        if (($me['can_read_all_group_messages'] ?? false) !== true) {
            echo $warn(
                'حالت Privacy روشن است: ربات همه پیام‌های گروه را نمی‌بیند، '
                . 'پس آمار «فعال‌ترین اعضا» ناقص می‌شود. '
                . 'در BotFather → Group Privacy → Disable',
            ), "\n";
        }
    }
} catch (ApiException $e) {
    echo $bad('اتصال به تلگرام ناموفق: ' . $e->getMessage()), "\n";
    $failures++;
}

$pending = $app->db->count('SELECT COUNT(*) FROM queue WHERE status = :s', ['s' => 'pending']);

echo str_repeat('─', 46), "\n";
printf("بنر: %d | گروه فعال: %d | عضو: %d | در صف: %d\n",
    $app->banners->countAll(),
    $app->targets->countActive(),
    $app->users->countSubscribers(),
    $pending,
);

echo str_repeat('─', 46), "\n";
echo $failures === 0
    ? "همه چیز آماده است. اجرا کنید:\n  php bin/bot.php\n  php bin/worker.php\n"
    : "{$failures} مشکل پیدا شد — قبل از اجرا برطرف کنید.\n";

exit($failures === 0 ? 0 : 1);
