<?php

namespace Xelqun\Panels\Notifications;

use Illuminate\Notifications\Notification;

/**
 * What a DatabaseNotification is stored as. Its class is the row's type, which is
 * how the bell leaves out an app's other database notifications.
 *
 * @internal
 */
final class StoredNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
