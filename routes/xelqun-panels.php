<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Panels\Http\Controllers\AppearanceController;
use Xelqun\Panels\Http\Controllers\Auth\EmailChangeController;
use Xelqun\Panels\Http\Controllers\Auth\EmailVerificationController;
use Xelqun\Panels\Http\Controllers\Auth\LoginController;
use Xelqun\Panels\Http\Controllers\Auth\LogoutController;
use Xelqun\Panels\Http\Controllers\Auth\MultiFactorChallengeController;
use Xelqun\Panels\Http\Controllers\Auth\PasswordResetController;
use Xelqun\Panels\Http\Controllers\Auth\ProfileController;
use Xelqun\Panels\Http\Controllers\Auth\RegisterController;
use Xelqun\Panels\Http\Controllers\Auth\TenantController;
use Xelqun\Panels\Http\Controllers\ExportController;
use Xelqun\Panels\Http\Controllers\GlobalSearchController;
use Xelqun\Panels\Http\Controllers\NotificationController;
use Xelqun\Panels\Http\Controllers\RedirectToTenantController;
use Xelqun\Panels\Http\Controllers\ResourceRecordController;
use Xelqun\Panels\Http\Controllers\TenantSearchController;
use Xelqun\Panels\Http\Middleware\Authenticate;
use Xelqun\Panels\Http\Middleware\AuthenticatePanelSession;
use Xelqun\Panels\Http\Middleware\EnterPanel;
use Xelqun\Panels\Http\Middleware\IdentifyTenant;
use Xelqun\Panels\Http\Middleware\RequireMultiFactorSetup;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Pages\ManageSingleRecord;

