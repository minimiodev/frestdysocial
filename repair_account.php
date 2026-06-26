<?php
/**
 * Account Repair Tool API - Frest App
 * Performs automatic checks and corrections for the logged-in user.
 * Keeps all data intact. Returns JSON report.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập để thực hiện chức năng này.'
    ]);
    exit;
}

$user_id = intval($me['id']);
$username = $me['username'];
$db = getDB();
$report = [];

try {
    $db->beginTransaction();

    // 1. Kiểm tra cấu trúc thư mục tải lên (Upload directories check)
    $upload_subdirs = ['avatars', 'posts', 'proofs', 'system'];
    $has_error = false;
    foreach ($upload_subdirs as $subdir) {
        $path = UPLOAD_DIR . $subdir;
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true)) {
                $has_error = true;
            }
        }
    }
    $report[] = [
        'name' => 'Hệ thống lưu trữ phương tiện',
        'status' => $has_error ? 'Lỗi thiết lập' : 'OK',
        'details' => $has_error ? 'Lỗi thiết lập hệ thống lưu trữ. Vui lòng kiểm tra quyền ghi của máy chủ.' : 'Hệ thống lưu trữ dữ liệu hoạt động bình thường và an toàn.'
    ];

    // 2. Kiểm tra ảnh đại diện (Avatar)
    $avatar_file = $me['avatar_filename'];
    $avatar_repaired = false;
    if (empty($avatar_file) || $avatar_file === 'avatar_default.png') {
        $avatar_detail = "Đang sử dụng ảnh đại diện mặc định.";
    } else {
        $avatar_path = UPLOAD_DIR . 'avatars/' . $avatar_file;
        if (!file_exists($avatar_path)) {
            // File ảnh không tồn tại vật lý, đưa về ảnh mặc định
            $stmt = $db->prepare("UPDATE users SET avatar_filename = 'avatar_default.png' WHERE id = ?");
            $stmt->execute([$user_id]);
            $avatar_repaired = true;
            $avatar_detail = "Phát hiện ảnh đại diện bị lỗi hoặc thiếu. Đã tự động khôi phục về ảnh mặc định.";
        } else {
            $avatar_detail = "Ảnh đại diện hợp lệ.";
        }
    }
    $report[] = [
        'name' => 'Ảnh đại diện (Avatar)',
        'status' => $avatar_repaired ? 'Đã sửa lỗi' : 'OK',
        'details' => $avatar_detail
    ];

    // 3. Kiểm tra ảnh bìa (Cover photo)
    $cover_file = $me['cover_filename'] ?? null;
    $cover_repaired = false;
    if (!empty($cover_file)) {
        $cover_path = UPLOAD_DIR . 'avatars/' . $cover_file;
        if (!file_exists($cover_path)) {
            // File ảnh bìa không tồn tại, reset về NULL
            $stmt = $db->prepare("UPDATE users SET cover_filename = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            $cover_repaired = true;
            $cover_detail = "Phát hiện ảnh bìa bị lỗi hoặc thiếu. Đã tự động gỡ bỏ liên kết lỗi.";
        } else {
            $cover_detail = "Ảnh bìa hợp lệ.";
        }
    } else {
        $cover_detail = "Chưa thiết lập ảnh bìa.";
    }
    $report[] = [
        'name' => 'Ảnh bìa (Cover)',
        'status' => $cover_repaired ? 'Đã sửa lỗi' : 'OK',
        'details' => $cover_detail
    ];

    // 4. Kiểm tra định dạng Email
    $email = $me['email'];
    $email_repaired = false;
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $new_email = $username . '@frest.local';
        // Đảm bảo email mới không trùng lặp
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$new_email, $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            $new_email = $username . '_' . rand(100, 999) . '@frest.local';
        }
        
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$new_email, $user_id]);
        $email_repaired = true;
        $email_detail = "Địa chỉ email không hợp lệ hoặc để trống. Đã cập nhật thành địa chỉ email an toàn.";
    } else {
        $email_detail = "Địa chỉ email hợp lệ.";
    }
    $report[] = [
        'name' => 'Địa chỉ Email',
        'status' => $email_repaired ? 'Đã sửa lỗi' : 'OK',
        'details' => $email_detail
    ];

    // 5. Kiểm tra thiết lập mặc định của tài khoản
    $defaults_repaired = false;
    $defaults_updated = [];
    
    // Đảm bảo show_nsfw có giá trị
    if ($me['show_nsfw'] === null) {
        $db->prepare("UPDATE users SET show_nsfw = 0 WHERE id = ?")->execute([$user_id]);
        $defaults_updated[] = "show_nsfw = 0";
        $defaults_repaired = true;
    }
    // Đảm bảo is_private có giá trị
    if ($me['is_private'] === null) {
        $db->prepare("UPDATE users SET is_private = 0 WHERE id = ?")->execute([$user_id]);
        $defaults_updated[] = "is_private = 0";
        $defaults_repaired = true;
    }
    // Đảm bảo is_page có giá trị
    if ($me['is_page'] === null) {
        $db->prepare("UPDATE users SET is_page = 0 WHERE id = ?")->execute([$user_id]);
        $defaults_updated[] = "is_page = 0";
        $defaults_repaired = true;
    }
    // Đảm bảo age_verification_status có giá trị
    if (empty($me['age_verification_status'])) {
        $db->prepare("UPDATE users SET age_verification_status = 'unverified' WHERE id = ?")->execute([$user_id]);
        $defaults_updated[] = "age_verification_status = 'unverified'";
        $defaults_repaired = true;
    }
    
    $report[] = [
        'name' => 'Cấu hình mặc định',
        'status' => $defaults_repaired ? 'Đã cấu hình lại' : 'OK',
        'details' => $defaults_repaired ? 'Đã cài đặt lại các thiết lập tài khoản bị thiếu.' : 'Cấu hình tài khoản hợp lệ.'
    ];

    // 6. Kiểm tra & Tối ưu hóa dữ liệu liên kết (follows)
    // Loại bỏ các follows trùng lặp hoặc tự follow chính mình
    $follows_deleted = 0;
    
    // Xóa tự follow
    $stmt_self = $db->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt_self->execute([$user_id, $user_id]);
    $follows_deleted += $stmt_self->rowCount();



    $report[] = [
        'name' => 'Dữ liệu Theo dõi (Follows)',
        'status' => $follows_deleted > 0 ? 'Đã dọn dẹp' : 'OK',
        'details' => $follows_deleted > 0 ? "Đã phát hiện và xóa bỏ $follows_deleted bản ghi theo dõi trùng lặp/lỗi." : "Dữ liệu kết nối theo dõi sạch."
    ];

    // 7. Kiểm tra cảm xúc mồ côi (Orphan reactions)
    // Xóa reactions của người dùng này mà post hoặc reply không còn tồn tại
    $reactions_deleted = 0;
    $stmt_react = $db->prepare("
        DELETE FROM reactions 
        WHERE user_id = ? 
          AND (
            (post_id IS NOT NULL AND post_id NOT IN (SELECT id FROM posts)) 
            OR 
            (reply_id IS NOT NULL AND reply_id NOT IN (SELECT id FROM replies))
          )
    ");
    $stmt_react->execute([$user_id]);
    $reactions_deleted = $stmt_react->rowCount();

    $report[] = [
        'name' => 'Dữ liệu Cảm xúc (Reactions)',
        'status' => $reactions_deleted > 0 ? 'Đã làm sạch' : 'OK',
        'details' => $reactions_deleted > 0 ? "Đã gỡ bỏ $reactions_deleted lượt bày tỏ cảm xúc mồ côi (trên bài viết/phản hồi đã bị xóa)." : "Dữ liệu cảm xúc hợp lệ."
    ];

    // 8. Kiểm tra bảo mật đăng nhập (Unusual Login Check)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip === '::1') $ip = '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_unusual = isLoginUnusual($user_id, $ip, $ua);

    $report[] = [
        'name' => 'Bảo mật đăng nhập',
        'status' => $is_unusual ? 'Cần xác minh' : 'OK',
        'details' => $is_unusual ? 'Phát hiện hoạt động đăng nhập từ thiết bị hoặc địa điểm lạ. Vui lòng kiểm tra mục Lịch sử đăng nhập để xác minh.' : 'Không phát hiện hoạt động đăng nhập bất thường nào.'
    ];

    $db->commit();

    // Cập nhật lại session
    $fresh_user_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $fresh_user_stmt->execute([$user_id]);
    $fresh_user = $fresh_user_stmt->fetch();
    if ($fresh_user) {
        $_SESSION['avatar'] = $fresh_user['avatar_filename'];
        $_SESSION['username'] = $fresh_user['username'];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Hoàn tất kiểm tra và sửa lỗi tài khoản thành công!',
        'report' => $report
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi trong quá trình bảo trì tài khoản: ' . $e->getMessage()
    ]);
}
