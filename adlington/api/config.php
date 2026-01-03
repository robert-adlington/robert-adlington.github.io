<?php
/**
 * Configuration File for Adlington.fr Database
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'adlington_games');
define('DB_USER', 'adlington_user');
define('DB_PASS', 'change_this_password_in_production');

// Session Configuration
define('SESSION_DURATION', 60 * 60 * 24 * 30); // 30 days in seconds

// CORS Configuration
define('ALLOWED_ORIGINS', [
    'https://adlington.fr',
    'https://www.adlington.fr',
    'http://localhost:3000',
    'http://localhost:8000',
]);

// Debug Mode (set to false in production)
define('DEBUG_MODE', false);
