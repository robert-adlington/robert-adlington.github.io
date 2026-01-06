<?php
/**
 * Authentication Helper
 * Validates session using existing adlington.fr authentication system
 */

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/response.php';

/**
 * Start session if not already started
 */
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated() {
    ensureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    ensureSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require authentication or send 401 response
 * @return int User ID
 */
function requireAuth() {
    if (!isAuthenticated()) {
        jsonUnauthorized('Authentication required');
    }
    return getCurrentUserId();
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
