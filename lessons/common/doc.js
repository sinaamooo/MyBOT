'use strict';
// Shared page layout and PDF renderer for the lesson handouts.
//
// Solid colours only (no alpha, gradients or shadows) so phone PDF viewers
// (Telegram / Android pdfium) render pages fast and sharp.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright');
const { mix } = require('./charts');

const FONT_DIR = path.join(__dirname, 'fonts');
const fontUrl = (f) => pathToFileURL(path.join(FONT_DIR, f)).href;

const BG = '#070B12';
const CARD = '#101826';
const WARN = '#FF6B81';
const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const acc = (c, base = CARD) =>
  `--acc:${c};--acc-bg:${mix(c, 0.1, base)};--acc-note:${mix(c, 0.09, base)};--acc-line:${mix(c, 0.45, base)};--acc-ring:${mix(c, 0.25, base)};--acc-dim:${mix(c, 0.3, base)}`;

const ICON = {
  info: '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="10" width="2" height="7" rx="1" fill="currentColor"/><circle cx="12" cy="7" r="1.3" fill="currentColor"/></svg>',
  warn: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 3 L22 20 H2 Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><rect x="11" y="9" width="2" height="6" rx="1" fill="currentColor"/><circle cx="12" cy="17.2" r="1.2" fill="currentColor"/></svg>',
  ex: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M4 18 L9 11 L13 14 L20 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="20" cy="5" r="2" fill="currentColor"/></svg>',
  up: '<svg viewBox="0 0 12 12" width="9" height="9"><path d="M6 1 L11 10 H1 Z" fill="currentColor"/></svg>',
  down: '<svg viewBox="0 0 12 12" width="9" height="9"><path d="M6 11 L11 2 H1 Z" fill="currentColor"/></svg>',
  arrow: '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M20 12 H5 M11 6 L5 12 L11 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  arrowSm: '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M20 12 H5 M11 6 L5 12 L11 18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  cross: '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M6 6 L18 18 M18 6 L6 18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
  check: '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M5 12.5 L10 17 L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  box: '<svg viewBox="0 0 24 24" width="15" height="15"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
  key: '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="8" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M12.5 12 H21 M18 12 V15.5 M15 12 V14.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>',
};

// meta: { badge, kicker, topic, en, footer, total }
function layout(meta) {
  const header = () => `
<header class="ph">
  <div class="ph-r"><span class="ict">${meta.badge}</span><span>${meta.kicker}</span><i></i><span class="ph-t">${meta.topic}</span></div>
  <div class="ph-l">${meta.en}</div>
</header>`;

  const footer = (n) => `
<footer class="pf">
  <span>${meta.footer}</span>
  <span class="pn">${fa(n)} / ${fa(meta.total)}</span>
</footer>`;

  const page = (n, inner, cls = '') => `<div class="page ${cls}">${n > 1 ? header() : ''}${inner}${footer(n)}</div>`;

  const note = ({ type = 'info', title, text, color, base = CARD, cls = '' }) =>
    `<div class="note ${cls}" style="${acc(type === 'warn' ? WARN : color, base)}"><span class="ni">${ICON[type]}</span><div>${title ? `<b>${title}</b> ` : ''}${text}</div></div>`;

  // A section card. `after` is extra HTML placed between the figure and the notes.
  function card({ num, title, en, abbr, color, loc, locIcon, body = [], fig, figBox = '', after = '', notes, note: text, noteType = 'info', noteTitle, cls = '' }) {
    const list = notes || (text ? [{ type: noteType, title: noteTitle, text }] : []);
    return `
<section class="card ${cls}" style="${acc(color)}">
  <div class="card-h">
    <div class="num">${fa(num)}</div>
    <div class="ttl">
      <h2>${title}</h2>
      <div class="en"><b>${abbr}</b>${en ? `<span>${en}</span>` : ''}</div>
    </div>
    ${loc ? `<div class="loc">${locIcon ? ICON[locIcon] : ''}${loc}</div>` : ''}
  </div>
  ${body.map((p) => `<p>${p}</p>`).join('')}
  ${fig ? `<div class="fig">${fig}</div>` : figBox}
  ${after}
  ${list.map((n) => note({ ...n, color })).join('')}
</section>`;
  }

  return { header, footer, page, card, note };
}

