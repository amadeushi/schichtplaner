<?php
declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS_PER_EMAIL = 5;
const LOGIN_MAX_ATTEMPTS_PER_IP = 20;
const LOGIN_LOCKOUT_MINUTES = 15;

function clientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function isLoginLocked(string $email, string $ip): bool
{
    return recentFailedLoginCount('email', $email) >= LOGIN_MAX_ATTEMPTS_PER_EMAIL
        || recentFailedLoginCount('ip_address', $ip) >= LOGIN_MAX_ATTEMPTS_PER_IP;
}

function recentFailedLoginCount(string $column, string $value): int
{
    $allowedColumns = ['email', 'ip_address'];
    if (!in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('Ungültige Spalte.');
    }
    $since = date('Y-m-d H:i:s', strtotime('-' . LOGIN_LOCKOUT_MINUTES . ' minutes'));
    $stmt = db()->prepare(
        "SELECT COUNT(*) c FROM login_attempts WHERE $column = :value AND success = 0 AND created_at >= :since"
    );
    $stmt->execute(['value' => $value, 'since' => $since]);
    return (int)$stmt->fetch()['c'];
}

function logLoginAttempt(string $email, string $ip, bool $success): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip_address, success, created_at) VALUES (:e, :ip, :s, :now)')
        ->execute(['e' => $email, 'ip' => $ip, 's' => $success ? 1 : 0, 'now' => date('Y-m-d H:i:s')]);
}

function currentUser(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND active = 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $row = $stmt->fetch();
    $user = $row ?: null;
    return $user;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('/login.php');
    }
    // Ein vorläufiges Passwort (Neuanlage oder Reset durch den Admin) blockiert jede andere
    // Seite, bis im Profil ein eigenes Passwort gesetzt wurde - sonst bliebe das Feld reine
    // Dateninfrastruktur ohne tatsächliche Durchsetzung.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!empty($user['must_change_password']) && !str_ends_with($scriptName, '/profile.php')) {
        flash('error', 'Bitte zuerst dein vorläufiges Passwort ändern, bevor du fortfährst.');
        redirect('/profile.php');
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        die('Kein Zugriff. Diese Seite ist nur für Administratoren.');
    }
    return $user;
}

function attemptLogin(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND active = 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(300000); // leichte Bremse gegen simples Durchprobieren
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
