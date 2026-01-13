<?php
// api/index.php
// Vercel Bridge Router -> Routes requests to the legacy root PHP files

// 1. Get the path from the request URI (e.g. /pos.php)
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

// 2. Normalize path (strip leading slash)
$file_path = ltrim($path, '/');

// 3. Default to index.php if root is requested
if (empty($file_path) || $file_path === '/') {
    $file_path = 'index.php';
}

// 4. Security: Prevent directory traversal
if (strpos($file_path, '..') !== false) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}

// 5. Check if file exists in the parent (root) directory
// We look for files ending in .php or static assets if needed (though static usually handled by Vercel directly)
// Here we assume we are routing PHP files.
$target_file = __DIR__ . '/../' . $file_path;

if (file_exists($target_file) && is_file($target_file)) {
    // 6. Set the working directory to the project root so relative includes (require 'config/config.php') work
    chdir(__DIR__ . '/../');

    // 7. Include the target file
    require_once $target_file;
} else {
    // 8. 404 Handler
    http_response_code(404);
    echo "404 - Page Not Found: " . htmlspecialchars($file_path);
}
