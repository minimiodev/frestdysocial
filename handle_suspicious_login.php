<?php
/**
 * Handle suspicious login - change password and log out all sessions - Frest App
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập để thực hiện chức năng này.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_security_reset'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng điền đầy đủ thông tin.'
        ]);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode([
            'success' => false,
            'message' => 'Mật khẩu mới phải có tối thiểu 6 ký tự.'
        ]);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode([
            'success' => false,
            'message' => 'Xác nhận mật khẩu mới không khớp.'
        ]);
        exit;
    }

    try {
        $db = getDB();
        $user_id = intval($me['id']);
        
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $db->beginTransaction();
        
        // Update password hash
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        
        // Delete all old login histories
        $stmt_del = $db->prepare("DELETE FROM login_history WHERE user_id = ?");
        $stmt_del->execute([$user_id]);
        
        // Record this security action as a fresh login log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip === '::1') $ip = '127.0.0.1';
        $ua = ($_SERVER['HTTP_USER_AGENT'] ?? '') . ' (Security Reset)';
        $location = getIpLocation($ip);
        
        $stmt_log = $db->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, location) VALUES (?, ?, ?, ?)");
        $stmt_log->execute([$user_id, $ip, $ua, $location]);
        
        $db->commit();
        
        // Logout current session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công! Hệ thống đang đăng xuất.'
        ]);
        exit;
    } catch (Exception $e) {
        try {
            $db->rollBack();
        } catch(Exception $tr_e) {}
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Yêu cầu không hợp lệ.'
    ]);
    exit;
}
