<?php
require __DIR__ . '/../app/bootstrap.php';

// Der Link steht in der URL: weder an andere Seiten weitergeben (Referer) noch zwischenspeichern.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$reset = findValidPasswordReset($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    // Nur das Absenden verbraucht den Link, nicht das bloße Öffnen - Link-Vorschauen in
    // Messengern und Mail-Scanner rufen die Adresse oft schon vorher auf.
    if (!$reset) {
        flash('error', 'Dieser Link ist ungültig oder abgelaufen. Fordere bitte einen neuen an.');
        redirect('/forgot_password.php');
    }

    $error = passwordPolicyError((string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
    if ($error !== null) {
        flash('error', $error);
        redirect('/reset_password.php?t=' . urlencode($token));
    }

    if (!completePasswordReset($reset, (string)$_POST['new_password'])) {
        flash('error', 'Dieser Link wurde bereits benutzt. Fordere bitte einen neuen an.');
        redirect('/forgot_password.php');
    }

    flash('success', 'Dein neues Passwort ist gesetzt. Du kannst dich jetzt anmelden.');
    redirect('/login.php');
}

$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:380px;margin:3rem auto;">
  <?php if (!$reset): ?>
    <h1>Link ungültig</h1>
    <p class="muted">Dieser Link ist ungültig, abgelaufen oder wurde schon benutzt. Links gelten <?= PASSWORD_RESET_TTL_MINUTES ?> Minuten und nur einmal.</p>
    <a href="/forgot_password.php" class="btn" style="margin-top:1rem;width:100%;">Neuen Link anfordern</a>
  <?php else: ?>
    <h1>Neues Passwort vergeben</h1>
    <p class="muted">Für das Konto <strong><?= e($reset['email']) ?></strong>.</p>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <label for="new_password">Neues Passwort</label>
      <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" autofocus>
      <label for="confirm_password">Neues Passwort bestätigen</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      <p class="muted" style="margin-top:0.5rem;">Mindestens 8 Zeichen.</p>
      <button type="submit" class="btn" style="margin-top:1rem;width:100%;">Passwort speichern</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
