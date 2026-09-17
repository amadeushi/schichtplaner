<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'notify') {
        $notify = isset($_POST['notify_email']) ? 1 : 0;
        db()->prepare('UPDATE users SET notify_email = :n WHERE id = :id')
            ->execute(['n' => $notify, 'id' => $user['id']]);
        flash('success', 'Benachrichtigungseinstellung gespeichert.');
        redirect('/profile.php');
    }

    if ($action === 'regenerate_calendar_token') {
        $token = bin2hex(random_bytes(24));
        db()->prepare('UPDATE users SET calendar_token = :t WHERE id = :id')->execute(['t' => $token, 'id' => $user['id']]);
        flash('success', 'Neue Kalender-Adresse erzeugt. Die alte funktioniert ab jetzt nicht mehr.');
        redirect('/profile.php');
    }

    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'Aktuelles Passwort ist falsch.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Neues Passwort muss mindestens 8 Zeichen haben.');
        } elseif ($new !== $confirm) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
        } else {
            db()->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id')
                ->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
            flash('success', 'Passwort wurde geändert.');
        }
        redirect('/profile.php');
    }
}

$calendarToken = ensureCalendarToken((int)$user['id'], $user['calendar_token'] ?? null);
$calendarFeedUrl = rtrim((string)($config['app_url'] ?? ''), '/') . '/calendar_feed.php?token=' . $calendarToken;
$calendarWebcalUrl = preg_replace('/^https?:/', 'webcal:', $calendarFeedUrl);
// Googles eigener "per URL abonnieren"-Weg über den Browser statt webcal:// - wichtig für
// Android, dessen Google-Kalender-App (anders als die Web-Version) kein "Kalender per URL
// hinzufügen" kennt und auch keinen webcal://-Handler mitbringt. Dieser Link öffnet Googles
// Abonnieren-Dialog im Browser; das Konto übernimmt die Synchronisation in die App danach von selbst.
$googleCalendarUrl = 'https://calendar.google.com/calendar/render?cid=' . rawurlencode($calendarWebcalUrl);

require __DIR__ . '/partials/header.php';
?>
<h1>Profil</h1>

<div class="card">
  <h2>Benachrichtigungen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="notify">
    <label style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="notify_email" style="width:auto;" <?= $user['notify_email'] ? 'checked' : '' ?>>
      E-Mail-Benachrichtigungen erhalten
    </label>
    <button type="submit" class="btn" style="margin-top:1rem;">Speichern</button>
  </form>
</div>

<div class="card">
  <h2>Kalender-Abo</h2>
  <p class="muted">Deine angenommenen und bereits veröffentlichten Schichten als Kalender — einmal hinzufügen, danach hält sich dein Kalender von selbst aktuell (wie oft er sich neu holt, entscheidet deine Kalender-App).</p>
  <label for="calendar_url">Kalender-Adresse</label>
  <input type="text" id="calendar_url" value="<?= e($calendarFeedUrl) ?>" readonly onclick="this.select()">
  <div class="table-actions" style="margin-top:0.75rem;">
    <a class="btn" href="<?= e($calendarWebcalUrl) ?>">Zum Kalender hinzufügen</a>
    <a class="btn secondary" href="<?= e($googleCalendarUrl) ?>">Zu Google Kalender hinzufügen</a>
    <a class="btn secondary" href="<?= e($calendarFeedUrl) ?>">Jetzt herunterladen</a>
  </div>
  <p class="muted" style="margin-top:0.5rem;">iPhone/iPad/Mac: "Zum Kalender hinzufügen". Android/Google Kalender: "Zu Google Kalender hinzufügen" — Androids Google-Kalender-App unterstützt den ersten Weg oft nicht direkt.</p>
  <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Neue Adresse erzeugen? Die alte funktioniert danach nicht mehr und muss in der Kalender-App neu eingetragen werden.');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="regenerate_calendar_token">
    <button type="submit" class="btn danger small">Adresse neu erzeugen</button>
  </form>
</div>

<div class="card">
  <h2>Passwort ändern</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="password">
    <label for="current_password">Aktuelles Passwort</label>
    <input type="password" id="current_password" name="current_password" required>
    <label for="new_password">Neues Passwort</label>
    <input type="password" id="new_password" name="new_password" required minlength="8">
    <label for="confirm_password">Neues Passwort bestätigen</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
    <button type="submit" class="btn" style="margin-top:1rem;">Passwort ändern</button>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
