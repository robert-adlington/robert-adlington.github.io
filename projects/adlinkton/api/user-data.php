<?php
/**
 * User Data Management API Endpoints
 * Handles bulk operations on user data
 */

/**
 * Handle user data API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleUserDataRequest($method, $segments, $requestBody, $userId) {
    $action = $segments[1] ?? null;

    if ($method !== 'DELETE') {
        jsonError('Method not allowed', 405);
        return;
    }

    switch ($action) {
        case 'all':
            deleteAllUserData($userId);
            break;

        default:
            jsonError('Unknown action', 400);
    }
}

/**
 * Delete all links and categories for a user
 */
function deleteAllUserData($userId) {
    $db = getDB();

    try {
        $db->beginTransaction();

        // Delete all links
        $stmt = $db->prepare("DELETE FROM links WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $linksDeleted = $stmt->rowCount();

        // Delete all categories
        $stmt = $db->prepare("DELETE FROM categories WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $categoriesDeleted = $stmt->rowCount();

        // Delete all tags
        $stmt = $db->prepare("DELETE FROM tags WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $tagsDeleted = $stmt->rowCount();

        $db->commit();

        jsonSuccess([
            'message' => 'All user data deleted successfully',
            'deleted' => [
                'links' => $linksDeleted,
                'categories' => $categoriesDeleted,
                'tags' => $tagsDeleted
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Delete all failed: " . $e->getMessage());
        jsonError('Failed to delete data: ' . $e->getMessage(), 500);
    }
}
