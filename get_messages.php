<?php
/**
 * AJAX Handler: Get Messages - Frest App
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

// Release session lock so that other requests are not blocked
session_write_close();

$contact_type = trim($_GET['contact_type'] ?? '');
$contact_id   = intval($_GET['contact_id'] ?? 0);

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

    // Kiểm tra chặn hai chiều
    if ($contact_type === 'user' || $contact_type === 'page') {
        $contact_identity = [
            'type' => $contact_type,
            'id' => $contact_id
        ];
        if (isBlocked($identity, $contact_identity)) {
            echo json_encode([
                'status' => 'success',
                'blocked' => true,
                'messages' => [],
                'message' => 'Bạn không thể trò chuyện hoặc xem tin nhắn với người này do có quan hệ chặn.'
            ]);
            exit;
        }
    }

    // 1. Fetch messages
    $messages = [];
    if ($contact_type === 'group') {
        // Verify group membership
        $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
        $mem_stmt->execute([$contact_id, $my_type, $my_id]);
        if ($mem_stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của nhóm này.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT m.id, m.sender_type, m.sender_id, m.receiver_type, m.receiver_id, m.message_text, 
                   m.is_read, m.is_edited, m.is_recalled, m.created_at,
                   m.image_filename, m.video_filename, m.audio_filename, m.document_filename, m.original_filename,
                   COALESCE(pg.page_name, u.full_name, u.username) AS sender_name,
                   COALESCE(pg.avatar_filename, u.avatar_filename, 'avatar_default.png') AS sender_avatar,
                   COALESCE(pg.page_username, u.username) AS sender_username,
                   u.verification_type AS sender_user_verification,
                   pg.is_verified AS sender_page_verification
            FROM messages m
            LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
            LEFT JOIN pages pg ON m.sender_type = 'page' AND m.sender_id = pg.id
            WHERE m.receiver_type = 'group' AND m.receiver_id = ?
            ORDER BY m.id DESC
            LIMIT 100
        ");
        $stmt->execute([$contact_id]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // Mark group messages as read for this member
        $upd = $db->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE receiver_type = 'group' AND receiver_id = ? AND (sender_type != ? OR sender_id != ?) AND is_read = 0
        ");
        $upd->execute([$contact_id, $my_type, $my_id]);
    } else {
        // Direct messages: Optimized with UNION ALL to use composite index (O(log N))
        $stmt = $db->prepare("
            (
                SELECT m.id, m.sender_type, m.sender_id, m.receiver_type, m.receiver_id, m.message_text, 
                       m.is_read, m.is_edited, m.is_recalled, m.created_at,
                       m.image_filename, m.video_filename, m.audio_filename, m.document_filename, m.original_filename,
                       COALESCE(pg.page_name, u.full_name, u.username) AS sender_name,
                       COALESCE(pg.avatar_filename, u.avatar_filename, 'avatar_default.png') AS sender_avatar,
                       COALESCE(pg.page_username, u.username) AS sender_username,
                       u.verification_type AS sender_user_verification,
                       pg.is_verified AS sender_page_verification
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
                LEFT JOIN pages pg ON m.sender_type = 'page' AND m.sender_id = pg.id
                WHERE m.sender_type = ? AND m.sender_id = ? AND m.receiver_type = ? AND m.receiver_id = ?
            )
            UNION ALL
            (
                SELECT m.id, m.sender_type, m.sender_id, m.receiver_type, m.receiver_id, m.message_text, 
                       m.is_read, m.is_edited, m.is_recalled, m.created_at,
                       m.image_filename, m.video_filename, m.audio_filename, m.document_filename, m.original_filename,
                       COALESCE(pg.page_name, u.full_name, u.username) AS sender_name,
                       COALESCE(pg.avatar_filename, u.avatar_filename, 'avatar_default.png') AS sender_avatar,
                       COALESCE(pg.page_username, u.username) AS sender_username,
                       u.verification_type AS sender_user_verification,
                       pg.is_verified AS sender_page_verification
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
                LEFT JOIN pages pg ON m.sender_type = 'page' AND m.sender_id = pg.id
                WHERE m.sender_type = ? AND m.sender_id = ? AND m.receiver_type = ? AND m.receiver_id = ?
            )
            ORDER BY id DESC
            LIMIT 100
        ");
        $stmt->execute([
            $my_type, $my_id, $contact_type, $contact_id,
            $contact_type, $contact_id, $my_type, $my_id
        ]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // Mark direct incoming messages as read
        $upd = $db->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ? AND is_read = 0
        ");
        $upd->execute([$contact_type, $contact_id, $my_type, $my_id]);
    }

    // 2. Fetch reactions for these messages
    if (!empty($messages)) {
        $msg_ids = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($msg_ids), '?'));
        
        $r_stmt = $db->prepare("
            SELECT message_id, reactor_type, reactor_id, reaction_emoji
            FROM message_reactions
            WHERE message_id IN ($placeholders)
        ");
        $r_stmt->execute($msg_ids);
        $raw_reactions = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group reactions by message_id
        $reactions_map = [];
        foreach ($raw_reactions as $r) {
            $m_id = intval($r['message_id']);
            if (!isset($reactions_map[$m_id])) {
                $reactions_map[$m_id] = [];
            }
            $reactions_map[$m_id][] = [
                'reactor_type' => $r['reactor_type'],
                'reactor_id' => intval($r['reactor_id']),
                'reaction_emoji' => $r['reaction_emoji']
            ];
        }

        // Map reactions and format IDs back to messages
        foreach ($messages as &$m) {
            $m_id = intval($m['id']);
            $m['reactions'] = $reactions_map[$m_id] ?? [];
            $m['id'] = intval($m['id']);
            $m['sender_id'] = intval($m['sender_id']);
            $m['receiver_id'] = intval($m['receiver_id']);
            $m['is_read'] = intval($m['is_read']);
            $m['is_edited'] = intval($m['is_edited']);
            $m['is_recalled'] = intval($m['is_recalled']);

            // Determine author verification badge type
            $v_type = 'none';
            if ($m['sender_type'] === 'user') {
                $v_type = $m['sender_user_verification'] ?: 'none';
            } else {
                $v_type = intval($m['sender_page_verification'] ?? 0) === 1 ? 'official' : 'none';
            }
            $m['sender_verification_type'] = $v_type;

            // Clean up unneeded raw DB columns
            unset($m['sender_user_verification']);
            unset($m['sender_page_verification']);
        }
        unset($m);
    }

    echo json_encode([
        'status' => 'success',
        'messages' => $messages
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
