<?php
// api/index.php - Vercel Bridge Router

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

// Normalize path
$file_path = ltrim($path, '/');

// Default to index.php
if (empty($file_path)) {
    $file_path = 'index.php';
}

// Security: Prevent directory traversal
$file_path = basename($file_path); // Only allow files in root, no subdirs
// OR for subdirectories, use realpath check below

// Only allow .php files
if (pathinfo($file_path, PATHINFO_EXTENSION) !== 'php') {
    http_response_code(403);
    exit("Access Denied");
}

// Resolve target path
$root_dir = realpath(__DIR__ . '/../');
$target_file = realpath($root_dir . '/' . $file_path);

// Security: Ensure file is within allowed directory
if ($target_file === false || strpos($target_file, $root_dir) !== 0) {
    http_response_code(403);
    exit("Access Denied");
}

if (is_file($target_file)) {
    chdir($root_dir);
    
    // Use include instead of require_once for scripts that output content
    include $target_file;
    exit;
} else {
    http_response_code(404);
    exit("404 - Page Not Found");
}
