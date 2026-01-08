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
    $sort = $_GET['sort'] ?? 'created';
    $order = $_GET['order'] ?? 'desc';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Build query with filters
    $conditions = ['l.user_id = :user_id'];
    $params = [':user_id' => $userId];
    $joins = [];

    // Filter by category
    if ($categoryId) {
        $joins[] = "INNER JOIN link_categories lc ON l.id = lc.link_id";
        $conditions[] = "lc.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    // Filter by favorite
    if ($favorite !== null) {
        $conditions[] = "l.is_favorite = :favorite";
        $params[':favorite'] = $favorite ? 1 : 0;
    }

    // Filter by search
    if ($search) {
        $conditions[] = "MATCH(l.name, l.description, l.url) AGAINST (:search IN NATURAL LANGUAGE MODE)";
        $params[':search'] = $search;
    }

    // Build ORDER BY clause
    $orderClause = match($sort) {
        'name' => "l.name " . strtoupper($order),
        'created' => "l.created_at " . strtoupper($order),
        'accessed' => "l.last_accessed " . strtoupper($order),
        'frequency' => "l.access_count " . strtoupper($order) . ", l.last_accessed DESC",
        'manual' => "lc.sort_order " . strtoupper($order),
        default => "l.created_at DESC"
    };

    // Build full query
    $joinClause = implode(' ', $joins);
    $whereClause = implode(' AND ', $conditions);

    $query = "SELECT DISTINCT l.*
              FROM links l
              {$joinClause}
              WHERE {$whereClause}
              ORDER BY {$orderClause}
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $links = $stmt->fetchAll();

    // Enrich links with categories and tags
    foreach ($links as &$link) {
        $link['categories'] = getLinkCategories($db, $link['id']);
        $link['tags'] = getLinkTags($db, $link['id']);
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT l.id) as total
                   FROM links l
                   {$joinClause}
                   WHERE {$whereClause}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    jsonSuccess([
        'links' => $links,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Get a single link by ID
 */
function getLinkById($linkId, $userId) {
    $db = getDB();

    $query = "SELECT * FROM links WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $linkId,
        ':user_id' => $userId
    ]);

    $link = $stmt->fetch();

    if (!$link) {
        jsonNotFound('Link not found');
    }

    // Enrich with categories and tags
    $link['categories'] = getLinkCategories($db, $link['id']);
    $link['tags'] = getLinkTags($db, $link['id']);

    jsonSuccess($link);
}

/**
 * Create a new link
 */
function createLink($data, $userId) {
    require_once __DIR__ . '/helpers/favicon.php';

    $db = getDB();

    // Validate required fields
    $missing = validateRequired($data, ['url', 'name']);
    if (!empty($missing)) {
        jsonValidationError(['missing_fields' => $missing]);
    }

    // Validate URL
    if (!validateUrl($data['url'])) {
        jsonValidationError(['url' => 'Invalid URL format']);
    }

    // Validate name length
    if (!validateLength($data['name'], 255, 1)) {
        jsonValidationError(['name' => 'Name must be between 1 and 255 characters']);
    }

    // Sanitize inputs
    $url = trim($data['url']);
    $name = sanitizeHtml(trim($data['name']));
    $description = isset($data['description']) ? sanitizeHtml(trim($data['description'])) : null;
    $isFavorite = isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false;
    $categoryIds = $data['category_ids'] ?? [];
    $tagIds = $data['tag_ids'] ?? [];

    // Fetch favicon
    $faviconPath = fetchFavicon($url);

    try {
        $db->beginTransaction();

        // Insert link
        $query = "INSERT INTO links (user_id, url, name, description, favicon_path, is_favorite)
                  VALUES (:user_id, :url, :name, :description, :favicon_path, :is_favorite)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':url' => $url,
            ':name' => $name,
            ':description' => $description,
            ':favicon_path' => $faviconPath,
            ':is_favorite' => $isFavorite ? 1 : 0
        ]);

        $linkId = $db->lastInsertId();

        // Assign categories (if none provided, link goes to Inbox)
        if (!empty($categoryIds)) {
            foreach ($categoryIds as $index => $categoryId) {
                if (validatePositiveInt($categoryId)) {
                    $stmt = $db->prepare("INSERT INTO link_categories (link_id, category_id, sort_order)
                                         VALUES (:link_id, :category_id, :sort_order)");
                    $stmt->execute([
                        ':link_id' => $linkId,
                        ':category_id' => $categoryId,
                        ':sort_order' => $index
                    ]);
                }
            }
        }

        // Assign tags
        if (!empty($tagIds)) {
            foreach ($tagIds as $tagId) {
                if (validatePositiveInt($tagId)) {
                    $stmt = $db->prepare("INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)");
                    $stmt->execute([
                        ':link_id' => $linkId,
                        ':tag_id' => $tagId
                    ]);
                }
            }
        }

        $db->commit();

        // Fetch and return the created link
        getLinkById($linkId, $userId);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error creating link: " . $e->getMessage());
        jsonError('Failed to create link', 500);
    }
}

