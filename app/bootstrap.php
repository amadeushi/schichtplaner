<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('Konfiguration fehlt. Bitte app/config.example.php nach app/config.php kopieren und anpassen.');
}
$config = require $configFile;

date_default_timezone_set($config['timezone'] ?? 'Europe/Berlin');

// SQLite braucht ein beschreibbares Verzeichnis für die Datenbankdatei.
$dbDir = dirname($config['db_path']);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}
$dbIsNew = !file_exists($config['db_path']);

require_once __DIR__ . '/helpers.php';

// Anmeldung bleibt standardmäßig 30 Tage ab dem letzten Besuch bestehen. Vorher galt der PHP-
// Standard: ein Cookie ohne Laufzeit (weg beim Schließen des Browsers bzw. wenn das Handy die App
// aus dem Speicher wirft) und serverseitig nur 24 Minuten Inaktivität - das System-Aufräumen
// (phpsessionclean) löscht in /var/lib/php/sessions, einem Ordner, den sich alle PHP-Apps des
// Pi teilen und dessen Frist die App nicht ändern kann. Darum ein eigener Ordner in storage/
// (nicht per Web erreichbar), den die App selbst nach der gewünschten Frist aufräumt.
$sessionLifetime = sessionLifetimeDays($config['session_lifetime_days'] ?? null) * 86400;
$sessionSecure = (bool)($config['session_secure_cookie'] ?? false);
$sessionDir = $dbDir . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
}
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $sessionSecure,
]);
session_start();

// Sitzungen ohne Anmeldung (Bots, Scanner) nicht 30 Tage liegen lassen, siehe purgeAnonymousSessions().
// Wie PHPs eigener Aufräumlauf nur bei etwa jeder 100. Anfrage.
if (is_dir($sessionDir) && random_int(1, 100) === 1) {
    purgeAnonymousSessions($sessionDir);
}

// PHP verlängert den Ablauf eines dauerhaften Cookies bei Folgebesuchen nicht von selbst (nur
// beim Anlegen). Damit die Frist ab dem LETZTEN Besuch zählt, wird das Cookie hier erneuert -
// nur wenn der Browser es mitgeschickt hat, neue Sessions bekommen es schon von PHP.
if (isset($_COOKIE[session_name()]) && $_COOKIE[session_name()] === session_id()) {
    setcookie(session_name(), session_id(), [
        'expires'  => time() + $sessionLifetime,
        'path'     => '/',
        'secure'   => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/services/Mailer.php';
require_once __DIR__ . '/services/Webhook.php';
require_once __DIR__ . '/services/Notifier.php';
require_once __DIR__ . '/services/IcsBuilder.php';
require_once __DIR__ . '/services/SmsClient.php';
require_once __DIR__ . '/services/MailLayout.php';
SmsClient::configure($config['sms'] ?? [], (string)($config['app_url'] ?? ''));

$pdo = db($config['db_path']);

if ($dbIsNew) {
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);
}
