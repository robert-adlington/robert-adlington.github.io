<?php
/**
 * Tags API Endpoints
 * Handles CRUD operations for tags
 */

/**
 * Handle tags API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleTagsRequest($method, $segments, $requestBody, $userId) {
    $tagId = $segments[1] ?? null;

    switch ($method) {
        case 'GET':
            getTags($userId);
            break;

        case 'POST':
            createTag($requestBody, $userId);
            break;

        case 'PUT':
            if ($tagId) {
                updateTag($tagId, $requestBody, $userId);
            } else {
                jsonError('Tag ID required for update', 400);
            }
            break;

        case 'DELETE':
            if ($tagId) {
                deleteTag($tagId, $userId);
            } else {
                jsonError('Tag ID required for delete', 400);
            }
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Get all tags for the user
 */
function getTags($userId) {
    $db = getDB();

    $query = "SELECT * FROM tags WHERE user_id = :user_id ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);

    $tags = $stmt->fetchAll();

    jsonSuccess(['tags' => $tags]);
}

/**
 * Create a new tag
 */
function createTag($data, $userId) {
    // TODO: Implement with validation
    jsonError('Not implemented yet', 501);
}

/**
 * Update a tag (rename)
 */
function updateTag($tagId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Delete a tag
 */
function deleteTag($tagId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}
