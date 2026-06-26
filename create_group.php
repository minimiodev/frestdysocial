<?php
/**
 * AJAX Handler: Create Chat Group - Frest App
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

$identity = getCurrentIdentity();
if (!$identity) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy danh tính hoạt động.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
    exit;
}

$group_name        = trim($_POST['group_name'] ?? '');
$group_description = trim($_POST['group_description'] ?? '');
$members_json      = trim($_POST['members'] ?? '[]');

if ($group_name === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tên nhóm không được để trống.']);
    exit;
}

$members = json_decode($members_json, true);
if (!is_array($members)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Danh sách thành viên không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $my_type = $identity['type'];
    $my_id   = intval($identity['id']);

    // Handle avatar upload if provided
    $avatar_filename = 'group_default.png';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_name = 'group_' . uniqid() . '.' . $file_ext;
            $dest = UPLOAD_DIR . 'avatars/' . $new_name;
            if (move_uploaded_file($file_tmp, $dest)) {
                $avatar_filename = $new_name;
            }
        }
    }

    $db->beginTransaction();

    // 1. Create the group
    $stmt = $db->prepare("
        INSERT INTO chat_groups (name, avatar_filename, description, creator_type, creator_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$group_name, $avatar_filename, $group_description, $my_type, $my_id]);
    $group_id = intval($db->lastInsertId());

    // 2. Add creator to members
    $ins_member = $db->prepare("
        INSERT INTO chat_group_members (group_id, member_type, member_id, role)
        VALUES (?, ?, ?, 'creator')
    ");
    $ins_member->execute([$group_id, $my_type, $my_id]);

    // 3. Add other members
    $ins_others = $db->prepare("
        INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id, role)
        VALUES (?, ?, ?, 'member')
    ");

    foreach ($members as $m) {
        $m_type = trim($m['type'] ?? '');
        $m_id   = intval($m['id'] ?? 0);

        if ($m_id > 0 && in_array($m_type, ['user', 'page'])) {
            // Prevent adding creator again
            if ($m_type === $my_type && $m_id === $my_id) {
                continue;
            }
            $ins_others->execute([$group_id, $m_type, $m_id]);
        }
    }

    // 4. Send a system message indicating group creation
    $sys_msg = "đã tạo nhóm \"" . $group_name . "\"";
    $ins_msg = $db->prepare("
        INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message_text)
        VALUES (?, ?, 'group', ?, ?)
    ");
    $ins_msg->execute([$my_type, $my_id, $group_id, $sys_msg]);

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Tạo nhóm thành công.',
        'group_id' => $group_id,
        'group_name' => $group_name,
        'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($avatar_filename)
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
