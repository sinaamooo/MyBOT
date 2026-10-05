<?php
/**
 * Refreshes the ready-to-upload copies kept in the repository:
 *   autosomal/                the bot files (bot.php, cron.php, modules/, config.example.php)
 *   zip/AnalystBot-Hub.zip    the same files as one zip
 *
 *   php tools/release.php
 *
 * No secrets: config.php is never included, only config.example.php, so uploading the
 * folder over a running install never replaces its real config.php.
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$root = dirname(__DIR__);
$dir = "$root/autosomal";
$zip = "$root/zip/AnalystBot-Hub.zip";

$rmTree = static function (string $path) use (&$rmTree): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $rmTree("$path/$name");
            }
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
};
$copyTree = static function (string $from, string $to) use (&$copyTree): void {
    if (!is_dir($to)) {
        mkdir($to, 0775, true);
    }
    foreach (scandir($from) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        is_dir("$from/$name") ? $copyTree("$from/$name", "$to/$name") : copy("$from/$name", "$to/$name");
    }
};

// 1. fresh build in a temp folder
$tmp = sys_get_temp_dir() . '/analyst-release-' . getmypid();
$rmTree($tmp);
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/build.php") . ' ' . escapeshellarg($tmp), $code);
if ($code !== 0 || !is_file("$tmp/bot.php")) {
    fwrite(STDERR, "build failed\n");
    exit(1);
}

// 2. autosomal/
$rmTree($dir);
mkdir($dir, 0775, true);
copy("$tmp/bot.php", "$dir/bot.php");
copy("$tmp/cron.php", "$dir/cron.php");
$copyTree("$tmp/modules", "$dir/modules");
copy("$root/config.example.php", "$dir/config.example.php");
file_put_contents("$dir/README.txt", <<<TXT
ربات تحلیل + بنر + سیگنال (یک ربات، /panel با سه بخش)

نصب یا به‌روزرسانی روی هاست (مثلاً public_html/Dina):
1) bot.php و cron.php و پوشه‌ی modules را آپلود و جایگزین کنید.
2) نصب جدید: config.example.php را به config.php تغییر نام دهید و توکن، آیدی مدیر،
   webhook_secret، webhook_url و کلید Gemini را پر کنید.
   به‌روزرسانی: config.php فعلی هاست را نگه دارید (این پوشه config.php ندارد تا روی آن نوشته نشود).
3) یک بار این آدرس را باز کنید تا وبهوک ثبت شود و وضعیت بخش‌ها را ببینید:
   https://دامنه/مسیر/bot.php?setup=<webhook_secret>
4) کرون هر دقیقه (PHP 8.1 یا بالاتر):
   /usr/local/bin/php /home/USER/public_html/Dina/cron.php

TXT);
$rmTree($tmp);

// 3. zip/AnalystBot-Hub.zip
if (!is_dir(dirname($zip))) {
    mkdir(dirname($zip), 0775, true);
}
@unlink($zip);
exec('cd ' . escapeshellarg($dir) . ' && zip -qr -X ' . escapeshellarg($zip) . ' . 2>&1', $outLines, $zipCode);
if ($zipCode !== 0 || !is_file($zip)) {
    fwrite(STDERR, "zip failed (is the zip command installed?)\n" . implode("\n", $outLines) . "\n");
    exit(1);
}

echo "autosomal/ and zip/AnalystBot-Hub.zip (" . round(filesize($zip) / 1024) . " KB) updated\n";
