<?php
/**
 * Configuration File Template for Adlington.fr Database
 *
 * INSTRUCTIONS:
 * 1. Copy this file to config.php: cp config.example.php config.php
 * 2. Update the values below with your actual database credentials
 * 3. NEVER commit config.php to the repository!
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_secure_password');

// Session Configuration
define('SESSION_DURATION', 60 * 60 * 24 * 30); // 30 days in seconds

// CORS Configuration
// Add your actual domain(s) here
define('ALLOWED_ORIGINS', [
    'https://yourdomain.com',
    'https://www.yourdomain.com',
    'http://localhost:3000',  // Remove in production
    'http://localhost:8000',  // Remove in production
]);

// Debug Mode (set to false in production)
define('DEBUG_MODE', false);

// Site Configuration
define('SITE_URL', 'https://yourdomain.com'); // Your site URL (no trailing slash)
define('SITE_NAME', 'Adlington.fr');

// Email Configuration (for password resets)
// Using PHP mail() function (default)
define('EMAIL_METHOD', 'mail'); // 'mail' or 'smtp'

// SMTP Configuration (only needed if EMAIL_METHOD is 'smtp')
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587); // Usually 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('SMTP_USERNAME', 'your_email@example.com');
define('SMTP_PASSWORD', 'your_email_password');

// Email Settings
define('EMAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'Adlington.fr');

// Password Reset Configuration
define('RESET_TOKEN_EXPIRY', 60 * 60); // 1 hour in seconds
