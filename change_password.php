<?php
/**
 * AJAX Endpoint - Change User Password
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$user_id = getLoggedInUserId();
$current_pass = $_POST['current_password'] ?? '';
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_password'] ?? '';

if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ các thông tin.']);
    exit;
}

if (strlen($new_pass) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải dài từ 6 ký tự trở lên.']);
    exit;
}

if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới và mật khẩu xác nhận không khớp.']);
    exit;
}

try {
    $db = getDB();
    
    // Fetch user password hash
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();
    
    if (!$hash || !password_verify($current_pass, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không chính xác.']);
        exit;
    }
    
    // Update hash
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$new_hash, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Thay đổi mật khẩu thành công!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

