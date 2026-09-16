<?php
declare(strict_types=1);

/**
 * Einmaliges CLI-Skript zum Anlegen des ersten Administrator-Kontos.
 * Aufruf: php bin/create_admin.php "Name" email@example.com
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausfuehrbar.\n");
}

if ($argc < 3) {
    fwrite(STDERR, "Verwendung: php bin/create_admin.php \"Name\" email@example.com\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

$name = $argv[1];
$email = $argv[2];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Ungueltige E-Mail-Adresse.\n");
    exit(1);
}

$existing = db()->prepare('SELECT id FROM users WHERE email = :email');
$existing->execute(['email' => $email]);
if ($existing->fetch()) {
    fwrite(STDERR, "Ein Nutzer mit dieser E-Mail existiert bereits.\n");
    exit(1);
}

$password = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 14);

$stmt = db()->prepare(
    'INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (:n, :e, :h, \'admin\', 1)'
);
$stmt->execute([
    'n' => $name,
    'e' => $email,
    'h' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Administrator angelegt.\n";
echo "E-Mail:    $email\n";
echo "Passwort:  $password\n";
echo "(Bitte nach dem ersten Login unter 'Profil' aendern.)\n";
