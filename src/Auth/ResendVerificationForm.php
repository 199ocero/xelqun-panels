<?php

namespace Xelqun\Panels\Auth;

use Xelqun\Forms\Form;

/**
 * A form with nothing to fill in, so the resend button works like every other
 * submit on these screens.
 *
 * @internal
 */
final class ResendVerificationForm extends Form
{
    public function schema(): array
    {
        return [];
    }
}
