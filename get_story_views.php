<?php
/**
 * Get Story Views API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập để thực hiện hành động này.'
    ]);
    exit;
}

$me = getLoggedInUser();
$story_id = intval($_GET['story_id'] ?? 0);

if ($story_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tham số không hợp lệ.'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Kiểm tra xem story có phải của người dùng đang đăng nhập không
    $check_stmt = $db->prepare("SELECT user_id FROM stories WHERE id = ?");
    $check_stmt->execute([$story_id]);
    $story = $check_stmt->fetch();
    
    if (!$story) {
        echo json_encode([
            'success' => false,
            'message' => 'Câu chuyện không tồn tại.'
        ]);
        exit;
    }
    
    if (intval($story['user_id']) !== intval($me['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có quyền xem thông tin này.'
        ]);
        exit;
    }
    
    // Lấy danh sách người đã xem
    $views_stmt = $db->prepare("
        SELECT sv.viewed_at, 
               u.id as user_id, 
               u.username, 
               u.full_name, 
               u.avatar_filename
        FROM story_views sv
        JOIN users u ON sv.user_id = u.id
        WHERE sv.story_id = ?
        ORDER BY sv.viewed_at DESC
    ");
    $views_stmt->execute([$story_id]);
    $views = $views_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach ($views as $v) {
        $data[] = [
            'user_id' => intval($v['user_id']),
            'username' => sanitize($v['username']),
            'full_name' => sanitize($v['full_name'] ?: $v['username']),
            'avatar_url' => AVATARS_URL . '/' . sanitize($v['avatar_filename']),
            'viewed_at' => timeElapsedString($v['viewed_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
