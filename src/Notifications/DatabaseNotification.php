<?php

namespace Xelqun\Panels\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Xelqun\Forms\Support\SafeUrl;
use Xelqun\Notifications\Notification;

/**
 * A notification that waits in someone's bell, as well as one you can send() to
 * the current page. The panel needs databaseNotifications(), and the recipient
 * needs Laravel's Notifiable.
 *
 * @api
 */
class DatabaseNotification extends Notification
{
    protected ?string $url = null;

    /**
     * Where clicking it leads.
     */
    public function url(?string $url): static
    {
        $this->url = SafeUrl::filter($url);

        return $this;
    }

    public function sendToDatabase(Model $recipient): static
    {
        NotificationFacade::send($recipient, new StoredNotification([...$this->toArray(), 'url' => $this->url]));

        return $this;
    }

    /**
     * The same, and it tells an open bell right away over Laravel broadcasting,
     * rather than at its next check. The panel needs broadcastNotifications().
     */
    public function broadcast(Model $recipient): static
    {
        NotificationFacade::send($recipient, new StoredNotification([...$this->toArray(), 'url' => $this->url], shouldBroadcast: true));

        return $this;
    }
}
