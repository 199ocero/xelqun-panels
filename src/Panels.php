<?php

namespace Xelqun\Panels;

use Illuminate\Support\Facades\Auth;
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
    public const string VERSION = '0.1.0';

    private const string CURRENT = 'xelqun-panels.current';

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
            if ($other->getPath() === $panel->getPath()) {
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
     * Make this panel the one the request is in, with its guard as the default.
     */
    public function enter(Panel $panel): void
    {
        request()->attributes->set(self::CURRENT, $panel);
        Auth::shouldUse($panel->getAuthGuard());
    }
}
