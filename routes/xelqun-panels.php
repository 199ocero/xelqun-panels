<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Panels\Http\Middleware\EnterPanel;
use Xelqun\Panels\Panels;

foreach (app(Panels::class)->all() as $panel) {
    Route::prefix($panel->getPath())
        ->middleware(['web', EnterPanel::class.':'.$panel->getId()])
        ->name($panel->routeName(''))
        ->group(function () use ($panel): void {
            foreach ($panel->getPages() as $page) {
                Route::get($page::getSlug(), $page)->name($page::getRouteName());
            }
        });
}
