<?php
/**
 * AJAX Handler: Delete Chat Group - Frest App
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

$group_id = intval($_POST['group_id'] ?? 0);
if ($group_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID nhóm không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $my_type = $identity['type'];
    $my_id   = intval($identity['id']);

    // 1. Verify if the user is the creator
    $stmt = $db->prepare("SELECT role FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
    $stmt->execute([$group_id, $my_type, $my_id]);
    $role = $stmt->fetchColumn();

    if ($role !== 'creator') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Chỉ trưởng nhóm mới được phép xóa nhóm.']);
        exit;
    }

    $db->beginTransaction();

    // 2. Delete message reactions linked to group messages
    $del_reactions = $db->prepare("
        DELETE mr FROM message_reactions mr
        INNER JOIN messages m ON mr.message_id = m.id
        WHERE m.receiver_type = 'group' AND m.receiver_id = ?
    ");
    $del_reactions->execute([$group_id]);

    // 3. Delete group messages
    $del_messages = $db->prepare("DELETE FROM messages WHERE receiver_type = 'group' AND receiver_id = ?");
    $del_messages->execute([$group_id]);

    // 4. Delete group members
    $del_members = $db->prepare("DELETE FROM chat_group_members WHERE group_id = ?");
    $del_members->execute([$group_id]);

    // 5. Delete group
    $del_group = $db->prepare("DELETE FROM chat_groups WHERE id = ?");
    $del_group->execute([$group_id]);

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Nhóm đã được xóa thành công.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
