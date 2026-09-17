<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: legt die absences-Tabelle für
 * Abwesenheiten (Urlaub/Krankheit/Sonstiges) an. Gefahrlos mehrfach ausführbar.
 * Aufruf: php bin/migrate_absences.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS absences (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        start_date  TEXT NOT NULL,
        end_date    TEXT NOT NULL,
        type        TEXT NOT NULL CHECK (type IN ('urlaub', 'krankheit', 'sonstiges')) DEFAULT 'urlaub',
        note        TEXT,
        created_by  INTEGER NOT NULL REFERENCES users(id),
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);
echo "Tabelle 'absences' ist vorhanden.\n";

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_absences_user ON absences(user_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_absences_dates ON absences(start_date, end_date)');
echo "Indizes für 'absences' sind vorhanden.\n";

echo "Migration abgeschlossen.\n";
