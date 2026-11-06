<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Xelqun\Panels\Tests\Fixtures\Team;

class Project extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
