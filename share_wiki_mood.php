<?php
/**
 * Share Wiki Mood API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập để thực hiện hành động này.'
    ]);
    exit;
}

$me = getLoggedInUser();

$content = trim($_POST['content'] ?? '');
$emoji = trim($_POST['emoji'] ?? '');
$color = trim($_POST['color'] ?? '');

if (empty($content)) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng nhập nội dung tâm trạng.'
    ]);
    exit;
}

if (mb_strlen($content) > 150) {
    echo json_encode([
        'success' => false,
        'message' => 'Nội dung tâm trạng tối đa 150 ký tự.'
    ]);
    exit;
}

if (empty($emoji)) {
    $emoji = '🎈';
}

if (empty($color)) {
    $color = 'linear-gradient(135deg, #7F00FF 0%, #E100FF 100%)';
}

try {
    $db = getDB();
    
    // 1. Xóa các mood cũ của người dùng này (để giữ mỗi người chỉ có 1 mood hoạt động)
    $del_stmt = $db->prepare("DELETE FROM wiki_moods WHERE user_id = ?");
    $del_stmt->execute([$me['id']]);
    
    // 2. Thiết lập thời gian hết hạn sau 24 giờ
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // 3. Chèn mood mới
    $ins_stmt = $db->prepare("INSERT INTO wiki_moods (user_id, content, emoji, color, expires_at) VALUES (?, ?, ?, ?, ?)");
    $ins_stmt->execute([
        $me['id'],
        sanitize($content),
        sanitize($emoji),
        $color,
        $expires_at
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Phát tán tâm trạng thành công! 🎈'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi hệ thống xảy ra: ' . $e->getMessage()
    ]);
}
