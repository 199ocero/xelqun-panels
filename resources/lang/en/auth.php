<?php

return [

    'name' => 'Name',
    'email' => 'Email address',
    'password' => 'Password',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm password',
    'current_password' => 'Current password',
    'remember' => 'Remember me',

    'quote' => 'Good tools get out of the way, and the work is all that\'s left.',

    'sign_out' => 'Sign out',
    'back_to_login' => 'Back to sign in',

    'login' => [
        'heading' => 'Sign in to :brand',
        'description' => 'Enter your details to continue.',
        'no_account' => 'Don\'t have an account?',
        'submit' => 'Sign in',
        'forgot' => 'Forgot your password?',
        'register' => 'Sign up',
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
        'sign_in' => 'Sign in',
        'done' => 'Your account is created. You can sign in once someone gives you access.',
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

    'email_change' => [
        'sent' => 'Open the link we sent to :email to finish changing your email address.',
        'verify_subject' => 'Confirm your new email address',
        'verify_line' => 'Open this link to make this your account\'s email address.',
        'verify_action' => 'Confirm the address',
        'verify_footer' => 'The link works for an hour. If you didn\'t ask for this, ignore this email.',
        'notice_subject' => 'Your email address is changing',
        'notice_line' => 'Someone asked to change your account\'s email address to :email.',
        'notice_footer' => 'If it wasn\'t you, sign in and change your password.',
        'done' => 'Your email address is changed.',
        'taken' => 'That email address is taken now, so nothing changed.',
    ],

    'mfa' => [
        'heading' => 'Two-factor authentication',
        'description' => 'Enter the 6 digit code from your authenticator app, or a recovery code.',
        'description_email' => 'Enter the 6 digit code we just emailed you. A code from an authenticator app or a recovery code works too.',
        'code' => 'Code',
        'submit' => 'Continue',
        'back' => 'Back to sign in',
        'resend' => 'Send a new code',
        'resent' => 'We emailed you a new code.',
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
        'email_subject' => 'Your sign in code',
        'email_line' => 'Your code is :code.',
        'email_footer' => 'It works for 5 minutes. If you didn\'t try to sign in, change your password.',
        'email_enable' => 'Send me codes by email',
        'email_disable' => 'Stop email codes',
        'email_enabled' => 'Email codes are on.',
        'email_disabled' => 'Email codes are off.',
        'codes_description' => 'Each one signs you in once if you lose your phone. You won\'t see them again.',
    ],

    'tenant' => [
        'register_heading' => 'Start a new team',
        'register_submit' => 'Create',
        'profile_heading' => 'Team settings',
    ],

    'failed' => 'These credentials do not match our records.',
    'throttled' => 'Too many attempts. Try again in :seconds seconds.',

    'cannot_verify' => '[:model] must implement Illuminate\Contracts\Auth\MustVerifyEmail for a panel that uses emailVerification().',
    'not_a_panel_user' => '[:model] must implement Xelqun\Panels\Contracts\PanelUser before it can enter a panel.',

];
