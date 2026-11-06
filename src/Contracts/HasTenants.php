<?php

namespace Xelqun\Panels\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Xelqun\Panels\Panel;

/**
 * Says which tenants a user belongs to. A user model that hasn't declared it has
 * none, so it can't enter a panel with tenants.
 *
 * @api
 */
interface HasTenants
{
    /**
     * @return Collection<int, Model>
     */
    public function getTenants(Panel $panel): Collection;

    public function canAccessTenant(Model $tenant): bool;
}
