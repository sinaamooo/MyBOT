/* ═══════════════ Core helpers shared by every module ═══════════════ */

const NX = {
  root: document.documentElement,
  $: (selector, scope = document) => scope.querySelector(selector),
  $$: (selector, scope = document) => Array.from(scope.querySelectorAll(selector)),
  finePointer: matchMedia('(hover: hover) and (pointer: fine)').matches,
  reducedMotion: matchMedia('(prefers-reduced-motion: reduce)'),

  get perf() { return NX.root.dataset.perf === 'on'; },
  get animated() { return !NX.perf && !NX.reducedMotion.matches; },

  store: {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { /* storage blocked */ } },
  },

  fa: (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]),

  /** Change the device tier; CSS and the galaxy both follow `data-quality`. */
  setQuality(tier) {
    if (NX.root.dataset.quality === tier) return;
    NX.root.dataset.quality = tier;
    document.dispatchEvent(new CustomEvent('nx:quality', { detail: tier }));
  },

  /** Run `callback` once when `el` finishes its own CSS animation (ignores bubbling ones). */
  afterAnimation(el, callback) {
    const done = (event) => {
      if (event.target !== el) return;
      el.removeEventListener('animationend', done);
      callback();
    };
    el.addEventListener('animationend', done);
  },
};
