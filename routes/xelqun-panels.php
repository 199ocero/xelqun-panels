<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Panels\Http\Controllers\Auth\EmailVerificationController;
use Xelqun\Panels\Http\Controllers\Auth\LoginController;
use Xelqun\Panels\Http\Controllers\Auth\LogoutController;
use Xelqun\Panels\Http\Controllers\Auth\PasswordResetController;
use Xelqun\Panels\Http\Controllers\Auth\ProfileController;
use Xelqun\Panels\Http\Controllers\Auth\RegisterController;
use Xelqun\Panels\Http\Controllers\GlobalSearchController;
use Xelqun\Panels\Http\Controllers\ResourceRecordController;
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

            if ($panel->getPasswordResetRequestForm() !== null) {
                Route::get('forgot-password', [PasswordResetController::class, 'showRequest'])->name('auth.password.request');
                Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('auth.password.email');
                Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('auth.password.reset');
                Route::post('reset-password/{token}', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('auth.password.update');
            }

            if ($panel->getRegistrationForm() !== null) {
                Route::get('register', [RegisterController::class, 'show'])->name('auth.register');
                Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('auth.register.store');
            }

            if ($panel->requiresEmailVerification()) {
                Route::middleware(Authenticate::class.':unverified')->group(function (): void {
                    Route::get('email/verify', [EmailVerificationController::class, 'prompt'])->name('auth.verification.notice');
                    Route::post('email/send', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('auth.verification.send');
                    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('auth.verification.verify');
                });
            }

            Route::post('logout', LogoutController::class)->name('auth.logout');

            Route::middleware(Authenticate::class)->group(function () use ($panel): void {
                // The underscore keeps it clear of a page or resource called search.
                Route::post('_search', GlobalSearchController::class)->middleware('throttle:60,1')->name('search');

                if ($panel->getProfileForm() !== null) {
                    Route::get('profile', [ProfileController::class, 'show'])->name('auth.profile');
                    Route::put('profile', [ProfileController::class, 'update'])->name('auth.profile.update');
                }

                foreach ($panel->getPages() as $page) {
                    Route::get($page::getSlug(), $page)->name($page::getRouteName());
                }

                foreach ($panel->getResources() as $resource) {
                    $pages = $resource::getPages();
                    $slug = $resource::getSlug();

                    Route::prefix($slug)->name("resources.{$slug}.")->group(function () use ($pages, $resource): void {
                        Route::get('', $pages['index'])->name('index')->defaults('resource', $resource);
                        Route::get('create', $pages['create'])->name('create')->defaults('resource', $resource);
                        Route::post('', [ResourceRecordController::class, 'store'])->name('store')->defaults('resource', $resource);
                        Route::get('{record}', $pages['view'])->name('view')->defaults('resource', $resource);
                        Route::get('{record}/edit', $pages['edit'])->name('edit')->defaults('resource', $resource);
                        Route::put('{record}', [ResourceRecordController::class, 'update'])->name('update')->defaults('resource', $resource);
                    });
                }
            });
        });
}
