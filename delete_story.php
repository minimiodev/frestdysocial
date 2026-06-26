<?php
/**
 * Delete Story API - Frest App
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
    
    // Fetch the story to verify ownership
    $stmt = $db->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch();
    
    if (!$story) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy câu chuyện.'
        ]);
        exit;
    }
    
    // Check if the current user is the owner (or admin)
    if (intval($story['user_id']) !== intval($me['id']) && !isAdminLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có quyền xóa câu chuyện này.'
        ]);
        exit;
    }
    
    // Delete media file if exists
    if (!empty($story['media_filename'])) {
        $file_path = UPLOAD_DIR . 'stories/' . $story['media_filename'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    // Delete story record (related views & reactions will be deleted by CASCADE constraint)
    $del_stmt = $db->prepare("DELETE FROM stories WHERE id = ?");
    $del_stmt->execute([$story_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa câu chuyện thành công.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
