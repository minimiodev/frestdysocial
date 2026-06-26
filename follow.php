<?php
/**
 * AJAX Follow Action - Frests Lite
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$followed_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
$follower_id = getLoggedInUserId();

if ($followed_id <= 0 && $page_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Đối tượng không hợp lệ.']);
    exit;
}

try {
    $db = getDB();

    if ($page_id > 0) {
        // Check if page exists
        $page_stmt = $db->prepare("SELECT COUNT(*) FROM pages WHERE id = ?");
        $page_stmt->execute([$page_id]);
        if ($page_stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Trang không tồn tại.']);
            exit;
        }

        // Check if already following
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM page_follows WHERE user_id = ? AND page_id = ?");
        $check_stmt->execute([$follower_id, $page_id]);
        $is_following = ($check_stmt->fetchColumn() > 0);

        if ($is_following) {
            // Unfollow page
            $stmt = $db->prepare("DELETE FROM page_follows WHERE user_id = ? AND page_id = ?");
            $stmt->execute([$follower_id, $page_id]);
            $status = 'unfollowed';
        } else {
            // Follow page
            $stmt = $db->prepare("INSERT INTO page_follows (user_id, page_id) VALUES (?, ?)");
            $stmt->execute([$follower_id, $page_id]);
            $status = 'followed';
            // Notify the page owner
            $page_owner_stmt = $db->prepare("SELECT owner_id FROM pages WHERE id = ?");
            $page_owner_stmt->execute([$page_id]);
            $page_owner_id = $page_owner_stmt->fetchColumn();
            if ($page_owner_id) {
                createNotification($page_owner_id, $follower_id, 'follow', null, null, null);
            }
        }

        // Count new total page followers
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM page_follows WHERE page_id = ?");
        $count_stmt->execute([$page_id]);
        $followers_count = $count_stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'status' => $status,
            'followers_count' => $followers_count
        ]);
        exit;
    } else {
        if ($followed_id === $follower_id) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể tự theo dõi chính mình.']);
            exit;
        }

        // Check if target user exists
        $user_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $user_stmt->execute([$followed_id]);
        if ($user_stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại.']);
            exit;
        }

        // Check if already following
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = ?");
        $check_stmt->execute([$follower_id, $followed_id]);
        $is_following = ($check_stmt->fetchColumn() > 0);

        if ($is_following) {
            // Unfollow user
            $stmt = $db->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
            $stmt->execute([$follower_id, $followed_id]);
            $status = 'unfollowed';
        } else {
            // Follow user
            $stmt = $db->prepare("INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)");
            $stmt->execute([$follower_id, $followed_id]);
            $status = 'followed';
            createNotification($followed_id, $follower_id, 'follow', null, null, null);
        }

        // Count new total followers
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
        $count_stmt->execute([$followed_id]);
        $followers_count = $count_stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'status' => $status,
            'followers_count' => $followers_count
        ]);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
}

