<?php

namespace Xelqun\Panels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Xelqun\Panels\Exceptions\PanelDefinitionException;

/**
 * Every panel of the app, and which one this request is in.
 *
 * The current panel lives on the request, not here, because this object outlives
 * a request under Octane.
 *
 * @api
 */
final class Panels
{
    public const string VERSION = '0.4.2';

    private const string CURRENT = 'xelqun-panels.current';

    private const string TENANT = 'xelqun-panels.tenant';

    private const string PARENT = 'xelqun-panels.parent';

    /** @var array<string, Panel> */
    private array $panels = [];

    public function register(Panel $panel): void
    {
        $id = $panel->getId();

        if ($id === '') {
            throw new PanelDefinitionException('A panel needs an id().');
        }

        if (isset($this->panels[$id])) {
            throw new PanelDefinitionException("Two panels have the id [{$id}].");
        }

        foreach ($this->panels as $other) {
            if ($other->getPath() === $panel->getPath() && $other->getDomain() === $panel->getDomain()) {
                throw new PanelDefinitionException("The panels [{$other->getId()}] and [{$id}] have the same path.");
            }
        }

        if ($panel->mustSetUpMultiFactorAuthentication(null) && $panel->getProfileForm() === null) {
            throw new PanelDefinitionException("The panel [{$id}] requires two-factor authentication, which is set up on the profile page, so it needs profile().");
        }

        $this->panels[$id] = $panel;
    }

    public function get(string $id): ?Panel
    {
        return $this->panels[$id] ?? null;
    }

    /**
     * @return array<string, Panel>
     */
    public function all(): array
    {
        return $this->panels;
    }

    public function current(): ?Panel
    {
        $panel = request()->attributes->get(self::CURRENT);

        return $panel instanceof Panel ? $panel : null;
    }

    /**
     * The tenant this request is in, on a panel that has them.
     */
    public function tenant(): ?Model
    {
        $tenant = request()->attributes->get(self::TENANT);

        return $tenant instanceof Model ? $tenant : null;
    }

    /**
     * From here on the panel's URLs carry this tenant, and its resources' models
     * only show its records.
     */
    public function enterTenant(Model $tenant): void
    {
        $panel = $this->current() ?? throw new PanelDefinitionException('Enter a panel before a tenant.');

        request()->attributes->set(self::TENANT, $tenant);
        URL::defaults(['tenant' => $tenant->getAttribute($panel->getTenantSlugAttribute())]);

        foreach ($panel->getResources() as $resource) {
            $resource::scopeToTenants($panel);
        }
    }

    /**
     * The key of the parent record, on a page of a nested resource.
     */
    public function parentKey(): ?string
    {
        $key = request()->attributes->get(self::PARENT) ?? request()->route()?->parameter('parentRecord');

        return $key === null ? null : (string) $key;
    }

    /**
     * A token brings the parent back on the requests with none in their URL.
     */
    public function enterParent(string $key): void
    {
        request()->attributes->set(self::PARENT, $key);
    }

    /**
     * Make this panel the one the request is in, with its guard as the default.
     */
    public function enter(Panel $panel): void
    {
        request()->attributes->set(self::CURRENT, $panel);
        Auth::shouldUse($panel->getAuthGuard());
    }
}
