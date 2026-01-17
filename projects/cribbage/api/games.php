<?php
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../api/database.php';
require_once __DIR__ . '/../../../api/utils.php';

handlePreflight();

try {
    route([
        'GET'    => 'handleGet',
        'POST'   => 'handleCreate',
    ]);
} catch (Exception $e) {
    error($e->getMessage(), 400);
}

function handleGet(): void {
    $user = Auth::requireAuth();
    $db = db();

    if (isset($_GET['id'])) {
        // Get specific game
        $gameId = intval($_GET['id']);

        $stmt = $db->prepare('
            SELECT g.*, s.user_id
            FROM cribbage_games g
            JOIN cribbage_sessions s ON g.session_id = s.id
            WHERE g.id = ? AND s.user_id = ?
        ');
        $stmt->execute([$gameId, $user['id']]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            error('Game not found', 404);
        }

        success(['game' => $game]);
    } else {
        error('Game ID required', 400);
    }
}

function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();

    validateRequired($data, ['session_id']);

    $sessionId = intval($data['session_id']);
    $db = db();

    // Verify session ownership
    $stmt = $db->prepare('
        SELECT * FROM cribbage_sessions
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$sessionId, $user['id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        error('Session not found', 404);
    }

    // Get the last game to determine game number and dealer rotation
    $stmt = $db->prepare('
        SELECT * FROM cribbage_games
        WHERE session_id = ?
        ORDER BY game_number DESC
        LIMIT 1
    ');
    $stmt->execute([$sessionId]);
    $lastGame = $stmt->fetch(PDO::FETCH_ASSOC);

    $gameNumber = $lastGame ? intval($lastGame['game_number']) + 1 : 1;
    // Alternate dealer
    $player1IsDealer = $lastGame ? !$lastGame['player1_is_dealer'] : true;

    // Create new game
    $stmt = $db->prepare('
        INSERT INTO cribbage_games (
            session_id, game_number, player1_is_dealer
        ) VALUES (?, ?, ?)
    ');
    $stmt->execute([$sessionId, $gameNumber, $player1IsDealer]);

    $gameId = $db->lastInsertId();

    // Fetch created game
    $stmt = $db->prepare('SELECT * FROM cribbage_games WHERE id = ?');
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    success(['game' => $game], 'New game started');
}
