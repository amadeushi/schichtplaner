# Changelog

Alle nennenswerten Änderungen an Schichtplaner werden hier festgehalten.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung an [Semantic Versioning](https://semver.org/lang/de/) (grob:
MAJOR für Breaking Changes an Daten/URLs, MINOR für neue Funktionen, PATCH
für Fixes). Die aktuell laufende Version steht in `app/version.php`.

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
