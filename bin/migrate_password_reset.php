<?php
declare(strict_types=1);

/**
 * Einmalige Migration für bereits laufende Installationen: legt die Tabellen für "Passwort
 * vergessen" an (password_resets, password_reset_requests). Gefahrlos mehrfach ausführbar.
 * Aufruf: php bin/migrate_password_reset.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_resets (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash  TEXT NOT NULL UNIQUE,
        expires_at  TEXT NOT NULL,
        used_at     TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);
echo "Tabelle 'password_resets' ist vorhanden.\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_reset_requests (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        email       TEXT NOT NULL,
        ip_address  TEXT NOT NULL,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )"
);
echo "Tabelle 'password_reset_requests' ist vorhanden.\n";

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_requests_email ON password_reset_requests(email, created_at)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_requests_ip ON password_reset_requests(ip_address, created_at)');
echo "Indizes sind vorhanden.\n";

echo "Migration abgeschlossen.\n";
