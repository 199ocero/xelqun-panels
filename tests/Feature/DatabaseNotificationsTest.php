<?php

use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Notifications\DatabaseNotification;
use Xelqun\Panels\Notifications\StoredNotification;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\User;

class SomeOtherNotification extends LaravelNotification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['secret' => 'not for the bell'];
    }
}

function notify(User $user, string $title = 'Order paid', ?string $url = '/admin/posts'): void
{
    DatabaseNotification::make()->title($title)->body('Thanks')->success()->url($url)->sendToDatabase($user);
}

beforeEach(function () {
    $this->user = $this->signIn();
});

it('lists what was sent, newest first, with the unread count', function () {
    notify($this->user);

    $this->postJson('/admin/_notifications')->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notifications.0.title', 'Order paid')
        ->assertJsonPath('notifications.0.body', 'Thanks')
        ->assertJsonPath('notifications.0.status', 'success')
        ->assertJsonPath('notifications.0.url', '/admin/posts')
        ->assertJsonPath('notifications.0.read', false);
});

it('leaves out the app\'s other database notifications', function () {
    $this->user->notify(new SomeOtherNotification);

    $this->postJson('/admin/_notifications')->assertJsonPath('unread', 0)->assertJsonCount(0, 'notifications');
});

it('drops an unsafe link', function () {
    notify($this->user, url: 'javascript:alert(1)');

    $this->postJson('/admin/_notifications')->assertJsonPath('notifications.0.url', null);
});

it('marks one as read, then all, then clears them', function () {
    notify($this->user, 'One');
    notify($this->user, 'Two');
    $id = $this->user->notifications()->first()->id;

    $this->postJson("/admin/_notifications/{$id}/read")->assertJsonPath('unread', 1);
    $this->postJson('/admin/_notifications/read-all')->assertJsonPath('unread', 0);
    $this->postJson('/admin/_notifications/clear')->assertJsonCount(0, 'notifications');

    expect($this->user->notifications()->count())->toBe(0);
});

it('can\'t read another user\'s notification', function () {
    $other = User::create(['name' => 'Bea', 'email' => 'bea@example.com', 'password' => 'x']);
    notify($other);
    $id = $other->notifications()->first()->id;

    $this->postJson("/admin/_notifications/{$id}/read")->assertNotFound();

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('keeps read-all and clear to this user', function () {
    $other = User::create(['name' => 'Bea', 'email' => 'bea@example.com', 'password' => 'x']);
    notify($other);

    $this->postJson('/admin/_notifications/read-all')->assertOk();
    $this->postJson('/admin/_notifications/clear')->assertOk();

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('refuses someone whose access was taken away', function () {
    User::$allowed = false;

    $this->postJson('/admin/_notifications')->assertForbidden();
});

it('tells the shell where the bell is, inside the panel only', function () {
    $this->get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('xelqunPanel.notifications.url', url('/admin/_notifications'))
        ->where('xelqunPanel.notifications.pollSeconds', 30));
});

it('broadcasts only that there is news, and stores the rest', function () {
    Notification::fake();

    DatabaseNotification::make()->title('Secret title')->broadcast($this->user);

    Notification::assertSentTo($this->user, StoredNotification::class, fn (StoredNotification $notification): bool => $notification->via($this->user) === ['database', 'broadcast']
        && $notification->toBroadcast($this->user) === []
        && $notification->toDatabase($this->user)['title'] === 'Secret title');
});

it('names the user\'s private channel only when asked to', function () {
    $panels = app(Panels::class);
    $panel = $panels->get('admin');

    $panels->enter($panel);
    expect($panel->toShell()['notifications']['broadcastChannel'])->toBeNull();

    $panel->broadcastNotifications();
    expect($panel->toShell()['notifications']['broadcastChannel'])->toBe(str_replace('\\', '.', User::class).'.'.$this->user->id);

    $panel->broadcastNotifications(false);
});

it('has no routes when a panel doesn\'t ask for it', function () {
    expect(route('xelqun-panels.admin.notifications.index'))->toEndWith('/admin/_notifications')
        ->and(Route::has('xelqun-panels.staff.notifications.index'))->toBeFalse();
});

it('reads a polling interval in seconds, or none', function () {
    expect(fn () => Panel::make()->databaseNotificationsPolling('often'))->toThrow(PanelDefinitionException::class);

    expect(Panel::make()->databaseNotificationsPolling('60s')->databaseNotificationsPolling(null))->toBeInstanceOf(Panel::class);
});
