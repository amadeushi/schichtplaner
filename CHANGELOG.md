# Changelog

Alle nennenswerten Änderungen an Schichtplaner werden hier festgehalten.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung an [Semantic Versioning](https://semver.org/lang/de/) (grob:
MAJOR für Breaking Changes an Daten/URLs, MINOR für neue Funktionen, PATCH
für Fixes). Die aktuell laufende Version steht in `app/version.php`.

## [1.8.0] - 2026-09-17

### Hinzugefügt
- E-Mail-Vorlagen: Betreff und Text jeder System-Mail (Konto angelegt,
  Passwort zurückgesetzt, neue Bewerbung, Bewerbung entschieden, Zuweisung
  entfernt, Sammel-Mail bei Veröffentlichung) sind unter Einstellungen mit
  Platzhaltern wie `{{name}}` anpassbar. Eine leere/zurückgesetzte Vorlage
  verwendet automatisch den eingebauten Standardtext.
- Protokoll-Verwaltung: konfigurierbare maximale Protokolllänge (Anzahl
  Einträge, Standard 1000) unter Einstellungen, mit Anzeige der
  Gesamtzahl und einem Löschen-Button für die ältesten Einträge über dem
  Limit.
- Arbeitszeiten-Export: CSV-Export (Excel-kompatibel, Semikolon-getrennt)
  für einen einzelnen Mitarbeiter oder alle auf einmal, pro Monat, zur
  Dokumentation gegenüber Steuerberater/Lohnverrechnung.
- `bin/migrate_admin_tools.php` für bereits laufende Installationen.

### Hinweis
- Einträge in der Stundenübersicht eines Mitarbeiters bearbeiten oder
  löschen kann der Admin bereits seit Version 1.5.0
  (`admin/time_entries.php`) — keine neue Funktion, nur zur Klarstellung.

## [1.7.3] - 2026-09-17

### Behoben
- Mitarbeiter verwalten: die Tabelle (Name/E-Mail/Rolle/Status/Zeiterfassung/
  Aktion, sechs Spalten) war auf die mobile-first 640px-Spalte begrenzt und
  presste selbst am Desktop jede Spalte so eng, dass kurze Namen ("Hamun"),
  E-Mail-Adressen und sogar Status-Badges ("AKTIV") mitten im Wort umbrachen.
  Bekommt jetzt wie `admin/calendar.php` mehr Breite am Desktop (`main.wide`).
- Grundsätzlicher: das erzwungene `word-break` auf Tabellenzellen (aus der
  letzten Korrektur) brach auch kurze, normale Wörter, sobald eine Spalte
  knapp wurde, statt die insgesamt vorhandene Breite zu nutzen. Zellen
  brechen jetzt nur noch an echten Wortgrenzen; ein wirklich unbrechbares
  langes Wort lässt die Tabelle stattdessen horizontal scrollen
  (`overflow-x`, siehe 1.7.1) statt mitten im Wort zu reißen.
- Badges brechen jetzt ebenfalls nie mehr mitten im Wort (dieselbe Regel wie
  zuvor schon bei Buttons).
- Der Zeiterfassung-Umschalter ("Aktiv"/"Gesperrt") hat jetzt eine
  einheitliche Mindestbreite, statt je nach Zustand unterschiedlich breit zu
  sein.

## [1.7.2] - 2026-09-17

### Behoben
- Kalenderansicht: Wochentags-Spaltenköpfe und -Zellen ohne eigene Fläche
  (alle außer Wochenende/heute) zeigten dunklen Ink-Text direkt auf dem
  dunklen Tresen-Hintergrund — kaum lesbar. Das Wochenraster bekommt jetzt
  selbst eine Papier-Fläche, wie jede andere Tabelle im System.
- Uneinheitliche Ad-hoc-Schriftgrößen im Kalender-Chip (Zeit 0.7rem,
  Entwurf-Label 0.62rem) auf die dokumentierte Label-Untergrenze (0.72rem)
  vereinheitlicht.
