<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = requireAdmin();

// Entscheidungen (Annehmen/Ablehnen) laufen ausschliesslich über den
// Bon-Strang in admin/shifts.php - diese Seite ist bewusst eine reine,
// wochenübergreifende Übersicht, damit es nur einen Ort für die
// eigentliche Entscheidung gibt.

function weekStartOf(string $date): string
{
    $ts = strtotime($date);
    $dow = (int)date('N', $ts);
    return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
}

$pending = db()->query(
    "SELECT sa.*, sh.title, sh.shift_date, sh.start_time, sh.end_time, sh.needed_count,
        u.name AS applicant_name, u.email AS applicant_email,
        (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id = sh.id AND a.status = 'approved') AS approved_count
     FROM shift_applications sa
     JOIN shifts sh ON sh.id = sa.shift_id
     JOIN users u ON u.id = sa.user_id
     WHERE sa.status = 'pending'
     ORDER BY sh.shift_date, sh.start_time"
)->fetchAll();

$recent = db()->query(
    "SELECT sa.*, sh.title, sh.shift_date, u.name AS applicant_name
     FROM shift_applications sa
     JOIN shifts sh ON sh.id = sa.shift_id
     JOIN users u ON u.id = sa.user_id
     WHERE sa.status IN ('approved', 'rejected')
     ORDER BY sa.decided_at DESC LIMIT 20"
)->fetchAll();

require __DIR__ . '/../partials/header.php';
?>
<h1>Bewerbungen</h1>
<p class="muted" style="color:var(--surround-ink-soft);">Entscheidungen triffst du direkt im <a href="/admin/shifts.php" style="color:var(--surround-ink);text-decoration:underline;">Schichtplan</a> &mdash; hier siehst du alles wochenübergreifend auf einen Blick.</p>

<div class="card">
  <h2>Ausstehend (<?= count($pending) ?>)</h2>
  <?php if (!$pending): ?>
    <p class="muted">Keine offenen Bewerbungen.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Datum</th><th>Schicht</th><th>Bewerber</th><th>Plätze</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $a): ?>
      <tr>
        <td data-label="Datum"><?= e(formatDateDe($a['shift_date'])) ?>, <?= e($a['start_time']) ?>-<?= e($a['end_time']) ?></td>
        <td data-label="Schicht"><?= e($a['title']) ?></td>
        <td data-label="Bewerber"><?= e($a['applicant_name']) ?><br><span class="muted"><?= e($a['applicant_email']) ?></span></td>
        <td data-label="Plätze"><?= (int)$a['approved_count'] ?>/<?= (int)$a['needed_count'] ?></td>
        <td data-label="Aktion">
          <a class="btn small" href="/admin/shifts.php?date=<?= e(weekStartOf($a['shift_date'])) ?>">Im Plan entscheiden</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Zuletzt entschieden</h2>
  <?php if (!$recent): ?>
    <p class="muted">Noch keine Entscheidungen getroffen.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Datum</th><th>Schicht</th><th>Bewerber</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $a): ?>
      <tr>
        <td data-label="Datum"><?= e(formatDateDe($a['shift_date'])) ?></td>
        <td data-label="Schicht"><?= e($a['title']) ?></td>
        <td data-label="Bewerber"><?= e($a['applicant_name']) ?></td>
        <td data-label="Status">
          <span class="badge <?= e($a['status']) ?>"><?= e(statusLabelDe($a['status'])) ?></span>
          <?php if ($a['note']): ?><br><span class="muted"><?= e($a['note']) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
