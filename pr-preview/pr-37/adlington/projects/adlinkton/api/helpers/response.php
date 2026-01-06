<?php
/**
 * JSON Response Helper Functions
 */

/**
 * Send a JSON response
 * @param mixed $data Response data
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a success response
 * @param mixed $data Response data
 */
function jsonSuccess($data) {
    jsonResponse($data, 200);
}

/**
 * Send an error response
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Send a not found response
 * @param string $message Error message
 */
function jsonNotFound($message = 'Resource not found') {
    jsonError($message, 404);
}

/**
 * Send an unauthorized response
 * @param string $message Error message
 */
function jsonUnauthorized($message = 'Unauthorized') {
    jsonError($message, 401);
}

/**
 * Send a forbidden response
 * @param string $message Error message
 */
function jsonForbidden($message = 'Forbidden') {
    jsonError($message, 403);
}

/**
 * Send a validation error response
 * @param array $errors Array of validation errors
 */
function jsonValidationError($errors) {
    jsonResponse([
        'error' => 'Validation failed',
        'errors' => $errors
    ], 422);
}
