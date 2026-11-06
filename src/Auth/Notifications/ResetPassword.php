<?php

namespace Xelqun\Panels\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

/**
 * Laravel's email with a link back to this panel. Sent directly, so the app's
 * own reset link and any override of it stay as they are.
 *
 * @internal
 */
final class ResetPassword extends BaseResetPassword
{
    public function __construct(string $token, private readonly string $url)
    {
        parent::__construct($token);
    }

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
