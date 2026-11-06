<?php

namespace Xelqun\Panels;

use Closure;
use Illuminate\Support\Collection;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Navigation\SidebarCard;
use Xelqun\Panels\Pages\Page;

/**
 * One admin area: its URL, its guard and what it holds. Configure it in a
 * PanelProvider.
 *
 * @api
 */
final class Panel
{
    private const string ID = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const string HEX_COLOR = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    private const string FONT = '/^[A-Za-z0-9 ]+$/';

    private string $id = '';

    private string $path = '';

    private ?string $brandName = null;

    private string $authGuard;

    private ?string $primaryColor = null;

    private string $font = 'Inter';

    /** @var array<int, class-string<Page>> */
    private array $pages = [];

    /** @var array<int, string> */
    private array $navigationGroups = [];

    /** @var array<int, NavigationItem> */
    private array $navigationItems = [];

    /** @var (Closure(): ?SidebarCard)|null */
    private ?Closure $sidebarFooter = null;

    /**
     * The default guard is read now, because entering a panel changes that
     * setting, and a panel with no guard of its own would then get another's.
     */
    public static function make(): self
    {
        $panel = new self;
        $panel->authGuard = (string) config('auth.defaults.guard');

        return $panel;
    }

    /**
     * Names the panel in route names and tokens, so it can't change once tokens are out.
     */
    public function id(string $id): self
    {
        if (preg_match(self::ID, $id) !== 1) {
            throw new PanelDefinitionException("[{$id}] is not a valid panel id. Use lowercase letters, numbers and dashes.");
        }

        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function path(string $path): self
    {
        $this->path = trim($path, '/');

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): string
    {
        return url($this->path);
    }

    public function brandName(string $name): self
    {
        $this->brandName = $name;

        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName ?? (string) config('app.name');
    }

    /**
     * The panel's accent, as a hex value. The dark theme derives its own from it.
     * It ends up in a style attribute, which is why nothing else is accepted.
     *
     * @param  array{primary?: string}  $colors
     */
    public function colors(array $colors): self
    {
        $primary = $colors['primary'] ?? '';

        if (array_keys($colors) !== ['primary'] || preg_match(self::HEX_COLOR, $primary) !== 1) {
            throw new PanelDefinitionException('colors() takes a primary color as a hex value, like ["primary" => "#0071e3"].');
        }

        $this->primaryColor = $primary;

        return $this;
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    /**
     * A family from Bunny Fonts.
     */
    public function font(string $family): self
    {
        if (preg_match(self::FONT, $family) !== 1) {
            throw new PanelDefinitionException("[{$family}] is not a valid font family.");
        }

        $this->font = $family;

        return $this;
    }

    public function getFont(): string
    {
        return $this->font;
    }

    /**
     * Must be a session guard.
     */
    public function authGuard(string $guard): self
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }

    /**
     * @param  array<int, class-string<Page>>  $pages
     */
    public function pages(array $pages): self
    {
        $this->pages = [...$this->pages, ...$pages];

        return $this;
    }

    /**
     * @return array<int, class-string<Page>>
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * The order of the sidebar's groups. Ungrouped links come first, and a group
     * not named here comes last.
     *
     * @param  array<int, string>  $groups
     */
    public function navigationGroups(array $groups): self
    {
        $this->navigationGroups = $groups;

        return $this;
    }

    /**
     * Links to anything other than this panel's pages.
     *
     * @param  array<int, NavigationItem>  $items
     */
    public function navigationItems(array $items): self
    {
        $this->navigationItems = [...$this->navigationItems, ...$items];

        return $this;
    }

    /**
     * What the viewer can reach, grouped and in order.
     *
     * @return array<int, array{label: string|null, items: array<int, array<string, mixed>>}>
     */
    public function getNavigation(): array
    {
        /** @var Collection<string, Collection<int, NavigationItem>> $groups */
        $groups = collect($this->pages)
            ->filter(fn (string $page): bool => $page::shouldRegisterNavigation() && $page::canAccess())
            ->map(fn (string $page): NavigationItem => $page::getNavigationItem($this))
            ->concat($this->navigationItems)
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->groupBy(fn (NavigationItem $item): string => (string) $item->getGroup());

        return $groups
            ->sortBy(function (Collection $items, string $label): int|float {
                $position = array_search($label, $this->navigationGroups, true);

                return $label === '' ? -1 : ($position === false ? INF : $position);
            })
            ->map(fn (Collection $items, string $label): array => [
                'label' => $label === '' ? null : $label,
                'items' => $items->map(fn (NavigationItem $item): array => $item->toArray())->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * A card at the foot of the sidebar. It's a closure because it runs on every
     * page, for the current user.
     *
     * @param  Closure(): ?SidebarCard  $card
     */
    public function sidebarFooter(Closure $card): self
    {
        $this->sidebarFooter = $card;

        return $this;
    }

    /**
     * The full name of a route of this panel, as in route($panel->routeName('pages.home')).
     */
    public function routeName(string $name): string
    {
        return "xelqun-panels.{$this->id}.{$name}";
    }

    /**
     * What the shell needs on every page. The user is picked field by field,
     * because a whole model would send every attribute it doesn't hide.
     *
     * @return array<string, mixed>
     */
    public function toShell(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->getUrl(),
            'brand' => ['name' => $this->getBrandName()],
            'theme' => ['primary' => $this->primaryColor, 'font' => $this->font],
            'navigation' => $this->getNavigation(),
            'sidebarFooter' => $this->sidebarFooter === null ? null : ($this->sidebarFooter)()?->toArray(),
            'user' => $this->shellUser(),
        ];
    }

    /**
     * @return array{name: string, email: string}|null
     */
    private function shellUser(): ?array
    {
        $user = auth()->user();

        return $user === null ? null : ['name' => (string) data_get($user, 'name'), 'email' => (string) data_get($user, 'email')];
    }
}
