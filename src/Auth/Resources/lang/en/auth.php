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
    'audit' => [
        'module' => ['name' => 'Authentication'],
        'events' => ['login' => 'Login', 'logout' => 'Logout'],
    ],
    'locale' => ['label' => 'Interface language', 'czech' => 'Čeština', 'english' => 'English'],
    'brand' => ['subtitle' => 'Content Management System', 'poweredByLemonade' => 'Powered by Lemonade Framework'],
    'theme' => ['appearance' => 'Appearance', 'system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
    'common' => ['close' => 'Close'],
];
