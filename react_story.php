<?php
/**
 * React Story API - Frest App
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
session_write_close(); // Giải phóng khóa session sớm để tránh block các request khác
$story_id = intval($_POST['story_id'] ?? 0);
$reaction_type = trim($_POST['reaction_type'] ?? ''); // 'like', 'love', 'haha', 'wow', 'sad', 'angry'

if ($story_id <= 0 || empty($reaction_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'Tham số không hợp lệ.'
    ]);
    exit;
}

$valid_types = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
if (!in_array($reaction_type, $valid_types)) {
    echo json_encode([
        'success' => false,
        'message' => 'Loại cảm xúc không hợp lệ.'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Check if story exists and is active
    $story_stmt = $db->prepare("SELECT id FROM stories WHERE id = ? AND expires_at > NOW()");
    $story_stmt->execute([$story_id]);
    if (!$story_stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Câu chuyện không tồn tại hoặc đã hết hạn.'
        ]);
        exit;
    }
    
    // Insert or update reaction (using unique key uq_story_react)
    $stmt = $db->prepare("
        INSERT INTO story_reactions (story_id, user_id, reaction_type) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)
    ");
    $stmt->execute([$story_id, $me['id'], $reaction_type]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã thả cảm xúc thành công.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
