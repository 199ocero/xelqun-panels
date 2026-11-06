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
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Widgets\PanelWidget;
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
     * The title of this request's page, which a generic page works out per record.
     */
    public function getHeading(): string
    {
        return static::getTitle();
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
        return [['label' => $this->getHeading()]];
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
     * Whether this request may see this page.
     */
    public function isAccessible(): bool
    {
        return static::canAccess();
    }

    /**
     * Why it can't, for a developer. Empty outside debug mode.
     */
    protected function getAccessDeniedReason(): string
    {
        return '';
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
     * @return array<int, Table|ActionSet|FormContent>
     */
    public function content(): array
    {
        return [];
    }

    /**
     * Links above the content, as for a resource's list.
     *
     * @return array<int, array{key: string, label: string, icon: string|null, badge: string|null, url: string, active: bool}>
     */
    public function getTabs(): array
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

    /**
     * Widgets above the content.
     *
     * @return array<int, class-string<PanelWidget>>
     */
    protected function getWidgets(): array
    {
        return [];
    }

    /**
     * Deferred, so the page paints at once and a slow query only holds back the cards.
     *
     * @return array<string, mixed>
     */
    private function getWidgetProps(): array
    {
        return $this->getWidgets() === [] ? [] : ['widgets' => Inertia::defer(fn (): array => collect($this->getWidgets())
            ->filter(fn (string $widget): bool => $widget::canView())
            ->sortBy(fn (string $widget): int => $widget::getSort())
            // One failing widget shouldn't take the others down. rescue() reports it.
            ->map(fn (string $widget): ?array => rescue(fn (): array => $widget::make()->toWidget()))
            ->filter()
            ->values()
            ->all())];
    }

    protected function getComponent(): string
    {
        return 'xelqun/Panel';
    }

    public function __invoke(): Response
    {
        abort_unless($this->isAccessible(), 403, $this->getAccessDeniedReason());

        return Inertia::render($this->getComponent(), [...$this->getWidgetProps(), ...$this->getProps(), 'page' => $this->toData()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toData(): array
    {
        return [
            'title' => $this->getHeading(),
            'eyebrow' => $this->getEyebrow(),
            'subheading' => $this->getSubheading(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'hasWidgets' => $this->getWidgets() !== [],
            'tabs' => $this->getTabs(),
            'notice' => null,
            'headerActions' => $this->headerActions() === [] ? null : PageActionSet::for($this)->toSchema(),
            'content' => array_map(fn (Table|ActionSet|FormContent $item): array => match (true) {
                $item instanceof Table => [
                    'type' => 'table',
                    'schema' => $item->toSchema(),
                    'heading' => $item instanceof RelationTable ? $item->getHeading() : null,
                ],
                $item instanceof ActionSet => ['type' => 'actions', 'schema' => $item->toSchema()],
                $item instanceof FormContent => $item->toArray(),
            }, $this->getVisibleContent()),
        ];
    }

    /**
     * Only a Table checks itself when it renders, and then it fails the whole
     * page. Part of a page being off limits should just leave that part out.
     *
     * @return array<int, Table|ActionSet|FormContent>
     */
    private function getVisibleContent(): array
    {
        $user = auth()->user();

        return array_values(array_filter($this->content(), fn (Table|ActionSet|FormContent $item): bool => match (true) {
            $item instanceof Table => $item->authorize($user),
            $item instanceof ActionSet => $item->authorize($user, $item->getRecord()),
            $item instanceof FormContent => $item->form->authorize($user, $item->form->getRecord()),
        }));
    }
}
