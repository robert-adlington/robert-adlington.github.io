<?php
/**
 * Settings and Layouts API Endpoints
 * Handles user settings and saved layouts
 */

/**
 * Handle settings API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleSettingsRequest($method, $segments, $requestBody, $userId) {
    switch ($method) {
        case 'GET':
            getSettings($userId);
            break;

        case 'PUT':
            updateSettings($requestBody, $userId);
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Handle layouts API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleLayoutsRequest($method, $segments, $requestBody, $userId) {
    $layoutId = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    // Special case for setting current layout
    if ($action === 'current' && $method === 'PUT') {
        setCurrentLayout($requestBody, $userId);
        return;
    }

    switch ($method) {
        case 'GET':
            getLayouts($userId);
            break;

        case 'POST':
            createLayout($requestBody, $userId);
            break;

        case 'PUT':
            if ($layoutId) {
                updateLayout($layoutId, $requestBody, $userId);
            } else {
                jsonError('Layout ID required for update', 400);
            }
            break;

        case 'DELETE':
            if ($layoutId) {
                deleteLayout($layoutId, $userId);
            } else {
                jsonError('Layout ID required for delete', 400);
            }
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Get user settings
 */
function getSettings($userId) {
    $db = getDB();

    $query = "SELECT * FROM user_settings WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);

    $settings = $stmt->fetch();

    // Create default settings if not exists
    if (!$settings) {
        $settings = [
            'user_id' => $userId,
            'link_open_behavior' => 'new_tab',
            'default_display_mode' => 'collapsible_tile',
            'default_sort' => 'manual',
            'keyboard_shortcuts' => null
        ];
    }

    jsonSuccess($settings);
}

/**
 * Update user settings
 */
function updateSettings($data, $userId) {
    // TODO: Implement with validation
    jsonError('Not implemented yet', 501);
}

/**
 * Get all saved layouts
 */
function getLayouts($userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Create a new saved layout
 */
function createLayout($data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Update a saved layout
 */
function updateLayout($layoutId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Delete a saved layout
 */
function deleteLayout($layoutId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Set the current active layout
 */
function setCurrentLayout($data, $userId) {
    // TODO: Implement (set is_current flag)
    jsonError('Not implemented yet', 501);
}
