'use strict';
// Illustrations for LIT lesson 3 — IDM (Inducement).
const { COLORS: K, makeCandles, chart } = require('../common/charts');
const { TEAM } = require('../common/team');

// Every figure takes its height (viewBox units, width 1000 or 600) so the build can fit it to its box.

// Bullish IDM leg: swing low (8) → internal low (19) → new high (30) →
// minor lower high (36) → sweep below the internal low (40) → structure break (45) → continuation.
const LV = { low: 99.6, internal: 111.4, high: 128.6, minor: 120.4, sweep: 109.8 };

function bullCandles(seed) {
  return makeCandles({
    n: 56, seed, noise: 0.8,
    keys: [[0, 107.5], [3, 104.5], [8, 100.8], [11, 106], [15, 117.4], [17, 114.6], [19, 112.4], [22, 117], [26, 123.5], [30, 127.6],
      [32, 124], [34, 117.9], [36, 119.4], [38, 115.8], [40, 113.3], [42, 115.6], [44, 118.4], [45, 121.8], [48, 123.6], [51, 127.2], [55, 131.6]],
    capHi: [[0, 51, 128.2], [37, 44, 119.9]],
    capLo: [[16, 18, 112.6], [20, 39, 111.9], [9, 55, 100.2]],
    ov: {
      8: { l: LV.low },
      15: { h: 118.6 },
      19: { l: LV.internal },
      30: { h: LV.high },
      36: { o: 118.5, h: LV.minor, l: 117.8, c: 119.3 },
      40: { o: 112.9, h: 113.9, l: LV.sweep, c: 113.4 },
      45: { o: 118.6, h: 122.3, l: 118.3, c: 121.8 },
    },
  });
}

const mirror = (cs, a) => cs.map((c) => ({ o: a - c.o, h: a - c.l, l: a - c.h, c: a - c.c }));

// Step-by-step IDM chart; dir = 1 bullish, -1 bearish (mirror image of the bullish leg).
function scenario(dir, h = 470) {
  const A = 231.2;
  const P = (p) => (dir > 0 ? p : A - p);
  const out = (d) => d * dir; // positive = beyond the extreme on the "low" side for bullish
  const bull = dir > 0;
  const t = bull
    ? { low: 'کف', high: 'سقف جدید', internal: 'کف داخلی', confirm: 'تأیید صعودی', zone: K.blue }
    : { low: 'سقف', high: 'کف جدید', internal: 'سقف داخلی', confirm: 'تأیید نزولی', zone: K.gold };
  const trend = bull ? K.bull : K.bear;
  const candles = bull ? bullCandles(5) : mirror(bullCandles(29), A);
  return chart({
    candles, h, min: 96, max: 135.5, hl: [40], watermark: TEAM.name,
    draw(a) {
      a.zone({ p1: P(LV.internal), p2: P(107.9), i1: 19, i2: 40, color: t.zone, op: 0.3 });
      a.orders({ p1: P(LV.internal), p2: P(107.9), i1: 21, i2: 38, color: t.zone, count: 16, seed: 4 });
      a.level({ p: P(LV.internal), i1: 19, i2: 41, color: K.magenta, tag: false });
      a.highlight({ i: 40, color: K.magenta });
      a.level({ p: P(LV.minor), i1: 36, i2: 46, color: trend, dash: '5 5', w: 2, glow: false, tag: false });
      a.dot({ i: 8, p: P(LV.low), color: trend, r: 6 });
      a.dot({ i: 30, p: P(LV.high), color: trend, r: 6 });
      a.dot({ i: 19, p: P(LV.internal), color: K.magenta, r: 6 });
      a.pill({ i: 29, p: P(LV.internal), text: 'IDM', color: K.magenta, solid: true, size: 18 });

      a.num({ i: 8, p: P(LV.low), dy: out(30), text: '۱', color: trend });
      a.text({ i: 9.3, p: P(LV.low), dy: out(30), text: t.low, color: K.text, size: 16, weight: 700, anchor: 'left' });
      a.num({ i: 30, p: P(LV.high), dy: out(-30), text: '۲', color: trend });
      a.text({ i: 31.3, p: P(LV.high), dy: out(-30), text: t.high, color: K.text, size: 16, weight: 700, anchor: 'left' });
      a.num({ i: 19, p: P(LV.internal), dy: out(30), text: '۳', color: K.magenta });
      a.pill({ i: 20.1, p: P(LV.internal), dy: out(30), text: t.internal, color: K.magenta, size: 15, anchor: 'left', px: 8, py: 3 });
      a.num({ i: 40, p: P(LV.sweep), dy: out(32), text: '۴', color: K.magenta });
      a.text({ i: 41.3, p: P(LV.sweep), dy: out(32), text: 'جمع‌آوری نقدینگی', color: K.magenta, size: 16, weight: 700, anchor: 'left' });
      a.num({ i: 41, p: P(LV.minor), dy: out(-24), text: '۵', color: trend });
      a.text({ i: 39.7, p: P(LV.minor), dy: out(-24), text: t.confirm, color: trend, size: 16, weight: 700, anchor: 'right' });
      a.arrow({ i1: 47.5, p1: P(126.2), i2: 53.5, p2: P(133.4), color: trend, w: 2.8, bend: out(10) });
    },
  });
}

