<?php
declare(strict_types=1);

/**
 * Baut eine minimale iCalendar-Datei (RFC 5545) aus einer Liste von Schichten - reiner
 * Text, keine externe Bibliothek nötig. Wird sowohl für den einmaligen Download als auch
 * für das Kalender-Abo genutzt (siehe public/calendar_feed.php) - beide teilen sich diese
 * eine Erzeugung, nur der Aufrufer/Auth-Weg unterscheidet sich.
 */
final class IcsBuilder
{
    public static function build(array $shifts, string $calendarName, string $domain): string
    {
        $tz = new DateTimeZone('Europe/Berlin');
        $utc = new DateTimeZone('UTC');

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Amadeus Delivery//Schichtplaner//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = self::foldLine('X-WR-CALNAME:' . self::escape($calendarName));
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';
        // Hinweis für Clients, die das respektieren (z.B. ältere Thunderbird/Lightning) - die
        // meisten modernen Kalender-Apps entscheiden ihr Abfrage-Intervall trotzdem selbst.
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT4H';
        $lines[] = 'X-PUBLISHED-TTL:PT4H';

        foreach ($shifts as $sh) {
            $start = new DateTime($sh['shift_date'] . ' ' . $sh['start_time'], $tz);
            $end = new DateTime($sh['shift_date'] . ' ' . $sh['end_time'], $tz);
            $start->setTimezone($utc);
            $end->setTimezone($utc);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:shift-' . (int)$sh['id'] . '@' . $domain;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
            $lines[] = self::foldLine('SUMMARY:' . self::escape((string)$sh['title']));
            if (!empty($sh['location'])) {
                $lines[] = self::foldLine('LOCATION:' . self::escape((string)$sh['location']));
            }
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }

    /** RFC 5545 verlangt Zeilenumbruch (Folding) nach 75 Oktetten. */
    private static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $folded = '';
        while (strlen($line) > 75) {
            $folded .= substr($line, 0, 75) . "\r\n ";
            $line = substr($line, 75);
        }
        return $folded . $line;
    }
}
