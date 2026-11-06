<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xelqun\Panels\Resources\Pages\CreateRecord;
use Xelqun\Panels\Resources\Pages\EditRecord;
use Xelqun\Panels\Resources\Resource;

/**
 * Hands a submit to the resource's page, so swapping a page changes it too. It's
 * a controller of its own because Laravel Wayfinder, in an app that uses it,
 * can't type a class that is both invokable and has a route on another method.
 */
final class ResourceRecordController
{
    public function store(Request $request): RedirectResponse
    {
        return app($this->pages($request)['create'])->store($request);
    }

    public function update(Request $request): RedirectResponse
    {
        return app($this->pages($request)['edit'])->update($request);
    }

    /**
     * From the route by name, because a controller's arguments arrive by position.
     *
     * @return array{index: string, create: class-string<CreateRecord>, view: string, edit: class-string<EditRecord>}
     */
    private function pages(Request $request): array
    {
        $resource = $request->route()->parameter('resource');

        abort_unless(is_string($resource) && is_subclass_of($resource, Resource::class), 404);

        return $resource::getPages();
    }
}
