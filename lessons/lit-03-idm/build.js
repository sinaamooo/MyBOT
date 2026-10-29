'use strict';
// Builds the "LIT Lesson 3 of 10 — IDM (Inducement)" PDF, made for KABOSU TEAM.
// Usage: node build.js [out.pdf] [--png <dir>]
const path = require('path');
const { BG, CARD, WARN, fa, mix, acc, ICON, layout, coverCss, cliArgs, render } = require('../common/doc');
const { COLORS: K } = require('../common/charts');
const { TEAM } = require('../common/team');
const F = require('./figures');

const TOTAL = 9;
const link = `<a href="${TEAM.url}">${TEAM.label}</a>`;
const { page, card } = layout({
  badge: 'LIT',
  kicker: 'درس ۳ از ۱۰',
  topic: 'IDM (Inducement)',
  en: `${TEAM.name} · LIT 03`,
  footer: `آموزش اختصاصی <b>${TEAM.name}</b> · ${link} · محتوای آموزشی، نه توصیه معاملاتی`,
  total: TOTAL,
});

const C = { idm: K.magenta, bull: K.bull, bear: K.bear, diff: K.violet, method: K.cyan, mistake: K.orange, practice: K.gold };

const progress = (done) => `
<div class="prog">
  <div class="bar">${Array.from({ length: 10 }, (_, i) => `<i class="${i < done - 1 ? 'on' : i === done - 1 ? 'cur' : ''}"></i>`).join('')}</div>
  <span>درس ${fa(done)} از ${fa(10)}</span>
</div>`;

const olist = (items, cls = '') => `
<ol class="olist ${cls}">${items.map(([n, color, text]) => `<li style="--c:${color}"><b class="n">${fa(n)}</b><span>${text}</span></li>`).join('')}</ol>`;

const story = (steps, color) => `
<div class="story" style="${acc(color)}">${steps.map((s, i) => `${i ? `<span class="ar">${ICON.arrowSm}</span>` : ''}<div class="s"><b>${fa(i + 1)}</b><span>${s}</span></div>`).join('')}</div>`;

const tile = ({ abbr, name, color, figBox, text }) => `
<div class="tile" style="${acc(color)}">
  <div class="tile-h"><b>${abbr}</b><span>${name}</span></div>
  ${figBox}
  <p>${text}</p>
</div>`;

const flow = (title, items, color) => `
<div class="flow" style="${acc(color)}"><span class="t">${title}</span>${items.map((s, i) => `${i ? `<i>${ICON.arrowSm}</i>` : ''}<b>${s}</b>`).join('')}</div>`;

const toc = [
  ['IDM', 'IDM چیست؟', 2, C.idm],
  ['Bullish IDM', 'IDM در روند صعودی', 3, C.bull],
  ['Bearish IDM', 'IDM در روند نزولی', 4, C.bear],
  ['IDM vs Sweep', 'تفاوت IDM با Sweep', 5, C.diff],
  ['Method', 'روش پیدا کردن IDM', 6, C.method],
  ['Mistakes', 'اشتباهات رایج', 7, C.mistake],
  ['Practice', 'تمرین عملی', 8, C.practice],
  ['Summary', 'جمع‌بندی و قانون اصلی', 9, '#E8ECF4'],
];

const mistakes = [
  'هر کف یا سقف کوچکی را IDM در نظر نگیرید.',
  'هر Liquidity Sweep را سیگنال ورود فرض نکنید.',
  'جهت تایم‌فریم بالاتر را نادیده نگیرید.',
  'بدون تعیین حد ضرر وارد معامله نشوید.',
  'فرض نکنید قیمت پس از شکستن هر سطح حتماً برمی‌گردد.',
  'برای تأیید تحلیل، فقط به یک کندل یا یک نشانه اکتفا نکنید.',
];

const practice = [
  'یک روند مشخص پیدا کنید.',
  'سقف‌ها و کف‌های اصلی را علامت بزنید.',
  'یک کف داخلی در سناریوی صعودی یا یک سقف داخلی در سناریوی نزولی پیدا کنید.',
  'بررسی کنید آیا قیمت از آن سطح عبور کرده است یا خیر.',
  'واکنش بعدی قیمت و وضعیت ساختار بازار را ثبت کنید.',
  'مشخص کنید آیا تأیید مناسبی برای بررسی یک معامله وجود داشته است یا خیر.',
];
const journalCols = ['بازار و تایم‌فریم', 'جهت روند', 'سطح داخلی (IDM)', 'عبور قیمت؟', 'واکنش و ساختار بعدی', 'تأیید؟'];

