<?php

namespace Xelqun\Panels;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Xelqun\Forms\Exceptions\InvalidFormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Panels\Commands\InstallCommand;
use Xelqun\Panels\Commands\MakePageCommand;
use Xelqun\Panels\Commands\MakePanelCommand;
use Xelqun\Panels\Commands\MakeRelationManagerCommand;
use Xelqun\Panels\Commands\MakeResourceCommand;
use Xelqun\Panels\Commands\MakeWidgetCommand;

class PanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xelqun-panels.php', 'xelqun-panels');

        $this->app->singletonIf(Panels::class);

        // The endpoints of forms and tables know nothing about panels, so a token takes its panel with it.
        $this->callAfterResolving(FormTokenEncrypter::class, function (FormTokenEncrypter $tokens): void {
            $panels = $this->app->make(Panels::class);

            $tokens->scopeUsing(
                fn (): ?string => $panels->current() === null ? null : rtrim(implode(':', [$panels->current()->getId(), $panels->tenant()?->getKey(), $panels->parentKey()]), ':'),
                function (?string $scope) use ($panels): void {
                    if ($scope === null) {
                        return;
                    }

                    [$id, $tenantKey, $parentKey] = array_pad(explode(':', $scope, 3), 3, '');
                    $panel = $panels->get($id) ?? throw InvalidFormToken::make('the token belongs to an unknown panel');
                    $panels->enter($panel);
                    $user = auth()->user();

                    // A guest is fine here, because the token's user is compared next.
                    if ($user !== null && ! ($panel->allows($user) && $panel->hasVerifiedEmail($user))) {
                        throw InvalidFormToken::make('this user can no longer enter the panel');
                    }

                    if ($panel->hasTenancy()) {
                        $tenant = $tenantKey === '' ? null : (new ($panel->getTenantModel()))->newQuery()->find($tenantKey);

                        if ($tenant === null || ($user !== null && ! $panel->canAccessTenant($user, $tenant))) {
                            throw InvalidFormToken::make('this user can no longer reach the tenant');
                        }

                        $panels->enterTenant($tenant);
                    }

                    if ($parentKey !== '') {
                        $panels->enterParent($parentKey);
                    }
                },
            );
        });
    }

    public function boot(): void
    {
        $this->verifyEmailsInThePanel();
        $this->bootPlugins();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'xelqun-panels');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xelqun-panels');
        $this->loadRoutesFrom(__DIR__.'/../routes/xelqun-panels.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/xelqun-panels.php' => config_path('xelqun-panels.php'),
            ], 'xelqun-panels-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/xelqun-panels'),
            ], 'xelqun-panels-lang');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/add_app_authentication_to_users_table.php.stub' => database_path('migrations/add_app_authentication_to_users_table.php'),
            ], 'xelqun-panels-mfa');

            $this->commands([InstallCommand::class, MakePageCommand::class, MakePanelCommand::class, MakeRelationManagerCommand::class, MakeResourceCommand::class, MakeWidgetCommand::class]);
        }
    }

    private function bootPlugins(): void
    {
        foreach ($this->app->make(Panels::class)->all() as $panel) {
            foreach ($panel->getPlugins() as $plugin) {
                $plugin->boot($panel);
            }
        }
    }

    /**
     * Laravel emails a link to `verification.verify` when someone registers, and
     * a panel's app may not have that route. Inside a panel that verifies, the
     * link comes here instead. Everywhere else it's what Laravel would build.
     *
     * shortcut: this reads the panel from the request, so a queued verification email gets Laravel's route. Send it on the panel's own queue job with the panel id if an app queues these.
     */
    private function verifyEmailsInThePanel(): void
    {
        VerifyEmail::createUrlUsing(function (Model&MustVerifyEmail $user): string {
            $panel = $this->app->make(Panels::class)->current();

            return URL::temporarySignedRoute(
                $panel?->requiresEmailVerification() ? $panel->routeName('auth.verification.verify') : 'verification.verify',
                Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );
        });
    }
}
