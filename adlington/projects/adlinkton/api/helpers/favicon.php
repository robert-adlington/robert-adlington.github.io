<?php
/**
 * Favicon Fetching and Generation Helper
 */

/**
 * Fetch favicon for a URL
 * @param string $url The URL to fetch favicon for
 * @param int $timeoutSeconds Timeout in seconds
 * @return string|null Path to saved favicon or null if failed
 */
function fetchFavicon($url, $timeoutSeconds = 3) {
    $domain = parse_url($url, PHP_URL_HOST);
    if (!$domain) {
        return null;
    }

    // Try common favicon locations
    $candidates = [
        "https://{$domain}/favicon.ico",
        "https://{$domain}/favicon.png",
        "https://{$domain}/apple-touch-icon.png",
    ];

    foreach ($candidates as $faviconUrl) {
        $favicon = fetchWithTimeout($faviconUrl, $timeoutSeconds);
        if ($favicon && isValidImage($favicon)) {
            return saveFavicon($favicon, $domain);
        }
    }

    // If all fetches failed, generate fallback
    return generateFallbackFavicon($domain);
}

/**
 * Fetch URL with timeout
 * @param string $url URL to fetch
 * @param int $timeout Timeout in seconds
 * @return string|false Content or false on failure
 */
function fetchWithTimeout($url, $timeout = 3) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false, // For development; enable in production
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Adlinkton Favicon Fetcher)',
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $result) {
        return $result;
    }

    return false;
}

/**
 * Validate if content is a valid image
 * @param string $content Image content
 * @return bool
 */
function isValidImage($content) {
    // Check size limit (100KB)
    if (strlen($content) > 100 * 1024) {
        return false;
    }

    // Check if it's a valid image by attempting to get image info
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($content);

    $validTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
    return in_array($mimeType, $validTypes);
}

/**
 * Save favicon to storage
 * @param string $content Favicon content
 * @param string $domain Domain name
 * @return string Path to saved favicon
 */
function saveFavicon($content, $domain) {
    $storageDir = __DIR__ . '/../../public/favicons';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    // Generate filename based on domain hash
    $hash = md5($domain);

    // Detect extension from content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($content);

    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    $ext = $extensions[$mimeType] ?? 'png';
    $filename = "{$hash}.{$ext}";
    $filepath = "{$storageDir}/{$filename}";

    file_put_contents($filepath, $content);
    chmod($filepath, 0644);

    return "/adlington/projects/adlinkton/public/favicons/{$filename}";
}

/**
 * Generate fallback favicon using domain initial
 * @param string $domain Domain name
 * @return string Path to saved SVG favicon
 */
function generateFallbackFavicon($domain) {
    $storageDir = __DIR__ . '/../../public/favicons';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $letter = strtoupper($domain[0]);
    $color = '#' . substr(md5($domain), 0, 6);

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
  <rect width="32" height="32" fill="{$color}" rx="4"/>
  <text x="16" y="22" text-anchor="middle" fill="white" font-family="Arial, sans-serif" font-size="18" font-weight="bold">{$letter}</text>
</svg>
SVG;

    $hash = md5($domain);
    $filename = "{$hash}.svg";
    $filepath = "{$storageDir}/{$filename}";

    file_put_contents($filepath, $svg);
    chmod($filepath, 0644);

    return "/adlington/projects/adlinkton/public/favicons/{$filename}";
}

/**
 * Get existing favicon path or generate new one
 * @param string $domain Domain name
 * @return string|null Existing favicon path or null if not found
 */
function getExistingFavicon($domain) {
    $hash = md5($domain);
    $storageDir = __DIR__ . '/../../public/favicons';

    $extensions = ['png', 'jpg', 'gif', 'ico', 'svg'];

    foreach ($extensions as $ext) {
        $filepath = "{$storageDir}/{$hash}.{$ext}";
        if (file_exists($filepath)) {
            return "/adlington/projects/adlinkton/public/favicons/{$hash}.{$ext}";
        }
    }

    return null;
}
