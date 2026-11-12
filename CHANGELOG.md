# Changelog

Xelqun Panels is alpha. Until 1.0, a minor version can break existing code, and each break is listed here.

## Unreleased

### Upgrading

- **A user with Fortify's own two-factor turned on is asked for a code at the panel's login**, as a starter kit sets it up. Their authenticator app or a Fortify recovery code answers it. A session that passed Fortify's challenge is asked again on the panel. Turning Fortify's `twoFactorAuthentication` feature off stops it.

### Fixed

- **The panel's login skipped Fortify's two-factor.** It signs in on the app's guard, so a starter kit user with two-factor turned on got a session for the whole app, not just the panel, on the password alone.
- `xelqun-panels:install` said "Xelqun Forms is installed." twice.

## 0.7.0 - 2026-11-11

### Upgrading

- **A panel's `middleware()`, `authMiddleware()` and `tenantMiddleware()` now also run on the form and table endpoints** (`/_xelqun/forms/*` and `/_xelqun/tables/*`) for every token issued inside the panel. Middleware there sees the endpoint's route, which has no route parameters, so middleware that reads one has to allow for that. A middleware that answers itself, such as a redirect, refuses the request with a 403.
- **An export writes what the table shows.** A heading from `getExportColumns()` that names one of the table's columns now gets that column's `state()` and formatting, such as `money()`, `date()` or `formatStateUsing()`. An attribute that isn't a table column is still written as stored.
- **`AttachAction`, `DetachAction`, `AssociateAction` and `DissociateAction` ask the related model's policy** for `attach`, `detach`, `associate` and `dissociate`, on top of `update` on the parent. A policy without the method refuses, so add them to the policies of related models you link, or pass `->authorize(true)` to keep the old behavior. The attach and associate lists also only offer records the user may `view`.
- **A `ResourcePage` of your own needs `viewAny`, or `view` on its record**, where before it was open to anyone in the panel. Override `isAccessible()` to change that.
- **`ManageSingleRecord` asks `create` until its record exists**, then `update`. Before, the first save needed `update` on an unsaved model.
- **Changing or resetting a password signs out every other session**, through Laravel's `AuthenticateSession`, which panels now run on their signed-in routes and on the form and table endpoints. The session that made the change stays signed in.
- **Turning on app two-factor and changing the email address ask for the current password.**
- **Five wrong two-factor codes end the challenge**, so the password has to be entered again, and a user then waits 15 minutes for more guesses, where before it was one minute.
- `POST login` is also limited to 20 tries a minute from one address, across all accounts.
- An email-change link only works while the address is still the one it was sent from, so an older link is spent once the address changes.
- **A `ListWidget`'s rows and actions ask `canView()`**, where before its table endpoints let anyone in the panel read them. A `TableWidget` still asks its table's own `authorize()`, so make that match `canView()`.
- A resource nested in a resource that is nested itself now fails when the routes register. Only one level was ever supported, and the grandparent was looked up with the parent's key.
- Tokens issued before this version are refused, so a page that was open across the upgrade asks to be reloaded.

### Fixed

- **The tenant-profile form's endpoints didn't ask `update` on the tenant**, so live updates, uploads and modal actions kept working for up to the token's 12 hours after the user lost it. The page and its save already asked.
- A tenant slug is URL-encoded in a `tenantDomain()`, so a slug like `evil.com/` can't make the tenant switcher link to another host.
- A record URL with a key that isn't a whole number, such as `/posts/5abc`, is a 404 for a model with integer keys. MySQL used to read it as post 5.
- A queued import does nothing unless its payload names an `ImportAction` and a `Resource`.
- **A form field for the tenant's or parent's key could move a record.** The tenant and parent were set before the form's values, so a `team_id` field, or a relationship `Select` for it, could put a new record in another tenant or move an existing one. They're now set again after the form saves.
- **Check the class in your `canAccessTenant()`.** The documented example only compared keys, so with two tenant panels on different models (say teams and organizations), a member of team 3 could open organization 3. Use `$tenant instanceof Team && $this->teams()->whereKey($tenant)->exists()`.
- **Linking and unlinking in a relation manager never asked a policy** about the related record, so anyone who could update the parent could attach any record the list offered, such as an admin role.
- **`_export` ignored the export button's own checks.** It only asked `viewAny`, so `ExportAction::for(...)->authorize(...)`, `visible()` and `hidden()`, and a stricter `isAccessible()` on the list page, could be skipped by opening the URL. It also wrote values a column masks for display. It now asks everything the button asks.
- **The form and table endpoints skipped the panel's middleware**, so a user an app's middleware turned away (a suspended account, a lapsed subscription, an IP allow-list) could keep loading rows and running actions with a token from a page they had open. A user who still has to set up two-factor can likewise only use the profile page's tokens now.
- **A tenant scope now applies only in the panel that added it.** All panels used to share one global scope name, so with a model in two tenancy panels, the last panel to add its scope replaced the other's. A scope also stayed on the model after a request moved to another panel, which matters under Octane. Each panel now adds its own `xelqun-panels.tenant.{panel id}` scope, and it does nothing outside that panel.

