<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Xelqun\Forms\Form;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;

/**
 * What every sign-in screen shares. Each flow is a form, a page and a handler,
 * with explicit POST routes because nothing here is a Livewire component.
 */
abstract class AuthController
{
    public function __construct(protected readonly Panels $panels) {}

    protected function panel(): Panel
    {
        return $this->panels->current();
    }

    /**
     * @return array{label: string, url: string|null, method: string}
     */
    protected function link(string $label, ?string $url, string $method = 'get'): array
    {
        return ['label' => $label, 'url' => $url, 'method' => $method];
    }

    /**
     * A page inside the panel that isn't a Page class. Everything a page needs is
     * here, so one of these can't miss a key the browser reads.
     *
     * @param  array<string, mixed>  $data
     */
    protected function panelPage(string $title, array $data): Response
    {
        return Inertia::render('xelqun/Panel', ['page' => [
            'title' => $title,
            'eyebrow' => null,
            'subheading' => null,
            'breadcrumbs' => [['label' => $title]],
            'hasWidgets' => false,
            'tabs' => [],
            'subNavigation' => [],
            'headerActions' => null,
            'notice' => null,
            'content' => [],
            ...$data,
        ]]);
    }

    /**
     * Always a new form, so nothing the browser typed comes back in the schema.
     *
     * @param  array<int, array{label: string, url: string|null, method: string}>  $links  Under the form.
     * @param  array{label: string, url: string|null, method: string}|null  $descriptionLink  Right after the description.
     */
    protected function page(string $title, ?string $description, Form $form, string $action, string $submitLabel, array $links = [], ?array $descriptionLink = null): Response
    {
        return Inertia::render('xelqun/PanelAuth', ['page' => [
            'title' => $title,
            'description' => $description,
            'descriptionLink' => $descriptionLink,
            'form' => $form->toSchema(),
            'action' => $action,
            'submitLabel' => $submitLabel,
            'links' => array_values(array_filter($links, fn (array $link): bool => $link['url'] !== null)),
            'quote' => __('xelqun-panels::auth.quote'),
        ]]);
    }
}
