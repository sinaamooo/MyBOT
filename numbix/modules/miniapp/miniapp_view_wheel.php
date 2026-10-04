<?php
defined('NB_ROOT') || exit;

function whView(array $boot) {
    $e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $font = function_exists('maFontCss') ? maFontCss() : '';
    return strtr(whTpl(), [
        '__TITLE__' => $e($boot['title'] ?? ''),
        '__FONT__'  => $font !== ''
            ? (is_file(nbAsset('fonts/Vazirmatn.woff2'))
                ? '<link rel="preload" href="assets/fonts/Vazirmatn.woff2" as="font" type="font/woff2" crossorigin>' . "\n" : '')
              . "<style>\n" . $font . "</style>"
            : '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap">',
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG |
                                          JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function whTpl() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover"><link rel="icon" href="data:,">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#07051A">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
__FONT__
<style>
:root{--bg:#07051A;--ink:#F6F3FF;--dim:#A9A3C9;--dim2:#6E6893;--line:rgba(255,255,255,.09);--gold:#E9B949;--gold2:#FFF1C1;--top:0px;color-scheme:dark}
html{zoom:.9;background:var(--bg)}
html.fs{--top:calc((var(--tg-safe-area-inset-top,24px) + var(--tg-content-safe-area-inset-top,46px)) / .9)}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
body{min-height:100%;color:var(--ink);background:var(--bg);font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:14px;line-height:1.7;
  overflow-x:hidden;-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none;padding-bottom:calc(env(safe-area-inset-bottom,0px) + 22px)}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
.hid{display:none!important}
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:radial-gradient(1.1px 1.1px at 12% 18%,#fff9,transparent),radial-gradient(1px 1px at 72% 12%,#fff7,transparent),
  radial-gradient(1.2px 1.2px at 38% 64%,#fff6,transparent),radial-gradient(1px 1px at 86% 48%,#fff5,transparent),radial-gradient(1.1px 1.1px at 22% 86%,#fff6,transparent),
  radial-gradient(90% 50% at 88% -6%,rgba(91,33,182,.55),transparent 70%),radial-gradient(80% 45% at -8% 78%,rgba(157,23,77,.32),transparent 70%),
  linear-gradient(180deg,#120D2C,#07051A 55%)}
.wrap{position:relative;z-index:1;max-width:460px;margin:0 auto;padding:calc(var(--top) + 6px) 16px 0}
.stage{position:relative;width:min(88vw,360px);aspect-ratio:1;margin:26px auto 8px}
.halo{position:absolute;inset:-8%;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.34),rgba(192,38,211,.12) 46%,transparent 68%)}
.rim{position:absolute;inset:0;border-radius:50%;padding:4.6%;
  background:conic-gradient(from 0deg,#2E2850,#100D1E 12%,#2E2850 25%,#100D1E 37%,#2E2850 50%,#100D1E 62%,#2E2850 75%,#100D1E 87%,#2E2850);
  box-shadow:0 0 0 2px #B8862A,0 0 0 3px rgba(0,0,0,.6),inset 0 0 0 2px #F2D27A,0 22px 50px rgba(0,0,0,.65)}
.rim-in{position:relative;width:100%;height:100%;border-radius:50%;background:#0B0918}
.wheel{position:absolute;inset:0;transform:rotate(0deg);will-change:transform;backface-visibility:hidden}
#wcv{display:block;width:100%;height:100%}
.leds{position:absolute;inset:0;pointer-events:none}
.leds .lg{position:absolute;inset:0}
.leds i{position:absolute;width:2.9%;height:2.9%;margin:-1.45% 0 0 -1.45%;border-radius:50%;background:radial-gradient(circle at 40% 35%,#fff,#FFE7A3 55%,#E9B949);box-shadow:0 0 7px #F5C451}
.leds .lg.b i{background:radial-gradient(circle at 40% 35%,#FFE7A3,#C9952F 60%,#8A5A12);box-shadow:none}
.pin{position:absolute;top:-4%;left:50%;width:12%;height:15.5%;transform:translateX(-50%);z-index:3;transform-origin:50% 22%}
.hub{position:absolute;top:50%;left:50%;width:27%;height:27%;transform:translate(-50%,-50%);z-index:4;border-radius:50%;
  background:radial-gradient(circle at 50% 32%,#2D2550 0%,#120F24 62%,#07060F 100%);
  box-shadow:0 0 0 3px #0B0918,0 0 0 5.5px var(--gold),0 0 0 7px rgba(0,0,0,.55),0 12px 28px rgba(0,0,0,.7),inset 0 1px 0 rgba(255,255,255,.14);
  display:grid;place-items:center;font-weight:900;font-size:clamp(12px,3.6vw,16px);color:#F7D77E}
.hub:active{transform:translate(-50%,-50%) scale(.95)}
.card{display:flex;align-items:center;gap:12px;margin:20px 0 0;padding:14px 16px;border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.035));border:1px solid var(--line)}
.st-ic{width:40px;height:40px;flex:none;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.06);box-shadow:inset 0 0 0 1px rgba(233,185,73,.3)}
.st-ic svg{width:26px;height:26px;overflow:visible}
.st-t{font-weight:700;font-size:14px}
.st-t small{display:block;font-weight:500;font-size:12px;color:var(--dim)}
.k-spin{animation:spin 1s linear infinite;transform-origin:50% 50%}
.k-pop{animation:pop2 .5s cubic-bezier(.2,1.6,.4,1)}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pop2{from{transform:scale(.4)}}
.go{display:block;width:100%;margin-top:14px;padding:15px 18px;border-radius:18px;font-weight:900;font-size:17px;letter-spacing:.3px;color:#fff;
  background:linear-gradient(100deg,#6D28D9 0%,#C026D3 52%,#F59E0B 100%);
  box-shadow:0 14px 30px rgba(192,38,211,.3),inset 0 1px 0 rgba(255,255,255,.35),inset 0 0 0 1px rgba(255,255,255,.12)}
.go:active{transform:scale(.98)}
.go[disabled]{opacity:.4}
.pz{margin-top:24px}
.pz h4{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:12px;font-size:13.5px;font-weight:800;color:#E9DFFF}
.pz h4::before,.pz h4::after{content:'';flex:0 0 34px;height:1px;background:linear-gradient(90deg,transparent,rgba(233,185,73,.7))}
.pz h4::after{transform:scaleX(-1)}
.grid{display:flex;flex-wrap:wrap;justify-content:center;gap:10px}
.tile{flex:0 0 calc((100% - 20px) / 3);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;min-height:104px;padding:12px 6px 11px;
  border-radius:18px;text-align:center;background:linear-gradient(165deg,var(--t1),rgba(255,255,255,.02) 72%);border:1px solid var(--t2)}
.tile .pl{width:50px;height:50px;border-radius:50%;display:grid;place-items:center;
  background:radial-gradient(circle at 50% 30%,rgba(255,255,255,.16),rgba(255,255,255,.03) 70%);box-shadow:inset 0 0 0 1px rgba(255,255,255,.09)}
.tile .pl svg{width:36px;height:36px;display:block}
.tile .pl b{font-size:28px;line-height:1}
.tile span{font-size:12.5px;font-weight:800;line-height:1.35;color:#F1ECFF}
.tile.none span{color:var(--dim)}
.rules{margin-top:16px;text-align:center;font-size:11.5px;color:var(--dim2)}
.modal{position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:20px;background:rgba(4,3,14,.86);animation:fi .2s ease}
@keyframes fi{from{opacity:0}}
.sheet{position:relative;width:min(100%,380px);padding:26px 20px 20px;border-radius:28px;text-align:center;overflow:hidden;
  background:linear-gradient(160deg,#281E50,#0B0918 62%,#46103A);border:1px solid rgba(233,185,73,.38);
  box-shadow:0 30px 70px rgba(0,0,0,.7),inset 0 1px 0 rgba(255,255,255,.12);animation:pop .35s cubic-bezier(.2,1.4,.4,1)}
@keyframes pop{from{transform:scale(.8);opacity:0}}
#cf{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
.big{position:relative;width:108px;height:108px;margin:0 auto}
.big .rg{position:absolute;inset:-10px;border-radius:50%;
  background:conic-gradient(from 0deg,transparent,rgba(233,185,73,.5),transparent 32%,rgba(192,38,211,.42),transparent 66%,rgba(34,211,238,.35),transparent)}
.big .rg::after{content:'';position:absolute;inset:9px;border-radius:50%;background:#1A1338}
.big svg{position:relative;display:block;width:100%;height:100%;overflow:visible;animation:pop2 .55s cubic-bezier(.2,1.6,.4,1)}
.sheet h3{position:relative;margin-top:12px;font-size:21px;font-weight:900;color:#FBE7A6}
.sheet p{position:relative;color:var(--dim);font-size:13px;margin-top:4px}
.code{position:relative;display:flex;align-items:center;gap:8px;margin:14px 0 4px;padding:8px 8px 8px 14px;border-radius:16px;background:rgba(0,0,0,.4);border:1px dashed rgba(233,185,73,.65)}
.code b{flex:1;direction:ltr;text-align:left;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;letter-spacing:1.5px;color:var(--gold2)}
.code button{padding:8px 12px;border-radius:12px;font-weight:800;font-size:12px;color:#1A1206;background:linear-gradient(100deg,var(--gold2),var(--gold))}
.go.sm{padding:12px;font-size:15px;margin-top:16px}
.note{font-size:12px!important}
.toast{position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom,0px) + 22px);transform:translate(-50%,30px);opacity:0;z-index:30;max-width:90vw;
  padding:11px 16px;border-radius:16px;font-weight:700;font-size:13px;background:#120E28;border:1px solid rgba(233,185,73,.3);transition:transform .3s,opacity .3s;text-align:center}
.toast.on{opacity:1;transform:translate(-50%,0)}
.gate{position:fixed;inset:0;z-index:40;display:grid;place-items:center;text-align:center;padding:30px;background:var(--bg)}
.gate b{display:block;font-size:20px;margin:12px 0 6px}
.gate p{color:var(--dim)}
@media (prefers-reduced-motion:reduce){.k-spin,.k-pop,.big svg,.sheet,.modal{animation:none!important}}
</style>
</head>
<body>
<div class="bg"></div>
<svg width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
 <defs>
  <radialGradient id="gTb" cx=".4" cy=".35" r=".75"><stop offset="0" stop-color="#E7B183"/><stop offset="1" stop-color="#8B5A2B"/></radialGradient>
  <linearGradient id="gHt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FDA4AF"/><stop offset=".55" stop-color="#F43F5E"/><stop offset="1" stop-color="#9F1239"/></linearGradient>
  <linearGradient id="gAu" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFF1C1"/><stop offset=".5" stop-color="#E9B949"/><stop offset="1" stop-color="#B7791F"/></linearGradient>
  <radialGradient id="gRo" cx=".45" cy=".35" r=".75"><stop offset="0" stop-color="#FB7185"/><stop offset=".6" stop-color="#E11D48"/><stop offset="1" stop-color="#881337"/></radialGradient>
  <linearGradient id="gRd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F43F5E"/><stop offset="1" stop-color="#9F1239"/></linearGradient>
  <linearGradient id="gRl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FB7185"/><stop offset="1" stop-color="#BE123C"/></linearGradient>
  <linearGradient id="gPk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F9A8D4"/><stop offset="1" stop-color="#DB2777"/></linearGradient>
  <linearGradient id="gPl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C4B5FD"/><stop offset="1" stop-color="#7C3AED"/></linearGradient>
  <linearGradient id="gFl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FEF3C7"/><stop offset=".5" stop-color="#FBBF24"/><stop offset="1" stop-color="#F97316"/></linearGradient>
  <linearGradient id="gDi" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#CFFAFE"/><stop offset=".45" stop-color="#38BDF8"/><stop offset="1" stop-color="#1D4ED8"/></linearGradient>
  <linearGradient id="gTl" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5EEAD4"/><stop offset="1" stop-color="#0E7490"/></linearGradient>
  <linearGradient id="gEm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6EE7B7"/><stop offset="1" stop-color="#047857"/></linearGradient>
  <linearGradient id="gGr" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E2E8F0"/><stop offset="1" stop-color="#64748B"/></linearGradient>
  <linearGradient id="gOk" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6EE7B7"/><stop offset="1" stop-color="#059669"/></linearGradient>
 </defs>
 <symbol id="i-teddy" viewBox="0 0 48 48">
  <circle cx="13" cy="12.5" r="7" fill="url(#gTb)"/><circle cx="35" cy="12.5" r="7" fill="url(#gTb)"/>
  <circle cx="13" cy="12.5" r="3.4" fill="#F3C9A0"/><circle cx="35" cy="12.5" r="3.4" fill="#F3C9A0"/>
  <circle cx="24" cy="25" r="15" fill="url(#gTb)"/>
  <ellipse cx="24" cy="30.5" rx="7.6" ry="6" fill="#F3D2B0"/>
  <ellipse cx="24" cy="28" rx="2.7" ry="2" fill="#3B2314"/>
  <path d="M24 30v2.1M20.8 32.6q3.2 2.2 6.4 0" stroke="#3B2314" stroke-width="1.4" fill="none" stroke-linecap="round"/>
  <circle cx="18.4" cy="21.8" r="2.1" fill="#2A170C"/><circle cx="29.6" cy="21.8" r="2.1" fill="#2A170C"/>
  <circle cx="19" cy="21.2" r=".7" fill="#fff"/><circle cx="30.2" cy="21.2" r=".7" fill="#fff"/>
  <path d="M24 40.5 17 37v7.2zM24 40.5 31 37v7.2z" fill="#F43F5E"/><circle cx="24" cy="40.5" r="2" fill="#FDA4AF"/>
 </symbol>
 <symbol id="i-heart" viewBox="0 0 48 48">
  <path d="M24 42C10 32 5 25 5 17.2 5 11 9.7 6.5 15.4 6.5c3.8 0 7 2.2 8.6 5.4 1.6-3.2 4.8-5.4 8.6-5.4C38.3 6.5 43 11 43 17.2 43 25 38 32 24 42z" fill="url(#gHt)"/>
  <path d="M11.5 14q2-4 6.5-3.6" stroke="#fff" stroke-opacity=".6" stroke-width="2.4" fill="none" stroke-linecap="round"/>
  <path d="M24 12.4c-4.6-6.4-10.4-3.2-6.4.9zM24 12.4c4.6-6.4 10.4-3.2 6.4.9z" fill="url(#gAu)"/><circle cx="24" cy="12.6" r="2.2" fill="#F59E0B"/>
 </symbol>
 <symbol id="i-rose" viewBox="0 0 48 48">
  <path d="M24 25c.4 7-1 13 1 21" stroke="#16A34A" stroke-width="3" fill="none" stroke-linecap="round"/>
  <path d="M24.5 34c5-4 10-3 12 0-4 3-9 3-12 0z" fill="#22C55E"/><path d="M24.6 39c-5.4-3-9.6-2-11.6 1 4 2 8 2 11.6-1z" fill="#15803D"/>
  <path d="M13 15C13 8 19 3.6 24 5.6 29 3.6 35 8 35 15c0 7.4-5 11.6-11 11.6S13 22.4 13 15z" fill="url(#gRo)"/>
  <path d="M18.5 12c2-3.4 9-3.4 11 0-2 3.6-9 3.6-11 0z" fill="#9F1239" opacity=".55"/>
  <path d="M16.6 16.4c2 5.6 12.8 5.6 14.8 0" stroke="#881337" stroke-width="1.6" fill="none" opacity=".7"/>
  <path d="M21.6 11.8q2.4-2.4 4.8 0-1.2 2.4-3.4 1.6" stroke="#FECDD3" stroke-width="1.3" fill="none" stroke-linecap="round"/>
 </symbol>
 <symbol id="i-box" viewBox="0 0 48 48">
  <rect x="9" y="21" width="30" height="21" rx="3" fill="url(#gRd)"/>
  <rect x="6" y="15" width="36" height="8" rx="2.5" fill="url(#gRl)"/>
  <rect x="9" y="23" width="30" height="1.6" fill="#000" opacity=".18"/>
  <rect x="21.5" y="15" width="5" height="27" fill="url(#gAu)"/>
  <path d="M24 15c-6-9-13-5-8 0zM24 15c6-9 13-5 8 0z" fill="url(#gAu)"/><circle cx="24" cy="14.6" r="2.4" fill="#F59E0B"/>
  <rect x="11" y="25" width="3" height="13" rx="1.5" fill="#fff" opacity=".25"/>
 </symbol>
 <symbol id="i-cake" viewBox="0 0 48 48">
  <ellipse cx="24" cy="42.5" rx="18" ry="3" fill="#CBD5E1" opacity=".5"/>
  <rect x="8" y="27" width="32" height="14" rx="3" fill="url(#gPk)"/>
  <rect x="13" y="18" width="22" height="10" rx="3" fill="url(#gPl)"/>
  <path d="M8 30q2 3 4 0 2 3 4 0 2 3 4 0 2 3 4 0 2 3 4 0 2 3 4 0 2 3 4 0 2 3 4 0v-1.5a3 3 0 0 0-3-1.5H11a3 3 0 0 0-3 1.5z" fill="#FFF7ED"/>
  <path d="M13 21q1.85 2.6 3.7 0 1.85 2.6 3.7 0 1.85 2.6 3.7 0 1.85 2.6 3.7 0 1.85 2.6 3.7 0 1.75 2.6 3.5 0V20a3 3 0 0 0-3-2H16a3 3 0 0 0-3 2z" fill="#FFF7ED"/>
  <rect x="14" y="34" width="2.4" height="1.2" rx=".6" fill="#FDE68A"/><rect x="22" y="36" width="2.4" height="1.2" rx=".6" fill="#A5F3FC"/><rect x="31" y="34" width="2.4" height="1.2" rx=".6" fill="#FDE68A"/>
  <rect x="22.5" y="9.5" width="3" height="9" rx="1" fill="#93C5FD"/>
  <path d="M24 3c2.6 3 2.6 5.6 0 6.6-2.6-1-2.6-3.6 0-6.6z" fill="url(#gFl)"/>
 </symbol>
 <symbol id="i-diamond" viewBox="0 0 48 48">
  <path d="M12 9h24l8 10-20 24L4 19z" fill="url(#gDi)"/>
  <path d="M12 9l6 10H4zM24 9l6 10H18z" fill="#fff" opacity=".32"/>
  <path d="M4 19h40" stroke="#E0F2FE" stroke-opacity=".75" stroke-width="1"/>
  <path d="M12 9l6 10 6-10 6 10 6-10" stroke="#E0F2FE" stroke-opacity=".6" fill="none"/>
  <path d="M18 19l6 24 6-24" stroke="#0369A1" stroke-opacity=".35" fill="none"/>
  <path d="M39 4l1.2 2.8L43 8l-2.8 1.2L39 12l-1.2-2.8L35 8l2.8-1.2z" fill="#fff"/>
 </symbol>
 <symbol id="i-tag" viewBox="0 0 48 48">
  <path d="M6 10a4 4 0 0 1 4-4h14a4 4 0 0 1 2.9 1.2L42 22.3a4 4 0 0 1 0 5.6L27.9 42a4 4 0 0 1-5.6 0L7.2 26.9A4 4 0 0 1 6 24z" fill="url(#gTl)"/>
  <circle cx="15" cy="15" r="3.4" fill="#07051A"/><circle cx="15" cy="15" r="3.4" fill="none" stroke="#fff" stroke-opacity=".5"/>
  <path d="M22 33l11-11" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="23.6" cy="24" r="2.4" fill="none" stroke="#fff" stroke-width="2"/><circle cx="31.4" cy="31.6" r="2.4" fill="none" stroke="#fff" stroke-width="2"/>
 </symbol>
 <symbol id="i-ticket" viewBox="0 0 48 48">
  <path d="M5 14a3 3 0 0 1 3-3h32a3 3 0 0 1 3 3v5a5 5 0 0 0 0 10v5a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-5a5 5 0 0 0 0-10z" fill="url(#gEm)"/>
  <path d="M31 13v22" stroke="#fff" stroke-opacity=".55" stroke-dasharray="2.5 2.5" stroke-width="1.4"/>
  <path d="M18 17.5l1.9 3.8 4.2.6-3 2.9.7 4.1-3.8-2-3.8 2 .7-4.1-3-2.9 4.2-.6z" fill="#FDE68A"/>
  <path d="M35 28l5-8" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><circle cx="35.6" cy="21" r="1.6" fill="#fff"/><circle cx="39.4" cy="27" r="1.6" fill="#fff"/>
 </symbol>
 <symbol id="i-puff" viewBox="0 0 48 48">
  <path d="M14 35a8 8 0 0 1-.8-16A10.5 10.5 0 0 1 33.4 15.6 8 8 0 1 1 35.5 35z" fill="url(#gGr)"/>
  <path d="M17.6 25.6q2.2-2.2 4.4 0M26 25.6q2.2-2.2 4.4 0" stroke="#334155" stroke-width="1.9" fill="none" stroke-linecap="round"/>
  <path d="M20.6 31.2q3.4-2.4 6.8 0" stroke="#334155" stroke-width="1.8" fill="none" stroke-linecap="round"/>
  <path d="M5 41h11M30 42h13" stroke="#64748B" stroke-width="2.4" stroke-linecap="round" opacity=".7"/>
 </symbol>
 <symbol id="s-ready" viewBox="0 0 48 48">
  <circle cx="24" cy="24" r="19" fill="none" stroke="url(#gRl)" stroke-width="4"/><circle cx="24" cy="24" r="11.5" fill="none" stroke="#fff" stroke-opacity=".85" stroke-width="3.4"/>
  <circle cx="24" cy="24" r="4.8" fill="url(#gRd)"/><path d="M24 24l15-15M33 8.6l6.4.6.6 6.4" stroke="url(#gAu)" stroke-width="3" fill="none" stroke-linecap="round"/>
 </symbol>
 <symbol id="s-done" viewBox="0 0 48 48">
  <circle cx="24" cy="24" r="20" fill="url(#gOk)"/><path d="M14.5 24.5l6.4 6.4L34 17.6" stroke="#fff" stroke-width="4.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
 </symbol>
 <symbol id="s-wait" viewBox="0 0 48 48">
  <path d="M13 6h22M13 42h22" stroke="url(#gAu)" stroke-width="3.6" stroke-linecap="round"/>
  <path d="M15 8c0 9 9 11 9 16s-9 7-9 16h18c0-9-9-11-9-16s9-7 9-16z" fill="none" stroke="#C4B5FD" stroke-width="2.6" stroke-linejoin="round"/>
  <path d="M19 37c1.4-4 3.4-5 5-5s3.6 1 5 5z" fill="url(#gAu)"/>
 </symbol>
 <symbol id="s-off" viewBox="0 0 48 48">
  <path d="M15 21v-5a9 9 0 0 1 18 0v5" stroke="url(#gGr)" stroke-width="4" fill="none"/><rect x="10" y="20" width="28" height="22" rx="5" fill="url(#gAu)"/>
  <circle cx="24" cy="30" r="3" fill="#3B2606"/><rect x="22.8" y="31" width="2.4" height="6" rx="1.2" fill="#3B2606"/>
 </symbol>
 <symbol id="s-busy" viewBox="0 0 48 48">
  <circle cx="24" cy="24" r="18" fill="none" stroke="#fff" stroke-opacity=".14" stroke-width="5"/>
  <path d="M24 6a18 18 0 0 1 18 18" stroke="url(#gAu)" stroke-width="5" fill="none" stroke-linecap="round"/>
 </symbol>
</svg>
<div class="wrap">
  <div class="stage" id="stage">
    <div class="halo"></div>
    <div class="rim"><div class="rim-in"><div class="wheel" id="wheel"><canvas id="wcv" width="640" height="640"></canvas></div></div></div>
    <div class="leds" id="leds"></div>
    <svg class="pin" id="pin" viewBox="0 0 60 74"><defs><linearGradient id="pg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFF1C1"/><stop offset=".45" stop-color="#E9B949"/><stop offset="1" stop-color="#8A5A12"/></linearGradient>
      <radialGradient id="pr" cx=".38" cy=".32" r=".8"><stop offset="0" stop-color="#FFE4E6"/><stop offset=".35" stop-color="#FB7185"/><stop offset="1" stop-color="#881337"/></radialGradient></defs>
      <path d="M30 72 L5 19 A26.5 26.5 0 1 1 55 19 Z" fill="#000" opacity=".35" transform="translate(1.5 3)"/>
      <path d="M30 72 L5 19 A26.5 26.5 0 1 1 55 19 Z" fill="url(#pg)" stroke="#3B2606" stroke-width="2"/><path d="M30 64 L11 21 A20 20 0 0 1 49 21 Z" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1.2"/>
      <circle cx="30" cy="21" r="9.5" fill="url(#pr)" stroke="#3B2606" stroke-width="2.4"/></svg>
    <button class="hub" id="hub"></button>
  </div>
  <div class="card" id="card"><div class="st-ic" id="stIc"></div><div class="st-t" id="stT">…</div></div>
  <button class="go" id="go" disabled>…</button>
  <section class="pz"><h4 id="pzT"></h4><div class="grid" id="grid"></div></section>
  <p class="rules" id="rules"></p>
</div>
<div class="modal hid" id="modal"><div class="sheet"><canvas id="cf"></canvas>
  <div class="big"><i class="rg"></i><div id="mI" style="position:relative;width:100%;height:100%"></div></div><h3 id="mT"></h3><p id="mS"></p>
  <div class="code hid" id="mC"><b id="mCt"></b><button id="mCb"></button></div>
  <p class="note" id="mN"></p><button class="go sm" id="mX"></button></div></div>
<div class="toast" id="toast"></div>
<script>
var B = __BOOT__;
var T = B.t || {};
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var D = document, $ = function(id){ return D.getElementById(id); };
var RM = false; try { RM = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); } catch(e){}
var LITE = (function(){ try { return (navigator.hardwareConcurrency || 8) <= 4 || (navigator.deviceMemory || 8) <= 3; } catch(e){ return false; } })();
var HASH_INIT = (function(){ try { var m = /(?:^|&)tgWebAppData=([^&]*)/.exec(String(location.hash || '').replace(/^#/, '')); return m ? decodeURIComponent(m[1]) : ''; } catch(e){ return ''; } })();
function initData(){ try { if (TG && TG.initData) return TG.initData; } catch(e){} return HASH_INIT; }
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function tx(k, v){ var s = String(T[k] || ''); for (var x in (v || {})) s = s.split('{' + x + '}').join(v[x]); return s; }
function hap(f){ try { if (TG && TG.HapticFeedback) f(TG.HapticFeedback); } catch(e){} }
function tap(k){ hap(function(h){ h.impactOccurred(k || 'light'); }); }
function buzz(k){ hap(function(h){ h.notificationOccurred(k); }); }
var TT;
function toast(m){ var t = $('toast'); t.textContent = m; t.classList.add('on'); clearTimeout(TT); TT = setTimeout(function(){ t.classList.remove('on'); }, 3200); }
var API = (function(){ try { if (/^https?:$/.test(location.protocol)) return location.origin + location.pathname + '?mapi=1'; } catch(e){} return ''; })();
function api(action, ok, bad){
  bad = bad || function(j){ toast((j && j.message) || 'خطا — دوباره امتحان کنید.'); };
  if (!API) { bad({ message: 'آدرس سرور تنظیم نشده است.' }); return; }
  var ctl = null, tm = null;
  try { ctl = new AbortController(); tm = setTimeout(function(){ ctl.abort(); }, 30000); } catch(e){}
  fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: action, initData: initData() }),
               signal: ctl ? ctl.signal : undefined, cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
    .then(function(r){ return r.json().catch(function(){ return { ok: false, message: 'پاسخ سرور نامعتبر بود.' }; }); })
    .then(function(j){ if (tm) clearTimeout(tm); if (j && j.ok) ok(j); else if (j && j.error === 'unauthorized') gate(j.message); else bad(j || {}); })
    .catch(function(){ if (tm) clearTimeout(tm); bad({ message: 'ارتباط با سرور برقرار نشد.' }); });
}
function gate(msg){
  var g = D.createElement('div'); g.className = 'gate';
  g.innerHTML = '<div><svg width="58" height="58"><use href="#s-off"/></svg><b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p></div>';
  D.body.appendChild(g);
}
function fsSync(){ var on = false; try { on = !!(TG && TG.isFullscreen); } catch(e){} D.documentElement.classList.toggle('fs', on); }
function tgSetup(){
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#07051A'); TG.setBackgroundColor && TG.setBackgroundColor('#07051A'); TG.setBottomBarColor && TG.setBottomBarColor('#07051A'); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
  try { var pf = String(TG.platform || ''); if (TG.requestFullscreen && /^(ios|android)/.test(pf)) TG.requestFullscreen(); } catch(e){}
  try { TG.onEvent('fullscreenChanged', fsSync); TG.onEvent('safeAreaChanged', fsSync); TG.onEvent('contentSafeAreaChanged', fsSync); } catch(e){}
  fsSync(); setTimeout(fsSync, 200); setTimeout(fsSync, 900);
}

var P = [], S = { st: '', busy: false, rot: 0 };
var ICON = { '🧸': 'teddy', '💝': 'heart', '❤': 'heart', '💖': 'heart', '💗': 'heart', '🌹': 'rose', '🥀': 'rose', '🎁': 'box', '🎂': 'cake', '🍰': 'cake',
             '💎': 'diamond', '🏷': 'tag', '🎟': 'ticket', '🎫': 'ticket', '💨': 'puff' };
function iconOf(p){
  var e = String(p.emoji || '').replace(/[︎️]/g, '');
  return ICON[e] || (e ? '' : ({ coupon: 'tag', none: 'puff', diamond: 'diamond' })[p.type] || 'box');
}
function icHtml(p){ var n = iconOf(p); return n ? '<svg viewBox="0 0 48 48"><use href="#i-' + n + '"/></svg>' : '<b>' + esc(p.emoji) + '</b>'; }
function shade(hex, k){ var n = parseInt(hex.slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
  var f = function(c){ return Math.max(0, Math.min(255, Math.round(k < 0 ? c * (1 + k) : c + (255 - c) * k))); };
  return '#' + ((1 << 24) + (f(r) << 16) + (f(g) << 8) + f(b)).toString(16).slice(1); }
function rgb(hex){ var n = parseInt(String(hex).slice(1), 16) || 0; return (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255); }
function lines(s){
  s = s.trim(); if (s.length * 12 * .56 <= 42 || s.indexOf(' ') < 0) return [s];
  var best = -1, c = s.length / 2;
  for (var k = 0; k < s.length; k++) if (s[k] === ' ' && (best < 0 || Math.abs(k - c) < Math.abs(best - c))) best = k;
  return [s.slice(0, best).trim(), s.slice(best + 1).trim()];
}
var IMG = {}, DQ = 0;
function iconImg(n){
  if (IMG[n]) return IMG[n];
  var sym = $('i-' + n), defs = D.querySelector('svg defs');
  if (!sym || !defs) return null;
  var im = new Image();
  im.onload = function(){ im.ok = true; paintSoon(); };
  im.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="96" height="96"><defs>' + defs.innerHTML + '</defs>' + sym.innerHTML + '</svg>');
  return IMG[n] = im;
}
function paintSoon(){ if (!DQ) DQ = requestAnimationFrame(function(){ DQ = 0; paint(); }); }
function paint(){
  var cv = $('wcv'), w = $('wheel').offsetWidth || 320, n = P.length;
  if (!n) return;
  var px = Math.round(Math.max(480, Math.min(1100, w * (window.devicePixelRatio || 2))));
  if (cv.width !== px) { cv.width = px; cv.height = px; }
  var x = cv.getContext('2d'), k = px / 320, R = Math.PI / 180, seg = 360 / n, i, a;
  x.setTransform(k, 0, 0, k, px / 2, px / 2);
  x.clearRect(-160, -160, 320, 320);
  for (i = 0; i < n; i++) {
    var c = P[i].type === 'none' ? '#2B3245' : P[i].color, g = x.createRadialGradient(0, 0, 0, 0, 0, 150);
    g.addColorStop(0, shade(c, -.78)); g.addColorStop(.2, shade(c, -.78)); g.addColorStop(.72, shade(c, -.28)); g.addColorStop(1, shade(c, .1));
    x.beginPath(); x.moveTo(0, 0); x.arc(0, 0, 150.4, (i * seg - 90) * R, ((i + 1) * seg - 90) * R); x.closePath(); x.fillStyle = g; x.fill();
  }
  x.lineCap = 'round';
  for (i = 0; i < n; i++) {
    var none = P[i].type === 'none', col = none ? '#64748B' : shade(P[i].color, .3), a0 = (i * seg + 1.2 - 90) * R, a1 = ((i + 1) * seg - 1.2 - 90) * R;
    x.strokeStyle = col;
    x.globalAlpha = none ? .12 : .22; x.lineWidth = 8; x.beginPath(); x.arc(0, 0, 145, a0, a1); x.stroke();
    x.globalAlpha = none ? .5 : .95; x.lineWidth = 3; x.beginPath(); x.arc(0, 0, 145, a0, a1); x.stroke();
  }
  var au = x.createLinearGradient(-150, -150, 150, 150);
  au.addColorStop(0, '#FFF1C1'); au.addColorStop(.5, '#E9B949'); au.addColorStop(1, '#8A5A12');
  x.strokeStyle = au; x.lineWidth = 1.5; x.globalAlpha = .85; x.lineCap = 'butt';
  for (i = 0; i < n; i++) {
    a = (i * seg - 90) * R;
    x.beginPath(); x.moveTo(58 * Math.cos(a), 58 * Math.sin(a)); x.lineTo(150 * Math.cos(a), 150 * Math.sin(a)); x.stroke();
  }
  x.textAlign = 'center'; x.textBaseline = 'middle'; x.lineJoin = 'round';
  try { x.direction = 'rtl'; } catch(e){}
  for (i = 0; i < n; i++) {
    var p = P[i], nn = p.type === 'none', name = iconOf(p), im = name ? iconImg(name) : null, ln = lines(String(p.label || '')), mx = 0;
    ln.forEach(function(t){ mx = Math.max(mx, t.length); });
    var fs = Math.max(8.5, Math.min(13, 42 / (Math.max(1, mx) * .56)));
    x.save(); x.rotate((i + .5) * seg * R); x.globalAlpha = nn ? .8 : 1;
    if (im && im.ok) x.drawImage(im, -14.5, -136, 29, 29);
    else if (!name) { x.font = '24px sans-serif'; x.fillStyle = '#fff'; x.fillText(String(p.emoji || ''), 0, -121); }
    x.rotate(Math.PI / 2);
    x.font = '800 ' + fs.toFixed(2) + 'px Vazirmatn, Vazir, Tahoma, sans-serif';
    x.lineWidth = 2.4; x.strokeStyle = 'rgba(0,0,0,.55)'; x.fillStyle = nn ? '#AEB8CC' : '#FFFFFF';
    ln.forEach(function(t, j){ var yy = (j - (ln.length - 1) / 2) * fs * 1.15; x.strokeText(t, -86, yy); x.fillText(t, -86, yy); });
    x.restore();
  }
  x.globalAlpha = 1;
  var gl = x.createRadialGradient(0, -95, 0, 0, -95, 200);
  gl.addColorStop(0, 'rgba(255,255,255,.2)'); gl.addColorStop(.5, 'rgba(255,255,255,.03)'); gl.addColorStop(1, 'rgba(255,255,255,0)');
  x.fillStyle = gl; x.beginPath(); x.arc(0, 0, 150, 0, 2 * Math.PI); x.fill();
  x.strokeStyle = au; x.lineWidth = 1.6; x.beginPath(); x.arc(0, 0, 149.2, 0, 2 * Math.PI); x.stroke();
  x.fillStyle = '#0B0918'; x.lineWidth = 2; x.beginPath(); x.arc(0, 0, 58, 0, 2 * Math.PI); x.fill(); x.stroke();
  x.strokeStyle = 'rgba(233,185,73,.25)'; x.lineWidth = 1; x.beginPath(); x.arc(0, 0, 52, 0, 2 * Math.PI); x.stroke();
}
function leds(){
  var a = '', b = '';
  for (var k = 0; k < 24; k++) {
    var t = (k * 15 - 90) * Math.PI / 180, dot = '<i style="left:' + (50 + 48.1 * Math.cos(t)).toFixed(2) + '%;top:' + (50 + 48.1 * Math.sin(t)).toFixed(2) + '%"></i>';
    if (k % 2) b += dot; else a += dot;
  }
  $('leds').innerHTML = '<div class="lg a">' + a + '</div><div class="lg b">' + b + '</div>';
}
function grid(){
  var rank = { gift: 0, diamond: 1, coupon: 2, none: 3 }, seen = {}, list = [];
  P.forEach(function(p, i){
    var k = p.emoji + '|' + p.label;
    if (seen[k]) return;
    seen[k] = 1; list.push([rank[p.type] == null ? 1 : rank[p.type], i, p]);
  });
  list.sort(function(a, b){ return a[0] - b[0] || a[1] - b[1]; });
  $('grid').innerHTML = list.map(function(e){
    var p = e[2], c = rgb(p.type === 'none' ? '#64748B' : p.color);
    return '<div class="tile' + (p.type === 'none' ? ' none' : '') + '" style="--t1:rgba(' + c + ',.24);--t2:rgba(' + c + ',.42)"><i class="pl">' + icHtml(p) + '</i><span>' + esc(p.label) + '</span></div>';
  }).join('');
}
function setRot(deg, ms){
  var w = $('wheel');
  w.style.transition = ms ? 'transform ' + ms + 'ms cubic-bezier(.11,.67,.06,1)' : 'none';
  w.style.transform = 'rotate(' + deg + 'deg)';
  S.rot = deg;
}
function idxOf(id){ for (var i = 0; i < P.length; i++) if (P[i].id === id) return i; return -1; }
function restOn(i){ if (i < 0) return; var seg = 360 / P.length; setRot(360 - (i + .5) * seg, 0); }
var ST = { ready: ['s-ready', 'k-pop'], done: ['s-done', 'k-pop'], wait: ['s-wait', ''], need: ['s-wait', ''], off: ['s-off', ''], full: ['s-off', ''], busy: ['s-busy', 'k-spin'], spin: ['s-ready', ''] };
function state(st, extra){
  S.st = st;
  var ic = ST[st] || ST.busy;
  var msg = { ready: T.ready, done: T.done, wait: T.wait, need: T.need, off: T.off, full: T.full, busy: '…', spin: '…' }[st] || '';
  $('stIc').innerHTML = '<svg class="' + ic[1] + '"><use href="#' + ic[0] + '"/></svg>';
  $('stT').innerHTML = esc(msg) + (extra ? '<small>' + esc(extra) + '</small>' : '');
  var go = $('go');
  go.disabled = st !== 'ready'; go.textContent = T.spin || 'بچرخون';
  $('hub').textContent = T.spin || 'بچرخون';
}
var V = .9;
function mod(a, m){ return ((a % m) + m) % m; }
function ticker(angle){
  if (RM) return;
  var seg = 360 / P.length, pin = $('pin'), lastPin = 0, last = -1;
  (function f(now){
    if (!S.busy) return;
    var s = Math.floor(mod(360 - angle(), 360) / seg);
    if (s !== last) {
      last = s;
      if (now - lastPin > 90 && pin.animate) {
        lastPin = now;
        pin.animate([{ transform: 'translateX(-50%) rotate(0deg)' }, { transform: 'translateX(-50%) rotate(-14deg)', offset: .4 }, { transform: 'translateX(-50%) rotate(0deg)' }], { duration: 120 });
      }
    }
    requestAnimationFrame(f);
  })(performance.now());
}
function rest(deg){
  var w = $('wheel');
  w.style.transition = 'none'; w.style.transform = 'rotate(' + deg + 'deg)'; S.rot = deg;
  if (w.getAnimations) w.getAnimations().forEach(function(a){ a.cancel(); });
}
function spin(){
  if (S.busy || S.st !== 'ready') return;
  S.busy = true; tap('heavy'); state('spin');
  var w = $('wheel'), r0 = S.rot, live = !RM && !!w.animate, ramp = 520, r1 = V * ramp / 2, run = [];
  var angle = function(){ return r0; };
  if (live) {
    run.push(w.animate([{ transform: 'rotate(' + r0 + 'deg)' }, { transform: 'rotate(' + (r0 + r1) + 'deg)' }], { duration: ramp, easing: 'cubic-bezier(.4,0,.75,.5)', fill: 'forwards' }));
    run.push(w.animate([{ transform: 'rotate(' + (r0 + r1) + 'deg)' }, { transform: 'rotate(' + (r0 + r1 + 360) + 'deg)' }], { duration: 360 / V, delay: ramp, iterations: Infinity }));
    angle = function(){
      var b = run[1].effect.getComputedTiming();
      if (b.progress != null && b.currentIteration != null) return r0 + r1 + 360 * (b.currentIteration + b.progress);
      var a = run[0].effect.getComputedTiming();
      return r0 + r1 * (a.progress == null ? 1 : a.progress);
    };
    ticker(function(){ return angle(); });
  }
  api('wh_spin', function(j){
    var i = idxOf(j.prize && j.prize.id);
    if (i < 0) { P.push(j.prize); paint(); grid(); i = P.length - 1; }
    var seg = 360 / P.length, want = 360 - (i + .5) * seg + (Math.random() - .5) * seg * .6;
    var done = function(deg){ rest(deg); S.busy = false; state('done'); win(j.prize, j.coupon, j.note, j.dm); };
    if (!live) {
      var to = r0 - mod(r0, 360) + 360 * 3 + want, d = RM ? 600 : 4200;
      setRot(to, d);
      setTimeout(function(){ done(to); }, d + 120);
      return;
    }
    var cur = angle(), dist = mod(want - cur, 360) + 360 * (LITE ? 3 : 4), dur = 3.03 * dist / V;
    var fin = w.animate([{ transform: 'rotate(' + cur + 'deg)' }, { transform: 'rotate(' + (cur + dist) + 'deg)' }], { duration: dur, easing: 'cubic-bezier(.33,1,.68,1)', fill: 'forwards' });
    try { fin.startTime = D.timeline.currentTime; } catch(e){}
    run.forEach(function(a){ a.cancel(); });
    run = [];
    angle = function(){ var c = fin.effect.getComputedTiming(); return cur + dist * (c.progress == null ? 1 : c.progress); };
    fin.onfinish = function(){ done(cur + dist); };
  }, function(j){
    rest(angle());
    S.busy = false;
    if (j.error === 'done') { state('done'); if (j.spin) restOn(idxOf(j.spin.prize.id)); return; }
    if (j.error === 'wait' || j.error === 'need' || j.error === 'full') { state(j.error); return; }
    if (j.error === 'off' || j.error === 'empty') { state('off'); return; }
    state('ready'); toast((j && j.message) || 'خطا — دوباره امتحان کنید.');
  });
}
function closeApp(){
  try { if (TG && TG.close) { TG.close(); return; } } catch(e){}
  $('modal').classList.add('hid');
}
function win(p, cp, note, dm){
  var lose = p.type === 'none', name = iconOf(p), keep = !!(cp && cp.code && dm === false);
  $('mI').innerHTML = name ? '<svg viewBox="0 0 48 48"><use href="#i-' + name + '"/></svg>' : '<svg viewBox="0 0 48 48"><use href="#i-box"/></svg>';
  $('mT').textContent = lose ? (T.lose || '') : tx('win', { prize: p.label });
  $('mS').textContent = lose ? '' : p.label;
  var c = $('mC');
  if (cp && cp.code) {
    $('mCt').textContent = cp.code; $('mCb').textContent = T.copy || 'کپی';
    $('mCb').onclick = function(){ copy(cp.code); };
    c.classList.remove('hid');
  } else c.classList.add('hid');
  $('mN').textContent = lose ? '' : (keep ? (T.nodm || '') : (note || T.sent || ''));
  $('mX').textContent = T.close || 'باشه';
  $('modal').classList.remove('hid');
  if (!lose) { buzz('success'); confetti(); } else buzz('warning');
  if (!keep) setTimeout(closeApp, lose ? 2600 : 3600);
}
function copy(t){
  var ok = function(){ toast('✓'); buzz('success'); };
  try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).then(ok, fb); return; } } catch(e){}
  fb();
  function fb(){ try { var a = D.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = '0'; D.body.appendChild(a); a.select(); D.execCommand('copy'); D.body.removeChild(a); ok(); } catch(e){} }
}
function confetti(){
  if (RM) return;
  var cv = $('cf'), r = cv.getBoundingClientRect(), dpr = Math.min(1.5, window.devicePixelRatio || 1);
  cv.width = Math.round(r.width * dpr); cv.height = Math.round(r.height * dpr);
  var x = cv.getContext('2d'), cols = ['#FFF1C1', '#E9B949', '#C026D3', '#7C3AED', '#22D3EE', '#F59E0B'], ps = [], n = LITE ? 36 : 64;
  for (var i = 0; i < n; i++) ps.push({ x: cv.width / 2, y: cv.height * .3, vx: (Math.random() - .5) * 13 * dpr, vy: (-Math.random() * 12 - 4) * dpr,
    w: (4 + Math.random() * 4) * dpr, h: (2.5 + Math.random() * 3) * dpr, c: cols[i % cols.length] });
  var t0 = performance.now();
  (function f(t){
    x.clearRect(0, 0, cv.width, cv.height);
    for (var j = 0; j < ps.length; j++) { var p = ps[j]; p.vy += .36 * dpr; p.vx *= .99; p.x += p.vx; p.y += p.vy; x.fillStyle = p.c; x.fillRect(p.x, p.y, p.w, p.h); }
    if (t - t0 < 1800) requestAnimationFrame(f); else x.clearRect(0, 0, cv.width, cv.height);
  })(t0);
}
function boot(){
  $('rules').textContent = T.rules || '';
  $('pzT').textContent = T.prizes || '';
  $('go').onclick = spin; $('hub').onclick = spin;
  $('mX').onclick = function(){ tap('light'); closeApp(); };
  leds();
  state('busy');
  api('wh_state', function(j){
    P = j.prizes || [];
    paint(); grid();
    if (D.fonts && D.fonts.load) { try { D.fonts.load('800 13px Vazirmatn').then(paintSoon, function(){}); D.fonts.ready.then(paintSoon); } catch(e){} }
    if (!j.on) { state('off'); return; }
    if (j.spin) { restOn(idxOf(j.spin.prize.id)); state('done', j.spin.prize.label + (j.spin.code ? ' · ' + j.spin.code : '')); return; }
    if (!j.open) { state('wait'); return; }
    if (!j.ticket) { state('need'); return; }
    if (j.full) { state('full'); return; }
    state('ready');
  }, function(j){ state('off'); toast((j && j.message) || 'خطا'); });
  var rw = 0;
  window.addEventListener('resize', function(){ var w = $('wheel').offsetWidth; if (Math.abs(w - rw) > 8) { rw = w; paintSoon(); } });
}
boot();
if (TG) tgSetup();
else { var tries = 0, iv = setInterval(function(){
  if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; tgSetup(); }
  else if (++tries > 150) clearInterval(iv);
}, 100); }
</script>
</body>
</html>
HTML;
}
