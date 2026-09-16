</main>
<?php if (!empty($user)):
    $tabs = [];
    $tabs[] = ['href' => '/my_week.php', 'label' => 'Plan', 'match' => ['/my_week.php', '/shifts.php', '/index.php']];
    if ($user['role'] === 'admin') {
        $tabs[0]['href'] = '/admin/shifts.php';
        $tabs[0]['match'] = ['/admin/shifts.php'];
        $tabs[] = ['href' => '/admin/applications.php', 'label' => 'Bewerb.', 'match' => ['/admin/applications.php']];
        if (!empty($user['time_tracking_enabled'])) {
            $tabs[] = ['href' => '/time_tracking.php', 'label' => 'Stempeln', 'match' => ['/time_tracking.php']];
        }
    } else {
        if (!empty($user['time_tracking_enabled'])) {
            $tabs[] = ['href' => '/time_tracking.php', 'label' => 'Stempeln', 'match' => ['/time_tracking.php']];
        } else {
            $tabs[] = ['href' => '/my_applications.php', 'label' => 'Bewerb.', 'match' => ['/my_applications.php']];
        }
    }
    $tabs[] = ['href' => '/more.php', 'label' => 'Mehr', 'match' => ['/more.php', '/profile.php']];
    $currentScript = $scriptName ?? ($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="tabbar">
  <div class="tabbar-inner">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?= e($tab['href']) ?>" class="<?= navActive($currentScript, $tab['match']) ? 'active' : '' ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
</body>
</html>
