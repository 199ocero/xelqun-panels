# Changelog

Xelqun Panels is alpha. Until 1.0, a minor version can break existing code, and each break is listed here.

## Unreleased

This needs `xelqun/forms` 0.9 and `xelqun/tables` 0.7.

### Added

- **`Panel::middleware()`** adds middleware to every route of the panel, **`authMiddleware()`** to the routes behind sign in, and **`tenantMiddleware()`** to the routes inside a tenant, once it's known.
- **`Panel::tenantRoutePrefix('team')`** puts a segment before the tenant, as in `/admin/team/acme`, and **`tenantDomain('{tenant}.example.com')`** finds the tenant in the host instead.
- **`Panel::tenantMenuItems()`** adds links to the tenant menu.
- **Pages of a resource's own.** Any extra key in `getPages()` gets a route, at `$routePath` or its key, and `getUrl('key')` links to it. Before, such a page was quietly left without one.
- **`ManageRelatedRecords`**: one relation manager on a page of its own under the record, at `{record}/key`.
- **`Resource::shouldRegisterNavigation()`** leaves a resource out of the navigation.
- **`NavigationGroup::make('Shop')->icon('store')->collapsible()`** in `navigationGroups()`. A collapsible group remembers whether the viewer folded it, and `collapsed()` starts it folded.
- **`$navigationParentItem`** on a page or resource, or `parentItem()` on a `NavigationItem`, puts an item under another of its group, shown while either is open.
- **`getNavigationBadgeColor()`** and **`getNavigationBadgeTooltip()`** on pages and resources, and `badgeColor()` and `badgeTooltip()` on a `NavigationItem`.
- The notification bell shows a notification's icon and its actions that link somewhere. Actions that run code stay in the toast, because their token has expired by the time anyone opens the bell.
- **Dashboard filters.** A page's `filters()` puts fields above its widgets. Their values stay in the URL, are checked by the fields' rules, and reach each widget as `$this->pageFilters`.
- **`$pollingInterval`** on a widget reloads the page's widgets that often, in seconds.
- **`getFooterWidgets()`** puts widgets below a page's content.
- **Relation managers:** `getBadge()` and `getBadgeColor()` on the tab, `$isLazy` to load the rows after the page renders, `isReadOnly()` to override the page, `RelationGroup::make('Contacts', [...])` for several under one tab, and `Resource::getRelationManagersLayout()` returning `stacked` to show every table at once.
- **`getGlobalSearchResultActions()`** adds links under a search result, and **`$globalSearchResultsLimit`** sets how many results a resource gives.

## 0.4.2 - 2026-11-08

### Fixed

- A navigation or user menu item that links to another site opens it. It was sent as an Inertia visit, which the other site's CORS policy blocked, so the click did nothing.

## 0.4.1 - 2026-11-08

This release needs `xelqun/tables` 0.6.1: `composer update xelqun/panels xelqun/tables` and `npm install @xelqun/panels@0.4.1 @xelqun/tables@0.6.1`.

### Added

- "Send a new code" on the two-factor page for someone who gets codes by email. It replaces the old code and gives the challenge its full five minutes again (3 a minute).

### Changed

- `ListWidget` is generic: write `/** @extends ListWidget<Product> */` and `title()`, `meta()` and the others get a `Product` for Larastan.
- `ReplicateAction::beforeReplicaSaved()` takes a closure typed for your model, such as `fn (Post $replica) => ...`, without a Larastan error.
- `Resource::bulkActions()` and `RelationManager::bulkActions()` are documented to return `ActionGroup`s too, as tables always allowed.

### Fixed

- "Restore selected" and "Delete selected for good" only show while the list shows deleted rows. It needs `xelqun/tables` 0.6.1, which sends bulk actions with each query, so panels now requires it.

## 0.4.0 - 2026-11-08

This release needs `xelqun/forms` 0.8 and `xelqun/tables` 0.6.

### Upgrading

`composer update xelqun/panels xelqun/forms xelqun/tables` and `npm install @xelqun/panels@0.4.0 @xelqun/tables@0.6.0 @xelqun/forms@0.8.0`.

### Changed

- **Icons come from `@lucide/vue`**, the new name of the deprecated `lucide-vue-next`.
- **The tenant switcher lists 20 tenants**, always with the current one, and has a search box for the rest. Every page carried the whole list, which was 525 KB for 5,000 tenants.
- `make:xelqun-resource` and `make:xelqun-relation-manager` start from a column the model's table has (`name`, `title`, `label`, `number`, `email`, `slug`, or its first text column), and a generated resource sets `$recordTitleAttribute`, so it shows in global search.
- The published two-factor migration carries the date it was published, so it runs in order with the app's own.

