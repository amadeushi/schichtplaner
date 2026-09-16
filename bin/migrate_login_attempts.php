<?php
declare(strict_types=1);

/**
 * Einmalige Migration fuer bereits laufende Installationen: fuegt die
 * Tabelle fuer Login-Versuche (Kontosperre) nachtraeglich hinzu.
 * Gefahrlos mehrfach ausfuehrbar. Aufruf: php bin/migrate_login_attempts.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausfuehrbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        email       TEXT NOT NULL,
        ip_address  TEXT NOT NULL,
        success     INTEGER NOT NULL,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, created_at)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, created_at)');

echo "Tabelle 'login_attempts' ist vorhanden.\n";
echo "Migration abgeschlossen.\n";
