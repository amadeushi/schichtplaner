<?php
declare(strict_types=1);

/**
 * Client für das SMS-Gateway im eigenen Netz (https://sms.amds.at, POST /v1/messages).
 *
 * Jede SMS landet zuerst in der Tabelle sms_outbox und wird sofort einmal versucht. Das Gateway
 * nimmt nur 10 neue Aufträge pro Minute an und kann kurz nicht erreichbar sein - nicht
 * erreichbare/gedrosselte Aufträge (HTTP 429/503, Netzwerkfehler) bleiben deshalb mit
 * wachsendem Abstand in der Warteschlange und werden von bin/sms_flush.php (Cron, jede Minute)
 * erneut versucht - mit demselben Idempotency-Key, damit das Gateway nie doppelt versendet.
 * Ein Ausfall des Gateways darf nie eine Planänderung oder das Veröffentlichen blockieren,
 * deshalb wirft nichts hier eine Exception nach außen.
 *
 * Der API-Schlüssel steht ausschließlich in app/config.php ('sms' => ['api_key' => ...]),
 * nie in der Datenbank, nie im Repo und wird nie geloggt.
 */
final class SmsClient
{
    private const MAX_ATTEMPTS = 8;
    private const MAX_AGE_SECONDS = 21600; // 6 Stunden - danach ist eine Plan-SMS wertlos
    private const COALESCE_SECONDS = 900; // höchstens eine Benachrichtigungs-SMS je Person in 15 Minuten
    private const BACKOFF_SECONDS = [60, 120, 300, 900, 1800, 3600];

    private static array $cfg = [];
    private static ?array $effective = null;
    private static bool $gatewayUnreachable = false;
    private static string $portalUrl = '';

    public static function configure(array $cfg, string $portalUrl = ''): void
    {
        self::$cfg = $cfg;
        self::$effective = null;
        self::$portalUrl = rtrim($portalUrl, '/');
    }

    /** Adresse des Portals (app_url) für den Link in der SMS. */
    public static function portalUrl(): string
    {
        return self::$portalUrl;
    }

    /** Nach dem Speichern der Einstellungen: Überlagerung beim nächsten Zugriff neu lesen. */
    public static function refresh(): void
    {
        self::$effective = null;
    }

    /**
     * Wirksame Konfiguration: Werte aus den Einstellungen (Admin-Oberfläche) haben Vorrang vor
     * app/config.php, damit sich der Schlüssel ohne Zugriff auf den Server pflegen lässt. Fehlt
     * ein Wert in den Einstellungen, gilt config.php. Der Schlüssel wird nirgends ausgegeben.
     */
    private static function cfg(): array
    {
        if (self::$effective !== null) {
            return self::$effective;
        }
        $cfg = self::$cfg;
        try {
            $key = trim((string)setting('sms_api_key', ''));
            if ($key !== '') {
                $cfg['api_key'] = $key;
            }
            $flag = (string)setting('sms_enabled', '');
            if ($flag !== '') {
                $cfg['enabled'] = $flag === '1';
            }
        } catch (Throwable) {
            // Einstellungen nicht lesbar (z.B. Datenbank noch nicht migriert): nur config.php gilt.
        }
        return self::$effective = $cfg;
    }

    /** Ob "SMS aktivieren" gesetzt ist - unabhängig davon, ob schon ein Schlüssel vorhanden ist. */
    public static function enabledFlag(): bool
    {
        return !empty(self::cfg()['enabled']);
    }

    /** Woher der Schlüssel kommt: 'settings' (Oberfläche), 'config' (config.php) oder null. */
    public static function keySource(): ?string
    {
        try {
            if (trim((string)setting('sms_api_key', '')) !== '') {
                return 'settings';
            }
        } catch (Throwable) {
        }
        return trim((string)(self::$cfg['api_key'] ?? '')) !== '' ? 'config' : null;
    }

    /** "••••1234" - nur die letzten 4 Zeichen, für die Anzeige in der Oberfläche. */
    public static function maskedKey(): ?string
    {
        $key = trim((string)(self::cfg()['api_key'] ?? ''));
        return $key === '' ? null : '••••' . substr($key, -4);
    }

    public static function enabled(): bool
    {
        return !empty(self::cfg()['enabled'])
            && trim((string)(self::cfg()['api_key'] ?? '')) !== ''
            && self::baseUrl() !== '';
    }

    public static function defaultCountryCode(): string
    {
        $cc = trim((string)(self::cfg()['default_country_code'] ?? '+43'));
        return preg_match('/^\+[1-9]\d{0,3}$/', $cc) ? $cc : '+43';
    }