// fit(key, draw, opts) places a chart sized to the box the layout gives it (see render()).
const body = (fit) => {
  const cover = page(1, `
<div class="cv-top">
  <span class="hash">#آموزشی · ویژه ${TEAM.name}</span>
  <span class="cv-series">${TEAM.name} · LIT 03 / 10</span>
</div>
<div class="cv-head">
  <div class="cv-title">
    <div class="cv-kicker"><span class="ict">LIT</span> آموزش LIT | درس ۳ از ۱۰</div>
    <h1 class="lat">IDM</h1>
    <div class="cv-en">INDUCEMENT</div>
    <div class="cv-sub">ناحیه‌ای از ساختار قیمت که ممکن است معامله‌گران را به ورود زودهنگام ترغیب کند</div>
  </div>
  <a class="team-card" href="${TEAM.url}">
    <span class="tc-k">آموزش اختصاصی</span>
    <b>${TEAM.name}</b>
    <span class="qr">${TEAM.qr}</span>
    <span class="tc-l">${TEAM.label}</span>
  </a>
</div>
${progress(3)}
${fit('hero', (h) => F.hero(h), { h: 500, cls: 'hero' })}
<div class="toc-h">در این درس</div>
<div class="toc">
  ${toc.map(([a, t, p, c], i) => `<div class="toc-i" style="${acc(c, BG)}"><span class="toc-n">${fa(i + 1)}</span><div><b>${a}</b><span>${t}</span></div><span class="toc-p">ص ${fa(p)}</span></div>`).join('')}
</div>
`, 'cover');

  const p2 = page(2, card({
    num: 1, title: 'IDM چیست؟', abbr: 'IDM', en: 'Inducement', color: C.idm, loc: 'ورود زودهنگام',
    body: [
      'در سبک ICT، مفهوم <b>IDM</b> یا <b>Inducement</b> به ناحیه‌ای از ساختار قیمت گفته می‌شود که ممکن است معامله‌گران را به <b>ورود زودهنگام</b> به معامله ترغیب کند.',
      'برای مثال، قیمت در یک روند صعودی حرکت می‌کند و یک کف داخلی تشکیل می‌دهد. برخی معامله‌گران ممکن است بر اساس این کف وارد خرید شوند یا حد ضرر خود را زیر آن قرار دهند. قیمت می‌تواند به زیر این کف نفوذ کند، نقدینگی احتمالی آن ناحیه را جمع کند و سپس به حرکت صعودی ادامه دهد.',
    ],
    figBox: fit('what', (h) => F.whatIs(h)),
    after: story(['کف داخلی شکل می‌گیرد', 'ورود زودهنگام و حد ضرر زیر کف', 'نفوذ به زیر کف و ادامه صعود'], C.idm),
    notes: [{ type: 'warn', title: 'نکته مهم:', text: 'هر سقف یا کف کوچکی IDM نیست. برای تشخیص آن باید ساختار بازار، جهت حرکت قیمت و موقعیت آن ناحیه را بررسی کرد.' }],
  }));

  const scenarioPage = (n, { num, title, abbr, color, loc, locIcon, intro, figBox, steps, scenario, warning }) => page(n, card({
    num, title, abbr, color, loc, locIcon,
    body: [intro],
    figBox,
    after: `<div class="steps-h">مراحل بررسی</div>${olist(steps.map((s, i) => [i + 1, i === 2 || i === 3 ? C.idm : color, s]))}`,
    notes: [{ type: 'info', title: 'سناریو:', text: scenario }, { type: 'warn', title: 'هشدار:', text: warning }],
  }));

  const p3 = scenarioPage(3, {
    num: 2, title: 'IDM در روند صعودی', abbr: 'Bullish IDM', color: C.bull, loc: 'سقف‌ها و کف‌های بالاتر', locIcon: 'up',
    intro: 'فرض کنیم بازار در <b>روند صعودی</b> قرار دارد؛ یعنی قیمت سقف‌ها و کف‌های بالاتری تشکیل می‌دهد.',
    figBox: fit('bull', (h) => F.bullish(h)),
    steps: [
      'قیمت یک کف تشکیل می‌دهد.',
      'از آن کف حرکت می‌کند و سقف جدیدی می‌سازد.',
      'در مسیر حرکت، یک کف داخلی شکل می‌گیرد.',
      'قیمت ممکن است به سمت این کف برگردد و آن را بشکند یا نقدینگی زیر آن را جمع کند.',
      'پس از این حرکت، باید منتظر نشانه‌های تأیید صعودی و بررسی ساختار بازار ماند.',
    ],
    scenario: 'اگر قیمت پس از جمع‌آوری نقدینگی، نشانه‌های معتبر صعودی نشان دهد، می‌توان سناریوی خرید را بررسی کرد.',
    warning: 'صرفاً شکسته‌شدن یک کف داخلی، سیگنال قطعی خرید نیست.',
  });

  const p4 = scenarioPage(4, {
    num: 3, title: 'IDM در روند نزولی', abbr: 'Bearish IDM', color: C.bear, loc: 'سقف‌ها و کف‌های پایین‌تر', locIcon: 'down',
    intro: 'در <b>روند نزولی</b>، قیمت معمولاً سقف‌ها و کف‌های پایین‌تری تشکیل می‌دهد.',
    figBox: fit('bear', (h) => F.bearish(h)),
    steps: [
      'قیمت یک سقف تشکیل می‌دهد.',
      'از آن سقف پایین می‌آید و کف جدیدی می‌سازد.',
      'در مسیر حرکت، یک سقف داخلی شکل می‌گیرد.',
      'قیمت ممکن است به سمت آن سقف برگردد و از آن عبور کند یا نقدینگی بالای آن را جمع کند.',
      'پس از آن باید نشانه‌های تأیید نزولی و وضعیت ساختار بازار بررسی شوند.',
    ],
    scenario: 'اگر قیمت پس از این حرکت نشانه‌های معتبر نزولی نشان دهد، می‌توان سناریوی فروش را بررسی کرد.',
    warning: 'عبور قیمت از سقف داخلی به‌تنهایی به معنای شروع ریزش نیست؛ ممکن است بازار به روند صعودی خود ادامه دهد.',
  });

  const mini = (key, draw) => fit(key, draw, { w: 600, h: 260 });

  const p5 = page(5, card({
    num: 4, title: 'تفاوت IDM با Liquidity Sweep', abbr: 'IDM · Sweep · BSL · SSL', color: C.diff, cls: 'grow',
    after: `<div class="tiles">
      ${tile({ abbr: 'IDM', name: 'Inducement', color: C.idm, figBox: mini('t-idm', (h) => F.miniIdm(h)), text: 'ناحیه یا ساختاری که ممکن است معامله‌گران را به ورود زودهنگام ترغیب کند.' })}
      ${tile({ abbr: 'Liquidity Sweep', name: 'نقدینگی‌گیری', color: K.violet, figBox: mini('t-sweep', (h) => F.miniSweep(h)), text: 'حرکتی که در آن قیمت از یک سطح نقدینگی عبور می‌کند و ممکن است دوباره به محدوده قبلی برگردد.' })}
      ${tile({ abbr: 'BSL', name: 'Buy-Side Liquidity', color: K.gold, figBox: mini('t-bsl', (h) => F.miniBsl(h)), text: 'نقدینگی سمت خرید که معمولاً بالای سقف‌های قبلی و سقف‌های برابر بررسی می‌شود.' })}
      ${tile({ abbr: 'SSL', name: 'Sell-Side Liquidity', color: K.blue, figBox: mini('t-ssl', (h) => F.miniSsl(h)), text: 'نقدینگی سمت فروش که معمولاً پایین کف‌های قبلی و کف‌های برابر بررسی می‌شود.' })}
    </div>`,
    notes: [{ type: 'info', title: 'نکته:', text: 'این مفاهیم به یکدیگر مرتبط‌اند، اما یکسان نیستند. تعریف دقیق IDM ممکن است با توجه به روش و آموزش هر مدرس تفاوت داشته باشد.' }],
  }));

  const p6 = page(6, card({
    num: 5, title: 'روش پیدا کردن IDM روی چارت', abbr: 'Step by Step', color: C.method, loc: '۷ مرحله',
    body: ['برای بررسی IDM مراحل زیر را انجام دهید:'],
    figBox: fit('method', (h) => F.method(h)),
    after: olist([
      'ابتدا جهت کلی بازار را مشخص کنید: صعودی، نزولی یا رنج.',
      'سقف‌ها و کف‌های اصلی ساختار بازار را علامت بزنید.',
      'حرکت اصلی قیمت را بررسی کنید و سقف‌ها یا کف‌های داخلی مسیر را مشخص کنید.',
      'بررسی کنید کدام ناحیه ممکن است معامله‌گران را به ورود زودهنگام ترغیب کند.',
      'ببینید آیا قیمت به آن ناحیه برگشته و از آن عبور کرده است یا خیر.',
      'پس از حرکت قیمت، منتظر تأیید مناسب بمانید؛ برای مثال، واکنش قیمت و تغییر ساختار در تایم‌فریم ورود.',
      'قبل از ورود، حد ضرر، حد سود و میزان ریسک را مشخص کنید.',
    ].map((s, i) => [i + 1, C.method, s]), 'two') + flow('ترتیب بررسی:', ['ساختار بازار', 'نقدینگی', 'تأیید', 'تصمیم معاملاتی'], C.method),
  }));

  const p7 = page(7, card({
    num: 6, title: 'اشتباهات رایج در تشخیص IDM', abbr: 'Common Mistakes', color: C.mistake, loc: '۶ اشتباه', cls: 'grow',
    after: `
<div class="mist">${mistakes.map((m, i) => `<div><i>${ICON.cross}</i><small>${fa(i + 1)}</small><span>${m}</span></div>`).join('')}</div>
<div class="steps-h">یک سطح، دو سناریوی متفاوت</div>
<div class="pair">
  ${tile({ abbr: 'IDM', name: 'نفوذ و ادامه روند', color: C.idm, figBox: mini('p-idm', (h) => F.miniIdm(h)), text: 'قیمت به زیر کف داخلی نفوذ می‌کند، نقدینگی را جمع می‌کند و روند صعودی ادامه پیدا می‌کند.' })}
  ${tile({ abbr: 'Breakdown', name: 'شکست و ادامه ریزش', color: K.bear, figBox: mini('p-break', (h) => F.miniRealBreak(h)), text: 'کف داخلی شکسته می‌شود و قیمت برنمی‌گردد؛ ساختار تغییر کرده است. شکستن سطح لزوماً به بازگشت نمی‌انجامد.' })}
</div>`,
    notes: [{ type: 'warn', title: 'به خاطر بسپار:', text: 'هیچ الگویی موفقیت قطعی معامله را تضمین نمی‌کند. کیفیت تحلیل باید با بررسی نمونه‌های متعدد و ثبت نتایج ارزیابی شود.' }],
  }));

  const p8 = page(8, card({
    num: 7, title: 'تمرین عملی درس سوم', abbr: 'Practice', color: C.practice, loc: 'روی چارت واقعی', cls: 'grow',
    body: ['یک چارت از بازار موردنظر خود، مانند <b>EURUSD</b> یا <b>BTCUSDT</b>، باز کنید.'],
    after: `
<ul class="check">${practice.map((s, i) => `<li><span class="bx">${ICON.box}</span><b>${fa(i + 1)}</b><span>${s}</span></li>`).join('')}</ul>
<div class="steps-h">جدول ثبت نتایج</div>
<div class="jr-wrap">
<table class="jr">
  <thead><tr><th>#</th>${journalCols.map((c) => `<th>${c}</th>`).join('')}</tr></thead>
  <tbody>${Array.from({ length: 8 }, (_, r) => `<tr><td>${fa(r + 1)}</td>${journalCols.map(() => '<td></td>').join('')}</tr>`).join('')}</tbody>
</table>
</div>`,
    notes: [{ type: 'info', title: 'پیشنهاد:', text: 'این تمرین را ابتدا روی داده‌های گذشته یا حساب آزمایشی انجام دهید و نتایج را ثبت کنید.' }],
  }));

  const p9 = page(9, `
<div class="sum-h"><h2>جمع‌بندی درس سوم</h2><span>SUMMARY</span></div>
<div class="sum-box">
  <p>IDM یکی از مفاهیم مورد استفاده در تحلیل نقدینگی به سبک ICT است. هدف از بررسی آن، شناسایی نواحی‌ای است که ممکن است معامله‌گران را به <b>ورود زودهنگام</b> ترغیب کنند.</p>
  <p>برای استفاده صحیح از این مفهوم، باید <b>ساختار بازار</b>، <b>محل نقدینگی</b>، <b>واکنش قیمت</b> و <b>تأییدهای لازم</b> را در کنار یکدیگر بررسی کرد.</p>
</div>
<div class="rule">
  <div class="rule-h">${ICON.key}<span>قانون اصلی این درس</span></div>
  <div class="rule-cols">
    <div style="${acc(C.method, BG)}"><b class="n">۱</b><span>ساختار بازار را مشخص کنید</span></div>
    <i>${ICON.arrow}</i>
    <div style="${acc(C.idm, BG)}"><b class="n">۲</b><span>نواحی نقدینگی را بررسی کنید</span></div>
    <i>${ICON.arrow}</i>
    <div style="${acc(C.bull, BG)}"><b class="n">۳</b><span>بدون تأیید کافی وارد معامله نشوید</span></div>
  </div>
</div>
${fit('recap', (h) => F.recap(h), { h: 300 })}
<div class="recap">
  ${[
    [C.idm, 'IDM', 'ناحیه‌ای که ممکن است معامله‌گران را به ورود زودهنگام ترغیب کند.'],
    [C.bull, 'صعودی', 'کف داخلی، نفوذ به زیر آن، سپس نشانه‌های تأیید صعودی.'],
    [C.bear, 'نزولی', 'سقف داخلی، عبور از بالای آن، سپس نشانه‌های تأیید نزولی.'],
    [C.diff, 'تفاوت', 'IDM، Sweep، BSL و SSL به هم مرتبط‌اند اما یکسان نیستند.'],
    [C.mistake, 'هشدار', 'شکست یک سطح داخلی به‌تنهایی سیگنال قطعی ورود نیست.'],
    [C.practice, 'تمرین', 'ابتدا روی داده‌های گذشته یا حساب آزمایشی، همراه با ثبت نتایج.'],
  ].map(([c, t, d]) => `<div style="${acc(c, BG)}"><b>${t}</b><span>${d}</span></div>`).join('')}
</div>
<div class="end">
  <div class="end-t">
    <b>پایان درس ۳ از ۱۰</b>
    ${progress(3)}
    <span class="end-team">این آموزش مخصوص <b>${TEAM.name}</b> است · ${link}</span>
  </div>
  <a class="qr" href="${TEAM.url}">${TEAM.qr}</a>
</div>
<p class="disc">نمودارهای این جزوه نمایشی‌اند و از داده واقعی بازار ساخته نشده‌اند. این محتوا صرفاً آموزشی است و پیشنهاد خرید یا فروش هیچ دارایی‌ای نیست.</p>
`);

  return cover + p2 + p3 + p4 + p5 + p6 + p7 + p8 + p9;
};