// Section 1: why the internal low "induces" early entries.
function whatIs(h = 450) {
  const candles = bullCandles(13);
  return chart({
    candles, h, min: 96, max: 135.5, hl: [40], watermark: TEAM.name,
    draw(a) {
      a.zone({ p1: LV.internal, p2: 106.8, i1: 19, i2: 40, color: K.blue, op: 0.32 });
      a.orders({ p1: LV.internal, p2: 106.8, i1: 21, i2: 38, color: K.blue, count: 18, seed: 7 });
      a.level({ p: LV.internal, i1: 19, i2: 41, color: K.magenta, tag: false });
      a.highlight({ i: 40, color: K.magenta });
      a.dot({ i: 19, p: LV.internal, color: K.magenta, r: 6 });
      a.pill({ i: 13, p: 125.6, text: 'ورود زودهنگام خریداران', color: K.bull, size: 16 });
      a.arrow({ i1: 15.5, p1: 123.6, i2: 20.2, p2: 114.6, color: K.bull, w: 2.2, bend: -14, head: 9 });
      a.pill({ i: 29.5, p: 109.2, text: 'حد ضرر زیر کف داخلی', color: K.blue, size: 15 });
      a.text({ i: 18.4, p: LV.internal, dy: 22, text: 'کف داخلی', color: K.magenta, size: 16, weight: 800, anchor: 'right' });
      a.text({ i: 41.4, p: LV.sweep, dy: 4, text: 'نفوذ به زیر کف', color: K.magenta, size: 16, weight: 700, anchor: 'left' });
      a.arrow({ i1: 47.5, p1: 126.2, i2: 53.5, p2: 133.4, color: K.bull, w: 2.8, bend: 10 });
      a.pill({ i: 44.5, p: 131.5, text: 'ادامه حرکت صعودی', color: K.bull, size: 15 });
    },
  });
}

function hero(h = 500) {
  const candles = bullCandles(7);
  return chart({
    candles, h, min: 96, max: 136, hl: [40], watermark: TEAM.name,
    draw(a) {
      a.zone({ p1: LV.high, p2: 133.6, i1: 30, color: K.gold, op: 0.26 });
      a.orders({ p1: LV.high, p2: 133.6, i1: 32, color: K.gold, count: 18, seed: 2 });
      a.level({ p: LV.high, i1: 30, color: K.gold });
      a.zone({ p1: LV.internal, p2: 107.6, i1: 19, i2: 40, color: K.blue, op: 0.3 });
      a.orders({ p1: LV.internal, p2: 107.6, i1: 21, i2: 38, color: K.blue, count: 16, seed: 5 });
      a.level({ p: LV.internal, i1: 19, i2: 41, color: K.magenta, tag: false });
      a.highlight({ i: 40, color: K.magenta });
      a.dot({ i: 19, p: LV.internal, color: K.magenta, r: 6 });
      a.dot({ i: 30, p: LV.high, color: K.gold, r: 6 });
      a.pill({ i: 29, p: LV.internal, text: 'IDM', color: K.magenta, solid: true, size: 19 });
      a.pill({ i: 40, p: LV.sweep, dy: 34, text: 'Sweep', color: K.magenta, size: 16 });
      a.pill({ i: 38, p: 131.6, text: 'BSL', color: K.gold, solid: true, size: 18 });
      a.text({ i: 18.4, p: LV.internal, dy: 22, text: 'کف داخلی', color: K.magenta, size: 16, weight: 800, anchor: 'right' });
      a.arrow({ i1: 46.5, p1: 125, i2: 53.5, p2: 133.6, color: K.bull, w: 3, bend: 10 });
    },
  });
}

