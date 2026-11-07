<?php

namespace Xelqun\Panels\Actions;

use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\Resource;

/**
 * Downloads the list as CSV, with the columns from Resource::getExportColumns().
 *
 * @api
 */
class ExportAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('export')
            ->label(__('xelqun-panels::resources.export'))
            ->icon('download')
            ->color('gray')
            ->authorize('viewAny')
            ->url(fn (): string => $resource::getUrl('export', array_filter(['tab' => request()->query('tab')], is_string(...))), openInNewTab: true);
    }
}
