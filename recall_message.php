<?php
/**
 * AJAX Handler: Recall Chat Message - Frest App
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

$message_id = intval($_POST['message_id'] ?? 0);

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID tin nhắn không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    // Fetch message
    $stmt = $db->prepare("SELECT sender_type, sender_id FROM messages WHERE id = ?");
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
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thu hồi tin nhắn này.']);
        exit;
    }

    $db->beginTransaction();

    // 1. Update message as recalled
    $upd = $db->prepare("UPDATE messages SET message_text = 'Tin nhắn đã bị thu hồi', is_recalled = 1 WHERE id = ?");
    $upd->execute([$message_id]);

    // 2. Clear reactions for this message
    $del = $db->prepare("DELETE FROM message_reactions WHERE message_id = ?");
    $del->execute([$message_id]);

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Thu hồi tin nhắn thành công.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
