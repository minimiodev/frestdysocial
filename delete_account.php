<?php
/**
 * AJAX Endpoint - Delete User Account
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$user_id = getLoggedInUserId();
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mật khẩu xác nhận.']);
    exit;
}

try {
    $db = getDB();
    
    // Fetch password hash and avatar filename
    $user_stmt = $db->prepare("SELECT password_hash, avatar_filename FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại.']);
        exit;
    }
    
    // Verify password
    if (!password_verify($confirm_password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không chính xác.']);
        exit;
    }
    
    // Check if the user has any posts flagged as copyright violations
    $copyright_check = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND is_copyright_violation = 1");
    $copyright_check->execute([$user_id]);
    $has_copyright = intval($copyright_check->fetchColumn() ?? 0) > 0;
    
    if ($has_copyright) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn có bài viết bị đánh dấu vi phạm bản quyền. Bạn không thể tự xóa tài khoản vào lúc này.']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // 1. Get posts media of user and delete files from disk
    $posts_stmt = $db->prepare("SELECT image_filename, video_filename FROM posts WHERE user_id = ?");
    $posts_stmt->execute([$user_id]);
    $posts = $posts_stmt->fetchAll();
    
    $posts_dir = UPLOAD_DIR . 'posts/';
    foreach ($posts as $p) {
        if (!empty($p['image_filename'])) {
            @unlink($posts_dir . $p['image_filename']);
        }
        if (!empty($p['video_filename'])) {
            @unlink($posts_dir . $p['video_filename']);
        }
    }
    
    // 2. Get user avatar and delete file if it's not the default one
    $avatar = $user['avatar_filename'];
    if ($avatar && $avatar !== 'avatar_default.png' && $avatar !== 'avatar_hoangdung.png' && $avatar !== 'avatar_anhtuan.png' && $avatar !== 'avatar_linhchi.png' && $avatar !== 'avatar_minhquan.png') {
        @unlink(UPLOAD_DIR . 'avatars/' . $avatar);
    }
    
    // 3. Delete comments on user's posts or written by user
    // Delete replies on user's posts
    $db->prepare("DELETE FROM replies WHERE post_id IN (SELECT id FROM posts WHERE user_id = ?)")->execute([$user_id]);
    // Delete replies written by user
    $db->prepare("DELETE FROM replies WHERE user_id = ?")->execute([$user_id]);
    
    // 4. Delete reactions on user's posts or made by user
    $db->prepare("DELETE FROM reactions WHERE post_id IN (SELECT id FROM posts WHERE user_id = ?)")->execute([$user_id]);
    $db->prepare("DELETE FROM reactions WHERE user_id = ?")->execute([$user_id]);
    
    // 5. Delete follows (both follower and followed)
    $db->prepare("DELETE FROM follows WHERE follower_id = ? OR followed_id = ?")->execute([$user_id, $user_id]);
    
    // 6. Delete posts
    $db->prepare("DELETE FROM posts WHERE user_id = ?")->execute([$user_id]);
    
    // 7. Delete user
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    
    $db->commit();
    
    // Logout session
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Tài khoản của bạn đã được xóa vĩnh viễn thành công.']);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa tài khoản: ' . $e->getMessage()]);
}

