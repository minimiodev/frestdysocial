<?php
/**
 * Block/Unblock API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

$me = getLoggedInUser();
$me_identity = getCurrentIdentity();

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$target_type = isset($_POST['target_type']) ? trim($_POST['target_type']) : '';
$target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;

if (!in_array($action, ['block', 'unblock']) || !in_array($target_type, ['user', 'page']) || $target_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.']);
    exit;
}

$target_identity = [
    'type' => $target_type,
    'id' => $target_id
];

// Không được tự chặn chính mình
if ($me_identity['type'] === $target_identity['type'] && $me_identity['id'] === $target_identity['id']) {
    echo json_encode(['success' => false, 'message' => 'Bạn không thể tự chặn hoặc hủy chặn chính mình.']);
    exit;
}

try {
    if ($action === 'block') {
        $result = blockIdentity($me_identity, $target_identity);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Đã chặn người dùng này thành công.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể chặn người dùng này.']);
        }
    } else {
        $result = unblockIdentity($me_identity, $target_identity);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Đã hủy chặn người dùng này thành công.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể hủy chặn người dùng này.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
}
exit;
