<?php
// Quick test to verify API is accessible
echo json_encode([
    'status' => 'ok',
    'message' => 'Adlinkton API is working',
    'request_uri' => $_SERVER['REQUEST_URI'],
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'path_info' => $_SERVER['PATH_INFO'] ?? 'none',
]);
