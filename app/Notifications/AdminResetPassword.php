<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Password-reset mail for the `admin` guard. Identical to Laravel's, except
 * the link points at the admin reset route.
 */
class AdminResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
