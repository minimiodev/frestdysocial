<?php
/**
 * AJAX Handler: Edit Chat Message - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để thực hiện tác vụ này.']);
    exit;
}

$identity = getCurrentIdentity();
if (!$identity) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy danh tính hoạt động.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

$message_id   = intval($_POST['message_id'] ?? 0);
$message_text  = trim($_POST['message_text'] ?? '');

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID tin nhắn không hợp lệ.']);
    exit;
}

if ($message_text === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nội dung tin nhắn không được để trống.']);
    exit;
}

try {
    $db = getDB();
    
    // Fetch message
    $stmt = $db->prepare("SELECT sender_type, sender_id, is_recalled FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch();

    if (!$msg) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tin nhắn.']);
        exit;
    }

    // Verify sender
    if ($msg['sender_type'] !== $identity['type'] || intval($msg['sender_id']) !== intval($identity['id'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền chỉnh sửa tin nhắn này.']);
        exit;
    }

    if (intval($msg['is_recalled'] ?? 0) === 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Không thể chỉnh sửa tin nhắn đã bị thu hồi.']);
        exit;
    }

    // Update message text
    $upd = $db->prepare("UPDATE messages SET message_text = ?, is_edited = 1 WHERE id = ?");
    $upd->execute([$message_text, $message_id]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Chỉnh sửa tin nhắn thành công.',
        'edited_text' => $message_text
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
