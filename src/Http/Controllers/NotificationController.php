<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Xelqun\Forms\Support\SafeUrl;
use Xelqun\Panels\Notifications\StoredNotification;

/**
 * The bell. Every query starts from the signed-in user, so one user's id in the
 * URL can't touch another's.
 */
final class NotificationController
{
    private const int LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    public function read(Request $request): JsonResponse
    {
        $this->notifications($request)->whereKey($request->route()->parameter('notification'))->firstOrFail()->markAsRead();

        return $this->list($request);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notifications($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->list($request);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->notifications($request)->delete();

        return $this->list($request);
    }

    private function list(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->notifications($request)->whereNull('read_at')->count(),
            'notifications' => $this->notifications($request)->latest()->limit(self::LIMIT)->get()->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? null,
                'body' => $notification->data['body'] ?? null,
                'status' => $notification->data['status'] ?? null,
                'url' => SafeUrl::filter($notification->data['url'] ?? null),
                'read' => $notification->read(),
                'time' => $notification->getAttribute('created_at')?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * @return MorphMany<DatabaseNotification, Model>
     */
    private function notifications(Request $request): MorphMany
    {
        abort_unless(method_exists($request->user(), 'notifications'), 404);

        return $request->user()->notifications()->where('type', StoredNotification::class);
    }
}
