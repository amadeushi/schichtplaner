<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

$monthParam = (string)($_GET['month'] ?? '');
$monthKey = (preg_match('/^\d{4}-\d{2}$/', $monthParam)) ? $monthParam : date('Y-m');
[$monthStart, $monthEnd] = monthBounds($monthKey);
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
$monthLabel = monthNameDe($monthStart);

$summaryStmt = db()->prepare(
    "SELECT u.id, u.name,
        COALESCE(SUM(CASE WHEN te.clock_out IS NOT NULL AND te.clock_in >= :start AND te.clock_in < :end
            THEN (strftime('%s', te.clock_out) - strftime('%s', te.clock_in)) ELSE 0 END), 0) AS total_seconds,
        COUNT(CASE WHEN te.clock_in >= :start AND te.clock_in < :end THEN te.id END) AS entry_count
     FROM users u
     LEFT JOIN time_entries te ON te.user_id = u.id
     WHERE u.time_tracking_enabled = 1
     GROUP BY u.id
     ORDER BY u.name"
);
$summaryStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
$summary = $summaryStmt->fetchAll();

$openNow = db()->query(
    "SELECT te.*, u.name FROM time_entries te JOIN users u ON u.id = te.user_id
     WHERE te.clock_out IS NULL ORDER BY te.clock_in"
)->fetchAll();

require __DIR__ . '/../partials/header.php';
?>
<h1>Arbeitszeiten</h1>

<?php if ($openNow): ?>
<div class="card">
  <h2>Aktuell eingestempelt</h2>
  <table>
    <thead><tr><th>Mitarbeiter</th><th>Seit</th><th>Dauer</th></tr></thead>
    <tbody>
    <?php foreach ($openNow as $o): ?>
      <tr>
        <td data-label="Mitarbeiter"><?= e($o['name']) ?></td>
        <td data-label="Seit"><?= e(date('H:i', strtotime($o['clock_in']))) ?> Uhr</td>
        <td data-label="Dauer"><?= e(formatDurationHm(time() - strtotime($o['clock_in']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="week-nav">
  <a class="btn secondary small" href="?month=<?= e($prevMonth) ?>">&lsaquo; Vorheriger Monat</a>
  <div class="week-nav-label"><strong><?= e($monthLabel) ?></strong></div>
  <a class="btn secondary small" href="?month=<?= e($nextMonth) ?>">Nächster Monat &rsaquo;</a>
</div>

<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
  <h2 style="margin:0;">Monatsübersicht</h2>
  <a class="btn secondary small" href="/admin/export_hours.php?month=<?= e($monthKey) ?>">Alle exportieren (CSV)</a>
</div>
<?php if (!$summary): ?>
  <p class="muted">Für keinen Mitarbeiter ist die Zeiterfassung freigeschaltet. Das lässt sich in der Mitarbeiterverwaltung ändern.</p>
<?php else: ?>
<table>
  <thead><tr><th>Mitarbeiter</th><th>Gesamtstunden</th><th>Einträge</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($summary as $row): ?>
    <tr>
      <td data-label="Mitarbeiter"><?= e($row['name']) ?></td>
      <td data-label="Gesamtstunden"><?= e(formatDurationHm((int)$row['total_seconds'])) ?></td>
      <td data-label="Einträge"><?= (int)$row['entry_count'] ?></td>
      <td data-label="Aktion"><a class="btn small secondary" href="/admin/time_entries.php?user_id=<?= (int)$row['id'] ?>&month=<?= e($monthKey) ?>">Details</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
