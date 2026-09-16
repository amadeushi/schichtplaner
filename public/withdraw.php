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
    "UPDATE shift_applications SET status = 'withdrawn'
     WHERE shift_id = :s AND user_id = :u AND status = 'pending'"
);
$stmt->execute(['s' => $shiftId, 'u' => $user['id']]);

flash('success', 'Bewerbung wurde zurückgezogen.');
redirect($backTo);
