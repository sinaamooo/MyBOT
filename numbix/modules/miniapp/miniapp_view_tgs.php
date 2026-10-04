<?php
defined('NB_ROOT') || exit;

function svTplTg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl" class="spl-on" style="--sd:__SPL__s">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover"><link rel="icon" href="data:,">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#07051A">
<title>__TITLE__</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
<script>(function(){try{var n=navigator,m=n.deviceMemory||8,c=n.hardwareConcurrency||8,sd=(n.connection&&n.connection.saveData),rm=(window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches);if(m<=3||c<=4||sd||rm)document.documentElement.classList.add("lite");}catch(e){}})();</script>
__FONT__
<style>
:root{
  --bg:#07051A;--glass:rgba(28,20,70,.52);--glass2:rgba(40,28,92,.62);--solid:#110C2E;
  --line:rgba(196,181,253,.12);--line2:rgba(139,92,246,.32);
  --ink:#F3F0FF;--dim:#A7A1CC;--dim2:#6F6A98;
  --tg:#8B5CF6;--sky:#A78BFA;--cy:#22D3EE;--vi:#E879F9;--gold:#FCD34D;--red:#FB7185;--ok:#34D399;
  --grad:linear-gradient(120deg,#7C3AED 0%,#6366F1 52%,#0EA5E9 100%);
  --z:.9;--safe:calc(env(safe-area-inset-bottom,0px) / .9);--top:0px;color-scheme:dark;
  --dots:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cg fill='%23C4B5FD'%3E%3Ccircle cx='22' cy='38' r='1.2' opacity='.8'/%3E%3Ccircle cx='96' cy='12' r='.8' opacity='.6'/%3E%3Ccircle cx='160' cy='70' r='1.4' opacity='.5'/%3E%3Ccircle cx='250' cy='30' r='.9' opacity='.8'/%3E%3Ccircle cx='280' cy='120' r='1.1' opacity='.55'/%3E%3Ccircle cx='200' cy='160' r='.8' opacity='.7'/%3E%3Ccircle cx='60' cy='140' r='1' opacity='.5'/%3E%3Ccircle cx='120' cy='210' r='1.3' opacity='.65'/%3E%3Ccircle cx='30' cy='250' r='.8' opacity='.7'/%3E%3Ccircle cx='230' cy='240' r='1.2' opacity='.6'/%3E%3Ccircle cx='170' cy='290' r='.9' opacity='.5'/%3E%3Ccircle cx='90' cy='280' r='.7' opacity='.8'/%3E%3C/g%3E%3Cg fill='%2322D3EE'%3E%3Ccircle cx='140' cy='120' r='1' opacity='.6'/%3E%3Ccircle cx='270' cy='200' r='1.1' opacity='.5'/%3E%3Ccircle cx='50' cy='90' r='.9' opacity='.7'/%3E%3C/g%3E%3C/svg%3E")
}
html.scr .sky *{animation-play-state:paused!important}
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
@keyframes bob{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-4px,0)}}
@keyframes shine{0%,72%{transform:translate3d(-130%,0,0) skewX(-20deg)}100%{transform:translate3d(360%,0,0) skewX(-20deg)}}
@keyframes up{from{opacity:0;transform:translate3d(0,14px,0)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}

.sky{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;overflow:clip;contain:strict;
  background:var(--dots) 0 0/300px 300px repeat,
    radial-gradient(circle 55vw at 20vw 15vw,rgba(124,58,237,.3),transparent),
    radial-gradient(circle 48vw at 98vw 70vh,rgba(232,121,249,.18),transparent),
    radial-gradient(90vw 22vh at 50% 30vh,rgba(139,92,246,.12),transparent 72%),
    radial-gradient(130vw 70vh at 100% -12%,rgba(124,58,237,.24),transparent 62%),
    radial-gradient(100vw 60vh at -15% 110%,rgba(34,211,238,.14),transparent 62%),
    linear-gradient(180deg,#07051A 0%,#110B33 52%,#07051A 100%)}
.sky>*{position:absolute;display:block}
.sky .pd{bottom:-8px;width:3px;height:3px;border-radius:50%;background:#C4B5FD;box-shadow:0 0 6px 1px rgba(139,92,246,.8);opacity:0;animation:pd 14s linear infinite}
.sky .pd:nth-of-type(1){left:8%}
.sky .pd:nth-of-type(2){left:23%;animation-duration:18s;animation-delay:-6s;background:#A5F3FC}
.sky .pd:nth-of-type(3){left:41%;animation-duration:12s;animation-delay:-3s}
.sky .pd:nth-of-type(4){left:57%;animation-duration:20s;animation-delay:-11s;background:#F0ABFC}
.sky .pd:nth-of-type(5){left:71%;animation-duration:15s;animation-delay:-8s}
.sky .pd:nth-of-type(6){left:86%;animation-duration:17s;animation-delay:-1s;background:#A5F3FC}
.sky .pd:nth-of-type(7){left:33%;animation-duration:22s;animation-delay:-15s;width:2px;height:2px}
.sky .pd:nth-of-type(8){left:94%;animation-duration:19s;animation-delay:-4s;width:2px;height:2px}
@keyframes pd{0%{transform:translate3d(0,0,0);opacity:0}10%{opacity:.9}90%{opacity:.6}100%{transform:translate3d(0,-105vh,0);opacity:0}}
.sky .fp{left:0;top:0;width:24px;height:24px;color:#C4B5FD;opacity:0;will-change:transform,opacity}
.sky .fp svg{width:100%;height:100%;filter:drop-shadow(0 0 6px rgba(139,92,246,.8))}
.sky .fp:before{content:"";position:absolute;right:88%;top:62%;width:120px;height:1.5px;border-radius:2px;
  background:repeating-linear-gradient(90deg,rgba(196,181,253,.55) 0 7px,transparent 7px 12px);
  -webkit-mask-image:linear-gradient(90deg,transparent,#000);mask-image:linear-gradient(90deg,transparent,#000)}
.sky .f1{animation:fp1 17s linear infinite}
.sky .f2{width:18px;height:18px;animation:fp2 23s linear 6s infinite}
@keyframes fp1{0%{transform:translate3d(-20vw,34vh,0) rotate(-6deg);opacity:0}6%{opacity:.85}46%{transform:translate3d(112vw,8vh,0) rotate(-12deg);opacity:.85}50%,100%{transform:translate3d(122vw,5vh,0) rotate(-12deg);opacity:0}}
@keyframes fp2{0%{transform:translate3d(-20vw,80vh,0) rotate(-14deg);opacity:0}8%{opacity:.6}52%{transform:translate3d(112vw,46vh,0) rotate(-18deg);opacity:.6}56%,100%{transform:translate3d(122vw,43vh,0) rotate(-18deg);opacity:0}}
.sky .tw{width:3px;height:3px;border-radius:50%;background:#fff;box-shadow:0 0 6px 1px rgba(196,181,253,.9);opacity:.2;animation:twk 3.4s ease-in-out infinite}
.sky .tw:nth-of-type(1){left:14%;top:11%}
.sky .tw:nth-of-type(2){left:76%;top:19%;animation-delay:-.8s}
.sky .tw:nth-of-type(3){left:38%;top:34%;animation-delay:-1.6s;background:#A5F3FC}
.sky .tw:nth-of-type(4){left:88%;top:52%;animation-delay:-2.4s}
.sky .tw:nth-of-type(5){left:9%;top:63%;animation-delay:-.4s}
.sky .tw:nth-of-type(6){left:58%;top:74%;animation-delay:-1.2s;background:#F0ABFC}
.sky .tw:nth-of-type(7){left:26%;top:88%;animation-delay:-2s}
@keyframes twk{0%,100%{opacity:.15;transform:scale(.6)}50%{opacity:1;transform:scale(1.3)}}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:calc(var(--top) + 8px) 14px calc(100px + var(--safe));overflow-x:clip}

.hd0{position:sticky;top:calc(var(--top) + 6px);z-index:30;margin-bottom:12px}
.hd0:before{content:"";position:fixed;left:0;right:0;top:0;height:calc(var(--top) + 6px);z-index:-1;pointer-events:none;
  background:var(--dots) 0 0/300px 300px repeat,
    radial-gradient(circle 55vw at 20vw 15vw,rgba(124,58,237,.3),transparent) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat,
    radial-gradient(130vw 70vh at 100% -12%,rgba(124,58,237,.24),transparent 62%) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat,
    linear-gradient(180deg,#07051A 0%,#110B33 52%,#07051A 100%) 0 0/calc(100vw / .9) calc(100vh / .9) no-repeat}
.hdr{border-radius:22px;padding:8px;border:1px solid var(--line2);
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.015)),#0D0928;
  box-shadow:0 18px 38px -22px #000,inset 0 1px 0 rgba(255,255,255,.09)}
.hrow{display:flex;align-items:center;gap:10px}
.ava{position:relative;width:40px;height:40px;flex:0 0 auto;border-radius:50%;padding:2px;overflow:hidden;overflow:clip}
.ava:before{content:"";position:absolute;inset:-30%;background:conic-gradient(#7C3AED,#22D3EE,#E879F9,#7C3AED);animation:spin 5s linear infinite}
.ava span{position:relative;display:grid;place-items:center;width:100%;height:100%;border-radius:50%;overflow:hidden;overflow:clip;
  background:#100B30;border:2px solid #100B30;color:var(--cy);font-weight:900;font-size:15px}
.ava span img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who small{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--dim);white-space:nowrap;overflow:hidden}
.who small span{overflow:hidden;text-overflow:ellipsis}
.dot{position:relative;display:inline-block;flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.dot:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 2s ease-out infinite}
.bal{display:flex;align-items:center;gap:6px;height:36px;padding:0 12px;border-radius:13px;font-weight:900;font-size:12px;
  border:1px solid var(--line2);background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(34,211,238,.08))}
.bal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.ib{width:36px;height:36px;flex:0 0 auto;border-radius:12px;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.04)}
.ib svg{width:18px;height:18px;color:var(--cy)}

.tabs{position:fixed;left:12px;right:12px;bottom:calc(10px + var(--safe));z-index:30;max-width:456px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);
  height:64px;padding:5px;border-radius:24px;border:1px solid var(--line2);
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02)),#0C0826;
  box-shadow:0 22px 44px -16px #000,inset 0 1px 0 rgba(255,255,255,.09)}
.tabs button{position:relative;z-index:1;height:100%;border-radius:19px;font-size:10.5px;font-weight:800;color:var(--dim);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;transition:color .25s}
.tabs button svg{width:21px;height:21px}
.tabs button.on{color:#fff}
.tabs .ind{position:absolute;z-index:0;top:5px;bottom:5px;right:5px;width:calc((100% - 10px)/3);border-radius:19px;overflow:hidden;overflow:clip;
  background:var(--grad);box-shadow:0 8px 20px -6px rgba(124,58,237,.9);transition:transform .38s cubic-bezier(.3,.9,.3,1)}
.tabs .bd{position:absolute;top:5px;left:calc(50% - 24px);min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--gold);
  color:#1a1300;font-size:9.5px;font-weight:900;display:none;place-items:center}
.tabs .bd.on{display:grid}

.pg{display:none}
.pg.on{display:block}
.pg.on>*{animation:up .5s cubic-bezier(.2,.85,.25,1) backwards}
.pg.on>:nth-child(2){animation-delay:.05s}
.pg.on>:nth-child(3){animation-delay:.1s}
.pg.on>:nth-child(4){animation-delay:.15s}
.pg.on>:nth-child(n+5){animation-delay:.2s}

.hero{position:relative;overflow:hidden;overflow:clip;border-radius:26px;padding:1.3px;isolation:isolate;box-shadow:0 26px 50px -30px rgba(124,58,237,.8);
  background:linear-gradient(135deg,rgba(139,92,246,.85),rgba(34,211,238,.25) 38%,rgba(124,58,237,.12) 60%,rgba(232,121,249,.7))}
.hero .in:after{content:"";position:absolute;top:0;bottom:0;left:0;width:26%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(196,181,253,.12),transparent);animation:shine 6s ease-in-out infinite}
.hero .in{position:relative;border-radius:25px;padding:18px 16px 16px;overflow:hidden;overflow:clip;
  background:radial-gradient(120% 90% at 0% 0%,rgba(124,58,237,.26),transparent 60%),linear-gradient(155deg,#1F1452 0%,#150E3D 55%,#140C3A 100%)}
.art{position:absolute;left:10px;top:14px;width:124px;height:124px}
.art>i{position:absolute;display:block;border-radius:50%}
.art .o1{inset:8px;border:1.5px dashed rgba(34,211,238,.38)}
.art .o2{inset:-4px;border:1px solid rgba(139,92,246,.16);border-top-color:rgba(34,211,238,.75);animation:spin 7s linear infinite}
.art .rp{inset:28px;border:2px solid rgba(139,92,246,.55);animation:rpl 2.8s ease-out infinite}
.art .r2{animation-delay:1.4s}
@keyframes rpl{from{transform:scale(.85);opacity:.9}to{transform:scale(1.8);opacity:0}}
.art .st{inset:0;border-radius:0;animation:spin 9s linear infinite}
.art .st:before{content:"";position:absolute;top:1px;left:50%;width:8px;height:8px;margin-left:-4px;border-radius:50%;background:var(--cy);box-shadow:0 0 10px 2px rgba(34,211,238,.9)}
.art .s2{animation-duration:13s;animation-direction:reverse}
.art .s2:before{top:auto;bottom:6px;width:6px;height:6px;background:var(--vi);box-shadow:0 0 10px 2px rgba(232,121,249,.9)}
.art svg{position:absolute;left:22px;top:22px;width:80px;height:80px;filter:drop-shadow(0 14px 18px rgba(124,58,237,.6));animation:fly 4s ease-in-out infinite}
@keyframes fly{0%,100%{transform:translate3d(0,0,0) rotate(-4deg)}50%{transform:translate3d(-4px,-7px,0) rotate(3deg)}}
.hero .tx{position:relative;max-width:60%;min-height:118px}
.kick{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;
  color:var(--cy);background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.28)}
.kick i{position:relative;width:6px;height:6px;border-radius:50%;background:var(--cy)}
.kick i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.8s ease-out infinite}
.hero h1{margin-top:9px;font-size:22px;font-weight:900;line-height:1.35;
  background:linear-gradient(90deg,#fff 0%,#DDD6FE 45%,#A5F3FC 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.hero p{margin-top:5px;font-size:11.5px;color:var(--dim)}
.go{position:relative;overflow:hidden;overflow:clip;margin-top:12px;height:46px;padding:0 20px;border-radius:15px;background:var(--grad);color:#fff;
  font-weight:900;font-size:13.5px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 14px 26px -12px rgba(124,58,237,.95)}
.go:after{content:"";position:absolute;top:0;bottom:0;left:0;width:34%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);animation:shine 3.6s ease-in-out infinite}
.go svg{width:17px;height:17px}
.kpis{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}
.kpis div{padding:9px 10px;border-radius:15px;background:rgba(9,6,28,.5);border:1px solid var(--line);box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}
.kpis b{display:block;font-size:15px;font-weight:900}
.kpis small{display:block;font-size:9.5px;color:var(--dim);white-space:nowrap}

.tick{overflow:hidden;overflow:clip;contain:paint;direction:ltr;margin:14px -14px 0;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.tick .tr{display:flex;width:max-content;animation:mq 32s linear infinite;will-change:transform}
@keyframes mq{to{transform:translate3d(-50%,0,0)}}
.tick span{direction:rtl;display:inline-flex;align-items:center;gap:6px;margin-right:8px;white-space:nowrap;font-size:11px;font-weight:800;padding:7px 12px;border-radius:30px;
  border:1px solid var(--line2);background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02)),rgba(14,10,40,.5);color:var(--ink)}
.tick span svg{width:14px;height:14px;color:var(--cy)}

.hd{display:flex;align-items:center;gap:8px;margin:20px 2px 10px}
.hd h3{flex:1;font-size:14px;font-weight:900;display:flex;align-items:center;gap:8px}
.hd h3:before{content:"";width:4px;height:16px;border-radius:4px;background:var(--grad);box-shadow:0 0 10px rgba(139,92,246,.8)}
.hd button{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:800;color:var(--sky);min-height:34px;padding:0 6px;margin:-10px -6px}
.hd button svg{width:15px;height:15px}

.bento{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.bt{position:relative;overflow:hidden;overflow:clip;isolation:isolate;text-align:right;border-radius:20px;padding:14px;min-height:118px;
  background:linear-gradient(160deg,rgba(255,255,255,.07),rgba(255,255,255,.01) 60%),var(--glass);border:1px solid var(--line);
  display:flex;flex-direction:column;justify-content:space-between;gap:10px;box-shadow:inset 0 1px 0 rgba(255,255,255,.06);
  animation:up .55s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 60ms);transition:transform .15s}
.bt:before{content:"";position:absolute;z-index:-1;width:150px;height:150px;border-radius:50%;left:-55px;top:-65px;
  background:radial-gradient(closest-side,rgba(124,58,237,.34),transparent)}
.bt:first-child:before{animation:glw 7s ease-in-out infinite alternate}
@keyframes glw{to{transform:translate3d(60px,70px,0) scale(1.2)}}
.bt:first-child{grid-column:1/-1;min-height:92px;flex-direction:row;align-items:center;gap:14px;border-color:var(--line2);
  background:linear-gradient(120deg,rgba(124,58,237,.24),rgba(34,211,238,.07)),var(--glass)}
.bt .ic{width:46px;height:46px;border-radius:15px;display:grid;place-items:center;flex:0 0 auto;color:var(--tg);
  background:rgba(124,58,237,.14);border:1px solid rgba(139,92,246,.25)}
.bt:first-child .ic{width:56px;height:56px;border-radius:18px;background:var(--grad);color:#fff;border:0;box-shadow:0 10px 22px -10px rgba(124,58,237,.9);animation:bob 3.6s ease-in-out infinite}
.bt .ic svg{width:22px;height:22px}
.bt b{display:block;font-size:13.5px;font-weight:900}
.bt small{display:block;font-size:10.5px;color:var(--dim)}
.bt .pr{display:inline-block;margin-top:2px;font-size:11px;font-weight:800;color:var(--cy)}
.bt:nth-child(2n):last-child{grid-column:1/-1;min-height:92px;flex-direction:row;align-items:center;gap:14px}
.bt:active{transform:scale(.98)}
.bt:nth-child(3n+2) .ic{color:var(--cy);background:rgba(34,211,238,.12);border-color:rgba(34,211,238,.25)}
.bt:nth-child(3n) .ic{color:var(--vi);background:rgba(232,121,249,.14);border-color:rgba(232,121,249,.28)}
.bt:nth-child(3n+2):before{background:radial-gradient(closest-side,rgba(34,211,238,.26),transparent)}
.bt:nth-child(3n):before{background:radial-gradient(closest-side,rgba(232,121,249,.3),transparent)}

.chips{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;margin:0 -14px;padding:2px 14px 4px}
.chips::-webkit-scrollbar{display:none}
.chips button{flex:0 0 auto;height:34px;padding:0 13px;border-radius:11px;font-size:11.5px;font-weight:800;color:var(--dim);
  background:var(--glass);border:1px solid var(--line);display:flex;align-items:center;gap:6px;transition:color .2s,background .2s}
.chips button svg{width:14px;height:14px}
.chips button.on{color:#fff;background:var(--grad);border-color:transparent;box-shadow:0 8px 18px -10px rgba(124,58,237,.9)}
.srch{display:flex;align-items:center;gap:8px;height:46px;margin-bottom:10px;padding:0 13px;border-radius:15px;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line2)}
.srch svg{width:17px;height:17px;color:var(--sky)}
.srch input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:13px}
.srch input::placeholder{color:var(--dim2)}

.list{display:grid;gap:11px;margin-top:10px}
.pgr{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.cd{--tc:167,139,250;position:relative;overflow:hidden;overflow:clip;isolation:isolate;display:flex;flex-direction:column;min-width:0;width:100%;text-align:right;
  padding:11px 11px 11px;border-radius:22px;border:1px solid rgba(var(--tc),.28);
  background:radial-gradient(130% 60% at 100% 0%,rgba(var(--tc),.22),transparent 64%),linear-gradient(172deg,rgba(255,255,255,.075),rgba(255,255,255,.012) 58%),var(--glass);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.1),0 18px 30px -24px rgba(var(--tc),.85);
  animation:up .5s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 40ms);transition:transform .15s,border-color .2s}
.cd:before{content:"";position:absolute;top:0;left:16%;right:16%;height:2px;border-radius:0 0 3px 3px;background:linear-gradient(90deg,transparent,rgb(var(--tc)),transparent)}
.cd:active{transform:scale(.97);border-color:rgba(var(--tc),.65)}
.cd.t-cheap,.cd.t-iran{--tc:74,222,128}
.cd.t-high{--tc:251,191,36}
.cd.t-fake{--tc:148,163,184}
.cd.t-ru{--tc:96,165,250}
.cd.t-ref{--tc:45,212,191}
.cd .tp{display:flex;align-items:flex-start;justify-content:space-between;gap:6px}
.cd .rb{display:inline-flex;align-items:center;gap:3px;min-width:0;height:23px;padding:0 8px;border-radius:9px;font-size:9.5px;font-weight:900;white-space:nowrap;overflow:hidden;
  color:rgb(var(--tc));background:rgba(var(--tc),.14);border:1px solid rgba(var(--tc),.34)}
.cd .rb svg{width:11px;height:11px;flex:0 0 auto}
.cd.t-iran .rb{color:#DCFCE7;background:linear-gradient(90deg,rgba(34,197,94,.26),rgba(255,255,255,.1),rgba(239,68,68,.26));border-color:rgba(255,255,255,.22)}
.cd.t-ru .rb{color:#E0E7FF;background:linear-gradient(90deg,rgba(255,255,255,.12),rgba(59,130,246,.26),rgba(239,68,68,.26));border-color:rgba(147,197,253,.3)}
.cd .ic{flex:0 0 auto;width:34px;height:34px;border-radius:12px;display:grid;place-items:center;color:#fff;
  background:linear-gradient(140deg,rgba(var(--tc),.95),rgba(var(--tc),.4));box-shadow:0 8px 16px -8px rgba(var(--tc),.95),inset 0 1px 0 rgba(255,255,255,.4)}
.cd .ic svg{width:18px;height:18px}
.cd.t-high .ic,.cd.t-cheap .ic,.cd.t-iran .ic,.cd.t-fake .ic{color:#0B0620}
.cd .nm{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-top:9px;font-size:13.5px;font-weight:900;line-height:1.55;color:var(--ink)}
.cd .mt{display:flex;align-items:center;gap:5px;margin-top:2px;font-size:10px;font-weight:700;color:var(--dim);white-space:nowrap}
.cd .lv{width:6px;height:6px;flex:0 0 auto;border-radius:50%;background:var(--ok);box-shadow:0 0 8px rgba(52,211,153,.8)}
.cd .fcs{gap:5px;margin-top:8px}
.cd .fc{max-width:100%;height:23px;padding:0 7px;font-size:9.8px;overflow:hidden}
.cd .fc.em{height:28px;font-size:15px;letter-spacing:1.5px}
.cd .ft{margin-top:auto;padding-top:10px}
.cd .pz{display:block;margin-top:10px;padding-top:9px;border-top:1px dashed rgba(var(--tc),.24);white-space:nowrap}
.cd .pz b{font-size:20px;font-weight:900;line-height:1.2;background:linear-gradient(90deg,#fff 10%,rgb(var(--tc)));-webkit-background-clip:text;background-clip:text;color:transparent}
.cd .pz i{font-style:normal;font-size:10.5px;font-weight:800;margin-right:4px;color:var(--dim)}
.cd .pz small{display:block;font-size:9.5px;font-weight:700;color:var(--dim2)}
.cd .ordb{position:relative;overflow:hidden;overflow:clip;display:flex;align-items:center;justify-content:center;gap:3px;height:38px;margin-top:9px;border-radius:13px;
  background:var(--grad);color:#fff;font-size:12.5px;font-weight:900;box-shadow:0 10px 20px -12px rgba(124,58,237,.95),inset 0 1px 0 rgba(255,255,255,.35)}
.cd .ordb svg{width:15px;height:15px}
.cd.t-high{border-color:rgba(251,191,36,.42);background:radial-gradient(130% 60% at 100% 0%,rgba(251,191,36,.2),transparent 64%),radial-gradient(90% 60% at 0% 100%,rgba(124,58,237,.22),transparent 70%),linear-gradient(172deg,rgba(255,255,255,.08),rgba(255,255,255,.012) 58%),var(--glass)}
.cd.t-high .ordb{background:linear-gradient(120deg,#F59E0B,#FBBF24 55%,#FDE68A);color:#2A1700;box-shadow:0 10px 20px -12px rgba(245,158,11,.95),inset 0 1px 0 rgba(255,255,255,.5)}
.cd.t-high:after{content:"";position:absolute;z-index:2;top:0;bottom:0;left:0;width:34%;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(253,230,138,.16),transparent);transform:translate3d(-130%,0,0) skewX(-20deg);animation:shine 6.5s ease-in-out infinite}
.cd.w{grid-column:1/-1}
.cd.w .ft{display:flex;align-items:flex-end;justify-content:space-between;gap:12px}
.cd.w .pz{flex:1;min-width:0}
.cd.w .ordb{flex:0 0 46%;margin-top:0}
.cd.w .nm{font-size:14.5px}
.tier{display:inline-flex;align-items:center;gap:3px;vertical-align:middle;font-size:9.5px;font-weight:900;padding:2px 8px;border-radius:8px;white-space:nowrap;
  color:#DDD6FE;background:rgba(139,92,246,.14);border:1px solid rgba(139,92,246,.3)}
.tier.cheap{color:#86EFAC;background:rgba(34,197,94,.13);border-color:rgba(74,222,128,.32)}
.tier.mid{color:#DDD6FE;background:rgba(139,92,246,.13);border-color:rgba(139,92,246,.32)}
.tier.high{color:#FDE68A;background:rgba(245,158,11,.14);border-color:rgba(252,211,77,.4)}
.tier.fake{color:#CBD5E1;background:rgba(148,163,184,.13);border-color:rgba(148,163,184,.3)}
.tier.iran{color:#BBF7D0;background:linear-gradient(90deg,rgba(34,197,94,.18),rgba(255,255,255,.06),rgba(239,68,68,.18));border-color:rgba(255,255,255,.22)}
.tier.ru{color:#E0E7FF;background:linear-gradient(90deg,rgba(255,255,255,.1),rgba(59,130,246,.2),rgba(239,68,68,.2));border-color:rgba(147,197,253,.3)}
.tier.ref{color:#A5F3FC;background:rgba(20,184,166,.14);border-color:rgba(45,212,191,.35)}
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
.fld textarea{width:100%;min-height:118px;padding:12px 14px;border-radius:15px;background:rgba(9,6,28,.6);border:1px solid var(--line2);color:var(--ink);
  font-family:inherit;font-size:13.5px;font-weight:600;line-height:1.9;outline:0;resize:vertical}

.fcs{display:flex;flex-wrap:wrap;gap:6px;margin-top:11px}
.fc{display:inline-flex;align-items:center;gap:4px;height:25px;padding:0 9px;border-radius:9px;font-size:10.5px;font-weight:800;white-space:nowrap;
  color:var(--sky);background:rgba(139,92,246,.09);border:1px solid rgba(139,92,246,.2)}
.fc svg{width:12.5px;height:12.5px;flex:0 0 auto}
.fc.ok{color:var(--ok);background:rgba(52,211,153,.09);border-color:rgba(52,211,153,.24)}
.fc.no{color:var(--dim);background:rgba(167,161,204,.07);border-color:rgba(167,161,204,.16)}
.fc.hot{color:var(--gold);background:rgba(252,211,77,.08);border-color:rgba(252,211,77,.22)}
.fc.em{font-size:16px;letter-spacing:2px;height:30px}

.emp{text-align:center;padding:30px 18px;border-radius:22px;border:1px dashed var(--line2);color:var(--dim);
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass)}
.emp>svg{width:42px;height:42px;margin:0 auto 10px;color:var(--sky);animation:bob 3s ease-in-out infinite}
.emp>b{display:block;color:var(--ink);font-size:13.5px;margin-bottom:4px}
.sk{position:relative;overflow:hidden;overflow:clip;height:76px;border-radius:18px;margin-bottom:9px;background:var(--glass);border:1px solid var(--line)}
.sk:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(196,181,253,.1),transparent);animation:skl 1.2s linear infinite}
@keyframes skl{from{transform:translate3d(-100%,0,0)}to{transform:translate3d(100%,0,0)}}

.or{position:relative;overflow:hidden;overflow:clip;border-radius:20px;padding:13px;margin-bottom:10px;
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line);
  animation:up .45s cubic-bezier(.2,.85,.25,1) backwards;animation-delay:calc(var(--i,0) * 50ms)}
.or .h{display:flex;align-items:flex-start;gap:10px}
.or .h .ic{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;background:rgba(124,58,237,.14);color:var(--tg);flex:0 0 auto}
.or .h .ic svg{width:20px;height:20px}
.or .h div{flex:1;min-width:0}
.or .h b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
b[dir=auto]{text-align:right;unicode-bidi:plaintext}
.or .h small{font-size:10px;color:var(--dim)}
.pill{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;padding:3px 9px;border-radius:20px;background:rgba(124,58,237,.14);color:var(--sky)}
.pill.run:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;animation:twk 1.4s ease-in-out infinite}
.pill.done{background:rgba(52,211,153,.14);color:var(--ok)}
.pill.partial,.pill.check{background:rgba(252,211,77,.14);color:var(--gold)}
.pill.canceled,.pill.failed{background:rgba(251,113,133,.14);color:var(--red)}
.prog{height:8px;border-radius:8px;background:rgba(167,161,204,.14);margin:11px 0 7px;overflow:hidden;overflow:clip}
.prog i{position:relative;display:block;height:100%;border-radius:8px;background:var(--grad);overflow:hidden;overflow:clip;transition:width .6s ease}
.prog.run i:after{content:"";position:absolute;top:0;bottom:0;left:0;width:calc(100% + 17px);
  background:repeating-linear-gradient(-45deg,rgba(255,255,255,.3) 0 6px,transparent 6px 12px);animation:strp .8s linear infinite}
@keyframes strp{to{transform:translate3d(-16.97px,0,0)}}
.kv{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:10.5px;color:var(--dim)}
.kv b{color:var(--ink);font-weight:800}
.lnk{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:10.5px;color:var(--sky);direction:ltr;overflow:hidden;overflow:clip}
.lnk span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lnk svg{width:13px;height:13px;flex:0 0 auto}
.rfr{margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.rfs{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:var(--dim)}
.rfs svg{width:13px;height:13px}
.rfb{height:34px;padding:0 13px;border-radius:12px;display:inline-flex;align-items:center;gap:6px;margin-inline-start:auto;
  font-size:11px;font-weight:900;color:var(--cy);background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.35)}
.rfb svg{width:15px;height:15px}
.rfb:disabled{opacity:.55}

.fld{margin-top:12px}
.fld label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.fld input{width:100%;height:50px;padding:0 14px;border-radius:15px;background:rgba(9,6,28,.6);border:1px solid var(--line2);color:var(--ink);font-size:14px;font-weight:700;outline:0}
.fld input::placeholder{color:var(--dim2)}
.fld input:focus{border-color:var(--sky);box-shadow:0 0 0 3px rgba(139,92,246,.18)}
.fld small{display:block;margin-top:5px;font-size:10.5px;color:var(--dim)}
.fld small.er{color:var(--red)}
.cpy{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 10px;border-radius:10px;background:rgba(124,58,237,.12);border:1px solid var(--line2);font-weight:800;font-size:12px}
.cpy svg{width:14px;height:14px;color:var(--sky)}
.btn{position:relative;overflow:hidden;overflow:clip;width:100%;height:52px;margin-top:14px;border-radius:16px;background:var(--grad);color:#fff;font-weight:900;font-size:14px;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 14px 26px -14px rgba(124,58,237,.95)}
.btn:after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);animation:shine 4s ease-in-out infinite}
.btn svg{width:18px;height:18px}
.btn[disabled]{opacity:.45;filter:grayscale(.4)}
.btn[disabled]:after{display:none}
.btn.gh{background:var(--glass);color:var(--ink);border:1px solid var(--line2);box-shadow:none}
.btn.gh:after{display:none}

.note{margin-top:12px;padding:11px 12px;border-radius:14px;background:rgba(124,58,237,.08);border:1px solid var(--line);font-size:11px;color:var(--dim);line-height:1.9}

.links{display:grid;gap:9px;margin-top:14px}
.xl{position:relative;overflow:hidden;overflow:clip;display:flex;align-items:center;gap:12px;min-height:62px;padding:10px 12px;border-radius:18px;text-align:right;
  background:linear-gradient(90deg,rgba(255,255,255,.06),rgba(255,255,255,.01)),var(--glass);border:1px solid var(--line)}
.xl .ic{width:42px;height:42px;flex:0 0 auto;border-radius:14px;display:grid;place-items:center;color:#fff;box-shadow:0 10px 20px -12px #000}
.xl .ic svg{width:21px;height:21px}
.xl.ig .ic{background:linear-gradient(45deg,#F58529,#DD2A7B 50%,#8134AF)}
.xl.num .ic{background:linear-gradient(135deg,#22C55E,#2563EB)}
.xl.sup .ic{background:rgba(124,58,237,.16);color:var(--sky)}
.xl span{flex:1;min-width:0;font-weight:800;font-size:13px}
.xl span small{display:block;font-size:10px;color:var(--dim);font-weight:600}
.xl .ch{width:18px;height:18px;color:var(--dim);animation:nud 1.8s ease-in-out infinite}
@keyframes nud{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(-4px,0,0)}}

.ov{position:fixed;inset:0;z-index:40;background:radial-gradient(120% 60% at 50% 100%,rgba(124,58,237,.3),transparent 70%),rgba(4,2,14,.5);
  opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ov.on{opacity:1;visibility:visible}
.sh{position:fixed;left:0;right:0;bottom:0;z-index:41;max-width:480px;margin:0 auto;max-height:92vh;overflow:auto;overscroll-behavior:contain;
  border-radius:30px 30px 0 0;padding:8px 16px calc(18px + var(--safe));border:1px solid rgba(196,181,253,.24);border-bottom:0;
  background:linear-gradient(180deg,rgba(255,255,255,.11) 0%,rgba(255,255,255,.035) 18%,rgba(255,255,255,.012) 100%),
    radial-gradient(110% 38% at 100% 0%,rgba(124,58,237,.42),transparent 62%),radial-gradient(80% 30% at 0% 6%,rgba(34,211,238,.18),transparent 62%),
    radial-gradient(90% 40% at 50% 100%,rgba(99,102,241,.2),transparent 70%),rgba(20,13,54,.66);
  -webkit-backdrop-filter:blur(24px) saturate(170%);backdrop-filter:blur(24px) saturate(170%);
  box-shadow:0 -26px 60px -22px rgba(124,58,237,.6),inset 0 1px 0 rgba(255,255,255,.24);
  transform:translate3d(0,105%,0);transition:transform .36s cubic-bezier(.2,.85,.25,1)}
@supports not ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){.sh{background-color:rgba(20,13,54,.97)}}
.sh:before{content:"";position:absolute;top:0;left:14%;right:14%;height:1.5px;border-radius:2px;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(196,181,253,.95),rgba(34,211,238,.85),transparent)}
.sh.on{transform:none}
.grab{width:46px;height:5px;border-radius:5px;margin:2px auto 12px;background:linear-gradient(90deg,rgba(196,181,253,.55),rgba(34,211,238,.55));box-shadow:0 0 12px rgba(139,92,246,.5)}
.sh .st{display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:22px;
  background:linear-gradient(135deg,rgba(255,255,255,.1),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.12);box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
.sh .st .ic{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:var(--grad);color:#fff;flex:0 0 auto;
  box-shadow:0 10px 22px -8px rgba(124,58,237,.95),inset 0 1px 0 rgba(255,255,255,.4)}
.sh .st .ic svg{width:24px;height:24px}
.sh .st b{display:block;font-size:13.5px;font-weight:900;line-height:1.55}
.sh .st small{font-size:10.5px;color:var(--dim)}
.sh .x{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.sh .x svg{width:16px;height:16px}
.sh .fld label{color:#CFC8F5}
.sh .fld input,.sh .fld textarea{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));border:1px solid rgba(196,181,253,.24);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.09),inset 0 -12px 22px -20px rgba(124,58,237,.8);transition:border-color .2s,box-shadow .2s}
.sh .fld input:focus,.sh .fld textarea:focus{border-color:#A78BFA;box-shadow:0 0 0 3px rgba(139,92,246,.24),inset 0 1px 0 rgba(255,255,255,.12)}
.qrow{display:flex;gap:8px;align-items:center}
.qrow input{flex:1;text-align:center;direction:ltr}
.qrow button{width:50px;height:50px;flex:0 0 auto;border-radius:16px;font-size:22px;font-weight:900;color:#DDD6FE;transition:transform .12s;
  background:linear-gradient(180deg,rgba(255,255,255,.11),rgba(255,255,255,.03));border:1px solid rgba(196,181,253,.28);box-shadow:inset 0 1px 0 rgba(255,255,255,.15)}
.qrow button:active{transform:scale(.92)}
.qchips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.qchips button{height:32px;padding:0 12px;border-radius:11px;font-size:11px;font-weight:800;color:var(--dim);transition:background .2s,color .2s,box-shadow .2s;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.11)}
.qchips button.on{color:#fff;border-color:transparent;background:var(--grad);box-shadow:0 8px 18px -10px rgba(124,58,237,.95),inset 0 1px 0 rgba(255,255,255,.35)}
.sum{position:relative;margin-top:14px;border-radius:20px;padding:12px 14px;border:1px solid transparent;
  background:linear-gradient(135deg,rgba(46,30,110,.62),rgba(16,11,48,.5)) padding-box,
    linear-gradient(135deg,rgba(196,181,253,.6),rgba(124,58,237,.2) 45%,rgba(34,211,238,.55)) border-box;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.12),0 16px 30px -22px rgba(124,58,237,.95)}
.sum div{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--dim);padding:3px 0}
.sum div b{color:var(--ink);font-size:12.5px}
.sum div.t{margin-top:6px;padding-top:9px;border-top:1px dashed rgba(196,181,253,.24)}
.sum div.t b{font-size:21px;font-weight:900;background:linear-gradient(90deg,#fff,#C4B5FD 45%,#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent}
.sum div.lo b{color:var(--red)}
.sum div.cp b{color:#34D399}
.sh .btn{height:54px;border-radius:18px;box-shadow:0 18px 34px -14px rgba(124,58,237,.95),inset 0 1px 0 rgba(255,255,255,.4)}
.sh .btn.gh{background:rgba(255,255,255,.06);box-shadow:inset 0 1px 0 rgba(255,255,255,.1)}
html.shon .sky *,html.shon .tick .tr,html.shon .app *:before,html.shon .app *:after{animation-play-state:paused!important}
.res{text-align:center;padding:6px 0 4px}
.res .rc{position:relative;width:66px;height:66px;margin:4px auto 10px;border-radius:50%;display:grid;place-items:center;background:var(--grad);color:#fff}
.res .rc:after{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid rgba(34,211,238,.6);animation:rpl 1.8s ease-out infinite}
.res .rc svg{width:32px;height:32px}
.res b{display:block;font-size:15px;font-weight:900}
.res small{font-size:12px;color:var(--cy);font-weight:800}

.toast{position:fixed;left:16px;right:16px;bottom:calc(88px + var(--safe));z-index:60;max-width:448px;margin:0 auto;display:flex;align-items:center;gap:9px;
  padding:12px 14px;border-radius:16px;background:rgba(24,16,60,.96);border:1px solid var(--line2);font-size:12px;font-weight:700;
  transform:translate3d(0,140%,0);visibility:hidden;transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s;box-shadow:0 18px 40px -18px #000}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast svg{width:18px;height:18px;flex:0 0 auto}
.toast.ok svg{color:var(--ok)}.toast.er svg{color:var(--red)}
.gate{position:fixed;inset:0;z-index:90;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.gate>svg{width:64px;height:64px;color:var(--tg);margin-bottom:12px}
.gate b{font-size:15px}.gate p{color:var(--dim);font-size:12px;margin:6px 0 16px}
.spl{position:fixed;inset:0;z-index:100;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;
  background:radial-gradient(95vw 60vh at 50% 40%,rgba(124,58,237,.24),transparent 70%),radial-gradient(90vw 50vh at 50% 105%,rgba(34,211,238,.14),transparent 70%),
    var(--dots) 0 0/300px 300px repeat,#07051A;
  transition:opacity .5s ease .1s,visibility .5s ease .1s}
.spl.out{opacity:0;visibility:hidden}
.spl-on .app,.spl-on .tabs{visibility:hidden}.spl-on .sky{display:none}
.spl{will-change:opacity}
.orb>i,.orb .lg{will-change:transform}
.spl .spc{display:flex;flex-direction:column;align-items:center;animation:spIn .8s cubic-bezier(.2,.85,.25,1) both;
  transition:transform .5s cubic-bezier(.5,0,.75,0),opacity .3s ease}
.spl.out .spc{transform:scale(1.18);opacity:0}
@keyframes spIn{from{opacity:0;transform:translate3d(0,26px,0) scale(.88)}to{opacity:1;transform:none}}
.orb{position:relative;width:178px;height:178px;display:grid;place-items:center}
.orb>i{position:absolute;display:block;border-radius:50%}
.orb .r1{inset:0;border:1.5px dashed rgba(34,211,238,.42);animation:spin 16s linear infinite}
.orb .r2{inset:20px;border:2px solid rgba(139,92,246,.12);border-top-color:#22D3EE;border-left-color:rgba(139,92,246,.8);animation:spin 1.6s cubic-bezier(.5,.1,.5,.9) infinite}
.orb .rp{inset:40px;border:2px solid rgba(139,92,246,.6);animation:rpl 2.4s ease-out infinite}
.orb .rp.d2{animation-delay:1.2s}
.orb .tl{inset:-4px;border:2px solid transparent;border-top-color:rgba(196,181,253,.55);animation:spT 3.4s linear infinite}
@keyframes spT{from{transform:rotate(-58deg)}to{transform:rotate(302deg)}}
.orb .ob{inset:-4px;animation:spin 3.4s linear infinite}
.orb .ob b{position:absolute;top:-11px;left:50%;width:22px;height:22px;margin-left:-11px;color:#EDE9FE;transform:rotate(45deg)}
.orb .ob b svg{width:100%;height:100%;filter:drop-shadow(0 0 8px rgba(139,92,246,.95))}
.orb .st{inset:8px;animation:spin 5.5s linear infinite reverse}
.orb .st:before{content:"";position:absolute;bottom:4px;left:50%;width:7px;height:7px;margin-left:-3.5px;border-radius:50%;background:var(--vi);box-shadow:0 0 10px 2px rgba(232,121,249,.9)}
.orb .lg{position:relative;width:100px;height:100px;filter:drop-shadow(0 18px 30px rgba(124,58,237,.75));animation:fly 3.2s ease-in-out infinite}
.spl h1{margin-top:22px;font-size:23px;font-weight:900;letter-spacing:-.3px;background:linear-gradient(90deg,#fff,#DDD6FE 45%,#A5F3FC);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.spl p{margin-top:4px;color:var(--dim);font-size:12px;max-width:280px;text-align:center}
.spl .ld{position:absolute;left:0;right:0;bottom:calc(40px + var(--safe));width:min(290px,82vw);margin:0 auto;text-align:center;transition:opacity .3s,transform .4s}
.spl.out .ld{opacity:0;transform:translate3d(0,12px,0)}
.spl .pc{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:12px}
.spl .pc span{font-size:11px;font-weight:800;color:#EDE9FE;text-align:right}
.spl .pc b{font-size:24px;font-weight:900;line-height:1;min-width:62px;text-align:left;background:linear-gradient(90deg,#C4B5FD,#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent}
.trk{position:relative;height:5px;border-radius:5px;background:rgba(167,161,204,.16)}
.trk .fl{position:absolute;inset:0;border-radius:5px;background:var(--grad);box-shadow:0 0 12px rgba(139,92,246,.8);transform-origin:right center;transform:scaleX(0);
  will-change:transform}
.trk .pw{position:absolute;inset:0;will-change:transform}
@keyframes spb{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes spw{from{transform:translate3d(0,0,0)}to{transform:translate3d(-100%,0,0)}}
.trk b{position:absolute;top:50%;right:0;width:22px;height:22px;margin:-11px -11px 0 0;color:#fff}
.trk b svg{width:100%;height:100%;transform:rotate(-135deg);filter:drop-shadow(0 0 6px rgba(139,92,246,.95))}
.spl .stg{display:flex;justify-content:center;gap:6px;margin-top:14px}
.spl .stg i{width:22px;height:4px;border-radius:4px;background:rgba(167,161,204,.22);transition:background .3s,box-shadow .3s,width .3s}
.spl .stg i.on{width:30px;background:var(--cy);box-shadow:0 0 8px rgba(34,211,238,.85)}
.spl .ld small{display:block;margin-top:10px;min-height:18px;color:var(--dim);font-size:10.5px;font-weight:700;transition:opacity .18s}
.spl h1{font-size:28px!important;font-weight:900;letter-spacing:-.5px;filter:drop-shadow(0 6px 22px rgba(124,58,237,.5))}
.spl p{font-size:13px;font-weight:800;color:#E9E4FF;opacity:.92;line-height:1.8}
.spl .pc span{font-size:12.5px;font-weight:900}
@font-face{font-family:'NbxNum';font-style:normal;font-weight:600;font-display:swap;src:url('assets/fonts/NbxNum.woff2') format('woff2')}
.spl .pc b{font-family:'NbxNum',system-ui,sans-serif;font-size:13px;font-weight:600;letter-spacing:.4px;min-width:0;font-variant-numeric:tabular-nums;filter:none}
.spl .ld small{font-size:11.5px;font-weight:800;color:#D4CCF7}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
html.lite *,html.lite *:before,html.lite *:after{animation:none!important;-webkit-animation:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;background-attachment:scroll!important}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.5a4 4 0 0 0-3-3.8M15.5 4.2a3.5 3.5 0 0 1 0 6.6"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.8l8.8-8.8a5.2 5.2 0 0 0 0-7.4z"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="m12 2.8 2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3L2.9 9.5l6.3-.9z"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.2" fill="currentColor"/><circle cx="3.5" cy="12" r="1.2" fill="currentColor"/><circle cx="3.5" cy="18" r="1.2" fill="currentColor"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m7.5 12.3 3 3 6-6.3"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.3"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.6-4.5L3 9M3 4v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15M21 20v-5h-5"/></symbol>
    <symbol id="i-headset" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.6"/><rect x="17" y="13" width="4" height="6" rx="1.6"/><path d="M19 19a3 3 0 0 1-3 3h-3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-plane" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 3.5 2.8 10.6c-.9.3-.9 1.5 0 1.8l4.7 1.6 1.8 5.6c.3.8 1.3 1 1.8.4l2.7-2.8 4.6 3.4c.7.5 1.6.1 1.8-.7L23 4.7c.2-.9-.7-1.6-1.5-1.2z"/><path d="m7.5 14 11-7.5-8 9"/></symbol>
    <symbol id="i-sim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 2.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 6 20V4a1.5 1.5 0 0 1 1-1.5z"/><rect x="9" y="11" width="6" height="6" rx="1"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor"/></symbol>
    <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-gauge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 17a8.5 8.5 0 1 1 17 0"/><path d="m12 17 4.2-5.6"/><circle cx="12" cy="17" r="1.3" fill="currentColor"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.8 4.5 5.8v5.6c0 4.6 3.1 8.3 7.5 9.8 4.4-1.5 7.5-5.2 7.5-9.8V5.8z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><rect x="8" y="8" width="12.5" height="12.5" rx="2.5"/><path d="M16 8V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7.5A2.5 2.5 0 0 0 6 16h2"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M21.5 2.5 10 14M21.5 2.5l-7 19-4.5-7.5-7.5-4.5z"/></symbol>
  </defs>
</svg>
<div class="sky" aria-hidden="true"><i class="fp f1"><svg><use href="#i-plane"/></svg></i><i class="fp f2"><svg><use href="#i-plane"/></svg></i><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><b class="tw"></b><s class="pd"></s><s class="pd"></s><s class="pd"></s><s class="pd"></s><s class="pd"></s><s class="pd"></s><s class="pd"></s><s class="pd"></s></div>

<div class="spl" id="spl">
  <div class="spc">
    <div class="orb"><i class="r1"></i><i class="rp"></i><i class="rp d2"></i><i class="r2"></i><i class="st"></i><i class="tl"></i><i class="ob"><b><svg><use href="#i-plane"/></svg></b></i>
      <svg class="lg" viewBox="0 0 120 120"><defs><linearGradient id="gps" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C4B5FD"/><stop offset="1" stop-color="#7C3AED"/></linearGradient></defs>
        <circle cx="60" cy="60" r="46" fill="url(#gps)"/><path d="M34 58.5 84 39c2.3-.9 4.3.6 3.6 3.8l-8.5 40c-.6 2.8-2.3 3.5-4.7 2.2l-13-9.6-6.3 6c-.7.7-1.3 1.3-2.6 1.3l.9-13.3 24.3-22c1-.9-.2-1.4-1.6-.5l-30 18.9-12.9-4c-2.8-.9-2.9-2.8.6-4.2z" fill="#fff"/></svg></div>
    <h1>__TITLE__</h1>
    <p>__TAG__</p>
  </div>
  <div class="ld"><div class="pc"><span id="spMsg">در حال اتصالِ امن…</span><b id="spPct">0%</b></div>
    <div class="trk" id="spTrk"><i class="fl" id="spBar"></i><i class="pw" id="spPl"><b><svg><use href="#i-plane"/></svg></b></i></div>
    <div class="stg" id="spStg"><i></i><i></i><i></i><i></i><i></i></div></div>
</div>

<div class="app">
  <div class="hd0">
    <header class="hdr">
      <div class="hrow">
        <div class="ava"><span id="ava"></span></div>
        <div class="who"><b id="uName">—</b><small><i class="dot"></i><span>آنلاین · ثبتِ خودکار</span></small></div>
        <div class="bal" id="balBtn"><span id="bal">…</span><em>تومان</em></div>
      </div>
    </header>
  </div>

  <section class="pg on" id="pg-home">
    <div class="hero"><div class="in">
      <div class="art" aria-hidden="true"><i class="o2"></i><i class="o1"></i><i class="rp"></i><i class="st"></i>
        <svg viewBox="0 0 120 120"><defs><linearGradient id="gp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C4B5FD"/><stop offset="1" stop-color="#7C3AED"/></linearGradient></defs>
        <circle cx="60" cy="60" r="44" fill="url(#gp)"/><path d="M34 58.5 84 39c2.3-.9 4.3.6 3.6 3.8l-8.5 40c-.6 2.8-2.3 3.5-4.7 2.2l-13-9.6-6.3 6c-.7.7-1.3 1.3-2.6 1.3l.9-13.3 24.3-22c1-.9-.2-1.4-1.6-.5l-30 18.9-12.9-4c-2.8-.9-2.9-2.8.6-4.2z" fill="#fff"/></svg></div>
      <div class="tx">
        <span class="kick"><i></i>آنلاین · شروعِ خودکار</span>
        <h1 id="hTitle">__TITLE__</h1>
        <p id="hTag">__TAG__</p>
      </div>
      <button class="go" data-go="list"><svg><use href="#i-plane"/></svg>ثبت سفارش</button>
      <div class="kpis"><div><b id="kN">—</b><small>سرویسِ فعال</small></div><div><b id="kF">—</b><small>شروع از / ۱۰۰۰</small></div><div><b>۲۴/۷</b><small>ثبتِ خودکار</small></div></div>
    </div></div>
    <div class="tick"><div class="tr" id="tick"></div></div>
    <div class="hd"><h3>دسته‌بندی‌ها</h3></div>
    <div class="bento" id="bento"></div>
    <div class="links" id="xl"></div>
  </section>

  <section class="pg" id="pg-list">
    <div class="srch"><svg><use href="#i-search"/></svg><input id="q" type="search" placeholder="جست‌وجوی سرویس…" autocomplete="off"></div>
    <div class="chips" id="cchips"></div>
    <div class="list" id="slist"></div>
  </section>

  <section class="pg" id="pg-orders">
    <div class="hd" style="margin-top:4px"><h3>سفارش‌های من</h3><button id="oRef"><svg><use href="#i-refresh"/></svg>تازه کن</button></div>
    <div id="olist"></div>
  </section>

</div>

<nav class="tabs" id="tabs">
  <span class="ind" id="ind"></span>
  <button data-go="home" class="on"><svg><use href="#i-home"/></svg>خانه</button>
  <button data-go="list"><svg><use href="#i-grid"/></svg>سرویس‌ها</button>
  <button data-go="orders"><svg><use href="#i-list"/></svg>سفارش‌ها<span class="bd" id="ordN"></span></button>
</nav>
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
var SPM = ['در حال اتصالِ امن…', 'دریافتِ سرویس‌های تلگرام…', 'به‌روزرسانیِ قیمت‌ها…', 'بررسیِ کیف پول…', 'آماده‌سازیِ باند…', 'آماده‌ی پرواز'];
function splStep(){
  if (SPL.gone) return false;
  var el = Date.now() - SPL.t0, p = SPL.min ? Math.min(1, el / SPL.min) : 1;
  var pc = $('spPct'), bar = $('spBar'), pl = $('spPl'), tr = $('spTrk'), m = $('spMsg'), g = $('spStg');
  if (pc) pc.textContent = Math.floor(p * 100) + '%';
  if (RM || !SPL.anim) { if (bar) bar.style.transform = 'scaleX(' + p.toFixed(4) + ')'; if (pl) pl.style.transform = 'translate3d(' + (-p * 100).toFixed(2) + '%,0,0)'; }
  var mi = Math.min(SPM.length - 1, Math.floor(p * (SPM.length - 1) + (p >= 1 ? 1 : 0)));
  if (m && SPL.mi !== mi) { SPL.mi = mi; m.textContent = SPM[mi]; }
  if (g) [].forEach.call(g.children, function(x, k){ x.classList.toggle('on', p * g.children.length >= k + 1 - 0.001); });
  return true;
}
(function(){
  var bar = $('spBar'), pl = $('spPl'), el = Date.now() - SPL.t0;
  if (!bar || !pl || RM || SPL.min <= el) return;
  bar.style.animation = 'spb ' + SPL.min + 'ms linear ' + (-el) + 'ms both';
  pl.style.animation = 'spw ' + SPL.min + 'ms linear ' + (-el) + 'ms both'; SPL.anim = true;
})();
splStep();
SPL.iv = setInterval(function(){ if (!splStep()) clearInterval(SPL.iv); }, 100);
function hideSplash(now){
  if (SPL.gone || (SPL.hiding && !now)) return;
  SPL.hiding = true;
  var wait = now ? 0 : Math.max(0, SPL.min - (Date.now() - SPL.t0));
  setTimeout(function(){
    if (SPL.gone) return;
    SPL.anim = false; var bar = $('spBar'), pl = $('spPl'); if (bar) bar.style.animation = 'none'; if (pl) pl.style.animation = 'none';
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
  g.innerHTML = ico('plane') + '<b>از داخل ربات باز کنید</b><p>' + esc(msg || 'این صفحه فقط از داخل ربات تلگرام باز می‌شود.') + '</p>' +
    (B.bot ? '<button class="btn" style="max-width:260px">رفتن به ربات</button>' : '');
  D.body.appendChild(g);
  var b = g.querySelector('button'); if (b) b.onclick = function(){ openLink('https://t.me/' + B.bot); };
}

var CATS = B.cats || [], ITEMS = B.items || [], CAT = {}, ITEM = {};
CATS.forEach(function(c){ CAT[c.id] = c; });
ITEMS.forEach(function(i){ ITEM[i.i] = i; i.k = String(i.n || '').toLowerCase(); });
var HINT = { members: ['لینک یا آیدیِ کانال/گروه', 't.me/mychannel'], views: ['لینکِ پست', 't.me/mychannel/125'],
  reactions: ['لینکِ پست', 't.me/mychannel/125'], votes: ['لینکِ پستِ نظرسنجی', 't.me/mychannel/125'],
  comments: ['لینکِ پست', 't.me/mychannel/125'], premium: ['لینکِ کانال', 't.me/mychannel'], story: ['لینکِ استوری', 't.me/mychannel/s/12'],
  other: ['لینک', 't.me/…'] };
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
  var btns = D.querySelectorAll('#tabs button'), idx = 0;
  [].forEach.call(btns, function(b, i){ var on = b.getAttribute('data-go') === p; b.classList.toggle('on', on); if (on) idx = i; });
  $('ind').style.transform = 'translate3d(' + (-idx * 100) + '%,0,0)';
  window.scrollTo(0, 0);
  clearTimeout(S.poll);
  if (p === 'list') drawList();
  if (p === 'orders') loadOrders();
  backBtn();
}
function goBack(){
  if (S.sheet) { closeSheet(); return; }
  var p = S.stack.pop(); go(p || 'home', true);
}
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

function nm(n){ n = String(n || ''); var k = n.indexOf(' — '); return { t: k > 0 ? n.slice(0, k) : n, f: k > 0 ? n.slice(k + 3).split(' · ').filter(Boolean) : [] }; }
function ttl(i){ return i.f ? i.n : nm(i.n).t; }
function emN(s){ return String(s || '').replace(/[\uFE0E\uFE0F]/g, '').replace(/\uD83C[\uDFFB-\uDFFF]/g, '').trim(); }
function faS(n){ n = Number(n) || 0; if (n >= 1e6) return faD(String(Math.round(n / 1e5) / 10)).replace('.', '٫') + ' میلیون'; if (n >= 1e4) return fa(Math.round(n / 1000)) + ' هزار'; return fa(n); }
var FT = [[/^بدونِ ضمانت/, 'shield', 'no'], [/ضمانت|ریزش/, 'shield', 'ok'], [/^شروع/, 'bolt', 'hot'], [/^سقف/, 'chart', ''],
          [/^سرعت/, 'gauge', ''], [/پست/, 'list', ''], [/(روزه|دقیقه)$/, 'clock', '']];
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
var TICK = [['bolt', 'شروعِ خودکار در چند دقیقه'], ['shield', 'بدونِ نیاز به رمز'], ['refresh', 'ضمانتِ ریزش در سرویس‌های مشخص'],
            ['chart', 'گزارشِ لحظه‌ایِ پیشرفت'], ['star', 'قیمتِ شفاف، بدونِ هزینه‌ی پنهان']];
function drawHome(){
  var th = TICK.map(function(t){ return '<span>' + ico(t[0]) + esc(t[1]) + '</span>'; }).join('');
  $('tick').innerHTML = th + th;
  countUp($('kN'), ITEMS.length);
  var min = 0; ITEMS.forEach(function(i){ if (!min || i.p < min) min = i.p; });
  if (min) countUp($('kF'), min); else $('kF').textContent = '—';
  $('bento').innerHTML = CATS.length ? CATS.map(function(c, k){
    return '<button class="bt" style="--i:' + k + '" data-go="list" data-cat="' + esc(c.id) + '"><span class="ic">' + ico(c.ic) + '</span>' +
      '<span><b>' + esc(c.n) + '</b><small>' + fa(c.c) + ' محصول</small><span class="pr">از ' + fa(c.f) + ' تومان</span></span></button>';
  }).join('') : '<div class="emp" style="grid-column:1/-1">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>';

  var xl = '';
  if ((B.links || {}).ig) xl += '<button class="xl ig" data-open="ig"><span class="ic">' + ico('camera') + '</span><span>خدمات اینستاگرام<small>فالوور، لایک، ویو و کامنت</small></span>' + ico('chev', 'ch') + '</button>';
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
  $('cchips').innerHTML = '<button data-c="" class="' + (S.cat === '' ? 'on' : '') + '">همه</button>' + CATS.map(function(c){
    return '<button data-c="' + esc(c.id) + '" class="' + (S.cat === c.id ? 'on' : '') + '">' + ico(c.ic) + esc(c.n) + '</button>';
  }).join('');
  var q = S.q.trim().toLowerCase();
  var l = ITEMS.filter(function(i){ return (!S.cat || i.c === S.cat) && (!q || i.k.indexOf(q) >= 0); });
  $('slist').innerHTML = l.length ? listHtml(l) : (ITEMS.length
    ? '<div class="emp">' + ico('search') + '<b>چیزی پیدا نشد</b>دسته یا کلمه‌ی دیگری امتحان کنید.</div>'
    : '<div class="emp">' + ico('spark') + '<b>به‌زودی</b>سرویس‌ها به‌زودی اضافه می‌شوند.</div>');
}
$('cchips').addEventListener('click', function(ev){ var b = ev.target.closest('[data-c]'); if (!b) return; tap(); S.cat = b.getAttribute('data-c'); drawList(true); });
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
function niceQty(i){
  var c = [1000, 500, 100, 5000, 10000]; for (var k = 0; k < c.length; k++) if (c[k] >= i.mn && c[k] <= i.mx) return c[k];
  return i.mn;
}
function linkOk(v){ return /^(@[A-Za-z][A-Za-z0-9_]{3,31}|(https?:\/\/)?(www\.)?(t|telegram)\.(me|dog)\/\S+)$/i.test(v.trim()); }
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
    '<div class="fld"><label>' + esc(h[0]) + '</label><input id="oLink" class="ltr" placeholder="' + esc(h[1]) + '" autocomplete="off" autocapitalize="off" spellcheck="false"><small id="oLinkH">کانال یا گروه باید عمومی باشد</small></div>' +
    (i.y === 'poll' ? '<div class="fld"><label>رای به کدام گزینه برود؟</label><div class="qchips" id="aC">' +
      [1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(function(v){ return '<button data-a="' + v + '">گزینه‌ی ' + fa(v) + '</button>'; }).join('') +
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
    '<button class="btn" id="oGo">' + ico('plane') + '<span id="oGoT">پرداخت و ثبتِ سفارش</span></button>';
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
    else { hs.textContent = 'کانال یا گروه باید عمومی باشد'; hs.className = ''; } };
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
  if (!l.length) { $('olist').innerHTML = '<div class="emp">' + ico('list') + '<b>هنوز سفارشی ندارید</b>اولین سفارش‌تان را از «سرویس‌ها» ثبت کنید.<button class="btn" data-go="list">' + ico('grid') + 'دیدنِ سرویس‌ها</button></div>'; return; }
  $('olist').innerHTML = l.map(function(o, k){
    var c = CAT[o.c] || {};
    return '<div class="or" style="--i:' + Math.min(k, 10) + '"><div class="h"><span class="ic">' + ico(c.ic || 'spark') + '</span><div><b dir="auto">' + esc(nm(o.n).t) + '</b><small>' + ago(o.at) + ' · ' + fa(o.t) + ' تومان</small></div>' +
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
  try { TG.setHeaderColor && TG.setHeaderColor('#07051A'); TG.setBackgroundColor && TG.setBackgroundColor('#07051A'); TG.setBottomBarColor && TG.setBottomBarColor('#07051A'); } catch(e){}
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
