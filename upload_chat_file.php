<?php
/**
 * AJAX Handler: Upload Chat Attachment - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để thực hiện tác vụ này.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
    $err_code = $_FILES['chat_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tệp tải lên hoặc xảy ra lỗi (' . $err_code . ').']);
    exit;
}

$file = $_FILES['chat_file'];
$file_tmp = $file['tmp_name'];
$file_name = $file['name'];
$file_size = $file['size'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Size limit validation removed as requested

// Ext groups
$image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$video_exts = ['mp4', 'webm', 'mov', 'ogg'];
$audio_exts = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
$document_exts = ['pdf', 'docx', 'doc', 'txt', 'xlsx', 'pptx', 'zip', 'apk', 'exe', 'rar', 'tar', 'gz'];

$file_type = '';
if (in_array($file_ext, $image_exts)) {
    $file_type = 'image';
} elseif (in_array($file_ext, $video_exts)) {
    $file_type = 'video';
} elseif (in_array($file_ext, $audio_exts)) {
    $file_type = 'audio';
} elseif (in_array($file_ext, $document_exts)) {
    $file_type = 'document';
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Định dạng tệp không được hỗ trợ.']);
    exit;
}

// Ensure target folder exists
$me = getLoggedInUser();
$chat_upload_dir = getUserUploadPath($me['username'], 'chat');

$new_name = 'chat_' . uniqid() . '.' . $file_ext;
$dest = $chat_upload_dir . $new_name;

if (move_uploaded_file($file_tmp, $dest)) {
    echo json_encode([
        'status' => 'success',
        'file_type' => $file_type,
        'filename' => 'users/' . $me['username'] . '/' . $new_name,
        'original_filename' => $file_name
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Không thể lưu tệp tải lên máy chủ.']);
}
