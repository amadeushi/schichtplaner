<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: fügt die Spalte für das
 * persönliche Kalender-Abo hinzu. Gefahrlos mehrfach ausführbar.
 * Aufruf: php bin/migrate_calendar_feed.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$hasColumn = false;
foreach ($pdo->query('PRAGMA table_info(users)') as $col) {
    if ($col['name'] === 'calendar_token') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "Spalte 'calendar_token' existiert bereits.\n";
} else {
    $pdo->exec('ALTER TABLE users ADD COLUMN calendar_token TEXT');
    echo "Spalte 'calendar_token' zu users hinzugefügt.\n";
}

$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_calendar_token ON users(calendar_token)');
echo "Index 'idx_users_calendar_token' ist vorhanden.\n";

echo "Migration abgeschlossen.\n";
