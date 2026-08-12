# Telegram Crypto Futures Signal Bot (PHP / cPanel edition)

A full PHP port of the Python bot, purpose-built for shared hosting
(cPanel): **Telegram webhook** instead of long-polling, **MySQL** instead
of SQLite, **cron jobs** instead of a persistent background process, and
**MEXC Futures** (public REST via cURL, no SDK) instead of Binance - so it
works from hosts/regions where Binance's API is blocked. No Composer
dependencies; everything is plain PHP 8.1+.

> ⚠️ **Educational / Signal Only.** This bot never places real orders. It
> only *suggests* leverage, entries and exits. `TRADING_MODE` only supports
> `paper` (default) and `signal_only` - real order execution is
> intentionally not implemented.

## Why webhook + cron instead of a long-running process

Shared hosting generally does not let a script run forever in the
background (and even if it briefly does, it can get killed on the next
process sweep). PHP is a poor fit for a persistent event loop anyway - so
instead:

- **`public/webhook.php`** - Telegram calls this URL the instant a user
  sends a message or taps a button. Each request handles exactly one
  update and exits. This is what powers the whole Admin Panel.
- **`cron/scanner.php`** - run every ~4 minutes by a cPanel Cron Job. One
  invocation = one scan pass (Scan → Analyze → Score → Filter → Risk Check
  → Duplicate Check → Publish or do nothing), then exits.
- **`cron/monitor.php`** - run every ~1 minute by a cPanel Cron Job. One
  invocation checks every open signal's price and reacts to TP/SL/Risk-Free/Trailing.

"Start/Stop Scanner" in the panel doesn't start or kill a process (there
isn't one) - it flips a `scanner_running` flag in the database that
`cron/scanner.php` checks on every tick. Stopping takes effect on the next
cron run (within a few minutes), not instantly.

## Project structure

```
bootstrap.php              # autoloader + .env loading, included by every entrypoint
setup_webhook.php          # one-off CLI script: registers the Telegram webhook

public/webhook.php         # Telegram webhook endpoint (needs to be reachable over HTTPS)
cron/scanner.php           # cPanel Cron Job #1 (every ~4 min)
cron/monitor.php           # cPanel Cron Job #2 (every ~1 min)
cron/test_exchange.php     # manual connectivity check after deploy

sql/schema_sqlite.sql      # SQLite schema - auto-applied on first run (default database)
sql/schema.sql             # MySQL schema - only needed if DB_DRIVER=mysql (import once via phpMyAdmin)

src/Config.php             # .env loader + strategy defaults (mirrors config.py)
src/Database.php           # PDO/MySQL connection
src/Exchange/MexcClient.php        # cURL-only MEXC Futures public REST client
src/Market/MarketDataService.php   # multi-timeframe snapshot
src/Analysis/              # Indicators, Trend, PriceAction, SupportResistance, MarketRegime, Scoring, Risk
src/Signals/                # SignalGenerator, Formatter, Publisher, Scanner, Monitor, Backtester
src/Services/                # Settings/Signal/Symbol/User/Admin/Log/Statistics/AdminState (DB access layer)
src/Telegram/                # TelegramApi, Keyboards, BotContext, UpdateHandler, Handlers/
```

The **analysis engine** (indicators, multi-timeframe scoring, ATR risk
engine, market regime, support/resistance) is a line-for-line behavioral
port of the Python version - same weights, same thresholds, same
timeframe roles (4H = Trend, 1H = Confirmation, 15M = Setup, 5M = Entry).
See the Python README for the full scoring table; it applies unchanged here.

## Requirements

- PHP 8.1+ with `pdo_sqlite` (default database, on by default on virtually
  every host) and `curl` extensions
- A domain with **HTTPS** (cPanel's free AutoSSL/Let's Encrypt is enough) -
  required for the Telegram webhook
- SSH/Terminal access (to run the one-off setup scripts and cron)
- A Telegram bot token, and the bot added as **admin** in your channel

## Database: SQLite by default - nothing to create

Unlike a typical PHP app, this does **not** require creating a MySQL
database. By default (`DB_DRIVER=sqlite` in `.env`) it stores everything in
a single file, `data/bot.sqlite`, created automatically the first time the
app runs (webhook or cron, whichever fires first) - no phpMyAdmin, no
import step. Just make sure the `data/` folder is writable by PHP, which
it is by default since it's inside your own home directory.

If you'd rather use MySQL (e.g. you already run several sites off one
shared database, or expect heavy write volume), set `DB_DRIVER=mysql` and
fill in `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`; create the database via
cPanel → MySQL Databases and import `sql/schema.sql` via phpMyAdmin once.
Everything else about the bot behaves identically either way.

## Setup

### 1. Upload

Upload this whole folder to your hosting (e.g. `~/crypto-signal-bot`,
outside or inside `public_html` - see the webhook path note below).

### 2. `.env`

```bash
cd ~/crypto-signal-bot
cp .env.example .env
nano .env
```

Fill in:

```env
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHANNEL_ID=-100xxxxxxxxxx
ADMIN_IDS=your_numeric_telegram_id

TELEGRAM_WEBHOOK_SECRET=<run: php -r "echo bin2hex(random_bytes(20));">
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/crypto-signal-bot/public/webhook.php

DB_DRIVER=sqlite
DB_PATH=data/bot.sqlite

EXCHANGE_ID=mexc
```

(Leave the MySQL block below it untouched/empty unless you set `DB_DRIVER=mysql`.)

`EXCHANGE_API_KEY`/`EXCHANGE_API_SECRET` can stay empty - only public
market data is used. `.env` is not web-accessible as long as it sits
outside `public_html` (or you keep this whole project outside
`public_html` and only symlink/alias `public/` - see below).

