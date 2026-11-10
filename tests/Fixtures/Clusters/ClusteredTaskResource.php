<?php

namespace Xelqun\Panels\Tests\Fixtures\Clusters;

use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;

/**
 * The same slug as TaskResource, inside a cluster, so their URLs and route names must not clash.
 */
class ClusteredTaskResource extends TaskResource
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'tasks';

    protected static int $navigationSort = 3;
}
