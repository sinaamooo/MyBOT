'use strict';
// One function per illustration. Candle paths are hand-shaped so each concept reads clearly.
const { COLORS: K, makeCandles, chart } = require('../common/charts');

function hero() {
  const candles = makeCandles({
    n: 64, seed: 7, noise: 1.0,
    keys: [[0, 112], [6, 118], [13, 127.5], [19, 119], [24, 115], [30, 104.5], [36, 112], [44, 123.5], [49, 117], [54, 110], [59, 114], [63, 117.5]],
    capHi: [[0, 63, 128.2]], capLo: [[0, 63, 103.3]],
    ov: { 13: { h: 129.2 }, 30: { l: 102.4 } },
  });
  return chart({
    candles, h: 540, min: 96, max: 135.5, watermark: 'LIQUIDITY',
    draw(a) {
      a.zone({ p1: 129.2, p2: 134.2, i1: 13, color: K.gold, op: 0.3 });
      a.orders({ p1: 129.2, p2: 134.2, i1: 15, color: K.gold, count: 30, seed: 4 });
      a.level({ p: 129.2, i1: 13, color: K.gold });
      a.zone({ p1: 102.4, p2: 97.4, i1: 30, color: K.blue, op: 0.3 });
      a.orders({ p1: 102.4, p2: 97.4, i1: 32, color: K.blue, count: 22, seed: 9 });
      a.level({ p: 102.4, i1: 30, color: K.blue });
      a.dot({ i: 13, p: 129.2, color: K.gold });
      a.dot({ i: 30, p: 102.4, color: K.blue });
      a.pill({ i: 31, p: 131.9, text: 'BSL', color: K.gold, solid: true, size: 21 });
      a.pill({ i: 41.5, p: 131.9, text: 'نقدینگی سمت خرید', color: K.gold, size: 18 });
      a.pill({ i: 42, p: 99.9, text: 'SSL', color: K.blue, solid: true, size: 21 });
      a.pill({ i: 52.5, p: 99.9, text: 'نقدینگی سمت فروش', color: K.blue, size: 18 });
      a.text({ i: 13, p: 129.2, dy: -26, text: 'سقف مهم', color: K.gold, size: 17, weight: 700 });
      a.text({ i: 30, p: 102.4, dy: 28, text: 'کف مهم', color: K.blue, size: 17, weight: 700 });
    },
  });
}

function bsl() {
  const candles = makeCandles({
    n: 48, seed: 11, noise: 0.9,
    keys: [[0, 103], [7, 110], [14, 123.4], [19, 117], [22, 115.5], [27, 122.9], [31, 118], [34, 117], [38, 123.2], [43, 118.5], [47, 120]],
    capHi: [[0, 47, 124.0]],
    ov: { 14: { h: 125.6 }, 27: { h: 124.6 }, 38: { h: 124.9 } },
  });
  return chart({
    candles, min: 99, max: 131.5,
    draw(a) {
      a.zone({ p1: 125.6, p2: 130.6, i1: 14, color: K.gold, op: 0.32 });
      a.orders({ p1: 125.6, p2: 130.6, i1: 16, color: K.gold, count: 26, seed: 5 });
      a.level({ p: 125.6, i1: 14, color: K.gold });
      a.dot({ i: 14, p: 125.6, color: K.gold });
      a.dot({ i: 27, p: 124.6, color: K.bear, r: 5.5 });
      a.dot({ i: 38, p: 124.9, color: K.bear, r: 5.5 });
      a.text({ i: 14, p: 125.6, dx: -20, text: 'سقف مهم', color: K.gold, size: 17, weight: 700, anchor: 'right' });
      a.pill({ i: 21, p: 129.1, text: 'BSL', color: K.gold, solid: true, size: 20 });
      a.pill({ i: 31.5, p: 129.1, text: 'حد ضرر فروشندگان', color: K.gold, size: 16 });
      a.pill({ i: 42.5, p: 129.1, text: 'سفارش خرید شکست', color: K.gold, size: 16 });
      a.text({ i: 27, p: 126.7, text: 'عدم عبور', color: K.bear, size: 14, weight: 800 });
      a.text({ i: 38, p: 126.8, text: 'عدم عبور', color: K.bear, size: 14, weight: 800 });
    },
  });
}

