/* ═══════════════ Galaxy background ═══════════════
   Stars, drifting dust, asteroids and the odd meteor on a single canvas.
   The sky is seeded, so it is identical on every page, and its clock carries
   over between pages. One rAF loop, paused while hidden; the tier steps down
   by itself when the device cannot keep up. */

NX.galaxy = (() => {
  const TAU = Math.PI * 2;
  const TIERS = {
    high: { density: 1 / 4200, maxStars: 380, dust: 56, rocks: 7, chips: 12, fps: 60, dpr: 1.75, meteors: true },
    medium: { density: 1 / 7000, maxStars: 210, dust: 24, rocks: 4, chips: 6, fps: 30, dpr: 1.25, meteors: false },
    low: { density: 1 / 7000, maxStars: 210, dust: 16, rocks: 3, chips: 4, fps: 0, dpr: 1, meteors: false },
  };
  const STAR_TINTS = ['255,255,255', '196,232,255', '200,196,255', '255,226,206'];
  const STAR_FILLS = STAR_TINTS.map((rgb) => `rgb(${rgb})`);
  const DUST_TINTS = ['91,227,238', '160,143,255'];
  const LIGHT = { x: 0.55, y: -0.65 }; // key light from the upper right, like the nebula

  let canvas, ctx, layer;
  let width = 0, height = 0, ratio = 1, tier = TIERS.high;
  let stars = [], dust = [], rocks = [];
  let starSprites = [], dustSprites = [], meteorSprite = null;
  let raf = 0, last = 0, clock = 0, running = false;
  let avgFrame = 16, slowFor = 0, warmup = 0, resizeTimer = 0;
  let scroll = 0, meteor = null, nextMeteor = 7, layerX = 0, layerY = 0;
  const pointer = { x: 0, y: 0, tx: 0, ty: 0 };

  /* Deterministic PRNG (mulberry32): the same seed paints the same sky. */
  const seeded = (seed) => () => {
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };

  const wrap = (value, size, margin = 0) => {
    const span = size + margin * 2;
    return ((((value + margin) % span) + span) % span) - margin;
  };

  function surface(w, h = w) {
    const c = document.createElement('canvas');
    c.width = Math.ceil(w);
    c.height = Math.ceil(h);
    return c;
  }

  /* ── Pre-rendered sprites (built once, drawn every frame) ── */
  function glowSprite(rgb, size = 64) {
    const c = surface(size);
    const g = c.getContext('2d');
    const r = size / 2;
    const fill = g.createRadialGradient(r, r, 0, r, r, r);
    fill.addColorStop(0, `rgba(${rgb},1)`);
    fill.addColorStop(0.16, `rgba(${rgb},.85)`);
    fill.addColorStop(0.4, `rgba(${rgb},.16)`);
    fill.addColorStop(1, `rgba(${rgb},0)`);
    g.fillStyle = fill;
    g.fillRect(0, 0, size, size);
    return c;
  }

  function meteorTrail() {
    const c = surface(220, 6);
    const g = c.getContext('2d');
    const fill = g.createLinearGradient(0, 0, 220, 0);
    fill.addColorStop(0, 'rgba(160,236,255,0)');
    fill.addColorStop(0.8, 'rgba(196,244,255,.5)');
    fill.addColorStop(1, 'rgba(255,255,255,1)');
    g.fillStyle = fill;
    g.beginPath();
    g.moveTo(0, 3);
    g.lineTo(216, 1.4);
    g.lineTo(216, 4.6);
    g.closePath();
    g.fill();
    return c;
  }

  function rockSprite(radius, rand) {
    const pad = 3;
    const c = surface((radius + pad) * 2 * ratio);
    const g = c.getContext('2d');
    g.scale(ratio, ratio);
    const cx = radius + pad;
    const cy = radius + pad;

    const count = 9 + Math.floor(rand() * 5);
    const points = Array.from({ length: count }, (_, i) => {
      const angle = (i / count) * TAU + rand() * 0.3;
      const r = radius * (0.72 + rand() * 0.28);
      return [cx + Math.cos(angle) * r, cy + Math.sin(angle) * r];
    });
    const mid = (a, b) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
    const shape = new Path2D();
    const start = mid(points[count - 1], points[0]);
    shape.moveTo(start[0], start[1]);
    points.forEach((p, i) => {
      const m = mid(p, points[(i + 1) % count]);
      shape.quadraticCurveTo(p[0], p[1], m[0], m[1]);
    });
    shape.closePath();

    const base = rand() < 0.5 ? [132, 142, 178] : [152, 140, 158];
    const lx = cx + LIGHT.x * radius * 0.7;
    const ly = cy + LIGHT.y * radius * 0.7;
    const body = g.createRadialGradient(lx, ly, radius * 0.1, cx, cy, radius * 1.2);
    body.addColorStop(0, `rgb(${base.map((v) => v + 46).join(',')})`);
    body.addColorStop(0.45, `rgb(${base.map((v) => Math.round(v * 0.5)).join(',')})`);
    body.addColorStop(1, 'rgb(12,13,28)');
    g.fillStyle = body;
    g.fill(shape);

    g.save();
    g.clip(shape);
    const away = Math.atan2(-LIGHT.y, -LIGHT.x);
    for (let i = 0, n = 2 + Math.floor(rand() * 3); i < n; i++) {
      const angle = rand() * TAU;
      const dist = rand() * radius * 0.55;
      const r = radius * (0.12 + rand() * 0.15);
      const x = cx + Math.cos(angle) * dist;
      const y = cy + Math.sin(angle) * dist;
      g.fillStyle = 'rgba(6,7,18,.38)';
      g.beginPath();
      g.arc(x, y, r, 0, TAU);
      g.fill();
      g.strokeStyle = 'rgba(214,226,255,.16)';
      g.lineWidth = Math.max(0.5, r * 0.22);
      g.beginPath();
      g.arc(x, y, r * 0.86, away - 1, away + 1);
      g.stroke();
    }
    g.restore();

    const rim = g.createLinearGradient(cx - LIGHT.x * radius, cy - LIGHT.y * radius, cx + LIGHT.x * radius, cy + LIGHT.y * radius);
    rim.addColorStop(0.35, 'rgba(120,230,245,0)');
    rim.addColorStop(1, 'rgba(170,242,255,.6)');
    g.strokeStyle = rim;
    g.lineWidth = 1;
    g.stroke(shape);
    return c;
  }

  /* ── Scene ── */
  function makeStars(count, rand) {
    return Array.from({ length: count }, () => {
      const z = rand() ** 2; // most stars are far away
      const bright = rand() < 0.05;
      return {
        x: rand(), y: rand(), z,
        size: 0.5 + z * 1.4 + (bright ? 1.4 : 0),
        alpha: 0.3 + z * 0.55 + (bright ? 0.15 : 0),
        tint: Math.floor(rand() * STAR_TINTS.length),
        phase: rand() * TAU,
        speed: 0.5 + rand() * 1.8,
      };
    });
  }

  function makeDust(count, rand) {
    return Array.from({ length: count }, () => ({
      x: rand(), y: rand(), z: 0.3 + rand() * 0.7,
      size: 2 + rand() * 4,
      alpha: 0.12 + rand() * 0.3,
      tint: rand() < 0.55 ? 0 : 1,
      vx: (rand() - 0.5) * 6,
      vy: -1.5 - rand() * 4,
      amp: 10 + rand() * 26,
      freq: 0.04 + rand() * 0.09,
      phase: rand() * TAU,
    }));
  }

  function makeRocks(count, chips, rand) {
    const list = Array.from({ length: count + chips }, (_, i) => {
      const chip = i >= count; // tiny fragments of galactic rock
      const z = chip ? 0.3 + rand() * 0.7 : 0.25 + rand() * 0.75;
      const radius = chip ? 1.2 + rand() * 1.8 : (4 + rand() * 10) * (0.5 + z * 0.9);
      return {
        x: rand(), y: rand(), z, radius,
        vx: -(3 + rand() * 7) * z,
        vy: (rand() - 0.5) * 4 * z,
        bob: 3 + rand() * 9,
        bobSpeed: 0.04 + rand() * 0.08,
        phase: rand() * TAU,
        spin: (rand() - 0.5) * (chip ? 0.9 : 0.22),
        angle: rand() * TAU,
        alpha: chip ? 0.35 + z * 0.4 : 0.4 + z * 0.55,
        sprite: rockSprite(radius, rand),
      };
    });
    return list.sort((a, b) => a.z - b.z); // far ones are painted first
  }

  function setup() {
    tier = TIERS[NX.root.dataset.quality] || TIERS.medium;
    ratio = Math.min(window.devicePixelRatio || 1, tier.dpr);
    width = canvas.clientWidth;
    height = canvas.clientHeight;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    stars = makeStars(Math.min(tier.maxStars, Math.round(width * height * tier.density)), seeded(1405));
    dust = makeDust(tier.dust, seeded(7));
    rocks = makeRocks(tier.rocks, tier.chips, seeded(42));
    avgFrame = 1000 / (tier.fps || 60);
    slowFor = 0;
    warmup = 0;
  }

  /* ── Rendering ── */
  function drawMeteor(dt) {
    if (!meteor) {
      nextMeteor -= dt;
      if (nextMeteor > 0) return;
      nextMeteor = 9 + Math.random() * 10;
      meteor = {
        x: width * (0.35 + Math.random() * 0.6),
        y: height * Math.random() * 0.35,
        angle: Math.PI * (0.76 + Math.random() * 0.08),
        speed: 700 + Math.random() * 300,
        life: 0,
      };
    }
    meteor.life += dt;
    const progress = meteor.life / 1.1;
    if (progress >= 1) {
      meteor = null;
      return;
    }
    const dist = meteor.speed * meteor.life;
    const x = meteor.x + Math.cos(meteor.angle) * dist;
    const y = meteor.y + Math.sin(meteor.angle) * dist;
    const cos = Math.cos(meteor.angle) * ratio;
    const sin = Math.sin(meteor.angle) * ratio;
    ctx.setTransform(cos, sin, -sin, cos, x * ratio, y * ratio);
    ctx.globalAlpha = Math.sin(progress * Math.PI) * 0.8;
    ctx.drawImage(meteorSprite, -216, -3);
  }

  function draw(dt) {
    const ease = dt ? Math.min(1, dt * 2.4) : 1;
    pointer.x += (pointer.tx - pointer.x) * ease;
    pointer.y += (pointer.ty - pointer.y) * ease;
    scroll += (window.scrollY - scroll) * (dt ? Math.min(1, dt * 7) : 1);
    const px = pointer.x * 26;
    const py = pointer.y * 18;

    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);

    for (const s of stars) {
      const x = wrap(s.x * width - clock * (1.2 + s.z * 4) - px * s.z, width);
      const y = wrap(s.y * height - scroll * s.z * 0.1 - py * s.z, height);
      ctx.globalAlpha = s.alpha * (0.72 + 0.28 * Math.sin(clock * s.speed + s.phase));
      if (s.size < 1.3) {
        ctx.fillStyle = STAR_FILLS[s.tint];
        ctx.fillRect(x, y, s.size, s.size);
      } else {
        const d = s.size * 5;
        ctx.drawImage(starSprites[s.tint], x - d / 2, y - d / 2, d, d);
      }
    }

    for (const p of dust) {
      const x = wrap(p.x * width + p.vx * clock + Math.sin(clock * p.freq + p.phase) * p.amp - px * p.z * 1.4, width, 10);
      const y = wrap(p.y * height + p.vy * clock + Math.cos(clock * p.freq * 0.8 + p.phase) * p.amp * 0.6
        - scroll * p.z * 0.22 - py * p.z * 1.4, height, 10);
      ctx.globalAlpha = p.alpha * (0.6 + 0.4 * Math.sin(clock * p.freq * 3 + p.phase));
      ctx.drawImage(dustSprites[p.tint], x - p.size, y - p.size, p.size * 2, p.size * 2);
    }

    for (const r of rocks) {
      const margin = r.radius + 8;
      const x = wrap(r.x * width + r.vx * clock - px * r.z * 2, width, margin);
      const y = wrap(r.y * height + r.vy * clock + Math.sin(clock * r.bobSpeed + r.phase) * r.bob
        - scroll * r.z * 0.32 - py * r.z * 2, height, margin);
      const angle = r.angle + r.spin * clock;
      const cos = Math.cos(angle) * ratio;
      const sin = Math.sin(angle) * ratio;
      const size = r.sprite.width / ratio;
      ctx.setTransform(cos, sin, -sin, cos, x * ratio, y * ratio);
      ctx.globalAlpha = r.alpha;
      ctx.drawImage(r.sprite, -size / 2, -size / 2, size, size);
    }

    if (tier.meteors) drawMeteor(dt);
    ctx.globalAlpha = 1;

    const lx = -pointer.x * 14;
    const ly = -pointer.y * 10 - Math.min(scroll * 0.02, 40);
    if (Math.abs(lx - layerX) > 0.1 || Math.abs(ly - layerY) > 0.1) {
      layerX = lx;
      layerY = ly;
      layer.style.transform = `translate3d(${lx.toFixed(1)}px, ${ly.toFixed(1)}px, 0)`;
    }
  }

  /* ── Loop ── */
  function frame(now) {
    raf = requestAnimationFrame(frame);
    const elapsed = now - last;
    if (elapsed < 1000 / tier.fps - 3) return;
    last = now;
    const dt = Math.min(elapsed, 100) / 1000;
    clock += dt;
    draw(dt);
    watch(elapsed);
  }

  /* Step down a tier when frames stay slow for a few seconds; below medium,
     ask the interface to switch to performance mode. */
  function watch(elapsed) {
    warmup += elapsed;
    if (warmup < 2500) return;
    avgFrame += (elapsed - avgFrame) * 0.05;
    slowFor = avgFrame > (1000 / tier.fps) * 1.45 ? slowFor + elapsed : 0;
    if (slowFor <= 3000) return;
    slowFor = 0;
    if (tier === TIERS.high) NX.setQuality('medium');
    else document.dispatchEvent(new CustomEvent('nx:slow'));
  }

  function sync() {
    const live = tier.fps > 0 && !document.hidden;
    if (live && !running) {
      running = true;
      last = performance.now();
      raf = requestAnimationFrame(frame);
    } else if (!live && running) {
      running = false;
      cancelAnimationFrame(raf);
    }
  }

  function rebuild() {
    setup();
    draw(0);
    sync();
  }

  function init() {
    canvas = NX.$('[data-galaxy]');
    layer = NX.$('[data-galaxy-parallax]');
    ctx = canvas && canvas.getContext('2d');
    if (!ctx) return;

    try { clock = Number(sessionStorage.getItem('nx-clock')) || 0; } catch { /* storage blocked */ }
    starSprites = STAR_TINTS.map((rgb) => glowSprite(rgb));
    dustSprites = DUST_TINTS.map((rgb) => glowSprite(rgb, 32));
    meteorSprite = meteorTrail();
    rebuild();
    NX.root.classList.add('galaxy-live');

    document.addEventListener('visibilitychange', sync);
    document.addEventListener('nx:quality', rebuild);
    NX.reducedMotion.addEventListener('change', () => NX.setQuality(window.nxQuality()));
    addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (canvas.clientWidth !== width || Math.abs(canvas.clientHeight - height) > 1) rebuild();
      }, 180);
    }, { passive: true });
    addEventListener('pagehide', () => {
      try { sessionStorage.setItem('nx-clock', clock.toFixed(2)); } catch { /* storage blocked */ }
    });
    if (NX.finePointer) {
      addEventListener('pointermove', (event) => {
        pointer.tx = (event.clientX / innerWidth - 0.5) * 2;
        pointer.ty = (event.clientY / innerHeight - 0.5) * 2;
      }, { passive: true });
    }
  }

  return { init };
})();
