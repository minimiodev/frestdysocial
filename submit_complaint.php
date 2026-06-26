<?php
/**
 * Submit Copyright Complaint API - Frest App
 */
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Phương thức yêu cầu không hợp lệ.';
    echo json_encode($response);
    exit;
}

$reporter_name = trim($_POST['reporter_name'] ?? '');
$reporter_email = trim($_POST['reporter_email'] ?? '');
$reporter_phone = trim($_POST['reporter_phone'] ?? '');
$post_url = trim($_POST['post_url'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validation
if (empty($reporter_name) || empty($reporter_email) || empty($post_url) || empty($description)) {
    $response['message'] = 'Vui lòng điền đầy đủ các thông tin bắt buộc (*).';
    echo json_encode($response);
    exit;
}

if (!filter_var($reporter_email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Địa chỉ email liên hệ không hợp lệ.';
    echo json_encode($response);
    exit;
}

if (!filter_var($post_url, FILTER_VALIDATE_URL)) {
    $response['message'] = 'Đường dẫn (URL) bài viết không hợp lệ.';
    echo json_encode($response);
    exit;
}

// Handle file upload (evidence)
$evidence_filename = null;
if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['evidence']['tmp_name'];
    $file_name = $_FILES['evidence']['name'];
    $file_size = $_FILES['evidence']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'rar', 'txt'];
    if (!in_array($file_ext, $allowed_exts)) {
        $response['message'] = 'Tệp minh chứng không đúng định dạng cho phép (chỉ nhận JPG, PNG, GIF, PDF, DOC, DOCX, ZIP, RAR, TXT).';
        echo json_encode($response);
        exit;
    }
    
    // 10MB limit
    if ($file_size > 10 * 1024 * 1024) {
        $response['message'] = 'Kích thước tệp minh chứng vượt quá giới hạn cho phép (tối đa 10MB).';
        echo json_encode($response);
        exit;
    }
    
    $complaints_dir = UPLOAD_DIR . 'complaints/';
    if (!is_dir($complaints_dir)) {
        mkdir($complaints_dir, 0777, true);
    }
    
    $new_name = 'complaint_evidence_' . uniqid() . '.' . $file_ext;
    if (move_uploaded_file($file_tmp, $complaints_dir . $new_name)) {
        $evidence_filename = $new_name;
    } else {
        $response['message'] = 'Không thể tải lên tệp minh chứng. Vui lòng thử lại.';
        echo json_encode($response);
        exit;
    }
}

try {
    $db = getDB();
    
    // Insert into copyright_complaints
    $stmt = $db->prepare("INSERT INTO copyright_complaints (reporter_name, reporter_email, reporter_phone, post_url, description, evidence_filename, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    
    if ($stmt->execute([$reporter_name, $reporter_email, $reporter_phone ?: null, $post_url, $description, $evidence_filename])) {
        $response['success'] = true;
        $response['message'] = 'Khiếu nại bản quyền của bạn đã được gửi thành công. Ban quản trị sẽ sớm xem xét và xử lý.';
    } else {
        $response['message'] = 'Không thể lưu khiếu nại. Vui lòng thử lại sau.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
}

echo json_encode($response);
exit;
