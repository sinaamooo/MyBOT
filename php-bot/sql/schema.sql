-- Telegram Crypto Futures Signal Bot - MySQL schema
-- Import this once via phpMyAdmin (or: mysql -u USER -p DBNAME < schema.sql)

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT NOT NULL,
  username VARCHAR(64) NULL,
  first_name VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_telegram_id (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT NOT NULL,
  label VARCHAR(128) NULL,
  added_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admins_telegram_id (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS symbols (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_symbols_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(128) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  level VARCHAR(16) NOT NULL,
  source VARCHAR(64) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS signals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(32) NOT NULL,
  side VARCHAR(8) NOT NULL,
  entry DOUBLE NOT NULL,
  stop_loss DOUBLE NOT NULL,
  current_sl DOUBLE NULL,
  tp1 DOUBLE NOT NULL,
  tp2 DOUBLE NOT NULL,
  tp3 DOUBLE NOT NULL,
  leverage INT NOT NULL,
  score DOUBLE NOT NULL,
  risk_score VARCHAR(16) NOT NULL,
  rr DOUBLE NOT NULL,
  timeframe VARCHAR(32) NOT NULL,
  trend VARCHAR(16) NULL,
  regime VARCHAR(24) NULL,
  reasons TEXT NULL,
  is_manual TINYINT(1) NOT NULL DEFAULT 0,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
  channel_message_id BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tp1_hit_at DATETIME NULL,
  tp2_hit_at DATETIME NULL,
  tp3_hit_at DATETIME NULL,
  risk_free_at DATETIME NULL,
  closed_at DATETIME NULL,
  close_price DOUBLE NULL,
  result VARCHAR(16) NULL,
  profit_percent DOUBLE NULL,
  KEY idx_signals_symbol (symbol),
  KEY idx_signals_status (status),
  KEY idx_signals_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS signal_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  signal_id INT NOT NULL,
  update_type VARCHAR(32) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_signal_updates_signal_id (signal_id),
  CONSTRAINT fk_signal_updates_signal FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `date` VARCHAR(10) NOT NULL,
  total_signals INT NOT NULL DEFAULT 0,
  tp1_count INT NOT NULL DEFAULT 0,
  tp2_count INT NOT NULL DEFAULT 0,
  tp3_count INT NOT NULL DEFAULT 0,
  stopped_count INT NOT NULL DEFAULT 0,
  risk_free_count INT NOT NULL DEFAULT 0,
  cancelled_count INT NOT NULL DEFAULT 0,
  win_rate DOUBLE NOT NULL DEFAULT 0,
  avg_rr DOUBLE NOT NULL DEFAULT 0,
  avg_score DOUBLE NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_system_stats_date (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Replaces aiogram's in-memory FSM: PHP webhook requests are stateless, so
-- "waiting for admin to type a value" state has to be persisted between
-- the request that asks the question and the request with the answer.
CREATE TABLE IF NOT EXISTS admin_states (
  telegram_id BIGINT NOT NULL PRIMARY KEY,
  state VARCHAR(64) NOT NULL,
  context TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
