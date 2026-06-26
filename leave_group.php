<?php
/**
 * AJAX Handler: Leave Chat Group - Frest App
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

    // 1. Verify membership
    $mem_stmt = $db->prepare("SELECT role FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
    $mem_stmt->execute([$group_id, $my_type, $my_id]);
    $member = $mem_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của nhóm này.']);
        exit;
    }

    $my_role = $member['role'];

    $db->beginTransaction();

    // 2. Fetch other members
    $others_stmt = $db->prepare("
        SELECT member_type, member_id 
        FROM chat_group_members 
        WHERE group_id = ? AND NOT (member_type = ? AND member_id = ?)
        ORDER BY joined_at ASC
    ");
    $others_stmt->execute([$group_id, $my_type, $my_id]);
    $other_members = $others_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($other_members)) {
        // No other members left in the group, delete the group entirely
        // Cascade delete will delete group members, but let's delete messages first.
        $del_reactions = $db->prepare("
            DELETE mr FROM message_reactions mr
            INNER JOIN messages m ON mr.message_id = m.id
            WHERE m.receiver_type = 'group' AND m.receiver_id = ?
        ");
        $del_reactions->execute([$group_id]);

        $del_messages = $db->prepare("DELETE FROM messages WHERE receiver_type = 'group' AND receiver_id = ?");
        $del_messages->execute([$group_id]);

        $del_group = $db->prepare("DELETE FROM chat_groups WHERE id = ?");
        $del_group->execute([$group_id]);
    } else {
        // Promote another member if the leaving user is the creator
        if ($my_role === 'creator') {
            $new_creator = $other_members[0];
            $new_type = $new_creator['member_type'];
            $new_id   = intval($new_creator['member_id']);

            // Update new creator's role
            $upd_role = $db->prepare("UPDATE chat_group_members SET role = 'creator' WHERE group_id = ? AND member_type = ? AND member_id = ?");
            $upd_role->execute([$group_id, $new_type, $new_id]);

            // Update group's creator fields
            $upd_group = $db->prepare("UPDATE chat_groups SET creator_type = ?, creator_id = ? WHERE id = ?");
            $upd_group->execute([$new_type, $new_id, $group_id]);
        }

        // Delete the leaving member
        $del_mem = $db->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
        $del_mem->execute([$group_id, $my_type, $my_id]);

        // Post system message
        $sys_msg = "đã rời khỏi nhóm";
        $ins_msg = $db->prepare("
            INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message_text)
            VALUES (?, ?, 'group', ?, ?)
        ");
        $ins_msg->execute([$my_type, $my_id, $group_id, $sys_msg]);
    }

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Bạn đã rời nhóm thành công.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
