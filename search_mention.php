<?php
/**
 * search_mention.php - API endpoint for @mention autocomplete
 * Returns matching users and pages based on a query string.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

// Sanitize: only allow alphanumeric + underscore + dot
$q = preg_replace('/[^a-zA-Z0-9_.\p{L}]/u', '', $q);
if (empty($q)) {
    echo json_encode([]);
    exit;
}

$db = getDB();
$results = [];
$seen = [];

// Search users
$stmt = $db->prepare(
    "SELECT id, username, full_name, avatar_filename, verification_type, 'user' AS type
     FROM users
     WHERE username LIKE ? OR full_name LIKE ?
     LIMIT 6"
);
$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $u) {
    $key = 'user_' . $u['id'];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $results[] = [
            'id'     => $u['id'],
            'handle' => $u['username'],
            'name'   => $u['full_name'] ?: $u['username'],
            'avatar' => AVATARS_URL . '/' . htmlspecialchars($u['avatar_filename']),
            'type'   => 'user',
            'verification_type' => $u['verification_type'] ?: 'none',
            'is_verified' => (!empty($u['verification_type']) && $u['verification_type'] !== 'none') ? 1 : 0
        ];
    }
}

// Search pages
$stmt2 = $db->prepare(
    "SELECT id, page_username, page_name, avatar_filename, is_verified, 'page' AS type
     FROM pages
     WHERE page_username LIKE ? OR page_name LIKE ?
     LIMIT 4"
);
$stmt2->execute([$like, $like]);
$pages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($pages as $pg) {
    $key = 'page_' . $pg['id'];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $results[] = [
            'id'     => $pg['id'],
            'handle' => $pg['page_username'],
            'name'   => $pg['page_name'],
            'avatar' => AVATARS_URL . '/' . htmlspecialchars($pg['avatar_filename']),
            'type'   => 'page',
            'verification_type' => intval($pg['is_verified']) === 1 ? 'official' : 'none',
            'is_verified' => intval($pg['is_verified'] ?? 0)
        ];
    }
}

echo json_encode(array_slice($results, 0, 8));
