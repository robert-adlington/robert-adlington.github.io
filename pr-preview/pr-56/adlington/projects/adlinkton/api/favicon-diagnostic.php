<!DOCTYPE html>
<html>
<head>
    <title>Favicon Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        img { border: 2px solid #ccc; }
        img.error { border-color: red; }
    </style>
</head>
<body>
    <h1>🔍 Favicon Diagnostic Report</h1>

    <?php
    require_once __DIR__ . '/helpers/db.php';

    echo '<div class="section">';
    echo '<h2>1. Storage Directory Check</h2>';
    $storageDir = __DIR__ . '/../storage/favicons';
    if (is_dir($storageDir)) {
        echo '<p class="success">✓ Storage directory exists: ' . realpath($storageDir) . '</p>';
        $files = array_diff(scandir($storageDir), ['.', '..']);
        echo '<p>Files in storage: ' . count($files) . '</p>';
        if (count($files) > 0) {
            echo '<ul>';
            foreach ($files as $file) {
                $size = filesize($storageDir . '/' . $file);
                echo "<li>{$file} ({$size} bytes)</li>";
            }
            echo '</ul>';
        }
    } else {
        echo '<p class="error">✗ Storage directory does not exist!</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>2. Database Links Check</h2>';
    try {
        $db = getDB();
        $stmt = $db->query('SELECT COUNT(*) as total FROM links');
        $totalLinks = $stmt->fetch()['total'];

        $stmt = $db->query('SELECT COUNT(*) as with_favicon FROM links WHERE favicon_path IS NOT NULL AND favicon_path != ""');
        $withFavicon = $stmt->fetch()['with_favicon'];

        $stmt = $db->query('SELECT COUNT(*) as without_favicon FROM links WHERE favicon_path IS NULL OR favicon_path = ""');
        $withoutFavicon = $stmt->fetch()['without_favicon'];

        echo "<p><strong>Total links:</strong> {$totalLinks}</p>";
        echo "<p><strong>Links with favicons:</strong> {$withFavicon} <span class='success'>✓</span></p>";
        echo "<p><strong>Links without favicons:</strong> {$withoutFavicon} <span class='error'>✗</span></p>";

        if ($withoutFavicon > 0) {
            echo '<p class="info">📝 Run <code>php api/refetch-favicons.php</code> to fetch missing favicons</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Database error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>3. Sample Links</h2>';
    try {
        $stmt = $db->query('SELECT id, name, url, favicon_path FROM links LIMIT 5');
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($links) > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Name</th><th>URL</th><th>Favicon Path</th><th>Preview</th></tr>';
            foreach ($links as $link) {
                echo '<tr>';
                echo '<td>' . $link['id'] . '</td>';
                echo '<td>' . htmlspecialchars($link['name']) . '</td>';
                echo '<td>' . htmlspecialchars(substr($link['url'], 0, 50)) . '...</td>';
                echo '<td>' . ($link['favicon_path'] ? htmlspecialchars($link['favicon_path']) : '<span class="error">NULL</span>') . '</td>';
                echo '<td>';
                if ($link['favicon_path']) {
                    echo '<img src="' . htmlspecialchars($link['favicon_path']) . '" width="32" height="32" onerror="this.className=\'error\'" title="' . htmlspecialchars($link['favicon_path']) . '">';
                } else {
                    echo '—';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="info">No links in database yet</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Error fetching links: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>4. Web Path Test</h2>';
    echo '<p>Testing if favicons are accessible via web:</p>';
    $testFavicons = glob($storageDir . '/*.{ico,png,svg,jpg,gif}', GLOB_BRACE);
    if (count($testFavicons) > 0) {
        $testFile = basename($testFavicons[0]);
        $webPath = '/adlington/projects/adlinkton/storage/favicons/' . $testFile;
        echo '<p>Test file: <code>' . htmlspecialchars($webPath) . '</code></p>';
        echo '<img src="' . htmlspecialchars($webPath) . '" width="32" height="32" onerror="this.className=\'error\'; this.title=\'Failed to load\'" title="Should show favicon">';
        echo '<script>';
        echo 'fetch("' . $webPath . '").then(r => {';
        echo '  const status = document.createElement("p");';
        echo '  status.innerHTML = r.ok ? "<span class=\'success\'>✓ Favicon is accessible via web (HTTP " + r.status + ")</span>" : "<span class=\'error\'>✗ Favicon HTTP request failed (" + r.status + ")</span>";';
        echo '  document.currentScript.parentElement.appendChild(status);';
        echo '});';
        echo '</script>';
    } else {
        echo '<p class="info">No favicon files to test</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>5. Action Items</h2>';
    if ($withoutFavicon > 0) {
        echo '<p class="info">⚠️ You have ' . $withoutFavicon . ' link(s) without favicons.</p>';
        echo '<p><strong>To fix:</strong></p>';
        echo '<ol>';
        echo '<li>Run: <code>php adlington/projects/adlinkton/api/refetch-favicons.php</code></li>';
        echo '<li>Or create new links (they will automatically have favicons)</li>';
        echo '</ol>';
    } else {
        echo '<p class="success">✓ All links have favicons!</p>';
    }
    echo '</div>';
    ?>
</body>
</html>