foreach (app(Panels::class)->all() as $panel) {
    $panel->assertClusterSlugsAreFree();
    $panel->assertNestingIsOneLevel();

    Route::prefix($panel->getPath())
        ->domain($panel->getDomain())
        ->middleware(['web', EnterPanel::class.':'.$panel->getId(), ...$panel->getMiddleware()])
        ->name($panel->routeName(''))
        ->group(function () use ($panel): void {
            if ($panel->getLoginForm() !== null) {
                Route::get('login', [LoginController::class, 'show'])->name('auth.login');
                Route::post('login', [LoginController::class, 'store'])->middleware("throttle:20,1,xelqun-panels.{$panel->getId()}.auth.login")->name('auth.login.store');
            }

            // Even with the panel's own two-factor off, as Fortify's can still ask. Without a challenge under way, it sends people to the login.
            Route::get('two-factor', [MultiFactorChallengeController::class, 'show'])->name('auth.challenge');
            Route::post('two-factor', [MultiFactorChallengeController::class, 'store'])->name('auth.challenge.store');
            Route::post('two-factor/resend', [MultiFactorChallengeController::class, 'resend'])->middleware("throttle:3,1,xelqun-panels.{$panel->getId()}.auth.challenge.resend")->name('auth.challenge.resend');

            if ($panel->getPasswordResetRequestForm() !== null) {
                Route::get('forgot-password', [PasswordResetController::class, 'showRequest'])->name('auth.password.request');
                Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware("throttle:6,1,xelqun-panels.{$panel->getId()}.auth.password.email")->name('auth.password.email');
                Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('auth.password.reset');
                Route::post('reset-password/{token}', [PasswordResetController::class, 'reset'])->middleware("throttle:6,1,xelqun-panels.{$panel->getId()}.auth.password.update")->name('auth.password.update');
            }

            if ($panel->getRegistrationForm() !== null) {
                Route::get('register', [RegisterController::class, 'show'])->name('auth.register');
                Route::post('register', [RegisterController::class, 'store'])->middleware("throttle:6,1,xelqun-panels.{$panel->getId()}.auth.register.store")->name('auth.register.store');
            }

            if ($panel->requiresEmailVerification()) {
                Route::middleware(Authenticate::class.':unverified')->group(function () use ($panel): void {
                    Route::get('email/verify', [EmailVerificationController::class, 'prompt'])->name('auth.verification.notice');
                    Route::post('email/send', [EmailVerificationController::class, 'send'])->middleware("throttle:6,1,xelqun-panels.{$panel->getId()}.auth.verification.send")->name('auth.verification.send');
                    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', "throttle:6,1,xelqun-panels.{$panel->getId()}.auth.verification.verify"])->name('auth.verification.verify');
                });
            }

            Route::post('logout', LogoutController::class)->name('auth.logout');

            Route::middleware([Authenticate::class, AuthenticatePanelSession::class, ...$panel->getAuthMiddleware()])->group(function () use ($panel): void {
                if ($panel->hasTenancy()) {
                    Route::get('', RedirectToTenantController::class)->name('tenant.redirect');
                }

                // Whenever there's a profile, since the switch can turn on after routes are cached.
                if ($panel->getProfileForm() !== null) {
                    Route::get('email-change/verify', [EmailChangeController::class, 'verify'])->middleware(['signed', "throttle:6,1,xelqun-panels.{$panel->getId()}.auth.email-change"])->name('auth.email-change.verify');
                }

                if ($panel->getTenantRegistrationForm() !== null) {
                    Route::get('new-tenant', [TenantController::class, 'showRegistration'])->name('tenant.register');
                    Route::post('new-tenant', [TenantController::class, 'register'])->middleware("throttle:6,1,xelqun-panels.{$panel->getId()}.tenant.register.store")->name('tenant.register.store');
                }

                // Built as attributes, so a panel without a tenant domain keeps its own domain.
                Route::group(array_filter([
                    'prefix' => $panel->getTenantRoutePrefix(),
                    'domain' => $panel->hasTenancy() ? $panel->getTenantDomain() : null,
                    'middleware' => [...($panel->hasTenancy() ? [IdentifyTenant::class, ...$panel->getTenantMiddleware()] : []), RequireMultiFactorSetup::class],
                ]), function () use ($panel): void {
                    if ($panel->hasGlobalSearch()) {
                        // The underscore keeps it clear of a page or resource called search.
                        Route::post('_search', GlobalSearchController::class)->middleware("throttle:60,1,xelqun-panels.{$panel->getId()}.search")->name('search');
                    }

                    if ($panel->hasThemeSwitcher()) {
                        Route::post('_appearance', AppearanceController::class)->middleware("throttle:60,1,xelqun-panels.{$panel->getId()}.appearance")->name('appearance');
                    }

                    if ($panel->hasTenancy()) {
                        Route::post('_tenants', TenantSearchController::class)->middleware("throttle:60,1,xelqun-panels.{$panel->getId()}.tenant.search")->name('tenant.search');
                    }

                    if ($panel->getTenantProfileForm() !== null) {
                        Route::get('tenant-profile', [TenantController::class, 'showProfile'])->name('tenant.profile');
                        Route::put('tenant-profile', [TenantController::class, 'updateProfile'])->name('tenant.profile.update');
                    }

                    if ($panel->hasDatabaseNotifications()) {
                        Route::prefix('_notifications')->middleware("throttle:120,1,xelqun-panels.{$panel->getId()}.notifications")->name('notifications.')->group(function (): void {
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
                        Route::get($page::getPathInPanel(), $page)->name($page::getRouteName());
                    }

                    foreach ($panel->getResources() as $resource) {
                        $pages = $resource::getPages();
                        $slug = $resource::getSlug();

                        $parent = $resource::getParentResource();

                        Route::prefix($parent === null ? $resource::getPathInPanel() : $parent::getPathInPanel()."/{parentRecord}/{$slug}")->name($resource::getRouteBaseName().'.')->group(function () use ($pages, $resource, $panel): void {
                            Route::get('', $pages['index'])->name('index')->defaults('resource', $resource);
                            Route::get('_export', ExportController::class)->middleware("throttle:10,1,xelqun-panels.{$panel->getId()}.export")->name('export')->defaults('resource', $resource);

                            if (is_subclass_of($pages['index'], ManageSingleRecord::class) || $pages['index'] === ManageSingleRecord::class) {
                                Route::put('', [ResourceRecordController::class, 'update'])->name('update')->defaults('resource', $resource);
                            }

                            if (isset($pages['create'])) {
                                Route::get('create', $pages['create'])->name('create')->defaults('resource', $resource);
                                Route::post('', [ResourceRecordController::class, 'store'])->name('store')->defaults('resource', $resource);
                            }

                            // Ahead of view, so a page at 'sort' isn't read as a record called sort.
                            foreach (array_diff_key($pages, array_flip(['index', 'create', 'view', 'edit'])) as $key => $page) {
                                Route::get($page::getRoutePath($key), $page)->name($key)->defaults('resource', $resource);
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
