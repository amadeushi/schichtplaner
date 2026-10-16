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

require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/password_reset.php';
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

// --- expandShiftDates(): "dieselbe Schicht an mehreren Tagen" ---
same('Ohne Wochentage nur das gewählte Datum', ['2026-10-14'], expandShiftDates('2026-10-14', [], 1));
same('Montag bis Sonntag der Woche des Datums (Datum ist ein Mittwoch)', [
    '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18',
], expandShiftDates('2026-10-14', [1, 2, 3, 4, 5, 6, 7], 1));
same('Nur Sa und So, unsortiert und mit Duplikat übergeben', ['2026-10-17', '2026-10-18'], expandShiftDates('2026-10-12', [7, 6, 7], 1));
same('Ungültige Wochentage werden ignoriert', ['2026-10-12'], expandShiftDates('2026-10-12', [0, 1, 8, -3], 1));
same('Wiederholung über Wochen', ['2026-10-12', '2026-10-19', '2026-10-26'], expandShiftDates('2026-10-12', [1], 3));
same('Mo bis So über 2 Wochen = 14 Tage', 14, count(expandShiftDates('2026-10-12', [1, 2, 3, 4, 5, 6, 7], 2)));
same('Sommerzeit-Umstellung (So 25.10.2026) verschiebt keinen Tag', [
    '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25',
], expandShiftDates('2026-10-21', [1, 2, 3, 4, 5, 6, 7], 1));
same('Wiederholung über die Umstellung hinweg bleibt auf dem Wochentag', ['2026-10-19', '2026-10-26', '2026-11-02'], expandShiftDates('2026-10-19', [1], 3));
same('Ungültiges Datum liefert nichts', [], expandShiftDates('kein datum', [1], 1));

// --- sessionLifetimeDays() ---
same('Session-Laufzeit: ohne Konfiguration 30 Tage', 30, sessionLifetimeDays(null));
same('Session-Laufzeit: konfigurierter Wert', 14, sessionLifetimeDays(14));
same('Session-Laufzeit: Zahl als String aus der Konfiguration', 90, sessionLifetimeDays('90'));
same('Session-Laufzeit: Unsinn fällt auf 30 zurück', 30, sessionLifetimeDays('viel'));
same('Session-Laufzeit: 0 oder negativ wird mindestens 1 Tag', 1, sessionLifetimeDays(0));
same('Session-Laufzeit: gedeckelt auf 365 Tage', 365, sessionLifetimeDays(5000));

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

// --- "Passwort vergessen": SMS-Text, Passwortregel, Session-Fingerabdruck ---
$resetUrl = 'https://schichtplaner.amds.at/reset_password.php?t=' . str_repeat('a', 32);
$resetSms = smsPasswordReset($resetUrl);
check('Reset-SMS passt in 160 Zeichen', $resetSms !== null && mb_strlen($resetSms) <= 160, (string)mb_strlen((string)$resetSms));
check('Reset-SMS ist GSM-7 und enthält den vollständigen Link', $resetSms !== null && isGsm7Basic($resetSms) && str_contains($resetSms, $resetUrl), (string)$resetSms);
same('Reset-SMS: zu langer Link ergibt null statt eines abgeschnittenen Links', null, smsPasswordReset('https://example.test/' . str_repeat('x', 200)));
same('Passwortregel: zu kurz', 'Das neue Passwort muss mindestens 8 Zeichen haben.', passwordPolicyError('kurz', 'kurz'));
same('Passwortregel: stimmt nicht überein', 'Die beiden Passwörter stimmen nicht überein.', passwordPolicyError('langgenug1', 'langgenug2'));
same('Passwortregel: gültig', null, passwordPolicyError('langgenug1', 'langgenug1'));
check('Fingerabdruck ist stabil und je Hash verschieden', passwordFingerprint('hash-a') === passwordFingerprint('hash-a') && passwordFingerprint('hash-a') !== passwordFingerprint('hash-b'));
check('Fingerabdruck verrät nicht den Hash', !str_contains(passwordFingerprint('hash-a'), 'hash-a') && strlen(passwordFingerprint('hash-a')) === 16);

