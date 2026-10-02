<?php
declare(strict_types=1);

/**
 * Minimale Test-Suite ohne Abhängigkeiten (kein Composer/PHPUnit - siehe CLAUDE.md).
 * Aufruf: php tests/run.php   (Exit-Code 1 bei Fehlern, damit die CI daran scheitert)
 *
 * Deckt gezielt die Fehlerklassen ab, die bereits einmal in Produktion bzw. im Review
 * aufgetaucht sind: GSM-7/Unicode in SMS, Telefonnummern-Validierung, Escaping in
 * Bestätigungsdialogen.
 */

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/SmsClient.php';

date_default_timezone_set('Europe/Berlin');

$failures = [];
$count = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures[] = $name . ($detail !== '' ? ' - ' . $detail : '');
    }
}

function same(string $name, mixed $expected, mixed $actual): void
{
    check($name, $expected === $actual, 'erwartet ' . var_export($expected, true) . ', erhalten ' . var_export($actual, true));
}

/** Nur Zeichen des GSM-7-Basiszeichensatzes (strenger als nötig: Erweiterungszeichen wie € { } gelten als Fehler). */
function isGsm7Basic(string $text): bool
{
    $allowed = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    foreach (mb_str_split($text) as $ch) {
        if (mb_strpos($allowed, $ch) === false) {
            return false;
        }
    }
    return true;
}

// --- e(): Escaping ---
same('e() escaped beide Anführungszeichen', '&quot;x&quot; &#039;y&#039;', e('"x" \'y\''));
same('e() akzeptiert null', '', e(null));

// --- publishConfirmMessage() ---
same('Publish-Text: nur Entwürfe', '1 Entwurf jetzt an betroffene Mitarbeiter senden?', publishConfirmMessage(1, 0));
same('Publish-Text: beide Teile im Plural', '3 Entwürfe und 2 ausstehende Benachrichtigungen jetzt an betroffene Mitarbeiter senden?', publishConfirmMessage(3, 2));

// --- formatDurationHm() ---
same('Dauer 1:12', '1:12 Std.', formatDurationHm(72 * 60));
same('Dauer negativ wird 0', '0:00 Std.', formatDurationHm(-5));

// --- normalizePhone() ---
same('AT national', '+436641234567', normalizePhone('0664 1234567'));
same('AT international mit überflüssiger 0', '+436641234567', normalizePhone('+43 0664 1234567'));
same('DE Mobil', '+4915510442489', normalizePhone('+49 15510 442489'));
same('DE Mobil mit unsichtbaren Formatzeichen (U+202A/U+202C)', '+4915510442489', normalizePhone("\u{202A}+49 15510 442489\u{202C}"));
same('DE Festnetz wird abgelehnt', null, normalizePhone('+49 30 1234567'));
same('Text wird abgelehnt', null, normalizePhone('abc'));
same('Leerstring wird abgelehnt', null, normalizePhone(''));
same('Ohne Landesbezug wird abgelehnt', null, normalizePhone('664 1234567'));

// --- gsmSafe() und SMS-Texte: ein einziges Nicht-GSM-Zeichen erzwingt Unicode (70 statt 160 Zeichen) ---
check('gsmSafe ersetzt Gedankenstrich/Anführungszeichen/Auslassungspunkte', isGsm7Basic(gsmSafe('A – B „C“ ‘D’ …')));

$shift = ['title' => 'Küche – Spätschicht „Nord“ …', 'shift_date' => '2026-10-02', 'start_time' => '10:00', 'end_time' => '18:00'];
$urgent = smsUrgentNotice($shift, 'https://schichtplaner.amds.at');
check('Rundruf-SMS ist GSM-7', isGsm7Basic($urgent), $urgent);
check('Rundruf-SMS höchstens 160 Zeichen', mb_strlen($urgent) <= 160, (string)mb_strlen($urgent));

$longShift = $shift;
$longShift['title'] = str_repeat('Sehr langer Schichttitel ', 20);
$urgentLong = smsUrgentNotice($longShift, 'https://schichtplaner.amds.at');
check('Rundruf-SMS mit langem Titel höchstens 160 Zeichen', mb_strlen($urgentLong) <= 160, (string)mb_strlen($urgentLong));
check('Rundruf-SMS mit langem Titel ist GSM-7', isGsm7Basic($urgentLong));

$broadcast = smsBroadcastNotice('Heute geschlossen – bitte „nicht“ kommen … 😀', 'https://schichtplaner.amds.at');
check('Mitteilungs-SMS ist GSM-7 (inkl. Emoji-Entfernung)', isGsm7Basic($broadcast), $broadcast);
check('Mitteilungs-SMS höchstens 160 Zeichen', mb_strlen($broadcast) <= 160, (string)mb_strlen($broadcast));

// --- XSS-Regression: kein interpoliertes PHP in einem onsubmit-confirm('...')-Handler ---
// (Stored XSS: htmlspecialchars schützt das Attribut, aber der Browser dekodiert Entities vor
// der JS-Ausführung. Stattdessen data-confirm verwenden, siehe partials/header.php.)
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../public', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    foreach (file($file->getPathname()) as $i => $line) {
        if (preg_match('/on(submit|click|change)\s*=\s*"[^"]*\b(confirm|alert)\([^)]*<\?/', $line)) {
            check('Kein PHP-Wert in Inline-Handler: ' . basename($file->getPathname()) . ':' . ($i + 1), false, trim($line));
        }
    }
}
check('XSS-Regression-Scan lief', true);

// --- Ergebnis ---
if ($failures) {
    fwrite(STDERR, count($failures) . ' von ' . $count . " Prüfungen fehlgeschlagen:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "OK: $count Prüfungen bestanden\n";
