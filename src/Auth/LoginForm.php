<?php

namespace Xelqun\Panels\Auth;

use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * Extend this to change the login form, and pass the class to Panel::login().
 *
 * @api
 */
class LoginForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('email')
                ->label(__('xelqun-panels::auth.email'))
                ->email()
                ->required()
                ->autocomplete('username')
                ->autofocus(),
            TextInput::make('password')
                ->label(__('xelqun-panels::auth.password'))
                ->password()
                ->revealable()
                ->required()
                ->autocomplete('current-password'),
            Checkbox::make('remember')->label(__('xelqun-panels::auth.remember')),
        ];
    }
}
