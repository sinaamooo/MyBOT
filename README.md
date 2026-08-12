# Telegram Crypto Futures Signal Bot

A modular, async, production-oriented Telegram bot that scans USDT-M
Futures (MEXC by default - no geo-restrictions; KuCoin/Binance also
supported via config), scores LONG/SHORT setups with a weighted multi-indicator system,
computes ATR-based Stop Loss / Take Profit levels and a suggested leverage,
publishes clean signals to a Telegram channel, and then monitors the trade
automatically (TP1/TP2/TP3, Risk-Free, Trailing Stop, Stop Loss) - all
controllable from a full Telegram Admin Panel.

> ⚠️ **Educational / Signal Only.** This bot never places real orders. It
> only *suggests* leverage, entries and exits. Nothing here is financial
> advice, and no output should be read as a guarantee of profit. Manage your
> own risk. `TRADING_MODE` only supports `paper` (default) and
> `signal_only` - real ("live") order execution is intentionally not
> implemented.

## Architecture

```
Market Data (MEXC Futures, public) ──▶ Indicator Engine ──▶ Scoring Engine ──▶ Risk Engine
                                                                        │
                                                                        ▼
                                                                 Quality Gates
                                                          (score, RR, ADX, volume,
                                                           HTF confirm, duplicate,
                                                           cooldown, max active)
                                                                        │
                                                                        ▼
                                                              Scanner (every ~4 min)
                                                                        │
                                                                        ▼
                                                        Telegram Channel  ◀── Notification Service
                                                                        │
                                                                        ▼
                                                             Trade Monitor (price polling)
                                                                        │
                                                          TP1 / Risk-Free / TP2 / Trailing / TP3 / SL
                                                                        │
                                                                        ▼
                                                              Database + Statistics
```

Everything above is orchestrated by the Telegram **Admin Panel**, which can
start/stop the scanner, enable/disable signals, edit every threshold,
manage the symbol list, toggle indicators, edit the message template, run a
backtest, and review statistics/history/logs - all without touching code.

## Project structure

```
config.py                  # env-based Settings (secrets) + TRADING_DEFAULTS (editable via panel)
main.py                    # entrypoint - wires everything and starts polling

app/core/                  # database engine, ORM models, enums, logging setup
app/market/                # ccxt Futures exchange wrapper (MEXC default) + multi-timeframe data fetcher
app/analysis/               # indicators, trend, price action, S/R, market regime, scoring, risk
app/signals/                # signal generator, formatter, publisher, scanner, trade monitor, backtester
app/services/                # settings/signal/user/admin/symbol/log/statistics services (DB access layer)
app/bot/                    # aiogram bot: routers, middlewares, keyboards, FSM states, handlers/
```

Timeframe roles (see `config.py`): **4H = Trend, 1H = Confirmation, 15M =
Setup, 5M = Entry.** A signal only fires when the higher timeframes agree
with the lower ones - this is the "Higher Timeframe Confirmation" score
component and hard gate.

### Scoring engine (0-100 per side)

| Component | Default weight | Timeframe |
|---|---|---|
| Trend (EMA stack + ADX) | 20 | 4H |
| EMA confirmation | 15 | 15M |
| RSI confirmation | 15 | 15M |
| MACD | 10 | 15M |
| ADX strength | 10 | 15M |
| Volume confirmation | 10 | 5M |
| Price action (candlestick/breakout) | 10 | 5M |
| Higher-timeframe confirmation | 10 | 1H |

`score >= min_score` (default 75) → **SIGNAL**, `watchlist_min_score <=
score < min_score` (default 65-74) → **WATCHLIST** (logged only, never
published), otherwise **NO SIGNAL**. All weights/thresholds are editable
live from Settings → Scoring & Thresholds.

