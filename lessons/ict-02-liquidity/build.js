'use strict';
// Builds the "ICT Lesson 2 — Liquidity" PDF.
// Usage: node build.js [out.pdf] [--png <dir>]
const fs = require('fs');
const os = require('os');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright');
const F = require('./figures');
const { mix } = require('./charts');

const FONT_DIR = path.join(__dirname, 'fonts');
const TOTAL = 6;
const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const fontUrl = (f) => pathToFileURL(path.join(FONT_DIR, f)).href;

// Solid colours only (no alpha, gradients or shadows) so phone PDF viewers render fast and sharp.
const BG = '#070B12';
const CARD = '#101826';
const WARN = '#FF6B81';
const acc = (c, base = CARD) =>
  `--acc:${c};--acc-bg:${mix(c, 0.1, base)};--acc-note:${mix(c, 0.09, base)};--acc-line:${mix(c, 0.45, base)};--acc-ring:${mix(c, 0.25, base)};--acc-dim:${mix(c, 0.3, base)}`;

const ICON = {
  info: '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="10" width="2" height="7" rx="1" fill="currentColor"/><circle cx="12" cy="7" r="1.3" fill="currentColor"/></svg>',
  warn: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 3 L22 20 H2 Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><rect x="11" y="9" width="2" height="6" rx="1" fill="currentColor"/><circle cx="12" cy="17.2" r="1.2" fill="currentColor"/></svg>',
  ex: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M4 18 L9 11 L13 14 L20 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="20" cy="5" r="2" fill="currentColor"/></svg>',
  up: '<svg viewBox="0 0 12 12" width="9" height="9"><path d="M6 1 L11 10 H1 Z" fill="currentColor"/></svg>',
  arrow: '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M20 12 H5 M11 6 L5 12 L11 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  down: '<svg viewBox="0 0 12 12" width="9" height="9"><path d="M6 11 L11 2 H1 Z" fill="currentColor"/></svg>',
};

const header = () => `
<header class="ph">
  <div class="ph-r"><span class="ict">ICT</span><span>درس دوم</span><i></i><span class="ph-t">نقدینگی</span></div>
  <div class="ph-l">LIQUIDITY · LESSON 02</div>
</header>`;

const footer = (n) => `
<footer class="pf">
  <span>سری آموزشی ICT · محتوای آموزشی است و توصیه معاملاتی محسوب نمی‌شود</span>
  <span class="pn">${fa(n)} / ${fa(TOTAL)}</span>
</footer>`;

function card({ num, title, en, abbr, color, loc, locIcon, body, fig, note, noteType = 'info', noteTitle }) {
  return `
<section class="card" style="${acc(color)}">
  <div class="card-h">
    <div class="num">${fa(num)}</div>
    <div class="ttl">
      <h2>${title}</h2>
      <div class="en"><b>${abbr}</b>${en ? `<span>${en}</span>` : ''}</div>
    </div>
    <div class="loc">${locIcon ? ICON[locIcon] : ''}${loc}</div>
  </div>
  ${body.map((p) => `<p>${p}</p>`).join('')}
  <div class="fig">${fig}</div>
  <div class="note" style="${acc(noteType === 'warn' ? WARN : color)}"><span class="ni">${ICON[noteType]}</span><div>${noteTitle ? `<b>${noteTitle}</b> ` : ''}${note}</div></div>
</section>`;
}

const page = (n, inner, cls = '') => `<div class="page ${cls}">${n > 1 ? header() : ''}${inner}${footer(n)}</div>`;

const toc = [
  ['BSL', 'نقدینگی سمت خرید', 2, '#FFB020'],
  ['SSL', 'نقدینگی سمت فروش', 2, '#3DA9FC'],
  ['EQH', 'سقف‌های برابر', 3, '#FFB020'],
  ['EQL', 'کف‌های برابر', 3, '#3DA9FC'],
  ['Sweep', 'نقدینگی‌گیری', 4, '#F472B6'],
  ['Breakout', 'شکست سطح', 4, '#22D3EE'],
  ['IRL', 'نقدینگی داخلی', 5, '#A78BFA'],
  ['PDH · PDL', 'سقف و کف روز قبل', 5, '#FB923C'],
];

