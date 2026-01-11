<?php
/**
 * Categories API Endpoints
 * Handles CRUD operations for categories
 */

/**
 * Handle categories API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleCategoriesRequest($method, $segments, $requestBody, $userId) {
    $categoryId = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    switch ($method) {
        case 'GET':
            if ($categoryId) {
                getCategoryById($categoryId, $userId);
            } else {
                getCategories($userId);
            }
            break;

        case 'POST':
            if ($categoryId && $action === 'open-all') {
                getCategoryUrls($categoryId, $userId);
            } else {
                createCategory($requestBody, $userId);
            }
            break;

        case 'PUT':
            if ($categoryId && $action === 'reorder') {
                reorderCategory($categoryId, $requestBody, $userId);
            } elseif ($categoryId) {
                updateCategory($categoryId, $requestBody, $userId);
            } else {
                jsonError('Category ID required for update', 400);
            }
            break;

        case 'DELETE':
            if ($categoryId) {
                deleteCategory($categoryId, $userId);
            } else {
                jsonError('Category ID required for delete', 400);
            }
            break;

        default:
            jsonError('Method not allowed', 405);
    }
}

/**
 * Get all categories as a tree
 */
function getCategories($userId) {
    $db = getDB();

    // Get all categories for the user
    $query = "SELECT * FROM categories WHERE user_id = :user_id ORDER BY sort_order, name";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $categories = $stmt->fetchAll();

    // Build tree structure
    $categoriesById = [];
    foreach ($categories as &$category) {
        $category['children'] = [];
        $category['link_count'] = getCategoryLinkCount($db, $category['id']);
        $categoriesById[$category['id']] = &$category;
    }

    // Build hierarchy
    $tree = [];
    foreach ($categoriesById as &$category) {
        if ($category['parent_id'] === null) {
            $tree[] = &$category;
        } else {
            if (isset($categoriesById[$category['parent_id']])) {
                $categoriesById[$category['parent_id']]['children'][] = &$category;
            }
        }
    }

    // Calculate effective display mode for each category
    foreach ($tree as &$category) {
        calculateEffectiveDisplayMode($category);
    }

    jsonSuccess(['categories' => $tree]);
}

/**
 * Recursively calculate effective_display_mode for category tree
 */
function calculateEffectiveDisplayMode(&$category, $parentMode = 'collapsible_tile') {
    $category['effective_display_mode'] = $category['display_mode'] ?? $parentMode;

    foreach ($category['children'] as &$child) {
        calculateEffectiveDisplayMode($child, $category['effective_display_mode']);
    }
}

/**
 * Get a single category with its links
 */
function getCategoryById($categoryId, $userId) {
    $db = getDB();

    // Verify category ownership
    $query = "SELECT * FROM categories WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $categoryId,
        ':user_id' => $userId
    ]);

    $category = $stmt->fetch();

    if (!$category) {
        jsonNotFound('Category not found');
    }

    // Get links in this category
    $linksQuery = "SELECT l.*
                   FROM links l
                   INNER JOIN link_categories lc ON l.id = lc.link_id
                   WHERE lc.category_id = :category_id AND l.user_id = :user_id
                   ORDER BY lc.sort_order";
    $stmt = $db->prepare($linksQuery);
    $stmt->execute([
        ':category_id' => $categoryId,
        ':user_id' => $userId
    ]);

    $category['links'] = $stmt->fetchAll();
    $category['link_count'] = count($category['links']);

    jsonSuccess($category);
}

/**
 * Create a new category
 */
