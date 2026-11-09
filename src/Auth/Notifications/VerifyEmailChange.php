<?php

namespace Xelqun\Panels\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the new address. The change happens only when its link is opened.
 */
final class VerifyEmailChange extends Notification
{
    public function __construct(private readonly string $url) {}

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
            ->subject(__('xelqun-panels::auth.email_change.verify_subject'))
            ->line(__('xelqun-panels::auth.email_change.verify_line'))
            ->action(__('xelqun-panels::auth.email_change.verify_action'), $this->url)
            ->line(__('xelqun-panels::auth.email_change.verify_footer'));
    }
}
