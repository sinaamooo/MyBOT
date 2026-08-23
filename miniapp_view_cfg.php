<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ» — تم منشور (Prism)
 *
 * عمدا هیچ‌چیزش شبیه مینی‌اپ خدمات تلگرام نیست:
 *   • آنجا شفق و دایره و گوشه‌های گرد است؛ اینجا مشبک، خط‌کش و گوشه‌های بریده
 *   • آنجا جزیره‌ی شناور پایین است؛ اینجا نوار تمام‌عرض با نشانگر لغزان
 *   • آنجا رنگ بنفش/فیروزه‌ای؛ اینجا سبز نئون/آبی با تاکید صورتی
 *   • آنجا عددها فارسیِ نرم؛ اینجا عددها در قاب مونواسپیس مثل صفحه‌ی دستگاه
 *
 * صفحه‌ها: خانه · پلن‌ها · سفارش‌ها · پروفایل — محصول‌ها دوتا دوتا.
 * قاعده‌های سرعت همان‌هاست: بلور فقط روی سطح‌های ثابت، ساخت یک‌باره‌ی کارت‌ها،
 * فیلتر با کلاس، و هر حرکتی فقط transform/opacity.
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

    return strtr(maTplCfg(), [
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

function maTplCfg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- فونت نباید رندر را نگه دارد: در ایران گوگل‌فونت اغلب کند یا نارساست و
     صفحه‌ی سفیدِ چندثانیه‌ای می‌ساخت. حالا نامتقارن می‌آید و تا رسیدنش
     همان فونت سیستم می‌نشیند. -->
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#E9FBF4; --dim:#7FA096; --line:rgba(0,255,156,.14);
  --pane:#08110F; --pane2:#0B1A16; --edge:rgba(255,255,255,.07);
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  --safe:env(safe-area-inset-bottom,0px);
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}
img{max-width:100%}

/* ═══ مشبک پس‌زمینه ═══ دو گرادیان خطی، بدون filter و بدون انیمیشن لایه‌ی بزرگ */
.mesh{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.5;
  background-image:
    linear-gradient(to right,color-mix(in srgb,var(--c1) 12%,transparent) 1px,transparent 1px),
    linear-gradient(to bottom,color-mix(in srgb,var(--c1) 12%,transparent) 1px,transparent 1px);
  background-size:46px 46px}
.aur{position:fixed;inset:0;z-index:1;pointer-events:none;
  background:
    radial-gradient(56vw 46vw at 100% 0%,color-mix(in srgb,var(--c1) 34%,transparent),transparent 62%),
    radial-gradient(50vw 44vw at 0% 42%,color-mix(in srgb,var(--c2) 30%,transparent),transparent 62%),
    radial-gradient(44vw 40vw at 76% 100%,color-mix(in srgb,var(--c3) 22%,transparent),transparent 60%)}
.scan{position:fixed;left:0;right:0;height:180px;z-index:2;pointer-events:none;opacity:0;
  background:linear-gradient(180deg,transparent,color-mix(in srgb,var(--c1) 14%,transparent),transparent)}
body.fx2 .scan{animation:scan 7.5s linear infinite}
@keyframes scan{0%{transform:translateY(-190px);opacity:.9}100%{transform:translateY(104vh);opacity:.9}}
.fog{position:fixed;inset:0;z-index:3;pointer-events:none;
  background:radial-gradient(126% 76% at 50% 0%,transparent 16%,var(--bg) 90%)}
.grain{position:fixed;inset:0;z-index:4;pointer-events:none;opacity:.045;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.6'/></svg>")}
body.grain-on .grain{display:block}
body.fx0 .mesh{display:none}
@media (prefers-reduced-motion:reduce){ .scan{animation:none!important;display:none} }

.wrap{position:relative;z-index:6;max-width:600px;margin:0 auto;padding:0 14px calc(104px + var(--safe))}

/* ═══ نوار بالا — مثل هدر یک دستگاه ═══ */
.bar{display:flex;align-items:center;gap:11px;padding:15px 2px 13px}
.sig{width:42px;height:42px;flex:0 0 auto;position:relative;display:grid;place-items:center;overflow:hidden;
  font-weight:900;font-size:17px;color:#04120C;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(0 0,100% 0,100% 74%,74% 100%,0 100%)}
.sig img{width:100%;height:100%;object-fit:cover}
.idn{flex:1;min-width:0}
.idn h1{margin:0;font-size:15.5px;font-weight:900;letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.idn p{margin:3px 0 0;font-size:11px;color:var(--dim);display:flex;align-items:center;gap:6px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dot{width:6px;height:6px;border-radius:50%;background:var(--c1);flex:0 0 auto;
  box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 20%,transparent)}
body.fx2 .dot{animation:blink 2.1s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.credit{flex:0 0 auto;padding:8px 12px;font-size:12.5px;font-weight:900;font-family:var(--mono);cursor:pointer;
  border:1px solid var(--line);background:rgba(0,255,156,.06);color:var(--c1);
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}

/* ═══ صفحه‌ها ═══ */
.pg{display:none;animation:pgIn .28s cubic-bezier(.2,.9,.3,1)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .pg{animation:none} }

.hdr{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:20px 0 11px}
.hdr h2{margin:0;font-size:13.5px;font-weight:900;letter-spacing:.2px;display:flex;align-items:center;gap:8px}
.hdr h2:before{content:"";width:14px;height:2px;background:var(--c1)}
.hdr a{font-size:11px;color:var(--c2);font-weight:800;cursor:pointer;font-family:var(--mono)}

/* ═══ کارت اعتبار ═══ */
.credit-card{position:relative;overflow:hidden;padding:18px 17px;
  border:1px solid var(--line);background:linear-gradient(135deg,var(--pane2),var(--pane));
  clip-path:polygon(18px 0,100% 0,100% calc(100% - 18px),calc(100% - 18px) 100%,0 100%,0 18px)}
.credit-card:before{content:"";position:absolute;inset:0;opacity:.4;pointer-events:none;
  background:radial-gradient(80% 130% at 100% 0%,color-mix(in srgb,var(--c1) 40%,transparent),transparent 60%)}
.credit-card .k{position:relative;font-size:10.5px;letter-spacing:1px;color:var(--dim);
  text-transform:uppercase;margin-bottom:7px}
.credit-card .v{position:relative;font-family:var(--mono);font-size:30px;font-weight:800;letter-spacing:-1px;
  color:var(--c1);line-height:1.1}
body.glow-on .credit-card .v{text-shadow:0 0 22px color-mix(in srgb,var(--c1) 55%,transparent)}
.credit-card .u{position:relative;font-size:12px;color:var(--dim);margin-inline-start:6px;font-family:Vazirmatn,inherit}
.credit-card .row{position:relative;display:flex;gap:8px;margin-top:16px}
.credit-card .row button{flex:1;padding:12px;border:1px solid var(--line);cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#04120C;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.credit-card .row button.o{color:var(--c1);background:transparent}
.credit-card .row button:active{transform:translateY(1px)}
.bars{position:relative;display:flex;gap:3px;margin-top:15px;height:4px}
.bars i{flex:1;background:color-mix(in srgb,var(--c1) 22%,transparent)}
.bars i:nth-child(-n+3){background:var(--c1)}

/* ═══ کارت معرفی ═══ */
.intro{margin-top:14px;position:relative;overflow:hidden;padding:20px 17px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 20px),calc(100% - 20px) 100%,0 100%)}
.intro:before{content:"";position:absolute;top:0;right:0;width:120px;height:120px;pointer-events:none;
  background:radial-gradient(circle at 100% 0,color-mix(in srgb,var(--c2) 40%,transparent),transparent 70%)}
.shieldwrap{position:relative;width:74px;height:74px;margin:0 auto 12px}
.shieldwrap svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.shieldwrap u{position:absolute;inset:-8px;text-decoration:none;
  border:1px solid color-mix(in srgb,var(--c1) 35%,transparent);
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%)}
body.fx2 .shieldwrap u{animation:hex 6s ease-in-out infinite}
@keyframes hex{0%,100%{transform:scale(1);opacity:.45}50%{transform:scale(1.09);opacity:1}}
body.fx2 .shieldwrap svg{animation:hover 4.2s ease-in-out infinite}
@keyframes hover{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@media (prefers-reduced-motion:reduce){ .shieldwrap u,.shieldwrap svg{animation:none!important} }
.intro h2{position:relative;margin:0 0 8px;text-align:center;font-size:17px;font-weight:900;color:var(--c1)}
.intro p{position:relative;margin:0;text-align:center;font-size:12px;line-height:1.95;color:#B9D6CC}

/* ═══ ردیف نوع سرویس ═══ */
.strip{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:none}
.strip::-webkit-scrollbar{display:none}
.strip .sc{flex:0 0 auto;width:88px;padding:13px 6px;text-align:center;cursor:pointer;
  border:1px solid var(--edge);background:var(--pane);transition:border-color .18s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.strip .sc .ico{margin:0 auto 7px}
.strip .sc span{display:block;font-size:10.5px;font-weight:800;color:var(--dim);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.strip .sc.on{border-color:var(--c1)}
.strip .sc.on span{color:var(--ink)}

/* ═══ آیکون‌ها ═══ */
.ico{position:relative;width:36px;height:36px;display:grid;place-items:center;overflow:hidden;
  background:linear-gradient(150deg,rgba(255,255,255,.12),rgba(255,255,255,.02));
  border:1px solid rgba(255,255,255,.14);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.2),inset 0 -6px 12px rgba(0,0,0,.22);
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px);
  transition:transform .24s cubic-bezier(.34,1.56,.64,1),border-color .24s}
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(112deg,transparent 42%,rgba(255,255,255,.5) 50%,transparent 58%);
  transform:translateX(-130%)}
.ico svg{position:relative;width:20px;height:20px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
.ico .ico-em{font-size:18px;font-style:normal;line-height:1}
.on>.ico,.sc.on .ico{transform:translateY(-3px);border-color:color-mix(in srgb,var(--c1) 70%,transparent)}
.on>.ico:before,.sc.on .ico:before{animation:sheen 2.9s cubic-bezier(.4,0,.2,1) infinite}
@keyframes sheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}
.i-spin{transform-box:fill-box;transform-origin:50% 50%}
.on .i-spin {animation:iSpin 5.5s linear infinite}
.on .i-pulse{animation:iPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-float{animation:iFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-lid  {animation:iLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
.on .i-draw {stroke-dasharray:64;animation:iDraw 2.6s ease-in-out infinite}
.on .i-tick {animation:iTick 4s steps(12) infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes iSpin {to{transform:rotate(360deg)}}
@keyframes iPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.72}}
@keyframes iFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes iLid  {0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@keyframes iDraw {0%{stroke-dashoffset:64}45%,100%{stroke-dashoffset:0}}
@keyframes iTick {to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){
  .ico,.ico:before,.on>.ico:before,.sc.on .ico:before,
  .on .i-spin,.on .i-pulse,.on .i-float,.on .i-lid,.on .i-draw,.on .i-tick{animation:none!important;transition:none!important}
}

/* ═══ جستجو و فیلتر ═══ */
.seek{position:relative;margin:4px 0 11px}
.seek input{width:100%;padding:13px 40px 13px 13px;border:1px solid var(--edge);
  background:var(--pane);color:var(--ink);font-family:inherit;font-size:13px;outline:none;transition:.2s;
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.seek input:focus{border-color:var(--c1)}
.seek span{position:absolute;top:50%;right:13px;transform:translateY(-50%);opacity:.45;font-size:14px}
.filt{display:flex;gap:6px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none}
.filt::-webkit-scrollbar{display:none}
.filt b{flex:0 0 auto;padding:9px 14px;cursor:pointer;font-size:11.5px;font-weight:800;white-space:nowrap;
  color:var(--dim);border:1px solid var(--edge);background:var(--pane);transition:.18s;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.filt b.on{color:#04120C;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c2))}

/* ═══ کارت پلن — دوتا دوتا ═══ */
.deck{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.plan{position:relative;overflow:hidden;padding:13px 12px 12px;cursor:pointer;contain:content;
  border:1px solid var(--edge);background:var(--pane);
  display:flex;flex-direction:column;min-height:170px;
  clip-path:polygon(0 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%);
  transition:border-color .18s,transform .14s;
  animation:up .38s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes up{from{opacity:0;transform:translateY(11px)}to{opacity:1;transform:none}}
.deck:not(.first) .plan{animation:none}
@media (prefers-reduced-motion:reduce){ .plan{animation:none} }
.plan:active{transform:scale(.978)}
.plan:before{content:"";position:absolute;top:0;right:0;bottom:0;width:2px;background:var(--c2);opacity:.5}
.plan.hot:before{background:linear-gradient(180deg,var(--c1),var(--c3));opacity:1}
.plan.hot{border-color:color-mix(in srgb,var(--c1) 42%,transparent)}
.plan.hide{display:none}
.plan.off{opacity:.55}
.pico{position:relative;width:46px;height:46px;display:grid;place-items:center;font-size:22px;margin-bottom:10px;
  border:1px solid var(--line);background:rgba(0,255,156,.07);
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.plan h3{position:relative;margin:0;font-size:12.5px;font-weight:800;line-height:1.6;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.plan p{position:relative;margin:4px 0 0;font-size:10px;color:var(--dim);line-height:1.7;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.plan .end{position:relative;margin-top:auto;padding-top:10px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.plan .cost b{display:block;font-family:var(--mono);font-size:14.5px;font-weight:800;color:var(--c1);letter-spacing:-.4px}
.plan .cost i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:2px}
.plan .arw{width:26px;height:26px;flex:0 0 auto;display:grid;place-items:center;font-size:14px;font-weight:900;
  color:#04120C;background:var(--c1);
  clip-path:polygon(6px 0,100% 0,100% calc(100% - 6px),calc(100% - 6px) 100%,0 100%,0 6px)}
.flag{position:absolute;top:0;left:0;z-index:2;font-size:8.5px;font-weight:900;padding:4px 8px;
  color:#04120C;background:linear-gradient(135deg,var(--c3),var(--c1));
  clip-path:polygon(0 0,100% 0,100% 100%,8px 100%)}
.pulse{position:absolute;top:0;left:0;z-index:2;font-size:8px;font-weight:900;padding:4px 8px;letter-spacing:.4px;
  color:#04120C;background:var(--c2);clip-path:polygon(0 0,100% 0,100% 100%,8px 100%)}
.plan.hasflag .pulse{top:22px}

/* ═══ نرخ زنده ═══ */
.ticks{display:grid;gap:8px}
.tick{display:flex;align-items:center;gap:11px;padding:12px 13px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%)}
.tick .e{font-size:20px;flex:0 0 auto}
.tick .n{flex:1;min-width:0;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tick .n em{display:block;font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600;margin-top:2px}
.tick .p{font-family:var(--mono);font-size:13px;font-weight:800;color:var(--c1);flex:0 0 auto}
.tick .p.down{color:var(--c3);font-size:10.5px;font-family:Vazirmatn,inherit}

/* ═══ سفارش‌ها ═══ */
.rec{display:flex;align-items:center;gap:11px;padding:12px 13px;margin-bottom:8px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%)}
.rec .e{width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;font-size:20px;
  border:1px solid var(--edge);background:rgba(255,255,255,.03)}
.rec .m{flex:1;min-width:0}
.rec .m b{display:block;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rec .m span{display:block;font-size:9.5px;color:var(--dim);margin-top:3px;direction:ltr;text-align:right;font-family:var(--mono)}
.rec .s{flex:0 0 auto;text-align:left}
.rec .s u{display:block;text-decoration:none;font-family:var(--mono);font-size:12px;font-weight:800;color:var(--c1)}
.rec .s i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:3px}

/* ═══ پروفایل ═══ */
.idcard{display:flex;align-items:center;gap:13px;padding:18px 16px;position:relative;overflow:hidden;
  border:1px solid var(--line);background:linear-gradient(135deg,var(--pane2),var(--pane));
  clip-path:polygon(20px 0,100% 0,100% calc(100% - 20px),calc(100% - 20px) 100%,0 100%,0 20px)}
.idcard:before{content:"";position:absolute;inset:0;opacity:.32;pointer-events:none;
  background:radial-gradient(80% 130% at 100% 0%,color-mix(in srgb,var(--c2) 46%,transparent),transparent 60%)}
.idcard .face{position:relative;width:62px;height:62px;flex:0 0 auto;overflow:hidden;display:grid;place-items:center;
  font-size:25px;font-weight:900;color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%)}
.idcard .face img{width:100%;height:100%;object-fit:cover}
.idcard .t{position:relative;flex:1;min-width:0}
.idcard .t b{display:block;font-size:15.5px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.idcard .t span{display:block;font-size:11px;color:var(--dim);margin-top:4px;direction:ltr;text-align:right;font-family:var(--mono)}
.idcard .t code{display:inline-block;margin-top:7px;font-size:10px;padding:3px 9px;font-family:var(--mono);
  border:1px solid var(--line);background:rgba(0,255,156,.06);color:var(--c1);direction:ltr}

.box{margin-top:12px;padding:15px;border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%)}
.box h3{margin:0 0 12px;font-size:12.5px;font-weight:900;display:flex;align-items:center;gap:7px}
/* شماره کارت خط خودش، دکمه زیرش — وگرنه روی گوشی باریک دو خط می‌شد */
.pan{padding:13px;border:1px dashed color-mix(in srgb,var(--c1) 40%,transparent);background:rgba(0,255,156,.05)}
.pan b{display:block;font-family:var(--mono);font-size:18px;font-weight:800;letter-spacing:1.5px;
  direction:ltr;text-align:center;color:var(--c1);white-space:nowrap;overflow-x:auto;scrollbar-width:none}
.pan b::-webkit-scrollbar{display:none}
.pan button{width:100%;margin-top:10px;padding:10px;border:0;cursor:pointer;font-family:inherit;font-size:11.5px;font-weight:800;
  color:#04120C;background:var(--c1);
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.pan button:active{transform:translateY(1px)}
.holder{margin-top:9px;font-size:11px;color:var(--dim)}
.holder b{color:var(--ink)}
.money{margin-top:12px}
.money input{width:100%;padding:13px;border:1px solid var(--edge);background:rgba(255,255,255,.03);
  color:var(--ink);font-family:var(--mono);font-size:15px;font-weight:800;outline:none;text-align:center;transition:.2s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.money input:focus{border-color:var(--c1)}
.picks{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.picks i{padding:7px 11px;font-style:normal;font-family:var(--mono);font-size:11px;font-weight:800;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);color:var(--dim)}
.picks i:active{border-color:var(--c1);color:var(--c1)}
.jump{display:flex;align-items:center;gap:11px;padding:13px;margin-top:8px;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.025);font-size:12px;font-weight:700}
.jump:active{transform:translateY(1px)}
.jump em{flex:1;font-style:normal}
.jump s{text-decoration:none;color:var(--dim);font-size:15px}

.none{text-align:center;padding:44px 18px;color:var(--dim);font-size:12px;line-height:1.9}
.none div{font-size:40px;margin-bottom:10px;opacity:.5}
.ghostbox{height:170px;border:1px solid var(--edge);
  background:linear-gradient(90deg,rgba(255,255,255,.02),rgba(255,255,255,.06),rgba(255,255,255,.02));
  background-size:200% 100%;animation:gsh 1.3s linear infinite}
@keyframes gsh{to{background-position:-200% 0}}

/* ═══ نوار پایین — تمام‌عرض با نشانگر لغزان ═══ */
.rail{position:fixed;left:0;right:0;bottom:0;z-index:30;display:flex;
  padding:6px 6px calc(6px + var(--safe));
  background:rgba(4,12,10,.93);border-top:1px solid var(--line);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
body.fx0 .rail{backdrop-filter:none;-webkit-backdrop-filter:none;background:#050D0B}
.rail:before{content:"";position:absolute;top:-1px;right:0;height:2px;width:25%;background:var(--c1);
  transform:translateX(0);transition:transform .3s cubic-bezier(.2,.9,.3,1)}
.rail.p1:before{transform:translateX(-100%)}
.rail.p2:before{transform:translateX(-200%)}
.rail.p3:before{transform:translateX(-300%)}
@media (prefers-reduced-motion:reduce){ .rail:before{transition:none} }
.rail b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;cursor:pointer;color:var(--dim);font-size:9.5px;font-weight:800;transition:color .16s}
.rail b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rail b.on{color:var(--c1)}

/* ═══ شیت ═══ */
.mask{position:fixed;inset:0;z-index:40;background:rgba(1,5,4,.78);backdrop-filter:blur(6px);
  opacity:0;pointer-events:none;transition:.26s}
.mask.on{opacity:1;pointer-events:auto}
.term{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .36s cubic-bezier(.2,.9,.25,1);
  background:linear-gradient(180deg,#0B1A16,#04070A);
  border-top:1px solid var(--c1);
  padding:10px 16px calc(20px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -22px 56px rgba(0,0,0,.7)}
.term.on{transform:none}
.hold{width:44px;height:3px;background:color-mix(in srgb,var(--c1) 45%,transparent);margin:4px auto 15px}
.term .top{display:flex;align-items:center;gap:12px;margin-bottom:15px}
.term .top .pico{width:54px;height:54px;font-size:26px;margin:0}
.term .top h2{margin:0;font-size:16px;font-weight:900}
.term .top p{margin:4px 0 0;font-size:11px;color:var(--dim);line-height:1.7}

.in{margin-bottom:13px}
.in label{display:block;font-size:11.5px;font-weight:800;color:var(--dim);margin-bottom:7px}
.in input,.in textarea{width:100%;padding:13px;border:1px solid var(--edge);background:rgba(255,255,255,.03);
  color:var(--ink);font-family:inherit;font-size:14px;outline:none;transition:.2s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.in textarea{min-height:78px;resize:vertical;font-size:12.5px}
.in input:focus,.in textarea:focus{border-color:var(--c1)}
.in .tip{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.75}
.pm{display:flex;align-items:center;gap:9px}
.pm button{width:44px;height:44px;flex:0 0 auto;border:1px solid var(--edge);background:rgba(255,255,255,.04);
  color:var(--ink);font-size:20px;font-weight:700;cursor:pointer;transition:.16s}
.pm button:active{border-color:var(--c1);color:var(--c1)}
.pm input{text-align:center;font-family:var(--mono);font-weight:800;font-size:16px}
.quick2{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.quick2 i{padding:6px 11px;font-style:normal;font-family:var(--mono);font-size:11px;font-weight:800;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);color:var(--dim)}
.quick2 i:active{border-color:var(--c1);color:var(--c1)}

/* گزینه‌های حجم */
.vols{display:grid;grid-template-columns:repeat(auto-fill,minmax(94px,1fr));gap:7px;margin-top:5px}
.vols i{display:flex;flex-direction:column;align-items:center;gap:2px;padding:10px 5px;cursor:pointer;font-style:normal;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);transition:.16s;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.vols i:active{transform:scale(.96)}
.vols i b{font-size:12px;font-weight:800;color:var(--ink)}
.vols i u{font-family:var(--mono);font-size:11px;text-decoration:none;color:var(--c1);font-weight:800}
.vols i s{font-size:9px;text-decoration:none;color:var(--dim)}
.vols i.on{border-color:var(--c1);background:rgba(0,255,156,.1)}

.due{display:flex;justify-content:space-between;align-items:center;margin:15px 0;padding:14px 15px;
  border:1px solid var(--line);background:rgba(0,255,156,.06);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 14px),calc(100% - 14px) 100%,0 100%)}
.due span{font-size:12px;color:var(--dim)}
.due b{font-family:var(--mono);font-size:19px;font-weight:800;color:var(--c1)}

.act{width:100%;padding:15px;border:0;cursor:pointer;font-family:inherit;font-size:14.5px;font-weight:900;
  color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));transition:.2s;
  clip-path:polygon(11px 0,100% 0,100% calc(100% - 11px),calc(100% - 11px) 100%,0 100%,0 11px)}
body.glow-on .act{box-shadow:0 12px 30px -16px var(--c1)}
.act:active{transform:translateY(1px)}
.act[disabled]{cursor:default;color:var(--dim);background:rgba(255,255,255,.05);box-shadow:none}
.act.o{margin-top:8px;color:var(--c1);background:transparent;border:1px solid var(--line);
  box-shadow:none;font-weight:800;font-size:13px}
.hintbox{margin-top:10px;padding:11px 13px;font-size:11px;line-height:1.8;
  border:1px solid var(--edge);background:rgba(255,255,255,.025);color:var(--dim)}
.hintbox b{color:var(--c1);font-family:var(--mono)}
.plain{width:100%;margin-top:8px;padding:13px;cursor:pointer;border:1px solid var(--edge);background:transparent;
  color:var(--dim);font-family:inherit;font-size:13px;font-weight:700}

/* ═══ نتیجه ═══ */
.ok{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:28px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 22%,transparent),transparent 72%)}
.ok.on{display:grid}
.seal{position:relative;width:104px;height:104px;margin:0 auto 20px;display:grid;place-items:center;font-size:46px;
  color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%);
  animation:seal .5s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes seal{from{transform:scale(0) rotate(-30deg);opacity:0}to{transform:none;opacity:1}}
@media (prefers-reduced-motion:reduce){ .seal{animation:none} }
.ok h2{margin:0 0 9px;font-size:19px;font-weight:900;color:var(--c1)}
.ok p{margin:0 0 22px;font-size:12px;color:var(--dim);line-height:1.9;max-width:300px}
.ok .ref{font-family:var(--mono);font-size:12px;padding:8px 14px;border:1px solid var(--line);
  background:rgba(0,255,156,.06);margin-bottom:20px;direction:ltr;color:var(--c1)}

/* ═══ هشدار ═══ */
.warn{position:fixed;top:13px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:12px 17px;font-size:12px;font-weight:800;max-width:88vw;text-align:center;line-height:1.7;
  background:linear-gradient(135deg,var(--c3),#B1004B);color:#fff;
  transition:transform .32s cubic-bezier(.2,1.3,.4,1);
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.warn.good{background:linear-gradient(135deg,var(--c1),var(--c2));color:#04120C}
.warn.on{transform:translate(-50%,0)}
</style>
</head>
<body>
<div class="mesh"></div><div class="aur"></div><div class="scan"></div>
<div class="fog"></div><div class="grain"></div>

<div class="wrap">
  <div class="bar">
    <div class="sig" id="sig">🛡</div>
    <div class="idn"><h1 id="ttl">—</h1><p><span class="dot"></span><span id="sub">—</span></p></div>
    <div class="credit" id="credTop">…</div>
  </div>

  <!-- ══ خانه ══ -->
  <section class="pg on" id="pgHome">
    <div class="credit-card">
      <div class="k" id="balLbl">اعتبار شما</div>
      <div class="v"><span id="bal">…</span><span class="u" id="cur"></span></div>
      <div class="bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
      <div class="row">
        <button id="hTop">＋ افزایش اعتبار</button>
        <button class="o" id="hShop">مشاهده پلن‌ها</button>
      </div>
    </div>

    <div class="intro">
      <div class="shieldwrap">
        <u></u>
        <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
          <defs>
            <linearGradient id="sg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="var(--c1)"/><stop offset="1" stop-color="var(--c2)"/>
            </linearGradient>
          </defs>
          <path d="M50 8 L86 21 V50c0 21-15 34-36 42C29 84 14 71 14 50V21z"
                fill="url(#sg)" stroke="rgba(255,255,255,.3)" stroke-width="1.6" stroke-linejoin="round"/>
          <path d="M35 51l11 11 21-23" stroke="#04120C" stroke-width="6.5"
                stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>
      </div>
      <h2 id="wcTtl">—</h2>
      <p id="hero"></p>
    </div>

    <div class="hdr"><h2 id="catsTtl">نوع سرویس</h2><a id="goShop">همه</a></div>
    <div class="strip" id="strip"></div>

    <div id="hotBox" style="display:none">
      <div class="hdr"><h2 id="hotTtl">پرفروش‌ترین‌ها</h2><a id="goShop2">همه</a></div>
      <div class="deck" id="hotDeck"></div>
    </div>

    <div id="tickBox" style="display:none">
      <div class="hdr"><h2 id="ratesTtl">نرخ لحظه‌ای</h2></div>
      <div class="ticks" id="tickList"></div>
    </div>
  </section>

  <!-- ══ پلن‌ها ══ -->
  <section class="pg" id="pgShop">
    <div class="seek"><input id="q" placeholder="جستجو…"><span>⌕</span></div>
    <div class="filt" id="filt"></div>
    <div class="deck" id="deck">
      <div class="ghostbox"></div><div class="ghostbox"></div>
    </div>
  </section>

  <!-- ══ سفارش‌ها ══ -->
  <section class="pg" id="pgOrd">
    <div class="hdr"><h2 id="ordTtl">سفارش‌های اخیر</h2></div>
    <div id="ordList"><div class="none"><div>▤</div>در حال خواندن…</div></div>
  </section>

  <!-- ══ پروفایل ══ -->
  <section class="pg" id="pgMe">
    <div class="idcard">
      <div class="face" id="meFace">🛡</div>
      <div class="t">
        <b id="meName">—</b>
        <span id="meUser"></span>
        <code id="meId"></code>
      </div>
    </div>

    <div class="box" id="topBox">
      <h3>💳 <span id="topTtl">افزایش اعتبار</span></h3>
      <div id="panBox"></div>
      <div class="money"><input id="amt" type="text" inputmode="numeric" placeholder="مبلغ به تومان"></div>
      <div class="picks" id="amtPicks"></div>
      <button class="act" id="topGo" style="margin-top:12px">ثبت درخواست شارژ</button>
      <div class="hintbox" id="topNote"></div>
    </div>

    <div class="box">
      <h3>▤ میان‌بر</h3>
      <div class="jump" id="lnkOrd"><s>▤</s><em>سفارش‌های من</em><s>‹</s></div>
      <div class="jump" id="lnkShop"><s>⬢</s><em>مشاهده پلن‌ها</em><s>‹</s></div>
      <div class="jump" id="lnkBot"><s>⌂</s><em>بازگشت به ربات</em><s>‹</s></div>
      <div class="hintbox" id="meNote" style="margin-top:11px"></div>
    </div>
  </section>
</div>

<nav class="rail" id="rail"></nav>

<div class="mask" id="mask"></div>
<div class="term" id="term">
  <div class="hold"></div>
  <div class="top">
    <div class="pico" id="tIco">⬢</div>
    <div style="flex:1;min-width:0"><h2 id="tName">—</h2><p id="tDesc"></p></div>
  </div>
  <div id="tField"></div>
  <div class="due"><span>مبلغ نهایی</span><b id="tSum">0</b></div>
  <button class="act" id="tWal">پرداخت از کیف پول</button>
  <button class="act o" id="tGo">روش‌های دیگر پرداخت</button>
  <div class="hintbox" id="tWalNote"></div>
  <button class="plain" id="tNo">بستن</button>
</div>

<div class="ok" id="ok">
  <div>
    <div class="seal">✓</div>
    <h2 id="oTtl">سفارش ثبت شد</h2>
    <p id="oSub"></p>
    <div class="ref" id="oRef"></div>
    <button class="act" id="oGo" style="max-width:280px">بازگشت به ربات</button>
    <button class="plain" id="oBack" style="max-width:280px;margin:8px auto 0">ادامه خرید</button>
  </div>
</div>

<div class="warn" id="warn"></div>

<script>
(function(){
"use strict";
var B  = __BOOT__;
var FX = __FX__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };
document.body.classList.add('fx' + FX);
if (__GLOW__)  document.body.classList.add('glow-on');
if (__GRAIN__) document.body.classList.add('grain-on');

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}
function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}
/* «۱۲٬۳۴۵٫۶» → «12345.6»
   رقم فارسی و عربی را لاتین می‌کند، جداکننده‌ی هزارگان را دور می‌ریزد،
   و ممیز — چه «.» چه «٫» فارسی — را نگه می‌دارد. بدون نگه‌داشتن ممیز،
   «۲٫۵ تون» می‌شد «۲۵ تون». */
function digits(s){
  s = String(s == null ? '' : s);
  var out = '', fa0 = 1776, ar0 = 1632, dot = false;
  for (var i=0;i<s.length;i++){
    var ch = s[i], c = s.charCodeAt(i);
    if (c >= fa0 && c <= fa0+9) out += (c - fa0);
    else if (c >= ar0 && c <= ar0+9) out += (c - ar0);
    else if (ch >= '0' && ch <= '9') out += ch;
    else if ((ch === '.' || c === 0x066B) && !dot) { out += '.'; dot = true; }
  }
  return out;
}
/* همان، ولی برای جاهایی که فقط عدد صحیح معنی دارد (مبلغ شارژ) */
function intIn(s){ return Math.floor(Number(digits(s)) || 0); }

var U = B.ui;
$('ttl').textContent    = B.title;
$('sub').textContent    = B.sub || '';
$('hero').textContent   = B.hero || '';
$('wcTtl').textContent  = B.title;
$('cur').textContent    = B.currency;
$('balLbl').textContent = U.balance;
$('q').placeholder      = U.search;
$('tWal').textContent   = U.pay_wallet;
$('tGo').textContent    = U.pay_other;
$('tNo').textContent    = U.close;
$('oTtl').textContent   = U.done;
$('oSub').textContent   = U.done_sub;
$('catsTtl').textContent= U.cats_ttl;
$('hotTtl').textContent = U.hot;
$('ratesTtl').textContent = U.rates_ttl;
$('ordTtl').textContent = U.orders_ttl;
$('topTtl').textContent = U.topup;
$('topGo').textContent  = U.topup_do;
$('goShop').textContent = U.see_all;
$('goShop2').textContent= U.see_all;
$('meNote').textContent = B.note || '';
document.title = B.title;

var S = { page:'home', cat:'', q:'', item:null, qty:1, vol:0, busy:false, bal:0, nodes:[], me:null };

function api(action, extra, ok, bad){
  // آدرس سرور تنظیم نشده؟ به‌جای زدن به خود صفحه (که خطای CORS می‌دهد) صریح بگو
  if (!B.api){ bad({ message:'آدرس سرور مینی‌اپ تنظیم نشده است. با پشتیبانی تماس بگیرید.' }); return; }
  var body = Object.assign({ action:action, app:B.app,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  var ctl = null, timer = null;
  try { ctl = new AbortController(); timer = setTimeout(function(){ ctl.abort(); }, 20000); } catch(e){}
  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined,
    cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'پاسخ سرور نامعتبر بود.'}; }); })
    .then(function(j){ if (timer) clearTimeout(timer); if (j && j.ok) ok(j); else bad(j || {}); })
    .catch(function(){ if (timer) clearTimeout(timer); bad({ message:'ارتباط با سرور برقرار نشد.' }); });
}

var warnT;
function warn(m, good){
  var w = $('warn');
  w.textContent = m;
  w.classList.toggle('good', !!good);
  w.classList.add('on');
  clearTimeout(warnT);
  warnT = setTimeout(function(){ w.classList.remove('on'); }, 3600);
  buzz(good ? 'success' : 'error');
}

function setBal(v){
  S.bal = Number(v) || 0;
  $('bal').textContent     = fa(S.bal);
  $('credTop').textContent = fa(S.bal);
  if (S.item) walletState();
}

/* ── آیکون‌ها ── */
var ICONS = {
  home:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M3.4 10.6L12 3.4l8.6 7.2v9.4H3.4z"/><path d="M9.4 20v-6h5.2v6"/></svg>',
  layers:'<svg viewBox="0 0 24 24"><path class="i-float" d="M12 2.8l9 4.6-9 4.6-9-4.6z"/><path d="M3 12l9 4.6 9-4.6M3 16.6l9 4.6 9-4.6"/></svg>',
  bill:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M5.4 2.8h13.2v18.4l-2.6-1.8-2.2 1.8-2.2-1.8-2.2 1.8-2.2-1.8-1.8 1.8z"/><path class="i-draw" d="M8.6 8.4h6.8M8.6 12.4h6.8M8.6 16.2h4.2"/></svg>',
  user:  '<svg viewBox="0 0 24 24"><circle class="i-float" cx="12" cy="8" r="4.2"/><path d="M3.8 21c.6-4.6 4-7 8.2-7s7.6 2.4 8.2 7"/></svg>',
  box:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M12 2.8l8.4 4.4v9.6L12 21.2 3.6 16.8V7.2z"/><path class="i-float" d="M3.6 7.2L12 11.6l8.4-4.4M12 11.6v9.6"/></svg>',
  inf:   '<svg viewBox="0 0 24 24"><path class="i-draw" d="M8.4 8.2a3.8 3.8 0 100 7.6c3 0 4.2-7.6 7.2-7.6a3.8 3.8 0 110 7.6c-3 0-4.2-7.6-7.2-7.6z"/></svg>',
  lock:  '<svg viewBox="0 0 24 24"><rect x="4.4" y="10.2" width="15.2" height="10.6" rx="2.4"/><path class="i-lid" d="M8 10.2V7.6a4 4 0 018 0v2.6"/><circle class="fl i-pulse" cx="12" cy="15.4" r="1.6"/></svg>',
  shield:'<svg viewBox="0 0 24 24"><path class="i-draw" d="M12 2.6l8 3v6.2c0 5-3.4 8.7-8 9.8-4.6-1.1-8-4.8-8-9.8V5.6z"/><path class="i-draw" d="M8.6 12.2l2.4 2.4 4.6-4.8"/></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><ellipse class="i-pulse" cx="12" cy="12" rx="4" ry="9"/></svg>',
  bolt:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.4 2.2L4.6 13.6h5.4l-.8 8.2 9-11.6h-5.4z"/></svg>',
  clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.8"/><path class="i-tick" d="M12 12V6.6"/><path d="M12 12l3.6 2.2"/></svg>',
  gauge: '<svg viewBox="0 0 24 24"><path class="i-draw" d="M4 17a9 9 0 1116 0"/><path class="i-pulse" d="M12 17l4.4-5.4"/><circle class="fl" cx="12" cy="17" r="1.6"/></svg>',
  fire:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.2 2.4c2.6 3.4 1 5.2 2.6 6.6 1.4 1.2 3.4.4 3.4.4 1.6 4.6-1.4 12.2-7.2 12.2S3.2 15.6 5.6 11c.8 1.6 2.4 2 2.4 2C6.6 8.8 9.6 5 13.2 2.4z"/></svg>',
  tag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M11.4 2.8h9.8v9.8L11.6 22.2 1.8 12.4z"/><circle class="fl i-pulse" cx="17" cy="7" r="1.7"/></svg>',
  coin:  '<svg viewBox="0 0 24 24"><ellipse class="i-pulse" cx="12" cy="12" rx="8.6" ry="8.6"/><path d="M14.6 8.6c-.7-.8-1.7-1.2-2.8-1.2-1.7 0-2.9.9-2.9 2.2 0 3 5.9 1.3 5.9 4.3 0 1.4-1.3 2.3-3 2.3-1.2 0-2.3-.5-3-1.3M12 5.6v12.8"/></svg>'
};
var ICO_MAP = [
  [/vol|حجم|گیگ|گیگا|مگ|giga|\bgb\b/i,      'box'],
  [/unlim|نامحدود|بی.?نهایت/i,               'inf'],
  [/اختصاص|dedicat|private|خصوص|قفل|lock/i,  'lock'],
  [/loc|کشور|لوکیشن|country|سرور|server/i,   'globe'],
  [/fast|سریع|توربو|turbo|speed|پرسرعت/i,    'bolt'],
  [/time|زمان|روز|ماه|month|day/i,           'clock'],
  [/vpn|کانفیگ|config|امن|secure/i,          'shield'],
  [/test|تست|آزمای/i,                        'gauge'],
  [/hot|داغ|ویژه|vip|special|پرفروش/i,       'fire'],
  [/off|تخفیف|حراج|discount|اقتصاد/i,        'tag'],
  [/ارز|currency|coin|نرخ|تتر|usdt|ton/i,    'coin']
];
function icoFor(c){
  var key = String(c.id || '') + ' ' + String(c.name || '');
  for (var i = 0; i < ICO_MAP.length; i++) if (ICO_MAP[i][0].test(key)) return ICONS[ICO_MAP[i][1]];
  return '<span class="ico-em">' + esc(c.emoji || '⬢') + '</span>';
}

/* ── نوار پایین ── */
var PAGES = [
  { id:'Home', ico:'home',   name:U.nav_home },
  { id:'Shop', ico:'layers', name:U.nav_shop },
  { id:'Ord',  ico:'bill',   name:U.nav_orders },
  { id:'Me',   ico:'user',   name:U.nav_me }
];
(function drawRail(){
  var h = '';
  PAGES.forEach(function(p, n){
    h += '<b data-n="' + n + '"><i class="ico">' + ICONS[p.ico] + '</i><span>' + esc(p.name) + '</span></b>';
  });
  $('rail').innerHTML = h;
})();
var TABS = [].slice.call($('rail').children);

function go(n, silent){
  if (typeof n === 'string'){
    for (var k=0;k<PAGES.length;k++) if (PAGES[k].id === n) { n = k; break; }
  }
  n = Number(n) || 0;
  S.page = PAGES[n].id;
  for (var i=0;i<PAGES.length;i++){
    TABS[i].classList.toggle('on', i === n);
    $('pg' + PAGES[i].id).classList.toggle('on', i === n);
  }
  var r = $('rail');
  r.className = 'rail' + (n ? ' p' + n : '');
  window.scrollTo({ top:0, behavior: silent ? 'auto' : 'smooth' });
  if (PAGES[n].id === 'Ord') drawOrders();
  backBtn();
}
$('rail').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  tap(); go(Number(el.getAttribute('data-n')));
});

function backBtn(){
  if (!TG || !TG.BackButton) return;
  try {
    if (S.item || S.page !== 'Home') TG.BackButton.show();
    else TG.BackButton.hide();
  } catch(e){}
}
if (TG && TG.BackButton){
  try { TG.BackButton.onClick(function(){
    if (S.item) { shut(); return; }
    if (S.page !== 'Home') go(0);
  }); } catch(e){}
}

/* ── من ── */
api('me', {}, function(j){
  S.me = j;
  setBal(j.balance);
  var nm = (j.name || '').trim();
  if (nm) $('ttl').textContent = nm;
  $('meName').textContent = nm || B.title;
  $('meUser').textContent = j.uname ? '@' + j.uname : '';
  $('meId').textContent   = 'ID ' + (j.uid || '');
  // عکس فقط وقتی می‌نشیند که واقعا بارگذاری شود — وگرنه نشان خودِ اپ می‌ماند
  if (j.photo) ['sig','meFace'].forEach(function(id){
    var im = new Image();
    im.alt = '';
    im.onload = function(){ var b = $(id); b.textContent = ''; b.appendChild(im); };
    im.src = j.photo;
  });
  if (S.page === 'Ord') drawOrders();
}, function(j){
  setBal(0);
  if (j && j.message) warn(j.message);
});

/* ── ردیف نوع سرویس ── */
(function drawStrip(){
  var h = '';
  B.cats.forEach(function(c){
    h += '<div class="sc" data-c="' + esc(c.id) + '"><i class="ico">' + icoFor(c) + '</i>' +
         '<span>' + esc(c.name) + '</span></div>';
  });
  $('strip').innerHTML = h;
})();
$('strip').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.sc') : null;
  if (!el) return;
  tap(); S.cat = el.getAttribute('data-c'); S.q = ''; $('q').value = '';
  drawFilt(); applyFilter(); go('Shop');
});

function drawFilt(){
  var h = '<b class="' + (S.cat===''?'on':'') + '" data-c="">' + esc(U.all) + '</b>';
  B.cats.forEach(function(c){
    h += '<b class="' + (S.cat===c.id?'on':'') + '" data-c="' + esc(c.id) + '">' +
         esc(c.emoji ? c.emoji + ' ' : '') + esc(c.name) + '</b>';
  });
  $('filt').innerHTML = h;
  var sc = $('strip').children;
  for (var i=0;i<sc.length;i++) sc[i].classList.toggle('on', sc[i].getAttribute('data-c') === S.cat);
}
$('filt').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  S.cat = el.getAttribute('data-c');
  tap(); drawFilt(); applyFilter();
});

/* ── کارت پلن ── */
function planHtml(i, n){
  var cls = 'plan' + (i.badge ? ' hot hasflag' : '') + (i.stale ? ' off' : '');
  var price = i.stale ? '—'
    : (i.ask === 'volume' && i.vols && i.vols.length ? 'از ' + fa(i.vols[0].price) : fa(i.price));
  return '<div class="' + cls + '" data-i="' + esc(i.id) + '" style="animation-delay:' + Math.min(n*32, 280) + 'ms">' +
           (i.badge ? '<span class="flag">' + esc(i.badge) + '</span>' : '') +
           (i.live  ? '<span class="pulse">LIVE</span>' : '') +
           '<div class="pico">' + esc(i.emoji || '⬢') + '</div>' +
           '<h3>' + esc(i.name) + '</h3>' +
           (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
           '<div class="end"><div class="cost"><b>' + price + '</b><i>' + esc(B.currency) +
             (i.unit && ['qty','qty_wallet','qty_username'].indexOf(i.ask) >= 0 ? ' / ' + esc(i.unit) : '') + '</i></div>' +
             '<div class="arw">‹</div></div>' +
         '</div>';
}

function buildDeck(){
  var box = $('deck');
  if (!B.items.length){
    box.style.display = 'block';
    box.innerHTML = '<div class="none"><div>▨</div>' + esc(U.empty) + '</div>';
    return;
  }
  var h = '';
  for (var n = 0; n < B.items.length; n++) h += planHtml(B.items[n], n);
  box.classList.add('first');
  box.innerHTML = h;
  S.nodes = [].slice.call(box.children);
  setTimeout(function(){ box.classList.remove('first'); }, 620);
}

function buildHot(){
  var pick = [];
  for (var i=0;i<B.items.length && pick.length<6;i++) if (B.items[i].badge) pick.push(B.items[i]);
  if (pick.length < 2) pick = B.items.slice(0, 4);
  if (!pick.length) return;
  var h = '';
  for (var k=0;k<pick.length;k++) h += planHtml(pick[k], k);
  $('hotDeck').innerHTML = h;
  $('hotBox').style.display = '';
}

function buildTicks(){
  var live = B.items.filter(function(i){ return i.live; }).slice(0, 5);
  if (!live.length) return;
  var h = '';
  live.forEach(function(i){
    h += '<div class="tick" data-i="' + esc(i.id) + '">' +
           '<span class="e">' + esc(i.emoji || '⬢') + '</span>' +
           '<span class="n">' + esc(i.name) +
             (i.unit ? '<em>هر ' + esc(i.unit) + '</em>' : '') + '</span>' +
           (i.stale ? '<span class="p down">موقتا بسته</span>'
                    : '<span class="p">' + fa(i.price) + '</span>') +
         '</div>';
  });
  $('tickList').innerHTML = h;
  $('tickBox').style.display = '';
}

function openFrom(ev){
  var el = ev.target.closest ? ev.target.closest('[data-i]') : null;
  if (el) open(el.getAttribute('data-i'));
}
$('deck').addEventListener('click', openFrom);
$('hotDeck').addEventListener('click', openFrom);
$('tickList').addEventListener('click', openFrom);

function applyFilter(){
  var q = S.q.trim().toLowerCase(), shown = 0;
  for (var n = 0; n < S.nodes.length; n++){
    var el = S.nodes[n], it = B.items[n];
    var inCat = q ? true : (!S.cat || it.cat === S.cat);
    var ok = inCat && (!q || (it.name + ' ' + it.desc + ' ' + it.badge).toLowerCase().indexOf(q) >= 0);
    el.classList.toggle('hide', !ok);
    if (ok) shown++;
  }
  var none = $('noneBox');
  if (!shown && !none){
    none = document.createElement('div');
    none.id = 'noneBox'; none.className = 'none';
    none.style.gridColumn = '1 / -1';
    none.innerHTML = '<div>▨</div>' + esc(U.empty);
    $('deck').appendChild(none);
  } else if (shown && none){
    none.remove();
  }
}
var qT;
$('q').addEventListener('input', function(){
  var v = this.value;
  clearTimeout(qT);
  qT = setTimeout(function(){ S.q = v; applyFilter(); }, 120);
});

/* ── سفارش‌ها ── */
function drawOrders(){
  var box = $('ordList');
  if (!S.me){ box.innerHTML = '<div class="none"><div>▤</div>در حال خواندن…</div>'; return; }
  var os = S.me.orders || [];
  if (!os.length){ box.innerHTML = '<div class="none"><div>▤</div>' + esc(U.no_orders) + '</div>'; return; }
  var h = '';
  os.forEach(function(o){
    h += '<div class="rec">' +
           '<span class="e">' + esc(o.emoji || '⬢') + '</span>' +
           '<span class="m"><b>' + esc(o.name) + '</b><span>' + esc(o.date || '') + '</span></span>' +
           '<span class="s"><u>' + fa(o.total) + '</u><i>' + esc(o.status) + '</i></span>' +
         '</div>';
  });
  box.innerHTML = h;
}

/* «6037997512345678» → «6037 9975 1234 5678» — فقط برای خواندن */
function prettyCard(v){
  var d = String(v || '').replace(/\D/g, '');
  if (d.length !== 16) return String(v || '');
  return d.replace(/(\d{4})(?=\d)/g, '$1 ');
}

/* ── افزایش اعتبار ── */
(function topup(){
  var t = B.topup || {};
  if (!t.on && !t.gw){ $('topBox').style.display = 'none'; return; }
  if (t.card){
    $('panBox').innerHTML =
      '<div class="pan"><b>' + esc(prettyCard(t.card)) + '</b><button id="panCp">' + esc(U.copy) + '</button></div>' +
      (t.name ? '<div class="holder">به نام: <b>' + esc(t.name) + '</b></div>' : '');
    $('panCp').onclick = function(){
      var v = String(t.card);
      var done = function(){ warn(U.copied, true); tap('medium'); };
      function fallback(){
        try {
          var ta = document.createElement('textarea');
          ta.value = v; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          document.execCommand('copy'); ta.remove(); done();
        } catch(e2){ warn('شماره کارت را دستی یادداشت کنید.'); }
      }
      try {
        if (navigator.clipboard && navigator.clipboard.writeText)
          navigator.clipboard.writeText(v).then(done, fallback);
        else fallback();
      } catch(e){ fallback(); }
    };
  }
  var min = Number(t.min) || 10000;
  $('amtPicks').innerHTML = [min, min*5, min*10, min*20].map(function(p){
    return '<i data-a="' + p + '">' + fa(p) + '</i>';
  }).join('');
  $('amtPicks').addEventListener('click', function(ev){
    var el = ev.target.closest ? ev.target.closest('i[data-a]') : null;
    if (!el) return;
    $('amt').value = fa(Number(el.getAttribute('data-a')));
    tap();
  });
  $('topNote').innerHTML = 'مبلغ را به کارت بالا واریز کنید، بعد این دکمه را بزنید — ' +
    'فاکتور و دکمه «ارسال رسید» داخل ربات می‌آید.<br>حداقل: <b>' + fa(min) + '</b> ' + esc(B.currency);

  var busy = false;
  $('topGo').onclick = function(){
    if (busy) return;
    var amt = intIn($('amt').value);
    if (!amt || amt < min){ warn('حداقل مبلغ ' + fa(min) + ' ' + B.currency + ' است.'); return; }
    busy = true;
    var btn = this, old = btn.textContent;
    btn.disabled = true; btn.textContent = U.sending;
    tap('medium');
    api('topup', { amount: amt }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      $('amt').value = '';
      $('oTtl').textContent = 'درخواست شارژ ثبت شد';
      $('oSub').textContent = j.message || '';
      $('oRef').textContent = j.order || '';
      $('ok').classList.add('on');
      buzz('success');
    }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      warn((j && j.message) ? j.message : 'ثبت درخواست شارژ انجام نشد.');
    });
  };
})();

/* ── میان‌برها ── */
$('hTop').onclick    = function(){ tap(); go('Me'); };
$('hShop').onclick   = function(){ tap(); go('Shop'); };
$('goShop').onclick  = function(){ tap(); S.cat=''; drawFilt(); applyFilter(); go('Shop'); };
$('goShop2').onclick = $('goShop').onclick;
$('credTop').onclick = function(){ tap(); go('Me'); };
$('lnkOrd').onclick  = function(){ tap(); go('Ord'); };
$('lnkShop').onclick = function(){ tap(); go('Shop'); };
$('lnkBot').onclick  = function(){ if (TG) { try{ TG.close(); }catch(e){} } };

/* ── شیت ── */
function open(id){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id) it = B.items[i];
  if (!it) return;
  tap('medium');

  S.item = it;
  S.qty  = (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username')
             ? Math.max(1, it.min || 1) : 1;
  S.vol  = (it.ask === 'volume' && it.vols && it.vols.length) ? it.vols[0].mb : 0;

  $('tIco').textContent  = it.emoji || '⬢';
  $('tName').textContent = it.name;
  $('tDesc').textContent = it.desc || '';
  $('tGo').disabled  = false; $('tGo').textContent  = U.pay_other;
  $('tWal').disabled = false; $('tWal').textContent = U.pay_wallet;

  var f = $('tField'), html = '';
  if (it.stale){
    html += '<div class="in"><div class="tip">⏸ نرخ لحظه‌ای این سرویس الان در دسترس نیست، ' +
            'برای همین فروشش موقتا بسته است.</div></div>';
  }
  var hasQty = it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username';
  if (hasQty){
    var isCoin = it.ask === 'qty_wallet';
    html += '<div class="in"><label>' + (isCoin ? '🔢 مقدار' : '🔢 تعداد') +
              (it.unit ? ' (' + esc(it.unit) + ')' : '') + '</label>' +
            '<div class="pm">' +
              '<button type="button" data-d="-1">−</button>' +
              '<input id="fQty" type="text" inputmode="numeric" value="' + S.qty + '">' +
              '<button type="button" data-d="1">+</button>' +
            '</div>' +
            '<div class="quick2">' +
              (isCoin ? [1,5,10,50].map(function(m){ return '<i data-s="' + m + '">' + m + '</i>'; }).join('')
                      : [1,2,5,10,50].map(function(m){ return '<i data-m="' + m + '">×' + m + '</i>'; }).join('')) +
            '</div>' +
            '<div class="tip">حداقل ' + fa(it.min || 1) +
              (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') +
              (isCoin ? ' · قیمت هر ' + esc(it.unit || 'واحد') + ': ' + fa(it.price) + ' ' + esc(B.currency) : '') +
            '</div></div>';
  }
  if (it.ask === 'qty_username'){
    html += '<div class="in"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="tip">آیدی عمومی حسابی که سرویس روی آن فعال می‌شود.</div></div>';
  }
  if (it.ask === 'qty_wallet'){
    html += '<div class="in"><label>💼 آدرس ولت مقصد</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="tip">آدرس را کامل و بدون فاصله وارد کنید؛ بعد از ارسال برگشت‌پذیر نیست.</div></div>';
  }
  if (it.ask === 'volume'){
    var vs = it.vols || [];
    if (!vs.length){
      html += '<div class="in"><label>📦 حجم</label>' +
              '<div class="tip">الان هیچ حجمی در مخزن موجود نیست. کمی بعد دوباره سر بزنید.</div></div>';
    } else {
      html += '<div class="in"><label>📦 حجم سرویس</label><div class="vols">' +
              vs.map(function(v){
                return '<i class="' + (v.mb === S.vol ? 'on' : '') + '" data-v="' + v.mb + '">' +
                       '<b>' + esc(v.label) + '</b><u>' + fa(v.price) + '</u>' +
                       '<s>' + fa(v.n) + ' موجود</s></i>';
              }).join('') + '</div>' +
              '<div class="tip">فقط حجم‌های رند: ۵۰۰ مگابایت، یا گیگابایت کامل.</div></div>';
    }
  }
  if (it.ask === 'username'){
    html += '<div class="in"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="tip">آیدی عمومی حسابی که سرویس روی آن فعال می‌شود.</div></div>';
  }
  if (it.ask === 'wallet'){
    html += '<div class="in"><label>💼 آدرس ولت</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="tip">آدرس را کامل و بدون فاصله وارد کنید.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="in"><label>✍️ توضیح سفارش</label>' +
            '<textarea id="fTxt" maxlength="300" placeholder="توضیح سفارش، لوکیشن دلخواه، تعداد کاربر…"></textarea></div>';
  }
  f.innerHTML = html;

  if (it.ask === 'volume'){
    f.addEventListener('click', function(ev){
      var el = ev.target.closest ? ev.target.closest('i[data-v]') : null;
      if (!el) return;
      S.vol = Number(el.getAttribute('data-v')) || 0;
      var all = f.querySelectorAll('i[data-v]');
      for (var k = 0; k < all.length; k++)
        all[k].classList.toggle('on', Number(all[k].getAttribute('data-v')) === S.vol);
      tap(); total();
    });
  }
  if (hasQty){
    f.addEventListener('click', function(ev){
      var b = ev.target;
      if (b.hasAttribute && b.hasAttribute('data-d')){ setQty(S.qty + Number(b.getAttribute('data-d'))); tap(); }
      if (b.hasAttribute && b.hasAttribute('data-m')){ setQty(S.qty * Number(b.getAttribute('data-m'))); tap(); }
      if (b.hasAttribute && b.hasAttribute('data-s')){ setQty(Number(b.getAttribute('data-s'))); tap(); }
    });
    $('fQty').addEventListener('input', function(){
      var raw = digits(this.value) || this.value.replace(/[^\d.]/g,'');
      setQty(parseFloat(raw) || 0, true);
    });
  }
  total();
  var noVol = it.ask === 'volume' && !(it.vols && it.vols.length);
  if (it.stale || noVol){ $('tGo').disabled = true; $('tWal').disabled = true; }

  $('mask').classList.add('on');
  $('term').classList.add('on');
  backBtn();
}

function setQty(v, typing){
  var it = S.item; if (!it) return;
  v = Number(v) || 0;
  v = it.ask === 'qty_wallet' ? Math.round(v * 10000) / 10000 : Math.floor(v);
  if (!isFinite(v) || v < 0) v = 0;
  if (!typing){
    if (v < (it.min || 1)) v = it.min || 1;
    if (it.max > 0 && v > it.max) v = it.max;
  }
  S.qty = v;
  var qi = $('fQty');
  if (qi && !typing) qi.value = v;
  total();
}

function sum(){
  var it = S.item; if (!it) return 0;
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username')
    return Math.round(it.price * Math.max(0, S.qty));
  if (it.ask === 'volume'){
    var vs = it.vols || [];
    for (var i = 0; i < vs.length; i++) if (vs[i].mb === S.vol) return vs[i].price;
    return 0;
  }
  return it.price;
}
function total(){
  $('tSum').textContent = fa(sum()) + ' ' + B.currency;
  walletState();
}
function walletState(){
  var it = S.item; if (!it) return;
  var t = sum(), enough = S.bal >= t;
  var noVol = it.ask === 'volume' && !(it.vols && it.vols.length);
  $('tWal').disabled = !enough || !!it.stale || noVol;
  $('tWalNote').innerHTML = enough
    ? '👛 اعتبار شما: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · بعد از پرداخت: <b>' + fa(S.bal - t) + '</b>'
    : '⚠️ ' + esc(U.low_bal) + ' — اعتبار: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · کسری: <b>' + fa(t - S.bal) + '</b><br>' + esc(U.topup_hint);
}

function shut(){
  $('mask').classList.remove('on');
  $('term').classList.remove('on');
  S.item = null;
  backBtn();
}
$('mask').onclick = shut;
$('tNo').onclick  = function(){ tap(); shut(); };

function validate(){
  var it = S.item, fv = '';
  var fx = $('fTxt');
  if (fx) fv = fx.value.trim();
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username'){
    if (!S.qty || S.qty < (it.min || 1)) { warn('حداقل مقدار ' + fa(it.min || 1) + ' است.'); return null; }
    if (it.max > 0 && S.qty > it.max)    { warn('حداکثر مقدار ' + fa(it.max) + ' است.'); return null; }
  }
  if (it.ask === 'volume'){
    if (!S.vol){ warn('یک حجم انتخاب کنید.'); return null; }
    if (S.vol !== 500 && S.vol % 1024 !== 0){ warn('حجم باید رند باشد: ۵۰۰ مگابایت یا گیگابایت کامل.'); return null; }
  }
  if (['username','wallet','qty_wallet','qty_username','text'].indexOf(it.ask) >= 0 && !fv){
    warn('لطفا فیلد بالا را پر کنید.'); return null;
  }
  return fv;
}

function send(payMode, btn){
  if (S.busy || !S.item) return;
  var fv = validate();
  if (fv === null) return;
  var it = S.item;

  S.busy = true;
  btn.disabled = true;
  var old = btn.textContent;
  btn.textContent = U.sending;
  tap('medium');

  api('order', { item: it.id, qty: S.qty, volume: S.vol, field: fv, seen_price: it.price, pay: payMode },
    function(j){
      S.busy = false;
      btn.disabled = false; btn.textContent = old;
      if (typeof j.balance === 'number') setBal(j.balance);
      shut();
      $('oRef').textContent = j.order || '';
      $('oSub').textContent = j.message || U.done_sub;
      $('oTtl').textContent = j.paid ? U.paid_ok : U.done;
      $('ok').classList.add('on');
      buzz('success');
      api('me', {}, function(m){ S.me = m; setBal(m.balance); }, function(){});
    },
    function(j){
      S.busy = false;
      btn.disabled = false;
      btn.textContent = old;
      if (j && j.error === 'price_changed' && j.price){
        it.price = j.price;
        total();
        var node = S.nodes[B.items.indexOf(it)];
        if (node){
          var c = node.querySelector('.cost b');
          if (c) c.textContent = fa(j.price);
        }
      }
      if (j && j.error === 'no_balance'){ shut(); }
      warn((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
    });
}
$('tWal').onclick = function(){ send('wallet', this); };
$('tGo').onclick  = function(){ send('',       this); };

$('oGo').onclick   = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };
$('oBack').onclick = function(){ $('ok').classList.remove('on'); tap(); go('Shop'); };

drawFilt();
buildDeck();
applyFilter();
buildHot();
buildTicks();
go(0, true);
})();
</script>
</body>
</html>
HTML;
}
