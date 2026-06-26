<?php
/**
 * AJAX Endpoint - Pin / Unpin Post - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện chức năng này.']);
    exit;
}

$user_id = getLoggedInUserId();
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'pin' or 'unpin'

if ($post_id <= 0 || !in_array($action, ['pin', 'unpin'])) {
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $identity = getCurrentIdentity();
    $page_id = ($identity && $identity['type'] === 'page') ? $identity['id'] : null;

    // Fetch post to check ownership
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại hoặc đã bị xóa.']);
        exit;
    }

    // Verify ownership: Post must belong to the active identity
    $owns = false;
    if ($page_id) {
        $owns = (intval($post['page_id'] ?? 0) === intval($page_id) && intval($post['user_id'] ?? 0) === intval($user_id));
    } else {
        $owns = (empty($post['page_id']) && intval($post['user_id'] ?? 0) === intval($user_id));
    }

    if (!$owns) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền ghim bài viết này.']);
        exit;
    }

    if ($action === 'pin') {
        // Unpin any existing pinned post for this identity
        if ($page_id) {
            $unpin_stmt = $db->prepare("UPDATE posts SET is_pinned = 0 WHERE page_id = ?");
            $unpin_stmt->execute([$page_id]);
        } else {
            $unpin_stmt = $db->prepare("UPDATE posts SET is_pinned = 0 WHERE user_id = ? AND page_id IS NULL");
            $unpin_stmt->execute([$user_id]);
        }

        // Pin current post
        $pin_stmt = $db->prepare("UPDATE posts SET is_pinned = 1 WHERE id = ?");
        $pin_stmt->execute([$post_id]);

        echo json_encode(['success' => true, 'message' => 'Đã ghim bài viết lên đầu trang.', 'is_pinned' => 1]);
    } else {
        // Unpin current post
        $pin_stmt = $db->prepare("UPDATE posts SET is_pinned = 0 WHERE id = ?");
        $pin_stmt->execute([$post_id]);

        echo json_encode(['success' => true, 'message' => 'Đã bỏ ghim bài viết.', 'is_pinned' => 0]);
    }
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}
