<?php
/**
 * Common Utilities and Response Helpers
 */

require_once __DIR__ . '/config.php';

/**
 * Set CORS headers for API responses
 */
function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json; charset=utf-8");
}

/**
 * Handle preflight OPTIONS request
 */
function handlePreflight(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(204);
        exit;
    }
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    setCorsHeaders();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send success response
 */
function success(array $data = [], string $message = 'Success'): void {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Send error response
 */
function error(string $message, int $statusCode = 400, array $errors = []): void {
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    jsonResponse($response, $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): array {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error('Invalid JSON in request body', 400);
    }
    
    return $data ?? [];
}

/**
 * Validate required fields
 */
function validateRequired(array $data, array $fields): array {
    $errors = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[$field] = "$field is required";
        }
    }
    
    return $errors;
}

/**
 * Validate email format
 */
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a secure random token
 */
function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get request method
 */
function getMethod(): string {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Simple request router
 */
function route(array $routes): void {
    $method = getMethod();
    
    if (isset($routes[$method])) {
        $routes[$method]();
    } else {
        error('Method not allowed', 405);
    }
}
