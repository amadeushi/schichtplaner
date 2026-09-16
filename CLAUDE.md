# Schichtplaner — Projektkontext für Claude

Selbst-gehostetes Schichtplanungs-/Zeiterfassungs-Tool für Amadeus Delivery
(AMDS), ein Gastronomie-/Lieferdienst-Betrieb in Österreich. Details zu
Produkt und Design stehen in [PRODUCT.md](PRODUCT.md) und [DESIGN.md](DESIGN.md)
— hier geht es nur um die technischen/operativen Leitplanken für die Arbeit
an diesem Repo.

## Harter technischer Rahmen

- **Reines PHP + PDO/SQLite.** Kein Framework, kein Composer, kein
  Node/JS-Build-Schritt, keine client-seitige JS-Library. Das ist bewusst so,
  weil die Produktion auf einem ressourcenknappen Raspberry Pi läuft, der sich
  RAM mit anderen Diensten teilt. Neue Abhängigkeiten (PHP-Pakete, JS-Bundles,
  schwere Animationsbibliotheken) sind ein Zielkonflikt mit dieser Vorgabe —
  vorher nachfragen, nicht einfach hinzufügen.
- **Deutsche UI-Sprache mit echten Umlauten.** ä/ö/ü/ß immer als echte
  UTF-8-Zeichen, nie als ae/oe/ue/ss-Transliteration. Der Stack unterstützt
  das vollständig (UTF-8-Templates, `htmlspecialchars(..., 'UTF-8')`,
  SQLites natives UTF-8) — es gibt keinen technischen Grund für ASCII-Ersatz.
- **Mobile-first.** Beide Rollen (Mitarbeiter, Admin) nutzen die App
  überwiegend am Handy, oft einhändig im Küchenbetrieb. Touch-Targets sollten
  nicht unter ~36px (kompakt) bzw. ~44px (primäre Aktionen) liegen — siehe
  "The Comfortable-Tap Rule" in DESIGN.md.

## Design-System

`public/partials/header.php` ist die einzige gemeinsame Stylesheet-Quelle
(CSS-Custom-Properties/Tokens, keine separate Datei). Das Design-System
("Der Bon-Strang") ist in [DESIGN.md](DESIGN.md) dokumentiert, mit
Sidecar-Metadaten in `.impeccable/design.json`. Bei sichtbaren UI-Änderungen:
DESIGN.md/Sidecar aktuell halten, und wenn das Impeccable-Skill verfügbar
ist, `impeccable detect` gegen die geänderten Dateien laufen lassen.

## Konfiguration

- `app/config.php` — echte lokale Konfiguration (SMTP, DB-Pfad, Session-
  Security). Ist in `.gitignore`, landet nie im Repo.
- `app/config.example.php` — das Template mit Platzhaltern, das committed
  wird. Bei neuen Config-Keys immer beide Dateien pflegen.

## Lokale Entwicklung

PHP-Built-in-Server über `.claude/launch.json` (Konfigurationsname
`schichtplaner`, Port 8098): `php -S localhost:8098 -t public`.

Test-Admin lokal anlegen:
```bash
php bin/create_admin.php "Name" email@example.com
```
Erzeugt ein zufälliges Passwort. Für reproduzierbare Test-Logins stattdessen
direkt per PHP-Einzeiler einen bekannten Passwort-Hash setzen (siehe
Session-Historie für das Muster) — danach die Test-Daten wieder aus der
lokalen `storage/database.sqlite` entfernen, nicht committen.

## Deployment (Produktion)

Läuft auf einem Raspberry Pi ("ballotpi") unter `schichtplaner.amds.at`
(nginx + php-fpm, Let's Encrypt/certbot, ufw). Deploy-Ablauf für geänderte
Dateien:

1. Per SSH als `hamun@ballotpi` verbinden (Key-Auth, passwortloses `sudo`
   ist eingerichtet).
2. Geänderte Dateien via `rsync` in ein `/tmp`-Staging-Verzeichnis auf dem
   Pi kopieren (der Zielordner `/var/www/schichtplaner/public/...` gehört
   `www-data`, `hamun` kann dort nicht direkt per rsync schreiben).
3. Mit `sudo cp` vom Staging-Verzeichnis in `/var/www/schichtplaner/...`
   kopieren, dann `sudo chown www-data:www-data` + `sudo chmod 644` setzen.
4. `php -l` auf jeder deployten Datei remote laufen lassen.
5. **Vor Abschluss immer per SHA-256 verifizieren**, dass lokale und
   remote Datei byte-identisch sind (`shasum -a 256` lokal vs.
   `sha256sum` remote vergleichen) — erst dann gilt der Deploy als
   abgeschlossen.

**Nie selbst Passwörter des Nutzers eingeben** (Login-Passwörter, sudo-
Passwörter etc.) — wenn ein Befehl ein Passwort braucht, das der Nutzer
selbst eingeben muss, den Nutzer bitten, ihn selbst auszuführen.

## Git / GitHub

Das Repo ist öffentlich unter `github.com/amadeushi/schichtplaner`. Remote
läuft über SSH (`git@github.com:amadeushi/schichtplaner.git`) mit dem Key
`schichtplaner-deploy` (demselben Key wie für den Pi-Zugriff — bewusste
Entscheidung, kein separater Key). Vor jedem Commit/Push kurz prüfen, dass
`app/config.php` und `storage/*.sqlite*` nicht versehentlich getrackt sind
(`.gitignore` deckt das ab, aber bei neuen lokalen Artefakten nachschauen).

## Tests

Es gibt aktuell **keine automatisierte Test-Suite**. CI (`.github/workflows/`)
prüft bisher nur `php -l` (Syntaxfehler) über alle PHP-Dateien — das ist
kein Ersatz für echte Tests, nur ein Mindest-Sicherheitsnetz.
