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

    if ($action === 'save_notice') {
        setSetting('urgent_notice_enabled', isset($_POST['urgent_notice_enabled']) ? '1' : '0');
        setSetting('urgent_notice_text', trim((string)($_POST['urgent_notice_text'] ?? '')));
        flash('success', 'Mitteilung gespeichert.');
        redirect('/admin/settings.php');
    }

    if ($action === 'test_webhook') {
        $webhook = new Webhook();
        $ok = $webhook->send('webhook.test', ['message' => 'Testnachricht vom Schichtplaner', 'triggered_by' => $user['email']]);
        flash($ok ? 'success' : 'error', $ok ? 'Test-Webhook wurde gesendet.' : 'Test-Webhook fehlgeschlagen. Bitte URL und Erreichbarkeit prüfen (siehe Log unten).');
        redirect('/admin/settings.php');
    }

    if ($action === 'save_sms') {
        $rawKey = trim((string)($_POST['sms_api_key'] ?? ''));
        if ($rawKey !== '') {
            $rawKey = (string)preg_replace('/^Bearer\s+/i', '', $rawKey);
            if (!preg_match('/^[\x21-\x7E]{8,512}$/', $rawKey)) {
                flash('error', 'Der Schlüssel sieht nicht gültig aus (mindestens 8 Zeichen, keine Leerzeichen oder Zeilenumbrüche). Bitte genau so einfügen, wie er auf dem SMS-Pi steht.');
                redirect('/admin/settings.php');
            }
            setSetting('sms_api_key', $rawKey);
        }
        $enable = isset($_POST['sms_enabled']);
        if ($enable && $rawKey === '' && SmsClient::keySource() === null) {
            flash('error', 'Zum Aktivieren bitte zuerst den API-Schlüssel des SMS-Gateways eingeben.');
            redirect('/admin/settings.php');
        }
        setSetting('sms_enabled', $enable ? '1' : '0');
        flash('success', 'SMS-Einstellungen gespeichert' . ($rawKey !== '' ? ' (neuer Schlüssel hinterlegt)' : '') . '. Mit "Verbindung prüfen" lässt sich der Schlüssel testen.');
        redirect('/admin/settings.php');
    }

    if ($action === 'clear_sms_key') {
        setSetting('sms_api_key', '');
        setSetting('sms_enabled', '0');
        flash('success', 'API-Schlüssel entfernt, SMS-Versand ist ausgeschaltet.');
        redirect('/admin/settings.php');
    }

    if ($action === 'sms_health') {
        if (SmsClient::keySource() === null) {
            flash('error', 'Bitte zuerst den API-Schlüssel des SMS-Gateways eingeben.');
        } else {
            $health = SmsClient::health();
            flash($health['ok'] ? 'success' : 'error', $health['ok']
                ? 'SMS-Gateway erreichbar, Schlüssel gültig' . ($health['project'] ? ' (Projekt: ' . $health['project'] . ')' : '') . '. Es wurde keine SMS gesendet.'
                : 'SMS-Gateway nicht nutzbar: ' . $health['error']);
        }
        redirect('/admin/settings.php');
    }

    if ($action === 'sms_test') {
        if (!SmsClient::enabled()) {
            flash('error', 'SMS ist nicht aktiviert (Schlüssel eingeben und "SMS-Versand aktivieren" ankreuzen).');
        } elseif (empty($user['phone'])) {
            flash('error', 'Bitte zuerst deine Mobilnummer im Profil eintragen.');
        } else {
            $res = SmsClient::enqueue((int)$user['id'], (string)$user['phone'], 'test', 'Testnachricht vom Schichtplaner');
            flash($res['status'] === 'failed' ? 'error' : 'success', match ($res['status']) {
                'accepted' => 'Test-SMS wurde vom Gateway angenommen und geht an ' . maskPhone((string)$user['phone']) . '.',
                'queued' => 'Test-SMS ist in der Warteschlange (' . ($res['last_error'] ?? 'Gateway gerade nicht erreichbar') . ') und wird erneut versucht.',
                default => 'Test-SMS fehlgeschlagen: ' . ($res['last_error'] ?? 'unbekannter Fehler'),
            });
        }
        redirect('/admin/settings.php');
    }

    if ($action === 'save_email_template') {
        $key = (string)($_POST['template_key'] ?? '');
        if (!array_key_exists($key, Notifier::TEMPLATES)) {
            flash('error', 'Unbekannte Vorlage.');
            redirect('/admin/settings.php');
        }
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        db()->prepare(
            "INSERT INTO email_templates (template_key, subject, body, updated_at) VALUES (:k, :s, :b, datetime('now'))
             ON CONFLICT(template_key) DO UPDATE SET subject = excluded.subject, body = excluded.body, updated_at = excluded.updated_at"
        )->execute(['k' => $key, 's' => $subject, 'b' => $body]);
        flash('success', 'Vorlage "' . Notifier::TEMPLATES[$key]['label'] . '" gespeichert.');
        redirect('/admin/settings.php');
    }

    if ($action === 'reset_email_template') {
        $key = (string)($_POST['template_key'] ?? '');
        db()->prepare('DELETE FROM email_templates WHERE template_key = :k')->execute(['k' => $key]);
        flash('success', 'Vorlage auf Standard zurückgesetzt.');
        redirect('/admin/settings.php');
    }

    if ($action === 'save_log_max') {
        $max = max(10, (int)($_POST['log_max_entries'] ?? 1000));
        setSetting('notification_log_max_entries', (string)$max);
        flash('success', "Maximale Protokolllänge auf $max Einträge gesetzt.");
        redirect('/admin/settings.php');
    }

    if ($action === 'cleanup_log') {
        $max = (int)setting('notification_log_max_entries', '1000');
        $stmt = db()->prepare(
            'DELETE FROM notification_log WHERE id NOT IN (SELECT id FROM notification_log ORDER BY id DESC LIMIT :max)'
        );
        $stmt->bindValue(':max', $max, PDO::PARAM_INT);
        $stmt->execute();
        $deleted = $stmt->rowCount();
        flash('success', $deleted > 0 ? "$deleted alte Einträge gelöscht." : 'Keine alten Einträge zum Löschen gefunden.');
        redirect('/admin/settings.php');
    }
}

