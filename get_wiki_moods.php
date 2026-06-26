<?php
/**
 * Get Active Wiki Moods API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Lấy danh sách các mood hoạt động (chưa hết hạn)
    $stmt = $db->prepare("
        SELECT wm.id, 
               wm.content, 
               wm.emoji, 
               wm.color, 
               wm.created_at,
               u.id as user_id, 
               u.username, 
               u.full_name, 
               u.avatar_filename,
               u.verification_type
        FROM wiki_moods wm
        JOIN users u ON wm.user_id = u.id
        WHERE wm.expires_at > NOW()
        ORDER BY wm.created_at DESC
        LIMIT 30
    ");
    $stmt->execute();
    $moods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format lại dữ liệu avatar_url tương ứng
    $formatted_moods = [];
    foreach ($moods as $m) {
        $avatar_url = SITE_URL . '/uploads/avatars/' . ($m['avatar_filename'] ?: 'avatar_default.png');
        $formatted_moods[] = [
            'id' => intval($m['id']),
            'content' => htmlspecialchars_decode($m['content']),
            'emoji' => $m['emoji'],
            'color' => $m['color'],
            'created_at' => $m['created_at'],
            'user' => [
                'id' => intval($m['user_id']),
                'username' => $m['username'],
                'full_name' => $m['full_name'],
                'avatar_url' => $avatar_url,
                'verification_type' => $m['verification_type']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'moods' => $formatted_moods
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi hệ thống xảy ra: ' . $e->getMessage()
    ]);
}
