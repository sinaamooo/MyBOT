/* Numbix Cosmos — living galaxy background & motion engine (no dependencies) */
(function () {
  'use strict';
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine = window.matchMedia('(pointer: fine)').matches;
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const mouse = { x: 0, y: 0, nx: 0, ny: 0 };
  window.addEventListener('pointermove', (e) => {
    mouse.x = e.clientX; mouse.y = e.clientY;
    mouse.nx = e.clientX / window.innerWidth - 0.5; mouse.ny = e.clientY / window.innerHeight - 0.5;
  }, { passive: true });

  /* ------------------------------------------------------------------ starfield */
  const canvas = document.getElementById('cosmos');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    const dense = canvas.dataset.density ? parseFloat(canvas.dataset.density) : 1;
    let W = 0, H = 0, DPR = 1, stars = [], shooters = [], nextShot = 0, last = 0;
    const palette = [[255, 255, 255], [196, 181, 253], [165, 243, 252], [251, 207, 232], [253, 230, 138]];
    const isLight = () => document.documentElement.dataset.theme === 'light';
    function resize() {
      DPR = Math.min(window.devicePixelRatio || 1, 1.5);
      W = window.innerWidth; H = window.innerHeight;
      canvas.width = W * DPR; canvas.height = H * DPR;
      canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
      const n = Math.round(Math.min(420, (W * H) / 3600) * dense);
      stars = Array.from({ length: n }, () => {
        const z = Math.random() ** 1.6;
        return {
          x: Math.random() * W, y: Math.random() * H, z,
          r: 0.25 + z * 1.35, a: 0.25 + z * 0.75,
          tw: 0.6 + Math.random() * 2.2, ph: Math.random() * 6.28,
          c: palette[Math.random() < 0.72 ? 0 : 1 + Math.floor(Math.random() * 4)],
        };
      });
    }
    function shoot(now) {
      const fromLeft = Math.random() < 0.5;
      shooters.push({
        x: fromLeft ? Math.random() * W * 0.5 : W * 0.5 + Math.random() * W * 0.5,
        y: Math.random() * H * 0.45,
        vx: (fromLeft ? 1 : -1) * (7 + Math.random() * 6), vy: 3 + Math.random() * 3,
        life: 0, max: 55 + Math.random() * 30,
      });
      nextShot = now + 3500 + Math.random() * 6500;
    }
    function frame(now) {
      requestAnimationFrame(frame);
      if (document.hidden || now - last < 24) return;
      last = now;
      const t = now / 1000, light = isLight();
      const sy = window.scrollY;
      ctx.clearRect(0, 0, W, H);
      for (const s of stars) {
        const px = s.x - mouse.nx * 18 * s.z;
        let py = (s.y - sy * 0.08 * s.z - mouse.ny * 12 * s.z) % H; if (py < 0) py += H;
        const a = s.a * (0.55 + 0.45 * Math.sin(t * s.tw + s.ph));
        if (light) {
          ctx.fillStyle = `rgba(109,40,217,${(a * 0.45).toFixed(3)})`;
        } else {
          ctx.fillStyle = `rgba(${s.c[0]},${s.c[1]},${s.c[2]},${a.toFixed(3)})`;
        }
        ctx.beginPath(); ctx.arc(px, py, s.r, 0, 6.283); ctx.fill();
        if (!light && s.z > 0.82) {
          ctx.fillStyle = `rgba(${s.c[0]},${s.c[1]},${s.c[2]},${(a * 0.12).toFixed(3)})`;
          ctx.beginPath(); ctx.arc(px, py, s.r * 4, 0, 6.283); ctx.fill();
        }
      }
      if (!light && canvas.dataset.shooting !== '0') {
        if (now > nextShot) shoot(now);
        shooters = shooters.filter((m) => m.life < m.max);
        for (const m of shooters) {
          m.life++; m.x += m.vx; m.y += m.vy;
          const k = 1 - m.life / m.max;
          const g = ctx.createLinearGradient(m.x, m.y, m.x - m.vx * 14, m.y - m.vy * 14);
          g.addColorStop(0, `rgba(255,255,255,${(0.9 * k).toFixed(3)})`);
          g.addColorStop(0.3, `rgba(196,181,253,${(0.45 * k).toFixed(3)})`);
          g.addColorStop(1, 'rgba(34,211,238,0)');
          ctx.strokeStyle = g; ctx.lineWidth = 1.6; ctx.lineCap = 'round';
          ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - m.vx * 14, m.y - m.vy * 14); ctx.stroke();
        }
      }
    }
    resize();
    window.addEventListener('resize', resize);
    if (reduce) { last = -1e9; frame(performance.now()); } else { nextShot = performance.now() + 1500; requestAnimationFrame(frame); }
  }

  /* ------------------------------------------------------------------ orbit systems */
  const systems = $$('[data-orbit-system]').map((el) => {
    const sats = $$('.sat', el).map((s) => ({
      el: s, a: +s.dataset.a || 0.4, b: +s.dataset.b || 0.2, sp: +s.dataset.speed || 0.2,
      ph: (+s.dataset.phase || 0) * Math.PI / 180, tilt: (+s.dataset.tilt || 0) * Math.PI / 180,
    }));
    const rings = $$('ellipse[data-ring]', el);
    return { el, sats, rings, w: 0, h: 0, cy: +el.dataset.cy || 0.5, visible: true };
  });
  function measure() {
    systems.forEach((s) => {
      s.w = s.el.clientWidth; s.h = s.el.clientHeight;
      s.rings.forEach((r) => {
        const a = +r.dataset.a, b = +r.dataset.b, tilt = +(r.dataset.tilt || 0);
        const cy = s.h * s.cy;
        r.setAttribute('cx', s.w / 2); r.setAttribute('cy', cy);
        r.setAttribute('rx', a * s.w / 2); r.setAttribute('ry', b * s.h / 2);
        r.setAttribute('transform', `rotate(${tilt} ${s.w / 2} ${cy})`);
        r.closest('svg').setAttribute('viewBox', `0 0 ${s.w} ${s.h}`);
      });
    });
  }
  if (systems.length) {
    measure();
    window.addEventListener('resize', measure);
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((es) => es.forEach((e) => { const s = systems.find((x) => x.el === e.target); if (s) s.visible = e.isIntersecting; }));
      systems.forEach((s) => io.observe(s.el));
    }
    const tick = (now) => {
      requestAnimationFrame(tick);
      const t = reduce ? 0 : now / 1000;
      systems.forEach((sys) => {
        if (!sys.visible) return;
        const px = fine ? mouse.nx * 14 : 0, py = fine ? mouse.ny * 10 : 0;
        sys.sats.forEach((s) => {
          const ang = s.ph + t * s.sp;
          const ex = Math.cos(ang) * s.a * sys.w / 2, ey = Math.sin(ang) * s.b * sys.h / 2;
          const x = ex * Math.cos(s.tilt) - ey * Math.sin(s.tilt), y = ex * Math.sin(s.tilt) + ey * Math.cos(s.tilt);
          const depth = (Math.sin(ang) + 1) / 2; // 0 = far, 1 = near
          s.el.style.transform = `translate(-50%,-50%) translate(${(x + px * (0.5 + depth)).toFixed(1)}px,${(y + py * (0.5 + depth)).toFixed(1)}px) scale(${(0.62 + depth * 0.45).toFixed(3)})`;
          s.el.style.zIndex = depth > 0.5 ? 6 : 2;
          s.el.style.opacity = (0.45 + depth * 0.55).toFixed(2);
          s.el.style.filter = depth < 0.35 ? `blur(${((0.35 - depth) * 4).toFixed(1)}px)` : '';
        });
      });
    };
    requestAnimationFrame(tick);
  }

  /* ------------------------------------------------------------------ parallax layers */
  const layers = $$('[data-depth]');
  if (layers.length && fine && !reduce) {
    let cx = 0, cy = 0;
    const loop = () => {
      requestAnimationFrame(loop);
      cx += (mouse.nx - cx) * 0.06; cy += (mouse.ny - cy) * 0.06;
      layers.forEach((l) => { const d = +l.dataset.depth; l.style.translate = `${(cx * d * -40).toFixed(1)}px ${(cy * d * -30).toFixed(1)}px`; });
    };
    loop();
  }

  /* ------------------------------------------------------------------ 3D tilt cards */
  if (fine && !reduce) {
    document.addEventListener('pointermove', (e) => {
      const card = e.target.closest && e.target.closest('[data-tilt]');
      $$('[data-tilt].tilting').forEach((c) => { if (c !== card) { c.classList.remove('tilting'); c.style.transform = ''; } });
      if (!card) return;
      const r = card.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      card.classList.add('tilting');
      card.style.transform = `perspective(900px) rotateX(${(-y * 7).toFixed(2)}deg) rotateY(${(x * 9).toFixed(2)}deg) translateY(-6px)`;
      card.style.setProperty('--mx', (x + 0.5) * 100 + '%');
      card.style.setProperty('--my', (y + 0.5) * 100 + '%');
    }, { passive: true });
  }

  /* ------------------------------------------------------------------ cursor glow */
  if (fine && !reduce && document.body.dataset.glow !== '0') {
    const g = document.createElement('div');
    g.className = 'cursor-glow';
    document.body.appendChild(g);
    let gx = innerWidth / 2, gy = innerHeight / 2;
    const follow = () => {
      requestAnimationFrame(follow);
      gx += (mouse.x - gx) * 0.12; gy += (mouse.y - gy) * 0.12;
      g.style.transform = `translate(${gx.toFixed(1)}px,${gy.toFixed(1)}px)`;
    };
    follow();
  }

  /* ------------------------------------------------------------------ typing terminal */
  $$('[data-typing]').forEach((el) => {
    const lines = JSON.parse(el.dataset.typing);
    const start = () => {
      let li = 0, ci = 0;
      el.innerHTML = '';
      let row = null;
      const step = () => {
        if (li >= lines.length) { setTimeout(start, 4500); return; }
        const [text, cls] = lines[li];
        if (ci === 0) { row = document.createElement('div'); row.className = 't-line ' + (cls || ''); el.appendChild(row); }
        row.textContent = text.slice(0, ++ci);
        if (ci >= text.length) { li++; ci = 0; setTimeout(step, cls === 'out' ? 380 : 220); }
        else setTimeout(step, cls === 'out' ? 9 : 26 + Math.random() * 30);
      };
      step();
    };
    if (reduce) { el.innerHTML = lines.map(([t, c]) => `<div class="t-line ${c || ''}">${t.replace(/</g, '&lt;')}</div>`).join(''); return; }
    const io = new IntersectionObserver((es) => { if (es[0].isIntersecting) { io.disconnect(); start(); } });
    io.observe(el);
  });

  /* ------------------------------------------------------------------ magnetic buttons */
  if (fine && !reduce) {
    $$('[data-magnetic]').forEach((b) => {
      b.addEventListener('pointermove', (e) => {
        const r = b.getBoundingClientRect();
        b.style.translate = `${((e.clientX - r.left - r.width / 2) * 0.18).toFixed(1)}px ${((e.clientY - r.top - r.height / 2) * 0.25).toFixed(1)}px`;
      });
      b.addEventListener('pointerleave', () => { b.style.translate = ''; });
    });
  }
})();
