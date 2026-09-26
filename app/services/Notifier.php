<?php
declare(strict_types=1);

/**
 * Bündelt E-Mail- und Webhook-Benachrichtigungen für die fachlichen
 * Ereignisse der Anwendung. Ein Ereignis kann über beide, einen, oder
 * keinen Kanal ausgeliefert werden - steuerbar in admin/settings.php
 * bzw. je Nutzer über users.notify_email.
 *
 * Betreff/Text jeder Mail sind in TEMPLATES als Standard hinterlegt und vom
 * Admin unter Einstellungen > E-Mail-Vorlagen mit Platzhaltern wie
 * {{name}} überschreibbar (Tabelle email_templates, siehe renderTemplate()).
 * Eine leere/fehlende Zeile in email_templates bedeutet: Standard verwenden.
 *
 * Persönliche Plan-Benachrichtigungen (applicationDecided, assignmentRemoved,
 * scheduleChanged) prüfen zusätzlich isUserAbsentOn() - wer heute laut eingetragener
 * Abwesenheit (siehe absences.php) nicht verfügbar ist, bekommt währenddessen keine
 * E-Mails über Planänderungen. Webhooks sind davon unberührt (kein persönlicher Kanal).
 *
 * Dieselben drei Anlässe gehen zusätzlich als SMS über SmsClient (dazu ein Rundruf "Neuer Plan verfügbar" per
 * SMS und E-Mail beim Veröffentlichen neuer offener Schichten, siehe newPlanAvailable) - nur ein neutraler Hinweis
 * mit Portal-Link (smsChangeNotice), nie mit Schichten oder Bewerbungsergebnissen,
 * unter denselben Bedingungen plus: Gateway konfiguriert, Mobilnummer vorhanden, notify_sms gesetzt.
 * Passwörter (Konto angelegt/zurückgesetzt) werden bewusst nie per SMS verschickt.
 */
final class Notifier
{
    private Mailer $mailer;
    private Webhook $webhook;

