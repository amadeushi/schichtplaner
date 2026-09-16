# Schichtplaner

Kleines Personalplanungs-Tool: Mitarbeiter bewerben sich auf vom Admin
angelegte Schichten, der Admin gibt Bewerbungen frei oder lehnt sie ab.
Beide Seiten koennen per E-Mail und/oder n8n-Webhook benachrichtigt werden.

Reine PHP/SQLite-Anwendung ohne Framework und ohne Build-Schritt — laeuft
bewusst ressourcenschonend, auch auf einem Raspberry Pi 3B mit 1GB RAM.

## Warum SQLite statt MySQL/MariaDB?

Ein separater MariaDB-Server belegt auf einem Pi 3B allein 150-300MB RAM.
SQLite laeuft ohne eigenen Serverprozess direkt im PHP-Prozess, braucht
praktisch keinen zusaetzlichen Speicher und reicht fuer die Schreiblast
dieser Anwendung (gelegentliche Bewerbungen/Freigaben) locker aus. Die
Datenbank ist eine einzelne Datei (`storage/database.sqlite`), Backups
sind daher ein einfaches `cp`.

## Voraussetzungen

- PHP 8.1 oder neuer mit den Extensions `pdo_sqlite` und `curl`
- Ein Webserver (empfohlen: nginx + php-fpm, alternativ Apache mit mod_php)
- Kein Composer, kein Node.js, kein Build-Schritt noetig

## Installation (lokal / Server allgemein)

```bash
cd schichtplaner
cp app/config.example.php app/config.php
```

`app/config.php` anpassen: mindestens `app_url`, optional SMTP-Zugangsdaten
fuer E-Mail-Versand (siehe unten).

Datenbank wird beim ersten Aufruf automatisch aus `database/schema.sql`
angelegt (Ordner `storage/` muss beschreibbar sein).

Ersten Administrator anlegen:

```bash
php bin/create_admin.php "Vorname Nachname" admin@example.com
```

Das Skript gibt ein einmaliges Passwort aus — bitte notieren und nach dem
ersten Login unter "Profil" aendern.

## Deployment auf einem Raspberry Pi 3B (1GB RAM)

Empfehlung: **nginx + php-fpm** (deutlich sparsamer als Apache+mod_php).

```bash
sudo apt update
sudo apt install -y nginx php-fpm php-sqlite3 php-curl

sudo mkdir -p /var/www/schichtplaner
sudo rsync -a --exclude storage/*.sqlite* ./schichtplaner/ /var/www/schichtplaner/
sudo chown -R www-data:www-data /var/www/schichtplaner/storage

sudo cp /var/www/schichtplaner/app/config.example.php /var/www/schichtplaner/app/config.php
sudo -u www-data nano /var/www/schichtplaner/app/config.php   # SMTP etc. eintragen

sudo -u www-data php /var/www/schichtplaner/bin/create_admin.php "Admin" admin@example.com
```

nginx-Vhost (`/etc/nginx/sites-available/schichtplaner`), **`root` zeigt
bewusst auf `public/`**, damit `app/`, `database/` und `storage/` vom Web
aus nicht erreichbar sind:

