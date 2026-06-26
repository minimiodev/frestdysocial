<?php
/**
 * sse_messages.php — Server-Sent Events endpoint for real-time messaging.
 * Streams new messages, edits, recalls, reactions, and sidebar updates.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Must be logged in
if (!isUserLoggedIn()) {
    http_response_code(401);
    exit;
}

$identity = getCurrentIdentity();
if (!$identity) {
    http_response_code(403);
    exit;
}

$my_type = $identity['type'];
$my_id = intval($identity['id']);

// Release session lock so that other requests are not blocked
session_write_close();

// Kiểm tra chế độ polling
$is_polling = (isset($_GET['polling']) && $_GET['polling'] == 1) || (defined('DISABLE_SSE') && DISABLE_SSE && isset($_GET['polling']));

// Ngắt sớm kết nối SSE thông thường nếu SSE bị vô hiệu hóa trên server đơn luồng để tránh khóa luồng
if (defined('DISABLE_SSE') && DISABLE_SSE && !$is_polling) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "event: error\n";
    echo "data: SSE is disabled on this server.\n\n";
    exit;
}

if ($is_polling) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    try {
        $db = getDB();
        
        // Lấy danh sách ID nhóm mà người dùng thuộc về
        $group_ids = [];
        $g_stmt = $db->prepare("SELECT group_id FROM chat_group_members WHERE member_type = ? AND member_id = ?");
        $g_stmt->execute([$my_type, $my_id]);
        $group_ids = $g_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 1. Lấy tin nhắn mới trong cuộc trò chuyện hiện tại
        $new_messages = [];
        if ($contact_type === 'group') {
            $stmt = $db->prepare("
                SELECT id FROM messages 
                WHERE receiver_type = 'group' AND receiver_id = ? AND id > ?
                ORDER BY id ASC
            ");
            $stmt->execute([$contact_id, $last_id]);
        } else {
            $stmt = $db->prepare("
                SELECT id FROM messages 
                WHERE id > ? AND (
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                )
                ORDER BY id ASC
            ");
            $stmt->execute([
                $last_id,
                $my_type, $my_id, $contact_type, $contact_id,
                $contact_type, $contact_id, $my_type, $my_id
            ]);
        }
        $new_msg_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($new_msg_ids)) {
            $new_messages = fetchMessageDetails($db, $new_msg_ids);
        }
        
        // 2. Kiểm tra cập nhật của 50 tin nhắn cuối (sửa, thu hồi, cảm xúc)
        $updated_messages = [];
        if ($contact_type === 'group') {
            $stmt = $db->prepare("
                SELECT id, is_edited, is_recalled FROM messages
                WHERE receiver_type = 'group' AND receiver_id = ?
                ORDER BY id DESC LIMIT 50
            ");
            $stmt->execute([$contact_id]);
        } else {
            $stmt = $db->prepare("
                SELECT id, is_edited, is_recalled FROM messages
                WHERE (
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                )
                ORDER BY id DESC LIMIT 50
            ");
            $stmt->execute([
                $my_type, $my_id, $contact_type, $contact_id,
                $contact_type, $contact_id, $my_type, $my_id
            ]);
        }
        $monitored_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $client_states = isset($_POST['states']) ? json_decode($_POST['states'], true) : [];
        $changed_ids = [];
        
        if (!empty($monitored_messages)) {
            $monitored_ids = array_column($monitored_messages, 'id');
            $placeholders = implode(',', array_fill(0, count($monitored_ids), '?'));
            
            $r_stmt = $db->prepare("
                SELECT message_id, reactor_type, reactor_id, reaction_emoji
                FROM message_reactions
                WHERE message_id IN ($placeholders)
            ");
            $r_stmt->execute($monitored_ids);
            $monitored_reactions = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reactions_by_msg = [];
            foreach ($monitored_reactions as $r) {
                $reactions_by_msg[$r['message_id']][] = $r;
            }
            
            foreach ($monitored_messages as $m) {
                $m_id = intval($m['id']);
                $reactions = $reactions_by_msg[$m_id] ?? [];
                usort($reactions, function($a, $b) {
                    return strcmp($a['reactor_type'].$a['reactor_id'].$a['reaction_emoji'], $b['reactor_type'].$b['reactor_id'].$b['reaction_emoji']);
                });
                $react_str = json_encode($reactions);
                $current_state = "{$m['is_edited']}_{$m['is_recalled']}_" . md5($react_str);
                
                if (isset($client_states[$m_id])) {
                    if ($client_states[$m_id] !== $current_state) {
                        $changed_ids[] = $m_id;
                    }
                }
            }
            
            if (!empty($changed_ids)) {
                $updated_messages = fetchMessageDetails($db, $changed_ids);
            }
        }
        
        // 3. Kiểm tra cập nhật sidebar (tin nhắn mới từ người khác)
        $global_max_id = isset($_GET['global_max_id']) ? intval($_GET['global_max_id']) : 0;
        $new_global_max_id = getGlobalMaxMessageId($db, $my_type, $my_id, $group_ids);
        $sidebar_update = ($new_global_max_id > $global_max_id);
        
        echo json_encode([
            'success' => true,
            'messages' => $new_messages,
            'updates' => $updated_messages,
            'sidebar_update' => $sidebar_update,
            'global_max_id' => $new_global_max_id
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}


// Do NOT keep running if user closes connection (prevents orphan background PHP processes)
ignore_user_abort(false);
set_time_limit(0);

// SSE headers
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no'); // disable nginx proxy buffering
header('Connection: keep-alive');

// Disable output buffering
while (ob_get_level() > 0) { ob_end_flush(); }
flush();

// Input parameters
$contact_type = trim($_GET['contact_type'] ?? '');
$contact_id   = intval($_GET['contact_id'] ?? 0);
$last_id      = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if (empty($contact_type) || !in_array($contact_type, ['user', 'page', 'group']) || $contact_id <= 0) {
    http_response_code(400);
    exit;
}

// Check blocking status for direct chat
$db = getDB();
if ($contact_type === 'user' || $contact_type === 'page') {
    $contact_identity = [
        'type' => $contact_type,
        'id' => $contact_id
    ];
    if (isBlocked($identity, $contact_identity)) {
        echo "event: blocked\n";
        echo "data: " . json_encode(['message' => 'Bạn không thể trò chuyện với người này.'], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        exit;
    }
} elseif ($contact_type === 'group') {
    // Verify group membership
    $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
    $mem_stmt->execute([$contact_id, $my_type, $my_id]);
    if ($mem_stmt->fetchColumn() == 0) {
        http_response_code(403);
        exit;
    }
}

// Function to emit SSE event
function sseEvent($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Send connection established event
echo ": connected\n\n";
flush();

// Helper to query and format messages
function fetchMessageDetails($db, $message_ids) {
    if (empty($message_ids)) return [];
    
    $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
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
        WHERE m.id IN ($placeholders)
    ");
    $stmt->execute($message_ids);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch reactions
    $r_stmt = $db->prepare("
        SELECT message_id, reactor_type, reactor_id, reaction_emoji
        FROM message_reactions
        WHERE message_id IN ($placeholders)
    ");
    $r_stmt->execute($message_ids);
    $raw_reactions = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    foreach ($messages as &$m) {
        $m_id = intval($m['id']);
        $m['reactions'] = $reactions_map[$m_id] ?? [];
        $m['id'] = intval($m['id']);
        $m['sender_id'] = intval($m['sender_id']);
        $m['receiver_id'] = intval($m['receiver_id']);
        $m['is_read'] = intval($m['is_read']);
        $m['is_edited'] = intval($m['is_edited']);
        $m['is_recalled'] = intval($m['is_recalled']);

        $v_type = 'none';
        if ($m['sender_type'] === 'user') {
            $v_type = $m['sender_user_verification'] ?: 'none';
        } else {
            $v_type = intval($m['sender_page_verification'] ?? 0) === 1 ? 'official' : 'none';
        }
        $m['sender_verification_type'] = $v_type;
        unset($m['sender_user_verification']);
        unset($m['sender_page_verification']);
    }
    return $messages;
}

// Fetch group IDs user belongs to
$group_ids = [];
$g_stmt = $db->prepare("SELECT group_id FROM chat_group_members WHERE member_type = ? AND member_id = ?");
$g_stmt->execute([$my_type, $my_id]);
$group_ids = $g_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build global max ID query
function getGlobalMaxMessageId($db, $my_type, $my_id, $group_ids) {
    $where_clauses = [
        "(receiver_type = ? AND receiver_id = ?)",
        "(sender_type = ? AND sender_id = ?)"
    ];
    $params = [$my_type, $my_id, $my_type, $my_id];

    if (!empty($group_ids)) {
        $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
        $where_clauses[] = "(receiver_type = 'group' AND receiver_id IN ($placeholders))";
        $params = array_merge($params, $group_ids);
    }

    $query_str = "SELECT COALESCE(MAX(id),0) FROM messages WHERE " . implode(" OR ", $where_clauses);
    $max_stmt = $db->prepare($query_str);
    $max_stmt->execute($params);
    return intval($max_stmt->fetchColumn());
}

$global_max_id = getGlobalMaxMessageId($db, $my_type, $my_id, $group_ids);

$state_cache = []; // msg_id => hash of properties
$loop_limit = 25; // Giới hạn mỗi kết nối SSE ~50 giây để giải phóng PHP process trên hosting
$iteration = 0;

while ($iteration < $loop_limit) {
    if (connection_aborted()) break;
    
    try {
        $db = getDB();
        
        // 1. Fetch new messages in this chat
        if ($contact_type === 'group') {
            $stmt = $db->prepare("
                SELECT id FROM messages 
                WHERE receiver_type = 'group' AND receiver_id = ? AND id > ?
                ORDER BY id ASC
            ");
            $stmt->execute([$contact_id, $last_id]);
        } else {
            $stmt = $db->prepare("
                SELECT id FROM messages 
                WHERE id > ? AND (
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                )
                ORDER BY id ASC
            ");
            $stmt->execute([
                $last_id,
                $my_type, $my_id, $contact_type, $contact_id,
                $contact_type, $contact_id, $my_type, $my_id
            ]);
        }
        
        $new_msg_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($new_msg_ids)) {
            $new_messages = fetchMessageDetails($db, $new_msg_ids);
            usort($new_messages, function($a, $b) { return $a['id'] <=> $b['id']; });
            
            foreach ($new_messages as $m) {
                sseEvent('message', $m);
                $last_id = max($last_id, $m['id']);
                
                $react_str = json_encode($m['reactions']);
                $state_cache[$m['id']] = "{$m['is_edited']}_{$m['is_recalled']}_" . md5($react_str);
            }
        }
        
        // 2. Monitor last 50 messages for edits, recalls, or reactions
        if ($contact_type === 'group') {
            $stmt = $db->prepare("
                SELECT id, is_edited, is_recalled FROM messages
                WHERE receiver_type = 'group' AND receiver_id = ?
                ORDER BY id DESC LIMIT 50
            ");
            $stmt->execute([$contact_id]);
        } else {
            $stmt = $db->prepare("
                SELECT id, is_edited, is_recalled FROM messages
                WHERE (
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR
                    (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                )
                ORDER BY id DESC LIMIT 50
            ");
            $stmt->execute([
                $my_type, $my_id, $contact_type, $contact_id,
                $contact_type, $contact_id, $my_type, $my_id
            ]);
        }
        $monitored_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($monitored_messages)) {
            $monitored_ids = array_column($monitored_messages, 'id');
            $placeholders = implode(',', array_fill(0, count($monitored_ids), '?'));
            
            $r_stmt = $db->prepare("
                SELECT message_id, reactor_type, reactor_id, reaction_emoji
                FROM message_reactions
                WHERE message_id IN ($placeholders)
            ");
            $r_stmt->execute($monitored_ids);
            $monitored_reactions = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reactions_by_msg = [];
            foreach ($monitored_reactions as $r) {
                $reactions_by_msg[$r['message_id']][] = $r;
            }
            
            $changed_ids = [];
            foreach ($monitored_messages as $m) {
                $m_id = intval($m['id']);
                $reactions = $reactions_by_msg[$m_id] ?? [];
                usort($reactions, function($a, $b) {
                    return strcmp($a['reactor_type'].$a['reactor_id'].$a['reaction_emoji'], $b['reactor_type'].$b['reactor_id'].$b['reaction_emoji']);
                });
                $react_str = json_encode($reactions);
                $current_state = "{$m['is_edited']}_{$m['is_recalled']}_" . md5($react_str);
                
                if (isset($state_cache[$m_id])) {
                    if ($state_cache[$m_id] !== $current_state) {
                        $changed_ids[] = $m_id;
                        $state_cache[$m_id] = $current_state;
                    }
                } else {
                    $state_cache[$m_id] = $current_state;
                }
            }
            
            if (!empty($changed_ids)) {
                $updated_messages = fetchMessageDetails($db, $changed_ids);
                foreach ($updated_messages as $um) {
                    sseEvent('update', $um);
                }
            }
        }

        // 3. Monitor sidebar updates globally (new messages outside this active conversation)
        $new_global_max_id = getGlobalMaxMessageId($db, $my_type, $my_id, $group_ids);
        if ($new_global_max_id > $global_max_id) {
            sseEvent('sidebar_update', ['max_id' => $new_global_max_id]);
            $global_max_id = $new_global_max_id;
        }
        
        // Keepalive heartbeat
        echo ": heartbeat\n\n";
        flush();
        
    } catch (Exception $e) {
        echo ": error\n\n";
        flush();
    }
    
    sleep(2); // Tăng lên 2 giây để giảm 50% số lượng truy vấn DB liên tiếp
    $iteration++;
}

// Signal reconnect
sseEvent('reconnect', ['reason' => 'timeout']);
