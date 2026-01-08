<?php
/**
 * Debug endpoint to check authentication status
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers/db.php';

header('Content-Type: application/json');

$debug = [
    'cookies' => $_COOKIE,
    'session_token_present' => isset($_COOKIE['session_token']),
    'session_token_value' => isset($_COOKIE['session_token']) ? substr($_COOKIE['session_token'], 0, 20) . '...' : null,
];

if (isset($_COOKIE['session_token'])) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT s.user_id, s.expires_at, u.username, u.email
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$_COOKIE['session_token']]);
        $session = $stmt->fetch();

        $debug['session_found'] = $session ? true : false;
        if ($session) {
            $debug['user_id'] = $session['user_id'];
            $debug['username'] = $session['username'];
            $debug['email'] = $session['email'];
            $debug['expires_at'] = $session['expires_at'];
            $debug['is_expired'] = strtotime($session['expires_at']) <= time();
        }
    } catch (Exception $e) {
        $debug['error'] = $e->getMessage();
    }
}

$debug['auth_check'] = isAuthenticated();
$debug['user_id_from_auth'] = getCurrentUserId();

echo json_encode($debug, JSON_PRETTY_PRINT);
