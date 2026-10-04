<?php
defined('NB_ROOT') || exit;

function svTplIg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl" class="spl-on" style="--sd:__SPL__s">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover"><link rel="icon" href="data:,">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#0A0510">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
<script>(function(){try{var n=navigator,m=n.deviceMemory||8,c=n.hardwareConcurrency||8,sd=(n.connection&&n.connection.saveData),rm=(window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches);if(m<=3||c<=4||sd||rm)document.documentElement.classList.add("lite");}catch(e){}})();</script>
__FONT__
<style>
:root{
  --bg:#0A0510;--glass:rgba(38,14,44,.5);--glass2:rgba(52,18,58,.6);--solid:#170A1C;
  --line:rgba(255,255,255,.09);--line2:rgba(236,72,153,.32);
  --ink:#FFF3F9;--dim:#C0A6BF;--dim2:#846C86;
  --o:#F58529;--p:#DD2A7B;--pk:#FF5FA2;--v:#8134AF;--b:#515BD4;--y:#FEDA75;--ok:#4ADE80;--warn:#FBBF24;--red:#FB7185;
  --grad:linear-gradient(45deg,#F58529 0%,#DD2A7B 45%,#8134AF 78%,#515BD4 100%);
  --grad2:linear-gradient(135deg,#FEDA75 0%,#FA7E1E 30%,#D62976 60%,#962FBF 85%,#4F5BD5 100%);
  --ring:conic-gradient(#FEDA75,#FA7E1E,#D62976,#962FBF,#4F5BD5,#962FBF,#D62976,#FA7E1E,#FEDA75);
  --z:.9;--safe:calc(env(safe-area-inset-bottom,0px) / .9);--top:0px;color-scheme:dark;
  --dots:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cg fill='%23FFD1E6'%3E%3Ccircle cx='24' cy='40' r='1.1' opacity='.8'/%3E%3Ccircle cx='98' cy='14' r='.8' opacity='.6'/%3E%3Ccircle cx='162' cy='72' r='1.3' opacity='.5'/%3E%3Ccircle cx='252' cy='28' r='.9' opacity='.8'/%3E%3Ccircle cx='282' cy='118' r='1' opacity='.55'/%3E%3Ccircle cx='198' cy='162' r='.8' opacity='.7'/%3E%3Ccircle cx='58' cy='142' r='1' opacity='.5'/%3E%3Ccircle cx='122' cy='212' r='1.2' opacity='.6'/%3E%3Ccircle cx='28' cy='252' r='.8' opacity='.7'/%3E%3Ccircle cx='232' cy='238' r='1.1' opacity='.6'/%3E%3Ccircle cx='172' cy='288' r='.9' opacity='.5'/%3E%3Ccircle cx='88' cy='282' r='.7' opacity='.8'/%3E%3C/g%3E%3Cg fill='%23FEDA75'%3E%3Ccircle cx='142' cy='118' r='1' opacity='.6'/%3E%3Ccircle cx='268' cy='198' r='1' opacity='.5'/%3E%3Ccircle cx='52' cy='92' r='.9' opacity='.6'/%3E%3C/g%3E%3C/svg%3E")
}
html.scr .mesh *{animation-play-state:paused!important}
html.fs{--top:calc((var(--tg-content-safe-area-inset-top,var(--tg-safe-area-inset-top,34px)) + 46px) / .9)}
html{zoom:.9}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg);color:var(--ink);min-height:100%}
body{font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:13px;line-height:1.7;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
input{font-family:inherit;-webkit-user-select:text;user-select:text}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
svg{display:block}
.hid{display:none!important}
.ltr{direction:ltr;unicode-bidi:isolate}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes ping{0%{transform:scale(1);opacity:.7}80%,100%{transform:scale(2.8);opacity:0}}
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-6px,0)}}
@keyframes shine{0%,72%{transform:translate3d(-130%,0,0) skewX(-20deg)}100%{transform:translate3d(360%,0,0) skewX(-20deg)}}
@keyframes up{from{opacity:0;transform:translate3d(0,14px,0) scale(.98)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}
@keyframes beat{0%,100%{transform:scale(1)}14%{transform:scale(1.22)}28%{transform:scale(1)}42%{transform:scale(1.12)}56%{transform:scale(1)}}
.gb{position:relative}
.gb:before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;pointer-events:none;z-index:3;
  background:linear-gradient(120deg,rgba(254,218,117,.65),rgba(245,133,41,.6),rgba(221,42,123,.6),rgba(129,52,175,.55),rgba(81,91,212,.7));
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude}

