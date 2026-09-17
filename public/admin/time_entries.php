<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

$employeeId = (int)($_GET['user_id'] ?? $_POST['employee_id'] ?? 0);
$monthParam = (string)($_GET['month'] ?? $_POST['month'] ?? '');
$monthKey = (preg_match('/^\d{4}-\d{2}$/', $monthParam)) ? $monthParam : date('Y-m');

$empStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
$empStmt->execute(['id' => $employeeId]);
$employee = $empStmt->fetch();

if (!$employee) {
    flash('error', 'Mitarbeiter nicht gefunden.');
    redirect('/admin/time_tracking.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    $backUrl = '/admin/time_entries.php?user_id=' . $employeeId . '&month=' . urlencode($monthKey);

    if ($action === 'add') {
        $date = (string)($_POST['date'] ?? '');
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));

        if (!$date || !$start || !$end || $end <= $start) {
            flash('error', 'Bitte Datum sowie Start- und Endzeit angeben (Ende muss nach Start liegen).');
            redirect($backUrl);
        }

        db()->prepare('INSERT INTO time_entries (user_id, clock_in, clock_out, note, created_by) VALUES (:u, :in, :out, :note, :by)')
            ->execute(['u' => $employeeId, 'in' => "$date $start:00", 'out' => "$date $end:00", 'note' => $note ?: null, 'by' => $user['id']]);
        flash('success', 'Eintrag wurde hinzugefügt.');
        redirect($backUrl);
    }

    if ($action === 'edit') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $inDate = (string)($_POST['in_date'] ?? '');
        $inTime = (string)($_POST['in_time'] ?? '');
        $outDate = (string)($_POST['out_date'] ?? '');
        $outTime = (string)($_POST['out_time'] ?? '');

        if (!$inDate || !$inTime) {
            flash('error', 'Bitte mindestens Start-Datum und -Zeit angeben.');
            redirect($backUrl);
        }

        $clockIn = "$inDate $inTime:00";
        $clockOut = ($outDate && $outTime) ? "$outDate $outTime:00" : null;

        if ($clockOut !== null && $clockOut <= $clockIn) {
            flash('error', 'Ende muss nach dem Start liegen.');
            redirect($backUrl);
        }

        $stmt = db()->prepare('UPDATE time_entries SET clock_in = :in, clock_out = :out WHERE id = :id AND user_id = :u');
        $stmt->execute(['in' => $clockIn, 'out' => $clockOut, 'id' => $id, 'u' => $employeeId]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Eintrag aktualisiert.' : 'Eintrag nicht gefunden.');
        redirect($backUrl);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM time_entries WHERE id = :id AND user_id = :u');
        $stmt->execute(['id' => $id, 'u' => $employeeId]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Eintrag gelöscht.' : 'Eintrag nicht gefunden.');
        redirect($backUrl);
    }
}

[$monthStart, $monthEnd] = monthBounds($monthKey);
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
$monthLabel = monthNameDe($monthStart);

$entriesStmt = db()->prepare(
    'SELECT * FROM time_entries WHERE user_id = :u AND clock_in >= :start AND clock_in < :end ORDER BY clock_in'
);
$entriesStmt->execute(['u' => $employeeId, 'start' => $monthStart, 'end' => $monthEnd]);
$entries = $entriesStmt->fetchAll();

