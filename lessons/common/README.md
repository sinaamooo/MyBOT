# Shared lesson toolkit

- `charts.js` — SVG candlestick engine (opaque colours only, so phone PDF viewers stay fast)
- `doc.js` — page layout, shared CSS and the Playwright PDF renderer. `render({ body })` accepts
  `body` as a function `(fit) => html`; charts placed with `fit(key, draw)` are measured first and
  redrawn at the exact size of their box.
- `team.js`, `team-qr.svg` — KABOSU TEAM name, channel link and QR code
- `fonts/` — Vazirmatn (OFL licence)
