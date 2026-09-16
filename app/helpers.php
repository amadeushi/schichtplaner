<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function checkCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Ungültige Anfrage (CSRF-Token fehlt oder abgelaufen). Bitte Seite neu laden und erneut versuchen.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function formatDateDe(string $isoDate): string
{
    $ts = strtotime($isoDate);
    return $ts ? date('d.m.Y', $ts) : $isoDate;
}

function weekdayDe(string $isoDate): string
{
    $days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $ts = strtotime($isoDate);
    if (!$ts) {
        return '';
    }
    return $days[(int)date('N', $ts) - 1];
}

function statusLabelDe(string $status): string
{
    return match ($status) {
        'open' => 'Offen',
        'filled' => 'Besetzt',
        'closed' => 'Geschlossen',
        'pending' => 'Ausstehend',
        'approved' => 'Angenommen',
        'rejected' => 'Abgelehnt',
        'withdrawn' => 'Zurueckgezogen',
        default => $status,
    };
}

function formatDurationHm(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%d:%02d Std.', $hours, $minutes);
}

function monthNameDe(string $yearMonthDay): string
{
    $months = ['Januar', 'Februar', 'Maerz', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $ts = strtotime($yearMonthDay);
    if (!$ts) {
        return '';
    }
    return $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function monthBounds(string $yearMonth): array
{
    $start = $yearMonth . '-01';
    $end = date('Y-m-d', strtotime($start . ' +1 month'));
    return [$start, $end];
}