// Section 5: the full checklist on one chart, with a position box.
function method(h = 450) {
  const E = 120.6;
  const SL = 113.4;
  const TP = 131.4;
  const candles = makeCandles({
    n: 60, seed: 19, noise: 0.75,
    keys: [[0, 106], [4, 101.8], [9, 110], [14, 115.6], [17, 111.5], [20, 109.2], [24, 114.5], [26, 117.4], [28, 115.8], [32, 121.5], [36, 126],
      [38, 122.5], [40, 118.8], [41, 119.2], [43, 116.5], [44, 116.6], [46, 117.8], [47, 118.6], [48, E], [51, 123], [55, 127.8], [59, 130.4]],
    capHi: [[0, 13, 116], [15, 22, 116], [0, 35, 126.4], [42, 47, 119.4]],
    capLo: [[29, 43, 115.5], [5, 59, 101.4]],
    ov: {
      4: { l: 100.8 },
      14: { h: 116.6 },
      20: { l: 108.4 },
      28: { l: 115.0 },
      36: { h: 127.0 },
      41: { o: 118.7, h: 119.8, l: 118.2, c: 119.1 },
      44: { o: 116.4, h: 117.0, l: 114.0, c: 116.8 },
      48: { o: 118.4, h: 121.0, l: 118.1, c: E },
    },
  });
  return chart({
    candles, h, min: 97, max: 135, hl: [44, 48], lastTag: false, watermark: TEAM.name,
    draw(a) {
      a.zone({ p1: E, p2: TP, i1: 48.5, i2: 59, color: K.bull, op: 0.2, fade: false });
      a.zone({ p1: E, p2: SL, i1: 48.5, i2: 59, color: K.bear, op: 0.2, fade: false });
      a.level({ p: E, i1: 48, i2: 59, color: '#E6EAF2', dash: '0', w: 1.6, glow: false, tag: false });
      a.axisTag({ p: TP, color: K.bull, text: 'TP' });
      a.axisTag({ p: E, color: '#E6EAF2', text: 'Entry' });
      a.axisTag({ p: SL, color: K.bear, text: 'SL' });
      a.level({ p: 115.0, i1: 28, i2: 45, color: K.magenta, tag: false });
      a.level({ p: 119.8, i1: 41, i2: 49, color: K.bull, dash: '5 5', w: 2, glow: false, tag: false });
      a.highlight({ i: 44, color: K.magenta, pad: 6 });

      for (const [i, p, txt, up] of [[4, 100.8, 'HL', false], [14, 116.6, 'HH', true], [20, 108.4, 'HL', false], [36, 127.0, 'HH', true]]) {
        a.pill({ i, p, dy: up ? -22 : 22, text: txt, color: K.muted, size: 13, px: 8, py: 3 });
      }
      a.arrow({ i1: 1, p1: 112, i2: 8, p2: 123, color: K.bull, w: 3, bend: -6 });
      a.num({ i: 1.6, p: 126.6, text: '۱', color: K.cyan });
      a.text({ i: 2.9, p: 126.6, text: 'جهت بازار', color: K.text, size: 15, weight: 700, anchor: 'left' });
      a.num({ i: 36, p: 127.0, dy: -50, text: '۲', color: K.cyan });
      a.num({ i: 28, p: 115.0, dy: 26, text: '۳', color: K.cyan });
      a.pill({ i: 34, p: 115.0, text: 'IDM', color: K.magenta, solid: true, size: 16 });
      a.num({ i: 34, p: 115.0, dy: 26, text: '۴', color: K.cyan });
      a.num({ i: 44, p: 114.0, dy: 30, text: '۵', color: K.cyan });
      a.num({ i: 45, p: 119.8, dy: -22, text: '۶', color: K.cyan });
      a.num({ i: 50, p: 116.2, text: '۷', color: K.cyan });
      a.text({ i: 51.3, p: 116.2, text: 'حد ضرر', color: K.bear, size: 15, weight: 800, anchor: 'left' });
      a.text({ i: 51.3, p: 128.6, text: 'حد سود', color: K.bull, size: 15, weight: 800, anchor: 'left' });
    },
  });
}

