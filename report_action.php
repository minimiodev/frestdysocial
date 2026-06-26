<?php
/**
 * Report Action API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện báo cáo.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

$me_identity = getCurrentIdentity();

$target_type = isset($_POST['target_type']) ? trim($_POST['target_type']) : '';
$target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$details = isset($_POST['details']) ? trim($_POST['details']) : null;

$allowed_targets = ['post', 'reply', 'user', 'page'];
$allowed_reasons = ['spam', 'nsfw', 'hate_speech', 'harassment', 'other'];

if (!in_array($target_type, $allowed_targets) || $target_id <= 0 || !in_array($reason, $allowed_reasons)) {
    echo json_encode(['success' => false, 'message' => 'Thông tin báo cáo không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    // Kiểm tra trùng lặp báo cáo (Deduplication) để tránh spam báo cáo
    $check_stmt = $db->prepare("SELECT id FROM reports WHERE reporter_type = ? AND reporter_id = ? AND target_type = ? AND target_id = ? AND status = 'pending'");
    $check_stmt->execute([
        $me_identity['type'],
        $me_identity['id'],
        $target_type,
        $target_id
    ]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Bạn đã gửi báo cáo cho nội dung này rồi. Chúng tôi đang xem xét.']);
        exit;
    }
    
    // Thêm báo cáo mới vào DB
    $stmt = $db->prepare("INSERT INTO reports (reporter_type, reporter_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $me_identity['type'],
        $me_identity['id'],
        $target_type,
        $target_id,
        $reason,
        $details
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Cảm ơn phản hồi của bạn. Báo cáo đã được gửi tới Ban Quản Trị để xử lý.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
}
exit;
