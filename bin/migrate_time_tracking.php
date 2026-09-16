<?php
declare(strict_types=1);

/**
 * Einmalige Migration fuer bereits laufende Installationen: fuegt die
 * Zeiterfassung nachtraeglich zum Datenbankschema hinzu. Gefahrlos
 * mehrfach ausfuehrbar (prueft vor jeder Aenderung, ob sie schon
 * vorhanden ist). Aufruf: php bin/migrate_time_tracking.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausfuehrbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$hasColumn = false;
foreach ($pdo->query('PRAGMA table_info(users)') as $col) {
    if ($col['name'] === 'time_tracking_enabled') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "Spalte 'time_tracking_enabled' existiert bereits.\n";
} else {
    $pdo->exec('ALTER TABLE users ADD COLUMN time_tracking_enabled INTEGER NOT NULL DEFAULT 0');
    echo "Spalte 'time_tracking_enabled' zu users hinzugefuegt.\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS time_entries (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        clock_in    TEXT NOT NULL,
        clock_out   TEXT,
        note        TEXT,
        created_by  INTEGER REFERENCES users(id),
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_time_entries_user ON time_entries(user_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_time_entries_clockin ON time_entries(clock_in)');

echo "Tabelle 'time_entries' ist vorhanden.\n";
echo "Migration abgeschlossen.\n";
