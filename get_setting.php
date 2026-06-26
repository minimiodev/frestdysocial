<?php
/**
 * AJAX Settings Fetcher (Get policies content)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$key = isset($_GET['key']) ? sanitize($_GET['key']) : '';

if (empty($key) || !in_array($key, ['privacy_policy', 'terms_of_service'])) {
    echo json_encode(['success' => false, 'message' => 'Khóa cấu hình không hợp lệ.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'key' => $key,
        'value' => $value ? $value : '<h3>Cấu hình</h3><p>Chưa cấu hình nội dung cho phần này.</p>'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage()]);
    exit;
}

