<?php
require __DIR__ . '/../app/bootstrap.php';

if (currentUser()) {
    redirect('/index.php');
}

$smsAvailable = SmsClient::enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $viaSms = $smsAvailable && (string)($_POST['channel'] ?? '') === 'sms';
    $reset = createPasswordReset((string)($_POST['email'] ?? ''), clientIp());

    // Immer dieselbe Antwort - ob die Adresse ein Konto hat, das Limit erreicht ist oder alles klappt,
    // darf von außen nicht zu erkennen sein. Auch nicht an der Antwortzeit: den Versand (Mailserver,
    // SMS-Gateway, dauert Sekunden) erledigen wir erst NACH der Antwort. Die Session wird vorher
    // freigegeben, sonst würde die Anmeldeseite auf das Ende dieses Requests warten.
    flash('success', 'Wenn zu dieser E-Mail-Adresse ein Konto besteht, ist der Link zum Zurücksetzen unterwegs. Er gilt ' . PASSWORD_RESET_TTL_MINUTES . ' Minuten.');
    session_write_close();
    header('Location: /login.php');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    if ($reset !== null) {
        (new Notifier($config['smtp']))->passwordResetLink($reset['user'], $reset['token'], $viaSms);
    }
    exit;
}

$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:380px;margin:3rem auto;">
  <h1>Passwort vergessen</h1>
  <p class="muted">Gib die E-Mail-Adresse deines Kontos ein. Du bekommst einen Link, mit dem du ein neues Passwort vergibst.</p>
  <form method="post">
    <?= csrfField() ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required autofocus autocomplete="email">
    <?php if ($smsAvailable): ?>
      <label class="checkbox-field"><input type="radio" name="channel" value="email" checked> Link per E-Mail</label>
      <label class="checkbox-field"><input type="radio" name="channel" value="sms"> Link per SMS an die hinterlegte Mobilnummer</label>
    <?php endif; ?>
    <button type="submit" class="btn" style="margin-top:1.25rem;width:100%;">Link anfordern</button>
  </form>
  <p class="muted" style="margin-top:1rem;"><a href="/login.php">Zurück zur Anmeldung</a></p>
  <p class="muted">Kommt nichts an oder ist keine Mobilnummer hinterlegt? Dann wende dich an deinen Admin.</p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
