<?php
defined('NB_ROOT') || exit;

function maView($boot) {
    $e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $logo = maSplashLogoHtml();
    $font = maFontCss();
    return strtr(maViewTpl(), [
        '__TITLE__' => $e($boot['title'] ?? ''),
        '__TAG__'   => $e($boot['tagline'] ?? ''),
        '__LOGO__'  => $logo !== '' ? $logo : '<div class="core"><svg><use href="#i-sim"/></svg></div>',
        '__FONT__'  => $font !== ''
            ? (is_file(nbAsset('fonts/Vazirmatn.woff2'))
                ? '<link rel="preload" href="assets/fonts/Vazirmatn.woff2" as="font" type="font/woff2" crossorigin>' . "\n" : '')
              . "<style>\n" . $font . "</style>"
            : '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap">',
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG |
                                          JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        '__SPL__'   => (string)(int)($boot['spl'] ?? 8),
    ]);
}

function maViewTpl() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl" class="boot" style="--sd:__SPL__s">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover"><link rel="icon" href="data:,">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#000000">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
<script>(function(){try{var h=decodeURIComponent(String(location.hash||''));if(/(?:^|[&#])start_param=wheel(?:&|$)/.test(h)){var s=location.search;s=/[?&]app=/.test(s)?s.replace(/([?&])app=[^&]*/,'$1app=wheel'):(s?s+'&':'?')+'app=wheel';location.replace(location.pathname+s+location.hash);}}catch(e){}})();</script>
<script>(function(){try{var n=navigator,m=n.deviceMemory||8,c=n.hardwareConcurrency||8,sd=(n.connection&&n.connection.saveData),rm=(window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches);if(m<=3||c<=4||sd||rm)document.documentElement.classList.add('lite');}catch(e){}})();</script>
__FONT__
<style>
:root{
  --bg:#000;--bg2:#06080c;--card:#0b0e13;--card2:#10141b;--card3:#151a23;
  --line:rgba(255,255,255,.075);--line2:rgba(255,255,255,.12);
  --ink:#F4F6FA;--dim:#8B94A5;--dim2:#5F6878;
  --blue:#3B82F6;--blue2:#60A5FA;--blue3:#93C5FD;
  --green:#22C55E;--green2:#4ADE80;--green3:#86EFAC;
  --red:#F87171;
  --grad:linear-gradient(135deg,#3B82F6 0%,#22C55E 100%);
  --grad2:linear-gradient(135deg,#60A5FA 0%,#4ADE80 100%);
  --gl:rgba(11,15,22,.66);--gl2:rgba(16,21,30,.74);
  --r:18px;--z:.9;--safe:calc(env(safe-area-inset-bottom,0px) / .9);--top:0px;
  --st1:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='360' height='360'%3E%3Ccircle cx='163.0' cy='201.3' r='0.9' fill='%23DCFCE7' opacity='0.48'/%3E%3Ccircle cx='306.4' cy='69.6' r='0.83' fill='%23DCFCE7' opacity='0.49'/%3E%3Ccircle cx='284.3' cy='35.5' r='0.53' fill='%23fff' opacity='0.3'/%3E%3Ccircle cx='214.0' cy='143.0' r='0.62' fill='%23fff' opacity='0.62'/%3E%3Ccircle cx='223.8' cy='298.1' r='0.39' fill='%23fff' opacity='0.27'/%3E%3Ccircle cx='215.5' cy='279.0' r='0.55' fill='%23fff' opacity='0.55'/%3E%3Ccircle cx='186.8' cy='229.9' r='0.65' fill='%23BFDBFE' opacity='0.58'/%3E%3Ccircle cx='235.1' cy='146.8' r='0.68' fill='%23fff' opacity='0.72'/%3E%3Ccircle cx='254.0' cy='114.2' r='0.49' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='202.5' cy='40.4' r='0.41' fill='%23fff' opacity='0.4'/%3E%3Ccircle cx='343.1' cy='303.6' r='0.35' fill='%23fff' opacity='0.35'/%3E%3Ccircle cx='169.3' cy='351.0' r='0.59' fill='%23fff' opacity='0.29'/%3E%3Ccircle cx='279.1' cy='98.0' r='0.4' fill='%23BFDBFE' opacity='0.42'/%3E%3Ccircle cx='271.9' cy='44.0' r='0.5' fill='%23fff' opacity='0.3'/%3E%3Ccircle cx='167.5' cy='175.3' r='0.76' fill='%23DCFCE7' opacity='0.34'/%3E%3Ccircle cx='69.9' cy='262.6' r='0.43' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='142.6' cy='354.5' r='0.35' fill='%23DCFCE7' opacity='0.68'/%3E%3Ccircle cx='110.3' cy='317.0' r='0.48' fill='%23DCFCE7' opacity='0.45'/%3E%3Ccircle cx='230.5' cy='37.7' r='0.94' fill='%23fff' opacity='0.36'/%3E%3Ccircle cx='5.4' cy='219.3' r='0.85' fill='%23fff' opacity='0.44'/%3E%3Ccircle cx='34.1' cy='209.5' r='0.5' fill='%23fff' opacity='0.55'/%3E%3Ccircle cx='223.5' cy='47.3' r='0.7' fill='%23fff' opacity='0.67'/%3E%3Ccircle cx='310.5' cy='67.1' r='0.44' fill='%23DCFCE7' opacity='0.7'/%3E%3Ccircle cx='90.8' cy='69.6' r='0.79' fill='%23fff' opacity='0.72'/%3E%3Ccircle cx='246.6' cy='140.2' r='0.64' fill='%23fff' opacity='0.29'/%3E%3Ccircle cx='39.0' cy='15.8' r='0.93' fill='%23BFDBFE' opacity='0.37'/%3E%3Ccircle cx='93.5' cy='295.2' r='0.71' fill='%23fff' opacity='0.4'/%3E%3Ccircle cx='332.8' cy='349.9' r='0.43' fill='%23DCFCE7' opacity='0.49'/%3E%3Ccircle cx='220.7' cy='101.8' r='0.9' fill='%23fff' opacity='0.35'/%3E%3Ccircle cx='26.6' cy='148.5' r='0.5' fill='%23fff' opacity='0.27'/%3E%3Ccircle cx='133.3' cy='205.7' r='0.43' fill='%23BFDBFE' opacity='0.43'/%3E%3Ccircle cx='351.1' cy='235.9' r='0.76' fill='%23fff' opacity='0.54'/%3E%3Ccircle cx='212.0' cy='330.8' r='0.63' fill='%23fff' opacity='0.43'/%3E%3Ccircle cx='344.7' cy='9.6' r='0.73' fill='%23fff' opacity='0.49'/%3E%3Ccircle cx='115.5' cy='357.8' r='0.4' fill='%23fff' opacity='0.52'/%3E%3Ccircle cx='322.5' cy='264.4' r='0.77' fill='%23fff' opacity='0.65'/%3E%3Ccircle cx='127.3' cy='245.9' r='0.89' fill='%23BFDBFE' opacity='0.69'/%3E%3Ccircle cx='338.1' cy='12.8' r='0.65' fill='%23BFDBFE' opacity='0.26'/%3E%3Ccircle cx='137.0' cy='6.4' r='0.39' fill='%23fff' opacity='0.3'/%3E%3Ccircle cx='355.6' cy='315.2' r='0.79' fill='%23DCFCE7' opacity='0.44'/%3E%3Ccircle cx='165.0' cy='166.7' r='0.67' fill='%23DCFCE7' opacity='0.51'/%3E%3Ccircle cx='12.6' cy='216.1' r='0.64' fill='%23fff' opacity='0.37'/%3E%3Ccircle cx='179.0' cy='220.8' r='0.9' fill='%23fff' opacity='0.38'/%3E%3Ccircle cx='133.0' cy='53.0' r='0.72' fill='%23fff' opacity='0.51'/%3E%3Ccircle cx='237.0' cy='159.3' r='0.89' fill='%23fff' opacity='0.41'/%3E%3Ccircle cx='72.7' cy='155.4' r='0.83' fill='%23fff' opacity='0.71'/%3E%3Ccircle cx='138.9' cy='209.6' r='0.54' fill='%23BFDBFE' opacity='0.32'/%3E%3Ccircle cx='126.9' cy='320.9' r='0.37' fill='%23fff' opacity='0.28'/%3E%3Ccircle cx='294.6' cy='42.2' r='0.63' fill='%23BFDBFE' opacity='0.71'/%3E%3Ccircle cx='138.2' cy='187.1' r='0.75' fill='%23DCFCE7' opacity='0.61'/%3E%3Ccircle cx='163.1' cy='28.6' r='0.37' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='243.5' cy='102.1' r='0.56' fill='%23DCFCE7' opacity='0.57'/%3E%3Ccircle cx='8.8' cy='50.4' r='0.62' fill='%23fff' opacity='0.26'/%3E%3Ccircle cx='86.5' cy='52.2' r='0.38' fill='%23BFDBFE' opacity='0.56'/%3E%3C/svg%3E");
  --st2:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='520' height='520'%3E%3Cg opacity='.75'%3E%3Ccircle cx='284.8' cy='180.4' r='1.39' fill='%2393C5FD' opacity='0.72'/%3E%3Ccircle cx='204.7' cy='11.6' r='0.82' fill='%23fff' opacity='0.94'/%3E%3Ccircle cx='416.4' cy='46.1' r='1.15' fill='%23BBF7D0' opacity='0.97'/%3E%3Ccircle cx='433.4' cy='104.9' r='1.48' fill='%23BBF7D0' opacity='0.69'/%3E%3Ccircle cx='349.9' cy='354.8' r='1.17' fill='%23BBF7D0' opacity='0.82'/%3E%3Ccircle cx='156.0' cy='266.1' r='0.95' fill='%23BBF7D0' opacity='0.76'/%3E%3Ccircle cx='268.1' cy='390.2' r='1.33' fill='%23BBF7D0' opacity='0.83'/%3E%3Ccircle cx='207.0' cy='320.9' r='1.34' fill='%2393C5FD' opacity='0.64'/%3E%3Ccircle cx='444.4' cy='138.3' r='1.28' fill='%23BBF7D0' opacity='0.92'/%3E%3Ccircle cx='86.3' cy='324.6' r='1.1' fill='%23fff' opacity='0.71'/%3E%3Ccircle cx='167.6' cy='453.4' r='1.29' fill='%23fff' opacity='0.99'/%3E%3Ccircle cx='348.6' cy='9.9' r='1.33' fill='%23BBF7D0' opacity='0.76'/%3E%3Ccircle cx='471.8' cy='72.1' r='0.91' fill='%23fff' opacity='0.97'/%3E%3Ccircle cx='375.8' cy='394.0' r='1.02' fill='%23fff' opacity='0.6'/%3E%3Ccircle cx='491.7' cy='267.6' r='0.81' fill='%23BBF7D0' opacity='0.67'/%3E%3Ccircle cx='192.7' cy='35.9' r='1.31' fill='%23fff' opacity='0.7'/%3E%3Ccircle cx='67.5' cy='60.2' r='0.92' fill='%23BBF7D0' opacity='0.61'/%3E%3Ccircle cx='448.7' cy='106.1' r='1.24' fill='%23fff' opacity='0.95'/%3E%3Ccircle cx='488.7' cy='119.1' r='1.38' fill='%23fff' opacity='0.6'/%3E%3Ccircle cx='411.7' cy='297.7' r='1.02' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='462.8' cy='214.6' r='1.2' fill='%23BBF7D0' opacity='0.67'/%3E%3Ccircle cx='349.6' cy='226.4' r='1.06' fill='%23fff' opacity='0.8'/%3E%3C/g%3E%3C/svg%3E");
  color-scheme:dark
}
html.fs{--top:calc((var(--tg-content-safe-area-inset-top,var(--tg-safe-area-inset-top,34px)) + 46px) / .9)}
html{zoom:.9}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html,body{background:var(--bg);color:var(--ink);min-height:100%}
body{font-family:Vazirmatn,Vazir,IRANSans,system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;font-size:13px;line-height:1.7;
  overflow-x:hidden;-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
input,textarea{-webkit-user-select:text;user-select:text;font-family:inherit}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
img{max-width:100%;-webkit-user-drag:none}
svg{display:block}
.ltr{direction:ltr;unicode-bidi:isolate}
.hid{display:none!important}

.sky{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;contain:strict;background:
  radial-gradient(120vw 62vh at 105vw -12vh,rgba(37,99,235,.30),transparent 62%),
  radial-gradient(95vw 55vh at -12vw 108vh,rgba(16,185,129,.17),transparent 64%),
  radial-gradient(70vw 40vh at 30vw 48vh,rgba(59,130,246,.07),transparent 70%),#000}
.sky i{position:absolute;display:block}
.sky .neb{left:-40%;right:-40%;top:24%;height:34%;transform:rotate(-26deg);
  background:radial-gradient(50% 50% at 50% 50%,rgba(147,197,253,.11),rgba(79,70,229,.05) 42%,transparent 72%)}
.sky .st1{inset:0;background:var(--st1) 0 -8vh/360px 360px repeat}
.sky .st2{inset:0;background:var(--st2) 90px 40px/520px 520px repeat}
.sky .pl{right:-38vw;bottom:-62vw;width:96vw;height:96vw;border-radius:50%;
  background:radial-gradient(circle at 32% 22%,rgba(96,165,250,.34),rgba(30,58,138,.35) 34%,rgba(3,7,18,.95) 62%);
  box-shadow:0 0 70px 8px rgba(59,130,246,.16),inset 6px 8px 26px rgba(147,197,253,.22)}
.cmt{position:absolute;top:16vh;left:-45vw;width:190vw;height:2px;transform:rotate(15deg);transform-origin:0 0}
.cmt i{left:0;top:0;width:130px;height:2px;border-radius:2px;opacity:0;
  background:linear-gradient(90deg,rgba(96,165,250,0),rgba(96,165,250,.45) 55%,rgba(255,255,255,.95));animation:cmt 17s linear infinite;will-change:transform,opacity}
.cmt i:after{content:"";position:absolute;right:-3px;top:-3px;width:8px;height:8px;border-radius:50%;background:#fff;
  box-shadow:0 0 8px 2px rgba(191,219,254,.95),0 0 20px 6px rgba(59,130,246,.45)}
.sky .tw{position:absolute;display:block;width:3px;height:3px;border-radius:50%;background:#fff;box-shadow:0 0 6px 1px rgba(191,219,254,.8);opacity:.2;animation:twk 3s ease-in-out infinite}
.sky .tw:nth-of-type(1){left:12%;top:9%}
.sky .tw:nth-of-type(2){left:78%;top:17%;animation-delay:-.7s}
.sky .tw:nth-of-type(3){left:33%;top:31%;animation-delay:-1.4s;background:#BBF7D0}
.sky .tw:nth-of-type(4){left:88%;top:44%;animation-delay:-2.1s}
.sky .tw:nth-of-type(5){left:8%;top:57%;animation-delay:-.35s;background:#BFDBFE}
.sky .tw:nth-of-type(6){left:61%;top:66%;animation-delay:-1.05s}
.sky .tw:nth-of-type(7){left:24%;top:81%;animation-delay:-1.75s}
.sky .tw:nth-of-type(8){left:70%;top:90%;animation-delay:-2.45s;background:#BBF7D0}
@keyframes twk{0%,100%{opacity:.15;transform:scale(.6)}50%{opacity:1;transform:scale(1.25)}}
@keyframes cmt{
  0%{transform:translate3d(0,0,0);opacity:0}
  3%{opacity:1}
  40%{transform:translate3d(175vw,0,0);opacity:1}
  43%{transform:translate3d(183vw,0,0);opacity:0}
  50%{transform:translate3d(183vw,0,0) scaleX(-1);opacity:0}
  53%{opacity:1}
  90%{transform:translate3d(8vw,0,0) scaleX(-1);opacity:1}
  93%{transform:translate3d(0,0,0) scaleX(-1);opacity:0}
  100%{transform:translate3d(0,0,0) scaleX(1);opacity:0}}

html.boot .app,html.boot .dock,html.boot .fab{opacity:0}
html.boot .hdr{transform:translate3d(0,-16px,0)}
html.boot .dock nav{transform:translate3d(0,34px,0)}
html.boot .pg.on>*{opacity:0;transform:perspective(900px) rotateX(14deg) translate3d(0,30px,0) scale(.96)}
html.in .app,html.in .dock,html.in .fab{transition:opacity .45s ease}
html.in .hdr,html.in .dock nav{transition:transform .7s cubic-bezier(.2,.85,.25,1)}
html.in .pg.on>*{transition:opacity .55s ease,transform .8s cubic-bezier(.2,.85,.25,1)}
html.in .pg.on>:nth-child(2){transition-delay:.05s}
html.in .pg.on>:nth-child(3){transition-delay:.1s}
html.in .pg.on>:nth-child(4){transition-delay:.15s}
html.in .pg.on>:nth-child(5){transition-delay:.2s}
html.in .pg.on>:nth-child(6){transition-delay:.25s}
html.in .pg.on>:nth-child(n+7){transition-delay:.3s}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:calc(var(--top) + 8px) 12px calc(96px + var(--safe));overflow-x:clip}

.hdr{position:sticky;top:calc(var(--top) + 6px);z-index:30;display:flex;align-items:center;gap:10px;padding:8px 9px;margin-bottom:12px;
  border-radius:20px;border:1px solid var(--line);background:rgba(8,10,14,.94);
  box-shadow:0 12px 30px -18px rgba(0,0,0,.9),inset 0 1px 0 rgba(255,255,255,.05)}
.hdr:before{content:"";position:fixed;left:0;right:0;top:0;height:calc(var(--top) + 6px);z-index:-1;pointer-events:none;
  background:var(--st2) 90px 40px/520px 520px repeat,var(--st1) 0 -8vh/360px 360px repeat,
  radial-gradient(120vw 62vh at 105vw -12vh,rgba(37,99,235,.30),transparent 62%),
  radial-gradient(95vw 55vh at -12vw 108vh,rgba(16,185,129,.17),transparent 64%),
  radial-gradient(70vw 40vh at 30vw 48vh,rgba(59,130,246,.07),transparent 70%),#000}
.ava{width:38px;height:38px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  background:var(--grad);color:#fff;font-weight:900;font-size:15px;box-shadow:0 0 0 2px var(--bg),0 0 0 3px rgba(96,165,250,.45)}
.ava img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim)}
.dot{position:relative;display:inline-block;flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:var(--green)}
.dot:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;opacity:0;animation:ping 2s ease-out infinite}
@keyframes ping{0%{transform:scale(1);opacity:.6}75%,100%{transform:scale(2.9);opacity:0}}
.bal{display:flex;align-items:center;gap:6px;padding:6px 11px;border-radius:13px;border:1px solid rgba(96,165,250,.28);
  background:linear-gradient(135deg,rgba(59,130,246,.16),rgba(34,197,94,.10));font-weight:900;font-size:12px}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.ib{position:relative;width:36px;height:36px;border-radius:12px;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.04)}
.ib svg{width:18px;height:18px}
.ib .bd{position:absolute;top:-4px;inset-inline-end:-4px;min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:var(--green);
  color:#02140a;font-size:9.5px;font-weight:900;display:none;place-items:center;border:2px solid var(--bg)}
.ib.has .bd{display:grid}

.pg{display:none}
.pg.on{display:block;animation:pin .28s cubic-bezier(.2,.8,.2,1)}
@keyframes pin{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.sec{margin:16px 2px 9px;display:flex;align-items:center;gap:8px}
.sec h3{font-size:13.5px;font-weight:900;flex:1;display:flex;align-items:center;gap:7px}
.sec h3 svg{width:17px;height:17px;color:var(--blue2)}
.sec a,.sec button{font-size:11px;font-weight:700;color:var(--blue2)}
.sec>button,.sec>a{display:inline-flex;align-items:center;min-height:34px;padding:0 6px;margin:-10px -6px}

.card{position:relative;border-radius:var(--r);border:1px solid var(--line);background:var(--card);padding:14px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.045)}
.glow{position:relative;border-radius:22px;padding:1.3px;background:linear-gradient(125deg,rgba(96,165,250,.95),rgba(59,130,246,.35) 30%,rgba(34,197,94,.3) 65%,rgba(74,222,128,.95))}
.glow>.in{border-radius:21px;background:radial-gradient(120% 90% at 100% 0,rgba(59,130,246,.22),transparent 60%),
  radial-gradient(90% 80% at 0 100%,rgba(34,197,94,.16),transparent 60%),rgba(5,8,13,.8);padding:18px 16px;overflow:hidden;position:relative}
.glow>.in:before{content:"";position:absolute;left:0;right:0;top:0;height:46%;background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,0));pointer-events:none}
.glow{box-shadow:0 26px 50px -26px rgba(37,99,235,.75),0 10px 24px -14px rgba(0,0,0,.9)}
.hero .orb{position:absolute;left:4px;top:14px;width:116px;height:116px;border-radius:50%;border:1px solid rgba(147,197,253,.32);
  box-shadow:0 0 18px rgba(59,130,246,.25);transform:rotateX(74deg);animation:orb 10s linear infinite;will-change:transform}
.hero .orb b{position:absolute;top:-4px;left:calc(50% - 4px);width:8px;height:8px;border-radius:50%;background:#86EFAC;box-shadow:0 0 10px 2px rgba(74,222,128,.8)}
@keyframes orb{to{transform:rotateX(74deg) rotateZ(360deg)}}
.qk{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:12px}
.qk button{display:flex;flex-direction:column;align-items:center;gap:7px;padding:11px 4px 10px;border-radius:17px;border:1px solid transparent;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,0) 50%) padding-box,linear-gradient(var(--gl),var(--gl)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.24),rgba(255,255,255,.05) 40%,rgba(96,165,250,.3)) border-box;
  box-shadow:0 14px 24px -18px rgba(0,0,0,.95);transition:transform .15s}
.qk button:active{transform:translateY(2px) scale(.96)}
.qk b{font-size:10.5px;font-weight:800;white-space:nowrap}
.emp{flex:1;display:flex;align-items:center;gap:12px;border-radius:17px;border:1px dashed rgba(147,197,253,.25);background:var(--gl);padding:14px}
.emp b{display:block;font-size:12.5px;font-weight:900}
.emp small{display:block;font-size:10.5px;color:var(--dim)}

.hero h1{font-size:21px;font-weight:900;line-height:1.45;letter-spacing:-.3px}
.hero h1 span{background:var(--grad2);-webkit-background-clip:text;background-clip:text;color:transparent}
.hero p{color:var(--dim);font-size:11.5px;margin-top:5px}
.hero .ph3{position:absolute;inset-inline-start:-6px;top:4px;width:124px;height:136px;pointer-events:none;perspective:600px}
.hero .ph{width:100%;height:100%;transform:rotateY(-16deg) rotateX(6deg);animation:phf 5s ease-in-out infinite;will-change:transform}
@keyframes phf{0%,100%{transform:rotateY(-16deg) rotateX(6deg) translateY(0)}50%{transform:rotateY(-10deg) rotateX(4deg) translateY(-5px)}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
.hero .txt{padding-inline-start:114px;min-height:112px}
.live{display:inline-flex;align-items:center;gap:7px;padding:5px 10px;border-radius:30px;background:rgba(34,197,94,.11);
  border:1px solid rgba(34,197,94,.3);color:var(--green3);font-size:10.5px;font-weight:800;margin-bottom:9px}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}
.stat{border-radius:14px;background:rgba(255,255,255,.035);border:1px solid var(--line);padding:9px 8px;text-align:center}
.stat b{display:block;font-size:15px;font-weight:900}
.stat small{font-size:9.5px;color:var(--dim)}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px 16px;border-radius:15px;font-weight:900;font-size:13.5px;
  color:#fff;background:var(--grad);box-shadow:0 10px 26px -12px rgba(59,130,246,.75);transition:transform .15s,opacity .15s}
.btn:active{transform:scale(.97)}
.btn svg{width:18px;height:18px}
.btn[disabled]{opacity:.55;pointer-events:none}
.btn.gh{background:rgba(255,255,255,.055);border:1px solid var(--line2);box-shadow:none;color:var(--ink)}

.btn.bl{background:linear-gradient(135deg,#2563EB,#3B82F6)}
.btn.rd{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.35);color:#FCA5A5;box-shadow:none}
.btn.sm{padding:9px 12px;font-size:12px;border-radius:12px;width:auto}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.mt{margin-top:12px}.mt2{margin-top:8px}

.hs{display:flex;gap:9px;overflow-x:auto;scroll-snap-type:x mandatory;padding:2px 2px 6px;margin:0 -12px;padding-inline:12px;scrollbar-width:none}
.hs::-webkit-scrollbar{display:none}
.cc{flex:0 0 auto;width:112px;scroll-snap-align:start;border-radius:17px;border:1px solid var(--line);background:var(--card);padding:12px 10px;text-align:center;position:relative}
.cc:active,.co:active,.crow:active{transform:scale(.97)}
.cc .fl{width:50px;height:50px;font-size:28px;margin:0 auto}
.cc b{display:block;font-size:12px;font-weight:800;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cc small{display:block;font-size:10px;color:var(--dim)}
.cc .pr{margin-top:6px;font-size:11px;font-weight:900;color:var(--green2)}
.hot{position:absolute;top:7px;inset-inline-end:7px;width:22px;height:22px;border-radius:8px;display:grid;place-items:center;
  background:linear-gradient(145deg,#4ADE80,#16A34A);color:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),0 6px 12px -6px rgba(34,197,94,.9)}
.hot svg{width:13px;height:13px}

.lv{display:flex;align-items:center;gap:11px;border-radius:17px;border:1px solid rgba(96,165,250,.28);padding:11px 12px;margin-bottom:8px;
  background:linear-gradient(135deg,rgba(59,130,246,.12),rgba(34,197,94,.06)),var(--card)}
.lv.dn{border-color:rgba(34,197,94,.4)}
.lv .fl{width:40px;height:40px;font-size:22px}
.lv .mid{flex:1;min-width:0}
.lv b{display:block;font-size:14px;font-weight:900;direction:ltr;text-align:right}
.lv small{font-size:10.5px;color:var(--dim)}
.cd{font-size:12px;font-weight:900;color:var(--blue3);direction:ltr;min-width:44px;text-align:center}
.lv.dn .cd{color:var(--green2)}


.feed{overflow:hidden;contain:layout paint;height:46px;border-radius:15px;border:1px solid var(--line);background:var(--card);position:relative}
.feed .fi{position:absolute;inset:0;display:flex;align-items:center;gap:9px;padding:0 13px;font-size:11.5px;transition:transform .5s,opacity .5s}
.feed .fi.out{transform:translateY(-100%);opacity:0}
.feed .fi.in{transform:translateY(100%);opacity:0}
.feed .fi b{font-weight:800}
.feed .fi span{margin-inline-start:auto;color:var(--dim);font-size:10px}
.feed .fi .fb{width:30px;height:30px;font-size:17px}
.feed .fi .t3{width:30px;height:30px;border-radius:10px}

.note{display:flex;gap:10px;align-items:flex-start;border-radius:16px;border:1px solid rgba(34,197,94,.22);background:rgba(34,197,94,.06);padding:11px 12px;font-size:11px;color:var(--green3);line-height:1.8}
.note svg{flex:0 0 auto;width:19px;height:19px;margin-top:1px}
.note.bl{border-color:rgba(96,165,250,.25);background:rgba(59,130,246,.07);color:var(--blue3)}

.ad{display:flex;align-items:center;gap:12px;border-radius:18px;padding:13px;border:1px solid rgba(96,165,250,.25);
  background:radial-gradient(90% 120% at 0 0,rgba(34,197,94,.14),transparent 60%),radial-gradient(90% 120% at 100% 100%,rgba(59,130,246,.16),transparent 60%),var(--card)}
.ad .gmw{width:50px;height:50px;flex:0 0 auto;border-radius:16px;display:grid;place-items:center;
  background:radial-gradient(70% 70% at 35% 25%,rgba(147,197,253,.28),rgba(15,23,42,.9) 70%);box-shadow:inset 0 1px 0 rgba(255,255,255,.2),inset 0 -4px 10px rgba(0,0,0,.5),0 10px 20px -10px rgba(59,130,246,.8)}
.ad .gm{width:38px;height:38px;animation:bob 3.4s ease-in-out infinite}
.ad b{display:block;font-size:13px;font-weight:900}
.ad small{font-size:10.5px;color:var(--dim)}
.ad .mid{flex:1;min-width:0}

.srch{display:flex;align-items:center;gap:8px;border-radius:15px;border:1px solid var(--line2);background:var(--card);padding:0 12px}
.srch svg{width:17px;height:17px;color:var(--dim)}
.srch input{flex:1;background:none;border:0;outline:0;color:var(--ink);font-size:13px;padding:12px 0}
.chips{display:flex;gap:7px;overflow-x:auto;margin:10px -12px 4px;padding:0 12px;scrollbar-width:none}
.chips::-webkit-scrollbar{display:none}
.chip{flex:0 0 auto;padding:7px 13px;border-radius:12px;border:1px solid var(--line);background:var(--card);font-size:11.5px;font-weight:800;color:var(--dim)}
.chip.on{color:#fff;border-color:transparent;background:var(--grad)}
.more{width:100%;height:46px;margin-top:6px;border-radius:15px;border:1px dashed var(--line2);background:var(--card);color:var(--ink);font-size:12.5px;font-weight:900}
.bdg.hotb{background:rgba(245,158,11,.16);color:#FCD34D;border-color:rgba(252,211,77,.35)}
.prods{display:grid;grid-template-columns:repeat(var(--n,3),1fr);gap:8px;margin-bottom:10px}
.prods button{position:relative;display:flex;flex-direction:column;align-items:center;gap:5px;padding:11px 6px 10px;border-radius:17px;border:1px solid var(--line);
  background:var(--card);color:var(--dim);font-size:12px;font-weight:900;transition:transform .15s,border-color .2s,color .2s}
.prods button i{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;font-style:normal;font-size:19px;background:rgba(255,255,255,.06)}
.prods button i svg{width:19px;height:19px}
.prods button small{font-size:9.5px;font-weight:700;color:var(--dim2)}
.prods button.on{color:#fff;border-color:transparent;transform:translateY(-1px)}
.prods button.on.telegram{background:linear-gradient(145deg,rgba(42,171,238,.34),rgba(42,171,238,.1));box-shadow:0 10px 22px -14px #2AABEE,inset 0 0 0 1px rgba(125,211,252,.5)}
.prods button.on.instagram{background:linear-gradient(145deg,rgba(221,42,123,.34),rgba(129,52,175,.16));box-shadow:0 10px 22px -14px #DD2A7B,inset 0 0 0 1px rgba(255,95,162,.5)}
.prods button.on.whatsapp{background:linear-gradient(145deg,rgba(37,211,102,.32),rgba(37,211,102,.08));box-shadow:0 10px 22px -14px #25D366,inset 0 0 0 1px rgba(74,222,128,.5)}
.prods button.telegram i{background:rgba(42,171,238,.16)}.prods button.instagram i{background:rgba(221,42,123,.16)}.prods button.whatsapp i{background:rgba(37,211,102,.16)}
.prods button small b{color:var(--ink)}
.clist{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
.clist>.more,.clist>.empty{grid-column:1/-1}
.ctl{position:relative;overflow:hidden;isolation:isolate;display:flex;flex-direction:column;align-items:center;min-width:0;width:100%;text-align:center;
  padding:14px 10px 10px;border-radius:20px;border:1px solid transparent;transition:transform .12s;
  background:radial-gradient(120% 70% at 50% 0%,rgba(59,130,246,.2),transparent 62%) padding-box,linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 50%) padding-box,
    linear-gradient(var(--gl),var(--gl)) padding-box,linear-gradient(155deg,rgba(255,255,255,.24),rgba(255,255,255,.05) 35%,rgba(74,222,128,.3)) border-box;
  box-shadow:0 14px 26px -20px rgba(0,0,0,.95)}
.ctl:active{transform:scale(.97)}
.ctl.h{background:radial-gradient(120% 70% at 50% 0%,rgba(245,158,11,.2),transparent 62%) padding-box,linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 50%) padding-box,
    linear-gradient(var(--gl),var(--gl)) padding-box,linear-gradient(155deg,rgba(252,211,77,.5),rgba(255,255,255,.05) 40%,rgba(74,222,128,.3)) border-box}
.ctl .bd{position:absolute;top:8px;inset-inline-start:8px;font-size:9px;font-weight:900;padding:2px 7px;border-radius:8px;background:rgba(34,197,94,.16);color:var(--green3);border:1px solid rgba(74,222,128,.3)}
.ctl.h .bd{background:rgba(245,158,11,.16);color:#FCD34D;border-color:rgba(252,211,77,.35)}
.ctl .fl{width:52px;height:52px;font-size:30px}
.ctl .tx{display:block;min-width:0;max-width:100%;margin-top:7px}
.ctl .tx b{display:block;font-size:13.5px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctl .tx small{display:block;font-size:10px;color:var(--dim);white-space:nowrap}
.ctl .pz{display:flex;align-items:baseline;justify-content:center;gap:4px;margin-top:7px;white-space:nowrap}
.ctl .pz small{font-size:10px;color:var(--dim);font-weight:700}
.ctl .pz b{font-size:18px;font-weight:900;background:linear-gradient(90deg,#fff 10%,#4ADE80);-webkit-background-clip:text;background-clip:text;color:transparent}
.ctl .go{display:flex;align-items:center;justify-content:center;gap:3px;width:100%;height:36px;margin-top:9px;border-radius:12px;background:var(--grad);color:#fff;
  font-size:12px;font-weight:900;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 0 -2px 0 rgba(0,0,0,.18),0 8px 18px -10px rgba(59,130,246,.9)}
.ctl .go svg{width:14px;height:14px}
.ctl.w{grid-column:1/-1;flex-direction:row;text-align:right;gap:11px;padding:12px}
.ctl.w .bd{top:6px}
.ctl.w .fl{width:46px;height:46px;font-size:26px}
.ctl.w .tx{flex:1;margin-top:0}
.ctl.w .pz{flex-direction:column;align-items:flex-end;gap:0;margin-top:0}
.ctl.w .go{width:auto;padding:0 14px;margin-top:0}
.chev{width:16px;height:16px;color:var(--dim2)}

.co{display:flex;align-items:center;gap:11px;border-radius:16px;border:1px solid var(--line);background:var(--card2);padding:11px 12px;margin-bottom:8px;transition:transform .12s}
.opg{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.opg .co{flex-direction:column;align-items:stretch;gap:8px;margin:0;padding:12px 11px 11px;text-align:center}
.opg .co .mid b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.opg .co .mid .bdg{display:inline-block;margin:4px 0 0}
.opg .co .pbtn{display:block;text-align:center;padding:9px 8px}
.opg .co.w{grid-column:1/-1;flex-direction:row;align-items:center;text-align:right}
.opg .co.w .mid b{display:inline-block;max-width:68%;vertical-align:middle}
.opg .co.w .mid .bdg{margin:0;margin-inline-start:6px;vertical-align:middle}
.co .mid{flex:1;min-width:0}
.co b{font-size:13px;font-weight:800}
.co small{display:block;font-size:10px;color:var(--dim)}
.bdg{display:inline-block;margin-inline-start:6px;font-size:9.5px;font-weight:900;padding:1px 7px;border-radius:8px;background:rgba(34,197,94,.15);color:var(--green3);vertical-align:2px}
.bdg.b{background:rgba(59,130,246,.16);color:var(--blue3)}
.pbtn{padding:8px 12px;border-radius:12px;background:var(--grad);color:#fff;font-weight:900;font-size:12px;white-space:nowrap}

.empty{text-align:center;padding:34px 16px;color:var(--dim)}
.empty svg{width:64px;height:64px;margin:0 auto 10px;color:var(--dim2)}
.empty b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:3px}
.sk{position:relative;overflow:hidden;border-radius:16px;height:62px;background:var(--card);margin-bottom:8px}
.sk:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);transform:translate3d(-100%,0,0);animation:sh 1.3s infinite}
@keyframes sh{to{transform:translate3d(100%,0,0)}}

.seg{display:flex;gap:4px;padding:4px;border-radius:14px;border:1px solid var(--line);background:var(--card);margin-bottom:10px}
.seg button{flex:1;padding:8px 4px;border-radius:10px;font-size:11.5px;font-weight:800;color:var(--dim)}
.seg button.on{background:var(--card3);color:#fff;box-shadow:inset 0 0 0 1px var(--line2)}

.or{border-radius:17px;border:1px solid var(--line);background:var(--card);padding:12px;margin-bottom:8px;content-visibility:auto;contain-intrinsic-size:auto 110px}
.or .t{display:flex;align-items:center;gap:9px}
.or .fl{width:38px;height:38px;font-size:21px}
.or .t .mid{flex:1;min-width:0}
.or .t b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .t small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;font-size:10px;font-weight:900;padding:3px 9px;border-radius:9px;background:rgba(255,255,255,.07);color:var(--dim)}
.pill.w{background:rgba(59,130,246,.15);color:var(--blue3)}
.pill.d{background:rgba(34,197,94,.15);color:var(--green3)}
.or .kv{display:flex;gap:8px;margin-top:10px}
.or .kv div{flex:1;border-radius:12px;background:rgba(255,255,255,.035);border:1px solid var(--line);padding:7px 9px;min-width:0}
.or .kv small{display:block;font-size:9.5px;color:var(--dim)}
.or .kv b{font-size:12.5px;font-weight:900;direction:ltr;display:block;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.or .kv .kc b{color:var(--green2);letter-spacing:1.5px}

.fld{margin-top:10px}
.fld label{display:block;font-size:11px;color:var(--dim);margin:0 3px 5px;font-weight:700}
.inp{width:100%;border-radius:14px;border:1px solid var(--line2);background:var(--card);color:var(--ink);padding:12px 13px;font-size:14px;outline:0;transition:border-color .15s}
.inp:focus{border-color:rgba(96,165,250,.6)}
textarea.inp{resize:none;min-height:96px;line-height:1.8;font-size:13px}
.inw{position:relative}
.inw .sf{position:absolute;inset-inline-end:13px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--dim);pointer-events:none}
.kvr{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px dashed var(--line);font-size:12px}
.kvr:last-child{border-bottom:0}
.kvr span{color:var(--dim)}
.kvr b{font-weight:900}
.kvr b.g{color:var(--green2)}
.cpy{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:10px;border:1px solid var(--line2);background:rgba(255,255,255,.04);font-size:11px;font-weight:800}
.cpy svg{width:14px;height:14px}

.prof{display:flex;align-items:center;gap:13px}
.prof .ava{width:58px;height:58px;font-size:22px}
.prof b{display:block;font-size:15px;font-weight:900}
.prof small{color:var(--dim);font-size:11px}
.grid4{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
.gc{border-radius:16px;border:1px solid var(--line);background:var(--card);padding:12px}
.gc small{display:block;color:var(--dim);font-size:10.5px}
.gc b{font-size:16px;font-weight:900}
.gc b em{font-style:normal;font-size:10px;color:var(--dim);font-weight:700;margin-inline-start:3px}
.menu{border-radius:18px;border:1px solid var(--line);background:var(--card);overflow:hidden}
.mi{display:flex;align-items:center;gap:11px;width:100%;padding:13px 14px;border-bottom:1px solid var(--line);font-size:12.5px;font-weight:800;text-align:right}
.mi:last-child{border-bottom:0}

.mi span{flex:1}
.mi .bdn{font-size:10px;font-weight:900;padding:1px 7px;border-radius:8px;background:var(--green);color:#02140a}
.ref{border-radius:18px;padding:14px;border:1px dashed rgba(96,165,250,.45);background:rgba(59,130,246,.06)}
.ref .lnk{margin-top:9px;display:flex;align-items:center;gap:8px;border-radius:12px;background:rgba(0,0,0,.35);border:1px solid var(--line);padding:9px 10px;font-size:11px}
.ref .lnk span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;direction:ltr;text-align:left;color:var(--blue3)}

.ntf{border-radius:16px;border:1px solid var(--line);background:var(--card);padding:12px;margin-bottom:8px;content-visibility:auto;contain-intrinsic-size:auto 96px}
.ntf.new{border-color:rgba(34,197,94,.4)}
.ntf .h{display:flex;align-items:center;gap:8px}
.ntf .h .t3{width:32px;height:32px;border-radius:11px}
.ntf .h b{flex:1;font-size:12.5px;font-weight:900}
.ntf .h time{font-size:9.5px;color:var(--dim)}
.ntf p{font-size:11.5px;color:var(--dim);white-space:pre-line;margin-top:5px;line-height:1.85}
.ntf p bdi{display:inline-block;direction:ltr}
.ntf .cps{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}

.dock{position:fixed;left:0;right:0;bottom:0;z-index:40;padding:0 10px calc(10px + var(--safe));pointer-events:none}
.dock nav{pointer-events:auto;max-width:460px;margin:0 auto;display:flex;align-items:stretch;gap:2px;padding:6px;border-radius:24px;
  border:1px solid var(--line2);background:rgba(9,11,15,.97);box-shadow:0 18px 40px -12px rgba(0,0,0,.95),inset 0 1px 0 rgba(255,255,255,.06)}
.dock button{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:7px 2px 6px;border-radius:17px;color:var(--dim);font-size:9.5px;font-weight:800;position:relative}
.dock button svg{width:21px;height:21px}
.dock button.on{color:#fff;background:linear-gradient(135deg,rgba(59,130,246,.22),rgba(34,197,94,.16))}
.dock button.on svg{color:var(--blue2)}
.dock .ctr{flex:0 0 auto;width:58px;margin:-18px 2px 0;padding:0;background:none!important}
.dock .ctr span{position:relative;width:54px;height:54px;border-radius:19px;display:grid;place-items:center;
  background:radial-gradient(80% 80% at 35% 22%,#2b4a86 0%,#0d1630 55%,#060a14 100%) padding-box,linear-gradient(135deg,#60A5FA,#22C55E) border-box;
  border:1.6px solid transparent;box-shadow:inset 0 1px 0 rgba(255,255,255,.28),inset 0 -6px 12px rgba(0,0,0,.55),0 12px 26px -8px rgba(59,130,246,.75),0 0 0 4px var(--bg);transition:transform .2s}
.dock .ctr span:after{content:"";position:absolute;inset:2px 6px 55% 6px;border-radius:14px 14px 50% 50%;background:linear-gradient(180deg,rgba(255,255,255,.2),rgba(255,255,255,0));pointer-events:none}
.dock .ctr span svg{width:34px;height:34px;filter:drop-shadow(0 3px 5px rgba(0,0,0,.5))}
.dock .ctr.on span{transform:translateY(-2px) scale(1.04)}
.dock .ctr em{font-style:normal;margin-top:3px}
.dock .bd{position:absolute;top:4px;inset-inline-end:calc(50% - 19px);min-width:15px;height:15px;border-radius:8px;background:var(--green);color:#02140a;font-size:9px;font-weight:900;display:none;place-items:center;padding:0 4px}
.dock .has .bd{display:grid}

.fab{position:fixed;left:14px;bottom:calc(92px + var(--safe));z-index:41;width:50px;height:50px;border-radius:50%;display:grid;place-items:center;
  background:var(--grad);color:#fff;box-shadow:0 12px 26px -8px rgba(59,130,246,.8),0 0 0 3px rgba(0,0,0,.6);transition:transform .2s}
.fab:active{transform:scale(.92)}
.fab svg{width:25px;height:25px}
.fab .bd{position:absolute;top:-3px;right:-3px;min-width:19px;height:19px;border-radius:10px;background:#fff;color:#0a0a0a;font-size:10px;font-weight:900;display:none;place-items:center;padding:0 5px;border:2px solid var(--bg)}
.fab.has .bd{display:grid}
.fab.has:after{content:"";position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(74,222,128,.7);animation:rp 1.6s infinite}
@keyframes rp{from{transform:scale(.9);opacity:1}to{transform:scale(1.35);opacity:0}}
html.nofab .fab,.fab{display:none!important}

.ov{position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.66);opacity:0;pointer-events:none;transition:opacity .22s}
.ov.on{opacity:1;pointer-events:auto}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:61;max-width:480px;margin:0 auto;max-height:88vh;display:flex;flex-direction:column;
  border-radius:26px 26px 0 0;border:1px solid var(--line2);border-bottom:0;background:#090b10;transform:translateY(105%);transition:transform .3s cubic-bezier(.2,.85,.25,1);
  box-shadow:0 -20px 50px -10px rgba(0,0,0,.9)}
.sh.on{transform:none}
.sh .grab{width:40px;height:4px;border-radius:3px;background:rgba(255,255,255,.18);margin:9px auto 4px;flex:0 0 auto}
.sh .shd{display:flex;align-items:center;gap:10px;padding:6px 16px 10px;flex:0 0 auto}
.sh .shd b{flex:1;font-size:14.5px;font-weight:900}
.sh .shd .fl{width:44px;height:44px;font-size:25px}
.sh .shd small{display:block;color:var(--dim);font-size:10.5px;font-weight:600}
.sh .x{width:32px;height:32px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.06)}
.sh .x svg{width:15px;height:15px}
.sh .bdy{overflow-y:auto;padding:4px 16px calc(18px + var(--safe));-webkit-overflow-scrolling:touch;overscroll-behavior:contain}

.pn{text-align:center;padding:4px 0 2px}
.pn small{color:var(--dim);font-size:11px}
.pn .num.off{opacity:.45;background:none;color:var(--dim)}
.pn .num{font-size:25px;font-weight:900;direction:ltr;letter-spacing:1px;margin:4px 0 8px;background:var(--grad2);-webkit-background-clip:text;background-clip:text;color:transparent}
.ring{position:relative;width:132px;height:132px;margin:10px auto 6px}
.ring svg{width:132px;height:132px;transform:rotate(-90deg)}
.ring circle{fill:none;stroke-width:8}
.ring .tr{stroke:rgba(255,255,255,.07)}
.ring .pr{stroke:url(#rg);stroke-linecap:round;transition:stroke-dashoffset 1s linear}
.ring .c{position:absolute;inset:0;display:grid;place-items:center;text-align:center}
.ring .c b{display:block;font-size:22px;font-weight:900;direction:ltr}
.ring .c small{font-size:10px;color:var(--dim)}
@keyframes rd{from{transform:scale(.35);opacity:1}to{transform:scale(1);opacity:0}}
.code{text-align:center;border-radius:20px;padding:16px;border:1px solid rgba(34,197,94,.45);background:radial-gradient(100% 120% at 50% 0,rgba(34,197,94,.2),transparent 70%),#07120b;margin:8px 0}
.code small{color:var(--green3);font-size:11px;font-weight:800}
.code b{display:block;font-size:34px;font-weight:900;letter-spacing:8px;direction:ltr;color:#fff;margin:4px 0 8px;text-shadow:0 0 22px rgba(74,222,128,.45)}

.toast{position:fixed;left:50%;top:calc(var(--top) + 14px);z-index:90;transform:translate(-50%,-160%);visibility:hidden;max-width:92vw;width:max-content;
  padding:11px 16px;border-radius:15px;background:#12161e;border:1px solid var(--line2);font-size:12px;font-weight:800;
  box-shadow:0 16px 40px -10px rgba(0,0,0,.9);transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility .3s;display:flex;align-items:center;gap:8px}
.toast.on{transform:translate(-50%,0);visibility:visible}
.toast.ok{border-color:rgba(34,197,94,.45)}
.toast.er{border-color:rgba(248,113,113,.45)}
.toast svg{width:17px;height:17px;flex:0 0 auto}
.toast.ok svg{color:var(--green2)}
.toast.er svg{color:var(--red)}

.ax{text-align:center;padding:18px 14px 16px;border-radius:24px;border:1px solid rgba(96,165,250,.3);position:relative;overflow:hidden;
  background:radial-gradient(70% 70% at 50% 20%,rgba(59,130,246,.25),transparent 70%),radial-gradient(60% 60% at 50% 100%,rgba(34,197,94,.16),transparent 70%),#06080d}
.gw{position:relative;width:150px;height:140px;margin:0 auto;display:grid;place-items:center}
.gw .rays{position:absolute;left:50%;top:50%;width:260px;height:260px;margin:-130px 0 0 -130px;border-radius:50%;pointer-events:none;
  background:conic-gradient(from 0deg,transparent 0 6%,rgba(96,165,250,.16) 9%,transparent 13% 29%,rgba(74,222,128,.13) 32%,transparent 36% 54%,rgba(96,165,250,.16) 57%,transparent 61% 79%,rgba(74,222,128,.13) 82%,transparent 86%);
  -webkit-mask-image:radial-gradient(closest-side,#000 25%,transparent 100%);mask-image:radial-gradient(closest-side,#000 25%,transparent 100%);animation:spin 26s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.gw .gmv{position:relative;display:block;width:118px;height:125px;animation:fly 4.2s ease-in-out infinite}
.gw .gm3{position:relative;width:100%;height:100%;filter:drop-shadow(0 16px 20px rgba(37,99,235,.5))}
.gw .gsh{position:absolute;inset:0;overflow:hidden;pointer-events:none;clip-path:polygon(50% 4.4%,9.4% 39.7%,50% 89.7%,90.6% 39.7%)}
.gw .gsh:after{content:"";position:absolute;top:0;bottom:0;left:0;width:18%;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.85),rgba(255,255,255,0));
  transform:translate3d(-150%,0,0) skewX(-18deg);animation:gsh 3.8s ease-in-out infinite}
@keyframes gsh{0%,62%{transform:translate3d(-150%,0,0) skewX(-18deg)}100%{transform:translate3d(620%,0,0) skewX(-18deg)}}
@keyframes fly{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-8px) rotate(2deg)}}
.gw .sp{position:absolute;width:14px;height:14px;background:#fff;clip-path:polygon(50% 0,62% 38%,100% 50%,62% 62%,50% 100%,38% 62%,0 50%,38% 38%);
  opacity:0;animation:tw 2.6s ease-in-out infinite}
.gw .sp.a{top:14px;right:22px}
.gw .sp.b{top:62px;left:14px;width:10px;height:10px;animation-delay:.9s}
.gw .sp.c{bottom:22px;right:14px;width:9px;height:9px;animation-delay:1.7s}
@keyframes tw{0%,100%{opacity:0;transform:scale(.3) rotate(0)}45%{opacity:1;transform:scale(1) rotate(45deg)}70%{opacity:0;transform:scale(.4) rotate(90deg)}}
.ax .cr{font-size:34px;font-weight:900;direction:ltr;margin-top:2px;letter-spacing:-.5px;background:linear-gradient(180deg,#fff 30%,#BFDBFE);
  -webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 8px 24px rgba(59,130,246,.35)}
.ax .cr small{font-size:12px;color:var(--dim);font-weight:700}
.ax .rt{display:inline-flex;gap:6px;align-items:center;font-size:11px;color:var(--green3);font-weight:800;padding:4px 10px;border-radius:20px;background:rgba(34,197,94,.1);margin-top:4px}
.tapz{position:relative;width:170px;margin:0 auto;cursor:pointer;touch-action:manipulation;-webkit-user-select:none;user-select:none;-webkit-tap-highlight-color:transparent}
.tapz.off .gm3{filter:grayscale(.55) drop-shadow(0 16px 20px rgba(37,99,235,.25));opacity:.8}
.tapz .fx{position:absolute;z-index:3;pointer-events:none;font-size:18px;font-weight:900;color:#fff;direction:ltr;transform:translate(-50%,-50%);
  text-shadow:0 2px 10px rgba(59,130,246,.9),0 0 2px #1d4ed8;animation:fxu .75s ease-out forwards;will-change:transform,opacity}
@keyframes fxu{to{transform:translate(-50%,-150%);opacity:0}}
.en{margin-top:12px;text-align:right}
.en .h{display:flex;justify-content:space-between;font-size:11px;color:var(--dim);font-weight:700;margin-bottom:5px}
.en .h span{display:inline-flex;align-items:center;gap:4px}
.en .h svg{width:13px;height:13px;color:#FACC15}
.en .bar{position:relative}
.en .bar i{position:absolute;inset:0;width:auto;background:linear-gradient(90deg,#FACC15,#22C55E);transform:translate3d(100%,0,0);transition:transform .25s}
.en small{display:block;font-size:10px;color:var(--dim2);font-weight:700;margin-top:5px}
.lvl{margin-top:14px;text-align:right}
.lvl .h{display:flex;justify-content:space-between;font-size:11px;color:var(--dim);font-weight:700;margin-bottom:5px}
.lvl .h b{color:var(--ink)}
.bar{height:9px;border-radius:9px;background:rgba(255,255,255,.07);overflow:hidden}
.bar i{display:block;height:100%;border-radius:9px;background:var(--grad);transition:width .6s}
.bkc{margin-top:10px;border-radius:20px;border:1px solid rgba(96,165,250,.28);padding:12px;
  background:linear-gradient(135deg,rgba(59,130,246,.14),rgba(34,197,94,.06)),var(--card)}
.bkc .bh{display:flex;align-items:center;gap:10px}
.bkc .bh div{flex:1;min-width:0}
.bkc .bh b{display:block;font-size:13px;font-weight:900}
.bkc .bh small{font-size:10px;color:var(--dim)}
.bkc .bv{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
.bkc .bv div{border-radius:14px;background:rgba(255,255,255,.035);border:1px solid var(--line);padding:9px 10px}
.bkc .bv small{display:block;font-size:10px;color:var(--dim);font-weight:700}
.bkc .bv b{display:flex;align-items:center;gap:5px;font-size:15px;font-weight:900;direction:ltr;justify-content:flex-end}
.bkc .bv b .gmi{width:16px;height:16px}
.axg{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
.axg button{border-radius:16px;border:1px solid var(--line);background:var(--card);padding:11px 6px;font-size:11px;font-weight:800;display:flex;flex-direction:column;align-items:center;gap:5px}
.axg button small{font-size:9.5px;color:var(--dim);font-weight:700}
.ms{display:flex;align-items:center;gap:11px;border-radius:16px;border:1px solid var(--line);background:var(--card);padding:11px 12px;margin-bottom:8px}

.ms .mid{flex:1;min-width:0}
.ms b{font-size:12.5px;font-weight:800;display:block}
.ms small{font-size:10px;color:var(--dim)}
.ms .bar{height:5px;margin-top:5px}
.ms .go{display:inline-flex;align-items:center;gap:4px;padding:7px 11px;border-radius:12px;font-size:11px;font-weight:900;background:var(--grad);color:#fff;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 0 -2px 0 rgba(0,0,0,.2),0 8px 16px -8px rgba(34,197,94,.8)}
.ms .go .gmi{width:15px;height:15px}
.ms .go.dn{background:rgba(255,255,255,.06);color:var(--dim);box-shadow:none}
.ms .go.wt{background:rgba(255,255,255,.04);color:var(--ink);border:1px solid var(--line);box-shadow:none}
.ms.rdy{border-color:rgba(74,222,128,.45)!important}
.ms.rdy .go{position:relative}
.ms.rdy .go:after{content:"";position:absolute;inset:-3px;border-radius:14px;border:2px solid rgba(74,222,128,.7);animation:rp 1.6s infinite;pointer-events:none}
.lb{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--line);font-size:12px}
.lb:last-child{border-bottom:0}
.lb .rk{flex:0 0 26px;width:26px;height:26px;border-radius:9px;display:grid;place-items:center;font-weight:900;font-size:11px;background:rgba(255,255,255,.06)}
.lb:nth-child(1) .rk{background:linear-gradient(135deg,#FDE68A,#F59E0B);color:#1a1200}
.lb:nth-child(2) .rk{background:linear-gradient(135deg,#E5E7EB,#9CA3AF);color:#111}
.lb:nth-child(3) .rk{background:linear-gradient(135deg,#FDBA74,#C2410C);color:#1a0a00}
.lb span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700}
.lb b{font-weight:900;direction:ltr}
.lb.me{background:rgba(59,130,246,.1)}
.tour{position:fixed;inset:0;z-index:80;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px 24px;text-align:center}
.tour .tv{margin-bottom:14px}
.tour .gw .gmv{width:132px;height:140px}
.tour h2{font-size:20px;font-weight:900;margin-bottom:8px}
.tour p{color:var(--dim);font-size:12.5px;line-height:1.95;max-width:320px}
.tour .dts{display:flex;gap:6px;margin:22px 0}
.tour .dts i{width:7px;height:7px;border-radius:5px;background:rgba(255,255,255,.2);transition:width .3s}
.tour .dts i.on{width:22px;background:var(--grad)}
.tour .btn{max-width:320px}

.chat{position:fixed;left:0;right:0;top:0;z-index:50;max-width:480px;margin:0 auto;display:flex;flex-direction:column;background:var(--bg);height:100vh}
.chat .ch{flex:0 0 auto;display:flex;align-items:center;gap:10px;padding:calc(10px + var(--top)) 12px 10px;border-bottom:1px solid var(--line);background:#07090d}
.chat .ch .ava{width:38px;height:38px}
.chat .ch .ava svg{width:20px;height:20px}
.chat .ch b{display:block;font-size:13px;font-weight:900}
.chat .ch small{font-size:10px;color:var(--green3);display:flex;align-items:center;gap:5px}
.chat .log{flex:1;overflow-y:auto;padding:12px 12px 8px;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
.bub{max-width:80%;margin:4px 0;padding:8px 11px 5px;border-radius:17px;font-size:12.5px;line-height:1.75;word-wrap:break-word;white-space:pre-wrap;-webkit-user-select:text;user-select:text}
.bub time{display:block;font-size:9px;color:rgba(255,255,255,.5);text-align:left;margin-top:2px;direction:ltr}
.bub.me{margin-inline-start:auto;background:linear-gradient(135deg,#2563EB,#3B82F6);border-end-end-radius:6px;border-start-end-radius:17px}
.bub.them{margin-inline-end:auto;background:var(--card3);border:1px solid var(--line);border-end-start-radius:6px}
.bub.pend{opacity:.6}
.bub.fail{background:rgba(248,113,113,.2)}
.bub.me.rd time:after{content:" ✓✓";color:#bfdbfe}
.cday{text-align:center;margin:10px 0}
.cday span{font-size:10px;color:var(--dim);padding:3px 10px;border-radius:10px;background:rgba(255,255,255,.05)}
.chat .cb{flex:0 0 auto;display:flex;align-items:flex-end;gap:8px;padding:8px 10px calc(8px + var(--safe));border-top:1px solid var(--line);background:#07090d}
.chat.kb .cb{padding-bottom:8px}
.chat .cb textarea{flex:1;max-height:120px;min-height:42px;border-radius:16px;border:1px solid var(--line2);background:var(--card);color:var(--ink);padding:10px 13px;font-size:13px;outline:0;resize:none;line-height:1.6}
.chat .cb button{width:42px;height:42px;border-radius:15px;background:var(--grad);display:grid;place-items:center;color:#fff;flex:0 0 auto;transition:opacity .15s}
.chat .cb button.idle{opacity:.45}
.chat .cb button svg{width:19px;height:19px}
.chat .nw{position:absolute;bottom:72px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:4px;padding:6px 12px;border-radius:14px;background:var(--green);color:#02140a;font-size:11px;font-weight:900}
.chat .nw svg{width:13px;height:13px}
html.chaton .dock,html.chaton .fab,html.chaton .hdr{visibility:hidden}

.splash{position:fixed;inset:0;z-index:100;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;
  transition:opacity .6s ease .12s,visibility .6s ease .12s}
.splash.out{opacity:0;visibility:hidden}
.splash:before{content:"";position:absolute;inset:-20%;background:radial-gradient(38% 30% at 50% 42%,rgba(59,130,246,.30),transparent 70%),radial-gradient(32% 26% at 50% 62%,rgba(34,197,94,.18),transparent 70%);animation:bre 3s ease-in-out infinite;will-change:transform,opacity}
@keyframes bre{0%,100%{opacity:.7;transform:scale(1)}50%{opacity:1;transform:scale(1.06)}}
.splash .spc{position:relative;display:flex;flex-direction:column;align-items:center;animation:spin0 .9s cubic-bezier(.2,.85,.25,1) both;
  transition:transform .6s cubic-bezier(.5,0,.75,0),opacity .32s ease}
.splash.out .spc{transform:scale(1.12);opacity:0}
@keyframes spin0{from{opacity:0;transform:perspective(700px) rotateX(18deg) translate3d(0,26px,0) scale(.92)}to{opacity:1;transform:none}}
.splash .lg{position:relative;width:118px;height:118px;display:grid;place-items:center;animation:lgf 3.2s ease-in-out infinite;will-change:transform}
@keyframes lgf{0%,100%{transform:translate3d(0,0,0) rotate(-2deg)}50%{transform:translate3d(0,-7px,0) rotate(2deg)}}
.splash .lg:before,.splash .lg:after{content:"";position:absolute;inset:-14px;border-radius:50%;border:1.5px solid rgba(96,165,250,.5);animation:rd 2.2s infinite}
.splash .lg:after{animation-delay:1.1s;border-color:rgba(74,222,128,.5)}
.splash .lg .core{position:relative;width:104px;height:104px;border-radius:32px;display:grid;place-items:center;background:var(--grad);overflow:hidden;
  box-shadow:inset 0 2px 0 rgba(255,255,255,.45),inset 0 -8px 16px rgba(0,0,0,.3),0 22px 50px -10px rgba(59,130,246,.75)}
.splash .lg .core:before{content:"";position:absolute;top:0;bottom:0;left:0;width:45%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);
  transform:translateX(-160%) skewX(-18deg);animation:lgs 2.6s ease-in-out infinite}
@keyframes lgs{0%,55%{transform:translateX(-160%) skewX(-18deg)}100%{transform:translateX(330%) skewX(-18deg)}}
.splash .lg .core svg{position:relative;width:54px;height:54px;color:#fff}
.splash .lg img{width:104px;height:104px;border-radius:32px;object-fit:cover;box-shadow:0 22px 50px -10px rgba(59,130,246,.75)}
.splash h1{position:relative;margin-top:26px;font-size:22px;font-weight:900;letter-spacing:-.3px}
.splash p{position:relative;margin-top:4px;color:var(--dim);font-size:12px;max-width:280px;text-align:center}
.splash .ld{position:absolute;left:0;right:0;bottom:calc(40px + var(--safe));width:min(290px,82vw);margin:0 auto;text-align:center;transition:opacity .35s ease,transform .45s ease}
.splash.out .ld{opacity:0;transform:translate3d(0,12px,0)}
.splash .ld .pc{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:9px}
.splash .ld .pc span{font-size:11px;font-weight:800;color:var(--ink);opacity:.9;text-align:right;transition:opacity .2s}
.splash .ld .pc b{font-size:24px;font-weight:900;line-height:1;background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent;min-width:62px;text-align:left}
.splash .ld .bar{position:relative;height:7px;border-radius:7px;overflow:hidden;overflow:clip;background:rgba(148,163,184,.16);box-shadow:inset 0 1px 2px rgba(0,0,0,.35)}
@keyframes spb{from{transform:translate3d(100%,0,0)}to{transform:translate3d(0,0,0)}}
.splash .ld .bar i{position:absolute;inset:0;border-radius:7px;background:var(--grad);transform:translate3d(100%,0,0);will-change:transform;
  box-shadow:0 0 14px rgba(59,130,246,.75)}
.splash .ld .bar i:after{content:"";position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);animation:ldg 1.3s linear infinite}
@keyframes ldg{from{transform:translate3d(-100%,0,0)}to{transform:translate3d(260%,0,0)}}
.splash .ld .stg{display:flex;justify-content:center;gap:6px;margin-top:11px}
.splash .ld .stg i{width:22px;height:4px;border-radius:4px;background:rgba(148,163,184,.22);transition:background .3s,box-shadow .3s,width .3s}
.splash .ld .stg i.on{width:30px;background:var(--green);box-shadow:0 0 8px rgba(74,222,128,.8)}
.splash .ld small{display:block;margin-top:10px;min-height:18px;color:var(--dim);font-size:10.5px;font-weight:700;transition:opacity .18s}
.splash h1{font-size:28px;font-weight:900;letter-spacing:-.5px;background:linear-gradient(90deg,#fff,#BFDBFE 55%,#86EFAC);-webkit-background-clip:text;background-clip:text;color:transparent;
  filter:drop-shadow(0 6px 22px rgba(59,130,246,.45))}
.splash p{font-size:13px;font-weight:800;color:#D6E4FF;opacity:.92;line-height:1.8}
.splash .ld .pc span{font-size:12.5px;font-weight:900;color:#EAF2FF}
@font-face{font-family:'NbxNum';font-style:normal;font-weight:600;font-display:swap;src:url('assets/fonts/NbxNum.woff2') format('woff2')}
.splash .ld .pc b{font-family:'NbxNum',system-ui,sans-serif;font-size:13px;font-weight:600;letter-spacing:.4px;min-width:0;font-variant-numeric:tabular-nums;filter:none}
.splash .ld small{font-size:11.5px;font-weight:800;color:#C7D7F5}

.gate{position:fixed;inset:0;z-index:95;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate svg{width:70px;height:70px;color:var(--blue2);margin-bottom:14px}
.gate b{font-size:16px;font-weight:900}
.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px;max-width:300px}

.cf{position:fixed;inset:0;z-index:70;pointer-events:none;overflow:hidden}
.cf i{position:absolute;top:-10px;width:8px;height:12px;border-radius:2px;animation:cfd 1.6s ease-in forwards}
@keyframes cfd{to{transform:translateY(105vh) rotate(540deg);opacity:.2}}

.fl,.fb{display:grid;place-items:center;flex:0 0 auto;border-radius:50%;line-height:1;font-style:normal;
  background:radial-gradient(60% 55% at 34% 26%,rgba(255,255,255,.2),rgba(255,255,255,.04) 62%),linear-gradient(160deg,#1b2332,#0a0d13);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.2),inset 0 -3px 7px rgba(0,0,0,.55),0 8px 16px -10px rgba(0,0,0,.95)}
.fl svg,.fb svg{width:52%;height:52%;color:var(--blue2)}
.fl.ok{background:linear-gradient(145deg,#4ADE80,#16A34A 60%,#14532D);color:#fff;box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 8px rgba(0,0,0,.3),0 10px 18px -8px rgba(34,197,94,.8)}
.fl.ok.c{background:linear-gradient(145deg,#67E8F9,#0EA5E9 55%,#1E40AF)}
.fl.ok svg{color:#fff}

.t3{position:relative;display:grid;place-items:center;width:42px;height:42px;border-radius:14px;flex:0 0 auto;color:#fff;font-style:normal;
  background:linear-gradient(145deg,#7DB4FF,#2563EB 55%,#1E3A8A);
  box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 9px rgba(0,0,0,.35),0 10px 18px -9px rgba(37,99,235,.85)}
.t3:before{content:"";position:absolute;left:2px;right:2px;top:1px;height:48%;border-radius:12px 12px 45% 45%/12px 12px 14px 14px;
  background:linear-gradient(180deg,rgba(255,255,255,.34),rgba(255,255,255,0));pointer-events:none}
.t3 svg{position:relative;width:21px;height:21px}
.t3.g{background:linear-gradient(145deg,#6EE7A0,#16A34A 58%,#14532D);box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 9px rgba(0,0,0,.32),0 10px 18px -9px rgba(34,197,94,.85)}
.t3.c{background:linear-gradient(145deg,#7DE3FA,#0EA5E9 55%,#1E40AF);box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 9px rgba(0,0,0,.32),0 10px 18px -9px rgba(14,165,233,.85)}
.t3.m{background:linear-gradient(145deg,#60A5FA,#1D9E75 70%,#166534);box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 9px rgba(0,0,0,.32),0 10px 18px -9px rgba(34,197,94,.7)}
.t3.r{background:linear-gradient(145deg,#4a2129,#200d11);color:#FCA5A5;box-shadow:inset 0 1px 0 rgba(255,255,255,.2),inset 0 -4px 9px rgba(0,0,0,.4)}
.t3.k{background:radial-gradient(80% 80% at 35% 22%,#2b4a86 0%,#0d1630 60%,#060a14 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.25),inset 0 -4px 9px rgba(0,0,0,.5),0 10px 18px -9px rgba(59,130,246,.7)}
.t3.k svg{width:26px;height:26px}
.t3.s{width:34px;height:34px;border-radius:12px}
.t3.s svg{width:18px;height:18px}
.t3.s.k svg{width:24px;height:24px}
.t3.xl{width:120px;height:120px;border-radius:36px;animation:fly 4.2s ease-in-out infinite}
.t3.xl:before{border-radius:34px 34px 50% 50%/34px 34px 30px 30px}
.t3.xl svg{width:60px;height:60px}
.gmi{display:inline-block;width:16px;height:16px;vertical-align:-3px}
.lb b .gmi,.lb b .ii{margin-inline-start:4px}
.ii{display:inline-block;width:13px;height:13px;vertical-align:-2px;margin-inline-end:4px}
.ii.g{color:var(--green2)}
.rh{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:900}
.code small .ii{width:14px;height:14px}

.card,.cc,.crow,.co,.or,.ntf,.gc,.menu,.ms,.axg button,.srch,.feed,.seg,.qa button,.stat{
  border-color:transparent;
  background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,0) 46%) padding-box,linear-gradient(var(--gl),var(--gl)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.22),rgba(255,255,255,.06) 28%,rgba(255,255,255,.025) 62%,rgba(96,165,250,.26)) border-box;
  box-shadow:0 14px 26px -20px rgba(0,0,0,.95)}
.co{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 46%) padding-box,linear-gradient(var(--gl2),var(--gl2)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.22),rgba(255,255,255,.06) 30%,rgba(96,165,250,.28)) border-box}
.stat{background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.01) 55%) padding-box,linear-gradient(rgba(10,13,19,.8),rgba(10,13,19,.8)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.22),rgba(255,255,255,.05) 40%,rgba(74,222,128,.25)) border-box}
.stat b{background:linear-gradient(180deg,#fff 35%,#BFDBFE);-webkit-background-clip:text;background-clip:text;color:transparent}
.crow,.ntf,.or{box-shadow:none}
.cc{box-shadow:0 14px 24px -16px rgba(0,0,0,.95)}
.ntf.new{background:linear-gradient(180deg,rgba(74,222,128,.08),rgba(255,255,255,0) 50%) padding-box,linear-gradient(var(--card),var(--card)) padding-box,
    linear-gradient(155deg,rgba(74,222,128,.55),rgba(74,222,128,.12) 45%,rgba(96,165,250,.3)) border-box}
.lv{border-color:transparent;background:linear-gradient(135deg,rgba(59,130,246,.14),rgba(34,197,94,.07)) padding-box,linear-gradient(var(--gl),var(--gl)) padding-box,
    linear-gradient(135deg,rgba(96,165,250,.6),rgba(96,165,250,.12) 50%,rgba(74,222,128,.45)) border-box;box-shadow:0 14px 26px -18px rgba(37,99,235,.7)}
.lv.dn{background:linear-gradient(135deg,rgba(34,197,94,.16),rgba(59,130,246,.06)) padding-box,linear-gradient(var(--gl),var(--gl)) padding-box,
    linear-gradient(135deg,rgba(74,222,128,.7),rgba(74,222,128,.15) 50%,rgba(96,165,250,.4)) border-box}
.ad{border-color:transparent;background:radial-gradient(90% 120% at 0 0,rgba(34,197,94,.16),transparent 60%) padding-box,radial-gradient(90% 120% at 100% 100%,rgba(59,130,246,.2),transparent 60%) padding-box,
    linear-gradient(var(--gl),var(--gl)) padding-box,linear-gradient(135deg,rgba(96,165,250,.65),rgba(255,255,255,.08) 45%,rgba(74,222,128,.55)) border-box;
  box-shadow:0 16px 30px -18px rgba(37,99,235,.8)}
.hdr{border-color:transparent;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 60%) padding-box,linear-gradient(rgba(8,10,14,.96),rgba(8,10,14,.96)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.22),rgba(255,255,255,.05) 40%,rgba(96,165,250,.3)) border-box}
.dock nav{border-color:transparent;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 50%) padding-box,linear-gradient(rgba(9,11,15,.97),rgba(9,11,15,.97)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.24),rgba(255,255,255,.05) 40%,rgba(96,165,250,.32)) border-box}
.dock button.on{background:linear-gradient(180deg,rgba(96,165,250,.26),rgba(34,197,94,.12));box-shadow:inset 0 1px 0 rgba(255,255,255,.14)}

.btn{position:relative;overflow:hidden;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 0 -3px 0 rgba(0,0,0,.2),0 12px 24px -12px rgba(59,130,246,.85)}
.btn:before{content:"";position:absolute;left:0;right:0;top:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.2),rgba(255,255,255,0));pointer-events:none}
.btn:active{transform:translateY(2px) scale(.985);box-shadow:inset 0 1px 0 rgba(255,255,255,.3),inset 0 -1px 0 rgba(0,0,0,.2),0 6px 14px -10px rgba(59,130,246,.8)}
.btn.gh,.btn.rd{box-shadow:inset 0 1px 0 rgba(255,255,255,.08)}
.btn.gh:before,.btn.rd:before{display:none}
.btn.shn:after{content:"";position:absolute;top:0;bottom:0;left:0;width:40%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);
  transform:translateX(-150%) skewX(-20deg);animation:shn 4.5s ease-in-out infinite}
@keyframes shn{0%,62%{transform:translateX(-150%) skewX(-20deg)}100%{transform:translateX(350%) skewX(-20deg)}}
.chip.on,.pbtn,.bal i,.fab{box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 0 -2px 0 rgba(0,0,0,.18),0 8px 18px -10px rgba(59,130,246,.9)}
.fab{box-shadow:inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -4px 8px rgba(0,0,0,.25),0 12px 26px -8px rgba(59,130,246,.8),0 0 0 3px rgba(0,0,0,.6)}
.bar{box-shadow:inset 0 1px 3px rgba(0,0,0,.6)}
.bar i{box-shadow:inset 0 1px 0 rgba(255,255,255,.45),0 0 12px rgba(74,222,128,.35)}
.lb .rk{box-shadow:inset 0 1px 0 rgba(255,255,255,.18)}
.lb:nth-child(-n+3) .rk{box-shadow:inset 0 1.5px 0 rgba(255,255,255,.6),inset 0 -3px 5px rgba(0,0,0,.25),0 6px 12px -6px rgba(0,0,0,.8)}
.ax{border-color:transparent;padding-top:8px;
  background:radial-gradient(70% 60% at 50% 22%,rgba(59,130,246,.3),transparent 70%) padding-box,radial-gradient(60% 60% at 50% 100%,rgba(34,197,94,.18),transparent 70%) padding-box,
    linear-gradient(#06080d,#06080d) padding-box,linear-gradient(160deg,rgba(147,197,253,.7),rgba(255,255,255,.06) 40%,rgba(74,222,128,.55)) border-box;
  box-shadow:0 22px 40px -22px rgba(37,99,235,.8)}
.axg button .t3{margin-bottom:2px}

.ov{background:radial-gradient(120% 60% at 50% 100%,rgba(37,99,235,.26),transparent 70%),rgba(0,0,0,.5)}
.sh{border-radius:30px 30px 0 0;border:1px solid rgba(147,197,253,.22);border-bottom:0;
  background:linear-gradient(180deg,rgba(255,255,255,.1) 0%,rgba(255,255,255,.03) 18%,rgba(255,255,255,.01) 100%),
    radial-gradient(110% 38% at 100% 0%,rgba(59,130,246,.34),transparent 62%),radial-gradient(80% 30% at 0% 6%,rgba(34,197,94,.18),transparent 62%),
    radial-gradient(90% 40% at 50% 100%,rgba(37,99,235,.16),transparent 70%),rgba(10,14,24,.66);
  -webkit-backdrop-filter:blur(24px) saturate(170%);backdrop-filter:blur(24px) saturate(170%);
  box-shadow:0 -26px 60px -22px rgba(37,99,235,.55),inset 0 1px 0 rgba(255,255,255,.22)}
@supports not ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){.sh{background-color:rgba(10,14,24,.97)}}
.sh:before{content:"";position:absolute;top:0;left:14%;right:14%;height:1.5px;border-radius:2px;pointer-events:none;
  background:linear-gradient(90deg,transparent,#93C5FD,#4ADE80,transparent)}
.sh .grab{width:46px;height:5px;border-radius:5px;background:linear-gradient(90deg,rgba(147,197,253,.6),rgba(74,222,128,.6));box-shadow:0 0 12px rgba(59,130,246,.45)}
.sh .shd{margin:4px 16px 8px;padding:10px 12px;border-radius:22px;
  background:linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.11);box-shadow:inset 0 1px 0 rgba(255,255,255,.1)}
.sh .x{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.sh .card{border-color:transparent;
  background:linear-gradient(135deg,rgba(30,58,138,.38),rgba(8,12,22,.35)) padding-box,linear-gradient(135deg,rgba(147,197,253,.55),rgba(255,255,255,.08) 45%,rgba(74,222,128,.5)) border-box;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.1),0 16px 30px -22px rgba(37,99,235,.9)}
.sh .co{background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.02)) padding-box,
    linear-gradient(155deg,rgba(255,255,255,.26),rgba(255,255,255,.05) 35%,rgba(96,165,250,.35)) border-box}
.sh .inp{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));border-color:rgba(147,197,253,.24);box-shadow:inset 0 1px 0 rgba(255,255,255,.09)}
.sh .inp:focus{border-color:rgba(96,165,250,.7);box-shadow:0 0 0 3px rgba(59,130,246,.22),inset 0 1px 0 rgba(255,255,255,.1)}
.sh .btn:not(.gh):not(.rd){min-height:52px;border-radius:17px;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),inset 0 -3px 0 rgba(0,0,0,.2),0 18px 34px -14px rgba(59,130,246,.95)}
.sh .btn.gh{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.14)}
.sh #bTot{font-size:17px;background:var(--grad2);-webkit-background-clip:text;background-clip:text;color:transparent}
html.shon .sky *,html.shon .app *:before,html.shon .app *:after{animation-play-state:paused!important}

@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
html.lite *,html.lite *:before,html.lite *:after{animation:none!important;-webkit-animation:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;background-attachment:scroll!important}
.trust{overflow:hidden;contain:paint;direction:ltr;margin:12px -12px 0;padding:2px 0;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.tk{display:flex;gap:8px;width:max-content;animation:mq 30s linear infinite;will-change:transform}
.tk.one{animation:none;width:auto}
@keyframes mq{to{transform:translate3d(-50%,0,0)}}
.tb{direction:rtl;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;font-size:11px;font-weight:800;padding:7px 12px;border-radius:30px;
  border:1px solid var(--line2);background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02));color:var(--ink)}
.tb svg{width:14px;height:14px;color:var(--green2)}
.rva{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:800;color:#FACC15}
.rva svg{width:13px;height:13px}
.rvw{overflow:hidden;contain:paint;direction:ltr;margin:0 -12px;padding:2px 0 4px}
.rvw:active .tk{animation-play-state:paused}
.rvw .tk{padding:0 12px}
.rv{direction:rtl;width:236px;flex:none;border-radius:18px;border:1px solid var(--line2);background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,0) 50%),var(--gl);padding:12px 13px;box-shadow:0 14px 24px -18px rgba(0,0,0,.95)}
.rv .h{display:flex;align-items:center;gap:7px}
.rv .av{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:12px;font-weight:900;background:var(--grad);color:#fff}
.rv .av{position:relative;overflow:hidden}
.rv .av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%}
.rv .h b{font-size:12px;font-weight:800;flex:1}
.rv p{font-size:11.5px;line-height:1.75;color:#D7DCE5;margin-top:7px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:60px}
.rv small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim2);margin-top:6px}
.rv small svg{width:14px;height:14px}
.sts{display:inline-flex;gap:1px;direction:ltr}
.sts svg{width:12px;height:12px;color:rgba(255,255,255,.18)}
.sts svg.on{color:#FACC15}
.rate{margin-top:12px;border-radius:16px;border:1px solid var(--line2);background:var(--card2);padding:12px;text-align:center}
.rate b{font-size:12.5px;font-weight:900}
.rate .pick{display:flex;justify-content:center;gap:6px;margin:9px 0 10px;direction:ltr}
.rate .pick button{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.05);border:1px solid var(--line)}
.rate .pick svg{width:22px;height:22px;color:rgba(255,255,255,.22);transition:color .15s,transform .15s}
.rate .pick button.on svg{color:#FACC15;transform:scale(1.08)}
.rate textarea{min-height:0;resize:none}
.rate .anon{display:inline-flex;align-items:center;gap:8px;margin-top:10px;font-size:11.5px;font-weight:700;color:var(--dim)}
.rate .anon i{width:32px;height:19px;border-radius:10px;background:rgba(255,255,255,.1);border:1px solid var(--line);position:relative;transition:background .2s}
.rate .anon i:after{content:'';position:absolute;top:2px;right:2px;width:13px;height:13px;border-radius:50%;background:#fff;transition:transform .2s}
.rate .anon.on i{background:var(--green)}
.rate .anon.on i:after{transform:translateX(-13px)}
.rate .rnote{display:block;font-size:10.5px;line-height:1.7;color:var(--dim2);margin-top:5px}
.rate.dn{display:flex;align-items:center;justify-content:center;gap:8px;font-size:12px;font-weight:800;color:var(--green3)}
.rate.dn .sts svg{width:15px;height:15px}
@media (prefers-reduced-motion:reduce){.tk{animation:none}.trust,.rvw{overflow-x:auto}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
<defs>
<linearGradient id="rg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#60A5FA"/><stop offset="1" stop-color="#4ADE80"/></linearGradient>
<linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#93C5FD"/><stop offset=".5" stop-color="#3B82F6"/><stop offset="1" stop-color="#22C55E"/></linearGradient>
<linearGradient id="gm1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#A5D8FF"/></linearGradient>
<linearGradient id="gm2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E0F2FE"/><stop offset="1" stop-color="#60A5FA"/></linearGradient>
<linearGradient id="gm3" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#93C5FD"/><stop offset="1" stop-color="#2563EB"/></linearGradient>
<linearGradient id="gm4" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#38BDF8"/><stop offset=".5" stop-color="#0EA5E9"/><stop offset="1" stop-color="#10B981"/></linearGradient>
<linearGradient id="gm7" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3B82F6"/><stop offset="1" stop-color="#1E3A8A"/></linearGradient>
<linearGradient id="gm5" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7CC6FF"/><stop offset="1" stop-color="#1E6FE0"/></linearGradient>
<linearGradient id="gm6" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1D4ED8"/><stop offset="1" stop-color="#065F46"/></linearGradient>
<radialGradient id="gmH" cx=".32" cy=".18" r=".7"><stop offset="0" stop-color="#fff" stop-opacity=".9"/><stop offset=".45" stop-color="#fff" stop-opacity=".12"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
<radialGradient id="gmSh" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#3B82F6" stop-opacity=".55"/><stop offset="1" stop-color="#3B82F6" stop-opacity="0"/></radialGradient>
<linearGradient id="phB" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1b2433"/><stop offset="1" stop-color="#070a10"/></linearGradient>
<linearGradient id="phS" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset=".55" stop-color="#0b1a3a"/><stop offset="1" stop-color="#062a1c"/></linearGradient>
<linearGradient id="phG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient>
<radialGradient id="phD" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".75"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
<linearGradient id="okG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#86EFAC"/><stop offset="1" stop-color="#16A34A"/></linearGradient>
</defs>
<symbol id="g-gem" viewBox="0 0 64 64">
  <path d="M32 3 6 27l15 7z" fill="url(#gm1)"/>
  <path d="M32 3 21 34h22z" fill="url(#gm2)"/>
  <path d="M32 3l11 31 15-7z" fill="url(#gm3)"/>
  <path d="M6 27l15 7 11 27z" fill="url(#gm7)"/>
  <path d="M21 34h22L32 61z" fill="url(#gm4)"/>
  <path d="M43 34l15-7-26 34z" fill="url(#gm6)"/>
  <path d="M32 3 6 27 32 61 58 27Z" fill="url(#gmH)" opacity=".45"/>
  <path d="M30.2 7.2 10 26.2l4 1.9 15-17.3z" fill="#fff" opacity=".45"/>
  <path d="M26 36h3l3.4 17z" fill="#fff" opacity=".22"/>
  <path d="M6 27l15 7h22l15-7M21 34 32 3l11 31M21 34l11 27 11-27" stroke="#fff" stroke-opacity=".55" stroke-width=".8" fill="none" stroke-linejoin="round"/>
  <path d="M32 3 6 27 32 61 58 27Z" stroke="#fff" stroke-opacity=".85" stroke-width="1.1" fill="none" stroke-linejoin="round"/>
  <path d="M21 8.4l.9 2.4 2.4.9-2.4.9-.9 2.4-.9-2.4-2.4-.9 2.4-.9z" fill="#fff"/>
</symbol>
<symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10.5 12 3.8l8.5 6.7V19a1.6 1.6 0 0 1-1.6 1.6h-3.8v-5.8H8.9v5.8H5.1A1.6 1.6 0 0 1 3.5 19z"/></symbol>
<symbol id="i-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.6"/><path d="M3.6 12h16.8M12 3.4c2.4 2.4 3.6 5.3 3.6 8.6s-1.2 6.2-3.6 8.6c-2.4-2.4-3.6-5.3-3.6-8.6S9.6 5.8 12 3.4z"/></symbol>
<symbol id="i-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3.4" width="16" height="17.2" rx="2.6"/><path d="M8 8h8M8 12h8M8 16h5"/></symbol>
<symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.2" r="4"/><path d="M4.4 20.2c.8-3.8 3.9-6 7.6-6s6.8 2.2 7.6 6"/></symbol>
<symbol id="i-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6.2 10a5.8 5.8 0 0 1 11.6 0c0 4.6 1.8 6.2 1.8 6.2H4.4S6.2 14.6 6.2 10z"/><path d="M10.2 19.6a1.9 1.9 0 0 0 3.6 0"/></symbol>
<symbol id="i-wallet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.6 7.4h15.6a1.6 1.6 0 0 1 1.6 1.6v9.4a1.6 1.6 0 0 1-1.6 1.6H5.2a1.6 1.6 0 0 1-1.6-1.6z"/><path d="M3.6 7.4 15.4 4v3.4"/><path d="M16.4 13.6h1.2"/></symbol>
<symbol id="i-headset" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4.4 14v-2a7.6 7.6 0 0 1 15.2 0v2"/><rect x="3.4" y="13.2" width="4.2" height="6" rx="1.6"/><rect x="16.4" y="13.2" width="4.2" height="6" rx="1.6"/><path d="M18.5 19.2c0 1.2-1.6 1.8-4.2 1.8"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m15.8 15.8 4.6 4.6"/></symbol>
<symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.6 6-6 6 6 6"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
<symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="8.4" y="8.4" width="11.6" height="11.6" rx="2.4"/><path d="M15.6 8.4V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7.6a2 2 0 0 0 2 2h2.4"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m4.8 12.6 4.6 4.6 9.8-10.2"/></symbol>
<symbol id="i-sim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.4h7.6L19 7.8v11.2a1.6 1.6 0 0 1-1.6 1.6H7a1.6 1.6 0 0 1-1.6-1.6V5A1.6 1.6 0 0 1 7 3.4z"/><rect x="8.6" y="11" width="6.8" height="6.2" rx="1.2"/><path d="M12 11v6.2M8.6 14.1h6.8"/></symbol>
<symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="6.4" y="2.6" width="11.2" height="18.8" rx="2.6"/><path d="M10.6 5.6h2.8M11.2 18h1.6"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.2 19.4 6v5.6c0 4.4-3.1 8-7.4 9.2-4.3-1.2-7.4-4.8-7.4-9.2V6z"/><path d="m8.8 12.2 2.3 2.3 4.3-4.6"/></symbol>
<symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13.4 2.8 5 13.6h6.2l-.8 7.6 8.4-10.8h-6.2z"/></symbol>
<symbol id="i-gift" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3.6" y="8.4" width="16.8" height="4.2" rx="1"/><path d="M5.2 12.6v7.8h13.6v-7.8M12 8.4v12M12 8.4C10.4 4.6 6.8 4.2 6.8 6.6 6.8 8.4 12 8.4 12 8.4zM12 8.4c1.6-3.8 5.2-4.2 5.2-1.8 0 1.8-5.2 1.8-5.2 1.8z"/></symbol>
<symbol id="i-ticket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.6 7.4a1.6 1.6 0 0 1 1.6-1.6h13.6a1.6 1.6 0 0 1 1.6 1.6v2.2a2.4 2.4 0 0 0 0 4.8v2.2a1.6 1.6 0 0 1-1.6 1.6H5.2a1.6 1.6 0 0 1-1.6-1.6v-2.2a2.4 2.4 0 0 0 0-4.8z"/><path d="M14.2 5.8v12.4" stroke-dasharray="2 2.2"/></symbol>
<symbol id="i-share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="17.6" cy="5.8" r="2.6"/><circle cx="6.4" cy="12" r="2.6"/><circle cx="17.6" cy="18.2" r="2.6"/><path d="m8.7 10.8 6.6-3.7M8.7 13.2l6.6 3.7"/></symbol>
<symbol id="i-cam" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/></symbol>
<symbol id="i-bub" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 19.5l1.2-3.6A8 8 0 1 1 8.4 18.6z"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 3.4 3.4 10.2l6.8 2.8 2.8 6.8z"/><path d="m10.2 13 4.4-4.4"/></symbol>
<symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19.6 11.4A7.6 7.6 0 1 0 17.4 17"/><path d="M19.8 5.6v5.8H14"/></symbol>
<symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8.4" r="3.6"/><path d="M2.8 19.4c.6-3.2 3.1-5 6.2-5s5.6 1.8 6.2 5"/><path d="M15.6 5.2a3.4 3.4 0 0 1 0 6.4M17.4 14.6c2 .6 3.4 2.2 3.8 4.8"/></symbol>
<symbol id="i-trophy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7.4 3.6h9.2v5.2a4.6 4.6 0 0 1-9.2 0z"/><path d="M7.4 5.2H4.6v1.4a3 3 0 0 0 3 3M16.6 5.2h2.8v1.4a3 3 0 0 1-3 3M12 13.4v3.8M8.6 20.4h6.8"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.6"/><path d="M12 7.2V12l3.2 2"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.4 13.9 9l5.7 1.9-5.7 1.9L12 18.6l-1.9-5.8-5.7-1.9L10.1 9z"/><path d="M18.6 3.6v3.2M17 5.2h3.2"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.6"/><path d="M12 7.6v5M12 16.2v.2"/></symbol>
<symbol id="i-flame" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12.6 2.8c2.4 3.4 1 5.2 2.6 6.6.9-.6 1.3-1.6 1.3-2.8 2.4 2.2 3.2 5.2 3.2 6.9a7.7 7.7 0 0 1-15.4 0c0-3.2 2.4-6.4 5-8-.4 1.8.2 3.2 1 3.8.6-2.3 1-4.3 2.3-6.5z"/></symbol>
<symbol id="i-bank" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 4l9 5.5M5 10v7M9.7 10v7M14.3 10v7M19 10v7M3.5 20.5h17"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4.6" y="10.4" width="14.8" height="10" rx="2.4"/><path d="M8 10.4V7.6a4 4 0 0 1 8 0v2.8"/></symbol>
<symbol id="i-card" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.8" y="5.2" width="18.4" height="13.6" rx="2.4"/><path d="M2.8 9.6h18.4M6.4 15h3.4"/></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
<symbol id="i-inbox" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3.4 13.2 6 5.2A1.6 1.6 0 0 1 7.5 4h9a1.6 1.6 0 0 1 1.5 1.2l2.6 8V19a1.6 1.6 0 0 1-1.6 1.6H5A1.6 1.6 0 0 1 3.4 19z"/><path d="M3.4 13.2h5l1.4 2.4h4.4l1.4-2.4h5"/></symbol>
<symbol id="i-cal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3.6" y="5" width="16.8" height="15.4" rx="3"/><path d="M3.6 9.8h16.8M8.2 3v4M15.8 3v4"/><path d="M8.4 13.6h2.2M13.4 13.6h2.2M8.4 16.8h2.2"/></symbol>
<symbol id="i-uplus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9.6" cy="8.2" r="3.8"/><path d="M2.8 20c.7-3.7 3.4-5.8 6.8-5.8 1.7 0 3.2.5 4.4 1.4"/><path d="M18.4 13.2v6.4M15.2 16.4h6.4"/></symbol>
<symbol id="i-rocket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.4 4.2c2.8-1.2 5.6-1.4 5.8-1.2.2.2 0 3-1.2 5.8l-6.2 6.2-4.4-4.4z"/><path d="M8.4 10.6H5.2L3.2 13l4.2.8M13.4 15.6v3.2l-2.4 2-.8-4.2"/><circle cx="15.6" cy="8.4" r="1.6"/><path d="M6.6 17.4c-1.4.4-2.4 2.2-2.6 3.8 1.6-.2 3.4-1.2 3.8-2.6"/></symbol>
<symbol id="i-star" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3.2l2.6 5.6 6.1.7-4.5 4.1 1.2 6-5.4-3.1-5.4 3.1 1.2-6-4.5-4.1 6.1-.7z"/></symbol>
<symbol id="i-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6"/></symbol>
</svg>

<div class="sky" aria-hidden="true"><i class="neb"></i><i class="st1"></i><i class="st2"></i><i class="pl"></i><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><div class="cmt"><i></i></div></div>

<div class="splash" id="splash">
  <div class="spc">
    <div class="lg">__LOGO__</div>
    <h1>__TITLE__</h1>
    <p>__TAG__</p>
  </div>
  <div class="ld"><div class="pc"><span id="spMsg">در حال اتصال امن…</span><b id="spPct">0%</b></div>
    <div class="bar"><i id="spBar"></i></div><div class="stg" id="spStg"><i></i><i></i><i></i><i></i><i></i></div></div>
</div>

<div class="app">
  <header class="hdr">
    <div class="ava" id="ava"></div>
    <div class="who"><b id="uName">—</b><small><i class="dot"></i><span>آنلاین · تحویل آنی</span></small></div>
    <div class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em></div>
    <button class="ib" id="bellBtn" aria-label="اعلان‌ها"><svg><use href="#i-bell"/></svg><span class="bd" id="bellN"></span></button>
  </header>

  <section class="pg" id="pg-home">
    <div class="glow hero"><div class="in">
      <div class="ph3"><i class="orb"><b></b></i><svg class="ph" viewBox="0 0 120 132" fill="none">
        <ellipse cx="58" cy="124" rx="36" ry="6" fill="url(#phD)"/>
        <rect x="25" y="11" width="62" height="106" rx="16" fill="#05070b"/>
        <rect x="29" y="7" width="62" height="106" rx="16" fill="url(#phB)" stroke="url(#gg)" stroke-width="1.8"/>
        <rect x="35" y="18" width="50" height="84" rx="9" fill="url(#phS)"/>
        <rect x="52" y="11" width="16" height="4" rx="2" fill="#05070b"/>
        <rect x="39" y="26" width="42" height="17" rx="6" fill="rgba(255,255,255,.1)" stroke="rgba(255,255,255,.14)" stroke-width=".8"/>
        <circle cx="46.5" cy="34.5" r="4.2" fill="url(#okG)"/>
        <path d="M44.6 34.6l1.4 1.4 2.6-2.8" stroke="#052e16" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M54 31.6h19M54 37.4h12" stroke="#BFDBFE" stroke-width="2.2" stroke-linecap="round"/>
        <rect x="39" y="52" width="9" height="12" rx="3" fill="#EFF6FF"/><rect x="50" y="52" width="9" height="12" rx="3" fill="#EFF6FF"/>
        <rect x="61" y="52" width="9" height="12" rx="3" fill="#EFF6FF"/><rect x="72" y="52" width="9" height="12" rx="3" fill="#DCFCE7"/>
        <path d="M43.5 58h0M54.5 58h0M65.5 58h0M76.5 58h0" stroke="#0b1a3a" stroke-width="3" stroke-linecap="round"/>
        <rect x="39" y="72" width="42" height="9" rx="4.5" fill="url(#gg)"/>
        <path d="M35 18h50l-50 40z" fill="url(#phG)"/>
        <rect x="52" y="106" width="16" height="2.4" rx="1.2" fill="rgba(255,255,255,.25)"/>
        <g><circle cx="96" cy="26" r="12" fill="url(#okG)"/><circle cx="92.6" cy="22" r="4.4" fill="#fff" opacity=".35"/>
        <path d="m90.6 26.2 3.6 3.6 6.4-7" stroke="#052e16" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></g>
        <g><path d="M9 82h11l5 5v17a2.6 2.6 0 0 1-2.6 2.6H9A2.6 2.6 0 0 1 6.4 104V84.6A2.6 2.6 0 0 1 9 82z" fill="url(#gm5)" stroke="rgba(255,255,255,.5)" stroke-width=".8"/>
        <rect x="10" y="91" width="10" height="9" rx="1.6" fill="#DBEAFE"/><path d="M15 91v9M10 95.5h10" stroke="#2563EB" stroke-width=".9"/></g>
      </svg></div>
      <div class="txt">
        <span class="live"><i class="dot"></i><span id="liveTxt">فروش باز است</span></span>
        <h1>شماره مجازی <span>تلگرام</span></h1>
        <p id="tagline"></p>
      </div>
      <div class="stats">
        <div class="stat"><b id="stC">—</b><small>کشور</small></div>
        <div class="stat"><b id="stO">—</b><small>اپراتور</small></div>
        <div class="stat"><b id="stF">—</b><small>شروع از (تومان)</small></div>
      </div>
      <button class="btn mt shn" data-go="shop"><svg><use href="#i-sim"/></svg>خرید شماره مجازی</button>
    </div></div>

    <div class="trust" id="trust"></div>

    <div class="qk">
      <button data-go="orders"><i class="t3 s"><svg><use href="#i-list"/></svg></i><b>شماره‌های من</b></button>
      <button data-go="me"><i class="t3 s c"><svg><use href="#i-gift"/></svg></i><b>دعوت دوستان</b></button>
    </div>

    <div id="homeLive"></div>

    <div class="sec"><h3><svg><use href="#i-flame"/></svg>کشورهای محبوب</h3><button data-go="shop">همه کشورها</button></div>
    <div class="hs" id="popular"></div>

    <div class="sec"><h3><svg><use href="#i-bolt"/></svg>همین الان تحویل شد</h3></div>
    <div class="feed" id="feed"><div class="fi"><i class="t3 s g"><svg><use href="#i-sim"/></svg></i><b>در حال بارگذاری…</b></div></div>

    <div class="sec hid" id="revHead"><h3><svg><use href="#i-star"/></svg>نظر خریداران</h3><span class="rva" id="revAvg"></span></div>
    <div class="rvw hid" id="revBox"></div>

    <div class="ad mt" id="adTeaser" data-go="air">
      <span class="gmw"><svg class="gm"><use href="#g-gem"/></svg></span>
      <div class="mid"><b>ایردراپ کریستال</b><small id="adTxt">کریستال جمع کن، به موجودی یا کد تخفیف تبدیل کن</small></div>
      <svg class="chev"><use href="#i-chev"/></svg>
    </div>

    <div class="note mt" id="guard"><svg><use href="#i-shield"/></svg><span id="noteTxt"></span></div>
  </section>

  <section class="pg" id="pg-shop">
    <div class="prods hid" id="prodTabs"></div>
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجوی کشور…" autocomplete="off"></div>
    <div class="chips" id="sortChips">
      <button class="chip on" data-s="r">پیشنهادی</button>
      <button class="chip" data-s="sold">پرفروش‌ترین</button>
      <button class="chip" data-s="cheap">ارزان‌ترین</button>
      <button class="chip" data-s="az">الفبا</button>
    </div>
    <div class="clist" id="clist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="seg" id="ordSeg">
      <button class="on" data-f="all">همه</button>
      <button data-f="live">فعال</button>
      <button data-f="done">تحویل‌شده</button>
      <button data-f="back">برگشتی</button>
    </div>
    <div id="olist"></div>
  </section>

  <section class="pg" id="pg-air"><div id="airBox"></div></section>

  <section class="pg" id="pg-me">
    <div class="card prof"><div class="ava" id="ava2"></div><div><b id="uName2">—</b><small id="uId"></small></div></div>
    <div class="grid4">
      <div class="gc"><small>موجودی</small><b id="mBal">—</b><b><em>تومان</em></b></div>
      <div class="gc"><small>شماره‌های تحویل‌شده</small><b id="mDone">—</b></div>
      <div class="gc"><small>زیرمجموعه‌ها</small><b id="mRef">—</b></div>
      <div class="gc"><small>درآمد دعوت</small><b id="mEarn">—</b><b><em>تومان</em></b></div>
    </div>
    <div class="ref mt" id="refBox">
      <b class="rh"><i class="t3 s g"><svg><use href="#i-gift"/></svg></i>دوستانت را دعوت کن</b>
      <p style="font-size:11px;color:var(--dim);margin-top:3px" id="refTxt"></p>
      <div class="lnk"><span id="refLink">—</span><button class="cpy" id="refCopy"><svg><use href="#i-copy"/></svg>کپی</button></div>
      <button class="btn mt2" id="refShare"><svg><use href="#i-share"/></svg>ارسال لینک برای دوستان</button>
    </div>
    <div class="menu mt">
      <button class="mi" data-go="orders"><i class="t3 s"><svg><use href="#i-list"/></svg></i><span>شماره‌های من</span><svg class="chev"><use href="#i-chev"/></svg></button>
      <button class="mi" data-go="notes"><i class="t3 s c"><svg><use href="#i-bell"/></svg></i><span>اعلان‌ها</span><b class="bdn hid" id="mNotes"></b><svg class="chev"><use href="#i-chev"/></svg></button>
      <button class="mi" data-go="air"><i class="t3 s k"><svg class="gmi"><use href="#g-gem"/></svg></i><span>ایردراپ کریستال</span><svg class="chev"><use href="#i-chev"/></svg></button>
    </div>
  </section>


  <section class="pg" id="pg-notes"><div id="nlist"></div></section>

  <section class="pg" id="pg-sup">
    <div id="supForm">
      <div class="card" style="text-align:center">
        <div class="ava" style="width:56px;height:56px;margin:0 auto 8px"><svg style="width:26px;height:26px"><use href="#i-headset"/></svg></div>
        <b style="font-size:14px;font-weight:900">پشتیبانی آنلاین</b>
        <p style="font-size:11px;color:var(--dim);margin-top:3px">پیامتان مستقیم به پشتیبان می‌رسد و جواب همین‌جا نمایش داده می‌شود.</p>
      </div>
      <div class="fld"><label>نام</label><input class="inp" id="supName" maxlength="60"></div>
      <div class="fld"><label>کد سفارش (اختیاری)</label><input class="inp ltr" id="supOrd" maxlength="40" placeholder="ma_…"></div>
      <div class="fld"><label>پیام</label><textarea class="inp" id="supTx" maxlength="900" placeholder="مشکل یا سوالتان را بنویسید…"></textarea></div>
      <button class="btn mt" id="supSendF"><svg><use href="#i-send"/></svg>ارسال به پشتیبانی</button>
      <button class="btn gh mt2 hid" id="supLink"><svg><use href="#i-headset"/></svg>گفت‌وگو در تلگرام</button>
    </div>
  </section>
</div>

<div class="chat hid" id="supChat">
  <div class="ch"><button class="x ib" id="supBack"><svg><use href="#i-arrow"/></svg></button>
    <div class="ava"><svg><use href="#i-headset"/></svg></div>
    <div style="flex:1"><b>پشتیبانی</b><small><i class="dot"></i>معمولا در چند دقیقه جواب می‌دهیم</small></div></div>
  <div class="log" id="supLog"></div>
  <button class="nw hid" id="supNew">پیام جدید<svg><use href="#i-down"/></svg></button>
  <div class="cb"><textarea id="supIn" rows="1" maxlength="900" placeholder="پیام…"></textarea><button class="idle" id="supSend"><svg><use href="#i-send"/></svg></button></div>
</div>

<button class="fab" id="fab" aria-label="پشتیبانی"><svg><use href="#i-headset"/></svg><span class="bd" id="fabN"></span></button>

<div class="dock"><nav id="dock">
  <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
  <button data-go="shop"><svg><use href="#i-globe"/></svg>کشورها</button>
  <button data-go="air" class="ctr"><span><svg class="gmi"><use href="#g-gem"/></svg></span><em>ایردراپ</em></button>
  <button data-go="orders" id="dkOrd"><svg><use href="#i-list"/></svg>شماره‌ها<span class="bd" id="dkN"></span></button>
  <button data-go="me"><svg><use href="#i-user"/></svg>حساب</button>
</nav></div>

<div class="ov" id="ov"></div>
<div class="sh" id="sh"><div class="grab"></div><div class="shd" id="shHead"></div><div class="bdy" id="shBody"></div></div>
<div class="toast" id="toast"></div>
<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var $ = function(id){ return document.getElementById(id); };
var D = document, H = D.documentElement;
var RM = false; try { RM = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); } catch(e){}

var HASH_INIT = (function(){
  try {
    var h = String(location.hash || '').replace(/^#/, '');
    var m = /(?:^|&)tgWebAppData=([^&]*)/.exec(h);
    return m ? decodeURIComponent(m[1]) : '';
  } catch(e){ return ''; }
})();
function initData(){
  try { if (TG && TG.initData) return TG.initData; } catch(e){}
  return HASH_INIT;
}
function tgUser(){
  try { if (TG && TG.initDataUnsafe && TG.initDataUnsafe.user) return TG.initDataUnsafe.user; } catch(e){}
  try {
    var m = /(?:^|&)user=([^&]*)/.exec(HASH_INIT);
    var u = m ? JSON.parse(decodeURIComponent(m[1])) : null;
    return (u && u.id) ? u : null;
  } catch(e){ return null; }
}

function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function faD(s){ return String(s == null ? '' : s).replace(/\d/g, function(d){ return String.fromCharCode(1776 + +d); }); }
var NF = null, DF = null;
try { NF = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }); } catch(e){}
try { DF = new Intl.DateTimeFormat('fa-IR', { day: 'numeric', month: 'long' }); } catch(e){}
function fa(n){
  n = Number(n) || 0;
  if (NF) return NF.format(n);
  try { return n.toLocaleString('fa-IR', { maximumFractionDigits: 2 }); } catch(e){ return faD(Math.round(n)); }
}
function digits(s){
  s = String(s == null ? '' : s);
  var out = '';
  for (var i = 0; i < s.length; i++) {
    var c = s.charCodeAt(i);
    if (c >= 1776 && c <= 1785) out += (c - 1776);
    else if (c >= 1632 && c <= 1641) out += (c - 1632);
    else if (c >= 48 && c <= 57) out += s[i];
  }
  return out;
}
function norm(s){
  return String(s || '').toLowerCase().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک')
    .replace(/[‌‏‎]/g, '').replace(/\s+/g, ' ').trim();
}
function mmss(sec){
  sec = Math.max(0, Math.floor(sec));
  var m = Math.floor(sec / 60), s = sec % 60;
  return faD((m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s);
}
function ago(ts){
  var d = Math.max(0, Math.floor(Date.now() / 1000 - (ts || 0)));
  if (d < 60) return 'همین الان';
  if (d < 3600) return fa(Math.floor(d / 60)) + ' دقیقه پیش';
  if (d < 86400) return fa(Math.floor(d / 3600)) + ' ساعت پیش';
  if (d < 604800) return fa(Math.floor(d / 86400)) + ' روز پیش';
  try { return DF ? DF.format(new Date(ts * 1000)) : new Date(ts * 1000).toLocaleDateString('fa-IR', { day: 'numeric', month: 'long' }); } catch(e){ return ''; }
}
function agoS(sec){
  sec = Math.max(0, sec | 0);
  if (sec < 60) return 'چند لحظه پیش';
  if (sec < 3600) return fa(Math.floor(sec / 60)) + ' دقیقه پیش';
  if (sec < 86400) return fa(Math.floor(sec / 3600)) + ' ساعت پیش';
  return fa(Math.floor(sec / 86400)) + ' روز پیش';
}
function ico(n, cls){ return '<svg' + (cls ? ' class="' + cls + '"' : '') + '><use href="#i-' + n + '"/></svg>'; }
function gem(cls){ return '<svg class="gmi' + (cls ? ' ' + cls : '') + '"><use href="#g-gem"/></svg>'; }
function stars(r){ var h = ''; for (var i = 1; i <= 5; i++) h += ico('star', i <= r ? 'on' : ''); return '<span class="sts">' + h + '</span>'; }
function tile(n, v){ return '<i class="t3 s' + (v ? ' ' + v : '') + '">' + ico(n) + '</i>'; }
function flg(e){
  e = String(e || '');
  return /\uD83C[\uDDE6-\uDDFF]|\uD83C[\uDFF3\uDFF4]/.test(e) ? esc(e) : ico('sim');
}
function tap(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k || 'light'); } catch(e){} }
function buzz(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); } catch(e){} }

var toastT;
function toast(msg, good){
  var t = $('toast');
  t.className = 'toast ' + (good ? 'ok' : 'er');
  t.innerHTML = ico(good ? 'check' : 'alert') + '<span>' + esc(msg) + '</span>';
  void t.offsetWidth;
  t.classList.add('on');
  clearTimeout(toastT);
  toastT = setTimeout(function(){ t.classList.remove('on'); }, 3400);
  buzz(good ? 'success' : 'error');
}

function copy(text, what){
  text = String(text || '');
  if (!text) return;
  var done = function(){ toast((what || 'متن') + ' کپی شد', true); };
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function(){ legacy(); });
      return;
    }
  } catch(e){}
  legacy();
  function legacy(){
    var a = D.createElement('textarea');
    a.value = text; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.opacity = '0';
    D.body.appendChild(a); a.select();
    try { D.execCommand('copy'); done(); } catch(e){ toast('کپی نشد'); }
    D.body.removeChild(a);
  }
}

function topupBot(need){
  if (S.tub) return; S.tub = 1; tap('medium');
  var toChat = function(){ S.tub = 0; if (B.bot) openLink('https://t.me/' + B.bot + '?start=topup'); else toast('افزایش موجودی در ربات'); };
  api('topup_bot', { need: Math.ceil(Number(need) || 0) }, function(){
    buzz('success'); toast('منوی شارژ در ربات برایتان فرستاده شد.', true);
    setTimeout(function(){ S.tub = 0; try { if (TG && TG.close) { TG.close(); return; } } catch(e){} toChat(); }, 1100);
  }, toChat);
}
function openLink(url){
  if (!url) return;
  try {
    if (TG && /^https:\/\/t\.me\//i.test(url) && TG.openTelegramLink) { TG.openTelegramLink(url); return; }
    if (TG && TG.openLink) { TG.openLink(url); return; }
  } catch(e){}
  window.open(url, '_blank', 'noopener');
}

function confirmBox(msg, cb){
  try {
    if (TG && TG.showConfirm && TG.isVersionAtLeast && TG.isVersionAtLeast('6.2')) { TG.showConfirm(msg, function(ok){ if (ok) cb(); }); return; }
  } catch(e){}
  if (window.confirm(msg)) cb();
}

var API = (function(){
  try { if (/^https?:$/.test(location.protocol) && location.pathname) return location.origin + location.pathname + '?mapi=1'; } catch(e){}
  return B.api || '';
})();
var GATED = false;
var READS = { me: 1, live: 1, feed: 1, orders: 1, notes: 1, sup_state: 1, sup_thread: 1, airdrop_state: 1,
              airdrop_leaderboard: 1, airdrop_referral: 1, airdrop_history: 1, airdrop_coupons: 1 };
function api(action, extra, ok, bad, tried){
  bad = bad || function(j){ toast((j && j.message) || 'خطا — دوباره امتحان کنید.'); };
  if (!API) { bad({ message: 'آدرس سرور تنظیم نشده است.' }); return; }
  var t0 = Date.now(), got = false;
  var body = { action: action, initData: initData() };
  for (var k in (extra || {})) body[k] = extra[k];
  var ctl = null, tm = null;
  try { ctl = new AbortController(); tm = setTimeout(function(){ ctl.abort(); }, 25000); } catch(e){}
  fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
               signal: ctl ? ctl.signal : undefined, cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
    .then(function(r){ got = true; return r.json().catch(function(){ return { ok: false, message: 'پاسخ سرور نامعتبر بود.' }; }); })
    .then(function(j){
      if (tm) clearTimeout(tm);
      if (j && j.ok) { ok(j); return; }
      if (j && j.error === 'unauthorized') { gate(j.message); return; }
      bad(j || {});
    })
    .catch(function(){
      if (tm) clearTimeout(tm);
      if (!got && !tried && READS[action] && Date.now() - t0 < 8000) { setTimeout(function(){ api(action, extra, ok, bad, 1); }, 700); return; }
      bad({ message: 'ارتباط با سرور برقرار نشد.' });
    });
}
function gate(msg){
  if (GATED) return;
  GATED = true;
  hideSplash(true);
  var g = D.createElement('div');
  g.className = 'gate';
  g.innerHTML = ico('lock') + '<b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p>' +
    (B.bot ? '<button class="btn" style="max-width:260px">' + ico('send') + 'رفتن به ربات</button>' : '');
  D.body.appendChild(g);
  var b = g.querySelector('button');
  if (b) b.onclick = function(){ openLink('https://t.me/' + B.bot); };
}

var SPL = { t0: (function(){ try { var o = performance.timeOrigin || performance.timing.navigationStart; if (o > 0 && Date.now() - o < 15000) return o; } catch(e){} return Date.now(); })(),
            ready: false, gone: false, min: Math.max(0, Math.min(20, Number(B.spl) || 0)) * 1000 };
var SPM = ['در حال اتصالِ امن…', 'بررسیِ حسابِ شما…', 'دریافتِ کشورها و قیمت‌ها…', 'بررسیِ موجودیِ شماره‌ها…', 'آماده‌سازیِ فروشگاه…', 'آماده است!'];
function splStep(){
  if (SPL.gone) return false;
  var el = Date.now() - SPL.t0, p = SPL.min ? Math.min(1, el / SPL.min) : 1;
  var pc = $('spPct'), bar = $('spBar'), m = $('spMsg'), g = $('spStg');
  if (pc) pc.textContent = Math.floor(p * 100) + '%';
  if (bar && (RM || !SPL.anim)) bar.style.transform = 'translate3d(' + ((1 - p) * 100).toFixed(2) + '%,0,0)';
  var mi = Math.min(SPM.length - 1, Math.floor(p * (SPM.length - 1) + (p >= 1 ? 1 : 0)));
  if (m && SPL.mi !== mi) { SPL.mi = mi; m.textContent = SPM[mi]; }
  if (g) [].forEach.call(g.children, function(x, k){ x.classList.toggle('on', p * g.children.length >= k + 1 - 0.001); });
  return true;
}
(function(){
  var bar = $('spBar'), el = Date.now() - SPL.t0;
  if (!bar || RM || SPL.min <= el) return;
  bar.style.animation = 'spb ' + SPL.min + 'ms linear ' + (-el) + 'ms both'; SPL.anim = true;
})();
splStep();
SPL.iv = setInterval(function(){ if (!splStep()) clearInterval(SPL.iv); }, 100);
function hideSplash(now){
  if (SPL.gone || (SPL.hiding && !now)) return;
  SPL.hiding = true;
  var wait = now ? 0 : Math.max(0, SPL.min - (Date.now() - SPL.t0));
  setTimeout(function(){
    if (SPL.gone) return;
    SPL.anim = false; var bar = $('spBar'); if (bar) bar.style.animation = 'none';
    splStep();
    SPL.gone = true; clearInterval(SPL.iv);
    var s = $('splash'), done = false;
    var reveal = function(){
      if (done) return;
      done = true;
      if (s) s.classList.add('out');
      H.classList.add('in');
      H.classList.remove('boot');
      setTimeout(function(){ if (s && s.parentNode) s.parentNode.removeChild(s); }, 820);
      setTimeout(function(){ H.classList.remove('in'); }, 1500);
    };
    requestAnimationFrame(function(){ requestAnimationFrame(reveal); });
    setTimeout(reveal, 250);
  }, wait);
}
setTimeout(function(){ hideSplash(false); }, Math.max(3000, SPL.min + 2500 - (Date.now() - SPL.t0)));
var CATS = B.cats || [], ITEMS = B.items || [];
var CAT = {}, ITEM = {}, BYCAT = {};
CATS.forEach(function(c){ CAT[c.id] = c; c.k = norm(c.name) + ' ' + String(c.en || '').toLowerCase(); });
ITEMS.forEach(function(i){ ITEM[i.i] = i; i.pr = i.pr || 'telegram'; (BYCAT[i.c] = BYCAT[i.c] || []).push(i); });
var PRODS = { telegram: { fa: 'تلگرام', e: 'send' }, instagram: { fa: 'اینستاگرام', e: 'cam' }, whatsapp: { fa: 'واتساپ', e: 'bub' } };
var PLIST = Object.keys(PRODS).filter(function(k){ return ITEMS.some(function(i){ return i.pr === k; }); });
function catOps(cid, pr){ return (BYCAT[cid] || []).filter(function(i){ return !pr || i.pr === pr; }); }
function prodFa(pr){ return (PRODS[pr] || PRODS.telegram).fa; }

var S = { clim: 40, page: '', stack: [], bal: 0, me: null, live: {}, orders: null, of: 'all', sort: 'r', q: '',
          sheet: '', cur: '', uid: 0, notes: 0, ends: {}, polls: {}, feed: [], fi: 0 };
S.prod = PLIST[0] || 'telegram';
var U = tgUser() || {};
S.uid = U.id || 0;

var PAGES = ['home', 'shop', 'orders', 'air', 'me', 'notes', 'sup'];
var DOCKMAP = { notes: 'me' };

function go(p, back){
  if (PAGES.indexOf(p) < 0 || p === 'sup') p = 'home';
  if (p === S.page) { window.scrollTo(0, 0); return; }
  if (!back && S.page) { S.stack.push(S.page); if (S.stack.length > 12) S.stack.shift(); }
  var prev = S.page;
  S.page = p;
  PAGES.forEach(function(x){ var e = $('pg-' + x); if (e) e.classList.toggle('on', x === p); });
  var dk = DOCKMAP[p] || p;
  [].forEach.call(D.querySelectorAll('#dock button'), function(b){ b.classList.toggle('on', b.getAttribute('data-go') === dk); });
  H.classList.toggle('nofab', p === 'sup');
  window.scrollTo(0, 0);
  if (prev === 'sup') supLeave();
  if (prev === 'air') { axStop(); axFlush(); }
  if (p === 'shop') drawShop();
  if (p === 'orders') loadOrders();
  if (p === 'air') loadAir();
  if (p === 'me') { drawMe(); refresh(); }
  if (p === 'notes') loadNotes();
  if (p === 'sup') supEnter();
  if (p === 'home') { drawHome(); loadFeed(); }
  backBtn();
}
function goBack(){
  if (TOUR.open) { tourEnd(); return; }
  if (S.sheet) { closeSheet(); return; }
  var p = S.stack.pop();
  go(p || 'home', true);
}
function backBtn(){
  if (!TG || !TG.BackButton) return;
  try { if (S.sheet || S.page !== 'home' || TOUR.open) TG.BackButton.show(); else TG.BackButton.hide(); } catch(e){}
}
D.addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('[data-go]') : null;
  if (!el) return;
  ev.preventDefault();
  tap();
  var p = el.getAttribute('data-go');
  if (S.sheet) closeSheet();
  go(p);
});

function setBal(v){
  if (v == null || isNaN(Number(v))) return;
  S.bal = Number(v);
  $('bal').textContent = fa(S.bal);
  var m = $('mBal'); if (m) m.textContent = fa(S.bal);
}
$('bellBtn').onclick = function(){ tap(); go('notes'); };
$('fab').onclick = function(){ tap('medium'); go('sup'); };

function setNotes(n){
  S.notes = Math.max(0, n | 0);
  var t = S.notes > 9 ? '۹+' : fa(S.notes);
  $('bellN').textContent = t;
  $('bellBtn').classList.toggle('has', S.notes > 0);
  var m = $('mNotes'); m.textContent = t; m.classList.toggle('hid', S.notes === 0);
}

function avaHtml(url){
  var n = (U.first_name || U.username || '؟').trim();
  var ini = esc(n.charAt(0).toUpperCase());
  return url ? '<img src="' + esc(url) + '" alt="" onerror="if(this.parentNode)this.parentNode.textContent=\'' + ini.replace(/'/g, '') + '\'">' : ini;
}
function drawSelf(avatar){
  var name = ((U.first_name || '') + ' ' + (U.last_name || '')).trim() || (U.username ? '@' + U.username : 'کاربر');
  $('uName').textContent = name;
  $('uName2').textContent = name;
  $('uId').textContent = S.uid ? 'شناسه: ' + faD(S.uid) : '';
  $('ava').innerHTML = avaHtml(avatar);
  $('ava2').innerHTML = avaHtml(avatar);
}

function minPrice(){
  var m = 0;
  ITEMS.forEach(function(i){ if (i.p > 0 && (!m || i.p < m)) m = i.p; });
  return m;
}
function drawHome(){
  $('tagline').textContent = B.tagline || '';
  $('noteTxt').textContent = B.note || '';
  $('guard').classList.toggle('hid', !B.note);
  $('stC').textContent = fa(CATS.length);
  $('stO').textContent = fa(ITEMS.length);
  $('stF').textContent = CATS.length ? fa(minPrice()) : '—';
  $('liveTxt').textContent = CATS.length ? 'فروش باز است · تحویل آنی' : 'به‌زودی';
  var hotC = {}; ITEMS.forEach(function(i){ if (i.h) hotC[i.c] = 1; });
  var pop = CATS.slice().sort(function(a, b){ return ((hotC[b.id] ? 1 : 0) - (hotC[a.id] ? 1 : 0)) || (b.sold - a.sold) || (a.from - b.from); }).slice(0, 10);
  $('popular').innerHTML = pop.length ? pop.map(function(c, ix){
    return '<button class="cc" data-cat="' + esc(c.id) + '">' + (ix < 3 && c.sold > 0 ? '<span class="hot">' + ico('flame') + '</span>' : '') +
      '<div class="fl">' + flg(c.e) + '</div><b>' + esc(c.name) + '</b><small>' + fa(c.n) + ' اپراتور</small>' +
      '<div class="pr">از ' + fa(c.from) + '</div></button>';
  }).join('') : '<div class="emp">' + tile('globe', 'c') + '<div><b>کشورهای تازه در راه است</b><small>فعلا شماره‌ای برای فروش نیست</small></div></div>';
  drawHomeLive();
}
$('popular').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-cat]');
  if (b) { tap(); openCountry(b.getAttribute('data-cat')); }
});

function isOpen(r){ return r && (r.nst === 'waiting' || r.nst === 'buying' || (r.nst === 'done' && r.repeat)); }
function drawHomeLive(){
  var rows = Object.keys(S.live).map(function(k){ return S.live[k]; }).filter(isOpen)
    .sort(function(a, b){ return b.at - a.at; });
  var box = $('homeLive');
  var n = rows.filter(function(r){ return r.nst === 'waiting' || r.nst === 'buying'; }).length;
  $('dkN').textContent = fa(n);
  $('dkOrd').classList.toggle('has', n > 0);
  if (!rows.length) { box.innerHTML = ''; return; }
  box.innerHTML = '<div class="sec"><h3>' + ico('sim') + 'شماره‌های فعال شما</h3></div>' + rows.map(function(r){
    var dn = r.nst === 'done';
    return '<button class="lv' + (dn ? ' dn' : '') + '" data-live="' + esc(r.id) + '" style="width:100%;text-align:right">' +
      '<span class="fl">' + flg(r.e) + '</span><span class="mid"><b>' + esc(r.phone || '…') + '</b><small>' +
      (dn ? ico('check', 'ii g') + 'کد: ' + esc(r.code) : ico('clock', 'ii') + 'در انتظار کد — ' + esc(r.name)) + '</small></span>' +
      '<span class="cd" data-cd="' + esc(r.id) + '">' + mmss(leftOf(r.id)) + '</span></button>';
  }).join('');
}
$('homeLive').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-live]');
  if (b) { tap(); openLive(S.live[b.getAttribute('data-live')]); }
});

function setEnd(r){ if (r && r.id) S.ends[r.id] = Date.now() + Math.max(0, r.left | 0) * 1000; }
function leftOf(id){ return Math.max(0, ((S.ends[id] || 0) - Date.now()) / 1000); }
function putRow(r){
  if (!r || !r.id) return;
  var old = S.live[r.id];
  S.live[r.id] = r;
  setEnd(r);
  if (old && old.nst !== 'done' && r.nst === 'done' && r.code) codeArrived(r);
}
function codeArrived(r){
  buzz('success');
  toast('کد رسید: ' + r.code, true);
  confetti();
  if (!S.sheet) openLive(r);
}
setInterval(function(){
  if (D.hidden) return;
  [].forEach.call(D.querySelectorAll('[data-cd]'), function(el){
    el.textContent = mmss(leftOf(el.getAttribute('data-cd')));
  });
  if (S.sheet === 'live' && S.cur) ringTick();
}, 1000);

var FEEDT = null;
function loadFeed(){
  api('feed', {}, function(j){
    S.feed = j.list || [];
    S.fi = 0;
    drawFeed(true);
    drawTrust(j.trust || {}, j.rev || {});
    drawRev(j.rev || {});
  }, function(){});
}
function marq(items, secs){
  return '<div class="tk" style="animation-duration:' + secs + 's">' + items + items + '</div>';
}
function drawTrust(t, rv){
  var l = [['shield', 'پرداخت امن از کیف پول'], ['bolt', 'تحویل خودکار و آنی'],
           ['wallet', 'برگشت خودکار پول اگر کد نیاید']];
  if (t.done > 0) l.push(['check', fa(t.done) + '+ شماره‌ی تحویل‌شده']);
  if (rv.n > 0) l.push(['star', 'امتیاز ' + fa(rv.avg) + ' از ۵ — ' + fa(rv.n) + ' نظر']);
  if (CATS.length) l.push(['globe', fa(CATS.length) + ' کشور']);
  $('trust').innerHTML = marq(l.map(function(x){ return '<span class="tb">' + ico(x[0]) + esc(x[1]) + '</span>'; }).join(''), l.length * 5);
}
function drawRev(rv){
  var l = rv.list || [];
  $('revHead').classList.toggle('hid', !l.length);
  $('revBox').classList.toggle('hid', !l.length);
  if (!l.length) { $('revBox').innerHTML = ''; return; }
  $('revAvg').innerHTML = ico('star') + fa(rv.avg) + ' از ۵ — ' + fa(rv.n) + ' نظر';
  var cards = l.map(function(x){
    return '<div class="rv"><div class="h"><i class="av">' + esc(x.n.charAt(0)) +
      (x.p ? '<img src="' + esc(x.p) + '" alt="" loading="lazy" decoding="async" onerror="this.remove()">' : '') + '</i><b>' + esc(x.n) + '</b>' + stars(x.r) + '</div>' +
      '<p>' + esc(x.x) + '</p><small>' + flg(x.e) + '<span>خرید شماره · ' + agoS(x.t) + '</span></small></div>';
  }).join('');
  $('revBox').innerHTML = l.length > 1 ? marq(cards, l.length * 6) : '<div class="tk one">' + cards + '</div>';
}
function drawFeed(first){
  var f = $('feed');
  if (!S.feed.length) { f.innerHTML = '<div class="fi">' + tile('sim', 'g') + '<b>اولین خرید امروز مال شما باشد!</b></div>'; return; }
  var x = S.feed[S.fi % S.feed.length];
  var html = '<i class="fb">' + flg(x.e) + '</i><b>' + esc(x.c) + '</b><em style="font-style:normal;color:var(--dim)">کد تحویل شد</em><span>' + agoS(x.t) + '</span>';
  var old = f.querySelector('.fi');
  var el = D.createElement('div');
  el.className = 'fi' + (first ? '' : ' in');
  el.innerHTML = html;
  f.appendChild(el);
  if (!first) {
    void el.offsetWidth;
    el.classList.remove('in');
    if (old) { old.classList.add('out'); setTimeout(function(){ if (old.parentNode) old.parentNode.removeChild(old); }, 550); }
  } else if (old) old.parentNode.removeChild(old);
  clearTimeout(FEEDT);
  FEEDT = setTimeout(feedNext, 3200);
}
function feedNext(){
  if (S.page === 'home' && !D.hidden && S.feed.length > 1) { S.fi++; drawFeed(false); }
  else FEEDT = setTimeout(feedNext, 3200);
}

var SHOPK = '', SHOPL = '';
function drawShop(force){
  var key = S.q + '|' + S.sort + '|' + S.prod;
  if (!force && key === SHOPK) return;
  SHOPK = key;
  drawProds();
  var q = norm(S.q);
  var pr = PLIST.length > 1 ? S.prod : '';
  var list = CATS.filter(function(c){ return (!q || c.k.indexOf(q) >= 0) && catOps(c.id, pr).length; }).map(function(c){
    var ops = catOps(c.id, pr), f = 0, h = 0; ops.forEach(function(i){ if (!f || i.p < f) f = i.p; if (i.h) h = 1; });
    return { id: c.id, name: c.name, e: c.e, sold: c.sold, r: c.r, n: ops.length, from: f, h: h };
  });
  var s = S.sort;
  list.sort(function(a, b){
    if (s === 'sold') return (b.sold - a.sold) || (a.r - b.r);
    if (s === 'cheap') return (a.from - b.from) || (a.r - b.r);
    if (s === 'az') return a.name.localeCompare(b.name, 'fa');
    return (b.h - a.h) || (a.from - b.from) || (a.r - b.r);
  });
  if (key !== SHOPL) { SHOPL = key; S.clim = 40; }
  var more = list.length - S.clim;
  list = list.slice(0, S.clim);
  var box = $('clist');
  if (!CATS.length) { box.innerHTML = emptyHtml('globe', 'فعلا شماره‌ای برای فروش نیست', 'به‌زودی کشورها اضافه می‌شوند.'); return; }
  if (!list.length) { box.innerHTML = emptyHtml('search', 'کشوری پیدا نشد'); return; }
  box.innerHTML = list.map(function(c, ix){
    var w = ix === list.length - 1 && list.length % 2 === 1;
    return '<button class="ctl' + (c.h ? ' h' : '') + (w ? ' w' : '') + '" data-cat="' + esc(c.id) + '">' +
      (c.h ? '<span class="bd">محبوب</span>' : (c.sold >= 5 ? '<span class="bd">پرفروش</span>' : '')) +
      '<span class="fl">' + flg(c.e) + '</span>' +
      '<span class="tx"><b>' + esc(c.name) + '</b><small>' + fa(c.n) + ' اپراتور' + (c.sold > 0 ? ' · ' + fa(c.sold) + ' تحویل' : '') + '</small></span>' +
      '<span class="pz"><small>از</small><b>' + fa(c.from) + '</b><small>تومان</small></span>' +
      '<span class="go">خرید' + ico('chev') + '</span></button>';
  }).join('') + (more > 0 ? '<button class="more" id="cMore">نمایشِ ' + fa(Math.min(more, 40)) + ' کشورِ دیگر</button>' : '');
}
function drawProds(){
  var box = $('prodTabs');
  box.classList.toggle('hid', PLIST.length < 2);
  if (PLIST.length < 2) return;
  box.style.setProperty('--n', PLIST.length);
  box.innerHTML = PLIST.map(function(k){
    var n = 0; CATS.forEach(function(c){ if (catOps(c.id, k).length) n++; });
    return '<button class="' + k + (S.prod === k ? ' on' : '') + '" data-pr="' + k + '"><i>' + ico(PRODS[k].e) + '</i>' + esc(PRODS[k].fa) +
      '<small><b>' + fa(n) + '</b> کشور</small></button>';
  }).join('');
}
$('prodTabs').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-pr]');
  if (!b || b.getAttribute('data-pr') === S.prod) return;
  tap(); S.prod = b.getAttribute('data-pr'); drawShop(true);
});
function emptyHtml(ic, t, s){ return '<div class="empty">' + ico(ic) + '<b>' + esc(t) + '</b><span>' + esc(s || '') + '</span></div>'; }
$('clist').addEventListener('click', function(ev){
  if (ev.target.closest('#cMore')) { tap(); S.clim += 40; SHOPK = ''; drawShop(true); return; }
  var b = ev.target.closest('[data-cat]');
  if (b) { tap(); openCountry(b.getAttribute('data-cat')); }
});
var QT = null;
$('q').addEventListener('input', function(){
  var v = this.value;
  clearTimeout(QT);
  QT = setTimeout(function(){ S.q = v; drawShop(); }, 120);
});
$('sortChips').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-s]');
  if (!b) return;
  tap();
  S.sort = b.getAttribute('data-s');
  [].forEach.call(this.children, function(x){ x.classList.toggle('on', x === b); });
  drawShop();
});

function openSheet(kind, head, body){
  S.sheet = kind;
  $('shHead').innerHTML = head + '<button class="x" id="shX">' + ico('x') + '</button>';
  $('shBody').innerHTML = body;
  $('shBody').onclick = null;
  $('shBody').scrollTop = 0;
  H.classList.add('shon');
  $('ov').classList.add('on');
  $('sh').classList.add('on');
  $('shX').onclick = function(){ tap(); closeSheet(); };
  backBtn();
}
function closeSheet(){
  var was = S.sheet;
  S.sheet = '';
  H.classList.remove('shon');
  $('ov').classList.remove('on');
  $('sh').classList.remove('on');
  if (was === 'live') { stopPoll(); S.cur = ''; }
  backBtn();
}
$('ov').onclick = function(){ closeSheet(); };
(function(){
  var sh = $('sh'), y0 = null, dy = 0;
  sh.addEventListener('touchstart', function(e){
    if ($('shBody').scrollTop > 0 && !e.target.closest('.grab,.shd')) { y0 = null; return; }
    y0 = e.touches[0].clientY; dy = 0;
  }, { passive: true });
  sh.addEventListener('touchmove', function(e){
    if (y0 == null) return;
    dy = e.touches[0].clientY - y0;
    if (dy > 0) { sh.style.transition = 'none'; sh.style.transform = 'translateY(' + dy + 'px)'; }
  }, { passive: true });
  sh.addEventListener('touchend', function(){
    if (y0 == null) return;
    sh.style.transition = ''; sh.style.transform = '';
    if (dy > 110) closeSheet();
    y0 = null;
  });
})();

function openCountry(cid){
  var c = CAT[cid];
  if (!c) return;
  var pr = catOps(cid, S.prod).length ? S.prod : ((BYCAT[cid] || [])[0] || {}).pr || 'telegram';
  var ops = catOps(cid, pr).slice().sort(function(a, b){ return a.p - b.p; });
  openSheet('cat',
    '<span class="fl">' + flg(c.e) + '</span><b>' + esc(c.name) + '<small>' + fa(ops.length) + ' اپراتور · شماره‌ی ' + esc(prodFa(pr)) + '</small></b>',
    '<div class="opg">' + ops.map(function(i, ix){
      var w = ix === ops.length - 1 && ops.length % 2 === 1;
      return '<button class="co' + (w ? ' w' : '') + '" data-buy="' + esc(i.i) + '" style="width:100%"><span class="mid"><b>' + esc(i.o || 'اپراتور') + '</b>' +
        (i.b ? '<span class="bdg">' + esc(i.b) + '</span>' : (ix === 0 && ops.length > 1 ? '<span class="bdg b">ارزان‌ترین</span>' : '')) +
        '<small>تحویل آنی · کد در چند ثانیه</small></span><span class="pbtn">' + fa(i.p) + ' تومان</span></button>';
    }).join('') + '</div>' + (B.note ? '<div class="note mt">' + ico('shield') + '<span>' + esc(B.note) + '</span></div>' : ''));
  $('shBody').onclick = function(ev){
    var b = ev.target.closest('[data-buy]');
    if (b) { tap('medium'); openBuy(b.getAttribute('data-buy')); }
  };
}

var BUY = { item: '', disc: 0, busy: false };
function openBuy(iid){
  var i = ITEM[iid];
  if (!i) return;
  var c = CAT[i.c] || { e: '', name: '' };
  BUY.item = iid; BUY.disc = 0; BUY.busy = false;
  openSheet('buy',
    '<span class="fl">' + flg(c.e) + '</span><b>' + esc(c.name) + '<small>' + esc(i.o || 'اپراتور') + ' · شماره‌ی مجازی ' + esc(prodFa(i.pr)) + '</small></b>',
    '<div class="card"><div class="kvr"><span>قیمت شماره</span><b>' + fa(i.p) + ' تومان</b></div>' +
    '<div class="kvr" id="bDiscR" style="display:none"><span id="bDiscL">تخفیف</span><b class="g" id="bDisc"></b></div>' +
    '<div class="kvr"><span>مبلغ قابل پرداخت</span><b id="bTot"></b></div>' +
    '<div class="kvr"><span>موجودی شما</span><b id="bBal"></b></div></div>' +
    '<div id="bAct" class="mt"></div>' +
    (B.note ? '<div class="note mt2">' + ico('shield') + '<span>' + esc(B.note) + '</span></div>' : ''));
  drawBuy();
}
function cpDisc(t){
  var c = S.cp;
  if (!c || t <= 0 || t < (c.min || 0)) return 0;
  var d = c.kind === 'fixed' ? c.value : Math.round(t * c.value / 100);
  if (c.max > 0) d = Math.min(d, c.max);
  return Math.max(0, Math.min(d, t));
}
function buyTotal(){ var i = ITEM[BUY.item]; return i ? Math.max(0, i.p - BUY.disc) : 0; }
function drawBuy(){
  var i = ITEM[BUY.item];
  if (!i || S.sheet !== 'buy') return;
  BUY.disc = cpDisc(i.p);
  var tot = buyTotal();
  $('bTot').textContent = fa(tot) + ' تومان';
  $('bBal').textContent = fa(S.bal) + ' تومان';
  $('bDiscR').style.display = BUY.disc > 0 ? '' : 'none';
  $('bDisc').textContent = fa(BUY.disc) + ' تومان';
  $('bDiscL').textContent = S.cp ? 'تخفیف (' + S.cp.code + ')' : 'تخفیف';
  var need = tot - S.bal;
  var a = $('bAct');
  if (need > 0) {
    a.innerHTML = '<div class="note bl">' + ico('wallet') + '<span>موجودی‌تان ' + fa(need) + ' تومان کم است؛ کیف پول از داخلِ ربات شارژ می‌شود.</span></div>' +
      '<button class="btn mt2" id="bGo">' + ico('send') + 'شارژ در ربات</button>';
    $('bGo').onclick = function(){ topupBot(need); };
  } else {
    a.innerHTML = '<button class="btn" id="bPay">' + ico('sim') + 'پرداخت و دریافت شماره</button>';
    $('bPay').onclick = doBuy;
  }
}
function doBuy(){
  if (BUY.busy) return;
  var i = ITEM[BUY.item];
  if (!i) return;
  BUY.busy = true;
  tap('heavy');
  var b = $('bPay');
  b.disabled = true;
  b.innerHTML = '<span class="dot" style="background:#fff"></span>در حال گرفتن شماره…';
  api('buy', { item: i.i, seen: i.p }, function(j){
    BUY.busy = false;
    setBal(j.balance);
    if ('coupon' in j) S.cp = j.coupon;
    if (j.row) { putRow(j.row); drawHomeLive(); S.orders = null; openLive(j.row); }
    buzz('success');
  }, function(j){
    BUY.busy = false;
    if (j && j.balance != null) setBal(j.balance);
    var e = j && j.error;
    if (e === 'price_changed' && j.price) { i.p = Number(j.price) || i.p; }
    if (j && 'coupon' in j) S.cp = j.coupon;
    if (S.sheet === 'buy') drawBuy();
    toast((j && j.message) || 'خرید انجام نشد.');
    if (e === 'too_many') { closeSheet(); go('orders'); }
  });
}
function stInfo(r){
  if (r.nst === 'waiting' || r.nst === 'buying') return ['w', 'در انتظار کد'];
  if (r.nst === 'done') return ['d', 'کد تحویل شد'];
  if (r.ref || r.st === 'rejected') return ['', 'مبلغ برگشت'];
  if (r.st === 'done') return ['d', 'تحویل شد'];
  if (r.st === 'paid') return ['w', 'در حال آماده‌سازی'];
  return ['', 'در حال ثبت'];
}
var RING = 2 * Math.PI * 58;
function openLive(r){
  if (!r || !r.id) return;
  S.live[r.id] = S.live[r.id] || r;
  if (!S.ends[r.id]) setEnd(r);
  S.cur = r.id;
  S.rate = 0; S.rateTx = ''; S.rateAn = false;
  openSheet('live', '<span class="fl">' + flg(r.e) + '</span><b>' + esc(r.name) + '<small id="lvSt"></small></b>', '<div id="lvB"></div>');
  drawLive();
  startPoll();
}
function rateNote(){
  return S.rateAn ? 'نظرت بی‌نام و بدون عکس نمایش داده می‌شود.' : 'نظرت با اسم کوچک و عکس پروفایلت در «نظرات خریداران» نمایش داده می‌شود.';
}
function drawLive(){
  var r = S.live[S.cur];
  var box = $('lvB');
  if (!r || !box) return;
  if (D.activeElement && D.activeElement.id === 'rateTx') return;
  var st = stInfo(r), wt = r.nst === 'waiting' || r.nst === 'buying', dn = r.nst === 'done';
  $('lvSt').innerHTML = '<span class="pill ' + st[0] + '">' + st[1] + '</span>';
  var h = '';
  if (r.phone) h += '<div class="pn"><small>شماره‌ی مجازی شما</small><div class="num' + (wt || dn ? '' : ' off') + '">' + esc(r.phone) + '</div>' +
    (wt || dn ? '<button class="cpy" data-cp="phone">' + ico('copy') + 'کپی شماره</button>' : '') + '</div>';
  if (wt) {
    h += '<div class="ring"><svg viewBox="0 0 132 132"><circle class="tr" cx="66" cy="66" r="58"/><circle class="pr" id="lvRing" cx="66" cy="66" r="58" stroke-dasharray="' + RING.toFixed(1) + '"/></svg>' +
      '<div class="c"><div><small>در انتظار کد</small><b id="lvLeft">' + mmss(leftOf(r.id)) + '</b><small>زمان باقی‌مانده</small></div></div></div>';
    h += '<div class="row2 mt"><button class="btn gh" data-act="check">' + ico('refresh') + 'بررسی کد</button>' +
      '<button class="btn rd" data-act="cancel">' + ico('x') + 'لغو و برگشت پول</button></div>';
  } else if (dn) {
    h += '<div class="code"><small>' + ico('spark', 'ii') + 'کد تایید شما رسید</small><b>' + esc(r.code) + '</b><button class="cpy" data-cp="code">' + ico('copy') + 'کپی کد</button></div>';
    if (r.repeat) h += '<div class="note bl">' + ico('clock') + '<span>کدِ دوباره · <b data-cd="' + esc(r.id) + '">' + mmss(leftOf(r.id)) + '</b></span></div>' +
      '<button class="btn mt2" data-act="repeat">' + ico('refresh') + 'دریافت کد دوباره</button>';
    else h += '<div class="note">' + ico('shield') + '<span>این شماره فقط برای شماست</span></div>';
    h += r.rv ? '<div class="rate dn">' + stars(r.rv) + '<span>ممنون! نظرت ثبت شده.</span></div>'
      : '<div class="rate" id="rate"><b>از این خرید راضی بودی؟</b><div class="pick">' + [1, 2, 3, 4, 5].map(function(i){
          return '<button data-star="' + i + '"' + (i <= S.rate ? ' class="on"' : '') + ' aria-label="' + i + '">' + ico('star') + '</button>';
        }).join('') + '</div><textarea class="inp" id="rateTx" maxlength="160" rows="2" placeholder="نظرت را کوتاه بنویس (اختیاری)">' + esc(S.rateTx) + '</textarea>' +
        '<button class="anon' + (S.rateAn ? ' on' : '') + '" data-act="anon"><i></i><span>بی‌نام نمایش بده</span></button>' +
        '<small class="rnote" id="rateNo">' + rateNote() + '</small>' +
        '<button class="btn mt2" data-act="rate"' + (S.rate ? '' : ' disabled') + '>' + ico('send') + 'ثبت نظر</button></div>';
  } else {
    h += '<div class="empty" style="padding:16px 8px">' + ico(r.ref ? 'wallet' : 'clock') + '<b>' + (r.ref ? 'مبلغ به کیف پول شما برگشت' : 'این شماره بسته شد') + '</b><span>' +
      (r.nst === 'expired' ? 'کدی تا پایان مهلت نرسید.' : r.nst === 'cancel' ? 'شماره لغو شد.' : 'اگر سوالی دارید، داخلِ ربات بپرسید.') + '</span></div>';
    h += '<button class="btn mt2" data-go="shop">' + ico('sim') + 'خرید شماره‌ی دیگر</button>';
  }
  h += '<div class="kvr mt"><span>مبلغ</span><b>' + fa(r.total) + ' تومان</b></div><div class="kvr"><span>کد سفارش</span><b class="ltr" style="font-size:11px">' + esc(r.id) + '</b></div>';
  box.innerHTML = h;
  ringTick();
}
function ringTick(){
  var r = S.live[S.cur], rg = $('lvRing'), lf = $('lvLeft');
  if (!r || !rg) return;
  var left = leftOf(r.id), wait = Math.max(60, r.wait || 900);
  rg.style.strokeDashoffset = (RING * (1 - Math.min(1, left / wait))).toFixed(1);
  if (lf) lf.textContent = mmss(left);
}
$('shBody').addEventListener('click', function(ev){
  if (S.sheet !== 'live') return;
  var r = S.live[S.cur];
  if (!r) return;
  var cp = ev.target.closest('[data-cp]');
  if (cp) {
    tap();
    if (cp.getAttribute('data-cp') === 'phone') copy(r.phone, 'شماره');
    else copy(r.code, 'کد');
    return;
  }
  var sb = ev.target.closest('[data-star]');
  if (sb) {
    tap();
    S.rate = +sb.getAttribute('data-star');
    [].forEach.call($('rate').querySelectorAll('[data-star]'), function(b){ b.classList.toggle('on', +b.getAttribute('data-star') <= S.rate); });
    $('rate').querySelector('[data-act="rate"]').disabled = false;
    return;
  }
  var a = ev.target.closest('[data-act]');
  if (!a || a.disabled) return;
  var act = a.getAttribute('data-act');
  tap('medium');
  if (act === 'anon') {
    S.rateAn = !S.rateAn;
    a.classList.toggle('on', S.rateAn);
    $('rateNo').textContent = rateNote();
    return;
  }
  if (act === 'rate') {
    if (!S.rate) return;
    a.disabled = true;
    api('review', { order: r.id, r: S.rate, x: S.rateTx, a: S.rateAn ? 1 : 0 }, function(j){
      if (D.activeElement) D.activeElement.blur();
      putRow(j.row); S.rate = 0; S.rateTx = ''; S.rateAn = false; drawLive(); S.orders = null;
      buzz('success'); toast('ممنون! نظرت ثبت شد.', true);
    }, function(j){ a.disabled = false; toast((j && j.message) || 'ثبت نشد.'); });
    return;
  }
  if (act === 'check') {
    a.disabled = true;
    api('num', { order: r.id, check: 1 }, function(j){
      a.disabled = false;
      putRow(j.row); setBal(j.balance); drawLive(); drawHomeLive();
      if (j.row && j.row.nst === 'waiting') toast('هنوز کدی نیامده — خودکار هم بررسی می‌شود.', true);
    }, function(j){ a.disabled = false; toast((j && j.message) || 'دوباره امتحان کنید.'); });
  } else if (act === 'cancel') {
    confirmBox('شماره لغو شود و مبلغ به کیف پول برگردد؟', function(){
      a.disabled = true;
      api('num_cancel', { order: r.id }, function(j){
        putRow(j.row); setBal(j.balance); drawLive(); drawHomeLive(); S.orders = null;
        toast('لغو شد و مبلغ به کیف پول برگشت.', true);
      }, function(j){
        a.disabled = false;
        if (j && j.row) { putRow(j.row); drawLive(); drawHomeLive(); }
        toast((j && j.message) || 'لغو انجام نشد.');
      });
    });
  } else if (act === 'repeat') {
    a.disabled = true;
    api('num_repeat', { order: r.id }, function(j){
      putRow(j.row); drawLive(); drawHomeLive(); startPoll();
      toast('درخواست کد تازه ثبت شد — چند لحظه صبر کنید.', true);
    }, function(j){
      a.disabled = false;
      if (j && j.row) { putRow(j.row); drawLive(); }
      toast((j && j.message) || 'انجام نشد.');
    });
  }
});
$('shBody').addEventListener('input', function(ev){ if (ev.target.id === 'rateTx') S.rateTx = ev.target.value; });
function stopPoll(){ clearTimeout(S.polls.live); S.polls.live = null; }
function startPoll(){
  stopPoll();
  var r = S.live[S.cur];
  if (!r || !isOpen(r)) return;
  S.polls.live = setTimeout(function tick(){
    if (S.sheet !== 'live' || !S.cur) return;
    if (D.hidden) { S.polls.live = setTimeout(tick, 3000); return; }
    api('num', { order: S.cur }, function(j){
      if (j.row) { putRow(j.row); if (S.sheet === 'live') drawLive(); drawHomeLive(); }
      setBal(j.balance);
      if (S.sheet === 'live' && isOpen(S.live[S.cur])) S.polls.live = setTimeout(tick, 5000);
    }, function(){ if (S.sheet === 'live') S.polls.live = setTimeout(tick, 8000); });
  }, 4000);
}

function loadOrders(){
  if (!S.orders) $('olist').innerHTML = '<div class="sk"></div><div class="sk"></div><div class="sk"></div>';
  else drawOrders();
  api('orders', {}, ordersIn, function(j){
    if (!S.orders) $('olist').innerHTML = emptyHtml('alert', 'بارگذاری نشد', (j && j.message) || '');
  });
}
function ordersIn(j){
  S.orders = j.list || [];
  S.orders.forEach(function(r){ putRow(r); });
  setBal(j.balance);
  if (S.page === 'orders') drawOrders();
  drawHomeLive();
}
function drawOrders(){
  var f = S.of, list = (S.orders || []).map(function(r){ return S.live[r.id] || r; }).filter(function(r){
    if (f === 'live') return isOpen(r);
    if (f === 'done') return r.st === 'done';
    if (f === 'back') return r.st === 'rejected';
    return true;
  });
  if (!list.length) {
    $('olist').innerHTML = emptyHtml('inbox', f === 'all' ? 'هنوز شماره‌ای نگرفته‌اید' : 'موردی نیست', f === 'all' ? 'اولین شماره‌ی مجازی‌تان را همین حالا بگیرید.' : '') +
      (f === 'all' ? '<button class="btn" data-go="shop">' + ico('sim') + 'خرید شماره</button>' : '');
    return;
  }
  $('olist').innerHTML = list.map(function(r){
    var st = stInfo(r);
    return '<div class="or" data-oid="' + esc(r.id) + '"><div class="t"><span class="fl">' + flg(r.e) + '</span><div class="mid"><b>' + esc(r.name) +
      '</b><small>' + ago(r.at) + ' — ' + fa(r.total) + ' تومان</small></div><span class="pill ' + st[0] + '">' + st[1] + '</span></div>' +
      (r.phone ? '<div class="kv"><div><small>شماره</small><b>' + esc(r.phone) + '</b></div>' +
        '<div class="kc"><small>کد</small><b>' + (r.code ? esc(r.code) : '—') + '</b></div></div>' : '') + '</div>';
  }).join('');
}
$('olist').addEventListener('click', function(ev){
  var o = ev.target.closest('[data-oid]');
  if (!o) return;
  var r = S.live[o.getAttribute('data-oid')];
  if (r && r.phone) { tap(); openLive(r); }
});
$('ordSeg').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-f]');
  if (!b) return;
  tap();
  S.of = b.getAttribute('data-f');
  [].forEach.call(this.children, function(x){ x.classList.toggle('on', x === b); });
  drawOrders();
});


function refLink(){ return (B.bot && S.uid) ? 'https://t.me/' + B.bot + '?start=ref' + S.uid : ''; }
function drawMe(){
  var m = S.me || {};
  setBal(S.bal);
  $('mDone').textContent = m.done != null ? fa(m.done) : '—';
  $('mRef').textContent = m.ref ? fa(m.ref.n) : '—';
  $('mEarn').textContent = m.ref ? fa(m.ref.earned) : '—';
  var on = B.ref && B.ref.on && refLink();
  $('refBox').classList.toggle('hid', !on);
  if (on) {
    $('refLink').textContent = refLink();
    $('refTxt').textContent = 'از هر خریدِ دوستانی که با لینک شما بیایند ' + fa(B.ref.pct) + '٪ پورسانت می‌گیرید' +
      (m.ref && m.ref.pending > 0 ? ' — ' + fa(m.ref.pending) + ' تومان آماده‌ی برداشت در ربات.' : '.');
  }
}
$('refCopy').onclick = function(){ tap(); copy(refLink(), 'لینک دعوت'); };
$('refShare').onclick = function(){
  tap('medium');
  var l = refLink();
  if (l) openLink('https://t.me/share/url?url=' + encodeURIComponent(l) + '&text=' + encodeURIComponent('با این لینک وارد ربات شو و شماره‌ی مجازی تلگرام آنی بگیر'));
};

function loadNotes(){
  $('nlist').innerHTML = '<div class="sk"></div><div class="sk"></div>';
  api('notes', {}, function(j){
    var list = j.list || [], n = j.n | 0;
    setNotes(0);
    if (!list.length) { $('nlist').innerHTML = emptyHtml('bell', 'اعلانی ندارید', 'خبر شماره‌ها، کدها و برگشت وجه‌ها اینجا می‌آید.'); return; }
    $('nlist').innerHTML = list.map(function(x, i){
      return '<div class="ntf' + (i < n ? ' new' : '') + '"><div class="h">' + noteIco(x.e) + '<b>' + esc(x.h) + '</b><time>' + ago(x.t) + '</time></div>' +
        (x.b ? '<p>' + noteBody(x.b) + '</p>' : '') +
        ((x.c && x.c.length) || x.o ? '<div class="cps">' + (x.c || []).map(function(c){ return '<button class="cpy" data-copy="' + esc(c) + '">' + ico('copy') + '<span class="ltr">' + esc(c) + '</span></button>'; }).join('') +
          (x.o ? '<button class="cpy" data-open="' + esc(x.o) + '">' + ico('sim') + 'مشاهده</button>' : '') + '</div>' : '') + '</div>';
    }).join('');
  }, function(j){ $('nlist').innerHTML = emptyHtml('alert', 'بارگذاری نشد', (j && j.message) || ''); });
}
$('nlist').addEventListener('click', function(ev){
  var c = ev.target.closest('[data-copy]');
  if (c) { tap(); copy(c.getAttribute('data-copy')); return; }
  var o = ev.target.closest('[data-open]');
  if (o) {
    tap();
    var id = o.getAttribute('data-open');
    if (S.live[id]) openLive(S.live[id]);
    else api('num', { order: id }, function(j){ if (j.row) { putRow(j.row); openLive(j.row); } }, function(){ go('orders'); });
  }
});

var EMO = /^(?:[\u2190-\u21FF\u2300-\u23FF\u2500-\u27BF\u2B00-\u2BFF\uFE0F\u200D]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|\uD83E[\uDC00-\uDFFF])+\s*/;
function noteIco(e){
  e = String(e || '');
  var k = /\u2705|\uD83C\uDF89|\uD83D\uDD11/.test(e) ? ['check', 'g'] : /\u21A9|\uD83D\uDCB8|\uD83D\uDCB0/.test(e) ? ['refresh', 'c']
        : /\uD83C\uDF81|\uD83D\uDC8E/.test(e) ? ['gift', 'm'] : /\u26A0|\u274C|\u26D4/.test(e) ? ['alert', 'r']
        : /\u260E|\uD83D\uDCF1/.test(e) ? ['sim', 'g'] : /\uD83D\uDCB3|\u2795|\uD83E\uDDFE/.test(e) ? ['wallet', ''] : ['bell', 'c'];
  return tile(k[0], k[1]);
}
function noteBody(b){
  return String(b).split('\n').map(function(l){
    l = l.replace(EMO, '');
    return /[\u0600-\u06FF]/.test(l) ? esc(l) : '<bdi>' + esc(l) + '</bdi>';
  }).join('\n');
}
function confetti(){
  var c = D.createElement('div');
  c.className = 'cf';
  var cols = ['#60A5FA', '#4ADE80', '#FFFFFF', '#3B82F6', '#22C55E'], h = '';
  for (var i = 0; i < 28; i++) {
    h += '<i style="left:' + (Math.random() * 100).toFixed(1) + '%;background:' + cols[i % cols.length] +
      ';animation-delay:' + (Math.random() * .35).toFixed(2) + 's;animation-duration:' + (1.2 + Math.random() * .8).toFixed(2) + 's"></i>';
  }
  c.innerHTML = h;
  D.body.appendChild(c);
  setTimeout(function(){ if (c.parentNode) c.parentNode.removeChild(c); }, 2400);
}
var SUP = { has: false, last: 0, timer: null, watch: null, open: false, busy: false, seq: 0, unread: 0, day: '' };
function supTime(t){
  var d = new Date((t || 0) * 1000), p = function(n){ return (n < 10 ? '0' : '') + n; };
  return faD(p(d.getHours()) + ':' + p(d.getMinutes()));
}
function supDayKey(d){ return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate(); }
function supSep(at){
  var d = new Date((at || 0) * 1000), k = supDayKey(d);
  if (k === SUP.day) return '';
  SUP.day = k;
  var now = new Date(), y = new Date(now.getTime() - 86400000), lbl;
  if (k === supDayKey(now)) lbl = 'امروز';
  else if (k === supDayKey(y)) lbl = 'دیروز';
  else { try { lbl = d.toLocaleDateString('fa-IR', { day: 'numeric', month: 'long' }); } catch(e){ lbl = ''; } }
  return '<div class="cday"><span>' + esc(lbl) + '</span></div>';
}
function supBadge(n){
  SUP.unread = Math.max(0, n | 0);
  var t = SUP.unread > 9 ? '۹+' : fa(SUP.unread);
  $('fabN').textContent = SUP.unread ? t : '';
  $('fab').classList.toggle('has', SUP.unread > 0);
}
function supBubble(m, tmp){
  return '<div class="bub ' + (m.me ? 'me' : 'them') + (tmp ? ' pend' : '') + '"' + (m.id ? ' data-id="' + m.id + '"' : '') +
    (tmp ? ' data-tmp="' + tmp + '"' : '') + '>' + esc(m.t) + '<time>' + supTime(m.at) + '</time></div>';
}
function supView(chat){
  var on = !!chat && S.page === 'sup';
  $('supForm').classList.toggle('hid', !!chat);
  $('supChat').classList.toggle('hid', !on);
  H.classList.toggle('chaton', on);
  if (on) supFit();
}
function supFit(){
  var c = $('supChat');
  if (!c || c.classList.contains('hid')) return;
  var vv = window.visualViewport, full = window.innerHeight;
  var h = vv ? vv.height : full, top = vv ? Math.max(0, vv.offsetTop) : 0;
  try { if (TG && TG.viewportHeight > 200 && TG.viewportHeight < h) h = TG.viewportHeight; } catch(e){}
  var log = $('supLog'), near = log.scrollHeight - log.scrollTop - log.clientHeight < 90;
  c.style.height = Math.round(h) + 'px';
  c.style.top = Math.round(top) + 'px';
  c.classList.toggle('kb', full - h > 120);
  if (near) log.scrollTop = log.scrollHeight;
}
function supAppend(list, force){
  var log = $('supLog');
  var near = log.scrollHeight - log.scrollTop - log.clientHeight < 90;
  var first = SUP.last === 0, fresh = 0, h = '';
  list.forEach(function(m){
    if (!m || !m.id || m.id <= SUP.last) return;
    SUP.last = m.id;
    if (!m.me) fresh++;
    if (m.me) {
      var pend = log.querySelectorAll('.bub.pend');
      for (var i = 0; i < pend.length; i++) {
        if (pend[i].getAttribute('data-t') === m.t) {
          pend[i].classList.remove('pend'); pend[i].setAttribute('data-id', m.id); pend[i].removeAttribute('data-t');
          return;
        }
      }
    }
    h += supSep(m.at) + supBubble(m);
  });
  if (h) log.insertAdjacentHTML('beforeend', h);
  if ((h && near) || force) log.scrollTop = log.scrollHeight;
  if (fresh) supMarkRead();
  if (fresh && !first) { buzz('success'); if (!near && !force) $('supNew').classList.remove('hid'); }
}
function supMarkRead(){
  var all = $('supLog').querySelectorAll('.bub'), seen = false;
  for (var i = all.length - 1; i >= 0; i--) {
    if (all[i].classList.contains('them')) seen = true;
    else if (seen) { if (all[i].classList.contains('rd')) break; all[i].classList.add('rd'); }
  }
}
$('supNew').onclick = function(){ var l = $('supLog'); l.scrollTop = l.scrollHeight; this.classList.add('hid'); };
$('supLog').addEventListener('scroll', function(){
  if (this.scrollHeight - this.scrollTop - this.clientHeight < 60) $('supNew').classList.add('hid');
}, { passive: true });
function supNext(){
  clearTimeout(SUP.timer);
  if (SUP.open && !D.hidden) SUP.timer = setTimeout(supPoll, 4000);
}
function supPoll(){
  clearTimeout(SUP.timer);
  if (!SUP.open) return;
  api('sup_thread', { after: SUP.last }, function(j){
    var msgs = j.msgs || [];
    if (msgs.length && !SUP.has) { SUP.has = true; supView(true); }
    supAppend(msgs, false);
    supBadge(0);
    supNext();
  }, function(){ supNext(); });
}
function supWatch(){ clearTimeout(SUP.watch); }
function supEnter(){
  var on = !!B.supform;
  $('supLink').classList.toggle('hid', !B.sup);
  [].forEach.call($('supForm').querySelectorAll('.fld,#supSendF'), function(e){ e.classList.toggle('hid', !on); });
  if (!$('supName').value) $('supName').value = ((U.first_name || '') + ' ' + (U.last_name || '')).trim();
  if (!on) return;
  SUP.open = true;
  supView(SUP.has);
  if (SUP.has) setTimeout(function(){ var l = $('supLog'); l.scrollTop = l.scrollHeight; }, 30);
  supPoll();
}
function supLeave(){
  SUP.open = false;
  clearTimeout(SUP.timer);
  var c = $('supChat');
  if (!c.classList.contains('hid')) {
    try { $('supIn').blur(); } catch(e){}
    c.classList.add('hid');
    H.classList.remove('chaton');
  }
  supWatch();
}
$('supBack').onclick = function(){ tap(); goBack(); };
$('supLink').onclick = function(){ tap(); openLink(B.sup); };
if (window.visualViewport) {
  window.visualViewport.addEventListener('resize', supFit);
  window.visualViewport.addEventListener('scroll', supFit);
}
window.addEventListener('resize', supFit);
function supGrow(){
  var t = $('supIn');
  t.style.height = 'auto';
  t.style.height = Math.min(120, t.scrollHeight + 2) + 'px';
  $('supSend').classList.toggle('idle', !t.value.trim());
}
$('supIn').addEventListener('input', supGrow);
$('supIn').addEventListener('keydown', function(ev){
  if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); $('supSend').click(); }
});
$('supSend').onclick = function(){
  var t = $('supIn'), tx = t.value.trim();
  if (!tx || SUP.busy) return;
  SUP.busy = true;
  tap();
  var tmp = 't' + (++SUP.seq), log = $('supLog'), now = Date.now() / 1000;
  log.insertAdjacentHTML('beforeend', supSep(now) + supBubble({ me: true, t: tx, at: now }, tmp));
  var el = log.querySelector('[data-tmp="' + tmp + '"]');
  if (el) el.setAttribute('data-t', tx);
  log.scrollTop = log.scrollHeight;
  $('supNew').classList.add('hid');
  t.value = '';
  supGrow();
  api('sup_msg', { text: tx }, function(j){
    SUP.busy = false;
    if (el && j.msg && j.msg.id) {
      el.classList.remove('pend'); el.removeAttribute('data-t'); el.setAttribute('data-id', j.msg.id);
      if (j.msg.id > SUP.last) SUP.last = j.msg.id;
    }
    supNext();
  }, function(j){
    SUP.busy = false;
    if (el) { el.classList.remove('pend'); el.classList.add('fail'); el.removeAttribute('data-t'); }
    if (!t.value) { t.value = tx; supGrow(); }
    toast((j && j.message) || 'فرستاده نشد.');
  });
};
$('supSendF').onclick = function(){
  var tx = $('supTx').value.trim(), btn = this;
  if (tx.length < 3) { toast('پیام کوتاه است'); $('supTx').focus(); return; }
  btn.disabled = true;
  tap('medium');
  api('support_send', { name: $('supName').value.trim(), order: $('supOrd').value.trim(), text: tx }, function(j){
    btn.disabled = false;
    $('supTx').value = ''; $('supOrd').value = '';
    try { $('supTx').blur(); } catch(e){}
    SUP.has = true;
    supView(true);
    supAppend(j.msgs || [], true);
    supNext();
    toast('پیام شما رسید — جواب همین‌جا می‌آید.', true);
  }, function(j){ btn.disabled = false; toast((j && j.message) || 'فرستاده نشد.'); });
};
var AX = { st: null, t0: 0, tab: 'lb', timer: null, cache: {} };
var TP = { q: 0, busy: false, t: null, en: 0, at: 0, left: 0, fx: 0 };
var GEM = '<div class="gw"><i class="rays"></i><i class="sp a"></i><i class="sp b"></i><i class="sp c"></i>' +
  '<span class="gmv"><svg class="gm3" viewBox="0 0 64 68"><ellipse cx="32" cy="64" rx="18" ry="3" fill="url(#gmSh)"/><use href="#g-gem"/></svg>' +
  '<i class="gsh"></i></span></div>';
var MIC = { daily: ['cal', ''], ref1: ['uplus', 'g'], ref5: ['rocket', 'm'], order1: ['sim', 'c'] };
function axStop(){ clearInterval(AX.timer); AX.timer = null; }
function loadAir(){
  if (!AX.st) $('airBox').innerHTML = '<div class="sk" style="height:220px;border-radius:24px"></div><div class="sk"></div><div class="sk"></div>';
  else drawAir();
  api('airdrop_state', {}, function(j){ airIn(j); if (S.page !== 'air') return; drawAir(); if (!j.tour_seen) tourStart(); },
      function(j){ if (!AX.st) $('airBox').innerHTML = emptyHtml('alert', 'ایردراپ بارگذاری نشد', (j && j.message) || ''); });
}
function airIn(j){ AX.st = j; AX.t0 = Date.now(); tpSync(j, 0); setBal(j.wallet_balance); }
function warm(){
  if (D.hidden || GATED) return;
  if (!S.orders && S.page !== 'orders') api('orders', {}, ordersIn, function(){});
  if (!AX.st && S.page !== 'air') setTimeout(function(){
    if (!AX.st && S.page !== 'air') api('airdrop_state', {}, function(j){ if (!AX.st) airIn(j); }, function(){});
  }, 400);
}
function axNow(){
  var s = AX.st;
  if (!s) return 0;
  return Number(s.crystals) + Number(s.rate) * ((Date.now() - AX.t0) / 3600000);
}
function axFmt(v){ return fa(Math.floor(v * 10) / 10); }
function drawAir(){
  var s = AX.st;
  if (!s) return;
  var pct = Math.min(100, (Number(s.xp) / Math.max(1, Number(s.xp_need))) * 100);
  var days = Math.max(0, Math.ceil((Number(s.season_end) - Date.now() / 1000) / 86400));
  var full = s.boost_n >= s.boost_max;
  var h = '<div class="ax"><div class="tapz" id="axTap">' + GEM + '</div>' +
    '<div class="cr"><span id="axC">' + axFmt(axNow()) + '</span> <small>کریستال</small></div>' +
    '<span class="rt"><i class="dot"></i>' + fa(Math.round(s.rate)) + ' کریستال در ساعت</span>' +
    '<div class="en"><div class="h"><span>' + ico('bolt', 'ii') + 'انرژی ماین</span><span id="axE"></span></div>' +
    '<div class="bar"><i id="axEb"></i></div><small id="axTl"></small></div>' +
    '<div class="lvl"><div class="h"><span>سطح <b>' + fa(s.level) + '</b></span><span id="axX">' + fa(Math.floor(s.xp)) + ' / ' + fa(Math.round(s.xp_need)) + '</span></div>' +
    '<div class="bar"><i id="axL" style="width:' + pct.toFixed(1) + '%"></i></div></div>' +
    '<div class="stats"><div class="stat"><b>' + fa(s.streak) + '</b><small>روز پشت‌سرهم</small></div>' +
    '<div class="stat"><b>' + fa(s.boost_n) + '/' + fa(s.boost_max) + '</b><small>تقویت سرعت</small></div>' +
    '<div class="stat"><b>' + fa(days) + '</b><small>روز تا پایان فصل</small></div></div></div>';
  if (s.bank) h += '<div class="bkc"><div class="bh">' + tile('bank', 'c') + '<div><b>بانک الماس</b><small>سطح ' + fa(s.bank.level) + ' · سودِ روزانه ' +
    fa(Math.round(Number(s.bank.rate) * 100) / 100) + '٪</small></div></div>' +
    '<div class="bv"><div><small>کیف‌پولِ الماس</small><b>' + fa(Math.floor(s.bank.wallet)) + gem() + '</b></div>' +
    '<div><small>موجودیِ صندوقِ بانک</small><b>' + fa(Math.floor(s.bank.vault)) + gem() + '</b></div></div></div>';
  h += '<div class="axg">' +
    '<button data-ax="boost"' + (full ? ' disabled style="opacity:.5"' : '') + '>' + tile('bolt') + 'تقویت سرعت<small>' + (full ? 'به سقف رسیدید' : fa(s.boost_price) + ' تومان — ' + fa(Math.round(s.boost_step * 100)) + '٪ سریع‌تر') + '</small></button>' +
    '<button data-ax="redeem">' + tile('wallet', 'g') + 'تبدیل به موجودی<small>هر کریستال ' + fa(s.redeem_rate) + ' تومان</small></button>' +
    '<button data-ax="coupon">' + tile('ticket', 'c') + 'کد تخفیف<small>برای خرید در مینی‌اپ‌ها</small></button></div>';
  h += '<div class="sec"><h3>' + ico('trophy') + 'ماموریت‌ها</h3></div>';
  h += (s.missions || []).map(function(m){
    var p = Math.min(100, (m.progress / Math.max(1, m.need)) * 100);
    var ic = MIC[m.id] || ['spark', ''];
    var btn = m.claimed ? '<span class="go dn">' + ico('check', 'ii') + 'دریافت شد</span>' : m.ready ? '<button class="go" data-claim="' + esc(m.id) + '">دریافت ' + fa(m.reward) + gem() + '</button>' : '<span class="go wt">' + fa(m.reward) + gem() + '</span>';
    return '<div class="ms' + (m.ready ? ' rdy' : '') + '"><i class="t3 ' + ic[1] + '">' + ico(ic[0]) + '</i><div class="mid"><b>' + esc(m.name) + '</b><small>' + fa(m.progress) + ' از ' + fa(m.need) + '</small>' +
      '<div class="bar"><i style="width:' + p.toFixed(0) + '%"></i></div></div>' + btn + '</div>';
  }).join('');
  h += '<div class="seg mt" id="axTabs"><button data-t="lb"' + (AX.tab === 'lb' ? ' class="on"' : '') + '>برترین‌ها</button>' +
    '<button data-t="rf"' + (AX.tab === 'rf' ? ' class="on"' : '') + '>دعوت</button>' +
    '<button data-t="hs"' + (AX.tab === 'hs' ? ' class="on"' : '') + '>تاریخچه</button></div><div id="axTab"></div>';
  h += '<div class="note bl mt">' + ico('spark') + '<span>جمعِ خودکارِ کریستال تا ۲۴ ساعت</span></div>';
  $('airBox').innerHTML = h;
  axTab();
  axStop();
  axPaint();
  AX.timer = setInterval(function(){
    var e = $('axC');
    if (!e || S.page !== 'air') { axStop(); return; }
    if (!D.hidden) axPaint();
  }, 1000);
}
function tpSync(j, pend){
  TP.en = Number(j.energy) - pend; TP.at = Date.now(); TP.left = Number(j.tap_left) - pend;
}
function tpEn(){
  var s = AX.st;
  return s ? Math.min(Number(s.energy_max), TP.en + (Date.now() - TP.at) / 1000 / Number(s.regen)) : 0;
}
function setTx(el, t){ if (el && el.textContent !== t) el.textContent = t; }
function axPaint(){
  var s = AX.st, c = $('axC');
  if (!s || !c) return;
  var en = Math.max(0, Math.floor(tpEn())), mx = Number(s.energy_max);
  setTx(c, axFmt(axNow()));
  setTx($('axE'), fa(en) + ' / ' + fa(mx));
  var tf = 'translate3d(' + (100 - Math.min(100, en / mx * 100)).toFixed(1) + '%,0,0)', eb = $('axEb');
  if (eb.style.transform !== tf) eb.style.transform = tf;
  setTx($('axTl'), TP.left > 0 ? 'هر ضربه ' + fa(s.tap_value) + '+ کریستال — امروز ' + fa(Math.max(0, TP.left)) + ' ضربه مانده' : 'سقف ماینِ امروز پر شد — فردا دوباره!');
  $('axTap').classList.toggle('off', en < 1 || TP.left < 1);
}
function axFx(box, x, y, txt){
  if (TP.fx > 10) return;
  TP.fx++;
  var f = D.createElement('span');
  f.className = 'fx';
  f.textContent = txt;
  f.style.left = x + 'px'; f.style.top = y + 'px';
  f.addEventListener('animationend', function(){ TP.fx--; if (f.parentNode) f.parentNode.removeChild(f); });
  box.appendChild(f);
}
function axFlush(){
  clearTimeout(TP.t); TP.t = null;
  if (!TP.q) return;
  if (TP.busy) { TP.t = setTimeout(axFlush, 800); return; }
  var n = TP.q;
  TP.q = 0; TP.busy = true;
  api('airdrop_tap', { n: n }, function(j){
    TP.busy = false;
    var s = AX.st;
    if (!s) return;
    var lv = s.level;
    s.crystals = Number(j.crystals) + TP.q * Number(j.tap_value); AX.t0 = Date.now();
    s.level = j.level; s.xp = j.xp; s.xp_need = j.xp_need; s.rate = j.rate; s.tap_value = j.tap_value;
    s.energy_max = j.energy_max; s.regen = j.regen; s.tap_left = j.tap_left;
    tpSync(j, TP.q);
    if (j.level !== lv && S.page === 'air') { drawAir(); buzz('success'); toast('سطح ' + fa(j.level) + '! سرعت ماین بیشتر شد.', true); }
    else if ($('axL')) { $('axL').style.width = Math.min(100, Number(j.xp) / Math.max(1, Number(j.xp_need)) * 100).toFixed(1) + '%'; $('axX').textContent = fa(Math.floor(j.xp)) + ' / ' + fa(Math.round(j.xp_need)); axPaint(); }
    if (TP.q && !TP.t) TP.t = setTimeout(axFlush, 2500);
  }, function(){ TP.busy = false; });
}
D.addEventListener('visibilitychange', function(){ if (D.hidden) axFlush(); });
$('airBox').addEventListener('pointerdown', function(ev){
  var g = ev.target.closest('#axTap');
  var s = AX.st;
  if (!g || !s) return;
  ev.preventDefault();
  var en = tpEn();
  if (en < 1 || TP.left < 1) {
    if (!g.dataset.w) { g.dataset.w = 1; toast(TP.left < 1 ? 'سقف ماینِ امروز پر شد — فردا دوباره!' : 'انرژی تمام شد — چند ثانیه صبر کن تا پر شود.'); setTimeout(function(){ delete g.dataset.w; }, 2500); }
    return;
  }
  TP.en = en - 1; TP.at = Date.now(); TP.left--; TP.q++;
  s.crystals = Number(s.crystals) + Number(s.tap_value);
  var r = g.getBoundingClientRect();
  axFx(g, ev.clientX - r.left, ev.clientY - r.top, '+' + fa(s.tap_value));
  if (g.animate) g.animate([{ transform: 'scale(.93)' }, { transform: 'scale(1)' }], { duration: 140, easing: 'ease-out' });
  tap('light');
  axPaint();
  if (!TP.t) TP.t = setTimeout(axFlush, 2500);
});
function axTab(){
  var t = AX.tab, box = $('axTab');
  if (!box) return;
  var c = AX.cache[t];
  if (c) box.innerHTML = c;
  else box.innerHTML = '<div class="sk"></div><div class="sk"></div>';
  var done = function(html){ AX.cache[t] = html; if (AX.tab === t && $('axTab')) $('axTab').innerHTML = html; };
  var fail = function(){ if (!c) done(emptyHtml('alert', 'بارگذاری نشد', '')); };
  if (t === 'lb') api('airdrop_leaderboard', {}, function(j){
    var l = j.list || [];
    done(l.length ? '<div class="menu">' + l.slice(0, 30).map(function(x){
      var nm = x.name || (x.username ? '@' + x.username : 'کاربر');
      return '<div class="lb' + (x.uid === j.me ? ' me' : '') + '"><span class="rk">' + fa(x.rank) + '</span><span>' + esc(nm) + '</span><b>' + axFmt(x.crystals) + gem() + '</b></div>';
    }).join('') + '</div>' : emptyHtml('trophy', 'هنوز کسی نیست', 'اولین نفر جدول باشید!'));
  }, fail);
  if (t === 'rf') api('airdrop_referral', {}, function(j){
    var top = j.top || [];
    done('<div class="ref"><b class="rh">' + tile('users', 'g') + fa(j.ref_count) + ' دوست دعوت کرده‌اید</b>' +
      '<p style="font-size:11px;color:var(--dim);margin-top:3px">درآمد دعوت: ' + fa(j.ref_earned) + ' تومان</p>' +
      (j.link ? '<div class="lnk"><span>' + esc(j.link) + '</span><button class="cpy" data-copy="' + esc(j.link) + '">' + ico('copy') + 'کپی</button></div>' : '') + '</div>' +
      (top.length ? '<div class="sec"><h3>' + ico('users') + 'بیشترین دعوت</h3></div><div class="menu">' + top.slice(0, 20).map(function(x){
        var nm = x.name || (x.username ? '@' + x.username : 'کاربر');
        return '<div class="lb' + (x.uid === S.uid ? ' me' : '') + '"><span class="rk">' + fa(x.rank) + '</span><span>' + esc(nm) + '</span><b>' + fa(x.count) + ico('users', 'ii') + '</b></div>';
      }).join('') + '</div>' : ''));
  }, fail);
  if (t === 'hs') api('airdrop_history', {}, function(j){
    api('airdrop_coupons', {}, function(k){
      var a = (j.list || []).map(function(x){ return { t: x.t, i: ['wallet', 'g'], h: axFmt(x.crystals) + ' کریستال ← ' + fa(x.toman) + ' تومان', s: 'به موجودی اضافه شد' }; });
      var b = (k.list || []).map(function(x){ return { t: x.t, i: ['ticket', 'c'], h: 'کد ' + x.code + ' — ' + fa(x.toman) + ' تومان', s: x.used ? 'استفاده شد' : (x.active ? 'فعال' : 'منقضی'), c: x.active ? x.code : '' }; });
      var all = a.concat(b).sort(function(p, q){ return q.t - p.t; });
      done(all.length ? all.map(function(x){
        return '<div class="ntf"><div class="h">' + tile(x.i[0], x.i[1]) + '<b>' + esc(x.h) + '</b><time>' + ago(x.t) + '</time></div><p>' + esc(x.s) + '</p>' +
          (x.c ? '<div class="cps"><button class="cpy" data-copy="' + esc(x.c) + '">' + ico('copy') + '<span class="ltr">' + esc(x.c) + '</span></button></div>' : '') + '</div>';
      }).join('') : emptyHtml('clock', 'تاریخچه‌ای نیست', 'تبدیل‌ها و کدهای تخفیف شما اینجا می‌آید.'));
    }, fail);
  }, fail);
}
$('airBox').addEventListener('click', function(ev){
  var cp = ev.target.closest('[data-copy]');
  if (cp) { tap(); copy(cp.getAttribute('data-copy')); return; }
  var tb = ev.target.closest('[data-t]');
  if (tb) {
    tap();
    AX.tab = tb.getAttribute('data-t');
    [].forEach.call($('axTabs').children, function(x){ x.classList.toggle('on', x === tb); });
    axTab();
    return;
  }
  var cl = ev.target.closest('[data-claim]');
  if (cl) {
    tap('medium');
    cl.disabled = true;
    api('airdrop_mission_claim', { id: cl.getAttribute('data-claim') }, function(j){
      AX.st = j.state; AX.t0 = Date.now(); drawAir(); confetti();
      toast(fa(j.reward) + ' کریستال گرفتید!', true);
    }, function(j){ cl.disabled = false; toast((j && j.message) || 'انجام نشد.'); });
    return;
  }
  var a = ev.target.closest('[data-ax]');
  if (!a || a.disabled) return;
  tap();
  var k = a.getAttribute('data-ax');
  if (k === 'boost') axBoost();
  if (k === 'redeem') axConvert('redeem');
  if (k === 'coupon') axConvert('coupon');
});
function axBoost(){
  var s = AX.st;
  confirmBox('با ' + fa(s.boost_price) + ' تومان از کیف پول، سرعت جمع‌شدن کریستال ' + fa(Math.round(s.boost_step * 100)) + '٪ بیشتر شود؟', function(){
    api('airdrop_boost', {}, function(j){
      AX.st = j.state; AX.t0 = Date.now(); setBal(j.balance); drawAir();
      toast('سرعت بیشتر شد! تقویت ' + fa(j.boost_n) + ' از ' + fa(AX.st.boost_max), true);
    }, function(j){
      toast((j && j.message) || 'انجام نشد.');
    });
  });
}
function axConvert(kind){
  var s = AX.st, have = Math.floor(axNow() * 10) / 10;
  var min = kind === 'redeem' ? s.redeem_min : s.coupon_min;
  var t = kind === 'redeem' ? 'تبدیل کریستال به موجودی' : 'ساخت کد تخفیف';
  openSheet('ax', '<span class="fl ok' + (kind === 'redeem' ? '' : ' c') + '">' + ico(kind === 'redeem' ? 'wallet' : 'ticket') + '</span><b>' + t + '<small>موجودی: ' + axFmt(have) + ' کریستال</small></b>',
    '<div class="fld"><label>چند کریستال؟ (حداقل ' + fa(min) + ')</label><div class="inw"><input class="inp" id="axIn" inputmode="decimal"><span class="sf">کریستال</span></div></div>' +
    '<div class="card mt2"><div class="kvr"><span>' + (kind === 'redeem' ? 'به کیف پول شما' : 'ارزش کد تخفیف') + '</span><b class="g" id="axEq">—</b></div>' +
    (kind === 'coupon' ? '<div class="kvr"><span>اعتبار</span><b>' + fa(s.coupon_days) + ' روز — یک‌بار مصرف</b></div>' : '') +
    (s.cashout_left >= 0 ? '<div class="kvr"><span>سقفِ مجاز الان</span><b>' + fa(s.cashout_left) + ' تومان</b></div>' : '') + '</div>' +
    (s.cashout_note ? '<p style="font-size:11px;color:var(--dim);margin-top:8px">' + esc(s.cashout_note) + '</p>' : '') +
    '<button class="btn mt" id="axGo">' + ico(kind === 'redeem' ? 'wallet' : 'ticket') + (kind === 'redeem' ? 'تبدیل کن' : 'ساخت کد') + '</button>' +
    '<div id="axRes"></div>');
  var inp = $('axIn');
  var cap = s.cashout_left >= 0 && s.redeem_rate > 0 ? Math.floor(s.cashout_left / s.redeem_rate) : have;
  var def = Math.floor(Math.min(have, cap));
  inp.value = def >= min ? faD(def) : '';
  var upd = function(){
    var v = Number(digits(inp.value)) || 0;
    $('axEq').textContent = v > 0 ? fa(Math.round(v * s.redeem_rate)) + ' تومان' : '—';
  };
  inp.addEventListener('input', upd);
  upd();
  $('axGo').onclick = function(){
    var v = Number(digits(inp.value)) || 0, b = this;
    if (v < min) { toast('حداقل ' + fa(min) + ' کریستال لازم است.'); return; }
    if (v > have + 0.01) { toast('کریستال کافی ندارید.'); return; }
    b.disabled = true;
    tap('medium');
    api(kind === 'redeem' ? 'airdrop_redeem' : 'airdrop_coupon', { amount: v }, function(j){
      AX.st = j.state; AX.t0 = Date.now(); AX.cache.hs = null;
      if (kind === 'redeem') {
        setBal(j.balance); closeSheet(); drawAir(); confetti();
        toast(fa(j.toman) + ' تومان به موجودی اضافه شد!', true);
      } else {
        drawAir(); confetti();
        $('axRes').innerHTML = '<div class="code mt"><small>' + ico('ticket', 'ii') + 'کد تخفیف شما</small><b style="font-size:24px;letter-spacing:3px">' + esc(j.code) + '</b>' +
          '<button class="cpy" data-copy="' + esc(j.code) + '">' + ico('copy') + 'کپی کد</button>' +
          '<p style="font-size:10.5px;color:var(--dim);margin-top:8px">ارزش ' + fa(j.toman) + ' تومان</p></div>';
        b.classList.add('hid');
        $('shBody').onclick = function(ev){ var c = ev.target.closest('[data-copy]'); if (c) { tap(); copy(c.getAttribute('data-copy'), 'کد'); } };
      }
    }, function(j){ b.disabled = false; toast((j && j.message) || 'انجام نشد.'); });
  };
}

var TOUR = { open: false, i: 0, el: null };
var TOURS = [
  [GEM, 'کریستال جمع کنید', 'هر ساعت خودکار کریستال می‌گیرید — حتی وقتی مینی‌اپ بسته است. هرچه سطحتان بالاتر، سرعت بیشتر.'],
  ['<div class="gw"><i class="rays"></i><i class="t3 xl">' + ico('bolt') + '</i></div>', 'سرعت را چند برابر کنید', 'با ماموریت‌ها، دعوت دوستان و تقویت سرعت، زودتر به سطح‌های بالا برسید.'],
  ['<div class="gw"><i class="rays"></i><i class="t3 xl g">' + ico('gift') + '</i></div>', 'تبدیل به شماره‌ی رایگان', 'کریستال‌ها را به موجودی کیف پول یا کد تخفیف تبدیل کنید و شماره‌ی مجازی بخرید.']
];
function tourStart(){
  if (TOUR.open) return;
  TOUR.open = true; TOUR.i = 0;
  var el = D.createElement('div');
  el.className = 'tour';
  D.body.appendChild(el);
  TOUR.el = el;
  tourDraw();
  backBtn();
}
function tourDraw(){
  var t = TOURS[TOUR.i], last = TOUR.i === TOURS.length - 1;
  TOUR.el.innerHTML = '<div class="tv">' + t[0] + '</div><h2>' + esc(t[1]) + '</h2><p>' + esc(t[2]) + '</p>' +
    '<div class="dts">' + TOURS.map(function(x, i){ return '<i class="' + (i === TOUR.i ? 'on' : '') + '"></i>'; }).join('') + '</div>' +
    '<button class="btn">' + (last ? 'شروع کنیم' : 'بعدی') + '</button>' +
    (last ? '' : '<button class="btn gh mt2" style="max-width:320px" data-skip="1">رد کردن</button>');
  TOUR.el.querySelector('.btn').onclick = function(){ tap(); if (last) tourEnd(); else { TOUR.i++; tourDraw(); } };
  var sk = TOUR.el.querySelector('[data-skip]');
  if (sk) sk.onclick = function(){ tap(); tourEnd(); };
}
function tourEnd(){
  if (!TOUR.open) return;
  TOUR.open = false;
  if (TOUR.el && TOUR.el.parentNode) TOUR.el.parentNode.removeChild(TOUR.el);
  TOUR.el = null;
  if (AX.st) AX.st.tour_seen = 1;
  api('airdrop_tour_done', {}, function(){}, function(){});
  backBtn();
}
function fsSync(){
  var on = false;
  try { on = !!(TG && TG.isFullscreen); } catch(e){}
  H.classList.toggle('fs', on);
}
function tgSetup(){
  if (!TG) return;
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#000000'); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor('#000000'); } catch(e){}
  try { TG.setBottomBarColor && TG.setBottomBarColor('#000000'); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
  try {
    var pf = String(TG.platform || '');
    if (TG.requestFullscreen && /^(ios|android)/.test(pf)) TG.requestFullscreen();
  } catch(e){}
  try {
    TG.onEvent('fullscreenChanged', fsSync);
    TG.onEvent('safeAreaChanged', fsSync);
    TG.onEvent('contentSafeAreaChanged', fsSync);
    TG.onEvent('viewportChanged', supFit);
    if (TG.BackButton) TG.BackButton.onClick(goBack);
  } catch(e){}
  setTimeout(fsSync, 200);
  setTimeout(fsSync, 900);
}
if (TG) tgSetup();
else {
  var tries = 0, iv = setInterval(function(){
    if (window.Telegram && window.Telegram.WebApp) {
      clearInterval(iv);
      TG = window.Telegram.WebApp;
      U = tgUser() || U; S.uid = U.id || S.uid;
      tgSetup(); drawSelf(S.me && S.me.avatar); backBtn();
    } else if (++tries > 40) clearInterval(iv);
  }, 100);
}

function applyMe(j){
  S.me = j;
  S.cp = j.coupon || null;
  setBal(j.balance);
  if (S.sheet === 'buy') drawBuy();
  setNotes(j.notes);
  drawSelf(j.avatar);
  (j.live || []).forEach(putRow);
  drawHomeLive();
  if (j.sup) {
    if (j.sup.has) SUP.has = true;
    if (S.page !== 'sup') supBadge(j.sup.unread);
    supWatch();
  }
  if (S.page === 'me') drawMe();
}
function refresh(){
  api('me', {}, applyMe, function(){});
}
var LIVET = null;
function liveLoop(){
  clearTimeout(LIVET);
  LIVET = setTimeout(function(){
    var busy = Object.keys(S.live).some(function(k){ var r = S.live[k]; return r.nst === 'waiting' || r.nst === 'buying'; });
    if (!D.hidden && busy && S.sheet !== 'live') {
      api('live', {}, function(j){
        (j.live || []).forEach(putRow);
        Object.keys(S.live).forEach(function(k){
          var r = S.live[k];
          if ((r.nst === 'waiting' || r.nst === 'buying') && !(j.live || []).some(function(x){ return x.id === k; })) {
            api('num', { order: k }, function(q){ if (q.row) { putRow(q.row); drawHomeLive(); if (S.page === 'orders') drawOrders(); } }, function(){});
          }
        });
        setBal(j.balance);
        drawHomeLive();
        if (S.page === 'orders') drawOrders();
      }, function(){});
    }
    liveLoop();
  }, 10000);
}
setInterval(function(){ if (!D.hidden) refresh(); }, 45000);
setInterval(function(){ if (!D.hidden && S.page === 'home') loadFeed(); }, 60000);
D.addEventListener('visibilitychange', function(){
  if (D.hidden) { clearTimeout(SUP.timer); clearTimeout(SUP.watch); return; }
  refresh();
  if (SUP.open) supPoll(); else supWatch(800);
  if (S.sheet === 'live') startPoll();
});

drawSelf('');
setBal(0);
$('bal').textContent = '…';
var WANT = (function(){ try { return String(new URLSearchParams(location.search).get('p') || ''); } catch(e){ return ''; } })();
go(PAGES.indexOf(WANT) >= 0 ? WANT : 'home');
api('me', {}, function(j){
  applyMe(j);
  hideSplash(false);
  liveLoop();
  setTimeout(warm, SPL.min + 900);
  var open = (j.live || []).filter(isOpen).sort(function(a, b){ return b.at - a.at; });
  var auto = open.length && (WANT === 'live' || (S.page === 'home' && open.length === 1 && open[0].nst === 'waiting'));
  if (auto) setTimeout(function(){ if (!S.sheet) openLive(open[0]); }, Math.max(300, SPL.min + 200 - (Date.now() - SPL.t0)));
}, function(j){
  hideSplash(false);
  if (!GATED) toast((j && j.message) || 'اتصال برقرار نشد — دوباره باز کنید.');
  liveLoop();
});
})();
</script>
</body>
</html>
HTML;
}
