'use strict';
// Builds the "ICT Lesson 2 — Liquidity" PDF.
// Usage: node build.js [out.pdf] [--png <dir>]
const path = require('path');
const { BG, WARN, fa, mix, acc, ICON, layout, coverCss, cliArgs, render } = require('../common/doc');
const F = require('./figures');

const { page, card } = layout({
  badge: 'ICT',
  kicker: 'درس دوم',
  topic: 'نقدینگی',
  en: 'LIQUIDITY · LESSON 02',
  footer: 'سری آموزشی ICT · محتوای آموزشی است و توصیه معاملاتی محسوب نمی‌شود',
  total: 6,
});

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
/* summary */
.sum-h { display: flex; align-items: baseline; gap: 3mm; margin: 0 0 -2mm; }
.sum-h h2 { margin: 0; font-size: 26pt; font-weight: 900; color: var(--gold); line-height: 1.3; }
.sum-h span { direction: ltr; font-size: 9pt; letter-spacing: .4em; color: var(--muted); font-weight: 800; }
.sum-box { border: 1px solid ${mix('#FFB020', 0.35, BG)}; background: ${mix('#FFB020', 0.07, BG)}; border-right: 3px solid var(--gold); border-radius: 3.5mm; padding: 3.4mm 5mm; }
.sum-box b { color: var(--text); font-weight: 800; }
.sum-box p { margin: 0; font-size: 11pt; line-height: 1.95; color: var(--soft); }
.steps { display: flex; align-items: stretch; gap: 2mm; }
.step { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.3mm; padding: 3.6mm 2.5mm 3.2mm; border-radius: 3.5mm; background: var(--acc-note); border: 1px solid var(--acc-line); }
.sn { width: 9mm; height: 9mm; border-radius: 50%; display: grid; place-items: center; font-weight: 900; font-size: 13pt; color: #0B111C; background: var(--acc); outline: 1.3mm solid var(--acc-ring); margin-bottom: 1mm; }
.step b { font-size: 10.6pt; font-weight: 900; }
.step small { font-size: 7.8pt; color: var(--muted); line-height: 1.6; }
.arr { align-self: center; color: var(--muted); line-height: 0; }
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
.gl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 8.8pt; border: 1px solid var(--line-bg); border-radius: 3mm; }
.gl th { text-align: right; font-weight: 800; color: var(--muted); font-size: 8pt; background: var(--head-bg); padding: 1.8mm 3mm; }
.gl th:first-child { border-top-right-radius: 3mm; }
.gl th:last-child { border-top-left-radius: 3mm; }
.gl td { padding: 1.35mm 3mm; border-top: 1px solid var(--line-bg); color: var(--soft); }
.gl td b { color: var(--gold); font-weight: 900; direction: ltr; display: inline-block; }
.gl .lt { direction: ltr; text-align: right; font-size: 8.2pt; color: var(--muted); }
`;

render({
  title: 'ICT درس دوم — نقدینگی',
  css: coverCss + css,
  body: cover + p2 + p3 + p4 + p5 + p6,
  ...cliArgs(path.join(__dirname, 'ICT-Lesson-02-Liquidity.pdf')),
}).catch((e) => {
  console.error(e);
  process.exit(1);
});
