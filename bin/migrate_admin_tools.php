<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: fügt die
 * E-Mail-Vorlagen-Tabelle und die Protokoll-Aufbewahrungs-Einstellung
 * nachträglich zum Datenbankschema hinzu. Gefahrlos mehrfach ausführbar.
 * Aufruf: php bin/migrate_admin_tools.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS email_templates (
        template_key TEXT PRIMARY KEY,
        subject      TEXT,
        body         TEXT,
        updated_at   TEXT
    )"
);
echo "Tabelle 'email_templates' ist vorhanden.\n";

$pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:k, :v)')
    ->execute(['k' => 'notification_log_max_entries', 'v' => '1000']);
echo "Einstellung 'notification_log_max_entries' ist vorhanden (Standard: 1000 Einträge).\n";

echo "Migration abgeschlossen.\n";
