<?php
// Debug endpoint - check if index.php is being reached
header('Content-Type: application/json');

echo json_encode([
    'status' => 'index.php is working',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'not set',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'not set',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
]);
exit;
