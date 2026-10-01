/* Numbix — core UI script (no dependencies) */
(function () {
  'use strict';

  const NBX = window.NBX || {};
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const FA = '۰۱۲۳۴۵۶۷۸۹';
  const fa = (v) => String(v).replace(/\d/g, (d) => FA[d]);
  const en = (v) => String(v).replace(/[۰-۹]/g, (d) => FA.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const fmt = (n) => fa(Math.round(Number(n) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  NBX.fa = fa; NBX.fmt = fmt; NBX.en = en; NBX.esc = esc;

  /* ---------------- HTTP ---------------- */
  NBX.post = async function (url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data || {});
    if (!(data instanceof FormData) || !data.has('_token')) body.append('_token', NBX.csrf);
    const res = await fetch(url, {
      method: 'POST', body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    let json = {};
    try { json = await res.json(); } catch (e) { json = { ok: false, message: 'پاسخ نامعتبر از سرور' }; }
    if (res.status === 401 && json.login) { location.href = json.login + '?next=' + encodeURIComponent(location.pathname + location.search); }
    return json;
  };

  /* ---------------- Toasts ---------------- */
  const icons = {
    success: '<path d="M20 6 9 17l-5-5"/>',
    error: '<path d="M18 6 6 18M6 6l12 12"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
  };
  function toast(type, message, timeout = 5000) {
    let wrap = $('.toasts');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toasts'; document.body.appendChild(wrap); }
    const t = document.createElement('div');
    t.className = 'toast ' + (icons[type] ? type : 'info');
    t.innerHTML = `<span class="t-ic"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${icons[type] || icons.info}</svg></span><div class="t-body">${esc(message)}</div><button class="t-close" aria-label="بستن"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>`;
    t.style.setProperty('--dur', timeout + 'ms');
    wrap.appendChild(t);
    const close = () => { t.classList.add('out'); setTimeout(() => t.remove(), 350); };
    t.querySelector('.t-close').onclick = close;
    setTimeout(close, timeout);
  }
  window.toast = toast;
  (window.__flashes || []).forEach(([type, msg], i) => setTimeout(() => toast(type, msg), 150 + i * 120));

  /* ---------------- Theme ---------------- */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-theme-toggle]');
    if (!b) return;
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('nbx-th', next); } catch (err) { }
    document.dispatchEvent(new CustomEvent('nbx:theme', { detail: next }));
  });

  /* ---------------- Header on scroll ---------------- */
  const header = $('.site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 12);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------------- Dropdowns ---------------- */
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-dropdown]');
    $$('.dropdown.open').forEach((d) => { if (!trigger || d !== trigger.closest('.dropdown')) d.classList.remove('open'); });
    if (trigger) { e.preventDefault(); trigger.closest('.dropdown').classList.toggle('open'); }
  });

  /* ---------------- Modals / drawers / generic open ---------------- */
  function openEl(el) { if (!el) return; el.classList.add('open'); document.body.style.overflow = 'hidden'; const f = el.querySelector('[autofocus], input:not([type=hidden])'); if (f) setTimeout(() => f.focus(), 250); }
  function closeEl(el) { if (!el) return; el.classList.remove('open'); if (!$('.modal.open, .drawer.open, .filters.open')) document.body.style.overflow = ''; }
  NBX.open = openEl; NBX.close = closeEl;
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); openEl($(o.dataset.open)); return; }
    const c = e.target.closest('[data-close]');
    if (c) { e.preventDefault(); closeEl(c.closest('.modal, .drawer, .filters')); return; }
    if (e.target.matches('.modal-backdrop, .drawer-backdrop, .filters')) closeEl(e.target.closest('.modal, .drawer, .filters'));
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') $$('.modal.open, .drawer.open, .filters.open').forEach(closeEl); });

  /* ---------------- Confirm dialog ---------------- */
  NBX.confirm = function (message, { ok = 'تایید', danger = true } = {}) {
    return new Promise((resolve) => {
      const m = document.createElement('div');
      m.className = 'modal open';
      m.innerHTML = `<div class="modal-backdrop"></div><div class="modal-dialog" style="width:min(420px,100%)"><div class="modal-body center" style="padding:32px 28px 24px">
        <div style="width:64px;height:64px;border-radius:20px;margin:0 auto 16px;display:grid;place-items:center;background:var(${danger ? '--danger-soft' : '--p-soft'});color:var(${danger ? '--danger' : '--p'})"><svg class="i" style="width:30px;height:30px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${icons.warning}</svg></div>
        <h3 style="font-size:17px">${esc(message)}</h3></div>
        <div class="modal-foot" style="justify-content:center;border:0;padding-top:0"><button class="btn btn-outline" data-x>انصراف</button><button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-y>${esc(ok)}</button></div></div>`;
      document.body.appendChild(m);
      requestAnimationFrame(() => m.classList.add('open'));
      const done = (v) => { m.classList.remove('open'); setTimeout(() => m.remove(), 300); resolve(v); };
      m.querySelector('[data-y]').onclick = () => done(true);
      m.querySelector('[data-x]').onclick = () => done(false);
      m.querySelector('.modal-backdrop').onclick = () => done(false);
      m.querySelector('[data-y]').focus();
    });
  };
  document.addEventListener('submit', async (e) => {
    const f = e.target;
    if (f.dataset.confirm && !f.dataset.confirmed) {
      e.preventDefault();
      if (await NBX.confirm(f.dataset.confirm)) { f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit(e.submitter) : f.submit(); }
      return;
    }
    if (f.dataset.ajax !== undefined) {
      e.preventDefault();
      const btn = e.submitter || f.querySelector('[type=submit]');
      btn && btn.classList.add('loading');
      const r = await NBX.post(f.action, new FormData(f));
      btn && btn.classList.remove('loading');
      delete f.dataset.confirmed;
      if (r.message) toast(r.ok ? 'success' : 'error', r.message);
      if (r.redirect) setTimeout(() => (location.href = r.redirect), r.message ? 700 : 0);
      else if (r.reload) setTimeout(() => location.reload(), 700);
      f.dispatchEvent(new CustomEvent('nbx:done', { detail: r }));
      return;
    }
    const btn = e.submitter || f.querySelector('[type=submit]');
    if (btn && !f.hasAttribute('data-no-loading')) { btn.classList.add('loading'); setTimeout(() => btn.classList.remove('loading'), 8000); }
  });

  /* ---------------- Copy ---------------- */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    e.preventDefault();
    const text = b.dataset.copy || ($(b.dataset.copyFrom) || {}).value || '';
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(
      () => toast('success', 'کپی شد'),
      () => { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); toast('success', 'کپی شد'); }
    );
  });

  /* ---------------- Password tools ---------------- */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-pw-toggle]');
    if (!b) return;
    const inp = b.parentElement.querySelector('input');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    b.classList.toggle('on');
  });
  $$('[data-pw-meter]').forEach((inp) => {
    const meter = $(inp.dataset.pwMeter);
    inp.addEventListener('input', () => {
      const v = inp.value; let s = 0;
      if (v.length >= 8) s++;
      if (/[a-z]/i.test(v) && /\d/.test(v)) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/[^a-z0-9]/i.test(v) && v.length >= 10) s++;
      meter.dataset.s = v ? Math.max(1, s) : 0;
    });
  });

  /* ---------------- Reveal & counters ---------------- */
  const counters = (el) => {
    const target = parseFloat(el.dataset.count) || 0;
    const dur = 1600; const start = performance.now();
    const suffix = el.dataset.suffix || '';
    const tick = (now) => {
      const p = Math.min(1, (now - start) / dur);
      const eased = 1 - Math.pow(1 - p, 4);
      el.textContent = fmt(target * eased) + suffix;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (!en.isIntersecting) return;
      en.target.classList.add('in');
      if (en.target.dataset.count !== undefined) counters(en.target);
      io.unobserve(en.target);
    }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    $$('.reveal, [data-count]').forEach((el) => io.observe(el));
  } else {
    $$('.reveal').forEach((el) => el.classList.add('in'));
  }

  /* ---------------- Rotating words ---------------- */
  $$('.rotator').forEach((r) => {
    const items = $$('span', r); let i = 0;
    if (items.length < 2) return;
    setInterval(() => { items[i].classList.remove('on'); i = (i + 1) % items.length; items[i].classList.add('on'); }, 2600);
  });

  /* ---------------- Card spotlight ---------------- */
  document.addEventListener('pointermove', (e) => {
    const c = e.target.closest && e.target.closest('.svc-card');
    if (!c) return;
    const r = c.getBoundingClientRect();
    c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    c.style.setProperty('--my', (e.clientY - r.top) + 'px');
  }, { passive: true });

  /* ---------------- Live search ---------------- */
  $$('[data-search]').forEach((inp) => {
    const box = inp.closest('.hero-search, .header-search, [data-search-wrap]').querySelector('.search-suggest');
    if (!box) return;
    let timer, ctrl, hl = -1;
    const render = (items) => {
      hl = -1;
      if (!items.length) { box.innerHTML = '<div class="none">سرویسی پیدا نشد</div>'; box.classList.add('open'); return; }
      box.innerHTML = items.map((s) => `<a href="${esc(s.url)}"><span class="btile btile-sm" style="--c1:${esc(s.c1)};--c2:${esc(s.c2)}">${s.icon}</span><span><b>${esc(s.title)}</b><small>${esc(s.category)}</small></span><span class="price">${fmt(s.price)} <small class="unit">${esc(NBX.currency)}</small></span></a>`).join('');
      box.classList.add('open');
    };
    inp.addEventListener('input', () => {
      clearTimeout(timer);
      const q = inp.value.trim();
      if (q.length < 2) { box.classList.remove('open'); return; }
      timer = setTimeout(async () => {
        ctrl && ctrl.abort(); ctrl = new AbortController();
        try {
          const res = await fetch(NBX.url('search') + (NBX.url('search').includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { signal: ctrl.signal, headers: { Accept: 'application/json' } });
          render((await res.json()).items || []);
        } catch (err) { }
      }, 220);
    });
    inp.addEventListener('keydown', (e) => {
      const links = $$('a', box);
      if (!links.length || !box.classList.contains('open')) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        hl = (hl + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach((l, i) => l.classList.toggle('hl', i === hl));
      } else if (e.key === 'Enter' && hl >= 0) { e.preventDefault(); location.href = links[hl].href; }
    });
    document.addEventListener('click', (e) => { if (!box.contains(e.target) && e.target !== inp) box.classList.remove('open'); });
  });

  /* ---------------- Favorites ---------------- */
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-fav]');
    if (!b) return;
    e.preventDefault();
    const r = await NBX.post(NBX.url('favorite/' + b.dataset.fav));
    if (r.ok) {
      $$(`[data-fav="${b.dataset.fav}"]`).forEach((x) => { x.classList.toggle('on', r.on); x.classList.remove('pulse'); void x.offsetWidth; x.classList.add('pulse'); });
      toast('success', r.message);
    } else if (r.message) toast('error', r.message);
  });

  /* ---------------- Order calculator ---------------- */
  function bindCalc(root) {
    const qty = $('[name=qty]', root); if (!qty) return;
    const d = () => ({ price: parseFloat(root.dataset.price || 0), min: parseInt(root.dataset.min || 1, 10), max: parseInt(root.dataset.max || 1e9, 10) });
    const out = $$('[data-total]', root); const hint = $('[data-qty-hint]', root);
    const update = () => {
      const { price, min, max } = d();
      let q = parseInt(en(qty.value).replace(/\D/g, ''), 10) || 0;
      out.forEach((o) => (o.textContent = fmt(Math.ceil(price * q / 1000))));
      $$('.presets button', root).forEach((b) => b.classList.toggle('on', parseInt(b.dataset.q, 10) === q));
      if (hint) hint.classList.toggle('error-text', q && (q < min || q > max));
    };
    qty.addEventListener('input', update);
    root.addEventListener('click', (e) => {
      const p = e.target.closest('.presets button');
      if (p) { e.preventDefault(); qty.value = p.dataset.q; update(); }
      const s = e.target.closest('[data-step]');
      if (s) {
        e.preventDefault();
        const { min, max } = d();
        const step = Math.max(10, Math.pow(10, Math.floor(Math.log10(Math.max(min, 10)))));
        let q = (parseInt(en(qty.value), 10) || min) + (s.dataset.step === '+' ? step : -step);
        qty.value = Math.min(max, Math.max(min, q)); update();
      }
    });
    qty.addEventListener('nbx:recalc', update);
    update();
  }
  $$('[data-order-form]').forEach(bindCalc);
  NBX.bindCalc = bindCalc;

  /* ---------------- Quick add to cart ---------------- */
  const qa = $('#quick-add');
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-quick-add]');
    if (!b || !qa) return;
    e.preventDefault();
    const d = JSON.parse(b.dataset.quickAdd);
    const form = $('form', qa);
    form.dataset.price = d.price; form.dataset.min = d.min; form.dataset.max = d.max;
    $('[name=service_id]', form).value = d.id;
    $('[name=qty]', form).value = d.min;
    $('[name=link]', form).value = '';
    $('[name=link]', form).placeholder = d.hint || 'لینک یا آیدی مقصد';
    $('[data-qa-title]', qa).textContent = d.title;
    $('[data-qa-tile]', qa).innerHTML = d.tile;
    $('[data-qa-range]', qa).textContent = 'حداقل ' + fmt(d.min) + ' — حداکثر ' + fmt(d.max);
    const presets = [d.min, 1000, 5000, 10000, 50000].filter((v, i, a) => v >= d.min && v <= d.max && a.indexOf(v) === i).slice(0, 5);
    $('.presets', form).innerHTML = presets.map((v) => `<button type="button" data-q="${v}">${fmt(v)}</button>`).join('');
    if (!form.dataset.bound) { bindCalc(form); form.dataset.bound = 1; }
    $('[name=qty]', form).dispatchEvent(new Event('input'));
    openEl(qa);
  });
  if (qa) {
    $('form', qa).addEventListener('nbx:done', (e) => {
      const r = e.detail;
      if (r.ok) {
        closeEl(qa);
        $$('[data-cart-count]').forEach((c) => { c.textContent = fa(r.count); c.dataset.n = r.count; });
      }
    });
  }

  /* ---------------- Dual range (price filter) ---------------- */
  $$('[data-range]').forEach((wrap) => {
    const [a, b] = $$('input[type=range]', wrap);
    const fill = $('.fill', wrap);
    const outA = $(wrap.dataset.minOut), outB = $(wrap.dataset.maxOut);
    const max = parseFloat(a.max);
    const sync = (src) => {
      let x = +a.value, y = +b.value;
      if (x > y) { if (src === a) a.value = y; else b.value = x; x = +a.value; y = +b.value; }
      fill.style.right = (x / max * 100) + '%';
      fill.style.left = (100 - y / max * 100) + '%';
      if (outA) outA.value = fmt(x); if (outB) outB.value = fmt(y);
    };
    a.addEventListener('input', () => sync(a)); b.addEventListener('input', () => sync(b));
    [a, b].forEach((r) => r.addEventListener('change', () => r.form && r.form.dataset.autosubmit !== undefined && r.form.submit()));
    sync();
  });
  $$('form[data-autosubmit]').forEach((f) => f.addEventListener('change', (e) => { if (e.target.type !== 'range' && e.target.type !== 'text' && e.target.type !== 'search') f.submit(); }));

  /* ---------------- Pay options ---------------- */
  $$('.pay-opt input').forEach((inp) => {
    const sync = () => $$(`input[name="${inp.name}"]`).forEach((o) => o.closest('.pay-opt').classList.toggle('on', o.checked));
    inp.addEventListener('change', sync); sync();
  });
  $$('[data-toggle-target]').forEach((inp) => {
    const sync = () => $$(inp.dataset.toggleTarget).forEach((t) => t.classList.toggle('hidden', t.dataset.when !== undefined ? t.dataset.when !== (inp.type === 'checkbox' ? String(inp.checked) : inp.value) : !inp.checked));
    inp.addEventListener('change', sync);
    if (inp.type !== 'radio' || inp.checked) sync();
  });

  /* ---------------- Image preview ---------------- */
  $$('[data-preview]').forEach((inp) => inp.addEventListener('change', () => {
    const f = inp.files && inp.files[0]; const box = inp.closest('.img-preview');
    if (!f || !box) return;
    const url = URL.createObjectURL(f);
    let img = $('img', box); if (!img) { img = document.createElement('img'); box.prepend(img); }
    img.src = url; $$('.ph', box).forEach((x) => x.remove());
  }));

  /* ---------------- Money inputs: show formatted value ---------------- */
  $$('[data-money]').forEach((inp) => {
    const out = document.createElement('div'); out.className = 'help';
    inp.closest('.field, .input-wrap, div').after ? (inp.closest('.input-wrap') || inp).after(out) : null;
    const sync = () => { const v = parseInt(en(inp.value).replace(/\D/g, ''), 10) || 0; out.textContent = v ? fmt(v) + ' ' + (NBX.currency || '') : ''; };
    inp.addEventListener('input', sync); sync();
  });

  /* ---------------- Digits normaliser for numeric inputs ---------------- */
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t.matches && t.matches('input[inputmode=numeric], input[type=tel]') && /[۰-۹٠-٩]/.test(t.value)) t.value = en(t.value);
  });
})();
