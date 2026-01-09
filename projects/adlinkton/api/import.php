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
    require_once __DIR__ . '/helpers/favicon.php';
    require_once __DIR__ . '/helpers/validation.php';

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

    // Read file content
    $html = file_get_contents($file['tmp_name']);
    if ($html === false) {
        jsonError('Failed to read file', 400);
    }

    $db = getDB();

    try {
        $db->beginTransaction();

        // Parse bookmarks
        $stats = parseBookmarkHTML($html, $userId, $db);

        $db->commit();

        jsonSuccess([
            'message' => 'Bookmarks imported successfully',
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Bookmark import failed: " . $e->getMessage());
        jsonError('Import failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Parse Netscape bookmark HTML format
 */
function parseBookmarkHTML($html, $userId, $db) {
    // Use DOMDocument to parse HTML
    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $stats = [
        'folders' => 0,
        'links' => 0,
        'skipped' => 0,
        'favicons_fetched' => 0,
        'debug' => []
    ];

    // Find the root DL (definition list) which contains bookmarks
    $dlElements = $dom->getElementsByTagName('dl');
    if ($dlElements->length === 0) {
        throw new Exception('No bookmarks found in file');
    }

    // Get or create "Imported" root category
    $rootCategoryId = getOrCreateCategory($userId, $db, 'Imported Bookmarks', null);
    $stats['folders']++;

    // Process the first DL element
    processBookmarkList($dlElements->item(0), $userId, $db, $rootCategoryId, $stats);

    return $stats;
}

/**
 * Recursively process a bookmark list (DL element)
 */
function processBookmarkList($dlElement, $userId, $db, $parentCategoryId, &$stats) {
    if (!$dlElement || !$dlElement->childNodes) {
        return;
    }

    $skipNextDl = false;

    foreach ($dlElement->childNodes as $node) {
        // Skip text nodes
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tagName = strtolower($node->nodeName);
        $stats['debug'][] = "Processing node: {$tagName}, parent category: {$parentCategoryId}";

        // DT contains either a folder (H3) or a link (A)
        if ($tagName === 'dt') {
            $hasFolder = false;
            $hasLink = false;

            // Check what's inside this DT
            foreach ($node->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $childTag = strtolower($child->nodeName);
                $stats['debug'][] = "  DT child: {$childTag}";

                // H3 = Folder/Category
                if ($childTag === 'h3') {
                    $hasFolder = true;
                    $folderName = trim($child->textContent);
                    $stats['debug'][] = "  Found folder: {$folderName}";
                    if (!empty($folderName)) {
                        $folderCategoryId = getOrCreateCategory($userId, $db, $folderName, $parentCategoryId);
                        $stats['folders']++;

                        // In Chrome bookmark format, the DL (folder contents) is the NEXT SIBLING of this DT
                        // Skip text nodes to find the next element
                        $nextSibling = $node->nextSibling;
                        while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
                            $nextSibling = $nextSibling->nextSibling;
                        }

                        // If the next sibling is a DL, it contains this folder's contents
                        if ($nextSibling && strtolower($nextSibling->nodeName) === 'dl') {
                            $stats['debug'][] = "  Found folder DL sibling, recursing with category {$folderCategoryId}";
                            processBookmarkList($nextSibling, $userId, $db, $folderCategoryId, $stats);
                            $skipNextDl = true; // Mark this DL as processed so we don't process it again
                        } else {
                            $stats['debug'][] = "  No DL sibling found after folder";
                        }
                    }
                }

                // A = Bookmark/Link
                elseif ($childTag === 'a') {
                    $hasLink = true;
                    $url = $child->getAttribute('href');
                    $name = trim($child->textContent);
                    $addDate = $child->getAttribute('add_date');
                    $icon = $child->getAttribute('icon');

                    $stats['debug'][] = "  Found link: {$name} -> {$url}";

                    if (!empty($url) && !empty($name)) {
                        $stats['debug'][] = "  Creating bookmark link for: {$name}";
                        try {
                            createBookmarkLink($userId, $db, $url, $name, $parentCategoryId, $addDate, $icon, $stats);
                            $stats['links']++;
                            $stats['debug'][] = "  Successfully created link";
                        } catch (Exception $e) {
                            $stats['debug'][] = "  Failed to create bookmark: {$name} - {$e->getMessage()}";
                            $stats['skipped']++;
                        }
                    } else {
                        $stats['debug'][] = "  Skipping link - empty url or name";
                    }
                }
            }

            $stats['debug'][] = "  DT summary - hasFolder: " . ($hasFolder ? 'yes' : 'no') . ", hasLink: " . ($hasLink ? 'yes' : 'no'));
        }

        // DL = Nested list
        elseif ($tagName === 'dl') {
            // Skip if we already processed this DL as part of a folder
            if ($skipNextDl) {
                $stats['debug'][] = "Skipping DL (already processed as folder contents)";
                $skipNextDl = false;
                continue;
            }
            // Otherwise process it (shouldn't normally happen in Chrome format)
            $stats['debug'][] = "Processing standalone DL";
            processBookmarkList($node, $userId, $db, $parentCategoryId, $stats);
        }
    }
}

/**
 * Get or create a category
 */
function getOrCreateCategory($userId, $db, $name, $parentId) {
    // Sanitize name
    $name = sanitizeHtml(trim($name));

    // Check if category already exists
    $query = "SELECT id FROM categories
              WHERE user_id = :user_id AND name = :name AND parent_id ";

    if ($parentId === null) {
        $query .= "IS NULL";
        $params = [':user_id' => $userId, ':name' => $name];
    } else {
        $query .= "= :parent_id";
        $params = [':user_id' => $userId, ':name' => $name, ':parent_id' => $parentId];
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $existing = $stmt->fetch();

    if ($existing) {
        return $existing['id'];
    }

    // Create new category
    $insertQuery = "INSERT INTO categories (user_id, parent_id, name, display_mode, default_count, sort_order)
                    VALUES (:user_id, :parent_id, :name, 'tab', 10, 0)";
    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':user_id' => $userId,
        ':parent_id' => $parentId,
        ':name' => $name
    ]);

    return $db->lastInsertId();
}

/**
 * Create a bookmark link
 */
function createBookmarkLink($userId, $db, $url, $name, $categoryId, $addDate, $icon, &$stats) {
    // Validate URL
    if (!validateUrl($url)) {
        throw new Exception("Invalid URL: {$url}");
    }

    // Sanitize inputs
    $url = trim($url);
    $name = sanitizeHtml(trim($name));

    // Check if link already exists
    $checkQuery = "SELECT id FROM links WHERE user_id = :user_id AND url = :url";
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([':user_id' => $userId, ':url' => $url]);
    if ($stmt->fetch()) {
        // Link already exists, skip
        throw new Exception("Duplicate link: {$url}");
    }

    // Try to fetch favicon
    $faviconPath = null;

    // First, try to use the icon from the bookmark file (base64 encoded)
    if (!empty($icon) && str_starts_with($icon, 'data:image/')) {
        $faviconPath = saveFaviconFromDataUrl($icon, $url);
        if ($faviconPath) {
            $stats['favicons_fetched']++;
        }
    }

    // If no icon in bookmark file, fetch from URL
    if (!$faviconPath) {
        $faviconPath = fetchFavicon($url, 2); // 2 second timeout
        if ($faviconPath) {
            $stats['favicons_fetched']++;
        }
    }

    // Convert add_date (Unix timestamp) to datetime
    $createdAt = null;
    if (!empty($addDate) && is_numeric($addDate)) {
        $createdAt = date('Y-m-d H:i:s', (int)$addDate);
    }

    // Insert link
    $query = "INSERT INTO links (user_id, url, name, favicon_path, is_favorite, created_at)
              VALUES (:user_id, :url, :name, :favicon_path, 0, " .
              ($createdAt ? ":created_at" : "NOW()") . ")";

    $params = [
        ':user_id' => $userId,
        ':url' => $url,
        ':name' => $name,
        ':favicon_path' => $faviconPath
    ];

    if ($createdAt) {
        $params[':created_at'] = $createdAt;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $linkId = $db->lastInsertId();

    // Assign to category
    if ($categoryId) {
        $linkCatQuery = "INSERT INTO link_categories (link_id, category_id, sort_order)
                         VALUES (:link_id, :category_id, 0)";
        $stmt = $db->prepare($linkCatQuery);
        $stmt->execute([
            ':link_id' => $linkId,
            ':category_id' => $categoryId
        ]);
    }

    return $linkId;
}

/**
 * Save favicon from data URL
 */
function saveFaviconFromDataUrl($dataUrl, $url) {
    try {
        // Parse data URL
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $imageType = $matches[1];
        $base64Data = $matches[2];

        $content = base64_decode($base64Data);
        if ($content === false) {
            return null;
        }

        // Validate image
        if (!isValidImage($content)) {
            return null;
        }

        // Save it
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            return null;
        }

        return saveFavicon($content, $domain);
    } catch (Exception $e) {
        error_log("Failed to save favicon from data URL: " . $e->getMessage());
        return null;
    }
}
