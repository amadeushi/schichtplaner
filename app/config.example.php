<?php
/**
 * Kopiere diese Datei nach config.php und trage deine Werte ein.
 * config.php steht NICHT unter public/ und ist damit vom Webserver
 * aus nicht direkt abrufbar.
 */

return [
    'app_url' => 'http://schichtplaner.local',

    'db_path' => __DIR__ . '/../storage/database.sqlite',

    // SMTP fuer E-Mail-Benachrichtigungen (z.B. Gmail App-Passwort,
    // Mailgun, ein Provider-Postfach, o.ae.). Lokaler Mailversand vom
    // Pi aus wird von den meisten Empfängern als Spam markiert, daher
    // wird ausdruecklich ein externer SMTP-Relay empfohlen.
    'smtp' => [
        'enabled'    => false,
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'encryption' => 'tls', // 'tls', 'ssl' oder 'none' (nur fuer vertrauenswuerdige lokale Relays)
        'username'   => 'user@example.com',
        'password'   => 'geheim',
        'from_email' => 'schichtplaner@example.com',
        'from_name'  => 'Schichtplaner',
    ],

    // SMS-Benachrichtigungen ueber das eigene SMS-Gateway (POST /v1/messages). Unter denselben
    // Bedingungen wie E-Mails, zusaetzlich nur mit hinterlegter Mobilnummer des Mitarbeiters.
    // Der Schluessel laesst sich hier ODER im Adminbereich (Einstellungen > SMS-Versand) hinterlegen;
    // ein dort eingegebener Schluessel hat Vorrang. Nie ins Repo. Am besten ein eigener Schluessel
    // fuer dieses Projekt: auf dem SMS-Pi `sudo sms-project create-project schichtplaner 100`.
    // Nur HTTPS ist erlaubt (Ausnahme: localhost fuer Tests).
    'sms' => [
        'enabled'              => false,
        'base_url'             => 'https://sms.amds.at',
        'api_key'              => '',
        'default_country_code' => '+43', // fuer Eingaben wie 0664 1234567
        'timeout'              => 6,     // Sekunden je Aufruf
    ],

    // Session-Sicherheit. Auf 'true' setzen, sobald die Seite ueber
    // HTTPS erreichbar ist (empfohlen, z.B. via Caddy/Let's Encrypt).
    'session_secure_cookie' => false,

    'timezone' => 'Europe/Berlin',
];