const mini = (cfg) => chart({ w: 600, axis: false, vGrid: 6, gridStep: 4, right: 1, fs: 1.3, ...cfg });

// internal low pierced, then the trend resumes
function miniIdm(h = 260, label = 'IDM') {
  const candles = makeCandles({
    n: 24, seed: 51, noise: 0.5,
    keys: [[0, 104.5], [4, 110.5], [7, 115.6], [10, 111.2], [14, 119.4], [16, 116], [18, 111.6], [20, 115.5], [23, 121.5]],
    capLo: [[11, 17, 111.0]], capHi: [[0, 19, 120]],
    ov: { 10: { l: 110.5 }, 14: { h: 120.2 }, 18: { o: 111.8, h: 112.6, l: 108.8, c: 112.2 } },
  });
  return mini({
    candles, h, min: 101, max: 124.5, hl: [18],
    draw(a) {
      a.zone({ p1: 110.5, p2: 107.6, i1: 10, i2: 18, color: K.blue, op: 0.3 });
      a.level({ p: 110.5, i1: 10, i2: 19, color: K.magenta, tag: false });
      a.highlight({ i: 18, color: K.magenta, pad: 5 });
      a.arrow({ i1: 19.5, p1: 115, i2: 22.6, p2: 122.6, color: K.bull, w: 3, bend: 6 });
      a.pill({ i: 6, p: 121.5, text: label, color: K.magenta, solid: true, size: 15 });
    },
  });
}

// level crossed and price returns to the old range
function miniSweep(h = 260) {
  const candles = makeCandles({
    n: 22, seed: 41, noise: 0.6,
    keys: [[0, 108], [4, 115.8], [7, 112], [10, 110.5], [13, 114.8], [14, 115.2], [15, 112], [18, 108.5], [21, 107.5]],
    capHi: [[0, 21, 116.0]],
    ov: { 4: { h: 116.8 }, 14: { o: 114.9, h: 119.6, l: 114.5, c: 114.7 }, 15: { o: 114.7, c: 111.8 } },
  });
  return mini({
    candles, h, min: 104, max: 122.5, hl: [14],
    draw(a) {
      a.level({ p: 116.8, i1: 4, color: K.gold, tag: false });
      a.highlight({ i: 14, color: K.magenta, pad: 5 });
      a.arrow({ i1: 16, p1: 113.6, i2: 19.5, p2: 109.6, color: K.magenta, w: 3, bend: 14 });
      a.pill({ i: 7.5, p: 120.6, text: 'Sweep', color: K.magenta, solid: true, size: 15 });
    },
  });
}

// resting liquidity above equal-ish highs
function miniBsl(h = 260) {
  const candles = makeCandles({
    n: 22, seed: 45, noise: 0.5,
    keys: [[0, 106], [4, 114.2], [7, 110.5], [11, 114.4], [14, 110.8], [18, 114.1], [21, 112]],
    capHi: [[0, 21, 114.6]],
    ov: { 4: { h: 115.2 }, 11: { h: 115.4 }, 18: { h: 115.1 } },
  });
  return mini({
    candles, h, min: 103, max: 121.5,
    draw(a) {
      a.zone({ p1: 115.4, p2: 119.2, i1: 4, color: K.gold, op: 0.32 });
      a.orders({ p1: 115.4, p2: 119.2, i1: 5, color: K.gold, count: 14, seed: 3 });
      a.level({ p: 115.4, i1: 4, color: K.gold, tag: false });
      a.pill({ i: 11, p: 117.4, text: 'BSL', color: K.gold, solid: true, size: 15 });
    },
  });
}

