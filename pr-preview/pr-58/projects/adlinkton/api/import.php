<?php
/**
 * Import API Endpoints
 * Handles bookmark imports
 */

/**
 * Handle import API requests
 * @param string $method HTTP method
 * @param array $segments URL path segments
 * @param array|null $requestBody Request body data
 * @param int $userId Current user ID
 */
function handleImportRequest($method, $segments, $requestBody, $userId) {
    $importType = $segments[1] ?? null;

    if ($method !== 'POST') {
        jsonError('Method not allowed', 405);
        return;
    }

    switch ($importType) {
        case 'bookmarks':
            importBookmarks($userId);
            break;

        default:
            jsonError('Unknown import type', 400);
    }
}

/**
 * Import browser bookmarks from Netscape HTML format
 */
function importBookmarks($userId) {
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        jsonError('No file uploaded', 400);
    }

    $file = $_FILES['file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('File upload failed', 400);
    }

    if ($file['type'] !== 'text/html' && !str_ends_with($file['name'], '.html')) {
        jsonError('Invalid file type. Expected HTML file.', 400);
    }

    // TODO: Implement bookmark parsing
    // 1. Parse Netscape bookmark HTML format
    // 2. Walk tree recursively (H3 = folders, A = links)
    // 3. Create categories matching folder structure
    // 4. Create links
    // 5. Fetch favicons in batches
    // 6. Return summary

    jsonError('Not implemented yet', 501);
}
