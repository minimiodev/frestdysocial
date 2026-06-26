<?php
/**
 * Dynamic XML Sitemap Generator - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Thiết lập định dạng trả về là XML
header("Content-Type: application/xml; charset=utf-8");

$site_url = rtrim(SITE_URL, '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
echo '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL;
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . PHP_EOL;

// 1. Các trang tĩnh chính
$static_pages = [
    '' => ['priority' => '1.0', 'changefreq' => 'daily'],
    '/login' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    '/register' => ['priority' => '0.8', 'changefreq' => 'weekly'],
];

foreach ($static_pages as $path => $meta) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . $site_url . $path . '</loc>' . PHP_EOL;
    echo '    <changefreq>' . $meta['changefreq'] . '</changefreq>' . PHP_EOL;
    echo '    <priority>' . $meta['priority'] . '</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

try {
    $db = getDB();
    
    // 2. Danh sách trang cá nhân người dùng hoạt động (Tối đa 5000 người dùng gần nhất)
    $u_stmt = $db->query("SELECT id, username, created_at FROM users WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    $users = $u_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $lastmod = !empty($u['created_at']) ? date('c', strtotime($u['created_at'])) : date('c');
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . $site_url . '/profile?id=' . $u['id'] . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>0.7</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }

    // 3. Danh sách bài viết công khai (Tối đa 10000 bài viết gần nhất)
    $p_stmt = $db->query("
        SELECT id, post_token, image_filename, content, created_at 
        FROM posts 
        WHERE is_copyright_violation = 0 
        ORDER BY id DESC 
        LIMIT 10000
    ");
    $posts = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as $p) {
        $post_id = !empty($p['post_token']) ? $p['post_token'] : $p['id'];
        $lastmod = !empty($p['created_at']) ? date('c', strtotime($p['created_at'])) : date('c');
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . $site_url . '/detail?id=' . $post_id . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>monthly</changefreq>' . PHP_EOL;
        echo '    <priority>0.6</priority>' . PHP_EOL;
        
        // Thêm sơ đồ hình ảnh đính kèm bài viết để hỗ trợ Google Image Search SEO
        if (!empty($p['image_filename'])) {
            $img = trim($p['image_filename']);
            $img_file = '';
            
            // Xử lý nếu ảnh được lưu dạng JSON array hoặc chuỗi đơn
            if (strpos($img, '[') === 0 || strpos($img, '{') === 0) {
                $imgs = json_decode($img, true);
                $img_file = is_array($imgs) && !empty($imgs) ? $imgs[0] : '';
            } else {
                $img_file = $img;
            }
            
            if (!empty($img_file)) {
                $img_url = $site_url . '/uploads/posts/' . $img_file;
                // Cắt ngắn content làm chú thích ảnh
                $caption = htmlspecialchars(mb_substr(strip_tags($p['content']), 0, 100, 'utf-8'));
                echo '    <image:image>' . PHP_EOL;
                echo '      <image:loc>' . htmlspecialchars($img_url) . '</image:loc>' . PHP_EOL;
                echo '      <image:caption>' . $caption . '</image:caption>' . PHP_EOL;
                echo '    </image:image>' . PHP_EOL;
            }
        }
        echo '  </url>' . PHP_EOL;
    }
} catch (Exception $e) {
    // Xuất lỗi trong XML comments để không làm vỡ cấu trúc XML
    echo '  <!-- Error loading database items: ' . htmlspecialchars($e->getMessage()) . ' -->' . PHP_EOL;
}

echo '</urlset>' . PHP_EOL;