// resting liquidity below equal-ish lows
function miniSsl(h = 260) {
  const candles = makeCandles({
    n: 22, seed: 47, noise: 0.5,
    keys: [[0, 116], [4, 107.8], [7, 111.5], [11, 107.6], [14, 111.2], [18, 107.9], [21, 110]],
    capLo: [[0, 21, 107.4]],
    ov: { 4: { l: 106.8 }, 11: { l: 106.6 }, 18: { l: 106.9 } },
  });
  return mini({
    candles, h, min: 100.5, max: 119,
    draw(a) {
      a.zone({ p1: 106.6, p2: 102.8, i1: 4, color: K.blue, op: 0.32 });
      a.orders({ p1: 106.6, p2: 102.8, i1: 5, color: K.blue, count: 14, seed: 6 });
      a.level({ p: 106.6, i1: 4, color: K.blue, tag: false });
      a.pill({ i: 11, p: 104.6, text: 'SSL', color: K.blue, solid: true, size: 15 });
    },
  });
}

// the internal low breaks and price keeps falling: not every break comes back
function miniRealBreak(h = 260) {
  const candles = makeCandles({
    n: 24, seed: 53, noise: 0.5,
    keys: [[0, 104.5], [4, 110.5], [7, 115.6], [10, 111.2], [14, 119.4], [16, 116], [17, 113], [18, 108.6], [20, 106.5], [23, 102.4]],
    capLo: [[11, 17, 111.0]], capHi: [[15, 23, 117]],
    ov: { 10: { l: 110.5 }, 14: { h: 120.2 }, 18: { o: 112.8, h: 113.1, l: 108.2, c: 108.6 } },
  });
  return mini({
    candles, h, min: 99.5, max: 124.5, hl: [18],
    draw(a) {
      a.level({ p: 110.5, i1: 10, color: K.bear, tag: false });
      a.highlight({ i: 18, color: K.bear, pad: 5 });
      a.arrow({ i1: 19.5, p1: 112.5, i2: 22.8, p2: 104.6, color: K.bear, w: 3, bend: -8 });
      a.pill({ i: 6, p: 121.5, text: 'شکست واقعی', color: K.bear, solid: true, size: 15 });
    },
  });
}

// Summary: structure → liquidity → confirmation, matching the three parts of the main rule.
function recap(h = 300) {
  const candles = bullCandles(23);
  return chart({
    candles, h, min: 96, max: 135.5, hl: [40], watermark: TEAM.name,
    draw(a) {
      a.zone({ p1: LV.internal, p2: 107.9, i1: 19, i2: 40, color: K.blue, op: 0.3 });
      a.level({ p: LV.internal, i1: 19, i2: 41, color: K.magenta, tag: false });
      a.highlight({ i: 40, color: K.magenta });
      a.level({ p: LV.minor, i1: 36, i2: 46, color: K.bull, dash: '5 5', w: 2, glow: false, tag: false });
      a.dot({ i: 8, p: LV.low, color: K.cyan, r: 6 });
      a.dot({ i: 30, p: LV.high, color: K.cyan, r: 6 });
      a.pill({ i: 8, p: LV.low, dy: 26, text: 'HL', color: K.muted, size: 14, px: 8, py: 3 });
      a.pill({ i: 30, p: LV.high, dy: -26, text: 'HH', color: K.muted, size: 14, px: 8, py: 3 });
      a.num({ i: 32.4, p: LV.high, dy: -26, text: '۱', color: K.cyan });
      a.text({ i: 33.7, p: LV.high, dy: -26, text: 'ساختار', color: K.cyan, size: 16, weight: 800, anchor: 'left' });
      a.pill({ i: 29, p: LV.internal, text: 'IDM', color: K.magenta, solid: true, size: 17 });
      a.num({ i: 40, p: LV.sweep, dy: 32, text: '۲', color: K.magenta });
      a.text({ i: 41.3, p: LV.sweep, dy: 32, text: 'نقدینگی', color: K.magenta, size: 16, weight: 800, anchor: 'left' });
      a.num({ i: 41, p: LV.minor, dy: -24, text: '۳', color: K.bull });
      a.text({ i: 39.7, p: LV.minor, dy: -24, text: 'تأیید', color: K.bull, size: 16, weight: 800, anchor: 'right' });
      a.arrow({ i1: 47.5, p1: 126.2, i2: 53.5, p2: 133.4, color: K.bull, w: 2.8, bend: 10 });
    },
  });
}

module.exports = { hero, whatIs, bullish: (h) => scenario(1, h), bearish: (h) => scenario(-1, h), method, recap, miniIdm, miniSweep, miniBsl, miniSsl, miniRealBreak };
