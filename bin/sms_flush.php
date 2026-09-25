<?php
declare(strict_types=1);

/**
 * Versucht fällige SMS aus der Warteschlange (sms_outbox) erneut zu senden - für Aufträge, die
 * das Gateway wegen seines Limits (10 neue Aufträge/Minute) oder eines kurzen Ausfalls nicht sofort
 * angenommen hat. Räumt außerdem erledigte Einträge nach 30 Tagen auf.
 *
 * Als Cron jede Minute, als Besitzer der Datenbank (auf dem Pi www-data):
 *   * * * * * cd /var/www/schichtplaner && php bin/sms_flush.php >/dev/null 2>&1
 */

if (PHP_SAPI !== 'cli') {
    die("Nur per Kommandozeile ausführbar.\n");
}

require __DIR__ . '/../app/bootstrap.php';

if (!SmsClient::enabled()) {
    exit(0); // SMS nicht konfiguriert - nichts zu tun
}

$tried = SmsClient::flush(8);
if ($tried > 0) {
    echo date('c') . " $tried SMS aus der Warteschlange versucht.\n";
}