const cover = page(1, `
<div class="cv-top">
  <span class="hash">#آموزشی</span>
  <span class="cv-series">ICT SERIES · LESSON 02</span>
</div>
<div class="cv-title">
  <div class="cv-kicker"><span class="ict">ICT</span> درس دوم</div>
  <h1>نقدینگی</h1>
  <div class="cv-en">LIQUIDITY</div>
  <div class="cv-sub">شناخت نواحی نقدینگی روی نمودار: از BSL و SSL تا Sweep، Breakout و PDH/PDL</div>
</div>
<div class="fig hero">${F.hero()}</div>
<div class="intro">
  <div class="intro-h">مقدمه</div>
  <p>نقدینگی یکی از مفاهیمی است که اگر به‌درستی آموخته شود، کمک می‌کند بهتر درک کنیم چرا قیمت به برخی سقف‌ها و کف‌ها واکنش نشان می‌دهد. قرار نیست هرجا قیمت به یک سقف رسید، فوراً وارد معامله فروش شویم یا با رسیدن به کف به دنبال خرید باشیم. ابتدا باید مشخص کنیم با چه ناحیه‌ای روبه‌رو هستیم و قیمت در آن ناحیه چه رفتاری از خود نشان می‌دهد.</p>
</div>
<div class="toc-h">در این درس</div>
<div class="toc">
  ${toc.map(([a, t, p, c], i) => `<div class="toc-i" style="${acc(c, BG)}"><span class="toc-n">${fa(i + 1)}</span><div><b>${a}</b><span>${t}</span></div><span class="toc-p">ص ${fa(p)}</span></div>`).join('')}
</div>
`, 'cover');

const p2 = page(2, `
${card({
  num: 1, title: 'نقدینگی سمت خرید', en: 'Buy-Side Liquidity', abbr: 'BSL', color: '#FFB020', loc: 'بالای سقف‌های مهم', locIcon: 'up',
  body: ['BSL معمولاً در <b>بالای سقف‌های مهم</b> بازار بررسی می‌شود. ممکن است در بالای این سقف‌ها، حد ضرر فروشندگان و سفارش‌های خرید معامله‌گرانی که منتظر شکست سطح هستند، انباشته شده باشد.'],
  fig: F.bsl(),
  noteType: 'ex', noteTitle: 'مثال:',
  note: 'قیمت چند بار به یک سقف می‌رسد و نمی‌تواند از آن عبور کند. ناحیه بالای آن سقف را به‌عنوان ناحیه احتمالی نقدینگی سمت خرید علامت‌گذاری می‌کنیم.',
})}
${card({
  num: 2, title: 'نقدینگی سمت فروش', en: 'Sell-Side Liquidity', abbr: 'SSL', color: '#3DA9FC', loc: 'پایین کف‌های مهم', locIcon: 'down',
  body: ['SSL معمولاً در <b>پایین کف‌های مهم</b> بازار قرار می‌گیرد. در پایین این کف‌ها ممکن است حد ضرر خریداران و سفارش‌های فروش معامله‌گرانی که منتظر شکست کف هستند، وجود داشته باشد.'],
  fig: F.ssl(),
  noteType: 'warn', noteTitle: 'به خاطر بسپار:',
  note: 'BSL را بالای سقف‌ها و SSL را پایین کف‌ها بررسی می‌کنیم؛ اما این نواحی به‌تنهایی جهت حرکت بعدی قیمت را مشخص نمی‌کنند.',
})}
`);

