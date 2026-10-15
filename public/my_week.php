<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

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
$todayInThisWeek = $todayDate >= $weekStart && $todayDate <= $weekEnd;

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("+{$i} days", $weekStartTs));
}

$stmt = db()->prepare(
    "SELECT sh.*,
        (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count,
        (SELECT status FROM shift_applications a WHERE a.shift_id = sh.id AND a.user_id = :uid) AS my_status
     FROM shifts sh
     WHERE sh.shift_date BETWEEN :start AND :end AND sh.published_at IS NOT NULL
     ORDER BY sh.shift_date, sh.start_time"
);
$stmt->execute(['uid' => $user['id'], 'start' => $weekStart, 'end' => $weekEnd]);
$weekShifts = $stmt->fetchAll();

$shiftsByDate = [];
foreach ($weekShifts as $sh) {
    $shiftsByDate[$sh['shift_date']][] = $sh;
}

$openEntry = null;
if ($user['time_tracking_enabled']) {
    $openStmt = db()->prepare('SELECT * FROM time_entries WHERE user_id = :u AND clock_out IS NULL');
    $openStmt->execute(['u' => $user['id']]);
    $openEntry = $openStmt->fetch() ?: null;
}

// Fürs Herunterladen der Woche bzw. einzelner Schichten unten im Bon-Strang (siehe
// calendar_feed.php) - dieselbe Kalender-Adresse wie im Profil, nur hier schon mit
// shift_id/start+end eingegrenzt statt des vollen laufenden Abos.
$calendarToken = ensureCalendarToken((int)$user['id'], $user['calendar_token'] ?? null);

function dayRows(array $dayShifts): array
{
    $rows = [];
    foreach ($dayShifts as $sh) {
        $free = max(0, (int)$sh['needed_count'] - (int)$sh['approved_count']);
        $canApply = !$sh['my_status'] || in_array($sh['my_status'], ['withdrawn', 'rejected'], true);
        if ($sh['my_status'] === 'approved') {
            $rows[] = ['shift' => $sh, 'kind' => 'confirmed'];
        } elseif ($sh['my_status'] === 'pending') {
            $rows[] = ['shift' => $sh, 'kind' => 'pending'];
        } elseif ($canApply && $sh['status'] === 'open' && $free > 0) {
            // gap = null für bereits begonnene Schichten: Vergangenes wird nicht hervorgehoben.
            $rows[] = ['shift' => $sh, 'kind' => 'open', 'gap' => staffingGap($sh)];
        }
    }
    return $rows;
}

$rowsByDate = [];
$gapItems = [];
foreach ($days as $d) {
    $rowsByDate[$d] = dayRows($shiftsByDate[$d] ?? []);
    foreach ($rowsByDate[$d] as $row) {
        if (!empty($row['gap'])) {
            $gapItems[] = ['shift' => $row['shift'], 'gap' => $row['gap'], 'date' => $d];
        }
    }
}

require __DIR__ . '/partials/header.php';

$weekdayNamesFull = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
?>
<h1>Mein Plan</h1>

<?php require __DIR__ . '/partials/urgent_notice.php'; ?>

<div class="week-nav">
  <a class="btn secondary small" href="?date=<?= e($prevWeek) ?>">&lsaquo;</a>
  <div class="week-nav-label">
    <strong>KW <?= e($weekNumber) ?></strong>
    <span class="muted"><?= e(formatDateDe($weekStart)) ?> &ndash; <?= e(formatDateDe($weekEnd)) ?></span>
  </div>
  <a class="btn secondary small" href="?date=<?= e($nextWeek) ?>">&rsaquo;</a>
</div>
<div style="text-align:right;margin:-0.5rem 0 1rem;">
  <a href="/calendar_feed.php?token=<?= e($calendarToken) ?>&amp;start=<?= e($weekStart) ?>&amp;end=<?= e($weekEnd) ?>" class="btn small secondary">Diese Woche exportieren (.ics)</a>
</div>

<?php if ($gapItems): ?>
  <?php
    $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
    $gapLine = function (array $g) use ($todayDate, $tomorrowDate, $weekdayNamesFull, $days): string {
        $sh = $g['shift'];
        $when = $g['date'] === $todayDate ? 'Heute'
            : ($g['date'] === $tomorrowDate ? 'Morgen'
            : mb_substr($weekdayNamesFull[(int)array_search($g['date'], $days, true)], 0, 2) . ' ' . date('d.m.', strtotime($g['date'])));
        return '<li><a href="#shift-' . (int)$sh['id'] . '">'
            . '<span class="gap-slot"><span class="gap-when">' . e($when) . '</span>'
            . '<span class="gap-time">' . e($sh['start_time']) . '</span></span>'
            . '<span class="gap-title">' . e($sh['title']) . '</span>'
            . staffingGapBadgeHtml($g['gap']) . '</a></li>';
    };
    $gapVisible = array_slice($gapItems, 0, 5);
    $gapMore = array_slice($gapItems, 5);
  ?>
  <section class="gap-board" aria-labelledby="gap-board-title">
    <h2 id="gap-board-title"><?= count($gapItems) === 1 ? '1 Schicht' : count($gapItems) . ' Schichten' ?> noch frei</h2>
    <ul><?php foreach ($gapVisible as $g) { echo $gapLine($g); } ?></ul>
    <?php if ($gapMore): ?>
      <details>
        <summary>+ <?= count($gapMore) ?> weitere</summary>
        <ul><?php foreach ($gapMore as $g) { echo $gapLine($g); } ?></ul>
      </details>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($todayInThisWeek): ?>
  <?php
    $todayIndex = array_search($todayDate, $days, true);
    $todayRows = $rowsByDate[$todayDate];
  ?>
  <div class="ticket today hero">
    <div class="ticket-head">
      <div>
        <div class="ticket-day">Heute &middot; <?= e($weekdayNamesFull[$todayIndex]) ?></div>
        <div class="ticket-date"><?= e(formatDateDe($todayDate)) ?></div>
      </div>
      <?php if ($user['time_tracking_enabled']): ?>
        <?php if ($openEntry): ?>
          <a href="/time_tracking.php" class="stamp-mark">IM<br>DIENST</a>
        <?php else: ?>
          <a href="/time_tracking.php" class="btn secondary">Stempeln</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (!$todayRows): ?>
      <p class="ticket-empty">Frei &mdash; keine Schicht heute.</p>
    <?php else: ?>
      <?php foreach ($todayRows as $row): ?>
        <?php $sh = $row['shift']; $gap = $row['gap'] ?? null; ?>
        <div class="ticket-row<?= $gap ? ' gap' . ($gap['urgent'] ? ' urgent' : '') : '' ?>" id="shift-<?= (int)$sh['id'] ?>">
          <div>
            <?= shiftTimeHtml($sh['start_time'], $sh['end_time']) ?>
            <div><strong><?= e($sh['title']) ?></strong></div>
            <?php if ($sh['location']): ?><div class="ticket-meta"><?= e($sh['location']) ?></div><?php endif; ?>
            <?php if ($gap): ?><div style="margin-top:0.3rem;"><?= staffingGapBadgeHtml($gap) ?></div><?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <?php if ($row['kind'] === 'confirmed'): ?>
              <span class="badge confirmed" style="margin-bottom:0.4rem;">Bestätigt</span><br>
              <a href="/calendar_feed.php?token=<?= e($calendarToken) ?>&amp;shift_id=<?= (int)$sh['id'] ?>" class="btn small secondary">Kalender</a>
            <?php elseif ($row['kind'] === 'pending'): ?>
              <div class="badge pending" style="margin-bottom:0.4rem;">Angefragt</div><br>
              <form class="inline" method="post" action="/withdraw.php" onsubmit="return confirm('Bewerbung zurückziehen?');">
                <?= csrfField() ?>
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small secondary">Zurückziehen</button>
              </form>
            <?php elseif ($row['kind'] === 'open'): ?>
              <form class="inline" method="post" action="/apply.php">
                <?= csrfField() ?>
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small stamp-btn">Bewerben</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="rail-label">Diese Woche</div>
<?php endif; ?>

<div class="rail">
<?php foreach ($days as $i => $d): ?>
  <?php if ($d === $todayDate) { continue; } // bereits oben als Hero gezeigt ?>
  <?php $rows = $rowsByDate[$d]; ?>
  <div class="ticket">
    <div class="ticket-head">
      <div>
        <div class="ticket-day"><?= e($weekdayNamesFull[$i]) ?></div>
        <div class="ticket-date"><?= e(formatDateDe($d)) ?></div>
      </div>
    </div>

    <?php if (!$rows): ?>
      <p class="ticket-empty">Frei &mdash; keine Schicht.</p>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php $sh = $row['shift']; $gap = $row['gap'] ?? null; ?>
        <div class="ticket-row<?= $gap ? ' gap' . ($gap['urgent'] ? ' urgent' : '') : '' ?>" id="shift-<?= (int)$sh['id'] ?>">
          <div>
            <?= shiftTimeHtml($sh['start_time'], $sh['end_time']) ?>
            <div><strong><?= e($sh['title']) ?></strong></div>
            <?php if ($sh['location']): ?><div class="ticket-meta"><?= e($sh['location']) ?></div><?php endif; ?>
            <?php if ($gap): ?><div style="margin-top:0.3rem;"><?= staffingGapBadgeHtml($gap) ?></div><?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <?php if ($row['kind'] === 'confirmed'): ?>
              <span class="badge confirmed" style="margin-bottom:0.4rem;">Bestätigt</span><br>
              <a href="/calendar_feed.php?token=<?= e($calendarToken) ?>&amp;shift_id=<?= (int)$sh['id'] ?>" class="btn small secondary">Kalender</a>
            <?php elseif ($row['kind'] === 'pending'): ?>
              <div class="badge pending" style="margin-bottom:0.4rem;">Angefragt</div><br>
              <form class="inline" method="post" action="/withdraw.php" onsubmit="return confirm('Bewerbung zurückziehen?');">
                <?= csrfField() ?>
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small secondary">Zurückziehen</button>
              </form>
            <?php elseif ($row['kind'] === 'open'): ?>
              <form class="inline" method="post" action="/apply.php">
                <?= csrfField() ?>
                <input type="hidden" name="shift_id" value="<?= (int)$sh['id'] ?>">
                <input type="hidden" name="return_date" value="<?= e($weekStart) ?>">
                <button type="submit" class="btn small stamp-btn">Bewerben</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
