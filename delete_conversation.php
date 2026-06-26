<?php
/**
 * AJAX Handler: Delete Conversation - Frest App
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

$contact_type = trim($_POST['contact_type'] ?? '');
$contact_id   = intval($_POST['contact_id'] ?? 0);

if (empty($contact_type) || !in_array($contact_type, ['user', 'page', 'group'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Loại liên hệ không hợp lệ.']);
    exit;
}

if ($contact_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID liên hệ không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $my_type = $identity['type'];
    $my_id   = $identity['id'];

    if ($contact_type === 'group') {
        $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
        $mem_stmt->execute([$contact_id, $my_type, $my_id]);
        if ($mem_stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của nhóm này.']);
            exit;
        }
    }

    $db->beginTransaction();

    if ($contact_type === 'group') {
        // Delete all reactions linked to the group's messages
        $del_reactions = $db->prepare("
            DELETE mr FROM message_reactions mr
            INNER JOIN messages m ON mr.message_id = m.id
            WHERE m.receiver_type = 'group' AND m.receiver_id = ?
        ");
        $del_reactions->execute([$contact_id]);

        // Delete all messages in the group
        $del_messages = $db->prepare("
            DELETE FROM messages
            WHERE receiver_type = 'group' AND receiver_id = ?
        ");
        $del_messages->execute([$contact_id]);
    } else {
        // Direct messages: delete reactions first
        $del_reactions = $db->prepare("
            DELETE mr FROM message_reactions mr
            INNER JOIN messages m ON mr.message_id = m.id
            WHERE (m.sender_type = ? AND m.sender_id = ? AND m.receiver_type = ? AND m.receiver_id = ?)
               OR (m.sender_type = ? AND m.sender_id = ? AND m.receiver_type = ? AND m.receiver_id = ?)
        ");
        $del_reactions->execute([
            $my_type, $my_id, $contact_type, $contact_id,
            $contact_type, $contact_id, $my_type, $my_id
        ]);

        // Delete the messages
        $del_messages = $db->prepare("
            DELETE FROM messages
            WHERE (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
               OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
        ");
        $del_messages->execute([
            $my_type, $my_id, $contact_type, $contact_id,
            $contact_type, $contact_id, $my_type, $my_id
        ]);
    }

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Xóa cuộc trò chuyện thành công.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
