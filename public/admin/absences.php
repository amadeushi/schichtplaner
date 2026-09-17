<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $start = (string)($_POST['start_date'] ?? '');
        $end = (string)($_POST['end_date'] ?? '');
        $type = (string)($_POST['type'] ?? 'urlaub');
        $note = trim((string)($_POST['note'] ?? ''));

        if (!in_array($type, ['urlaub', 'krankheit', 'sonstiges'], true)) {
            $type = 'sonstiges';
        }

        $targetStmt = db()->prepare('SELECT id FROM users WHERE id = :id AND active = 1');
        $targetStmt->execute(['id' => $targetId]);
        if (!$targetStmt->fetch()) {
            flash('error', 'Mitarbeiter nicht gefunden.');
            redirect('/admin/absences.php');
        }

        if (!$start || !strtotime($start) || !$end || !strtotime($end) || $end < $start) {
            flash('error', 'Bitte einen gültigen Zeitraum angeben (Ende darf nicht vor Beginn liegen).');
            redirect('/admin/absences.php');
        }

        db()->prepare(
            'INSERT INTO absences (user_id, start_date, end_date, type, note, created_by) VALUES (:u, :s, :e, :t, :n, :by)'
        )->execute(['u' => $targetId, 's' => $start, 'e' => $end, 't' => $type, 'n' => $note ?: null, 'by' => $user['id']]);
        flash('success', 'Abwesenheit wurde eingetragen.');
        redirect('/admin/absences.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['absence_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM absences WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Abwesenheit wurde gelöscht.' : 'Eintrag nicht gefunden.');
        redirect('/admin/absences.php');
    }
}

$employees = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

$stmt = db()->query(
    "SELECT a.*, u.name AS user_name FROM absences a JOIN users u ON u.id = a.user_id
     ORDER BY a.end_date < date('now'), a.start_date"
);
$absences = $stmt->fetchAll();
$today = date('Y-m-d');

require __DIR__ . '/../partials/header.php';
?>
<h1>Abwesenheiten</h1>
<p class="muted" style="color:var(--surround-ink-soft);">Wer hier als abwesend eingetragen ist, wird im <a href="/admin/shifts.php" style="color:var(--surround-ink);text-decoration:underline;">Schichtplan</a> und im <a href="/admin/calendar.php" style="color:var(--surround-ink);text-decoration:underline;">Kalender</a> markiert und bekommt währenddessen keine E-Mails über Planänderungen.</p>

<div class="card">
  <h2>Abwesenheit eintragen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <label for="user_id">Mitarbeiter</label>
    <select id="user_id" name="user_id" required>
      <option value="">Bitte wählen</option>
      <?php foreach ($employees as $e): ?>
        <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="grid-2">
      <div>
        <label for="start_date">Von</label>
        <input type="date" id="start_date" name="start_date" required>
      </div>
      <div>
        <label for="end_date">Bis</label>
        <input type="date" id="end_date" name="end_date" required>
      </div>
    </div>
    <label for="type">Art</label>
    <select id="type" name="type">
      <option value="urlaub">Urlaub</option>
      <option value="krankheit">Krankheit</option>
      <option value="sonstiges">Sonstiges</option>
    </select>
    <label for="note">Notiz (optional)</label>
    <input type="text" id="note" name="note">
    <button type="submit" class="btn" style="margin-top:1rem;">Eintragen</button>
  </form>
</div>

<div class="card">
  <h2>Alle Abwesenheiten</h2>
  <?php if (!$absences): ?>
    <p class="muted">Noch keine Abwesenheiten eingetragen.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Mitarbeiter</th><th>Zeitraum</th><th>Art</th><th>Notiz</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($absences as $a): ?>
      <?php
        $active = $today >= $a['start_date'] && $today <= $a['end_date'];
        $past = $a['end_date'] < $today;
      ?>
      <tr style="<?= $past ? 'opacity:0.6;' : '' ?>">
        <td data-label="Mitarbeiter"><?= e($a['user_name']) ?></td>
        <td data-label="Zeitraum">
          <span class="mono"><?= e(formatDateDe($a['start_date'])) ?>&ndash;<?= e(formatDateDe($a['end_date'])) ?></span>
          <?php if ($active): ?> <span class="badge today">Aktiv</span><?php endif; ?>
        </td>
        <td data-label="Art"><?= e(absenceTypeLabelDe($a['type'])) ?></td>
        <td data-label="Notiz"><?= $a['note'] ? e($a['note']) : '<span class="muted">—</span>' ?></td>
        <td data-label="Aktion">
          <form class="inline" method="post" onsubmit="return confirm('Abwesenheit von <?= e($a['user_name']) ?> löschen?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="absence_id" value="<?= (int)$a['id'] ?>">
            <button type="submit" class="btn small danger">Löschen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
