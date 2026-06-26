<?php
/**
 * AJAX Bookmark Action Handler - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để lưu bài viết.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$user_id = getLoggedInUserId();

if ($post_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    // Kiểm tra xem post có tồn tại không
    $stmt_check = $db->prepare("SELECT 1 FROM posts WHERE id = ?");
    $stmt_check->execute([$post_id]);
    if ($stmt_check->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.']);
        exit;
    }
    
    // Kiểm tra xem đã bookmark chưa
    $stmt_exist = $db->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND post_id = ?");
    $stmt_exist->execute([$user_id, $post_id]);
    $bookmark = $stmt_exist->fetch();
    
    if ($bookmark) {
        // Đã bookmark -> Hủy bookmark
        $stmt_del = $db->prepare("DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?");
        $stmt_del->execute([$user_id, $post_id]);
        echo json_encode([
            'success' => true, 
            'action' => 'unbookmarked', 
            'message' => 'Đã hủy lưu bài viết.'
        ]);
    } else {
        // Chưa bookmark -> Lưu bookmark
        $stmt_add = $db->prepare("INSERT INTO bookmarks (user_id, post_id) VALUES (?, ?)");
        $stmt_add->execute([$user_id, $post_id]);
        echo json_encode([
            'success' => true, 
            'action' => 'bookmarked', 
            'message' => 'Đã lưu bài viết thành công.'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