function ssl() {
  const candles = makeCandles({
    n: 48, seed: 23, noise: 0.9,
    keys: [[0, 126], [7, 119], [14, 105.6], [19, 112], [22, 113.5], [27, 106.1], [31, 111], [34, 112], [38, 105.8], [43, 110.5], [47, 109]],
    capLo: [[0, 47, 105.0]],
    ov: { 14: { l: 103.4 }, 27: { l: 104.4 }, 38: { l: 104.1 } },
  });
  return chart({
    candles, min: 97, max: 130,
    draw(a) {
      a.zone({ p1: 103.4, p2: 98.4, i1: 14, color: K.blue, op: 0.32 });
      a.orders({ p1: 103.4, p2: 98.4, i1: 16, color: K.blue, count: 26, seed: 8 });
      a.level({ p: 103.4, i1: 14, color: K.blue });
      a.dot({ i: 14, p: 103.4, color: K.blue });
      a.dot({ i: 27, p: 104.4, color: K.bull, r: 5.5 });
      a.dot({ i: 38, p: 104.1, color: K.bull, r: 5.5 });
      a.text({ i: 14, p: 103.4, dx: -20, text: 'کف مهم', color: K.blue, size: 17, weight: 700, anchor: 'right' });
      a.pill({ i: 21, p: 99.9, text: 'SSL', color: K.blue, solid: true, size: 20 });
      a.pill({ i: 31.5, p: 99.9, text: 'حد ضرر خریداران', color: K.blue, size: 16 });
      a.pill({ i: 42.5, p: 99.9, text: 'سفارش فروش شکست', color: K.blue, size: 16 });
      a.text({ i: 27, p: 102.3, text: 'عدم عبور', color: K.bull, size: 14, weight: 800 });
      a.text({ i: 38, p: 102.3, text: 'عدم عبور', color: K.bull, size: 14, weight: 800 });
    },
  });
}

function eqh() {
  const candles = makeCandles({
    n: 50, seed: 5, noise: 0.7, wick: 0.8,
    keys: [[0, 103], [5, 108.5], [10, 119.1], [13, 115], [16, 112.5], [22, 119.3], [25, 115], [28, 111.5], [35, 119.0], [38, 115.5], [42, 113.5], [46, 116], [49, 117.2]],
    capHi: [[0, 49, 119.5]],
    ov: { 10: { h: 120.05 }, 22: { h: 120.35 }, 35: { h: 119.85 } },
  });
  return chart({
    candles, min: 100, max: 126,
    draw(a) {
      a.zone({ p1: 120.35, p2: 124.6, i1: 10, color: K.gold, op: 0.3 });
      a.orders({ p1: 120.35, p2: 124.6, i1: 12, color: K.gold, count: 24, seed: 2 });
      a.box({ p1: 119.85, p2: 120.35, i1: 9, i2: 36, color: K.gold, op: 0.12, dash: '0', sw: 0, so: 0 });
      a.level({ p: 120.1, i1: 10, color: K.gold, dash: '3 6', w: 2.4 });
      a.num({ i: 10, p: 120.05, dy: -24, text: '۱', color: K.gold });
      a.num({ i: 22, p: 120.35, dy: -24, text: '۲', color: K.gold });
      a.num({ i: 35, p: 119.85, dy: -24, text: '۳', color: K.gold });
      a.pill({ i: 16, p: 122.9, text: 'EQH', color: K.gold, solid: true, size: 20 });
      a.pill({ i: 28.5, p: 122.9, text: 'Equal Highs', color: K.gold, size: 16 });
      a.pill({ i: 42.5, p: 123.3, text: 'ناحیه احتمالی نقدینگی', color: K.gold, size: 16 });
      a.text({ i: 37, p: 104.5, text: 'سقف‌ها نزدیک به هم هستند، نه لزوماً دقیقاً برابر', color: K.muted, size: 15, weight: 600 });
    },
  });
}

