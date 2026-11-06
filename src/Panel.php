<?php

namespace Xelqun\Panels;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;
use Xelqun\Panels\Auth\EditProfileForm;
use Xelqun\Panels\Auth\LoginForm;
use Xelqun\Panels\Auth\RegisterForm;
use Xelqun\Panels\Auth\RequestPasswordResetForm;
use Xelqun\Panels\Auth\ResetPasswordForm;
use Xelqun\Panels\Contracts\HasAppAuthentication;
use Xelqun\Panels\Contracts\HasTenants;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Contracts\Plugin;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Navigation\SidebarCard;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Support\Colors\Color;
use Xelqun\Panels\View\RenderHook;
use Xelqun\Panels\Widgets\PanelWidget;

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

    /** Filament's semantic defaults. colors() changes only the ones it names. */
    private const array DEFAULT_COLORS = [
        'primary' => '#0071e3',
        'danger' => Color::Red,
        'gray' => Color::Zinc,
        'info' => Color::Blue,
        'success' => Color::Green,
        'warning' => Color::Amber,
    ];

    private string $id = '';

    private string $path = '';

    private ?string $brandName = null;

    private string $authGuard;

    /** @var class-string<LoginForm>|null */
    private ?string $loginForm = null;

    /** @var class-string<RequestPasswordResetForm>|null */
    private ?string $passwordResetRequestForm = null;

    /** @var class-string<ResetPasswordForm>|null */
    private ?string $passwordResetForm = null;

    private ?string $passwordBroker = null;

    /** @var class-string<RegisterForm>|null */
    private ?string $registrationForm = null;

    private bool $requiresEmailVerification = false;

    /** @var class-string<EditProfileForm>|null */
    private ?string $profileForm = null;

    /** @var array<string, mixed>|Closure(): array<string, mixed> */
    private array|Closure $colors = [];

    private string $font = 'Inter';

    /** @var array<int, class-string<Page>> */
    private array $pages = [];

    /** @var array<int, class-string<Resources\Resource>> */
    private array $resources = [];

    /** @var array<int, class-string<PanelWidget>> */
    private array $widgets = [];

    /** @var array<int, string> */
    private array $navigationGroups = [];

    /** @var array<int, NavigationItem> */
    private array $navigationItems = [];

    /** @var class-string<Model>|null */
    private ?string $tenantModel = null;

    private ?string $tenantSlugAttribute = null;

    private ?string $tenantOwnershipRelationship = null;

    private bool $hasMultiFactorAuthentication = false;

    private bool $requiresMultiFactorAuthentication = false;

    private bool $hasTopNavigation = false;

    /** @var array<string, Plugin> */
    private array $plugins = [];

    /** @var array<string, array<int, array{component: string, props: array<string, mixed>|Closure}>> */
    private array $renderHooks = [];

    private bool $hasDatabaseNotifications = false;

    private ?int $notificationPollingSeconds = 30;

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
     * The panel's colors, by name: primary, danger, gray, info, success and
     * warning. Each is a Color or a hex value, and any left out keep their
     * default. Text stays readable in both themes, whichever you pick. A closure
     * runs on every request, so a color can come from a setting.
     *
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $colors  Each a Color or a hex value, checked by getColorVariables().
     */
    public function colors(array|Closure $colors): self
    {
        $this->colors = $colors;

        if (is_array($colors)) {
            $this->getColorVariables();
        }

        return $this;
    }

    /**
     * What the colors become on <html>. They end up in a style attribute, which is
     * why nothing but a color is accepted, however they arrived.
     *
     * @return array<string, string>
     */
    public function getColorVariables(): array
    {
        $variables = [];

        $colors = $this->colors instanceof Closure ? ($this->colors)() : $this->colors;

        foreach ([...self::DEFAULT_COLORS, ...$colors] as $name => $color) {
            $hex = $color instanceof Color ? $color->value : $color;

            if (! isset(self::DEFAULT_COLORS[$name]) || ! is_string($hex) || preg_match(self::HEX_COLOR, $hex) !== 1) {
                throw new PanelDefinitionException('colors() takes primary, danger, gray, info, success and warning, each a Color or a hex value.');
            }

            $variables["--xq-panel-{$name}"] = $hex;

            if ($name === 'primary') {
                $variables['--xq-panel-primary-foreground'] = Color::foregroundOn($hex);
            }
        }

        return $variables;
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
     * Give this panel a login page. Without one, a guest is refused.
     *
     * @param  class-string<LoginForm>  $form
     */
    public function login(string $form = LoginForm::class): self
    {
        $this->loginForm = $form;

        return $this;
    }

    /**
     * @return class-string<LoginForm>|null
     */
    public function getLoginForm(): ?string
    {
        return $this->loginForm;
    }

    /**
     * Let users set a new password from an emailed link.
     *
     * @param  class-string<RequestPasswordResetForm>  $requestForm
     * @param  class-string<ResetPasswordForm>  $resetForm
     */
    public function passwordReset(string $requestForm = RequestPasswordResetForm::class, string $resetForm = ResetPasswordForm::class): self
    {
        $this->passwordResetRequestForm = $requestForm;
        $this->passwordResetForm = $resetForm;

        return $this;
    }

    /**
     * @return class-string<RequestPasswordResetForm>|null
     */
    public function getPasswordResetRequestForm(): ?string
    {
        return $this->passwordResetRequestForm;
    }

    /**
     * @return class-string<ResetPasswordForm>|null
     */
    public function getPasswordResetForm(): ?string
    {
        return $this->passwordResetForm;
    }

    /**
     * Which of config/auth.php's password brokers to use. Null is the default one.
     */
    public function authPasswordBroker(?string $broker): self
    {
        $this->passwordBroker = $broker;

        return $this;
    }

    public function getPasswordBroker(): ?string
    {
        return $this->passwordBroker;
    }

    /**
     * Let anyone create an account. A new user still has to pass canAccessPanel().
     *
     * @param  class-string<RegisterForm>  $form
     */
    public function registration(string $form = RegisterForm::class): self
    {
        $this->registrationForm = $form;

        return $this;
    }

    /**
     * @return class-string<RegisterForm>|null
     */
    public function getRegistrationForm(): ?string
    {
        return $this->registrationForm;
    }

    /**
     * Require a verified email address. The user model must implement
     * MustVerifyEmail, or nobody can get in.
     */
    public function emailVerification(): self
    {
        $this->requiresEmailVerification = true;

        return $this;
    }

    public function requiresEmailVerification(): bool
    {
        return $this->requiresEmailVerification;
    }

    /**
     * Whether this user is past email verification, if this panel asks for it.
     */
    public function hasVerifiedEmail(?Authenticatable $user): bool
    {
        return ! $this->requiresEmailVerification || ($user instanceof MustVerifyEmail && $user->hasVerifiedEmail());
    }

    /**
     * Split this panel between tenants, such as teams, under /admin/{tenant}. The
     * user model needs HasTenants. A resource's records must belong to a tenant
     * through $ownershipRelationship, which by default is named after the model.
     *
     * @param  class-string<Model>  $model
     * @param  string|null  $slugAttribute  What identifies a tenant in the URL. By default, its route key.
     */
    public function tenant(string $model, ?string $slugAttribute = null, ?string $ownershipRelationship = null): self
    {
        $this->tenantModel = $model;
        $this->tenantSlugAttribute = $slugAttribute;
        $this->tenantOwnershipRelationship = $ownershipRelationship;

        return $this;
    }

    public function hasTenancy(): bool
    {
        return $this->tenantModel !== null;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getTenantModel(): ?string
    {
        return $this->tenantModel;
    }

    public function getTenantSlugAttribute(): string
    {
        return $this->tenantSlugAttribute ?? (new $this->tenantModel)->getRouteKeyName();
    }

    public function getTenantOwnershipRelationship(): string
    {
        return $this->tenantOwnershipRelationship ?? Str::camel(class_basename($this->tenantModel));
    }

    /**
     * By what the URL says. A word can't be looked up in a number column on every database.
     */
    public function findTenant(string $slug): ?Model
    {
        $tenant = new $this->tenantModel;
        $attribute = $this->getTenantSlugAttribute();

        if ($attribute === $tenant->getKeyName() && $tenant->getKeyType() === 'int' && ! ctype_digit($slug)) {
            return null;
        }

        return $tenant->newQuery()->where($attribute, $slug)->first();
    }

    public function canAccessTenant(?Authenticatable $user, Model $tenant): bool
    {
        return $user instanceof HasTenants && $user->canAccessTenant($tenant);
    }

    /**
     * @return Collection<int, Model>
     */
    public function getTenantsFor(?Authenticatable $user): Collection
    {
        return $user instanceof HasTenants ? $user->getTenants($this) : collect();
    }

    public function getTenantUrl(Model $tenant): string
    {
        return url($this->path.'/'.$tenant->getAttribute($this->getTenantSlugAttribute()));
    }

    /**
     * Let users protect their account with an authenticator app, from the profile
     * page. With $isRequired, nobody gets past that page until they have. The user
     * model needs HasAppAuthentication.
     */
    public function multiFactorAuthentication(bool $isRequired = false): self
    {
        $this->hasMultiFactorAuthentication = true;
        $this->requiresMultiFactorAuthentication = $isRequired;

        return $this;
    }

    public function hasMultiFactorAuthentication(): bool
    {
        return $this->hasMultiFactorAuthentication;
    }

    /**
     * Put the navigation across the top, in place of the sidebar. Phones keep the drawer.
     */
    public function topNavigation(bool $condition = true): self
    {
        $this->hasTopNavigation = $condition;

        return $this;
    }

    public function plugin(Plugin $plugin): self
    {
        if (isset($this->plugins[$plugin->getId()])) {
            throw new PanelDefinitionException("The plugin [{$plugin->getId()}] is on this panel twice.");
        }

        $this->plugins[$plugin->getId()] = $plugin;
        $plugin->register($this);

        return $this;
    }

    /**
     * @return array<string, Plugin>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Show a Vue component at a place in the shell. Register it in
     * createXelqunForms({ components }). Props reach the browser, and hooks outside
     * the auth pages only render for someone inside the panel.
     *
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $props
     */
    public function renderHook(RenderHook $hook, string $component, array|Closure $props = []): self
    {
        $this->renderHooks[$hook->value][] = ['component' => $component, 'props' => $props];

        return $this;
    }

    /**
     * @return array<string, array<int, array{component: string, props: array<string, mixed>}>>
     */
    private function getRenderHooks(bool $isInside): array
    {
        $hooks = [];

        foreach ($this->renderHooks as $name => $items) {
            if ($isInside || RenderHook::from($name)->isPublic()) {
                $hooks[$name] = array_map(fn (array $item): array => [
                    'component' => $item['component'],
                    'props' => $item['props'] instanceof Closure ? ($item['props'])() : $item['props'],
                ], $items);
            }
        }

        return $hooks;
    }

    /**
     * Whether a password alone isn't enough for this user here.
     */
    public function needsChallenge(?Authenticatable $user): bool
    {
        return $this->hasMultiFactorAuthentication && $user instanceof HasAppAuthentication && $user->getAppAuthenticationSecret() !== null;
    }

    /**
     * Whether this user has to set up an authenticator before going any further.
     */
    public function mustSetUpMultiFactorAuthentication(?Authenticatable $user): bool
    {
        return $this->requiresMultiFactorAuthentication && ! $this->needsChallenge($user);
    }

    /**
     * A bell in the top bar. It needs Laravel's notifications table, and Notifiable
     * on the user model. Send to it with DatabaseNotification.
     */
    public function databaseNotifications(bool $condition = true): self
    {
        $this->hasDatabaseNotifications = $condition;

        return $this;
    }

    public function hasDatabaseNotifications(): bool
    {
        return $this->hasDatabaseNotifications;
    }

    /**
     * How often the bell checks, as in '30s', or null to check on page load only.
     */
    public function databaseNotificationsPolling(?string $interval): self
    {
        if ($interval !== null && preg_match('/^[1-9]\d*s$/', $interval) !== 1) {
            throw new PanelDefinitionException("[{$interval}] is not an interval. Use seconds, as in '30s'.");
        }

        $this->notificationPollingSeconds = $interval === null ? null : (int) $interval;

        return $this;
    }

    /**
     * Give users a page to change their own details.
     *
     * @param  class-string<EditProfileForm>  $form
     */
    public function profile(string $form = EditProfileForm::class): self
    {
        $this->profileForm = $form;

        return $this;
    }

    /**
     * @return class-string<EditProfileForm>|null
     */
    public function getProfileForm(): ?string
    {
        return $this->profileForm;
    }

    public function getLoginUrl(): ?string
    {
        return $this->loginForm === null ? null : route($this->routeName('auth.login'));
    }

    public function getLogoutUrl(): string
    {
        return route($this->routeName('auth.logout'));
    }

    /**
     * Whether this user may be in this panel. A user model that hasn't declared
     * it can't be.
     */
    public function allows(?Authenticatable $user): bool
    {
        return $user instanceof PanelUser && $user->canAccessPanel($this);
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
     * @param  array<int, class-string<Resources\Resource>>  $resources
     */
    public function resources(array $resources): self
    {
        $this->resources = [...$this->resources, ...$resources];

        return $this;
    }

    /**
     * Add every resource under a directory, so a new one needs no listing. A
     * directory that isn't there yet adds nothing.
     *
     * shortcut: this walks the directory on every request. Cache the list if an app has enough files to notice.
     */
    public function discoverResources(string $in, string $for): self
    {
        if (! is_dir($in)) {
            return $this;
        }

        return $this->resources(collect((new Filesystem)->allFiles($in))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): string => trim($for, '\\').'\\'.Str::beforeLast(strtr($file->getRelativePathname(), '/', '\\'), '.'))
            ->filter(fn (string $class): bool => is_subclass_of($class, Resources\Resource::class) && ! (new ReflectionClass($class))->isAbstract())
            ->values()
            ->all());
    }

    /**
     * @return array<int, class-string<Resources\Resource>>
     */
    public function getResources(): array
    {
        return array_values(array_unique($this->resources));
    }

    /**
     * What this user can start from the sidebar's New menu.
     *
     * @return array<int, array{label: string, icon: string|null, url: string}>
     */
    public function getQuickCreateItems(): array
    {
        return collect($this->getResources())
            ->filter(fn (string $resource): bool => $resource::hasPage('create') && $resource::can('create'))
            ->map(fn (string $resource): array => [
                'label' => Str::ucfirst($resource::getModelLabel()),
                'icon' => $resource::getNavigationIcon(),
                'url' => $resource::getUrl('create', panel: $this),
            ])
            ->values()
            ->all();
    }

    /**
     * What the Dashboard page shows.
     *
     * @param  array<int, class-string<PanelWidget>>  $widgets
     */
    public function widgets(array $widgets): self
    {
        $this->widgets = [...$this->widgets, ...$widgets];

        return $this;
    }

    /**
     * @return array<int, class-string<PanelWidget>>
     */
    public function getWidgets(): array
    {
        return $this->widgets;
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
            ->concat(collect($this->getResources())
                ->filter(fn (string $resource): bool => $resource::can('viewAny'))
                ->map(fn (string $resource): NavigationItem => $resource::getNavigationItem($this)))
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
     * because a whole model would send every attribute it doesn't hide. The login
     * page gets this too, so what's inside the panel stays out until they're in.
     *
     * @return array<string, mixed>
     */
    public function toShell(): array
    {
        $user = auth()->user();
        $tenant = app(Panels::class)->tenant();

        // Without its tenant a panel with tenants has no links to give, as on the email prompt.
        $isInside = $this->allows($user) && $this->hasVerifiedEmail($user) && (! $this->hasTenancy() || $tenant !== null);

        return [
            'id' => $this->id,
            'url' => $tenant === null ? $this->getUrl() : $this->getTenantUrl($tenant),
            'tenant' => $isInside && $tenant !== null ? [
                'name' => (string) $tenant->getAttribute('name'),
                'tenants' => $this->getTenantsFor($user)->map(fn (Model $option): array => [
                    'name' => (string) $option->getAttribute('name'),
                    'url' => $this->getTenantUrl($option),
                    'active' => $option->is($tenant),
                ])->values()->all(),
            ] : null,
            'brand' => ['name' => $this->getBrandName()],
            'theme' => ['colors' => (object) $this->getColorVariables(), 'font' => $this->font],
            'topNavigation' => $this->hasTopNavigation,
            'hooks' => (object) $this->getRenderHooks($isInside),
            'messages' => (object) __('xelqun-panels::shell'),
            'navigation' => $isInside ? $this->getNavigation() : [],
            'quickCreate' => $isInside ? $this->getQuickCreateItems() : [],
            'searchUrl' => $isInside ? route($this->routeName('search')) : null,
            'notifications' => $isInside && $this->hasDatabaseNotifications ? [
                'url' => route($this->routeName('notifications.index')),
                'pollSeconds' => $this->notificationPollingSeconds,
            ] : null,
            'sidebarFooter' => $isInside && $this->sidebarFooter !== null ? ($this->sidebarFooter)()?->toArray() : null,
            'user' => $isInside ? ['name' => (string) data_get($user, 'name'), 'email' => (string) data_get($user, 'email')] : null,
            'logoutUrl' => $isInside ? $this->getLogoutUrl() : null,
            'profileUrl' => $isInside && $this->profileForm !== null ? route($this->routeName('auth.profile')) : null,
        ];
    }
}
