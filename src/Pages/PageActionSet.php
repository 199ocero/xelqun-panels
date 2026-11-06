<?php

namespace Xelqun\Panels\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Actions\ActionSet;

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
        return self::make(null, ['page' => $page::class]);
    }

    public function actions(): array
    {
        return $this->getPage()->headerActions();
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $this->getPage()::canAccess();
    }

    public function getId(): string
    {
        return $this->getPage()::getRouteName().'-actions';
    }

    public function getUploadScope(): string
    {
        return parent::getUploadScope().'>'.$this->getPage()::class;
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

        return $this->page = app($class);
    }
}
