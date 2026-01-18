<?php
/**
 * Players API
 *
 * GET    /api/players.php              - List/search players
 * GET    /api/players.php?id=123       - Get specific player with stats
 * GET    /api/players.php?check=name   - Check if player exists (for friend creation flow)
 * POST   /api/players.php              - Create new player
 */

require_once __DIR__ . '/../../../api/auth.php';

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
            $lastSession = getPlayerLastSession($pdo, $player['id']);

            success([
                'exists' => true,
                'player' => [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'total_sessions' => $stats['total_sessions'],
                    'total_games' => $stats['total_games'],
                    'last_session_date' => $lastSession['created_at'] ?? null
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
    $lastSession = getPlayerLastSession($pdo, $playerId);

    return array_merge($player, [
        'statistics' => $stats,
        'last_session' => $lastSession
    ]);
}

/**
 * Get player statistics (cribbage-specific)
 */
function getPlayerStats(PDO $pdo, int $playerId): array {
    // Count total sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_sessions
        FROM cribbage_sessions
        WHERE player1_id = ? OR player2_id = ?
    ");
    $stmt->execute([$playerId, $playerId]);
    $sessions = $stmt->fetch();

    // Get total games and wins
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_games,
            SUM(CASE WHEN
                (cs.player1_id = ? AND cg.winner = 1) OR
                (cs.player2_id = ? AND cg.winner = 2)
                THEN 1 ELSE 0 END) as games_won,
            SUM(CASE WHEN cg.is_skunk = 1 AND
                ((cs.player1_id = ? AND cg.winner = 1) OR
                 (cs.player2_id = ? AND cg.winner = 2))
                THEN 1 ELSE 0 END) as skunks
        FROM cribbage_games cg
        JOIN cribbage_sessions cs ON cg.session_id = cs.id
        WHERE cg.is_complete = 1
        AND (cs.player1_id = ? OR cs.player2_id = ?)
    ");
    $stmt->execute([$playerId, $playerId, $playerId, $playerId, $playerId, $playerId]);
    $games = $stmt->fetch();

    $totalGames = (int) ($games['total_games'] ?? 0);
    $gamesWon = (int) ($games['games_won'] ?? 0);

    return [
        'total_sessions' => (int) ($sessions['total_sessions'] ?? 0),
        'total_games' => $totalGames,
        'games_won' => $gamesWon,
        'win_percentage' => $totalGames > 0 ? round(($gamesWon / $totalGames) * 100, 1) : 0,
        'skunks' => (int) ($games['skunks'] ?? 0)
    ];
}

/**
 * Get player's last session info
 */
function getPlayerLastSession(PDO $pdo, int $playerId): ?array {
    $stmt = $pdo->prepare("
        SELECT id, session_name, created_at
        FROM cribbage_sessions
        WHERE player1_id = ? OR player2_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$playerId, $playerId]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    return [
        'session_id' => (int) $session['id'],
        'session_name' => $session['session_name'],
        'created_at' => $session['created_at']
    ];
}
