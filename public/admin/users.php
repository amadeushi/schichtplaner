<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

function generatePassword(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
}

$generatedPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte gültigen Namen und E-Mail angeben.');
            redirect('/admin/users.php');
        }

        $tempPassword = generatePassword();
        try {
            db()->prepare(
                'INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (:n, :e, :h, :r, 1)'
            )->execute([
                'n' => $name, 'e' => $email,
                'h' => password_hash($tempPassword, PASSWORD_DEFAULT), 'r' => $role,
            ]);
            $generatedPassword = ['email' => $email, 'password' => $tempPassword];
            $notifier = new Notifier($config['smtp']);
            $emailed = $notifier->accountCreated(['name' => $name, 'email' => $email], $tempPassword);
            flash('success', ($emailed
                ? "Mitarbeiter angelegt. Zugangsdaten wurden an $email verschickt."
                : "Mitarbeiter angelegt. E-Mail-Versand ist nicht aktiv oder fehlgeschlagen — bitte manuell mitteilen.")
                . " Vorläufiges Passwort (nur zur Sicherheit hier vermerkt): $tempPassword");
        } catch (PDOException $e) {
            flash('error', str_contains($e->getMessage(), 'UNIQUE') ? 'Diese E-Mail ist bereits vergeben.' : 'Fehler beim Anlegen.');
        }
        redirect('/admin/users.php');
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === (int)$user['id']) {
            flash('error', 'Du kannst dich nicht selbst deaktivieren.');
            redirect('/admin/users.php');
        }
        $stmt = db()->prepare('UPDATE users SET active = 1 - active WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Status aktualisiert.' : 'Mitarbeiter nicht gefunden.');
        redirect('/admin/users.php');
    }

    if ($action === 'toggle_time_tracking') {
        $id = (int)($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('UPDATE users SET time_tracking_enabled = 1 - time_tracking_enabled WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Zeiterfassung aktualisiert.' : 'Mitarbeiter nicht gefunden.');
        redirect('/admin/users.php');
    }

    if ($action === 'reset_password') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetStmt = db()->prepare('SELECT name, email FROM users WHERE id = :id');
        $targetStmt->execute(['id' => $id]);
        $target = $targetStmt->fetch();

        if (!$target) {
            flash('error', 'Mitarbeiter nicht gefunden.');
            redirect('/admin/users.php');
        }

        $tempPassword = generatePassword();
        db()->prepare('UPDATE users SET password_hash = :h, must_change_password = 1 WHERE id = :id')
            ->execute(['h' => password_hash($tempPassword, PASSWORD_DEFAULT), 'id' => $id]);

        $notifier = new Notifier($config['smtp']);
        $emailed = $notifier->passwordWasReset($target, $tempPassword);
        flash('success', ($emailed
            ? "Passwort zurückgesetzt. Neue Zugangsdaten wurden an {$target['email']} verschickt."
            : 'Passwort zurückgesetzt. E-Mail-Versand ist nicht aktiv oder fehlgeschlagen — bitte manuell mitteilen.')
            . " Vorläufiges Passwort (nur zur Sicherheit hier vermerkt): $tempPassword");
        redirect('/admin/users.php');
    }
}

$users = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();

require __DIR__ . '/../partials/header.php';
?>
<h1>Mitarbeiter verwalten</h1>

<div class="card">
  <h2>Neuen Mitarbeiter anlegen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid-2">
      <div>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required>
      </div>
      <div>
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required>
      </div>
    </div>
    <label for="role">Rolle</label>
    <select id="role" name="role">
      <option value="employee">Mitarbeiter</option>
      <option value="admin">Administrator</option>
    </select>
    <button type="submit" class="btn" style="margin-top:1rem;">Anlegen</button>
  </form>
  <p class="muted" style="margin-top:0.75rem;">Es wird ein vorläufiges Passwort generiert und per E-Mail verschickt (zur Sicherheit auch hier einmalig angezeigt, falls der Versand fehlschlägt). Der Mitarbeiter muss es bei der ersten Anmeldung ändern, bevor er die App weiter nutzen kann.</p>
</div>

<div class="card">
<h2>Alle Mitarbeiter</h2>
<table>
  <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th>Zeiterfassung</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td data-label="Name"><?= e($u['name']) ?></td>
      <td data-label="E-Mail"><?= e($u['email']) ?></td>
      <td data-label="Rolle"><?= $u['role'] === 'admin' ? 'Administrator' : 'Mitarbeiter' ?></td>
      <td data-label="Status"><span class="badge <?= $u['active'] ? 'open' : 'closed' ?>"><?= $u['active'] ? 'Aktiv' : 'Deaktiviert' ?></span></td>
      <td data-label="Zeiterfassung">
        <form class="inline" method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle_time_tracking">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn small <?= $u['time_tracking_enabled'] ? '' : 'secondary' ?>"><?= $u['time_tracking_enabled'] ? 'Aktiv' : 'Gesperrt' ?></button>
        </form>
      </td>
      <td data-label="Aktion">
        <form class="inline" method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn small secondary">Passwort zurücksetzen</button>
        </form>
        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
        <form class="inline" method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle_active">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn small <?= $u['active'] ? 'danger' : '' ?>"><?= $u['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
