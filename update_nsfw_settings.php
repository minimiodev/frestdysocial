<?php
/**
 * AJAX Endpoint - Update NSFW Settings & Age Verification - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$is_logged_in = isUserLoggedIn();

if ($is_logged_in) {
    $user_id = getLoggedInUserId();
    try {
        $db = getDB();
        
        if ($action === 'submit_verification') {
            $dob = $_POST['dob'] ?? '';
            
            if (empty($dob)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền ngày sinh hợp lệ.']);
                exit;
            }
            
            // Check age
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            if ($birthDate > $today) {
                echo json_encode(['success' => false, 'message' => 'Ngày sinh không hợp lệ.']);
                exit;
            }
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18) {
                echo json_encode(['success' => false, 'message' => 'Bạn phải từ 18 tuổi trở lên để thực hiện xác minh độ tuổi.']);
                exit;
            }
            
            // Handle file upload
            if (!isset($_FILES['id_proof']) || $_FILES['id_proof']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng đính kèm ảnh chụp CMND/CCCD hoặc Hộ chiếu của bạn.']);
                exit;
            }
            
            $file_tmp = $_FILES['id_proof']['tmp_name'];
            $file_name = $_FILES['id_proof']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                echo json_encode(['success' => false, 'message' => 'Định dạng file không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP.']);
                exit;
            }
            
            // Ensure proofs directory exists
            $proofs_dir = UPLOAD_DIR . 'proofs/';
            if (!is_dir($proofs_dir)) {
                mkdir($proofs_dir, 0777, true);
            }
            
            $new_name = 'proof_' . $user_id . '_' . uniqid() . '.' . $file_ext;
            $dest = $proofs_dir . $new_name;
            
            if (move_uploaded_file($file_tmp, $dest)) {
                // Update DB
                $stmt = $db->prepare("UPDATE users SET dob = ?, id_proof_filename = ?, age_verification_status = 'pending', is_adult = 0 WHERE id = ?");
                $stmt->execute([$dob, $new_name, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Yêu cầu xác minh đã được gửi thành công và đang chờ duyệt.']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi lưu trữ ảnh minh chứng trên máy chủ.']);
                exit;
            }
        } elseif ($action === 'toggle_nsfw') {
            $value = isset($_POST['value']) ? intval($_POST['value']) : 0;
            
            if ($value === 0) {
                // Reset verification
                // Fetch proof file to delete it
                $stmt_proof = $db->prepare("SELECT id_proof_filename FROM users WHERE id = ?");
                $stmt_proof->execute([$user_id]);
                $proof_file = $stmt_proof->fetchColumn();
                if (!empty($proof_file)) {
                    @unlink(UPLOAD_DIR . 'proofs/' . $proof_file);
                }
                
                $stmt = $db->prepare("UPDATE users SET show_nsfw = 0, is_adult = 0, age_verification_status = 'unverified', id_proof_filename = NULL, dob = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Đã tắt chế độ xem nội dung nhạy cảm. Trạng thái xác minh độ tuổi của bạn đã được lập lại để bảo mật.', 
                    'show_nsfw' => 0,
                    'reset_verification' => true
                ]);
                exit;
            } else {
                // Check if user is verified adult first
                $check = $db->prepare("SELECT is_adult FROM users WHERE id = ?");
                $check->execute([$user_id]);
                $is_adult = intval($check->fetchColumn() ?? 0);
                
                if ($is_adult === 0) {
                    echo json_encode(['success' => false, 'message' => 'Bạn cần gửi yêu cầu xác minh độ tuổi và được admin duyệt trước khi bật chế độ này.']);
                    exit;
                }
                
                $stmt = $db->prepare("UPDATE users SET show_nsfw = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                echo json_encode(['success' => true, 'message' => 'Đã bật chế độ xem nội dung nhạy cảm.', 'show_nsfw' => 1]);
                exit;
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Guest flow
    if ($action === 'toggle_nsfw') {
        echo json_encode(['success' => false, 'message' => 'Khách không thể thay đổi thiết lập này. Vui lòng đăng nhập.']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
exit;