    /**
     * Einzige Quelle der Wahrheit für Standard-Betreff/-Text jeder Mail und ihre
     * verfügbaren Platzhalter - genutzt von renderTemplate() hier UND von
     * admin/settings.php, um die Bearbeiten-Formulare und ihre Hilfetexte zu bauen.
     */
    public const TEMPLATES = [
        'new_application' => [
            'label' => 'Neue Bewerbung (an Admin)',
            'subject' => 'Neue Bewerbung von {{applicant_name}}: {{shift_title}} am {{shift_date}}',
            'body' => "{{applicant_name}} würde gern eine Schicht übernehmen:\n\nSchicht: {{shift_title}}\nTag: {{shift_date}}\nZeit: {{shift_time}}\n\nSchau bei Gelegenheit im Adminbereich vorbei und gib Bescheid – {{applicant_name}} freut sich über eine Rückmeldung.",
            'placeholders' => ['applicant_name', 'shift_title', 'shift_date', 'shift_time'],
            'cta' => ['label' => 'Bewerbung ansehen', 'path' => '/admin/shifts.php'],
        ],
        'application_decided' => [
            'label' => 'Bewerbung entschieden (an Bewerber)',
            'subject' => 'Rückmeldung zu deiner Bewerbung: {{shift_title}} am {{shift_date}}',
            'body' => "Hallo {{name}},\n\n{{rueckmeldung}}\n\nSchicht: {{shift_title}}\nTag: {{shift_date}}\nZeit: {{shift_time}}",
            'placeholders' => ['name', 'shift_title', 'shift_date', 'shift_time', 'status_label', 'rueckmeldung'],
            'cta' => ['label' => 'Zum Plan', 'path' => '/my_week.php'],
        ],
        'account_created' => [
            'label' => 'Konto angelegt',
            'subject' => 'Willkommen im Team – dein Zugang zu {{app_name}}',
            'body' => "Hallo {{name}},\n\nschön, dass du dabei bist! Für dich wurde ein Konto in {{app_name}} angelegt. Dort siehst du deine Schichten und kannst dich auf offene Schichten bewerben.\n\nE-Mail: {{email}}\nVorläufiges Passwort: {{password}}\n\nMelde dich damit an und vergib beim ersten Login unter „Profil“ dein eigenes Passwort. Das vorläufige brauchst du nur für diesen ersten Schritt.",
            'placeholders' => ['name', 'email', 'password', 'app_name'],
            'cta' => ['label' => 'Jetzt anmelden', 'path' => '/login.php'],
        ],
        'password_reset' => [
            'label' => 'Passwort zurückgesetzt',
            'subject' => 'Dein neues vorläufiges Passwort für {{app_name}}',
            'body' => "Hallo {{name}},\n\nein Admin hat dein Passwort für {{app_name}} zurückgesetzt. Damit kommst du wieder rein:\n\nVorläufiges Passwort: {{password}}\n\nBitte vergib nach der Anmeldung unter „Profil“ ein eigenes Passwort. Falls dich das überrascht, frag kurz bei deinem Admin nach.",
            'placeholders' => ['name', 'password', 'app_name'],
            'cta' => ['label' => 'Jetzt anmelden', 'path' => '/login.php'],
        ],
        'assignment_removed' => [
            'label' => 'Zuweisung entfernt',
            'subject' => 'Kurze Info: Du bist für {{shift_title}} am {{shift_date}} ausgetragen',
            'body' => "Hallo {{name}},\n\nkurze Info zu deinem Plan: Du wurdest für diese Schicht ausgetragen.\n\nSchicht: {{shift_title}}\nTag: {{shift_date}}\nZeit: {{shift_time}}\n\nBei Fragen sprich einfach kurz mit deinem Admin. Danke für dein Verständnis!",
            'placeholders' => ['name', 'shift_title', 'shift_date', 'shift_time'],
            'cta' => ['label' => 'Zum Plan', 'path' => '/my_week.php'],
        ],
        'new_plan' => [
            'label' => 'Neuer Plan verfügbar (Rundmail bei Veröffentlichung neuer offener Schichten)',
            'subject' => 'Neuer Plan verfügbar – such dir deine Schichten aus',
            'body' => "Hallo {{name}},\n\nder neue Plan ist online, und es gibt offene Schichten, auf die du dich bewerben kannst.\n\nSchau in {{app_name}} unter „Plan“ nach und such dir aus, was zu dir passt. Wir freuen uns über jede Bewerbung!",
            'placeholders' => ['name', 'app_name'],
            'cta' => ['label' => 'Schichten ansehen und bewerben', 'path' => '/shifts.php', 'variant' => 'stamp'],
        ],
        'schedule_changed' => [
            'label' => 'Sammel-Mail bei Veröffentlichung',
            'subject' => 'Neues in deinem Schichtplan',
            'body' => "Hallo {{name}},\n\nin deinem Schichtplan in {{app_name}} hat sich etwas getan. Die Details findest du unter „Mein Plan“, dort steht immer der aktuelle Stand.\n\nSchau kurz rein, damit du nichts verpasst.",
            'placeholders' => ['name', 'app_name'],
            'cta' => ['label' => 'Mein Plan öffnen', 'path' => '/my_week.php'],
        ],
    ];

    private int $smsQueued = 0;

    /** Wie viele SMS dieser Notifier bisher an das Gateway übergeben bzw. in die Warteschlange gelegt hat. */
    public function smsCount(): int
    {
        return $this->smsQueued;
    }

    /**
     * SMS unter denselben Bedingungen wie die E-Mail (heute nicht laut Abwesenheit verhindert),
     * zusätzlich: SMS im Gateway konfiguriert, Mobilnummer hinterlegt und "SMS erhalten" gesetzt.
     * $user muss eine volle users-Zeile sein (id, phone, notify_sms). Nie Passwörter per SMS.
     */
    private function sms(array $user, string $eventType, string $text): void
    {
        if (!SmsClient::enabled() || empty($user['notify_sms']) || empty($user['phone'])) {
            return;
        }
        if (isUserAbsentOn((int)$user['id'], date('Y-m-d'))) {
            return;
        }
        try {
            // Überschneiden sich mehrere Anlässe im Zeitfenster, geht der allgemeine Plan-Hinweis raus.
            SmsClient::enqueueCoalesced((int)$user['id'], (string)$user['phone'], $eventType, $text, smsChangeNotice(SmsClient::portalUrl()));
            $this->smsQueued++;
        } catch (Throwable) {
            // Ein SMS-Problem darf nie eine Planänderung oder das Veröffentlichen blockieren.
        }
    }

    public function __construct(array $smtpConfig)
    {
        $this->mailer = new Mailer($smtpConfig);
        $this->webhook = new Webhook();
    }

