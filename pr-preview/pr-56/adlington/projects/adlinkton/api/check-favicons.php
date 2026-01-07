<?php
/**
 * Check database favicon status
 */

require_once __DIR__ . '/helpers/db.php';

$db = getDB();
$stmt = $db->query('SELECT id, name, url, favicon_path FROM links LIMIT 10');
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Links in Database ===\n\n";
echo "Total links checked: " . count($links) . "\n\n";

foreach ($links as $link) {
    echo "ID: {$link['id']}\n";
    echo "Name: {$link['name']}\n";
    echo "URL: {$link['url']}\n";
    echo "Favicon: " . ($link['favicon_path'] ?: 'NULL') . "\n";

    if ($link['favicon_path']) {
        $fullPath = __DIR__ . '/../storage/favicons/' . basename($link['favicon_path']);
        $exists = file_exists($fullPath) ? '✓ EXISTS' : '✗ MISSING';
        echo "File check: {$exists}\n";
    }

    echo "\n";
}
