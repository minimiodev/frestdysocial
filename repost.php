<?php
/**
 * AJAX Endpoint - Repost / Share Post - Frest App
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
$comment = trim($_POST['comment'] ?? '');

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã bài viết không hợp lệ.']);
    exit;
}

try {
    $db = getDB();

    // Verify target post exists and is not copyright violation
    $stmt = $db->prepare("SELECT id, is_copyright_violation FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $target_post = $stmt->fetch();

    if (!$target_post) {
        echo json_encode(['success' => false, 'message' => 'Bài viết gốc không tồn tại hoặc đã bị xóa.']);
        exit;
    }

    if (intval($target_post['is_copyright_violation']) === 1) {
        echo json_encode(['success' => false, 'message' => 'Bài viết này đang bị khiếu nại bản quyền và không thể đăng lại.']);
        exit;
    }

    $identity = getCurrentIdentity();
    $page_id = ($identity && $identity['type'] === 'page') ? $identity['id'] : null;

    // Determine if it is a simple repost or quote repost
    if (empty($comment)) {
        // Simple repost: Toggle logic
        // Check if already reposted
        if ($page_id) {
            $check_stmt = $db->prepare("SELECT id FROM posts WHERE user_id = ? AND page_id = ? AND repost_of_post_id = ? AND (content = '' OR content IS NULL)");
            $check_stmt->execute([$user_id, $page_id, $post_id]);
        } else {
            $check_stmt = $db->prepare("SELECT id FROM posts WHERE user_id = ? AND page_id IS NULL AND repost_of_post_id = ? AND (content = '' OR content IS NULL)");
            $check_stmt->execute([$user_id, $post_id]);
        }
        $existing = $check_stmt->fetch();
 
        if ($existing) {
            // Delete the repost (Undo repost)
            $delete = $db->prepare("DELETE FROM posts WHERE id = ?");
            $delete->execute([$existing['id']]);
            $action = 'unreposted';
            $msg = 'Đã hủy đăng lại bài viết.';
        } else {
            $post_token = bin2hex(random_bytes(8));
            // Insert new simple repost
            $insert = $db->prepare("INSERT INTO posts (user_id, content, repost_of_post_id, allow_download, page_id, post_token) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$user_id, '', $post_id, 1, $page_id, $post_token]);
            $action = 'reposted';
            $msg = 'Đã đăng lại bài viết thành công!';
        }
    } else {
        // Quote repost: Always insert a new post with comment content
        // Detect link and fetch preview
        $link_preview_url   = null;
        $link_preview_title = null;
        $link_preview_desc  = null;
        $link_preview_image = null;
        
        if (preg_match('/https?:\/\/[^\s]+/i', $comment, $matches)) {
            $detected_url = $matches[0];
            require_once __DIR__ . '/fetch_link_preview.php';
            $preview = fetchLinkPreview($detected_url);
            if (!empty($preview['title'])) {
                $link_preview_url   = $preview['url'] ?? null;
                $link_preview_title = $preview['title'] ?? null;
                $link_preview_desc  = $preview['description'] ?? null;
                $link_preview_image = $preview['image'] ?? null;
            }
        }

        // Handle uploaded media files (images, videos)
        $uploaded_images = [];
        $video_filename = null;

        if (isset($_FILES['repost_media'])) {
            $files = [];
            if (is_array($_FILES['repost_media']['name'])) {
                $file_count = count($_FILES['repost_media']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['repost_media']['error'][$i] === UPLOAD_ERR_OK) {
                        $files[] = [
                            'name' => $_FILES['repost_media']['name'][$i],
                            'type' => $_FILES['repost_media']['type'][$i],
                            'tmp_name' => $_FILES['repost_media']['tmp_name'][$i],
                            'error' => $_FILES['repost_media']['error'][$i],
                            'size' => $_FILES['repost_media']['size'][$i]
                        ];
                    }
                }
            } else {
                if ($_FILES['repost_media']['error'] === UPLOAD_ERR_OK) {
                    $files[] = $_FILES['repost_media'];
                }
            }

            $me = getLoggedInUser();
            $user_posts_dir = getUserUploadPath($me['username'], 'posts');
            $db_save_prefix = 'users/' . $me['username'] . '/';

            foreach ($files as $file) {
                $file_tmp = $file['tmp_name'];
                $file_name = $file['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $video_exts = ['mp4', 'webm', 'mov', 'ogg'];

                if (in_array($file_ext, $image_exts)) {
                    $new_name = 'post_' . uniqid() . '.' . $file_ext;
                    $dest = $user_posts_dir . $new_name;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $uploaded_images[] = $db_save_prefix . $new_name;
                    }
                } elseif (in_array($file_ext, $video_exts) && empty($video_filename)) {
                    $new_name = 'post_' . uniqid() . '.' . $file_ext;
                    $dest = $user_posts_dir . $new_name;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $video_filename = $db_save_prefix . $new_name;
                    }
                }
            }
        }
        $image_filename = !empty($uploaded_images) ? implode(',', $uploaded_images) : null;

        $post_token = bin2hex(random_bytes(8));
        $insert = $db->prepare("INSERT INTO posts (user_id, content, repost_of_post_id, allow_download, page_id, link_preview_url, link_preview_title, link_preview_desc, link_preview_image, image_filename, video_filename, post_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$user_id, $comment, $post_id, 1, $page_id, $link_preview_url, $link_preview_title, $link_preview_desc, $link_preview_image, $image_filename, $video_filename, $post_token]);
        $action = 'quoted';
        $msg = 'Đã trích dẫn bài viết thành công!';
    }

    // Count total reposts for this post
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE repost_of_post_id = ?");
    $count_stmt->execute([$post_id]);
    $repost_count = intval($count_stmt->fetchColumn());

    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => $msg,
        'repost_count' => $repost_count
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}

