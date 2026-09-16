<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

if (!$user['time_tracking_enabled']) {
    require __DIR__ . '/partials/header.php';
    echo '<h1>Zeiterfassung</h1><div class="card"><p class="muted">Die Zeiterfassung ist für dein Konto nicht freigeschaltet. Bitte wende dich an den Admin.</p></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'clock_in') {
        $open = db()->prepare('SELECT id FROM time_entries WHERE user_id = :u AND clock_out IS NULL');
        $open->execute(['u' => $user['id']]);
        if ($open->fetch()) {
            flash('error', 'Du bist bereits eingestempelt.');
        } else {
            db()->prepare('INSERT INTO time_entries (user_id, clock_in, created_by) VALUES (:u, :now, :u)')
                ->execute(['u' => $user['id'], 'now' => date('Y-m-d H:i:s')]);
            flash('success', 'Eingestempelt.');
        }
        redirect('/time_tracking.php');
    }

    if ($action === 'clock_out') {
        $stmt = db()->prepare('UPDATE time_entries SET clock_out = :now WHERE user_id = :u AND clock_out IS NULL');
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'u' => $user['id']]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Ausgestempelt.' : 'Du bist aktuell nicht eingestempelt.');
        redirect('/time_tracking.php');
    }

    if ($action === 'manual_add') {
        $date = (string)($_POST['date'] ?? '');
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));

        if (!$date || !$start || !$end || $end <= $start) {
            flash('error', 'Bitte Datum sowie Start- und Endzeit angeben (Ende muss nach Start liegen).');
            redirect('/time_tracking.php');
        }

        db()->prepare('INSERT INTO time_entries (user_id, clock_in, clock_out, note, created_by) VALUES (:u, :in, :out, :note, :u)')
            ->execute(['u' => $user['id'], 'in' => "$date $start:00", 'out' => "$date $end:00", 'note' => $note ?: null]);
        flash('success', 'Eintrag wurde nachgetragen.');
        redirect('/time_tracking.php');
    }

    if ($action === 'edit_entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $inDate = (string)($_POST['in_date'] ?? '');
        $inTime = (string)($_POST['in_time'] ?? '');
        $outDate = (string)($_POST['out_date'] ?? '');
        $outTime = (string)($_POST['out_time'] ?? '');

        if (!$inDate || !$inTime) {
            flash('error', 'Bitte mindestens Start-Datum und -Zeit angeben.');
            redirect('/time_tracking.php');
        }

        $clockIn = "$inDate $inTime:00";
        $clockOut = ($outDate && $outTime) ? "$outDate $outTime:00" : null;

        if ($clockOut !== null && $clockOut <= $clockIn) {
            flash('error', 'Ende muss nach dem Start liegen.');
            redirect('/time_tracking.php');
        }

        $stmt = db()->prepare('UPDATE time_entries SET clock_in = :in, clock_out = :out WHERE id = :id AND user_id = :u');
        $stmt->execute(['in' => $clockIn, 'out' => $clockOut, 'id' => $id, 'u' => $user['id']]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Eintrag aktualisiert.' : 'Eintrag nicht gefunden.');
        redirect('/time_tracking.php');
    }

    if ($action === 'delete_entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM time_entries WHERE id = :id AND user_id = :u');
        $stmt->execute(['id' => $id, 'u' => $user['id']]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Eintrag gelöscht.' : 'Eintrag nicht gefunden.');
        redirect('/time_tracking.php');
    }
}

$openStmt = db()->prepare('SELECT * FROM time_entries WHERE user_id = :u AND clock_out IS NULL');
$openStmt->execute(['u' => $user['id']]);
$openEntry = $openStmt->fetch();

$monthKey = date('Y-m');
[$monthStart, $monthEnd] = monthBounds($monthKey);
$monthStmt = db()->prepare(
    "SELECT COALESCE(SUM(strftime('%s', clock_out) - strftime('%s', clock_in)), 0) AS total
     FROM time_entries WHERE user_id = :u AND clock_out IS NOT NULL AND clock_in >= :start AND clock_in < :end"
);
$monthStmt->execute(['u' => $user['id'], 'start' => $monthStart, 'end' => $monthEnd]);
$monthTotalSeconds = (int)$monthStmt->fetch()['total'];

$entriesStmt = db()->prepare(
    'SELECT * FROM time_entries WHERE user_id = :u ORDER BY clock_in DESC LIMIT 60'
);
$entriesStmt->execute(['u' => $user['id']]);
$entries = $entriesStmt->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editEntry = null;
if ($editId) {
    foreach ($entries as $e) {
        if ((int)$e['id'] === $editId) {
            $editEntry = $e;
            break;
        }
    }
}

require __DIR__ . '/partials/header.php';
?>
<h1>Zeiterfassung</h1>

<div class="card" style="text-align:center;padding:2rem 1.25rem;">
  <?php if ($openEntry): ?>
    <?php $sinceSeconds = time() - strtotime($openEntry['clock_in']); ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="clock_out">
      <button type="submit" class="duty-stamp on-duty" aria-label="Ausstempeln">
        <span class="duty-stamp-label">Im Dienst</span>
        <span class="duty-stamp-time mono">seit <?= e(date('H:i', strtotime($openEntry['clock_in']))) ?></span>
      </button>
    </form>
    <p class="muted" style="margin-top:1rem;">Aktuell: <strong class="mono"><?= e(formatDurationHm($sinceSeconds)) ?></strong> &middot; antippen zum Ausstempeln</p>
  <?php else: ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="clock_in">
      <button type="submit" class="duty-stamp off-duty" aria-label="Einstempeln">
        <span class="duty-stamp-label">Nicht im<br>Dienst</span>
      </button>
    </form>
    <p class="muted" style="margin-top:1rem;">Antippen zum Einstempeln</p>
  <?php endif; ?>
  <p class="muted" style="margin-top:1.25rem;border-top:1px dashed var(--ink-line);padding-top:1rem;">Diesen Monat gearbeitet: <strong class="mono" style="color:var(--ink);"><?= e(formatDurationHm($monthTotalSeconds)) ?></strong></p>
</div>

<?php if ($editEntry): ?>
<div class="card">
  <h2>Eintrag bearbeiten</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="edit_entry">
    <input type="hidden" name="entry_id" value="<?= (int)$editEntry['id'] ?>">
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
    <p class="muted">End-Datum/-Zeit leer lassen, falls noch eingestempelt.</p>
    <button type="submit" class="btn" style="margin-top:0.5rem;">Speichern</button>
    <a href="/time_tracking.php" class="btn secondary">Abbrechen</a>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Eintrag nachtragen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="manual_add">
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
    <input type="text" id="note" name="note" placeholder="z.B. vergessen auszustempeln">
    <button type="submit" class="btn" style="margin-top:1rem;">Nachtragen</button>
  </form>
</div>

<div class="card">
  <h2>Meine Einträge</h2>
  <?php if (!$entries): ?>
    <p class="muted">Noch keine Einträge.</p>
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
          <a href="/time_tracking.php?edit=<?= (int)$en['id'] ?>" class="btn small secondary">Bearbeiten</a>
          <form class="inline" method="post" onsubmit="return confirm('Eintrag löschen?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_entry">
            <input type="hidden" name="entry_id" value="<?= (int)$en['id'] ?>">
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
