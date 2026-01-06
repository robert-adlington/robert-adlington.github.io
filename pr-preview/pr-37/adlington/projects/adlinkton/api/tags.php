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
    $db = getDB();

    // Validate required fields
    $missing = validateRequired($data, ['name']);
    if (!empty($missing)) {
        jsonValidationError(['missing_fields' => $missing]);
    }

    // Validate name length
    if (!validateLength($data['name'], 100, 1)) {
        jsonValidationError(['name' => 'Name must be between 1 and 100 characters']);
    }

    // Sanitize input
    $name = sanitizeHtml(trim($data['name']));

    try {
        // Check if tag already exists for this user
        $stmt = $db->prepare("SELECT id FROM tags WHERE user_id = :user_id AND name = :name");
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name
        ]);

        if ($stmt->fetch()) {
            jsonValidationError(['name' => 'Tag with this name already exists']);
        }

        // Insert tag
        $query = "INSERT INTO tags (user_id, name) VALUES (:user_id, :name)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name
        ]);

        $tagId = $db->lastInsertId();

        // Fetch and return the created tag
        $stmt = $db->prepare("SELECT * FROM tags WHERE id = :id");
        $stmt->execute([':id' => $tagId]);
        $tag = $stmt->fetch();

        jsonSuccess($tag);
    } catch (Exception $e) {
        error_log("Error creating tag: " . $e->getMessage());
        jsonError('Failed to create tag', 500);
    }
}

/**
 * Update a tag (rename)
 */
function updateTag($tagId, $data, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM tags WHERE id = :id");
    $stmt->execute([':id' => $tagId]);
    $existingTag = $stmt->fetch();

    if (!$existingTag) {
        jsonNotFound('Tag not found');
    }

    requireOwnership($existingTag['user_id'], $userId);

    // Validate name
    if (!isset($data['name'])) {
        jsonError('Name is required', 400);
    }

    if (!validateLength($data['name'], 100, 1)) {
        jsonValidationError(['name' => 'Name must be between 1 and 100 characters']);
    }

    $name = sanitizeHtml(trim($data['name']));

    try {
        // Check if another tag with this name already exists for this user
        $stmt = $db->prepare("SELECT id FROM tags WHERE user_id = :user_id AND name = :name AND id != :id");
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':id' => $tagId
        ]);

        if ($stmt->fetch()) {
            jsonValidationError(['name' => 'Tag with this name already exists']);
        }

        // Update tag
        $stmt = $db->prepare("UPDATE tags SET name = :name WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':name' => $name,
            ':id' => $tagId,
            ':user_id' => $userId
        ]);

        // Fetch and return the updated tag
        $stmt = $db->prepare("SELECT * FROM tags WHERE id = :id");
        $stmt->execute([':id' => $tagId]);
        $tag = $stmt->fetch();

        jsonSuccess($tag);
    } catch (Exception $e) {
        error_log("Error updating tag: " . $e->getMessage());
        jsonError('Failed to update tag', 500);
    }
}

/**
 * Delete a tag
 */
function deleteTag($tagId, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM tags WHERE id = :id");
    $stmt->execute([':id' => $tagId]);
    $existingTag = $stmt->fetch();

    if (!$existingTag) {
        jsonNotFound('Tag not found');
    }

    requireOwnership($existingTag['user_id'], $userId);

    try {
        // Delete tag (cascading will handle link_tags)
        $stmt = $db->prepare("DELETE FROM tags WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':id' => $tagId,
            ':user_id' => $userId
        ]);

        jsonSuccess(['message' => 'Tag deleted successfully']);
    } catch (Exception $e) {
        error_log("Error deleting tag: " . $e->getMessage());
        jsonError('Failed to delete tag', 500);
    }
}
