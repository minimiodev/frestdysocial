<?php
/**
 * Configuration file for Wallpaper Repository Web App
 */

// Basic site settings
define('SITE_NAME', 'Frest');

// Dynamic base URL detection
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
// Extract base directory path relative to server root
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base_path = str_replace('/admin', '', $script_dir);
$base_path = rtrim(str_replace('\\', '/', $base_path), '/');
define('SITE_URL', $protocol . '://' . $host . $base_path);

define('AVATARS_URL', SITE_URL . '/uploads/avatars');
define('POSTS_URL', SITE_URL . '/uploads/posts');
define('STORIES_URL', SITE_URL . '/uploads/stories');

define('ITEMS_PER_PAGE', 15);

// Database configuration (MySQL)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'frestapp');

// Secure path definitions
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ORIGINAL_DIR', UPLOAD_DIR . 'original/');
define('PREVIEW_DIR', UPLOAD_DIR . 'preview/');
define('PROOFS_DIR', UPLOAD_DIR . 'proofs/');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Set custom session save path to resolve permission issues with default temporary directory
    $session_path = __DIR__ . '/sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0700, true);
    }
    if (is_dir($session_path)) {
        // Protect the sessions folder if it runs under Apache/IIS
        $htaccess_path = $session_path . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            @file_put_contents($htaccess_path, "Deny from all\n");
        }
        session_save_path($session_path);
    }
    
    // Set session parameters to persist for 30 days (remember login)
    ini_set('session.gc_maxlifetime', 2592000); // 30 days in seconds
    ini_set('session.cookie_lifetime', 2592000); // 30 days in seconds
    
    session_start();
}

// ─── SMTP Email Configuration ──────────────────────────────────────────────
// Fill in your SMTP details to enable real email sending.
// Leave SMTP_HOST as 'smtp.example.com' to use mock/fallback display mode.
define('SMTP_HOST',     'smtp.gmail.com');   // e.g. smtp.gmail.com
define('SMTP_PORT',     465);                  // 587 (TLS) or 465 (SSL)
define('SMTP_USER',     'dungflows@gmail.com');                   // your@email.com
define('SMTP_PASS',     'cbui qthd uukc aypv');                   // app password or SMTP password
define('SMTP_FROM',     'dungflows@gmail.com'); // sender address

// Error reporting (set to 0 in production)
$is_local_env = (
    in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || 
    stripos($host, 'localhost') !== false || 
    stripos($host, '127.0.0.1') !== false || 
    php_sapi_name() === 'cli-server'
);

if ($is_local_env) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Tự động tắt hoặc cấu hình tắt chế độ Server-Sent Events (SSE) trên hosting yếu/shared hosting để tránh nghẽn tiến trình PHP
define('DISABLE_SSE_ON_HOSTING', !$is_local_env); // Tự động bật true (vô hiệu hóa SSE) khi chạy trên hosting online để tránh nghẽn tiến trình PHP

if (php_sapi_name() === 'cli-server' || DISABLE_SSE_ON_HOSTING) {
    define('DISABLE_SSE', true);
} else {
    define('DISABLE_SSE', false);
}


