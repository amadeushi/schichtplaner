<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

// Ziel des "Rückgängig"-Hinweises (siehe rememberUndo()/undoForDisplay() in app/helpers.php und
// partials/header.php). Nur per POST mit CSRF-Token; das Token der Rückgängig-Möglichkeit ist
// einmalig und läuft nach UNDO_TTL_SECONDS ab.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/my_week.php');
}
checkCsrf();

$result = performUndo((string)($_POST['undo_token'] ?? ''), (int)$user['id']);
if ($result === null) {
    flash('error', 'Rückgängig ist nicht mehr möglich.');
    redirect('/my_week.php');
}

flash('success', $result['message']);
redirect($result['back']);
