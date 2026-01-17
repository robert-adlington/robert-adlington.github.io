<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../api/utils.php';

handlePreflight();

try {
    route([
        'GET'    => 'handleGet',
        'POST'   => 'handleCreate',
        'DELETE' => 'handleDelete',
    ]);
} catch (Exception $e) {
    error($e->getMessage(), 400);
}

function handleGet(): void {
    $user = Auth::requireAuth();
    $db = db();

    if (!isset($_GET['game_id'])) {
        error('Game ID required', 400);
    }

    $gameId = intval($_GET['game_id']);

    // Verify game ownership
    $stmt = $db->prepare('
        SELECT g.*, s.user_id
        FROM cribbage_games g
        JOIN cribbage_sessions s ON g.session_id = s.id
        WHERE g.id = ? AND s.user_id = ?
    ');
    $stmt->execute([$gameId, $user['id']]);

    if (!$stmt->fetch()) {
        error('Game not found', 404);
    }

    // Get all moves for this game
    $stmt = $db->prepare('
        SELECT * FROM cribbage_moves
        WHERE game_id = ?
        ORDER BY move_number ASC
    ');
    $stmt->execute([$gameId]);
    $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    success(['moves' => $moves]);
}

function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();

    validateRequired($data, ['game_id', 'player', 'points']);

    $gameId = intval($data['game_id']);
    $player = intval($data['player']);
    $points = intval($data['points']);

    if ($player !== 1 && $player !== 2) {
        error('Player must be 1 or 2', 400);
    }

    if ($points <= 0) {
        error('Points must be positive', 400);
    }

    $db = db();

    // Get game and verify ownership
    $stmt = $db->prepare('
        SELECT g.*, s.user_id, s.id as session_id
        FROM cribbage_games g
        JOIN cribbage_sessions s ON g.session_id = s.id
        WHERE g.id = ? AND s.user_id = ?
    ');
    $stmt->execute([$gameId, $user['id']]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        error('Game not found', 404);
    }

    if ($game['is_complete']) {
        error('Game is already complete', 400);
    }

    // Calculate new scores
    $player1Score = intval($game['player1_score']);
    $player2Score = intval($game['player2_score']);

    if ($player === 1) {
        $player1Score += $points;
    } else {
        $player2Score += $points;
    }

    // Cap at 121
    $player1Score = min($player1Score, 121);
    $player2Score = min($player2Score, 121);

    // Get next move number
    $stmt = $db->prepare('
        SELECT COALESCE(MAX(move_number), 0) + 1 as next_move
        FROM cribbage_moves
        WHERE game_id = ?
    ');
    $stmt->execute([$gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $moveNumber = intval($result['next_move']);

    // Create move record
    $stmt = $db->prepare('
        INSERT INTO cribbage_moves (
            game_id, player, points,
            player1_score_after, player2_score_after,
            move_number
        ) VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $gameId, $player, $points,
        $player1Score, $player2Score,
        $moveNumber
    ]);

    $moveId = $db->lastInsertId();

    // Update game scores
    $isComplete = false;
    $winner = null;
    $isSkunk = false;

    if ($player1Score >= 121) {
        $isComplete = true;
        $winner = 1;
        $isSkunk = $player2Score < 90;
    } elseif ($player2Score >= 121) {
        $isComplete = true;
        $winner = 2;
        $isSkunk = $player1Score < 90;
    }

    $stmt = $db->prepare('
        UPDATE cribbage_games
        SET player1_score = ?,
            player2_score = ?,
            is_complete = ?,
            winner = ?,
            is_skunk = ?,
            completed_at = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $player1Score,
        $player2Score,
        $isComplete,
        $winner,
        $isSkunk,
        $isComplete ? date('Y-m-d H:i:s') : null,
        $gameId
    ]);

    // Update session win counts if game is complete
    if ($isComplete) {
        $sessionId = $game['session_id'];
        $gamesWon = $isSkunk ? 2 : 1;

        if ($winner === 1) {
            $stmt = $db->prepare('
                UPDATE cribbage_sessions
                SET player1_total_wins = player1_total_wins + ?
                WHERE id = ?
            ');
            $stmt->execute([$gamesWon, $sessionId]);
        } else {
            $stmt = $db->prepare('
                UPDATE cribbage_sessions
                SET player2_total_wins = player2_total_wins + ?
                WHERE id = ?
            ');
            $stmt->execute([$gamesWon, $sessionId]);
        }
    }

    // Fetch updated game
    $stmt = $db->prepare('SELECT * FROM cribbage_games WHERE id = ?');
    $stmt->execute([$gameId]);
    $updatedGame = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch created move
    $stmt = $db->prepare('SELECT * FROM cribbage_moves WHERE id = ?');
    $stmt->execute([$moveId]);
    $move = $stmt->fetch(PDO::FETCH_ASSOC);

    success([
        'game' => $updatedGame,
        'move' => $move
    ], 'Points added');
}

function handleDelete(): void {
    $user = Auth::requireAuth();

    if (!isset($_GET['id'])) {
        error('Move ID required', 400);
    }

    $moveId = intval($_GET['id']);
    $db = db();

    // Get move and verify ownership
    $stmt = $db->prepare('
        SELECT m.*, g.session_id, s.user_id, g.is_complete
        FROM cribbage_moves m
        JOIN cribbage_games g ON m.game_id = g.id
        JOIN cribbage_sessions s ON g.session_id = s.id
        WHERE m.id = ? AND s.user_id = ?
    ');
    $stmt->execute([$moveId, $user['id']]);
    $move = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$move) {
        error('Move not found', 404);
    }

    $gameId = $move['game_id'];
    $sessionId = $move['session_id'];
    $wasComplete = $move['is_complete'];

    // Get current game state
    $stmt = $db->prepare('SELECT * FROM cribbage_games WHERE id = ?');
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    // If game was complete, we need to reverse the win count
    if ($wasComplete) {
        $gamesWon = $game['is_skunk'] ? 2 : 1;
        $winner = $game['winner'];

        if ($winner === 1) {
            $stmt = $db->prepare('
                UPDATE cribbage_sessions
                SET player1_total_wins = GREATEST(0, player1_total_wins - ?)
                WHERE id = ?
            ');
            $stmt->execute([$gamesWon, $sessionId]);
        } else {
            $stmt = $db->prepare('
                UPDATE cribbage_sessions
                SET player2_total_wins = GREATEST(0, player2_total_wins - ?)
                WHERE id = ?
            ');
            $stmt->execute([$gamesWon, $sessionId]);
        }
    }

    // Delete the move
    $stmt = $db->prepare('DELETE FROM cribbage_moves WHERE id = ?');
    $stmt->execute([$moveId]);

    // Get previous move to restore game state
    $stmt = $db->prepare('
        SELECT * FROM cribbage_moves
        WHERE game_id = ?
        ORDER BY move_number DESC
        LIMIT 1
    ');
    $stmt->execute([$gameId]);
    $previousMove = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($previousMove) {
        // Restore to previous move's scores
        $stmt = $db->prepare('
            UPDATE cribbage_games
            SET player1_score = ?,
                player2_score = ?,
                is_complete = FALSE,
                winner = NULL,
                is_skunk = FALSE,
                completed_at = NULL
            WHERE id = ?
        ');
        $stmt->execute([
            $previousMove['player1_score_after'],
            $previousMove['player2_score_after'],
            $gameId
        ]);
    } else {
        // No previous moves, reset to 0
        $stmt = $db->prepare('
            UPDATE cribbage_games
            SET player1_score = 0,
                player2_score = 0,
                is_complete = FALSE,
                winner = NULL,
                is_skunk = FALSE,
                completed_at = NULL
            WHERE id = ?
        ');
        $stmt->execute([$gameId]);
    }

    // Fetch updated game
    $stmt = $db->prepare('SELECT * FROM cribbage_games WHERE id = ?');
    $stmt->execute([$gameId]);
    $updatedGame = $stmt->fetch(PDO::FETCH_ASSOC);

    success([
        'game' => $updatedGame
    ], 'Move undone');
}
