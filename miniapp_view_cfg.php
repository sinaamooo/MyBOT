<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ» — تم «کریستال» (Crystal)
 *
 * بازطراحی کامل: کارت‌های شیشه‌ای واقعی با لبه نورانی، هاله رنگی پشت هر کارت،
 * نوار وضعیت زنده، و پرداخت مستقیم از کیف پول بدون خروج از مینی‌اپ.
 *
 * سرعت همچنان اولویت است:
 *   • شیشه با لایه‌های نیمه‌شفاف و لبه گرادیانی ساخته می‌شود، نه backdrop-filter
 *     روی تک‌تک کارت‌ها (که روی موبایل اسکرول را می‌کشد)
 *   • لیست یک بار ساخته می‌شود؛ فیلتر فقط کلاس عوض می‌کند
 *   • انیمیشن ورود فقط بار اول؛ سه سطح افکت
 */

function maViewCfg($a, $boot) {
    $th = $a['theme'] ?? [];
    return strtr(maTplCfg(), [
        '__C1__'    => $th['c1'] ?? '#00FF9C',
        '__C2__'    => $th['c2'] ?? '#00B3FF',
        '__C3__'    => $th['c3'] ?? '#FF2E97',
        '__BG__'    => $th['bg'] ?? '#04070A',
        '__FX__'    => (string)maFxLevel($th),
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/** سطح افکت: ۲ کامل · ۱ سبک · ۰ خاموش */
function maFxLevel($th) {
    if (isset($th['fx'])) return max(0, min(2, (int)$th['fx']));
    return !empty($th['glow']) ? 2 : 1;
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#EAFBF5; --dim:#7FA79C;
  --glass:rgba(255,255,255,.055);
  --glass2:rgba(255,255,255,.085);
  --edge:rgba(255,255,255,.14);
  --safe:env(safe-area-inset-bottom,0px);
  --mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
  --r:20px;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,system-ui,-apple-system,Tahoma,sans-serif;
  overflow-x:hidden;-webkit-font-smoothing:antialiased;overscroll-behavior-y:contain;
}

/* ═══ پس‌زمینه کریستالی — گرادیان ثابت، بدون filter و بدون انیمیشن سنگین ═══ */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(60vw 55vw at 82% -8%, color-mix(in srgb,var(--c1) 34%,transparent), transparent 66%),
    radial-gradient(55vw 50vw at 4% 30%,  color-mix(in srgb,var(--c2) 30%,transparent), transparent 64%),
    radial-gradient(50vw 45vw at 70% 108%,color-mix(in srgb,var(--c3) 24%,transparent), transparent 62%)}
/* شبکه ظریف روی پس‌زمینه — حس سطح شیشه */
.bg:before{content:"";position:absolute;inset:0;opacity:.5;
  background-image:
    repeating-linear-gradient(90deg,rgba(255,255,255,.028) 0 1px,transparent 1px 46px),
    repeating-linear-gradient(0deg, rgba(255,255,255,.028) 0 1px,transparent 1px 46px)}
/* درخشش نرمِ در حال نفس کشیدن — فقط opacity، روی GPU */
.bg:after{content:"";position:absolute;inset:0;opacity:0;
  background:radial-gradient(46vw 46vw at 26% 8%,color-mix(in srgb,var(--c2) 22%,transparent),transparent 60%)}
body.fx2 .bg:after{animation:breathe 10s ease-in-out infinite}
@keyframes breathe{0%,100%{opacity:0}50%{opacity:.9}}
.vig{position:fixed;inset:0;z-index:1;pointer-events:none;
  background:radial-gradient(130% 78% at 50% 0%,transparent 34%,var(--bg) 96%)}
@media (prefers-reduced-motion:reduce){ .bg:after{animation:none!important} }

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 15px calc(106px + var(--safe))}

/* ═══ سطح شیشه — پایه همه کارت‌ها ═══
   لبه نورانی با border-image ساخته می‌شود که برخلاف backdrop-filter
   هیچ هزینه‌ای موقع اسکرول ندارد. */
.pane{position:relative;border-radius:var(--r);
  background:linear-gradient(160deg,var(--glass2),var(--glass) 46%,rgba(255,255,255,.03));
  border:1px solid var(--edge);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.16), 0 10px 30px -18px #000}
/* برق مورب بالای شیشه */
.pane:before{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:linear-gradient(115deg,rgba(255,255,255,.15) 0%,transparent 34%)}

/* ═══ نوار وضعیت ═══ */
.hud{display:flex;align-items:center;gap:10px;padding:10px 14px;margin:12px 0 14px;border-radius:15px}
.led{width:8px;height:8px;flex:0 0 auto;border-radius:50%;background:var(--c1);position:relative}
body.fx2 .led{box-shadow:0 0 10px var(--c1);animation:blink 2.2s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
.hud .sys{font-family:var(--mono);font-size:10px;color:var(--c1);letter-spacing:.6px;direction:ltr;position:relative}
.hud .sp{flex:1}
.hud .clk{font-family:var(--mono);font-size:10.5px;color:var(--dim);direction:ltr;position:relative}

/* ═══ سربرگ ═══ */
.head{margin-bottom:15px;position:relative}
.head h1{margin:0;font-size:25px;font-weight:900;letter-spacing:-.6px;line-height:1.3}
.head h1 em{font-style:normal;
  background:linear-gradient(92deg,var(--c1),var(--c2));
  -webkit-background-clip:text;background-clip:text;color:transparent}
body.fx2 .head h1{text-shadow:0 2px 26px color-mix(in srgb,var(--c1) 26%,transparent)}
.head p{margin:6px 0 0;font-size:12px;color:var(--dim)}

/* ═══ کیف پول ═══ */
.credit{padding:16px 18px;margin-bottom:12px;overflow:hidden}
.credit .k{position:relative;font-size:11px;color:var(--dim);font-family:var(--mono);letter-spacing:.4px;margin-bottom:7px}
.credit .v{position:relative;font-family:var(--mono);font-size:27px;font-weight:700;
  direction:ltr;text-align:right;letter-spacing:-.6px;
  background:linear-gradient(92deg,var(--c1),#fff 78%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.credit .u{font-family:Vazirmatn,sans-serif;font-size:12px;color:var(--dim);margin-inline-start:6px;
  -webkit-text-fill-color:var(--dim)}
.credit .bar{position:relative;height:2px;margin-top:12px;border-radius:2px;overflow:hidden;
  background:rgba(255,255,255,.07)}
.credit .bar i{position:absolute;inset:0;width:42%;border-radius:2px;
  background:linear-gradient(90deg,var(--c1),var(--c2))}

.brief{padding:12px 14px;margin-bottom:15px;border-radius:14px;font-size:12px;line-height:1.95;color:#B6D8CD;
  border:1px solid var(--edge);border-inline-start:2px solid var(--c2);
  background:linear-gradient(90deg,color-mix(in srgb,var(--c2) 11%,transparent),rgba(255,255,255,.02))}

/* ═══ جستجو ═══ */
.seek{position:relative;margin-bottom:13px}
.seek input{width:100%;padding:13px 40px 13px 14px;border-radius:14px;
  border:1px solid var(--edge);background:rgba(255,255,255,.045);
  color:var(--ink);font-family:inherit;font-size:13px;outline:none;transition:border-color .18s}
.seek input::placeholder{color:var(--dim)}
.seek input:focus{border-color:color-mix(in srgb,var(--c1) 60%,transparent)}
.seek span{position:absolute;top:50%;right:14px;transform:translateY(-50%);color:var(--c1);font-size:14px}

/* ═══ کارت خوش‌آمد + لوگوی زنده ═══ */
.welcome{padding:24px 18px;margin-bottom:15px;text-align:center;overflow:hidden}
.logo{width:92px;height:92px;margin:0 auto 15px;position:relative}
.logo svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.logo i{position:absolute;inset:-7px;border-radius:22px;
  border:1.5px dashed color-mix(in srgb,var(--c2) 50%,transparent)}
.logo i:nth-child(2){inset:3px;border-radius:18px;border-style:solid;
  border-color:color-mix(in srgb,var(--c1) 38%,transparent)}
body.fx2 .logo i{animation:spin 16s linear infinite}
body.fx2 .logo i:nth-child(2){animation:spin 10s linear infinite reverse}
@keyframes spin{to{transform:rotate(360deg)}}
body.fx2 .logo svg{animation:float 5s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.logo .halo{position:absolute;inset:-20px;border-radius:50%;z-index:0;
  background:radial-gradient(circle,color-mix(in srgb,var(--c1) 40%,transparent),transparent 68%)}
body.fx2 .logo .halo{animation:glow 3.8s ease-in-out infinite}
@keyframes glow{0%,100%{opacity:.45;transform:scale(.93)}50%{opacity:1;transform:scale(1.09)}}
@media (prefers-reduced-motion:reduce){
  body .logo i,body .logo svg,body .logo .halo{animation:none!important}
}
.welcome h2{position:relative;margin:0 0 9px;font-size:20px;font-weight:900;letter-spacing:-.4px;
  background:linear-gradient(92deg,var(--c1),#fff 82%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.welcome p{position:relative;margin:0;font-size:12.5px;line-height:1.95;color:#B6D8CD}

/* ═══ نوار دسته‌ها — پایین صفحه ═══ */
.nav{position:fixed;left:0;right:0;bottom:0;z-index:30;display:flex;gap:5px;
  padding:8px 9px calc(8px + var(--safe));
  background:rgba(5,13,11,.95);border-top:1px solid var(--edge)}
.nav b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:9px 3px;border-radius:15px;cursor:pointer;color:var(--dim);
  font-size:10px;font-weight:700;transition:color .16s,background .16s}
.nav b .ico-em{font-size:19px;font-style:normal;line-height:1}
.nav b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav b.on{color:#04120D;background:linear-gradient(135deg,var(--c1),var(--c2))}
.nav b.on .ico-em{transform:scale(1.1)}

/* ═══ آیکون شیشه‌ای متحرک دسته‌ها ═══
   یک قاب شیشه با برقی که رد می‌شود، و آیکون SVG که خودش جان دارد.
   همه‌ی حرکت‌ها با transform/opacity است تا روی موبایل هم روان بماند. */
.ico{position:relative;width:36px;height:36px;border-radius:13px;display:grid;place-items:center;
  background:linear-gradient(158deg,rgba(255,255,255,.15),rgba(255,255,255,.03));
  border:1px solid rgba(255,255,255,.16);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.26),inset 0 -6px 12px rgba(0,0,0,.18),0 4px 12px rgba(0,0,0,.28);
  overflow:hidden;will-change:transform;
  transition:transform .26s cubic-bezier(.34,1.56,.64,1),box-shadow .26s,border-color .26s}
/* برقِ شیشه — فقط روی دسته‌ی فعال می‌دود */
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(115deg,transparent 41%,rgba(255,255,255,.55) 50%,transparent 59%);
  transform:translateX(-130%)}
.ico:after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:radial-gradient(120% 70% at 50% -10%,rgba(255,255,255,.3),transparent 60%)}
.ico svg{position:relative;width:20px;height:20px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}

.nav b.on .ico{transform:translateY(-4px) scale(1.08);
  border-color:rgba(255,255,255,.42);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.5),0 8px 20px rgba(0,0,0,.4)}
.nav b.on .ico:before{animation:icoSheen 2.8s cubic-bezier(.4,0,.2,1) infinite}
@keyframes icoSheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}

/* جانِ خودِ آیکون‌ها */
.i-spin  {transform-box:fill-box;transform-origin:50% 50%}
.nav b.on .i-spin  {animation:icoSpin 5.5s linear infinite}
.nav b.on .i-pulse {animation:icoPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.nav b.on .i-float {animation:icoFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.nav b.on .i-lid   {animation:icoLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
.nav b.on .i-draw  {stroke-dasharray:64;animation:icoDraw 2.6s ease-in-out infinite}
.nav b.on .i-tick  {animation:icoTick 4s steps(12) infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes icoSpin {to{transform:rotate(360deg)}}
@keyframes icoPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.75}}
@keyframes icoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes icoLid  {0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@keyframes icoDraw {0%{stroke-dashoffset:64}45%,100%{stroke-dashoffset:0}}
@keyframes icoTick {to{transform:rotate(360deg)}}

@media (prefers-reduced-motion:reduce){
  .ico,.ico:before,.nav b.on .ico:before,
  .nav b.on .i-spin,.nav b.on .i-pulse,.nav b.on .i-float,
  .nav b.on .i-lid,.nav b.on .i-draw,.nav b.on .i-tick{animation:none!important;transition:none!important}
}


/* ═══ انتخاب حجم — چیپ‌های شیشه‌ای ═══ */
.vols{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px;margin-top:6px}
.vols i{position:relative;display:flex;flex-direction:column;align-items:center;gap:2px;
  padding:10px 6px;border-radius:14px;cursor:pointer;font-style:normal;overflow:hidden;
  border:1px solid var(--edge);
  background:linear-gradient(158deg,rgba(255,255,255,.09),rgba(255,255,255,.02));
  box-shadow:inset 0 1px 0 rgba(255,255,255,.16);
  transition:transform .18s cubic-bezier(.34,1.56,.64,1),border-color .18s,background .18s}
.vols i:active{transform:scale(.96)}
.vols i b{font-size:12.5px;font-weight:800;color:var(--ink)}
.vols i u{font-size:11px;text-decoration:none;color:var(--c1);font-weight:700}
.vols i s{font-size:9.5px;text-decoration:none;color:var(--dim)}
.vols i.on{border-color:color-mix(in srgb,var(--c1) 65%,transparent);
  background:linear-gradient(158deg,color-mix(in srgb,var(--c1) 26%,transparent),rgba(255,255,255,.04));
  box-shadow:inset 0 1px 0 rgba(255,255,255,.3),0 6px 16px rgba(0,0,0,.32)}
.vols i.on b{color:#fff}
.vols i.on u{color:#fff}

/* ═══ کارت محصول — شیشه واقعی ═══ */
.list{display:grid;gap:13px}
.node{padding:16px;cursor:pointer;overflow:hidden;contain:content}
/* هاله رنگی که از گوشه کارت می‌تابد */
.node:after{content:"";position:absolute;width:150px;height:150px;top:-70px;left:-50px;border-radius:50%;
  background:radial-gradient(circle,color-mix(in srgb,var(--c1) 30%,transparent),transparent 70%);
  pointer-events:none}
.node.vip:after{background:radial-gradient(circle,color-mix(in srgb,var(--c3) 32%,transparent),transparent 70%)}
.node.hide{display:none}
.node:active{transform:scale(.987);border-color:color-mix(in srgb,var(--c1) 55%,transparent)}
.first .node{animation:rise .4s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .first .node{animation:none} }

.nrow{position:relative;display:flex;align-items:flex-start;gap:13px}
.ico{width:52px;height:52px;flex:0 0 auto;display:grid;place-items:center;font-size:25px;border-radius:16px;
  border:1px solid var(--edge);
  background:linear-gradient(150deg,rgba(255,255,255,.14),rgba(255,255,255,.03))}
body.fx2 .ico{box-shadow:inset 0 1px 0 rgba(255,255,255,.2)}
.nbody{flex:1;min-width:0}
.nbody h3{margin:0;font-size:15px;font-weight:800;display:flex;align-items:center;gap:7px;flex-wrap:wrap;line-height:1.5}
.nbody p{margin:5px 0 0;font-size:11.5px;color:var(--dim);line-height:1.8}
.flag{font-size:9px;font-weight:800;padding:3px 8px;border-radius:8px;color:#04120D;
  background:linear-gradient(135deg,var(--c3),var(--c1))}
.dot{font-family:var(--mono);font-size:8.5px;font-weight:700;padding:3px 7px;border-radius:7px;
  color:#04120D;background:var(--c2)}

.nfoot{position:relative;display:flex;align-items:center;justify-content:space-between;gap:10px;
  margin-top:14px;padding-top:13px;border-top:1px solid rgba(255,255,255,.07)}
.cost{font-family:var(--mono);font-size:17px;font-weight:700;direction:ltr;
  background:linear-gradient(92deg,var(--c1),var(--c2));
  -webkit-background-clip:text;background-clip:text;color:transparent}
.cost em{font-family:Vazirmatn,sans-serif;font-style:normal;font-size:10.5px;color:var(--dim);
  margin-inline-start:5px;-webkit-text-fill-color:var(--dim)}
.take{padding:9px 17px;border-radius:12px;font-size:12px;font-weight:800;color:#04120D;border:0;cursor:pointer;
  background:linear-gradient(135deg,var(--c1),var(--c2))}
body.fx2 .take{box-shadow:0 8px 18px -9px var(--c1)}

.none{text-align:center;padding:46px 18px;color:var(--dim);font-size:12.5px}
.none div{font-size:42px;margin-bottom:12px;opacity:.4}
.load{height:104px;border-radius:var(--r);border:1px solid var(--edge);background:var(--glass)}
body.fx2 .load{animation:pulse 1.3s ease-in-out infinite alternate}
@keyframes pulse{to{opacity:.45}}

/* ═══ شیت خرید ═══ */
.mask{position:fixed;inset:0;z-index:40;background:rgba(2,8,6,.86);
  opacity:0;pointer-events:none;transition:opacity .22s}
.mask.on{opacity:1;pointer-events:auto}
.term{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translate3d(0,102%,0);
  transition:transform .32s cubic-bezier(.2,.9,.25,1);max-height:93vh;overflow-y:auto;
  background:linear-gradient(180deg,#0C1E19,#050D0B);
  border-radius:26px 26px 0 0;border-top:1px solid var(--edge);
  padding:0 0 calc(20px + var(--safe));-webkit-overflow-scrolling:touch;will-change:transform}
.term.on{transform:translate3d(0,0,0)}
.grip{width:40px;height:4px;border-radius:4px;background:rgba(255,255,255,.2);margin:10px auto 4px}
.pad{padding:12px 16px 0}

.tHead{display:flex;align-items:center;gap:13px;margin-bottom:17px}
.tHead .ico{width:58px;height:58px;font-size:28px}
.tHead h2{margin:0;font-size:17px;font-weight:900;line-height:1.5}
.tHead p{margin:5px 0 0;font-size:11.5px;color:var(--dim);line-height:1.8}

.in{margin-bottom:14px}
.in label{display:block;font-size:11.5px;font-weight:700;color:var(--dim);margin-bottom:8px}
.in input,.in textarea{width:100%;padding:14px;border-radius:14px;border:1px solid var(--edge);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:14px;outline:none;
  transition:border-color .18s}
.in textarea{min-height:80px;resize:vertical;font-size:13px}
.in input:focus,.in textarea:focus{border-color:color-mix(in srgb,var(--c1) 60%,transparent)}
.in .tip{font-size:10.5px;color:var(--dim);margin-top:7px;line-height:1.8}

.pm{display:flex;gap:10px;align-items:center}
.pm button{width:46px;height:48px;flex:0 0 auto;border-radius:14px;border:1px solid var(--edge);
  background:rgba(255,255,255,.06);color:var(--c1);font-size:20px;font-weight:700;cursor:pointer}
.pm button:active{background:color-mix(in srgb,var(--c1) 26%,transparent);color:#fff}
.pm input{text-align:center;font-weight:800;font-size:17px;font-family:var(--mono)}
.quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.quick i{padding:6px 13px;border-radius:10px;font-family:var(--mono);font-size:11px;font-style:normal;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.045);color:var(--dim)}
.quick i:active{background:color-mix(in srgb,var(--c1) 28%,transparent);color:#fff}

.sum{margin:16px 0;padding:15px 17px;border-radius:17px;display:flex;justify-content:space-between;align-items:center;
  border:1px solid color-mix(in srgb,var(--c1) 45%,transparent);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 15%,transparent),color-mix(in srgb,var(--c2) 8%,transparent))}
.sum span{font-size:12px;color:var(--dim)}
.sum b{font-family:var(--mono);font-size:21px;font-weight:700;direction:ltr;
  background:linear-gradient(92deg,var(--c1),#fff);-webkit-background-clip:text;background-clip:text;color:transparent}

.exec{width:100%;padding:16px;border:0;border-radius:17px;cursor:pointer;
  font-family:inherit;font-size:15px;font-weight:900;color:#04120D;
  background:linear-gradient(135deg,var(--c1),var(--c2))}
body.fx2 .exec{box-shadow:0 14px 30px -14px var(--c1)}
.exec:active{transform:scale(.986)}
/* غیرفعال باید کاملا واضح باشد — گرادیان روشن، کاربر را گمراه می‌کرد */
.exec[disabled]{cursor:default;color:var(--dim);background:rgba(255,255,255,.05);
  border:1px solid var(--edge);box-shadow:none}
.exec.alt{margin-top:9px;color:var(--ink);background:rgba(255,255,255,.07);border:1px solid var(--edge);
  box-shadow:none;font-weight:700;font-size:13.5px}
.abort{width:100%;margin-top:9px;padding:13px;border-radius:14px;border:1px solid var(--edge);
  background:transparent;color:var(--dim);font-family:inherit;font-size:13px;cursor:pointer}
.walbox{margin-top:10px;padding:11px 14px;border-radius:13px;font-size:11.5px;line-height:1.8;
  border:1px solid var(--edge);background:rgba(255,255,255,.035);color:var(--dim)}
.walbox b{color:var(--c1)}

/* ═══ موفقیت ═══ */
.ok{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:28px;
  background-color:var(--bg);
  background-image:radial-gradient(72% 52% at 50% 42%,color-mix(in srgb,var(--c1) 24%,transparent),transparent 76%)}
.ok.on{display:grid}
.box{width:108px;height:108px;margin:0 auto 24px;display:grid;place-items:center;font-size:48px;color:#04120D;
  border-radius:34px;background:linear-gradient(135deg,var(--c1),var(--c2))}
body.fx2 .box{box-shadow:0 20px 50px -18px var(--c1);animation:zoom .45s cubic-bezier(.2,1.4,.4,1) backwards}
@keyframes zoom{from{transform:scale(.5);opacity:0}to{transform:none;opacity:1}}
.ok h2{margin:0 0 10px;font-size:21px;font-weight:900}
.ok p{margin:0 0 22px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.ok .ref{font-family:var(--mono);font-size:11.5px;padding:10px 16px;margin-bottom:22px;direction:ltr;
  border-radius:12px;border:1px solid var(--edge);background:rgba(255,255,255,.04);color:var(--c1)}

/* ═══ هشدار ═══ */
.warn{position:fixed;top:12px;left:50%;transform:translate(-50%,-160%);z-index:80;max-width:88vw;
  padding:13px 18px;border-radius:15px;font-size:12.5px;font-weight:700;text-align:center;line-height:1.75;
  color:#fff;background:linear-gradient(135deg,var(--c3),#B1004B);
  transition:transform .3s cubic-bezier(.2,1.3,.4,1);box-shadow:0 14px 32px -14px var(--c3)}
.warn.on{transform:translate(-50%,0)}
.warn.good{background:linear-gradient(135deg,var(--c1),var(--c2));color:#04120D}
</style>
</head>
<body>
<div class="bg"></div><div class="vig"></div>

<div class="wrap">
  <div class="hud pane">
    <span class="led"></span>
    <span class="sys">SYSTEM ONLINE</span>
    <span class="sp"></span>
    <span class="clk" id="clk">--:--:--</span>
  </div>

  <div class="head">
    <h1 id="ttl">—</h1>
    <p id="sub"></p>
  </div>

  <div class="credit pane">
    <div class="k" id="balLbl">CREDIT</div>
    <div class="v"><span id="bal">…</span><span class="u" id="cur"></span></div>
    <div class="bar"><i></i></div>
  </div>

  <div class="welcome pane">
    <div class="logo">
      <span class="halo"></span><i></i><i></i>
      <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
        <defs>
          <linearGradient id="lg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="var(--c1)"/><stop offset="1" stop-color="var(--c2)"/>
          </linearGradient>
        </defs>
        <path d="M50 10 L84 24 V52 C84 71 69 84 50 91 C31 84 16 71 16 52 V24 Z"
              fill="url(#lg)" stroke="rgba(255,255,255,.35)" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M35 51 L46 62 L67 40" stroke="rgba(255,255,255,.95)" stroke-width="6"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h2 id="wcTtl">—</h2>
    <p id="hero"></p>
  </div>

  <div class="seek"><input id="q" placeholder="جستجو…" autocomplete="off" spellcheck="false"><span>⌕</span></div>
  <div class="list" id="list">
    <div class="load"></div><div class="load"></div><div class="load"></div>
  </div>
</div>

<nav class="nav" id="nav"></nav>

<div class="mask" id="mask"></div>
<div class="term" id="term">
  <div class="grip"></div>
  <div class="pad">
    <div class="tHead">
      <div class="ico" id="tIco">💠</div>
      <div style="flex:1;min-width:0"><h2 id="tName">—</h2><p id="tDesc"></p></div>
    </div>
    <div id="tField"></div>
    <div class="sum"><span>مبلغ قابل پرداخت</span><b id="tSum">0</b></div>
    <button class="exec" id="tWal">پرداخت از کیف پول</button>
    <button class="exec alt" id="tGo">روش‌های دیگر پرداخت</button>
    <div class="walbox" id="tWalNote"></div>
    <button class="abort" id="tNo">بستن</button>
  </div>
</div>

<div class="ok" id="ok">
  <div>
    <div class="box">✓</div>
    <h2 id="oTtl">ثبت شد</h2>
    <p id="oSub"></p>
    <div class="ref" id="oRef"></div>
    <button class="exec" id="oGo" style="max-width:280px">بازگشت به ربات</button>
  </div>
</div>

<div class="warn" id="warn"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var FX = __FX__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };

document.body.className = 'fx' + FX;

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

(function(){
  var t = null;
  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function tick(){
    var d = new Date();
    $('clk').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  function start(){ if (!t) { tick(); t = setInterval(tick, 1000); } }
  function stop(){ if (t) { clearInterval(t); t = null; } }
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
  start();
})();

function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}
function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

$('ttl').innerHTML     = esc(B.title).replace(/\s(\S+)$/, ' <em>$1</em>');
$('sub').textContent   = B.sub || '';
$('hero').textContent  = B.hero || '';
$('wcTtl').textContent = B.title;
$('cur').textContent   = B.currency;
$('balLbl').textContent= B.ui.balance;
$('q').placeholder     = B.ui.search;
$('tWal').textContent  = B.ui.pay_wallet;
$('tGo').textContent   = B.ui.pay_other;
$('tNo').textContent   = B.ui.close;
$('oTtl').textContent  = B.ui.done;
$('oSub').textContent  = B.ui.done_sub;
document.title = B.title;

var S = { cat:(B.cats[0] ? B.cats[0].id : ''), q:'', item:null, qty:1, busy:false, bal:0, nodes:[] };

function api(action, extra, ok, bad){
  var body = Object.assign({ action:action, app:B.app,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  var ctl = null, timer = null;
  try { ctl = new AbortController(); timer = setTimeout(function(){ ctl.abort(); }, 25000); } catch(e){}

  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined,
    cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){
    return r.json().catch(function(){ return { ok:false, message:'پاسخ سرور نامعتبر بود.' }; });
  }).then(function(j){
    if (timer) clearTimeout(timer);
    if (j && j.ok) ok(j); else bad(j || {});
  }).catch(function(){
    if (timer) clearTimeout(timer);
    bad({ message:'ارتباط با سرور برقرار نشد.' });
  });
}

var warnT;
function warn(m, good){
  var w = $('warn');
  w.textContent = m;
  w.className = 'warn on' + (good ? ' good' : '');
  clearTimeout(warnT); warnT = setTimeout(function(){ w.className = 'warn'; }, 3800);
  buzz(good ? 'success' : 'error');
}

function setBal(v){
  S.bal = Number(v) || 0;
  $('bal').textContent = fa(S.bal);
  if (S.item) walletState();
}

api('me', {}, function(j){ setBal(j.balance); }, function(j){
  setBal(0);                        // «—» گیج‌کننده بود؛ صفر یعنی صفر
  if (j && j.message) warn(j.message);
});


/* ── آیکون شیشه‌ای هر دسته ──
   با کلیدواژه‌ی داخل شناسه/نام دسته تشخیص داده می‌شود؛ اگر هیچ‌کدام
   نخواند، همان ایموجی خود دسته می‌نشیند. پس دسته‌ی تازه هم بی‌آیکون نمی‌ماند. */
var ICONS = {
  star:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5 6.1 20.6l1.2-6.5L2.5 9.5l6.6-.9z"/></svg>',
  crown: '<svg viewBox="0 0 24 24"><path class="fl i-float" d="M3 8.4l4.2 3.2L12 4.6l4.8 7 4.2-3.2-1.7 9.6H4.7z"/><rect class="fl" x="4.4" y="19" width="15.2" height="2.1" rx="1"/></svg>',
  gift:  '<svg viewBox="0 0 24 24"><rect x="3.4" y="9.8" width="17.2" height="10.8" rx="2"/><path class="i-lid" d="M2.4 6.4h19.2v3.6H2.4z"/><path d="M12 6.4v14.2"/><path class="i-lid" d="M12 6.4C10.6 3 6.6 3.4 7.2 6.4M12 6.4c1.4-3.4 5.4-3 4.8 0"/></svg>',
  gem:   '<svg viewBox="0 0 24 24"><g class="i-spin"><path d="M4 9.2L12 21l8-11.8L16.6 3H7.4z"/><path d="M4 9.2h16M9.2 9.2L12 21l2.8-11.8M7.4 3l1.8 6.2M16.6 3l-1.8 6.2"/></g></svg>',
  tri:   '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 3.2l9 17.6H3z" opacity=".9"/><path d="M12 3.2l9 17.6H3z" stroke="rgba(0,0,0,.35)"/></svg>',
  bolt:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.4 2.2L4.6 13.6h5.4l-.8 8.2 9-11.6h-5.4z"/></svg>',
  shield:'<svg viewBox="0 0 24 24"><path class="i-draw" d="M12 2.6l8 3v6.2c0 5-3.4 8.7-8 9.8-4.6-1.1-8-4.8-8-9.8V5.6z"/><path class="i-draw" d="M8.6 12.2l2.4 2.4 4.6-4.8"/></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><ellipse class="i-pulse" cx="12" cy="12" rx="4" ry="9"/></svg>',
  box:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M12 2.8l8.4 4.4v9.6L12 21.2 3.6 16.8V7.2z"/><path class="i-float" d="M3.6 7.2L12 11.6l8.4-4.4M12 11.6v9.6"/></svg>',
  clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.8"/><path class="i-tick" d="M12 12V6.6"/><path d="M12 12l3.6 2.2"/></svg>',
  inf:   '<svg viewBox="0 0 24 24"><path class="i-draw" d="M8.4 8.2a3.8 3.8 0 100 7.6c3 0 4.2-7.6 7.2-7.6a3.8 3.8 0 110 7.6c-3 0-4.2-7.6-7.2-7.6z"/></svg>',
  bag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M4.4 8h15.2l-1.2 12.4H5.6z"/><path class="i-lid" d="M8.6 8V6.2a3.4 3.4 0 016.8 0V8"/></svg>',
  wallet:'<svg viewBox="0 0 24 24"><rect x="3" y="6.2" width="18" height="13" rx="2.6"/><path d="M3 10.4h18"/><circle class="fl i-pulse" cx="17" cy="14.6" r="1.5"/></svg>',
  fire:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.2 2.4c2.6 3.4 1 5.2 2.6 6.6 1.4 1.2 3.4.4 3.4.4 1.6 4.6-1.4 12.2-7.2 12.2S3.2 15.6 5.6 11c.8 1.6 2.4 2 2.4 2C6.6 8.8 9.6 5 13.2 2.4z"/></svg>',
  tag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M11.4 2.8h9.8v9.8L11.6 22.2 1.8 12.4z"/><circle class="fl i-pulse" cx="17" cy="7" r="1.7"/></svg>',
  coin:  '<svg viewBox="0 0 24 24"><ellipse class="i-pulse" cx="12" cy="12" rx="8.6" ry="8.6"/><path d="M14.6 8.6c-.7-.8-1.7-1.2-2.8-1.2-1.7 0-2.9.9-2.9 2.2 0 3 5.9 1.3 5.9 4.3 0 1.4-1.3 2.3-3 2.3-1.2 0-2.3-.5-3-1.3M12 5.6v12.8"/></svg>',
  swap:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M4 8.6h13.4l-3.2-3.4M20 15.4H6.6l3.2 3.4"/></svg>',
  lock:  '<svg viewBox="0 0 24 24"><rect x="4.4" y="10.2" width="15.2" height="10.6" rx="2.4"/><path class="i-lid" d="M8 10.2V7.6a4 4 0 018 0v2.6"/><circle class="fl i-pulse" cx="12" cy="15.4" r="1.6"/></svg>',
  user:  '<svg viewBox="0 0 24 24"><circle class="i-float" cx="12" cy="8" r="4.2"/><path d="M3.8 21c.6-4.6 4-7 8.2-7s7.6 2.4 8.2 7"/></svg>'
};
var ICO_MAP = [
  [/star|استار|ستار/i,                'star'],
  [/prem|پریم|پرمی/i,                 'crown'],
  [/gift|گیفت|هدیه|teddy|تدی/i,       'gift'],
  [/\bton\b|تون|tonco/i,              'gem'],
  [/trx|tron|ترون|ترکس/i,             'tri'],
  [/usdt|تتر|tether/i,                'wallet'],
  [/vol|حجم|گیگ|گیگا|مگ|giga|\bgb\b/i,'box'],
  [/time|زمان|روز|ماه|month|day/i,    'clock'],
  [/unlim|نامحدود|بی.?نهایت/i,        'inf'],
  [/loc|کشور|لوکیشن|country|سرور/i,   'globe'],
  [/vpn|کانفیگ|config|امن|secure/i,   'shield'],
  [/fast|سریع|توربو|turbo|speed/i,    'bolt'],
  [/off|تخفیف|حراج|discount/i,        'tag'],
  [/hot|داغ|ویژه|vip|special/i,       'fire'],
  [/ارز|currency|exchange|صراف|coin|نرخ/i,  'coin'],
  [/swap|تبدیل|مبادله/i,                    'swap'],
  [/اختصاص|dedicat|private|خصوص|قفل|lock/i, 'lock'],
  [/acc|اکانت|account|عضو|member/i,   'user'],
  [/pack|بسته|فروش|shop|buy/i,        'bag']
];
function icoFor(c){
  var key = String(c.id || '') + ' ' + String(c.name || '');
  for (var i = 0; i < ICO_MAP.length; i++) if (ICO_MAP[i][0].test(key)) return ICONS[ICO_MAP[i][1]];
  return '<span class="ico-em">' + esc(c.emoji || '💠') + '</span>';
}

/* ── نوار دسته‌ها، پایین صفحه ── */
function drawNav(){
  var box = $('nav'), html = '';
  B.cats.forEach(function(c){
    html += '<b class="' + (S.cat===c.id?'on':'') + '" data-c="' + esc(c.id) + '">' +
            '<i class="ico">' + icoFor(c) + '</i><span>' + esc(c.name) + '</span></b>';
  });
  box.innerHTML = html;
}

function buildList(){
  var box = $('list');
  if (!B.items.length){
    box.innerHTML = '<div class="none"><div>◇</div>' + esc(B.ui.empty) + '</div>';
    return;
  }
  var html = '';
  B.items.forEach(function(i, n){
    html += '<div class="node pane' + (i.badge ? ' vip' : '') + '" data-i="' + esc(i.id) + '"' +
            ' style="animation-delay:' + Math.min(n*40, 320) + 'ms">' +
              '<div class="nrow">' +
                '<div class="ico">' + esc(i.emoji || '💠') + '</div>' +
                '<div class="nbody">' +
                  '<h3>' + esc(i.name) +
                    (i.badge ? '<span class="flag">' + esc(i.badge) + '</span>' : '') +
                    (i.live  ? '<span class="dot">LIVE</span>' : '') +
                  '</h3>' +
                  (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
                '</div>' +
              '</div>' +
              '<div class="nfoot">' +
                '<div class="cost">' + fa(i.price) + '<em>' + esc(B.currency) +
                  (i.ask === 'qty' && i.unit ? ' / ' + esc(i.unit) : '') + '</em></div>' +
                '<button class="take" type="button">' + esc(B.ui.buy) + '</button>' +
              '</div>' +
            '</div>';
  });
  box.classList.add('first');
  box.innerHTML = html;
  S.nodes = [].slice.call(box.children);
  setTimeout(function(){ box.classList.remove('first'); }, 700);

  box.addEventListener('click', function(ev){
    var el = ev.target.closest ? ev.target.closest('.node') : null;
    if (el && el.getAttribute) open(el.getAttribute('data-i'));
  });
}

function applyFilter(){
  var q = S.q.trim().toLowerCase(), shown = 0;
  for (var n = 0; n < S.nodes.length; n++){
    var el = S.nodes[n], it = B.items[n];
    // موقع جستجو، دسته نادیده گرفته می‌شود تا کاربر مجبور نباشد
    // اول دسته درست را پیدا کند
    var inCat = q ? true : (!S.cat || it.cat === S.cat);
    var ok = inCat && (!q || (it.name + ' ' + it.desc + ' ' + it.badge).toLowerCase().indexOf(q) >= 0);
    if (ok) { el.classList.remove('hide'); shown++; } else { el.classList.add('hide'); }
  }
  var none = document.getElementById('noneBox');
  if (!shown && !none){
    none = document.createElement('div');
    none.id = 'noneBox'; none.className = 'none';
    none.innerHTML = '<div>◇</div>' + esc(B.ui.empty);
    $('list').appendChild(none);
  } else if (shown && none){ none.remove(); }
}

$('nav').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  S.cat = el.getAttribute('data-c');
  tap(); drawNav(); applyFilter();
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

var qT;
$('q').addEventListener('input', function(){
  var v = this.value;
  clearTimeout(qT);
  qT = setTimeout(function(){ S.q = v; applyFilter(); }, 120);
});

/* ── شیت خرید ── */
function open(id){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id) it = B.items[i];
  if (!it) return;
  tap('medium');

  S.item = it;
  S.qty  = it.ask === 'qty' ? Math.max(1, it.min || 1) : 1;
  S.vol  = (it.ask === 'volume' && it.vols && it.vols.length) ? it.vols[0].mb : 0;

  $('tIco').textContent  = it.emoji || '💠';
  $('tName').textContent = it.name;
  $('tDesc').textContent = it.desc || '';
  $('tGo').disabled = false;  $('tGo').textContent  = B.ui.pay_other;
  $('tWal').disabled = false; $('tWal').textContent = B.ui.pay_wallet;

  var f = $('tField'), html = '';
  if (it.ask === 'qty'){
    html += '<div class="in"><label>🔢 تعداد' + (it.unit ? ' (' + esc(it.unit) + ')' : '') + '</label>' +
            '<div class="pm">' +
              '<button type="button" data-d="-1">−</button>' +
              '<input id="fQty" type="text" inputmode="numeric" value="' + S.qty + '">' +
              '<button type="button" data-d="1">+</button>' +
            '</div>' +
            '<div class="quick">' +
              [1,2,5,10,50].map(function(m){ return '<i data-m="' + m + '">×' + m + '</i>'; }).join('') +
            '</div>' +
            '<div class="tip">حداقل ' + fa(it.min || 1) +
              (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') + '</div></div>';
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
                       '<b>' + esc(v.label) + '</b>' +
                       '<u>' + fa(v.price) + '</u>' +
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
  if (it.ask === 'qty'){
    f.addEventListener('click', function(ev){
      var b = ev.target;
      if (b.hasAttribute && b.hasAttribute('data-d')){ setQty(S.qty + Number(b.getAttribute('data-d'))); tap(); }
      if (b.hasAttribute && b.hasAttribute('data-m')){ setQty(S.qty * Number(b.getAttribute('data-m'))); tap(); }
    });
    $('fQty').addEventListener('input', function(){
      setQty(parseFloat(this.value.replace(/[^\d.]/g,'')) || 0, true);
    });
  }
  total();

  $('mask').classList.add('on');
  $('term').classList.add('on');
  if (TG && TG.BackButton){ try{ TG.BackButton.show(); }catch(e){} }
}

function setQty(v, typing){
  var it = S.item; if (!it) return;
  v = Math.floor(Number(v) || 0);
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
  if (it.ask === 'qty') return it.price * Math.max(0, S.qty);
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

/* دکمه کیف پول فقط وقتی موجودی کافی است فعال می‌شود */
function walletState(){
  var t = sum(), enough = S.bal >= t;
  $('tWal').disabled = !enough;
  $('tWal').style.display = '';
  $('tWalNote').innerHTML = enough
    ? '👛 موجودی شما: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · بعد از پرداخت: <b>' + fa(S.bal - t) + '</b>'
    : '⚠️ ' + esc(B.ui.low_bal) + ' — موجودی: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · کسری: <b>' + fa(t - S.bal) + '</b><br>' + esc(B.ui.topup_hint);
}

function shut(){
  $('mask').classList.remove('on');
  $('term').classList.remove('on');
  S.item = null;
  if (TG && TG.BackButton){ try{ TG.BackButton.hide(); }catch(e){} }
}
$('mask').onclick = shut;
$('tNo').onclick  = function(){ tap(); shut(); };
if (TG && TG.BackButton){ try{ TG.BackButton.onClick(shut); }catch(e){} }

function validate(){
  var it = S.item, fv = '';
  var fx = $('fTxt');
  if (fx) fv = fx.value.trim();

  if (it.ask === 'qty'){
    if (!S.qty || S.qty < (it.min || 1)) { warn('حداقل تعداد ' + fa(it.min || 1) + ' است.'); return null; }
    if (it.max > 0 && S.qty > it.max)    { warn('حداکثر تعداد ' + fa(it.max) + ' است.'); return null; }
  }
  if (it.ask === 'volume'){
    if (!S.vol){ warn('یک حجم انتخاب کنید.'); return null; }
    if (S.vol !== 500 && S.vol % 1024 !== 0){ warn('حجم باید رند باشد: ۵۰۰ مگابایت یا گیگابایت کامل.'); return null; }
  }
  if ((it.ask === 'username' || it.ask === 'wallet' || it.ask === 'text') && !fv){
    warn('لطفا فیلد بالا را پر کنید.'); return null;
  }
  return fv;
}

function send(payMode, btn, busyText){
  if (S.busy || !S.item) return;
  var fv = validate();
  if (fv === null) return;
  var it = S.item;

  S.busy = true;
  btn.disabled = true;
  var old = btn.textContent;
  btn.textContent = busyText;
  tap('medium');

  api('order', { item: it.id, qty: S.qty, volume: S.vol, field: fv, seen_price: it.price, pay: payMode },
    function(j){
      S.busy = false;
      if (typeof j.balance === 'number') setBal(j.balance);
      shut();
      $('oRef').textContent = j.order || '';
      $('oSub').textContent = j.message || B.ui.done_sub;
      $('oTtl').textContent = j.paid ? B.ui.paid_ok : B.ui.done;
      $('ok').classList.add('on');
      buzz('success');
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
          var c = node.querySelector('.cost');
          if (c) c.innerHTML = fa(j.price) + '<em>' + esc(B.currency) +
                               (it.ask === 'qty' && it.unit ? ' / ' + esc(it.unit) : '') + '</em>';
        }
      }
      if (j && j.error === 'no_balance'){ shut(); }
      warn((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
    });
}

$('tWal').onclick = function(){ send('wallet', this, B.ui.sending); };
$('tGo').onclick  = function(){ send('',       this, B.ui.sending); };

$('oGo').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };

drawNav();
buildList();
applyFilter();
})();
</script>
</body>
</html>
HTML;
}
