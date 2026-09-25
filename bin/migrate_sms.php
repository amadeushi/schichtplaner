<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen (SMS-Benachrichtigungen):
 *  - users.phone / users.notify_sms
 *  - Tabelle sms_outbox
 *  - notification_log.channel darf zusätzlich 'sms' sein (SQLite kann einen CHECK nicht ändern,
 *    daher wird die Tabelle in einer Transaktion neu aufgebaut - Inhalt bleibt erhalten).
 * Gefahrlos mehrfach ausführbar. Aufruf (als Besitzer der Datenbank, auf dem Pi www-data):
 *   sudo -u www-data php bin/migrate_sms.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
if (!in_array('phone', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN phone TEXT');
    echo "Spalte 'phone' zu users hinzugefügt.\n";
}
if (!in_array('notify_sms', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN notify_sms INTEGER NOT NULL DEFAULT 1');
    echo "Spalte 'notify_sms' zu users hinzugefügt.\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS sms_outbox (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id          INTEGER REFERENCES users(id) ON DELETE SET NULL,
        phone            TEXT NOT NULL,
        event_type       TEXT NOT NULL,
        text             TEXT NOT NULL,
        idempotency_key  TEXT NOT NULL UNIQUE,
        status           TEXT NOT NULL CHECK (status IN ('queued', 'accepted', 'failed')) DEFAULT 'queued',
        attempts         INTEGER NOT NULL DEFAULT 0,
        next_attempt_at  TEXT NOT NULL DEFAULT (datetime('now')),
        api_message_id   TEXT,
        last_error       TEXT,
        created_at       TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at       TEXT
    )"
);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_sms_outbox_due ON sms_outbox(status, next_attempt_at)');
echo "Tabelle 'sms_outbox' ist vorhanden.\n";

$logSql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'notification_log'")->fetchColumn();
if ($logSql !== '' && !str_contains($logSql, "'sms'")) {
    $pdo->beginTransaction();
    try {
        $pdo->exec(
            "CREATE TABLE notification_log_new (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type  TEXT NOT NULL,
                channel     TEXT NOT NULL CHECK (channel IN ('email', 'webhook', 'sms')),
                recipient   TEXT,
                success     INTEGER NOT NULL,
                error       TEXT,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            )"
        );
        $pdo->exec(
            'INSERT INTO notification_log_new (id, event_type, channel, recipient, success, error, created_at)
             SELECT id, event_type, channel, recipient, success, error, created_at FROM notification_log'
        );
        $pdo->exec('DROP TABLE notification_log');
        $pdo->exec('ALTER TABLE notification_log_new RENAME TO notification_log');
        $pdo->commit();
        echo "notification_log erlaubt jetzt den Kanal 'sms' (Einträge übernommen).\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, 'Umbau von notification_log fehlgeschlagen, zurückgerollt: ' . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "notification_log erlaubt den Kanal 'sms' bereits.\n";
}

echo "Migration abgeschlossen.\n";
