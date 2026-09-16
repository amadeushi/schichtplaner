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
