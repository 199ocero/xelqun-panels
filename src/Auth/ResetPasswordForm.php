<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Validation\Rules\Password;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @api
 */
class ResetPasswordForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('email')
                ->label(__('xelqun-panels::auth.email'))
                ->email()
                ->required()
                ->autocomplete('username')
                ->default($this->context['email'] ?? null),
            TextInput::make('password')
                ->label(__('xelqun-panels::auth.new_password'))
                ->password()
                ->revealable()
                ->required()
                ->autocomplete('new-password')
                ->autofocus()
                ->rules([Password::defaults()]),
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
