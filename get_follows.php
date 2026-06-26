<?php
/**
 * get_follows.php — Returns JSON list of followers or following for a user
 * GET params: user_id=<int> & type=followers|following
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ob_start();

$user_id = intval($_GET['user_id'] ?? 0);
$type    = $_GET['type'] ?? 'followers';

if ($user_id <= 0) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
    exit;
}

try {
    $db = getDB();

    if ($type === 'following') {
        // People this user follows
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.avatar_filename, u.verification_type, u.bio, u.is_page
            FROM follows f
            JOIN users u ON f.followed_id = u.id
            WHERE f.follower_id = ?
            ORDER BY f.created_at DESC
            LIMIT 200
        ");
    } else {
        // People who follow this user
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.avatar_filename, u.verification_type, u.bio, u.is_page
            FROM follows f
            JOIN users u ON f.follower_id = u.id
            WHERE f.followed_id = ?
            ORDER BY f.created_at DESC
            LIMIT 200
        ");
    }

    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add avatar URL and sanitize
    $me_id = getLoggedInUserId();
    foreach ($users as &$u) {
        $u['avatar_url']   = AVATARS_URL . '/' . htmlspecialchars($u['avatar_filename'], ENT_QUOTES, 'UTF-8');
        $u['profile_url']  = SITE_URL . '/profile.php?username=' . urlencode($u['username']);
        $u['username']     = htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8');
        $u['full_name']    = htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $u['bio']          = htmlspecialchars(mb_substr($u['bio'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8');
        $u['badge_html']   = renderAuthorBadgeHTML($u['verification_type'], $u['username'], null, intval($u['is_page'] ?? 0) === 1);
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'users' => $users, 'type' => $type]);

} catch (PDOException $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

