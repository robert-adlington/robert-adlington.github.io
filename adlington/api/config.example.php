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