### 3. Making `public/webhook.php` reachable over HTTPS

Two common options on cPanel:

- **Simplest**: put the whole `crypto-signal-bot` folder directly under
  `public_html/crypto-signal-bot/` (or straight in `public_html/` itself).
  Then the webhook URL is
  `https://yourdomain.com/crypto-signal-bot/public/webhook.php` (or
  `https://yourdomain.com/public/webhook.php` if uploaded to the
  `public_html` root). The included `.htaccess` files block direct web
  access to everything except `public/` - so `.env` and
  `data/bot.sqlite` (your signals/settings) aren't downloadable by anyone
  who guesses the URL, even though they're physically inside `public_html`.
  This relies on Apache/LiteSpeed honoring `.htaccess`, which is the
  default on essentially all cPanel hosts; if yours is one of the rare
  nginx-only setups, use the stricter option below instead.
- **Stricter**: put the project outside `public_html` (e.g.
  `~/crypto-signal-bot`) and create an **Addon Domain / Alias / Subdomain**
  in cPanel whose document root points straight at
  `~/crypto-signal-bot/public`. Then the webhook URL is just
  `https://bot.yourdomain.com/webhook.php`, and `.env`/`src/`/`sql/` are
  completely outside the web root (belt-and-suspenders on top of the `.htaccess`).

Either way, update `TELEGRAM_WEBHOOK_URL` in `.env` to match.

### 4. Register the webhook

```bash
php setup_webhook.php set
php setup_webhook.php info   # confirm Telegram accepted it
```

### 5. Test exchange connectivity

```bash
php cron/test_exchange.php BTCUSDT
```

This was written from MEXC's documented Contract API and could not be
exercised against the live API from the build environment - run this once
after deploying to confirm the endpoints/field names still match before
relying on it. If it fails, check `logs/bot.log` for the exact HTTP
error/response body.

### 6. Cron Jobs

cPanel → **Cron Jobs** → add two:

```
*/4 * * * *  /usr/local/bin/php /home/USER/crypto-signal-bot/cron/scanner.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1
*    *  *  *  *  /usr/local/bin/php /home/USER/crypto-signal-bot/cron/monitor.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1
```

(Find your PHP CLI binary path with `which php` over SSH - cPanel hosts
sometimes need `/usr/local/bin/ea-php81` or similar instead of `php`.)

### 7. Go

Message your bot: `/start`, then `/admin` to open the control panel. Press
**🖥 Dashboard → ▶️ Start Scanner**. Signals start appearing on the next
`cron/scanner.php` tick that finds a qualifying setup.

## Admin Panel

Identical feature set to the Python edition: Dashboard, Signal Control
(enable/disable, Manual LONG/SHORT, Test Signal), Symbols, Indicators,
Settings (every threshold/weight live-editable), Statistics, Active
Signals / History (view, cancel, close), Message Templates, Backtest,
Users, Admins, Logs.

Since PHP has no in-memory session between webhook requests, "waiting for
you to type a value" (editing a setting, adding a symbol, manual signal
symbol entry, etc.) is tracked in the `admin_states` table instead of
aiogram's FSM - functionally identical from the user's perspective.

Public commands: `/start /help /status /signals /stats`. Admin commands:
`/admin /start_scanner /stop_scanner /test_signal`.

## Test scenario

1. Fill `.env` (SQLite needs no import step), run `setup_webhook.php set`.
2. `/admin` → 📡 Signal Control → 🧪 Send Test Signal - confirms Telegram
   connectivity, formatting, buttons, and DB writes immediately (no cron wait).
3. `/admin` → 🖥 Dashboard → ▶️ Start Scanner.
4. Within ~4 minutes, `cron/scanner.php` fires: fetch MEXC data (4H/1H/15M/5M)
   → indicators → LONG/SHORT score → market regime → hard quality gates →
   ATR risk engine → duplicate/cooldown/max-active checks → publish, or do nothing.
5. `cron/monitor.php` fires every ~1 minute for any open signal: TP1 →
   (Risk-Free: SL→Entry) → TP2 → (Trailing: SL→TP1) → TP3 (closed, WIN) or
   Stop Loss hit (closed, LOSS/BREAKEVEN) at any point.
6. `/admin` → 📈 Statistics reflects the new numbers immediately (computed
   on demand from the `signals` table, also rolled up into `system_stats`).

## Notes on the MEXC client

`src/Exchange/MexcClient.php` talks directly to `https://contract.mexc.com`
public endpoints (kline, ticker, contract detail) via cURL - no SDK. The
ticker endpoint conveniently returns price, 24h change, quote volume,
funding rate, and open interest all in one call. Run
`php cron/test_exchange.php` after deploying (see step 5 above); if MEXC
has changed a field name or path since this was written, the error message
will show the raw HTTP response so it's a quick one-file fix in `MexcClient.php`.

## Extending

- **Different exchange**: KuCoin Futures' public API works similarly
  (`https://api-futures.kucoin.com`) - implement the same public methods
  (`getKlines`, `getTicker`, `getPricePrecision`) in a new class and swap it
  in `src/AppFactory.php`.
- **PostgreSQL/other DB**: `src/Database.php` is the only file that builds
  the PDO DSN - swap `mysql:` for another PDO driver there; all Services
  use portable parameterized SQL.

## Disclaimer

This project generates and publishes **educational trading signals only**.
It does not execute real trades, does not manage real funds, and makes no
promise of profit. Futures trading is highly risky, especially at 15-25x
leverage. Always do your own research and manage risk carefully.
