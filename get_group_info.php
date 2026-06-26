<?php
/**
 * AJAX Handler: Get Group Info & Members - Frest App
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

$group_id = intval($_GET['group_id'] ?? 0);
if ($group_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID nhóm không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $my_type = $identity['type'];
    $my_id   = intval($identity['id']);

    // 1. Verify membership
    $mem_stmt = $db->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ? AND member_type = ? AND member_id = ?");
    $mem_stmt->execute([$group_id, $my_type, $my_id]);
    if ($mem_stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của nhóm này.']);
        exit;
    }

    // 2. Fetch group info
    $g_stmt = $db->prepare("SELECT name, avatar_filename, description, creator_type, creator_id, created_at FROM chat_groups WHERE id = ?");
    $g_stmt->execute([$group_id]);
    $group = $g_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy nhóm.']);
        exit;
    }

    // 3. Fetch members list
    $m_stmt = $db->prepare("
        SELECT member_type, member_id, role
        FROM chat_group_members
        WHERE group_id = ?
        ORDER BY role DESC, joined_at ASC
    ");
    $m_stmt->execute([$group_id]);
    $raw_members = $m_stmt->fetchAll(PDO::FETCH_ASSOC);

    $members = [];
    foreach ($raw_members as $m) {
        $m_type = $m['member_type'];
        $m_id   = intval($m['member_id']);
        $role   = $m['role'];

        $name = '';
        $handle = '';
        $avatar = 'avatar_default.png';

        if ($m_type === 'user') {
            $p_stmt = $db->prepare("SELECT username, full_name, avatar_filename FROM users WHERE id = ?");
            $p_stmt->execute([$m_id]);
            $res = $p_stmt->fetch();
            if ($res) {
                $handle = $res['username'];
                $name = $res['full_name'] ?: $res['username'];
                $avatar = $res['avatar_filename'];
            }
        } else {
            $p_stmt = $db->prepare("SELECT page_username, page_name, avatar_filename FROM pages WHERE id = ?");
            $p_stmt->execute([$m_id]);
            $res = $p_stmt->fetch();
            if ($res) {
                $handle = $res['page_username'];
                $name = $res['page_name'];
                $avatar = $res['avatar_filename'];
            }
        }

        if (!empty($handle)) {
            $members[] = [
                'type' => $m_type,
                'id' => $m_id,
                'name' => $name,
                'handle' => $handle,
                'avatar' => AVATARS_URL . '/' . htmlspecialchars($avatar ?: 'avatar_default.png'),
                'role' => $role
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'group' => [
            'id' => $group_id,
            'name' => $group['name'],
            'description' => $group['description'],
            'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($group['avatar_filename'] ?: 'group_default.png'),
            'members' => $members
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
