'use strict';
// Minimal SVG candlestick engine for the lesson illustrations.
// Prices are abstract (illustrative only) — no real market data.
//
// Everything is drawn with opaque colours: translucency is pre-blended against the
// panel colour with mix(). Alpha, soft masks and transparency groups make mobile PDF
// viewers (Telegram / Android pdfium) render pages very slowly and blurrily.

const PANEL = '#0B111C';

const hex = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
// colour `fg` at strength `a` (0..1) laid over `bg`, as a solid hex colour
function mix(fg, a, bg = PANEL) {
  const f = hex(fg);
  const b = hex(bg);
  return '#' + f.map((v, i) => Math.round(v * a + b[i] * (1 - a)).toString(16).padStart(2, '0')).join('');
}

const COLORS = {
  panel: PANEL,
  grid: mix('#94A3B8', 0.08),
  axisLine: mix('#94A3B8', 0.18),
  axis: '#5B6880',
  text: '#E6EAF2',
  muted: '#8A94A7',
  bull: '#1FD39B',
  bear: '#FF4D6D',
  gold: '#FFB020',
  blue: '#3DA9FC',
  violet: '#A78BFA',
  magenta: '#F472B6',
  cyan: '#22D3EE',
  orange: '#FB923C',
};

const isFa = (s) => /[؀-ۿ]/.test(s);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function rng(seed) {
  let s = (seed >>> 0) || 1;
  return () => (s = (s * 1664525 + 1013904223) >>> 0) / 4294967296;
}

// Builds OHLC candles whose closes pass through the given [index, price] keypoints.
// capHi/capLo: [[from, to, limit]] keep the noise from crossing a level by accident.
// ov: per-index overrides {o, h, l, c}, applied last so they always win.
function makeCandles({ n, keys, seed = 1, noise = 0.9, wick = 1.1, capHi = [], capLo = [], ov = {} }) {
  const r = rng(seed);
  const closes = new Array(n);
  for (let k = 0; k < keys.length - 1; k++) {
    const [i0, p0] = keys[k];
    const [i1, p1] = keys[k + 1];
    for (let i = i0; i <= i1; i++) {
      const t = (i - i0) / (i1 - i0);
      const e = t * t * (3 - 2 * t);
      const jitter = i === i0 || i === i1 ? 0 : (r() - 0.5) * 2 * noise;
      closes[i] = p0 + (p1 - p0) * (0.45 * t + 0.55 * e) + jitter;
    }
  }
  const out = [];
  let prev = closes[0] - (r() - 0.4) * noise * 1.5;
  for (let i = 0; i < n; i++) {
    let o = prev;
    let c = closes[i];
    let h = Math.max(o, c) + r() * wick;
    let l = Math.min(o, c) - r() * wick;
    for (const [a, b, lim] of capHi) if (i >= a && i <= b && !ov[i]) { h = Math.min(h, lim); o = Math.min(o, lim - 0.15); c = Math.min(c, lim - 0.15); }
    for (const [a, b, lim] of capLo) if (i >= a && i <= b && !ov[i]) { l = Math.max(l, lim); o = Math.max(o, lim + 0.15); c = Math.max(c, lim + 0.15); }
    const v = ov[i];
    if (v) {
      if (v.o != null) o = v.o;
      if (v.c != null) c = v.c;
      h = v.h != null ? v.h : Math.max(h, o, c);
      l = v.l != null ? v.l : Math.min(l, o, c);
    }
    h = Math.max(h, o, c);
    l = Math.min(l, o, c);
    out.push({ o, h, l, c });
    prev = c;
  }
  return out;
}

let uid = 0;