## 0.6.1 - 2026-11-10

### Fixed

- The import modal's button says "Import", not "Save".
- **A queued import on the `sync` queue no longer signs the viewer out** for the rest of the request that started it. The job now puts back the user the request had.

## 0.6.0 - 2026-11-10

Update both halves together: `composer update xelqun/panels xelqun/tables xelqun/forms` and `npm install @xelqun/panels@0.6.0 @xelqun/tables@0.8.0 @xelqun/forms@0.10.0`.

### Upgrading

- `getImportColumns()` may return `ImportColumn` objects as well as names. Code that reads it should allow for both, or use `ImportAction::getColumns()`. A header that only differs in case or spacing now matches, where before it was ignored.
- **Requires `xelqun/forms` ^0.10 and `xelqun/tables` ^0.8**, and the same `@xelqun/forms` and `@xelqun/tables` on npm. Their upgrade notes apply too.

### Upgrading (names)

- **An infolist entry's schema type is now `xelqun/entry`**, not `xelqun-panels/entry`, so every Xelqun type has the same vendor prefix. A plugin that replaces the entry renderer should list the new name.
- **`Page::headerActions()` is now `getHeaderActions()`**, as in Filament and like `Resource::getHeaderActions()`. Rename the method on your pages. A table's and a relation manager's `headerActions()` stay as they are.

### Upgrading (clusters)

- **A cluster's members live under its slug**, as in Filament: a page `Billing` in the cluster `Settings` moves from `/admin/billing` to `/admin/settings/billing`. A cluster's slug defaults to its class name in kebab case. Set `protected static ?string $slug` to change it. Their route names gain the cluster too, as in `xelqun-panels.admin.pages.settings.billing` and `xelqun-panels.admin.resources.settings.countries.index`. `getUrl()` builds the new URLs, so only hard-coded paths and route names need changing. Two clusters can now have members with the same slug, and a page or resource outside a cluster that takes the cluster's slug fails while the routes register, instead of catching its members' URLs.

### Added

- **`$navigationLabel` on a resource**, as on a page and in Filament.
- **`TextEntry::hint()` and `inlineLabel()` take a closure**, which gets `$record`, as `Field`'s do.
- **`ImportColumn`**, as in Filament. `getImportColumns()` can return `ImportColumn::make('sku')->label('SKU')->guesses(['item code'])->rules(['max:20'])->requiredMapping()` beside plain names. A CSV header now matches a column by its name, label or guesses, ignoring case, spaces, dashes and underscores, so `Product Name` fills `product_name`. A file with no header for a required column is refused before a row is read. A column's rules come on top of the form's.
- **`ImportAction::for(...)->queue()`** reads the whole file in a queued job, with no row limit, as the user who started it, in their panel, tenant and parent record. The job checks again that they can still reach the panel and the tenant and create records, then tells them the result in their bell, or that it stopped part way if the job fails. The panel needs `databaseNotifications()`. The job runs once and never retries, so a crash can't import rows twice.

### Fixed

- **An import past 1,000 rows says so.** The rows after the first 1,000 used to be dropped without a word. They're now counted and named in the notification, which turns into a warning. `importRows()` returns a `skipped` count too.

### Changed

- `panelsPlugin()` no longer registers the icons that forms already ships (`eye`, `link`, `mail`, `map-pin`, `settings`, `tag`, `users`). The same icons still show.
- **Dashboard filters** sit in one row above the widgets, each field at a set width with the Apply button beside them, instead of in a card across the whole page.

## 0.5.0 - 2026-11-10

This needs `xelqun/forms` 0.9 and `xelqun/tables` 0.7: `composer update xelqun/panels xelqun/forms xelqun/tables` and `npm install @xelqun/panels@0.5.0 @xelqun/forms@0.9.0 @xelqun/tables@0.7.0`.

### Upgrading

- `Resource::getPages()` routes every extra key now, so a page you added there that used to be ignored becomes reachable. Give it an `isAccessible()`.
- Relation tables share one tab bar above them, instead of each carrying its own.

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
- **Infolist entries** take a text column's options: `description()`, `placeholder()`, `limit()`, `words()`, `lineClamp()`, `copyable()`, `tooltip()`, `numeric()`, `since()` and more. Entries add **`hint()`** and **`inlineLabel()`**, and `TextEntry` adds **`html()`**, **`markdown()`** (both cleaned on the server), **`prefix()`** and **`suffix()`**.
- **"Create & create another"** on create pages, which saves and opens a new form. Turn it off with `protected static bool $canCreateAnother = false` on a `CreateRecord`.
- **`Panel::emailChangeVerification()`** keeps a new email address on the profile waiting until a link sent to it is opened, and tells the old address about the change.
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