$logs = db()->query('SELECT * FROM notification_log ORDER BY id DESC LIMIT 25')->fetchAll();
$totalLogCount = (int)db()->query('SELECT COUNT(*) FROM notification_log')->fetchColumn();
$logMaxEntries = (int)setting('notification_log_max_entries', '1000');
$overLimitCount = max(0, $totalLogCount - $logMaxEntries);

$templateOverrides = [];
foreach (db()->query('SELECT * FROM email_templates') as $row) {
    $templateOverrides[$row['template_key']] = $row;
}

require __DIR__ . '/../partials/header.php';
?>
<h1>Einstellungen</h1>

<div class="card">
  <h2>Dringende Mitteilung</h2>
  <p class="muted">Wird farblich hervorgehoben über dem Wochenplan angezeigt (Mein Plan und Schichtplan) — sichtbar für alle Mitarbeiter, bleibt beim Wechseln der Kalenderwoche stehen.</p>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_notice">
    <label style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="urgent_notice_enabled" style="width:auto;" <?= setting('urgent_notice_enabled', '0') === '1' ? 'checked' : '' ?>>
      Mitteilung anzeigen
    </label>
    <label for="urgent_notice_text">Text</label>
    <textarea id="urgent_notice_text" name="urgent_notice_text" placeholder="z.B. Küche heute wegen Wasserschaden geschlossen."><?= e(setting('urgent_notice_text', '')) ?></textarea>
    <button type="submit" class="btn" style="margin-top:1rem;">Speichern</button>
  </form>
</div>

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

<?php
  $smsOn = SmsClient::enabled();
  $smsStats = $smsOn ? SmsClient::stats() : null;
  $smsKeySource = SmsClient::keySource();
  $smsKeyMask = SmsClient::maskedKey();
