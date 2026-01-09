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

    // Increase PHP limits for large imports
    @ini_set('max_execution_time', '300'); // 5 minutes
    @ini_set('memory_limit', '256M');
    @ini_set('max_input_vars', '5000');

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

        error_log("Bookmark import completed: {$stats['folders']} folders, {$stats['links']} links, {$stats['skipped']} skipped");

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
        'favicons_fetched' => 0
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

    $nodesToSkip = [];

    foreach ($dlElement->childNodes as $node) {
        // Skip text nodes
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        // Skip if we already processed this node as a folder's DL
        if (in_array(spl_object_id($node), $nodesToSkip, true)) {
            continue;
        }

        $tagName = strtolower($node->nodeName);

        // DT contains either a folder (H3) or a link (A)
        if ($tagName === 'dt') {
            $hasFolder = false;
            $hasLink = false;
            $folderCategoryId = null;
            $folderDl = null;
            $nestedFolderCategoryId = null;

            // Check what's inside this DT
            foreach ($node->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $childTag = strtolower($child->nodeName);

                // H3 = Folder/Category
                if ($childTag === 'h3') {
                    $hasFolder = true;
                    $folderName = trim($child->textContent);
                    if (!empty($folderName)) {
                        $folderCategoryId = getOrCreateCategory($userId, $db, $folderName, $parentCategoryId);
                        $stats['folders']++;
                    }
                }

                // A = Bookmark/Link
                if ($childTag === 'a') {
                    $hasLink = true;
                    $url = $child->getAttribute('href');
                    $name = trim($child->textContent);
                    $addDate = $child->getAttribute('add_date');
                    $icon = $child->getAttribute('icon');

                    if (!empty($url) && !empty($name)) {
                        try {
                            createBookmarkLink($userId, $db, $url, $name, $parentCategoryId, $addDate, $icon, $stats);
                            $stats['links']++;
                        } catch (Exception $e) {
                            $stats['skipped']++;
                        }
                    }
                }

                // DL = Folder contents (may be child of DT in some parsings)
                if ($childTag === 'dl') {
                    $folderDl = $child;
                }

                // Nested DT - DOMDocument creates this when DT tags aren't properly closed
                // Check if it contains an H3 (folder) or just A (link that should be in parent category)
                if ($childTag === 'dt') {
                    $hasNestedFolder = false;
                    foreach ($child->childNodes as $grandchild) {
                        if ($grandchild->nodeType === XML_ELEMENT_NODE && strtolower($grandchild->nodeName) === 'h3') {
                            $nestedFolderName = trim($grandchild->textContent);
                            if (!empty($nestedFolderName)) {
                                $nestedFolderCategoryId = getOrCreateCategory($userId, $db, $nestedFolderName, $parentCategoryId);
                                $stats['folders']++;
                                $hasNestedFolder = true;
                            }
                            break;
                        }
                    }

                    // If nested DT has no folder (just links), recursively process it in the parent category
                    if (!$hasNestedFolder) {
                        processNestedDT($child, $userId, $db, $parentCategoryId, $stats);
                    }
                }
            }

            // If we found a folder and its DL as a child, process it
            if ($hasFolder && $folderCategoryId !== null && $folderDl !== null) {
                processBookmarkList($folderDl, $userId, $db, $folderCategoryId, $stats);
            }
            // Otherwise, if we found a folder, check for sibling DL
            elseif ($hasFolder && $folderCategoryId !== null) {
                // Find the next element sibling (skip text nodes)
                $nextSibling = $node->nextSibling;
                while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
                    $nextSibling = $nextSibling->nextSibling;
                }

                // If next sibling is a DL, it contains the folder's contents
                if ($nextSibling && strtolower($nextSibling->nodeName) === 'dl') {
                    processBookmarkList($nextSibling, $userId, $db, $folderCategoryId, $stats);
                    // Mark this DL to skip in the main loop
                    $nodesToSkip[] = spl_object_id($nextSibling);
                }
            }
            // Special case: if we have a link AND a nested folder, the nextSibling DL belongs to the nested folder
            elseif ($hasLink && $nestedFolderCategoryId !== null) {
                $nextSibling = $node->nextSibling;
                while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
                    $nextSibling = $nextSibling->nextSibling;
                }

                if ($nextSibling && strtolower($nextSibling->nodeName) === 'dl') {
                    processBookmarkList($nextSibling, $userId, $db, $nestedFolderCategoryId, $stats);
                    $nodesToSkip[] = spl_object_id($nextSibling);
                }
            }
        }

        // DL can also appear as a direct child of another DL
        elseif ($tagName === 'dl') {
            processBookmarkList($node, $userId, $db, $parentCategoryId, $stats);
        }
    }
}

/**
 * Process a nested DT element that contains links (not folders)
 */
function processNestedDT($dtElement, $userId, $db, $parentCategoryId, &$stats) {
    foreach ($dtElement->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $childTag = strtolower($child->nodeName);

        // Process links in the nested DT
        if ($childTag === 'a') {
            $url = $child->getAttribute('href');
            $name = trim($child->textContent);
            $addDate = $child->getAttribute('add_date');
            $icon = $child->getAttribute('icon');

            if (!empty($url) && !empty($name)) {
                try {
                    createBookmarkLink($userId, $db, $url, $name, $parentCategoryId, $addDate, $icon, $stats);
                    $stats['links']++;
                } catch (Exception $e) {
                    $stats['skipped']++;
                }
            }
        }

        // Recursively process further nested DTs
        elseif ($childTag === 'dt') {
            processNestedDT($child, $userId, $db, $parentCategoryId, $stats);
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
