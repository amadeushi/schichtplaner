<?php
/**
 * Ein Tages-Ticket der Admin-Wochenansicht (admin/shifts.php). Erwartet im aufrufenden
 * Scope: $d (YYYY-MM-DD), $i (Wochentag-Index 0-6) und die Wochen-Daten ($shiftsByDate,
 * $approvedByShiftId, $pendingByShiftId, $employees, $absencesByUser, $weekStart, $todayDate,
 * $weekdayNamesFull). Wird zweimal genutzt: einmal als großer "Heute"-Hero über dem
 * Bon-Strang (wenn heute in der angezeigten Woche liegt) und für alle übrigen Tage im Strang.
 */
?>
  <?php
    $isToday = $d === $todayDate;
    $dayShifts = $shiftsByDate[$d] ?? [];
    $absentToday = [];
    foreach ($absencesByUser as $absUserId => $absList) {
        foreach ($absList as $abs) {
            if ($d >= $abs['start_date'] && $d <= $abs['end_date']) {
                $absentToday[] = $abs['user_name'] . ' (' . absenceTypeLabelDe($abs['type']) . ')';
                break;
            }
        }
    }
    $isAbsentOnDay = function (int $uid) use ($absencesByUser, $d): bool {
        foreach ($absencesByUser[$uid] ?? [] as $abs) {
            if ($d >= $abs['start_date'] && $d <= $abs['end_date']) {
                return true;
            }
        }
        return false;
    };
  ?>
  <div class="ticket<?= $isToday ? ' today hero' : '' ?>">
    <div class="ticket-head">
      <div>
        <div class="ticket-day"><?= $isToday ? 'Heute &middot; ' : '' ?><?= e($weekdayNamesFull[$i]) ?></div>
        <div class="ticket-date"><?= e(formatDateDe($d)) ?></div>
      </div>
    </div>
    <?php if ($absentToday): ?>
      <p class="muted" style="font-size:0.85rem;margin:-0.3rem 0 0.7rem;">Abwesend: <?= e(implode(', ', $absentToday)) ?></p>
    <?php endif; ?>

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
        <div class="ticket-row" id="shift-<?= (int)$sh['id'] ?>" style="flex-direction:column;align-items:stretch;gap:0.5rem;scroll-margin-top:5rem;">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;">
            <div>
              <strong><?= e($sh['title']) ?></strong>
              <?php if ($sh['published_at'] === null): ?><span class="badge pending">Entwurf</span><?php endif; ?>
              <span class="ticket-meta mono"> <?= e($sh['start_time']) ?>&ndash;<?= e($sh['end_time']) ?></span>
              <?php if ($sh['location']): ?><span class="ticket-meta"> &middot; <?= e($sh['location']) ?></span><?php endif; ?>
            </div>
            <span class="badge <?= $full ? 'confirmed' : 'pending' ?>"><?= (int)$sh['approved_count'] ?>/<?= (int)$sh['needed_count'] ?></span>
          </div>

          <?php foreach ($assigned as $person): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;">
              <span>&#10003; <?= e($person['name']) ?><?php if ($isAbsentOnDay((int)$person['id'])): ?> <span class="muted">(abwesend)</span><?php endif; ?></span>
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
              <span><span class="badge pending" style="margin-right:0.4rem;">Bewerbung</span><?= e($p['name']) ?></span>
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
                    <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?><?= $isAbsentOnDay((int)$e['id']) ? ' (abwesend)' : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php else: ?>
              <span></span>
            <?php endif; ?>
            <span style="display:flex;gap:0.4rem;align-items:center;">
              <a class="btn small secondary" href="?edit=<?= (int)$sh['id'] ?>&date=<?= e($weekStart) ?>#edit-shift">Bearbeiten</a>
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
