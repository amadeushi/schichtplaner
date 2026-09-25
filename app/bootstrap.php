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

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (bool)($config['session_secure_cookie'] ?? false),
]);
session_start();

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services/Mailer.php';
require_once __DIR__ . '/services/Webhook.php';
require_once __DIR__ . '/services/Notifier.php';
require_once __DIR__ . '/services/IcsBuilder.php';
require_once __DIR__ . '/services/SmsClient.php';
SmsClient::configure($config['sms'] ?? [], (string)($config['app_url'] ?? ''));

$pdo = db($config['db_path']);

if ($dbIsNew) {
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);
}
