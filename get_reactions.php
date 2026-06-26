<?php
/**
 * AJAX Endpoint - Get Users who reacted to a Post
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID bài đăng không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT r.reaction_type, u.username, u.avatar_filename, u.verification_type, u.is_page
        FROM reactions r
        JOIN users u ON r.user_id = u.id
        WHERE r.post_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $reactors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map badges and avatar URLs
    foreach ($reactors as &$reactor) {
        $reactor['badge_html'] = renderAuthorBadgeHTML($reactor['verification_type'], $reactor['username'], null, intval($reactor['is_page'] ?? 0) === 1);
        $reactor['avatar_url'] = AVATARS_URL . '/' . sanitize($reactor['avatar_filename']);
        $reactor['username'] = sanitize($reactor['username']);
    }
    
    echo json_encode(['success' => true, 'reactors' => $reactors]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
}

