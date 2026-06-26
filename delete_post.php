<?php
/**
 * AJAX Delete Post - Frest App
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$is_logged_in = isUserLoggedIn() || isAdminLoggedIn();
if (!$is_logged_in) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$user_id = getLoggedInUserId();

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Bài viết không hợp lệ.']);
    exit;
}

try {
    $db = getDB();

    // Check if post exists and get file details
    $post_stmt = $db->prepare("SELECT user_id, image_filename, video_filename, is_copyright_violation FROM posts WHERE id = ?");
    $post_stmt->execute([$post_id]);
    $post = $post_stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại.']);
        exit;
    }

    // Must be either post author or logged-in admin
    $is_owner = (intval($post['user_id']) === intval($user_id));
    $is_admin = isAdminLoggedIn();

    if (!$is_owner && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa bài viết này.']);
        exit;
    }

    // If marked as copyright violation, non-admin users cannot delete it!
    if (intval($post['is_copyright_violation'] ?? 0) === 1 && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Bài viết bị báo cáo vi phạm bản quyền và không thể tự xóa. Vui lòng liên hệ quản trị viên.']);
        exit;
    }

    // Delete associated files
    if (!empty($post['image_filename'])) {
        $img_path = UPLOAD_DIR . 'posts/' . $post['image_filename'];
        if (file_exists($img_path)) {
            @unlink($img_path);
        }
    }

    if (!empty($post['video_filename'])) {
        $video_path = UPLOAD_DIR . 'posts/' . $post['video_filename'];
        if (file_exists($video_path)) {
            @unlink($video_path);
        }
    }

    // Delete from DB (replies and reactions will cascade delete if database triggers are configured, 
    // which they are: ON DELETE CASCADE)
    $delete_stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
    $delete_stmt->execute([$post_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa bài viết thành công!'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
}

