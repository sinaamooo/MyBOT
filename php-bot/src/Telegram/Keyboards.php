<?php

declare(strict_types=1);

namespace App\Telegram;

/** All visible button labels are Persian; callback_data values stay in
 * English (protocol detail, never shown to the user). */
final class Keyboards
{
    /** @return array<string, mixed> */
    private static function kb(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    private static function truncateForButton(string $value): string
    {
        $flat = str_replace(["\n", "\r"], ' ', $value);
        return mb_strlen($flat, 'UTF-8') > 30 ? mb_substr($flat, 0, 30, 'UTF-8') . '…' : $flat;
    }

    private static function backRow(string $target = 'root'): array
    {
        return [['text' => '⬅️ بازگشت', 'callback_data' => "menu:{$target}"]];
    }

    public static function mainMenu(): array
    {
        return self::kb([
            [['text' => '🖥 داشبورد', 'callback_data' => 'menu:dashboard']],
            [['text' => '📡 کنترل سیگنال', 'callback_data' => 'menu:signal_control']],
            [
                ['text' => '🪙 ارزها', 'callback_data' => 'menu:symbols'],
                ['text' => '📊 اندیکاتورها', 'callback_data' => 'menu:indicators'],
            ],
            [
                ['text' => '⚙️ تنظیمات', 'callback_data' => 'menu:settings'],
                ['text' => '📈 آمار', 'callback_data' => 'menu:statistics'],
            ],
            [
                ['text' => '📋 سیگنال‌های فعال', 'callback_data' => 'menu:active'],
                ['text' => '🗂 تاریخچه سیگنال‌ها', 'callback_data' => 'menu:history'],
            ],
            [
                ['text' => '📝 قالب پیام', 'callback_data' => 'menu:templates'],
                ['text' => '🧪 بک‌تست', 'callback_data' => 'menu:backtest'],
            ],
            [
                ['text' => '👥 کاربران', 'callback_data' => 'menu:users'],
                ['text' => '🔐 ادمین‌ها', 'callback_data' => 'menu:admins'],
            ],
            [
                ['text' => '📜 لاگ‌ها', 'callback_data' => 'menu:logs'],
                ['text' => '🌟 ایموجی پرمیوم', 'callback_data' => 'menu:premium_emoji'],
            ],
            [
                ['text' => '🔄 ری‌استارت اسکنر', 'callback_data' => 'scanner:restart'],
                ['text' => '⛔ توقف اسکنر', 'callback_data' => 'scanner:stop'],
            ],
        ]);
    }

    /** @param array<string, mixed> $params */
    public static function premiumEmoji(array $params = []): array
    {
        $enabled = !empty($params['premium_emoji_enabled']);
        $toggleLabel = $enabled ? '🟢 روشن (بزن تا خاموش شه)' : '🔴 خاموش (بزن تا روشن شه)';
        return self::kb([
            [['text' => $toggleLabel, 'callback_data' => 'pe:toggle']],
            [['text' => '🚨 تنظیم ایموجی هدر سیگنال', 'callback_data' => 'pe:set:signal']],
            [['text' => '🚀 تنظیم ایموجی شکارچی پامپ', 'callback_data' => 'pe:set:pump']],
            self::backRow(),
        ]);
    }

    public static function dashboard(bool $running): array
    {
        $toggle = $running
            ? ['text' => '⛔ توقف اسکنر', 'callback_data' => 'scanner:stop']
            : ['text' => '▶️ روشن کردن اسکنر', 'callback_data' => 'scanner:start'];
        return self::kb([
            [$toggle, ['text' => '🔄 ری‌استارت', 'callback_data' => 'scanner:restart']],
            [['text' => '🔃 تازه‌سازی', 'callback_data' => 'menu:dashboard']],
            self::backRow(),
        ]);
    }

    public static function signalControl(bool $signalsEnabled): array
    {
        $toggle = $signalsEnabled
            ? ['text' => '🔴 غیرفعال کردن سیگنال‌ها', 'callback_data' => 'signals:disable']
            : ['text' => '🟢 فعال کردن سیگنال‌ها', 'callback_data' => 'signals:enable'];
        return self::kb([
            [$toggle],
            [
                ['text' => '🟢 سیگنال دستی LONG', 'callback_data' => 'signals:manual:LONG'],
                ['text' => '🔴 سیگنال دستی SHORT', 'callback_data' => 'signals:manual:SHORT'],
            ],
            [['text' => '🧪 ارسال سیگنال تستی', 'callback_data' => 'signals:test']],
            self::backRow(),
        ]);
    }

    /** @param array<int, array<string, mixed>> $pageOfSymbols already-sliced page */
    public static function symbols(array $pageOfSymbols, int $page, bool $hasMore, int $total): array
    {
        $rows = [];
        foreach ($pageOfSymbols as $s) {
            $dot = $s['enabled'] ? '🟢' : '🔴';
            $rows[] = [
                ['text' => "{$s['symbol']} {$dot}", 'callback_data' => "sym:toggle:{$s['symbol']}"],
                ['text' => '🗑', 'callback_data' => "sym:remove:{$s['symbol']}"],
            ];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '⬅️ قبلی', 'callback_data' => 'sym:page:' . ($page - 1)];
        }
        if ($hasMore) {
            $nav[] = ['text' => 'بعدی ➡️', 'callback_data' => 'sym:page:' . ($page + 1)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = [
            ['text' => '➕ افزودن ارز', 'callback_data' => 'sym:add'],
            ['text' => '🔄 همگام‌سازی Top-N بر اساس حجم', 'callback_data' => 'sym:sync'],
        ];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $params */
    public static function indicators(array $params): array
    {
        $flags = [
            ['EMA', 'indicator_ema_enabled'],
            ['RSI', 'indicator_rsi_enabled'],
            ['MACD', 'indicator_macd_enabled'],
            ['ADX', 'indicator_adx_enabled'],
            ['حجم معاملات', 'indicator_volume_enabled'],
            ['بولینگر باند', 'indicator_bollinger_enabled'],
            ['اوردر بلاک / بریکر بلاک (ICT-SMC)', 'indicator_smc_ob_enabled'],
            ['شکاف ارزش منصفانه FVG (ICT-SMC)', 'indicator_fvg_enabled'],
            ['نقدینگی / شکار استاپ (ICT-SMC)', 'indicator_liquidity_enabled'],
            ['ساختار بازار BOS/CHoCH (ICT-SMC)', 'indicator_structure_enabled'],
        ];
        $rows = [];
        foreach ($flags as [$label, $key]) {
            $dot = !empty($params[$key]) ? '🟢' : '🔴';
            $rows[] = [['text' => "{$label} {$dot}", 'callback_data' => "ind:toggle:{$key}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @return array<string, array<int, array{0:string,1:string}>> category => [[label, key], ...] */
    public static function settingsCategories(): array
    {
        return [
            'scoring' => [
                ['حداقل امتیاز (LONG/SHORT)', 'min_score'],
                ['حداقل امتیاز واچ‌لیست', 'watchlist_min_score'],
                ['حداقل ADX', 'min_adx'],
                ['ضریب تأیید حجم', 'volume_confirm_multiplier'],
                ['وزن: روند', 'score_weight_trend'],
                ['وزن: EMA', 'score_weight_ema'],
                ['وزن: RSI', 'score_weight_rsi'],
                ['وزن: MACD', 'score_weight_macd'],
                ['وزن: ADX', 'score_weight_adx'],
                ['وزن: حجم', 'score_weight_volume'],
                ['وزن: پرایس اکشن', 'score_weight_price_action'],
                ['وزن: تأیید تایم‌فریم بالاتر', 'score_weight_htf'],
                ['وزن: اوردر بلاک/بریکر بلاک', 'score_weight_smc_ob'],
                ['وزن: FVG', 'score_weight_fvg'],
                ['وزن: نقدینگی/شکار استاپ', 'score_weight_liquidity'],
                ['وزن: ساختار بازار BOS/CHoCH', 'score_weight_structure'],
            ],
            'risk' => [
                ['ضریب ATR برای استاپ', 'atr_sl_multiplier'],
                ['ضریب ریسک TP1', 'tp1_r_multiple'],
                ['ضریب ریسک TP2', 'tp2_r_multiple'],
                ['ضریب ریسک TP3', 'tp3_r_multiple'],
                ['حداقل ریسک به ریوارد', 'min_rr'],
            ],
            'leverage' => [
                ['حداکثر اهرم - نوسان کم', 'leverage_low_vol_max'],
                ['حداکثر اهرم - نوسان متوسط', 'leverage_medium_vol_max'],
                ['حداکثر اهرم - نوسان زیاد', 'leverage_high_vol_max'],
                ['کف مطلق اهرم', 'leverage_absolute_min'],
                ['سقف مطلق اهرم', 'leverage_absolute_max'],
                ['آستانه ATR% نوسان کم', 'leverage_low_vol_atr_pct'],
                ['آستانه ATR% نوسان متوسط', 'leverage_medium_vol_atr_pct'],
            ],
            'scanner' => [
                ['فاصله اسکن (ثانیه)', 'scan_interval_seconds'],
                ['کول‌داون سیگنال (ثانیه)', 'signal_cooldown_seconds'],
                ['حداکثر سیگنال فعال', 'max_active_signals'],
                ['حداکثر سیگنال روزانه', 'max_daily_signals'],
                ['حداکثر ضرر روزانه % (۰=خاموش)', 'max_daily_loss_percent'],
            ],
            'monitoring' => [
                ['فاصله مانیتور (ثانیه)', 'monitor_interval_seconds'],
                ['ریسک‌فری فعال', 'risk_free_enabled'],
                ['تریلینگ استاپ فعال', 'trailing_stop_enabled'],
            ],
            'discovery' => [
                ['همگام‌سازی خودکار ارزها', 'symbol_auto_sync_enabled'],
                ['Top-N بر اساس حجم', 'symbol_top_n'],
            ],
            'pump' => [
                ['شکارچی پامپ فعال', 'pump_hunter_enabled'],
                ['ضریب حجم', 'pump_volume_multiplier'],
                ['امتیاز جایزه', 'pump_score_bonus'],
                ['بازه شتاب (کندل)', 'pump_momentum_lookback'],
            ],
            'daily_messages' => [
                ['منطقه زمانی', 'notification_timezone'],
                ['پیام صبح‌بخیر فعال', 'morning_message_enabled'],
                ['ساعت صبح‌بخیر (HH:MM)', 'morning_message_time'],
                ['متن صبح‌بخیر', 'morning_message_text'],
                ['پیام شب‌بخیر فعال', 'night_message_enabled'],
                ['ساعت شب‌بخیر (HH:MM)', 'night_message_time'],
                ['متن شب‌بخیر', 'night_message_text'],
            ],
        ];
    }

    public static function categoryTitles(): array
    {
        return [
            'scoring' => '🎯 امتیازدهی و آستانه‌ها',
            'risk' => '🛡 ریسک (استاپ/تارگت/ریسک‌ریوارد)',
            'leverage' => '⚡ سطوح اهرم',
            'scanner' => '🔁 اسکنر و کول‌داون',
            'monitoring' => '👁 مانیتورینگ',
            'discovery' => '🪙 کشف ارز',
            'pump' => '🚀 شکارچی پامپ',
            'daily_messages' => '☀️ پیام‌های روزانه',
        ];
    }

    public static function settingsMenu(): array
    {
        $titles = self::categoryTitles();
        $rows = [];
        foreach ($titles as $cat => $title) {
            $rows[] = [['text' => $title, 'callback_data' => "set:cat:{$cat}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $params */
    public static function settingsCategory(string $category, array $params): array
    {
        $rows = [];
        foreach (self::settingsCategories()[$category] as [$label, $key]) {
            $value = $params[$key] ?? null;
            if (is_bool($value)) {
                $display = $value ? '🟢 روشن' : '🔴 خاموش';
                $rows[] = [['text' => "{$label}: {$display}", 'callback_data' => "set:bool:{$key}"]];
            } else {
                $display = is_string($value) ? self::truncateForButton($value) : (string) $value;
                $rows[] = [['text' => "{$label}: {$display}", 'callback_data' => "set:edit:{$key}"]];
            }
        }
        $rows[] = [['text' => '⬅️ بازگشت', 'callback_data' => 'menu:settings']];
        return self::kb($rows);
    }

    public static function statistics(): array
    {
        return self::kb([[['text' => '🔃 تازه‌سازی', 'callback_data' => 'menu:statistics']], self::backRow()]);
    }

    /** @param array<int, array<string, mixed>> $signals */
    public static function activeSignals(array $signals): array
    {
        $rows = [];
        foreach ($signals as $s) {
            $rows[] = [['text' => "{$s['symbol']} {$s['side']} ({$s['status']})", 'callback_data' => "sig:view:{$s['id']}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $signal */
    public static function signalDetail(array $signal): array
    {
        return self::kb([
            [
                ['text' => '❌ لغو', 'callback_data' => "sig:cancel:{$signal['id']}"],
                ['text' => '🔒 بستن همین الان', 'callback_data' => "sig:close:{$signal['id']}"],
            ],
            [['text' => '⬅️ بازگشت', 'callback_data' => 'menu:active']],
        ]);
    }

    /** @param array<int, array<string, mixed>> $signals */
    public static function history(array $signals, int $page): array
    {
        $rows = [];
        foreach ($signals as $s) {
            $result = $s['result'] ?? '-';
            $rows[] = [['text' => "{$s['symbol']} {$s['side']} {$s['status']} ({$result})", 'callback_data' => "sig:view:{$s['id']}"]];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '⬅️ قبلی', 'callback_data' => 'hist:page:' . ($page - 1)];
        }
        if (count($signals) === 10) {
            $nav[] = ['text' => 'بعدی ➡️', 'callback_data' => 'hist:page:' . ($page + 1)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $params */
    public static function templates(array $params = []): array
    {
        $quoteOn = !empty($params['signal_quote_enabled']);
        $quoteLabel = $quoteOn ? '💬 نقل‌قول انگیزشی: 🟢 روشن' : '💬 نقل‌قول انگیزشی: 🔴 خاموش';
        $cardOn = !empty($params['signal_card_enabled']);
        $cardLabel = $cardOn ? '🖼 کارت تصویری رنک: 🟢 روشن' : '🖼 کارت تصویری رنک: 🔴 خاموش';
        return self::kb([
            [['text' => '✏️ ویرایش قالب سیگنال', 'callback_data' => 'tpl:edit']],
            [['text' => $quoteLabel, 'callback_data' => 'tpl:toggle_quote']],
            [['text' => $cardLabel, 'callback_data' => 'tpl:toggle_card']],
            [['text' => '♻️ بازگشت به پیش‌فرض', 'callback_data' => 'tpl:reset']],
            self::backRow(),
        ]);
    }

    public static function users(int $page, bool $hasMore): array
    {
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '⬅️ قبلی', 'callback_data' => 'usr:page:' . ($page - 1)];
        }
        if ($hasMore) {
            $nav[] = ['text' => 'بعدی ➡️', 'callback_data' => 'usr:page:' . ($page + 1)];
        }
        $rows = $nav !== [] ? [$nav] : [];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<int, array<string, mixed>> $admins */
    public static function admins(array $admins): array
    {
        $rows = [];
        foreach ($admins as $a) {
            $label = $a['label'] ?: $a['telegram_id'];
            $rows[] = [['text' => "🔐 {$label}", 'callback_data' => "adm:remove:{$a['telegram_id']}"]];
        }
        $rows[] = [['text' => '➕ افزودن ادمین', 'callback_data' => 'adm:add']];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    public static function logs(): array
    {
        return self::kb([[['text' => '🔃 تازه‌سازی', 'callback_data' => 'menu:logs']], self::backRow()]);
    }

    public static function backtest(): array
    {
        return self::kb([[['text' => '▶️ اجرای بک‌تست', 'callback_data' => 'bt:start']], self::backRow()]);
    }

    public static function cancel(string $target = 'root'): array
    {
        return self::kb([self::backRow($target)]);
    }
}
