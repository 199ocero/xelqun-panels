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
- **A `Color` enum, as in Filament.** `colors(['primary' => Color::Amber])` takes Tailwind's palettes or a hex value, for `primary`, `danger`, `gray`, `info`, `success` and `warning`, and a closure for a color that comes from a setting. Text on any color meets WCAG AA.
- **A notification bell.** `databaseNotifications()`, with `DatabaseNotification::make()->sendToDatabase($user)`.
- **Two-factor authentication.** `multiFactorAuthentication()` adds authenticator apps and recovery codes, optional or required, with no new dependency.
- **Infolists.** `Resource::infolist()` with `TextEntry`, `IconEntry` and `ImageEntry`, in place of a disabled form on the view page.

### Changed

- A signed-out JSON request to a panel gets a 401 instead of a redirect to login.
- Panels default to Filament's semantic colors: `danger` Red, `gray` Zinc, `info` Blue, `success` Green and `warning` Amber.
- `colors()` no longer refuses colors other than `primary`, and `getPrimaryColor()` is gone. Use `getColorVariables()`.
- With tabs, a resource's table id includes the tab, as in `posts-active-table`.

### Fixed

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
