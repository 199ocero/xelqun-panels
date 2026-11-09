<?php

namespace Xelqun\Panels\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the old address, so someone who didn't ask for the change hears of it.
 */
final class EmailChangeRequested extends Notification
{
    public function __construct(private readonly string $newEmail) {}

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
            ->subject(__('xelqun-panels::auth.email_change.notice_subject'))
            ->line(__('xelqun-panels::auth.email_change.notice_line', ['email' => $this->newEmail]))
            ->line(__('xelqun-panels::auth.email_change.notice_footer'));
    }
}
