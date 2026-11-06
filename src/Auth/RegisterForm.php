<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * What this form declares is all that reaches a new user, so keep anything that
 * grants access out of it.
 *
 * @api
 */
class RegisterForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('xelqun-panels::auth.name'))
                ->required()
                ->maxLength(255)
                ->autocomplete('name')
                ->autofocus(),
            TextInput::make('email')
                ->label(__('xelqun-panels::auth.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->autocomplete('username')
                ->rules([Rule::unique((string) ($this->context['table'] ?? 'users'), 'email')]),
            TextInput::make('password')
                ->label(__('xelqun-panels::auth.password'))
                ->password()
                ->revealable()
                ->required()
                ->autocomplete('new-password')
                ->rules([Password::defaults()])
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
            TextInput::make('password_confirmation')
                ->label(__('xelqun-panels::auth.confirm_password'))
                ->password()
                ->required()
                ->autocomplete('new-password')
                ->same('password')
                ->saved(false),
        ];
    }
}
