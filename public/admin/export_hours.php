<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

/**
 * CSV-Export der Arbeitszeiten für einen Monat - für den Admin zur Dokumentation
 * gegenüber Steuerberater/Lohnverrechnung (z.B. Mindestlohn-Nachweis). Ohne
 * user_id: alle Mitarbeiter mit aktivierter Zeiterfassung in einer Datei;
 * mit user_id: nur dieser eine Mitarbeiter (Aufruf von admin/time_entries.php aus).
 * Semikolon als Trennzeichen und Komma als Dezimaltrennzeichen, weil Excel in
 * deutscher Spracheinstellung sonst die Spalten bzw. Nachkommastellen falsch liest.
 */

$monthParam = (string)($_GET['month'] ?? '');
$monthKey = (preg_match('/^\d{4}-\d{2}$/', $monthParam)) ? $monthParam : date('Y-m');
[$monthStart, $monthEnd] = monthBounds($monthKey);

$employeeId = (int)($_GET['user_id'] ?? 0);
$employeeName = 'alle';

if ($employeeId > 0) {
    $empStmt = db()->prepare('SELECT id, name FROM users WHERE id = :id');
    $empStmt->execute(['id' => $employeeId]);
    $employee = $empStmt->fetch();
    if (!$employee) {
        flash('error', 'Mitarbeiter nicht gefunden.');
        redirect('/admin/time_tracking.php');
    }
    $employeeName = $employee['name'];
    $entriesStmt = db()->prepare(
        'SELECT te.*, u.name AS employee_name FROM time_entries te JOIN users u ON u.id = te.user_id
         WHERE te.user_id = :u AND te.clock_in >= :start AND te.clock_in < :end
         ORDER BY te.clock_in'
    );
    $entriesStmt->execute(['u' => $employeeId, 'start' => $monthStart, 'end' => $monthEnd]);
} else {
    $entriesStmt = db()->prepare(
        'SELECT te.*, u.name AS employee_name FROM time_entries te JOIN users u ON u.id = te.user_id
         WHERE te.clock_in >= :start AND te.clock_in < :end
         ORDER BY u.name, te.clock_in'
    );
    $entriesStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
}
$entries = $entriesStmt->fetchAll();

$slug = preg_replace('/[^A-Za-z0-9]+/', '_', $employeeName);
$filename = "arbeitszeiten_{$monthKey}_{$slug}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM, damit Excel Umlaute korrekt erkennt
fputcsv($out, ['Mitarbeiter', 'Datum', 'Von', 'Bis', 'Dauer (Std.)', 'Dauer', 'Notiz'], ';', '"', '\\');

$totalSeconds = 0;
foreach ($entries as $en) {
    $seconds = $en['clock_out'] ? (strtotime($en['clock_out']) - strtotime($en['clock_in'])) : 0;
    $totalSeconds += $seconds;
    fputcsv($out, [
        $en['employee_name'],
        formatDateDe(date('Y-m-d', strtotime($en['clock_in']))),
        date('H:i', strtotime($en['clock_in'])),
        $en['clock_out'] ? date('H:i', strtotime($en['clock_out'])) : '',
        $en['clock_out'] ? number_format($seconds / 3600, 2, ',', '') : '',
        $en['clock_out'] ? formatDurationHm($seconds) : 'läuft noch',
        $en['note'] ?? '',
    ], ';', '"', '\\');
}

fputcsv($out, [], ';', '"', '\\');
fputcsv($out, ['Gesamt', '', '', '', number_format($totalSeconds / 3600, 2, ',', ''), formatDurationHm($totalSeconds), ''], ';', '"', '\\');

fclose($out);
exit;
