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

require_once __DIR__ . '/auth.php';

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
 */
function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();
    $pdo = db();
    
    // Validate required fields
    if (empty($data['players']) || !is_array($data['players'])) {
        error('Players array is required', 400);
    }
    
    if (count($data['players']) < 2 || count($data['players']) > 7) {
        error('Contract Whist requires 2-7 players', 400);
    }
    
    $gameName = sanitize($data['game_name'] ?? 'Game ' . date('Y-m-d H:i'));
    $players = json_encode($data['players']);
    $scores = json_encode($data['scores'] ?? []);
    $currentRound = (int)($data['current_round'] ?? 1);
    $isComplete = !empty($data['is_complete']);
    
    $stmt = $pdo->prepare("
        INSERT INTO whist_games (user_id, game_name, players, scores, current_round, is_complete)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $gameName, $players, $scores, $currentRound, $isComplete]);
    
    $gameId = $pdo->lastInsertId();
    
    success([
        'game' => [
            'id' => (int) $gameId,
            'game_name' => $gameName,
            'players' => $data['players'],
            'scores' => $data['scores'] ?? [],
            'current_round' => $currentRound,
            'is_complete' => $isComplete
        ]
    ], 'Game created');
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
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM whist_games WHERE id = ? AND user_id = ?");
    $stmt->execute([$gameId, $user['id']]);
    
    if (!$stmt->fetch()) {
        error('Game not found', 404);
    }
    
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
    
    success(['game' => $game], 'Game updated');
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
