<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
