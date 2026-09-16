<?php
declare(strict_types=1);

/**
 * Loescht Schichten (und per ON DELETE CASCADE ihre Bewerbungen), deren
 * Datum laenger als RETENTION_DAYS zurueckliegt. Gedacht fuer einen
 * taeglichen Cronjob. Aufruf: php bin/cleanup_old_shifts.php
 */

const RETENTION_DAYS = 90;
const LOGIN_ATTEMPT_RETENTION_DAYS = 7;

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausfuehrbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$cutoff = date('Y-m-d', strtotime('-' . RETENTION_DAYS . ' days'));

$stmt = db()->prepare('DELETE FROM shifts WHERE shift_date < :cutoff');
$stmt->execute(['cutoff' => $cutoff]);
$deleted = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " - $deleted Schicht(en) aelter als $cutoff geloescht (Aufbewahrung: " . RETENTION_DAYS . " Tage).\n";

$loginCutoff = date('Y-m-d H:i:s', strtotime('-' . LOGIN_ATTEMPT_RETENTION_DAYS . ' days'));
$loginStmt = db()->prepare('DELETE FROM login_attempts WHERE created_at < :cutoff');
$loginStmt->execute(['cutoff' => $loginCutoff]);
$loginDeleted = $loginStmt->rowCount();

echo date('Y-m-d H:i:s') . " - $loginDeleted Login-Versuch(e) aelter als " . LOGIN_ATTEMPT_RETENTION_DAYS . " Tage geloescht.\n";
