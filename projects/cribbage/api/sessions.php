<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../api/utils.php';

handlePreflight();

try {
    route([
        'GET'    => 'handleGet',
        'POST'   => 'handleCreate',
        'PUT'    => 'handleUpdate',
        'DELETE' => 'handleDelete',
    ]);
} catch (Exception $e) {
    error_log('Cribbage API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString(), 500);
}

function handleGet(): void {
    $user = Auth::requireAuth();
    $db = db();

    if (isset($_GET['id'])) {
        // Get specific session with current game and moves
        $sessionId = intval($_GET['id']);

        $stmt = $db->prepare('
            SELECT * FROM cribbage_sessions
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$sessionId, $user['id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            error('Session not found', 404);
        }

        // Get current (latest) game
        $stmt = $db->prepare('
            SELECT * FROM cribbage_games
            WHERE session_id = ?
            ORDER BY game_number DESC
            LIMIT 1
        ');
        $stmt->execute([$sessionId]);
        $currentGame = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get moves for current game
        $moves = [];
        if ($currentGame) {
            $stmt = $db->prepare('
                SELECT * FROM cribbage_moves
                WHERE game_id = ?
                ORDER BY move_number ASC
            ');
            $stmt->execute([$currentGame['id']]);
            $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        success([
            'session' => $session,
            'current_game' => $currentGame,
            'moves' => $moves
        ]);
    } else {
        // Get all sessions for user
        $stmt = $db->prepare('
            SELECT * FROM cribbage_sessions
            WHERE user_id = ?
            ORDER BY updated_at DESC
        ');
        $stmt->execute([$user['id']]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        success(['sessions' => $sessions]);
    }
}

function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();

    validateRequired($data, ['player1_name', 'player2_name']);

    $db = db();

    // Create session
    $stmt = $db->prepare('
        INSERT INTO cribbage_sessions (
            user_id, player1_name, player2_name,
            player1_id, player2_id
        ) VALUES (?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $user['id'],
        $data['player1_name'],
        $data['player2_name'],
        $data['player1_id'] ?? null,
        $data['player2_id'] ?? null
    ]);

    $sessionId = $db->lastInsertId();

    // Create first game
    $stmt = $db->prepare('
        INSERT INTO cribbage_games (
            session_id, game_number, player1_is_dealer
        ) VALUES (?, 1, TRUE)
    ');
    $stmt->execute([$sessionId]);

    $gameId = $db->lastInsertId();

    // Fetch created session and game
    $stmt = $db->prepare('SELECT * FROM cribbage_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare('SELECT * FROM cribbage_games WHERE id = ?');
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    success([
        'session' => $session,
        'game' => $game
    ], 'Session created successfully');
}

function handleUpdate(): void {
    $user = Auth::requireAuth();
    $sessionId = intval($_GET['id'] ?? 0);
    $data = getJsonBody();

    $db = db();

    // Verify ownership
    $stmt = $db->prepare('
        SELECT * FROM cribbage_sessions
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$sessionId, $user['id']]);

    if (!$stmt->fetch()) {
        error('Session not found', 404);
    }

    // Update session
    $updates = [];
    $values = [];

    if (isset($data['session_name'])) {
        $updates[] = 'session_name = ?';
        $values[] = $data['session_name'];
    }

    if (empty($updates)) {
        error('No valid fields to update', 400);
    }

    $values[] = $sessionId;
    $sql = 'UPDATE cribbage_sessions SET ' . implode(', ', $updates) . ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    success(['updated' => true]);
}

function handleDelete(): void {
    $user = Auth::requireAuth();
    $sessionId = intval($_GET['id'] ?? 0);

    $db = db();

    // Verify ownership
    $stmt = $db->prepare('
        SELECT * FROM cribbage_sessions
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$sessionId, $user['id']]);

    if (!$stmt->fetch()) {
        error('Session not found', 404);
    }

    // Delete session (cascade will handle games and moves)
    $stmt = $db->prepare('DELETE FROM cribbage_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);

    success(['deleted' => true]);
}