const baseCss = `
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
.pf b { color: var(--soft); font-weight: 800; direction: ltr; unicode-bidi: isolate; }
.pf a { color: var(--gold); font-weight: 700; text-decoration: none; direction: ltr; unicode-bidi: isolate; }

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
.card p b { color: var(--text); font-weight: 800; }
.fig { border-radius: 3mm; border: 1px solid var(--line); line-height: 0; background: #0B111C; overflow: hidden; }
.card .fig { flex: 1 1 auto; min-height: 0; display: flex; }
.card .fig svg { width: 100%; height: 100%; }
.fig svg { display: block; width: 100%; height: auto; }
.note { display: flex; gap: 2.4mm; align-items: flex-start; font-size: 9pt; line-height: 1.8; color: var(--soft); padding: 2.2mm 3.2mm; border-radius: 2.4mm; background: var(--acc-note); border-right: 3px solid var(--acc); }
.note .ni { color: var(--acc); flex: none; margin-top: .9mm; }
.note b { color: var(--acc); font-weight: 800; }
.note.wide { font-size: 9.2pt; }
.sec { margin: 1mm 0 0; font-size: 12pt; font-weight: 900; display: flex; align-items: center; gap: 2.4mm; }
.sec::before { content: ''; width: 1.2mm; height: 5mm; border-radius: 1mm; background: var(--gold); }
.disc { margin: 0; font-size: 7.6pt; color: var(--muted); line-height: 1.8; text-align: center; }
`;

// cover page: title block, hero chart, intro and table of contents
const coverCss = `
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
`;

// Command line: [out.pdf] [--png <dir>]
function cliArgs(defaultOut) {
  const args = process.argv.slice(2);
  const pngIdx = args.indexOf('--png');
  const pngDir = pngIdx >= 0 ? args[pngIdx + 1] : null;
  const out = path.resolve(args.find((a, i) => !a.startsWith('--') && (pngIdx < 0 || i !== pngIdx + 1)) || defaultOut);
  return { out, pngDir };
}

// `body` is either an HTML string or a function (fit) => HTML. In the second form,
// charts placed with fit(key, draw) are first measured with their SVG taken out of the
// flow, then redrawn at exactly the aspect ratio of the box the layout gave them, so no
// chart is letterboxed inside a taller or wider box.
async function render({ title, css, body, out, pngDir }) {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'lesson-'));
  const htmlPath = path.join(tmp, 'lesson.html');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 2 });

  const load = async (bodyHtml, extraCss = '') => {
    fs.writeFileSync(htmlPath, `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>${title}</title><style>${baseCss}${css}${extraCss}</style></head>
<body>${bodyHtml}</body></html>`);
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
  };

  if (typeof body === 'function') {
    const fitter = (ratios) => (key, draw, { w = 1000, h = 400, min = 0.25, cls = '' } = {}) => {
      const r = ratios && ratios[key];
      const hh = r ? Math.max(Math.floor(w * Math.max(r, min)) - 2, 60) : h;
      return `<div class="fig fit ${cls}" data-fit="${key}">${draw(hh)}</div>`;
    };
    await load(body(fitter(null)), '.fig.fit { position: relative; } .fig.fit > svg { position: absolute; inset: 0; }');
    const ratios = await page.evaluate(() => Object.fromEntries([...document.querySelectorAll('.fig.fit')].map((f) => [f.dataset.fit, f.clientHeight / f.clientWidth])));
    await load(body(fitter(ratios)));
  } else {
    await load(body);
  }

  const spare = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const pr = p.getBoundingClientRect();
    const bottom = Math.max(...[...p.children].filter((c) => !c.matches('.pf')).map((c) => c.getBoundingClientRect().bottom));
    return { page: i + 1, spare: Math.round(pr.bottom - bottom) };
  }));
  console.log('space left above footer (px):', JSON.stringify(spare));
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

module.exports = { BG, CARD, WARN, fa, mix, acc, ICON, layout, baseCss, coverCss, cliArgs, render };
