# Sicherheitsrichtlinie

Schichtplaner verarbeitet Mitarbeiter- und Arbeitszeitdaten eines kleinen Betriebs.
Sicherheitslücken nehmen wir deshalb ernst.

## Schwachstelle melden

Bitte **nicht** als öffentliches Issue melden, solange die Lücke nicht behoben ist.
Nutze stattdessen die private Meldung von GitHub: im Repository unter
**Security → Report a vulnerability**.

Hilfreich sind eine kurze Beschreibung, die betroffene Seite oder Datei, die Schritte
zum Nachstellen und die vermutete Auswirkung. Echte Zugangsdaten, API-Schlüssel oder
personenbezogene Daten bitte nicht mitschicken.

## Unterstützte Versionen

Gepflegt wird jeweils nur die aktuelle Version (siehe `app/version.php` und
[CHANGELOG.md](CHANGELOG.md)). Sicherheitsrelevante Korrekturen sind im Changelog als
solche gekennzeichnet.

## Was das Projekt bewusst so macht

- Keine Abhängigkeiten von Drittpaketen (kein Composer, kein JS-Build), damit es nichts
  gibt, was veralten oder über die Lieferkette angegriffen werden könnte.
- Alle Datenbankzugriffe laufen über PDO Prepared Statements, Formulare sind per
  CSRF-Token geschützt, Ausgaben werden mit `htmlspecialchars` escaped.
- Zugangsdaten (SMTP, SMS-Gateway) liegen in `app/config.php` bzw. in den
  Einstellungen und nie im Repository (`.gitignore`).
