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
                fn (): ?string => $panels->current()?->getId(),
                function (?string $id) use ($panels): void {
                    if ($id === null) {
                        return;
                    }

                    $panel = $panels->get($id) ?? throw InvalidFormToken::make('the token belongs to an unknown panel');

                    $panels->enter($panel);

                    // A guest is fine here, because the token's user is compared next.
                    if (auth()->check() && ! ($panel->allows(auth()->user()) && $panel->hasVerifiedEmail(auth()->user()))) {
                        throw InvalidFormToken::make('this user can no longer enter the panel');
                    }
                },
            );
        });
    }

    public function boot(): void
    {
        $this->verifyEmailsInThePanel();

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

            $this->commands([InstallCommand::class, MakePageCommand::class, MakePanelCommand::class]);
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
