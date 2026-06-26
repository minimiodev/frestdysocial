<?php
/**
 * sse_stream.php — Server-Sent Events endpoint for real-time notifications.
 * Polls the notifications table every 10 seconds (activity page) or 20s (badge-only).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Must be logged in
if (!isUserLoggedIn()) {
    http_response_code(401);
    exit;
}

$me = getLoggedInUser();
$user_id = intval($me['id']);
$identity = getCurrentIdentity();

// CRITICAL: Release session lock immediately so other tabs don't freeze
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
        $unread = 0;
        $unread_chat = 0;
        
        $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_dismissed=0 AND is_read=0");
        $cnt_stmt->execute([$user_id]);
        $unread = intval($cnt_stmt->fetchColumn());
        
        if ($identity) {
            $chat_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
            $chat_cnt_stmt->execute([$identity['type'], $identity['id']]);
            $unread_chat = intval($chat_cnt_stmt->fetchColumn());
        }
        
        $new_notifications = [];
        $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
        
        if ($last_id > 0 && !isset($_GET['badge_only'])) {
            $stmt = $db->prepare(
                "SELECT n.id, n.type, n.ref_post_id, n.ref_reply_id, n.detail, n.created_at,
                        u.username AS actor_username, u.full_name AS actor_name,
                        u.avatar_filename AS actor_avatar,
                        u.verification_type, u.is_page AS actor_is_page,
                        p.post_token
                 FROM notifications n
                 JOIN users u ON u.id = n.actor_id
                 LEFT JOIN posts p ON p.id = n.ref_post_id
                 WHERE n.user_id = ? AND n.id > ? AND n.is_dismissed = 0
                 ORDER BY n.id ASC
                 LIMIT 20"
            );
            $stmt->execute([$user_id, $last_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $upd = $db->prepare("UPDATE notifications SET is_read=1 WHERE id IN ($placeholders)");
                $upd->execute($ids);
                
                foreach ($rows as $row) {
                    $avatar_url = AVATARS_URL . '/' . htmlspecialchars($row['actor_avatar'] ?? 'avatar_default.png');
                    $new_notifications[] = [
                        'id'            => intval($row['id']),
                        'type'          => $row['type'],
                        'ref_post_id'   => $row['ref_post_id'] ? intval($row['ref_post_id']) : null,
                        'post_token'    => $row['post_token'] ? htmlspecialchars($row['post_token']) : null,
                        'ref_reply_id'  => $row['ref_reply_id'] ? intval($row['ref_reply_id']) : null,
                        'detail'        => $row['detail'],
                        'actor_username'=> $row['actor_username'],
                        'actor_name'    => $row['actor_name'] ?: $row['actor_username'],
                        'actor_avatar'  => $avatar_url,
                        'created_at'    => $row['created_at'],
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'unread_count' => $unread,
            'unread_chat_count' => $unread_chat,
            'notifications' => $new_notifications
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

// Ensure fast composite index exists on notifications table
try {
    $db = getDB();
    $db->exec("CREATE INDEX idx_notif_stream ON notifications (user_id, is_dismissed, id)");
} catch (Exception $e) {}

// Starting point: client tells us the last notification ID it has seen
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
// Badge-only mode = last_id=0 and no 'full' param from global badge (non-activity pages)
$badge_only = !isset($_GET['full']) && $last_id === 0;
$first_connect = true;
$last_unread_count = -1;
$last_unread_chat_count = -1;

$sleep_sec  = $badge_only ? 8 : 4; // badge-only pages poll every 8s, full feed polls every 4s (giãn để giảm tải DB)
$loop_limit = $badge_only ? 12 : 15; // Giới hạn mỗi kết nối SSE ~1.6 phút (badge) hoặc 1 phút (full) để giải phóng PHP process
$iteration  = 0;

function sseEvent($event, $data, $id = null) {
    if ($id !== null) {
        echo "id: {$id}\n";
    }
    echo "event: {$event}\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Send a keepalive comment immediately on connect
echo ": connected\n\n";
flush();

while ($iteration < $loop_limit) {
    // Check if client disconnected
    if (connection_aborted()) break;

    try {
        $db = getDB();

        // On first connect, also send unread count (so badge syncs immediately)
        if ($first_connect) {
            $cnt_stmt = $db->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_dismissed=0 AND is_read=0"
            );
            $cnt_stmt->execute([$user_id]);
            $unread = intval($cnt_stmt->fetchColumn());
            $last_unread_count = $unread;

            // Get unread chat count based on active identity
            $unread_chat = 0;
            if ($identity) {
                $chat_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
                $chat_cnt_stmt->execute([$identity['type'], $identity['id']]);
                $unread_chat = intval($chat_cnt_stmt->fetchColumn());
            }
            $last_unread_chat_count = $unread_chat;

            // Also get last existing ID so subsequent polls don't resend old notifications
            $last_stmt = $db->prepare(
                "SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=?"
            );
            $last_stmt->execute([$user_id]);
            $last_id = intval($last_stmt->fetchColumn());

            sseEvent('sync', [
                'unread_count' => $unread,
                'unread_chat_count' => $unread_chat,
                'last_id' => $last_id
            ]);
            $first_connect = false;

            sleep($sleep_sec);
            $iteration++;
            continue;
        }

        if (!$badge_only) {
            // Fetch new notifications since last_id
            $stmt = $db->prepare(
                "SELECT n.id, n.type, n.ref_post_id, n.ref_reply_id, n.detail, n.created_at,
                        u.username AS actor_username, u.full_name AS actor_name,
                        u.avatar_filename AS actor_avatar,
                        u.verification_type, u.is_page AS actor_is_page,
                        p.post_token
                 FROM notifications n
                 JOIN users u ON u.id = n.actor_id
                 LEFT JOIN posts p ON p.id = n.ref_post_id
                 WHERE n.user_id = ? AND n.id > ? AND n.is_dismissed = 0
                 ORDER BY n.id ASC
                 LIMIT 20"
              );
            $stmt->execute([$user_id, $last_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                // Mark as read
                $ids = array_column($rows, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $upd = $db->prepare("UPDATE notifications SET is_read=1 WHERE id IN ($placeholders)");
                $upd->execute($ids);

                foreach ($rows as $row) {
                    $last_id = max($last_id, intval($row['id']));
                    $avatar_url = AVATARS_URL . '/' . htmlspecialchars($row['actor_avatar'] ?? 'avatar_default.png');

                    sseEvent('notification', [
                        'id'            => intval($row['id']),
                        'type'          => $row['type'],
                        'ref_post_id'   => $row['ref_post_id'] ? intval($row['ref_post_id']) : null,
                        'post_token'    => $row['post_token'] ? htmlspecialchars($row['post_token']) : null,
                        'ref_reply_id'  => $row['ref_reply_id'] ? intval($row['ref_reply_id']) : null,
                        'detail'        => $row['detail'],
                        'actor_username'=> $row['actor_username'],
                        'actor_name'    => $row['actor_name'] ?: $row['actor_username'],
                        'actor_avatar'  => $avatar_url,
                        'created_at'    => $row['created_at'],
                    ], $row['id']);
                }
            }
        }

        // Global Badge update checks at the end of cycle
        $cnt_stmt = $db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_dismissed=0 AND is_read=0"
        );
        $cnt_stmt->execute([$user_id]);
        $unread = intval($cnt_stmt->fetchColumn());

        $unread_chat = 0;
        if ($identity) {
            $chat_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
            $chat_cnt_stmt->execute([$identity['type'], $identity['id']]);
            $unread_chat = intval($chat_cnt_stmt->fetchColumn());
        }

        if ($unread !== $last_unread_count || $unread_chat !== $last_unread_chat_count) {
            $last_unread_count = $unread;
            $last_unread_chat_count = $unread_chat;
            sseEvent('badge', [
                'unread_count' => $unread,
                'unread_chat_count' => $unread_chat
            ]);
        }

        // Keepalive heartbeat every cycle
        echo ": heartbeat\n\n";
        flush();

    } catch (Exception $e) {
        // Don't crash the stream on DB errors
        echo ": db_error\n\n";
        flush();
    }

    sleep($sleep_sec);
    $iteration++;
}

// Signal client to reconnect after 60 iterations (~5 minutes)
sseEvent('reconnect', ['reason' => 'timeout']);
