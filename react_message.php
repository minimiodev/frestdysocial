<?php
/**
 * AJAX Handler: React to Chat Message - Frest App
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

$message_id     = intval($_POST['message_id'] ?? 0);
$reaction_emoji = trim($_POST['reaction_emoji'] ?? '');

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID tin nhắn không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    // Fetch message to verify membership
    $stmt = $db->prepare("SELECT sender_type, sender_id, receiver_type, receiver_id, is_recalled FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch();

    if (!$msg) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tin nhắn.']);
        exit;
    }

    $my_type = $identity['type'];
    $my_id   = intval($identity['id']);

    // Check if participant
    $is_sender = ($msg['sender_type'] === $my_type && intval($msg['sender_id']) === $my_id);
    $is_receiver = ($msg['receiver_type'] === $my_type && intval($msg['receiver_id']) === $my_id);

    $is_group_member = false;
    if ($msg['receiver_type'] === 'group') {
        $group_id = intval($msg['receiver_id']);
        $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
        $mem_stmt->execute([$group_id, $my_type, $my_id]);
        if (intval($mem_stmt->fetchColumn()) > 0) {
            $is_group_member = true;
        }
    }

    if (!$is_sender && !$is_receiver && !$is_group_member) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền phản ứng tin nhắn này.']);
        exit;
    }

    if (intval($msg['is_recalled'] ?? 0) === 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Không thể phản ứng tin nhắn đã bị thu hồi.']);
        exit;
    }

    if ($reaction_emoji === '') {
        // Delete reaction
        $del = $db->prepare("
            DELETE FROM message_reactions 
            WHERE message_id = ? AND reactor_type = ? AND reactor_id = ?
        ");
        $del->execute([$message_id, $my_type, $my_id]);
        $action = 'removed';
    } else {
        // Insert or update reaction
        // REPLACE works perfectly on MySQL and SQLite
        $rep = $db->prepare("
            REPLACE INTO message_reactions (message_id, reactor_type, reactor_id, reaction_emoji)
            VALUES (?, ?, ?, ?)
        ");
        $rep->execute([$message_id, $my_type, $my_id, $reaction_emoji]);
        $action = 'reacted';
    }

    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'reaction_emoji' => $reaction_emoji
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