- Eine zugewiesene, aber noch unveröffentlichte Schicht im Kalender bekommt
  jetzt zusätzlich zum Text-Label die im System etablierte gestrichelte
  "nicht final"-Umrandung (wie offene Schichten und `.badge.pending`), statt
  allein auf die kleine Beschriftung angewiesen zu sein.
- Lange, ungebrochene Namen in der schmalen Mitarbeiter-Spalte des mobilen
  Kalenders brachen mitten im Wort ohne Trennstrich (z.B. "Musterlanger" /
  "name"). Die Spalte ist jetzt etwas breiter und nutzt echte deutsche
  Silbentrennung (`hyphens: auto`), sodass ein nötiger Umbruch an einer
  Silbengrenze mit sichtbarem Trennstrich erfolgt statt beliebig.

## [1.7.1] - 2026-09-16

### Behoben
- Tabellen mit mehreren Aktions-Buttons in einer Zelle (z.B. "Passwort
  zurücksetzen" + "Deaktivieren" in Mitarbeiter verwalten, oder
  "Bearbeiten" + "Löschen" in der Zeiterfassung) konnten am Desktop die
  Tabelle über die Kartenbreite hinaus zwingen — die Buttons rutschten
  sichtbar über den Kartenrand auf den dunklen Tresen-Hintergrund. Neue
  `.table-actions`-Klasse lässt mehrere Buttons in einer Zelle kontrolliert
  umbrechen; lange, ungebrochene Inhalte (z.B. E-Mail-Adressen) dürfen jetzt
  innerhalb ihrer Spalte umbrechen statt die Tabelle in die Breite zu
  ziehen; als Sicherheitsnetz bekommt jede Tabelle zusätzlich `overflow-x:
  auto`, falls trotzdem einmal nicht genug Platz ist — dann scrollt die
  Tabelle innerhalb der Karte, statt sichtbar über den Rand zu laufen.
  Betroffen und behoben: Mitarbeiter verwalten, Zeiterfassung (Admin und
  Mitarbeiter-Ansicht).

## [1.7.0] - 2026-09-16

### Hinzugefügt
- Entwurf/Publish-Workflow für Schichten (angelehnt an edtime): neue
  Schichten und jede Änderung an einer bereits veröffentlichten Schicht
  (Bearbeiten, Zu-/Abweisen, eine Bewerbung annehmen, im Kalender
  verschieben/umbesetzen) sind für Mitarbeiter unsichtbar und nicht
  bewerbbar, bis der Admin die Woche über eine neue Publish-Leiste
  explizit veröffentlicht.
- Statt einzelner Mails pro Änderung sammelt sich alles bis zum nächsten
  Publish und geht dann als eine einzige, unspezifische Sammel-Mail
  ("Änderungen an deinem Plan") an jede tatsächlich betroffene Person —
  keine Schicht-Details im E-Mail-Text, nur ein Verweis auf die App.
- Eine Bewerbungs-Ablehnung bleibt bewusst die eine Ausnahme und wird
  weiterhin sofort verschickt, da sie eine direkte persönliche Antwort ist.
- `bin/migrate_publish.php` für bereits laufende Installationen.

## [1.6.0] - 2026-09-16

### Hinzugefügt
- Vorläufige Passwörter (Neuanlage und Zurücksetzen durch den Admin)
  werden jetzt per E-Mail an den Mitarbeiter verschickt, unabhängig von
  dessen Benachrichtigungseinstellung — ohne dieses Passwort kann er sich
  gar nicht anmelden. Schlägt der Versand fehl oder ist SMTP nicht
  konfiguriert, zeigt die Oberfläche dem Admin das Passwort weiterhin
  zur manuellen Weitergabe an.
- `must_change_password` wird jetzt tatsächlich durchgesetzt: Ein Nutzer
  mit vorläufigem Passwort wird von jeder Seite außer dem Profil zurück
  dorthin geleitet, bis er ein eigenes Passwort gesetzt hat. Vorher war
  das Feld nur in der Datenbank gesetzt, aber nirgends geprüft.

## [1.5.1] - 2026-09-16

