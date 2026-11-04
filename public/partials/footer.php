<footer class="site-footer">
  <?= e(setting('app_name', 'Schichtplaner')) ?> · Amadeus Delivery ·
  <a href="https://github.com/amadeushi/schichtplaner/blob/main/CHANGELOG.md" target="_blank" rel="noopener">v<?= e(APP_VERSION) ?></a>
</footer>
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
<script type="speculationrules">{"prefetch":[{"where":{"selector_matches":"nav.tabbar a"},"eagerness":"moderate"}]}</script>
<nav class="tabbar" aria-label="Hauptnavigation">
  <div class="tabbar-inner">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?= e($tab['href']) ?>" class="<?= navActive($currentScript, $tab['match']) ? 'active' : '' ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
<script>
  // "Mehr" im Schichtplan: seltene Aktionen einklappen. Ohne Skript bleiben sie sichtbar.
  document.querySelectorAll('[data-more-toggle]').forEach(function (button) {
    var body = document.getElementById(button.getAttribute('aria-controls'));
    if (!body) { return; }
    button.hidden = false;
    body.hidden = true;
    button.addEventListener('click', function () {
      var opening = body.hidden;
      body.hidden = !opening;
      button.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
  });
</script>
<script>
  // Doppeltipp-Schutz: ein abgeschicktes POST-Formular nimmt keine zweite Absendung an, bis die Seite gewechselt hat.
  // Nach 8 Sekunden (Download, Netzfehler) und beim Zurück-Navigieren (bfcache) wird es wieder freigegeben.
  (function () {
    function release(form) {
      delete form.dataset.submitting;
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) { b.disabled = false; });
    }
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement) || String(form.method).toLowerCase() !== 'post') { return; }
      if (form.dataset.submitting === '1') { event.preventDefault(); return; }
      form.dataset.submitting = '1'; // sofort markieren, damit auch eine zweite Absendung im selben Augenblick geblockt wird
      setTimeout(function () {
        if (event.defaultPrevented) { release(form); return; } // z. B. abgebrochene Rückfrage oder ein Formular, das per Skript sendet
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) { b.disabled = true; });
        setTimeout(function () { release(form); }, 8000);
      }, 0);
    });
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) { document.querySelectorAll('form[data-submitting]').forEach(release); }
    });
  })();
</script>
<script>
  // Rückgängig-Hinweis nach 10 Sekunden ausblenden (die Möglichkeit selbst gilt serverseitig 60 s).
  document.querySelectorAll('.undo-toast').forEach(function (toast) {
    setTimeout(function () {
      toast.classList.add('is-leaving');
      setTimeout(function () { toast.remove(); }, 250);
    }, 10000);
  });
</script>
</body>
</html>
