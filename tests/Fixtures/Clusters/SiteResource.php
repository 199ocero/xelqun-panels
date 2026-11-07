<?php

namespace Xelqun\Panels\Tests\Fixtures\Clusters;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Pages\ManageSingleRecord;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Note;

/**
 * One record and no list.
 */
class SiteResource extends Resource
{
    protected static string $model = Note::class;

    protected static ?string $slug = 'site';

    protected static ?string $modelLabel = 'site settings';

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => ManageSingleRecord::class];
    }
}
