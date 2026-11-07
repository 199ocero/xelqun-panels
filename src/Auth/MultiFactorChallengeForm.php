<?php

namespace Xelqun\Panels\Auth;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @api
 */
class MultiFactorChallengeForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('code')
                ->label(__('xelqun-panels::auth.mfa.code'))
                ->hiddenLabel()
                ->placeholder(__('xelqun-panels::auth.mfa.code'))
                ->required()
                ->maxLength(32)
                ->autocomplete('one-time-code')
                ->autofocus(),
        ];
    }
}
