<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Bestätigungstext für "Woche veröffentlichen" - identisch in admin/shifts.php und
 * admin/calendar.php verwendet (beide posten an denselben Handler), vorher an beiden
 * Stellen dupliziert (siehe ecc:php-reviewer, 2026-10-02).
 */
function publishConfirmMessage(int $draftCount, int $pendingNotifyCount): string
{
    $parts = [];
    if ($draftCount > 0) {
        $parts[] = $draftCount . ($draftCount === 1 ? ' Entwurf' : ' Entwürfe');
    }
    if ($pendingNotifyCount > 0) {
        $parts[] = $pendingNotifyCount . ' ausstehende Benachrichtigung' . ($pendingNotifyCount === 1 ? '' : 'en');
    }
    return implode(' und ', $parts) . ' jetzt an betroffene Mitarbeiter senden?';
}

/**
 * Datumsliste für "Neue Schicht anlegen": ohne Wochentage nur $date selbst, mit Wochentagen
 * (1 = Montag ... 7 = Sonntag) jeder gewählte Tag der ISO-Woche, in der $date liegt. Beides
 * wird um $repeatWeeks Wochen wiederholt. Ergebnis: aufsteigend, ohne Duplikate, 'Y-m-d'.
 * Rechnet über Datumsstrings statt Sekunden-Offsets, damit die Sommerzeit-Umstellung
 * (letzter Oktober-/März-Sonntag) keinen Tag verschiebt.
 */
function expandShiftDates(string $date, array $weekdays, int $repeatWeeks): array
{
    $baseTs = strtotime($date);
    if ($baseTs === false) {
        return [];
    }
    $baseDate = date('Y-m-d', $baseTs);
    $weekdays = array_values(array_unique(array_filter(
        array_map('intval', $weekdays),
        fn(int $d): bool => $d >= 1 && $d <= 7
    )));
    sort($weekdays);

    $baseDates = [$baseDate];
    if ($weekdays) {
        $monday = date('Y-m-d', strtotime($baseDate . ' -' . ((int)date('N', $baseTs) - 1) . ' days'));
        $baseDates = array_map(fn(int $d): string => date('Y-m-d', strtotime($monday . ' +' . ($d - 1) . ' days')), $weekdays);
    }

    $dates = [];
    for ($i = 0; $i < max(1, $repeatWeeks); $i++) {
        foreach ($baseDates as $d) {
            $dates[] = date('Y-m-d', strtotime($d . " +{$i} week"));
        }
    }
    $dates = array_values(array_unique($dates));
    sort($dates);
    return $dates;
}

/**
 * SMS mit dem Link zum Zurücksetzen des Passworts. Nur GSM-7-Zeichen (ein einziges Sonderzeichen
 * würde die SMS auf 70 Zeichen Unicode drücken, siehe gsmSafe) und höchstens 160 Zeichen. Passt der Link
 * nicht hinein, gibt es null zurück - ein abgeschnittener Link wäre wertlos, der Aufrufer nimmt dann E-Mail.
 */
function smsPasswordReset(string $url): ?string
{
    $text = 'Schichtplaner: Passwort zuruecksetzen (' . PASSWORD_RESET_TTL_MINUTES . ' Min gueltig): ' . $url;
    return mb_strlen($text) <= 160 ? $text : null;
}

