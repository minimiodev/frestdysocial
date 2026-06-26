<?php
/**
 * AJAX Handler: Get Chat List - Frest App
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

try {
    $db = getDB();
    $my_type = $identity['type'];
    $my_id   = $identity['id'];

    // 1. Get unique direct contacts (users, pages), last message ID, and unread counts (Optimized with UNION ALL)
    $stmt = $db->prepare("
        SELECT 
            t.contact_type,
            t.contact_id,
            MAX(t.msg_id) AS last_msg_id,
            SUM(t.is_incoming_unread) AS unread_count
        FROM (
            SELECT 
                id AS msg_id,
                receiver_type AS contact_type,
                receiver_id AS contact_id,
                0 AS is_incoming_unread
            FROM messages
            WHERE sender_type = ? AND sender_id = ? AND receiver_type != 'group'
            
            UNION ALL
            
            SELECT 
                id AS msg_id,
                sender_type AS contact_type,
                sender_id AS contact_id,
                CASE WHEN is_read = 0 THEN 1 ELSE 0 END AS is_incoming_unread
            FROM messages
            WHERE receiver_type = ? AND receiver_id = ? AND receiver_type != 'group'
        ) t
        GROUP BY t.contact_type, t.contact_id
    ");
    $stmt->execute([
        $my_type, $my_id,
        $my_type, $my_id
    ]);
    $raw_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $user_ids = [];
    $page_ids = [];
    $last_msg_ids = [];

    foreach ($raw_contacts as $c) {
        $c_type = $c['contact_type'];
        $c_id   = intval($c['contact_id']);
        $last_msg_id = intval($c['last_msg_id']);

        if ($c_type === 'user') {
            $user_ids[] = $c_id;
        } else {
            $page_ids[] = $c_id;
        }
        $last_msg_ids[] = $last_msg_id;
    }

    // Bulk fetch users
    $users_info_map = [];
    if (!empty($user_ids)) {
        $user_ids = array_unique($user_ids);
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $u_stmt = $db->prepare("SELECT id, username, full_name, avatar_filename, verification_type, last_active FROM users WHERE id IN ($placeholders)");
        $u_stmt->execute($user_ids);
        while ($row = $u_stmt->fetch(PDO::FETCH_ASSOC)) {
            $users_info_map[intval($row['id'])] = $row;
        }
    }

    // Bulk fetch pages
    $pages_info_map = [];
    if (!empty($page_ids)) {
        $page_ids = array_unique($page_ids);
        $placeholders = implode(',', array_fill(0, count($page_ids), '?'));
        $p_stmt = $db->prepare("SELECT id, page_username, page_name, avatar_filename, is_verified FROM pages WHERE id IN ($placeholders)");
        $p_stmt->execute($page_ids);
        while ($row = $p_stmt->fetch(PDO::FETCH_ASSOC)) {
            $pages_info_map[intval($row['id'])] = $row;
        }
    }

    // Bulk fetch last messages
    $messages_info_map = [];
    if (!empty($last_msg_ids)) {
        $last_msg_ids = array_unique($last_msg_ids);
        $placeholders = implode(',', array_fill(0, count($last_msg_ids), '?'));
        $m_stmt = $db->prepare("SELECT id, sender_type, sender_id, message_text, created_at, image_filename, video_filename, audio_filename, document_filename FROM messages WHERE id IN ($placeholders)");
        $m_stmt->execute($last_msg_ids);
        while ($row = $m_stmt->fetch(PDO::FETCH_ASSOC)) {
            $messages_info_map[intval($row['id'])] = $row;
        }
    }

    $contacts = [];

    foreach ($raw_contacts as $c) {
        $c_type = $c['contact_type'];
        $c_id   = intval($c['contact_id']);
        $last_msg_id = intval($c['last_msg_id']);
        $unread_count = intval($c['unread_count'] ?? 0);

        $name = '';
        $username = '';
        $avatar = 'avatar_default.png';
        $is_verified = 0;
        $is_online = false;

        if ($c_type === 'user') {
            if (isset($users_info_map[$c_id])) {
                $user_info = $users_info_map[$c_id];
                $username = $user_info['username'];
                $name = $user_info['full_name'] ?: $user_info['username'];
                $avatar = $user_info['avatar_filename'];
                $is_verified = (!empty($user_info['verification_type']) && $user_info['verification_type'] !== 'none') ? 1 : 0;
                $is_online = isUserOnline($user_info['last_active']);
            }
        } else {
            if (isset($pages_info_map[$c_id])) {
                $page_info = $pages_info_map[$c_id];
                $username = $page_info['page_username'];
                $name = $page_info['page_name'];
                $avatar = $page_info['avatar_filename'];
                $is_verified = intval($page_info['is_verified'] ?? 0);
                $is_online = false;
            }
        }

        if (empty($username)) {
            continue;
        }

        $last_msg_text = '';
        $last_msg_time = '';
        $last_msg_sender_type = '';
        $last_msg_sender_id = 0;

        if (isset($messages_info_map[$last_msg_id])) {
            $msg_info = $messages_info_map[$last_msg_id];
            $last_msg_time = $msg_info['created_at'];
            $last_msg_sender_type = $msg_info['sender_type'];
            $last_msg_sender_id = intval($msg_info['sender_id']);

            if ($msg_info['message_text'] !== '') {
                $last_msg_text = $msg_info['message_text'];
            } elseif ($msg_info['image_filename']) {
                $last_msg_text = '[Hình ảnh]';
            } elseif ($msg_info['video_filename']) {
                $last_msg_text = '[Video]';
            } elseif ($msg_info['audio_filename']) {
                $last_msg_text = '[Ghi âm]';
            } elseif ($msg_info['document_filename']) {
                $last_msg_text = '[Tệp đính kèm]';
            }
        }

        $avatar_url = AVATARS_URL . '/' . htmlspecialchars($avatar ?: 'avatar_default.png');

        $contacts[] = [
            'contact_type' => $c_type,
            'contact_id' => $c_id,
            'username' => $username,
            'name' => $name,
            'avatar_url' => $avatar_url,
            'is_verified' => $is_verified,
            'is_online' => $is_online,
            'unread_count' => $unread_count,
            'last_message' => $last_msg_text,
            'last_message_time' => $last_msg_time,
            'is_my_message' => ($last_msg_sender_type === $my_type && $last_msg_sender_id === $my_id)
        ];
    }

    // 2. Fetch groups the active identity is a member of
    $g_stmt = $db->prepare("
        SELECT g.id, g.name, g.avatar_filename, g.description, g.created_at
        FROM chat_groups g
        JOIN chat_group_members m ON g.id = m.group_id
        WHERE m.member_type = ? AND m.member_id = ?
    ");
    $g_stmt->execute([$my_type, $my_id]);
    $raw_groups = $g_stmt->fetchAll(PDO::FETCH_ASSOC);

    $group_ids = array_column($raw_groups, 'id');
    $group_last_messages = [];
    $group_unread_counts = [];

    if (!empty($group_ids)) {
        $placeholders = implode(',', array_fill(0, count($group_ids), '?'));

        // Query the last message for each group in one batch
        $lm_stmt = $db->prepare("
            SELECT m1.receiver_id as group_id, m1.sender_type, m1.sender_id, m1.message_text, m1.created_at, m1.image_filename, m1.video_filename, m1.audio_filename, m1.document_filename
            FROM messages m1
            INNER JOIN (
                SELECT receiver_id, MAX(id) as max_id 
                FROM messages 
                WHERE receiver_type = 'group' AND receiver_id IN ($placeholders) 
                GROUP BY receiver_id
            ) m2 ON m1.id = m2.max_id
        ");
        $lm_stmt->execute($group_ids);
        while ($row = $lm_stmt->fetch(PDO::FETCH_ASSOC)) {
            $group_last_messages[intval($row['group_id'])] = $row;
        }

        // Query the unread count for each group in one batch
        $ur_stmt = $db->prepare("
            SELECT receiver_id as group_id, COUNT(*) as qty 
            FROM messages
            WHERE receiver_type = 'group' AND receiver_id IN ($placeholders)
              AND (sender_type != ? OR sender_id != ?)
              AND is_read = 0
            GROUP BY receiver_id
        ");
        $ur_stmt->execute(array_merge($group_ids, [$my_type, $my_id]));
        while ($row = $ur_stmt->fetch(PDO::FETCH_ASSOC)) {
            $group_unread_counts[intval($row['group_id'])] = intval($row['qty']);
        }
    }

    foreach ($raw_groups as $g) {
        $g_id = intval($g['id']);
        
        $lm = $group_last_messages[$g_id] ?? null;

        $last_msg_text = '';
        $last_msg_time = $g['created_at']; // fallback to group creation time
        $is_my_message = false;

        if ($lm) {
            $is_my_message = ($lm['sender_type'] === $my_type && intval($lm['sender_id']) === $my_id);
            
            if ($lm['message_text'] !== '') {
                $last_msg_text = $lm['message_text'];
            } elseif ($lm['image_filename']) {
                $last_msg_text = '[Hình ảnh]';
            } elseif ($lm['video_filename']) {
                $last_msg_text = '[Video]';
            } elseif ($lm['audio_filename']) {
                $last_msg_text = '[Ghi âm]';
            } elseif ($lm['document_filename']) {
                $last_msg_text = '[Tệp đính kèm]';
            }
            $last_msg_time = $lm['created_at'];
        }

        $unread_count = $group_unread_counts[$g_id] ?? 0;

        $contacts[] = [
            'contact_type' => 'group',
            'contact_id' => $g_id,
            'username' => 'group_' . $g_id,
            'name' => $g['name'],
            'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($g['avatar_filename'] ?: 'group_default.png'),
            'is_verified' => 0,
            'unread_count' => $unread_count,
            'last_message' => $last_msg_text,
            'last_message_time' => $last_msg_time,
            'is_my_message' => $is_my_message
        ];
    }

    // Đảm bảo frest_ai luôn có mặt trong danh sách liên hệ của người dùng
    $has_ai = false;
    foreach ($contacts as $c) {
        if ($c['username'] === 'frest_ai') {
            $has_ai = true;
            break;
        }
    }
    
    if (!$has_ai) {
        try {
            $ai_stmt = $db->prepare("SELECT id, username, full_name, avatar_filename, verification_type, created_at FROM users WHERE username = ?");
            $ai_stmt->execute(['frest_ai']);
            $ai_user = $ai_stmt->fetch(PDO::FETCH_ASSOC);
            if ($ai_user) {
                $contacts[] = [
                    'contact_type' => 'user',
                    'contact_id' => intval($ai_user['id']),
                    'username' => 'frest_ai',
                    'name' => $ai_user['full_name'] ?: 'Trợ Lý Frest AI',
                    'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($ai_user['avatar_filename'] ?: 'ai_avatar.png'),
                    'is_verified' => 1,
                    'is_online' => true,
                    'unread_count' => 0,
                    'last_message' => 'Chào mừng bạn! Tôi là Frest AI. Hãy gửi tin nhắn để trò chuyện cùng tôi nhé! 🤖',
                    'last_message_time' => $ai_user['created_at'],
                    'is_my_message' => false
                ];
            }
        } catch (Exception $e) {}
    }

    // Sort all frests by last activity DESC
    usort($contacts, function ($a, $b) {
        return strcmp($b['last_message_time'], $a['last_message_time']);
    });

    echo json_encode([
        'status' => 'success',
        'contacts' => $contacts
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
