<?php
/**
 * Utility script to refetch favicons for links that don't have them
 */

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/favicon.php';

echo "=== Favicon Refetch Utility ===\n\n";

try {
    $db = getDB();

    // Find links without favicons
    $stmt = $db->query("SELECT id, url, name FROM links WHERE favicon_path IS NULL OR favicon_path = ''");
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($links);
    echo "Found {$total} links without favicons\n\n";

    if ($total === 0) {
        echo "All links have favicons!\n";
        exit(0);
    }

    $updated = 0;
    $failed = 0;

    foreach ($links as $index => $link) {
        $num = $index + 1;
        echo "[$num/$total] Processing: {$link['name']}\n";
        echo "  URL: {$link['url']}\n";

        // Fetch favicon
        $faviconPath = fetchFavicon($link['url'], 5);

        if ($faviconPath) {
            // Update database
            $updateStmt = $db->prepare("UPDATE links SET favicon_path = :favicon_path WHERE id = :id");
            $updateStmt->execute([
                ':favicon_path' => $faviconPath,
                ':id' => $link['id']
            ]);

            echo "  ✓ Success: {$faviconPath}\n";
            $updated++;
        } else {
            echo "  ✗ Failed to fetch favicon\n";
            $failed++;
        }

        echo "\n";

        // Small delay to be nice to servers
        if ($num < $total) {
            usleep(500000); // 0.5 second delay
        }
    }

    echo "=== Summary ===\n";
    echo "Total processed: {$total}\n";
    echo "Successfully updated: {$updated}\n";
    echo "Failed: {$failed}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
