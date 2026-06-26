<?php
/**
 * AJAX Handler: Send Chat Message - Frest App
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

$receiver_type = trim($_POST['receiver_type'] ?? '');
$receiver_id   = intval($_POST['receiver_id'] ?? 0);
$message_text  = trim($_POST['message_text'] ?? '');

$image_filename    = trim($_POST['image_filename'] ?? '');
$video_filename    = trim($_POST['video_filename'] ?? '');
$audio_filename    = trim($_POST['audio_filename'] ?? '');
$document_filename = trim($_POST['document_filename'] ?? '');
$original_filename = trim($_POST['original_filename'] ?? '');

if (empty($receiver_type) || !in_array($receiver_type, ['user', 'page', 'group'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Loại người nhận không hợp lệ.']);
    exit;
}

if ($receiver_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID người nhận không hợp lệ.']);
    exit;
}

if ($message_text === '' && $image_filename === '' && $video_filename === '' && $audio_filename === '' && $document_filename === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nội dung tin nhắn không được để trống.']);
    exit;
}

// Check if receiver exists
try {
    $db = getDB();
    $sender_type = $identity['type'];
    $sender_id   = intval($identity['id']);
    
    if ($receiver_type === 'user') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $exists = ($stmt->fetchColumn() > 0);
    } elseif ($receiver_type === 'page') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM pages WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $exists = ($stmt->fetchColumn() > 0);
    } else { // group
        $stmt = $db->prepare("SELECT COUNT(*) FROM chat_groups WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $exists = ($stmt->fetchColumn() > 0);
        
        if ($exists) {
            // Check membership
            $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
            $mem_stmt->execute([$receiver_id, $sender_type, $sender_id]);
            if ($mem_stmt->fetchColumn() == 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của nhóm này.']);
                exit;
            }
        }
    }

    if (!$exists) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy người nhận/nhóm trong hệ thống.']);
        exit;
    }

    // Kiểm tra chặn hai chiều
    if ($receiver_type === 'user' || $receiver_type === 'page') {
        $receiver_identity = [
            'type' => $receiver_type,
            'id' => $receiver_id
        ];
        if (isBlocked($identity, $receiver_identity)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Bạn không thể gửi tin nhắn cho người này do có quan hệ chặn.']);
            exit;
        }
    }

    // Insert message
    $ins = $db->prepare("
        INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message_text, image_filename, video_filename, audio_filename, document_filename, original_filename)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ins->execute([
        $sender_type, 
        $sender_id, 
        $receiver_type, 
        $receiver_id, 
        $message_text,
        $image_filename ?: null,
        $video_filename ?: null,
        $audio_filename ?: null,
        $document_filename ?: null,
        $original_filename ?: null
    ]);
    $msg_id = $db->lastInsertId();

    // --- Bắt đầu xử lý phản hồi AI ---
    $ai_user_id = 0;
    try {
        $ai_stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $ai_stmt->execute(['frest_ai']);
        $ai_user_id = intval($ai_stmt->fetchColumn());
    } catch (Exception $e) {}

    $is_chatting_with_ai = ($receiver_type === 'user' && $receiver_id === $ai_user_id);
    $is_tagging_ai = (mb_strpos($message_text, '@ai') !== false);
    
    if (($is_chatting_with_ai || $is_tagging_ai) && $ai_user_id > 0 && $sender_id !== $ai_user_id) {
        $sender_name = 'Bạn';
        try {
            if ($sender_type === 'user') {
                $s_stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
                $s_stmt->execute([$sender_id]);
                $s_user = $s_stmt->fetch(PDO::FETCH_ASSOC);
                if ($s_user) {
                    $sender_name = $s_user['full_name'] ?: $s_user['username'];
                }
            } else {
                $s_stmt = $db->prepare("SELECT page_name FROM pages WHERE id = ?");
                $s_stmt->execute([$sender_id]);
                $sender_name = $s_stmt->fetchColumn() ?: 'Trang';
            }
        } catch (Exception $e) {}

        $ai_query = $message_text;
        if ($is_tagging_ai) {
            $ai_query = trim(str_replace('@ai', '', $message_text));
            if (empty($ai_query)) {
                $ai_query = "chào";
            }
        }

        $ai_response = getAIResponse($ai_query, $sender_name);

        if ($receiver_type === 'group' && $sender_type === 'user') {
            try {
                $u_stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $u_stmt->execute([$sender_id]);
                $u_uname = $u_stmt->fetchColumn();
                if ($u_uname) {
                    $ai_response = "@" . $u_uname . " " . $ai_response;
                }
            } catch (Exception $e) {}
        }

        $ai_rec_type = $is_chatting_with_ai ? $sender_type : $receiver_type;
        $ai_rec_id   = $is_chatting_with_ai ? $sender_id : $receiver_id;

        $ins_ai = $db->prepare("
            INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message_text)
            VALUES ('user', ?, ?, ?, ?)
        ");
        $ins_ai->execute([
            $ai_user_id,
            $ai_rec_type,
            $ai_rec_id,
            $ai_response
        ]);
    }
    // --- Kết thúc xử lý phản hồi AI ---

    echo json_encode([
        'status' => 'success',
        'message' => [
            'id' => intval($msg_id),
            'sender_type' => $sender_type,
            'sender_id' => intval($sender_id),
            'receiver_type' => $receiver_type,
            'receiver_id' => intval($receiver_id),
            'message_text' => $message_text,
            'image_filename' => $image_filename ?: null,
            'video_filename' => $video_filename ?: null,
            'audio_filename' => $audio_filename ?: null,
            'document_filename' => $document_filename ?: null,
            'original_filename' => $original_filename ?: null,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
