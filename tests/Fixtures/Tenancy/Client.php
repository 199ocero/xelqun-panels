<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared across tenants, with projects that belong to one.
 */
class Client extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
