<?php
require __DIR__ . '/../app/bootstrap.php';

if (currentUser()) {
    redirect('/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip = clientIp();

    if (isLoginLocked($email, $ip)) {
        $error = 'Zu viele Fehlversuche. Bitte in ' . LOGIN_LOCKOUT_MINUTES . ' Minuten erneut versuchen.';
    } elseif (attemptLogin($email, $password)) {
        logLoginAttempt($email, $ip, true);
        redirect('/index.php');
    } else {
        logLoginAttempt($email, $ip, false);
        $error = 'E-Mail oder Passwort ist falsch.';
    }
}

$user = null;
require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:380px;margin:3rem auto;text-align:center;">
  <div class="zeus-frame">
    <img src="/assets/zeus.webp" alt="Zeus" width="96" height="96">
  </div>
  <h1 style="text-align:left;">Anmelden</h1>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrfField() ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required autofocus>
    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="btn" style="margin-top:1.25rem;width:100%;">Anmelden</button>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
