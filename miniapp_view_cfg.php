<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ» — پوسته‌ی منشور (Prism)
 *
 * اسکلت و رفتارش با مینی‌اپ خدمات تلگرام یکی است (همان چهار صفحه، همان
 * انتخاب بسته، همان صفحه‌ی مدیریت) ولی ظاهرش هیچ شباهتی ندارد:
 *
 *   خدمات تلگرام            کانفیگ
 *   ─────────────           ──────
 *   شفق بنفش/فیروزه‌ای       مشبک سبز نئون با خط اسکن
 *   گوشه‌های گرد             گوشه‌های بریده
 *   جزیره‌ی شناور پایین      نوار تمام‌عرض با خط بالای تب فعال
 *   عددهای نرم              عددها در قاب مونواسپیس
 *
 * چون کلاس‌ها یکی‌اند، هر بهبود رفتاری روی هردو می‌نشیند؛ و چون پوسته
 * جداست، هیچ‌وقت شبیه هم نمی‌شوند.
 */

function maViewCfg($a, $boot) {
    $th   = $a['theme'] ?? [];
    return strtr(maTplApp(maSkinPrism()), [
        '__C1__'    => $th['c1'] ?? '#00FF9C',
        '__C2__'    => $th['c2'] ?? '#00B3FF',
        '__C3__'    => $th['c3'] ?? '#FF2E97',
        '__BG__'    => $th['bg'] ?? '#04070A',
        '__GLOW__'  => !empty($th['glow']) ? '1' : '0',
        '__GRAIN__' => !empty($th['grain']) ? '1' : '0',
        '__FX__'    => (string)maFxLevel($th),
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/** ⬢ پوسته‌ی منشور — گوشه‌های بریده، مشبک، سبز نئون */
function maSkinPrism() {
    return <<<'CSS'
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#E9FBF4; --dim:#7FA096; --line:rgba(0,255,156,.16); --edge:rgba(255,255,255,.07);
  --pane:#08110F; --pane2:#0B1A16;
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  --cut:12px; --safe:env(safe-area-inset-bottom,0px);
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}
img{max-width:100%}

/* ═══ پس‌زمینه: مشبک + هاله + خط اسکن ═══ */
.sky{position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:
    linear-gradient(to right,color-mix(in srgb,var(--c1) 11%,transparent) 1px,transparent 1px),
    linear-gradient(to bottom,color-mix(in srgb,var(--c1) 11%,transparent) 1px,transparent 1px),
    radial-gradient(56vw 46vw at 100% 0%,color-mix(in srgb,var(--c1) 30%,transparent),transparent 62%),
    radial-gradient(50vw 44vw at 0% 42%,color-mix(in srgb,var(--c2) 26%,transparent),transparent 62%),
    radial-gradient(44vw 40vw at 76% 100%,color-mix(in srgb,var(--c3) 20%,transparent),transparent 60%);
  background-size:46px 46px,46px 46px,auto,auto,auto}
.sky:after{content:"";position:absolute;left:0;right:0;height:180px;opacity:0;
  background:linear-gradient(180deg,transparent,color-mix(in srgb,var(--c1) 13%,transparent),transparent)}
body.fx2 .sky:after{animation:scan 7.5s linear infinite}
@keyframes scan{0%{transform:translateY(-190px);opacity:.9}100%{transform:translateY(104vh);opacity:.9}}
#stars{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.5}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:radial-gradient(126% 76% at 50% 0%,transparent 16%,var(--bg) 90%)}
.grain{position:fixed;inset:0;z-index:3;pointer-events:none;opacity:.045;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.6'/></svg>")}
body.grain-on .grain{display:block}
body.fx0 .sky{background-image:none;background:var(--bg)}
@media (prefers-reduced-motion:reduce){ .sky:after{animation:none!important;display:none} #stars{display:none} }

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 14px calc(96px + var(--safe))}

/* گوشه‌های بریده — امضای این پوسته */
.cut{clip-path:polygon(var(--cut) 0,100% 0,100% calc(100% - var(--cut)),calc(100% - var(--cut)) 100%,0 100%,0 var(--cut))}
.cut1{clip-path:polygon(0 0,100% 0,100% calc(100% - var(--cut)),calc(100% - var(--cut)) 100%,0 100%)}

/* ═══ سربرگ ═══ */
.top{display:flex;align-items:center;gap:11px;margin:13px 0 4px;padding:10px 11px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(14px 0,100% 0,100% calc(100% - 14px),calc(100% - 14px) 100%,0 100%,0 14px)}
.ava{width:44px;height:44px;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  font-weight:900;font-size:17px;color:#04120C;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%)}
.ava img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chipbal{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:4px 5px 4px 9px;
  font-family:var(--mono);font-size:12px;font-weight:900;cursor:pointer;
  border:1px solid var(--line);background:rgba(0,255,156,.06);color:var(--c1);
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.chipbal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600;font-family:Vazirmatn,inherit}
.chipbal b{width:19px;height:19px;display:grid;place-items:center;font-size:13px;line-height:1;
  color:#04120C;background:var(--c1)}
.cta{flex:0 0 auto;padding:10px 13px;border:0;cursor:pointer;
  font-family:inherit;font-size:11.5px;font-weight:800;color:#04120C;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.cta:active{transform:translateY(1px)}
.wsub{margin:0 0 9px;font-size:11px;color:var(--dim);text-align:center}

/* ═══ صفحه‌ها ═══ */
.pg{display:none;animation:pgIn .26s cubic-bezier(.2,.9,.3,1)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .pg{animation:none} }

.sect{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:20px 0 10px}
.sect h2{margin:0;font-size:11px;font-weight:800;letter-spacing:1.3px;color:var(--dim);
  display:flex;align-items:center;gap:8px}
.sect h2 s{text-decoration:none;width:14px;height:2px;background:var(--c1)}
.sect a{font-size:11px;color:var(--c2);font-weight:800;cursor:pointer;font-family:var(--mono)}

/* ═══ اعتبار ═══ */
.purse{position:relative;overflow:hidden;padding:18px 17px;
  border:1px solid var(--line);background:linear-gradient(135deg,var(--pane2),var(--pane));
  clip-path:polygon(18px 0,100% 0,100% calc(100% - 18px),calc(100% - 18px) 100%,0 100%,0 18px)}
.purse:before{content:"";position:absolute;inset:0;opacity:.4;pointer-events:none;
  background:radial-gradient(80% 130% at 100% 0%,color-mix(in srgb,var(--c1) 40%,transparent),transparent 60%)}
.purse .spark{display:none}
.purse .lbl{position:relative;font-size:10.5px;letter-spacing:1px;color:var(--dim);
  text-transform:uppercase;margin-bottom:7px;display:flex;align-items:center;gap:6px}
.purse .val{position:relative;font-family:var(--mono);font-size:30px;font-weight:800;letter-spacing:-1px;
  color:var(--c1);line-height:1.1}
body.glow-on .purse .val{text-shadow:0 0 22px color-mix(in srgb,var(--c1) 55%,transparent)}
.purse .cur{font-size:12px;color:var(--dim);margin-inline-start:6px;font-family:Vazirmatn,inherit}
.purse .acts{position:relative;display:flex;gap:8px;margin-top:16px}
.purse .acts button{flex:1;padding:12px;border:1px solid var(--line);cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#04120C;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.purse .acts button.g{color:var(--c1);background:transparent}
.purse .acts button:active{transform:translateY(1px)}

/* ═══ کارت معرفی ═══ */
.welcome{margin-top:14px;position:relative;overflow:hidden;padding:20px 17px;text-align:center;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 20px),calc(100% - 20px) 100%,0 100%)}
.welcome:before{content:"";position:absolute;top:0;right:0;width:120px;height:120px;pointer-events:none;
  background:radial-gradient(circle at 100% 0,color-mix(in srgb,var(--c2) 40%,transparent),transparent 70%)}
.logo{position:relative;width:74px;height:74px;margin:0 auto 12px}
.logo svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.logo i{position:absolute;inset:-8px;border:1px solid color-mix(in srgb,var(--c1) 35%,transparent);
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%)}
.logo i:nth-child(3){inset:2px;border-color:color-mix(in srgb,var(--c2) 30%,transparent)}
.logo .halo{display:none}
body.fx2 .logo i{animation:hex 6s ease-in-out infinite}
body.fx2 .logo svg{animation:hover 4.2s ease-in-out infinite}
@keyframes hex{0%,100%{transform:scale(1);opacity:.45}50%{transform:scale(1.09);opacity:1}}
@keyframes hover{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@media (prefers-reduced-motion:reduce){ .logo i,.logo svg{animation:none!important} }
.welcome h2{position:relative;margin:0 0 8px;font-size:17px;font-weight:900;color:var(--c1)}
.welcome p{position:relative;margin:0;font-size:12px;line-height:1.95;color:#B9D6CC}

/* ═══ ردیف دسته‌ها ═══ */
.rail{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail .rc{flex:0 0 auto;width:88px;padding:13px 6px;text-align:center;cursor:pointer;
  border:1px solid var(--edge);background:var(--pane);transition:border-color .18s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.rail .rc .ico{margin:0 auto 7px}
.rail .rc span{display:block;font-size:10.5px;font-weight:800;color:var(--dim);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rail .rc.on{border-color:var(--c1)}
.rail .rc.on span{color:var(--ink)}

/* ═══ آیکون ═══ */
.ico{position:relative;width:36px;height:36px;display:grid;place-items:center;overflow:hidden;
  background:linear-gradient(150deg,rgba(255,255,255,.12),rgba(255,255,255,.02));
  border:1px solid rgba(255,255,255,.14);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.2),inset 0 -6px 12px rgba(0,0,0,.22);
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px);
  transition:transform .24s cubic-bezier(.34,1.56,.64,1),border-color .24s}
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(112deg,transparent 42%,rgba(255,255,255,.5) 50%,transparent 58%);
  transform:translateX(-130%)}
.ico:after{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(120% 70% at 50% -10%,rgba(255,255,255,.22),transparent 60%)}
.ico svg{position:relative;width:20px;height:20px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
.ico .ico-em{font-size:18px;font-style:normal;line-height:1}
.on>.ico,.rc.on .ico{transform:translateY(-3px);border-color:color-mix(in srgb,var(--c1) 70%,transparent)}
.on>.ico:before,.rc.on .ico:before{animation:icoSheen 2.9s cubic-bezier(.4,0,.2,1) infinite}
@keyframes icoSheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}
.i-spin{transform-box:fill-box;transform-origin:50% 50%}
.on .i-spin {animation:icoSpin 5.5s linear infinite}
.on .i-pulse{animation:icoPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-float{animation:icoFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-lid  {animation:icoLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
.on .i-draw {stroke-dasharray:64;animation:icoDraw 2.6s ease-in-out infinite}
.on .i-tick {animation:icoTick 4s steps(12) infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes icoSpin {to{transform:rotate(360deg)}}
@keyframes icoPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.72}}
@keyframes icoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes icoLid  {0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@keyframes icoDraw {0%{stroke-dashoffset:64}45%,100%{stroke-dashoffset:0}}
@keyframes icoTick {to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){
  .ico,.ico:before,.on>.ico:before,.rc.on .ico:before,
  .on .i-spin,.on .i-pulse,.on .i-float,.on .i-lid,.on .i-draw,.on .i-tick{animation:none!important;transition:none!important}
}

/* ═══ جستجو و چیپ ═══ */
.find{position:relative;margin:4px 0 11px}
.find input{width:100%;padding:13px 40px 13px 13px;border:1px solid var(--edge);
  background:var(--pane);color:var(--ink);font-family:inherit;font-size:13px;outline:none;transition:.2s;
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.find input:focus{border-color:var(--c1)}
.find span{position:absolute;top:50%;right:13px;transform:translateY(-50%);opacity:.45;font-size:14px}
.tabs{display:flex;gap:6px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs b{flex:0 0 auto;padding:9px 14px;cursor:pointer;font-size:11.5px;font-weight:800;white-space:nowrap;
  color:var(--dim);border:1px solid var(--edge);background:var(--pane);transition:.18s;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.tabs b.on{color:#04120C;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c2))}

/* ═══ کارت محصول — دوتا دوتا ═══ */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.tile{position:relative;overflow:hidden;padding:13px 12px 12px;cursor:pointer;contain:content;
  border:1px solid var(--edge);background:var(--pane);
  display:flex;flex-direction:column;min-height:170px;
  clip-path:polygon(0 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%);
  transition:border-color .18s,transform .14s;
  animation:rise .38s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes rise{from{opacity:0;transform:translateY(11px)}to{opacity:1;transform:none}}
.grid:not(.first) .tile{animation:none}
@media (prefers-reduced-motion:reduce){ .tile{animation:none} }
.tile:active{transform:scale(.978)}
.tile:before{content:"";position:absolute;top:0;right:0;bottom:0;width:2px;background:var(--c2);opacity:.5}
.tile.hot:before{background:linear-gradient(180deg,var(--c1),var(--c3));opacity:1}
.tile.hot{border-color:color-mix(in srgb,var(--c1) 42%,transparent)}
.tile.hide{display:none}
.tile.off{opacity:.55}
.orb{position:relative;width:46px;height:46px;display:grid;place-items:center;font-size:22px;margin-bottom:10px;
  border:1px solid var(--line);background:rgba(0,255,156,.07);
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
body.glow-on .orb{box-shadow:0 8px 20px -14px var(--c1)}
.tile h3{position:relative;margin:0;font-size:12.5px;font-weight:800;line-height:1.6;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile p{position:relative;margin:4px 0 0;font-size:10px;color:var(--dim);line-height:1.7;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile .foot{position:relative;margin-top:auto;padding-top:10px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .cost b{display:block;font-family:var(--mono);font-size:14.5px;font-weight:800;color:var(--c1);letter-spacing:-.4px}
.tile .cost i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:2px}
.tile .plus{width:26px;height:26px;flex:0 0 auto;display:grid;place-items:center;font-size:15px;font-weight:900;
  color:#04120C;background:var(--c1);line-height:1;
  clip-path:polygon(6px 0,100% 0,100% calc(100% - 6px),calc(100% - 6px) 100%,0 100%,0 6px)}
.tag{position:absolute;top:0;left:0;z-index:2;font-size:8.5px;font-weight:900;padding:4px 8px;
  color:#04120C;background:linear-gradient(135deg,var(--c3),var(--c1));
  clip-path:polygon(0 0,100% 0,100% 100%,8px 100%)}
.livedot{position:absolute;top:0;left:0;z-index:2;font-size:8px;font-weight:900;padding:4px 8px;letter-spacing:.4px;
  color:#04120C;background:var(--c2);clip-path:polygon(0 0,100% 0,100% 100%,8px 100%)}
.tile.hasbadge .livedot{top:22px}

/* ═══ نرخ زنده ═══ */
.rates{display:grid;gap:8px}
.rate{display:flex;align-items:center;gap:11px;padding:12px 13px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%)}
.rate .e{font-size:20px;flex:0 0 auto}
.rate .n{flex:1;min-width:0;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rate .n em{display:block;font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600;margin-top:2px}
.rate .p{font-family:var(--mono);font-size:13px;font-weight:800;color:var(--c1);flex:0 0 auto}
.rate .p.down{color:var(--c3);font-size:10.5px;font-family:Vazirmatn,inherit}

/* ═══ سفارش‌ها ═══ */
.ord{display:flex;align-items:center;gap:11px;padding:12px 13px;margin-bottom:8px;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%)}
.ord .e{width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;font-size:20px;
  border:1px solid var(--edge);background:rgba(255,255,255,.03)}
.ord .m{flex:1;min-width:0}
.ord .m b{display:block;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ord .m span{display:block;font-size:9.5px;color:var(--dim);margin-top:3px;direction:ltr;text-align:right;font-family:var(--mono)}
.ord .s{flex:0 0 auto;text-align:left}
.ord .s u{display:block;text-decoration:none;font-family:var(--mono);font-size:12px;font-weight:800;color:var(--c1)}
.ord .s i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:3px}

/* ═══ حساب کاربری ═══ */
.prof{display:flex;align-items:center;gap:13px;padding:18px 16px;position:relative;overflow:hidden;
  border:1px solid var(--line);background:linear-gradient(135deg,var(--pane2),var(--pane));
  clip-path:polygon(20px 0,100% 0,100% calc(100% - 20px),calc(100% - 20px) 100%,0 100%,0 20px)}
.prof:before{content:"";position:absolute;inset:0;opacity:.32;pointer-events:none;
  background:radial-gradient(80% 130% at 100% 0%,color-mix(in srgb,var(--c2) 46%,transparent),transparent 60%)}
.prof .big{position:relative;width:62px;height:62px;flex:0 0 auto;overflow:hidden;display:grid;place-items:center;
  font-size:25px;font-weight:900;color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%)}
.prof .big img{width:100%;height:100%;object-fit:cover}
.prof .d{position:relative;flex:1;min-width:0}
.prof .d b{display:block;font-size:15.5px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prof .d span{display:block;font-size:11px;color:var(--dim);margin-top:4px;direction:ltr;text-align:right;font-family:var(--mono)}
.prof .d code{display:inline-block;margin-top:7px;font-size:10px;padding:3px 9px;font-family:var(--mono);
  border:1px solid var(--line);background:rgba(0,255,156,.06);color:var(--c1);direction:ltr}

.pane{margin-top:12px;padding:15px;border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%)}
.pane h3{margin:0 0 12px;font-size:12.5px;font-weight:900;display:flex;align-items:center;gap:7px}
.card-no{padding:13px;border:1px dashed color-mix(in srgb,var(--c1) 40%,transparent);background:rgba(0,255,156,.05)}
.card-no b{display:block;font-family:var(--mono);font-size:18px;font-weight:800;letter-spacing:1.5px;
  direction:ltr;text-align:center;color:var(--c1);white-space:nowrap;overflow-x:auto;scrollbar-width:none}
.card-no b::-webkit-scrollbar{display:none}
.card-no button{width:100%;margin-top:10px;padding:10px;border:0;cursor:pointer;
  font-family:inherit;font-size:11.5px;font-weight:800;color:#04120C;background:var(--c1);
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.card-no button:active{transform:translateY(1px)}
.card-holder{margin-top:9px;font-size:11px;color:var(--dim)}
.card-holder b{color:var(--ink)}
.amt{display:flex;gap:9px;margin-top:12px}
.amt input{flex:1;min-width:0;padding:13px;border:1px solid var(--edge);background:rgba(255,255,255,.03);
  color:var(--ink);font-family:var(--mono);font-size:15px;font-weight:800;outline:none;text-align:center;transition:.2s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.amt input:focus{border-color:var(--c1)}
.quick{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.quick i{padding:7px 11px;font-style:normal;font-family:var(--mono);font-size:11px;font-weight:800;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);color:var(--dim)}
.quick i:active{border-color:var(--c1);color:var(--c1)}
.link{display:flex;align-items:center;gap:11px;padding:13px;margin-top:8px;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.025);font-size:12px;font-weight:700}
.link:active{transform:translateY(1px)}
.link em{flex:1;font-style:normal}
.link s{text-decoration:none;color:var(--dim);font-size:15px}

.void{text-align:center;padding:44px 18px;color:var(--dim);font-size:12px;line-height:1.9}
.void div{font-size:40px;margin-bottom:10px;opacity:.5}
.skel{height:170px;border:1px solid var(--edge);
  background:linear-gradient(90deg,rgba(255,255,255,.02),rgba(255,255,255,.06),rgba(255,255,255,.02));
  background-size:200% 100%;animation:sh 1.3s linear infinite}
@keyframes sh{to{background-position:-200% 0}}

/* ═══ نوار پایین — تمام‌عرض، نه جزیره ═══ */
.dock{position:fixed;left:0;right:0;bottom:0;z-index:30;display:flex;
  padding:6px 6px calc(6px + var(--safe));
  background:rgba(4,12,10,.94);border-top:1px solid var(--line);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
body.fx0 .dock{backdrop-filter:none;-webkit-backdrop-filter:none;background:#050D0B}
.dock b{flex:1 1 0;min-width:0;position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;cursor:pointer;color:var(--dim);font-size:9.5px;font-weight:800;transition:color .16s}
.dock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dock b.on{color:var(--c1);background:transparent}
.dock b.on:before{content:"";position:absolute;top:-7px;left:14%;right:14%;height:2px;background:var(--c1)}
.dock b[data-p="adm"]{display:none}
body.is-admin .dock b[data-p="adm"]{display:flex}

/* ═══ شیت ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(1,5,4,.78);backdrop-filter:blur(6px);
  opacity:0;pointer-events:none;transition:.26s}
.scrim.on{opacity:1;pointer-events:auto}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .36s cubic-bezier(.2,.9,.25,1);
  background:linear-gradient(180deg,#0B1A16,#04070A);
  border-top:1px solid var(--c1);
  padding:10px 16px calc(20px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -22px 56px rgba(0,0,0,.7)}
.sheet.on{transform:none}
.grip{width:44px;height:3px;background:color-mix(in srgb,var(--c1) 45%,transparent);margin:4px auto 15px}
.sheet .head{display:flex;align-items:center;gap:12px;margin-bottom:15px}
.sheet .head .orb{width:54px;height:54px;font-size:26px;margin:0}
.sheet .head h2{margin:0;font-size:16px;font-weight:900}
.sheet .head p{margin:4px 0 0;font-size:11px;color:var(--dim);line-height:1.7}

.field{margin-bottom:13px}
.field label{display:block;font-size:11.5px;font-weight:800;color:var(--dim);margin-bottom:7px}
.field input,.field textarea,.field select{width:100%;padding:13px;border:1px solid var(--edge);
  background:rgba(255,255,255,.03);color:var(--ink);font-family:inherit;font-size:14px;outline:none;transition:.2s;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.field textarea{min-height:78px;resize:vertical;font-size:12.5px}
.field input:focus,.field textarea:focus{border-color:var(--c1)}
.field .hint{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.75}
.step{display:flex;align-items:center;gap:9px}
.step button{width:44px;height:44px;flex:0 0 auto;border:1px solid var(--edge);background:rgba(255,255,255,.04);
  color:var(--ink);font-size:20px;font-weight:700;cursor:pointer;transition:.16s}
.step button:active{border-color:var(--c1);color:var(--c1)}
.step input{text-align:center;font-family:var(--mono);font-weight:800;font-size:16px}

/* انتخاب حجم */
.vols{display:grid;grid-template-columns:repeat(auto-fill,minmax(94px,1fr));gap:7px;margin-top:5px}
.vols i{display:flex;flex-direction:column;align-items:center;gap:2px;padding:10px 5px;cursor:pointer;font-style:normal;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);transition:.16s;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
.vols i:active{transform:scale(.96)}
.vols i b{font-size:12px;font-weight:800;color:var(--ink)}
.vols i u{font-family:var(--mono);font-size:11px;text-decoration:none;color:var(--c1);font-weight:800}
.vols i s{font-size:9px;text-decoration:none;color:var(--dim)}
.vols i.on{border-color:var(--c1);background:rgba(0,255,156,.1)}

/* انتخاب بسته */
.lbl{font-size:10px;font-weight:800;letter-spacing:1.2px;color:var(--dim);margin:13px 0 8px}
.plans{display:grid;gap:8px}
.plans i{display:flex;align-items:center;gap:11px;padding:12px 13px;cursor:pointer;font-style:normal;
  border:1px solid var(--edge);background:rgba(255,255,255,.025);transition:border-color .18s,background .18s;
  clip-path:polygon(0 0,100% 0,100% calc(100% - 11px),calc(100% - 11px) 100%,0 100%)}
.plans i:active{transform:scale(.985)}
.plans i .pg{width:36px;height:36px;flex:0 0 auto;display:grid;place-items:center;font-size:19px;text-decoration:none;
  border:1px solid var(--line);background:rgba(0,255,156,.07);
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.plans i b{flex:1;min-width:0;font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plans i u{flex:0 0 auto;text-decoration:none;font-family:var(--mono);font-size:12px;font-weight:800;color:var(--c1)}
.plans i .chk{width:21px;height:21px;flex:0 0 auto;border:1.5px solid var(--edge);
  display:grid;place-items:center;font-size:12px;font-style:normal;color:transparent}
.plans i.on{border-color:var(--c1);background:rgba(0,255,156,.08)}
.plans i.on .chk{border-color:transparent;color:#04120C;background:var(--c1)}

.total{display:flex;justify-content:space-between;align-items:center;margin:15px 0;padding:14px 15px;
  border:1px solid var(--line);background:rgba(0,255,156,.06);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 14px),calc(100% - 14px) 100%,0 100%)}
.total span{font-size:12px;color:var(--dim)}
.total b{font-family:var(--mono);font-size:19px;font-weight:800;color:var(--c1)}

.go{width:100%;padding:15px;border:0;cursor:pointer;font-family:inherit;font-size:14.5px;font-weight:900;
  color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));transition:.2s;
  clip-path:polygon(11px 0,100% 0,100% calc(100% - 11px),calc(100% - 11px) 100%,0 100%,0 11px)}
body.glow-on .go{box-shadow:0 12px 30px -16px var(--c1)}
.go:active{transform:translateY(1px)}
.go[disabled]{cursor:default;color:var(--dim);background:rgba(255,255,255,.05);box-shadow:none}
.go.alt{margin-top:8px;color:var(--c1);background:transparent;border:1px solid var(--line);
  box-shadow:none;font-weight:800;font-size:13px}
.walbox{margin-top:10px;padding:11px 13px;font-size:11px;line-height:1.8;
  border:1px solid var(--edge);background:rgba(255,255,255,.025);color:var(--dim)}
.walbox b{color:var(--c1);font-family:var(--mono)}
.ghost{width:100%;margin-top:8px;padding:13px;cursor:pointer;border:1px solid var(--edge);background:transparent;
  color:var(--dim);font-family:inherit;font-size:13px;font-weight:700}

/* ═══ نتیجه ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:28px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 22%,transparent),transparent 72%)}
.win.on{display:grid}
.ring{position:relative;width:104px;height:104px;margin:0 auto 20px;display:grid;place-items:center;font-size:46px;
  color:#04120C;background:linear-gradient(135deg,var(--c1),var(--c2));
  clip-path:polygon(50% 0,100% 26%,100% 74%,50% 100%,0 74%,0 26%);
  animation:pop .5s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes pop{from{transform:scale(0) rotate(-30deg);opacity:0}to{transform:none;opacity:1}}
.ring:after{display:none}
@media (prefers-reduced-motion:reduce){ .ring{animation:none} }
.win h2{margin:0 0 9px;font-size:19px;font-weight:900;color:var(--c1)}
.win p{margin:0 0 22px;font-size:12px;color:var(--dim);line-height:1.9;max-width:300px}
.win .code{font-family:var(--mono);font-size:12px;padding:8px 14px;border:1px solid var(--line);
  background:rgba(0,255,156,.06);margin-bottom:20px;direction:ltr;color:var(--c1)}

/* ═══ هشدار ═══ */
.toast{position:fixed;top:13px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:12px 17px;font-size:12px;font-weight:800;max-width:88vw;text-align:center;line-height:1.7;
  background:linear-gradient(135deg,var(--c3),#B1004B);color:#fff;
  transition:transform .32s cubic-bezier(.2,1.3,.4,1);
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.toast.ok{background:linear-gradient(135deg,var(--c1),var(--c2));color:#04120C}
.toast.on{transform:translate(-50%,0)}

/* ═══ 👑 مدیریت ═══ */
.adm{display:none}
body.is-admin .adm{display:block}
.arow{display:flex;align-items:center;gap:11px;padding:12px;margin-bottom:8px;cursor:pointer;
  border:1px solid var(--edge);background:var(--pane);
  clip-path:polygon(0 0,100% 0,100% calc(100% - 11px),calc(100% - 11px) 100%,0 100%)}
.arow .e{width:36px;height:36px;flex:0 0 auto;display:grid;place-items:center;font-size:18px;
  border:1px solid var(--edge);background:rgba(255,255,255,.03)}
.arow .m{flex:1;min-width:0}
.arow .m b{display:block;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arow .m span{display:block;font-size:9.5px;color:var(--dim);margin-top:3px}
.arow .p{flex:0 0 auto;font-family:var(--mono);font-size:11.5px;font-weight:800;color:var(--c1)}
.arow.off{opacity:.5}
.aform label{display:block;font-size:10.5px;font-weight:800;color:var(--dim);margin-bottom:6px}
.a2{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.aswitch{display:flex;align-items:center;justify-content:space-between;padding:12px;cursor:pointer;
  border:1px solid var(--edge);background:rgba(255,255,255,.03);font-size:12px;font-weight:700}
.aswitch i{width:42px;height:24px;background:rgba(255,255,255,.1);position:relative;transition:.2s}
.aswitch i:after{content:"";position:absolute;top:3px;right:3px;width:18px;height:18px;background:#fff;transition:.2s}
.aswitch.on i{background:var(--c1)}
.aswitch.on i:after{right:21px}

/* ══════════════════════════════════════════════════════════════
   ⚡️ لایه‌ی جان‌دار — فقط بخش خرید

   همه‌چیز اینجا روی transform و opacity کار می‌کند، یعنی روی کارت
   گرافیک، نه روی CPU. هیچ چیزی layout را دوباره حساب نمی‌کند، پس
   هرچقدر هم چشمگیر باشد اسکرول کند نمی‌شود. با «جلوه: کم» و با
   تنظیمِ کم‌کردنِ حرکتِ خودِ گوشی، همه‌شان خاموش می‌شوند.
   ══════════════════════════════════════════════════════════════ */

/* ── ورودِ پلکانیِ کارت‌ها ── */
.grid.first .tile{animation:tileIn .52s cubic-bezier(.16,1,.3,1) backwards}
@keyframes tileIn{
  from{opacity:0;transform:translateY(22px) scale(.94)}
  to  {opacity:1;transform:none}
}
.grid.first .tile:nth-child(1),.grid.first .tile:nth-child(2){animation-delay:.02s}
.grid.first .tile:nth-child(3),.grid.first .tile:nth-child(4){animation-delay:.09s}
.grid.first .tile:nth-child(5),.grid.first .tile:nth-child(6){animation-delay:.16s}
.grid.first .tile:nth-child(7),.grid.first .tile:nth-child(8){animation-delay:.23s}
.grid.first .tile:nth-child(9),.grid.first .tile:nth-child(10){animation-delay:.30s}
.grid.first .tile:nth-child(n+11){animation-delay:.36s}

/* ── نورِ روان روی کارت ── یک نوار مورب که آرام رد می‌شود */
.tile:after{content:"";position:absolute;top:-60%;bottom:-60%;width:38%;left:-45%;z-index:1;
  pointer-events:none;transform:skewX(-18deg);
  background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--c1) 16%,transparent),transparent)}
.tile.hot:after{animation:tileSheen 3.6s ease-in-out infinite}
.tile:active:after{animation:tileSheen .55s ease-out}
@keyframes tileSheen{0%{left:-45%}55%,100%{left:135%}}

/* لبه‌ی سمت راستِ کارتِ داغ، نفس می‌کشد */
.tile.hot:before{animation:edgePulse 2.4s ease-in-out infinite}
@keyframes edgePulse{0%,100%{opacity:.55;transform:scaleY(.82)}50%{opacity:1;transform:scaleY(1)}}

.tile:active{transform:scale(.965)}
.tile .plus{transition:transform .18s cubic-bezier(.2,1.6,.4,1)}
.tile:active .plus{transform:rotate(90deg) scale(1.14)}

/* ── باز شدنِ شیت ── */
.scrim.on{animation:scrimIn .34s ease-out}
@keyframes scrimIn{from{opacity:0}to{opacity:1}}

.sheet{transition:transform .42s cubic-bezier(.16,1,.3,1)}
/* ریلِ بالای شیت، از وسط باز می‌شود — انگار دارد شارژ می‌گیرد */
.sheet:before{content:"";position:absolute;top:-1px;left:0;right:0;height:2px;z-index:2;
  background:linear-gradient(90deg,transparent,var(--c1),var(--c2),var(--c1),transparent);
  transform:scaleX(0);transform-origin:50% 50%}
.sheet.on:before{animation:railCharge .62s cubic-bezier(.16,1,.3,1) .1s forwards}
@keyframes railCharge{to{transform:scaleX(1)}}

/* محتوای شیت، آبشاری بالا می‌آید */
.sheet.on .head,
.sheet.on #sField,
.sheet.on .total,
.sheet.on .go,
.sheet.on .go.alt{animation:sheetRise .46s cubic-bezier(.16,1,.3,1) backwards}
.sheet.on .head    {animation-delay:.10s}
.sheet.on #sField  {animation-delay:.16s}
.sheet.on .total   {animation-delay:.22s}
.sheet.on .go      {animation-delay:.28s}
.sheet.on .go.alt  {animation-delay:.33s}
@keyframes sheetRise{
  from{opacity:0;transform:translateY(18px)}
  to  {opacity:1;transform:none}
}

/* نشانِ محصول، با یک چرخشِ کوتاه می‌نشیند */
.sheet.on .head .orb{animation:orbLand .6s cubic-bezier(.2,1.5,.35,1) .12s backwards}
@keyframes orbLand{
  from{opacity:0;transform:scale(.4) rotate(-32deg)}
  to  {opacity:1;transform:none}
}

/* ── مبلغِ قابل پرداخت ── قابِ نفس‌کش و عددِ درخشان */
.total{position:relative;overflow:hidden}
.total:before{content:"";position:absolute;inset:0;pointer-events:none;
  border:1px solid color-mix(in srgb,var(--c1) 55%,transparent);
  clip-path:inherit;animation:totalBreath 2.8s ease-in-out infinite}
@keyframes totalBreath{0%,100%{opacity:.28}50%{opacity:.9}}
.total b{position:relative;transition:transform .22s cubic-bezier(.2,1.7,.4,1)}
.total:after{content:"";position:absolute;top:0;bottom:0;width:44%;left:-50%;pointer-events:none;
  transform:skewX(-20deg);
  background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--c1) 13%,transparent),transparent);
  animation:totalSweep 3.4s ease-in-out infinite}
@keyframes totalSweep{0%{left:-50%}60%,100%{left:130%}}

/* ── دکمه‌ی پرداخت ── حلقه‌ی انرژی دور دکمه */
.go{position:relative;overflow:hidden;isolation:isolate;
  transition:transform .16s cubic-bezier(.2,1.6,.4,1),box-shadow .2s}
.go:not([disabled]):before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.42),transparent);
  transform:translateX(-100%) skewX(-20deg);
  animation:goSweep 2.6s cubic-bezier(.5,0,.5,1) infinite}
@keyframes goSweep{0%{transform:translateX(-140%) skewX(-20deg)}
                   55%,100%{transform:translateX(240%) skewX(-20deg)}}
.go:not([disabled]):active{transform:scale(.972)}
body.glow-on .go:not([disabled]){animation:goGlow 2.6s ease-in-out infinite}
@keyframes goGlow{0%,100%{box-shadow:0 12px 30px -18px var(--c1)}
                  50%    {box-shadow:0 14px 38px -14px var(--c1)}}

/* ── انتخابِ بسته و حجم ── */
.plans i,.vols i{transition:border-color .18s,transform .16s cubic-bezier(.2,1.6,.4,1)}
.plans i:active,.vols i:active{transform:scale(.955)}
.plans i.on,.vols i.on{animation:pickPop .42s cubic-bezier(.2,1.55,.35,1)}
@keyframes pickPop{
  0%  {transform:scale(1)}
  42% {transform:scale(1.045)}
  100%{transform:scale(1)}
}
.plans i.on:after,.vols i.on:after{content:"";position:absolute;inset:0;pointer-events:none;
  border:1px solid var(--c1);clip-path:inherit;animation:pickRing .5s ease-out forwards}
@keyframes pickRing{from{opacity:.95;transform:scale(1.035)}to{opacity:0;transform:scale(1)}}
.plans i .chk,.vols i .chk{transition:transform .2s cubic-bezier(.2,1.8,.4,1)}
.plans i.on .chk{transform:scale(1.18)}

/* ── پیام موفقیت ── */
.win.on .ring{animation:ringIn .7s cubic-bezier(.2,1.5,.3,1)}
@keyframes ringIn{
  0%  {opacity:0;transform:scale(.3) rotate(-90deg)}
  60% {opacity:1;transform:scale(1.12) rotate(6deg)}
  100%{opacity:1;transform:none}
}

/* ── و همه‌ی این‌ها، وقتی نباید، نیستند ── */
body.fx0 .tile:after,
body.fx0 .total:after,
body.fx0 .total:before,
body.fx0 .go:before,
body.fx0 .tile.hot:before{animation:none;display:none}
body.fx0 .grid.first .tile,
body.fx0 .sheet.on .head,
body.fx0 .sheet.on #sField,
body.fx0 .sheet.on .total,
body.fx0 .sheet.on .go,
body.fx0 .sheet.on .sheet:before{animation:none}
@media (prefers-reduced-motion:reduce){
  .grid.first .tile,.tile:after,.tile.hot:before,.total:after,.total:before,
  .go:before,.sheet:before,.sheet.on .head,.sheet.on #sField,.sheet.on .total,
  .sheet.on .go,.sheet.on .go.alt,.sheet.on .head .orb,
  .plans i.on,.vols i.on,.plans i.on:after,.vols i.on:after,.win.on .ring{
    animation:none!important;transform:none!important}
  .sheet:before{transform:scaleX(1)!important}
}
</style>
CSS;
}