### Fixed

- `Resource::getUrl()` works outside a panel's request, as in a job, a mail or a test, when one panel registers the resource. With more than one, it says to pass the panel.
- `actingInPanel()` enters the panel too, so `getUrl()` works in a test after it.
- A nested resource finds its parent once a request, instead of once for every row: 26 queries for a page of 10 became 1.
- Registration reads the new user back before asking `canAccessPanel()`, so a column the database fills in, such as `is_admin`, isn't null. A new user the panel refuses is told someone has to give them access.
- An action with `authorize('export')` on a page of its own asks the gate, rather than always hiding. This needs `xelqun/forms` with the same fix.
- A custom widget keeps the heading it gives its card when the widget class sets none.
- The two-factor page says the code was emailed when it was.
- A navigation item with no icon lines up with the others, and shows its initial on a collapsed sidebar.
- `make:xelqun-panel` only asks you to add `PanelUser` when the user model doesn't have it.
- Each auth and panel endpoint has its own rate limit, in each panel. They shared one counter per user or visitor, so six requests of any throttled kind blocked a password reset.

## 0.3.1 - 2026-11-07

### Fixed

- **Doughnut charts show their ring.** Its animation moved the pivot of the ring's rotation, which pushed it out of the card.
- **`HasTenants::getTenants()` accepts a collection of your own model** in PHPStan, as in `Collection<int, Team>`.

## 0.3.0 - 2026-11-07

### Added

- **`Panel::domain()`** puts a panel on its own host, as in `->domain('admin.example.com')`. Panels on different domains can share a path, and `path('')` puts one at the root of its domain.

## 0.2.0 - 2026-11-07

This release needs `xelqun/forms` 0.7 and `xelqun/tables` 0.5.

### Added

- **Global search finds records.** ⌘K searches each resource through `getEloquentQuery()` and its policy. Choose the columns with `getGloballySearchableAttributes()` and what shows under a result with `getGlobalSearchResultDetails()`.
- **A New button in the sidebar**, listing every resource the user may create.
- **`discoverResources()`** adds every resource in a folder. `make:xelqun-panel` now includes it.
- **Relation managers.** A table of a relationship under a record's edit and view pages, with modals, and `make:xelqun-relation-manager`.
- **One-page resources.** `ManageRecords` and `make:xelqun-resource --simple` keep everything on the list page, in modals.
- **List tabs.** `Resource::getTabs()` with `Tab::make()`.
- **A `Color` enum, as in Filament.** `colors(['primary' => Color::Amber])` takes Tailwind's palettes or a hex value, for `primary`, `danger`, `gray`, `info`, `success` and `warning`, and a closure for a color that comes from a setting. A solid color keeps white text and deepens until it passes WCAG AA, and bright yellows, ambers and limes get dark text in their own hue.
- **A notification bell.** `databaseNotifications()`, with `DatabaseNotification::make()->sendToDatabase($user)`.
- **Two-factor authentication.** `multiFactorAuthentication()` adds authenticator apps and recovery codes, optional or required, with no new dependency.
- **Tenancy.** `tenant(Team::class)` moves a panel under `/admin/{tenant}`, keeps its resources' models to that tenant in every query, and adds a switcher to the sidebar.
- **`topNavigation()`** puts the navigation across the top.
- **Plugins.** `Panel::plugin()` with the `Plugin` contract.
- **Render hooks.** `Panel::renderHook()` puts a Vue component in the shell.
- **Translations.** The shell's text comes from `shell.php`, so every part of a panel can be translated.
- **Ready-made actions.** `ViewAction`, `EditAction`, `DeleteAction`, `ReplicateAction`, `RestoreAction` and `ForceDeleteAction`.
- **Soft deletes.** A model that uses them gets a filter, restore and force delete, one at a time or in bulk.
- **Linking existing records.** `AttachAction`, `DetachAction`, `AssociateAction` and `DissociateAction` for relation managers.
- **Chart widgets.** `ChartWidget` draws line, bar and doughnut charts as SVG, with no chart library.
- **Widgets on resource pages.** `getHeaderWidgets()` above a list, and `getRecordWidgets()` above a record.
- **Clusters.** `Cluster` puts pages and resources behind one link, with tabs between them.
- **One-record resources.** `ManageSingleRecord` edits a single record, with no list.
- **More panel settings.** `brandLogo()`, `darkModeBrandLogo()`, `brandLogoHeight()`, `favicon()`, `maxContentWidth()`, `darkMode()`, `defaultThemeMode()`, `globalSearch()`, `breadcrumbs()` and `userMenuItems()`.
- **CSV export and import.** `ExportAction` and `ImportAction`.
- **Broadcast notifications.** `broadcastNotifications()` updates the bell at once, through Laravel Echo.
- **More entries.** `ColorEntry`, `CodeEntry`, `KeyValueEntry` and `RepeatableEntry`.
- **Email codes at sign in.** `emailAuthentication()`, with `HasEmailAuthentication`.
- **Tenant registration and profile.** `tenantRegistration()` and `tenantProfile()`.
- **Nested resources.** `$parentResource` puts a resource under a record, as in `/posts/1/comments`.
- **Relation tables on tabs**, when a record has more than one.
- **Record sub-navigation.** `getRecordSubNavigation()` adds tabs across a record's pages.
- **`make:xelqun-widget`** makes a chart or stats widget, and **`InteractsWithPanels`** helps tests sign in on a panel.
- **Motion throughout.** One highlight glides between sidebar links and table rows, and a pill slides to the current page. The same glide runs through menus and the search palette, by pointer or arrow keys. A tab's underline leans over to a hovered tab and settles back. Stat cards and tiles share one highlight that slides from card to card. Sidebar links press, their icons lift on hover, and a badge pops when its count changes. Pages and widgets ripple in, numbers rise digit by digit, charts draw themselves, and menus, the palette and the drawer all open and close properly. Theme changes crossfade. It follows forms' `--xq-*` tokens, and reduced motion turns it off.
- **An animations switch.** Anyone can turn animations off in the user menu, and `animations(false)` sets a panel's default.
- **Infolists.** `Resource::infolist()` with `TextEntry`, `IconEntry` and `ImageEntry`, in place of a disabled form on the view page.

