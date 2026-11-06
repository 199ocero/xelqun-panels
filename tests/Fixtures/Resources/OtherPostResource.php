<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

/**
 * The same model and the same fields, so only the scope tells them apart.
 */
class OtherPostResource extends PostResource
{
    protected static ?string $slug = 'other-posts';
}
