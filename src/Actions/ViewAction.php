<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Actions\Action;

/**
 * Goes to the view page.
 *
 * @api
 */
class ViewAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('view')
            ->label(__('xelqun-panels::resources.view_action'))
            ->icon('eye')
            ->iconButton()
            ->authorize('view')
            ->url(fn (Model $record): string => $resource::getUrl('view', ['record' => $record]));
    }
}
