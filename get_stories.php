<?php
/**
 * Get Stories JSON API - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$me = getLoggedInUser();
$me_id = $me ? intval($me['id']) : 0;
session_write_close(); // Giải phóng khóa session sớm để tránh block các request khác

try {
    $db = getDB();
    
    // Tự động gia hạn hoặc tạo mới các story demo của người dùng khác nếu rỗng
    try {
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM stories WHERE expires_at > NOW() AND user_id != ?");
        $check_stmt->execute([$me_id]);
        $active_other_stories_count = intval($check_stmt->fetchColumn());

        if ($active_other_stories_count === 0) {
            // Thử gia hạn các stories đã hết hạn hiện có trong DB
            $renew_stmt = $db->prepare("UPDATE stories SET expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id != ?");
            $renew_stmt->execute([$me_id]);
            
            // Kiểm tra lại sau khi gia hạn
            $check_stmt->execute([$me_id]);
            $active_other_stories_count = intval($check_stmt->fetchColumn());
            
            // Nếu vẫn bằng 0, tự động seed stories text demo cho các user khác
            if ($active_other_stories_count === 0) {
                $other_users_stmt = $db->prepare("SELECT id, username FROM users WHERE id != ? LIMIT 3");
                $other_users_stmt->execute([$me_id]);
                $other_users = $other_users_stmt->fetchAll();
                
                $demo_texts = [
                    "Hôm nay thời tiết đẹp quá! Chúc cả nhà Frest ngày mới tốt lành nhé! ☀️✨",
                    "Đang thưởng thức ly cà phê sáng và lướt Frest, mượt mà thực sự! ☕💻",
                    "Mọi người đã trải nghiệm giao diện mới của Frest chưa? Quá đỉnh! 🔥📱"
                ];
                $demo_gradients = [
                    "linear-gradient(135deg, #8b5cf6, #ec4899)",
                    "linear-gradient(135deg, #3b82f6, #8b5cf6)",
                    "linear-gradient(135deg, #06b6d4, #3b82f6)"
                ];
                
                foreach ($other_users as $i => $u) {
                    $seed_uid = intval($u['id']);
                    $text = $demo_texts[$i % count($demo_texts)];
                    $grad = $demo_gradients[$i % count($demo_gradients)];
                    
                    $insert_seed = $db->prepare("
                        INSERT INTO stories (user_id, media_type, text_content, bg_color, expires_at) 
                        VALUES (?, 'text', ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
                    $insert_seed->execute([$seed_uid, $text, $grad]);
                }
            }
        }
    } catch (Exception $e_seed) {}

    // Select all active stories (not expired)
    // Ordered by user_id and then story id
    $stmt = $db->prepare("
        SELECT s.*, 
               u.username, 
               u.full_name, 
               u.avatar_filename,
               (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id AND sv.user_id = :me_id) AS is_viewed,
               (SELECT COUNT(*) FROM story_views sv2 WHERE sv2.story_id = s.id) AS view_count
        FROM stories s
        JOIN users u ON s.user_id = u.id
        WHERE s.expires_at > NOW()
        ORDER BY s.created_at ASC
    ");
    $stmt->execute(['me_id' => $me_id]);
    $raw_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Eager load reactions for all active stories to prevent N+1 query problem on hosting
    $reactions_map = [];
    $active_story_ids = array_column($raw_stories, 'id');
    if (!empty($active_story_ids)) {
        $placeholders = implode(',', array_fill(0, count($active_story_ids), '?'));
        $reacts_stmt = $db->prepare("
            SELECT story_id, reaction_type, COUNT(*) as qty 
            FROM story_reactions 
            WHERE story_id IN ($placeholders) 
            GROUP BY story_id, reaction_type
        ");
        $reacts_stmt->execute($active_story_ids);
        $reacts_raw = $reacts_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reacts_raw as $rr) {
            $sid = intval($rr['story_id']);
            $reactions_map[$sid][$rr['reaction_type']] = intval($rr['qty']);
        }
    }

    // Group stories by user
    $grouped_users = [];
    
    foreach ($raw_stories as $story) {
        $uid = intval($story['user_id']);
        
        if (!isset($grouped_users[$uid])) {
            $grouped_users[$uid] = [
                'user_id' => $uid,
                'username' => sanitize($story['username']),
                'full_name' => sanitize($story['full_name'] ?: $story['username']),
                'avatar_url' => AVATARS_URL . '/' . sanitize($story['avatar_filename']),
                'all_viewed' => true, // default, will turn false if any story is not viewed
                'stories' => []
            ];
        }
        
        $viewed = intval($story['is_viewed']) > 0;
        if (!$viewed) {
            $grouped_users[$uid]['all_viewed'] = false;
        }
        
        $media_url = null;
        if (!empty($story['media_filename'])) {
            $media_url = SITE_URL . '/uploads/stories/' . sanitize($story['media_filename']);
        }
        
        $story_id = intval($story['id']);
        
        $reactions_summary = $reactions_map[$story_id] ?? [];
        
        $grouped_users[$uid]['stories'][] = [
            'story_id' => $story_id,
            'media_type' => sanitize($story['media_type']),
            'media_url' => $media_url,
            'text_content' => sanitize($story['text_content']),
            'bg_color' => sanitize($story['bg_color']),
            'created_at' => timeElapsedString($story['created_at']),
            'viewed' => $viewed,
            'view_count' => intval($story['view_count']),
            'reactions' => $reactions_summary
        ];
    }
    
    // Sort array: current logged in user first (if exists), then order by recent activity
    $result = [];
    
    if ($me_id > 0 && isset($grouped_users[$me_id])) {
        $result[] = $grouped_users[$me_id];
        unset($grouped_users[$me_id]);
    }
    
    // Append the rest of users
    foreach ($grouped_users as $user_stories) {
        $result[] = $user_stories;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
