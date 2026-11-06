<?php

return [

    'name' => 'Name',
    'email' => 'Email address',
    'password' => 'Password',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm password',
    'current_password' => 'Current password',
    'remember' => 'Remember me',

    'sign_out' => 'Sign out',
    'back_to_login' => 'Back to sign in',

    'login' => [
        'heading' => 'Sign in',
        'description' => 'Enter your details to continue.',
        'submit' => 'Sign in',
        'forgot' => 'Forgot your password?',
        'register' => 'Create an account',
    ],

    'forgot' => [
        'heading' => 'Reset your password',
        'description' => 'We will email you a link to choose a new one.',
        'submit' => 'Send the link',
        'sent' => 'If that address has an account, a link is on its way.',
    ],

    'reset' => [
        'heading' => 'Choose a new password',
        'submit' => 'Save the password',
        'done' => 'Your password is changed. Sign in with it.',
        'invalid' => 'This link is invalid or has expired, or the email address is wrong.',
    ],

    'register' => [
        'heading' => 'Create an account',
        'description' => 'It only takes a minute.',
        'submit' => 'Create the account',
        'have_account' => 'Already have an account?',
        'done' => 'Your account is created.',
    ],

    'verify' => [
        'heading' => 'Check your email',
        'description' => 'We sent a link to :email. Open it to continue.',
        'submit' => 'Send it again',
        'sent' => 'A new link is on its way.',
    ],

    'profile' => [
        'heading' => 'Profile',
        'details' => 'Your details',
        'password' => 'Change password',
        'submit' => 'Save changes',
        'saved' => 'Your profile is saved.',
    ],

    'mfa' => [
        'heading' => 'Two-factor authentication',
        'description' => 'Enter the 6 digit code from your authenticator app, or a recovery code.',
        'code' => 'Code',
        'submit' => 'Continue',
        'back' => 'Back to sign in',
        'invalid' => 'That code didn\'t work.',
        'required' => 'Set up two-factor authentication to continue.',
        'enable' => 'Set up two-factor',
        'enable_description' => 'Add this account to your authenticator app, then enter the code it shows.',
        'enable_submit' => 'Turn on',
        'open_app' => 'Open in your authenticator app',
        'key' => 'Or type this key: :key',
        'enabled' => 'Two-factor authentication is on.',
        'regenerate' => 'New recovery codes',
        'regenerate_description' => 'Your old recovery codes stop working.',
        'regenerated' => 'New recovery codes are ready.',
        'disable' => 'Turn off two-factor',
        'disable_description' => 'Your account will be protected by your password alone.',
        'disabled' => 'Two-factor authentication is off.',
        'codes_title' => 'Save your recovery codes',
        'codes_description' => 'Each one signs you in once if you lose your phone. You won\'t see them again.',
    ],

    'failed' => 'These credentials do not match our records.',
    'throttled' => 'Too many attempts. Try again in :seconds seconds.',

    'cannot_verify' => '[:model] must implement Illuminate\Contracts\Auth\MustVerifyEmail for a panel that uses emailVerification().',
    'not_a_panel_user' => '[:model] must implement Xelqun\Panels\Contracts\PanelUser before it can enter a panel.',

];
