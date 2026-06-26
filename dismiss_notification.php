<?php
/**
 * dismiss_notification.php — Mark a single notification as dismissed (deleted).
 * POST: id=<notification_id>       → dismiss one notification
 * POST: action=clear_all           → dismiss all for this user
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập.']);
    exit;
}

$me = getLoggedInUser();
$user_id = intval($me['id']);

$action = $_POST['action'] ?? '';

try {
    $db = getDB();

    if ($action === 'clear_all') {
        // Mark all as dismissed for this user
        $stmt = $db->prepare("UPDATE notifications SET is_dismissed=1 WHERE user_id=?");
        $stmt->execute([$user_id]);

        echo json_encode(['success' => true, 'action' => 'clear_all']);
        exit;
    }

    // Single dismiss
    $notif_id = intval($_POST['id'] ?? 0);
    if ($notif_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID không hợp lệ.']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE notifications SET is_dismissed=1 WHERE id=? AND user_id=?"
    );
    $stmt->execute([$notif_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy thông báo.']);
        exit;
    }

    // Return updated unread count
    $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_dismissed=0 AND is_read=0");
    $cnt_stmt->execute([$user_id]);
    $unread = intval($cnt_stmt->fetchColumn());

    echo json_encode(['success' => true, 'unread_count' => $unread]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
