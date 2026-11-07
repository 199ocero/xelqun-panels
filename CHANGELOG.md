# Changelog

Xelqun Panels is pre-alpha. Until 1.0, a minor version can break existing code, and each break is listed here.

## Unreleased

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

- A signed-out JSON request to a panel gets a 401 instead of a redirect to login.
- Panels default to Filament's semantic colors: `danger` Red, `gray` Zinc, `info` Blue, `success` Green and `warning` Amber.
- Two-factor is checked on every panel page and token, not just the panel's login form, so signing in through the app's own login can't get around it. A remember-me login is asked for a code again once its session ends.
- `colors()` no longer refuses colors other than `primary`, and `getPrimaryColor()` is gone. Use `getThemeVariables()`.
- With tabs, a resource's table id includes the tab, as in `posts-active-table`.

### Fixed

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
