<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Xelqun\Panels\Notifications\DatabaseNotification;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Resource;

/**
 * A queued import: the whole file, as the user who started it, in their panel,
 * tenant and parent record. Only keys travel, so a user who lost access since
 * or a tenant that's gone stops it.
 *
 * shortcut: one job reads the whole file; split it into chunk jobs if imports outgrow the worker's timeout.
 *
 * @internal
 */
class ImportRecords implements ShouldQueue
{
    use Queueable;

    /** A retry would import the rows before the crash a second time. */
    public int $tries = 1;

    /**
     * The classes are plain strings, because a queued payload can say anything; handle() checks them.
     */
    public function __construct(
        public string $action,
        public string $resource,
        public string $path,
        public string $panel,
        public int|string|null $userKey,
        public int|string|null $tenantKey,
        public ?string $parentKey,
    ) {}

    public function handle(Panels $panels): void
    {
        try {
            // A payload is only as safe as the queue it sat in.
            if (! is_a($this->action, ImportAction::class, true) || ! is_subclass_of($this->resource, Resource::class)) {
                return;
            }

            $panel = $panels->get($this->panel);
            $user = $this->findUser($panel);
            $tenantModel = $panel?->getTenantModel();
            $tenant = $tenantModel === null || $this->tenantKey === null ? null : $tenantModel::query()->find($this->tenantKey);

            // Gone since, or no longer allowed in: the panel, the user, or the tenant. A bell needs a model.
            if ($panel === null || ! $user instanceof Model || ! $panel->allows($user) || ($panel->hasTenancy() && $tenant === null)) {
                return;
            }

            // No middleware runs in a job, so the tenant check the request had happens here.
            if ($tenant !== null && ! $panel->canAccessTenant($user, $tenant)) {
                DatabaseNotification::make()->title(__('xelqun-panels::resources.import_denied'))->danger()->sendToDatabase($user);

                return;
            }

            $panels->within($panel, $user, $tenant, $this->parentKey, function () use ($user): void {
                if (! $this->resource::can('create')) {
                    DatabaseNotification::make()->title(__('xelqun-panels::resources.import_denied'))->danger()->sendToDatabase($user);

                    return;
                }

                $counts = $this->action::importRowsUpTo($this->resource, Storage::disk(ImportAction::DISK)->path($this->path), null);
                $this->action::makeReport($counts)->sendToDatabase($user);
            });
        } finally {
            Storage::disk(ImportAction::DISK)->delete($this->path);
        }
    }

    /**
     * A crash or a timeout part way leaves some rows in, so the user hears about it.
     */
    public function failed(): void
    {
        Storage::disk(ImportAction::DISK)->delete($this->path);

        $user = $this->findUser(app(Panels::class)->get($this->panel));

        if ($user instanceof Model) {
            DatabaseNotification::make()->title(__('xelqun-panels::resources.import_failed'))->danger()->sendToDatabase($user);
        }
    }

    private function findUser(?Panel $panel): ?Authenticatable
    {
        if ($panel === null || $this->userKey === null) {
            return null;
        }

        return Auth::createUserProvider(config('auth.guards.'.$panel->getAuthGuard().'.provider'))?->retrieveById($this->userKey);
    }
}