On top of the score, a signal must also pass **hard AND-gates** before it
can publish: ADX ≥ minimum, volume confirmation in the trade's direction,
1H trend agrees with the side, Risk:Reward ≥ minimum, no existing open
signal for that symbol, cooldown elapsed, and active/daily signal caps not
exceeded. Market regime (TRENDING/RANGING/HIGH_VOLATILITY/LOW_VOLATILITY)
further derates confidence in ranging markets and raises the bar in
high-volatility conditions.

## Requirements

- Python 3.11+
- A Telegram bot token (via [@BotFather](https://t.me/BotFather))
- A Telegram channel where the bot is an **admin** (so it can post)
- (Optional) Exchange API key/secret - not required for public market data

## Setup (Linux / macOS)

```bash
git clone <this-repo>
cd MyBOT
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# edit .env: TELEGRAM_BOT_TOKEN, TELEGRAM_CHANNEL_ID, ADMIN_IDS, ...
python main.py
```

## Setup (Windows 11)

```powershell
git clone <this-repo>
cd MyBOT
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
notepad .env
python main.py
```

### Filling in `.env`

1. **TELEGRAM_BOT_TOKEN** - from @BotFather (`/newbot`).
2. **TELEGRAM_CHANNEL_ID** - add the bot as admin in your channel, then get
   the channel ID (e.g. via @userinfobot or the Bot API `getUpdates` after
   posting once) - looks like `-100xxxxxxxxxx`.
3. **ADMIN_IDS** - your numeric Telegram user ID(s), comma-separated. Only
   these users (or ones added later via the Admins panel) can open `/admin`.
4. **EXCHANGE_ID** - which ccxt futures exchange to use. Defaults to `mexc`
   (public API has no geo-restrictions, so it works from regions where
   Binance is blocked, e.g. Iran). Other supported values: `kucoinfutures`,
   `binanceusdm`. **EXCHANGE_API_KEY / EXCHANGE_API_SECRET** are optional -
   the bot works on public market data alone.
5. **DATABASE_URL** - defaults to a local SQLite file
   (`sqlite+aiosqlite:///./data/bot.db`). Swap for a `postgresql+asyncpg://...`
   URL later if you need Postgres - the ORM layer (SQLAlchemy async) already
   supports it, just `pip install asyncpg` and change the URL.
6. **TRADING_MODE** - `paper` (default) or `signal_only`. There is no `live`
   mode; this bot never executes real orders.

**Security:** `.env` is git-ignored. Never commit tokens/keys. The bot's
Admin Panel is only reachable by the Telegram user IDs in `ADMIN_IDS` (env)
or added later via **Admins** in the panel.

## Running

```bash
python main.py
```

On first boot the bot seeds the default symbol list (see `config.py`),
creates the SQLite database/tables, and starts the Trade Monitor. The
Scanner itself starts **paused** until you press **▶️ Start Scanner** in
`/admin` → Dashboard (or `/start_scanner`) - or it auto-resumes on restart
if it was left running.

### Docker

```bash
docker compose up -d --build
```

Data and logs persist in `./data` and `./logs` via the compose volumes.

### Linux VPS with systemd

Create `/etc/systemd/system/cryptobot.service`:

```ini
[Unit]
Description=Telegram Crypto Signal Bot
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/MyBOT
ExecStart=/opt/MyBOT/venv/bin/python main.py
Restart=always
RestartSec=5
EnvironmentFile=/opt/MyBOT/.env

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cryptobot
sudo journalctl -u cryptobot -f
```

### Or just `tmux`/`screen`

```bash
tmux new -s cryptobot
source venv/bin/activate
python main.py
# Ctrl+B, D to detach
```

## Admin Panel

Send `/admin` (only works for configured admins) to open:

- **🖥 Dashboard** - bot/scanner status, last/next scan, full stats, Start/Pause/Restart/Stop
- **📡 Signal Control** - enable/disable auto-signals, Manual LONG/SHORT, Send Test Signal
- **🪙 Symbols** - enable/disable/add/remove watch-list symbols
- **📊 Indicators** - toggle EMA/RSI/MACD/ADX/Volume/Bollinger on or off
- **⚙️ Settings** - Scoring & Thresholds, Risk & Leverage, Scanner & Cooldown, Monitoring - every number in `TRADING_DEFAULTS` is editable here
- **📈 Statistics** - today + all-time win rate, avg R:R, avg score, TP/SL/Risk-Free counts
- **📋 Active Signals** / **🗂 Signal History** - view details, cancel or manually close a trade
- **📝 Message Templates** - edit the exact text/placeholders used for channel posts
- **🧪 Backtest** - replay the live strategy over N days of history for a symbol
- **👥 Users** / **🔐 Admins** - who has talked to the bot, who can manage it
- **📜 Logs** - latest events (also written to `logs/bot.log` and `logs/signals.log`)

Public commands (anyone): `/start`, `/help`, `/status`, `/signals`, `/stats`.
Admin commands: `/admin`, `/start_scanner`, `/stop_scanner`, `/test_signal`.

## Test scenario (end to end)

1. `python main.py` - bot starts, DB tables are created, Trade Monitor starts.
2. `/admin` → 📡 Signal Control → 🧪 Send Test Signal - confirms Telegram
   connectivity, message formatting, buttons, and DB writes without waiting
   for a real setup.
3. `/admin` → 🖥 Dashboard → ▶️ Start Scanner - the scanner now runs every
   `scan_interval_seconds` (default 240s).
4. Each cycle: fetch market data → compute indicators (4H/1H/15M/5M) →
   score LONG & SHORT → market regime check → hard quality gates → risk
   engine (ATR-based SL/TP, R:R) → duplicate/cooldown/max-active checks →
   publish, or do nothing.
5. On a qualifying signal, it's saved to the `signals` table and posted to
   the channel with Entry/SL/TP1-3/Leverage/R:R/Trend.
6. The Trade Monitor polls price every `monitor_interval_seconds` (default
   30s) for every open signal:
   - Price reaches TP1 → "TP1 HIT" posted, `tp1_hit_at` set; if Risk-Free
     or Trailing Stop is enabled, SL moves to Entry and "RISK FREE" is posted.
   - Price reaches TP2 → "TP2 HIT" posted; if Trailing Stop is enabled, SL
     moves to TP1.
   - Price reaches TP3 → "TP3 HIT - Trade Completed" posted, signal closed.
   - Price hits the (possibly moved) Stop Loss at any point → "STOP LOSS
     HIT - Trade Closed" posted, signal closed.
7. `signal_service.compute_result()` classifies the close as WIN/LOSS/BREAKEVEN
   and stores `profit_percent`; `app/services/statistics.py` rolls today's
   numbers into `system_stats`.
8. `/admin` → 📈 Statistics reflects the new counts and win rate immediately.

## Extending later

- **Top 30/50 by volume**: `app/services/symbol_service.py` + a small ccxt
  `fetch_tickers()` sort-by-`quoteVolume` job would populate the Symbols
  table automatically - the rest of the pipeline is symbol-count agnostic.
- **AI layer** (`app/services/ai_service.py`): three no-op functions are
  already wired as extension points (market summary, signal explanation,
  confidence context) - the rules-based scoring engine stays the sole
  decision-maker by design.
- **News filter** (`app/analysis/market_regime.py::news_risk_flag`): stub
  that returns `False`; wire a real news/economic-calendar source and flip
  `news_filter_enabled` in Settings when ready.
- **PostgreSQL**: change `DATABASE_URL` to an `asyncpg` URL; the schema is
  created via `SQLAlchemy`'s `Base.metadata.create_all`, no code changes
  needed elsewhere.

## Disclaimer

This project generates and publishes **educational trading signals only**.
It does not execute real trades, does not manage real funds, and makes no
promise of profit. Futures trading is highly risky, especially at 15-25x
leverage. Always do your own research and manage risk carefully.