    public function newApplication(array $shift, array $applicant): void
    {
        [$subject, $body] = $this->renderTemplate('new_application', [
            'applicant_name' => $applicant['name'],
            'shift_title' => $shift['title'],
            'shift_date' => formatDateDe($shift['shift_date']),
            'shift_time' => "{$shift['start_time']}-{$shift['end_time']}",
        ]);

        foreach ($this->admins() as $admin) {
            if ($admin['notify_email']) {
                $this->sendMail('new_application', $admin['email'], $admin['name'], $subject, $body);
            }
        }

        $this->webhook->send('application.created', [
            'shift' => $this->shiftPayload($shift),
            'applicant' => ['id' => $applicant['id'], 'name' => $applicant['name'], 'email' => $applicant['email']],
        ]);
    }

    public function applicationDecided(array $shift, array $applicant, string $status): void
    {
        $label = statusLabelDe($status);
        [$subject, $body] = $this->renderTemplate('application_decided', [
            'name' => $applicant['name'],
            'shift_title' => $shift['title'],
            'shift_date' => formatDateDe($shift['shift_date']),
            'shift_time' => "{$shift['start_time']}-{$shift['end_time']}",
            'status_label' => $label,
            'rueckmeldung' => $status === 'approved'
                ? 'Gute Nachricht: Du bist für diese Schicht eingeplant. Wir freuen uns auf dich!'
                : 'Danke, dass du dich gemeldet hast! Für diese Schicht können wir dich diesmal leider nicht einplanen. Schau gern wieder in den Plan – es kommen laufend neue Schichten dazu.',
        ]);

        if ($applicant['notify_email'] && !isUserAbsentOn((int)$applicant['id'], date('Y-m-d'))) {
            $this->sendMail('application_decided', $applicant['email'], $applicant['name'], $subject, $body);
        }

        $this->sms($applicant, 'application_decided', smsChangeNotice(SmsClient::portalUrl(), 'application'));

        $this->webhook->send('application.decided', [
            'shift' => $this->shiftPayload($shift),
            'applicant' => ['id' => $applicant['id'], 'name' => $applicant['name'], 'email' => $applicant['email']],
            'status' => $status,
        ]);
    }

    /**
     * Rundruf "Neuer Plan verfügbar" an eine Person, die vom Veröffentlichen nicht persönlich
     * betroffen ist: Es gibt neue offene Schichten zum Bewerben. Wie die anderen Sammelnachrichten
     * ohne Schichtdetails; E-Mail nach notify_email, SMS nach notify_sms - beides nicht während einer
     * Abwesenheit. Gibt zurück, ob eine E-Mail verschickt wurde. $user muss eine volle users-Zeile sein.
     */
    public function newPlanAvailable(array $user): bool
    {
        $this->sms($user, 'plan_published', smsChangeNotice(SmsClient::portalUrl(), 'new_plan'));

        if (empty($user['notify_email']) || isUserAbsentOn((int)$user['id'], date('Y-m-d'))) {
            return false;
        }
        [$subject, $body] = $this->renderTemplate('new_plan', [
            'name' => $user['name'],
            'app_name' => setting('app_name', 'Schichtplaner'),
        ]);

        return $this->sendMail('new_plan', $user['email'], $user['name'], $subject, $body);
    }

    public function shiftPublished(array $shift): void
    {
        $this->webhook->send('shift.published', ['shift' => $this->shiftPayload($shift)]);
    }

    /**
     * Verschickt das vorläufige Passwort per E-Mail. Läuft unabhängig von notify_email,
     * da dieses Feld reine Komfort-Benachrichtigungen steuert - ohne dieses Passwort kann sich
     * der Mitarbeiter überhaupt nicht anmelden, es ist also kein optionaler Hinweis.
     * Gibt zurück, ob der Mailversand erfolgreich war, damit der Aufrufer dem Admin sagen kann,
     * ob er das Passwort trotzdem manuell weitergeben muss.
     */
    public function accountCreated(array $user, string $tempPassword): bool
    {
        $appName = setting('app_name', 'Schichtplaner');
        [$subject, $body] = $this->renderTemplate('account_created', [
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => $tempPassword,
            'app_name' => $appName,
        ]);

        return $this->sendMail('account_created', $user['email'], $user['name'], $subject, $body);
    }

    public function passwordWasReset(array $user, string $tempPassword): bool
    {
        $appName = setting('app_name', 'Schichtplaner');
        [$subject, $body] = $this->renderTemplate('password_reset', [
            'name' => $user['name'],
            'password' => $tempPassword,
            'app_name' => $appName,
        ]);

        return $this->sendMail('password_reset', $user['email'], $user['name'], $subject, $body);
    }

