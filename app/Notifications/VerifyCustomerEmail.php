<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyCustomerEmail extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification code')
            ->greeting('Almost there')
            ->line('Enter this code to finish setting up your account:')
            ->line("**{$this->code}**")
            ->line('The code is good for one hour. If you did not create an account, you can ignore this email.');
    }
}
