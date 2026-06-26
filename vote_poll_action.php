<?php
/**
 * AJAX Vote Poll Action Handler - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để bình chọn.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$poll_id = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : 0;
$option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
$user_id = getLoggedInUserId();

if ($poll_id <= 0 || $option_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    
    // Kiểm tra xem poll có tồn tại không
    $stmt_poll = $db->prepare("SELECT * FROM polls WHERE id = ?");
    $stmt_poll->execute([$poll_id]);
    $poll = $stmt_poll->fetch();
    if (!$poll) {
        echo json_encode(['success' => false, 'message' => 'Cuộc thăm dò ý kiến không tồn tại.']);
        exit;
    }
    
    // Kiểm tra xem poll đã hết hạn chưa
    if (!empty($poll['expires_at']) && strtotime($poll['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Cuộc thăm dò ý kiến này đã kết thúc.']);
        exit;
    }
    
    // Kiểm tra xem option có thuộc về poll không
    $stmt_opt = $db->prepare("SELECT 1 FROM poll_options WHERE id = ? AND poll_id = ?");
    $stmt_opt->execute([$option_id, $poll_id]);
    if ($stmt_opt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Lựa chọn không hợp lệ cho cuộc thăm dò ý kiến này.']);
        exit;
    }
    
    // Kiểm tra xem người dùng đã vote cho poll này chưa
    $stmt_vote = $db->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $stmt_vote->execute([$poll_id, $user_id]);
    if ($stmt_vote->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã bình chọn cho cuộc thăm dò ý kiến này rồi.']);
        exit;
    }
    
    // Ghi nhận bình chọn
    $stmt_insert = $db->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
    $stmt_insert->execute([$poll_id, $option_id, $user_id]);
    
    // Lấy lại danh sách phương án để tính lại phần trăm và kết quả hiển thị
    $stmt_opts = $db->prepare("SELECT * FROM poll_options WHERE poll_id = ?");
    $stmt_opts->execute([$poll_id]);
    $options = $stmt_opts->fetchAll();
    
    // Đếm tổng số vote
    $stmt_total = $db->prepare("SELECT COUNT(*) as total FROM poll_votes WHERE poll_id = ?");
    $stmt_total->execute([$poll_id]);
    $total_votes = $stmt_total->fetch()['total'] ?? 0;
    
    $options_results = [];
    foreach ($options as $opt) {
        $stmt_count = $db->prepare("SELECT COUNT(*) as count FROM poll_votes WHERE option_id = ?");
        $stmt_count->execute([$opt['id']]);
        $count = $stmt_count->fetch()['count'] ?? 0;
        
        $percentage = 0;
        if ($total_votes > 0) {
            $percentage = round(($count / $total_votes) * 100);
        }
        
        $options_results[] = [
            'id' => $opt['id'],
            'text' => $opt['option_text'],
            'votes' => $count,
            'percentage' => $percentage,
            'is_user_choice' => ($opt['id'] == $option_id)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Bình chọn thành công.',
        'total_votes' => $total_votes,
        'options' => $options_results
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
