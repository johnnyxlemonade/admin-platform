<?php

declare(strict_types=1);

$catalog = [
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
    'mfa' => ['title' => 'Two-factor authentication', 'challenge_description' => 'Enter the code from your authenticator app.', 'code' => 'Authentication code', 'code_placeholder' => 'Enter the 6-digit code from your app', 'verify' => 'Verify', 'enroll_title' => 'Set up two-factor authentication', 'enroll_description' => 'Start setup to create a new authenticator credential.', 'enroll_start' => 'Start setup', 'enroll_scan' => 'Scan the QR code in your authenticator app, then enter its code.', 'app_title' => 'Use an authenticator app', 'app_description' => 'Scan the QR code with Google Authenticator, Microsoft Authenticator, or another compatible app.', 'manual_title' => 'Cannot scan the code?', 'manual_description' => 'Enter the manual setup key below in your authenticator app.', 'manual_key' => 'Manual setup key', 'copy' => 'Copy', 'enroll_confirm' => 'Confirm setup', 'profile_title' => 'Two-factor authentication', 'active' => 'Two-factor authentication is active.', 'inactive' => 'Two-factor authentication is not active.', 'reauthenticate' => 'Confirm password', 'reenroll' => 'Replace authenticator', 'disable' => 'Disable two-factor authentication', 'enrolled' => 'Two-factor authentication was set up.', 'disabled' => 'Two-factor authentication was disabled.', 'errors' => ['invalid' => 'The authentication code is invalid or has already been used.', 'reauthenticate_required' => 'Confirm your password first.', 'disable_not_allowed' => 'Two-factor authentication cannot be disabled under this policy.']],
    'audit' => [
        'module' => ['name' => 'Authentication'],
        'events' => ['login' => 'Login', 'logout' => 'Logout', 'mfa_enrolled' => 'Two-factor authentication enrolled', 'mfa_disabled' => 'Two-factor authentication disabled', 'mfa_reset' => 'Two-factor authentication reset', 'mfa_challenge_failed' => 'Two-factor authentication challenge failed'],
    ],
    'locale' => ['label' => 'Interface language', 'czech' => 'Čeština', 'english' => 'English'],
    'brand' => ['subtitle' => 'Content Management System', 'poweredByLemonade' => 'Powered by Lemonade Framework'],
    'theme' => ['appearance' => 'Appearance', 'system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
    'common' => ['close' => 'Close'],
];

$catalog['mfa']['code_placeholder'] = 'Enter the 6-digit code from your app';
$catalog['mfa']['app_title'] = 'Use an authenticator app';
$catalog['mfa']['app_description'] = 'Scan the QR code with Google Authenticator, Microsoft Authenticator, or another compatible app.';
$catalog['mfa']['manual_title'] = 'Cannot scan the code?';
$catalog['mfa']['manual_description'] = 'Enter the manual setup key below in your authenticator app.';
$catalog['mfa']['copy'] = 'Copy';
$catalog['mfa']['reenroll_title'] = 'Set up two-factor authentication again';
$catalog['mfa']['reauth_description'] = 'For security, confirm your password before changing two-factor authentication.';
$catalog['mfa']['reauth_help'] = 'This confirms that it is really you. Your password is never stored here.';
$catalog['mfa']['active_description'] = 'Your account is protected by an authenticator app.';
$catalog['mfa']['success_title'] = 'Two-factor authentication was successfully set up';
$catalog['mfa']['success_description'] = 'From now on, sign-in also requires a code from your authenticator app.';

return $catalog;
