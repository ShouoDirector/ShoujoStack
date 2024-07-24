<?php

namespace BookStack\Settings;

use BookStack\App\MailNotification;
use BookStack\Users\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class TestEmailNotification extends MailNotification
{
    public function toMail(User $notifiable): MailMessage
    {
        return $this->newMailMessage()
                ->subject(trans('settings.maint_send_test_email_mail_subject'))
                ->greeting(trans('settings.maint_send_test_email_mail_greeting'))
                ->line(trans('settings.maint_send_test_email_mail_text'));
    }
}
