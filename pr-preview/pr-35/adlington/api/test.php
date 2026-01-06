<?php
/**
 * API Test Script
 * Access this file directly to test if PHP and the API are working
 * Example: https://yourdomain.com/adlington/api/test.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Contract Whist API Test</h1>

    <h2>1. PHP Configuration</h2>
    <p class="success">✓ PHP is working! Version: <?php echo PHP_VERSION; ?></p>

    <h2>2. File Structure</h2>
    <?php
    $required_files = [
        'config.php' => 'Database configuration (must be created from config.example.php)',
        'config.example.php' => 'Configuration template',
        'database.php' => 'Database connection handler',
        'auth.php' => 'Authentication class',
        'auth-api.php' => 'Authentication API endpoints',
        'games.php' => 'Games API endpoints',
        'utils.php' => 'Utility functions',
        'schema.sql' => 'Database schema'
    ];

    foreach ($required_files as $file => $description) {
        $exists = file_exists(__DIR__ . '/' . $file);
        $class = $exists ? 'success' : 'error';
        $icon = $exists ? '✓' : '✗';
        echo "<p class='$class'>$icon <strong>$file</strong> - $description</p>";
    }
    ?>

    <h2>3. Configuration File</h2>
    <?php
    if (file_exists(__DIR__ . '/config.php')) {
        echo "<p class='success'>✓ config.php exists</p>";

        // Try to load it
        try {
            require_once __DIR__ . '/config.php';
            echo "<p class='success'>✓ config.php loads without errors</p>";

            // Check constants
            $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
            foreach ($required_constants as $const) {
                if (defined($const)) {
                    $value = constant($const);
                    // Hide password
                    if ($const === 'DB_PASS') {
                        $value = str_repeat('*', strlen($value));
                    }
                    echo "<p class='success'>✓ $const is defined: $value</p>";
                } else {
                    echo "<p class='error'>✗ $const is NOT defined</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error loading config.php: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p class='error'>✗ config.php does NOT exist!</p>";
        echo "<p class='warning'>⚠ You need to create it from config.example.php</p>";
        echo "<pre>cd " . __DIR__ . "\ncp config.example.php config.php\nnano config.php  # Edit with your database credentials</pre>";
    }
    ?>

    <h2>4. Database Connection</h2>
    <?php
    if (file_exists(__DIR__ . '/config.php') && file_exists(__DIR__ . '/database.php')) {
        try {
            require_once __DIR__ . '/database.php';
            $pdo = db();
            echo "<p class='success'>✓ Database connection successful!</p>";

            // Check tables
            $tables = ['users', 'sessions', 'whist_games'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✓ Table '$table' exists</p>";
                } else {
                    echo "<p class='error'>✗ Table '$table' does NOT exist</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p class='warning'>⚠ Check your database credentials in config.php</p>";
        }
    } else {
        echo "<p class='warning'>⚠ Skipped - missing required files</p>";
    }
    ?>

    <h2>5. API Endpoints Test</h2>
    <p>Test the authentication API:</p>
    <button onclick="testAPI()">Test Login Endpoint</button>
    <div id="api-result"></div>

    <script>
    async function testAPI() {
        const resultDiv = document.getElementById('api-result');
        resultDiv.innerHTML = '<p>Testing...</p>';

        try {
            const response = await fetch('/adlington/api/auth-api.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: 'test',
                    password: 'test'
                })
            });

            const contentType = response.headers.get('content-type');
            const text = await response.text();

            if (contentType && contentType.includes('application/json')) {
                const data = JSON.parse(text);
                resultDiv.innerHTML = `
                    <p class="success">✓ API returned JSON</p>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } else {
                resultDiv.innerHTML = `
                    <p class="error">✗ API returned HTML instead of JSON</p>
                    <p>This usually means there's a PHP error. Response:</p>
                    <pre>${text.substring(0, 500)}</pre>
                `;
            }
        } catch (error) {
            resultDiv.innerHTML = `<p class="error">✗ Error: ${error.message}</p>`;
        }
    }
    </script>

    <h2>Next Steps</h2>
    <ol>
        <li>If config.php doesn't exist, create it from config.example.php</li>
        <li>If database connection fails, check your credentials in config.php</li>
        <li>If tables don't exist, run schema.sql on your database</li>
        <li>If API returns HTML, check PHP error logs</li>
    </ol>

</body>
</html>
