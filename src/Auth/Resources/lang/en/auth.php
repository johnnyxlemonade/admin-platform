<?php

declare(strict_types=1);

return [
    'login' => [
        'page_title' => 'Sign in',
        'title' => 'Sign in',
        'description' => [
            'local_only' => 'Sign in with a local account.',
            'local_or_provider' => 'Continue with {provider} or sign in with a local account.',
            'local_or_sso' => 'Continue with single sign-on or sign in with a local account.',
        ],
        'email' => 'Email', 'password' => 'Password', 'remember' => 'Keep me signed in', 'forgot' => 'Forgot password?', 'submit' => 'Sign in', 'or' => 'or', 'sso' => 'Sign in with single sign-on', 'sso_provider' => 'Sign in with {provider}', 'back' => 'Back to website',
    ],
    'password' => ['show' => 'Show password', 'hide' => 'Hide password'],
    'forgot' => ['title' => 'Forgot password', 'description' => 'Enter the email address you use to sign in. We will send you a link to set a new password.', 'submit' => 'Send link', 'back' => 'Back to sign in', 'errors' => ['email' => 'Enter a valid email address.'], 'unavailable' => 'Password recovery is currently unavailable.'],
    'errors' => ['required' => 'Enter your email and password.', 'invalid' => 'The sign-in details are invalid.', 'disabled' => 'This account is disabled.', 'csrf' => 'The security token has expired. Please try again.'],
    'mfa' => ['title' => 'Two-factor authentication', 'challenge_description' => 'Enter the code from your authenticator app.', 'code' => 'Authentication code', 'verify' => 'Verify', 'enroll_title' => 'Set up two-factor authentication', 'enroll_description' => 'Start setup to create a new authenticator credential.', 'enroll_start' => 'Start setup', 'enroll_scan' => 'Add this secret to your authenticator app, then enter its code.', 'enroll_confirm' => 'Confirm setup', 'profile_title' => 'Two-factor authentication', 'active' => 'Two-factor authentication is active.', 'inactive' => 'Two-factor authentication is not active.', 'reauthenticate' => 'Confirm password', 'reenroll' => 'Replace authenticator', 'disable' => 'Disable two-factor authentication', 'errors' => ['invalid' => 'The authentication code is invalid or has already been used.']],
    'audit' => [
        'module' => ['name' => 'Authentication'],
        'events' => ['login' => 'Login', 'logout' => 'Logout', 'mfa_enrolled' => 'Two-factor authentication enrolled', 'mfa_disabled' => 'Two-factor authentication disabled', 'mfa_reset' => 'Two-factor authentication reset', 'mfa_challenge_failed' => 'Two-factor authentication challenge failed'],
    ],
    'locale' => ['label' => 'Interface language', 'czech' => 'Čeština', 'english' => 'English'],
    'brand' => ['subtitle' => 'Content Management System', 'poweredByLemonade' => 'Powered by Lemonade Framework'],
    'theme' => ['appearance' => 'Appearance', 'system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
    'common' => ['close' => 'Close'],
];
