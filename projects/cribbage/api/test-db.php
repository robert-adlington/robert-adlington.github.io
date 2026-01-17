<?php
// Debug script to check cribbage database setup
require_once __DIR__ . '/../../api/database.php';

header('Content-Type: application/json');

try {
    $db = db();

    // Check if tables exist
    $tables = ['cribbage_sessions', 'cribbage_games', 'cribbage_moves'];
    $results = [];

    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $results[$table] = $exists;

        if ($exists) {
            // Get column info
            $stmt = $db->query("DESCRIBE $table");
            $results[$table . '_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'tables' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
