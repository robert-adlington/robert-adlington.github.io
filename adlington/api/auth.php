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
     * Request password reset - generates token and sends email
     */
    public static function requestPasswordReset(string $email): bool {
        $pdo = db();

        // Find user by email
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Don't reveal if email exists or not (security)
        if (!$user) {
            return true;
        }

        // Generate reset token
        $token = generateToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

        // Delete any existing tokens for this user
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (token, user_id, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$token, $user['id'], $expiresAt]);

        // Send reset email
        self::sendPasswordResetEmail($user['email'], $user['username'], $token);

        return true;
    }

    /**
     * Verify if a reset token is valid
     */
    public static function verifyResetToken(string $token): bool {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT token FROM password_reset_tokens
            WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
        ");
        $stmt->execute([$token]);

        return $stmt->fetch() !== false;
    }

    /**
     * Reset password using token
     */
    public static function resetPassword(string $token, string $newPassword): bool {
        $pdo = db();

        // Verify token is valid
        $stmt = $pdo->prepare("
            SELECT user_id FROM password_reset_tokens
            WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();

        if (!$result) {
            throw new Exception("Invalid or expired reset token");
        }

        $userId = $result['user_id'];

        // Hash new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);

        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
        $stmt->execute([$token]);

        // Invalidate all sessions for this user (force re-login)
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$userId]);

        return true;
    }

    /**
     * Send password reset email
     */
    private static function sendPasswordResetEmail(string $email, string $username, string $token): bool {
        // Check if email configuration is set up
        if (!defined('SITE_URL') || !defined('EMAIL_FROM_ADDRESS')) {
            // Log error but don't fail the request
            error_log('Email configuration not set up. Cannot send password reset email.');
            return false;
        }

        $resetUrl = SITE_URL . '/index.html?reset=' . $token;
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Adlington.fr';
        $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : $siteName;

        $subject = "Password Reset Request - {$siteName}";

        $message = "
Hello {$username},

You recently requested to reset your password for your {$siteName} account.

Click the link below to reset your password:
{$resetUrl}

This link will expire in 1 hour.

If you didn't request this, please ignore this email. Your password will not be changed.

---
{$siteName}
";

        $headers = [
            'From: ' . $fromName . ' <' . EMAIL_FROM_ADDRESS . '>',
            'Reply-To: ' . EMAIL_FROM_ADDRESS,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        try {
            if (defined('EMAIL_METHOD') && EMAIL_METHOD === 'smtp') {
                return self::sendViaSMTP($email, $subject, $message);
            } else {
                return @mail($email, $subject, $message, implode("\r\n", $headers));
            }
        } catch (Exception $e) {
            error_log('Failed to send password reset email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via SMTP (basic implementation)
     */
    private static function sendViaSMTP(string $to, string $subject, string $message): bool {
        // For SMTP, you'd typically use PHPMailer or similar library
        // This is a placeholder - implement if SMTP is needed
        throw new Exception("SMTP sending not implemented. Please use EMAIL_METHOD='mail' or implement SMTP with PHPMailer.");
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

    /**
     * Clean up expired password reset tokens (call periodically)
     */
    public static function cleanupExpiredResetTokens(): int {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
