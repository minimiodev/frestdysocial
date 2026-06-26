<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) { die('Chưa đăng nhập'); }

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, sender_id, video_filename FROM messages WHERE video_filename IS NOT NULL AND video_filename != '' ORDER BY id DESC LIMIT 3");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Video debug tool</h2>";
    echo "<p><strong>SITE_URL:</strong> " . SITE_URL . "</p>";
    echo "<p><strong>UPLOAD_DIR:</strong> " . UPLOAD_DIR . "</p>";
    
    foreach ($rows as $r) {
        $db_name = $r['video_filename'];
        $physical_path = UPLOAD_DIR . 'chat/' . $db_name;
        $url = SITE_URL . '/uploads/chat/' . $db_name;
        $exists = file_exists($physical_path);
        
        echo "<hr>";
        echo "<p>Msg ID: {$r['id']} | DB filename: <code>{$db_name}</code></p>";
        echo "<p>Đường dẫn vật lý: <code>{$physical_path}</code> → " . ($exists ? "<b style='color:green'>TỒN TẠI</b> (" . number_format(filesize($physical_path)) . " bytes)" : "<b style='color:red'>KHÔNG TỒN TẠI</b>") . "</p>";
        echo "<p>URL: <a href='{$url}' target='_blank'>{$url}</a></p>";
        if ($exists) {
            echo "<video src='{$url}' controls preload='metadata' style='width:400px;max-height:240px;border:2px solid green;display:block;'></video>";
        }
    }
} catch (Exception $e) {
    echo "Lỗi DB: " . $e->getMessage();
}
