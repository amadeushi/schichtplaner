<?php
declare(strict_types=1);

/**
 * Minimaler SMTP-Mailer ohne externe Abhaengigkeiten (kein Composer noetig).
 * Reicht fuer einfache Text-/HTML-Mails ueber ein Standard-SMTP-Konto (TLS/SSL, AUTH LOGIN).
 * Fuer komplexere Anforderungen kann dies jederzeit durch PHPMailer ersetzt werden.
 */
final class Mailer
{
    private array $cfg;
    private ?string $lastError = null;

    public function __construct(array $smtpConfig)
    {
        $this->cfg = $smtpConfig;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** Mit $html geht die Mail als multipart/alternative (Text + HTML), sonst als reiner Text. */
    public function send(string $toEmail, string $toName, string $subject, string $body, ?string $html = null): bool
    {
        $this->lastError = null;

        if (empty($this->cfg['enabled'])) {
            $this->lastError = 'SMTP ist in der Konfiguration deaktiviert (enabled = false).';
            return false;
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Ungültige Empfänger-Adresse.';
            $this->log('email', $toEmail, false, $this->lastError);
            return false;
        }

        try {
            $this->sendSmtp($toEmail, $toName, $subject, $body, $html);
            $this->log('email', $toEmail, true, null);
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->log('email', $toEmail, false, $e->getMessage());
            return false;
        }
    }

    private function sendSmtp(string $toEmail, string $toName, string $subject, string $body, ?string $html = null): void
    {
        $host = $this->cfg['host'];
        $port = (int)$this->cfg['port'];
        $encryption = $this->cfg['encryption'] ?? 'tls';

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = stream_socket_client($transport . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            throw new RuntimeException("SMTP-Verbindung fehlgeschlagen: $errstr ($errno)");
        }

        $this->expect($socket, '220');
        $this->cmd($socket, 'EHLO ' . gethostname(), '250');

        if ($encryption === 'tls') {
            $this->cmd($socket, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS-Handshake fehlgeschlagen.');
            }
            $this->cmd($socket, 'EHLO ' . gethostname(), '250');
        }

        $this->cmd($socket, 'AUTH LOGIN', '334');
        $this->cmd($socket, base64_encode($this->cfg['username']), '334');
        $this->cmd($socket, base64_encode($this->cfg['password']), '235');

        $this->cmd($socket, 'MAIL FROM:<' . $this->cfg['from_email'] . '>', '250');
        $this->cmd($socket, 'RCPT TO:<' . $toEmail . '>', ['250', '251']);
        $this->cmd($socket, 'DATA', '354');

        $fromHeader = mb_encode_mimeheader($this->stripHeaderInjection((string)$this->cfg['from_name'])) . ' <' . $this->cfg['from_email'] . '>';
        $toHeader = mb_encode_mimeheader($this->stripHeaderInjection($toName)) . ' <' . $toEmail . '>';
        $subjectHeader = mb_encode_mimeheader($this->stripHeaderInjection($subject));

        $headers = "From: $fromHeader\r\n"
            . "To: $toHeader\r\n"
            . "Subject: $subjectHeader\r\n"
            . "MIME-Version: 1.0\r\n";

        if ($html === null) {
            $message = $headers
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "\r\n"
                . str_replace("\n.", "\n..", $body) . "\r\n.";
        } else {
            // Beide Teile als base64: bringt Umlaute/Leerzeichen am Zeilenende und lange Zeilen
            // sicher durch jedes SMTP-Relay, und ein einzelner Punkt am Zeilenanfang kann nicht vorkommen.
            $boundary = 'sp_' . bin2hex(random_bytes(12));
            $part = static fn(string $type, string $content): string => "--$boundary\r\n"
                . "Content-Type: $type; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode(str_replace("\r\n", "\n", $content)), 76, "\r\n");
            $message = $headers
                . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
                . "\r\n"
                . $part('text/plain', $body)
                . $part('text/html', $html)
                . "--$boundary--\r\n.";
        }

        $this->write($socket, $message);
        $this->expect($socket, '250');
        $this->cmd($socket, 'QUIT', '221');
        fclose($socket);
    }

    /** Entfernt CR/LF, damit Name/Betreff keine zusätzlichen SMTP-/MIME-Header einschleusen können. */
    private function stripHeaderInjection(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    private function cmd($socket, string $line, string|array $expectCode): string
    {
        $this->write($socket, $line);
        return $this->expect($socket, $expectCode);
    }

    private function write($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    private function expect($socket, string|array $codes): string
    {
        $codes = (array)$codes;
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break; // letzte Zeile einer mehrzeiligen Antwort
            }
        }
        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException("Unerwartete SMTP-Antwort: $response");
        }
        return $response;
    }

    private function log(string $channel, string $recipient, bool $success, ?string $error): void
    {
        try {
            $stmt = db()->prepare('INSERT INTO notification_log (event_type, channel, recipient, success, error) VALUES (:t, :c, :r, :s, :e)');
            $stmt->execute(['t' => 'mail', 'c' => $channel, 'r' => $recipient, 's' => $success ? 1 : 0, 'e' => $error]);
        } catch (Throwable) {
            // Logging darf den eigentlichen Ablauf nie unterbrechen.
        }
    }
}
