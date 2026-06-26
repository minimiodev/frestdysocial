<?php
/**
 * AJAX Edit Post - Frest App
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$content = trim($_POST['content'] ?? '');
$user_id = getLoggedInUserId();

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Bài viết không hợp lệ.']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung bài viết không được trống.']);
    exit;
}

try {
    $db = getDB();

    // Check if post exists and check owner
    $post_stmt = $db->prepare("SELECT user_id, is_copyright_violation FROM posts WHERE id = ?");
    $post_stmt->execute([$post_id]);
    $post = $post_stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.']);
        exit;
    }

    if (intval($post['user_id']) !== intval($user_id)) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa bài viết này.']);
        exit;
    }

    if (intval($post['is_copyright_violation'] ?? 0) === 1) {
        echo json_encode(['success' => false, 'message' => 'Bài viết bị báo cáo vi phạm bản quyền và không thể chỉnh sửa.']);
        exit;
    }

    // Update
    $update_stmt = $db->prepare("UPDATE posts SET content = ? WHERE id = ?");
    $update_stmt->execute([$content, $post_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật bài viết thành công!',
        'content' => sanitize($content)
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
}