$totalSeconds = 0;
foreach ($entries as $en) {
    if ($en['clock_out']) {
        $totalSeconds += strtotime($en['clock_out']) - strtotime($en['clock_in']);
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editEntry = null;
foreach ($entries as $e) {
    if ((int)$e['id'] === $editId) {
        $editEntry = $e;
        break;
    }
}

require __DIR__ . '/../partials/header.php';
?>
<h1>Arbeitszeiten: <?= e($employee['name']) ?></h1>

<div class="week-nav">
  <a class="btn secondary small" href="?user_id=<?= $employeeId ?>&month=<?= e($prevMonth) ?>">&lsaquo; Vorheriger Monat</a>
  <div class="week-nav-label"><strong><?= e($monthLabel) ?></strong></div>
  <a class="btn secondary small" href="?user_id=<?= $employeeId ?>&month=<?= e($nextMonth) ?>">Nächster Monat &rsaquo;</a>
</div>

<div class="card">
  <p class="muted">Gesamtstunden diesen Monat: <strong><?= e(formatDurationHm($totalSeconds)) ?></strong></p>
  <div class="table-actions">
    <a class="btn secondary small" href="/admin/time_tracking.php">&lsaquo; Zurück zur Übersicht</a>
    <a class="btn secondary small" href="/admin/export_hours.php?month=<?= e($monthKey) ?>&user_id=<?= $employeeId ?>">Exportieren (CSV)</a>
  </div>
</div>

<?php if ($editEntry): ?>
<div class="card">
  <h2>Eintrag bearbeiten</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="entry_id" value="<?= (int)$editEntry['id'] ?>">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <input type="hidden" name="month" value="<?= e($monthKey) ?>">
    <div class="grid-2">
      <div>
        <label for="in_date">Start-Datum</label>
        <input type="date" id="in_date" name="in_date" value="<?= e(date('Y-m-d', strtotime($editEntry['clock_in']))) ?>" required>
      </div>
      <div>
        <label for="in_time">Start-Zeit</label>
        <input type="time" id="in_time" name="in_time" value="<?= e(date('H:i', strtotime($editEntry['clock_in']))) ?>" required>
      </div>
    </div>
    <div class="grid-2">
      <div>
        <label for="out_date">End-Datum</label>
        <input type="date" id="out_date" name="out_date" value="<?= $editEntry['clock_out'] ? e(date('Y-m-d', strtotime($editEntry['clock_out']))) : '' ?>">
      </div>
      <div>
        <label for="out_time">End-Zeit</label>
        <input type="time" id="out_time" name="out_time" value="<?= $editEntry['clock_out'] ? e(date('H:i', strtotime($editEntry['clock_out']))) : '' ?>">
      </div>
    </div>
    <button type="submit" class="btn" style="margin-top:1rem;">Speichern</button>
    <a href="/admin/time_entries.php?user_id=<?= $employeeId ?>&month=<?= e($monthKey) ?>" class="btn secondary">Abbrechen</a>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Eintrag hinzufügen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
    <input type="hidden" name="month" value="<?= e($monthKey) ?>">
    <div class="grid-2">
      <div>
        <label for="date">Datum</label>
        <input type="date" id="date" name="date" required>
      </div>
      <div></div>
    </div>
    <div class="grid-2">
      <div>
        <label for="start_time">Start-Zeit</label>
        <input type="time" id="start_time" name="start_time" required>
      </div>
      <div>
        <label for="end_time">End-Zeit</label>
        <input type="time" id="end_time" name="end_time" required>
      </div>
    </div>
    <label for="note">Notiz (optional)</label>
    <input type="text" id="note" name="note">
    <button type="submit" class="btn" style="margin-top:1rem;">Hinzufügen</button>
  </form>
</div>

<div class="card">
<h2>Einträge</h2>
<?php if (!$entries): ?>
  <p class="muted">Keine Einträge in diesem Monat.</p>
<?php else: ?>
<table>
  <thead><tr><th>Datum</th><th>Von</th><th>Bis</th><th>Dauer</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($entries as $en): ?>
    <?php $duration = $en['clock_out'] ? formatDurationHm(strtotime($en['clock_out']) - strtotime($en['clock_in'])) : '-'; ?>
    <tr>
      <td data-label="Datum"><?= e(formatDateDe(date('Y-m-d', strtotime($en['clock_in'])))) ?></td>
      <td data-label="Von"><?= e(date('H:i', strtotime($en['clock_in']))) ?></td>
      <td data-label="Bis"><?= $en['clock_out'] ? e(date('H:i', strtotime($en['clock_out']))) : '-' ?></td>
      <td data-label="Dauer"><?= e($duration) ?></td>
      <td data-label="Aktion">
        <div class="table-actions">
          <a href="?user_id=<?= $employeeId ?>&month=<?= e($monthKey) ?>&edit=<?= (int)$en['id'] ?>" class="btn small secondary">Bearbeiten</a>
          <form class="inline" method="post" onsubmit="return confirm('Eintrag löschen?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="entry_id" value="<?= (int)$en['id'] ?>">
            <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
            <input type="hidden" name="month" value="<?= e($monthKey) ?>">
            <button type="submit" class="btn small danger">Löschen</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
