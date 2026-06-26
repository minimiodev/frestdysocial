<?php
/**
 * Create Page Action Handler - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_name = trim($_POST['page_name'] ?? '');
    $page_username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['page_username'] ?? ''));
    $category = trim($_POST['category'] ?? 'Cộng đồng');
    $bio = trim($_POST['bio'] ?? '');

    // Validation
    if (empty($page_name) || strlen($page_name) < 2 || strlen($page_name) > 100) {
        $error_msg = "Tên Trang phải có độ dài từ 2 đến 100 ký tự.";
    } elseif (empty($page_username) || strlen($page_username) < 3 || strlen($page_username) > 30) {
        $error_msg = "Tên người dùng của Trang (Handle) phải từ 3 đến 30 ký tự và chỉ chứa chữ cái, số, dấu gạch dưới.";
    } else {
        // Check reserved usernames
        $reserved = ['admin', 'login', 'logout', 'register', 'profile', 'search', 'index', 'activity', 'settings', 'switch_identity', 'create_page', 'assets', 'includes', 'uploads'];
        if (in_array($page_username, $reserved)) {
            $error_msg = "Tên người dùng này được bảo vệ bởi hệ thống. Vui lòng chọn tên khác.";
        } else {
            try {
                $db = getDB();
                // Check if username exists in users table
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$page_username]);
                $user_exists = $stmt->fetchColumn() > 0;

                // Check if page_username exists in pages table
                $stmt = $db->prepare("SELECT COUNT(*) FROM pages WHERE page_username = ?");
                $stmt->execute([$page_username]);
                $page_exists = $stmt->fetchColumn() > 0;

                if ($user_exists || $page_exists) {
                    $error_msg = "Tên người dùng của Trang (Handle) này đã được sử dụng.";
                }
            } catch (Exception $e) {
                $error_msg = "Lỗi hệ thống: " . $e->getMessage();
            }
        }
    }

    if (empty($error_msg)) {
        // Handle upload avatar
        $avatar_filename = 'avatar_default.png';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                $new_name = 'page_avatar_' . uniqid() . '.' . $file_ext;
                $dest = UPLOAD_DIR . 'avatars/' . $new_name;
                if (move_uploaded_file($file_tmp, $dest)) {
                    $avatar_filename = $new_name;
                }
            }
        }

        // Handle upload cover
        $cover_filename = null;
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover']['tmp_name'];
            $file_name = $_FILES['cover']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                $new_name = 'page_cover_' . uniqid() . '.' . $file_ext;
                $dest = UPLOAD_DIR . 'avatars/' . $new_name; // Save in avatars directory too
                if (move_uploaded_file($file_tmp, $dest)) {
                    $cover_filename = $new_name;
                }
            }
        }

        // Save to DB
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO pages (owner_id, page_name, page_username, avatar_filename, cover_filename, bio, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$me['id'], $page_name, $page_username, $avatar_filename, $cover_filename, $bio, $category]);
            $new_page_id = $db->lastInsertId();

            // Log page creation name history
            $stmt_hist = $db->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name) VALUES ('page', ?, NULL, ?)");
            $stmt_hist->execute([$new_page_id, $page_name]);

            // Set as active identity
            $_SESSION['active_page_id'] = $new_page_id;

            // Redirect to the pages management page
            header("Location: pages.php?success=" . urlencode("Tạo Trang mới thành công!"));
            exit;
        } catch (Exception $e) {
            $error_msg = "Lỗi khi lưu Trang vào cơ sở dữ liệu: " . $e->getMessage();
        }
    }
}

// If there was an error, redirect back with error message
if (!empty($error_msg)) {
    header("Location: pages.php?error=" . urlencode($error_msg));
    exit;
}

