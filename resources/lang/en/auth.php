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

    'failed' => 'These credentials do not match our records.',
    'throttled' => 'Too many attempts. Try again in :seconds seconds.',

    'cannot_verify' => '[:model] must implement Illuminate\Contracts\Auth\MustVerifyEmail for a panel that uses emailVerification().',
    'not_a_panel_user' => '[:model] must implement Xelqun\Panels\Contracts\PanelUser before it can enter a panel.',

];
