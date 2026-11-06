# Xelqun Panels

Describe an admin panel in PHP and get navigation, authentication, CRUD pages and dashboards, rendered in Vue, in light and dark themes.

> **Pre-alpha:** the API will change. Requires Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, [`xelqun/forms`](https://github.com/199ocero/xelqun-forms) and [`xelqun/tables`](https://github.com/199ocero/xelqun-tables).

## Installation

```bash
composer require xelqun/panels
php artisan xelqun-panels:install
php artisan make:xelqun-panel Admin
```

The installer adds the npm package, two small page files in `resources/js/pages/xelqun`, the styles, and `panelsPlugin()` in your Inertia entry file.

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

`php artisan make:xelqun-resource Product` creates this and a policy.

**Access goes through the model's policy:** `viewAny`, `view`, `create`, `update` and `delete`. With no policy, or no such method, the answer is no. This differs from Filament, which allows by default.

`getEloquentQuery()` is the security boundary. A record it leaves out is a 404 on every page, table action and form request.

Override `filters()`, `recordActions()`, `bulkActions()`, `configureTable()` and the hooks `mutateBeforeSave()`, `beforeCreate()`, `afterCreate()`, `beforeSave()` and `afterSave()`. To change a page, extend `ListRecords`, `CreateRecord`, `EditRecord` or `ViewRecord` and return it from `getPages()`.

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
| `Widget`        | Your own, with a Vue component registered in `createXelqunForms({ components })`.                                                                                              |

Set `$columnSpan` (out of 12), `$sort` and `$heading`, and override `canView()` to hide one.

## Theming

`colors(['primary' => '#0071e3'])` sets the accent, and the dark theme derives its own from it. `font('DM Sans')` picks a family from Bunny Fonts, loaded after the page so a slow font host can't hold it back. Everything else is a `--xq-*` token, set under `:root[data-xq-panel]`.

Icons in PHP are names. The panels plugin adds common sidebar icons to the forms set, and `createXelqunForms({ icons })` adds your own.

## Current limits

- Global search only finds pages. Searching records comes next, along with relation managers, tenancy and two-factor auth.
- Signing out of one panel signs out of the others in that session.
- A queued verification email gets Laravel's route, not the panel's.
- Changing a password ends "remember me" on other devices, but not their open sessions. Add Laravel's `AuthenticateSession` middleware for that.
- Text in the shell, such as "Search" and "Sign out", is English only for now.
- Charts are a sparkline on a stat. SSR isn't tested.

## Developing

To test against unreleased sibling changes, make a gitignored `composer.local.json` with `path` repositories for `../forms` and `../tables`, then run `COMPOSER=composer.local.json composer install`.

## Inspiration

Xelqun Panels is heavily inspired by [Filament](https://filamentphp.com), bringing its PHP-first way of building admin panels to Inertia and Vue.

## License

MIT
