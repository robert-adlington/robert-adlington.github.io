<?php
/**
 * Smart Categories API Endpoints
 * Handles tag-based smart categories
 */

/**
 * Handle smart categories API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleSmartCategoriesRequest($method, $segments, $requestBody, $userId) {
    $smartCategoryId = $segments[1] ?? null;

    // Special case for inbox
    if ($smartCategoryId === 'inbox' && $method === 'GET') {
        getInbox($userId);
        return;
    }

    switch ($method) {
        case 'GET':
            if ($smartCategoryId) {
                getSmartCategoryById($smartCategoryId, $userId);
            } else {
                getSmartCategories($userId);
            }
            break;

        case 'POST':
            createSmartCategory($requestBody, $userId);
            break;

        case 'PUT':
            if ($smartCategoryId) {
                updateSmartCategory($smartCategoryId, $requestBody, $userId);
            } else {
                jsonError('Smart category ID required for update', 400);
            }
            break;

        case 'DELETE':
            if ($smartCategoryId) {
                deleteSmartCategory($smartCategoryId, $userId);
            } else {
                jsonError('Smart category ID required for delete', 400);
            }
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Get all smart categories
 */
function getSmartCategories($userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Get a smart category with matching links
 */
function getSmartCategoryById($smartCategoryId, $userId) {
    // TODO: Implement query logic (ALL vs ANY tags)
    jsonError('Not implemented yet', 501);
}

/**
 * Get inbox (links with no categories)
 */
function getInbox($userId) {
    $db = getDB();

    $query = "SELECT l.*
              FROM links l
              LEFT JOIN link_categories lc ON l.id = lc.link_id
              WHERE l.user_id = :user_id
                AND lc.link_id IS NULL
              ORDER BY l.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);

    $links = $stmt->fetchAll();

    jsonSuccess([
        'id' => 'inbox',
        'name' => 'Inbox',
        'is_system' => true,
        'links' => $links
    ]);
}

/**
 * Create a smart category
 */
function createSmartCategory($data, $userId) {
    // TODO: Implement with tag query validation
    jsonError('Not implemented yet', 501);
}

/**
 * Update a smart category
 */
function updateSmartCategory($smartCategoryId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Delete a smart category (except system categories)
 */
function deleteSmartCategory($smartCategoryId, $userId) {
    // TODO: Implement with system category check
    jsonError('Not implemented yet', 501);
}