/**
 * Update an existing link
 */
function updateLink($linkId, $data, $userId) {
    require_once __DIR__ . '/helpers/favicon.php';

    $db = getDB();

    // Verify ownership
    $link = $db->prepare("SELECT user_id FROM links WHERE id = :id");
    $link->execute([':id' => $linkId]);
    $existingLink = $link->fetch();

    if (!$existingLink) {
        jsonNotFound('Link not found');
    }

    requireOwnership($existingLink['user_id'], $userId);

    // Build update query dynamically based on provided fields
    $updates = [];
    $params = [':id' => $linkId, ':user_id' => $userId];

    if (isset($data['url'])) {
        if (!validateUrl($data['url'])) {
            jsonValidationError(['url' => 'Invalid URL format']);
        }
        $updates[] = "url = :url";
        $params[':url'] = trim($data['url']);

        // Refetch favicon if URL changed
        $params[':favicon_path'] = fetchFavicon($params[':url']);
        $updates[] = "favicon_path = :favicon_path";
    }

    if (isset($data['name'])) {
        if (!validateLength($data['name'], 255, 1)) {
            jsonValidationError(['name' => 'Name must be between 1 and 255 characters']);
        }
        $updates[] = "name = :name";
        $params[':name'] = sanitizeHtml(trim($data['name']));
    }

    if (isset($data['description'])) {
        $updates[] = "description = :description";
        $params[':description'] = sanitizeHtml(trim($data['description']));
    }

    if (isset($data['is_favorite'])) {
        $updates[] = "is_favorite = :is_favorite";
        $params[':is_favorite'] = $data['is_favorite'] ? 1 : 0;
    }

    try {
        $db->beginTransaction();

        // Update link fields
        if (!empty($updates)) {
            $query = "UPDATE links SET " . implode(', ', $updates) . "
                     WHERE id = :id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }

        // Update categories if provided
        if (isset($data['category_ids'])) {
            // Remove existing categories
            $db->prepare("DELETE FROM link_categories WHERE link_id = :link_id")
               ->execute([':link_id' => $linkId]);

            // Add new categories
            foreach ($data['category_ids'] as $index => $categoryId) {
                if (validatePositiveInt($categoryId)) {
                    $stmt = $db->prepare("INSERT INTO link_categories (link_id, category_id, sort_order)
                                         VALUES (:link_id, :category_id, :sort_order)");
                    $stmt->execute([
                        ':link_id' => $linkId,
                        ':category_id' => $categoryId,
                        ':sort_order' => $index
                    ]);
                }
            }
        }

        // Update tags if provided
        if (isset($data['tag_ids'])) {
            // Remove existing tags
            $db->prepare("DELETE FROM link_tags WHERE link_id = :link_id")
               ->execute([':link_id' => $linkId]);

            // Add new tags
            foreach ($data['tag_ids'] as $tagId) {
                if (validatePositiveInt($tagId)) {
                    $stmt = $db->prepare("INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)");
                    $stmt->execute([
                        ':link_id' => $linkId,
                        ':tag_id' => $tagId
                    ]);
                }
            }
        }

        $db->commit();

        // Fetch and return the updated link
        getLinkById($linkId, $userId);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error updating link: " . $e->getMessage());
        jsonError('Failed to update link', 500);
    }
}

/**
 * Delete a link
 */
function deleteLink($linkId, $userId) {
    $db = getDB();

    // Verify ownership
    $link = $db->prepare("SELECT user_id FROM links WHERE id = :id");
    $link->execute([':id' => $linkId]);
    $existingLink = $link->fetch();

    if (!$existingLink) {
        jsonNotFound('Link not found');
    }

    requireOwnership($existingLink['user_id'], $userId);

    // Delete link (cascading will handle link_categories and link_tags)
    $stmt = $db->prepare("DELETE FROM links WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id' => $linkId,
        ':user_id' => $userId
    ]);

    jsonSuccess(['message' => 'Link deleted successfully']);
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

/**
 * Get categories for a link
 * @param PDO $db Database connection
 * @param int $linkId Link ID
 * @return array Array of category IDs
 */
function getLinkCategories($db, $linkId) {
    $stmt = $db->prepare("SELECT category_id FROM link_categories WHERE link_id = :link_id ORDER BY sort_order");
    $stmt->execute([':link_id' => $linkId]);
    return array_column($stmt->fetchAll(), 'category_id');
}

/**
 * Get tags for a link
 * @param PDO $db Database connection
 * @param int $linkId Link ID
 * @return array Array of tag objects
 */
function getLinkTags($db, $linkId) {
    $stmt = $db->prepare("SELECT t.id, t.name
                         FROM tags t
                         INNER JOIN link_tags lt ON t.id = lt.tag_id
                         WHERE lt.link_id = :link_id
                         ORDER BY t.name");
    $stmt->execute([':link_id' => $linkId]);
    return $stmt->fetchAll();
}
