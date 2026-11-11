<?php

namespace Xelqun\Panels;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
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
    private const string PROFILE_TOKEN = 'xelqun-panels.profile-token';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xelqun-panels.php', 'xelqun-panels');

        $this->app->singletonIf(Panels::class);

        // The endpoints of forms and tables know nothing about panels, so a token takes its panel with it.
        $this->callAfterResolving(FormTokenEncrypter::class, function (FormTokenEncrypter $tokens): void {
            $panels = $this->app->make(Panels::class);

            $tokens->scopeUsing(
                fn (): ?string => $panels->current() === null ? null : (string) json_encode([
                    $panels->current()->getId(),
                    $panels->tenant()?->getKey(),
                    $panels->parentKey(),
                    // A token from the profile page, or rebuilt from one, still works while two-factor setup is due.
                    request()->routeIs($panels->current()->routeName('auth.profile*')) || request()->attributes->get(self::PROFILE_TOKEN) === true,
                ]),
                function (?string $scope) use ($panels): void {
                    if ($scope === null) {
                        return;
                    }

                    $parts = json_decode($scope, true);

                    if (! is_array($parts) || ! array_is_list($parts) || count($parts) !== 4) {
                        throw InvalidFormToken::make('the token is from an older version, reload the page', 419);
                    }

                    [$id, $tenantKey, $parentKey, $isProfileToken] = $parts;
                    $panel = $panels->get((string) $id) ?? throw InvalidFormToken::make('the token belongs to an unknown panel');
                    $panels->enter($panel);
                    $user = auth()->user();

                    // A guest is fine here, because the token's user is compared next.
                    if ($user !== null && ! ($panel->allows($user) && $panel->hasVerifiedEmail($user) && $panel->hasPassedChallenge($user))) {
                        throw InvalidFormToken::make('this user can no longer enter the panel');
                    }

                    // No tenant is fine, for what sits outside one, like starting a tenant. Resources refuse without one.
                    if ($panel->hasTenancy() && $tenantKey !== null) {
                        $tenant = (new ($panel->getTenantModel()))->newQuery()->find($tenantKey);

                        if ($tenant === null || ($user !== null && ! $panel->canAccessTenant($user, $tenant))) {
                            throw InvalidFormToken::make('this user can no longer reach the tenant');
                        }

                        $panels->enterTenant($tenant);
                    }

                    if ($parentKey !== null) {
                        $panels->enterParent((string) $parentKey);
                    }

                    request()->attributes->set(self::PROFILE_TOKEN, $isProfileToken === true);

                    if ($user !== null && $isProfileToken !== true && $panel->mustSetUpMultiFactorAuthentication($user)) {
                        throw InvalidFormToken::make('two-factor setup is due first');
                    }

                    $this->runPanelMiddleware($panel, $user !== null, $panels->tenant() !== null);
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

            // The date is a placeholder that publishing replaces with the time it ran.
            $this->publishesMigrations([
                __DIR__.'/../database/migrations/add_app_authentication_to_users_table.php.stub' => database_path('migrations/2026_01_01_000000_add_app_authentication_to_users_table.php'),
            ], 'xelqun-panels-mfa');

            $this->commands([InstallCommand::class, MakePageCommand::class, MakePanelCommand::class, MakeRelationManagerCommand::class, MakeResourceCommand::class, MakeWidgetCommand::class]);
        }
    }

    /**
     * The endpoints of forms and tables sit outside the panel's routes, so the
     * middleware an app put on the panel runs here, in the order the panel's own
     * routes run it. It sees the endpoint's route, which has no parameters.
     */
    private function runPanelMiddleware(Panel $panel, bool $isSignedIn, bool $isInTenant): void
    {
        $middleware = [
            ...$panel->getMiddleware(),
            ...($isSignedIn ? $panel->getAuthMiddleware() : []),
            ...($isInTenant ? $panel->getTenantMiddleware() : []),
        ];

        if ($middleware === []) {
            return;
        }

        $passed = new Response;

        $response = (new Pipeline($this->app))
            ->send(request())
            ->through($this->app->make(Router::class)->resolveMiddleware($middleware))
            ->then(fn (): Response => $passed);

        if ($response !== $passed) {
            throw InvalidFormToken::make('the panel\'s middleware turned the request away');
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
