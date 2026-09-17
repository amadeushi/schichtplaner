<?php
require __DIR__ . '/../app/bootstrap.php';

/**
 * Öffentlicher Endpunkt (bewusst ohne requireLogin() - Kalender-Apps können keine
 * Login-Session nutzen). Auth läuft ausschließlich über das unratbare Token in der URL
 * (siehe ensureCalendarToken() in app/helpers.php). Dient zwei Zwecken mit demselben Code:
 * direkt im Browser aufgerufen ist es der einmalige Download; als webcal://-Adresse in
 * einer Kalender-App eingetragen wird dieselbe URL zum sich selbst aktualisierenden Abo -
 * Content-Disposition: attachment stört Kalender-Apps beim periodischen Abholen nicht,
 * macht aber den direkten Browser-Aufruf zu einem sauberen Download.
 *
 * Zeigt nur angenommene UND bereits veröffentlichte Schichten - Entwürfe dürfen nicht vor
 * der Veröffentlichung durch den Admin in den persönlichen Kalender durchsickern.
 */

$token = (string)($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(404);
    exit;
}

$userStmt = db()->prepare('SELECT * FROM users WHERE calendar_token = :t AND active = 1');
$userStmt->execute(['t' => $token]);
$feedUser = $userStmt->fetch();

if (!$feedUser) {
    http_response_code(404);
    exit;
}

// Optionale Eingrenzung fürs Herunterladen aus my_week.php heraus: eine einzelne Schicht
// (shift_id) oder eine Woche (start/end). Ohne beides bleibt das Verhalten exakt wie zuvor
// (14 Tage Rückblick + alles Zukünftige) - wichtig, damit ein bereits eingerichtetes Abo
// (ohne diese Parameter) sich nie stillschweigend verengt.
$shiftIdParam = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : 0;
$startParam = (string)($_GET['start'] ?? '');
$endParam = (string)($_GET['end'] ?? '');

if ($shiftIdParam > 0) {
    $shiftsStmt = db()->prepare(
        "SELECT sh.* FROM shifts sh
         JOIN shift_applications sa ON sa.shift_id = sh.id
         WHERE sa.user_id = :uid AND sa.status = 'approved' AND sh.published_at IS NOT NULL AND sh.id = :sid"
    );
    $shiftsStmt->execute(['uid' => $feedUser['id'], 'sid' => $shiftIdParam]);
    $shifts = $shiftsStmt->fetchAll();
    $filename = 'schicht.ics';
} elseif ($startParam !== '' && $endParam !== '' && strtotime($startParam) && strtotime($endParam)) {
    $shiftsStmt = db()->prepare(
        "SELECT sh.* FROM shifts sh
         JOIN shift_applications sa ON sa.shift_id = sh.id
         WHERE sa.user_id = :uid AND sa.status = 'approved' AND sh.published_at IS NOT NULL
           AND sh.shift_date BETWEEN :start AND :end
         ORDER BY sh.shift_date, sh.start_time"
    );
    $shiftsStmt->execute(['uid' => $feedUser['id'], 'start' => $startParam, 'end' => $endParam]);
    $shifts = $shiftsStmt->fetchAll();
    $filename = 'schichtwoche.ics';
} else {
    // 14 Tage Rückblick (kürzlich Gearbeitetes bleibt im Kalender sichtbar) plus alles
    // Zukünftige - kein Enddatum, damit neu veröffentlichte Zuweisungen beim nächsten
    // Abruf automatisch mit auftauchen. Das ist der Weg fürs Kalender-Abo.
    $cutoff = date('Y-m-d', strtotime('-14 days'));
    $shiftsStmt = db()->prepare(
        "SELECT sh.* FROM shifts sh
         JOIN shift_applications sa ON sa.shift_id = sh.id
         WHERE sa.user_id = :uid AND sa.status = 'approved' AND sh.published_at IS NOT NULL AND sh.shift_date >= :cutoff
         ORDER BY sh.shift_date, sh.start_time"
    );
    $shiftsStmt->execute(['uid' => $feedUser['id'], 'cutoff' => $cutoff]);
    $shifts = $shiftsStmt->fetchAll();
    $filename = 'schichtplan.ics';
}

$appName = setting('app_name', 'Schichtplaner');
$domain = parse_url((string)($config['app_url'] ?? ''), PHP_URL_HOST) ?: 'schichtplaner.local';
$ics = IcsBuilder::build($shifts, $appName . ' – ' . $feedUser['name'], $domain);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $ics;
