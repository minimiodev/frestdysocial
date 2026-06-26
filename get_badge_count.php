<?php
/**
 * get_badge_count.php — Lightweight endpoint to return unread notifications and chat count.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$me = getLoggedInUser();
$uid = intval($me['id']);
$identity = getCurrentIdentity();

// Release session lock so that other requests are not blocked
session_write_close();

// Get unread notification count
$unread_notif_count = getUnreadNotifCount($uid);

// Get unread chat count based on active identity
$unread_chat_count = 0;
if ($identity) {
    try {
        $db = getDB();
        $chat_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
        $chat_cnt_stmt->execute([$identity['type'], $identity['id']]);
        $unread_chat_count = intval($chat_cnt_stmt->fetchColumn());
    } catch (Exception $e) {}
}

header('Content-Type: application/json');
echo json_encode([
    'unread_count' => $unread_notif_count,
    'unread_chat_count' => $unread_chat_count
]);