const p3 = page(3, `
${card({
  num: 3, title: 'سقف‌های برابر', en: 'Equal Highs', abbr: 'EQH', color: '#FFB020', loc: 'بالای سقف‌های برابر', locIcon: 'up',
  body: ['هنگامی که قیمت چند بار به محدوده‌ای نزدیک به هم می‌رسد و از آن بازمی‌گردد، ممکن است سقف‌های برابر یا EQH تشکیل شده باشند. بالای این سقف‌ها می‌تواند <b>ناحیه احتمالی نقدینگی</b> باشد.'],
  fig: F.eqh(),
  noteType: 'info', noteTitle: 'نکته:',
  note: 'لازم نیست سقف‌ها دقیقاً روی یک قیمت باشند؛ مهم این است که ساختار کلی و نزدیکی سطوح به یکدیگر را درست بررسی کنید.',
})}
${card({
  num: 4, title: 'کف‌های برابر', en: 'Equal Lows', abbr: 'EQL', color: '#3DA9FC', loc: 'پایین کف‌های برابر', locIcon: 'down',
  body: ['EQL عکس EQH است. هنگامی که قیمت چند بار به محدوده‌ای نزدیک به هم می‌رسد و نمی‌تواند پایین‌تر برود، <b>کف‌های برابر</b> شکل می‌گیرند.'],
  fig: F.eql(),
  noteType: 'info', noteTitle: 'نکته:',
  note: 'پایین این کف‌ها می‌تواند ناحیه احتمالی نقدینگی سمت فروش (SSL) باشد.',
})}
`);

const p4 = page(4, `
${card({
  num: 5, title: 'نقدینگی‌گیری', en: 'Liquidity Sweep', abbr: 'Sweep', color: '#F472B6', loc: 'عبور و بازگشت',
  body: ['گاهی قیمت از یک سقف یا کف عبور می‌کند، اما دوباره به محدوده قبلی بازمی‌گردد. این رفتار می‌تواند نشانه‌ای از <b>نقدینگی‌گیری</b> باشد.'],
  fig: F.sweep(),
  noteType: 'warn', noteTitle: 'هشدار:',
  note: 'صرفِ عبور قیمت از سطح و بازگشت آن، به معنای ورود قطعی نیست. باید رفتار کندل‌ها، ساختار بازار و نشانه‌های تأیید را نیز بررسی کنیم.',
})}
${card({
  num: 6, title: 'شکست سطح', en: '', abbr: 'Breakout', color: '#22D3EE', loc: 'عبور و ادامه حرکت',
  body: ['در شکست، قیمت از یک سطح عبور می‌کند و ممکن است حرکتش را در همان جهت ادامه دهد. برای بررسی شکست، به <b>نحوه بسته‌شدن کندل‌ها</b>، <b>قدرت حرکت</b> و <b>رفتار قیمت پس از عبور</b> از سطح توجه کنید.'],
  fig: F.breakout(),
  noteType: 'info', noteTitle: 'تفاوت اصلی:',
  note: 'در نقدینگی‌گیری، قیمت پس از عبور به محدوده قبلی بازمی‌گردد؛ اما در شکست، احتمال ادامه حرکت در سمت جدید وجود دارد. هیچ‌کدام را فقط با دیدن یک کندل قطعی فرض نکنید.',
})}
`);

