<?php
/**
 * Adlinkton API Router
 * Main entry point for all API requests
 */

// DEBUG: Log request details
error_log("Adlinkton API Request: " . $_SERVER['REQUEST_URI']);
error_log("Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set'));
error_log("Path Info: " . ($_SERVER['PATH_INFO'] ?? 'not set'));

// Enable CORS for development (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include dependencies
require_once __DIR__ . '/../../../api/auth.php'; // Shared auth system
require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/validation.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];

// Get endpoint from query parameter (e.g., ?endpoint=/links)
$relativePath = $_GET['endpoint'] ?? '';
$relativePath = trim($relativePath, '/');

// Parse path segments
$segments = array_filter(explode('/', $relativePath));
$segments = array_values($segments); // Re-index

// Get request body for POST/PUT requests
$requestBody = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $requestBody = json_decode(file_get_contents('php://input'), true);
}

// Router logic
try {
    // All endpoints require authentication
    $userId = requireAuth();

    // Route to appropriate handler
    if (empty($segments)) {
        jsonError('API endpoint not specified', 404);
    }

    $resource = $segments[0];

    switch ($resource) {
        case 'links':
            require_once __DIR__ . '/links.php';
            handleLinksRequest($method, $segments, $requestBody, $userId);
            break;

        case 'categories':
            require_once __DIR__ . '/categories.php';
            handleCategoriesRequest($method, $segments, $requestBody, $userId);
            break;

        case 'smart-categories':
            require_once __DIR__ . '/smart-categories.php';
            handleSmartCategoriesRequest($method, $segments, $requestBody, $userId);
            break;

        case 'tags':
            require_once __DIR__ . '/tags.php';
            handleTagsRequest($method, $segments, $requestBody, $userId);
            break;

        case 'settings':
            require_once __DIR__ . '/settings.php';
            handleSettingsRequest($method, $segments, $requestBody, $userId);
            break;

        case 'layouts':
            require_once __DIR__ . '/settings.php';
            handleLayoutsRequest($method, $segments, $requestBody, $userId);
            break;

        case 'import':
            require_once __DIR__ . '/import.php';
            handleImportRequest($method, $segments, $requestBody, $userId);
            break;

        default:
            jsonError('Unknown API endpoint', 404);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonError('An unexpected error occurred', 500);
}
