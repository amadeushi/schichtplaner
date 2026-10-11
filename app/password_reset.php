<?php
declare(strict_types=1);

/**
 * "Passwort vergessen": Einmal-Link per E-Mail oder SMS.
 *
 * Sicherheitsentscheidungen:
 *  - Der Link trägt 128 Bit Zufall; in der Datenbank steht nur dessen SHA-256-Hash.
 *  - Gültig PASSWORD_RESET_TTL_MINUTES Minuten und genau einmal; eine neue Anfrage entwertet ältere Links.
 *  - Antworten verraten nie, ob eine Adresse ein Konto hat. Darum wird jede Anfrage protokolliert
 *    (auch für unbekannte Adressen) und begrenzt - je Adresse und je IP.
 *  - Diese Datei kennt keinen Versand: createPasswordReset() liefert das Token, der Aufrufer
 *    verschickt es (Notifier::passwordResetLink) - so bleibt die Logik ohne Mail/SMS testbar.
 *  - Zeiten rechnet SQLite (datetime('now'), UTC) - kein Vermischen mit PHP-Ortszeit.
 */

const PASSWORD_RESET_TTL_MINUTES = 30;
const PASSWORD_RESET_MAX_PER_EMAIL = 3;  // je Stunde
const PASSWORD_RESET_MAX_PER_IP = 10;    // je Stunde

function passwordResetTokenHash(string $token): string
{
    return hash('sha256', $token);
}

/** Dieselbe Regel wie beim Ändern im Profil. Gibt eine Fehlermeldung oder null zurück. */
function passwordPolicyError(string $new, string $confirm): ?string
{
    if (mb_strlen($new) < 8) {
        return 'Das neue Passwort muss mindestens 8 Zeichen haben.';
    }
    if ($new !== $confirm) {
        return 'Die beiden Passwörter stimmen nicht überein.';
    }
    return null;
}

/**
 * Nimmt eine Anfrage entgegen: räumt Altes auf, prüft die Limits, protokolliert die Anfrage und
 * legt - nur für ein aktives Konto - ein neues Token an. Gibt ['user' => Zeile, 'token' => Klartext]
 * zurück, oder null (unbekannte Adresse, deaktiviertes Konto oder Limit erreicht). Der Aufrufer
 * antwortet in allen Fällen gleich.
 */
function createPasswordReset(string $email, string $ip): ?array
{
    $email = mb_strtolower(trim($email));
    if ($email === '' || mb_strlen($email) > 254) {
        return null;
    }

    $pdo = db();
    $pdo->exec("DELETE FROM password_reset_requests WHERE created_at < datetime('now', '-1 day')");
    $pdo->exec("DELETE FROM password_resets WHERE expires_at < datetime('now', '-1 day')");

    $byEmail = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE email = :e AND created_at >= datetime('now', '-1 hour')");
    $byEmail->execute(['e' => $email]);
    $byIp = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE ip_address = :ip AND created_at >= datetime('now', '-1 hour')");
    $byIp->execute(['ip' => $ip]);
    if ((int)$byEmail->fetchColumn() >= PASSWORD_RESET_MAX_PER_EMAIL || (int)$byIp->fetchColumn() >= PASSWORD_RESET_MAX_PER_IP) {
        return null;
    }

    $pdo->prepare('INSERT INTO password_reset_requests (email, ip_address) VALUES (:e, :ip)')->execute(['e' => $email, 'ip' => $ip]);

    $userStmt = $pdo->prepare('SELECT * FROM users WHERE lower(email) = :e AND active = 1 ORDER BY id LIMIT 1');
    $userStmt->execute(['e' => $email]);
    $user = $userStmt->fetch();
    if (!$user) {
        return null;
    }

    $token = bin2hex(random_bytes(16));
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE password_resets SET used_at = datetime('now') WHERE user_id = :u AND used_at IS NULL")->execute(['u' => $user['id']]);
    $pdo->prepare(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:u, :h, datetime('now', :ttl))"
    )->execute(['u' => $user['id'], 'h' => passwordResetTokenHash($token), 'ttl' => '+' . PASSWORD_RESET_TTL_MINUTES . ' minutes']);
    $pdo->commit();

    return ['user' => $user, 'token' => $token];
}

/** Gültiger, unbenutzter, nicht abgelaufener Link eines aktiven Kontos? Gibt die users-Zeile plus reset_id zurück. */
function findValidPasswordReset(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT pr.id AS reset_id, u.* FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at > datetime('now') AND u.active = 1"
    );
    $stmt->execute(['h' => passwordResetTokenHash($token)]);
    return $stmt->fetch() ?: null;
}

/**
 * Setzt das neue Passwort, entwertet alle offenen Links des Kontos und löscht dessen Fehlversuche,
 * damit eine Login-Sperre den frisch zurückgesetzten Zugang nicht blockiert. Gibt false zurück, wenn
 * der Link inzwischen verbraucht wurde (z.B. zwei gleichzeitige Absendungen).
 */
function completePasswordReset(array $reset, string $newPassword): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    $claimed = $pdo->prepare("UPDATE password_resets SET used_at = datetime('now') WHERE id = :id AND used_at IS NULL");
    $claimed->execute(['id' => $reset['reset_id']]);
    if ($claimed->rowCount() !== 1) {
        $pdo->rollBack();
        return false;
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id')
        ->execute(['h' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $reset['id']]);
    $pdo->prepare("UPDATE password_resets SET used_at = datetime('now') WHERE user_id = :u AND used_at IS NULL")->execute(['u' => $reset['id']]);
    $pdo->prepare('DELETE FROM login_attempts WHERE email = :e AND success = 0')->execute(['e' => $reset['email']]);
    $pdo->commit();
    return true;
}
