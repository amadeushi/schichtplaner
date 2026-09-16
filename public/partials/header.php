<?php
/** @var array|null $user */
$appName = setting('app_name', 'Schichtplaner');
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
function navActive(string $path, array $matches): bool
{
    foreach ($matches as $m) {
        if (str_ends_with($path, $m)) {
            return true;
        }
    }
    return false;
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($appName) ?></title>
<style>
  :root {
    --paper-surround: #332b1c;
    --paper-surround-top: #443821;
    --paper-surround-bottom: #251f14;
    --paper: #faf3e3;
    --ink: #1c1917;
    --ink-soft: #6b6459;
    --ink-line: #ddd4bf;
    --ink-line-strong: #b9ad91;
    --stamp: #c8391f;
    --stamp-wash: #f7e3dc;
    --danger: #b3261e;
    --danger-wash: #f7e3dc;
    --confirm-wash: #eee8d8;
    --surround-ink: #f3e8d3;
    --surround-ink-soft: #a8967a;
    --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    --font-mono: ui-monospace, "SF Mono", "Roboto Mono", "IBM Plex Mono", Menlo, Consolas, monospace;
    --tab-bar-h: 62px;
    --safe-b: env(safe-area-inset-bottom, 0px);
  }
  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; scrollbar-color: var(--ink-line-strong) var(--paper-surround); }
  ::selection { background: var(--stamp); color: #fff8f4; }
  input, textarea { caret-color: var(--stamp); }
  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-track { background: var(--paper-surround); }
  ::-webkit-scrollbar-thumb { background: var(--ink-line-strong); border-radius: 5px; border: 2px solid var(--paper-surround); }
  body {
    margin: 0;
    font-family: var(--font-sans);
    background: linear-gradient(180deg, var(--paper-surround-top) 0%, var(--paper-surround-bottom) 100%);
    color: var(--ink);
    padding-bottom: calc(var(--tab-bar-h) + var(--safe-b) + 0.5rem);
    min-height: 100vh;
  }
  a { color: inherit; }

  /* Kopfleiste: schmal, nur Marke + Kontext */
  header.topbar {
    background: var(--paper);
    border-bottom: 1px solid var(--ink-line);
    padding: 0.7rem 1.1rem;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 20;
  }
  header.topbar .brand { display: flex; align-items: center; text-decoration: none; }
  header.topbar .brand img { height: 20px; width: auto; display: block; }
  header.topbar .who { font-size: 0.8rem; color: var(--ink-soft); text-decoration: none; }

  main { max-width: 640px; margin: 0 auto; padding: 1rem 1rem 1.5rem; }
  /* Ausnahme für admin/calendar.php: 7 Tagspalten + Namensspalte brauchen mehr als die
     mobile-first 640px-Spalte, sonst erzwingt die Tabelle einen Scrollbalken, den es auf
     einem echten Desktop-Bildschirm gar nicht braucht. */
  main.wide { max-width: 1200px; }

  .site-footer { margin-top: 1.5rem; text-align: center; font-size: 0.72rem; color: var(--surround-ink-soft); letter-spacing: 0.02em; }
  .site-footer a { color: var(--surround-ink); text-decoration: underline; text-underline-offset: 2px; }

  h1 { font-size: 1.3rem; margin: 0.2rem 0 1rem; letter-spacing: -0.01em; color: var(--surround-ink); }
  .card h1 { color: var(--ink); }
  h2 { font-size: 1rem; margin: 0 0 0.6rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-soft); font-weight: 700; }
  h3 { font-size: 0.95rem; margin: 0 0 0.4rem; }

  .card {
    background: var(--paper);
    border: 1px solid var(--ink-line);
    border-radius: 3px;
    padding: 1.1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 0 rgba(28,25,23,0.04);
  }

  /* overflow-x als Sicherheitsnetz: erzwingt bei zu vielen/zu breiten Spalten (z.B. lange
     E-Mail-Adressen plus mehrere Aktions-Buttons) einen Scrollbalken innerhalb der Karte statt
     dass die Tabelle sichtbar über den Kartenrand auf den dunklen Tresen-Hintergrund hinausläuft. */
  table { width: 100%; border-collapse: collapse; overflow-x: auto; }
  th, td { text-align: left; padding: 0.55rem 0.5rem; border-bottom: 1px solid var(--ink-line); font-size: 0.9rem; vertical-align: middle; }
  /* Nur Zelleninhalt (E-Mail-Adressen etc.) darf hart umbrechen, damit eine Spalte nicht die ganze
     Tabelle in die Breite zieht; die kurzen Großbuchstaben-Spaltenköpfe bleiben unangetastet. */
  td { overflow-wrap: break-word; word-break: break-word; }
  th { color: var(--ink-soft); font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
  td.mono, .mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem;
    box-sizing: border-box; min-height: 2.75rem; padding: 0.55rem 1.1rem; border-radius: 3px;
    border: 1.5px solid var(--ink); background: var(--ink); color: var(--paper);
    cursor: pointer; font-size: 0.88rem; font-weight: 600; text-decoration: none;
    font-family: var(--font-sans); letter-spacing: 0.01em;
    -webkit-tap-highlight-color: transparent;
    /* nie mitten im Wort brechen (z.B. eine Tabellenzelle, die sonst Umbruch erzwingt) -
       ein Button-Label bricht höchstens zwischen Wörtern, wie "zurücksetzen" ungeteilt. */
    overflow-wrap: normal; word-break: normal;
  }
  .btn:hover { background: #000; }
  .btn:active { transform: translateY(1px); }
  .btn.secondary { background: var(--paper); color: var(--ink); border-color: var(--ink-line-strong); }
  .btn.secondary:hover { background: var(--confirm-wash); }
  .btn.stamp-btn { background: var(--stamp); border-color: var(--stamp); color: #fff8f4; }
  .btn.stamp-btn:hover { background: #a92c17; }
  .btn.danger { background: transparent; color: var(--danger); border-color: var(--danger); }
  .btn.danger:hover { background: var(--danger-wash); }
  /* .small bleibt kompakt für dichte Reihen, aber nie unter 36px Kantenlänge */
  .btn.small { min-height: 2.25rem; min-width: 2.25rem; padding: 0.4rem 0.75rem; font-size: 0.8rem; }
  .btn:disabled { opacity: 0.45; cursor: not-allowed; }
  form.inline { display: inline; }
  /* Für Tabellenzellen mit mehreren Aktions-Buttons (z.B. "Passwort zurücksetzen" +
     "Deaktivieren"): umbricht kontrolliert statt die Spalte/Tabelle in die Breite zu zwingen. */
  .table-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }

  input, select, textarea {
    width: 100%; padding: 0.55rem 0.6rem; border: 1.5px solid var(--ink-line-strong);
    border-radius: 3px; font-size: 0.95rem; font-family: inherit; background: var(--paper); color: var(--ink);
    box-sizing: border-box; min-height: 2.75rem;
  }
  input:focus, select:focus, textarea:focus { outline: 2px solid var(--ink); outline-offset: 1px; }
  input[type="checkbox"], input[type="radio"] { width: auto; min-height: 0; accent-color: var(--stamp); }
  textarea { min-height: 5rem; }
  select.select-inline { width: auto; min-height: 2.25rem; padding: 0.4rem 0.5rem; font-size: 0.82rem; }
  label { display: block; font-size: 0.78rem; color: var(--ink-soft); margin-bottom: 0.3rem; margin-top: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

  /* Zustands-Badges: achromatisch, nur "heute/aktiv" bekommt die Stempelfarbe */
  .badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.18rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.03em; border: 1.5px solid currentColor;
  }
  .badge.confirmed, .badge.approved, .badge.open { color: var(--ink); background: var(--confirm-wash); border-color: var(--ink-line-strong); }
  .badge.pending { color: var(--ink-soft); background: transparent; border-style: dashed; }
  .badge.declined, .badge.rejected, .badge.withdrawn, .badge.closed { color: var(--ink-soft); background: transparent; text-decoration: line-through; opacity: 0.75; }
  .badge.filled { color: var(--ink); background: var(--confirm-wash); }
  .badge.today, .badge.active-duty, .badge.urgent { color: var(--stamp); background: var(--stamp-wash); border-color: var(--stamp); }

  /* Aushang: dringende Admin-Mitteilung über der Wochenansicht. Bewusst ein Card-, kein Ticket-Bauteil -
     Perforation/Risskante bleibt ein Signal ausschließlich für echte Schichten. */
  .notice-board { background: var(--stamp-wash); border: 1.5px solid var(--stamp); border-radius: 3px; padding: 0.9rem 1.1rem; margin-bottom: 1rem; }
  .notice-board .badge { margin-bottom: 0.5rem; }
  .notice-board-text { margin: 0; color: var(--ink); font-weight: 600; line-height: 1.45; white-space: pre-wrap; }

  /* Publish-Leiste: Entwürfe/ausstehende Änderungen für die Woche, gebündelt statt sofort
     verschickt (siehe admin/shifts.php Aktion 'publish'). Card-artig, damit ink/ink-soft passen. */
  .publish-bar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; background: var(--paper); border: 1px solid var(--ink-line); border-radius: 3px; padding: 0.75rem 1rem; margin-bottom: 1rem; }
  .publish-bar-summary { font-size: 0.85rem; color: var(--ink-soft); }
  .publish-bar-summary strong { color: var(--ink); }

  .flash { padding: 0.7rem 1rem; border-radius: 3px; margin-bottom: 1rem; font-size: 0.9rem; border: 1.5px solid; }
  .flash.success { background: var(--confirm-wash); border-color: var(--ink-line-strong); color: var(--ink); }
  .flash.error { background: var(--stamp-wash); border-color: var(--stamp); color: #7a2213; }
  .muted { color: var(--ink-soft); font-size: 0.85rem; }

  /* Bon-Strang: die zentrale Komponente */
  .rail { display: flex; flex-direction: column; gap: 1rem; position: relative; padding-left: 16px; }
  .rail::before {
    content: ""; position: absolute; left: 5px; top: 4px; bottom: 4px; width: 2px;
    background-image: linear-gradient(var(--ink-line-strong) 60%, transparent 0%);
    background-size: 2px 7px; background-repeat: repeat-y;
  }
  .rail-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--surround-ink-soft); font-weight: 700; margin: 1.5rem 0 0.6rem 16px; }
  .ticket.hero { padding: 1.4rem 1.4rem 1.6rem; }
  .ticket.hero .ticket-day { font-size: 1.25rem; }
  .ticket.hero .ticket-date { font-size: 0.88rem; }
  .ticket {
    background: var(--paper); border: 1.5px solid var(--ink-line); border-radius: 2px;
    position: relative; padding: 1.1rem 1.1rem 1.25rem; margin-top: 8px;
    box-shadow: 0 1px 0 rgba(28,25,23,0.05), 0 8px 16px -12px rgba(28,25,23,0.4);
  }
  .ticket::before {
    content: ""; position: absolute; top: -8px; left: 0; right: 0; height: 16px;
    background-image: radial-gradient(circle, var(--paper-surround) 4px, transparent 4.3px);
    background-size: 24px 16px; background-position: 12px center; background-repeat: repeat-x;
  }
  .ticket::after {
    content: ""; position: absolute; left: 0; right: 0; bottom: -7px; height: 14px;
    background-image:
      linear-gradient(135deg, var(--paper) 50%, transparent 50.5%),
      linear-gradient(45deg, var(--paper) 50%, transparent 50.5%);
    background-size: 14px 14px; background-position: left top; background-repeat: repeat-x;
  }
  .ticket.today, .ticket.on-duty { border-color: var(--stamp); }
  .ticket-head { display: flex; justify-content: space-between; align-items: baseline; gap: 0.6rem; margin-bottom: 0.5rem; }
  .ticket-day { font-weight: 700; font-size: 0.95rem; }
  .ticket-date { font-family: var(--font-mono); color: var(--ink-soft); font-size: 0.8rem; }
  .ticket-row { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.55rem 0; border-top: 1px dashed var(--ink-line); }
  .ticket-row:first-of-type { border-top: none; }
  .ticket-time { font-family: var(--font-mono); font-variant-numeric: tabular-nums; font-weight: 600; font-size: 0.92rem; white-space: nowrap; }
  .ticket-meta { font-size: 0.82rem; color: var(--ink-soft); }
  .ticket-empty { color: var(--ink-soft); font-size: 0.88rem; padding: 0.3rem 0; }

  .duty-stamp {
    width: 10rem; height: 10rem; border-radius: 50%; margin: 0 auto;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.35rem;
    cursor: pointer; font-family: var(--font-sans); -webkit-tap-highlight-color: transparent;
    transition: transform 0.15s ease; background: var(--paper);
  }
  .duty-stamp:active { transform: scale(0.96); }
  .duty-stamp.off-duty {
    border: 3px dashed var(--ink-line-strong); color: var(--ink-soft);
  }
  .duty-stamp.off-duty .duty-stamp-label { font-size: 1.05rem; font-weight: 700; line-height: 1.25; }
  .duty-stamp.on-duty {
    border: 4px solid var(--stamp); color: var(--stamp); transform: rotate(-6deg);
    mix-blend-mode: multiply; box-shadow: inset 0 0 0 2px var(--stamp-wash);
  }
  .duty-stamp.on-duty:active { transform: rotate(-6deg) scale(0.96); }
  .duty-stamp.on-duty .duty-stamp-label { font-size: 1.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
  .duty-stamp.on-duty .duty-stamp-time { font-size: 0.78rem; }

  /* Zeus-Rahmen: ein einzelner rotierter Kreis sieht identisch zu einem unrotierten aus, darum kein
     Stempel-Signal über Rotation allein. Stattdessen ein doppelter, absichtlich leicht unrund
     registrierter Ring (wie ein von Hand gedrücktes Siegel) plus mix-blend-mode: multiply für die
     Tuschequalität - dasselbe Blend-Prinzip wie beim Duty-Stamp, aber über zwei Ringe statt Rotation. */
  .zeus-frame { position: relative; width: 96px; height: 96px; margin: 0 auto 0.75rem; }
  .zeus-frame img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; }
  .zeus-frame::before {
    content: ""; position: absolute; inset: -4px; border-radius: 50%;
    border: 1.5px solid var(--stamp); mix-blend-mode: multiply;
  }
  .zeus-frame::after {
    content: ""; position: absolute; inset: -9px; border-radius: 48% 52% 51% 49% / 52% 48% 53% 47%;
    border: 2.5px solid var(--stamp); mix-blend-mode: multiply; transform: rotate(3deg);
  }

  .stamp-mark {
    display: inline-flex; align-items: center; justify-content: center;
    width: 2.6rem; height: 2.6rem; border-radius: 50%; border: 2.5px solid var(--stamp);
    color: var(--stamp); font-family: var(--font-mono); font-weight: 700; font-size: 0.62rem;
    letter-spacing: 0.02em; text-transform: uppercase; transform: rotate(-9deg);
    mix-blend-mode: multiply; text-align: center; line-height: 1.05; flex-shrink: 0;
  }

  /* Bottom-Tab-Navigation */
  nav.tabbar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
    background: var(--paper); border-top: 1px solid var(--ink-line);
    padding-bottom: var(--safe-b); height: calc(var(--tab-bar-h) + var(--safe-b));
  }
  nav.tabbar .tabbar-inner { display: flex; max-width: 640px; margin: 0 auto; height: 100%; }
  nav.tabbar a {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0.3rem; text-decoration: none; color: var(--ink-soft); font-size: 0.72rem;
    font-weight: 700; letter-spacing: 0.01em; -webkit-tap-highlight-color: transparent;
    font-family: var(--font-mono); text-transform: uppercase; position: relative;
  }
  nav.tabbar a::before {
    content: ""; width: 5px; height: 5px; border-radius: 50%; background: transparent;
  }
  nav.tabbar a.active { color: var(--stamp); }
  nav.tabbar a.active::before { background: var(--stamp); }

  @media (max-width: 640px) {
    .grid-2 { grid-template-columns: 1fr; }
    table:not(.week-grid), table:not(.week-grid) thead, table:not(.week-grid) tbody,
    table:not(.week-grid) th, table:not(.week-grid) td, table:not(.week-grid) tr { display: block; }
    table:not(.week-grid) thead { display: none; }
    table:not(.week-grid) tr { border-bottom: 1px solid var(--ink-line); padding: 0.5rem 0; }
    table:not(.week-grid) td { border: none; padding: 0.2rem 0; }
    table:not(.week-grid) td::before { content: attr(data-label) ": "; font-weight: 600; color: var(--ink-soft); }
  }

  /* Admin-Wochenraster (weniger Komposition, erbt Tokens) */
  .week-nav { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .week-nav-label { text-align: center; display: flex; flex-direction: column; gap: 0.1rem; color: var(--surround-ink); }
  .week-nav-label .muted { color: var(--surround-ink-soft); }
  .grid-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  table.week-grid { width: auto; min-width: 100%; border-collapse: collapse; }
  table.week-grid th, table.week-grid td { border: 1px solid var(--ink-line); padding: 0.4rem; vertical-align: top; font-size: 0.85rem; min-width: 128px; }
  table.week-grid th { text-align: center; white-space: nowrap; min-width: auto; }
  .sticky-col { position: sticky; left: 0; background: var(--paper); z-index: 2; font-weight: 600; white-space: nowrap; }
  table.week-grid thead .sticky-col { z-index: 3; }
  table.week-grid th.weekend, table.week-grid td.weekend { background: var(--confirm-wash); }
  table.week-grid th.today, table.week-grid td.today { outline: 2px solid var(--stamp); outline-offset: -2px; }
  .shift-chip { background: var(--paper); border: 1px solid var(--ink-line-strong); border-radius: 3px; padding: 0.3rem 1.3rem 0.3rem 0.4rem; margin-bottom: 0.3rem; font-size: 0.78rem; position: relative; }
  .shift-chip.pending { background: transparent; border: 1px dashed var(--ink-line-strong); }
  /* Als ziehbarer Kalender-Chip: kompaktes Padding (kein Platz für chip-remove reserviert),
     Titel/Zeit gestapelt, Greifhand-Cursor signalisiert die Drag-Fähigkeit. */
  .calendar-cell .shift-chip { display: block; padding: 0.3rem 0.4rem; cursor: grab; }
  .calendar-cell .shift-chip .chip-title { display: block; font-weight: 600; line-height: 1.2; }
  .calendar-cell .shift-chip .chip-time { display: block; font-size: 0.7rem; color: var(--ink-soft); }
  .chip-draft { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: var(--ink-soft); }
  .calendar-cell .shift-chip.dragging { opacity: 0.4; }
  .calendar-cell.today { background: var(--stamp-wash); }
  .calendar-cell { min-height: 3.2rem; }
  .calendar-cell.drag-over { outline: 2px dashed var(--stamp); outline-offset: -2px; background: var(--stamp-wash); }
  .chip-remove { position: absolute; top: 0.15rem; right: 0.3rem; background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0.1rem; }
  .cell-assign select { font-size: 0.78rem; padding: 0.3rem; width: auto; }
  .day-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
  .day-card { border: 1px solid var(--ink-line); border-radius: 3px; padding: 0.75rem; }
  .day-card.weekend { background: var(--confirm-wash); }
  .day-card.today { outline: 2px solid var(--stamp); outline-offset: -2px; }
  .day-card h3 { margin: 0 0 0.5rem; font-size: 0.9rem; }
  .day-card .shift-chip { position: static; padding: 0.4rem; }
  .day-card .chip-remove { display: none; }
  @media (max-width: 640px) {
    table.week-grid th, table.week-grid td { min-width: 108px; }
    .sticky-col { min-width: 96px; max-width: 96px; white-space: normal; }
  }
</style>
</head>
<body>
<!--
THESIS: Der Dienstplan ist kein Formular, er ist der Bon-Strang über der Ausgabe.
OWN-WORLD: Dunkler Espresso-Umber (aus Zeus' Fell/Maske) als Tresen, helles Bon-Papier darauf, ein gestempeltes Rot nur für heute/aktiv; Monospace für Zeiten, ruhiger Sans für Namen; echtes AMDS-Logo im Header. Jede Schicht ein perforierter Bon mit Lochrand.
STORY: Mitarbeiter sehen sofort, ob und wann sie dran sind, bewerben sich auf offene Bons, stempeln beim Kommen/Gehen; Admin sieht denselben Strang mit Zuweisungs-Kontrolle.
FIRST VIEWPORT: Bottom-Tab-Leiste unten; oben der heutige Bon vergrößert mit Lochrand und Stempel falls im Dienst; darunter der Wochenstrang, ein Bon pro Tag.
FORM: Der Bon-Strang, Impeccable's Pick aus der Direction-Runde, Seed f185dc6b.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
-->
<header class="topbar">
  <a href="/index.php" class="brand"><img src="/assets/logo.png" alt="<?= e($appName) ?>"></a>
  <?php if (!empty($user)): ?>
    <a href="/more.php" class="who"><?= e($user['name']) ?></a>
  <?php endif; ?>
</header>
<main<?= !empty($mainWide) ? ' class="wide"' : '' ?>>
<?php foreach (getFlashes() as $f): ?>
  <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
