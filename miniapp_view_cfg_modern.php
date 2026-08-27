<?php
/**
 * 💜 نمای مینی‌اپ «فروش کانفیگ» — پوسته‌ی Aurora Pro
 *
 * فقط ظاهر است. تمام منطق برنامه، شناسه‌ها، کلاس‌هایی که جاوااسکریپت
 * به آن‌ها وابسته است، و ساختار چهار صفحه دست‌نخورده مانده‌اند —
 * این فایل هیچ کاری جز برگرداندن یک بلوک <style> نمی‌کند.
 *
 * طراحی:
 *   • تیره‌ی عمیق (#08090D) با هاله‌های بسیار محو بنفش/آبی در گوشه‌ها
 *   • بدون شبکه، بدون خط اسکن، بدون گوشه‌ی بریده، بدون نویز سنگین
 *   • به‌جای border: اختلاف رنگ + سایه‌ی نرم + هاله‌ی بسیار کم‌رنگ
 *   • گوشه‌های گرد و مدرن، تایپوگرافی و فاصله‌گذاری حرفه‌ای
 *   • انیمیشن کم و کوتاه (۰.۲ تا ۰.۳۵ ثانیه): فقط fade / slide / scale
 */

function maViewCfg($a, $boot) {
    $th   = $a['theme'] ?? [];
    return strtr(maTplApp(maSkinPrism()), [
        '__C1__'    => $th['c1'] ?? '#8B5CF6',
        '__C2__'    => $th['c2'] ?? '#6366F1',
        '__C3__'    => $th['c3'] ?? '#22D3EE',
        '__BG__'    => $th['bg'] ?? '#08090D',
        '__GLOW__'  => !empty($th['glow']) ? '1' : '0',
        '__GRAIN__' => !empty($th['grain']) ? '1' : '0',
        '__FX__'    => (string)maFxLevel($th),
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/** 💜 پوسته‌ی Aurora Pro — تیره، بنفش، بدون خط و گوشه‌ی تیز */
function maSkinPrism() {
    return <<<'CSS'
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;

  /* سطح‌ها — به‌جای خط، اختلاف روشنایی */
  --s1:#101219;          /* کارت معمولی */
  --s2:#151824;          /* کارت برجسته */
  --s3:#1B1F2E;          /* حالت فشرده/فعال */
  --hair:rgba(255,255,255,.06);   /* فقط جایی که واقعا لازم است */

  --ink:#EDEEF5;         /* متن اصلی */
  --dim:#8B90A6;         /* متن فرعی، خاکستری سرد */
  --dim2:#5F6479;

  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,Vazirmatn,"Vazir",monospace;
  --r-lg:22px; --r-md:16px; --r-sm:12px; --r-xs:9px;
  --safe:env(safe-area-inset-bottom,0px);
  --sh1:0 2px 10px rgba(0,0,0,.30);
  --sh2:0 10px 30px -12px rgba(0,0,0,.65);
  --sh3:0 22px 60px -22px rgba(0,0,0,.8);
  --ease:cubic-bezier(.22,.61,.36,1);
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
  letter-spacing:-.1px;
}
img{max-width:100%}

/* ═══ پس‌زمینه: فقط هاله‌های بسیار محو، بدون شبکه و خط اسکن ═══ */
.sky{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(70vw 52vw at 92% -8%, color-mix(in srgb,var(--c1) 20%,transparent), transparent 62%),
    radial-gradient(62vw 50vw at 4% 26%, color-mix(in srgb,var(--c2) 16%,transparent), transparent 64%),
    radial-gradient(58vw 46vw at 70% 104%, color-mix(in srgb,var(--c1) 12%,transparent), transparent 62%)}
.sky:after{display:none}
#stars{display:none}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:linear-gradient(180deg, transparent 0%, color-mix(in srgb,var(--bg) 62%,transparent) 58%, var(--bg) 100%)}
.grain{position:fixed;inset:0;z-index:3;pointer-events:none;opacity:.02;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.6'/></svg>")}
body.grain-on .grain{display:block}
body.fx0 .sky{background:none}

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 16px calc(126px + var(--safe))}

/* گوشه‌های بریده حذف شدند — این دو کلاس فقط برای سازگاری مانده‌اند */
.cut,.cut1{clip-path:none}

/* ═══ سربرگ ═══ */
.top{display:flex;align-items:center;gap:12px;margin:14px 0 6px;padding:12px 13px;
  background:var(--s1);border-radius:var(--r-lg);box-shadow:var(--sh1)}
.ava{width:46px;height:46px;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  border-radius:50%;font-weight:800;font-size:17px;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 6px 18px -8px color-mix(in srgb,var(--c1) 70%,transparent)}
.ava img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:14.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chipbal{display:inline-flex;align-items:center;gap:7px;margin-top:6px;padding:5px 6px 5px 11px;
  font-size:12px;font-weight:700;cursor:pointer;border-radius:999px;
  background:rgba(255,255,255,.05);color:var(--ink);transition:background .2s var(--ease)}
.chipbal:active{background:rgba(255,255,255,.09)}
.chipbal em{font-style:normal;font-size:10px;color:var(--dim);font-weight:500}
.chipbal b{width:20px;height:20px;display:grid;place-items:center;font-size:13px;line-height:1;
  border-radius:50%;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2))}
.cta{flex:0 0 auto;padding:11px 15px;border:0;cursor:pointer;border-radius:var(--r-sm);
  font-family:inherit;font-size:12px;font-weight:700;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 8px 22px -12px color-mix(in srgb,var(--c1) 85%,transparent);
  transition:transform .2s var(--ease),box-shadow .2s var(--ease)}
.cta:active{transform:scale(.97)}
.wsub{margin:0 0 10px;font-size:11px;color:var(--dim2);text-align:center}

/* ═══ صفحه‌ها ═══ */
.pg{display:none;animation:pgIn .28s var(--ease)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

.sect{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:22px 0 11px}
.sect h2{margin:0;font-size:12.5px;font-weight:700;letter-spacing:.2px;color:var(--ink);
  display:flex;align-items:center;gap:8px}
.sect h2 s{display:none}
.sect a{font-size:11.5px;color:var(--c1);font-weight:600;cursor:pointer}

/* ═══ اعتبار — زیباترین کارت صفحه ═══ */
.purse{position:relative;overflow:hidden;padding:22px 20px;border-radius:var(--r-lg);
  background:
    radial-gradient(120% 140% at 100% 0%, color-mix(in srgb,var(--c1) 22%,transparent), transparent 58%),
    linear-gradient(160deg,var(--s2),var(--s1));
  box-shadow:var(--sh2)}
body.glow-on .purse{box-shadow:var(--sh2),0 0 44px -26px color-mix(in srgb,var(--c1) 80%,transparent)}
.purse:before{display:none}
.purse .spark{display:none}
.purse .lbl{position:relative;font-size:11px;letter-spacing:.3px;color:var(--dim);
  margin-bottom:9px;display:flex;align-items:center;gap:7px;font-weight:600}
.purse .val{position:relative;font-family:var(--mono);font-size:34px;font-weight:700;letter-spacing:-1.4px;
  color:#fff;line-height:1.05}
.purse .cur{font-size:12.5px;color:var(--dim);margin-inline-start:7px;font-family:Vazirmatn,inherit;font-weight:500}
.purse .acts{position:relative;display:flex;gap:9px;margin-top:20px}
.purse .acts button{flex:1;padding:13px;border:0;cursor:pointer;border-radius:var(--r-sm);
  font-family:inherit;font-size:12.5px;font-weight:700;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  transition:transform .2s var(--ease),background .2s var(--ease)}
.purse .acts button.g{color:var(--ink);background:rgba(255,255,255,.07)}
.purse .acts button:active{transform:scale(.975)}

/* ═══ کارت معرفی ═══ */
.welcome{margin-top:14px;position:relative;overflow:hidden;padding:26px 20px;text-align:center;
  border-radius:var(--r-lg);background:var(--s1);box-shadow:var(--sh1)}
.welcome:before{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(90% 70% at 50% 0%, color-mix(in srgb,var(--c2) 16%,transparent), transparent 60%)}
.logo{position:relative;width:66px;height:66px;margin:0 auto 14px;display:grid;place-items:center}
.logo svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.logo i,.logo .halo{display:none}
.welcome h2{position:relative;margin:0 0 9px;font-size:17.5px;font-weight:700;color:#fff}
.welcome p{position:relative;margin:0;font-size:12.5px;line-height:2;color:var(--dim)}

/* ═══ ردیف دسته‌ها ═══ */
.rail{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 8px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail .rc{flex:0 0 auto;width:90px;padding:14px 8px;text-align:center;cursor:pointer;
  border-radius:var(--r-md);background:var(--s1);
  transition:background .22s var(--ease),transform .22s var(--ease)}
.rail .rc:active{transform:scale(.97)}
.rail .rc .ico{margin:0 auto 8px}
.rail .rc span{display:block;font-size:11px;font-weight:600;color:var(--dim);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rail .rc.on{background:color-mix(in srgb,var(--c1) 16%,var(--s2))}
.rail .rc.on span{color:#fff}

/* ═══ آیکون — ساده، بدون قاب چندلایه ═══ */
.ico{position:relative;width:38px;height:38px;display:grid;place-items:center;overflow:hidden;
  border-radius:var(--r-sm);background:rgba(255,255,255,.05);color:var(--ink);
  transition:background .22s var(--ease),color .22s var(--ease)}
.ico:before,.ico:after{display:none}
.ico svg{position:relative;width:20px;height:20px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
.ico .ico-em{font-size:18px;font-style:normal;line-height:1}
.on>.ico,.rc.on .ico{background:color-mix(in srgb,var(--c1) 26%,transparent);color:#fff}
/* هیچ آیکنی دائم نمی‌چرخد یا نمی‌تپد */
.i-spin,.i-pulse,.i-float,.i-lid,.i-draw,.i-tick,
.on .i-spin,.on .i-pulse,.on .i-float,.on .i-lid,.on .i-draw,.on .i-tick{animation:none!important}

/* ═══ جستجو و چیپ ═══ */
.find{position:relative;margin:6px 0 12px}
.find input{width:100%;padding:14px 42px 14px 14px;border:0;border-radius:var(--r-md);
  background:var(--s1);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none;
  box-shadow:inset 0 0 0 1px transparent;transition:box-shadow .22s var(--ease),background .22s var(--ease)}
.find input::placeholder{color:var(--dim2)}
.find input:focus{background:var(--s2);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--c1) 55%,transparent)}
.find span{position:absolute;top:50%;right:14px;transform:translateY(-50%);opacity:.5;font-size:14px}
.tabs{display:flex;gap:7px;overflow-x:auto;padding:0 0 13px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs b{flex:0 0 auto;padding:9px 15px;cursor:pointer;font-size:12px;font-weight:600;white-space:nowrap;
  color:var(--dim);border-radius:999px;background:var(--s1);
  transition:background .22s var(--ease),color .22s var(--ease)}
.tabs b.on{color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2))}

/* ═══ کارت محصول ═══ */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.tile{position:relative;overflow:hidden;padding:15px 14px 14px;cursor:pointer;contain:content;
  border-radius:var(--r-md);background:var(--s1);box-shadow:var(--sh1);
  display:flex;flex-direction:column;min-height:172px;
  transition:transform .2s var(--ease),background .2s var(--ease),box-shadow .2s var(--ease)}
.tile:active{transform:scale(.975);background:var(--s3)}
.tile:before,.tile:after{display:none}
.tile.hot{background:
    radial-gradient(120% 90% at 100% 0%, color-mix(in srgb,var(--c1) 20%,transparent), transparent 62%),
    var(--s2)}
body.glow-on .tile.hot{box-shadow:var(--sh1),0 0 32px -20px color-mix(in srgb,var(--c1) 90%,transparent)}
.tile.hide{display:none}
.tile.off{opacity:.5}
.orb{position:relative;width:46px;height:46px;display:grid;place-items:center;font-size:23px;margin-bottom:12px;
  border-radius:var(--r-sm);background:rgba(255,255,255,.05)}
body.glow-on .orb{box-shadow:none}
.tile h3{position:relative;margin:0;font-size:13px;font-weight:600;line-height:1.65;color:var(--ink);
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile p{position:relative;margin:5px 0 0;font-size:10.5px;color:var(--dim);line-height:1.75;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile .foot{position:relative;margin-top:auto;padding-top:12px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .cost b{display:block;font-family:var(--mono);font-size:15px;font-weight:700;color:#fff;letter-spacing:-.5px}
.tile .cost i{display:block;font-style:normal;font-size:9.5px;color:var(--dim2);margin-top:3px}
.tile .plus{width:28px;height:28px;flex:0 0 auto;display:grid;place-items:center;font-size:16px;font-weight:500;
  color:#fff;line-height:1;border-radius:50%;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  transition:transform .2s var(--ease)}
.tile:active .plus{transform:scale(1.08)}
.tag{position:absolute;top:10px;left:10px;z-index:2;font-size:9px;font-weight:700;padding:4px 9px;
  border-radius:999px;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2))}
.livedot{position:absolute;top:10px;left:10px;z-index:2;font-size:8.5px;font-weight:700;padding:4px 9px;
  border-radius:999px;color:#06202A;background:var(--c3)}
.tile.hasbadge .livedot{top:36px}

/* ═══ نرخ زنده ═══ */
.rates{display:grid;gap:9px}
.rate{display:flex;align-items:center;gap:12px;padding:14px;border-radius:var(--r-md);
  background:var(--s1);box-shadow:var(--sh1)}
.rate .e{font-size:20px;flex:0 0 auto}
.rate .n{flex:1;min-width:0;font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rate .n em{display:block;font-style:normal;font-size:10px;color:var(--dim);font-weight:500;margin-top:3px}
.rate .p{font-family:var(--mono);font-size:13.5px;font-weight:700;color:#fff;flex:0 0 auto}
.rate .p.down{color:var(--dim);font-size:11px;font-family:Vazirmatn,inherit;font-weight:500}

/* ═══ سفارش‌ها ═══ */
.ord{display:flex;align-items:center;gap:12px;padding:14px;margin-bottom:9px;border-radius:var(--r-md);
  background:var(--s1);box-shadow:var(--sh1)}
.ord .e{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;font-size:20px;
  border-radius:var(--r-sm);background:rgba(255,255,255,.05)}
.ord .m{flex:1;min-width:0}
.ord .m b{display:block;font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ord .m span{display:block;font-size:10px;color:var(--dim2);margin-top:4px;direction:ltr;text-align:right;font-family:var(--mono)}
.ord .s{flex:0 0 auto;text-align:left}
.ord .s u{display:block;text-decoration:none;font-family:var(--mono);font-size:12.5px;font-weight:700;color:#fff}
.ord .s i{display:block;font-style:normal;font-size:9.5px;color:var(--dim);margin-top:4px}

/* ═══ حساب کاربری ═══ */
.prof{display:flex;align-items:center;gap:14px;padding:22px 18px;position:relative;overflow:hidden;
  border-radius:var(--r-lg);
  background:
    radial-gradient(120% 140% at 100% 0%, color-mix(in srgb,var(--c2) 20%,transparent), transparent 58%),
    linear-gradient(160deg,var(--s2),var(--s1));
  box-shadow:var(--sh2)}
.prof:before{display:none}
.prof .big{position:relative;width:64px;height:64px;flex:0 0 auto;overflow:hidden;display:grid;place-items:center;
  border-radius:50%;font-size:26px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 10px 26px -12px color-mix(in srgb,var(--c1) 80%,transparent)}
.prof .big img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.prof .d{position:relative;flex:1;min-width:0}
.prof .d b{display:block;font-size:16px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prof .d span{display:block;font-size:11.5px;color:var(--dim);margin-top:5px;direction:ltr;text-align:right;font-family:var(--mono)}
.prof .d code{display:inline-block;margin-top:9px;font-size:10.5px;padding:4px 11px;font-family:var(--mono);
  border-radius:999px;background:rgba(255,255,255,.07);color:var(--ink);direction:ltr}

.pane{margin-top:13px;padding:17px;border-radius:var(--r-md);background:var(--s1);box-shadow:var(--sh1)}
.pane h3{margin:0 0 14px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px}
.card-no{padding:15px;border-radius:var(--r-sm);background:rgba(255,255,255,.04)}
.card-no b{display:block;font-family:var(--mono);font-size:18px;font-weight:700;letter-spacing:1.6px;
  direction:ltr;text-align:center;color:#fff;white-space:nowrap;overflow-x:auto;scrollbar-width:none}
.card-no b::-webkit-scrollbar{display:none}
.card-no button{width:100%;margin-top:12px;padding:11px;border:0;cursor:pointer;border-radius:var(--r-xs);
  font-family:inherit;font-size:12px;font-weight:700;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c2));transition:transform .2s var(--ease)}
.card-no button:active{transform:scale(.975)}
.card-holder{margin-top:10px;font-size:11.5px;color:var(--dim)}
.card-holder b{color:var(--ink)}
.amt{display:flex;gap:10px;margin-top:14px}
.amt input{flex:1;min-width:0;padding:14px;border:0;border-radius:var(--r-sm);background:rgba(255,255,255,.05);
  color:var(--ink);font-family:var(--mono);font-size:15.5px;font-weight:700;outline:none;text-align:center;
  box-shadow:inset 0 0 0 1px transparent;transition:box-shadow .22s var(--ease)}
.amt input:focus{box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--c1) 55%,transparent)}
.quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.quick i{padding:8px 13px;font-style:normal;font-family:var(--mono);font-size:11.5px;font-weight:600;cursor:pointer;
  border-radius:999px;background:rgba(255,255,255,.05);color:var(--dim);
  transition:background .2s var(--ease),color .2s var(--ease)}
.quick i:active{background:color-mix(in srgb,var(--c1) 24%,transparent);color:#fff}
.link{display:flex;align-items:center;gap:12px;padding:14px;margin-top:9px;cursor:pointer;border-radius:var(--r-sm);
  background:rgba(255,255,255,.04);font-size:12.5px;font-weight:600;
  transition:background .2s var(--ease)}
.link:active{background:rgba(255,255,255,.08)}
.link em{flex:1;font-style:normal}
.link s{text-decoration:none;color:var(--dim2);font-size:15px}

.void{text-align:center;padding:48px 20px;color:var(--dim);font-size:12.5px;line-height:2}
.void div{font-size:38px;margin-bottom:12px;opacity:.35}
.skel{height:172px;border-radius:var(--r-md);background:var(--s1)}

/* ═══ نوار پایین — شناور، بلور، گوشه‌گرد ═══ */
.dock{position:fixed;left:12px;right:12px;bottom:calc(10px + var(--safe));z-index:30;display:flex;
  padding:7px;border-radius:20px;
  background:color-mix(in srgb,var(--s2) 82%,transparent);
  backdrop-filter:blur(20px) saturate(1.4);-webkit-backdrop-filter:blur(20px) saturate(1.4);
  box-shadow:var(--sh3)}
body.fx0 .dock{backdrop-filter:none;-webkit-backdrop-filter:none;background:var(--s2)}
.dock b{flex:1 1 0;min-width:0;position:relative;display:flex;flex-direction:column;align-items:center;gap:5px;
  padding:9px 2px;cursor:pointer;color:var(--dim2);font-size:9.5px;font-weight:600;border-radius:14px;
  transition:color .22s var(--ease),background .22s var(--ease)}
.dock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dock b.on{color:#fff;background:color-mix(in srgb,var(--c1) 20%,transparent)}
.dock b.on:before{display:none}
.dock b[data-p="adm"]{display:none}
body.is-admin .dock b[data-p="adm"]{display:flex}

/* ═══ شیت خرید ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(4,5,10,.7);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  opacity:0;pointer-events:none;transition:opacity .3s var(--ease)}
.scrim.on{opacity:1;pointer-events:auto}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .34s var(--ease);
  background:linear-gradient(180deg,var(--s2),#0B0D14);
  border-radius:28px 28px 0 0;
  padding:12px 18px calc(22px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -26px 70px rgba(0,0,0,.75)}
.sheet.on{transform:none}
.sheet:before{display:none}
.grip{width:38px;height:4px;border-radius:999px;background:rgba(255,255,255,.16);margin:4px auto 18px}
.sheet .head{display:flex;align-items:center;gap:13px;margin-bottom:18px}
.sheet .head .orb{width:54px;height:54px;font-size:26px;margin:0}
.sheet .head h2{margin:0;font-size:16.5px;font-weight:700}
.sheet .head p{margin:5px 0 0;font-size:11.5px;color:var(--dim);line-height:1.8}

.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:600;color:var(--dim);margin-bottom:8px}
.field input,.field textarea,.field select{width:100%;padding:14px;border:0;border-radius:var(--r-sm);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:14px;outline:none;
  box-shadow:inset 0 0 0 1px transparent;transition:box-shadow .22s var(--ease)}
.field textarea{min-height:80px;resize:vertical;font-size:13px}
.field input:focus,.field textarea:focus{box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--c1) 55%,transparent)}
.field .hint{font-size:11px;color:var(--dim2);margin-top:7px;line-height:1.8}
.step{display:flex;align-items:center;gap:10px}
.step button{width:46px;height:46px;flex:0 0 auto;border:0;border-radius:var(--r-sm);background:rgba(255,255,255,.06);
  color:var(--ink);font-size:20px;font-weight:500;cursor:pointer;transition:background .2s var(--ease)}
.step button:active{background:color-mix(in srgb,var(--c1) 26%,transparent)}
.step input{text-align:center;font-family:var(--mono);font-weight:700;font-size:16px}

/* انتخاب حجم */
.vols{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px;margin-top:6px}
.vols i{display:flex;flex-direction:column;align-items:center;gap:3px;padding:12px 6px;cursor:pointer;font-style:normal;
  border-radius:var(--r-sm);background:rgba(255,255,255,.05);
  transition:background .2s var(--ease),transform .2s var(--ease)}
.vols i:active{transform:scale(.97)}
.vols i b{font-size:12.5px;font-weight:600;color:var(--ink)}
.vols i u{font-family:var(--mono);font-size:11.5px;text-decoration:none;color:var(--dim);font-weight:700}
.vols i s{font-size:9.5px;text-decoration:none;color:var(--dim2)}
.vols i.on{background:color-mix(in srgb,var(--c1) 22%,transparent)}
.vols i.on b,.vols i.on u{color:#fff}
body.glow-on .vols i.on{box-shadow:0 0 22px -12px color-mix(in srgb,var(--c1) 90%,transparent)}

/* انتخاب بسته */
.lbl{font-size:11px;font-weight:600;letter-spacing:.2px;color:var(--dim);margin:15px 0 9px}
.plans{display:grid;gap:9px}
.plans i{display:flex;align-items:center;gap:12px;padding:14px;cursor:pointer;font-style:normal;
  border-radius:var(--r-sm);background:rgba(255,255,255,.045);
  transition:background .2s var(--ease),transform .2s var(--ease)}
.plans i:active{transform:scale(.99)}
.plans i .pg{width:38px;height:38px;flex:0 0 auto;display:grid;place-items:center;font-size:19px;text-decoration:none;
  border-radius:var(--r-xs);background:rgba(255,255,255,.06)}
.plans i b{flex:1;min-width:0;font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plans i u{flex:0 0 auto;text-decoration:none;font-family:var(--mono);font-size:12.5px;font-weight:700;color:var(--dim)}
.plans i .chk{width:22px;height:22px;flex:0 0 auto;border-radius:50%;background:rgba(255,255,255,.08);
  display:grid;place-items:center;font-size:12px;font-style:normal;color:transparent;
  transition:background .2s var(--ease),color .2s var(--ease)}
.plans i.on{background:color-mix(in srgb,var(--c1) 20%,transparent)}
.plans i.on u{color:#fff}
.plans i.on .chk{color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2))}
body.glow-on .plans i.on{box-shadow:0 0 26px -14px color-mix(in srgb,var(--c1) 90%,transparent)}

.total{display:flex;justify-content:space-between;align-items:center;margin:18px 0;padding:16px 17px;
  border-radius:var(--r-md);
  background:
    radial-gradient(120% 160% at 100% 0%, color-mix(in srgb,var(--c1) 20%,transparent), transparent 60%),
    rgba(255,255,255,.045)}
.total:before,.total:after{display:none}
.total span{font-size:12.5px;color:var(--dim)}
.total b{font-family:var(--mono);font-size:20px;font-weight:700;color:#fff}

.go{width:100%;padding:16px;border:0;cursor:pointer;font-family:inherit;font-size:15px;font-weight:700;
  border-radius:var(--r-md);color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 14px 34px -18px color-mix(in srgb,var(--c1) 90%,transparent);
  transition:transform .2s var(--ease),box-shadow .2s var(--ease)}
.go:active{transform:scale(.985)}
.go[disabled]{cursor:default;color:var(--dim2);background:rgba(255,255,255,.05);box-shadow:none}
.go.alt{margin-top:9px;color:var(--ink);background:rgba(255,255,255,.06);box-shadow:none;font-weight:600;font-size:13.5px}
.walbox{margin-top:11px;padding:12px 14px;font-size:11.5px;line-height:1.85;border-radius:var(--r-sm);
  background:rgba(255,255,255,.04);color:var(--dim)}
.walbox b{color:#fff;font-family:var(--mono)}
.ghost{width:100%;margin-top:9px;padding:14px;cursor:pointer;border:0;border-radius:var(--r-sm);
  background:rgba(255,255,255,.05);color:var(--dim);font-family:inherit;font-size:13px;font-weight:600}

/* ═══ نتیجه ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:30px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 34%, color-mix(in srgb,var(--c1) 18%,transparent), transparent 70%)}
.win.on{display:grid}
.ring{position:relative;width:104px;height:104px;margin:0 auto 24px;display:grid;place-items:center;font-size:46px;
  border-radius:50%;color:#fff;background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 20px 50px -22px color-mix(in srgb,var(--c1) 90%,transparent);
  animation:pop .32s var(--ease) backwards}
@keyframes pop{from{transform:scale(.86);opacity:0}to{transform:none;opacity:1}}
.ring:after{display:none}
.win h2{margin:0 0 10px;font-size:19.5px;font-weight:700;color:#fff}
.win p{margin:0 0 24px;font-size:12.5px;color:var(--dim);line-height:2;max-width:300px}
.win .code{font-family:var(--mono);font-size:12.5px;padding:9px 16px;border-radius:999px;
  background:rgba(255,255,255,.06);margin-bottom:22px;direction:ltr;color:var(--ink)}

/* ═══ هشدار ═══ */
.toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:13px 18px;font-size:12.5px;font-weight:600;max-width:88vw;text-align:center;line-height:1.75;
  border-radius:var(--r-sm);background:#2A1520;color:#FFD7E0;box-shadow:var(--sh2);
  transition:transform .3s var(--ease)}
.toast.ok{background:linear-gradient(135deg,var(--c1),var(--c2));color:#fff}
.toast.on{transform:translate(-50%,0)}

/* ═══ 👑 مدیریت ═══ */
.adm{display:none}
body.is-admin .adm{display:block}
.arow{display:flex;align-items:center;gap:12px;padding:14px;margin-bottom:9px;cursor:pointer;
  border-radius:var(--r-md);background:var(--s1);box-shadow:var(--sh1);
  transition:background .2s var(--ease)}
.arow:active{background:var(--s3)}
.arow .e{width:38px;height:38px;flex:0 0 auto;display:grid;place-items:center;font-size:18px;
  border-radius:var(--r-xs);background:rgba(255,255,255,.05)}
.arow .m{flex:1;min-width:0}
.arow .m b{display:block;font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arow .m span{display:block;font-size:10px;color:var(--dim);margin-top:4px}
.arow .p{flex:0 0 auto;font-family:var(--mono);font-size:12px;font-weight:700;color:var(--ink)}
.arow.off{opacity:.45}
.aform label{display:block;font-size:11px;font-weight:600;color:var(--dim);margin-bottom:7px}
.a2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.aswitch{display:flex;align-items:center;justify-content:space-between;padding:14px;cursor:pointer;
  border-radius:var(--r-sm);background:rgba(255,255,255,.05);font-size:12.5px;font-weight:600}
.aswitch i{width:44px;height:26px;border-radius:999px;background:rgba(255,255,255,.12);position:relative;
  transition:background .22s var(--ease)}
.aswitch i:after{content:"";position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:50%;
  background:#fff;transition:right .22s var(--ease)}
.aswitch.on i{background:linear-gradient(135deg,var(--c1),var(--c2))}
.aswitch.on i:after{right:21px}

/* ═══ ورودِ نرمِ کارت‌ها — یک بار، کوتاه ═══ */
.grid.first .tile{animation:tileIn .3s var(--ease) backwards}
@keyframes tileIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.grid.first .tile:nth-child(1),.grid.first .tile:nth-child(2){animation-delay:.02s}
.grid.first .tile:nth-child(3),.grid.first .tile:nth-child(4){animation-delay:.06s}
.grid.first .tile:nth-child(5),.grid.first .tile:nth-child(6){animation-delay:.10s}
.grid.first .tile:nth-child(7),.grid.first .tile:nth-child(8){animation-delay:.14s}
.grid.first .tile:nth-child(n+9){animation-delay:.18s}
.grid:not(.first) .tile{animation:none}

/* محتوای شیت، یک بار و کوتاه بالا می‌آید */
.sheet.on .head,
.sheet.on #sField,
.sheet.on .total,
.sheet.on .go{animation:sheetRise .3s var(--ease) backwards}
.sheet.on .head  {animation-delay:.04s}
.sheet.on #sField{animation-delay:.08s}
.sheet.on .total {animation-delay:.12s}
.sheet.on .go    {animation-delay:.16s}
@keyframes sheetRise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ═══ احترام به «کم‌کردن حرکت» و جلوه‌ی کم ═══ */
body.fx0 .pg,body.fx0 .tile,body.fx0 .ring,
body.fx0 .sheet.on .head,body.fx0 .sheet.on #sField,
body.fx0 .sheet.on .total,body.fx0 .sheet.on .go{animation:none!important}
@media (prefers-reduced-motion:reduce){
  *{animation:none!important;transition-duration:.01ms!important}
}

/* ═══ صفحه‌های خیلی کوچک ═══ */
@media (max-width:360px){
  .wrap{padding-left:12px;padding-right:12px}
  .grid{gap:9px}
  .tile{min-height:162px;padding:13px 12px 12px}
  .purse .val{font-size:29px}
  .dock b{font-size:9px}
}
</style>
CSS;
}