/** Session-Laufzeit in Tagen aus app/config.php ('session_lifetime_days'): Standard 30, sinnvoll begrenzt auf 1-365. */
function sessionLifetimeDays(mixed $configured): int
{
    $days = is_numeric($configured) ? (int)$configured : 30;
    return max(1, min(365, $days));
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function checkCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Ungültige Anfrage (CSRF-Token fehlt oder abgelaufen). Bitte Seite neu laden und erneut versuchen.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function formatDateDe(string $isoDate): string
{
    $ts = strtotime($isoDate);
    return $ts ? date('d.m.Y', $ts) : $isoDate;
}

function weekdayDe(string $isoDate): string
{
    $days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $ts = strtotime($isoDate);
    if (!$ts) {
        return '';
    }
    return $days[(int)date('N', $ts) - 1];
}

function statusLabelDe(string $status): string
{
    return match ($status) {
        'open' => 'Offen',
        'filled' => 'Besetzt',
        'closed' => 'Geschlossen',
        'pending' => 'Ausstehend',
        'approved' => 'Angenommen',
        'rejected' => 'Abgelehnt',
        'withdrawn' => 'Zurückgezogen',
        default => $status,
    };
}

function formatDurationHm(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%d:%02d Std.', $hours, $minutes);
}

/**
 * Zeigt einen von SQLite gespeicherten Zeitstempel (datetime('now') ist immer UTC) in der
 * Ortszeit der App (config 'timezone') an. Ohne Umrechnung stand im Benachrichtigungsprotokoll
 * die Uhrzeit im Sommer um zwei Stunden zurück. Zeiten, die PHP selbst mit date() schreibt
 * (z.B. time_entries), sind bereits Ortszeit und werden NICHT hierüber geleitet.
 */
function formatUtcLocal(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $ts = strtotime($utc . ' UTC');
    return $ts ? date('d.m.Y H:i:s', $ts) : $utc;
}

function monthNameDe(string $yearMonthDay): string
{
    $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $ts = strtotime($yearMonthDay);
    if (!$ts) {
        return '';
    }
    return $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function monthBounds(string $yearMonth): array
{
    $start = $yearMonth . '-01';
    $end = date('Y-m-d', strtotime($start . ' +1 month'));
    return [$start, $end];
}

/** Zieht eine Schicht zurück in den Entwurf, damit sie für Mitarbeiter unsichtbar wird,
 * bis der Admin sie erneut veröffentlicht (siehe admin/shifts.php, Aktion 'publish'). */
function markShiftUnpublished(int $shiftId): void
{
    db()->prepare('UPDATE shifts SET published_at = NULL WHERE id = :id')->execute(['id' => $shiftId]);
}

/** Merkt vor, dass $userId beim nächsten Publish über eine Änderung an $shiftId
 * informiert werden muss (Sammel-Mail ohne Schicht-Details). */
function queuePendingNotification(int $shiftId, int $userId): void
{
    db()->prepare('INSERT INTO pending_notifications (shift_id, user_id) VALUES (:s, :u)')
        ->execute(['s' => $shiftId, 'u' => $userId]);
}

/**
 * Entscheidet eine ausstehende Bewerbung (Annehmen/Ablehnen) - die eine gemeinsame
 * Entscheidungs-Logik für admin/shifts.php (Formular im Bon-Strang) UND admin/calendar.php
 * (Dialog direkt im Kalender-Bon), damit beide Oberflächen exakt dieselben Regeln anwenden
 * statt zweier Kopien, die mit der Zeit auseinanderlaufen könnten. Gibt ['ok' => bool,
 * 'message' => string] zurück statt selbst zu flashen/redirecten oder JSON auszugeben -
 * das bleibt Sache des jeweiligen Aufrufers (Redirect+Flash bzw. JSON-Antwort für fetch()).
 */
function decideApplication(int $appId, string $decision, int $adminUserId, array $smtpConfig): array
{
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Ungültige Entscheidung.'];
    }

    $stmt = db()->prepare(
        "SELECT sa.*, sh.needed_count,
            (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
         FROM shift_applications sa JOIN shifts sh ON sh.id = sa.shift_id
         WHERE sa.id = :id AND sa.status = 'pending'"
    );
    $stmt->execute(['id' => $appId]);
    $app = $stmt->fetch();

    if (!$app) {
        return ['ok' => false, 'message' => 'Bewerbung wurde bereits bearbeitet oder existiert nicht.'];
    }

    if ($decision === 'approved' && (int)$app['approved_count'] >= (int)$app['needed_count']) {
        return ['ok' => false, 'message' => 'Diese Schicht ist bereits voll besetzt.'];
    }

    db()->prepare('UPDATE shift_applications SET status = :s, decided_at = datetime(\'now\'), decided_by = :by WHERE id = :id')
        ->execute(['s' => $decision, 'by' => $adminUserId, 'id' => $appId]);

    $shiftStmt = db()->prepare('SELECT * FROM shifts WHERE id = :id');
    $shiftStmt->execute(['id' => $app['shift_id']]);
    $shift = $shiftStmt->fetch();

    $applicantStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $applicantStmt->execute(['id' => $app['user_id']]);
    $applicant = $applicantStmt->fetch();

    if ($decision === 'approved' && (int)$app['approved_count'] + 1 >= (int)$app['needed_count']) {
        db()->prepare("UPDATE shifts SET status = 'filled' WHERE id = :id")->execute(['id' => $shift['id']]);
    }

    if ($decision === 'approved') {
        // Eine Annahme ist eine finale Zuweisung - bündelt sich wie 'assign' in den
        // nächsten Publish statt sofort zu mailen.
        markShiftUnpublished($shift['id']);
        queuePendingNotification($shift['id'], $applicant['id']);
        return ['ok' => true, 'message' => 'Bewerbung wurde angenommen. Schicht ist wieder Entwurf, bis erneut veröffentlicht wird.'];
    }

    // Eine Ablehnung ist eine direkte, persönliche Antwort auf die eigene Bewerbung der
    // Person - bleibt sofort, damit sie zeitnah anderswo suchen kann.
    $notifier = new Notifier($smtpConfig);
    $notifier->applicationDecided($shift, $applicant, $decision);
    return ['ok' => true, 'message' => 'Bewerbung wurde ' . statusLabelDe($decision) . '.'];
}

/**
 * Liefert das persönliche Kalender-Token eines Nutzers, erzeugt es beim ersten Aufruf
 * (lazy statt bei jeder Kontoanlage - so bekommen auch längst bestehende Konten eins,
 * sobald sie die Kalender-Abo-Karte in profile.php zum ersten Mal öffnen). Kryptografisch
 * zufällig und lang genug, um als alleiniger Auth-Faktor für public/calendar_feed.php zu
 * dienen, da Kalender-Apps keine Login-Session nutzen können.
 */
function ensureCalendarToken(int $userId, ?string $existingToken): string
{
    if ($existingToken) {
        return $existingToken;
    }
    $token = bin2hex(random_bytes(24));
    db()->prepare('UPDATE users SET calendar_token = :t WHERE id = :id')->execute(['t' => $token, 'id' => $userId]);
    return $token;
}

function absenceTypeLabelDe(string $type): string
{
    return match ($type) {
        'urlaub' => 'Urlaub',
        'krankheit' => 'Krankheit',
        'sonstiges' => 'Sonstiges',
        default => $type,
    };
}

/** Ob $userId am Kalendertag $date (YYYY-MM-DD) eine eingetragene Abwesenheit hat. */
function isUserAbsentOn(int $userId, string $date): bool
{
    $stmt = db()->prepare('SELECT 1 FROM absences WHERE user_id = :u AND :d BETWEEN start_date AND end_date LIMIT 1');
    $stmt->execute(['u' => $userId, 'd' => $date]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Alle Abwesenheiten, die sich mit [$start, $end] überschneiden, gruppiert nach user_id -
 * ein Abruf für eine ganze Planungswoche statt einer Einzelabfrage pro Zelle/Tag
 * (admin/shifts.php, admin/calendar.php).
 */
function absencesOverlapping(string $start, string $end): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.name AS user_name FROM absences a JOIN users u ON u.id = a.user_id
         WHERE a.start_date <= :end AND a.end_date >= :start ORDER BY a.start_date'
    );
    $stmt->execute(['start' => $start, 'end' => $end]);
    $byUser = [];
    foreach ($stmt as $row) {
        $byUser[(int)$row['user_id']][] = $row;
    }
    return $byUser;
}

/**
 * Schichtzeit mit hervorgehobenem Beginn (siehe .shift-time in partials/header.php): große
 * Startzeit, dahinter klein "bis Ende". Der Beginn ist die eine Zahl, die Mitarbeiter am Bon
 * suchen - "10:00–18:00" als gleich gewichteter Block ließ sie Anfang und Ende verwechseln.
 * $compact für Tabellen/Listen, wo die große Variante zu viel Höhe kostet.
 */
function shiftTimeHtml(string $start, string $end, bool $compact = false): string
{
    return '<span class="shift-time' . ($compact ? ' compact' : '') . '">'
        . '<span class="shift-start">' . e($start) . '</span>'
        . '<span class="shift-end">bis ' . e($end) . '</span></span>';
}

/** Ab so vielen Stunden vor Schichtbeginn gilt eine unbesetzte Schicht als dringend (Stempel-Rot, siehe DESIGN.md "The One Stamp Rule"). */
const STAFFING_URGENT_HOURS = 48;

/**
 * Wie viele Leute einer noch nicht begonnenen Schicht fehlen, und ob das schon dringend ist.
 * $shift braucht shift_date, start_time, needed_count und approved_count (bestätigte
 * Bewerbungen/Zuweisungen). null = nichts zu melden: voll besetzt oder die Schicht hat schon
 * begonnen (Vergangenes wird nicht hervorgehoben). Gemeinsame Quelle für Mitarbeiter-Plan
 * ("Mein Plan") und Admin ("Personal gesucht"), damit die 48-Stunden-Grenze nicht auseinanderläuft.
 */
function staffingGap(array $shift, ?int $now = null): ?array
{
    $now ??= time();
    $missing = (int)$shift['needed_count'] - (int)$shift['approved_count'];
    $startTs = strtotime($shift['shift_date'] . ' ' . $shift['start_time']);
    if ($missing < 1 || $startTs === false || $startTs <= $now) {
        return null;
    }
    return ['missing' => $missing, 'urgent' => ($startTs - $now) / 3600 <= STAFFING_URGENT_HOURS];
}

function staffingGapLabel(int $missing): string
{
    return $missing === 1 ? '1 fehlt' : $missing . ' fehlen';
}

/** Vermerk "1 fehlt" bzw. "2 fehlen" für $gap aus staffingGap(); dringend = Stempel-Zustand. */
function staffingGapBadgeHtml(array $gap): string
{
    return '<span class="badge gap-count' . ($gap['urgent'] ? ' urgent' : '') . '">'
        . e(staffingGapLabel((int)$gap['missing'])) . '</span>';
}

/**
 * Räumt Sitzungsdateien auf, die nie zu einer Anmeldung geführt haben. Bots und Scanner legen mit
 * jeder Anfrage eine Sitzung an (bei Weitem die meisten Dateien); bei 30 Tagen Lebensdauer für
 * angemeldete Sitzungen würden die sonst ebenso lange liegen bleiben und das Verzeichnis füllen,
 * das PHP bei jedem Aufräumlauf komplett durchsieht. Gelöscht werden: leere Dateien (die Anfrage hat
 * die Sitzung nie benutzt) nach einer Stunde, und Dateien ohne Anmeldung (kein user_id, z. B. nur
 * ein CSRF-Token der Login-Seite) nach einem Tag. Angemeldete Sitzungen bleiben unberührt - deren
 * Ablauf regelt PHP über gc_maxlifetime. $maxDeletes begrenzt die Arbeit einer einzelnen Anfrage.
 * Gibt die Zahl der gelöschten Dateien zurück.
 */
function purgeAnonymousSessions(string $dir, ?int $now = null, int $maxDeletes = 2000): int
{
    $now ??= time();
    if (!is_dir($dir)) {
        return 0;
    }
    $deleted = 0;
    foreach (new DirectoryIterator($dir) as $file) {
        if ($deleted >= $maxDeletes) {
            break;
        }
        if (!$file->isFile() || strncmp($file->getFilename(), 'sess_', 5) !== 0) {
            continue;
        }
        $age = $now - $file->getMTime();
        $path = $file->getPathname();
        $stale = ($file->getSize() === 0 && $age > 3600)
            || ($age > 86400 && !str_contains((string)@file_get_contents($path), 'user_id|'));
        if ($stale && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

/** Wochentagsnamen nach ISO-Nummer (1 = Montag … 7 = Sonntag). */
function weekdayNamesDe(): array
{
    return [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
}

/**
 * Formularwerte der festen Verfügbarkeit ($modes[1..7] = any|from|none, $times[1..7] = HH:MM) in die
 * zu speichernden Einschränkungen übersetzen. 'any' (jederzeit) ergibt keine Zeile. Fehlt bei
 * "Ab Uhrzeit" eine gültige Uhrzeit, kommt eine Fehlermeldung statt stillem Raten zurück.
 *
 * @return array{rows: array<int, array{mode: string, from_time: ?string}>, error: ?string}
 */
function parseAvailabilityInput(array $modes, array $times): array
{
    $names = weekdayNamesDe();
    $rows = [];
    foreach ($names as $wd => $name) {
        $mode = (string)($modes[$wd] ?? 'any');
        if ($mode === 'none') {
            $rows[$wd] = ['mode' => 'none', 'from_time' => null];
        } elseif ($mode === 'from') {
            $time = trim((string)($times[$wd] ?? ''));
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return ['rows' => [], 'error' => 'Bitte für ' . $name . ' eine Uhrzeit angeben.'];
            }
            $rows[$wd] = ['mode' => 'from', 'from_time' => $time];
        }
    }
    return ['rows' => $rows, 'error' => null];
}

/** Ersetzt die gesamte feste Verfügbarkeit von $userId durch $rows (aus parseAvailabilityInput). */
function saveUserAvailability(int $userId, array $rows): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM availability WHERE user_id = :u')->execute(['u' => $userId]);
        $ins = $pdo->prepare('INSERT INTO availability (user_id, weekday, mode, from_time) VALUES (:u, :w, :m, :t)');
        foreach ($rows as $weekday => $row) {
            $ins->execute(['u' => $userId, 'w' => (int)$weekday, 'm' => $row['mode'], 't' => $row['from_time']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Feste Verfügbarkeit eines Mitarbeiters: Wochentag (1-7) => ['mode', 'from_time']; leer = keine Einschränkung. */
function userAvailability(int $userId): array
{
    $stmt = db()->prepare('SELECT weekday, mode, from_time FROM availability WHERE user_id = :u');
    $stmt->execute(['u' => $userId]);
    $byDay = [];
    foreach ($stmt as $row) {
        $byDay[(int)$row['weekday']] = $row;
    }
    return $byDay;
}

/** Alle festen Verfügbarkeiten, gruppiert: user_id => Wochentag => Zeile (ein Abruf für eine ganze Planungswoche). */
function availabilityByUser(): array
{
    $byUser = [];
    foreach (db()->query('SELECT user_id, weekday, mode, from_time FROM availability') as $row) {
        $byUser[(int)$row['user_id']][(int)$row['weekday']] = $row;
    }
    return $byUser;
}

/**
 * Widerspricht die Schicht (Datum + Beginn) der festen Verfügbarkeit $userRows (aus userAvailability())?
 * Maßgeblich ist der Schichtbeginn. null = kein Widerspruch. Immer nur ein Hinweis, nie eine Sperre.
 */
function availabilityConflict(array $userRows, string $date, string $startTime): ?array
{
    $ts = strtotime($date);
    $row = $ts ? ($userRows[(int)date('N', $ts)] ?? null) : null;
    if (!$row) {
        return null;
    }
    if ($row['mode'] === 'none') {
        return ['mode' => 'none', 'from_time' => null];
    }
    if ($row['mode'] === 'from' && $row['from_time'] !== null && $startTime < $row['from_time']) {
        return ['mode' => 'from', 'from_time' => $row['from_time']];
    }
    return null;
}

/** Kurztext für den Admin neben dem Namen: "nicht verfügbar" bzw. "erst ab 16:00". */
function availabilityHint(array $conflict): string
{
    return $conflict['mode'] === 'none' ? 'nicht verfügbar' : 'erst ab ' . $conflict['from_time'];
}

/** Erklärung für den Mitarbeiter, warum eine freie Schicht für ihn nicht hervorgehoben wird. */
function availabilityNote(array $conflict, string $date): string
{
    return 'Dein Eintrag für ' . weekdayDe($date) . ': ' . availabilityHint($conflict);
}

/**
 * Empfänger des Rundrufs "Personal gesucht" für $shift: alle aktiven Mitarbeiter außer wer für genau
 * diese Schicht schon beworben/zugewiesen ist, wer am Schichttag eine andere, zeitlich überlappende
 * Schicht hat (arbeitet dann bereits), und wer heute oder am Schichttag abwesend ist (Urlaub,
 * Krankheit, Sonstiges). Die feste Verfügbarkeit zählt hier bewusst nicht: der Rundruf geht an alle.
 * $shift braucht id, shift_date, start_time, end_time.
 */
function urgentReminderRecipients(array $shift, ?string $today = null): array
{
    $today ??= date('Y-m-d');
    // Eine Schicht über Mitternacht (Ende <= Beginn) reicht bis 24:00 desselben Tages.
    $end = $shift['end_time'] > $shift['start_time'] ? $shift['end_time'] : '24:00';
    $stmt = db()->prepare(
        "SELECT u.* FROM users u
         WHERE u.role = 'employee' AND u.active = 1
           AND NOT EXISTS (
               SELECT 1 FROM shift_applications sa
               WHERE sa.shift_id = :sid AND sa.user_id = u.id AND sa.status IN ('pending', 'approved')
           )
           AND NOT EXISTS (
               SELECT 1 FROM shift_applications sb JOIN shifts s2 ON s2.id = sb.shift_id
               WHERE sb.user_id = u.id AND sb.status = 'approved'
                 AND s2.shift_date = :d AND s2.id != :sid
                 AND s2.start_time < :end
                 AND (CASE WHEN s2.end_time > s2.start_time THEN s2.end_time ELSE '24:00' END) > :start
           )
         ORDER BY u.name"
    );
    $stmt->execute(['sid' => $shift['id'], 'd' => $shift['shift_date'], 'start' => $shift['start_time'], 'end' => $end]);
    return array_values(array_filter($stmt->fetchAll(), function ($u) use ($shift, $today) {
        return !isUserAbsentOn((int)$u['id'], $today) && !isUserAbsentOn((int)$u['id'], $shift['shift_date']);
    }));
}

/**
 * Bringt eine eingegebene Mobilnummer ins internationale E.164-Format (+436641234567) - das
 * verlangt das SMS-Gateway. Akzeptiert die üblichen Schreibweisen: "0664 1234567" (nationale
 * Null wird durch die Standard-Landesvorwahl ersetzt, siehe SmsClient::defaultCountryCode()),
 * "0043 664 1234567", "+43 (0) 664 123 45 67", "+43/664/1234567". Gibt null zurück, wenn das
 * Ergebnis keine gültige Nummer ist. Österreichische Nummern müssen eine Mobilnummer sein (+436…),
 * eine Festnetznummer kann keine SMS empfangen. Leere Eingabe prüft der Aufrufer selbst.
 */
function normalizePhone(string $input): ?string
{
    // Aus Kontakte/Nachrichten kopierte Nummern tragen oft unsichtbare Formatzeichen
    // (z.B. U+202A/U+202C) und geschützte Leerzeichen; beides würde sonst die Prüfung kippen.
    $s = preg_replace('/[\p{Cf}\x{00A0}\x{2007}\x{202F}]/u', '', $input) ?? $input;
    $s = preg_replace('/\(\s*0\s*\)/', '', trim($s));
    $s = preg_replace('/[\s\-\/.()]/', '', (string)$s);
    if ($s === '' || !preg_match('/^\+?\d+$/', $s)) {
        return null;
    }

    if (str_starts_with($s, '00')) {
        $s = '+' . substr($s, 2);
    } elseif (str_starts_with($s, '0')) {
        $s = SmsClient::defaultCountryCode() . substr($s, 1);
    } elseif ($s[0] !== '+') {
        return null; // ohne +, 00 oder 0 nicht eindeutig einem Land zuzuordnen
    }

    if (str_starts_with($s, '+430')) {
        $s = '+43' . substr($s, 4); // "+43 0664 …": überflüssige nationale Null hinter der Vorwahl
    }
    if (!preg_match('/^\+[1-9]\d{7,14}$/', $s)) {
        return null;
    }
    if (str_starts_with($s, '+43') && !preg_match('/^\+436\d{7,11}$/', $s)) {
        return null;
    }
    if (str_starts_with($s, '+49') && !preg_match('/^\+491[567]\d{8,9}$/', $s)) {
        return null; // Deutschland: Mobilfunk liegt bei 015x, 016x, 017x - alles andere ist Festnetz o.ä.
    }
    return $s;
}

/** "+43664***4567" - für Protokolle und Anzeigen, in denen die volle Nummer nicht nötig ist. */
function maskPhone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 9) {
        return $phone;
    }
    return substr($phone, 0, 5) . str_repeat('*', $len - 9) . substr($phone, -4);
}

/**
 * Text der Benachrichtigungs-SMS: nur ein allgemeiner Hinweis und der Link ins Portal, bewusst
 * ohne Schichten, Titel oder Bewerbungsergebnisse (die stehen erst nach dem Login da).
 * $kind: 'plan' = Änderung im eigenen Plan (Veröffentlichen), 'application' = Neuigkeit zur eigenen
 * Bewerbung (Ablehnung), 'new_plan' = neuer Plan mit offenen Schichten zum Bewerben (an alle).
 * Nur Zeichen aus dem GSM-Zeichensatz (Umlaute erlaubt): bis zu 160 Zeichen gelten.
 */
function smsChangeNotice(string $portalUrl, string $kind = 'plan'): string
{
    if ($kind === 'new_plan') {
        $text = 'Schichtplaner: Neuer Plan verfügbar. Jetzt Schichten ansehen und bewerben';
        return $portalUrl === '' ? $text . '.' : $text . ': ' . $portalUrl;
    }
    $text = $kind === 'application'
        ? 'Schichtplaner: Es gibt Neuigkeiten zu deiner Bewerbung.'
        : 'Schichtplaner: Es gibt eine Änderung in deinem Plan.';
    return $portalUrl === '' ? $text : $text . ' Details im Portal: ' . $portalUrl;
}

/**
 * SMS für den Personalbedarf-Rundruf einer konkreten Schicht (admin/shifts.php, Aktion
 * 'urgent_reminder'): nennt bewusst Titel, Tag und Zeit - anders als smsChangeNotice() ist das
 * hier die eine sanktionierte Ausnahme, weil der Admin die Schicht selbst auswählt und die
 * Nachricht eine konkrete Handlungsaufforderung ist. Reicht der Platz nicht (bis 160 Zeichen,
 * nur BMP), wird nur der Schichttitel gekürzt; Tag, Zeit und Link bleiben immer vollständig.
 */
/**
 * Ersetzt "typografische" Zeichen, die Texteditoren/Smartphones gern automatisch einsetzen
 * (Gedankenstrich, geschwungene Anführungszeichen, Auslassungspunkt, geschütztes Leerzeichen)
 * durch ihr ASCII-Gegenstück. Grund: Das SMS-Gateway sendet nur reinen GSM-7-Text mit bis zu 160
 * Zeichen; ist auch nur ein einziges Zeichen NICHT im GSM-Zeichensatz (z.B. "–" statt "-"), wertet
 * es die ganze Nachricht als Unicode und erlaubt dann nur noch 70 Zeichen - genau das ließ zuvor
 * den Personalbedarf-Rundruf mit "–" im festen Text scheitern (127 > 70 Zeichen, HTTP 400). Deutsche
 * Umlaute (ä/ö/ü/ß) gehören zum GSM-7-Zeichensatz und bleiben unangetastet.
 */
function gsmSafe(string $text): string
{
    $map = ['–' => '-', '—' => '-', '−' => '-', '‘' => "'", '’' => "'", '‚' => "'", '“' => '"', '”' => '"', '„' => '"', '…' => '...', "Â " => ' '];
    return strtr($text, $map);
}

function smsUrgentNotice(array $shift, string $portalUrl): string
{
    $prefix = 'Schichtplaner: Dringend gesucht - ';
    $suffix = ' am ' . date('d.m.', strtotime((string)$shift['shift_date']))
        . ', ' . $shift['start_time'] . '-' . $shift['end_time'] . '. Jetzt bewerben'
        . ($portalUrl === '' ? '.' : ': ' . rtrim($portalUrl, '/') . '/shifts.php');

    $max = 160;
    $clean = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/[\x{10000}-\x{10FFFF}\p{Cc}]/u', ' ', gsmSafe((string)$shift['title']))));
    $room = $max - mb_strlen($prefix) - mb_strlen($suffix);
    if ($room < 1) {
        return mb_substr($prefix . $suffix, 0, $max);
    }
    if (mb_strlen($clean) > $room) {
        $clean = mb_substr($clean, 0, max(0, $room - 3)) . '...';
    }
    return mb_substr($prefix . $clean . $suffix, 0, $max);
}

/**
 * SMS für den Rundruf der dringenden Mitteilung (admin/settings.php, "Dringende Mitteilung").
 * Anders als smsUrgentNotice() hat hier der ADMIN den gesamten Text frei verfasst (kein fester
 * Rahmensatz), deshalb wird nur "Wichtig: " davorgesetzt und bei Bedarf gekürzt (bis 160 Zeichen,
 * nur BMP).
 */
function smsBroadcastNotice(string $noticeText, string $portalUrl): string
{
    $prefix = 'Schichtplaner - Wichtig: ';
    $suffix = $portalUrl === '' ? '' : ' ' . rtrim($portalUrl, '/') . '/my_week.php';

    $max = 160;
    $clean = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/[\x{10000}-\x{10FFFF}\p{Cc}]/u', ' ', gsmSafe($noticeText))));
    $room = $max - mb_strlen($prefix) - mb_strlen($suffix);
    if ($room < 1) {
        return mb_substr($prefix . $suffix, 0, $max);
    }
    if (mb_strlen($clean) > $room) {
        $clean = mb_substr($clean, 0, max(0, $room - 3)) . '...';
    }
    return mb_substr($prefix . $clean . $suffix, 0, $max);
}
