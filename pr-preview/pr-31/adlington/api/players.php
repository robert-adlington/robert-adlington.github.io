<?php
/**
 * Players API
 *
 * GET    /api/players.php              - List/search players
 * GET    /api/players.php?id=123       - Get specific player with stats
 * GET    /api/players.php?check=name   - Check if player exists (for friend creation flow)
 * POST   /api/players.php              - Create new player
 */

require_once __DIR__ . '/auth.php';

handlePreflight();

try {
    route([
        'GET'  => 'handleGet',
        'POST' => 'handleCreate',
    ]);
} catch (Exception $e) {
    error($e->getMessage(), 400);
}

/**
 * GET - List players, get specific player, or check if name exists
 */
function handleGet(): void {
    $user = Auth::requireAuth();
    $pdo = db();

    $playerId = $_GET['id'] ?? null;
    $checkName = $_GET['check'] ?? null;
    $search = $_GET['search'] ?? null;

    if ($playerId) {
        // Get specific player with statistics
        $player = getPlayerWithStats($pdo, $playerId);

        if (!$player) {
            error('Player not found', 404);
        }

        success(['player' => $player]);

    } elseif ($checkName) {
        // Check if player name exists (for friend creation workflow)
        $checkName = sanitize($checkName);
        $player = getPlayerByName($pdo, $checkName);

        if ($player) {
            // Get additional info to help user confirm
            $stats = getPlayerStats($pdo, $player['id']);
            $lastGame = getPlayerLastGame($pdo, $player['id']);

            success([
                'exists' => true,
                'player' => [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'total_games' => $stats['total_games'],
                    'last_game_location' => $lastGame['location'] ?? null,
                    'last_game_date' => $lastGame['played_at'] ?? null
                ]
            ]);
        } else {
            success([
                'exists' => false,
                'name' => $checkName
            ]);
        }

    } else {
        // Search/list players
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);

        if ($search) {
            // Search by name
            $search = sanitize($search);
            $stmt = $pdo->prepare("
                SELECT id, name, created_at
                FROM players
                WHERE name LIKE ?
                ORDER BY name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(["%$search%", $limit, $offset]);
        } else {
            // List all players
            $stmt = $pdo->prepare("
                SELECT id, name, created_at
                FROM players
                ORDER BY name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
        }

        $players = $stmt->fetchAll();

        // Get total count
        if ($search) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE name LIKE ?");
            $stmt->execute(["%$search%"]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM players");
        }
        $total = $stmt->fetchColumn();

        success([
            'players' => $players,
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}

/**
 * POST - Create new player
 */
function handleCreate(): void {
    $user = Auth::requireAuth();
    $data = getJsonBody();
    $pdo = db();

    // Validate required fields
    if (empty($data['name'])) {
        error('Player name is required', 400);
    }

    $name = sanitize($data['name']);

    if (strlen($name) < 1 || strlen($name) > 100) {
        error('Player name must be between 1 and 100 characters', 400);
    }

    // Check if player already exists
    $existing = getPlayerByName($pdo, $name);
    if ($existing) {
        error('A player with this name already exists', 409, [
            'existing_player_id' => $existing['id']
        ]);
    }

    // Create player
    $stmt = $pdo->prepare("
        INSERT INTO players (name, created_by_user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$name, $user['id']]);

    $playerId = $pdo->lastInsertId();

    success([
        'player' => [
            'id' => (int) $playerId,
            'name' => $name,
            'created_by_user_id' => (int) $user['id']
        ]
    ], 'Player created');
}

/**
 * Get player by name
 */
function getPlayerByName(PDO $pdo, string $name): ?array {
    $stmt = $pdo->prepare("SELECT id, name, created_at FROM players WHERE name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get player with full statistics
 */
function getPlayerWithStats(PDO $pdo, int $playerId): ?array {
    $stmt = $pdo->prepare("SELECT id, name, created_at FROM players WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();

    if (!$player) {
        return null;
    }

    $stats = getPlayerStats($pdo, $playerId);
    $lastGame = getPlayerLastGame($pdo, $playerId);

    return array_merge($player, [
        'statistics' => $stats,
        'last_game' => $lastGame
    ]);
}

/**
 * Get player statistics
 */
function getPlayerStats(PDO $pdo, int $playerId): array {
    // Get total games and wins from game_players table
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_games,
            SUM(CASE WHEN gp.won = 1 THEN 1 ELSE 0 END) as games_won,
            SUM(COALESCE(gp.final_score, 0)) as total_points,
            AVG(COALESCE(gp.final_score, 0)) as avg_score
        FROM game_players gp
        JOIN whist_games g ON gp.game_id = g.id
        WHERE gp.player_id = ? AND g.is_complete = 1
    ");
    $stmt->execute([$playerId]);
    $stats = $stmt->fetch();

    $totalGames = (int) ($stats['total_games'] ?? 0);
    $gamesWon = (int) ($stats['games_won'] ?? 0);

    return [
        'total_games' => $totalGames,
        'games_won' => $gamesWon,
        'win_percentage' => $totalGames > 0 ? round(($gamesWon / $totalGames) * 100, 1) : 0,
        'total_points' => (int) ($stats['total_points'] ?? 0),
        'average_score' => round((float) ($stats['avg_score'] ?? 0), 1)
    ];
}

/**
 * Get player's last game info (for location display)
 */
function getPlayerLastGame(PDO $pdo, int $playerId): ?array {
    $stmt = $pdo->prepare("
        SELECT g.id, g.game_name, g.ip_address, g.played_at
        FROM whist_games g
        JOIN game_players gp ON g.id = gp.game_id
        WHERE gp.player_id = ?
        ORDER BY g.played_at DESC
        LIMIT 1
    ");
    $stmt->execute([$playerId]);
    $game = $stmt->fetch();

    if (!$game) {
        return null;
    }

    // Convert IP to approximate location (just display the IP for now)
    // In production, you could use a geolocation service
    $location = $game['ip_address'] ? getLocationFromIp($game['ip_address']) : null;

    return [
        'game_id' => (int) $game['id'],
        'game_name' => $game['game_name'],
        'played_at' => $game['played_at'],
        'location' => $location
    ];
}

/**
 * Get approximate location from IP address
 * This is a placeholder - in production, use a geolocation service
 */
function getLocationFromIp(?string $ip): ?string {
    if (!$ip) {
        return null;
    }

    // For privacy and simplicity, just return "recorded" to indicate we have location data
    // In production, you could integrate with ip-api.com, MaxMind, or similar
    // For now, return a generic message
    return "Location recorded";
}
