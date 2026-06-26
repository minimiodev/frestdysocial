<?php
/**
 * AJAX Endpoint - Get Page Transparency details
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
$is_user_page = isset($_GET['is_user_page']) && intval($_GET['is_user_page']) === 1;
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($page_id <= 0 && empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $is_page_entity = true;
    
    if (!empty($username)) {
        // Query users table for standard user profile
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại.']);
            exit;
        }
        
        $page_name = ($user['full_name'] ?? '') ?: ($user['username'] ?? '');
        $page_username = $user['username'] ?? '';
        $created_at = $user['created_at'] ?? date('Y-m-d H:i:s');
        $verification_type = (!empty($user['verification_type']) && $user['verification_type'] !== 'none') ? $user['verification_type'] : 'none';
        $is_page_entity = false;
        
        // Map verification type to custom categories
        if ($verification_type === 'subscribed') {
            $category = 'Thành viên Frest đã xác minh';
        } elseif ($verification_type === 'official') {
            $category = 'Nhân vật công chúng';
        } else {
            $category = 'Thành viên Frest';
        }
    } elseif ($is_user_page) {
        // Query users table for professional mode page
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_page = 1");
        $stmt->execute([$page_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Trang chuyên nghiệp không tồn tại.']);
            exit;
        }
        
        $page_name = ($user['full_name'] ?? '') ?: ($user['username'] ?? '');
        $page_username = $user['username'] ?? '';
        $category = $user['page_category'] ?? 'Blog cá nhân';
        $created_at = $user['created_at'] ?? date('Y-m-d H:i:s');
        $verification_type = (!empty($user['verification_type']) && $user['verification_type'] !== 'none') ? $user['verification_type'] : 'official';
        $is_page_entity = true;
    } else {
        // Query pages table
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Trang không tồn tại.']);
            exit;
        }
        
        $page_name = $page['page_name'] ?? '';
        $page_username = $page['page_username'] ?? '';
        $category = ($page['category'] ?? '') ?: 'Cộng đồng';
        $created_at = $page['created_at'] ?? date('Y-m-d H:i:s');
        $verification_type = (!empty($page['verification_type']) && $page['verification_type'] !== 'none') ? $page['verification_type'] : ((!empty($page['is_verified']) && intval($page['is_verified']) === 1) ? 'official' : 'none');
        $is_page_entity = true;
    }

    // Format created_at date to Vietnamese format (e.g. "Ngày 14 tháng 6 năm 2026")
    $timestamp = strtotime($created_at);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    $formatted_date = "$day tháng $month, $year";

    $verified_official_title = getSystemSetting('verified_official_title', 'Huy hiệu đã xác minh');
    $verified_official_desc = getSystemSetting('verified_official_desc', 'Huy hiệu đã xác minh cho thấy Frest đã xác minh tài khoản dựa vào hoạt động của tài khoản đó trên sản phẩm của chúng tôi và thông tin hoặc giấy tờ mà tài khoản cung cấp.');
    $verified_subscribed_title = getSystemSetting('verified_subscribed_title', 'Frest đã xác minh');
    $verified_subscribed_desc = getSystemSetting('verified_subscribed_desc', 'Frest đã xác minh là gói đăng ký trả phí mang lại nhiều lợi ích như huy hiệu đã xác minh, dịch vụ hỗ trợ nâng cao, khả năng chống mạo danh và hơn thế nữa.');
    $verified_learn_more_url = getSystemSetting('verified_learn_more_url', '');
    
    $transparency_user_updated_text = getSystemSetting('transparency_user_updated_text', 'Đã cập nhật thông tin cá nhân gần đây');
    $transparency_page_updated_text = getSystemSetting('transparency_page_updated_text', 'Đã cập nhật thông tin Trang gần đây');

    $badge_title = 'Không tích';
    $badge_desc = '';
    if ($verification_type === 'official') {
        $badge_title = $verified_official_title;
        $badge_desc = $verified_official_desc;
    } elseif ($verification_type === 'subscribed') {
        $badge_title = $verified_subscribed_title;
        $badge_desc = $verified_subscribed_desc;
    }

    $recently_updated = false;
    $sync_status = 0;
    $last_updated = null;
    $country = 'Việt Nam';
    $entity_type = '';
    $entity_id = 0;

    if (!empty($username)) {
        $sync_status = intval($user['sync_transparency_status'] ?? 0);
        $last_updated = $user['profile_updated_at'] ?? null;
        $country = !empty($user['country']) ? $user['country'] : 'Việt Nam';
        $entity_type = 'user';
        $entity_id = intval($user['id']);
    } elseif ($is_user_page) {
        $sync_status = intval($user['sync_transparency_status'] ?? 0);
        $last_updated = $user['profile_updated_at'] ?? null;
        $country = !empty($user['country']) ? $user['country'] : 'Việt Nam';
        $entity_type = 'user';
        $entity_id = intval($user['id']);
    } else {
        $sync_status = intval($page['sync_transparency_status'] ?? 0);
        $last_updated = $page['updated_at'] ?? null;
        $country = !empty($page['country']) ? $page['country'] : 'Việt Nam';
        $entity_type = 'page';
        $entity_id = intval($page['id']);
    }

    if ($sync_status === 1 && !empty($last_updated)) {
        $diff = time() - strtotime($last_updated);
        if ($diff >= 0 && $diff <= 7 * 86400) {
            $recently_updated = true;
        }
    }

    $update_status_text = $is_page_entity ? $transparency_page_updated_text : $transparency_user_updated_text;

    // Fetch name history entries
    $history = [];
    try {
        $stmt_hist = $db->prepare("SELECT * FROM name_history WHERE entity_type = ? AND entity_id = ? ORDER BY changed_at DESC, id DESC");
        $stmt_hist->execute([$entity_type, $entity_id]);
        $history_rows = $stmt_hist->fetchAll() ?: [];
        
        foreach ($history_rows as $row) {
            $ts = strtotime($row['changed_at']);
            $day = date('j', $ts);
            $month = date('n', $ts);
            $year = date('Y', $ts);
            $formatted_change_date = "$day tháng $month, $year";

            if (empty($row['old_name'])) {
                $event_text = ($entity_type === 'page') ? 'Tạo trang với tên ' . $row['new_name'] : 'Tạo tài khoản với tên ' . $row['new_name'];
                $event_type = 'create';
            } else {
                $event_text = 'Đã đổi tên thành ' . $row['new_name'];
                $event_type = 'rename';
            }

            $history[] = [
                'type' => $event_type,
                'text' => $event_text,
                'date' => $formatted_change_date
            ];
        }
    } catch (Exception $ex_hist) {
        // Fallback or ignore
    }

    // Fallback if history is empty
    if (empty($history)) {
        $event_text = ($entity_type === 'page') ? 'Tạo trang với tên ' . $page_name : 'Tạo tài khoản với tên ' . $page_name;
        $history[] = [
            'type' => 'create',
            'text' => $event_text,
            'date' => $formatted_date
        ];
    }

    echo json_encode([
        'success' => true,
        'page_name' => $page_name,
        'page_username' => $page_username,
        'category' => $category,
        'created_at' => $formatted_date,
        'verification_type' => $verification_type,
        'is_page_entity' => $is_page_entity,
        'badge_title' => $badge_title,
        'badge_desc' => $badge_desc,
        'learn_more_url' => $verified_learn_more_url,
        'update_status_text' => $update_status_text,
        'recently_updated' => $recently_updated,
        'country' => $country,
        'history' => $history,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
}
