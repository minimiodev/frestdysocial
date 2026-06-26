<?php
/**
 * check_workplace_page.php - API endpoint to check if a workplace string matches an existing page/user
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (empty($q)) {
    echo json_encode(['exists' => false]);
    exit;
}

$db = getDB();

// Try matching page_username or username (strip @ if present)
$username = $q;
if (strpos($username, '@') === 0) {
    $username = substr($username, 1);
}

try {
    // 1. Check pages by page_username
    $stmt = $db->prepare("SELECT id, page_name, page_username, avatar_filename FROM pages WHERE page_username = ? LIMIT 1");
    $stmt->execute([$username]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        echo json_encode([
            'exists' => true,
            'name' => $page['page_name'],
            'username' => $page['page_username'],
            'avatar' => AVATARS_URL . '/' . htmlspecialchars($page['avatar_filename']),
            'type' => 'page'
        ]);
        exit;
    }

    // 2. Check pages by page_name
    $stmt = $db->prepare("SELECT id, page_name, page_username, avatar_filename FROM pages WHERE page_name = ? LIMIT 1");
    $stmt->execute([$q]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        echo json_encode([
            'exists' => true,
            'name' => $page['page_name'],
            'username' => $page['page_username'],
            'avatar' => AVATARS_URL . '/' . htmlspecialchars($page['avatar_filename']),
            'type' => 'page'
        ]);
        exit;
    }

    // 3. Check users by username
    $stmt = $db->prepare("SELECT id, username, full_name, avatar_filename FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'exists' => true,
            'name' => $user['full_name'] ?: $user['username'],
            'username' => $user['username'],
            'avatar' => AVATARS_URL . '/' . htmlspecialchars($user['avatar_filename']),
            'type' => 'user'
        ]);
        exit;
    }
} catch (Exception $e) {}

echo json_encode(['exists' => false]);
