<?php
/**
 * Friends (Favorites) API
 *
 * Friends are players that a user has marked as favorites.
 * When creating a game, the user's friends are displayed for easy selection.
 *
 * GET    /api/friends.php              - List user's friends
 * POST   /api/friends.php              - Add friend (create new player or link existing)
 * DELETE /api/friends.php?player_id=123 - Remove friend (doesn't delete the player)
 */

require_once __DIR__ . '/../../../api/auth.php';

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

/**
 * GET - List user's friends
 */
function handleGet(): void {
    $user = Auth::requireAuth();
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.created_at as player_created_at,
            uf.created_at as added_as_friend_at
        FROM user_friends uf
        JOIN players p ON uf.player_id = p.id
        WHERE uf.user_id = ?
        ORDER BY p.name ASC
    ");
    $stmt->execute([$user['id']]);
    $friends = $stmt->fetchAll();

    // Optionally include basic stats for each friend
    foreach ($friends as &$friend) {
        $friend['statistics'] = getBasicStats($pdo, $friend['id']);
    }

    success([
        'friends' => $friends,
        'total' => count($friends)
    ]);
}

/**
 * POST - Add a friend
 *
 * Two modes:
 * 1. Create new player and add as friend: { "name": "PlayerName" }
 * 2. Link existing player as friend: { "player_id": 123 }
 */
function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();
    $pdo = db();

    $playerId = $data['player_id'] ?? null;
    $playerName = isset($data['name']) ? sanitize($data['name']) : null;

    if (!$playerId && !$playerName) {
        error('Either player_id or name is required', 400);
    }

    // If linking existing player
    if ($playerId) {
        // Verify player exists
        $stmt = $pdo->prepare("SELECT id, name FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            error('Player not found', 404);
        }

        // Check if already a friend
        if (isAlreadyFriend($pdo, $user['id'], $playerId)) {
            error('This player is already your friend', 409);
        }

        // Add as friend
        addFriend($pdo, $user['id'], $playerId);

        success([
            'friend' => [
                'id' => (int) $player['id'],
                'name' => $player['name'],
                'is_new_player' => false
            ]
        ], 'Friend added');

    } else {
        // Creating new player by name
        if (strlen($playerName) < 1 || strlen($playerName) > 100) {
            error('Player name must be between 1 and 100 characters', 400);
        }

        // Check if player with this name already exists
        $stmt = $pdo->prepare("SELECT id, name FROM players WHERE name = ?");
        $stmt->execute([$playerName]);
        $existingPlayer = $stmt->fetch();

        if ($existingPlayer) {
            // Player exists - client should have used ?check=name first
            // Return conflict with player info so client can ask user to confirm
            $stats = getBasicStats($pdo, $existingPlayer['id']);
            $lastGame = getLastGameInfo($pdo, $existingPlayer['id']);

            error('A player with this name already exists. Use player_id to add the existing player as a friend, or choose a different name.', 409, [
                'existing_player' => [
                    'id' => (int) $existingPlayer['id'],
                    'name' => $existingPlayer['name'],
                    'total_games' => $stats['total_games'],
                    'last_game_location' => $lastGame['location'] ?? null,
                    'last_game_date' => $lastGame['played_at'] ?? null
                ]
            ]);
        }

        // Create new player
        $stmt = $pdo->prepare("
            INSERT INTO players (name, created_by_user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$playerName, $user['id']]);
        $newPlayerId = $pdo->lastInsertId();

        // Add as friend
        addFriend($pdo, $user['id'], $newPlayerId);

        success([
            'friend' => [
                'id' => (int) $newPlayerId,
                'name' => $playerName,
                'is_new_player' => true
            ]
        ], 'Friend created and added');
    }
}

/**
 * DELETE - Remove a friend (does NOT delete the player)
 */
function handleDelete(): void {
    $user = Auth::requireAuth();
    $pdo = db();

    $playerId = $_GET['player_id'] ?? null;

    if (!$playerId) {
        error('player_id is required', 400);
    }

    $stmt = $pdo->prepare("DELETE FROM user_friends WHERE user_id = ? AND player_id = ?");
    $stmt->execute([$user['id'], $playerId]);

    if ($stmt->rowCount() === 0) {
        error('Friend not found', 404);
    }

    success([], 'Friend removed');
}

/**
 * Check if a player is already a friend of the user
 */
function isAlreadyFriend(PDO $pdo, int $userId, int $playerId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM user_friends WHERE user_id = ? AND player_id = ?");
    $stmt->execute([$userId, $playerId]);
    return $stmt->fetch() !== false;
}

/**
 * Add a friend association
 */
function addFriend(PDO $pdo, int $userId, int $playerId): void {
    $stmt = $pdo->prepare("
        INSERT INTO user_friends (user_id, player_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$userId, $playerId]);
}

/**
 * Get basic statistics for a player (cribbage-specific)
 */
function getBasicStats(PDO $pdo, int $playerId): array {
    // Count sessions where player participated
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_sessions
        FROM cribbage_sessions
        WHERE player1_id = ? OR player2_id = ?
    ");
    $stmt->execute([$playerId, $playerId]);
    $sessionCount = $stmt->fetch();

    // Count total games won by this player
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as games_won
        FROM cribbage_games cg
        JOIN cribbage_sessions cs ON cg.session_id = cs.id
        WHERE cg.is_complete = 1
        AND (
            (cs.player1_id = ? AND cg.winner = 1)
            OR (cs.player2_id = ? AND cg.winner = 2)
        )
    ");
    $stmt->execute([$playerId, $playerId]);
    $gamesWon = $stmt->fetch();

    // Count total games played
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_games
        FROM cribbage_games cg
        JOIN cribbage_sessions cs ON cg.session_id = cs.id
        WHERE cg.is_complete = 1
        AND (cs.player1_id = ? OR cs.player2_id = ?)
    ");
    $stmt->execute([$playerId, $playerId]);
    $totalGames = $stmt->fetch();

    return [
        'total_sessions' => (int) ($sessionCount['total_sessions'] ?? 0),
        'total_games' => (int) ($totalGames['total_games'] ?? 0),
        'games_won' => (int) ($gamesWon['games_won'] ?? 0)
    ];
}

/**
 * Get last game info for a player (for confirmation dialog)
 */
function getLastGameInfo(PDO $pdo, int $playerId): ?array {
    $stmt = $pdo->prepare("
        SELECT cs.session_name, cs.created_at
        FROM cribbage_sessions cs
        WHERE cs.player1_id = ? OR cs.player2_id = ?
        ORDER BY cs.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$playerId, $playerId]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    return [
        'played_at' => $session['created_at'],
        'location' => 'Session recorded'
    ];
}
