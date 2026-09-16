<?php
require __DIR__ . '/../app/bootstrap.php';
$user = requireLogin();

$links = [];
if ($user['role'] === 'admin') {
    $links[] = ['href' => '/admin/shifts.php', 'label' => 'Schichtplan', 'desc' => 'Bon-Strang, Zuweisungen, neue Schichten'];
    $links[] = ['href' => '/admin/applications.php', 'label' => 'Bewerbungen', 'desc' => 'Wochenübergreifende Übersicht'];
    $links[] = ['href' => '/admin/users.php', 'label' => 'Mitarbeiter', 'desc' => 'Konten, Zeiterfassung freischalten'];
    $links[] = ['href' => '/admin/time_tracking.php', 'label' => 'Arbeitszeiten', 'desc' => 'Monatsübersicht aller Mitarbeiter'];
    $links[] = ['href' => '/admin/settings.php', 'label' => 'Einstellungen', 'desc' => 'Name, Webhook, Protokoll'];
    if (!empty($user['time_tracking_enabled'])) {
        $links[] = ['href' => '/time_tracking.php', 'label' => 'Meine Zeiterfassung', 'desc' => 'Ein-/Ausstempeln'];
    }
} else {
    $links[] = ['href' => '/my_week.php', 'label' => 'Mein Plan', 'desc' => 'Wochenübersicht'];
    $links[] = ['href' => '/my_applications.php', 'label' => 'Meine Bewerbungen', 'desc' => 'Verlauf aller Bewerbungen'];
    if (!empty($user['time_tracking_enabled'])) {
        $links[] = ['href' => '/time_tracking.php', 'label' => 'Zeiterfassung', 'desc' => 'Ein-/Ausstempeln'];
    }
}
$links[] = ['href' => '/profile.php', 'label' => 'Profil', 'desc' => 'Passwort, Benachrichtigungen'];

require __DIR__ . '/partials/header.php';
?>
<h1>Mehr</h1>

<div class="card" style="padding:0;">
  <?php foreach ($links as $i => $l): ?>
    <a href="<?= e($l['href']) ?>" style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;padding:0.9rem 1.1rem;text-decoration:none;color:var(--ink);<?= $i > 0 ? 'border-top:1px dashed var(--ink-line);' : '' ?>">
      <span>
        <strong style="display:block;font-size:0.95rem;"><?= e($l['label']) ?></strong>
        <span class="muted"><?= e($l['desc']) ?></span>
      </span>
      <span aria-hidden="true" style="color:var(--ink-soft);">&rsaquo;</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <span class="muted">Angemeldet als <strong style="color:var(--ink);"><?= e($user['name']) ?></strong></span>
    <a href="/logout.php" class="btn danger small">Abmelden</a>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
