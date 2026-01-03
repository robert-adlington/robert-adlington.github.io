<?php
/**
 * Session and Authentication Management
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/utils.php';

class Auth {
    /**
     * Create a new user
     */
    public static function register(string $username, string $email, string $password): array {
        $pdo = db();
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception("Username already taken");
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email already registered");
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash]);
        
        $userId = $pdo->lastInsertId();
        
        // Create session
        $session = self::createSession($userId);
        
        return [
            'user' => self::getUserById($userId),
            'session_token' => $session['token']
        ];
    }
    
    /**
     * Login user
     */
    public static function login(string $identifier, string $password): array {
        $pdo = db();
        
        // Find user by username or email
        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, is_admin 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid credentials");
        }
        
        // Create session
        $session = self::createSession($user['id']);
        
        unset($user['password_hash']);
        
        return [
            'user' => $user,
            'session_token' => $session['token']
        ];
    }
    
    /**
     * Logout user (invalidate session)
     */
    public static function logout(string $token): void {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$token]);
    }
    
    /**
     * Create a new session
     */
    public static function createSession(int $userId): array {
        $pdo = db();
        
        $token = generateToken(128);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (id, user_id, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$token, $userId, $expiresAt]);
        
        return [
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Validate session and get user
     */
    public static function validateSession(string $token): ?array {
        $pdo = db();
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.is_admin, s.expires_at
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        unset($result['expires_at']);
        return $result;
    }
    
    /**
     * Get user from request (via Authorization header or cookie)
     */
    public static function getCurrentUser(): ?array {
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        return self::validateSession($token);
    }
    
    /**
     * Require authentication (throws error if not logged in)
     */
    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        
        if (!$user) {
            error('Authentication required', 401);
        }
        
        return $user;
    }
    
    /**
     * Require admin privileges
     */
    public static function requireAdmin(): array {
        $user = self::requireAuth();
        
        if (!$user['is_admin']) {
            error('Admin access required', 403);
        }
        
        return $user;
    }
    
    /**
     * Get token from request headers or cookies
     */
    private static function getTokenFromRequest(): ?string {
        // Check Authorization header first
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Check cookie
        return $_COOKIE['session_token'] ?? null;
    }
    
    /**
     * Get user by ID
     */
    public static function getUserById(int $id): ?array {
        $pdo = db();
        
        $stmt = $pdo->prepare("
            SELECT id, username, email, is_admin, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Clean up expired sessions (call periodically)
     */
    public static function cleanupExpiredSessions(): int {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
