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
    // TODO: Implement nested tree structure with effective_display_mode
    jsonError('Not implemented yet', 501);
}

/**
 * Get a single category with its links
 */
function getCategoryById($categoryId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Create a new category
 */
function createCategory($data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Update a category
 */
function updateCategory($categoryId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Delete a category
 */
function deleteCategory($categoryId, $userId) {
    // TODO: Implement (handle cascade or reassign links)
    jsonError('Not implemented yet', 501);
}

/**
 * Reorder a category
 */
function reorderCategory($categoryId, $data, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}

/**
 * Get all URLs in a category (for "open all" feature)
 */
function getCategoryUrls($categoryId, $userId) {
    // TODO: Implement
    jsonError('Not implemented yet', 501);
}
