<?php

namespace Xelqun\Panels\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code for the second step of signing in.
 */
final class EmailCode extends Notification
{
    public function __construct(private readonly string $code) {}

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
            ->subject(__('xelqun-panels::auth.mfa.email_subject'))
            ->line(__('xelqun-panels::auth.mfa.email_line', ['code' => $this->code]))
            ->line(__('xelqun-panels::auth.mfa.email_footer'));
    }
}
