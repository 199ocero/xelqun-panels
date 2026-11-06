<?php

namespace Xelqun\Panels\Pages;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;
use Xelqun\Actions\ActionSet;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Tables\Table;

/**
 * A page of a panel. List its class in Panel::pages().
 *
 * Fill content(), or override getComponent() to render a Vue page of the app.
 * That page uses XqPanelLayout to stay inside the panel.
 *
 * @api
 */
abstract class Page
{
    protected static ?string $title = null;

    protected static ?string $slug = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static int $navigationSort = 0;

    public static function getTitle(): string
    {
        return static::$title ?? Str::headline(class_basename(static::class));
    }

    /**
     * Small text above the title.
     */
    public function getEyebrow(): ?string
    {
        return null;
    }

    /**
     * A line under the title.
     */
    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * The trail in the top bar, ending with this page.
     *
     * @return array<int, array{label: string, url?: string}>
     */
    public function getBreadcrumbs(): array
    {
        return [['label' => static::getTitle()]];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): string|int|null
    {
        return null;
    }

    public static function getNavigationItem(Panel $panel): NavigationItem
    {
        return NavigationItem::make(static::$navigationLabel ?? static::getTitle())
            ->icon(static::$navigationIcon)
            ->group(static::$navigationGroup)
            ->sort(static::$navigationSort)
            ->url(static::getUrl(panel: $panel))
            ->badge(static::getNavigationBadge())
            ->active(request()->routeIs($panel->routeName(static::getRouteName())));
    }

    /**
     * The URL under the panel's path. An empty one is the panel's home.
     */
    public static function getSlug(): string
    {
        return static::$slug ?? Str::kebab(class_basename(static::class));
    }

    public static function getRouteName(): string
    {
        return 'pages.'.(static::getSlug() === '' ? 'home' : static::getSlug());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(array $parameters = [], ?Panel $panel = null): string
    {
        $panel ??= app(Panels::class)->current();

        return route($panel->routeName(static::getRouteName()), $parameters);
    }

    /**
     * Who can open this page. The panel has already let the user in.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * Buttons beside the title. Each one still needs its own authorize().
     *
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(): array
    {
        return [];
    }

    /**
     * What the page shows, top to bottom.
     *
     * @return array<int, Table|ActionSet>
     */
    public function content(): array
    {
        return [];
    }

    /**
     * Props for a Vue page of the app. See getComponent().
     *
     * @return array<string, mixed>
     */
    protected function getProps(): array
    {
        return [];
    }

    protected function getComponent(): string
    {
        return 'xelqun/Panel';
    }

    public function __invoke(): Response
    {
        abort_unless(static::canAccess(), 403);

        return Inertia::render($this->getComponent(), [...$this->getProps(), 'page' => $this->toData()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toData(): array
    {
        return [
            'title' => static::getTitle(),
            'eyebrow' => $this->getEyebrow(),
            'subheading' => $this->getSubheading(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'headerActions' => $this->headerActions() === [] ? null : PageActionSet::for($this)->toSchema(),
            'content' => array_map(fn (Table|ActionSet $item): array => match (true) {
                $item instanceof Table => ['type' => 'table', 'schema' => $item->toSchema()],
                $item instanceof ActionSet => ['type' => 'actions', 'schema' => $item->toSchema()],
            }, $this->getVisibleContent()),
        ];
    }

    /**
     * Only a Table checks itself when it renders, and then it fails the whole
     * page. Part of a page being off limits should just leave that part out.
     *
     * @return array<int, Table|ActionSet>
     */
    private function getVisibleContent(): array
    {
        $user = auth()->user();

        return array_values(array_filter($this->content(), fn (Table|ActionSet $item): bool => $item instanceof Table
            ? $item->authorize($user)
            : $item->authorize($user, $item->getRecord())));
    }
}
