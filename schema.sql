-- MyBOT :: schema (SQLite)

CREATE TABLE IF NOT EXISTS users (
    tg_id       INTEGER PRIMARY KEY,
    username    TEXT,
    first_name  TEXT,
    last_name   TEXT,
    is_admin    INTEGER NOT NULL DEFAULT 0,
    subscribed  INTEGER NOT NULL DEFAULT 1,
    blocked     INTEGER NOT NULL DEFAULT 0,
    created_at  INTEGER NOT NULL,
    last_seen   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_users_audience ON users(subscribed, blocked);

CREATE TABLE IF NOT EXISTS targets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id    INTEGER NOT NULL UNIQUE,
    type       TEXT    NOT NULL DEFAULT 'group',
    title      TEXT,
    username   TEXT,
    thread_id  INTEGER,
    members    INTEGER NOT NULL DEFAULT 0,
    active     INTEGER NOT NULL DEFAULT 1,
    can_post   INTEGER NOT NULL DEFAULT 1,
    added_by   INTEGER,
    added_at   INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_targets_active ON targets(active, can_post);

CREATE TABLE IF NOT EXISTS banners (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    text            TEXT    NOT NULL DEFAULT '',
    entities        TEXT,
    media_type      TEXT,
    media_file_id   TEXT,
    buttons         TEXT,
    disable_preview INTEGER NOT NULL DEFAULT 0,
    protect_content INTEGER NOT NULL DEFAULT 0,
    quote_text      TEXT,
    quote_entities  TEXT,
    quote_position  INTEGER,
    created_by      INTEGER,
    created_at      INTEGER NOT NULL,
    updated_at      INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS campaigns (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    banner_id      INTEGER NOT NULL REFERENCES banners(id) ON DELETE CASCADE,
    audience       TEXT    NOT NULL,
    target_chat_id INTEGER,
    status         TEXT    NOT NULL DEFAULT 'queued',
    scheduled_at   INTEGER NOT NULL DEFAULT 0,
    started_at     INTEGER,
    finished_at    INTEGER,
    total          INTEGER NOT NULL DEFAULT 0,
    sent           INTEGER NOT NULL DEFAULT 0,
    failed         INTEGER NOT NULL DEFAULT 0,
    skipped        INTEGER NOT NULL DEFAULT 0,
    created_by     INTEGER,
    created_at     INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_campaigns_due ON campaigns(status, scheduled_at);

CREATE TABLE IF NOT EXISTS queue (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id         INTEGER NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    chat_id             INTEGER NOT NULL,
    thread_id           INTEGER,
    reply_to_message_id INTEGER,
    kind                TEXT    NOT NULL DEFAULT 'chat',
    status              TEXT    NOT NULL DEFAULT 'pending',
    attempts            INTEGER NOT NULL DEFAULT 0,
    next_attempt_at     INTEGER NOT NULL DEFAULT 0,
    message_id          INTEGER,
    error               TEXT,
    sent_at             INTEGER
);
CREATE INDEX IF NOT EXISTS idx_queue_pick     ON queue(status, next_attempt_at);
CREATE INDEX IF NOT EXISTS idx_queue_campaign ON queue(campaign_id, status);

CREATE TABLE IF NOT EXISTS activity (
    chat_id  INTEGER NOT NULL,
    user_id  INTEGER NOT NULL,
    day      TEXT    NOT NULL,
    messages INTEGER NOT NULL DEFAULT 0,
    name     TEXT,
    username TEXT,
    PRIMARY KEY (chat_id, user_id, day)
);
CREATE INDEX IF NOT EXISTS idx_activity_day ON activity(day);

CREATE TABLE IF NOT EXISTS states (
    tg_id      INTEGER PRIMARY KEY,
    name       TEXT    NOT NULL,
    payload    TEXT,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type        TEXT    NOT NULL,
    chat_id     INTEGER,
    campaign_id INTEGER,
    meta        TEXT,
    created_at  INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_events_type ON events(type, created_at);
