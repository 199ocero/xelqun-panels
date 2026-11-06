<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Panels\Http\Controllers\Auth\LoginController;
use Xelqun\Panels\Http\Controllers\Auth\LogoutController;
use Xelqun\Panels\Http\Middleware\Authenticate;
use Xelqun\Panels\Http\Middleware\EnterPanel;
use Xelqun\Panels\Panels;

foreach (app(Panels::class)->all() as $panel) {
    Route::prefix($panel->getPath())
        ->middleware(['web', EnterPanel::class.':'.$panel->getId()])
        ->name($panel->routeName(''))
        ->group(function () use ($panel): void {
            if ($panel->getLoginForm() !== null) {
                Route::get('login', [LoginController::class, 'show'])->name('auth.login');
                Route::post('login', [LoginController::class, 'store'])->name('auth.login.store');
            }

            Route::post('logout', LogoutController::class)->name('auth.logout');

            Route::middleware(Authenticate::class)->group(function () use ($panel): void {
                foreach ($panel->getPages() as $page) {
                    Route::get($page::getSlug(), $page)->name($page::getRouteName());
                }
            });
        });
}
