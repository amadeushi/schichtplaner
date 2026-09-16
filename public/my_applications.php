<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

$stmt = db()->prepare(
    "SELECT sa.*, sh.title, sh.shift_date, sh.start_time, sh.end_time, sh.location
     FROM shift_applications sa
     JOIN shifts sh ON sh.id = sa.shift_id
     WHERE sa.user_id = :uid
     ORDER BY sh.shift_date DESC, sh.start_time DESC"
);
$stmt->execute(['uid' => $user['id']]);
$apps = $stmt->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<h1>Meine Bewerbungen</h1>

<?php if (!$apps): ?>
  <div class="card"><p class="muted">Du hast dich noch für keine Schicht beworben.</p></div>
<?php else: ?>
<div class="card">
<table>
  <thead><tr><th>Datum</th><th>Zeit</th><th>Titel</th><th>Ort</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($apps as $a): ?>
    <tr>
      <td data-label="Datum"><?= e(weekdayDe($a['shift_date'])) ?>, <?= e(formatDateDe($a['shift_date'])) ?></td>
      <td data-label="Zeit"><?= e($a['start_time']) ?>-<?= e($a['end_time']) ?></td>
      <td data-label="Titel"><?= e($a['title']) ?></td>
      <td data-label="Ort"><?= e($a['location'] ?? '-') ?></td>
      <td data-label="Status">
        <span class="badge <?= e($a['status']) ?>"><?= e(statusLabelDe($a['status'])) ?></span>
        <?php if ($a['note']): ?><br><span class="muted"><?= e($a['note']) ?></span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