const p5 = page(5, `
${card({
  num: 7, title: 'نقدینگی داخلی', en: 'Internal Range Liquidity', abbr: 'IRL', color: '#A78BFA', loc: 'داخل محدوده مرجع',
  body: ['IRL به نواحی نقدینگی <b>داخل محدوده‌ای</b> اشاره دارد که برای تحلیل انتخاب کرده‌ایم. ابتدا باید سقف و کف محدوده مرجع را مشخص کنیم و سپس نواحی داخل آن را بررسی کنیم.'],
  fig: F.irl(),
  noteType: 'info', noteTitle: 'ترتیب کار:',
  note: '۱) تعیین سقف و کف محدوده مرجع ← ۲) بررسی سقف‌ها و کف‌های داخلی. نقدینگی بیرون از مرزهای محدوده را ERL می‌نامند.',
})}
${card({
  num: 8, title: 'سقف و کف روز قبل', en: 'Previous Day High / Low', abbr: 'PDH · PDL', color: '#FB923C', loc: 'سطوح روز قبل',
  body: ['<b>PDH</b> یعنی بالاترین قیمت روز قبل و <b>PDL</b> یعنی پایین‌ترین قیمت روز قبل. این دو سطح را روی نمودار مشخص کنید و وقتی قیمت به آن‌ها رسید، واکنشش را زیر نظر بگیرید.'],
  fig: F.pdhpdl(),
  noteType: 'warn', noteTitle: 'به خاطر بسپار:',
  note: 'برخورد با این سطوح به‌تنهایی سیگنال خرید یا فروش نیست.',
})}
`);

const glossary = [
  ['BSL', 'Buy-Side Liquidity', 'نقدینگی سمت خرید', 'بالای سقف‌های مهم'],
  ['SSL', 'Sell-Side Liquidity', 'نقدینگی سمت فروش', 'پایین کف‌های مهم'],
  ['EQH', 'Equal Highs', 'سقف‌های برابر', 'بالای سقف‌های نزدیک به هم'],
  ['EQL', 'Equal Lows', 'کف‌های برابر', 'پایین کف‌های نزدیک به هم'],
  ['IRL', 'Internal Range Liquidity', 'نقدینگی داخلی', 'داخل محدوده مرجع'],
  ['PDH', 'Previous Day High', 'سقف روز قبل', 'بالاترین قیمت روز قبل'],
  ['PDL', 'Previous Day Low', 'کف روز قبل', 'پایین‌ترین قیمت روز قبل'],
];

const p6 = page(6, `
<div class="sum-h">
  <h2>جمع‌بندی</h2><span>SUMMARY</span>
</div>
<div class="sum-box">
  <p>نقدینگی کمک می‌کند نواحی مهم نمودار را بهتر بشناسیم؛ اما <b>تشخیص سطح</b> با <b>پیدا کردن یک موقعیت معاملاتی معتبر</b> فرق دارد.</p>
</div>
<div class="steps">
  <div class="step" style="${acc('#FFB020', BG)}"><span class="sn">۱</span><b>سطح را پیدا کن</b><small>BSL · SSL · EQH · EQL · IRL · PDH · PDL</small></div>
  <div class="arr">${ICON.arrow}</div>
  <div class="step" style="${acc('#F472B6', BG)}"><span class="sn">۲</span><b>رفتار قیمت را بررسی کن</b><small>نقدینگی‌گیری یا شکست؟ رفتار کندل‌ها</small></div>
  <div class="arr">${ICON.arrow}</div>
  <div class="step" style="${acc('#22D3EE', BG)}"><span class="sn">۳</span><b>با ساختار بازار تصمیم بگیر</b><small>ساختار بازار و نشانه‌های تأیید</small></div>
</div>
<h3 class="sec">نقدینگی‌گیری در برابر شکست</h3>
<div class="vs">
  <div class="vs-c" style="${acc('#F472B6')}">
    <div class="vs-h"><b>نقدینگی‌گیری</b><span>Liquidity Sweep</span></div>
    <div class="fig">${F.miniSweep()}</div>
    <ul>
      <li><span>عبور از سطح</span><b>بله</b></li>
      <li><span>پس از عبور</span><b>بازگشت به محدوده قبلی</b></li>
      <li><span>برداشت</span><b>نشانه احتمالی جمع‌آوری نقدینگی</b></li>
    </ul>
  </div>
  <div class="vs-mid">VS</div>
  <div class="vs-c" style="${acc('#22D3EE')}">
    <div class="vs-h"><b>شکست سطح</b><span>Breakout</span></div>
    <div class="fig">${F.miniBreakout()}</div>
    <ul>
      <li><span>عبور از سطح</span><b>بله</b></li>
      <li><span>پس از عبور</span><b>احتمال ادامه حرکت در سمت جدید</b></li>
      <li><span>بررسی</span><b>بسته‌شدن کندل، قدرت حرکت</b></li>
    </ul>
  </div>
</div>
<div class="note wide" style="${acc(WARN, BG)}"><span class="ni">${ICON.warn}</span><div><b>هیچ‌کدام را</b> فقط با دیدن یک کندل قطعی فرض نکن. اول سطح، بعد رفتار قیمت، و در نهایت تصمیم بر اساس ساختار بازار.</div></div>
<h3 class="sec">واژه‌نامه</h3>
<table class="gl">
  <thead><tr><th>اصطلاح</th><th>نام کامل</th><th>معنی</th><th>محل بررسی</th></tr></thead>
  <tbody>${glossary.map(([a, e, t, w]) => `<tr><td><b>${a}</b></td><td class="lt">${e}</td><td>${t}</td><td>${w}</td></tr>`).join('')}</tbody>
</table>
<p class="disc">نمودارهای این جزوه نمایشی‌اند و از داده واقعی بازار ساخته نشده‌اند. این محتوا صرفاً آموزشی است و پیشنهاد خرید یا فروش هیچ دارایی‌ای نیست.</p>
`);

