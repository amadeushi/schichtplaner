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
    calendar_token TEXT,                       -- unratbares Token für das persönliche Kalender-Abo (public/calendar_feed.php), lazy erzeugt
    phone         TEXT,                        -- Mobilnummer im E.164-Format (+436641234567) für SMS-Benachrichtigungen, NULL = keine
    notify_sms    INTEGER NOT NULL DEFAULT 1,  -- SMS nur, wenn zusätzlich phone gesetzt und das SMS-Gateway konfiguriert ist
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_calendar_token ON users(calendar_token);

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
    channel     TEXT NOT NULL CHECK (channel IN ('email', 'webhook', 'sms')),
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

-- Abwesenheiten (Urlaub/Krankheit/Sonstiges): von Mitarbeiter (für sich selbst) oder Admin
-- (für jeden) eintragbar. Kein Freigabe-Workflow - direkt wirksam, siehe isUserAbsentOn() in
-- app/helpers.php. Wirkt sich aus auf: Sichtbarkeit im Schichtplan/Kalender (admin/shifts.php,
-- admin/calendar.php) und Unterdrückung persönlicher Plan-Benachrichtigungen (Notifier).
CREATE TABLE IF NOT EXISTS absences (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    start_date  TEXT NOT NULL,              -- YYYY-MM-DD
    end_date    TEXT NOT NULL,              -- YYYY-MM-DD, >= start_date
    type        TEXT NOT NULL CHECK (type IN ('urlaub', 'krankheit', 'sonstiges')) DEFAULT 'urlaub',
    note        TEXT,
    created_by  INTEGER NOT NULL REFERENCES users(id),
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_absences_user ON absences(user_id);
CREATE INDEX IF NOT EXISTS idx_absences_dates ON absences(start_date, end_date);

-- SMS-Warteschlange (siehe app/services/SmsClient.php): jede SMS wird hier angelegt, sofort einmal
-- versucht und bei Gateway-Limit/-Ausfall (HTTP 429/503, Netzwerk) von bin/sms_flush.php mit
-- demselben Idempotency-Key erneut versucht. Nach 30 Tagen werden erledigte Einträge gelöscht.
CREATE TABLE IF NOT EXISTS sms_outbox (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER REFERENCES users(id) ON DELETE SET NULL,
    phone            TEXT NOT NULL,              -- E.164, Momentaufnahme zum Zeitpunkt des Ereignisses
    event_type       TEXT NOT NULL,
    text             TEXT NOT NULL,              -- Benachrichtigungstext (GSM-Zeichen, bis 160 Zeichen)
    idempotency_key  TEXT NOT NULL UNIQUE,
    status           TEXT NOT NULL CHECK (status IN ('queued', 'accepted', 'failed')) DEFAULT 'queued',
    attempts         INTEGER NOT NULL DEFAULT 0,
    next_attempt_at  TEXT NOT NULL DEFAULT (datetime('now')),
    api_message_id   TEXT,
    last_error       TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT
);
CREATE INDEX IF NOT EXISTS idx_sms_outbox_due ON sms_outbox(status, next_attempt_at);

CREATE INDEX IF NOT EXISTS idx_shifts_date ON shifts(shift_date);
CREATE INDEX IF NOT EXISTS idx_applications_shift ON shift_applications(shift_id);
CREATE INDEX IF NOT EXISTS idx_applications_user ON shift_applications(user_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_user ON time_entries(user_id);
CREATE INDEX IF NOT EXISTS idx_time_entries_clockin ON time_entries(clock_in);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, created_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, created_at);

-- "Passwort vergessen": Einmal-Link per E-Mail oder SMS. Gespeichert wird nur der SHA-256-Hash des
-- Tokens (der Link selbst steht nur in der Nachricht), gültig 30 Minuten und genau einmal.
CREATE TABLE IF NOT EXISTS password_resets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  TEXT NOT NULL UNIQUE,
    expires_at  TEXT NOT NULL,
    used_at     TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
-- Jede angenommene Anfrage (auch für unbekannte Adressen, damit sich daran nichts erkennen lässt)
-- für die Begrenzung je Adresse und je IP.
CREATE TABLE IF NOT EXISTS password_reset_requests (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL,
    ip_address  TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id);
CREATE INDEX IF NOT EXISTS idx_password_reset_requests_email ON password_reset_requests(email, created_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_requests_ip ON password_reset_requests(ip_address, created_at);

-- Feste Verfügbarkeit je Wochentag (nur Einschränkungen; keine Zeile = jederzeit). Reiner Hinweis
-- für den Admin bei der Planung und Dämpfung der "noch frei"-Markierung, nie eine Sperre.
-- weekday ist ISO (1 = Montag … 7 = Sonntag); 'from' = erst ab from_time, 'none' = gar nicht.
CREATE TABLE IF NOT EXISTS availability (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    weekday    INTEGER NOT NULL CHECK (weekday BETWEEN 1 AND 7),
    mode       TEXT NOT NULL CHECK (mode IN ('from', 'none')),
    from_time  TEXT,                           -- HH:MM, nur bei mode 'from'
    PRIMARY KEY (user_id, weekday)
);

-- Default-Einstellungen
INSERT OR IGNORE INTO settings (key, value) VALUES ('webhook_url', '');
INSERT OR IGNORE INTO settings (key, value) VALUES ('webhook_enabled', '0');
INSERT OR IGNORE INTO settings (key, value) VALUES ('email_enabled', '1');
INSERT OR IGNORE INTO settings (key, value) VALUES ('app_name', 'Schichtplaner');
INSERT OR IGNORE INTO settings (key, value) VALUES ('notification_log_max_entries', '1000');