    /** Nur HTTPS (der Schlüssel geht nie unverschlüsselt übers Netz) - außer für localhost-Tests. */
    private static function baseUrl(): string
    {
        // Ohne sms-Block in config.php (der Schlüssel kommt jetzt aus den Einstellungen) gilt das
        // Standard-Gateway - sonst blieb enabled() trotz Schlüssel und Aktivierung false.
        $url = rtrim(trim((string)(self::cfg()['base_url'] ?? '')) ?: 'https://sms.amds.at', '/');
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }
        $isLocal = in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true);
        if (($parts['scheme'] ?? '') !== 'https' && !$isLocal) {
            return '';
        }
        return $url;
    }

    /** Legt die SMS in die Outbox und versucht sie sofort einmal. Gibt Status/Fehler zurück. */
    public static function enqueue(?int $userId, string $phone, string $eventType, string $text, int $delaySeconds = 0): array
    {
        $key = 'schichtplaner-' . bin2hex(random_bytes(12));
        db()->prepare(
            "INSERT INTO sms_outbox (user_id, phone, event_type, text, idempotency_key, next_attempt_at)
             VALUES (:u, :p, :e, :t, :k, datetime('now', :d))"
        )->execute(['u' => $userId, 'p' => $phone, 'e' => $eventType, 't' => $text, 'k' => $key, 'd' => '+' . max(0, $delaySeconds) . ' seconds']);
        $id = (int)db()->lastInsertId();

        if ($delaySeconds <= 0) {
            self::attempt($id);
        }
        return self::status($id);
    }

    /**
     * Benachrichtigungs-SMS mit höchstens einer Nachricht je Person in COALESCE_SECONDS. Die SMS ist
     * immer derselbe Hinweis auf eine Änderung, mehrere kurz hintereinander wären reines Rauschen.
     * Erste Änderung: sofort. Weitere im Zeitfenster: zusammen als eine SMS zum Fensterende (der Cron
     * sendet sie). Ist für die Person schon eine SMS unterwegs, geht keine zweite raus - sie deckt
     * die neue Änderung mit ab. Eine Änderung geht so nie verloren.
     */
    public static function enqueueCoalesced(int $userId, string $phone, string $eventType, string $text): array
    {
        $stmt = db()->prepare("SELECT 1 FROM sms_outbox WHERE user_id = :u AND status = 'queued' AND event_type != 'test' LIMIT 1");
        $stmt->execute(['u' => $userId]);
        if ($stmt->fetchColumn()) {
            return ['status' => 'merged'];
        }

        $stmt = db()->prepare(
            "SELECT CAST(strftime('%s','now') AS INTEGER) - CAST(strftime('%s', MAX(updated_at)) AS INTEGER)
             FROM sms_outbox WHERE user_id = :u AND status = 'accepted' AND event_type != 'test'"
        );
        $stmt->execute(['u' => $userId]);
        $sinceLast = $stmt->fetchColumn();
        if ($sinceLast !== false && $sinceLast !== null && (int)$sinceLast < self::COALESCE_SECONDS) {
            return self::enqueue($userId, $phone, $eventType, $text, self::COALESCE_SECONDS - (int)$sinceLast);
        }
        return self::enqueue($userId, $phone, $eventType, $text);
    }

    public static function status(int $id): array
    {
        $stmt = db()->prepare('SELECT id, status, last_error, api_message_id, attempts FROM sms_outbox WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: ['id' => $id, 'status' => 'failed', 'last_error' => 'nicht gefunden', 'attempts' => 0];
    }

    /** Ein Versuch für einen Outbox-Eintrag; entscheidet über akzeptiert / erneut versuchen / verworfen. */
    public static function attempt(int $id): void
    {
        if (self::$gatewayUnreachable) {
            return; // in diesem Request schon einmal nicht erreichbar - alles Weitere bleibt für den Cron
        }

        $stmt = db()->prepare(
            "SELECT *, (CAST(strftime('%s','now') AS INTEGER) - CAST(strftime('%s', created_at) AS INTEGER)) AS age_s
             FROM sms_outbox WHERE id = :id AND status = 'queued'"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $res = self::request('POST', '/v1/messages', ['to' => $row['phone'], 'text' => $row['text']], $row['idempotency_key']);
        $http = $res['http'];
        $attempts = (int)$row['attempts'] + 1;

        if ($http === 200 || $http === 202) {
            db()->prepare(
                "UPDATE sms_outbox SET status = 'accepted', attempts = :a, api_message_id = :m, last_error = NULL, updated_at = datetime('now') WHERE id = :id"
            )->execute(['a' => $attempts, 'm' => is_string($res['body']['id'] ?? null) ? $res['body']['id'] : null, 'id' => $id]);
            self::log((string)$row['event_type'], (string)$row['phone'], true, null);
            return;
        }

        $reason = self::describeFailure($res);
        $retryable = $http === 0 || $http === 429 || $http >= 500;
        if ($http === 0) {
            self::$gatewayUnreachable = true;
        }

        if ($retryable && $attempts < self::MAX_ATTEMPTS && (int)$row['age_s'] < self::MAX_AGE_SECONDS) {
            $delay = self::BACKOFF_SECONDS[min($attempts, count(self::BACKOFF_SECONDS)) - 1];
            db()->prepare(
                "UPDATE sms_outbox SET attempts = :a, last_error = :e, next_attempt_at = datetime('now', :d), updated_at = datetime('now') WHERE id = :id"
            )->execute(['a' => $attempts, 'e' => $reason, 'd' => '+' . $delay . ' seconds', 'id' => $id]);
            self::log((string)$row['event_type'], (string)$row['phone'], false, $reason . ' - wird erneut versucht');
            return;
        }

        $final = $retryable ? 'verworfen nach ' . $attempts . ' Versuchen: ' . $reason : $reason;
        db()->prepare(
            "UPDATE sms_outbox SET status = 'failed', attempts = :a, last_error = :e, updated_at = datetime('now') WHERE id = :id"
        )->execute(['a' => $attempts, 'e' => $final, 'id' => $id]);
        self::log((string)$row['event_type'], (string)$row['phone'], false, $final);
    }

    /** Für den Cron: fällige Aufträge erneut versuchen (höchstens $limit pro Lauf - das Gateway nimmt 10/Minute an). */
    public static function flush(int $limit = 8): int
    {
        $ids = db()->query(
            "SELECT id FROM sms_outbox WHERE status = 'queued' AND next_attempt_at <= datetime('now') ORDER BY id LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            self::attempt((int)$id);
            if (self::$gatewayUnreachable) {
                break;
            }
        }

        // Erledigte Einträge (mit Telefonnummer) nach 30 Tagen nicht mehr aufbewahren.
        db()->exec("DELETE FROM sms_outbox WHERE status != 'queued' AND created_at < datetime('now', '-30 days')");

        return count($ids);
    }

    /** Verbindungstest gegen GET /v1/health (löst keinen Versand aus). */
    public static function health(): array
    {
        $res = self::request('GET', '/v1/health');
        $ok = $res['http'] === 200 && (($res['body']['status'] ?? '') === 'ok');
        return [
            'ok' => $ok,
            'project' => is_string($res['body']['project'] ?? null) ? $res['body']['project'] : null,
            'error' => $ok ? null : self::describeFailure($res),
        ];
    }

    public static function stats(): array
    {
        $row = db()->query(
            "SELECT
                SUM(status = 'queued') AS queued,
                SUM(status = 'failed' AND created_at >= datetime('now', '-7 days')) AS failed_week,
                SUM(status = 'accepted' AND created_at >= date('now')) AS accepted_today
             FROM sms_outbox"
        )->fetch();
        return [
            'queued' => (int)($row['queued'] ?? 0),
            'failed_week' => (int)($row['failed_week'] ?? 0),
            'accepted_today' => (int)($row['accepted_today'] ?? 0),
        ];
    }

    private static function describeFailure(array $res): string
    {
        if ($res['http'] === 0) {
            return 'Gateway nicht erreichbar' . ($res['error'] ? ' (' . $res['error'] . ')' : '');
        }
        $apiMsg = $res['body']['error'] ?? $res['body']['message'] ?? null;
        $label = match ($res['http']) {
            401 => 'Schlüssel fehlt oder ist falsch',
            429 => 'Limit des Gateways erreicht',
            503 => 'Gateway nicht verfügbar',
            default => null,
        };
        $text = $label ?? (is_string($apiMsg) ? $apiMsg : 'Fehler');
        return $text . ' (HTTP ' . $res['http'] . ')';
    }

    private static function request(string $method, string $path, ?array $json = null, ?string $idempotencyKey = null): array
    {
        $headers = ['Authorization: Bearer ' . trim((string)(self::cfg()['api_key'] ?? '')), 'Accept: application/json'];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => max(2, (int)(self::cfg()['timeout'] ?? 6)),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            if ($idempotencyKey !== null) {
                $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
            }
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init(self::baseUrl() . $path);
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno ? curl_error($ch) : null;
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $body = is_string($raw) ? json_decode($raw, true) : null;
        return ['http' => $errno ? 0 : $http, 'body' => is_array($body) ? $body : null, 'error' => $error];
    }

    private static function log(string $eventType, string $phone, bool $success, ?string $error): void
    {
        try {
            db()->prepare(
                'INSERT INTO notification_log (event_type, channel, recipient, success, error) VALUES (:t, :c, :r, :s, :e)'
            )->execute(['t' => 'sms.' . $eventType, 'c' => 'sms', 'r' => maskPhone($phone), 's' => $success ? 1 : 0, 'e' => $error]);
        } catch (Throwable) {
            // Logging darf den eigentlichen Ablauf nie unterbrechen.
        }
    }
}
