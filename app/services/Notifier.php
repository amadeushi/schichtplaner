<?php
declare(strict_types=1);

/**
 * Bündelt E-Mail- und Webhook-Benachrichtigungen für die fachlichen
 * Ereignisse der Anwendung. Ein Ereignis kann über beide, einen, oder
 * keinen Kanal ausgeliefert werden - steuerbar in admin/settings.php
 * bzw. je Nutzer über users.notify_email.
 */
final class Notifier
{
    private Mailer $mailer;
    private Webhook $webhook;

    public function __construct(array $smtpConfig)
    {
        $this->mailer = new Mailer($smtpConfig);
        $this->webhook = new Webhook();
    }

    public function newApplication(array $shift, array $applicant): void
    {
        $subject = "Neue Bewerbung: {$shift['title']} am " . formatDateDe($shift['shift_date']);
        $body = "{$applicant['name']} hat sich für die Schicht \"{$shift['title']}\" "
            . "am " . formatDateDe($shift['shift_date']) . " ({$shift['start_time']}-{$shift['end_time']}) beworben.\n\n"
            . "Bitte im Adminbereich freigeben oder ablehnen.";

        foreach ($this->admins() as $admin) {
            if ($admin['notify_email']) {
                $this->mailer->send($admin['email'], $admin['name'], $subject, $body);
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
        $subject = "Deine Bewerbung wurde $label: {$shift['title']} am " . formatDateDe($shift['shift_date']);
        $body = "Deine Bewerbung für die Schicht \"{$shift['title']}\" "
            . "am " . formatDateDe($shift['shift_date']) . " ({$shift['start_time']}-{$shift['end_time']}) wurde $label.";

        if ($applicant['notify_email']) {
            $this->mailer->send($applicant['email'], $applicant['name'], $subject, $body);
        }

        $this->webhook->send('application.decided', [
            'shift' => $this->shiftPayload($shift),
            'applicant' => ['id' => $applicant['id'], 'name' => $applicant['name'], 'email' => $applicant['email']],
            'status' => $status,
        ]);
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
        $subject = "Dein Zugang zu $appName";
        $body = "Hallo {$user['name']},\n\n"
            . "für dich wurde ein Konto in $appName angelegt.\n\n"
            . "E-Mail: {$user['email']}\n"
            . "Vorläufiges Passwort: $tempPassword\n\n"
            . "Bitte melde dich an und ändere dein Passwort beim ersten Login unter Profil.";

        return $this->mailer->send($user['email'], $user['name'], $subject, $body);
    }

    public function passwordWasReset(array $user, string $tempPassword): bool
    {
        $appName = setting('app_name', 'Schichtplaner');
        $subject = "Dein Passwort für $appName wurde zurückgesetzt";
        $body = "Hallo {$user['name']},\n\n"
            . "dein Passwort für $appName wurde von einem Admin zurückgesetzt.\n\n"
            . "Vorläufiges Passwort: $tempPassword\n\n"
            . "Bitte melde dich an und ändere dein Passwort beim ersten Login unter Profil.";

        return $this->mailer->send($user['email'], $user['name'], $subject, $body);
    }

    public function assignmentRemoved(array $shift, array $employee): void
    {
        $subject = "Zuweisung entfernt: {$shift['title']} am " . formatDateDe($shift['shift_date']);
        $body = "Deine Zuweisung für die Schicht \"{$shift['title']}\" "
            . "am " . formatDateDe($shift['shift_date']) . " ({$shift['start_time']}-{$shift['end_time']}) wurde vom Admin entfernt.";

        if ($employee['notify_email']) {
            $this->mailer->send($employee['email'], $employee['name'], $subject, $body);
        }

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
        if (empty($user['notify_email'])) {
            return false;
        }
        $appName = setting('app_name', 'Schichtplaner');
        $subject = "Änderungen an deinem Schichtplan";
        $body = "Hallo {$user['name']},\n\n"
            . "es gibt Änderungen an deinem Schichtplan in $appName. "
            . "Bitte melde dich an und sieh in \"Mein Plan\" nach, um die Details zu sehen.";

        return $this->mailer->send($user['email'], $user['name'], $subject, $body);
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
