<?php
/**
 * Links API Endpoints
 * Handles CRUD operations for links
 */

/**
 * Handle links API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleLinksRequest($method, $segments, $requestBody, $userId) {
    $linkId = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    switch ($method) {
        case 'GET':
            if ($linkId) {
                getLinkById($linkId, $userId);
            } else {
                getLinks($userId);
            }
            break;

        case 'POST':
            if ($linkId && $action === 'open') {
                recordLinkAccess($linkId, $userId);
            } elseif ($action === 'bulk') {
                handleBulkLinkOperation($requestBody, $userId);
            } else {
                createLink($requestBody, $userId);
            }
            break;

        case 'PUT':
            if ($linkId && $action === 'reorder') {
                reorderLink($linkId, $requestBody, $userId);
            } elseif ($linkId) {
                updateLink($linkId, $requestBody, $userId);
            } else {
                jsonError('Link ID required for update', 400);
            }
            break;

        case 'DELETE':
            if ($linkId) {
                deleteLink($linkId, $userId);
            } else {
                jsonError('Link ID required for delete', 400);
            }
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Get all links with optional filters
 */
function getLinks($userId) {
    $db = getDB();

    // Parse query parameters
    $categoryId = $_GET['category_id'] ?? null;
    $smartCategoryId = $_GET['smart_category_id'] ?? null;
    $tagIds = $_GET['tag_ids'] ?? [];
    $favorite = $_GET['favorite'] ?? null;
    $search = $_GET['search'] ?? null;
    $sort = $_GET['sort'] ?? 'manual';
    $order = $_GET['order'] ?? 'asc';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // TODO: Implement filtering and sorting logic
    // For now, return a basic query

    $query = "SELECT * FROM links WHERE user_id = :user_id LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $links = $stmt->fetchAll();

    jsonSuccess([
        'links' => $links,
        'total' => count($links),
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Get a single link by ID
 */
function getLinkById($linkId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Create a new link
 */
function createLink($data, $userId) {
    // TODO: Implement validation and link creation
    jsonError('Not implemented yet', 501);
}

/**
 * Update an existing link
 */
function updateLink($linkId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Delete a link
 */
function deleteLink($linkId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Record link access for analytics
 */
function recordLinkAccess($linkId, $userId) {
    $db = getDB();

    $query = "UPDATE links
              SET access_count = access_count + 1,
                  last_accessed = NOW(),
                  first_accessed = COALESCE(first_accessed, NOW())
              WHERE id = :id AND user_id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $linkId,
        ':user_id' => $userId
    ]);

    if ($stmt->rowCount() > 0) {
        jsonSuccess(['message' => 'Link access recorded']);
    } else {
        jsonNotFound('Link not found');
    }
}

/**
 * Reorder a link within/between categories
 */
function reorderLink($linkId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Handle bulk operations on links
 */
function handleBulkLinkOperation($data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}