const css = `
@font-face { font-family: Vazirmatn; font-weight: 400; src: url(${fontUrl('Vazirmatn-Regular.ttf')}); }
@font-face { font-family: Vazirmatn; font-weight: 500 600; src: url(${fontUrl('Vazirmatn-Medium.ttf')}); }
@font-face { font-family: Vazirmatn; font-weight: 700 800; src: url(${fontUrl('Vazirmatn-Bold.ttf')}); }
@font-face { font-family: Vazirmatn; font-weight: 900; src: url(${fontUrl('Vazirmatn-Black.ttf')}); }
@page { size: A4; margin: 0; }
:root {
  --bg: ${BG}; --card: ${CARD}; --line: ${mix('#94A3B8', 0.15, CARD)}; --line-bg: ${mix('#94A3B8', 0.15, BG)};
  --text: #E8ECF4; --soft: #C3CCDA; --muted: #8592A8; --gold: #FFB020;
  --chip-bg: ${mix('#ffffff', 0.04, BG)}; --row-bg: ${mix('#ffffff', 0.04, CARD)}; --head-bg: ${mix('#ffffff', 0.05, BG)};
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: var(--bg); }
body { font-family: Vazirmatn, sans-serif; color: var(--text); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.page {
  width: 210mm; height: 297mm; position: relative; overflow: hidden; direction: rtl; background: var(--bg);
  padding: 11mm 11mm 15mm; break-after: page; display: flex; flex-direction: column; gap: 4.2mm;
}
.page:last-child { break-after: auto; }
.ict { display: inline-block; font-weight: 900; font-size: 8.5pt; letter-spacing: .08em; color: #0B111C; background: var(--gold); padding: .5mm 2.2mm; border-radius: 1.4mm; direction: ltr; }
.ph { display: flex; justify-content: space-between; align-items: center; padding-bottom: 2.6mm; border-bottom: 1px solid var(--line-bg); position: relative; }
.ph::after { content: ''; position: absolute; right: 0; bottom: -1px; width: 32mm; height: 2px; background: var(--gold); }
.ph-r { display: flex; align-items: center; gap: 2.4mm; font-size: 9.5pt; color: var(--soft); font-weight: 600; }
.ph-r i { width: 1.2mm; height: 1.2mm; border-radius: 50%; background: var(--muted); }
.ph-t { color: var(--text); font-weight: 800; }
.ph-l { direction: ltr; font-size: 7.5pt; letter-spacing: .22em; color: var(--muted); font-weight: 700; }
.pf { position: absolute; left: 11mm; right: 11mm; bottom: 6.5mm; display: flex; justify-content: space-between; align-items: center; font-size: 7.4pt; color: var(--muted); }
.pn { font-weight: 800; color: var(--soft); background: var(--chip-bg); border: 1px solid var(--line-bg); border-radius: 10mm; padding: .4mm 3mm; }

/* cards */
.card {
  flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; gap: 2.4mm; position: relative;
  background: var(--card); border: 1px solid var(--line); border-top: 2px solid var(--acc-dim); border-radius: 4.5mm; padding: 4.2mm 5mm 4.4mm;
}
.card::before { content: ''; position: absolute; top: -2px; right: 8mm; width: 40mm; height: 2px; background: var(--acc); }
.card-h { display: flex; align-items: center; gap: 3.4mm; }
.num { width: 10.5mm; height: 10.5mm; border-radius: 3mm; display: grid; place-items: center; font-weight: 900; font-size: 16pt; color: #0B111C; background: var(--acc); outline: 1.1mm solid var(--acc-ring); flex: none; }
.ttl { flex: 1; }
.ttl h2 { margin: 0; font-size: 15pt; font-weight: 900; line-height: 1.25; }
.en { display: flex; gap: 2mm; align-items: center; direction: ltr; justify-content: flex-end; font-size: 8pt; color: var(--muted); font-weight: 600; letter-spacing: .03em; }
.en b { color: var(--acc); font-weight: 900; letter-spacing: .06em; }
.loc { display: flex; align-items: center; gap: 1.6mm; font-size: 8.4pt; font-weight: 700; color: var(--acc); border: 1px solid var(--acc-line); background: var(--acc-bg); padding: 1mm 3mm; border-radius: 10mm; white-space: nowrap; }
.card p { margin: 0; font-size: 10pt; line-height: 1.95; color: var(--soft); text-align: justify; }
.card p b, .sum-box b { color: var(--text); font-weight: 800; }
.fig { border-radius: 3mm; border: 1px solid var(--line); line-height: 0; background: #0B111C; overflow: hidden; }
.card .fig { flex: 1 1 auto; min-height: 0; display: flex; }
.card .fig svg { width: 100%; height: 100%; }
.fig svg { display: block; width: 100%; height: auto; }
.note { display: flex; gap: 2.4mm; align-items: flex-start; font-size: 9pt; line-height: 1.8; color: var(--soft); padding: 2.2mm 3.2mm; border-radius: 2.4mm; background: var(--acc-note); border-right: 3px solid var(--acc); }
.note .ni { color: var(--acc); flex: none; margin-top: .9mm; }
.note b { color: var(--acc); font-weight: 800; }

/* cover */
.cover { gap: 5.5mm; }
.cv-top { display: flex; justify-content: space-between; align-items: center; }
.hash { font-weight: 800; font-size: 10pt; color: var(--gold); border: 1px solid ${mix('#FFB020', 0.45, BG)}; background: ${mix('#FFB020', 0.08, BG)}; border-radius: 10mm; padding: 1mm 4mm; }
.cv-series { direction: ltr; font-size: 8pt; letter-spacing: .25em; color: var(--muted); font-weight: 700; }
.cv-title { padding-right: 5mm; border-right: 3px solid var(--gold); }
.cv-kicker { font-size: 13pt; font-weight: 800; color: var(--soft); display: flex; align-items: center; gap: 2.5mm; }
.cv-title h1 { margin: 0; font-size: 56pt; font-weight: 900; line-height: 1.25; letter-spacing: -.01em; color: #FFC54D; }
.cv-en { direction: ltr; text-align: right; font-size: 15pt; letter-spacing: .5em; font-weight: 900; color: #4A5468; margin-top: -2mm; }
.cv-sub { margin-top: 2.5mm; font-size: 10.5pt; color: var(--soft); font-weight: 500; }
.hero { border-radius: 4mm; border-color: ${mix('#FFB020', 0.3, BG)}; }
.toc-h { font-weight: 900; font-size: 11pt; color: var(--soft); margin-bottom: -2.5mm; display: flex; align-items: center; gap: 2.4mm; }
.toc-h::before { content: ''; width: 1.2mm; height: 4.5mm; border-radius: 1mm; background: var(--gold); }
.intro { background: var(--card); border: 1px solid var(--line); border-radius: 4mm; padding: 4mm 5mm; }
.intro-h { font-weight: 900; font-size: 11pt; color: var(--gold); margin-bottom: 1mm; display: flex; align-items: center; gap: 2mm; }
.intro-h::before { content: ''; width: 2mm; height: 2mm; border-radius: 50%; background: var(--gold); outline: 1mm solid ${mix('#FFB020', 0.25, CARD)}; }
.intro p { margin: 0; font-size: 10.3pt; line-height: 2; color: var(--soft); text-align: justify; }
.toc { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2.6mm; }
.toc-i { display: flex; align-items: center; gap: 2.4mm; padding: 3.4mm 3mm; border-radius: 3mm; background: var(--chip-bg); border: 1px solid var(--line-bg); border-top: 2px solid var(--acc); }
.toc-n { width: 6.5mm; height: 6.5mm; border-radius: 2mm; display: grid; place-items: center; font-weight: 900; font-size: 9.5pt; color: #0B111C; background: var(--acc); flex: none; }
.toc-i div { flex: 1; display: flex; flex-direction: column; line-height: 1.5; min-width: 0; }
.toc-i b { font-size: 9pt; color: var(--acc); font-weight: 900; direction: ltr; text-align: right; }
.toc-i div span { font-size: 8pt; color: var(--soft); white-space: nowrap; }
.toc-p { font-size: 7.5pt; color: var(--muted); align-self: flex-end; white-space: nowrap; }

/* summary */
.sum-h { display: flex; align-items: baseline; gap: 3mm; margin: 0 0 -2mm; }
.sum-h h2 { margin: 0; font-size: 26pt; font-weight: 900; color: var(--gold); line-height: 1.3; }
.sum-h span { direction: ltr; font-size: 9pt; letter-spacing: .4em; color: var(--muted); font-weight: 800; }
.sum-box { border: 1px solid ${mix('#FFB020', 0.35, BG)}; background: ${mix('#FFB020', 0.07, BG)}; border-right: 3px solid var(--gold); border-radius: 3.5mm; padding: 3.4mm 5mm; }
.sum-box p { margin: 0; font-size: 11pt; line-height: 1.95; color: var(--soft); }
.steps { display: flex; align-items: stretch; gap: 2mm; }
.step { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.3mm; padding: 3.6mm 2.5mm 3.2mm; border-radius: 3.5mm; background: var(--acc-note); border: 1px solid var(--acc-line); }
.sn { width: 9mm; height: 9mm; border-radius: 50%; display: grid; place-items: center; font-weight: 900; font-size: 13pt; color: #0B111C; background: var(--acc); outline: 1.3mm solid var(--acc-ring); margin-bottom: 1mm; }
.step b { font-size: 10.6pt; font-weight: 900; }
.step small { font-size: 7.8pt; color: var(--muted); line-height: 1.6; }
.arr { align-self: center; color: var(--muted); line-height: 0; }
.sec { margin: 1mm 0 0; font-size: 12pt; font-weight: 900; display: flex; align-items: center; gap: 2.4mm; }
.sec::before { content: ''; width: 1.2mm; height: 5mm; border-radius: 1mm; background: var(--gold); }
.vs { display: flex; gap: 3mm; align-items: stretch; }
.vs-c { flex: 1; border-radius: 3.5mm; border: 1px solid var(--line-bg); background: var(--card); padding: 3mm; display: flex; flex-direction: column; gap: 2.2mm; border-top: 2.5px solid var(--acc); }
.vs-h { display: flex; justify-content: space-between; align-items: baseline; }
.vs-h b { font-size: 11pt; font-weight: 900; color: var(--acc); }
.vs-h span { direction: ltr; font-size: 7.8pt; color: var(--muted); font-weight: 700; letter-spacing: .05em; }
.vs ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1mm; }
.vs li { display: flex; justify-content: space-between; gap: 2mm; font-size: 8.4pt; padding: 1.1mm 2mm; border-radius: 1.6mm; background: var(--row-bg); }
.vs li span { color: var(--muted); white-space: nowrap; }
.vs li b { color: var(--text); font-weight: 700; text-align: left; }
.vs-mid { align-self: center; font-weight: 900; font-size: 11pt; color: var(--muted); direction: ltr; letter-spacing: .1em; }
.note.wide { font-size: 9.2pt; }
.gl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 8.8pt; border: 1px solid var(--line-bg); border-radius: 3mm; }
.gl th { text-align: right; font-weight: 800; color: var(--muted); font-size: 8pt; background: var(--head-bg); padding: 1.8mm 3mm; }
.gl th:first-child { border-top-right-radius: 3mm; }
.gl th:last-child { border-top-left-radius: 3mm; }
.gl td { padding: 1.35mm 3mm; border-top: 1px solid var(--line-bg); color: var(--soft); }
.gl td b { color: var(--gold); font-weight: 900; direction: ltr; display: inline-block; }
.gl .lt { direction: ltr; text-align: right; font-size: 8.2pt; color: var(--muted); }
.disc { margin: 0; font-size: 7.6pt; color: var(--muted); line-height: 1.8; text-align: center; }
`;

