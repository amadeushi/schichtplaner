<?php
declare(strict_types=1);

/**
 * Interaktive SMTP-Einrichtung. Fragt Host/Port/Zugangsdaten ab (Passwort
 * maskiert), schreibt sie in app/config.php und verschickt zum Abschluss
 * eine Testmail. Aufruf: php bin/configure_smtp.php
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausfuehrbar.\n");
}

$configFile = __DIR__ . '/../app/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "app/config.php nicht gefunden. Bitte zuerst aus app/config.example.php erstellen.\n");
    exit(1);
}

function prompt(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? " [$default]" : '';
    fwrite(STDOUT, "$label$suffix: ");
    $line = trim((string)fgets(STDIN));
    return $line === '' ? $default : $line;
}

function promptPassword(string $label): string
{
    fwrite(STDOUT, "$label: ");
    shell_exec('stty -echo');
    $line = trim((string)fgets(STDIN));
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");
    return $line;
}

echo "SMTP-Einrichtung fuer den Schichtplaner\n";
echo "----------------------------------------\n";
echo "Das Passwort wird nicht auf dem Bildschirm angezeigt.\n\n";

$host = prompt('SMTP-Host (z.B. w0123456.kasserver.com)');
$port = (int)prompt('SMTP-Port', '587');
$encryption = prompt('Verschluesselung (tls/ssl)', 'tls');
$username = prompt('SMTP-Benutzername (meist die volle E-Mail-Adresse)');
$password = promptPassword('SMTP-Passwort / Postfach-Passwort');
$fromEmail = prompt('Absender-E-Mail', $username);
$fromName = prompt('Absender-Name', 'Schichtplaner');

if ($host === '' || $username === '' || $password === '') {
    fwrite(STDERR, "\nHost, Benutzername und Passwort duerfen nicht leer sein. Abgebrochen.\n");
    exit(1);
}

$smtpArray = [
    'enabled'    => true,
    'host'       => $host,
    'port'       => $port,
    'encryption' => $encryption,
    'username'   => $username,
    'password'   => $password,
    'from_email' => $fromEmail,
    'from_name'  => $fromName,
];

$exported = var_export($smtpArray, true);
$exported = preg_replace('/^array \(/', '[', $exported);
$exported = preg_replace('/\)$/', ']', $exported);

$content = file_get_contents($configFile);
$pattern = "/'smtp'\s*=>\s*\[.*?\n\s*\],\n/s";
if (!preg_match($pattern, $content)) {
    fwrite(STDERR, "\nKonnte den smtp-Block in app/config.php nicht finden. Bitte manuell bearbeiten.\n");
    exit(1);
}

$backupFile = $configFile . '.bak-' . date('YmdHis');
copy($configFile, $backupFile);

$newContent = preg_replace($pattern, "'smtp' => $exported,\n", $content, 1);
file_put_contents($configFile, $newContent);

echo "\nGespeichert (Backup der vorherigen Version: " . basename($backupFile) . ").\n";

$testEmail = prompt('An welche Adresse soll eine Testmail gesendet werden?', $fromEmail);

require __DIR__ . '/../app/services/Mailer.php';
$mailer = new Mailer($smtpArray);
$ok = $mailer->send(
    $testEmail,
    'Test',
    'Schichtplaner: SMTP-Test',
    "Wenn du diese Nachricht liest, funktioniert der E-Mail-Versand des Schichtplaners.\n\nGesendet ueber: $host:$port ($encryption)"
);

if ($ok) {
    echo "\nTestmail wurde erfolgreich gesendet an $testEmail. Bitte Posteingang (auch Spam-Ordner) pruefen.\n";
} else {
    echo "\nTestmail konnte NICHT gesendet werden. Die Zugangsdaten wurden trotzdem gespeichert.\n";
    echo "Fehlermeldung: " . ($mailer->getLastError() ?? 'unbekannt') . "\n";
    echo "Pruefe Host/Port/Benutzername/Passwort und fuehre das Skript einfach erneut aus.\n";
}
