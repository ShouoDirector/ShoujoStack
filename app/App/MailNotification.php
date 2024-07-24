<?php

namespace BookStack\App;

use BookStack\Translation\LocaleDefinition;
use BookStack\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class MailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the mail representation of the notification.
     */
    abstract public function toMail(User $notifiable): MailMessage;

    /**
     * Get the notification's channels.
     *
     * @param mixed $notifiable
     *
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Create a new mail message.
     */
    protected function newMailMessage(?LocaleDefinition $locale = null): MailMessage
    {
        $data = ['locale' => $locale ?? user()->getLocale()];

        return (new MailMessage())->view([
            'html' => 'vendor.notifications.email',
            'text' => 'vendor.notifications.email-plain',
        ], $data);
    }
}