const html = `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>ICT درس دوم — نقدینگی</title><style>${css}</style></head>
<body>${cover}${p2}${p3}${p4}${p5}${p6}</body></html>`;

async function main() {
  const args = process.argv.slice(2);
  const pngIdx = args.indexOf('--png');
  const pngDir = pngIdx >= 0 ? args[pngIdx + 1] : null;
  const out = path.resolve(args.find((a, i) => !a.startsWith('--') && (pngIdx < 0 || i !== pngIdx + 1)) || path.join(__dirname, 'ICT-Lesson-02-Liquidity.pdf'));

  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ict-'));
  const htmlPath = path.join(tmp, 'lesson.html');
  fs.writeFileSync(htmlPath, html);

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 2 });
  await page.goto(pathToFileURL(htmlPath).href);
  await page.evaluate(async () => {
    await document.fonts.ready;
    // size pill backgrounds to their text
    document.querySelectorAll('g.pill').forEach((g) => {
      const t = g.querySelector('text');
      const r = g.querySelector('rect');
      const b = t.getBBox();
      const px = +g.dataset.px;
      const py = +g.dataset.py;
      const h = b.height + py * 2;
      r.setAttribute('x', b.x - px);
      r.setAttribute('y', b.y - py);
      r.setAttribute('width', b.width + px * 2);
      r.setAttribute('height', h);
      r.setAttribute('rx', h / 2);
    });
  });
  const overflow = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const pr = p.getBoundingClientRect();
    const bottom = Math.max(...[...p.children].filter((c) => !c.matches('.pf')).map((c) => c.getBoundingClientRect().bottom));
    return { page: i + 1, spare: Math.round(pr.bottom - bottom) };
  }));
  console.log('space left above footer (px):', JSON.stringify(overflow));
  const fonts = await page.evaluate(() => [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.weight));
  console.log('loaded font weights:', fonts.join(', '));

  if (pngDir) {
    fs.mkdirSync(pngDir, { recursive: true });
    const pages = await page.$$('.page');
    for (let i = 0; i < pages.length; i++) await pages[i].screenshot({ path: path.join(pngDir, `page-${i + 1}.png`) });
  }
  await page.pdf({ path: out, preferCSSPageSize: true, printBackground: true, tagged: false, outline: false });
  await browser.close();
  fs.rmSync(tmp, { recursive: true, force: true });
  console.log('wrote', out);
}

main().catch((e) => { console.error(e); process.exit(1); });
