<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        setSetting('app_name', trim((string)($_POST['app_name'] ?? 'Schichtplaner')) ?: 'Schichtplaner');
        setSetting('webhook_url', trim((string)($_POST['webhook_url'] ?? '')));
        setSetting('webhook_enabled', isset($_POST['webhook_enabled']) ? '1' : '0');
        flash('success', 'Einstellungen gespeichert.');
        redirect('/admin/settings.php');
    }

    if ($action === 'test_webhook') {
        $webhook = new Webhook();
        $ok = $webhook->send('webhook.test', ['message' => 'Testnachricht vom Schichtplaner', 'triggered_by' => $user['email']]);
        flash($ok ? 'success' : 'error', $ok ? 'Test-Webhook wurde gesendet.' : 'Test-Webhook fehlgeschlagen. Bitte URL und Erreichbarkeit prüfen (siehe Log unten).');
        redirect('/admin/settings.php');
    }
}

$logs = db()->query('SELECT * FROM notification_log ORDER BY id DESC LIMIT 25')->fetchAll();

require __DIR__ . '/../partials/header.php';
?>
<h1>Einstellungen</h1>

<div class="card">
  <h2>Allgemein</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <label for="app_name">Name der Anwendung</label>
    <input type="text" id="app_name" name="app_name" value="<?= e(setting('app_name', 'Schichtplaner')) ?>">

    <h2 style="margin-top:1.5rem;">n8n / Webhook-Benachrichtigungen</h2>
    <p class="muted">Bei jedem Ereignis (neue Bewerbung, Entscheidung, neue Schicht) wird ein JSON-POST an diese URL gesendet. In n8n kannst du daraus z.B. Telegram-, Slack- oder zusätzliche E-Mail-Benachrichtigungen bauen. Empfohlen: ein "Webhook"-Trigger-Node in n8n, dessen Produktions-URL du hier einträgst.</p>
    <label style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="webhook_enabled" style="width:auto;" <?= setting('webhook_enabled', '0') === '1' ? 'checked' : '' ?>>
      Webhook-Benachrichtigungen aktivieren
    </label>
    <label for="webhook_url">n8n Webhook-URL</label>
    <input type="url" id="webhook_url" name="webhook_url" value="<?= e(setting('webhook_url', '')) ?>" placeholder="https://n8n.example.com/webhook/schichtplaner">

    <button type="submit" class="btn" style="margin-top:1rem;">Speichern</button>
  </form>
  <form method="post" style="margin-top:0.75rem;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="test_webhook">
    <button type="submit" class="btn secondary">Test-Webhook senden</button>
  </form>
</div>

<div class="card">
  <h2>E-Mail-Versand</h2>
  <p class="muted">
    SMTP wird in <code>app/config.php</code> konfiguriert (nicht über die Oberfläche, da dort auch das Passwort hinterlegt wird).
    Aktueller Status: <strong><?= !empty($config['smtp']['enabled']) ? 'aktiviert' : 'deaktiviert' ?></strong>,
    Host: <code><?= e($config['smtp']['host'] ?? '-') ?></code>
  </p>
</div>

<div class="card">
  <h2>Letzte Benachrichtigungen (Protokoll)</h2>
  <?php if (!$logs): ?>
    <p class="muted">Noch keine Benachrichtigungen gesendet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Zeit</th><th>Ereignis</th><th>Kanal</th><th>Empfänger</th><th>Erfolg</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td data-label="Zeit"><?= e($l['created_at']) ?></td>
        <td data-label="Ereignis"><?= e($l['event_type']) ?></td>
        <td data-label="Kanal"><?= e($l['channel']) ?></td>
        <td data-label="Empfänger"><?= e($l['recipient'] ?? '-') ?></td>
        <td data-label="Erfolg"><?= $l['success'] ? 'OK' : 'Fehler: ' . e($l['error'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
