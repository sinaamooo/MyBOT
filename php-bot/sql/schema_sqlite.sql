-- SQLite schema (default database - see src/Database.php).
-- Auto-applied on first run, nothing to do here manually. Kept as a plain
-- .sql file (rather than inlined PHP) so it's easy to read/audit.

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  telegram_id INTEGER NOT NULL UNIQUE,
  username TEXT,
  first_name TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_seen_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  telegram_id INTEGER NOT NULL UNIQUE,
  label TEXT,
  added_by INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS symbols (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  symbol TEXT NOT NULL UNIQUE,
  enabled INTEGER NOT NULL DEFAULT 1,
  added_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  `key` TEXT NOT NULL UNIQUE,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  level TEXT NOT NULL,
  source TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_logs_created_at ON logs (created_at);

CREATE TABLE IF NOT EXISTS signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  symbol TEXT NOT NULL,
  side TEXT NOT NULL,
  entry REAL NOT NULL,
  stop_loss REAL NOT NULL,
  current_sl REAL,
  tp1 REAL NOT NULL,
  tp2 REAL NOT NULL,
  tp3 REAL NOT NULL,
  leverage INTEGER NOT NULL,
  score REAL NOT NULL,
  risk_score TEXT NOT NULL,
  rr REAL NOT NULL,
  timeframe TEXT NOT NULL,
  trend TEXT,
  regime TEXT,
  reasons TEXT,
  is_manual INTEGER NOT NULL DEFAULT 0,
  is_test INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'PENDING',
  channel_message_id INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  tp1_hit_at TEXT,
  tp2_hit_at TEXT,
  tp3_hit_at TEXT,
  risk_free_at TEXT,
  closed_at TEXT,
  close_price REAL,
  result TEXT,
  profit_percent REAL
);
CREATE INDEX IF NOT EXISTS idx_signals_symbol ON signals (symbol);
CREATE INDEX IF NOT EXISTS idx_signals_status ON signals (status);
CREATE INDEX IF NOT EXISTS idx_signals_created_at ON signals (created_at);

CREATE TABLE IF NOT EXISTS signal_updates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_id INTEGER NOT NULL REFERENCES signals(id) ON DELETE CASCADE,
  update_type TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_signal_updates_signal_id ON signal_updates (signal_id);

CREATE TABLE IF NOT EXISTS system_stats (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  `date` TEXT NOT NULL UNIQUE,
  total_signals INTEGER NOT NULL DEFAULT 0,
  tp1_count INTEGER NOT NULL DEFAULT 0,
  tp2_count INTEGER NOT NULL DEFAULT 0,
  tp3_count INTEGER NOT NULL DEFAULT 0,
  stopped_count INTEGER NOT NULL DEFAULT 0,
  risk_free_count INTEGER NOT NULL DEFAULT 0,
  cancelled_count INTEGER NOT NULL DEFAULT 0,
  win_rate REAL NOT NULL DEFAULT 0,
  avg_rr REAL NOT NULL DEFAULT 0,
  avg_score REAL NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admin_states (
  telegram_id INTEGER PRIMARY KEY,
  state TEXT NOT NULL,
  context TEXT,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
