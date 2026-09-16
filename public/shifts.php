<?php
require __DIR__ . '/../app/bootstrap.php';
requireLogin();

// "Offene Schichten" wurde in "Mein Plan" zusammengeführt (Ansehen + Bewerben
// in einem Bon-Strang statt zweier getrennter Seiten). Alter Link bleibt gültig.
redirect('/my_week.php');
