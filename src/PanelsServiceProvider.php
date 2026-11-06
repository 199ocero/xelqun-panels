<?php

namespace Xelqun\Panels;

use Illuminate\Support\ServiceProvider;

class PanelsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'xelqun-panels');
    }
}