const css = `
.cv-head { display: flex; align-items: stretch; gap: 5mm; }
.cv-head .cv-title { flex: 1; }
.cv-title h1.lat { direction: ltr; text-align: right; font-size: 68pt; letter-spacing: .04em; line-height: 1.15; }
.team-card { flex: none; width: 44mm; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.4mm; padding: 3mm; border-radius: 4mm; background: var(--card); border: 1px solid ${mix('#FFB020', 0.5, BG)}; border-top: 2.5px solid var(--gold); text-decoration: none; color: var(--text); }
.team-card .tc-k { font-size: 8pt; color: var(--soft); font-weight: 700; }
.team-card b { direction: ltr; font-size: 12.5pt; font-weight: 900; color: var(--gold); letter-spacing: .05em; }
.team-card .tc-l { direction: ltr; font-size: 7.2pt; color: var(--soft); font-weight: 600; }
.qr { display: block; width: 27mm; height: 27mm; padding: 1mm; background: #fff; border-radius: 2mm; line-height: 0; }
.qr svg { width: 100%; height: 100%; display: block; }
.cover > .fig.fit, .page > .fig.fit { flex: 1 1 auto; min-height: 0; }

.prog { display: flex; align-items: center; gap: 3mm; }
.prog .bar { flex: 1; display: flex; gap: 1.3mm; }
.prog .bar i { flex: 1; height: 2.2mm; border-radius: 1mm; background: var(--chip-bg); border: 1px solid var(--line-bg); }
.prog .bar i.on { background: ${mix('#FFB020', 0.55, BG)}; border-color: ${mix('#FFB020', 0.55, BG)}; }
.prog .bar i.cur { background: var(--gold); border-color: var(--gold); outline: .7mm solid ${mix('#FFB020', 0.3, BG)}; }
.prog span { font-size: 9pt; font-weight: 800; color: var(--soft); white-space: nowrap; }

.card.grow > .tiles, .card.grow > .pair, .card.grow > .jr-wrap { flex: 1 1 auto; min-height: 0; }
.steps-h { font-size: 10.5pt; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 2mm; margin-bottom: -.6mm; }
.steps-h::before { content: ''; width: 1.1mm; height: 4mm; border-radius: 1mm; background: var(--acc); }
.olist { list-style: none; margin: 0; padding: 0; display: grid; gap: 1.3mm; }
.olist.two { grid-template-columns: 1fr 1fr; column-gap: 2.4mm; }
.olist li { display: flex; gap: 2.2mm; align-items: flex-start; font-size: 9.2pt; line-height: 1.75; color: var(--soft); background: var(--row-bg); border-radius: 2mm; padding: 1.4mm 2.6mm; }
.olist.two li { font-size: 8.7pt; }
.olist b.n { flex: none; width: 5.6mm; height: 5.6mm; border-radius: 50%; display: grid; place-items: center; background: var(--c); color: #0B111C; font-weight: 900; font-size: 8.6pt; margin-top: .5mm; }

.story { display: flex; gap: 1.6mm; align-items: stretch; }
.story .s { flex: 1; display: flex; gap: 2mm; align-items: center; background: var(--row-bg); border: 1px solid var(--line); border-radius: 2.4mm; padding: 2mm 2.6mm; font-size: 8.8pt; color: var(--soft); font-weight: 600; line-height: 1.6; }
.story .s b { flex: none; width: 5.6mm; height: 5.6mm; border-radius: 50%; display: grid; place-items: center; background: var(--acc); color: #0B111C; font-weight: 900; font-size: 8.6pt; }
.story .ar { align-self: center; color: var(--muted); line-height: 0; }

.tiles { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 3mm; }
.tile { min-height: 0; border: 1px solid var(--line); border-top: 2.5px solid var(--acc); background: ${mix('#ffffff', 0.025, CARD)}; border-radius: 3mm; padding: 3mm; display: flex; flex-direction: column; gap: 2.2mm; }
.tile-h { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; }
.tile-h b { color: var(--acc); font-size: 11.5pt; font-weight: 900; direction: ltr; }
.tile-h span { color: var(--muted); font-size: 8.2pt; font-weight: 700; }
.tile p { margin: 0; font-size: 9.2pt; line-height: 1.85; color: var(--soft); text-align: justify; }
.pair { display: flex; gap: 3mm; }
.pair .tile { flex: 1; }

.mist { display: grid; grid-template-columns: 1fr 1fr; gap: 2.2mm; }
.mist div { display: flex; gap: 2.2mm; align-items: center; background: var(--row-bg); border: 1px solid var(--line); border-radius: 2.4mm; padding: 2.4mm 3mm; font-size: 9.3pt; line-height: 1.7; color: var(--soft); }
.mist i { flex: none; width: 6.4mm; height: 6.4mm; border-radius: 50%; display: grid; place-items: center; background: ${mix(WARN, 0.18, CARD)}; color: ${WARN}; line-height: 0; }
.mist small { color: var(--muted); font-weight: 900; font-size: 9pt; }

.flow { display: flex; align-items: center; gap: 1.8mm; background: var(--acc-note); border-right: 3px solid var(--acc); border-radius: 2.4mm; padding: 2.2mm 3.2mm; }
.flow .t { font-size: 9.2pt; font-weight: 800; color: var(--acc); margin-left: 1mm; white-space: nowrap; }
.flow b { font-size: 8.8pt; font-weight: 800; color: var(--text); background: var(--card); border: 1px solid var(--acc-line); border-radius: 10mm; padding: .7mm 3mm; white-space: nowrap; }
.flow i { color: var(--muted); line-height: 0; }

.check { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1.5mm; }
.check li { display: flex; gap: 2.4mm; align-items: center; font-size: 9.8pt; line-height: 1.7; color: var(--soft); padding: 2mm 3mm; border-radius: 2.2mm; background: var(--row-bg); border: 1px solid var(--line); }
.check .bx { color: var(--acc); line-height: 0; }
.check b { color: var(--acc); font-weight: 900; }
.jr-wrap { display: flex; }
.jr { width: 100%; height: 100%; border-collapse: separate; border-spacing: 0; font-size: 8.2pt; border: 1px solid var(--line); border-radius: 2.6mm; }
.jr th { height: 9mm; background: var(--row-bg); color: var(--soft); font-weight: 800; padding: 1.6mm 1.4mm; text-align: center; border-left: 1px solid var(--line); line-height: 1.5; }
.jr th:first-child { border-top-right-radius: 2.6mm; width: 7mm; }
.jr th:last-child { border-top-left-radius: 2.6mm; border-left: 0; }
.jr td { border-top: 1px solid var(--line); border-left: 1px solid var(--line); }
.jr td:last-child { border-left: 0; }
.jr td:first-child { text-align: center; color: var(--muted); font-weight: 800; }

.sum-h { display: flex; align-items: baseline; gap: 3mm; margin: 0 0 -1mm; }
.sum-h h2 { margin: 0; font-size: 24pt; font-weight: 900; color: var(--gold); line-height: 1.3; }
.sum-h span { direction: ltr; font-size: 9pt; letter-spacing: .4em; color: var(--muted); font-weight: 800; }
.sum-box { border: 1px solid ${mix('#FFB020', 0.35, BG)}; background: ${mix('#FFB020', 0.07, BG)}; border-right: 3px solid var(--gold); border-radius: 3.5mm; padding: 3.2mm 5mm; display: flex; flex-direction: column; gap: 1.2mm; }
.sum-box p { margin: 0; font-size: 10.2pt; line-height: 1.9; color: var(--soft); text-align: justify; }
.sum-box b { color: var(--text); font-weight: 800; }
.rule { border: 1.5px solid var(--gold); background: ${mix('#FFB020', 0.06, BG)}; border-radius: 4mm; padding: 3.4mm 5mm 3.8mm; display: flex; flex-direction: column; gap: 2.8mm; }
.rule-h { display: flex; align-items: center; gap: 2.4mm; color: var(--gold); font-weight: 900; font-size: 12.5pt; }
.rule-cols { display: flex; gap: 2mm; align-items: stretch; }
.rule-cols > div { flex: 1; background: var(--acc-note); border: 1px solid var(--acc-line); border-radius: 3mm; padding: 2.8mm 2.4mm; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.8mm; }
.rule-cols > i { align-self: center; color: var(--muted); line-height: 0; }
.rule-cols b.n { width: 8mm; height: 8mm; border-radius: 50%; display: grid; place-items: center; background: var(--acc); color: #0B111C; font-weight: 900; font-size: 12pt; outline: 1.2mm solid var(--acc-ring); }
.rule-cols span { font-size: 10pt; font-weight: 800; line-height: 1.6; }
.recap { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2mm; }
.recap div { display: flex; flex-direction: column; gap: .4mm; padding: 2mm 2.8mm; border-radius: 2.6mm; background: var(--chip-bg); border: 1px solid var(--line-bg); border-right: 3px solid var(--acc); }
.recap b { color: var(--acc); font-size: 9.2pt; font-weight: 900; }
.recap span { color: var(--soft); font-size: 8.3pt; line-height: 1.65; }
.end { display: flex; align-items: center; gap: 5mm; padding: 3.4mm 5mm; border-radius: 3.5mm; background: var(--card); border: 1px solid ${mix('#FFB020', 0.45, BG)}; }
.end-t { flex: 1; display: flex; flex-direction: column; gap: 2.2mm; }
.end-t > b { font-size: 14pt; font-weight: 900; color: var(--gold); }
.end-team { font-size: 9.4pt; color: var(--soft); font-weight: 600; }
.end-team b { color: var(--gold); font-weight: 900; direction: ltr; unicode-bidi: isolate; }
.end-team a, .end-t a { color: var(--gold); text-decoration: none; direction: ltr; unicode-bidi: isolate; font-weight: 700; }
.end .qr { width: 24mm; height: 24mm; flex: none; }
`;

render({
  title: `آموزش LIT — درس ۳ از ۱۰: IDM · ${TEAM.name}`,
  css: coverCss + css,
  body,
  ...cliArgs(path.join(__dirname, 'LIT-Lesson-03-IDM.pdf')),
}).catch((e) => {
  console.error(e);
  process.exit(1);
});
