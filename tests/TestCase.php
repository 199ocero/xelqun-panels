<?php

namespace Xelqun\Panels\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Xelqun\Forms\FormsServiceProvider;
use Xelqun\Panels\PanelsServiceProvider;
use Xelqun\Panels\Tests\Fixtures\AdminPanelProvider;
use Xelqun\Panels\Tests\Fixtures\HandleInertia;
use Xelqun\Panels\Tests\Fixtures\StaffPanelProvider;
use Xelqun\Panels\Tests\Fixtures\User;
use Xelqun\Tables\TablesServiceProvider;
use Xelqun\Tables\Testing\InteractsWithTables;

abstract class TestCase extends Orchestra
{
    use InteractsWithTables;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            FormsServiceProvider::class,
            TablesServiceProvider::class,
            PanelsServiceProvider::class,
            AdminPanelProvider::class,
            StaffPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['view']->addLocation(__DIR__.'/Fixtures/views');
        $app->make(Kernel::class)->appendMiddlewareToGroup('web', HandleInertia::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        User::$allowed = true;
    }

    /**
     * Someone who may enter, signed in on both guards the test panels use.
     */
    protected function signIn(array $attributes = []): User
    {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret'), ...$attributes]);

        $this->actingAs($user, 'web')->actingAs($user, 'admin');

        return $user;
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }
}
