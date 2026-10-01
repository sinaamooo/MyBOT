/* Numbix — panel (dashboard / admin) script */
(function () {
  'use strict';
  const NBX = window.NBX;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const panel = $('.panel');

  /* ---------------- Sidebar ---------------- */
  try { if (localStorage.getItem('nbx-sb') === '1' && window.innerWidth > 1024) panel.classList.add('collapsed'); } catch (e) { }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sb-toggle]')) panel.classList.toggle('sb-open');
    if (e.target.closest('.sb-overlay')) panel.classList.remove('sb-open');
    if (e.target.closest('[data-sb-collapse]')) {
      panel.classList.toggle('collapsed');
      try { localStorage.setItem('nbx-sb', panel.classList.contains('collapsed') ? '1' : '0'); } catch (err) { }
    }
  });

  /* ---------------- Command palette (Ctrl+K) ---------------- */
  const cmdk = $('#cmdk');
  if (cmdk) {
    const input = $('input', cmdk);
    const list = $('.cmdk-list', cmdk);
    const staticItems = JSON.parse(cmdk.dataset.items || '[]');
    let hl = 0, timer, results = [];
    const draw = (groups) => {
      results = [];
      let html = '';
      groups.forEach((g) => {
        if (!g.items.length) return;
        html += `<div class="cmdk-group">${NBX.esc(g.title)}</div>`;
        g.items.forEach((it) => {
          results.push(it);
          html += `<a class="cmdk-item" href="${NBX.esc(it.url)}" data-i="${results.length - 1}"><span class="ci">${it.icon || ''}</span><span><b>${NBX.esc(it.title)}</b>${it.sub ? `<small>${NBX.esc(it.sub)}</small>` : ''}</span></a>`;
        });
      });
      list.innerHTML = html || '<div class="empty" style="padding:30px">نتیجه‌ای پیدا نشد</div>';
      hl = 0; mark();
    };
    const mark = () => $$('.cmdk-item', list).forEach((a, i) => { a.classList.toggle('hl', i === hl); if (i === hl) a.scrollIntoView({ block: 'nearest' }); });
    const search = () => {
      const q = input.value.trim();
      const local = staticItems.filter((i) => !q || i.title.includes(q));
      draw([{ title: 'دسترسی سریع', items: local.slice(0, q ? 6 : 10) }]);
      clearTimeout(timer);
      if (q.length < 2 || !cmdk.dataset.endpoint) return;
      timer = setTimeout(async () => {
        try {
          const u = cmdk.dataset.endpoint + (cmdk.dataset.endpoint.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q);
          const r = await (await fetch(u, { headers: { Accept: 'application/json' } })).json();
          draw([{ title: 'دسترسی سریع', items: local.slice(0, 4) }].concat(r.groups || []));
        } catch (e) { }
      }, 200);
    };
    const open = () => { cmdk.classList.add('open'); input.value = ''; search(); setTimeout(() => input.focus(), 30); };
    const close = () => cmdk.classList.remove('open');
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K' || e.key === 'ل')) { e.preventDefault(); cmdk.classList.contains('open') ? close() : open(); }
      if (!cmdk.classList.contains('open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowDown') { e.preventDefault(); hl = Math.min(results.length - 1, hl + 1); mark(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); hl = Math.max(0, hl - 1); mark(); }
      if (e.key === 'Enter' && results[hl]) { e.preventDefault(); location.href = results[hl].url; }
    });
    input.addEventListener('input', search);
    $('.cmdk-bg', cmdk).addEventListener('click', close);
    $$('[data-cmdk]').forEach((b) => b.addEventListener('click', open));
  }

  /* ---------------- Table selection / bulk ---------------- */
  $$('[data-check-all]').forEach((all) => {
    const table = all.closest('table');
    const bar = $(all.dataset.checkAll);
    const boxes = () => $$('.row-check', table);
    const sync = () => {
      const n = boxes().filter((b) => b.checked).length;
      if (bar) { bar.classList.toggle('show', n > 0); $('[data-selected]', bar).textContent = NBX.fa(n); }
      all.checked = n && n === boxes().length;
    };
    all.addEventListener('change', () => { boxes().forEach((b) => (b.checked = all.checked)); sync(); });
    table.addEventListener('change', (e) => { if (e.target.classList.contains('row-check')) sync(); });
    if (bar) bar.closest('form') && bar.closest('form').addEventListener('submit', (e) => {
      const f = bar.closest('form');
      $$('input[name="ids[]"][type=hidden]', f).forEach((x) => x.remove());
      boxes().filter((b) => b.checked).forEach((b) => { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'ids[]'; h.value = b.value; f.appendChild(h); });
    });
  });

  /* ---------------- Charts ---------------- */
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  const charts = [];
  function buildCharts() {
    if (!window.Chart) return;
    charts.forEach((c) => c.destroy()); charts.length = 0;
    Chart.defaults.font.family = css('--font');
    Chart.defaults.color = css('--muted');
    $$('canvas[data-chart]').forEach((cv) => {
      const cfg = JSON.parse(cv.dataset.chart);
      const ctx = cv.getContext('2d');
      const h = cv.parentElement.clientHeight || 300;
      const grad = (c1, a1 = 0.35, a2 = 0) => { const g = ctx.createLinearGradient(0, 0, 0, h); g.addColorStop(0, hexA(c1, a1)); g.addColorStop(1, hexA(c1, a2)); return g; };
      const datasets = cfg.datasets.map((d) => {
        const base = { label: d.label, data: d.data, yAxisID: d.axis || 'y', order: d.type === 'line' ? 0 : 1 };
        if (d.type === 'line') return Object.assign(base, { type: 'line', borderColor: d.color, backgroundColor: grad(d.color, 0.25), fill: d.fill !== false, cubicInterpolationMode: 'monotone', borderWidth: 3, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: d.color, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3 });
        if (d.type === 'doughnut') return Object.assign(base, { backgroundColor: d.colors, borderWidth: 0, hoverOffset: 8 });
        return Object.assign(base, { type: 'bar', backgroundColor: grad(d.color, 0.9, 0.25), hoverBackgroundColor: d.color, borderRadius: 8, borderSkipped: false, maxBarThickness: 22 });
      });
      const isPie = cfg.type === 'doughnut';
      const grid = css('--border');
      const chart = new Chart(ctx, {
        type: isPie ? 'doughnut' : 'bar',
        data: { labels: cfg.labels, datasets },
        options: {
          responsive: true, maintainAspectRatio: false, animation: { duration: 900, easing: 'easeOutQuart' },
          interaction: { mode: 'index', intersect: false },
          cutout: isPie ? '72%' : undefined,
          plugins: {
            legend: { display: isPie, position: 'bottom', labels: { usePointStyle: true, padding: 16, boxWidth: 8 } },
            tooltip: {
              rtl: true, backgroundColor: css('--card'), titleColor: css('--text'), bodyColor: css('--text-2'), borderColor: css('--border'), borderWidth: 1,
              padding: 12, cornerRadius: 12, usePointStyle: true, boxPadding: 6,
              callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + NBX.fmt(c.parsed.y ?? c.parsed) + (c.dataset.yAxisID === 'y1' || (isPie && cfg.money) ? ' ' + NBX.currency : '') },
            },
          },
          scales: isPie ? {} : {
            x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14, callback: function (v) { return NBX.fa(this.getLabelForValue(v)); } } },
            y: { position: 'right', grid: { color: grid, drawTicks: false }, border: { display: false }, beginAtZero: true, suggestedMax: 4, ticks: { padding: 8, precision: 0, callback: (v) => NBX.fmt(v), maxTicksLimit: 6 } },
            y1: { position: 'left', display: datasets.some((d) => d.yAxisID === 'y1'), grid: { display: false }, border: { display: false }, beginAtZero: true, suggestedMax: 1000, ticks: { padding: 8, precision: 0, callback: (v) => short(v), maxTicksLimit: 6 } },
          },
        },
      });
      charts.push(chart);
    });
  }
  function hexA(hex, a) { const n = parseInt(hex.replace('#', ''), 16); return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`; }
  function short(v) { if (v >= 1e9) return NBX.fa(+(v / 1e9).toFixed(1)) + 'B'; if (v >= 1e6) return NBX.fa(+(v / 1e6).toFixed(1)) + 'M'; if (v >= 1e3) return NBX.fa(+(v / 1e3).toFixed(0)) + 'K'; return NBX.fa(v); }
  buildCharts();
  document.addEventListener('nbx:theme', () => setTimeout(buildCharts, 50));

  /* ---------------- Stock page: service picker ---------------- */
  const pick = $('[data-stock-pick]');
  if (pick) {
    const sync = () => {
      const o = pick.selectedOptions[0]; if (!o) return;
      const card = $('[data-pick-card]');
      card.querySelector('[data-pick-tile]').innerHTML = o.dataset.tile || '';
      card.querySelector('[data-pick-title]').textContent = o.textContent;
      card.querySelector('[data-pick-sub]').textContent = o.dataset.sub || '';
      const f = pick.form;
      if (f.cost && !f.cost.dataset.touched) f.cost.value = o.dataset.cost || '';
      if (f.price && !f.price.dataset.touched) f.price.value = o.dataset.price || '';
      ['cost', 'price'].forEach((n) => f[n] && f[n].dispatchEvent(new Event('input')));
    };
    pick.addEventListener('change', () => { $$('[data-touch]', pick.form).forEach((i) => delete i.dataset.touched); sync(); });
    $$('[data-touch]', pick.form).forEach((i) => i.addEventListener('input', (e) => { if (e.isTrusted) i.dataset.touched = 1; }));
    sync();
  }

  /* ---------------- Slug auto-fill ---------------- */
  $$('[data-slug-from]').forEach((slug) => {
    const src = $(slug.dataset.slugFrom);
    if (!src) return;
    let manual = slug.value !== '';
    slug.addEventListener('input', () => (manual = slug.value !== ''));
    src.addEventListener('input', () => { if (!manual) slug.value = src.value.trim().toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, ''); });
  });

  /* ---------------- Provider import: filter + select ---------------- */
  const imp = $('[data-import-filter]');
  if (imp) {
    const rows = $$('tbody tr', imp.closest('form'));
    imp.addEventListener('input', () => {
      const q = imp.value.trim().toLowerCase();
      rows.forEach((r) => (r.style.display = !q || r.textContent.toLowerCase().includes(q) ? '' : 'none'));
    });
  }

  /* ---------------- Notifications: mark read ---------------- */
  const nm = $('[data-notif-read]');
  if (nm) nm.addEventListener('click', async (e) => {
    e.preventDefault();
    await NBX.post(nm.dataset.notifRead);
    $$('.notif-item.unread').forEach((i) => i.classList.remove('unread'));
    $$('[data-notif-count]').forEach((c) => { c.textContent = ''; c.dataset.n = 0; });
  });
})();
