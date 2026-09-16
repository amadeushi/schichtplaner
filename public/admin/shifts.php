<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $date = (string)($_POST['shift_date'] ?? '');
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        $location = trim((string)($_POST['location'] ?? ''));
        $needed = max(1, (int)($_POST['needed_count'] ?? 1));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $repeatWeeks = max(1, min(26, (int)($_POST['repeat_weeks'] ?? 1)));
        $returnDate = (string)($_POST['return_date'] ?? '');

        if ($title === '' || !$date || !$start || !$end) {
            flash('error', 'Bitte Titel, Datum sowie Start- und Endzeit angeben.');
            redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
        }

        $ins = db()->prepare(
            'INSERT INTO shifts (title, shift_date, start_time, end_time, location, needed_count, notes, status, created_by)
             VALUES (:title, :date, :start, :end, :loc, :needed, :notes, :status, :uid)'
        );

        $createdShifts = [];
        for ($i = 0; $i < $repeatWeeks; $i++) {
            $shiftDate = date('Y-m-d', strtotime($date . " +{$i} week"));
            $ins->execute([
                'title' => $title, 'date' => $shiftDate, 'start' => $start, 'end' => $end,
                'loc' => $location ?: null, 'needed' => $needed, 'notes' => $notes ?: null,
                'status' => 'open', 'uid' => $user['id'],
            ]);
            $createdShifts[] = (int)db()->lastInsertId();
        }

        $notifier = new Notifier($config['smtp']);
        foreach ($createdShifts as $sid) {
            $s = db()->prepare('SELECT * FROM shifts WHERE id = :id');
            $s->execute(['id' => $sid]);
            $notifier->shiftPublished($s->fetch());
        }

        $count = count($createdShifts);
        flash('success', $count > 1 ? "$count Schichten wurden angelegt und freigegeben." : 'Schicht wurde angelegt und freigegeben.');
        redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
    }

    if ($action === 'status') {
        $id = (int)($_POST['shift_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $returnDate = (string)($_POST['return_date'] ?? '');
        if (in_array($status, ['open', 'filled', 'closed'], true)) {
            db()->prepare('UPDATE shifts SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
            flash('success', 'Status aktualisiert.');
        }
        redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
    }

    if ($action === 'delete') {
        $id = (int)($_POST['shift_id'] ?? 0);
        $returnDate = (string)($_POST['return_date'] ?? '');
        db()->prepare('DELETE FROM shifts WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Schicht wurde gelöscht.');
        redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
    }

    if ($action === 'assign') {
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $returnDate = (string)($_POST['return_date'] ?? '');

        $shiftStmt = db()->prepare(
            "SELECT sh.*, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
             FROM shifts sh WHERE sh.id = :id"
        );
        $shiftStmt->execute(['id' => $shiftId]);
        $shift = $shiftStmt->fetch();

        $empStmt = db()->prepare('SELECT * FROM users WHERE id = :id AND active = 1');
        $empStmt->execute(['id' => $employeeId]);
        $employee = $empStmt->fetch();

        if (!$shift || !$employee) {
            flash('error', 'Schicht oder Mitarbeiter nicht gefunden.');
            redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
        }

        $existing = db()->prepare('SELECT status FROM shift_applications WHERE shift_id = :s AND user_id = :u');
        $existing->execute(['s' => $shiftId, 'u' => $employeeId]);
        $existingStatus = $existing->fetchColumn();

        if ($existingStatus === 'approved') {
            flash('error', $employee['name'] . ' ist dieser Schicht bereits zugewiesen.');
            redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
        }

        if ((int)$shift['approved_count'] >= (int)$shift['needed_count']) {
            flash('error', 'Diese Schicht ist bereits voll besetzt (benötigte Personenzahl erreicht).');
            redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
        }

        if ($existingStatus === false) {
            db()->prepare(
                "INSERT INTO shift_applications (shift_id, user_id, status, applied_at, decided_at, decided_by, note)
                 VALUES (:s, :u, 'approved', datetime('now'), datetime('now'), :by, 'Direkt vom Admin zugewiesen')"
            )->execute(['s' => $shiftId, 'u' => $employeeId, 'by' => $user['id']]);
        } else {
            db()->prepare(
                "UPDATE shift_applications SET status = 'approved', decided_at = datetime('now'), decided_by = :by, note = 'Direkt vom Admin zugewiesen'
                 WHERE shift_id = :s AND user_id = :u"
            )->execute(['s' => $shiftId, 'u' => $employeeId, 'by' => $user['id']]);
        }

        if ((int)$shift['approved_count'] + 1 >= (int)$shift['needed_count']) {
            db()->prepare("UPDATE shifts SET status = 'filled' WHERE id = :id")->execute(['id' => $shiftId]);
        }

        $notifier = new Notifier($config['smtp']);
        $notifier->applicationDecided($shift, $employee, 'approved');

        flash('success', $employee['name'] . ' wurde der Schicht zugewiesen.');
        redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
    }

    if ($action === 'unassign') {
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $returnDate = (string)($_POST['return_date'] ?? '');

        $existing = db()->prepare('SELECT status FROM shift_applications WHERE shift_id = :s AND user_id = :u');
        $existing->execute(['s' => $shiftId, 'u' => $employeeId]);
        $existingStatus = $existing->fetchColumn();

        if ($existingStatus !== 'approved') {
            flash('error', 'Diese Zuweisung wurde nicht gefunden.');
            redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
        }

        $shiftStmt = db()->prepare('SELECT * FROM shifts WHERE id = :id');
        $shiftStmt->execute(['id' => $shiftId]);
        $shift = $shiftStmt->fetch();

        $empStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $empStmt->execute(['id' => $employeeId]);
        $employee = $empStmt->fetch();

        db()->prepare(
            "UPDATE shift_applications SET status = 'withdrawn', decided_at = datetime('now'), decided_by = :by, note = 'Zuweisung vom Admin entfernt'
             WHERE shift_id = :s AND user_id = :u"
        )->execute(['by' => $user['id'], 's' => $shiftId, 'u' => $employeeId]);

        if ($shift['status'] === 'filled') {
            db()->prepare("UPDATE shifts SET status = 'open' WHERE id = :id")->execute(['id' => $shiftId]);
        }

        $notifier = new Notifier($config['smtp']);
        $notifier->assignmentRemoved($shift, $employee);

        flash('success', $employee['name'] . ' wurde von der Schicht entfernt.');
        redirect('/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : ''));
    }

    if ($action === 'decide') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $returnDate = (string)($_POST['return_date'] ?? '');
        $backTo = '/admin/shifts.php' . ($returnDate ? '?date=' . urlencode($returnDate) : '');

        if (in_array($decision, ['approved', 'rejected'], true)) {
            $stmt = db()->prepare(
                "SELECT sa.*, sh.needed_count,
                    (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
                 FROM shift_applications sa JOIN shifts sh ON sh.id = sa.shift_id
                 WHERE sa.id = :id AND sa.status = 'pending'"
            );
            $stmt->execute(['id' => $appId]);
            $app = $stmt->fetch();

            if (!$app) {
                flash('error', 'Bewerbung wurde bereits bearbeitet oder existiert nicht.');
                redirect($backTo);
            }

            if ($decision === 'approved' && (int)$app['approved_count'] >= (int)$app['needed_count']) {
                flash('error', 'Diese Schicht ist bereits voll besetzt.');
                redirect($backTo);
            }

            db()->prepare('UPDATE shift_applications SET status = :s, decided_at = datetime(\'now\'), decided_by = :by WHERE id = :id')
                ->execute(['s' => $decision, 'by' => $user['id'], 'id' => $appId]);

            $shiftStmt = db()->prepare('SELECT * FROM shifts WHERE id = :id');
            $shiftStmt->execute(['id' => $app['shift_id']]);
            $shift = $shiftStmt->fetch();

            $applicantStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
            $applicantStmt->execute(['id' => $app['user_id']]);
            $applicant = $applicantStmt->fetch();

            if ($decision === 'approved' && (int)$app['approved_count'] + 1 >= (int)$app['needed_count']) {
                db()->prepare("UPDATE shifts SET status = 'filled' WHERE id = :id")->execute(['id' => $shift['id']]);
            }

            $notifier = new Notifier($config['smtp']);
            $notifier->applicationDecided($shift, $applicant, $decision);

            flash('success', 'Bewerbung wurde ' . statusLabelDe($decision) . '.');
        }
        redirect($backTo);
    }
}

// --- Wochennavigation ---
$refDateParam = (string)($_GET['date'] ?? '');
$refTs = ($refDateParam !== '' && strtotime($refDateParam) !== false) ? strtotime($refDateParam) : time();
$isoDow = (int)date('N', $refTs); // 1 = Montag ... 7 = Sonntag
$weekStartTs = strtotime('-' . ($isoDow - 1) . ' days', $refTs);
$weekStart = date('Y-m-d', $weekStartTs);
$weekEnd = date('Y-m-d', strtotime('+6 days', $weekStartTs));
$weekNumber = date('W', $weekStartTs);
$prevWeek = date('Y-m-d', strtotime('-7 days', $weekStartTs));
$nextWeek = date('Y-m-d', strtotime('+7 days', $weekStartTs));
$todayDate = date('Y-m-d');

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("+{$i} days", $weekStartTs));
}

// --- Daten für die aktuelle Woche laden ---
$weekShiftsStmt = db()->prepare(
    "SELECT sh.*,
        (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
     FROM shifts sh
     WHERE sh.shift_date BETWEEN :start AND :end
     ORDER BY sh.shift_date, sh.start_time"
);
$weekShiftsStmt->execute(['start' => $weekStart, 'end' => $weekEnd]);
$weekShifts = $weekShiftsStmt->fetchAll();

$shiftsByDate = [];
foreach ($weekShifts as $sh) {
    $shiftsByDate[$sh['shift_date']][] = $sh;
}

$employees = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

$approvedByShiftId = [];
$pendingByShiftId = [];
if ($weekShifts) {
    $ids = array_column($weekShifts, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $appStmt = db()->prepare(
        "SELECT sa.id AS application_id, sa.shift_id, sa.status, u.id AS user_id, u.name
         FROM shift_applications sa JOIN users u ON u.id = sa.user_id
         WHERE sa.shift_id IN ($placeholders) AND sa.status IN ('approved', 'pending')
         ORDER BY u.name"
    );
    $appStmt->execute($ids);
    foreach ($appStmt as $row) {
        if ($row['status'] === 'approved') {
            $approvedByShiftId[(int)$row['shift_id']][] = ['id' => (int)$row['user_id'], 'name' => $row['name']];
        } else {
            $pendingByShiftId[(int)$row['shift_id']][] = ['application_id' => (int)$row['application_id'], 'name' => $row['name']];
        }
    }
}

require __DIR__ . '/../partials/header.php';

$weekdayNamesFull = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
?>
<h1>Schichtplan</h1>

<?php require __DIR__ . '/../partials/urgent_notice.php'; ?>

<div class="week-nav">
  <a class="btn secondary small" href="?date=<?= e($prevWeek) ?>">&lsaquo;</a>
  <div class="week-nav-label">
    <strong>KW <?= e($weekNumber) ?></strong>
    <span class="muted"><?= e(formatDateDe($weekStart)) ?> &ndash; <?= e(formatDateDe($weekEnd)) ?></span>
  </div>
  <a class="btn secondary small" href="?date=<?= e($nextWeek) ?>">&rsaquo;</a>
</div>

<div class="rail">
<?php foreach ($days as $i => $d): ?>
  <?php $isToday = $d === $todayDate; $dayShifts = $shiftsByDate[$d] ?? []; ?>
  <div class="ticket <?= $isToday ? 'today' : '' ?>">
    <div class="ticket-head">
      <div>
        <div class="ticket-day"><?= e($weekdayNamesFull[$i]) ?><?= $isToday ? ' &middot; heute' : '' ?></div>
        <div class="ticket-date"><?= e(formatDateDe($d)) ?></div>
      </div>
    </div>

    <?php if (!$dayShifts): ?>
      <p class="ticket-empty">Keine Schicht geplant.</p>
    <?php else: ?>
      <?php foreach ($dayShifts as $sh): ?>
        <?php
          $assigned = $approvedByShiftId[(int)$sh['id']] ?? [];
          $pending = $pendingByShiftId[(int)$sh['id']] ?? [];
          $assignedIds = array_column($assigned, 'id');
          $assignable = array_filter($employees, fn($e) => !in_array((int)$e['id'], $assignedIds, true));
          $full = (int)$sh['approved_count'] >= (int)$sh['needed_count'];
        ?>
        <div class="ticket-row" style="flex-direction:column;align-items:stretch;gap:0.5rem;">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;">
            <div>
              <strong><?= e($sh['title']) ?></strong>
              <span class="ticket-meta mono"> <?= e($sh['start_time']) ?>&ndash;<?= e($sh['end_time']) ?></span>
              <?php if ($sh['location']): ?><span class="ticket-meta"> &middot; <?= e($sh['location']) ?></span><?php endif; ?>
            </div>
            <span class="badge <?= $full ? 'confirmed' : 'pending' ?>"><?= (int)$sh['approved_count'] ?>/<?= (int)$sh['needed_count'] ?></span>
          </div>

          <?php foreach ($assigned as $person): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;">
              <span>&#10003; <?= e($person['name']) ?></span>
              <form class="inline" method="post" onsubmit="return confirm('<?= e($person['name']) ?> von \'<?= e($sh['title']) ?>\' entfernen?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="unassign">
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="employee_id" value="<?= (int)$person['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small secondary">Entfernen</button>
              </form>
            </div>
          <?php endforeach; ?>

          <?php foreach ($pending as $p): ?>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:0.4rem;font-size:0.85rem;">
              <span><span class="badge pending" style="border:none;padding:0;margin-right:0.4rem;">Bewerbung</span><?= e($p['name']) ?></span>
              <span style="display:flex;gap:0.4rem;">
                <form class="inline" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="decide">
                  <input type="hidden" name="application_id" value="<?= (int)$p['application_id'] ?>">
                  <input type="hidden" name="decision" value="approved">
                  <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                  <button type="submit" class="btn small stamp-btn">Annehmen</button>
                </form>
                <form class="inline" method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="decide">
                  <input type="hidden" name="application_id" value="<?= (int)$p['application_id'] ?>">
                  <input type="hidden" name="decision" value="rejected">
                  <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                  <button type="submit" class="btn small secondary">Ablehnen</button>
                </form>
              </span>
            </div>
          <?php endforeach; ?>

          <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;border-top:1px dashed var(--ink-line);padding-top:0.5rem;">
            <?php if (!$full && $assignable): ?>
              <form class="inline cell-assign" method="post" style="display:flex;gap:0.4rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <select name="employee_id" onchange="this.form.submit()">
                  <option value="">+ zuweisen</option>
                  <?php foreach ($assignable as $e): ?>
                    <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php else: ?>
              <span></span>
            <?php endif; ?>
            <span style="display:flex;gap:0.4rem;align-items:center;">
              <form class="inline" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <select name="status" onchange="this.form.submit()" class="select-inline">
                  <option value="open" <?= $sh['status'] === 'open' ? 'selected' : '' ?>>Offen</option>
                  <option value="filled" <?= $sh['status'] === 'filled' ? 'selected' : '' ?>>Besetzt</option>
                  <option value="closed" <?= $sh['status'] === 'closed' ? 'selected' : '' ?>>Geschlossen</option>
                </select>
              </form>
              <form class="inline" method="post" onsubmit="return confirm('Schicht wirklich löschen?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small danger">&times;</button>
              </form>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<div class="card">
  <h2>Neue Schicht anlegen</h2>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
    <div class="grid-2">
      <div>
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" required placeholder="z.B. Spätschicht Küche">
      </div>
      <div>
        <label for="location">Ort</label>
        <input type="text" id="location" name="location" placeholder="z.B. Filiale Nord">
      </div>
    </div>
    <div class="grid-2">
      <div>
        <label for="shift_date">Datum</label>
        <input type="date" id="shift_date" name="shift_date" value="<?= e($weekStart) ?>" required>
      </div>
      <div>
        <label for="needed_count">Benötigte Personen</label>
        <input type="number" id="needed_count" name="needed_count" min="1" value="1" required>
      </div>
    </div>
    <div class="grid-2">
      <div>
        <label for="start_time">Startzeit</label>
        <input type="time" id="start_time" name="start_time" required>
      </div>
      <div>
        <label for="end_time">Endzeit</label>
        <input type="time" id="end_time" name="end_time" required>
      </div>
    </div>
    <label for="notes">Notiz (optional)</label>
    <textarea id="notes" name="notes" rows="2"></textarea>
    <label for="repeat_weeks">Wiederholen (Anzahl Wochen, wöchentlich ab Datum)</label>
    <input type="number" id="repeat_weeks" name="repeat_weeks" min="1" max="26" value="1">
    <button type="submit" class="btn" style="margin-top:1rem;">Schicht anlegen &amp; freigeben</button>
  </form>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
