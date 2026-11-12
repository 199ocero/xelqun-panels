<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Xelqun\Panels\Auth\Concerns\InteractsWithAppAuthentication;
use Xelqun\Panels\Auth\Concerns\InteractsWithPanelAppearance;
use Xelqun\Panels\Contracts\HasAppAuthentication;
use Xelqun\Panels\Contracts\HasEmailAuthentication;
use Xelqun\Panels\Contracts\HasPanelAppearance;
use Xelqun\Panels\Contracts\HasTenants;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panel;

class User extends Authenticatable implements HasAppAuthentication, HasEmailAuthentication, HasPanelAppearance, HasTenants, MustVerifyEmail, PanelUser
{
    use InteractsWithAppAuthentication;
    use InteractsWithPanelAppearance;
    use Notifiable;

    public static bool $allowed = true;

    /** Lets a test decide by the user's own columns. */
    public static ?Closure $allowedWhen = null;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    public function canAccessPanel(Panel $panel): bool
    {
        return self::$allowedWhen === null ? self::$allowed : (self::$allowedWhen)($this);
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->teams()->orderBy('name')->get()->toBase();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Team && $this->teams()->whereKey($tenant->getKey())->exists();
    }
}
