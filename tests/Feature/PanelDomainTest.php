<?php

use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Team;

it('answers only on its own domain', function (): void {
    $this->get('http://demo.example.test/login')->assertOk();
    $this->get('http://other.example.test/login')->assertNotFound();
});

it('sits at the root of its domain', function (): void {
    expect(app(Panels::class)->get('demo')->getUrl())->toBe('http://demo.example.test');
});

it('links to its domain from any other host', function (): void {
    expect(route('xelqun-panels.demo.auth.login'))->toBe('http://demo.example.test/login');
});

it('keeps the port of the request in its links', function (): void {
    $this->get('http://demo.example.test:8000/login')->assertOk();

    expect(app(Panels::class)->get('demo')->getUrl())->toBe('http://demo.example.test:8000');
});

it('links to a tenant on its domain from any other host', function (): void {
    $panel = Panel::make()->id('tenants')->domain('app.example.test')->path('app')->tenant(Team::class, slugAttribute: 'slug');

    expect($panel->getTenantUrl(new Team(['slug' => 'acme'])))->toBe('http://app.example.test/app/acme');
});

it('takes a domain written with its scheme', function (): void {
    expect(Panel::make()->domain('https://admin.example.test')->getDomain())->toBe('admin.example.test');
});

it('lets panels on different domains share a path', function (): void {
    $panels = new Panels;

    $panels->register(Panel::make()->id('one')->domain('one.example.test')->path(''));
    $panels->register(Panel::make()->id('two')->domain('two.example.test')->path(''));

    expect($panels->get('two')->getPath())->toBe('');
});

it('refuses two panels with the same path on one domain', function (): void {
    $panels = new Panels;

    $panels->register(Panel::make()->id('one')->domain('one.example.test')->path(''));
    $panels->register(Panel::make()->id('two')->domain('one.example.test')->path(''));
})->throws(PanelDefinitionException::class, 'have the same path');
