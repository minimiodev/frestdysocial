<?php
/**
 * fetch_link_preview.php — Fetches Open Graph/meta data from a URL
 * POST: url=<string>  → returns JSON preview data
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

ob_start();

function fetchLinkPreview(string $url): array {
    $result = ['url' => $url, 'title' => '', 'description' => '', 'image' => ''];

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) return $result;

    // Only http/https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'])) return $result;

    // Detect internal URL by checking domain
    $parsed = parse_url($url);
    $shared_host = strtolower($parsed['host'] ?? '');
    $site_host = strtolower(parse_url(SITE_URL, PHP_URL_HOST) ?? '');

    // Allow localhost/127.0.0.1 or exact matching host
    $is_internal = ($shared_host === $site_host) || ($shared_host === 'localhost') || ($shared_host === '127.0.0.1');

    if ($is_internal) {
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        parse_str($query, $query_params);
        $internal_id = isset($query_params['id']) ? intval($query_params['id']) : 0;

        // Check detail.php
        if (preg_match('/detail\.php$/i', $path) && $internal_id > 0) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT p.*, u.username, u.full_name, u.avatar_filename 
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.id = ?
                ");
                $stmt->execute([$internal_id]);
                $post = $stmt->fetch();
                if ($post) {
                    $author = !empty($post['full_name']) ? $post['full_name'] : $post['username'];
                    $result['title'] = "Bài viết từ @" . $post['username'] . " (" . $author . ")";
                    $result['description'] = mb_substr(strip_tags($post['content']), 0, 200);
                    if (!empty($post['image_filename'])) {
                        $imgs = explode(',', $post['image_filename']);
                        if (!empty($imgs[0])) {
                            $result['image'] = SITE_URL . "/uploads/posts/" . $imgs[0];
                        }
                    }
                    $result['url'] = $url;
                    $result['domain'] = $shared_host;
                    return $result;
                }
            } catch (Exception $e) {}
        }

        // Check profile.php
        $internal_username = $query_params['username'] ?? '';
        if (preg_match('/profile\.php$/i', $path) && !empty($internal_username)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$internal_username]);
                $user = $stmt->fetch();
                if ($user) {
                    $author = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
                    $result['title'] = "Hồ sơ của @" . $user['username'] . " (" . $author . ")";
                    $result['description'] = !empty($user['bio']) ? mb_substr(strip_tags($user['bio']), 0, 200) : "Khám phá các bài viết và tương tác của @" . $user['username'] . " trên Frest App.";
                    $result['image'] = SITE_URL . "/uploads/avatars/" . $user['avatar_filename'];
                    $result['url'] = $url;
                    $result['domain'] = $shared_host;
                    return $result;
                }
            } catch (Exception $e) {}
        }
    }

    // Fetch with curl if available, else file_get_contents
    $html = '';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FrestApp-Bot/1.0; +' . SITE_URL . ')',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Accept-Language: vi,en;q=0.9'],
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout'     => 5,
            'user_agent'  => 'Mozilla/5.0 (compatible; FrestApp-Bot/1.0)',
            'ignore_errors' => true,
        ]]);
        $html = @file_get_contents($url, false, $ctx);
    }

    if (!$html) return $result;

    // Parse Open Graph tags (og:title, og:description, og:image)
    // Also fallback to <title> and <meta name="description">
    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

    // og:title
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m))
        $result['title'] = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
    elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m))
        $result['title'] = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');

    // og:description
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m))
        $result['description'] = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
    elseif (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m))
        $result['description'] = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');

    // og:image
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m))
        $result['image'] = $m[1];
    elseif (preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']og:image["\'][^>]*>/is', $html, $m))
        $result['image'] = $m[1];

    // Truncate
    $result['title']       = mb_substr(trim($result['title']), 0, 200);
    $result['description'] = mb_substr(trim($result['description']), 0, 400);
    $result['domain']      = parse_url($url, PHP_URL_HOST) ?? '';

    return $result;
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $url = trim($_POST['url'] ?? $_GET['url'] ?? '');
    if (empty($url)) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'No URL provided']);
        exit;
    }

    $preview = fetchLinkPreview($url);
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'preview' => $preview]);
}

