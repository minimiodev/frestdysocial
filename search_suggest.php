<?php
/**
 * Search Suggest API - Frest App
 */
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [
    'users' => [],
    'pages' => [],
    'hashtags' => []
];

if (!empty($query)) {
    try {
        $db = getDB();
        
        // 1. Search Users
        $stmt = $db->prepare("
            SELECT id, username, full_name, avatar_filename, verification_type 
            FROM users 
            WHERE (is_page = 0 OR is_page IS NULL) AND (username LIKE ? OR full_name LIKE ?) 
            LIMIT 4
        ");
        $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $badge = '';
            if (!empty($user['verification_type']) && $user['verification_type'] !== 'none') {
                $badge = getVerificationBadgeHTML($user['verification_type'], $user['username']);
            }
            
            $results['users'][] = [
                'id' => intval($user['id']),
                'username' => sanitize($user['username']),
                'full_name' => sanitize($user['full_name'] ?: $user['username']),
                'avatar' => AVATARS_URL . '/' . sanitize($user['avatar_filename']),
                'badge' => $badge
            ];
        }

        // 2. Search Pages
        $stmt_page = $db->prepare("
            SELECT id, page_username, page_name, avatar_filename, is_verified, category 
            FROM pages 
            WHERE page_username LIKE ? OR page_name LIKE ? 
            LIMIT 4
        ");
        $stmt_page->execute(['%' . $query . '%', '%' . $query . '%']);
        $pages = $stmt_page->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pages as $page) {
            $badge = getPageVerificationBadgeHTML($page['id'], false);
            
            $results['pages'][] = [
                'id' => intval($page['id']),
                'username' => sanitize($page['page_username']),
                'full_name' => sanitize($page['page_name']),
                'avatar' => AVATARS_URL . '/' . sanitize($page['avatar_filename']),
                'category' => sanitize($page['category'] ?: 'Cộng đồng'),
                'badge' => $badge
            ];
        }

        // 3. Search Hashtags
        $stmt_tag = $db->prepare("
            SELECT tag 
            FROM hashtags 
            WHERE tag LIKE ? 
            LIMIT 4
        ");
        $stmt_tag->execute(['%' . $query . '%']);
        $tags = $stmt_tag->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tags as $tag) {
            $results['hashtags'][] = [
                'tag' => sanitize($tag['tag'])
            ];
        }

    } catch (PDOException $e) {
        // Silent fail
    }
}

echo json_encode($results);
exit;
