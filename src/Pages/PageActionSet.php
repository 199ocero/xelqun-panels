<?php

namespace Xelqun\Panels\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Actions\ActionSet;
use Xelqun\Panels\Resources\Pages\ResourcePage;
use Xelqun\Panels\Resources\Resource;

/**
 * A page's header actions need a form to live in, the same way a table's do. The
 * token names the page, and every click rebuilds it and checks it again.
 *
 * @internal
 */
final class PageActionSet extends ActionSet
{
    private ?Page $page = null;

    public static function for(Page $page): self
    {
        return $page instanceof ResourcePage
            ? self::make($page->getRecord(), ['page' => $page::class, 'resource' => $page->getResource()])
            : self::make(null, ['page' => $page::class]);
    }

    public function actions(): array
    {
        return $this->getPage()->headerActions();
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $this->getPage()->isAccessible();
    }

    public function model(): ?string
    {
        $resource = $this->getResource();

        return $resource === null ? null : $resource::getModel();
    }

    /**
     * Forms' own lookup would ignore the resource's scope.
     */
    public function resolveRecord(int|string $key): Model
    {
        $resource = $this->getResource() ?? throw new LogicException('Only a resource\'s page has a record.');

        return $resource::findRecord($key);
    }

    public function getId(): string
    {
        return str_replace('.', '-', $this->getPage()::class).'-actions';
    }

    public function getUploadScope(): string
    {
        return parent::getUploadScope().'>'.$this->getPage()::class.'>'.$this->getResource();
    }

    private function getPage(): Page
    {
        if ($this->page !== null) {
            return $this->page;
        }

        $class = $this->context['page'] ?? null;

        if (! is_string($class) || ! is_subclass_of($class, Page::class)) {
            throw new LogicException('A page\'s actions need the page class.');
        }

        $page = app($class);

        if ($page instanceof ResourcePage) {
            $page->forResource($this->getResource() ?? '', $this->record);
        }

        return $this->page = $page;
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>|null
     */
    private function getResource(): ?string
    {
        $resource = $this->context['resource'] ?? null;

        return is_string($resource) && is_subclass_of($resource, Resource::class) ? $resource : null;
    }
}
