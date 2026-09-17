-- Schichtplaner Datenbankschema (SQLite)
-- Anlegen mit: sqlite3 storage/database.sqlite < database/schema.sql

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('admin', 'employee')) DEFAULT 'employee',
    active        INTEGER NOT NULL DEFAULT 1,
    notify_email  INTEGER NOT NULL DEFAULT 1,
    must_change_password INTEGER NOT NULL DEFAULT 0,
    time_tracking_enabled INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS shifts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    title         TEXT NOT NULL,
    shift_date    TEXT NOT NULL,              -- YYYY-MM-DD
    start_time    TEXT NOT NULL,              -- HH:MM
    end_time      TEXT NOT NULL,              -- HH:MM
    location      TEXT,
    needed_count  INTEGER NOT NULL DEFAULT 1,
    notes         TEXT,
    status        TEXT NOT NULL CHECK (status IN ('open', 'filled', 'closed')) DEFAULT 'open',
    published_at  TEXT,                        -- NULL = Entwurf, für Mitarbeiter unsichtbar
    created_by    INTEGER NOT NULL REFERENCES users(id),
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS shift_applications (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    shift_id      INTEGER NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status        TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn')) DEFAULT 'pending',
    note          TEXT,
    applied_at    TEXT NOT NULL DEFAULT (datetime('now')),
    decided_at    TEXT,
    decided_by    INTEGER REFERENCES users(id),
    UNIQUE (shift_id, user_id)
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS time_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    clock_in    TEXT NOT NULL,              -- YYYY-MM-DD HH:MM:SS
    clock_out   TEXT,                       -- NULL solange noch eingestempelt
    note        TEXT,
    created_by  INTEGER REFERENCES users(id),
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL,
    ip_address  TEXT NOT NULL,
    success     INTEGER NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Sammelt, wer über eine noch unveröffentlichte Änderung an einer Schicht (Zu-/Abweisung,
-- Bewerbung angenommen) informiert werden muss. Wird beim nächsten Publish pro Nutzer zu
-- einer einzigen, unspezifischen Sammel-Mail geleert statt einzeln sofort zu verschicken.
CREATE TABLE IF NOT EXISTS pending_notifications (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    shift_id    INTEGER NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS notification_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type  TEXT NOT NULL,
    channel     TEXT NOT NULL CHECK (channel IN ('email', 'webhook')),
    recipient   TEXT,
    success     INTEGER NOT NULL,
    error       TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Admin-Überschreibung von Betreff/Text einzelner Mails (siehe Notifier::TEMPLATES für die
-- Standardwerte und verfügbaren Platzhalter). Leere/fehlende Zeile = Standard verwenden.
CREATE TABLE IF NOT EXISTS email_templates (
    template_key TEXT PRIMARY KEY,
    subject      TEXT,
    body         TEXT,
    updated_at   TEXT
);

CREATE INDEX IF NOT EXISTS idx_shifts_date ON shifts(shift_date);
CREATE INDEX IF NOT EXISTS idx_applications_shift ON shift_applications(shift_id);
CREATE INDEX IF NOT EXISTS idx_applications_user ON shift_applications(user_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_user ON time_entries(user_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_clockin ON time_entries(clock_in);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, created_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, created_at);

-- Default-Einstellungen
INSERT OR IGNORE INTO settings (key, value) VALUES ('webhook_url', '');
INSERT OR IGNORE INTO settings (key, value) VALUES ('webhook_enabled', '0');
INSERT OR IGNORE INTO settings (key, value) VALUES ('email_enabled', '1');
INSERT OR IGNORE INTO settings (key, value) VALUES ('app_name', 'Schichtplaner');
INSERT OR IGNORE INTO settings (key, value) VALUES ('notification_log_max_entries', '1000');
