<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

// Reines Umsortieren per Drag&Drop - beide möglichen Änderungen (Datum, Zuweisung) in einer
// Transaktion, damit ein diagonaler Zug (anderer Tag UND anderer Mitarbeiter) atomar bleibt.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move') {
    header('Content-Type: application/json; charset=utf-8');
    checkCsrf();

    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $fromUserId = ($_POST['from_user_id'] ?? '') !== '' ? (int)$_POST['from_user_id'] : null;
    $toUserId = ($_POST['to_user_id'] ?? '') !== '' ? (int)$_POST['to_user_id'] : null;
    $toDate = (string)($_POST['to_date'] ?? '');

    if (!$toDate || !strtotime($toDate)) {
        echo json_encode(['ok' => false, 'error' => 'Ungültiges Datum.']);
        exit;
    }

    $shiftStmt = db()->prepare(
        "SELECT sh.*, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
         FROM shifts sh WHERE sh.id = :id"
    );
    $shiftStmt->execute(['id' => $shiftId]);
    $shift = $shiftStmt->fetch();

    if (!$shift) {
        echo json_encode(['ok' => false, 'error' => 'Schicht nicht gefunden.']);
        exit;
    }

    if ($toUserId !== null && $toUserId !== $fromUserId) {
        $dup = db()->prepare('SELECT status FROM shift_applications WHERE shift_id = :s AND user_id = :u');
        $dup->execute(['s' => $shiftId, 'u' => $toUserId]);
        if ($dup->fetchColumn() === 'approved') {
            echo json_encode(['ok' => false, 'error' => 'Ist dieser Schicht bereits zugewiesen.']);
            exit;
        }
        // Ein reiner Tausch (from + to gesetzt) ändert die Belegung netto nicht; nur eine
        // Neuzuweisung aus der Offen-Zeile (from = null) zählt gegen needed_count.
        $netChange = ($fromUserId !== null) ? 0 : 1;
        if ((int)$shift['approved_count'] + $netChange > (int)$shift['needed_count']) {
            echo json_encode(['ok' => false, 'error' => 'Schicht ist bereits voll besetzt.']);
            exit;
        }
    }

    db()->beginTransaction();
    try {
        if ($toDate !== $shift['shift_date']) {
            db()->prepare('UPDATE shifts SET shift_date = :d WHERE id = :id')->execute(['d' => $toDate, 'id' => $shiftId]);
        }

        if ($fromUserId !== null && $fromUserId !== $toUserId) {
            db()->prepare(
                "UPDATE shift_applications SET status = 'withdrawn', decided_at = datetime('now'), decided_by = :by, note = 'Per Kalender verschoben'
                 WHERE shift_id = :s AND user_id = :u AND status = 'approved'"
            )->execute(['by' => $user['id'], 's' => $shiftId, 'u' => $fromUserId]);
        }

        if ($toUserId !== null && $toUserId !== $fromUserId) {
            $existing = db()->prepare('SELECT status FROM shift_applications WHERE shift_id = :s AND user_id = :u');
            $existing->execute(['s' => $shiftId, 'u' => $toUserId]);
            $existingStatus = $existing->fetchColumn();

            if ($existingStatus === false) {
                db()->prepare(
                    "INSERT INTO shift_applications (shift_id, user_id, status, applied_at, decided_at, decided_by, note)
                     VALUES (:s, :u, 'approved', datetime('now'), datetime('now'), :by, 'Per Kalender zugewiesen')"
                )->execute(['s' => $shiftId, 'u' => $toUserId, 'by' => $user['id']]);
            } else {
                db()->prepare(
                    "UPDATE shift_applications SET status = 'approved', decided_at = datetime('now'), decided_by = :by, note = 'Per Kalender zugewiesen'
                     WHERE shift_id = :s AND user_id = :u"
                )->execute(['s' => $shiftId, 'u' => $toUserId, 'by' => $user['id']]);
            }
        }

        $countStmt = db()->prepare("SELECT COUNT(*) FROM shift_applications WHERE shift_id = :s AND status = 'approved'");
        $countStmt->execute(['s' => $shiftId]);
        $newApproved = (int)$countStmt->fetchColumn();
        if ($shift['status'] !== 'closed') {
            $newStatus = $newApproved >= (int)$shift['needed_count'] ? 'filled' : 'open';
            db()->prepare('UPDATE shifts SET status = :st WHERE id = :id')->execute(['st' => $newStatus, 'id' => $shiftId]);
        }

        // Jede Verschiebung/Umbesetzung zieht die Schicht zurück in den Entwurf und merkt die
        // betroffenen Personen für die nächste Sammel-Mail vor - keine sofortige Benachrichtigung.
        db()->prepare('UPDATE shifts SET published_at = NULL WHERE id = :id')->execute(['id' => $shiftId]);
        if ($fromUserId !== null && $fromUserId !== $toUserId) {
            queuePendingNotification($shiftId, $fromUserId);
        }
        if ($toUserId !== null && $toUserId !== $fromUserId) {
            queuePendingNotification($shiftId, $toUserId);
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Fehler beim Speichern.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

// Bewerbung direkt aus dem Kalender-Dialog annehmen/ablehnen - dieselbe Logik wie das
// Formular in admin/shifts.php (siehe decideApplication() in app/helpers.php), nur als
// JSON-Antwort statt Redirect+Flash, damit der Dialog ohne Seitenwechsel reagieren kann.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decide') {
    header('Content-Type: application/json; charset=utf-8');
    checkCsrf();

    $appId = (int)($_POST['application_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $result = decideApplication($appId, $decision, $user['id'], $config['smtp']);

    echo json_encode(['ok' => $result['ok'], 'error' => $result['ok'] ? null : $result['message']]);
    exit;
}

// Eine unentschiedene Bewerbung auf eine andere Schicht umhängen (z.B. falsche Startzeit
// erwischt) - per Drag&Drop des eigenen Bewerbungs-Bons auf eine andere Schicht am selben
// oder einem anderen Tag. Ändert nur shift_id der Bewerbung, keine Kapazitäts-/Entwurf-/
// Benachrichtigungs-Logik, da eine reine Bewerbung (noch nicht angenommen) niemandes
// tatsächlichen Dienstplan berührt - das passiert erst bei der Entscheidung.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retarget_application') {
    header('Content-Type: application/json; charset=utf-8');
    checkCsrf();

    $appId = (int)($_POST['application_id'] ?? 0);
    $toShiftId = (int)($_POST['to_shift_id'] ?? 0);

    $appStmt = db()->prepare("SELECT * FROM shift_applications WHERE id = :id AND status = 'pending'");
    $appStmt->execute(['id' => $appId]);
    $app = $appStmt->fetch();

    if (!$app) {
        echo json_encode(['ok' => false, 'error' => 'Bewerbung wurde bereits bearbeitet oder existiert nicht.']);
        exit;
    }

    if ($toShiftId === (int)$app['shift_id']) {
        echo json_encode(['ok' => true]);
        exit;
    }

    $targetStmt = db()->prepare('SELECT id FROM shifts WHERE id = :id');
    $targetStmt->execute(['id' => $toShiftId]);
    if (!$targetStmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Zielschicht nicht gefunden.']);
        exit;
    }

    $dupStmt = db()->prepare('SELECT id FROM shift_applications WHERE shift_id = :s AND user_id = :u');
    $dupStmt->execute(['s' => $toShiftId, 'u' => $app['user_id']]);
    if ($dupStmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Diese Person hat für die Zielschicht bereits eine Bewerbung oder Zuweisung.']);
        exit;
    }

    db()->prepare('UPDATE shift_applications SET shift_id = :s WHERE id = :id')->execute(['s' => $toShiftId, 'id' => $appId]);

    echo json_encode(['ok' => true]);
    exit;
}

// --- Wochennavigation (identisch zu admin/shifts.php) ---
$refDateParam = (string)($_GET['date'] ?? '');
$refTs = ($refDateParam !== '' && strtotime($refDateParam) !== false) ? strtotime($refDateParam) : time();
$isoDow = (int)date('N', $refTs);
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
$weekdayShort = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

$weekShiftsStmt = db()->prepare(
    "SELECT sh.*, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
     FROM shifts sh WHERE sh.shift_date BETWEEN :start AND :end ORDER BY sh.start_time"
);
$weekShiftsStmt->execute(['start' => $weekStart, 'end' => $weekEnd]);
$weekShifts = $weekShiftsStmt->fetchAll();

$employees = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

$assignedByShift = [];
$pendingByShift = [];
$shiftById = [];
foreach ($weekShifts as $sh) {
    $shiftById[(int)$sh['id']] = $sh;
}
if ($weekShifts) {
    $ids = array_column($weekShifts, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $appStmt = db()->prepare(
        "SELECT sa.shift_id, u.id AS user_id, u.name
         FROM shift_applications sa JOIN users u ON u.id = sa.user_id
         WHERE sa.shift_id IN ($placeholders) AND sa.status = 'approved' ORDER BY u.name"
    );
    $appStmt->execute($ids);
    foreach ($appStmt as $row) {
        $assignedByShift[(int)$row['shift_id']][] = ['id' => (int)$row['user_id'], 'name' => $row['name']];
    }

    // Auslastung sichtbar machen UND direkt entscheidbar machen: Name + application_id je
    // unentschiedener Bewerbung, damit der Kalender-Dialog Annehmen/Ablehnen anbieten kann,
    // ohne zu admin/shifts.php wechseln zu müssen (siehe .chip-pending/dialog.decide-dialog).
    // user_id zusätzlich, um dieselbe Bewerbung als eigenen ziehbaren Bon in der Zeile der
    // bewerbenden Person darzustellen (siehe applicant-chip weiter unten).
    $pendingStmt = db()->prepare(
        "SELECT sa.id AS application_id, sa.shift_id, sa.user_id, u.name
         FROM shift_applications sa JOIN users u ON u.id = sa.user_id
         WHERE sa.shift_id IN ($placeholders) AND sa.status = 'pending' ORDER BY u.name"
    );
    $pendingStmt->execute($ids);
    foreach ($pendingStmt as $row) {
        $pendingByShift[(int)$row['shift_id']][] = [
            'application_id' => (int)$row['application_id'], 'user_id' => (int)$row['user_id'], 'name' => $row['name'],
        ];
    }
}

// grid[rowKey][dayIndex] = [chip, ...] - rowKey ist 'open' oder eine Mitarbeiter-ID
$grid = [];
foreach ($weekShifts as $sh) {
    $dayIndex = array_search($sh['shift_date'], $days, true);
    if ($dayIndex === false) {
        continue;
    }
    $assigned = $assignedByShift[(int)$sh['id']] ?? [];
    $remaining = (int)$sh['needed_count'] - count($assigned);
    $pending = $pendingByShift[(int)$sh['id']] ?? [];
    $pendingCount = count($pending);

    foreach ($assigned as $person) {
        $grid[$person['id']][$dayIndex][] = [
            'kind' => 'assignment', 'shift_id' => (int)$sh['id'], 'title' => $sh['title'],
            'start' => $sh['start_time'], 'end' => $sh['end_time'],
            'user_id' => $person['id'], 'remaining' => null,
            'draft' => $sh['published_at'] === null, 'pending_count' => $pendingCount,
        ];
    }
    if ($remaining > 0) {
        $grid['open'][$dayIndex][] = [
            'kind' => 'assignment', 'shift_id' => (int)$sh['id'], 'title' => $sh['title'],
            'start' => $sh['start_time'], 'end' => $sh['end_time'],
            'user_id' => null, 'remaining' => $remaining,
            'draft' => $sh['published_at'] === null, 'pending_count' => $pendingCount,
        ];
    }

    // Jede unentschiedene Bewerbung bekommt zusätzlich einen eigenen, ziehbaren Bon in der
    // Zeile der bewerbenden Person - so kann eine falsch adressierte Bewerbung (z.B. andere
    // Startzeit am selben Tag gewünscht) direkt auf die richtige Schicht gezogen werden,
    // statt sie ablehnen und die Person neu bewerben lassen zu müssen.
    foreach ($pending as $p) {
        $grid[$p['user_id']][$dayIndex][] = [
            'kind' => 'application', 'shift_id' => (int)$sh['id'], 'application_id' => $p['application_id'],
            'title' => $sh['title'], 'start' => $sh['start_time'], 'end' => $sh['end_time'],
        ];
    }
}
$hasOpenRow = !empty($grid['open']);

$draftCount = 0;
foreach ($weekShifts as $sh) {
    if ($sh['published_at'] === null) {
        $draftCount++;
    }
}
$pendingNotifyCount = 0;
if ($weekShifts) {
    $pnStmt = db()->prepare(
        "SELECT COUNT(DISTINCT pn.user_id || '-' || pn.shift_id) FROM pending_notifications pn
         JOIN shifts sh ON sh.id = pn.shift_id WHERE sh.shift_date BETWEEN :start AND :end"
    );
    $pnStmt->execute(['start' => $weekStart, 'end' => $weekEnd]);
    $pendingNotifyCount = (int)$pnStmt->fetchColumn();
}

$mainWide = true;
require __DIR__ . '/../partials/header.php';
?>
<h1>Kalender</h1>
<p class="muted" style="color:var(--surround-ink-soft);"><a href="/admin/shifts.php?date=<?= e($weekStart) ?>" style="color:var(--surround-ink);text-decoration:underline;">&lsaquo; Zurück zum Schichtplan</a> &mdash; Bons zum Verschieben oder Zuweisen einfach ziehen.</p>

<?php require __DIR__ . '/../partials/urgent_notice.php'; ?>

<div class="week-nav">
  <a class="btn secondary small" href="?date=<?= e($prevWeek) ?>">&lsaquo;</a>
  <div class="week-nav-label">
    <strong>KW <?= e($weekNumber) ?></strong>
    <span class="muted"><?= e(formatDateDe($weekStart)) ?> &ndash; <?= e(formatDateDe($weekEnd)) ?></span>
  </div>
  <a class="btn secondary small" href="?date=<?= e($nextWeek) ?>">&rsaquo;</a>
</div>

<?php if ($draftCount > 0 || $pendingNotifyCount > 0): ?>
<form method="post" action="/admin/shifts.php" class="publish-bar">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="publish">
  <input type="hidden" name="week_date" value="<?= e($weekStart) ?>">
  <input type="hidden" name="return_to" value="/admin/calendar.php">
  <span class="publish-bar-summary">
    <?php if ($draftCount > 0): ?><strong><?= $draftCount ?></strong> Entwurf<?= $draftCount === 1 ? '' : 'e' ?><?php endif; ?>
    <?php if ($draftCount > 0 && $pendingNotifyCount > 0): ?> &middot; <?php endif; ?>
    <?php if ($pendingNotifyCount > 0): ?><strong><?= $pendingNotifyCount ?></strong> ausstehende Benachrichtigung<?= $pendingNotifyCount === 1 ? '' : 'en' ?><?php endif; ?>
  </span>
  <button type="submit" class="btn">Woche veröffentlichen</button>
</form>
<?php endif; ?>

<div class="grid-scroll">
  <table class="week-grid" id="calendar-grid" data-csrf="<?= e(csrfToken()) ?>">
    <thead>
      <tr>
        <th class="sticky-col">Mitarbeiter</th>
        <?php foreach ($days as $i => $d): ?>
          <th class="<?= $d === $todayDate ? 'today' : '' ?> <?= $i >= 5 ? 'weekend' : '' ?>">
            <?= e($weekdayShort[$i]) ?><br><span class="mono" style="font-weight:400;font-size:0.72rem;"><?= e(date('d.m.', strtotime($d))) ?></span>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($hasOpenRow): ?>
      <tr>
        <td class="sticky-col">Offen</td>
        <?php foreach ($days as $i => $d): ?>
          <td class="calendar-cell <?= $d === $todayDate ? 'today' : '' ?> <?= $i >= 5 ? 'weekend' : '' ?>" data-day="<?= $i ?>" data-employee="">
            <?php foreach ($grid['open'][$i] ?? [] as $chip): ?>
              <div class="shift-chip pending" draggable="true" data-shift-id="<?= $chip['shift_id'] ?>" data-user-id="">
                <span class="chip-title"><?= e($chip['title']) ?><?php if ($chip['remaining'] > 1): ?> <span class="mono">&times;<?= $chip['remaining'] ?></span><?php endif; ?></span>
                <span class="chip-time mono"><?= e($chip['start']) ?>&ndash;<?= e($chip['end']) ?></span>
                <?php if ($chip['pending_count'] > 0): ?>
                  <button type="button" class="chip-pending" draggable="false" onclick="document.getElementById('decide-<?= $chip['shift_id'] ?>').showModal()" title="<?= $chip['pending_count'] ?> unentschiedene Bewerbung<?= $chip['pending_count'] === 1 ? '' : 'en' ?> &ndash; hier direkt entscheiden"><?= $chip['pending_count'] ?> Bew.</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endif; ?>
      <?php foreach ($employees as $emp): ?>
      <tr>
        <td class="sticky-col"><?= e($emp['name']) ?></td>
        <?php foreach ($days as $i => $d): ?>
          <td class="calendar-cell <?= $d === $todayDate ? 'today' : '' ?> <?= $i >= 5 ? 'weekend' : '' ?>" data-day="<?= $i ?>" data-employee="<?= (int)$emp['id'] ?>">
            <?php foreach ($grid[$emp['id']][$i] ?? [] as $chip): ?>
              <?php if ($chip['kind'] === 'application'): ?>
                <div class="shift-chip pending applicant-chip" draggable="true" data-kind="application" data-shift-id="<?= $chip['shift_id'] ?>" data-application-id="<?= $chip['application_id'] ?>">
                  <span class="chip-title"><?= e($chip['title']) ?> <span class="chip-applicant-tag">Bewerbung</span></span>
                  <span class="chip-time mono"><?= e($chip['start']) ?>&ndash;<?= e($chip['end']) ?></span>
                  <button type="button" class="chip-pending" draggable="false" onclick="document.getElementById('decide-<?= $chip['shift_id'] ?>').showModal()" title="Bewerbung entscheiden">Entscheiden</button>
                </div>
              <?php else: ?>
                <div class="shift-chip<?= $chip['draft'] ? ' draft-chip' : '' ?>" draggable="true" data-shift-id="<?= $chip['shift_id'] ?>" data-user-id="<?= (int)$emp['id'] ?>">
                  <span class="chip-title"><?= e($chip['title']) ?><?php if ($chip['draft']): ?> <span class="chip-draft">Entwurf</span><?php endif; ?></span>
                  <span class="chip-time mono"><?= e($chip['start']) ?>&ndash;<?= e($chip['end']) ?></span>
                  <?php if ($chip['pending_count'] > 0): ?>
                    <button type="button" class="chip-pending" draggable="false" onclick="document.getElementById('decide-<?= $chip['shift_id'] ?>').showModal()" title="<?= $chip['pending_count'] ?> unentschiedene Bewerbung<?= $chip['pending_count'] === 1 ? '' : 'en' ?> &ndash; hier direkt entscheiden"><?= $chip['pending_count'] ?> Bew.</button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p id="calendar-error" class="flash error" hidden></p>

<?php foreach ($pendingByShift as $shiftId => $applicants): ?>
  <?php $sh = $shiftById[$shiftId]; ?>
  <dialog class="decide-dialog" id="decide-<?= $shiftId ?>">
    <h3><?= e($sh['title']) ?></h3>
    <p class="muted mono"><?= e(formatDateDe($sh['shift_date'])) ?> &middot; <?= e($sh['start_time']) ?>&ndash;<?= e($sh['end_time']) ?><?= $sh['location'] ? ' &middot; ' . e($sh['location']) : '' ?></p>
    <div class="decide-list">
      <?php foreach ($applicants as $p): ?>
        <div class="decide-row">
          <span><span class="badge pending" style="border:none;padding:0;">Bewerbung</span> <?= e($p['name']) ?></span>
          <span class="table-actions">
            <form method="post" class="inline decide-form">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="application_id" value="<?= $p['application_id'] ?>">
              <input type="hidden" name="decision" value="approved">
              <button type="submit" class="btn small stamp-btn">Annehmen</button>
            </form>
            <form method="post" class="inline decide-form">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="decide">
              <input type="hidden" name="application_id" value="<?= $p['application_id'] ?>">
              <input type="hidden" name="decision" value="rejected">
              <button type="submit" class="btn small secondary">Ablehnen</button>
            </form>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn secondary small" style="margin-top:0.85rem;" onclick="this.closest('dialog').close()">Schließen</button>
  </dialog>
<?php endforeach; ?>

<script>
(function () {
  var grid = document.getElementById('calendar-grid');
  if (!grid) return;
  var csrfToken = grid.dataset.csrf;
  var dayDates = <?= json_encode($days) ?>;
  var errorBox = document.getElementById('calendar-error');
  var dragged = null;

  grid.addEventListener('dragstart', function (e) {
    if (e.target.closest('.chip-pending')) {
      // Bewerbungs-Zähler ist ein Link, kein Ziehgriff - Drag hier immer verhindern,
      // damit ein Klick zuverlässig als Klick ankommt statt als (Fehl-)Ziehen.
      e.preventDefault();
      return;
    }
    var chip = e.target.closest('.shift-chip');
    if (!chip) return;
    dragged = chip;
    chip.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });

  grid.addEventListener('dragend', function () {
    if (dragged) dragged.classList.remove('dragging');
    dragged = null;
    clearRetargetHighlight();
  });

  // Ermittelt, welcher Bon in $cell das Ziel für ein Umhängen von $dragged wäre - exakter
  // Treffer unter dem Mauszeiger zuerst, sonst nur bei genau einem eindeutigen Kandidaten in
  // der Zelle. Dieselbe Logik für die Live-Markierung (dragover) UND die tatsächliche Aktion
  // (drop), damit niemals markiert wird, was am Ende nicht auch tatsächlich passiert.
  function findRetargetChip(e, cell) {
    var targetChip = e.target.closest('.shift-chip');
    if (targetChip === dragged || (targetChip && targetChip.dataset.shiftId === dragged.dataset.shiftId)) {
      targetChip = null;
    }
    if (!targetChip) {
      var others = [];
      cell.querySelectorAll('.shift-chip').forEach(function (c) {
        if (c !== dragged && c.dataset.shiftId !== dragged.dataset.shiftId) others.push(c);
      });
      if (others.length === 1) targetChip = others[0];
    }
    return targetChip;
  }

  var highlightedChip = null;
  function clearRetargetHighlight() {
    if (highlightedChip) highlightedChip.classList.remove('retarget-target');
    highlightedChip = null;
  }

  grid.addEventListener('dragover', function (e) {
    var cell = e.target.closest('.calendar-cell');
    if (!cell || !dragged) return;
    e.preventDefault();

    if (dragged.dataset.kind === 'application') {
      // Beim Umhängen einer Bewerbung markiert sich der exakte Ziel-Bon selbst (stempelroter
      // Rahmen wie sonst die ganze Zelle) statt der ganzen Tageszelle - zeigt in Echtzeit
      // genau, wohin der Drop tatsächlich träfe, auch wenn mehrere Schichten in der Zelle
      // liegen. Ist der aktuelle Hover-Punkt kein gültiges Ziel, bleibt nichts markiert.
      var candidate = (dragged.parentElement === cell) ? null : findRetargetChip(e, cell);
      if (candidate !== highlightedChip) {
        clearRetargetHighlight();
        if (candidate) {
          candidate.classList.add('retarget-target');
          highlightedChip = candidate;
        }
      }
      return;
    }

    cell.classList.add('drag-over');
  });

  grid.addEventListener('dragleave', function (e) {
    var cell = e.target.closest('.calendar-cell');
    if (cell) cell.classList.remove('drag-over');
  });

  grid.addEventListener('drop', function (e) {
    var cell = e.target.closest('.calendar-cell');
    if (!cell || !dragged) return;
    e.preventDefault();
    cell.classList.remove('drag-over');
    clearRetargetHighlight();

    if (dragged.parentElement === cell) return; // gleiche Zelle, nichts zu tun

    if (dragged.dataset.kind === 'application') {
      // Bewerbungs-Bon auf eine andere Schicht gezogen: Zielschicht ist zuerst der exakte
      // Bon unter dem Mauszeiger beim Drop (Treffgenauigkeit statt Raten) - wichtig, sobald
      // mehrere verschiedene Schichten in derselben Tageszelle liegen (z.B. 3 offene an
      // einem Tag), sonst würde immer dieselbe (die erste im DOM) gewinnen, egal wohin
      // gezogen wurde. Nur wenn der Drop keinen Bon exakt trifft UND es in der Zelle genau
      // eine einzige andere Schicht gibt, wird das noch als eindeutiger Fall akzeptiert.
      var targetChip = findRetargetChip(e, cell);
      if (!targetChip) {
        errorBox.textContent = 'Bitte genau auf die Ziel-Schicht ziehen, um die Bewerbung dorthin umzuhängen.';
        errorBox.hidden = false;
        return;
      }

      var retargetBody = new URLSearchParams();
      retargetBody.set('action', 'retarget_application');
      retargetBody.set('csrf_token', csrfToken);
      retargetBody.set('application_id', dragged.dataset.applicationId);
      retargetBody.set('to_shift_id', targetChip.dataset.shiftId);
      postAction(retargetBody);
      return;
    }

    var fromUserId = dragged.dataset.userId || '';
    var toUserId = cell.dataset.employee || '';
    var toDate = dayDates[parseInt(cell.dataset.day, 10)];

    var body = new URLSearchParams();
    body.set('action', 'move');
    body.set('csrf_token', csrfToken);
    body.set('shift_id', dragged.dataset.shiftId);
    body.set('from_user_id', fromUserId);
    body.set('to_user_id', toUserId);
    body.set('to_date', toDate);

    postAction(body);
  });

  function postAction(body) {
    errorBox.hidden = true;
    fetch(window.location.pathname + window.location.search, {
      method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' },
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          window.location.reload();
        } else {
          errorBox.textContent = data.error || 'Aktion fehlgeschlagen.';
          errorBox.hidden = false;
        }
      })
      .catch(function () {
        errorBox.textContent = 'Verbindung fehlgeschlagen.';
        errorBox.hidden = false;
      });
  }

  // Annehmen/Ablehnen im Dialog: derselbe fetch()-statt-Formular-Submit-Pfad wie beim
  // Verschieben, damit die Seite nach einer Entscheidung konsistent neu lädt und das
  // Raster (Offen-Zeile, Zuweisungen, verbleibende Bewerbungen) sich selbst korrekt
  // neu aufbaut, statt das per JS nachzubilden.
  document.querySelectorAll('.decide-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      postAction(new URLSearchParams(new FormData(form)));
    });
  });

  // Klick auf den Dialog-Hintergrund (außerhalb der Karte) schließt ihn - <dialog> tut das
  // nicht von selbst; ESC schließt bereits nativ.
  document.querySelectorAll('dialog.decide-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) dlg.close();
    });
  });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
