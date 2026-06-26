<?php
/**
 * Reply Action Handler - Edit / Delete replies
 * AJAX endpoint, returns JSON.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ob_start(); // buffer any PHP warnings so they don't corrupt JSON

/**
 * Helper: discard buffered output, send clean JSON response, and exit.
 */
function jsonExit(array $payload): void {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (!isUserLoggedIn()) {
    jsonExit(['success' => false, 'error' => 'Chưa đăng nhập.']);
}

$action   = $_POST['action']   ?? '';
$reply_id = intval($_POST['reply_id'] ?? 0);
$me       = getLoggedInUser();
$me_id    = $me['id'];
$is_admin = isAdminLoggedIn();

if ($reply_id <= 0) {
    jsonExit(['success' => false, 'error' => 'ID bình luận không hợp lệ.']);
}

try {
    $db = getDB();

    // Auto-ensure updated_at column exists
    try {
        $db->exec("ALTER TABLE replies ADD COLUMN updated_at DATETIME NULL DEFAULT NULL");
    } catch (PDOException $ignore) { /* already exists */ }

    // Fetch the reply
    $stmt = $db->prepare("SELECT * FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();

    if (!$reply) {
        jsonExit(['success' => false, 'error' => 'Bình luận không tồn tại.']);
    }

    $is_owner = (intval($reply['user_id']) === intval($me_id));

    // ---- DELETE ----
    if ($action === 'delete_reply') {
        if (!$is_owner && !$is_admin) {
            jsonExit(['success' => false, 'error' => 'Bạn không có quyền xóa bình luận này.']);
        }
        $del = $db->prepare("DELETE FROM replies WHERE id = ?");
        $del->execute([$reply_id]);
        jsonExit(['success' => true, 'action' => 'deleted']);
    }

    // ---- EDIT ----
    if ($action === 'edit_reply') {
        if (!$is_owner) {
            jsonExit(['success' => false, 'error' => 'Chỉ tác giả mới có thể chỉnh sửa bình luận.']);
        }
        $new_content = trim($_POST['content'] ?? '');
        if (empty($new_content)) {
            jsonExit(['success' => false, 'error' => 'Nội dung không được để trống.']);
        }
        $upd = $db->prepare("UPDATE replies SET content = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_content, $reply_id]);
        jsonExit(['success' => true, 'action' => 'edited', 'new_content' => htmlspecialchars($new_content, ENT_QUOTES, 'UTF-8')]);
    }

    jsonExit(['success' => false, 'error' => 'Hành động không hợp lệ.']);

} catch (PDOException $e) {
    jsonExit(['success' => false, 'error' => $e->getMessage()]);
}

