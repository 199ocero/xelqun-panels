<?php

namespace Xelqun\Panels;

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
                    if ($id !== null) {
                        $panels->enter($panels->get($id) ?? throw InvalidFormToken::make('the token belongs to an unknown panel'));
                    }
                },
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'xelqun-panels');
        $this->loadRoutesFrom(__DIR__.'/../routes/xelqun-panels.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/xelqun-panels.php' => config_path('xelqun-panels.php'),
            ], 'xelqun-panels-config');

            $this->commands([InstallCommand::class, MakePageCommand::class, MakePanelCommand::class]);
        }
    }
}
