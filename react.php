<?php
/**
 * AJAX React Action - Frest App
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$reply_id = isset($_POST['reply_id']) ? intval($_POST['reply_id']) : 0;
$type = trim($_POST['type'] ?? 'like');
$user_id = getLoggedInUserId();

$allowed_types = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

if ($post_id <= 0 && $reply_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
    exit;
}

if (!in_array($type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Cảm xúc không hợp lệ.']);
    exit;
}

try {
    $db = getDB();

    if ($reply_id > 0) {
        // --- Reacting to a Reply ---
        // Check if reply exists
        $reply_stmt = $db->prepare("SELECT COUNT(*) FROM replies WHERE id = ?");
        $reply_stmt->execute([$reply_id]);
        if ($reply_stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Bình luận không tồn tại.']);
            exit;
        }

        // Check if current reaction exists
        $check_stmt = $db->prepare("SELECT id, reaction_type FROM reactions WHERE user_id = ? AND reply_id = ? AND post_id IS NULL");
        $check_stmt->execute([$user_id, $reply_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            if ($existing['reaction_type'] === $type) {
                // Toggle off: Delete reaction
                $stmt = $db->prepare("DELETE FROM reactions WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $status = 'unreacted';
                $active_type = null;
            } else {
                // Update reaction
                $stmt = $db->prepare("UPDATE reactions SET reaction_type = ? WHERE id = ?");
                $stmt->execute([$type, $existing['id']]);
                $status = 'updated';
                $active_type = $type;
            }
        } else {
            // Insert reaction
            $stmt = $db->prepare("INSERT INTO reactions (user_id, reply_id, post_id, reaction_type) VALUES (?, ?, NULL, ?)");
            $stmt->execute([$user_id, $reply_id, $type]);
            $status = 'reacted';
            $active_type = $type;
        }

        // Get updated reactions summary for the reply
        $summary = getReplyReactionsSummary($reply_id);

        // Notify reply owner
        if (in_array($status, ['reacted', 'updated'])) {
            $reply_owner_stmt = $db->prepare("SELECT user_id FROM replies WHERE id = ?");
            $reply_owner_stmt->execute([$reply_id]);
            $reply_owner_id = $reply_owner_stmt->fetchColumn();
            if ($reply_owner_id) {
                createNotification($reply_owner_id, $user_id, 'reaction', null, $reply_id, $type);
            }
        }

    } else {
        // --- Reacting to a Post ---
        // Check if post exists
        $post_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE id = ?");
        $post_stmt->execute([$post_id]);
        if ($post_stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.']);
            exit;
        }

        // Check if current reaction exists
        $check_stmt = $db->prepare("SELECT id, reaction_type FROM reactions WHERE user_id = ? AND post_id = ? AND reply_id IS NULL");
        $check_stmt->execute([$user_id, $post_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            if ($existing['reaction_type'] === $type) {
                // Toggle off: Delete reaction
                $stmt = $db->prepare("DELETE FROM reactions WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $status = 'unreacted';
                $active_type = null;
            } else {
                // Update reaction
                $stmt = $db->prepare("UPDATE reactions SET reaction_type = ? WHERE id = ?");
                $stmt->execute([$type, $existing['id']]);
                $status = 'updated';
                $active_type = $type;
            }
        } else {
            // Insert reaction
            $stmt = $db->prepare("INSERT INTO reactions (user_id, post_id, reply_id, reaction_type) VALUES (?, ?, NULL, ?)");
            $stmt->execute([$user_id, $post_id, $type]);
            $status = 'reacted';
            $active_type = $type;
        }

        // Get updated reactions summary for the post
        $summary = getPostReactionsSummary($post_id);

        // Notify post owner
        if (in_array($status, ['reacted', 'updated'])) {
            $post_owner_stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
            $post_owner_stmt->execute([$post_id]);
            $post_owner_id = $post_owner_stmt->fetchColumn();
            if ($post_owner_id) {
                createNotification($post_owner_id, $user_id, 'reaction', $post_id, null, $type);
            }
        }


    } // end else (reacting to post)

    echo json_encode([
        'success' => true,
        'status' => $status,
        'active_type' => $active_type,
        'total' => $summary['total'],
        'types' => $summary['types']
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
}