.mesh{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;overflow:clip;contain:strict;
  background:var(--dots) 0 0/300px 300px repeat,
    radial-gradient(circle 47vw at 12vw 17vw,rgba(245,133,41,.34),transparent),
    radial-gradient(circle 52vw at 92vw calc(8vh + 52vw),rgba(221,42,123,.34),transparent),
    radial-gradient(circle 50vw at 15vw calc(52vh + 50vw),rgba(129,52,175,.34),transparent),
    radial-gradient(circle 42vw at 100vw 100vh,rgba(81,91,212,.26),transparent),
    radial-gradient(110vw 16vh at 50% 38vh,rgba(255,95,162,.1),transparent 72%),
    radial-gradient(120vw 70vh at 50% -18%,#2B0D30 0%,transparent 70%),
    linear-gradient(180deg,#0A0510 0%,#130619 55%,#0A0510 100%)}
.mesh>*{position:absolute;display:block}
.mesh .ht{bottom:-40px;width:18px;height:18px;color:rgba(255,95,162,.6);opacity:0;will-change:transform,opacity;animation:hup 15s linear infinite}
.mesh .ht svg{width:100%;height:100%;filter:drop-shadow(0 0 6px rgba(255,95,162,.7))}
.mesh .ht:nth-of-type(1){left:8%}
.mesh .ht:nth-of-type(2){left:28%;width:12px;height:12px;animation-duration:19s;animation-delay:-5s;color:rgba(254,218,117,.6)}
.mesh .ht:nth-of-type(3){left:52%;width:22px;height:22px;animation-duration:17s;animation-delay:-9s}
.mesh .ht:nth-of-type(4){left:72%;width:14px;height:14px;animation-duration:21s;animation-delay:-2s;color:rgba(167,139,250,.65)}
.mesh .ht:nth-of-type(5){left:88%;animation-duration:16s;animation-delay:-12s;color:rgba(245,133,41,.6)}
.mesh .ht:nth-of-type(6){left:40%;width:10px;height:10px;animation-duration:24s;animation-delay:-15s}
@keyframes hup{0%{transform:translate3d(0,0,0) scale(.6) rotate(-12deg);opacity:0}8%{opacity:.85}50%{transform:translate3d(20px,-55vh,0) scale(1) rotate(10deg)}88%{opacity:.5}100%{transform:translate3d(-14px,-112vh,0) scale(.8) rotate(-8deg);opacity:0}}
.mesh .sp{width:4px;height:4px;border-radius:50%;background:#fff;box-shadow:0 0 8px 2px rgba(255,95,162,.8);opacity:.2;animation:twk 3.2s ease-in-out infinite}
.mesh .sp:nth-of-type(1){left:18%;top:14%}
.mesh .sp:nth-of-type(2){left:80%;top:24%;animation-delay:-1s;box-shadow:0 0 8px 2px rgba(254,218,117,.9)}
.mesh .sp:nth-of-type(3){left:46%;top:44%;animation-delay:-2s}
.mesh .sp:nth-of-type(4){left:12%;top:70%;animation-delay:-.5s;box-shadow:0 0 8px 2px rgba(129,52,175,.9)}
.mesh .sp:nth-of-type(5){left:66%;top:84%;animation-delay:-1.5s}
@keyframes twk{0%,100%{opacity:.15;transform:scale(.6)}50%{opacity:1;transform:scale(1.3)}}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:calc(var(--top) + 8px) 14px calc(104px + var(--safe));overflow-x:clip}

.hd0{position:sticky;top:calc(var(--top) + 6px);z-index:30;margin-bottom:10px}
.hd0:before{content:"";position:fixed;left:0;right:0;top:0;height:calc(var(--top) + 6px);z-index:-1;pointer-events:none;
  background:var(--dots) 0 0/300px 300px repeat,
    radial-gradient(circle 47vw at 12vw 17vw,rgba(245,133,41,.34),transparent) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat,
    radial-gradient(circle 52vw at 92vw calc(8vh + 52vw),rgba(221,42,123,.34),transparent) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat,
    radial-gradient(120vw 70vh at 50% -18%,#2B0D30 0%,transparent 70%) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat,
    linear-gradient(180deg,#0A0510 0%,#130619 55%,#0A0510 100%) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat}
.hdr{display:flex;align-items:center;gap:10px;padding:8px 9px;border-radius:22px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),#18091E;
  box-shadow:0 18px 40px -22px #000,inset 0 1px 0 rgba(255,255,255,.08)}
.ava{position:relative;width:42px;height:42px;flex:0 0 auto;border-radius:50%;padding:2.5px;overflow:hidden;overflow:clip}
.ava:before{content:"";position:absolute;inset:-25%;background:var(--ring);animation:spin 4s linear infinite}
.ava span{position:relative;display:grid;place-items:center;width:100%;height:100%;border-radius:50%;overflow:hidden;overflow:clip;
  background:#1A0B20;border:2px solid #1A0B20;font-weight:900;font-size:15px;color:var(--pk)}
.ava span img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim);white-space:nowrap;overflow:hidden}
.who small span{overflow:hidden;text-overflow:ellipsis}
.dot{position:relative;display:inline-block;flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.dot:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 2s ease-out infinite}
.bal{display:flex;align-items:center;gap:6px;height:36px;padding:0 13px;border-radius:18px;font-weight:900;font-size:12px;
  background:linear-gradient(135deg,rgba(221,42,123,.22),rgba(129,52,175,.16));border:1px solid var(--line2)}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.ib{width:36px;height:36px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.05)}
.ib svg{width:18px;height:18px;color:var(--pk)}

.pg{display:none}
.pg.on{display:block}
.pg.on>*{animation:up .5s cubic-bezier(.2,.85,.25,1) backwards}
.pg.on>:nth-child(2){animation-delay:.05s}
.pg.on>:nth-child(3){animation-delay:.1s}
.pg.on>:nth-child(4){animation-delay:.15s}
.pg.on>:nth-child(n+5){animation-delay:.2s}

.wmk{display:flex;align-items:center;gap:8px;margin:4px 2px 0}
.wmk b{font-size:20px;font-weight:900;letter-spacing:-.3px;background:linear-gradient(90deg,#FEDA75,#FA7E1E 25%,#FF5FA2 55%,#C084FC 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.wmk span{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:12px;font-size:10px;font-weight:800;color:var(--ok);background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25)}

.stories{display:flex;gap:12px;overflow-x:auto;scrollbar-width:none;margin:6px -14px 0;padding:6px 14px 8px}
.stories::-webkit-scrollbar{display:none}
.story{flex:0 0 auto;width:68px;text-align:center;animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 55ms)}
.story .rg{position:relative;width:66px;height:66px;margin:0 auto;border-radius:50%;padding:3px;overflow:hidden;overflow:clip;box-shadow:0 10px 22px -12px rgba(221,42,123,.8)}
.story .rg:before{content:"";position:absolute;inset:-20%;background:var(--ring)}
.story.on .rg:before{animation:spin 2.6s linear infinite}
.story .in{position:relative;width:100%;height:100%;border-radius:50%;background:#140818;padding:3px}
.story .in div{width:100%;height:100%;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.06);color:var(--pk)}
.story.on .in div{background:var(--grad);color:#fff}
.story .in svg{width:24px;height:24px}
.story small{display:block;margin-top:5px;font-size:10.5px;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.post{margin-top:10px;border-radius:26px;overflow:hidden;overflow:clip;isolation:isolate;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),var(--glass);box-shadow:0 30px 50px -30px rgba(221,42,123,.6)}
.post .u{display:flex;align-items:center;gap:9px;padding:11px 13px}
.post .u .a{position:relative;width:36px;height:36px;border-radius:50%;padding:2px;overflow:hidden;overflow:clip;flex:0 0 auto}
.post .u .a:before{content:"";position:absolute;inset:-25%;background:var(--ring);animation:spin 5s linear infinite}
.post .u .a div{position:relative;width:100%;height:100%;border-radius:50%;border:2px solid #1A0B20;background:var(--grad);display:grid;place-items:center;color:#fff}
.post .u .a svg{width:16px;height:16px}
.post .u b{font-size:12.5px;font-weight:800;display:flex;align-items:center;gap:4px}
.post .u b svg{width:14px;height:14px;color:#3897F0}
.post .u small{display:block;font-size:10px;color:var(--dim)}
.post .img{position:relative;height:220px;overflow:hidden;overflow:clip;display:grid;place-items:center;isolation:isolate}
.post .img .sw{position:absolute;z-index:-2;top:0;bottom:0;right:0;width:200%;will-change:transform;
  background:linear-gradient(100deg,#F58529 0%,#DD2A7B 25%,#8134AF 50%,#515BD4 62%,#8134AF 75%,#DD2A7B 88%,#F58529 100%);animation:slw 14s ease-in-out infinite alternate}
@keyframes slw{to{transform:translate3d(50%,0,0)}}
.post .img:before{content:"";position:absolute;z-index:-1;inset:0;background:radial-gradient(60% 60% at 28% 26%,rgba(255,255,255,.26),transparent 70%),linear-gradient(180deg,transparent 45%,rgba(10,5,16,.4))}
.post .img:after{content:"";position:absolute;z-index:-1;width:210px;height:210px;border-radius:50%;border:26px solid rgba(255,255,255,.1);bottom:-100px;right:-60px}
.post .img .big{position:relative;display:flex;gap:10px;align-items:flex-end}
.post .img .big span{width:62px;height:62px;border-radius:20px;display:grid;place-items:center;color:#fff;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.38);box-shadow:0 14px 26px -14px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.4);
  animation:bob 3.4s ease-in-out infinite}
.post .img .big span:nth-child(2){width:78px;height:78px;border-radius:24px;animation-delay:-.8s}
.post .img .big span:nth-child(3){animation-delay:-1.6s}
.post .img .big svg{width:30px;height:30px}
.post .img .pop{position:absolute;width:96px;height:96px;color:#fff;opacity:0;filter:drop-shadow(0 8px 24px rgba(0,0,0,.35));animation:pop 6s ease-in-out 1.2s infinite}
@keyframes pop{0%,58%{opacity:0;transform:scale(.2)}64%{opacity:1;transform:scale(1.18)}70%{transform:scale(.94)}78%{opacity:1;transform:scale(1)}90%,100%{opacity:0;transform:translate3d(0,-26px,0) scale(1.3)}}
.post .img .tag{position:absolute;bottom:12px;right:12px;padding:4px 10px;border-radius:14px;background:rgba(10,5,16,.45);color:#fff;font-size:10.5px;font-weight:700;
  border:1px solid rgba(255,255,255,.2)}
.post .act{display:flex;align-items:center;gap:14px;padding:11px 13px 2px}
.post .act svg{width:23px;height:23px}
.post .act .sv{margin-right:auto}
.post .act .lk{color:#FF3B6B;animation:beat 1.8s ease-in-out infinite}
.post .cap{padding:4px 13px 14px}
.post .cap b{font-size:14px;font-weight:900}
.post .cap p{font-size:11.5px;color:var(--dim);margin-top:2px}
.cta{position:relative;overflow:hidden;overflow:clip;margin-top:12px;width:100%;height:48px;border-radius:15px;background:var(--grad);color:#fff;font-weight:900;font-size:13.5px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 14px 26px -14px rgba(221,42,123,.95)}
.cta:after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);animation:shine 4s ease-in-out infinite}
.cta svg{width:18px;height:18px}
.cta[disabled]{opacity:.45;filter:grayscale(.4)}
.cta[disabled]:after{display:none}
.cta.gh{background:var(--glass);color:var(--ink);border:1px solid var(--line2);box-shadow:none}
.cta.gh:after{display:none}


.stat3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.stat3 div{padding:10px 8px;border-radius:17px;text-align:center;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.stat3 b{display:block;font-size:15px;font-weight:900;background:linear-gradient(90deg,#FEDA75,#FF5FA2);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat3 small{font-size:10px;color:var(--dim);white-space:nowrap}

.hd{display:flex;align-items:center;margin:20px 2px 10px}
.hd h3{flex:1;font-size:15px;font-weight:900;display:flex;align-items:center;gap:7px}
.hd h3 i{width:8px;height:8px;border-radius:50%;background:var(--grad2);box-shadow:0 0 10px rgba(255,95,162,.9)}
.hd button{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:800;color:var(--pk);min-height:34px;padding:0 6px;margin:-10px -6px}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tile{position:relative;overflow:hidden;overflow:clip;isolation:isolate;display:flex;flex-direction:column;text-align:right;padding:12px;border-radius:22px;min-height:182px;width:100%;
  background:linear-gradient(165deg,rgba(255,255,255,.08),rgba(255,255,255,.015) 55%),var(--glass);border:1px solid var(--line);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.07);
  animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 55ms);transition:transform .15s}
.tile:before{content:"";position:absolute;z-index:-1;width:130px;height:130px;border-radius:50%;right:-40px;top:-50px;
  background:radial-gradient(closest-side,rgba(221,42,123,.36),transparent)}
.tile:after{content:"";position:absolute;z-index:2;top:-20%;bottom:-20%;left:0;width:34%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);transform:translate3d(-130%,0,0) skewX(-20deg);
  animation:shine 7s ease-in-out infinite;animation-delay:calc(var(--i,0) * .8s)}
.tile:nth-child(n+4):after{display:none}
.tile:nth-child(n+5) .ft i:after,.tile:nth-child(n+7) small i:after{display:none}
.tile .ic{position:relative;width:46px;height:46px;border-radius:15px;display:grid;place-items:center;color:#fff;background:var(--grad);
  box-shadow:0 10px 20px -10px rgba(221,42,123,.9)}
.tile .ic svg{width:22px;height:22px}
.tile.c1 .ic{background:linear-gradient(135deg,#F58529,#DD2A7B)}
.tile.c2 .ic{background:linear-gradient(135deg,#DD2A7B,#8134AF)}
.tile.c3 .ic{background:linear-gradient(135deg,#8134AF,#515BD4)}
.tile.c4 .ic{background:linear-gradient(135deg,#FEDA75,#F58529);color:#3A1500}
.tile.c1:before{background:radial-gradient(closest-side,rgba(245,133,41,.34),transparent)}
.tile.c3:before{background:radial-gradient(closest-side,rgba(81,91,212,.4),transparent)}
.tile.c4:before{background:radial-gradient(closest-side,rgba(254,218,117,.26),transparent)}
.tile b{margin-top:10px;font-size:12px;font-weight:800;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile small{display:flex;align-items:center;gap:5px;font-size:9.5px;color:var(--dim2);margin-top:3px}
.tile small i{position:relative;width:6px;height:6px;border-radius:50%;background:var(--ok);flex:0 0 auto}
.tile small i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.8s ease-out infinite}
.tile .ft{margin-top:auto;padding-top:8px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .ft span{font-size:10px;color:var(--dim);line-height:1.5}
.tile .ft span b{display:inline;margin:0;font-size:14.5px;font-weight:900;color:var(--ink)}
.tile .ft i{position:relative;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto;
  box-shadow:0 8px 16px -8px rgba(221,42,123,.9)}
.tile .ft i:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,95,162,.6);animation:ping 2.4s ease-out infinite}
.tile .ft i svg{width:15px;height:15px}
.tile .rf{position:absolute;top:12px;left:12px;font-size:9px;font-weight:800;padding:2px 7px;border-radius:8px;background:rgba(74,222,128,.12);color:var(--ok);border:1px solid rgba(74,222,128,.25)}
.tile:active{transform:scale(.98)}
.tile .fcs{margin-top:7px}
.tile .fc{height:22px;padding:0 8px;font-size:9.5px;max-width:100%;overflow:hidden;text-overflow:ellipsis}
.list{display:grid;gap:11px}
.pgr{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.cd{--tc:255,95,162;position:relative;overflow:hidden;overflow:clip;isolation:isolate;display:flex;flex-direction:column;min-width:0;width:100%;text-align:right;
  padding:11px;border-radius:24px;border:1px solid rgba(var(--tc),.3);
  background:radial-gradient(130% 60% at 100% 0%,rgba(var(--tc),.22),transparent 64%),linear-gradient(172deg,rgba(255,255,255,.075),rgba(255,255,255,.012) 58%),var(--glass);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.1),0 18px 30px -24px rgba(var(--tc),.85);
  animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 40ms);transition:transform .15s,border-color .2s}
.cd:before{content:"";position:absolute;top:0;left:16%;right:16%;height:2px;border-radius:0 0 3px 3px;background:linear-gradient(90deg,transparent,rgb(var(--tc)),transparent)}
.cd:active{transform:scale(.97);border-color:rgba(var(--tc),.65)}
.cd.t-cheap,.cd.t-iran{--tc:74,222,128}
.cd.t-high{--tc:251,191,36}
.cd.t-fake{--tc:161,161,190}
.cd.t-ru{--tc:96,165,250}
.cd.t-ref{--tc:45,212,191}
.cd .tp{display:flex;align-items:flex-start;justify-content:space-between;gap:6px}
.cd .rb{display:inline-flex;align-items:center;gap:3px;min-width:0;height:23px;padding:0 9px;border-radius:12px;font-size:9.5px;font-weight:900;white-space:nowrap;overflow:hidden;
  color:rgb(var(--tc));background:rgba(var(--tc),.14);border:1px solid rgba(var(--tc),.34)}
.cd .rb svg{width:11px;height:11px;flex:0 0 auto}
.cd.t-iran .rb{color:#DCFCE7;background:linear-gradient(90deg,rgba(34,197,94,.26),rgba(255,255,255,.1),rgba(239,68,68,.26));border-color:rgba(255,255,255,.22)}
.cd.t-ru .rb{color:#E0E7FF;background:linear-gradient(90deg,rgba(255,255,255,.12),rgba(59,130,246,.26),rgba(239,68,68,.26));border-color:rgba(147,197,253,.3)}
.cd .ic{flex:0 0 auto;width:34px;height:34px;border-radius:12px;display:grid;place-items:center;color:#fff;
  background:linear-gradient(140deg,rgba(var(--tc),.95),rgba(var(--tc),.4));box-shadow:0 8px 16px -8px rgba(var(--tc),.95),inset 0 1px 0 rgba(255,255,255,.4)}
.cd .ic svg{width:18px;height:18px}
.cd.t-high .ic,.cd.t-cheap .ic,.cd.t-iran .ic,.cd.t-fake .ic{color:#1A0716}
.cd .nm{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-top:9px;font-size:13.5px;font-weight:900;line-height:1.55;color:var(--ink)}
.cd .mt{display:flex;align-items:center;gap:5px;margin-top:2px;font-size:10px;font-weight:700;color:var(--dim);white-space:nowrap}
.cd .lv{width:6px;height:6px;flex:0 0 auto;border-radius:50%;background:var(--ok);box-shadow:0 0 8px rgba(74,222,128,.8)}
.cd .fcs{gap:5px;margin-top:8px}
.cd .fc{max-width:100%;height:23px;padding:0 8px;font-size:9.8px;overflow:hidden}
.cd .fc.em{height:28px;font-size:15px;letter-spacing:1.5px}
.cd .ft{margin-top:auto;padding-top:10px}
.cd .pz{display:block;margin-top:10px;padding-top:9px;border-top:1px dashed rgba(var(--tc),.26);white-space:nowrap}
.cd .pz b{font-size:20px;font-weight:900;line-height:1.2;background:linear-gradient(90deg,#fff 10%,rgb(var(--tc)));-webkit-background-clip:text;background-clip:text;color:transparent}
.cd .pz i{font-style:normal;font-size:10.5px;font-weight:800;margin-right:4px;color:var(--dim)}
.cd .pz small{display:block;font-size:9.5px;font-weight:700;color:var(--dim2)}
.cd .ordb{display:flex;align-items:center;justify-content:center;gap:3px;height:38px;margin-top:9px;border-radius:19px;
  background:var(--grad);color:#fff;font-size:12.5px;font-weight:900;box-shadow:0 10px 20px -12px rgba(221,42,123,.95),inset 0 1px 0 rgba(255,255,255,.32)}
.cd .ordb svg{width:15px;height:15px}
.cd.t-high{border-color:rgba(251,191,36,.45);background:radial-gradient(130% 60% at 100% 0%,rgba(251,191,36,.2),transparent 64%),radial-gradient(90% 60% at 0% 100%,rgba(221,42,123,.24),transparent 70%),linear-gradient(172deg,rgba(255,255,255,.08),rgba(255,255,255,.012) 58%),var(--glass)}
.cd.t-high .ordb{background:linear-gradient(120deg,#FEDA75,#FA7E1E 60%,#F58529);color:#3A1500;box-shadow:0 10px 20px -12px rgba(250,126,30,.95),inset 0 1px 0 rgba(255,255,255,.5)}
.cd.t-high:after{content:"";position:absolute;z-index:2;top:0;bottom:0;left:0;width:34%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(254,218,117,.16),transparent);transform:translate3d(-130%,0,0) skewX(-20deg);animation:shine 6.5s ease-in-out infinite}
.cd.w{grid-column:1/-1}
.cd.w .ft{display:flex;align-items:flex-end;justify-content:space-between;gap:12px}
.cd.w .pz{flex:1;min-width:0}
.cd.w .ordb{flex:0 0 46%;margin-top:0}
.cd.w .nm{font-size:14.5px}
.tier{display:inline-flex;align-items:center;gap:3px;vertical-align:middle;font-size:9.5px;font-weight:900;padding:2px 8px;border-radius:8px;white-space:nowrap;
  color:#BAE6FD;background:rgba(56,189,248,.14);border:1px solid rgba(56,189,248,.3)}
.tier.cheap{color:#86EFAC;background:rgba(34,197,94,.13);border-color:rgba(74,222,128,.32)}
.tier.mid{color:#BAE6FD;background:rgba(56,189,248,.13);border-color:rgba(56,189,248,.32)}
.tier.high{color:#FDE68A;background:rgba(245,158,11,.14);border-color:rgba(252,211,77,.4)}
.tier.fake{color:#CBD5E1;background:rgba(148,163,184,.13);border-color:rgba(148,163,184,.3)}
.tier.iran{color:#BBF7D0;background:linear-gradient(90deg,rgba(34,197,94,.18),rgba(255,255,255,.06),rgba(239,68,68,.18));border-color:rgba(255,255,255,.22)}
.tier.ru{color:#E0E7FF;background:linear-gradient(90deg,rgba(255,255,255,.1),rgba(59,130,246,.2),rgba(239,68,68,.2));border-color:rgba(147,197,253,.3)}
.tier.ref{color:#99F6E4;background:rgba(20,184,166,.14);border-color:rgba(45,212,191,.35)}
.sech{display:flex;align-items:center;gap:10px;margin:14px 2px 2px}
.sech:first-child{margin-top:2px}
.sech .ic{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto;color:#fff;background:var(--grad);box-shadow:0 8px 16px -10px rgba(0,0,0,.8)}
.sech .ic svg{width:18px;height:18px}
.sech b{display:block;font-size:14px;font-weight:900}
.sech small{display:block;font-size:10.5px;font-weight:700;color:var(--dim)}
.more{width:100%;height:46px;border-radius:15px;border:1px dashed var(--line2);background:var(--glass);color:var(--ink);font-size:12.5px;font-weight:900}
.emrow{display:flex;flex-direction:column;gap:9px}
.emi{font-size:28px!important;text-align:center;height:60px!important;letter-spacing:4px}
.qchips.ems button{font-size:19px;height:40px;min-width:46px;padding:0 8px}
.fld small.okk{color:var(--ok)}
.fld textarea{width:100%;min-height:118px;padding:12px 14px;border-radius:15px;background:rgba(3,12,26,.6);border:1px solid var(--line2);color:var(--ink);
  font-family:inherit;font-size:13.5px;font-weight:600;line-height:1.9;outline:0;resize:vertical}

.fcs{display:flex;flex-wrap:wrap;gap:6px;margin-top:11px}
.fc{display:inline-flex;align-items:center;gap:4px;height:25px;padding:0 10px;border-radius:13px;font-size:10.5px;font-weight:800;white-space:nowrap;
  color:#FFC2DD;background:rgba(255,95,162,.1);border:1px solid rgba(255,95,162,.24)}
.fc svg{width:12.5px;height:12.5px;flex:0 0 auto}
.fc.ok{color:var(--ok);background:rgba(74,222,128,.09);border-color:rgba(74,222,128,.25)}
.fc.no{color:var(--dim);background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1)}
.fc.hot{color:var(--y);background:rgba(254,218,117,.09);border-color:rgba(254,218,117,.25)}
.fc.em{font-size:16px;letter-spacing:2px;height:30px}

.srch{display:flex;align-items:center;gap:8px;height:46px;margin-top:2px;padding:0 14px;border-radius:23px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),var(--glass);border:1px solid var(--line2)}
.srch svg{width:17px;height:17px;color:var(--pk)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}
.srch input::placeholder{color:var(--dim2)}

.emp{text-align:center;padding:30px 18px;border-radius:22px;border:1px dashed var(--line2);color:var(--dim);
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass)}
.emp>svg{width:42px;height:42px;margin:0 auto 10px;color:var(--pk);animation:bob 3s ease-in-out infinite}
.emp>b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{position:relative;overflow:hidden;overflow:clip;height:86px;border-radius:20px;margin-bottom:10px;background:var(--glass);border:1px solid var(--line)}
.sk:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,95,162,.1),transparent);animation:skl 1.2s linear infinite}
@keyframes skl{from{transform:translate3d(-100%,0,0)}to{transform:translate3d(100%,0,0)}}

.or{position:relative;overflow:hidden;overflow:clip;border-radius:20px;padding:13px 16px 13px 13px;margin-bottom:10px;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line);
  animation:up .45s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 50ms)}
.or:before{content:"";position:absolute;right:0;top:0;bottom:0;width:4px;background:var(--grad2)}
.or.done:before{background:var(--ok)}.or.partial:before,.or.check:before{background:var(--warn)}.or.canceled:before,.or.failed:before{background:var(--red)}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
b[dir=auto]{text-align:right;unicode-bidi:plaintext}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(255,95,162,.14);color:var(--pk)}
.pill.run:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;animation:twk 1.4s ease-in-out infinite}
.pill.done{background:rgba(74,222,128,.12);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(251,191,36,.14);color:var(--warn)}
.pill.canceled,.pill.failed{background:rgba(251,113,133,.14);color:var(--red)}
.prog{height:7px;border-radius:7px;background:rgba(255,255,255,.08);margin:11px 0 7px;overflow:hidden;overflow:clip}
.prog i{position:relative;display:block;height:100%;border-radius:7px;background:var(--grad);overflow:hidden;overflow:clip;transition:width .6s ease}
.prog.run i:after{content:"";position:absolute;top:0;bottom:0;left:0;width:calc(100% + 17px);
  background:repeating-linear-gradient(-45deg,rgba(255,255,255,.3) 0 6px,transparent 6px 12px);animation:strp .8s linear infinite}
@keyframes strp{to{transform:translate3d(-16.97px,0,0)}}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:#7DB9FF;direction:ltr;overflow:hidden;overflow:clip}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}
.rfr{margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.rfs{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:var(--dim)}
.rfs svg{width:13px;height:13px}
.rfb{height:34px;padding:0 14px;border-radius:17px;display:inline-flex;align-items:center;gap:6px;margin-inline-start:auto;
  font-size:11px;font-weight:900;color:#fff;background:var(--grad);border:0;box-shadow:0 8px 18px -10px rgba(221,42,123,.9)}
.rfb svg{width:15px;height:15px}
.rfb:disabled{opacity:.55}

.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:50px;padding:0 16px;border-radius:16px;background:rgba(10,5,16,.55);border:1px solid var(--line2);color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input::placeholder{color:var(--dim2)}
.fld input:focus{border-color:var(--pk);box-shadow:0 0 0 3px rgba(255,95,162,.16)}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.cpy{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 10px;border-radius:16px;background:rgba(221,42,123,.14);border:1px solid var(--line2);font-weight:800;font-size:12px}
.cpy svg{width:14px;height:14px;color:var(--pk)}
.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(255,95,162,.07);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}

.links{display:grid;gap:9px;margin-top:14px}
.xl{position:relative;overflow:hidden;overflow:clip;display:flex;align-items:center;gap:12px;min-height:62px;padding:10px 12px;border-radius:20px;text-align:right;
  background:linear-gradient(90deg,rgba(255,255,255,.07),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.xl .ic{width:42px;height:42px;flex:0 0 auto;border-radius:50%;display:grid;place-items:center;color:#fff;box-shadow:0 10px 20px -12px #000}
.xl .ic svg{width:21px;height:21px}
.xl.tg .ic{background:linear-gradient(135deg,#2AABEE,#5EEAD4);color:#03101F}
.xl.num .ic{background:linear-gradient(135deg,#22C55E,#2563EB)}
.xl.sup .ic{background:rgba(255,95,162,.16);color:var(--pk)}
.xl span{flex:1;min-width:0;font-weight:800;font-size:13px}
.xl span small{display:block;font-size:10px;color:var(--dim);font-weight:600}
.xl .ch{width:18px;height:18px;color:var(--dim);animation:nud 1.8s ease-in-out infinite}
@keyframes nud{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(-4px,0,0)}}

.nav{position:fixed;left:12px;right:12px;bottom:calc(10px + var(--safe));z-index:30;max-width:456px;margin:0 auto;border-radius:26px;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02)),#16081C;
  box-shadow:0 22px 44px -18px #000,inset 0 1px 0 rgba(255,255,255,.09)}
.nav>div{position:relative;display:grid;grid-template-columns:repeat(3,1fr);height:64px}
.nav .ind{position:absolute;top:8px;bottom:8px;right:0;width:33.3333%;display:flex;justify-content:center;pointer-events:none;transition:transform .42s cubic-bezier(.3,.9,.3,1)}
.nav .ind:before{content:"";width:62px;border-radius:18px;background:var(--grad);box-shadow:0 10px 22px -8px rgba(221,42,123,.95)}
.nav button{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;font-size:10px;font-weight:800;color:var(--dim2);transition:color .25s}
.nav button svg{width:22px;height:22px;transition:transform .25s}
.nav button.on{color:#fff}
.nav button.on svg{transform:translate3d(0,-1px,0) scale(1.08)}
.nav .bd{position:absolute;top:7px;left:calc(50% - 22px);min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:#FEDA75;color:#3A1500;font-size:9.5px;font-weight:900;display:none;place-items:center}
.nav .bd.on{display:grid}

.ov{position:fixed;inset:0;z-index:40;background:radial-gradient(120% 60% at 50% 100%,rgba(221,42,123,.28),transparent 70%),rgba(10,3,14,.5);
  opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;overscroll-behavior:contain;
  border-radius:30px 30px 0 0;padding:8px 16px calc(18px + var(--safe));border:1px solid rgba(255,190,222,.24);border-bottom:0;
  background:linear-gradient(180deg,rgba(255,255,255,.11) 0%,rgba(255,255,255,.035) 18%,rgba(255,255,255,.012) 100%),
    radial-gradient(110% 38% at 100% 0%,rgba(221,42,123,.4),transparent 62%),radial-gradient(80% 30% at 0% 6%,rgba(245,133,41,.2),transparent 62%),
    radial-gradient(90% 40% at 50% 100%,rgba(129,52,175,.24),transparent 70%),rgba(42,12,48,.66);
  -webkit-backdrop-filter:blur(24px) saturate(170%);backdrop-filter:blur(24px) saturate(170%);
  box-shadow:0 -26px 60px -22px rgba(221,42,123,.55),inset 0 1px 0 rgba(255,255,255,.24);
  transform:translate3d(0,105%,0);transition:transform .36s cubic-bezier(.2,.85,.25,1)}
@supports not ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){.sh{background-color:rgba(42,12,48,.97)}}
.sh:before{content:"";position:absolute;top:0;left:14%;right:14%;height:1.5px;border-radius:2px;pointer-events:none;
  background:linear-gradient(90deg,transparent,#FEDA75,#FF5FA2 50%,#B58CFF,transparent)}
.sh.on{transform:none}
.grab{width:46px;height:5px;border-radius:5px;margin:2px auto 12px;background:linear-gradient(90deg,rgba(254,218,117,.6),rgba(255,95,162,.6),rgba(181,140,255,.6));box-shadow:0 0 12px rgba(255,95,162,.45)}
.sh .st{display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:22px;
  background:linear-gradient(135deg,rgba(255,255,255,.1),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.12);box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto;
  box-shadow:0 10px 22px -8px rgba(221,42,123,.95),inset 0 1px 0 rgba(255,255,255,.35)}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.sh .x svg{width:16px;height:16px}
.sh .fld label{color:#F1D3E6}
.sh .fld input,.sh .fld textarea{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));border:1px solid rgba(255,190,222,.24);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.09),inset 0 -12px 22px -20px rgba(221,42,123,.8);transition:border-color .2s,box-shadow .2s}
.sh .fld input:focus,.sh .fld textarea:focus{border-color:var(--pk);box-shadow:0 0 0 3px rgba(255,95,162,.22),inset 0 1px 0 rgba(255,255,255,.12)}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:50px;height:50px;flex:0 0 auto;border-radius:50%;font-size:22px;font-weight:900;color:#FFC2DD;transition:transform .12s;
  background:linear-gradient(180deg,rgba(255,255,255,.11),rgba(255,255,255,.03));border:1px solid rgba(255,190,222,.28);box-shadow:inset 0 1px 0 rgba(255,255,255,.15)}
.qrow button:active{transform:scale(.92)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:32px;padding:0 13px;border-radius:16px;font-size:11px;font-weight:800;color:var(--dim);transition:background .2s,color .2s,box-shadow .2s;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.11)}
.qchips button.on{color:#fff;border-color:transparent;background:var(--grad);box-shadow:0 8px 18px -10px rgba(221,42,123,.95),inset 0 1px 0 rgba(255,255,255,.3)}
.sum{position:relative;margin-top:14px;border-radius:20px;padding:12px 14px;border:1px solid transparent;
  background:linear-gradient(135deg,rgba(74,22,80,.62),rgba(26,8,32,.5)) padding-box,
    linear-gradient(135deg,rgba(254,218,117,.6),rgba(221,42,123,.35) 40%,rgba(129,52,175,.3) 70%,rgba(81,91,212,.6)) border-box;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.12),0 16px 30px -22px rgba(221,42,123,.95)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t{margin-top:6px;padding-top:9px;border-top:1px dashed rgba(255,190,222,.24)}
.sum div.t b{font-size:21px;font-weight:900;background:linear-gradient(90deg,#FEDA75,#FF5FA2 55%,#B58CFF);-webkit-background-clip:text;background-clip:text;color:transparent}
.sum div.lo b{color:var(--red)}
.sum div.cp b{color:#34D399}
.sh .cta{height:54px;border-radius:27px;box-shadow:0 18px 34px -14px rgba(221,42,123,.95),inset 0 1px 0 rgba(255,255,255,.35)}
.sh .cta.gh{background:rgba(255,255,255,.06);box-shadow:inset 0 1px 0 rgba(255,255,255,.1)}
html.shon .mesh *,html.shon .app *:before,html.shon .app *:after{animation-play-state:paused!important}
.res{text-align:center;padding:6px 0 4px}
.res .rc{position:relative;width:66px;height:66px;margin:4px auto 10px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff}
.res .rc:after{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid rgba(255,95,162,.6);animation:rpl 1.8s ease-out infinite}
@keyframes rpl{from{transform:scale(.9);opacity:.9}to{transform:scale(1.5);opacity:0}}
.res .rc svg{width:32px;height:32px}
.res b{display:block;font-size:15px;font-weight:900}
.res small{font-size:12px;color:var(--pk);font-weight:800}

.toast{position:fixed;left:16px;right:16px;bottom:calc(86px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:18px;background:rgba(36,12,42,.96);border:1px solid var(--line2);color:#fff;font-size:12px;font-weight:700;
  transform:translate3d(0,200%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px rgba(0,0,0,.8)}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:var(--ok)}.toast.er svg{color:var(--red)}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate>svg{width:64px;height:64px;color:var(--pk);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
.spl{position:fixed;inset:0;z-index:100;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;
  background:radial-gradient(85vw 55vh at 50% 40%,rgba(221,42,123,.26),transparent 70%),radial-gradient(70vw 45vh at 12% 8%,rgba(245,133,41,.2),transparent 70%),
    radial-gradient(70vw 45vh at 90% 96%,rgba(81,91,212,.24),transparent 70%),var(--dots) 0 0/300px 300px repeat,#0A0510;
  transition:opacity .5s ease .1s,visibility .5s ease .1s}
.spl.out{opacity:0;visibility:hidden}
.spl-on .app,.spl-on .nav{visibility:hidden}.spl-on .mesh{display:none}
.spl{will-change:opacity}
.spl .spc{display:flex;flex-direction:column;align-items:center;animation:spIn .8s cubic-bezier(.2,.85,.25,1) both;
  transition:transform .5s cubic-bezier(.5,0,.75,0),opacity .3s ease}
.spl.out .spc{transform:scale(1.18);opacity:0}
@keyframes spIn{from{opacity:0;transform:translate3d(0,26px,0) scale(.88)}to{opacity:1;transform:none}}
.lgw{position:relative;width:164px;height:164px;display:grid;place-items:center}
.lgw .rw{position:absolute;inset:0;will-change:transform,opacity;animation:rwIn 1.1s cubic-bezier(.2,.85,.25,1) both,spin 3.2s linear 1.1s infinite}
@keyframes rwIn{from{opacity:0;transform:rotate(-140deg) scale(.84)}to{opacity:1;transform:none}}
.lgw .rw svg{width:100%;height:100%;transform:rotate(-90deg)}
.lgw .rw circle{fill:none;stroke-width:4.5;stroke-linecap:round}
.lgw .gl{position:absolute;inset:14px;border-radius:50%;will-change:transform,opacity;background:radial-gradient(closest-side,rgba(255,95,162,.45),transparent);animation:gpl 2s ease-in-out infinite}
@keyframes gpl{0%,100%{transform:scale(.9);opacity:.7}50%{transform:scale(1.1);opacity:1}}
.lgw .lg{position:relative;width:96px;height:96px;border-radius:30px;display:grid;place-items:center;color:#fff;overflow:hidden;overflow:clip;
  background:linear-gradient(45deg,#FEDA75 0%,#FA7E1E 25%,#D62976 55%,#962FBF 80%,#4F5BD5 100%);
  box-shadow:0 22px 46px -12px rgba(221,42,123,.9),inset 0 2px 0 rgba(255,255,255,.45),inset 0 -8px 16px rgba(0,0,0,.2);will-change:transform;animation:lpu 1.6s ease-in-out infinite}
.lgw .lg:before{content:"";position:absolute;top:0;bottom:0;left:0;width:45%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);animation:shine 2.4s ease-in-out infinite}
.lgw .lg svg{position:relative;width:50px;height:50px}
@keyframes lpu{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
.lgw .hb{position:absolute;left:50%;top:50%;width:22px;height:22px;margin:-11px 0 0 -11px;color:#FF3B6B;opacity:0;animation:hbx 2.4s ease-out infinite}
.lgw .hb svg{width:100%;height:100%;filter:drop-shadow(0 0 8px rgba(255,59,107,.7))}
.lgw .h1{--x:-78px;--y:-82px}
.lgw .h2{--x:70px;--y:-96px;animation-delay:.6s;color:#FEDA75}
.lgw .h3{--x:-40px;--y:-118px;animation-delay:1.2s;width:16px;height:16px;margin:-8px 0 0 -8px}
.lgw .h4{--x:92px;--y:-40px;animation-delay:1.8s;color:#C084FC}
@keyframes hbx{0%{opacity:0;transform:translate3d(0,0,0) scale(.3)}18%{opacity:1}100%{opacity:0;transform:translate3d(var(--x),var(--y),0) scale(1.15)}}
.spl h1{margin-top:22px;font-size:23px;font-weight:900;letter-spacing:-.3px;background:linear-gradient(90deg,#FEDA75,#FA7E1E 25%,#FF5FA2 55%,#C084FC 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.spl p{margin-top:4px;color:var(--dim);font-size:12px;max-width:280px;text-align:center}
.spl .ld{position:absolute;left:0;right:0;bottom:calc(40px + var(--safe));width:min(290px,82vw);margin:0 auto;text-align:center;transition:opacity .3s,transform .4s}
.spl.out .ld{opacity:0;transform:translate3d(0,12px,0)}
.spl .pc{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:11px}
.spl .pc span{font-size:11px;font-weight:800;color:#FFE4F1;text-align:right}
.spl .pc b{font-size:24px;font-weight:900;line-height:1;min-width:62px;text-align:left;background:linear-gradient(90deg,#FEDA75,#FF5FA2 55%,#C084FC);-webkit-background-clip:text;background-clip:text;color:transparent}
.spl .br{position:relative;height:6px;border-radius:6px;overflow:hidden;overflow:clip;background:rgba(255,255,255,.12)}
.spl .br i{position:absolute;inset:0;border-radius:6px;background:linear-gradient(270deg,#FEDA75,#FA7E1E 25%,#D62976 55%,#962FBF 80%,#4F5BD5);
  transform-origin:right center;transform:scaleX(0);will-change:transform;box-shadow:0 0 14px rgba(255,95,162,.8)}
@keyframes spb{from{transform:scaleX(0)}to{transform:scaleX(1)}}
.dts{display:flex;justify-content:center;gap:7px;margin-top:13px}
.dts i{width:8px;height:8px;border-radius:50%;background:#FF5FA2;box-shadow:0 0 10px rgba(255,95,162,.8);animation:dj 1s ease-in-out infinite}
.dts i:nth-child(2){animation-delay:.15s;background:#C084FC;box-shadow:0 0 10px rgba(192,132,252,.8)}
.dts i:nth-child(3){animation-delay:.3s;background:#FEDA75;box-shadow:0 0 10px rgba(254,218,117,.8)}
@keyframes dj{0%,100%{transform:translate3d(0,0,0);opacity:.5}50%{transform:translate3d(0,-6px,0);opacity:1}}
.spl .stg{display:flex;justify-content:center;gap:6px;margin-top:12px}
.spl .stg i{width:22px;height:4px;border-radius:4px;background:rgba(255,255,255,.14);transition:background .3s,box-shadow .3s,width .3s}
.spl .stg i.on{width:30px;background:#FF5FA2;box-shadow:0 0 8px rgba(255,95,162,.85)}
.spl .ld small{display:block;margin-top:10px;min-height:18px;color:var(--dim);font-size:10.5px;font-weight:700;transition:opacity .18s}
.spl h1{font-size:28px!important;font-weight:900;letter-spacing:-.5px;filter:drop-shadow(0 6px 22px rgba(221,42,123,.55))}
.spl p{font-size:13px;font-weight:800;color:#FFE1EF;opacity:.92;line-height:1.8}
.spl .pc span{font-size:12.5px;font-weight:900}
@font-face{font-family:'NbxNum';font-style:normal;font-weight:600;font-display:swap;src:url('assets/fonts/NbxNum.woff2') format('woff2')}
.spl .pc b{font-family:'NbxNum',system-ui,sans-serif;font-size:13px;font-weight:600;letter-spacing:.4px;min-width:0;font-variant-numeric:tabular-nums;filter:none}
.spl .ld small{font-size:11.5px;font-weight:800;color:#F5CFE0}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
html.lite *,html.lite *:before,html.lite *:after{animation:none!important;-webkit-animation:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;background-attachment:scroll!important}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.5a4 4 0 0 0-3-3.8M15.5 4.2a3.5 3.5 0 0 1 0 6.6"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2.8 2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3L2.9 9.5l6.3-.9z"/></symbol>
    <symbol id="i-heartf" viewBox="0 0 24 24" fill="currentColor"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="m10 8.5 5.5 3.5-5.5 3.5z"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/></symbol>
    <symbol id="i-ring" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="8.5" stroke-dasharray="3.5 2.4"/><circle cx="12" cy="12" r="4"/></symbol>
    <symbol id="i-bookmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M6 3.5h12v17l-6-4.2-6 4.2z"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-explore" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></symbol>
    <symbol id="i-receipt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 2.5h14v19l-2.3-1.6-2.4 1.6-2.3-1.6-2.3 1.6-2.4-1.6L5 21.5z"/><path d="M9 8h6M9 12h6M9 16h3"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m7.5 12.3 3 3 6-6.3"/></symbol>
    <symbol id="i-verified" viewBox="0 0 24 24" fill="currentColor"><path d="m12 1.8 2.6 2 3.3-.2.9 3.2 2.8 1.8-1.2 3.1 1.2 3.1-2.8 1.8-.9 3.2-3.3-.2-2.6 2-2.6-2-3.3.2-.9-3.2-2.8-1.8 1.2-3.1-1.2-3.1 2.8-1.8.9-3.2 3.3.2z"/><path d="m8 12.2 2.7 2.7L16.2 9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.3"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.6-4.5L3 9M3 4v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15M21 20v-5h-5"/></symbol>
    <symbol id="i-headset" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.6"/><rect x="17" y="13" width="4" height="6" rx="1.6"/><path d="M19 19a3 3 0 0 1-3 3h-3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 2.5 10 14M21.5 2.5l-7 19-4.5-7.5-7.5-4.5z"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor"/></symbol>
    <symbol id="i-plane" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 3.5 2.8 10.6c-.9.3-.9 1.5 0 1.8l4.7 1.6 1.8 5.6c.3.8 1.3 1 1.8.4l2.7-2.8 4.6 3.4c.7.5 1.6.1 1.8-.7L23 4.7c.2-.9-.7-1.6-1.5-1.2z"/></symbol>
    <symbol id="i-sim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 2.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 6 20V4a1.5 1.5 0 0 1 1-1.5z"/><rect x="9" y="11" width="6" height="6" rx="1"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.8 4.5 5.8v5.6c0 4.6 3.1 8.3 7.5 9.8 4.4-1.5 7.5-5.2 7.5-9.8V5.8z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/></symbol>
    <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-gauge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 17a8.5 8.5 0 1 1 17 0"/><path d="m12 17 4.2-5.6"/><circle cx="12" cy="17" r="1.3" fill="currentColor"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="8" y="8" width="12.5" height="12.5" rx="2.5"/><path d="M16 8V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7.5A2.5 2.5 0 0 0 6 16h2"/></symbol>
  </defs>
</svg>
<div class="mesh" aria-hidden="true"><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><b class="ht"><svg><use href="#i-heartf"/></svg></b><s class="sp"></s><s class="sp"></s><s class="sp"></s><s class="sp"></s><s class="sp"></s></div>

<div class="spl" id="spl">
  <div class="spc">
    <div class="lgw"><i class="gl"></i>
      <div class="rw"><svg viewBox="0 0 120 120"><defs><linearGradient id="spg" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".3" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset=".85" stop-color="#962FBF"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs>
        <circle cx="60" cy="60" r="56" pathLength="100" stroke="url(#spg)"/></svg></div>
      <div class="lg"><svg><use href="#i-camera"/></svg></div>
      <i class="hb h1"><svg><use href="#i-heartf"/></svg></i><i class="hb h2"><svg><use href="#i-heartf"/></svg></i><i class="hb h3"><svg><use href="#i-heartf"/></svg></i><i class="hb h4"><svg><use href="#i-heartf"/></svg></i>
    </div>
    <h1>__TITLE__</h1>
    <p>__TAG__</p>
  </div>
  <div class="ld"><div class="pc"><span id="spMsg">در حال اتصالِ امن…</span><b id="spPct">0%</b></div>
    <div class="br"><i id="spBar"></i></div><div class="stg" id="spStg"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="dts"><i></i><i></i><i></i></div></div>
</div>

<div class="app">
  <div class="hd0">
    <header class="hdr gb">
      <div class="ava"><span id="ava"></span></div>
      <div class="who"><b id="uName">—</b><small><i class="dot"></i><span>آنلاین · سفارشِ آنی</span></small></div>
      <div class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em></div>
    </header>
  </div>

  <section class="pg on" id="pg-home">
    <div class="wmk"><b id="wm">__TITLE__</b><span><i class="dot"></i>فعال</span></div>
    <div class="stories" id="stH"></div>
    <article class="post gb">
      <div class="u"><div class="a"><div><svg><use href="#i-camera"/></svg></div></div>
        <div><b><span id="pName">نامبیکس</span><svg><use href="#i-verified"/></svg></b><small>سفارشِ آنی · بدونِ نیاز به رمز</small></div></div>
      <div class="img"><i class="sw"></i><div class="big"><span><svg><use href="#i-heart"/></svg></span><span><svg><use href="#i-users"/></svg></span><span><svg><use href="#i-play"/></svg></span></div>
        <svg class="pop"><use href="#i-heartf"/></svg><span class="tag">پیج‌های عمومی</span></div>
      <div class="act"><svg class="lk"><use href="#i-heartf"/></svg><svg><use href="#i-chat"/></svg><svg><use href="#i-send"/></svg><svg class="sv"><use href="#i-bookmark"/></svg></div>
      <div class="cap"><b id="hTitle">__TITLE__</b><p id="hTag">__TAG__</p>
        <button class="cta" data-go="list"><svg><use href="#i-explore"/></svg>شروعِ سفارش</button></div>
    </article>
    <div class="stat3"><div><b id="kN">—</b><small>سرویسِ فعال</small></div><div><b id="kF">—</b><small>شروع از / ۱۰۰۰</small></div><div><b>۲۴/۷</b><small>ثبتِ خودکار</small></div></div>
    <div class="hd" id="popH"><h3><i></i>محبوب‌ترین‌ها</h3><button data-go="list">همه</button></div>
    <div class="grid" id="pop"></div>
    <div class="links" id="xl"></div>
  </section>

  <section class="pg" id="pg-list">
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجو: فالوور، لایک…" autocomplete="off"></div>
    <div class="stories" id="stL"></div>
    <div class="list" id="slist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="hd" style="margin-top:4px"><h3><i></i>سفارش‌های من</h3><button id="oRef">تازه کن</button></div>
    <div id="olist"></div>
  </section>

</div>

<nav class="nav gb" id="nav"><div>
  <span class="ind" id="navInd"></span>
  <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
  <button data-go="list"><svg><use href="#i-explore"/></svg>سرویس‌ها</button>
  <button data-go="orders"><svg><use href="#i-receipt"/></svg>سفارش‌ها<span class="bd" id="ordN"></span></button>
</div></nav>

<div class="ov" id="ov"></div>
<div class="sh" id="sh"><div class="grab"></div><div id="shB"></div></div>
<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var D = document, H = D.documentElement;
var $ = function(id){ return D.getElementById(id); };
var RM = false; try { RM = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); } catch(e){}
var HASH_INIT = (function(){ try { var m = /(?:^|&)tgWebAppData=([^&]*)/.exec(String(location.hash || '').replace(/^#/, '')); return m ? decodeURIComponent(m[1]) : ''; } catch(e){ return ''; } })();
function initData(){ try { if (TG && TG.initData) return TG.initData; } catch(e){} return HASH_INIT; }
function tgUser(){
  try { if (TG && TG.initDataUnsafe && TG.initDataUnsafe.user) return TG.initDataUnsafe.user; } catch(e){}
  try { var m = /(?:^|&)user=([^&]*)/.exec(HASH_INIT); var u = m ? JSON.parse(decodeURIComponent(m[1])) : null; return (u && u.id) ? u : null; } catch(e){ return null; }
}
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function faD(s){ return String(s == null ? '' : s).replace(/\d/g, function(d){ return String.fromCharCode(1776 + +d); }); }
var NF = null; try { NF = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }); } catch(e){}
function fa(n){ n = Number(n) || 0; return NF ? NF.format(n) : faD(Math.round(n)); }
function digits(s){ s = String(s == null ? '' : s); var o = ''; for (var i = 0; i < s.length; i++) { var c = s.charCodeAt(i);
  if (c >= 1776 && c <= 1785) o += (c - 1776); else if (c >= 1632 && c <= 1641) o += (c - 1632); else if (c >= 48 && c <= 57) o += s[i]; } return o; }
function ico(n, cls){ return '<svg' + (cls ? ' class="' + cls + '"' : '') + '><use href="#i-' + n + '"/></svg>'; }
function tap(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k || 'light'); } catch(e){} }
function buzz(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); } catch(e){} }
var TT;
function toast(msg, good){
  var t = $('toast');
  t.className = 'toast ' + (good ? 'ok' : 'er');
  t.innerHTML = ico(good ? 'check' : 'alert') + '<span>' + esc(msg) + '</span>';
  void t.offsetWidth; t.classList.add('on');
  clearTimeout(TT); TT = setTimeout(function(){ t.classList.remove('on'); }, 3600);
  buzz(good ? 'success' : 'error');
}
function copy(txt, what){
  var done = function(){ toast((what || 'متن') + ' کپی شد.', true); };
  function fb(){
    try { var t = D.createElement('textarea'); t.value = txt; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
          D.body.appendChild(t); t.select(); D.execCommand('copy'); D.body.removeChild(t); done(); }
    catch(e){ toast('کپی نشد'); }
  }
  try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, fb); return; } } catch(e){}
  fb();
}
function countUp(el, to){
  to = Number(to) || 0;
  if (!to || RM || !window.requestAnimationFrame) { el.textContent = fa(to); return; }
  var t0 = 0;
  function st(ts){ if (!t0) t0 = ts; var k = Math.min(1, (ts - t0) / 900); k = 1 - Math.pow(1 - k, 3);
    el.textContent = fa(Math.round(to * k)); if (k < 1) requestAnimationFrame(st); }
  requestAnimationFrame(st);
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
  try { if (TG && /^https:\/\/t\.me\//i.test(url) && TG.openTelegramLink) { TG.openTelegramLink(url); return; }
        if (TG && TG.openLink) { TG.openLink(url); return; } } catch(e){}
  window.open(url, '_blank', 'noopener');
}
function openApp(url){
  if (!url) return;
  var d = initData(), h = '';
  if (d) h = '#tgWebAppData=' + encodeURIComponent(d) + '&tgWebAppVersion=' + encodeURIComponent((TG && TG.version) || '7.0') +
             '&tgWebAppPlatform=' + encodeURIComponent((TG && TG.platform) || 'unknown');
  location.href = url + h;
}

var SPL = { t0: (function(){ try { var o = performance.timeOrigin || performance.timing.navigationStart; if (o > 0 && Date.now() - o < 15000) return o; } catch(e){} return Date.now(); })(),
            gone: false, min: Math.max(0, Math.min(20, Number(B.spl) || 0)) * 1000 };
var SPM = ['در حال اتصالِ امن…', 'دریافتِ سرویس‌های اینستاگرام…', 'به‌روزرسانیِ قیمت‌ها…', 'بررسیِ کیف پول…', 'چیدنِ استوری‌ها…', 'آماده است'];
function splStep(){
  if (SPL.gone) return false;
  var el = Date.now() - SPL.t0, p = SPL.min ? Math.min(1, el / SPL.min) : 1;
  var pc = $('spPct'), bar = $('spBar'), m = $('spMsg'), g = $('spStg');
  if (pc) pc.textContent = Math.floor(p * 100) + '%';
  if (bar && (RM || !SPL.anim)) bar.style.transform = 'scaleX(' + p.toFixed(4) + ')';
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
    splStep(); SPL.gone = true; clearInterval(SPL.iv);
    H.classList.remove('spl-on');
    var sp = $('spl'); if (!sp) return;
    setTimeout(function(){ sp.classList.add('out');
      setTimeout(function(){ if (sp.parentNode) sp.parentNode.removeChild(sp); }, 700); }, 90); }, wait);
}
setTimeout(function(){ hideSplash(false); }, Math.max(3000, SPL.min + 2500 - (Date.now() - SPL.t0)));
var API = (function(){ try { if (/^https?:$/.test(location.protocol)) return location.origin + location.pathname + '?mapi=1'; } catch(e){} return ''; })();
var READS = { me: 1, sv_orders: 1, sv_order: 1 };
var GATED = false;
function api(action, extra, ok, bad, tried){
  bad = bad || function(j){ toast((j && j.message) || 'خطا — دوباره امتحان کنید.'); };
  if (!API) { bad({ message: 'آدرس سرور تنظیم نشده است.' }); return; }
  var t0 = Date.now(), got = false, body = { action: action, initData: initData() };
  for (var k in (extra || {})) body[k] = extra[k];
  var ctl = null, tm = null;
  try { ctl = new AbortController(); tm = setTimeout(function(){ ctl.abort(); }, 30000); } catch(e){}
  fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
               signal: ctl ? ctl.signal : undefined, cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
    .then(function(r){ got = true; return r.json().catch(function(){ return { ok: false, message: 'پاسخ سرور نامعتبر بود.' }; }); })
    .then(function(j){ if (tm) clearTimeout(tm);
      if (j && j.ok) { ok(j); return; }
      if (j && j.error === 'unauthorized') { gate(j.message); return; }
      bad(j || {}); })
    .catch(function(){ if (tm) clearTimeout(tm);
      if (!got && !tried && READS[action] && Date.now() - t0 < 8000) { setTimeout(function(){ api(action, extra, ok, bad, 1); }, 700); return; }
      bad({ message: 'ارتباط با سرور برقرار نشد.' }); });
}
function gate(msg){
  if (GATED) return; GATED = true;
  hideSplash(true);
  var g = D.createElement('div'); g.className = 'gate';
  g.innerHTML = ico('camera') + '<b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p>' +
    (B.bot ? '<button class="cta" style="max-width:260px">رفتن به ربات</button>' : '');
  D.body.appendChild(g);
  var b = g.querySelector('button'); if (b) b.onclick = function(){ openLink('https://t.me/' + B.bot); };
}

var CATS = B.cats || [], ITEMS = B.items || [], CAT = {}, ITEM = {};
CATS.forEach(function(c, k){ CAT[c.id] = c; c.tone = 'c' + ((k % 4) + 1); });
ITEMS.forEach(function(i){ ITEM[i.i] = i; i.k = String(i.n || '').toLowerCase(); });
var HINT = { followers: ['آیدی یا لینکِ پیج', '@mypage'], likes: ['لینکِ پست', 'instagram.com/p/XXXX'],
  views: ['لینکِ ریلز یا ویدیو', 'instagram.com/reel/XXXX'], comments: ['لینکِ پست', 'instagram.com/p/XXXX'],
  story: ['آیدیِ پیج', '@mypage'], saves: ['لینکِ پست', 'instagram.com/p/XXXX'], reach: ['لینکِ پست یا آیدیِ پیج', 'instagram.com/p/XXXX'],
  live: ['آیدیِ پیج — وقتی لایو روشن است', '@mypage'], channel: ['لینکِ کانال', 'ig.me/j/XXXX'], other: ['لینک یا آیدی', '@mypage'] };
var S = { lim: 60, page: '', stack: [], bal: 0, cat: '', q: '', orders: null, cur: null, sheet: false, poll: null, ava: '', cp: null };
var U = tgUser() || {};

function setBal(v){ if (v == null || isNaN(Number(v))) return; S.bal = Number(v); $('bal').textContent = fa(S.bal); }
function drawSelf(avatar){
  var n = ((U.first_name || '') + ' ' + (U.last_name || '')).trim() || (U.username ? '@' + U.username : 'کاربر');
  $('uName').textContent = n;
  var box = $('ava'), im;
  if (avatar) S.ava = avatar; else avatar = S.ava;
  box.textContent = n.charAt(0).toUpperCase();
  box._im = null;
  if (!avatar) return;
  im = new Image(); im.alt = ''; box._im = im;
  im.onload = function(){ if (box._im === im) { box.textContent = ''; box.appendChild(im); } };
  im.src = avatar;
}

var PAGES = ['home', 'list', 'orders'];
function go(p, back){
  if (PAGES.indexOf(p) < 0) p = 'home';
  if (p === S.page) { window.scrollTo(0, 0); return; }
  if (!back && S.page) S.stack.push(S.page);
  S.page = p;
  PAGES.forEach(function(x){ $('pg-' + x).classList.toggle('on', x === p); });
  [].forEach.call(D.querySelectorAll('#nav button'), function(b){ b.classList.toggle('on', b.getAttribute('data-go') === p); });
  $('navInd').style.transform = 'translate3d(' + (-PAGES.indexOf(p) * 100) + '%,0,0)';
  window.scrollTo(0, 0);
  clearTimeout(S.poll);
  if (p === 'list') drawList();
  if (p === 'orders') loadOrders();
  backBtn();
}
function goBack(){ if (S.sheet) { closeSheet(); return; } var p = S.stack.pop(); go(p || 'home', true); }
function backBtn(){
  if (!TG || !TG.BackButton) return;
  try { if (S.sheet || S.page !== 'home') TG.BackButton.show(); else TG.BackButton.hide(); } catch(e){}
}
D.addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('[data-go]') : null;
  if (!el) return;
  ev.preventDefault(); tap();
  if (S.sheet) closeSheet();
  var cat = el.getAttribute('data-cat');
  if (cat != null) S.cat = cat;
  go(el.getAttribute('data-go'));
  if (cat != null) drawList(true);
});

function stories(active){
  return '<button class="story' + (active === '' ? ' on' : '') + '" style="--i:0" data-go="list" data-cat=""><div class="rg"><div class="in"><div>' + ico('explore') + '</div></div></div><small>همه</small></button>' +
    CATS.map(function(c, k){
      return '<button class="story' + (active === c.id ? ' on' : '') + '" style="--i:' + (k + 1) + '" data-go="list" data-cat="' + esc(c.id) + '"><div class="rg"><div class="in"><div>' + ico(c.ic) + '</div></div></div><small>' + esc(c.n) + '</small></button>';
    }).join('');
}
function nm(n){ n = String(n || ''); var k = n.indexOf(' — '); return { t: k > 0 ? n.slice(0, k) : n, f: k > 0 ? n.slice(k + 3).split(' · ').filter(Boolean) : [] }; }
function ttl(i){ return i.f ? i.n : nm(i.n).t; }
function emN(s){ return String(s || '').replace(/[\uFE0E\uFE0F]/g, '').replace(/\uD83C[\uDFFB-\uDFFF]/g, '').trim(); }
function faS(n){ n = Number(n) || 0; if (n >= 1e6) return faD(String(Math.round(n / 1e5) / 10)).replace('.', '٫') + ' میلیون'; if (n >= 1e4) return fa(Math.round(n / 1000)) + ' هزار'; return fa(n); }
var FT = [[/^بدونِ ضمانت/, 'shield', 'no'], [/ضمانت|ریزش/, 'shield', 'ok'], [/^شروع/, 'bolt', 'hot'], [/^سقف/, 'chart', ''],
          [/^سرعت/, 'gauge', ''], [/پست/, 'receipt', ''], [/(روزه|دقیقه)$/, 'clock', '']];
function feats(i, max){
  var f = (i.f ? i.f.slice() : nm(i.n).f), out = [];
  if (i.r && !f.some(function(x){ return /ضمانت/.test(x); })) f = ['ضمانت‌دار'].concat(f);
  f.slice(0, max || 9).forEach(function(x){
    var m = null; for (var k = 0; k < FT.length; k++) if (FT[k][0].test(x)) { m = FT[k]; break; }
    var em = !m && !/[؀-ۿ]/.test(x);
    out.push('<span class="fc' + (m && m[2] ? ' ' + m[2] : '') + (em ? ' em' : '') + '">' + (m ? ico(m[1]) : '') + esc(x) + '</span>');
  });
  return out.join('');
}
function tile(i, k){
  var c = CAT[i.c] || {}, f = feats(i, 1);
  return '<button class="tile ' + (c.tone || 'c1') + '" style="--i:' + Math.min(k || 0, 12) + '" data-sv="' + esc(i.i) + '">' +
    '<span class="ic">' + ico(c.ic || 'spark') + '</span><b dir="auto">' + esc(ttl(i)) + '</b>' + (i.b ? '<span class="tier ' + esc(i.t || '') + '" style="align-self:flex-start;margin-top:4px">' + esc(i.b) + '</span>' : '') + '<small><i></i>' + faS(i.mn) + ' تا ' + faS(i.mx) + '</small>' +
    (f ? '<span class="fcs">' + f + '</span>' : '') +
    '<span class="ft"><span><b>' + fa(i.p) + '</b> تومان<br>هر ۱۰۰۰ تا</span><i>' + ico('plus') + '</i></span></button>';
}
function pcard(i, k, w){
  var c = CAT[i.c] || {}, t = String(i.t || ''), f = feats(i, w ? 6 : 2);
  return '<button class="cd' + (t ? ' t-' + esc(t) : '') + (w ? ' w' : '') + '" style="--i:' + Math.min(k || 0, 14) + '" data-sv="' + esc(i.i) + '">' +
    '<span class="tp"><span class="rb">' + (t === 'high' ? ico('star') : '') + esc(i.b || c.n || 'فعال') + '</span><span class="ic">' + ico(c.ic || 'spark') + '</span></span>' +
    '<b class="nm" dir="auto">' + esc(ttl(i)) + '</b><small class="mt"><i class="lv"></i>' + faS(i.mn) + ' تا ' + faS(i.mx) + '</small>' + (f ? '<span class="fcs">' + f + '</span>' : '') +
    '<span class="ft"><span class="pz"><b>' + fa(i.p) + '</b><i>تومان</i><small>' + (i.y === 'emoji' ? 'شروع از · ' : '') + 'هر ۱۰۰۰ تا</small></span>' +
    '<span class="ordb">سفارش' + ico('chev') + '</span></span></button>';
}
function pgrid(g, k0){
  var w = g.map(function(i){ return i.y === 'emoji'; }), col = 0;
  for (var j = 0; j < g.length; j++) {
    if (w[j]) { if (col === 1) w[j - 1] = true; col = 0; }
    else col ^= 1;
  }
  if (col === 1) w[g.length - 1] = true;
  return '<div class="pgr">' + g.map(function(i, j){ return pcard(i, k0 + j, w[j]); }).join('') + '</div>';
}
function drawHome(){
  $('stH').innerHTML = stories(null);
  countUp($('kN'), ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  if (min) countUp($('kF'), min); else $('kF').textContent = '—';
  if (B.bot) $('pName').textContent = '@' + B.bot;
  var pop = [];
  CATS.forEach(function(c){ var f = ITEMS.filter(function(i){ return i.c === c.id; }).sort(function(a, b){ return a.p - b.p; })[0]; if (f) pop.push(f); });
  ITEMS.slice().sort(function(a, b){ return a.p - b.p; }).forEach(function(i){ if (pop.length < 6 && pop.indexOf(i) < 0) pop.push(i); });
  $('pop').innerHTML = pop.length ? pop.slice(0, 6).map(tile).join('') :
    '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';
  var xl = '';
  if ((B.links || {}).tg) xl += '<button class="xl tg" data-open="tg"><span class="ic">' + ico('plane') + '</span><span>خدمات تلگرام<small>ممبر، بازدید، ری‌اکشن و بوست</small></span>' + ico('chev', 'ch') + '</button>';
  if ((B.links || {}).num) xl += '<button class="xl num" data-open="num"><span class="ic">' + ico('sim') + '</span><span>شماره مجازی تلگرام<small>تحویلِ آنی، کد همین‌جا</small></span>' + ico('chev', 'ch') + '</button>';
  $('xl').innerHTML = xl;
}
$('xl').addEventListener('click', function(ev){ var b = ev.target.closest('[data-open]'); if (b) { tap(); openApp(B.links[b.getAttribute('data-open')]); } });

function listHtml(l){
  var more = l.length > S.lim ? l.length - S.lim : 0;
  l = l.slice(0, S.lim);
  var out = '', k = 0;
  if (S.q.trim()) out = pgrid(l, 0);
  else {
    CATS.forEach(function(c){
      var g = l.filter(function(i){ return i.c === c.id; }); if (!g.length) return;
      out += '<div class="sech"><span class="ic">' + ico(c.ic) + '</span><span><b>' + esc(c.n) + '</b><small>' + fa(g.length) + ' محصول · از ' + fa(c.f) + ' تومان</small></span></div>' + pgrid(g, k);
      k += g.length;
    });
    var r = l.filter(function(i){ return !CAT[i.c]; }); if (r.length) out += pgrid(r, k);
  }
  if (more) out += '<button class="more" id="lMore">نمایشِ ' + fa(Math.min(more, 60)) + ' محصولِ دیگر</button>';
  return out;
}
D.addEventListener('click', function(ev){ var b = ev.target.closest ? ev.target.closest('#lMore') : null; if (!b) return; tap(); S.lim += 60; drawList(true); });
var LK = '';
function drawList(force){
  var key = S.cat + '|' + S.q;
  if (!force && key === LK && $('slist').children.length) return;
  if (key !== LK) S.lim = 60;
  LK = key;
  $('stL').innerHTML = stories(S.cat);
  var q = S.q.trim().toLowerCase();
  var l = ITEMS.filter(function(i){ return (!S.cat || i.c === S.cat) && (!q || i.k.indexOf(q) >= 0); });
  $('slist').innerHTML = l.length ? listHtml(l) : (ITEMS.length
    ? '<div class="emp" style="grid-column:1/-1">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>'
    : '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>');
}
var QT;
$('q').addEventListener('input', function(){ var v = this.value; clearTimeout(QT); QT = setTimeout(function(){ S.q = v; drawList(true); }, 140); });
D.addEventListener('click', function(ev){ var b = ev.target.closest ? ev.target.closest('[data-sv]') : null; if (b) { tap(); openOrder(b.getAttribute('data-sv')); } });

function total(i, q){ return Math.max(1, Math.ceil(i.p * q / 1000 - 1e-9)); }
function cpDisc(t){
  var c = S.cp;
  if (!c || t <= 0 || t < (c.min || 0)) return 0;
  var d = c.kind === 'fixed' ? c.value : Math.round(t * c.value / 100);
  if (c.max > 0) d = Math.min(d, c.max);
  return Math.max(0, Math.min(d, t));
}
function niceQty(i){ var c = [1000, 500, 100, 5000, 10000]; for (var k = 0; k < c.length; k++) if (c[k] >= i.mn && c[k] <= i.mx) return c[k]; return i.mn; }
function linkOk(v){ v = v.trim(); return /^@?[A-Za-z0-9._]{1,30}$/.test(v) || /^(https?:\/\/)?(www\.|m\.)?(instagram\.com|instagr\.am)\/\S+$/i.test(v) || /^(https?:\/\/)?(www\.)?ig\.me\/\S+$/i.test(v); }
function openSheet(){ S.sheet = true; H.classList.add('shon'); $('ov').classList.add('on'); $('sh').classList.add('on'); $('sh').scrollTop = 0; backBtn(); }
function closeSheet(){ S.sheet = false; H.classList.remove('shon'); $('ov').classList.remove('on'); $('sh').classList.remove('on'); backBtn(); }
$('ov').onclick = closeSheet;
function openOrder(id){
  var i0 = ITEM[id]; if (!i0) return;
  var i = {}; for (var kk in i0) i[kk] = i0[kk];
  var c = CAT[i.c] || {}, h = HINT[i.c] || HINT.other;
  S.cur = i;
  var chips = [i.mn, 1000, 5000, 10000, 50000, i.mx].filter(function(v, k, a){ return v >= i.mn && v <= i.mx && a.indexOf(v) === k; })
    .sort(function(a, b){ return a - b; }).slice(0, 5);
  $('shB').innerHTML =
    '<div class="st"><span class="ic">' + ico(c.ic || 'spark') + '</span><div style="flex:1;min-width:0"><b dir="auto">' + esc(ttl(i)) + (i.b ? ' <span class="tier ' + esc(i.t || '') + '">' + esc(i.b) + '</span>' : '') + '</b><small id="oHd">' +
      fa(i.p) + ' تومان برای هر ۱۰۰۰ تا · حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx) + '</small></div><button class="x" id="shX">' + ico('x') + '</button></div>' +
    (feats(i) ? '<div class="fcs" style="margin-top:10px">' + feats(i) + '</div>' : '') +
    '<div class="fld"><label>' + esc(h[0]) + '</label><input id="oLink" class="ltr" placeholder="' + esc(h[1]) + '" autocomplete="off" autocapitalize="off" spellcheck="false"><small id="oLinkH">پیج باید عمومی (Public) باشد</small></div>' +
    (i.y === 'poll' ? '<div class="fld"><label>رای به کدام گزینه برود؟</label><div class="qchips" id="aC">' +
      [1, 2, 3, 4].map(function(v){ return '<button data-a="' + v + '">گزینه‌ی ' + fa(v) + '</button>'; }).join('') +
      '</div><small>از بالا بشمارید — گزینه‌ی اول = ۱</small></div>' : '') +
    (i.y === 'emoji' ? '<div class="fld"><label>ایموجیِ ری‌اکشن</label><div class="emrow"><input id="oEmo" class="emi" maxlength="12" placeholder="👍" autocomplete="off"><div class="qchips ems" id="eC">' +
      (i.em || []).map(function(x){ return '<button data-e="' + esc(x.k) + '">' + esc(x.e) + '</button>'; }).join('') +
      '</div></div><small id="oEmoH"></small></div>' : '') +
    (i.y === 'cc' ? '<div class="fld"><label>متنِ کامنت‌ها — هر خط یک کامنت</label><textarea id="oCm" rows="5" placeholder="عالی بود&#10;چه پستِ خوبی!"></textarea>' +
      '<small id="oCmH">حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx) + ' کامنت</small></div>' : '') +
    '<div class="fld"' + (i.y === 'cc' ? ' style="display:none"' : '') + '><label>تعداد</label><div class="qrow"><button id="qM" aria-label="کم">−</button><input id="oQty" inputmode="numeric"><button id="qP" aria-label="زیاد">+</button></div>' +
    '<div class="qchips" id="qC">' + chips.map(function(v){ return '<button data-q="' + v + '">' + fa(v) + '</button>'; }).join('') + '</div></div>' +
    '<div class="sum"><div><span>قیمتِ هر ۱۰۰۰ تا</span><b id="oP1">' + fa(i.p) + ' تومان</b></div><div><span>موجودیِ شما</span><b id="oBal">' + fa(S.bal) + ' تومان</b></div>' +
    '<div class="cp" id="oCpR" style="display:none"><span id="oCpL">تخفیف</span><b id="oCp"></b></div>' +
    '<div class="t"><span>مبلغِ کل</span><b id="oTot">—</b></div></div>' +
    '<button class="cta" id="oGo">' + ico('send') + '<span id="oGoT">پرداخت و ثبتِ سفارش</span></button>';
  var qi = $('oQty'); qi.value = fa(niceQty(i));
  function ccLines(){ var e = $('oCm'); return e ? e.value.split(/\r?\n/).map(function(x){ return x.trim(); }).filter(Boolean) : []; }
  function q(){ if (i.y === 'cc') return ccLines().length; return parseInt(digits(qi.value), 10) || 0; }
  var pay = 0;
  function upd(){
    var n = q(), ok = n >= i.mn && n <= i.mx, t = ok ? total(i, n) : 0, d = ok ? cpDisc(t) : 0;
    pay = t - d;
    $('oCpR').style.display = d > 0 ? '' : 'none';
    if (d > 0) { $('oCpL').textContent = 'تخفیف (' + S.cp.code + ')'; $('oCp').textContent = '−' + fa(d) + ' تومان'; }
    $('oTot').textContent = ok ? fa(pay) + ' تومان' : 'تعداد بین ' + fa(i.mn) + ' تا ' + fa(i.mx);
    [].forEach.call($('qC').children, function(b){ b.classList.toggle('on', +b.getAttribute('data-q') === n); });
    var low = ok && pay > S.bal;
    $('oBal').parentNode.classList.toggle('lo', low);
    $('oGoT').textContent = low ? 'شارژ در ربات (' + fa(pay - S.bal) + ' تومان کم است)' : 'پرداخت و ثبتِ سفارش';
    return t;
  }
  function setQ(n){ n = Math.max(i.mn, Math.min(i.mx, n)); qi.value = fa(n); upd(); }
  var step = function(){ var n = q(); return n >= 10000 ? 1000 : n >= 1000 ? 100 : n >= 100 ? 10 : 1; };
  $('qM').onclick = function(){ tap(); setQ(q() - step()); };
  $('qP').onclick = function(){ tap(); setQ(q() + step()); };
  $('qC').onclick = function(ev){ var b = ev.target.closest('[data-q]'); if (b) { tap(); setQ(+b.getAttribute('data-q')); } };
  qi.oninput = function(){ var n = q(); qi.value = n ? fa(n) : ''; upd(); };
  var ans = 0, emo = null;
  if ($('oCm')) $('oCm').oninput = function(){ $('oCmH').textContent = fa(ccLines().length) + ' کامنت · حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx); upd(); };
  function setEmo(v){
    var k = emN(v), hit = null, best = -1;
    (i.em || []).forEach(function(x){ if (k && k === x.k) { hit = x; best = 1e9; } });
    if (!hit && k) (i.em || []).forEach(function(x){ if (k.indexOf(x.k) >= 0 && x.k.length > best) { hit = x; best = x.k.length; } });
    emo = hit;
    [].forEach.call($('eC').children, function(b){ b.classList.toggle('on', !!hit && b.getAttribute('data-e') === hit.k); });
    var hs = $('oEmoH');
    if (hit) { i.p = hit.p; i.mn = hit.mn; i.mx = hit.mx; hs.textContent = 'ری‌اکشنِ ' + hit.e + ' — ' + fa(hit.p) + ' تومان برای هر ۱۰۰۰ تا'; hs.className = 'okk'; }
    else { hs.textContent = k ? 'این ایموجی در فهرست نیست' : ''; hs.className = k ? 'er' : ''; }
    $('oHd').textContent = fa(i.p) + ' تومان برای هر ۱۰۰۰ تا · حداقل ' + fa(i.mn) + ' · حداکثر ' + fa(i.mx);
    $('oP1').textContent = fa(i.p) + ' تومان';
    var n = q(); if (hit && (n < i.mn || n > i.mx)) setQ(niceQty(i)); else upd();
  }
  if ($('oEmo')) {
    $('oEmo').oninput = function(){ setEmo(this.value); };
    $('eC').onclick = function(ev){ var b = ev.target.closest('[data-e]'); if (!b) return; tap();
      var x = (i.em || []).filter(function(y){ return y.k === b.getAttribute('data-e'); })[0]; $('oEmo').value = x ? x.e : ''; $('oEmo').blur(); setEmo($('oEmo').value); };
  }
  if ($('aC')) $('aC').onclick = function(ev){ var b = ev.target.closest('[data-a]'); if (!b) return; tap(); ans = +b.getAttribute('data-a');
    [].forEach.call(this.children, function(x){ x.classList.toggle('on', x === b); }); };
  $('oLink').oninput = function(){ var v = this.value.trim(); var hs = $('oLinkH');
    if (v && !linkOk(v)) { hs.textContent = 'لینکِ نامعتبر'; hs.className = 'er'; }
    else { hs.textContent = 'پیج باید عمومی (Public) باشد'; hs.className = ''; } };
  $('shX').onclick = function(){ tap(); closeSheet(); };
  $('oGo').onclick = function(){
    var n = q(), t = upd(), link = $('oLink').value.trim();
    if (n < i.mn || n > i.mx) { toast((i.y === 'cc' ? 'تعدادِ کامنت‌ها' : 'تعداد') + ' باید بین ' + fa(i.mn) + ' و ' + fa(i.mx) + ' باشد.'); return; }
    if (pay > S.bal) { topupBot(pay - S.bal); return; }
    if (!linkOk(link)) { toast('لینکِ نامعتبر'); $('oLink').focus(); return; }
    if (i.y === 'poll' && !ans) { toast('گزینه انتخاب نشده'); return; }
    if (i.y === 'emoji' && !emo) { toast('ایموجیِ ری‌اکشن انتخاب نشده'); if ($('oEmo')) $('oEmo').focus(); return; }
    var b = $('oGo'); b.disabled = true; $('oGoT').textContent = 'در حال ثبت…';
    api('sv_buy', { app: B.app, sid: i.i, link: link, qty: n, seen: t, ans: ans, emoji: emo ? emo.k : '', comments: i.y === 'cc' ? ccLines().join('\n') : '' }, function(j){
      b.disabled = false; setBal(j.balance); if ('coupon' in j) S.cp = j.coupon; closeSheet(); S.orders = null; buzz('success');
      toast(j.warn || 'سفارش ثبت شد و به‌زودی شروع می‌شود.', true);
      go('orders');
    }, function(j){
      b.disabled = false;
      if (j && 'coupon' in j) S.cp = j.coupon;
      upd();
      if (j && j.balance != null) setBal(j.balance);
      if (j && j.error === 'price_changed' && j.p) { i.p = j.p; upd(); }
      if (j && j.error === 'no_balance') { toast((j && j.message) || 'موجودی کافی نیست.'); return; }
      toast((j && j.message) || 'ثبت نشد — دوباره امتحان کنید.');
    });
  };
  upd();
  openSheet();
}

function ago(ts){
  var d = Math.max(0, Math.floor(Date.now() / 1000 - (ts || 0)));
  if (d < 60) return 'همین الان'; if (d < 3600) return fa(Math.floor(d / 60)) + ' دقیقه پیش';
  if (d < 86400) return fa(Math.floor(d / 3600)) + ' ساعت پیش'; return fa(Math.floor(d / 86400)) + ' روز پیش';
}
function loadOrders(){
  if (!S.orders) $('olist').innerHTML = '<div class="sk"></div><div class="sk"></div><div class="sk"></div>';
  else drawOrders();
  api('sv_orders', { app: B.app }, function(j){
    S.orders = j.list || []; setBal(j.balance); drawOrders();
    var run = S.orders.some(function(o){ return o.st === 'run'; });
    clearTimeout(S.poll);
    if (run && S.page === 'orders') S.poll = setTimeout(function(){ if (!D.hidden && S.page === 'orders') loadOrders(); }, 30000);
  }, function(j){ if (!S.orders) $('olist').innerHTML = '<div class="emp">' + ico('alert') + '<b>بارگذاری نشد</b>' + esc((j && j.message) || '') + '</div>'; });
}
function drawOrders(){
  var l = S.orders || [];
  var run = l.filter(function(o){ return o.st === 'run'; }).length;
  $('ordN').textContent = fa(run); $('ordN').classList.toggle('on', run > 0);
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('receipt') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="cta" data-go="list">' + ico('explore') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o, k){
    return '<div class="or ' + esc(o.st) + '" style="--i:' + Math.min(k, 10) + '"><div class="h"><div><b dir="auto">' + esc(nm(o.n).t) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
      '<span class="pill ' + esc(o.st) + '">' + esc(o.sx) + '</span></div>' +
      (o.st === 'run' || o.st === 'done' || o.st === 'partial' ? '<div class="prog' + (o.st === 'run' ? ' run' : '') + '"><i style="width:' + Math.max(o.st === 'run' ? 4 : 0, o.pc) + '%"></i></div>' : '<div style="height:8px"></div>') +
      '<div class="kv"><span>تعداد: <b>' + fa(o.q) + '</b></span>' + (o.sc >= 0 ? '<span>شروع از: <b>' + fa(o.sc) + '</b></span>' : '') +
      (o.rm >= 0 && o.st === 'run' ? '<span>مانده: <b>' + fa(o.rm) + '</b></span>' : '') + (o.rf > 0 ? '<span>برگشتی: <b>' + fa(o.rf) + '</b> تومان</span>' : '') +
      (o.an ? '<span>گزینه: <b>' + fa(o.an) + '</b></span>' : '') +
      '<span class="ltr">#' + esc(o.id) + '</span></div>' +
      '<div class="lnk">' + ico('link') + '<span>' + esc(o.l) + '</span></div>' + rfRow(o) + '</div>';
  }).join('');
}
function rfRow(o){
  if (!o.rb && !o.rs) return '';
  return '<div class="rfr">' + (o.rs ? '<span class="rfs">' + ico('refresh') + esc(o.rs) + '</span>' : '') +
    (o.rb ? '<button class="rfb" data-rf="' + esc(o.id) + '">' + ico('refresh') + 'درخواستِ ریفیل (جبرانِ ریزش)</button>' : '') + '</div>';
}
$('olist').addEventListener('click', function(ev){
  var b = ev.target.closest('[data-rf]'); if (!b || b.disabled) return;
  tap('medium'); b.disabled = true;
  var id = b.getAttribute('data-rf');
  function put(row){ if (!row || !S.orders) return; S.orders = S.orders.map(function(o){ return o.id === row.id ? row : o; }); drawOrders(); }
  api('sv_refill', { app: B.app, id: id }, function(j){ buzz('success'); toast(j.message || 'درخواستِ ریفیل ثبت شد.', true); put(j.row); },
    function(j){ b.disabled = false; toast((j && j.message) || 'ثبت نشد — دوباره امتحان کنید.'); if (j && j.row) put(j.row); });
});
$('oRef').onclick = function(){ tap(); loadOrders(); };


(function(){
  var sh = $('sh'), y0 = null, dy = 0;
  sh.addEventListener('touchstart', function(e){ if (!e.target.closest('.grab, .st') || (sh.scrollTop > 0 && !e.target.closest('.grab'))) { y0 = null; return; } y0 = e.touches[0].clientY; dy = 0; }, { passive: true });
  sh.addEventListener('touchmove', function(e){ if (y0 == null) return; dy = e.touches[0].clientY - y0; if (dy > 0) { sh.style.transition = 'none'; sh.style.transform = 'translate3d(0,' + dy + 'px,0)'; } }, { passive: true });
  sh.addEventListener('touchend', function(){ if (y0 == null) return; sh.style.transition = ''; sh.style.transform = ''; if (dy > 110) closeSheet(); y0 = null; });
})();

function fsSync(){ var on = false; try { on = !!(TG && TG.isFullscreen); } catch(e){} H.classList.toggle('fs', on); }
function tgSetup(){
  if (!TG) return;
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor('#0A0510'); TG.setBackgroundColor && TG.setBackgroundColor('#0A0510'); TG.setBottomBarColor && TG.setBottomBarColor('#0A0510'); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
  try { var pf = String(TG.platform || ''); if (TG.requestFullscreen && /^(ios|android)/.test(pf)) TG.requestFullscreen(); } catch(e){}
  try {
    TG.onEvent('fullscreenChanged', fsSync); TG.onEvent('safeAreaChanged', fsSync); TG.onEvent('contentSafeAreaChanged', fsSync);
    if (TG.BackButton) TG.BackButton.onClick(goBack);
  } catch(e){}
  fsSync(); setTimeout(fsSync, 200); setTimeout(fsSync, 900);
  backBtn();
}
if (TG) tgSetup();
else { var tries = 0, iv = setInterval(function(){
  if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; U = tgUser() || U; tgSetup(); drawSelf(''); }
  else if (++tries > 40) clearInterval(iv); }, 100); }
D.addEventListener('visibilitychange', function(){ if (!D.hidden && S.page === 'orders') loadOrders(); });
var SCT = 0;
window.addEventListener('scroll', function(){ if (!SCT) H.classList.add('scr'); clearTimeout(SCT); SCT = setTimeout(function(){ SCT = 0; H.classList.remove('scr'); }, 160); }, { passive: true });

drawSelf('');
drawHome();
var WANT = (function(){ try { return String(new URLSearchParams(location.search).get('p') || ''); } catch(e){ return ''; } })();
go(WANT === 'orders' || WANT === 'list' ? WANT : 'home');
api('me', {}, function(j){ setBal(j.balance); S.cp = j.coupon || null; drawSelf(j.avatar); hideSplash(false); }, function(){ hideSplash(false); });
})();
</script>
</body>
</html>
HTML;
}
