<?php
/**
 * View Story Action API - Frest App
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
session_write_close(); // Giải phóng khóa session sớm để tránh block các request khác
$story_id = intval($_POST['story_id'] ?? 0);

if ($story_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID câu chuyện không hợp lệ.'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Check if view already exists
    $check_stmt = $db->prepare("SELECT COUNT(*) FROM story_views WHERE story_id = ? AND user_id = ?");
    $check_stmt->execute([$story_id, $me['id']]);
    $exists = intval($check_stmt->fetchColumn()) > 0;
    
    if (!$exists) {
        $insert_stmt = $db->prepare("INSERT INTO story_views (story_id, user_id) VALUES (?, ?)");
        $insert_stmt->execute([$story_id, $me['id']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã đánh dấu đã xem câu chuyện.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
