<?php
/**
 * Delete Page Action Handler - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    
    if ($page_id > 0) {
        try {
            $db = getDB();
            
            // Verify that the current logged-in user is the owner of the page
            $stmt = $db->prepare("SELECT id, page_name, avatar_filename, cover_filename FROM pages WHERE id = ? AND owner_id = ?");
            $stmt->execute([$page_id, $me['id']]);
            $page = $stmt->fetch();
            
            if ($page) {
                // If the user is currently acting as this page, switch back to personal profile identity
                if (isset($_SESSION['active_page_id']) && intval($_SESSION['active_page_id']) === $page_id) {
                    unset($_SESSION['active_page_id']);
                }
                
                // Delete avatar files if they are not default
                if (!empty($page['avatar_filename']) && $page['avatar_filename'] !== 'avatar_default.png') {
                    $avatar_path = UPLOAD_DIR . 'avatars/' . $page['avatar_filename'];
                    if (file_exists($avatar_path)) {
                        @unlink($avatar_path);
                    }
                }
                if (!empty($page['cover_filename'])) {
                    $cover_path = UPLOAD_DIR . 'avatars/' . $page['cover_filename'];
                    if (file_exists($cover_path)) {
                        @unlink($cover_path);
                    }
                }

                // Delete the page from database
                // (Dependent rows in posts, replies, page_follows are automatically deleted by ON DELETE CASCADE)
                $delete_stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
                $delete_stmt->execute([$page_id]);
                
                header("Location: pages.php?success=" . urlencode("Đã xóa Trang '" . $page['page_name'] . "' thành công!"));
                exit;
            } else {
                header("Location: pages.php?error=" . urlencode("Bạn không có quyền xóa Trang này hoặc Trang không tồn tại."));
                exit;
            }
        } catch (Exception $e) {
            header("Location: pages.php?error=" . urlencode("Lỗi hệ thống: " . $e->getMessage()));
            exit;
        }
    }
}

header("Location: index.php");
exit;
