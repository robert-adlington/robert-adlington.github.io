<?php
/**
 * Authentication API Endpoint
 *
 * POST /api/auth.php?action=register        - Register new user
 * POST /api/auth.php?action=login           - Login
 * POST /api/auth.php?action=logout          - Logout
 * GET  /api/auth.php?action=me              - Get current user
 * POST /api/auth.php?action=request-reset   - Request password reset
 * POST /api/auth.php?action=verify-token    - Verify reset token
 * POST /api/auth.php?action=reset-password  - Reset password with token
 */

require_once __DIR__ . '/auth.php';

handlePreflight();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'register':
            handleRegister();
            break;
            
        case 'login':
            handleLogin();
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'me':
            handleMe();
            break;

        case 'request-reset':
            handleRequestReset();
            break;

        case 'verify-token':
            handleVerifyToken();
            break;

        case 'reset-password':
            handleResetPassword();
            break;

        default:
            error('Invalid action. Use: register, login, logout, me, request-reset, verify-token, or reset-password', 400);
    }
} catch (Exception $e) {
    error($e->getMessage(), 400);
}

/**
 * Handle user registration
 */
function handleRegister(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }
    
    $data = getJsonBody();
    
    // Validate required fields
    $errors = validateRequired($data, ['username', 'email', 'password']);
    
    // Validate username format
    if (isset($data['username'])) {
        $username = trim($data['username']);
        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors['username'] = 'Username must be 3-50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }
    }
    
    // Validate email
    if (isset($data['email']) && !validateEmail($data['email'])) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Validate password
    if (isset($data['password'])) {
        if (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
    }
    
    if (!empty($errors)) {
        error('Validation failed', 400, $errors);
    }
    
    $result = Auth::register(
        sanitize($data['username']),
        sanitize($data['email']),
        $data['password']
    );
    
    // Set session cookie
    setSessionCookie($result['session_token']);
    
    success($result, 'Registration successful');
}

/**
 * Handle user login
 */
function handleLogin(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }
    
    $data = getJsonBody();
    
    // Accept either username or email as identifier
    $identifier = $data['username'] ?? $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        error('Username/email and password are required', 400);
    }
    
    $result = Auth::login($identifier, $password);
    
    // Set session cookie
    setSessionCookie($result['session_token']);
    
    success($result, 'Login successful');
}

/**
 * Handle logout
 */
function handleLogout(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }
    
    $token = $_COOKIE['session_token'] ?? null;
    
    if ($token) {
        Auth::logout($token);
        
        // Clear cookie
        setcookie('session_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    success([], 'Logged out successfully');
}

/**
 * Get current user info
 */
function handleMe(): void {
    if (getMethod() !== 'GET') {
        error('Method not allowed', 405);
    }
    
    $user = Auth::getCurrentUser();
    
    if (!$user) {
        error('Not authenticated', 401);
    }
    
    success(['user' => $user]);
}

/**
 * Set session cookie
 */
function setSessionCookie(string $token): void {
    setcookie('session_token', $token, [
        'expires' => time() + SESSION_DURATION,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Handle password reset request
 */
function handleRequestReset(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }

    $data = getJsonBody();

    if (empty($data['email'])) {
        error('Email is required', 400);
    }

    if (!validateEmail($data['email'])) {
        error('Invalid email format', 400);
    }

    $result = Auth::requestPasswordReset(sanitize($data['email']));

    // Always return success (don't reveal if email exists)
    success(['message' => 'If this email exists, a password reset link has been sent.']);
}

/**
 * Handle verify reset token
 */
function handleVerifyToken(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }

    $data = getJsonBody();

    if (empty($data['token'])) {
        error('Token is required', 400);
    }

    $isValid = Auth::verifyResetToken($data['token']);

    success(['valid' => $isValid]);
}

/**
 * Handle password reset
 */
function handleResetPassword(): void {
    if (getMethod() !== 'POST') {
        error('Method not allowed', 405);
    }

    $data = getJsonBody();

    $errors = validateRequired($data, ['token', 'password']);

    // Validate password
    if (isset($data['password']) && strlen($data['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    if (!empty($errors)) {
        error('Validation failed', 400, $errors);
    }

    $result = Auth::resetPassword($data['token'], $data['password']);

    success(['message' => 'Password has been reset successfully. You can now log in.']);
}