function eql() {
  const candles = makeCandles({
    n: 50, seed: 31, noise: 0.7, wick: 0.8,
    keys: [[0, 123], [5, 117.5], [10, 106.9], [13, 111], [16, 113.5], [22, 106.7], [25, 111], [28, 114.5], [35, 107.0], [38, 110.5], [42, 112.5], [46, 110], [49, 108.8]],
    capLo: [[0, 49, 106.5]],
    ov: { 10: { l: 105.95 }, 22: { l: 105.65 }, 35: { l: 106.15 } },
  });
  return chart({
    candles, min: 100, max: 126,
    draw(a) {
      a.zone({ p1: 105.65, p2: 101.4, i1: 10, color: K.blue, op: 0.3 });
      a.orders({ p1: 105.65, p2: 101.4, i1: 12, color: K.blue, count: 24, seed: 6 });
      a.box({ p1: 105.65, p2: 106.15, i1: 9, i2: 36, color: K.blue, op: 0.14, dash: '0', sw: 0, so: 0 });
      a.level({ p: 105.9, i1: 10, color: K.blue, dash: '3 6', w: 2.4 });
      a.num({ i: 10, p: 105.95, dy: 24, text: '۱', color: K.blue });
      a.num({ i: 22, p: 105.65, dy: 24, text: '۲', color: K.blue });
      a.num({ i: 35, p: 106.15, dy: 24, text: '۳', color: K.blue });
      a.pill({ i: 16, p: 103.1, text: 'EQL', color: K.blue, solid: true, size: 20 });
      a.pill({ i: 28.5, p: 103.1, text: 'Equal Lows', color: K.blue, size: 16 });
      a.pill({ i: 42.5, p: 102.7, text: 'نقدینگی سمت فروش', color: K.blue, size: 16 });
      a.text({ i: 37, p: 121.5, text: 'قیمت چند بار به این محدوده رسید و پایین‌تر نرفت', color: K.muted, size: 15, weight: 600 });
    },
  });
}

function sweep() {
  const candles = makeCandles({
    n: 48, seed: 9, noise: 0.8,
    keys: [[0, 107], [5, 112], [10, 121.0], [14, 116], [18, 112.5], [23, 118], [26, 120.6], [27, 120.3], [28, 116.2], [32, 113], [36, 110], [40, 108.4], [44, 110], [47, 109]],
    capHi: [[0, 47, 121.5]],
    ov: { 10: { h: 122.2 }, 26: { h: 121.4 }, 27: { o: 120.7, h: 125.0, l: 119.9, c: 120.2 }, 28: { o: 120.2, h: 120.5, l: 115.9, c: 116.2 } },
  });
  return chart({
    candles, min: 103, max: 129, hl: [27],
    draw(a) {
      a.zone({ p1: 122.2, p2: 126.6, i1: 10, i2: 27, color: K.gold, op: 0.24 });
      a.level({ p: 122.2, i1: 10, color: K.gold });
      a.highlight({ i: 27, color: K.magenta });
      a.dot({ i: 10, p: 122.2, color: K.gold, r: 5.5 });
      a.pill({ i: 17.5, p: 124.6, text: 'سقف قبلی', color: K.gold, size: 16 });
      a.pill({ i: 27, p: 127.3, text: 'Liquidity Sweep', color: K.magenta, solid: true, size: 19 });
      a.text({ i: 28.2, p: 124.4, text: 'عبور از سقف', color: K.magenta, size: 16, weight: 700, anchor: 'left' });
      a.text({ i: 28.6, p: 121.1, text: 'بسته‌شدن زیر سطح', color: K.text, size: 15, weight: 600, anchor: 'left' });
      a.arrow({ i1: 31, p1: 118.0, i2: 37.5, p2: 111.8, color: K.magenta, w: 2.6, bend: 26 });
      a.pill({ i: 40.5, p: 115.2, text: 'بازگشت به محدوده قبلی', color: K.magenta, size: 16 });
    },
  });
}

function breakout() {
  const candles = makeCandles({
    n: 48, seed: 13, noise: 0.8,
    keys: [[0, 104], [4, 109.5], [8, 119.2], [12, 114], [15, 113], [18, 119.0], [21, 114.5], [24, 119.2], [27, 115.5], [29, 117.4], [30, 123.6], [33, 125.6], [36, 122.4], [37, 121.6], [41, 125.8], [47, 130.8]],
    capHi: [[0, 29, 119.6]], capLo: [[31, 47, 121.2]],
    ov: { 8: { h: 120.1 }, 18: { h: 119.95 }, 24: { h: 120.15 }, 30: { o: 117.4, h: 124.2, l: 117.1, c: 123.7 }, 37: { l: 120.45 } },
  });
  return chart({
    candles, min: 101, max: 134.5, hl: [30],
    draw(a) {
      a.zone({ p1: 120.1, p2: 117.6, i1: 8, i2: 29, color: K.cyan, op: 0.14 });
      a.level({ p: 120.1, i1: 8, color: K.cyan });
      a.highlight({ i: 30, color: K.cyan });
      a.pill({ i: 30, p: 127.6, text: 'Breakout', color: K.cyan, solid: true, size: 19 });
      a.text({ i: 29.1, p: 122.9, text: 'بسته‌شدن کندل بالای سطح', color: K.text, size: 15, weight: 600, anchor: 'right' });
      a.text({ i: 15.5, p: 122.0, text: 'مقاومت', color: K.cyan, size: 16, weight: 700 });
      a.dot({ i: 37, p: 120.45, color: K.cyan, r: 5.5 });
      a.text({ i: 37, p: 120.45, dy: 26, text: 'رفتار پس از عبور', color: K.cyan, size: 15, weight: 700 });
      a.arrow({ i1: 40, p1: 129.4, i2: 46, p2: 133.6, color: K.bull, w: 2.6, bend: -10 });
      a.pill({ i: 40.5, p: 131.8, dx: -40, text: 'ادامه حرکت', color: K.bull, size: 16 });
    },
  });
}

