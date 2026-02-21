<?php
declare(strict_types=1);

/**
 * XcaliburMoon Web Development Pricing - Settings
 *
 * All sensitive values (passwords, API keys) must be stored OUTSIDE
 * the web root and document root. Reference them by absolute path.
 *
 * Example:
 *   define('SMTP_PASSWORD', file_get_contents('/home/user/private/smtp_pass.txt'));
 */

// ---------------------------------------------------------------
// Application
// ---------------------------------------------------------------
define('APP_NAME',    'XcaliburMoon Web Development Pricing');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // 'development' or 'production'

// ---------------------------------------------------------------
// Quote Reference ID
// ---------------------------------------------------------------
// Number of days a reference ID remains valid after generation.
define('REFERENCE_EXPIRY_DAYS', 14);

// ---------------------------------------------------------------
// Mail Delivery Mode
//
//   'ssl'    -- SMTP over SSL (port 465). Use this for Hostinger
//               and most shared hosts that require port 465.
//   'tls'    -- SMTP with STARTTLS (port 587). Common for Gmail,
//               Outlook, and other providers.
//   'smtp'   -- Alias for 'ssl' (kept for backward compatibility).
//   'server' -- Use PHP mail()/sendmail. Only works if the server
//               has a working MTA (postfix, sendmail). Will fail
//               silently on most shared hosts.
// ---------------------------------------------------------------
define('MAIL_MODE', 'ssl');

// ---------------------------------------------------------------
// SMTP Credentials
// Required when MAIL_MODE is 'ssl', 'tls', or 'smtp'.
// Store SMTP_PASSWORD outside the web root on production.
//
// Hostinger example:
//   SMTP_HOST     = 'smtp.hostinger.com'
//   SMTP_PORT     = 465
//   SMTP_USERNAME = 'you@yourdomain.com'
//   SMTP_PASSWORD = 'your-email-password'
//   MAIL_MODE     = 'ssl'
// ---------------------------------------------------------------
define('SMTP_HOST',     'smtp.hostinger.com');
define('SMTP_PORT',     465);
define('SMTP_USERNAME', '');
// define('SMTP_PASSWORD', file_get_contents('/path/outside/webroot/smtp_pass.txt'));
define('SMTP_PASSWORD', '');

// ---------------------------------------------------------------
// Email Addresses
// MAIL_FROM should match the domain in SMTP_USERNAME on most hosts.
// ---------------------------------------------------------------
define('MAIL_FROM',      'noreply@xcaliburmoon.net');
define('MAIL_FROM_NAME', 'XcaliburMoon Pricing');
define('MAIL_TO',        'crissy@xcaliburmoon.net');

// ---------------------------------------------------------------
// Submission Behavior
// Toggle what happens when a visitor submits the live form.
// ---------------------------------------------------------------

// Save every submission to a JSON file.
// true  = write to SUBMISSIONS_FILE on every submit
// false = do not store submissions on disk
define('SAVE_SUBMISSIONS', true);

// Send email on submission.
// true  = attempt to send email via MAIL_MODE
// false = skip email entirely (useful during development)
define('SEND_EMAILS', true);

// Send a confirmation copy to the visitor who filled out the form.
// Only applies when SEND_EMAILS is true and the visitor provided
// a valid email address in the contact step.
define('SEND_EMAIL_TO_USER', true);

// Send a notification to the business/developer (MAIL_TO).
// Only applies when SEND_EMAILS is true.
define('SEND_EMAIL_TO_ADMIN', true);

// ---------------------------------------------------------------
// Data Storage
// ---------------------------------------------------------------
// Path to JSON data directory. Must not be inside the web root.
// Default is relative to the project src directory.
define('DATA_STORE_PATH', __DIR__ . '/../data/');

// File where form submissions are appended.
// Will be created automatically on the first submission.
define('SUBMISSIONS_FILE', DATA_STORE_PATH . 'submissions.json');

// ---------------------------------------------------------------
// Error Reporting
// ---------------------------------------------------------------
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
