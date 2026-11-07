<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Panels\Tests\Fixtures\Team;

class TeamForm extends Form
{
    public function model(): string
    {
        return Team::class;
    }

    public function schema(): array
    {
        return [TextInput::make('name')->required(), TextInput::make('slug')->required()];
    }

    public function afterSave(Model $record, array $data): void
    {
        if ($record->wasRecentlyCreated) {
            auth()->user()->teams()->attach($record);
        }
    }
}