function irl() {
  const candles = makeCandles({
    n: 56, seed: 17, noise: 0.85,
    keys: [[0, 116], [3, 121], [6, 129.2], [9, 124], [13, 116], [17, 106], [20, 100.8], [23, 106], [27, 113], [30, 119.6], [33, 114], [36, 109.6], [40, 116], [44, 123.4], [47, 119], [50, 114.6], [53, 117], [55, 118.4]],
    capHi: [[0, 55, 129.4]], capLo: [[0, 55, 100.4]],
    ov: { 6: { h: 130.6 }, 20: { l: 99.5 }, 30: { h: 120.5 }, 36: { l: 108.7 }, 44: { h: 124.3 }, 50: { l: 113.8 } },
  });
  return chart({
    candles, min: 94, max: 136,
    draw(a) {
      a.box({ p1: 99.5, p2: 130.6, i1: 6, color: K.violet, op: 0.06 });
      a.level({ p: 130.6, i1: 6, color: K.violet, glow: false, dash: '0' , w: 2 });
      a.level({ p: 99.5, i1: 20, color: K.violet, glow: false, dash: '0', w: 2 });
      a.pill({ i: 14, p: 133.2, text: 'سقف محدوده مرجع', color: K.violet, size: 16 });
      a.pill({ i: 30, p: 96.9, text: 'کف محدوده مرجع', color: K.violet, size: 16 });
      for (const [i, p] of [[30, 120.5], [36, 108.7], [44, 124.3], [50, 113.8]]) {
        a.level({ p, i1: i, i2: i + 7, color: K.magenta, dash: '4 5', w: 2, glow: false, tag: false });
        a.dot({ i, p, color: K.magenta, r: 5, ring: false });
      }
      a.pill({ i: 30, p: 120.5, dy: -24, text: 'IRL', color: K.magenta, size: 14, px: 8, py: 4 });
      a.pill({ i: 36, p: 108.7, dy: 24, text: 'IRL', color: K.magenta, size: 14, px: 8, py: 4 });
      a.pill({ i: 44, p: 124.3, dy: -24, text: 'IRL', color: K.magenta, size: 14, px: 8, py: 4 });
      a.pill({ i: 50, p: 113.8, dy: 24, text: 'IRL', color: K.magenta, size: 14, px: 8, py: 4 });
      a.pill({ i: 44, p: 133.2, text: 'ERL ▲', color: K.violet, solid: true, size: 15, px: 9, py: 4 });
      a.pill({ i: 44, p: 96.9, text: 'ERL ▼', color: K.violet, solid: true, size: 15, px: 9, py: 4 });
      a.text({ i: 11, p: 104.0, text: 'نقدینگی داخلی', color: K.magenta, size: 17, weight: 800 });
      a.text({ i: 11, p: 101.9, text: 'Internal Range Liquidity', color: K.muted, size: 13, weight: 600 });
    },
  });
}

