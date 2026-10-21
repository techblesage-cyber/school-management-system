<?php

namespace App\Notifications;

use App\Models\School;
use App\Support\PhoneNumber;
use App\Support\SchoolAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to a parent with their login to one school's parents' portal.
 */
class ParentLogin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public School $school,
        public string $phone,
        public string $password,
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
        return (new MailMessage)
            ->subject("Your parents' portal login for {$this->school->name}")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("{$this->school->name} has given you a login to its parents' portal. There you can follow your child's report cards, marks, attendance, fees and the school's circulars.")
            ->line('**Phone number:** '.PhoneNumber::display($this->phone))
            ->line('**First password:** '.$this->password)
            ->line('The first time you sign in, you will choose your own password.')
            ->action('Open the parents\' portal', SchoolAddress::parentPortal($this->school))
            ->line('This login is for '.$this->school->name.' only. If your children are at another school too, that school gives you a separate login.');
    }
}
