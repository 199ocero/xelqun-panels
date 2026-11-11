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
     * @return Collection<int, covariant Model>
     */
    public function getTenants(Panel $panel): Collection;

    /**
     * Check the tenant's class as well as its key: with two tenant panels, one
     * asks about its own model, and a team and an organization can share an id.
     */
    public function canAccessTenant(Model $tenant): bool;
}
