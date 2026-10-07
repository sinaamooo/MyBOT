/* ═══════════════ Galaxy background ═══════════════
   Three layers:
   • painted nebula clouds and a spiral galaxy — drawn once, animated by CSS on the GPU;
   • a live canvas — coloured twinkling stars, flaring stars, dust, asteroids,
     floating lights and shooting stars.
   The sky is seeded, so it is identical on every page, and its clock carries over
   between pages. One rAF loop, paused while hidden; it steps down by itself when
   the device cannot keep up. */

NX.galaxy = (() => {
  const TAU = Math.PI * 2;
  const TIERS = {
    high: { density: 1 / 3400, maxStars: 480, flares: 9, dust: 70, rocks: 7, chips: 12, lights: 9, fps: 60, dpr: 1.75, meteors: [3.5, 7], paint: 1 },
    medium: { density: 1 / 5600, maxStars: 280, flares: 6, dust: 32, rocks: 4, chips: 6, lights: 5, fps: 30, dpr: 1.25, meteors: [6, 11], paint: 0.65 },
    low: { density: 1 / 5600, maxStars: 280, flares: 6, dust: 22, rocks: 3, chips: 4, lights: 4, fps: 0, dpr: 1, meteors: null, paint: 0.55 },
  };
  // Mostly white, then blue, purple, red and a little green — weights add up to 1.
  const STAR_TINTS = [
    ['255,255,255', 0.52], ['210,224,255', 0.14], ['140,176,255', 0.1],
    ['198,156,255', 0.11], ['255,140,176', 0.08], ['140,255,206', 0.05],
  ];
  const FLARE_TINTS = ['255,255,255', '176,206,255', '214,176,255', '255,176,206'];
  const LIGHT_TINTS = ['176,132,255', '92,160,255', '64,228,166', '255,92,138', '220,200,255'];
  const METEOR_TINTS = ['220,236,255', '206,176,255', '180,255,226', '255,190,214'];
  const LIGHT = { x: 0.55, y: -0.65 }; // key light from the upper right

  let canvas, ctx, layer, nebula, spiral;
  let width = 0, height = 0, ratio = 1, tier = TIERS.high, painted = null;
  let stars = [], flares = [], dust = [], rocks = [], lights = [];
  let sprites = {};
  let raf = 0, last = 0, clock = 0, running = false;
  let avgFrame = 16, slowFor = 0, warmup = 0, resizeTimer = 0;
  let scroll = 0, meteor = null, nextMeteor = 3, layerX = 0, layerY = 0;
  const pointer = { x: 0, y: 0, tx: 0, ty: 0 };

  /* ── Randomness ── */
  const seeded = (seed) => () => {
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
  const gauss = (rand) => (rand() + rand() + rand() - 1.5) / 1.5; // ≈ normal, within ±1
  const pick = (rand, weighted) => {
    let roll = rand();
    for (const [value, weight] of weighted) if ((roll -= weight) <= 0) return value;
    return weighted[0][0];
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

  function puff(g, x, y, r, rgb, alpha) {
    const fill = g.createRadialGradient(x, y, 0, x, y, r);
    fill.addColorStop(0, `rgba(${rgb},${alpha})`);
    fill.addColorStop(1, `rgba(${rgb},0)`);
    g.fillStyle = fill;
    g.fillRect(x - r, y - r, r * 2, r * 2);
  }

  /* ── Painted once: nebula clouds ── */
  function paintNebula(scale, aspect) {
    const rand = seeded(2024);
    const w = Math.round(1000 * scale);
    const h = Math.round(w * aspect); // match the screen so clouds are not stretched
    nebula.width = w;
    nebula.height = h;
    const g = nebula.getContext('2d');
    g.globalCompositeOperation = 'lighter';

    // [centre x, centre y, spread x, spread y, colour, puffs, alpha]
    const clouds = [
      [0.8, 0.16, 0.2, 0.15, '150,76,255', 80, 0.075],
      [0.64, 0.3, 0.26, 0.1, '112,86,255', 50, 0.055],
      [0.16, 0.6, 0.18, 0.2, '56,116,255', 70, 0.065],
      [0.88, 0.78, 0.12, 0.12, '255,64,128', 44, 0.06],
      [0.3, 0.9, 0.17, 0.08, '40,214,156', 40, 0.05],
      [0.46, 0.08, 0.14, 0.06, '255,120,200', 18, 0.035],
    ];
    for (const [cx, cy, sx, sy, rgb, count, alpha] of clouds) {
      for (let i = 0; i < count * scale; i++) {
        const x = (cx + gauss(rand) * sx) * w;
        const y = (cy + gauss(rand) * sy) * h;
        puff(g, x, y, (0.035 + rand() * 0.11) * w, rgb, alpha * (0.6 + rand() * 0.8));
      }
    }
    // A faint milky band across the sky
    for (let i = 0; i < 70 * scale; i++) {
      const t = rand();
      const x = (0.02 + t * 0.96) * w + gauss(rand) * 0.04 * w;
      const y = (0.86 - t * 0.74) * h + gauss(rand) * 0.07 * h;
      puff(g, x, y, (0.03 + rand() * 0.07) * w, t < 0.5 ? '170,150,255' : '200,180,255', 0.03 + rand() * 0.03);
    }
    // Dark dust lanes carve structure into the glow
    g.globalCompositeOperation = 'destination-out';
    for (let i = 0; i < 34; i++) {
      const t = rand();
      const x = (0.98 - t * 0.5) * w + gauss(rand) * 0.03 * w;
      const y = (0.04 + t * 0.42) * h + gauss(rand) * 0.03 * h;
      puff(g, x, y, (0.015 + rand() * 0.045) * w, '0,0,0', 0.28);
    }
    g.globalCompositeOperation = 'source-over';
  }

  /* ── Painted once: a two-armed spiral galaxy ── */
  function paintSpiral(scale) {
    const rand = seeded(77);
    const size = Math.round(780 * Math.max(scale, 0.7));
    spiral.width = spiral.height = size;
    const g = spiral.getContext('2d');
    const c = size / 2;
    const R = size * 0.47;
    const ARMS = 2;
    const TWIST = 5.4;
    g.globalCompositeOperation = 'lighter';

    const disc = g.createRadialGradient(c, c, 0, c, c, R);
    disc.addColorStop(0, 'rgba(255,236,255,.5)');
    disc.addColorStop(0.1, 'rgba(214,160,255,.28)');
    disc.addColorStop(0.4, 'rgba(120,88,255,.1)');
    disc.addColorStop(1, 'rgba(60,70,220,0)');
    g.fillStyle = disc;
    g.fillRect(0, 0, size, size);

    const armPoint = (d, arm, jitter) => {
      const angle = (arm * TAU) / ARMS + d * TWIST + gauss(rand) * jitter;
      return [c + Math.cos(angle) * d * R, c + Math.sin(angle) * d * R];
    };
    for (let i = 0; i < 180; i++) {
      const d = 0.08 + rand() ** 0.9 * 0.9;
      const [x, y] = armPoint(d, i % ARMS, 0.22);
      const rgb = d < 0.3 ? '214,160,255' : rand() < 0.5 ? '110,140,255' : '176,110,255';
      puff(g, x, y, R * (0.04 + rand() * 0.07), rgb, 0.1);
    }
    for (let i = 0; i < 26; i++) { // red star-forming knots along the arms
      const [x, y] = armPoint(0.3 + rand() * 0.6, i % ARMS, 0.12);
      puff(g, x, y, R * (0.015 + rand() * 0.025), '255,90,150', 0.22);
    }

    const tints = [['206,220,255', 0.46], ['184,150,255', 0.22], ['130,170,255', 0.14], ['255,255,255', 0.1], ['255,130,176', 0.05], ['150,255,210', 0.03]];
    const count = Math.round(5200 * scale);
    for (let i = 0; i < count; i++) {
      const d = rand() ** 1.5;
      const scattered = rand() < 0.22;
      let x;
      let y;
      if (scattered) {
        const angle = rand() * TAU;
        x = c + Math.cos(angle) * d * R;
        y = c + Math.sin(angle) * d * R;
      } else {
        [x, y] = armPoint(d, i % ARMS, 0.42 - d * 0.22);
      }
      const rgb = d < 0.1 ? '255,236,214' : pick(rand, tints);
      const s = 0.6 + rand() * 1.1 + (rand() < 0.02 ? 1.2 : 0);
      g.fillStyle = `rgba(${rgb},${0.35 + rand() * 0.6})`;
      g.fillRect(x, y, s, s);
    }

    const core = g.createRadialGradient(c, c, 0, c, c, R * 0.16);
    core.addColorStop(0, 'rgba(255,255,255,.95)');
    core.addColorStop(0.3, 'rgba(255,220,255,.5)');
    core.addColorStop(1, 'rgba(176,120,255,0)');
    g.fillStyle = core;
    g.fillRect(0, 0, size, size);
    g.globalCompositeOperation = 'source-over';
  }

  /* ── Sprites for the live canvas (built once) ── */
  function glowSprite(rgb, size = 64) {
    const c = surface(size);
    const g = c.getContext('2d');
    const r = size / 2;
    const fill = g.createRadialGradient(r, r, 0, r, r, r);
    fill.addColorStop(0, `rgba(${rgb},1)`);
    fill.addColorStop(0.16, `rgba(${rgb},.85)`);
    fill.addColorStop(0.4, `rgba(${rgb},.18)`);
    fill.addColorStop(1, `rgba(${rgb},0)`);
    g.fillStyle = fill;
    g.fillRect(0, 0, size, size);
    return c;
  }

  function flareSprite(rgb) {
    const size = 96;
    const c = glowSprite(rgb, size);
    const g = c.getContext('2d');
    const r = size / 2;
    g.globalCompositeOperation = 'lighter';
    // Diffraction spikes: two long ones and two short diagonals
    for (const [length, thickness, angle] of [[size, 2, 0], [size, 2, Math.PI / 2], [size * 0.5, 1.2, Math.PI / 4], [size * 0.5, 1.2, -Math.PI / 4]]) {
      const spike = g.createRadialGradient(0, 0, 0, 0, 0, length / 2);
      spike.addColorStop(0, `rgba(${rgb},.9)`);
      spike.addColorStop(1, `rgba(${rgb},0)`);
      g.setTransform(Math.cos(angle), Math.sin(angle), -Math.sin(angle), Math.cos(angle), r, r);
      g.fillStyle = spike;
      g.fillRect(-length / 2, -thickness / 2, length, thickness);
    }
    return c;
  }

  function lightSprite(rgb) {
    const size = 128;
    const c = surface(size);
    const g = c.getContext('2d');
    const r = size / 2;
    const fill = g.createRadialGradient(r, r, 0, r, r, r);
    fill.addColorStop(0, `rgba(${rgb},.55)`);
    fill.addColorStop(0.55, `rgba(${rgb},.32)`);
    fill.addColorStop(0.8, `rgba(${rgb},.12)`);
    fill.addColorStop(1, `rgba(${rgb},0)`);
    g.fillStyle = fill;
    g.fillRect(0, 0, size, size);
    return c;
  }

  function meteorSprite(rgb) {
    const c = surface(240, 6);
    const g = c.getContext('2d');
    const fill = g.createLinearGradient(0, 0, 240, 0);
    fill.addColorStop(0, `rgba(${rgb},0)`);
    fill.addColorStop(0.75, `rgba(${rgb},.55)`);
    fill.addColorStop(1, 'rgba(255,255,255,1)');
    g.fillStyle = fill;
    g.beginPath();
    g.moveTo(0, 3);
    g.lineTo(236, 1.3);
    g.lineTo(236, 4.7);
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

    const base = rand() < 0.5 ? [140, 128, 186] : [160, 136, 160];
    const lx = cx + LIGHT.x * radius * 0.7;
    const ly = cy + LIGHT.y * radius * 0.7;
    const body = g.createRadialGradient(lx, ly, radius * 0.1, cx, cy, radius * 1.2);
    body.addColorStop(0, `rgb(${base.map((v) => v + 50).join(',')})`);
    body.addColorStop(0.45, `rgb(${base.map((v) => Math.round(v * 0.48)).join(',')})`);
    body.addColorStop(1, 'rgb(14,10,32)');
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
      g.fillStyle = 'rgba(8,4,24,.38)';
      g.beginPath();
      g.arc(x, y, r, 0, TAU);
      g.fill();
      g.strokeStyle = 'rgba(226,214,255,.16)';
      g.lineWidth = Math.max(0.5, r * 0.22);
      g.beginPath();
      g.arc(x, y, r * 0.86, away - 1, away + 1);
      g.stroke();
    }
    g.restore();

    const rim = g.createLinearGradient(cx - LIGHT.x * radius, cy - LIGHT.y * radius, cx + LIGHT.x * radius, cy + LIGHT.y * radius);
    rim.addColorStop(0.35, 'rgba(176,132,255,0)');
    rim.addColorStop(1, 'rgba(214,190,255,.7)');
    g.strokeStyle = rim;
    g.lineWidth = 1;
    g.stroke(shape);
    return c;
  }

  /* ── Scene ── */
  function makeStars(count, rand) {
    return Array.from({ length: count }, () => {
      const z = rand() ** 2; // most stars are far away
      const bright = rand() < 0.06;
      const tint = pick(rand, STAR_TINTS);
      return {
        x: rand(), y: rand(), z,
        size: 0.5 + z * 1.5 + (bright ? 1.5 : 0),
        alpha: 0.35 + z * 0.55 + (bright ? 0.1 : 0),
        tint,
        fill: `rgb(${tint})`,
        phase: rand() * TAU,
        speed: 0.6 + rand() * 2.2,
      };
    });
  }

  function makeFlares(count, rand) {
    return Array.from({ length: count }, (_, i) => ({
      x: rand(), y: rand() * 0.9, z: 0.4 + rand() * 0.6,
      size: 16 + rand() * 22,
      tint: FLARE_TINTS[i % FLARE_TINTS.length],
      phase: rand() * TAU,
      speed: 0.5 + rand() * 1.2,
    }));
  }

  function makeDust(count, rand) {
    return Array.from({ length: count }, () => ({
      x: rand(), y: rand(), z: 0.3 + rand() * 0.7,
      size: 2 + rand() * 4.5,
      alpha: 0.18 + rand() * 0.38,
      tint: LIGHT_TINTS[Math.floor(rand() * LIGHT_TINTS.length)],
      vx: (rand() - 0.5) * 7,
      vy: -1.5 - rand() * 5,
      amp: 10 + rand() * 28,
      freq: 0.04 + rand() * 0.1,
      phase: rand() * TAU,
    }));
  }

  function makeLights(count, rand) {
    return Array.from({ length: count }, (_, i) => ({
      x: rand(), y: rand(), z: 0.9 + rand() * 0.6, // closest layer: moves the most
      size: 70 + rand() * 170,
      alpha: 0.05 + rand() * 0.07,
      tint: LIGHT_TINTS[i % LIGHT_TINTS.length],
      vx: (rand() - 0.5) * 8,
      vy: (rand() - 0.5) * 6,
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
        alpha: chip ? 0.4 + z * 0.4 : 0.45 + z * 0.5,
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
    flares = makeFlares(tier.flares, seeded(9));
    dust = makeDust(tier.dust, seeded(7));
    lights = makeLights(tier.lights, seeded(31));
    rocks = makeRocks(tier.rocks, tier.chips, seeded(42));
    const aspect = Math.min(2.4, Math.max(0.4, height / width));
    const key = `${tier.paint}:${aspect.toFixed(1)}`;
    if (painted !== key) {
      paintNebula(tier.paint, aspect);
      if (!painted || painted.split(':')[0] !== String(tier.paint)) paintSpiral(tier.paint);
      painted = key;
    }
    avgFrame = 1000 / (tier.fps || 60);
    slowFor = 0;
    warmup = 0;
  }

  /* ── Rendering ── */
  function drawMeteor(dt) {
    if (!meteor) {
      nextMeteor -= dt;
      if (nextMeteor > 0) return;
      const [min, max] = tier.meteors;
      nextMeteor = min + Math.random() * (max - min);
      meteor = {
        x: width * (0.3 + Math.random() * 0.65),
        y: height * Math.random() * 0.4,
        angle: Math.PI * (0.74 + Math.random() * 0.1),
        speed: 650 + Math.random() * 400,
        sprite: sprites.meteors[Math.floor(Math.random() * sprites.meteors.length)],
        life: 0,
      };
    }
    meteor.life += dt;
    const progress = meteor.life / 1.15;
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
    ctx.globalAlpha = Math.sin(progress * Math.PI) * 0.9;
    ctx.drawImage(meteor.sprite, -236, -3);
    ctx.drawImage(sprites.stars['255,255,255'], -8, -8, 16, 16);
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
      ctx.globalAlpha = s.alpha * (0.55 + 0.45 * Math.sin(clock * s.speed + s.phase));
      if (s.size < 1.3) {
        ctx.fillStyle = s.fill;
        ctx.fillRect(x, y, s.size, s.size);
      } else {
        const d = s.size * 5;
        ctx.drawImage(sprites.stars[s.tint], x - d / 2, y - d / 2, d, d);
      }
    }

    for (const f of flares) {
      const pulse = 0.5 + 0.5 * Math.sin(clock * f.speed + f.phase);
      const d = f.size * (0.75 + pulse * 0.4);
      const x = wrap(f.x * width - clock * 2 - px * f.z, width, 20);
      const y = wrap(f.y * height - scroll * f.z * 0.12 - py * f.z, height, 20);
      ctx.globalAlpha = 0.45 + pulse * 0.55;
      ctx.drawImage(sprites.flares[f.tint], x - d / 2, y - d / 2, d, d);
    }

    for (const p of dust) {
      const x = wrap(p.x * width + p.vx * clock + Math.sin(clock * p.freq + p.phase) * p.amp - px * p.z * 1.4, width, 10);
      const y = wrap(p.y * height + p.vy * clock + Math.cos(clock * p.freq * 0.8 + p.phase) * p.amp * 0.6
        - scroll * p.z * 0.22 - py * p.z * 1.4, height, 10);
      ctx.globalAlpha = p.alpha * (0.55 + 0.45 * Math.sin(clock * p.freq * 3 + p.phase));
      ctx.drawImage(sprites.dust[p.tint], x - p.size, y - p.size, p.size * 2, p.size * 2);
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
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

    for (const l of lights) {
      const margin = l.size;
      const x = wrap(l.x * width + l.vx * clock - px * l.z * 2.4, width, margin);
      const y = wrap(l.y * height + l.vy * clock - scroll * l.z * 0.45 - py * l.z * 2.4, height, margin);
      ctx.globalAlpha = l.alpha * (0.7 + 0.3 * Math.sin(clock * 0.3 + l.phase));
      ctx.drawImage(sprites.lights[l.tint], x - l.size / 2, y - l.size / 2, l.size, l.size);
    }

    if (tier.meteors) drawMeteor(dt);
    ctx.globalAlpha = 1;

    const lx = -pointer.x * 16;
    const ly = -pointer.y * 12 - Math.min(scroll * 0.03, 50);
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
    nebula = NX.$('[data-nebula]');
    spiral = NX.$('[data-spiral]');
    ctx = canvas && canvas.getContext('2d');
    if (!ctx || !nebula || !spiral) return;

    try { clock = Number(sessionStorage.getItem('nx-clock')) || 0; } catch { /* storage blocked */ }
    const byTint = (tints, make) => Object.fromEntries(tints.map((rgb) => [rgb, make(rgb)]));
    sprites = {
      stars: byTint(STAR_TINTS.map(([rgb]) => rgb), (rgb) => glowSprite(rgb)),
      flares: byTint(FLARE_TINTS, flareSprite),
      dust: byTint(LIGHT_TINTS, (rgb) => glowSprite(rgb, 32)),
      lights: byTint(LIGHT_TINTS, lightSprite),
      meteors: METEOR_TINTS.map(meteorSprite),
    };
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
