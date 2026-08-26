<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ»
 *
 * همان قالب مینی‌اپ خدمات تلگرام را می‌پوشد — چون آن ساختار جواب داده:
 * چهار صفحه (خانه، پلن‌ها، سفارش‌ها، حساب)، محصول‌ها دوتا دوتا، انتخاب
 * بسته با تیک، و صفحه‌ی مدیریت برای مدیر.
 *
 * تفاوتشان از رنگ می‌آید، نه از ساختار: این یکی سبز نئون است و آن یکی
 * بنفش/فیروزه‌ای. هر دو از یک قالب می‌خوانند تا هر بهبودی روی هردو
 * بنشیند و با گذشت زمان از هم دور نیفتند.
 *
 * چیزی که فقط اینجا معنی دارد — انتخاب حجم رند — داخل همان قالب
 * مشترک است و برای مینی‌اپ تلگرام صرفا هیچ‌وقت نمایش داده نمی‌شود.
 */

function maViewCfg($a, $boot) {
    $th   = $a['theme'] ?? [];
    $c1   = $th['c1'] ?? '#00FF9C';
    $c2   = $th['c2'] ?? '#00B3FF';
    $c3   = $th['c3'] ?? '#FF2E97';
    $bg   = $th['bg'] ?? '#04070A';
    $glow = !empty($th['glow']) ? '1' : '0';
    $grain= !empty($th['grain']) ? '1' : '0';
    $fx   = (string)maFxLevel($th);

    return strtr(maTplApp(), [
        '__C1__'    => $c1,
        '__C2__'    => $c2,
        '__C3__'    => $c3,
        '__BG__'    => $bg,
        '__GLOW__'  => $glow,
        '__GRAIN__' => $grain,
        '__FX__'    => $fx,
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}
