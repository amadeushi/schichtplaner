<?php
declare(strict_types=1);

/**
 * Buendelt E-Mail- und Webhook-Benachrichtigungen fuer die fachlichen
 * Ereignisse der Anwendung. Ein Ereignis kann ueber beide, einen, oder
 * keinen Kanal ausgeliefert werden - steuerbar in admin/settings.php
 * bzw. je Nutzer ueber users.notify_email.
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
        $body = "{$applicant['name']} hat sich fuer die Schicht \"{$shift['title']}\" "
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
        $body = "Deine Bewerbung fuer die Schicht \"{$shift['title']}\" "
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

    public function assignmentRemoved(array $shift, array $employee): void
    {
        $subject = "Zuweisung entfernt: {$shift['title']} am " . formatDateDe($shift['shift_date']);
        $body = "Deine Zuweisung fuer die Schicht \"{$shift['title']}\" "
            . "am " . formatDateDe($shift['shift_date']) . " ({$shift['start_time']}-{$shift['end_time']}) wurde vom Admin entfernt.";

        if ($employee['notify_email']) {
            $this->mailer->send($employee['email'], $employee['name'], $subject, $body);
        }

        $this->webhook->send('application.removed', [
            'shift' => $this->shiftPayload($shift),
            'applicant' => ['id' => $employee['id'], 'name' => $employee['name'], 'email' => $employee['email']],
        ]);
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