// --- "Passwort vergessen": Token-Logik gegen eine frische In-Memory-Datenbank mit dem echten Schema ---
$pdo = db(':memory:');
$pdo->exec((string)file_get_contents(__DIR__ . '/../database/schema.sql'));
$mkUser = function (string $email, bool $active = true) use ($pdo): int {
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active, must_change_password) VALUES (:n, :e, :h, \'employee\', :a, 1)')
        ->execute(['n' => 'Test', 'e' => $email, 'h' => password_hash('altes-passwort', PASSWORD_DEFAULT), 'a' => $active ? 1 : 0]);
    return (int)$pdo->lastInsertId();
};
$annaId = $mkUser('anna@example.test');
$bobId = $mkUser('bob@example.test');
$mkUser('weg@example.test', false);
$countRequests = fn(): int => (int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn();

same('Reset: unbekannte Adresse liefert null', null, createPasswordReset('niemand@example.test', '10.0.0.1'));
same('Reset: auch die unbekannte Anfrage wird protokolliert (für das Limit)', 1, $countRequests());
same('Reset: deaktiviertes Konto liefert null', null, createPasswordReset('weg@example.test', '10.0.0.1'));
same('Reset: leere Adresse liefert null und wird nicht protokolliert', null, createPasswordReset('   ', '10.0.0.1'));

$first = createPasswordReset('anna@example.test', '10.0.0.1');
check('Reset: bekanntes Konto liefert Token (32 Hex-Zeichen = 128 Bit)', $first !== null && preg_match('/^[a-f0-9]{32}$/', $first['token']) === 1);
check('Reset: in der Datenbank steht nur der Hash, nie das Token', (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash = '" . $first['token'] . "'")->fetchColumn() === 0
    && (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash = '" . passwordResetTokenHash($first['token']) . "'")->fetchColumn() === 1);
check('Reset: gültiges Token wird erkannt', (findValidPasswordReset($first['token'])['email'] ?? null) === 'anna@example.test');
same('Reset: falsches Token wird abgelehnt', null, findValidPasswordReset(str_repeat('0', 32)));
same('Reset: Token mit falschem Format wird abgelehnt', null, findValidPasswordReset("' OR 1=1 --"));

$second = createPasswordReset('ANNA@Example.Test', '10.0.0.1');
check('Reset: Groß-/Kleinschreibung der Adresse ist egal', $second !== null);
same('Reset: eine neue Anfrage entwertet den älteren Link', null, findValidPasswordReset($first['token']));
check('Reset: der neue Link gilt', findValidPasswordReset($second['token']) !== null);

$third = createPasswordReset('anna@example.test', '10.0.0.1');
check('Reset: dritte Anfrage je Stunde geht noch durch', $third !== null);
same('Reset: vierte Anfrage je Stunde und Adresse wird begrenzt', null, createPasswordReset('anna@example.test', '10.0.0.1'));
check('Reset: Limit gilt je Adresse, eine andere Adresse ist nicht betroffen', createPasswordReset('bob@example.test', '10.0.0.2') !== null);

for ($i = 0; $i < PASSWORD_RESET_MAX_PER_IP; $i++) {
    createPasswordReset("spam$i@example.test", '10.0.0.9');
}
same('Reset: Limit je IP greift auch für ein echtes Konto', null, createPasswordReset('bob@example.test', '10.0.0.9'));

$pdo->exec("UPDATE password_resets SET expires_at = datetime('now', '-1 minute')");
same('Reset: abgelaufener Link wird abgelehnt', null, findValidPasswordReset($third['token']));

$fresh = createPasswordReset('bob@example.test', '10.0.0.3');
$pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES ('bob@example.test', '10.0.0.3', 0), ('anna@example.test', '10.0.0.3', 0)")->execute();
$resetRow = findValidPasswordReset($fresh['token']);
check('Reset: frischer Link wird gefunden', $resetRow !== null);
$resetRow = $resetRow ?? ['reset_id' => 0, 'id' => $bobId, 'email' => 'bob@example.test'];
check('Reset: Abschluss mit gültigem Link gelingt', completePasswordReset($resetRow, 'ganz-neues-passwort') === true);
$bobRow = $pdo->query("SELECT * FROM users WHERE id = $bobId")->fetch();
check('Reset: neues Passwort gilt, das alte nicht mehr', password_verify('ganz-neues-passwort', $bobRow['password_hash']) && !password_verify('altes-passwort', $bobRow['password_hash']));
same('Reset: must_change_password wird zurückgesetzt', 0, (int)$bobRow['must_change_password']);
same('Reset: derselbe Link ist danach verbraucht', null, findValidPasswordReset($fresh['token']));
same('Reset: ein zweiter Abschluss mit demselben Link scheitert', false, completePasswordReset($resetRow, 'noch-ein-passwort'));
same('Reset: Fehlversuche des Kontos werden gelöscht (keine Sperre nach dem Reset)', 0, (int)$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE email = 'bob@example.test'")->fetchColumn());
same('Reset: Fehlversuche anderer Konten bleiben', 1, (int)$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE email = 'anna@example.test'")->fetchColumn());

// --- staffingGap(): unbesetzte Schichten hervorheben ("Mein Plan") ---
$gapNow = strtotime('2026-10-14 12:00');
$gapShift = fn(string $date, string $start, int $needed, int $approved) =>
    ['shift_date' => $date, 'start_time' => $start, 'needed_count' => $needed, 'approved_count' => $approved];
same('Gap: voll besetzt = null', null, staffingGap($gapShift('2026-10-15', '16:00', 2, 2), $gapNow));
same('Gap: überbesetzt = null', null, staffingGap($gapShift('2026-10-15', '16:00', 1, 2), $gapNow));
same('Gap: begonnene Schicht = null', null, staffingGap($gapShift('2026-10-14', '11:00', 2, 0), $gapNow));
same('Gap: Beginn genau jetzt = null', null, staffingGap($gapShift('2026-10-14', '12:00', 2, 0), $gapNow));
same('Gap: fehlende Zahl', 2, staffingGap($gapShift('2026-10-14', '16:00', 3, 1), $gapNow)['missing'] ?? null);
same('Gap: heute Abend ist dringend', true, staffingGap($gapShift('2026-10-14', '16:00', 1, 0), $gapNow)['urgent'] ?? null);
same('Gap: genau 48 Stunden ist noch dringend', true, staffingGap($gapShift('2026-10-16', '12:00', 1, 0), $gapNow)['urgent'] ?? null);
same('Gap: 48 Stunden plus eine Minute ist nicht dringend', false, staffingGap($gapShift('2026-10-16', '12:01', 1, 0), $gapNow)['urgent'] ?? null);
same('Gap: Text für eine fehlende Person', '1 fehlt', staffingGapLabel(1));
same('Gap: Text für mehrere', '3 fehlen', staffingGapLabel(3));
check('Gap: Vermerk ist nur bei dringend rot', str_contains(staffingGapBadgeHtml(['missing' => 1, 'urgent' => true]), 'urgent')
    && !str_contains(staffingGapBadgeHtml(['missing' => 1, 'urgent' => false]), 'urgent'));

// --- Feste Verfügbarkeit: Formular, Speichern, Abgleich mit einer Schicht ---
$parsed = parseAvailabilityInput([1 => 'none', 2 => 'from', 3 => 'any', 4 => 'quatsch'], [2 => '16:00']);
same('Verfügbarkeit: Formular ergibt nur Einschränkungen', [1, 2], array_keys($parsed['rows']));
same('Verfügbarkeit: "Erst ab" behält die Uhrzeit', '16:00', $parsed['rows'][2]['from_time'] ?? null);
same('Verfügbarkeit: unbekannter Wert gilt als jederzeit', false, isset($parsed['rows'][4]));
same('Verfügbarkeit: "Erst ab" ohne Uhrzeit ist ein Fehler mit Tagesname', 'Bitte für Dienstag eine Uhrzeit angeben.',
    parseAvailabilityInput([2 => 'from'], [])['error']);
check('Verfügbarkeit: ungültige Uhrzeit wird abgelehnt', parseAvailabilityInput([5 => 'from'], [5 => '25:00'])['error'] !== null);
$availRows = [2 => ['mode' => 'from', 'from_time' => '16:00'], 4 => ['mode' => 'none', 'from_time' => null]];
same('Verfügbarkeit: Dienstag 15:59 liegt vor "erst ab 16:00"', 'from', availabilityConflict($availRows, '2026-10-20', '15:59')['mode'] ?? null);
same('Verfügbarkeit: Dienstag 16:00 ist erlaubt', null, availabilityConflict($availRows, '2026-10-20', '16:00'));
same('Verfügbarkeit: Montag ist ohne Eintrag frei', null, availabilityConflict($availRows, '2026-10-19', '08:00'));
same('Verfügbarkeit: Donnerstag "gar nicht"', 'none', availabilityConflict($availRows, '2026-10-22', '23:00')['mode'] ?? null);
same('Verfügbarkeit: Hinweis "erst ab"', 'erst ab 16:00', availabilityHint(['mode' => 'from', 'from_time' => '16:00']));
same('Verfügbarkeit: Hinweis "gar nicht"', 'nicht verfügbar', availabilityHint(['mode' => 'none', 'from_time' => null]));
same('Verfügbarkeit: Erklärung für den Mitarbeiter', 'Dein Eintrag für Dienstag: erst ab 16:00',
    availabilityNote(['mode' => 'from', 'from_time' => '16:00'], '2026-10-20'));
saveUserAvailability($annaId, $parsed['rows']);
same('Verfügbarkeit: gespeichert und gelesen', [1, 2], array_keys(userAvailability($annaId)));
saveUserAvailability($annaId, [3 => ['mode' => 'none', 'from_time' => null]]);
same('Verfügbarkeit: Speichern ersetzt alles', [3], array_keys(userAvailability($annaId)));
saveUserAvailability($annaId, []);
same('Verfügbarkeit: leer = jederzeit', [], userAvailability($annaId));

// --- Rundruf "Personal gesucht": wer ihn bekommt ---
$mkShift = function (string $date, string $start, string $end) use ($pdo, $annaId): array {
    $pdo->prepare("INSERT INTO shifts (title, shift_date, start_time, end_time, needed_count, created_by) VALUES ('T', :d, :s, :e, 3, :c)")
        ->execute(['d' => $date, 's' => $start, 'e' => $end, 'c' => $annaId]);
    return ['id' => (int)$pdo->lastInsertId(), 'shift_date' => $date, 'start_time' => $start, 'end_time' => $end];
};
$assign = fn(int $shiftId, int $userId, string $status) =>
    $pdo->prepare('INSERT INTO shift_applications (shift_id, user_id, status) VALUES (:s, :u, :st)')->execute(['s' => $shiftId, 'u' => $userId, 'st' => $status]);
$target = $mkShift('2026-10-20', '16:00', '22:00');
$cara = $mkUser('rc-frei@example.test');
$dan = $mkUser('rc-ueberlappt@example.test');
$emil = $mkUser('rc-morgens@example.test');
$fritz = $mkUser('rc-beworben@example.test');
$gina = $mkUser('rc-urlaub@example.test');
$hans = $mkUser('rc-nicht-verfuegbar@example.test');
$ida = $mkUser('rc-nachtschicht@example.test');
$assign($mkShift('2026-10-20', '20:00', '23:00')['id'], $dan, 'approved');
$assign($mkShift('2026-10-20', '08:00', '12:00')['id'], $emil, 'approved');
$assign($mkShift('2026-10-20', '12:00', '16:00')['id'], $cara, 'pending');
$assign($mkShift('2026-10-20', '21:00', '02:00')['id'], $ida, 'approved');
$assign($target['id'], $fritz, 'pending');
$pdo->prepare("INSERT INTO absences (user_id, start_date, end_date, type, created_by) VALUES (:u, '2026-10-19', '2026-10-21', 'sonstiges', :u)")->execute(['u' => $gina]);
saveUserAvailability($hans, [2 => ['mode' => 'none', 'from_time' => null]]);
$recipientMails = array_values(array_filter(
    array_column(urgentReminderRecipients($target, '2026-10-16'), 'email'),
    fn($m) => str_starts_with($m, 'rc-')
));
sort($recipientMails);
same('Rundruf: geht an alle außer beworben, überlappend, abwesend', [
    'rc-frei@example.test', 'rc-morgens@example.test', 'rc-nicht-verfuegbar@example.test',
], $recipientMails);

// --- purgeAnonymousSessions(): Bot-Sitzungen nicht 30 Tage liegen lassen, Anmeldungen schon ---
$sessDir = sys_get_temp_dir() . '/sp_sess_test_' . bin2hex(random_bytes(4));
mkdir($sessDir);
$sessNow = time();
$mkSess = function (string $name, string $content, int $ageSeconds) use ($sessDir, $sessNow): void {
    file_put_contents($sessDir . '/' . $name, $content);
    touch($sessDir . '/' . $name, $sessNow - $ageSeconds);
};
$mkSess('sess_leer_alt', '', 7200);
$mkSess('sess_leer_frisch', '', 600);
$mkSess('sess_csrf_alt', 'csrf_token|s:32:"abcdefabcdefabcdefabcdefabcdefab";', 2 * 86400);
$mkSess('sess_csrf_frisch', 'csrf_token|s:32:"abcdefabcdefabcdefabcdefabcdefab";', 7200);
$mkSess('sess_login_alt', 'csrf_token|s:3:"abc";user_id|i:5;pw_fp|s:3:"abc";', 29 * 86400);
$mkSess('hinweis.txt', '', 30 * 86400);
same('Sitzungen: gelöscht werden leere (>1 h) und anonyme (>1 Tag)', 2, purgeAnonymousSessions($sessDir, $sessNow));
same('Sitzungen: übrig bleiben frische, angemeldete und fremde Dateien',
    ['hinweis.txt', 'sess_csrf_frisch', 'sess_leer_frisch', 'sess_login_alt'],
    (function () use ($sessDir) { $l = array_values(array_diff(scandir($sessDir), ['.', '..'])); sort($l); return $l; })());
$mkSess('sess_leer_a', '', 7200);
$mkSess('sess_leer_b', '', 7200);
$mkSess('sess_leer_c', '', 7200);
same('Sitzungen: Obergrenze je Lauf', 2, purgeAnonymousSessions($sessDir, $sessNow, 2));
same('Sitzungen: fehlendes Verzeichnis ist kein Fehler', 0, purgeAnonymousSessions($sessDir . '/gibt-es-nicht'));
array_map('unlink', glob($sessDir . '/*') ?: []);
rmdir($sessDir);

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
