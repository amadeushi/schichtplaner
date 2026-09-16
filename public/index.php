<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

redirect($user['role'] === 'admin' ? '/admin/shifts.php' : '/my_week.php');