    public function assignmentRemoved(array $shift, array $employee): void
    {
        [$subject, $body] = $this->renderTemplate('assignment_removed', [
            'name' => $employee['name'],
            'shift_title' => $shift['title'],
            'shift_date' => formatDateDe($shift['shift_date']),
            'shift_time' => "{$shift['start_time']}-{$shift['end_time']}",
        ]);

        if ($employee['notify_email'] && !isUserAbsentOn((int)$employee['id'], date('Y-m-d'))) {
            $this->sendMail('assignment_removed', $employee['email'], $employee['name'], $subject, $body);
        }

        $this->sms($employee, 'assignment_removed', smsChangeNotice(SmsClient::portalUrl()));

        $this->webhook->send('application.removed', [
            'shift' => $this->shiftPayload($shift),
            'applicant' => ['id' => $employee['id'], 'name' => $employee['name'], 'email' => $employee['email']],
        ]);
    }

    /**
     * Die gebündelte, unspezifische Sammel-Mail beim Publish: bewusst ohne Schichtdetails
     * (Titel, Zeit, Ort), nur ein Verweis auf den Plan - genau ein Mail pro betroffener Person
     * statt einer einzelnen Mail pro einzelner Änderung.
     */
    public function scheduleChanged(array $user): bool
    {
        // Wie die Sammel-Mail bewusst ohne Schichtdetails. Unabhängig vom E-Mail-Schalter -
        // wer nur SMS möchte, bekommt trotzdem Bescheid.
        $this->sms($user, 'schedule_changed', smsChangeNotice(SmsClient::portalUrl()));

        if (empty($user['notify_email']) || isUserAbsentOn((int)$user['id'], date('Y-m-d'))) {
            return false;
        }
        $appName = setting('app_name', 'Schichtplaner');
        [$subject, $body] = $this->renderTemplate('schedule_changed', [
            'name' => $user['name'],
            'app_name' => $appName,
        ]);

        return $this->sendMail('schedule_changed', $user['email'], $user['name'], $subject, $body);
    }

    /**
     * Liest Betreff/Text für $key aus email_templates (Admin-Vorlage), fällt bei leerer/
     * fehlender Zeile auf TEMPLATES[$key] zurück, und ersetzt danach jeden {{platzhalter}}
     * in $vars - sowohl in der Admin-Vorlage als auch im Standard, damit ein Admin, der nur
     * den Betreff überschreibt, im Text trotzdem funktionierende Platzhalter behält.
     */
    /**
     * Verschickt eine Vorlagen-Mail als HTML im Bon-Layout (MailLayout) mit Text-Alternative. Der
     * Button kommt aus TEMPLATES[$key]['cta'], enthält der Text schon eine eigene Adresse, wird diese
     * zum Button.
     */
    private function sendMail(string $templateKey, string $toEmail, string $toName, string $subject, string $body): bool
    {
        $cta = self::TEMPLATES[$templateKey]['cta'] ?? null;
        $portalUrl = SmsClient::portalUrl();
        $appName = (string)setting('app_name', 'Schichtplaner');

        return $this->mailer->send(
            $toEmail,
            $toName,
            $subject,
            MailLayout::text($body, $cta, $portalUrl),
            MailLayout::html($subject, $body, $cta, $appName, $portalUrl)
        );
    }

    private function renderTemplate(string $key, array $vars): array
    {
        $default = self::TEMPLATES[$key];
        $subject = $default['subject'];
        $body = $default['body'];

        $stmt = db()->prepare('SELECT subject, body FROM email_templates WHERE template_key = :k');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        if ($row) {
            if (trim((string)$row['subject']) !== '') {
                $subject = $row['subject'];
            }
            if (trim((string)$row['body']) !== '') {
                $body = $row['body'];
            }
        }

        foreach ($vars as $name => $value) {
            $subject = str_replace('{{' . $name . '}}', (string)$value, $subject);
            $body = str_replace('{{' . $name . '}}', (string)$value, $body);
        }

        return [$subject, $body];
    }

    private function shiftPayload(array $shift): array
    {
        return [
            'id' => $shift['id'],
            'title' => $shift['title'],
            'date' => $shift['shift_date'],
            'start' => $shift['start_time'],
            'end' => $shift['end_time'],
            'location' => $shift['location'],
        ];
    }

    private function admins(): array
    {
        return db()->query("SELECT * FROM users WHERE role = 'admin' AND active = 1")->fetchAll();
    }
}
