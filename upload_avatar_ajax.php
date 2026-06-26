<?php
/**
 * AJAX Avatar Upload handler - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

$me = getLoggedInUser();
if (!$me) {
    echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

$data = $_POST['cropped_avatar'] ?? '';
if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy dữ liệu ảnh.']);
    exit;
}

try {
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]);
        if ($type === 'jpeg') $type = 'jpg';
        
        if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ảnh không hỗ trợ.']);
            exit;
        }

        $decoded_data = base64_decode($data);
        if ($decoded_data === false) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu ảnh không hợp lệ.']);
            exit;
        }

        $new_name = 'avatar_' . uniqid() . '.' . $type;
        $db_save_name = 'users/' . $me['username'] . '/' . $new_name;
        $dest = getUserUploadPath($me['username'], 'avatars') . $new_name;

        if (file_put_contents($dest, $decoded_data)) {
            $db = getDB();
            
            $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
            if ($page_id > 0) {
                // Verify page ownership
                $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? AND owner_id = ?");
                $stmt->execute([$page_id, $me['id']]);
                $page = $stmt->fetch();
                if (!$page) {
                    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa Trang này.']);
                    exit;
                }
                
                // Update pages table
                $stmt = $db->prepare("UPDATE pages SET avatar_filename = ? WHERE id = ?");
                $stmt->execute([$db_save_name, $page_id]);
                
                $avatar_url = AVATARS_URL . '/' . $db_save_name;
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật ảnh đại diện Trang thành công!',
                    'avatar_filename' => $db_save_name,
                    'avatar_url' => $avatar_url
                ]);
                exit;
            } else {
                // Update users table
                $stmt = $db->prepare("UPDATE users SET avatar_filename = ? WHERE id = ?");
                $stmt->execute([$db_save_name, $me['id']]);

                // Sync session avatar
                $_SESSION['avatar'] = $db_save_name;

                // Return success with url and new filename
                $avatar_url = AVATARS_URL . '/' . $db_save_name;
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật ảnh đại diện thành công!',
                    'avatar_filename' => $db_save_name,
                    'avatar_url' => $avatar_url
                ]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh trên máy chủ.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Định dạng dữ liệu base64 không đúng.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
    exit;
}
