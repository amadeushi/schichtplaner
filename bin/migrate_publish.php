<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: fügt den
 * Entwurf/Veröffentlichen-Workflow nachträglich zum Datenbankschema hinzu.
 * Gefahrlos mehrfach ausführbar. Aufruf: php bin/migrate_publish.php
 *
 * Wichtig: bereits bestehende Schichten werden als sofort veröffentlicht
 * markiert (published_at = created_at), damit sie für Mitarbeiter nicht
 * plötzlich aus dem Plan verschwinden - nur künftig neu angelegte oder
 * bearbeitete Schichten starten als Entwurf.
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$hasColumn = false;
foreach ($pdo->query('PRAGMA table_info(shifts)') as $col) {
    if ($col['name'] === 'published_at') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "Spalte 'published_at' existiert bereits.\n";
} else {
    $pdo->exec('ALTER TABLE shifts ADD COLUMN published_at TEXT');
    $backfilled = $pdo->exec('UPDATE shifts SET published_at = created_at WHERE published_at IS NULL');
    echo "Spalte 'published_at' zu shifts hinzugefügt, $backfilled bestehende Schicht(en) als veröffentlicht markiert.\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS pending_notifications (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        shift_id    INTEGER NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);

echo "Tabelle 'pending_notifications' ist vorhanden.\n";
echo "Migration abgeschlossen.\n";
