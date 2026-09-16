<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/my_week.php');
}
checkCsrf();

$shiftId = (int)($_POST['shift_id'] ?? 0);
$returnDate = (string)($_POST['return_date'] ?? '');
$backTo = '/my_week.php' . ($returnDate ? '?date=' . urlencode($returnDate) : '');

$stmt = db()->prepare(
    "SELECT sh.*, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
     FROM shifts sh WHERE sh.id = :id AND sh.status = 'open'"
);
$stmt->execute(['id' => $shiftId]);
$shift = $stmt->fetch();

if (!$shift) {
    flash('error', 'Diese Schicht ist nicht mehr verfügbar.');
    redirect($backTo);
}

if ((int)$shift['approved_count'] >= (int)$shift['needed_count']) {
    flash('error', 'Diese Schicht ist bereits voll besetzt.');
    redirect($backTo);
}

$existing = db()->prepare('SELECT status FROM shift_applications WHERE shift_id = :s AND user_id = :u');
$existing->execute(['s' => $shiftId, 'u' => $user['id']]);
$existingStatus = $existing->fetchColumn();

if (in_array($existingStatus, ['pending', 'approved'], true)) {
    flash('error', 'Du hast dich für diese Schicht bereits beworben.');
    redirect($backTo);
}

if ($existingStatus === false) {
    db()->prepare('INSERT INTO shift_applications (shift_id, user_id, status) VALUES (:s, :u, :status)')
        ->execute(['s' => $shiftId, 'u' => $user['id'], 'status' => 'pending']);
} else {
    // Zurückgezogene oder abgelehnte Bewerbung: bestehenden Datensatz reaktivieren statt neu anzulegen
    // (shift_id, user_id) ist UNIQUE, ein zweiter INSERT würde fehlschlagen.
    db()->prepare(
        "UPDATE shift_applications SET status = 'pending', applied_at = datetime('now'), decided_at = NULL, decided_by = NULL
         WHERE shift_id = :s AND user_id = :u"
    )->execute(['s' => $shiftId, 'u' => $user['id']]);
}

flash('success', 'Bewerbung wurde eingereicht. Der Admin wird benachrichtigt.');
$notifier = new Notifier($config['smtp']);
$notifier->newApplication($shift, $user);

redirect($backTo);