```nginx
server {
    listen 80;
    server_name schichtplaner.local;
    root /var/www/schichtplaner/public;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;  # ggf. PHP-Version anpassen
    }

    location ~ /\. {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/schichtplaner /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

php-fpm auf dem Pi etwas schlanker konfigurieren
(`/etc/php/8.2/fpm/pool.d/www.conf`), da 1GB RAM insgesamt zur Verfuegung
steht und auch fuer OS/nginx/SSH Platz bleiben muss:

```ini
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 30s
```

`sudo systemctl restart php8.2-fpm`.

Fuer HTTPS aus dem lokalen Netz/Internet empfiehlt sich ein Reverse Proxy
mit automatischem Zertifikat, z.B. Caddy oder nginx + certbot — danach
`session_secure_cookie` in `app/config.php` auf `true` setzen.

### Ressourcenverbrauch grob einordnen

- nginx: ~5-10MB
- php-fpm (2-4 Worker im Leerlauf): ~30-60MB
- SQLite: kein eigener Prozess, Bruchteile von MB im PHP-Prozess
- Restlicher RAM bleibt fuer OS, SSH, ggf. n8n auf demselben Geraet frei

Diese Anwendung laeuft damit auch problemlos parallel zu einer n8n-Instanz
auf demselben Pi, sofern n8n selbst genug RAM erhaelt (n8n allein braucht
je nach Workflow-Last eher 200-400MB — bei sehr knappem RAM ggf. n8n auf
einem zweiten Geraet oder als Cloud-/separate Instanz betreiben).

## E-Mail-Versand konfigurieren

In `app/config.php` unter `smtp`: `enabled => true` setzen und Zugangsdaten
eines bestehenden SMTP-Kontos eintragen (z.B. ein Firmenpostfach, Gmail mit
App-Passwort, oder ein Transaktions-Mail-Dienst). Lokaler Mailversand direkt
vom Pi wird von den meisten Providern als Spam eingestuft, daher ist ein
externer SMTP-Relay die zuverlaessigere Wahl. Es wird ein minimaler,
abhaengigkeitsfreier SMTP-Client mitgeliefert (`app/services/Mailer.php`,
unterstuetzt STARTTLS/SSL + AUTH LOGIN) — kein Composer/PHPMailer noetig.

## n8n-Anbindung (optional)

Unter "Einstellungen" (Adminbereich) kann eine n8n-Webhook-URL hinterlegt
werden. Bei folgenden Ereignissen wird ein JSON-POST gesendet:

- `shift.published` — neue Schicht wurde angelegt/freigegeben
- `application.created` — Mitarbeiter hat sich beworben
- `application.decided` — Admin hat Bewerbung angenommen/abgelehnt

Beispiel-Payload:

```json
{
  "event": "application.created",
  "timestamp": "2026-08-17T10:00:00+02:00",
  "data": {
    "shift": { "id": 12, "title": "Spaetschicht", "date": "2026-08-20", "start": "14:00", "end": "22:00", "location": "Filiale Nord" },
    "applicant": { "id": 4, "name": "Max Mustermann", "email": "max@example.com" }
  }
}
```

In n8n reicht ein "Webhook"-Trigger-Node (Methode POST), dessen
Produktions-URL du in den App-Einstellungen eintraegst. Darauf lassen sich
beliebige weitere Aktionen aufbauen: Telegram-/Slack-Nachricht, zusaetzliche
E-Mail-Formate, Eintrag in ein Google-/Excel-Sheet, Push-Benachrichtigung,
etc. Ein "Test-Webhook senden"-Button in den Einstellungen hilft beim
Einrichten; ein Protokoll der letzten Zustellversuche (E-Mail + Webhook)
wird ebenfalls dort angezeigt.

## Funktionsumfang

- Rollen: Administrator und Mitarbeiter
- Admin legt Schichten an (Titel, Datum, Zeit, Ort, benoetigte Anzahl
  Personen, optional woechentliche Wiederholung)
- Mitarbeiter sehen offene Schichten und bewerben sich mit einem Klick
- Admin nimmt Bewerbungen an oder lehnt sie ab; Schicht wird automatisch
  auf "besetzt" gesetzt, sobald genug Personen bestaetigt sind
- Mitarbeiter koennen ausstehende Bewerbungen zuruckziehen
- Benachrichtigungen per E-Mail und/oder n8n-Webhook bei Bewerbung,
  Entscheidung und neuer Schicht
- Mitarbeiter koennen E-Mail-Benachrichtigungen im Profil abschalten
- Admin verwaltet Mitarbeiterkonten (anlegen, deaktivieren, Passwort
  zuruecksetzen)
- CSRF-Schutz auf allen Formularen, Passwoerter gehasht (bcrypt via
  `password_hash`), Prepared Statements gegen SQL-Injection

## Bewusst nicht enthalten (haette Umfang und Ressourcenbedarf erhoeht)

- Kein Kalender-/Drag&Drop-UI wie bei kommerziellen Tools (edtime.de etc.)
  — stattdessen einfache, schnelle Listenansichten
- Keine automatischen Schichttausch-Workflows zwischen Mitarbeitern
- Keine Zeiterfassung/Stundenabrechnung

Diese Punkte lassen sich bei Bedarf spaeter ergaenzen, ohne die
Grundarchitektur zu aendern.

## Backup

Ein Cronjob, der die SQLite-Datei taeglich sichert, reicht aus:

```bash
0 3 * * * cp /var/www/schichtplaner/storage/database.sqlite /var/backups/schichtplaner-$(date +\%Y\%m\%d).sqlite
```
