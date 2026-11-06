<?php

namespace Xelqun\Panels;

use Xelqun\Panels\Exceptions\PanelDefinitionException;
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

    private string $id = '';

    private string $path = '';

    private ?string $brandName = null;

    private string $authGuard;

    /** @var array<int, class-string<Page>> */
    private array $pages = [];

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
     * @return array{id: string, url: string, brand: array{name: string}, user: array{name: string, email: string}|null}
     */
    public function toShell(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->getUrl(),
            'brand' => ['name' => $this->getBrandName()],
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