function pdhpdl() {
  const candles = makeCandles({
    n: 48, seed: 21, noise: 0.8,
    keys: [[0, 110], [4, 114], [9, 120.2], [12, 116], [14, 111], [17, 104.8], [20, 109], [23, 113], [26, 110.5], [28, 108.5], [32, 113], [36, 118.4], [38, 120.1], [39, 119.2], [41, 116.8], [44, 114.5], [47, 115.5]],
    capHi: [[0, 47, 120.6]], capLo: [[0, 47, 104.3]],
    ov: { 9: { h: 121.4 }, 17: { l: 103.4 }, 38: { h: 121.15 }, 39: { o: 120.1, h: 120.5, l: 117.9, c: 118.2 } },
  });
  return chart({
    candles, min: 99, max: 129.5,
    draw(a) {
      a.band({ i1: 0, i2: 23, color: '#ffffff', op: 0.03 });
      a.vline({ i: 23 });
      a.text({ i: 11.5, y: 24, text: 'روز قبل', color: K.muted, size: 17, weight: 700 });
      a.text({ i: 35.5, y: 24, text: 'امروز', color: K.text, size: 17, weight: 800 });
      a.level({ p: 121.4, i1: 9, color: K.orange });
      a.level({ p: 103.4, i1: 17, color: K.blue });
      a.dot({ i: 9, p: 121.4, color: K.orange, r: 5.5 });
      a.dot({ i: 17, p: 103.4, color: K.blue, r: 5.5 });
      a.pill({ i: 12, p: 123.7, text: 'PDH', color: K.orange, solid: true, size: 18 });
      a.text({ i: 14.4, p: 123.7, text: 'بالاترین قیمت روز قبل', color: K.orange, size: 15, weight: 600, anchor: 'left' });
      a.pill({ i: 20, p: 101.1, text: 'PDL', color: K.blue, solid: true, size: 18 });
      a.text({ i: 22.4, p: 101.1, text: 'پایین‌ترین قیمت روز قبل', color: K.blue, size: 15, weight: 600, anchor: 'left' });
      a.highlight({ i: 38, color: K.orange, pad: 6 });
      a.pill({ i: 38, p: 124.9, text: 'واکنش را زیر نظر بگیر', color: K.orange, size: 16 });
      a.arrow({ i1: 40, p1: 120.9, i2: 44.5, p2: 116.8, color: K.orange, w: 2.4, bend: -14, dash: '6 5' });
      a.text({ i: 44.5, p: 109.5, text: 'برخورد ≠ سیگنال', color: K.muted, size: 15, weight: 700 });
    },
  });
}

function miniSweep() {
  const candles = makeCandles({
    n: 22, seed: 41, noise: 0.6,
    keys: [[0, 108], [4, 115.8], [7, 112], [10, 110.5], [13, 114.8], [14, 115.2], [15, 112], [18, 108.5], [21, 107.5]],
    capHi: [[0, 21, 116.0]],
    ov: { 4: { h: 116.8 }, 14: { o: 114.9, h: 119.6, l: 114.5, c: 114.7 }, 15: { o: 114.7, c: 111.8 } },
  });
  return chart({
    candles, w: 600, h: 235, axis: false, min: 104, max: 122.5, hl: [14], vGrid: 6, gridStep: 4, right: 1, fs: 1.25,
    draw(a) {
      a.level({ p: 116.8, i1: 4, color: K.gold, tag: false });
      a.highlight({ i: 14, color: K.magenta });
      a.arrow({ i1: 16, p1: 113.6, i2: 19.5, p2: 109.6, color: K.magenta, w: 3, bend: 14 });
      a.pill({ i: 7.5, p: 120.6, text: 'Sweep', color: K.magenta, solid: true, size: 18 });
    },
  });
}

function miniBreakout() {
  const candles = makeCandles({
    n: 22, seed: 43, noise: 0.6,
    keys: [[0, 107], [4, 113.6], [7, 110.5], [10, 113.4], [12, 111.5], [13, 112.6], [14, 117.8], [16, 118.6], [18, 116.4], [21, 120.2]],
    capHi: [[0, 13, 114.0]], capLo: [[15, 21, 115.0]],
    ov: { 4: { h: 114.4 }, 10: { h: 114.3 }, 14: { o: 112.6, h: 118.2, l: 112.3, c: 117.9 } },
  });
  return chart({
    candles, w: 600, h: 235, axis: false, min: 104, max: 122.5, hl: [14], vGrid: 6, gridStep: 4, right: 1, fs: 1.25,
    draw(a) {
      a.level({ p: 114.4, i1: 4, color: K.cyan, tag: false });
      a.highlight({ i: 14, color: K.cyan });
      a.arrow({ i1: 16.5, p1: 120.2, i2: 20.5, p2: 121.8, color: K.bull, w: 3, bend: -6 });
      a.pill({ i: 7.5, p: 120.6, text: 'Breakout', color: K.cyan, solid: true, size: 18 });
    },
  });
}

module.exports = { hero, bsl, ssl, eqh, eql, sweep, breakout, irl, pdhpdl, miniSweep, miniBreakout };
