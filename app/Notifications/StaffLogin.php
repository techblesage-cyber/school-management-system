<?php

namespace App\Notifications;

use App\Models\School;
use App\Support\SchoolAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to staff when they are added to a school (or given a new
 * password): where to sign in, their email and a first password.
 */
class StaffLogin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public School $school,
        public ?string $password,
        public ?string $role = null,
        public bool $isNewPassword = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->isNewPassword ? "New password for {$this->school->name}" : "Your login for {$this->school->name}")
            ->greeting('Hello '.$notifiable->name.',');

        $mail = $this->isNewPassword
            ? $mail->line("You have a new password for the {$this->school->name} school portal.")
            : $mail->line("You have been added to the {$this->school->name} school portal".($this->role ? " as {$this->role}" : '').'.');

        if ($this->password) {
            $mail->line('**Email:** '.$notifiable->email)
                ->line('**First password:** '.$this->password)
                ->line('The first time you sign in, you will choose your own password.');
        } else {
            $mail->line('Sign in with your usual email and password.');
        }

        return $mail
            ->action('Sign in', SchoolAddress::staffLogin($this->school))
            ->line('If you were not expecting this, please tell the school office.');
    }
}