function createCategory($data, $userId) {
    $db = getDB();

    // Validate required fields
    $missing = validateRequired($data, ['name']);
    if (!empty($missing)) {
        jsonValidationError(['missing_fields' => $missing]);
    }

    // Validate name length
    if (!validateLength($data['name'], 255, 1)) {
        jsonValidationError(['name' => 'Name must be between 1 and 255 characters']);
    }

    // Validate parent_id if provided
    $parentId = $data['parent_id'] ?? null;
    if ($parentId !== null) {
        if (!validatePositiveInt($parentId)) {
            jsonValidationError(['parent_id' => 'Invalid parent category ID']);
        }

        // Verify parent exists and belongs to user
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $parentId, ':user_id' => $userId]);
        if (!$stmt->fetch()) {
            jsonValidationError(['parent_id' => 'Parent category not found']);
        }
    }

    // Validate display_mode if provided
    $displayMode = $data['display_mode'] ?? null;
    if ($displayMode !== null && !validateDisplayMode($displayMode)) {
        jsonValidationError(['display_mode' => 'Invalid display mode']);
    }

    // Sanitize inputs
    $name = sanitizeHtml(trim($data['name']));
    $defaultCount = isset($data['default_count']) ? (int)$data['default_count'] : 10;
    $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

    try {
        // Insert category
        $query = "INSERT INTO categories (user_id, parent_id, name, display_mode, default_count, sort_order)
                  VALUES (:user_id, :parent_id, :name, :display_mode, :default_count, :sort_order)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':parent_id' => $parentId,
            ':name' => $name,
            ':display_mode' => $displayMode,
            ':default_count' => $defaultCount,
            ':sort_order' => $sortOrder
        ]);

        $categoryId = $db->lastInsertId();

        // Fetch and return the created category
        getCategoryById($categoryId, $userId);
    } catch (Exception $e) {
        error_log("Error creating category: " . $e->getMessage());
        jsonError('Failed to create category', 500);
    }
}

/**
 * Update a category
 */
function updateCategory($categoryId, $data, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT user_id, parent_id FROM categories WHERE id = :id");
    $stmt->execute([':id' => $categoryId]);
    $existingCategory = $stmt->fetch();

    if (!$existingCategory) {
        jsonNotFound('Category not found');
    }

    requireOwnership($existingCategory['user_id'], $userId);

    // Build update query dynamically
    $updates = [];
    $params = [':id' => $categoryId, ':user_id' => $userId];

    if (isset($data['name'])) {
        if (!validateLength($data['name'], 255, 1)) {
            jsonValidationError(['name' => 'Name must be between 1 and 255 characters']);
        }
        $updates[] = "name = :name";
        $params[':name'] = sanitizeHtml(trim($data['name']));
    }

    // Use array_key_exists instead of isset to handle null values correctly
    if (array_key_exists('parent_id', $data)) {
        // Prevent setting self as parent
        if ($data['parent_id'] == $categoryId) {
            jsonValidationError(['parent_id' => 'Category cannot be its own parent']);
        }

        // Verify parent exists and belongs to user (if not null)
        if ($data['parent_id'] !== null) {
            if (!validatePositiveInt($data['parent_id'])) {
                jsonValidationError(['parent_id' => 'Invalid parent category ID']);
            }

            $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $data['parent_id'], ':user_id' => $userId]);
            if (!$stmt->fetch()) {
                jsonValidationError(['parent_id' => 'Parent category not found']);
            }

            // TODO: Check for circular references in tree
        }

        $updates[] = "parent_id = :parent_id";
        $params[':parent_id'] = $data['parent_id'];
    }

    if (array_key_exists('display_mode', $data)) {
        if ($data['display_mode'] !== null && !validateDisplayMode($data['display_mode'])) {
            jsonValidationError(['display_mode' => 'Invalid display mode']);
        }
        $updates[] = "display_mode = :display_mode";
        $params[':display_mode'] = $data['display_mode'];
    }

    if (isset($data['default_count'])) {
        $updates[] = "default_count = :default_count";
        $params[':default_count'] = (int)$data['default_count'];
    }

    if (isset($data['sort_order'])) {
        $updates[] = "sort_order = :sort_order";
        $params[':sort_order'] = (int)$data['sort_order'];
    }

    if (empty($updates)) {
        jsonError('No fields to update', 400);
    }

    try {
        $query = "UPDATE categories SET " . implode(', ', $updates) . "
                 WHERE id = :id AND user_id = :user_id";

        error_log("UpdateCategory - Query: $query");
        error_log("UpdateCategory - Params: " . json_encode($params));

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $rowCount = $stmt->rowCount();
        error_log("UpdateCategory - Rows affected: $rowCount");

        if ($rowCount === 0) {
            error_log("UpdateCategory - WARNING: No rows were updated for category $categoryId");
        }

        // Fetch and return the updated category
        getCategoryById($categoryId, $userId);
    } catch (Exception $e) {
        error_log("Error updating category: " . $e->getMessage());
        jsonError('Failed to update category', 500);
    }
}