### Changed

- **New sign-in screens.** Login, registration, password reset, email verification, two-factor and new tenant pages are two columns: the form on the page itself, with filled fields and a full-width button, beside a moving gradient in the panel's primary color, with a large quote on it (`auth.quote` in the translations) and a light and dark switch in the corner. The built-in forms use placeholders in place of visible labels. The login heading now names the panel, and its sign up link moved under the heading.
- A signed-out JSON request to a panel gets a 401 instead of a redirect to login.
- Panels default to Filament's semantic colors: `danger` Red, `gray` Zinc, `info` Blue, `success` Green and `warning` Amber.
- Two-factor is checked on every panel page and token, not just the panel's login form, so signing in through the app's own login can't get around it. A remember-me login is asked for a code again once its session ends.
- `colors()` no longer refuses colors other than `primary`, and `getPrimaryColor()` is gone. Use `getThemeVariables()`.
- With tabs, a resource's table id includes the tab, as in `posts-active-table`.

### Fixed

- **Top navigation has a tenant switcher on desktop.** It only lived in the sidebar, which that layout hides.
- **The top bar matches the page.** It takes the page's background in place of the card color, so the two read as one surface in both themes.
- **A collapsed sidebar stays usable.** Links show their name in a tooltip, a line separates the groups, the avatar opens the user menu, which was squeezed out, and the tenant switcher stays as a button with the tenant's initial. The whole user row opens it, not just the settings icon.
- **Saving and running an action no longer flicker.** A save that stays on the page keeps the page, and widgets stay on screen while a reload brings new data, so the entrance doesn't replay.
- **A new panel works straight away.** `make:xelqun-panel` left out `login()` and any pages, so `/admin` was a 404.
- **A missing `PanelUser` says so at login, in debug mode.** With the right password, the login page only said the credentials were wrong.

## 0.1.0 - 2026-11-06

The first release. It needs `xelqun/forms` 0.6 and `xelqun/tables` 0.4.

### Added

- **Panels.** Declare one or more in a `PanelProvider`, each with its own path, guard, brand and color, in light and dark themes.
- **Authentication.** Login, logout, password reset, registration, email verification and a profile page, per panel, with no Fortify. Access is denied unless a user model implements `PanelUser`.
- **Resources.** One class gives a model list, create, edit and view pages, with table actions and policies on every step. No policy means no access.
- **Pages.** Custom pages with header actions, or your own Vue component inside the panel's layout.
- **Dashboards.** `StatsOverview`, `ListWidget`, `TileWidget`, `TableWidget` and your own widgets, loaded after the page paints.
- **The shell.** A sidebar with groups, badges and a footer card, a top bar with breadcrumbs, ⌘K page search, a user menu, a theme switcher, a collapsible sidebar and a drawer on phones.
- **Commands.** `xelqun-panels:install`, `make:xelqun-panel`, `make:xelqun-page` and `make:xelqun-resource`.
