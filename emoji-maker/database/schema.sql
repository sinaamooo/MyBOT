-- ==========================================================================
-- Telegram Premium Emoji Maker - database schema
-- Compatible with MySQL 5.7+ / 8.x and MariaDB 10.3+
-- Charset utf8mb4 (Persian text + emoji). JSON payloads are stored in
-- LONGTEXT/MEDIUMTEXT columns for maximum hosting compatibility.
-- Import: mysql -u USER -p DBNAME < database/schema.sql   (or phpMyAdmin)
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------
-- Telegram users of the bot
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_id`        BIGINT NOT NULL,
  `username`           VARCHAR(64) NULL,
  `first_name`         VARCHAR(128) NULL,
  `last_name`          VARCHAR(128) NULL,
  `language_code`      VARCHAR(16) NULL,
  `role`               ENUM('user','vip','admin') NOT NULL DEFAULT 'user',
  `permissions`        TEXT NULL COMMENT 'JSON: {"tgs":true,"publish":true,"batch":true,"max_batch":100}',
  `is_blocked`         TINYINT(1) NOT NULL DEFAULT 0,
  `block_reason`       VARCHAR(255) NULL,
  `daily_limit`        INT NULL COMMENT 'NULL = global default',
  `max_batch`          INT NULL COMMENT 'NULL = global default',
  `credits`            INT NOT NULL DEFAULT 0,
  `total_generated`    INT UNSIGNED NOT NULL DEFAULT 0,
  `state`              VARCHAR(48) NOT NULL DEFAULT 'idle',
  `state_data`         TEXT NULL COMMENT 'JSON conversation data',
  `current_project_id` BIGINT UNSIGNED NULL,
  `last_seen_at`       DATETIME NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_telegram_id` (`telegram_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_blocked` (`is_blocked`),
  KEY `idx_users_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Design projects (one design + its animation/batch configuration)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `title`         VARCHAR(128) NULL,
  `input_type`    ENUM('image','text','combo','template') NOT NULL DEFAULT 'text',
  `status`        ENUM('draft','previewing','previewed','queued','generating','completed','failed','archived') NOT NULL DEFAULT 'draft',
  `config`        LONGTEXT NULL COMMENT 'JSON design + animation + batch settings',
  `template_id`   INT UNSIGNED NULL,
  `last_job_id`   BIGINT UNSIGNED NULL,
  `outputs_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projects_user` (`user_id`, `deleted_at`, `updated_at`),
  KEY `idx_projects_status` (`status`),
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Uploaded images / logos (re-encoded, stored outside the web root)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_assets` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`              BIGINT UNSIGNED NOT NULL,
  `user_id`                 BIGINT UNSIGNED NOT NULL,
  `type`                    ENUM('image','logo','font','other') NOT NULL DEFAULT 'image',
  `original_name`           VARCHAR(255) NULL,
  `mime`                    VARCHAR(64) NOT NULL,
  `size_bytes`              INT UNSIGNED NOT NULL DEFAULT 0,
  `width`                   INT UNSIGNED NOT NULL DEFAULT 0,
  `height`                  INT UNSIGNED NOT NULL DEFAULT 0,
  `has_alpha`               TINYINT(1) NOT NULL DEFAULT 0,
  `path`                    VARCHAR(255) NOT NULL COMMENT 'relative to storage root',
  `sha1`                    CHAR(40) NOT NULL,
  `telegram_file_id`        VARCHAR(255) NULL,
  `telegram_file_unique_id` VARCHAR(64) NULL,
  `meta`                    TEXT NULL,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assets_project` (`project_id`),
  KEY `idx_assets_user` (`user_id`),
  KEY `idx_assets_sha1` (`sha1`),
  CONSTRAINT `fk_assets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Job queue (database based - Redis is not required)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generation_jobs` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             BIGINT UNSIGNED NULL,
  `project_id`          BIGINT UNSIGNED NULL,
  `type`                ENUM('preview','generate','publish','deliver','update','maintenance') NOT NULL,
  `status`              ENUM('queued','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `priority`            TINYINT NOT NULL DEFAULT 5 COMMENT 'higher runs first',
  `payload`             LONGTEXT NULL COMMENT 'JSON snapshot of the configuration',
  `result`              LONGTEXT NULL COMMENT 'JSON result summary',
  `idempotency_key`     CHAR(40) NULL,
  `total_items`         INT UNSIGNED NOT NULL DEFAULT 0,
  `done_items`          INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_items`        INT UNSIGNED NOT NULL DEFAULT 0,
  `progress`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `progress_text`       VARCHAR(255) NULL,
  `attempts`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `cancel_requested`    TINYINT(1) NOT NULL DEFAULT 0,
  `available_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_by`           VARCHAR(64) NULL,
  `locked_at`           DATETIME NULL,
  `heartbeat_at`        DATETIME NULL,
  `started_at`          DATETIME NULL,
  `finished_at`         DATETIME NULL,
  `error_message`       VARCHAR(1000) NULL,
  `chat_id`             BIGINT NULL,
  `progress_message_id` BIGINT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobs_idempotency` (`idempotency_key`),
  KEY `idx_jobs_claim` (`status`, `available_at`, `priority`),
  KEY `idx_jobs_user_status` (`user_id`, `status`),
  KEY `idx_jobs_project` (`project_id`),
  KEY `idx_jobs_locked` (`locked_by`),
  KEY `idx_jobs_created` (`created_at`),
  CONSTRAINT `fk_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jobs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Telegram sticker sets (custom emoji packs) published by the bot
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sticker_sets` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `project_id`       BIGINT UNSIGNED NULL,
  `job_id`           BIGINT UNSIGNED NULL,
  `name`             VARCHAR(64) NOT NULL,
  `title`            VARCHAR(64) NOT NULL,
  `sticker_type`     ENUM('custom_emoji','regular') NOT NULL DEFAULT 'custom_emoji',
  `needs_repainting` TINYINT(1) NOT NULL DEFAULT 0,
  `item_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `status`           ENUM('pending','creating','published','failed','deleted') NOT NULL DEFAULT 'pending',
  `last_error`       VARCHAR(1000) NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sticker_sets_name` (`name`),
  KEY `idx_sticker_sets_user` (`user_id`),
  CONSTRAINT `fk_sets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Every produced file: main emoji (webm/tgs/png), previews, grids, zips
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generated_files` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id`             BIGINT UNSIGNED NOT NULL,
  `project_id`         BIGINT UNSIGNED NULL,
  `user_id`            BIGINT UNSIGNED NULL,
  `item_index`         INT NOT NULL DEFAULT 0 COMMENT '-1 = job level file (zip, grid)',
  `kind`               ENUM('main','preview','thumb','grid','sheet','zip','static') NOT NULL,
  `format`             VARCHAR(8) NOT NULL COMMENT 'webm,tgs,png,webp,mp4,gif,zip',
  `path`               VARCHAR(255) NOT NULL COMMENT 'relative to storage root',
  `filename`           VARCHAR(160) NOT NULL,
  `mime`               VARCHAR(64) NOT NULL,
  `size_bytes`         INT UNSIGNED NOT NULL DEFAULT 0,
  `width`              INT UNSIGNED NOT NULL DEFAULT 0,
  `height`             INT UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms`        INT UNSIGNED NOT NULL DEFAULT 0,
  `fps`                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `frame_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `sha1`               CHAR(40) NULL,
  `variant`            TEXT NULL COMMENT 'JSON: effects, colors and parameters of this item',
  `variant_hash`       CHAR(40) NULL,
  `validation_status`  ENUM('unchecked','valid','warning','invalid') NOT NULL DEFAULT 'unchecked',
  `validation_report`  TEXT NULL,
  `is_selected`        TINYINT(1) NOT NULL DEFAULT 1,
  `telegram_file_id`   VARCHAR(255) NULL,
  `custom_emoji_id`    VARCHAR(64) NULL,
  `sticker_set_id`     BIGINT UNSIGNED NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_files_job_item_kind` (`job_id`, `item_index`, `kind`, `format`),
  KEY `idx_files_user` (`user_id`, `created_at`),
  KEY `idx_files_project` (`project_id`),
  KEY `idx_files_kind` (`kind`, `deleted_at`),
  KEY `idx_files_validation` (`validation_status`),
  KEY `idx_files_variant` (`variant_hash`),
  KEY `idx_files_set` (`sticker_set_id`),
  CONSTRAINT `fk_files_job` FOREIGN KEY (`job_id`) REFERENCES `generation_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_set` FOREIGN KEY (`sticker_set_id`) REFERENCES `sticker_sets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Animation effects & combinations that users can pick
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `animation_presets` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(48) NOT NULL,
  `kind`         ENUM('effect','combo') NOT NULL DEFAULT 'effect',
  `effect_type`  VARCHAR(32) NULL COMMENT 'engine effect key for kind=effect',
  `name_fa`      VARCHAR(64) NOT NULL,
  `name_en`      VARCHAR(64) NOT NULL,
  `description`  VARCHAR(255) NULL,
  `icon`         VARCHAR(16) NULL,
  `params`       TEXT NULL COMMENT 'JSON default parameters (or list of effects for combos)',
  `cost_units`   SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'quota units consumed per output',
  `is_enabled`   TINYINT(1) NOT NULL DEFAULT 1,
  `is_premium`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'only vip/admin users',
  `sort_order`   INT NOT NULL DEFAULT 100,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_presets_slug` (`slug`),
  KEY `idx_presets_enabled` (`is_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Ready-made design templates (style + animation)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `design_templates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(48) NOT NULL,
  `name_fa`     VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NULL,
  `config`      TEXT NOT NULL COMMENT 'JSON partial project config',
  `is_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 100,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_templates_slug` (`slug`),
  KEY `idx_templates_enabled` (`is_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Fonts available to the renderer (TrueType files in resources/ or storage/fonts)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fonts` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(48) NOT NULL,
  `name`             VARCHAR(64) NOT NULL,
  `file`             VARCHAR(255) NOT NULL COMMENT 'resources/fonts/.. or fonts/.. (storage)',
  `supports_persian` TINYINT(1) NOT NULL DEFAULT 0,
  `is_enabled`       TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`       INT NOT NULL DEFAULT 100,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fonts_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Runtime settings editable from the admin panel
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_settings` (
  `key`         VARCHAR(64) NOT NULL,
  `value`       TEXT NULL,
  `type`        ENUM('string','int','float','bool','json','secret') NOT NULL DEFAULT 'string',
  `description` VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Admin panel accounts
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(64) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `display_name`    VARCHAR(128) NULL,
  `role`            ENUM('superadmin','admin','operator','viewer') NOT NULL DEFAULT 'admin',
  `permissions`     TEXT NULL COMMENT 'JSON list of extra permissions',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME NULL,
  `last_login_at`   DATETIME NULL,
  `last_login_ip`   VARCHAR(45) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Audit trail of admin and important user/system actions
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type`  ENUM('admin','user','system') NOT NULL,
  `actor_id`    BIGINT UNSIGNED NULL,
  `action`      VARCHAR(64) NOT NULL,
  `target_type` VARCHAR(32) NULL,
  `target_id`   BIGINT UNSIGNED NULL,
  `details`     TEXT NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_type`, `actor_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Received webhook updates (deduplication + optional async processing)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `telegram_updates` (
  `update_id`    BIGINT NOT NULL,
  `telegram_id`  BIGINT NULL,
  `type`         VARCHAR(32) NULL,
  `payload`      MEDIUMTEXT NULL,
  `status`       ENUM('received','processing','processed','failed','ignored') NOT NULL DEFAULT 'received',
  `error`        VARCHAR(500) NULL,
  `processed_at` DATETIME NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `idx_updates_status` (`status`, `created_at`),
  KEY `idx_updates_user` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Fixed-window rate limiting (bot messages, admin logins, downloads)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `bucket`       VARCHAR(120) NOT NULL,
  `window_start` DATETIME NOT NULL,
  `hits`         INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bucket`),
  KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Daily usage counters (quota enforcement)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usage_counters` (
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `day`         DATE NOT NULL,
  `units`       INT UNSIGNED NOT NULL DEFAULT 0,
  `outputs`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `day`),
  CONSTRAINT `fk_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Animated emoji templates (files in resources/templates or storage/templates);
-- this table only holds admin overrides and usage statistics.
CREATE TABLE IF NOT EXISTS `lottie_templates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(64) NOT NULL,
  `pack`        VARCHAR(48) NOT NULL,
  `name_fa`     VARCHAR(96) NOT NULL,
  `slots`       VARCHAR(32) NOT NULL DEFAULT 'main' COMMENT 'comma list of slot roles',
  `status`      ENUM('auto','reviewed','disabled') NOT NULL DEFAULT 'auto',
  `is_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 100,
  `uses`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lottie_templates_slug` (`slug`),
  KEY `idx_lottie_templates_pack` (`pack`, `is_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