/**
 * Delete a category
 */
function deleteCategory($categoryId, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM categories WHERE id = :id");
    $stmt->execute([':id' => $categoryId]);
    $existingCategory = $stmt->fetch();

    if (!$existingCategory) {
        jsonNotFound('Category not found');
    }

    requireOwnership($existingCategory['user_id'], $userId);

    try {
        // Delete category (cascading will handle subcategories and link_categories)
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':id' => $categoryId,
            ':user_id' => $userId
        ]);

        jsonSuccess(['message' => 'Category deleted successfully']);
    } catch (Exception $e) {
        error_log("Error deleting category: " . $e->getMessage());
        jsonError('Failed to delete category', 500);
    }
}

/**
 * Reorder a category
 * Properly renumbers all siblings to avoid sort_order collisions
 */
function reorderCategory($categoryId, $data, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $categoryId]);
    $existingCategory = $stmt->fetch();

    if (!$existingCategory) {
        jsonNotFound('Category not found');
    }

    requireOwnership($existingCategory['user_id'], $userId);

    // Validate required fields
    if (!isset($data['sort_order'])) {
        jsonError('sort_order is required', 400);
    }

    $newSortOrder = (int)$data['sort_order'];

    // Determine parent_id (from data if provided, else keep existing)
    $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $existingCategory['parent_id'];

    try {
        $db->beginTransaction();

        // Fetch all siblings (same parent_id) ordered by current sort_order
        $stmt = $db->prepare("
            SELECT id, sort_order
            FROM categories
            WHERE user_id = :user_id
              AND " . ($parentId === null ? "parent_id IS NULL" : "parent_id = :parent_id") . "
            ORDER BY sort_order, name
        ");

        $params = [':user_id' => $userId];
        if ($parentId !== null) {
            $params[':parent_id'] = $parentId;
        }

        $stmt->execute($params);
        $siblings = $stmt->fetchAll();

        // Build new order: remove current item, insert at new position
        $orderedIds = [];
        foreach ($siblings as $sibling) {
            if ($sibling['id'] != $categoryId) {
                $orderedIds[] = $sibling['id'];
            }
        }

        // Insert at new position (clamp to valid range)
        $newSortOrder = max(0, min($newSortOrder, count($orderedIds)));
        array_splice($orderedIds, $newSortOrder, 0, [$categoryId]);

        // Update sort_order for all siblings
        $updateStmt = $db->prepare("
            UPDATE categories
            SET sort_order = :sort_order, parent_id = :parent_id
            WHERE id = :id AND user_id = :user_id
        ");

        foreach ($orderedIds as $index => $id) {
            $updateStmt->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':sort_order' => $index,
                ':parent_id' => $parentId
            ]);
        }

        $db->commit();

        jsonSuccess(['message' => 'Category reordered successfully']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error reordering category: " . $e->getMessage());
        jsonError('Failed to reorder category', 500);
    }
}

/**
 * Get all URLs in a category (for "open all" feature)
 */
function getCategoryUrls($categoryId, $userId) {
    $db = getDB();

    // Verify category ownership
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $categoryId, ':user_id' => $userId]);
    if (!$stmt->fetch()) {
        jsonNotFound('Category not found');
    }

    // Get all URLs in this category
    $query = "SELECT l.url, l.name
              FROM links l
              INNER JOIN link_categories lc ON l.id = lc.link_id
              WHERE lc.category_id = :category_id AND l.user_id = :user_id
              ORDER BY lc.sort_order";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':category_id' => $categoryId,
        ':user_id' => $userId
    ]);

    $urls = $stmt->fetchAll();

    jsonSuccess([
        'category_id' => $categoryId,
        'urls' => $urls,
        'count' => count($urls)
    ]);
}

/**
 * Get link count for a category
 * @param PDO $db Database connection
 * @param int $categoryId Category ID
 * @return int Link count
 */
function getCategoryLinkCount($db, $categoryId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM link_categories WHERE category_id = :category_id");
    $stmt->execute([':category_id' => $categoryId]);
    return (int)$stmt->fetch()['count'];
}
