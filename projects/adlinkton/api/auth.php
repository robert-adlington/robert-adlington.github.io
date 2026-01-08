<?php
/**
 * Authentication Helper
 * Validates session using existing adlington.fr authentication system
 */

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/response.php';

/**
 * Get user from session token cookie
 * @return array|null User data or null if not authenticated
 */
function getUserFromToken() {
    $token = $_COOKIE['session_token'] ?? null;

    // DEBUG: Log cookie presence
    error_log("Adlinkton Auth - Cookie present: " . ($token ? "YES" : "NO"));
    if ($token) {
        error_log("Adlinkton Auth - Token: " . substr($token, 0, 10) . "...");
    }

    if (!$token) {
        error_log("Adlinkton Auth - No session_token cookie found");
        return null;
    }

    try {
        $pdo = getDB();

        // Look up session token in sessions table (token is stored in 'id' column)
        $stmt = $pdo->prepare("
            SELECT s.user_id, u.id, u.username, u.email, u.is_admin
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        // DEBUG: Log query result
        error_log("Adlinkton Auth - User found: " . ($user ? "YES (user_id: {$user['user_id']})" : "NO"));

        return $user ?: null;
    } catch (Exception $e) {
        error_log("Adlinkton Auth - Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated() {
    return getUserFromToken() !== null;
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    $user = getUserFromToken();
    return $user ? (int)$user['user_id'] : null;
}

/**
 * Require authentication or send 401 response
 * @return int User ID
 */
function requireAuth() {
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonUnauthorized('Authentication required');
    }
    return $userId;
}

/**
 * Verify that a resource belongs to the current user
 * @param int $resourceUserId User ID from the resource
 * @param int|null $currentUserId Current user ID (optional, will fetch if not provided)
 * @return bool
 */
function verifyOwnership($resourceUserId, $currentUserId = null) {
    if ($currentUserId === null) {
        $currentUserId = getCurrentUserId();
    }
    return (int)$resourceUserId === (int)$currentUserId;
}

/**
 * Require ownership or send 403 response
 * @param int $resourceUserId User ID from the resource
 * @param int|null $currentUserId Current user ID (optional)
 */
function requireOwnership($resourceUserId, $currentUserId = null) {
    if ($currentUserId === null) {
        $currentUserId = getCurrentUserId();
    }
    if (!verifyOwnership($resourceUserId, $currentUserId)) {
        jsonForbidden('You do not have access to this resource');
    }
}
