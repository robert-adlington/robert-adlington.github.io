<?php
/**
 * Test favicon fetching
 */

require_once __DIR__ . '/helpers/favicon.php';

// Test with a well-known site
$testUrl = 'https://github.com';
echo "Testing favicon fetch for: $testUrl\n\n";

$faviconPath = fetchFavicon($testUrl);

if ($faviconPath) {
    echo "Success! Favicon saved to: $faviconPath\n";

    // Check if file exists
    $fullPath = __DIR__ . '/../' . str_replace('/adlington/projects/adlinkton/', '', $faviconPath);
    echo "Full path: $fullPath\n";

    if (file_exists($fullPath)) {
        echo "File exists! Size: " . filesize($fullPath) . " bytes\n";
    } else {
        echo "ERROR: File does not exist at full path!\n";
    }
} else {
    echo "ERROR: Favicon fetch failed\n";
}

// List what's in the storage directory
echo "\n--- Contents of storage/favicons/ ---\n";
$files = scandir(__DIR__ . '/../storage/favicons/');
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "  - $file\n";
    }
}
