<?php

namespace Xelqun\Panels\Tests\Fixtures\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionSet;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Tests\Fixtures\User;
use Xelqun\Tables\Columns\TextColumn;
use Xelqun\Tables\Table;

class OpenUsersTable extends Table
{
    public function query(): Builder
    {
        return User::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name')];
    }
}

class DeniedUsersTable extends OpenUsersTable
{
    public function authorize(?Authenticatable $user): bool
    {
        return false;
    }
}

class DeniedActions extends ActionSet
{
    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return false;
    }

    public function actions(): array
    {
        return [Action::make('secret')->authorize(true)];
    }
}

class PartlyDenied extends Page
{
    public function content(): array
    {
        return [DeniedUsersTable::make(), DeniedActions::make(), OpenUsersTable::make()];
    }
}
