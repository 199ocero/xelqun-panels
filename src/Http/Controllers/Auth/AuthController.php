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
     * Always a new form, so nothing the browser typed comes back in the schema.
     */
    protected function page(string $title, ?string $description, Form $form, string $action, string $submitLabel): Response
    {
        return Inertia::render('xelqun/PanelAuth', ['page' => [
            'title' => $title,
            'description' => $description,
            'form' => $form->toSchema(),
            'action' => $action,
            'submitLabel' => $submitLabel,
        ]]);
    }
}
