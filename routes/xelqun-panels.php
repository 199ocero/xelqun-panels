<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Panels\Http\Controllers\Auth\EmailVerificationController;
use Xelqun\Panels\Http\Controllers\Auth\LoginController;
use Xelqun\Panels\Http\Controllers\Auth\LogoutController;
use Xelqun\Panels\Http\Controllers\Auth\MultiFactorChallengeController;
use Xelqun\Panels\Http\Controllers\Auth\PasswordResetController;
use Xelqun\Panels\Http\Controllers\Auth\ProfileController;
use Xelqun\Panels\Http\Controllers\Auth\RegisterController;
use Xelqun\Panels\Http\Controllers\GlobalSearchController;
use Xelqun\Panels\Http\Controllers\NotificationController;
use Xelqun\Panels\Http\Controllers\RedirectToTenantController;
use Xelqun\Panels\Http\Controllers\ResourceRecordController;
use Xelqun\Panels\Http\Middleware\Authenticate;
use Xelqun\Panels\Http\Middleware\EnterPanel;
use Xelqun\Panels\Http\Middleware\IdentifyTenant;
use Xelqun\Panels\Http\Middleware\RequireMultiFactorSetup;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Pages\ManageSingleRecord;

foreach (app(Panels::class)->all() as $panel) {
    Route::prefix($panel->getPath())
        ->middleware(['web', EnterPanel::class.':'.$panel->getId()])
        ->name($panel->routeName(''))
        ->group(function () use ($panel): void {
            if ($panel->getLoginForm() !== null) {
                Route::get('login', [LoginController::class, 'show'])->name('auth.login');
                Route::post('login', [LoginController::class, 'store'])->name('auth.login.store');
            }

            if ($panel->hasMultiFactorAuthentication()) {
                Route::get('two-factor', [MultiFactorChallengeController::class, 'show'])->name('auth.challenge');
                Route::post('two-factor', [MultiFactorChallengeController::class, 'store'])->name('auth.challenge.store');
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
                if ($panel->hasTenancy()) {
                    Route::get('', RedirectToTenantController::class)->name('tenant.redirect');
                }

                Route::prefix($panel->hasTenancy() ? '{tenant}' : '')
                    ->middleware([...($panel->hasTenancy() ? [IdentifyTenant::class] : []), RequireMultiFactorSetup::class])
                    ->group(function () use ($panel): void {
                        if ($panel->hasGlobalSearch()) {
                            // The underscore keeps it clear of a page or resource called search.
                            Route::post('_search', GlobalSearchController::class)->middleware('throttle:60,1')->name('search');
                        }

                        if ($panel->hasDatabaseNotifications()) {
                            Route::prefix('_notifications')->middleware('throttle:120,1')->name('notifications.')->group(function (): void {
                                Route::post('', [NotificationController::class, 'index'])->name('index');
                                Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
                                Route::post('clear', [NotificationController::class, 'clear'])->name('clear');
                                Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
                            });
                        }

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

                                if (is_subclass_of($pages['index'], ManageSingleRecord::class) || $pages['index'] === ManageSingleRecord::class) {
                                    Route::put('', [ResourceRecordController::class, 'update'])->name('update')->defaults('resource', $resource);
                                }

                                if (isset($pages['create'])) {
                                    Route::get('create', $pages['create'])->name('create')->defaults('resource', $resource);
                                    Route::post('', [ResourceRecordController::class, 'store'])->name('store')->defaults('resource', $resource);
                                }

                                if (isset($pages['view'])) {
                                    Route::get('{record}', $pages['view'])->name('view')->defaults('resource', $resource);
                                }

                                if (isset($pages['edit'])) {
                                    Route::get('{record}/edit', $pages['edit'])->name('edit')->defaults('resource', $resource);
                                    Route::put('{record}', [ResourceRecordController::class, 'update'])->name('update')->defaults('resource', $resource);
                                }
                            });
                        }
                    });
            });
        });
}