function chart(cfg) {
  const id = 'ch' + ++uid;
  const W = cfg.w || 1000;
  const H = cfg.h || 400;
  const pl = cfg.pl ?? 14;
  const pr = cfg.axis === false ? 14 : cfg.pr ?? 84;
  const pt = cfg.pt ?? 16;
  const pb = cfg.pb ?? 16;
  const cs = cfg.candles;
  const n = cs.length;
  let lo = Math.min(...cs.map((c) => c.l));
  let hi = Math.max(...cs.map((c) => c.h));
  const rg = hi - lo;
  lo = cfg.min ?? lo - rg * 0.15;
  hi = cfg.max ?? hi + rg * 0.2;
  const slots = n + (cfg.right ?? 3);
  const step = (W - pl - pr) / slots;
  const X = (i) => pl + step * (i + 0.5);
  const Y = (p) => pt + ((hi - p) / (hi - lo)) * (H - pt - pb);
  const xEnd = W - pr;
  const fs = cfg.fs || 1; // font scale
  const f1 = (v) => v.toFixed(1);

  const L = { defs: [], back: [], grid: [], zones: [], lines: [], candles: [], over: [], labels: [], axis: [] };
  const tagYs = [];
  let gid = 0;
  // opaque vertical fade: strong tint at the level, fading to the panel colour
  const fadeFill = (color, strength, towardsUp) => {
    const g = `${id}g${++gid}`;
    const [y1, y2] = towardsUp ? ['1', '0'] : ['0', '1'];
    L.defs.push(`<linearGradient id="${g}" x1="0" y1="${y1}" x2="0" y2="${y2}"><stop offset="0" stop-color="${mix(color, strength)}"/><stop offset="1" stop-color="${PANEL}"/></linearGradient>`);
    return `url(#${g})`;
  };

  // text helper: anchor is visual ('left' | 'right' | 'middle'), independent of script direction
  const textEl = ({ x, y, text, color = COLORS.text, size = 19, weight = 600, anchor = 'middle', ls = 0 }) => {
    const fa = isFa(text);
    let ta = 'middle';
    if (anchor === 'left') ta = fa ? 'end' : 'start';
    if (anchor === 'right') ta = fa ? 'start' : 'end';
    return `<text x="${f1(x)}" y="${f1(y)}" fill="${color}" font-size="${f1(size * fs)}" font-weight="${weight}" text-anchor="${ta}" dominant-baseline="central" direction="${fa ? 'rtl' : 'ltr'}"${ls ? ` letter-spacing="${ls}"` : ''}>${esc(text)}</text>`;
  };

  const rect = (layer, x, y, w, h, attrs) => L[layer].push(`<rect x="${f1(x)}" y="${f1(y)}" width="${f1(w)}" height="${f1(h)}" ${attrs}/>`);

  const api = {
    X, Y, step, W, H, n, xEnd,
    // shaded liquidity zone that fades away from the level
    zone({ p1, p2, i1 = 0, i2, color, op = 0.22, fade = true }) {
      const x1 = X(i1) - step * 0.5;
      const x2 = i2 == null ? xEnd : X(i2) + step * 0.5;
      const y1 = Y(Math.max(p1, p2));
      const y2 = Y(Math.min(p1, p2));
      const fill = fade ? fadeFill(color, op, p2 > p1) : mix(color, op);
      rect('zones', x1, y1, x2 - x1, y2 - y1, `fill="${fill}"`);
    },
    box({ p1, p2, i1, i2, color, op = 0.06, dash = '7 6', sw = 1.6, so = 0.7 }) {
      const x1 = X(i1) - step * 0.5;
      const x2 = i2 == null ? xEnd : X(i2) + step * 0.5;
      const y1 = Y(Math.max(p1, p2));
      const y2 = Y(Math.min(p1, p2));
      const stroke = sw ? ` stroke="${mix(color, so)}" stroke-width="${sw}"${dash && dash !== '0' ? ` stroke-dasharray="${dash}"` : ''}` : '';
      rect('zones', x1, y1, x2 - x1, y2 - y1, `fill="${mix(color, op)}"${stroke} rx="6"`);
    },
    // vertical background band (e.g. a trading day)
    band({ i1, i2, color = '#ffffff', op = 0.025 }) {
      const x1 = X(i1) - step * 0.5;
      const x2 = X(i2) + step * 0.5;
      rect('back', x1, 0, x2 - x1, H, `fill="${mix(color, op)}"`);
    },
    level({ p, i1 = 0, i2, color, dash = '9 7', w = 2.2, glow = true, tag = true }) {
      const x1 = f1(X(i1));
      const x2 = f1(i2 == null ? xEnd : X(i2));
      const y = f1(Y(p));
      if (glow) L.lines.push(`<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="${mix(color, 0.2)}" stroke-width="${w * 3}"/>`);
      L.lines.push(`<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="${color}" stroke-width="${w}"${dash && dash !== '0' ? ` stroke-dasharray="${dash}"` : ''}/>`);
      if (tag && cfg.axis !== false) api.axisTag({ p, color });
    },
    axisTag({ p, color, text, dark = PANEL }) {
      const y = Y(p);
      tagYs.push(y);
      const label = text || p.toFixed(1);
      L.axis.push(`<path d="M${xEnd + 2} ${f1(y)} l8 -12 h${pr - 14} a4 4 0 0 1 4 4 v16 a4 4 0 0 1 -4 4 h-${pr - 14} z" fill="${color}"/>`);
      L.axis.push(textEl({ x: xEnd + 14, y, text: label, color: dark, size: 16, weight: 800, anchor: 'left' }));
    },
    vline({ i, color = COLORS.muted, dash = '4 6', w = 1.6, op = 0.7 }) {
      const x = f1(X(i) + step * 0.5);
      L.grid.push(`<line x1="${x}" y1="0" x2="${x}" y2="${H}" stroke="${mix(color, op)}" stroke-width="${w}" stroke-dasharray="${dash}"/>`);
    },
    dot({ i, p, color, r = 7, ring = true }) {
      const x = f1(X(i));
      const y = f1(Y(p));
      if (ring) L.over.push(`<circle cx="${x}" cy="${y}" r="${f1(r * 2.1)}" fill="${mix(color, 0.2)}"/>`);
      L.over.push(`<circle cx="${x}" cy="${y}" r="${r}" fill="${PANEL}" stroke="${color}" stroke-width="3"/>`);
      L.over.push(`<circle cx="${x}" cy="${y}" r="${f1(r * 0.42)}" fill="${color}"/>`);
    },
    // numbered marker
    num({ i, p, text, color, dy = 0, r = 13 }) {
      const x = X(i);
      const y = Y(p) + dy;
      L.labels.push(`<circle cx="${f1(x)}" cy="${f1(y)}" r="${f1(r * fs)}" fill="${color}"/>`);
      L.labels.push(textEl({ x, y: y + 1, text, color: PANEL, size: 17, weight: 900 }));
    },
    arrow({ i1, p1, i2, p2, x1, y1, x2, y2, color, w = 2.6, bend = 0, dash, head = 11 }) {
      const ax = x1 ?? X(i1);
      const ay = y1 ?? Y(p1);
      const bx = x2 ?? X(i2);
      const by = y2 ?? Y(p2);
      const dx = bx - ax;
      const dy = by - ay;
      const len = Math.hypot(dx, dy) || 1;
      const cx = (ax + bx) / 2 + (-dy / len) * bend;
      const cy = (ay + by) / 2 + (dx / len) * bend;
      const ang = Math.atan2(by - cy, bx - cx);
      const hx = bx - Math.cos(ang) * head * 0.6;
      const hy = by - Math.sin(ang) * head * 0.6;
      L.over.push(`<path d="M${f1(ax)} ${f1(ay)} Q${f1(cx)} ${f1(cy)} ${f1(hx)} ${f1(hy)}" fill="none" stroke="${color}" stroke-width="${w}" stroke-linecap="round"${dash ? ` stroke-dasharray="${dash}"` : ''}/>`);
      const pt2 = (a, d) => `${f1(bx - Math.cos(ang + a) * d)},${f1(by - Math.sin(ang + a) * d)}`;
      L.over.push(`<polygon points="${f1(bx)},${f1(by)} ${pt2(0.45, head * 1.25)} ${pt2(-0.45, head * 1.25)}" fill="${color}"/>`);
    },
    // tinted frame around one candle
    highlight({ i, color, pad = 7 }) {
      const c = cs[i];
      const x = X(i) - step * 0.5 - pad * 0.3;
      const y1 = Y(c.h) - pad;
      const y2 = Y(c.l) + pad;
      rect('back', x, y1, step + pad * 0.6, y2 - y1, `rx="7" fill="${mix(color, 0.16)}" stroke="${color}" stroke-width="1.8" stroke-dasharray="5 4"`);
    },
    // scattered resting orders inside a zone
    orders({ p1, p2, i1, i2, color, count = 26, seed = 3 }) {
      const r = rng(seed);
      const x1 = X(i1);
      const x2 = i2 == null ? xEnd - 10 : X(i2);
      for (let k = 0; k < count; k++) {
        const x = x1 + r() * (x2 - x1);
        const p = p1 + (0.18 + r() * 0.64) * (p2 - p1);
        const wd = 6 + r() * 10;
        const strength = 0.3 + r() * 0.35;
        rect('zones', x, Y(p) - 1.6, wd, 3.2, `rx="1.6" fill="${mix(color, strength)}"`);
      }
    },
    // pill label; its background is sized in the browser once fonts are loaded
    pill({ i, p, x, y, dx = 0, dy = 0, text, color, solid = false, size = 19, weight = 800, anchor = 'middle', px = 12, py = 6 }) {
      const xx = (x ?? X(i)) + dx;
      const yy = (y ?? Y(p)) + dy;
      const bg = solid ? color : PANEL;
      const stroke = solid ? '' : ` stroke="${color}" stroke-width="1.6"`;
      const tc = solid ? PANEL : color;
      L.labels.push(`<g class="pill" data-px="${px * fs}" data-py="${py * fs}"><rect fill="${bg}"${stroke}/>${textEl({ x: xx, y: yy, text, color: tc, size, weight, anchor })}</g>`);
    },
    text(o) {
      const x = (o.x ?? X(o.i)) + (o.dx || 0);
      const y = (o.y ?? Y(o.p)) + (o.dy || 0);
      L.labels.push(textEl({ ...o, x, y }));
    },
  };

  // grid
  const gStep = cfg.gridStep || 5;
  for (let p = Math.ceil(lo / gStep) * gStep; p <= hi; p += gStep) {
    const y = f1(Y(p));
    L.grid.push(`<line x1="0" y1="${y}" x2="${xEnd}" y2="${y}" stroke="${COLORS.grid}" stroke-width="1.2"/>`);
  }
  for (let i = 0; i < slots; i += cfg.vGrid || 8) {
    const x = f1(X(i));
    L.grid.push(`<line x1="${x}" y1="0" x2="${x}" y2="${H}" stroke="${COLORS.grid}" stroke-width="1.2"/>`);
  }

  if (cfg.watermark) L.back.push(textEl({ x: (pl + xEnd) / 2, y: H / 2, text: cfg.watermark, color: mix('#ffffff', 0.035), size: 64, weight: 900, ls: 6 }));

  if (cfg.draw) cfg.draw(api);

  // candles
  const bw = Math.max(3, step * (cfg.body ?? 0.62));
  const hl = new Set(cfg.hl || []);
  cs.forEach((c, i) => {
    const col = c.c >= c.o ? COLORS.bull : COLORS.bear;
    const x = X(i);
    const yTop = Y(Math.max(c.o, c.c));
    const bh = Math.max(1.8, Y(Math.min(c.o, c.c)) - yTop);
    L.candles.push(`<line x1="${f1(x)}" y1="${f1(Y(c.h))}" x2="${f1(x)}" y2="${f1(Y(c.l))}" stroke="${col}" stroke-width="${f1(cfg.wickW || 1.8)}"/>`);
    rect('candles', x - bw / 2, yTop, bw, bh, `rx="1.2" fill="${col}"${hl.has(i) ? ' stroke="#ffffff" stroke-width="1.6"' : ''}`);
  });

  // last price marker
  if (cfg.axis !== false && cfg.lastTag !== false) {
    const last = cs[n - 1];
    const col = last.c >= last.o ? COLORS.bull : COLORS.bear;
    const y = f1(Y(last.c));
    L.lines.push(`<line x1="${f1(X(n - 1))}" y1="${y}" x2="${xEnd}" y2="${y}" stroke="${col}" stroke-width="1.2" stroke-dasharray="2 4"/>`);
    api.axisTag({ p: last.c, color: col });
  }

  // price scale, skipping numbers hidden under a tag
  if (cfg.axis !== false) {
    for (let p = Math.ceil(lo / gStep) * gStep; p <= hi; p += gStep) {
      const y = Y(p);
      if (tagYs.some((t) => Math.abs(t - y) < 22)) continue;
      L.axis.unshift(textEl({ x: xEnd + 14, y, text: p.toFixed(1), color: COLORS.axis, size: 15, weight: 500, anchor: 'left' }));
    }
  }

  const axisSep = cfg.axis !== false ? `<line x1="${xEnd}" y1="0" x2="${xEnd}" y2="${H}" stroke="${COLORS.axisLine}" stroke-width="1.2"/>` : '';
  return `<svg class="chart-svg" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" direction="ltr" font-family="Vazirmatn">
<defs>${L.defs.join('')}</defs>
<rect width="${W}" height="${H}" fill="${PANEL}"/>
${L.back.join('')}${L.grid.join('')}${L.zones.join('')}${L.lines.join('')}${L.candles.join('')}${L.over.join('')}${axisSep}${L.axis.join('')}${L.labels.join('')}
</svg>`;
}

module.exports = { COLORS, mix, makeCandles, chart };
