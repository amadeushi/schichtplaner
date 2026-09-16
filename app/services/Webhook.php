<?php
declare(strict_types=1);

/**
 * Sendet Ereignisse als JSON an eine n8n-Webhook-URL (oder jeden anderen
 * Endpunkt, der JSON per POST annimmt). n8n kann daraus dann beliebige
 * weitere Kanaele bauen: E-Mail, Telegram, Slack, Push, etc.
 */
final class Webhook
{
    public function send(string $eventType, array $payload): bool
    {
        if (setting('webhook_enabled', '0') !== '1') {
            return false;
        }
        $url = setting('webhook_url', '');
        if (!$url) {
            return false;
        }

        $body = json_encode([
            'event' => $eventType,
            'timestamp' => date('c'),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno ? curl_error($ch) : null;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = !$errno && $httpCode >= 200 && $httpCode < 300;

        try {
            $stmt = db()->prepare('INSERT INTO notification_log (event_type, channel, recipient, success, error) VALUES (:t, :c, :r, :s, :e)');
            $stmt->execute([
                't' => $eventType,
                'c' => 'webhook',
                'r' => $url,
                's' => $success ? 1 : 0,
                'e' => $error ?? ($success ? null : "HTTP $httpCode"),
            ]);
        } catch (Throwable) {
        }

        return $success;
    }
}
