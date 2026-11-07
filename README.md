# Xelqun Panels

Describe an admin panel in PHP and get navigation, authentication, CRUD pages and dashboards, rendered in Vue, in light and dark themes.

> **Pre-alpha:** the API will change. Requires Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, [`xelqun/forms`](https://github.com/199ocero/xelqun-forms) and [`xelqun/tables`](https://github.com/199ocero/xelqun-tables).

## Installation

```bash
composer require xelqun/panels
php artisan xelqun-panels:install
php artisan make:xelqun-panel Admin
```

The installer adds the npm package, two small page files in `resources/js/pages/xelqun`, the styles, and `panelsPlugin()` in your Inertia entry file. The new panel has a login page and a dashboard at `/admin`.

Then add `PanelUser` to your user model, as shown under [Access](#access). Nobody can sign in until you do.

## A panel

```php
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName('Acme')
            ->colors(['primary' => '#0071e3'])
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->registration()
            ->profile()
            ->resources([ProductResource::class])
            ->pages([Dashboard::class])
            ->widgets([OrderStats::class])
            ->navigationGroups(['Catalog', 'Sales']);
    }
}
```

## Access

Nobody gets in until you say so. Put `PanelUser` on your user model:

```php
class User extends Authenticatable implements PanelUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}
```

A user model without it can't enter any panel, in any environment. The check runs on every page and on the endpoints forms and tables use, so taking away access takes effect at once, even on a page that's already open.

### Authentication

Auth is built in, per panel, on Laravel's own `Auth`, `Password` and `RateLimiter`. Fortify isn't needed. Turn on what you want:

| Method                | Gives you                                                                          |
| --------------------- | ---------------------------------------------------------------------------------- |
| `login()`             | A login page, limited to 5 tries a minute. Without it, guests are refused.         |
| `passwordReset()`     | Emailed reset links. The answer never says whether an address has an account.      |
| `registration()`      | Sign up. Off by default, and a new user still has to pass `canAccessPanel()`.      |
| `emailVerification()` | Holds users at a prompt until they verify. The user model needs `MustVerifyEmail`. |
| `profile()`           | A page for users to change their name, email and password.                         |

Each one takes a form class, so `login(MyLoginForm::class)` swaps the form. Use `authGuard()` to give a panel its own session guard, and `authPasswordBroker()` for its own broker.

### Two-factor authentication

```php
$panel->multiFactorAuthentication();                    // optional for each user
$panel->multiFactorAuthentication(isRequired: true);    // nobody gets past the profile page without it
```

Add the columns with `php artisan vendor:publish --tag=xelqun-panels-mfa`, then put `HasAppAuthentication` and the `InteractsWithAppAuthentication` trait on your user model. People turn it on from the profile page.

It's the standard 30 second, 6 digit TOTP that any authenticator app reads, with 8 recovery codes. A code works once, guesses are limited to 5 a minute, and the right password alone never makes a session. Secrets are encrypted, and only hashes of recovery codes are kept.

Setup gives a link and a key to type, with no QR code, because that would need a new dependency.

`emailAuthentication()` also lets people get a 6 digit code by email at sign in. Add `HasEmailAuthentication` to the user model, and the same trait covers it. The code is kept only as a hash, works once, and ends with the 5 minute challenge. It's weaker than an authenticator, because anyone in the mailbox can read it.

On HTTPS, the browser's history is encrypted and cleared on sign out, so Back can't show records afterwards. Behind a proxy, set up trusted proxies, or Laravel won't see the request as secure.

## Resources

One class manages one model, and gets list, create, edit and view pages:

```php
class ProductResource extends Resource
{
    protected static string $model = Product::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationIcon = 'package';
    protected static ?string $navigationGroup = 'Catalog';

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')->searchable()->sortable()];
    }
}
```

`php artisan make:xelqun-resource Product` creates this and a policy, and `make:xelqun-widget` makes a chart or stats widget. A panel made with `make:xelqun-panel` picks it up by itself, through `discoverResources(in: app_path('Xelqun/Admin/Resources'), for: 'App\\Xelqun\\Admin\\Resources')`. Otherwise, add it to `resources()`.

**Access goes through the model's policy:** `viewAny`, `view`, `create`, `update` and `delete`. With no policy, or no such method, the answer is no. This differs from Filament, which allows by default.

`getEloquentQuery()` is the security boundary. A record it leaves out is a 404 on every page, table action and form request.

Override `filters()`, `recordActions()`, `bulkActions()`, `configureTable()` and the hooks `mutateBeforeSave()`, `beforeCreate()`, `afterCreate()`, `beforeSave()` and `afterSave()`. To change a page, extend `ListRecords`, `CreateRecord`, `EditRecord` or `ViewRecord` and return it from `getPages()`.

The sidebar's New button lists every resource with a create page that the user may `create`.

### Actions

`recordActions()` starts with edit and delete. Build your own list from the same parts, which take labels, modals and policies from the resource:

```php
public static function recordActions(): array
{
    return [
        ViewAction::for(static::class),
        EditAction::for(static::class),
        ReplicateAction::for(static::class)->excludeAttributes(['slug']),
        DeleteAction::for(static::class),
    ];
}
```

Each takes the usual action methods, such as `->label()` or `->visible()`. `ReplicateAction` needs `replicate` in the policy.

### Export and import

```php
public static function getHeaderActions(): array
{
    return [ExportAction::for(static::class), ImportAction::for(static::class)];
}
```

Export downloads the list as CSV, inside `getEloquentQuery()` and the current tab, using the table's columns. Override `getExportColumns()` to change them. Any cell that a spreadsheet would run as a formula is kept as text.

Import reads a CSV whose first row names the columns. Each row goes through the resource's form, so it gets the same validation, hooks and tenant as a record typed in, and bad rows are counted and skipped. Only columns from `getImportColumns()` are read. It runs in the request and stops at 1,000 rows.

### One record, no list

For settings and the like, return only `ManageSingleRecord` from `getPages()`. It edits what `getSingularRecord()` returns, which by default is the first record, or a new one until it's saved.

### Soft deletes

A model with `SoftDeletes` gets this by itself: deleted records leave the list, a filter brings them back, and `RestoreAction`, `ForceDeleteAction` and their bulk versions appear. They need `restore` and `forceDelete` in the policy. If you override `filters()`, add `TrashedFilter::make('trashed')`.

### Relation managers

A table of a relationship, under a record's edit and view pages, with create, edit and delete in modals:

```php
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function form(): array { return [TextInput::make('number')->required()]; }
    public static function columns(): array { return [TextColumn::make('number')->searchable()]; }
}
```

Return it from `getRelations()` on the resource, or run `php artisan make:xelqun-relation-manager Customer orders`. Set `$relatedResource` to reuse a resource's form, columns and filters instead.

To link records that already exist, add `AttachAction` and `DetachAction` for a many-to-many, or `AssociateAction` and `DissociateAction` for a has-many:

```php
public static function headerActions(Model $owner): array
{
    return [...parent::headerActions($owner), AttachAction::for(static::class, $owner)];
}
```

Access goes through the related model's policy and the parent's `view`. Changing anything also takes the parent's `update`, and the view page is read-only. It works for has-many, morph-many and many-to-many, where a new record is attached to the parent.

With two or more, the tables sit on tabs under the record.

### Nested resources

When related records need pages of their own, nest the resource under its parent, as in `/posts/1/comments/5/edit`:

```php
class CommentResource extends Resource
{
    protected static string $model = Comment::class;
    protected static ?string $parentResource = PostResource::class;
    protected static ?string $parentRelationship = 'comments'; // the default is this model's plural
}
```

It leaves the sidebar, the New menu and search, and only reaches the parent's related records. A parent out of reach is a 404 for everything under it, and new records join the parent whatever the browser sends. To get there, point a relation manager's `$relatedResource` at it, and its rows and New button link to the nested pages.

`getRecordSubNavigation()` on a resource adds tabs across a record's pages.

### One-page resources

For a model with a few fields, keep everything on the list page, with create and edit in modals. `make:xelqun-resource Tag --simple` sets this up:

```php
public static function getPages(): array
{
    return ['index' => ManageRecords::class];
}
```

### List tabs

```php
public static function getTabs(): array
{
    return [
        'all' => Tab::make(),
        'active' => Tab::make('Live')
            ->badge(fn () => Product::where('status', 'active')->count())
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
    ];
}
```

The first tab is the default. A tab always stays inside `getEloquentQuery()`, even with an `orWhere`, and so does everything on that table.

### Infolists

Give the view page real values instead of a disabled form. Entries take the same options as table columns, and sit in any of forms' layouts. Besides the ones below there are `ColorEntry`, `CodeEntry`, `KeyValueEntry`, and `RepeatableEntry` with its own `schema()`:

```php
public static function infolist(): array
{
    return [
        Section::make('Product')->columns(2)->schema([
            TextEntry::make('name'),
            TextEntry::make('category.name')->label('Category'),
            TextEntry::make('status')->badge()->color('success'),
            TextEntry::make('price')->money('USD'),
            IconEntry::make('is_featured')->boolean(),
            ImageEntry::make('image')->disk('public'),
        ]),
    ];
}
```

### Global search

⌘K finds pages and records. A resource with `$recordTitleAttribute` is searched on that column, so if the title is an accessor, or you want more columns, override this:

```php
public static function getGloballySearchableAttributes(): array
{
    return ['name', 'sku', 'category.name'];
}

public static function getGlobalSearchResultDetails(Model $record): array
{
    return ['SKU' => $record->sku];
}
```

Return `[]` to keep a resource out. Search runs through `getEloquentQuery()`, skips a resource the user can't `viewAny`, and checks `view` on each record, showing up to 5 per resource. Results link to the view page. A policy that refuses more than the first 20 matches can hide later ones.

## Tenancy

Split a panel between teams, shops or companies. Every page moves under `/admin/{tenant}`:

```php
$panel->tenant(Team::class, slugAttribute: 'slug');
```

Say who belongs where on your user model:

```php
class User extends Authenticatable implements HasTenants, PanelUser
{
    public function getTenants(Panel $panel): Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams()->whereKey($tenant)->exists();
    }
}
```

Each resource's model needs a relationship to the tenant, named after it (`team()`) unless you pass `ownershipRelationship`, or set `$tenantOwnershipRelationship` on the resource. It can be a belongs-to or a many-to-many. Set `$isScopedToTenant = false` on a resource that isn't owned by a tenant.

Inside a tenant, those models stay in it for every query of the request: tables, search, Select options, widgets, `scopedUnique()`, `scopedExists()` and your own code. New records join the tenant on their own, and it never comes from the browser. A tenant the user isn't in is a 404, and taking someone out takes effect at once, even on an open page.

A sidebar switcher moves between tenants. Notifications belong to the user, not the tenant.

`tenantRegistration(RegisterTeam::class)` lets a user start a tenant, and is where someone with no tenants lands. `tenantProfile(EditTeam::class)` adds a page to change one, which needs `update` in the tenant's policy. Both take a `Form` class. The registration form has to add the user in its `afterSave()`.

## Notifications

`databaseNotifications()` adds a bell to the top bar, which checks every 30 seconds. Change that with `databaseNotificationsPolling('60s')`, or pass `null` to check only on page load. It needs Laravel's table, from `php artisan make:notifications-table`, and `Notifiable` on your user model.

```php
use Xelqun\Panels\Notifications\DatabaseNotification;

DatabaseNotification::make()
    ->title('Order paid')
    ->body('Order 1001 is ready to ship.')
    ->success()
    ->url(OrderResource::getUrl('view', ['record' => $order]))
    ->sendToDatabase($user);
```

## Navigation

**Clusters** put several pages and resources behind one sidebar link, with tabs between them. Extend `Cluster`, then set `protected static ?string $cluster = Settings::class;` on each member. The link goes to the first member the user can reach.

**The user menu** takes more links: `userMenuItems([NavigationItem::make('Billing')->url(fn () => route('billing'))])`.

Add `broadcastNotifications()` and send with `->broadcast($user)` for an instant update. It uses your app's `window.Echo` on the user's private channel, and only sends a signal, so the bell fetches the content itself. Without Echo the bell keeps polling.

## Pages

```php
class Reports extends Page
{
    protected static ?string $navigationIcon = 'chart-column';

    public function headerActions(): array
    {
        return [Action::make('export')->authorize('export')->action(fn () => /* ... */)];
    }

    public function content(): array
    {
        return [ReportsTable::make()];
    }
}
```

`content()` takes tables, action sets and `FormContent`. Override `getComponent()` to render your own Vue page, and use `XqPanelLayout` as its layout to stay inside the panel.

## Dashboards

`Dashboard` shows the widgets from `Panel::widgets()`. It paints at once, and the widgets arrive after with skeletons in their place.

| Widget          | For                                                                                                                                                                            |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `StatsOverview` | A row of numbers, with `Stat::make()`.                                                                                                                                         |
| `ListWidget`    | Rows with an icon, a title, detail, and actions, a progress bar or a link. It's a table underneath, so actions are checked on the server and `query()` limits what it reaches. |
| `TileWidget`    | A grid of small cards with a status.                                                                                                                                           |
| `TableWidget`   | A full table.                                                                                                                                                                  |
| `ChartWidget`   | Line, bar and doughnut charts, drawn with no chart library.                                                                                                                    |
| `Widget`        | Your own, with a Vue component registered in `createXelqunForms({ components })`.                                                                                              |

Set `$columnSpan` (out of 12), `$sort` and `$heading`, and override `canView()` to hide one. `getHeaderWidgets()` on a resource shows widgets above its list, and `getRecordWidgets()` above a record, where a widget reads it from `$this->record`.

```php
class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Orders';

    protected function getType(): string
    {
        return 'line'; // or 'bar' or 'doughnut'
    }

    protected function getData(): array
    {
        return [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [['label' => 'Orders', 'data' => [12, 19, 8]]],
        ];
    }
}
```

## Theming

Set a panel's colors the way Filament does, with `Color` or a hex value:

```php
use Xelqun\Panels\Support\Colors\Color;

$panel->colors([
    'primary' => Color::Amber,
    'danger' => Color::Rose,
    'gray' => Color::Slate,
]);
```

`Color` holds Tailwind's 26 palettes. Any color you leave out keeps its default: `danger` Red, `gray` Zinc, `info` Blue, `success` Green, `warning` Amber, and a blue `primary`. Those match Filament's, apart from the primary.

Text stays readable whatever you choose. Labels on a filled color are black or white, whichever clears WCAG AA's 4.5:1, and colored text is capped in lightness on the light theme. The dark theme derives a lighter version of each color.

To let people choose, pass a closure, which runs on every request:

```php
$panel->colors(fn (): array => ['primary' => Color::fromName(auth()->user()?->theme) ?? Color::Blue]);
```

`Color::options()` gives a Select its choices. Anything that isn't a color is refused, because these end up in a style attribute.

`font('DM Sans')` picks a family from Bunny Fonts, loaded after the page so a slow font host can't hold it back. Everything else is a `--xq-*` token, set under `:root[data-xq-panel]`.

`brandLogo()`, `darkModeBrandLogo()`, `brandLogoHeight()` and `favicon()` set the brand. `maxContentWidth('full')` sets how wide pages get. `darkMode(false)` removes the dark theme, `darkMode(isForced: true)` makes it the only one, and `defaultThemeMode('dark')` picks the theme until someone chooses. `globalSearch(false)` and `breadcrumbs(false)` remove those.

`topNavigation()` moves the navigation across the top, in place of the sidebar. Phones keep the drawer.

Icons in PHP are names. The panels plugin adds common sidebar icons to the forms set, and `createXelqunForms({ icons })` adds your own.

## Extending a panel

**Plugins** bundle resources, pages and settings. Implement `Plugin`, then add it with `$panel->plugin(new BlogPlugin)`. `register()` runs as the panel is declared, and `boot()` once the app has booted.

**Render hooks** put your own Vue component in the shell:

```php
$panel->renderHook(RenderHook::TopbarEnd, 'acme/credits', fn (): array => ['left' => auth()->user()->credits]);
```

Register the component in `createXelqunForms({ components })`. The places are `SidebarStart`, `SidebarEnd`, `TopbarStart`, `TopbarEnd`, `ContentStart`, `ContentEnd`, `AuthFormBefore` and `AuthFormAfter`. Only the two auth ones run for guests.

**Translations.** All text comes from language files. Run `php artisan vendor:publish --tag=xelqun-panels-lang` to change it or add a language. In `shell.php` each key is the English text.

## Testing

`InteractsWithPanels` adds `actingInPanel($user, 'admin')`, which signs in on the panel's guard, and `enterPanel('admin', $tenant)`, for building a table or form to test. Forms' and tables' own test helpers work the same way on panel pages.

## Current limits

- `%` and `_` in a search act as wildcards.
- `discoverResources()` walks its folder on every request.
- Export ignores table filters, and only follows the tab.
- Signing out of one panel signs out of the others in that session.
- A queued verification email gets Laravel's route, not the panel's.
- Changing a password ends "remember me" on other devices, but not their open sessions. Add Laravel's `AuthenticateSession` middleware for that.
- SSR isn't tested.

See [how Xelqun compares with Filament](docs/parity.md), page by page.

## Developing

To test against unreleased sibling changes, make a gitignored `composer.local.json` with `path` repositories for `../forms` and `../tables`, then run `COMPOSER=composer.local.json composer install`.

## Inspiration

Xelqun Panels is heavily inspired by [Filament](https://filamentphp.com), bringing its PHP-first way of building admin panels to Inertia and Vue.

## License

MIT
