<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $start = (string)($_POST['start_date'] ?? '');
        $end = (string)($_POST['end_date'] ?? '');
        $type = (string)($_POST['type'] ?? 'urlaub');
        $note = trim((string)($_POST['note'] ?? ''));

        if (!in_array($type, ['urlaub', 'krankheit', 'sonstiges'], true)) {
            $type = 'sonstiges';
        }

        if (!$start || !strtotime($start) || !$end || !strtotime($end) || $end < $start) {
            flash('error', 'Bitte einen gültigen Zeitraum angeben (Ende darf nicht vor Beginn liegen).');
            redirect('/absences.php');
        }

        db()->prepare(
            'INSERT INTO absences (user_id, start_date, end_date, type, note, created_by) VALUES (:u, :s, :e, :t, :n, :u)'
        )->execute(['u' => $user['id'], 's' => $start, 'e' => $end, 't' => $type, 'n' => $note ?: null]);
        flash('success', 'Abwesenheit wurde eingetragen.');
        redirect('/absences.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['absence_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM absences WHERE id = :id AND user_id = :u');
        $stmt->execute(['id' => $id, 'u' => $user['id']]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Abwesenheit wurde gelöscht.' : 'Eintrag nicht gefunden.');
        redirect('/absences.php');
    }
}

$stmt = db()->prepare('SELECT * FROM absences WHERE user_id = :u ORDER BY start_date DESC');
$stmt->execute(['u' => $user['id']]);
$absences = $stmt->fetchAll();
$today = date('Y-m-d');

require __DIR__ . '/partials/header.php';
?>
<h1>Abwesenheiten</h1>

<div class="card">
  <h2>Abwesenheit eintragen</h2>
  <p class="muted">Urlaub oder Krankheit eintragen — der Admin sieht das direkt bei der Planung, und du bekommst in diesem Zeitraum keine E-Mails über Planänderungen.</p>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
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
    <input type="text" id="note" name="note" placeholder="z.B. nur eingeschränkt erreichbar">
    <button type="submit" class="btn" style="margin-top:1rem;">Eintragen</button>
  </form>
</div>

<div class="card">
  <h2>Meine Abwesenheiten</h2>
  <?php if (!$absences): ?>
    <p class="muted">Noch keine Abwesenheiten eingetragen.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Zeitraum</th><th>Art</th><th>Notiz</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($absences as $a): ?>
      <?php $active = $today >= $a['start_date'] && $today <= $a['end_date']; ?>
      <tr>
        <td data-label="Zeitraum">
          <span class="mono"><?= e(formatDateDe($a['start_date'])) ?>&ndash;<?= e(formatDateDe($a['end_date'])) ?></span>
          <?php if ($active): ?> <span class="badge today">Aktiv</span><?php endif; ?>
        </td>
        <td data-label="Art"><?= e(absenceTypeLabelDe($a['type'])) ?></td>
        <td data-label="Notiz"><?= $a['note'] ? e($a['note']) : '<span class="muted">—</span>' ?></td>
        <td data-label="Aktion">
          <form class="inline" method="post" onsubmit="return confirm('Abwesenheit löschen?');">
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

<?php require __DIR__ . '/partials/footer.php'; ?>
