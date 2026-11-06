<?php

namespace Xelqun\Panels\Auth;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @api
 */
class RequestPasswordResetForm extends Form
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
        ];
    }
}
