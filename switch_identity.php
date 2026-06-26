<?php
/**
 * Switch Active Identity Endpoint - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();
$type = isset($_GET['type']) ? trim($_GET['type']) : 'user';
$page_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$redirect_url = '';

if ($type === 'page' && $page_id > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, page_username FROM pages WHERE id = ? AND owner_id = ?");
        $stmt->execute([$page_id, $me['id']]);
        $page = $stmt->fetch();
        
        if ($page) {
            $_SESSION['active_page_id'] = $page['id'];
            $redirect_url = SITE_URL . "/page.php?username=" . urlencode($page['page_username']);
        }
    } catch (Exception $e) {
        // ignore database errors
    }
} else {
    // Switch back to personal profile
    unset($_SESSION['active_page_id']);
    $redirect_url = SITE_URL . "/profile.php?username=" . urlencode($me['username']);
}

// Redirect back to referring page or homepage
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
// Prevent redirection loop to this endpoint
if (strpos($referer, 'switch_identity.php') !== false) {
    $referer = 'index.php';
}

// Chỉ redirect về trang cá nhân mới nếu referer hiện tại là trang cá nhân (profile.php hoặc page.php)
$referer_path = parse_url($referer, PHP_URL_PATH);
$referer_file = basename($referer_path);

if (($referer_file === 'profile.php' || $referer_file === 'page.php') && !empty($redirect_url)) {
    $target = $redirect_url;
} else {
    $target = $referer;
}

header("Location: " . $target);
exit;

