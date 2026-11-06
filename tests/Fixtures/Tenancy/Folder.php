<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Xelqun\Panels\Tests\Fixtures\Team;

/**
 * Shared between teams, so it belongs to them through a pivot.
 */
class Folder extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }
}