### Behoben
- Kalenderansicht war weiterhin auf die mobile 640px-Spaltenbreite
  begrenzt und zeigte deshalb selbst auf einem echten Desktop-Bildschirm
  einen unnötigen Scrollbalken. `main` bekommt jetzt ein `.wide`-Modifier
  (bis 1200px), nur für diese eine Seite gesetzt.

## [1.5.0] - 2026-09-16

### Hinzugefügt
- Neue Kalenderansicht für den Admin (`admin/calendar.php`, nur Desktop):
  Wochenraster mit Mitarbeiter-Zeilen und Tag-Spalten, Schichten per Drag &
  Drop verschieben (Datum) oder umverteilen (Mitarbeiter). Reine
  Ergänzung zum bestehenden Bon-Strang, keine eigene
  Annehmen/Ablehnen-Oberfläche.
- "Bearbeiten"-Funktion für Schichten im Schichtplan (Titel, Ort, Datum,
  Zeiten, benötigte Anzahl, Notiz) — bisher nur Anlegen/Löschen/Status
  möglich.

## [1.4.0] - 2026-09-16

### Geändert
- Seitenhintergrund von einer flachen Fläche zu einem sanften Verlauf
  (`--paper-surround-top` → `--paper-surround-bottom`) — wirkt weniger
  schwer, ohne vom dunklen Tresen-Look abzurücken.
- Der rote Ring um Zeus' Foto auf der Login-Seite liest jetzt tatsächlich
  als Siegel/Stempel: doppelter, absichtlich leicht unrund registrierter
  Ring statt eines einzelnen (rotationsunabhängig identischen) Kreisrahmens.

### Hinzugefügt
- Footer mit App-Name, Firmenname ("Amadeus Delivery") und verlinkter
  Versionsnummer auf jeder Seite.
- `app/version.php` als einzige Quelle der Wahrheit für die Versionsnummer.
- Dieses Versionsprotokoll.

## [1.3.0] - 2026-09-16

### Hinzugefügt
- Admin-Funktion "Dringende Mitteilung": ein farblich hervorgehobener
  Aushang über der Wochenansicht (Mein Plan und Schichtplan), vom Admin in
  den Einstellungen befüllbar, bleibt beim Wechseln der Kalenderwoche
  stehen.

## [1.2.0] - 2026-09-16

### Geändert
- Buttons, Inputs und Selects auf eine Mindest-Tapfläche angehoben
  (44px für primäre Aktionen, 36px kompakt) — vorher bis zu ~25px bei
  `.btn.small`, zu klein für einhändige Bedienung in der Küche.
- Rund 60 Stellen mit ASCII-Umlaut-Ersatz (ae/oe/ue) auf echte deutsche
  Rechtschreibung (ä/ö/ü) korrigiert.
- `Mailer.php` gehärtet: SMTP-/MIME-Header-Injection über Name/Betreff
  verhindert, Empfänger-Adresse vor Verwendung validiert.

## [1.1.0] - 2026-09-16

### Geändert
- Visuelle Neuausrichtung "Der Bon-Strang": Seitenhintergrund von generischem
  Creme auf einen dunklen Espresso-Umber-Ton umgestellt, aus dem Fell des
  Maskottchens Zeus abgeleitet statt frei erfunden.
- Echtes AMDS-Logo im Header statt Text-Wortmarke.

### Hinzugefügt
- Zeus' Foto auf der Login-Seite.
- Neue Farb-Tokens `surround-ink`/`surround-ink-soft` für Text direkt auf
  dem dunklen Seitenhintergrund.

## [1.0.0] - 2026-09-15

### Hinzugefügt
- Erste lauffähige Version: Schichtverwaltung (Bewerben/Zuweisen/Genehmigen),
  Zeiterfassung mit Ein-/Ausstempeln, E-Mail- und n8n-Webhook-Benachrichtigungen,
  Admin-Bereich für Mitarbeiter, Schichten und Einstellungen.
- Selbst-gehostet auf einem Raspberry Pi, reines PHP + SQLite ohne Framework.
- Konto-Sperre nach wiederholten Fehlanmeldungen, CSRF-Schutz, Passwort-Hashing.
- "Der Bon-Strang"-Designsystem (Ticket-Optik mit Lochrand und Risskante).
