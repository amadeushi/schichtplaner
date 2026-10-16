<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: legt die Tabelle für die feste
 * Verfügbarkeit je Wochentag an (availability). Gefahrlos mehrfach ausführbar.
 * Aufruf: php bin/migrate_availability.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

db()->exec(
    "CREATE TABLE IF NOT EXISTS availability (
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        weekday    INTEGER NOT NULL CHECK (weekday BETWEEN 1 AND 7),
        mode       TEXT NOT NULL CHECK (mode IN ('from', 'none')),
        from_time  TEXT,
        PRIMARY KEY (user_id, weekday)
    )"
);
echo "Tabelle 'availability' ist vorhanden.\n";

echo "Migration abgeschlossen.\n";
