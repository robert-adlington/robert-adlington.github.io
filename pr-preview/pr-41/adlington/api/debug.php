<?php
/**
 * Simple debug script to see actual PHP errors
 */

// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Information</h1>";

// Test 1: Check if config.php exists and can be loaded
echo "<h2>1. Loading config.php</h2>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✓ config.php exists<br>";
    try {
        require_once __DIR__ . '/config.php';
        echo "✓ config.php loaded successfully<br>";

        // Check required constants
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SESSION_DURATION', 'ALLOWED_ORIGINS', 'DEBUG_MODE'];
        foreach ($required as $const) {
            if (defined($const)) {
                echo "✓ $const is defined<br>";
            } else {
                echo "✗ <strong style='color:red'>$const is NOT defined</strong><br>";
            }
        }
    } catch (Exception $e) {
        echo "✗ Error loading config.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ <strong style='color:red'>config.php does NOT exist!</strong><br>";
    echo "You need to create it from config.example.php<br>";
    die();
}

// Test 2: Try loading database.php
echo "<h2>2. Loading database.php</h2>";
try {
    require_once __DIR__ . '/database.php';
    echo "✓ database.php loaded<br>";
    $pdo = db();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Try loading utils.php
echo "<h2>3. Loading utils.php</h2>";
try {
    require_once __DIR__ . '/utils.php';
    echo "✓ utils.php loaded<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Try loading auth.php
echo "<h2>4. Loading auth.php</h2>";
try {
    require_once __DIR__ . '/auth.php';
    echo "✓ auth.php loaded<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 5: Try loading auth-api.php
echo "<h2>5. Testing auth-api.php</h2>";
echo "Now trying to simulate what happens when you call the API...<br><br>";

ob_start();
try {
    // Simulate a POST request to login
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'login';

    // Capture what auth-api.php outputs
    include __DIR__ . '/auth-api.php';

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
$output = ob_get_clean();

echo "<h3>API Output:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<hr>";
echo "<h2>Summary</h2>";
echo "If you see errors above, that's what's causing the 'HTML instead of JSON' error.<br>";
echo "Fix those errors and the login should work.";
?>