?>
<div class="card">
  <h2>SMS-Versand</h2>
  <p class="muted">
    SMS gehen unter denselben Bedingungen wie E-Mails an Mitarbeiter mit Mobilnummer (Profil bzw. Mitarbeiter-Verwaltung), nicht während einer Abwesenheit, und bewusst kurz (höchstens 70 Zeichen, ohne Schichtdetails bei der Sammelmeldung).
    Aktueller Status: <strong><?= $smsOn ? 'aktiviert' : (SmsClient::enabledFlag() ? 'eingeschaltet, aber ohne Schlüssel' : 'ausgeschaltet') ?></strong>.
  </p>
  <form method="post" autocomplete="off">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_sms">
    <label style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="sms_enabled" style="width:auto;" <?= SmsClient::enabledFlag() ? 'checked' : '' ?>>
      SMS-Versand aktivieren
    </label>
    <label for="sms_api_key">API-Schlüssel des SMS-Gateways</label>
    <input type="password" id="sms_api_key" name="sms_api_key" autocomplete="new-password" spellcheck="false" autocapitalize="off"
           placeholder="<?= $smsKeyMask ? 'Hinterlegt (' . e($smsKeyMask) . ') — nur zum Ändern neu eingeben' : 'Schlüssel hier einfügen' ?>">
    <p class="muted" style="margin-top:0.4rem;">
      Der Schlüssel wird nur gespeichert und nie wieder angezeigt (nur die letzten 4 Zeichen).
      <?php if ($smsKeySource === 'settings'): ?>Quelle: hier hinterlegt.<?php elseif ($smsKeySource === 'config'): ?>Quelle: <code>app/config.php</code> — ein hier eingegebener Schlüssel hat Vorrang.<?php else: ?>Noch kein Schlüssel hinterlegt.<?php endif; ?>
    </p>
    <button type="submit" class="btn" style="margin-top:0.75rem;">Speichern</button>
  </form>
  <?php if ($smsKeySource === 'settings'): ?>
    <form method="post" class="inline" style="margin-top:0.75rem;display:block;" onsubmit="return confirm('Hinterlegten Schlüssel entfernen und SMS-Versand ausschalten?');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="clear_sms_key">
      <button type="submit" class="btn small danger">Schlüssel entfernen</button>
    </form>
  <?php endif; ?>
  <?php if ($smsOn): ?>
    <p class="muted" style="margin-top:1rem;">
      Heute angenommen: <strong class="mono"><?= $smsStats['accepted_today'] ?></strong> &middot;
      in Warteschlange: <strong class="mono"><?= $smsStats['queued'] ?></strong> &middot;
      fehlgeschlagen (7 Tage): <strong class="mono"><?= $smsStats['failed_week'] ?></strong>
    </p>
  <?php endif; ?>
  <?php if ($smsKeySource !== null): ?>
    <div class="table-actions" style="margin-top:0.75rem;">
      <form method="post" class="inline">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="sms_health">
        <button type="submit" class="btn secondary">Verbindung prüfen</button>
      </form>
      <?php if ($smsOn): ?>
      <form method="post" class="inline" onsubmit="return confirm('Eine echte Test-SMS an deine Mobilnummer senden? Sie zählt gegen das Tageslimit des Gateways.');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="sms_test">
        <button type="submit" class="btn secondary">Test-SMS an mich senden</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>E-Mail-Vorlagen</h2>
  <p class="muted">Betreff und Text jeder System-Mail lassen sich hier anpassen. Platzhalter wie <code>{{name}}</code> werden beim Versand automatisch durch den passenden Wert ersetzt. Änderungen gelten sofort für neu verschickte Mails.</p>
  <?php foreach (Notifier::TEMPLATES as $key => $tpl): ?>
    <?php $override = $templateOverrides[$key] ?? null; ?>
    <details class="tpl-editor">
      <summary>
        <?= e($tpl['label']) ?>
        <?php if ($override): ?><span class="badge confirmed">Angepasst</span><?php endif; ?>
      </summary>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_email_template">
        <input type="hidden" name="template_key" value="<?= e($key) ?>">
        <label for="tpl-subject-<?= e($key) ?>">Betreff</label>
        <input type="text" id="tpl-subject-<?= e($key) ?>" name="subject" value="<?= e($override['subject'] ?? $tpl['subject']) ?>">
        <label for="tpl-body-<?= e($key) ?>">Text</label>
        <textarea id="tpl-body-<?= e($key) ?>" name="body" rows="6"><?= e($override['body'] ?? $tpl['body']) ?></textarea>
        <p class="muted">Platzhalter:
          <?php foreach ($tpl['placeholders'] as $p): ?><code>{{<?= e($p) ?>}}</code> <?php endforeach; ?>
        </p>
        <div class="table-actions">
          <button type="submit" class="btn">Speichern</button>
          <?php if ($override): ?>
          <button type="submit" name="action" value="reset_email_template" formnovalidate class="btn secondary">Auf Standard zurücksetzen</button>
          <?php endif; ?>
        </div>
      </form>
    </details>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Letzte Benachrichtigungen (Protokoll)</h2>
  <p class="muted">Zeigt die letzten 25 von insgesamt <strong><?= $totalLogCount ?></strong> Einträgen.</p>

  <form method="post" style="display:flex;align-items:flex-end;gap:0.75rem;flex-wrap:wrap;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_log_max">
    <div>
      <label for="log_max_entries">Maximale Protokolllänge (Einträge)</label>
      <input type="number" id="log_max_entries" name="log_max_entries" min="10" step="10" value="<?= $logMaxEntries ?>" style="width:8rem;">
    </div>
    <button type="submit" class="btn secondary" style="margin-top:0.85rem;">Speichern</button>
  </form>

  <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('<?= $overLimitCount ?> älteste Einträge jetzt endgültig löschen?');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="cleanup_log">
    <button type="submit" class="btn danger" <?= $overLimitCount === 0 ? 'disabled' : '' ?>>
      Alte Einträge löschen<?= $overLimitCount > 0 ? " ($overLimitCount über dem Limit)" : '' ?>
    </button>
  </form>

  <?php if (!$logs): ?>
    <p class="muted" style="margin-top:1rem;">Noch keine Benachrichtigungen gesendet.</p>
  <?php else: ?>
  <table style="margin-top:1rem;">
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
