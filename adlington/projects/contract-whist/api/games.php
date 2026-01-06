<?php
/**
 * Contract Whist Games API
 * 
 * GET    /api/games.php           - List user's games
 * GET    /api/games.php?id=123    - Get specific game
 * POST   /api/games.php           - Create new game
 * PUT    /api/games.php?id=123    - Update game
 * DELETE /api/games.php?id=123    - Delete game
 */

require_once __DIR__ . '/../../../api/auth.php';

handlePreflight();

try {
    route([
        'GET'    => 'handleGet',
        'POST'   => 'handleCreate',
        'PUT'    => 'handleUpdate',
        'DELETE' => 'handleDelete',
    ]);
} catch (Exception $e) {
    error($e->getMessage(), 400);
}

/**
 * GET - List games or get specific game
 */
function handleGet(): void {
    $user = Auth::requireAuth();
    $pdo = db();
    
    $gameId = $_GET['id'] ?? null;
    
    if ($gameId) {
        // Get specific game
        $stmt = $pdo->prepare("
            SELECT id, game_name, players, scores, current_round, is_complete, played_at, updated_at
            FROM whist_games
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$gameId, $user['id']]);
        $game = $stmt->fetch();

        if (!$game) {
            error('Game not found', 404);
        }

        // Decode JSON fields
        $game['players'] = json_decode($game['players'], true);
        $game['scores'] = json_decode($game['scores'], true);
        $game['is_complete'] = (bool) $game['is_complete'];

        // Include player_ids
        $game['player_ids'] = getGamePlayerIds($pdo, $gameId);

        success(['game' => $game]);
    } else {
        // List all games for user
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT id, game_name, players, current_round, is_complete, played_at, updated_at
            FROM whist_games 
            WHERE user_id = ?
            ORDER BY updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user['id'], $limit, $offset]);
        $games = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($games as &$game) {
            $game['players'] = json_decode($game['players'], true);
            $game['is_complete'] = (bool) $game['is_complete'];
        }
        
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM whist_games WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $total = $stmt->fetchColumn();
        
        success([
            'games' => $games,
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}

/**
 * POST - Create new game
 *
 * Accepts either:
 * - player_ids: Array of player IDs (preferred for new architecture)
 * - players: Array of player names (legacy support, will create/link players)
 */
function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();
    $pdo = db();

    $playerIds = $data['player_ids'] ?? null;
    $playerNames = $data['players'] ?? null;

    // Validate: need either player_ids or players
    if (empty($playerIds) && empty($playerNames)) {
        error('Either player_ids or players array is required', 400);
    }

    // Determine player list
    if (!empty($playerIds)) {
        // New architecture: use player IDs
        if (!is_array($playerIds) || count($playerIds) < 2 || count($playerIds) > 7) {
            error('Contract Whist requires 2-7 players', 400);
        }

        // Verify all players exist and get their names
        $placeholders = str_repeat('?,', count($playerIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, name FROM players WHERE id IN ($placeholders)");
        $stmt->execute($playerIds);
        $foundPlayers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (count($foundPlayers) !== count($playerIds)) {
            error('One or more player IDs are invalid', 400);
        }

        // Build ordered player names array for legacy compatibility
        $playerNames = array_map(function($id) use ($foundPlayers) {
            return $foundPlayers[$id];
        }, $playerIds);

    } else {
        // Legacy support: use player names
        if (!is_array($playerNames) || count($playerNames) < 2 || count($playerNames) > 7) {
            error('Contract Whist requires 2-7 players', 400);
        }

        // Ensure all players exist, create if needed
        $playerIds = [];
        foreach ($playerNames as $name) {
            $name = sanitize($name);
            $playerId = getOrCreatePlayer($pdo, $name, $user['id']);
            $playerIds[] = $playerId;
        }
    }

    $gameName = sanitize($data['game_name'] ?? 'Game ' . date('Y-m-d H:i'));
    $players = json_encode($playerNames);
    $scores = json_encode($data['scores'] ?? []);
    $currentRound = (int)($data['current_round'] ?? 1);
    $isComplete = !empty($data['is_complete']);
    $ipAddress = getClientIp();

    // Create the game
    $stmt = $pdo->prepare("
        INSERT INTO whist_games (user_id, game_name, players, scores, current_round, is_complete, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $gameName, $players, $scores, $currentRound, $isComplete, $ipAddress]);

    $gameId = $pdo->lastInsertId();

    // Link players to game
    linkPlayersToGame($pdo, $gameId, $playerIds, $isComplete ? $data['scores'] : null);

    success([
        'game' => [
            'id' => (int) $gameId,
            'game_name' => $gameName,
            'players' => $playerNames,
            'player_ids' => $playerIds,
            'scores' => $data['scores'] ?? [],
            'current_round' => $currentRound,
            'is_complete' => $isComplete
        ]
    ], 'Game created');
}

/**
 * Get or create a player by name
 */
function getOrCreatePlayer(PDO $pdo, string $name, int $userId): int {
    // Check if player exists
    $stmt = $pdo->prepare("SELECT id FROM players WHERE name = ?");
    $stmt->execute([$name]);
    $existing = $stmt->fetch();

    if ($existing) {
        return (int) $existing['id'];
    }

    // Create new player
    $stmt = $pdo->prepare("INSERT INTO players (name, created_by_user_id) VALUES (?, ?)");
    $stmt->execute([$name, $userId]);

    return (int) $pdo->lastInsertId();
}

/**
 * Link players to a game with position and score data
 */
function linkPlayersToGame(PDO $pdo, int $gameId, array $playerIds, ?array $scores): void {
    $finalScores = [];
    $winnerId = null;
    $highestScore = -1;

    // Calculate final scores if game is complete
    if ($scores && !empty($scores)) {
        $lastRound = end($scores);
        if (isset($lastRound['totals'])) {
            foreach ($lastRound['totals'] as $position => $score) {
                $finalScores[$position] = (int) $score;
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $winnerId = $position;
                }
            }
        }
    }

    // Insert game_players records
    $stmt = $pdo->prepare("
        INSERT INTO game_players (game_id, player_id, position, final_score, won)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($playerIds as $position => $playerId) {
        $finalScore = $finalScores[$position] ?? null;
        $won = ($winnerId !== null && $position === $winnerId);
        $stmt->execute([$gameId, $playerId, $position, $finalScore, $won]);
    }
}

/**
 * PUT - Update existing game
 */
function handleUpdate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();
    $pdo = db();

    $gameId = $_GET['id'] ?? null;

    if (!$gameId) {
        error('Game ID is required', 400);
    }

    // Verify ownership and get current state
    $stmt = $pdo->prepare("SELECT id, is_complete FROM whist_games WHERE id = ? AND user_id = ?");
    $stmt->execute([$gameId, $user['id']]);
    $currentGame = $stmt->fetch();

    if (!$currentGame) {
        error('Game not found', 404);
    }

    $wasComplete = (bool) $currentGame['is_complete'];

    // Build update query dynamically
    $updates = [];
    $params = [];

    if (isset($data['game_name'])) {
        $updates[] = 'game_name = ?';
        $params[] = sanitize($data['game_name']);
    }

    if (isset($data['players'])) {
        $updates[] = 'players = ?';
        $params[] = json_encode($data['players']);
    }

    if (isset($data['scores'])) {
        $updates[] = 'scores = ?';
        $params[] = json_encode($data['scores']);
    }

    if (isset($data['current_round'])) {
        $updates[] = 'current_round = ?';
        $params[] = (int) $data['current_round'];
    }

    if (isset($data['is_complete'])) {
        $updates[] = 'is_complete = ?';
        $params[] = (bool) $data['is_complete'];
    }

    if (empty($updates)) {
        error('No fields to update', 400);
    }

    $params[] = $gameId;
    $params[] = $user['id'];

    $sql = "UPDATE whist_games SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // If game is being marked as complete, update player scores
    $isNowComplete = isset($data['is_complete']) ? (bool) $data['is_complete'] : $wasComplete;

    if ($isNowComplete && isset($data['scores'])) {
        updateGamePlayerScores($pdo, $gameId, $data['scores']);
    }

    // Fetch updated game
    $stmt = $pdo->prepare("
        SELECT id, game_name, players, scores, current_round, is_complete, played_at, updated_at
        FROM whist_games
        WHERE id = ?
    ");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();

    $game['players'] = json_decode($game['players'], true);
    $game['scores'] = json_decode($game['scores'], true);
    $game['is_complete'] = (bool) $game['is_complete'];

    // Include player_ids in response
    $game['player_ids'] = getGamePlayerIds($pdo, $gameId);

    success(['game' => $game], 'Game updated');
}

/**
 * Update final scores for game players when game is completed
 */
function updateGamePlayerScores(PDO $pdo, int $gameId, array $scores): void {
    if (empty($scores)) {
        return;
    }

    $lastRound = end($scores);
    if (!isset($lastRound['totals'])) {
        return;
    }

    $highestScore = -1;
    $winnerId = null;

    // Find highest score
    foreach ($lastRound['totals'] as $position => $score) {
        if ($score > $highestScore) {
            $highestScore = $score;
            $winnerId = $position;
        }
    }

    // Update each player's score
    foreach ($lastRound['totals'] as $position => $score) {
        $won = ($position === $winnerId);
        $stmt = $pdo->prepare("
            UPDATE game_players
            SET final_score = ?, won = ?
            WHERE game_id = ? AND position = ?
        ");
        $stmt->execute([$score, $won, $gameId, $position]);
    }
}

/**
 * Get player IDs for a game
 */
function getGamePlayerIds(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT player_id FROM game_players
        WHERE game_id = ?
        ORDER BY position ASC
    ");
    $stmt->execute([$gameId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * DELETE - Delete game
 */
function handleDelete(): void {
    $user = Auth::requireAuth();
    $pdo = db();
    
    $gameId = $_GET['id'] ?? null;
    
    if (!$gameId) {
        error('Game ID is required', 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM whist_games WHERE id = ? AND user_id = ?");
    $stmt->execute([$gameId, $user['id']]);
    
    if ($stmt->rowCount() === 0) {
        error('Game not found', 404);
    }
    
    success([], 'Game deleted');
}
