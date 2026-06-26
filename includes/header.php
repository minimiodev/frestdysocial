<?php
/**
 * Global Header Component - Frest App
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$theme_class = isDarkModeActive() ? '' : 'light-theme';
$theme_icon = isDarkModeActive() ? 'fa-sun' : 'fa-moon';

// Get logged-in user data
$me = getLoggedInUser();

// Update user presence status (at most once every 60 seconds to optimize performance)
if ($me) {
    if (!isset($_SESSION['last_active_update']) || (time() - $_SESSION['last_active_update']) > 60) {
        try {
            $db = getDB();
            $stmt_la = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
            $stmt_la->execute([$me['id']]);
            $_SESSION['last_active_update'] = time();
        } catch (Exception $e) {}
    }
}

// Unread badge count — cached in session for 30s to avoid a DB query on every page load
$_header_notif_count = 0;
if ($me) {
    $uid = intval($me['id']);
    $cache_key = 'notif_count_' . $uid;
    $cache_ts   = 'notif_count_ts_' . $uid;
    if (!isset($_SESSION[$cache_key]) || (time() - ($_SESSION[$cache_ts] ?? 0)) > 30) {
        $_SESSION[$cache_key] = getUnreadNotifCount($uid);
        $_SESSION[$cache_ts]  = time();
    }
    $_header_notif_count = $_SESSION[$cache_key];
}

// Unread chat messages count
$unread_chat_count = 0;
if ($me) {
    try {
        $identity = getCurrentIdentity();
        if ($identity) {
            $db = getDB();
            $chat_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
            $chat_cnt_stmt->execute([$identity['type'], $identity['id']]);
            $unread_chat_count = intval($chat_cnt_stmt->fetchColumn());
        }
    } catch (Exception $e) {}
}

// Query pages for identity switcher early
$my_pages = [];
$online_friends = [];
if ($me) {
    try {
        $db = getDB();
        $pages_stmt = $db->prepare("SELECT * FROM pages WHERE owner_id = ?");
        $pages_stmt->execute([$me['id']]);
        $my_pages = $pages_stmt->fetchAll();
        
        // Fetch online followed friends (active in the last 5 minutes)
        $online_stmt = $db->prepare("
            SELECT u.* 
            FROM users u
            JOIN follows f ON u.id = f.followed_id
            WHERE f.follower_id = ?
              AND u.last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY u.last_active DESC
            LIMIT 5
        ");
        $online_stmt->execute([$me['id']]);
        $online_friends = $online_stmt->fetchAll();
    } catch (Exception $e) {}
}
$_css_ver = @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1';
// ─── TỐI ƯU HÓA SEO GOOGLE & CHIA SẺ MẠNG XÃ HỘI (OG / TWITTER CARDS) ───
$page_meta_title = isset($page_title) ? $page_title . ' - ' . getSiteName() : getSiteName() . ' - Siêu mạng xã hội tối giản thế hệ mới';
$page_meta_desc = getSiteName() . ' - Siêu mạng xã hội thế hệ mới, chia sẻ hình ảnh, đăng video ngắn, tương tác reactions và khẳng định cá nhân.';
$page_meta_image = SITE_URL . '/assets/images/icons/icon-192x192.png'; // default logo
$page_meta_url = SITE_URL . '/' . $current_page;
if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
    $page_meta_url .= '?' . $_SERVER['QUERY_STRING'];
}

// 1. Tối ưu cho trang chi tiết bài viết detail.php
if ($current_page === 'detail.php') {
    $id_param = isset($_GET['id']) ? trim($_GET['id']) : '';
    if (!empty($id_param)) {
        try {
            $db = getDB();
            $post_id = 0;
            if (is_numeric($id_param)) {
                $post_id = intval($id_param);
            } else {
                $stmt_tok = $db->prepare("SELECT id FROM posts WHERE post_token = ?");
                $stmt_tok->execute([$id_param]);
                $post_id = intval($stmt_tok->fetchColumn() ?: 0);
            }
            if ($post_id > 0) {
                $stmt_post = $db->prepare("
                    SELECT p.content, p.image_filename, COALESCE(pg.page_name, u.full_name, u.username) AS sender_name 
                    FROM posts p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN pages pg ON p.page_id = pg.id
                    WHERE p.id = ?
                ");
                $stmt_post->execute([$post_id]);
                $seo_post = $stmt_post->fetch(PDO::FETCH_ASSOC);
                if ($seo_post) {
                    $clean_content = strip_tags($seo_post['content']);
                    $snippet = mb_substr($clean_content, 0, 150, 'utf-8');
                    $page_meta_title = mb_substr($clean_content, 0, 60, 'utf-8') . ' - Đăng bởi ' . $seo_post['sender_name'] . ' - ' . getSiteName();
                    $page_meta_desc = $snippet ? $snippet . '...' : $page_meta_desc;
                    
                    if (!empty($seo_post['image_filename'])) {
                        $img = trim($seo_post['image_filename']);
                        if (strpos($img, '[') === 0 || strpos($img, '{') === 0) {
                            $imgs = json_decode($img, true);
                            $img_file = is_array($imgs) && !empty($imgs) ? $imgs[0] : '';
                        } else {
                            $img_file = $img;
                        }
                        if (!empty($img_file)) {
                            $page_meta_image = SITE_URL . '/uploads/posts/' . $img_file;
                        }
                    }
                }
            }
        } catch (Exception $e) {}
    }
}

// 2. Tối ưu cho trang cá nhân profile.php
if ($current_page === 'profile.php') {
    $id_param = isset($_GET['id']) ? trim($_GET['id']) : '';
    if (!empty($id_param)) {
        try {
            $db = getDB();
            $stmt_u = $db->prepare("SELECT username, full_name, bio, avatar_filename FROM users WHERE id = ? OR username = ?");
            $stmt_u->execute([$id_param, $id_param]);
            $seo_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
            if ($seo_u) {
                $name = $seo_u['full_name'] ?: $seo_u['username'];
                $page_meta_title = $name . ' (@' . $seo_u['username'] . ') - Trang cá nhân - ' . getSiteName();
                $page_meta_desc = $seo_u['bio'] ? $seo_u['bio'] : 'Khám phá trang cá nhân của ' . $name . ' trên Frest - Mạng xã hội thế hệ mới.';
                $page_meta_image = AVATARS_URL . '/' . $seo_u['avatar_filename'];
            }
        } catch (Exception $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="<?php echo $theme_class; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php 
    $site_favicon_val = getSystemSetting('site_favicon', '');
    if (!empty($site_favicon_val)): 
    ?>
        <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_favicon_val); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($page_meta_title); ?></title>
    
    <!-- Meta SEO Google -->
    <meta name="description" content="<?php echo htmlspecialchars($page_meta_desc); ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="<?php echo htmlspecialchars($page_meta_url); ?>">
    
    <!-- Open Graph tags for Facebook / Zalo -->
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_meta_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_meta_desc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_meta_url); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(getSiteName()); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_meta_image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_meta_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_meta_desc); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_meta_image); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Cropper.js (For Avatar Cropping) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo getAssetVersion('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/frest_wiki.css?v=<?php echo getAssetVersion('assets/css/frest_wiki.css'); ?>">
    <style>
        /* Bỏ hoàn toàn lớp bóng mờ đen (dimmer/gradient) trên video */
        .video-dimmer {
            display: none !important;
            background: transparent !important;
            background-image: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        .video-controls-overlay {
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
        }
        .custom-video-container,
        .custom-video-container:hover,
        body.light-theme .custom-video-container,
        body.light-theme .custom-video-container:hover {
            box-shadow: none !important;
        }
    </style>
    <?php if (isset($page_css)): ?>
        <?php $_rel_css = str_replace(SITE_URL, '', $page_css); ?>
        <link rel="stylesheet" href="<?php echo $page_css; ?>?v=<?php echo getAssetVersion($_rel_css); ?>">
    <?php endif; ?>
    
    <!-- PWA Optimized Meta & Manifest -->
    <?php if (getSystemSetting('pwa_enabled', '1') === '1'): ?>
    <meta name="theme-color" content="#3b82f6">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Frest">
    <?php 
    $pwa_icon_val = getSystemSetting('pwa_icon', '');
    if (!empty($pwa_icon_val)): 
    ?>
        <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($pwa_icon_val); ?>_192.png">
    <?php else: ?>
        <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/images/icons/icon-192x192.png">
    <?php endif; ?>

    <!-- Register Service Worker & PWA Logic -->
    <script>
        window.SITE_URL = '<?php echo SITE_URL; ?>';
        window.FREST_CONFIG = {
            siteUrl: '<?php echo SITE_URL; ?>',
            pwaEnabled: <?php echo getSystemSetting('pwa_enabled', '1') === '1' ? 'true' : 'false'; ?>,
            pwaIcon: '<?php echo !empty($pwa_icon_val) ? SITE_URL . "/uploads/system/" . sanitize($pwa_icon_val) . "_192.png" : SITE_URL . "/assets/images/icons/icon-192x192.png"; ?>',
            disableSSE: <?php echo DISABLE_SSE ? 'true' : 'false'; ?>
        };
        // Định nghĩa thông tin người dùng đăng nhập toàn cục
        window.FREST_USER = <?php echo $me ? json_encode([
            'id' => intval($me['id']),
            'username' => sanitize($me['username'])
        ]) : 'null'; ?>;

        // Chuyển hướng người dùng chưa đăng nhập khi bấm vào các tính năng tương tác
        document.addEventListener('click', function(e) {
            if (window.FREST_USER && window.FREST_USER.id > 0) return; // Đã đăng nhập thì bỏ qua

            const target = e.target;
            const isInteractButton = target.closest('.react-btn') || 
                                     target.closest('.reaction-emoji') || 
                                     target.closest('.reply-react-trigger-btn') ||
                                     target.closest('.reply-reaction-emoji') ||
                                     target.closest('.repost-btn') || 
                                     target.closest('.repost-action-trigger') ||
                                     target.closest('#repost-simple-btn') ||
                                     target.closest('#repost-quote-btn') ||
                                     target.closest('.quick-react-btn') || 
                                     target.closest('.poll-vote-btn') ||
                                     target.closest('.show-reply-form-btn') || // Nút "Phản hồi" của bình luận con
                                     (target.closest('.frest-action-btn') && !target.closest('.share-btn'));

            if (isInteractButton) {
                e.preventDefault();
                e.stopPropagation();
                
                if (typeof showToast === 'function') {
                    showToast('Bạn cần đăng nhập để sử dụng tính năng này. Đang chuyển hướng...');
                } else {
                    alert('Bạn cần đăng nhập để sử dụng tính năng này.');
                }
                
                setTimeout(function() {
                    window.location.href = window.SITE_URL + '/login.php';
                }, 1200);
            }
        }, true); // Dùng capture phase để chặn sự kiện click trước khi các JS khác xử lý
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/pwa.js?v=<?php echo getAssetVersion('assets/js/pwa.js'); ?>" defer></script>
    <?php else: ?>
    <!-- Auto-unregister service worker if PWA is disabled by admin -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(registrations => {
                for (let registration of registrations) {
                    registration.unregister().then(success => {
                        if (success) console.log('SW: Unregistered service worker successfully.');
                    });
                }
            });
        }
    </script>
    <?php endif; ?>
</head>
<body class="<?php echo $theme_class; ?>">

    <!-- Glassmorphic Page Loader (Premium Circular Spinner) -->
    <div id="cat-page-loader" class="cat-loader-overlay">
        <div class="cat-loader-track-wrapper">
            <div class="frest-loader-spinner-wrapper">
                <div class="frest-loader-spinner"></div>
                <div class="frest-loader-logo">F</div>
            </div>
            <div class="cat-loader-text">Đang tải Frest...</div>
        </div>
    </div>
    <script>
        (function() {
            var loader = document.getElementById('cat-page-loader');
            if (loader) {
                var dismiss = function() {
                    loader.style.opacity = '0';
                    setTimeout(function() { loader.style.display = 'none'; }, 400);
                };
                window.addEventListener('load', dismiss);
                window.addEventListener('DOMContentLoaded', function() {
                    setTimeout(dismiss, 2500); // 2.5s safe fallback timeout
                });
            }
        })();
    </script>


    <!-- Global SVG gradients for multi-verification badges -->
    <svg width="0" height="0" style="position: absolute;">
      <defs>
        <linearGradient id="activeIconGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#8b5cf6" />
            <stop offset="50%" stop-color="#a855f7" />
            <stop offset="100%" stop-color="#ec4899" />
        </linearGradient>
        <linearGradient id="developerGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#8b5cf6" />
            <stop offset="100%" stop-color="#ec4899" />
        </linearGradient>
        <linearGradient id="officialGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#1d9bf0" />
            <stop offset="100%" stop-color="#00ba7c" />
        </linearGradient>
        <linearGradient id="subscribedGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#1d9bf0" />
            <stop offset="100%" stop-color="#3b82f6" />
        </linearGradient>
        <linearGradient id="businessGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#eab308" />
            <stop offset="100%" stop-color="#f97316" />
        </linearGradient>
        <linearGradient id="vietnamGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#ef4444" />
            <stop offset="100%" stop-color="#eab308" />
        </linearGradient>
        <linearGradient id="globalGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#10b981" />
            <stop offset="100%" stop-color="#3b82f6" />
        </linearGradient>
      </defs>
    </svg>

    <!-- Mobile Bottom Navigation Bar (Optimized to 5 items with labels y như hình) -->
    <div class="mobile-bottom-nav">
        <a href="<?php echo SITE_URL; ?>/index.php" class="mobile-nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span class="mobile-nav-label">Home</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/search.php" class="mobile-nav-item <?php echo $current_page === 'search.php' ? 'active' : ''; ?>">
            <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
            </svg>
            <span class="mobile-nav-label">Discover</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/create_post.php" class="mobile-nav-item mobile-nav-create <?php echo $current_page === 'create_post.php' ? 'active' : ''; ?>">
            <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span class="mobile-nav-label">Create</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/chat.php" class="mobile-nav-item <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>" id="mobile-chat-link">
            <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
            <?php if ($unread_chat_count > 0): ?>
            <span class="notif-badge" id="mobile-chat-badge"><?php echo min($unread_chat_count, 99); ?></span>
            <?php else: ?>
            <span class="notif-badge" id="mobile-chat-badge" style="display:none;">0</span>
            <?php endif; ?>
            <span class="mobile-nav-label">Messages</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/profile.php" class="mobile-nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <?php if ($me && $me['avatar_filename'] !== 'avatar_default.png'): ?>
                <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" class="mobile-nav-avatar" alt="Avatar">
            <?php else: ?>
                <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            <?php endif; ?>
            <span class="mobile-nav-label">
                <?php 
                if ($me) {
                    $name_parts = explode(' ', trim($me['full_name'] ?: $me['username']));
                    if (count($name_parts) > 1) {
                        $first_name = $name_parts[0];
                        $last_name = $name_parts[count($name_parts) - 1];
                        $last_initial = mb_substr($last_name, 0, 1, 'UTF-8') . '.';
                        echo htmlspecialchars($first_name . ' ' . $last_initial);
                    } else {
                        echo htmlspecialchars($name_parts[0]);
                    }
                } else {
                    echo 'Profile';
                }
                ?>
            </span>
        </a>
    </div>

    <!-- Header / Navigation -->
    <header class="header">
        <div class="container navbar">
            <!-- Logo -->
            <?php 
            $site_logo_val = getSystemSetting('site_logo', '');
            $site_name_val = getSiteName();
            ?>
            <a href="<?php echo SITE_URL; ?>" class="logo" style="gap: 8px; display: inline-flex; align-items: center; text-decoration: none;">
                <?php if (!empty($site_logo_val)): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_logo_val); ?>" alt="Logo" style="height: 28px; max-width: 120px; object-fit: contain;">
                <?php else: ?>
                    <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <?php endif; ?>
                <span class="hide-mobile" style="font-weight: 800; font-family: var(--font-heading);"><?php echo htmlspecialchars($site_name_val); ?></span>
            </a>

            <!-- Central Navigation Menu (Frest Style) -->
            <div class="nav-center-menu">
                <a href="<?php echo SITE_URL; ?>/index.php" class="nav-icon-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" title="Trang chủ">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </a>
                <a href="<?php echo SITE_URL; ?>/search.php" class="nav-icon-link <?php echo $current_page === 'search.php' ? 'active' : ''; ?>" title="Tìm kiếm">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </a>
                <a href="<?php echo SITE_URL; ?>/create_post.php" class="nav-icon-link <?php echo $current_page === 'create_post.php' ? 'active' : ''; ?>" title="Đăng bài">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </a>
                <a href="<?php echo SITE_URL; ?>/activity.php" class="nav-icon-link <?php echo $current_page === 'activity.php' ? 'active' : ''; ?>" title="Thông báo" id="desktop-notif-link" style="position:relative;">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if ($_header_notif_count > 0): ?>
                    <span class="notif-badge" id="desktop-notif-badge"><?php echo min($_header_notif_count, 99); ?></span>
                    <?php else: ?>
                    <span class="notif-badge" id="desktop-notif-badge" style="display:none;">0</span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/chat.php" class="nav-icon-link <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>" title="Tin nhắn" id="desktop-chat-link" style="position:relative;">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <?php if ($unread_chat_count > 0): ?>
                    <span class="notif-badge" id="desktop-chat-badge" style="background: var(--accent-primary);"><?php echo min($unread_chat_count, 99); ?></span>
                    <?php else: ?>
                    <span class="notif-badge" id="desktop-chat-badge" style="display:none; background: var(--accent-primary);">0</span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/profile.php" class="nav-icon-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" title="Trang cá nhân">
                    <svg class="nav-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            </div>

            <!-- Mobile Header Actions (Tìm kiếm & Thông báo) -->
            <div class="mobile-header-actions hide-desktop">
                <a href="<?php echo SITE_URL; ?>/search.php" class="mobile-action-btn" title="Tìm kiếm">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/activity.php" class="mobile-action-btn" title="Thông báo" style="position: relative;">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($_header_notif_count > 0): ?>
                    <span class="mobile-action-badge"><?php echo min($_header_notif_count, 99); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Nav Actions -->
            <div class="nav-actions hide-mobile" style="gap: 12px;">
                <?php if (isAdminLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/admin/index.php" class="btn-secondary" style="padding: 6px 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; border-radius: var(--radius-sm);">
                        <i class="fa-solid fa-user-shield"></i> <span class="hide-mobile">Admin</span>
                    </a>
                <?php endif; ?>

                <?php if ($me): ?>
                    <?php 
                    $identity = getCurrentIdentity(); 
                    $db = getDB();
                    $pages_stmt = $db->prepare("SELECT * FROM pages WHERE owner_id = ?");
                    $pages_stmt->execute([$me['id']]);
                    $my_pages = $pages_stmt->fetchAll();
                    ?>
                    <div class="identity-switch-container">
                        <div class="identity-trigger" id="identity-trigger-btn">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($identity['avatar']); ?>" 
                                 alt="Avatar" 
                                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1.5px solid var(--accent-primary);">
                            <span style="font-size: 13px; font-weight: 700; color: var(--text-primary);" class="hide-mobile">
                                <?php echo $identity['type'] === 'page' ? sanitize($identity['name']) : '@' . sanitize($identity['username']); ?>
                            </span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--text-secondary); opacity: 0.7;"></i>
                        </div>
                        
                        <!-- Identity Dropdown Menu (Premium CSS Design) -->
                        <div class="identity-dropdown" id="identity-dropdown-menu">
                            <div class="identity-dropdown-title">
                                Chuyển danh tính
                            </div>
                            
                            <!-- Quick link: View personal profile -->
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="identity-dropdown-item" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 8px;">
                                <i class="fa-regular fa-user" style="font-size: 14px; color: var(--accent-primary); width: 20px; text-align: center;"></i>
                                <span style="font-size: 12.5px; font-weight: 700; color: var(--text-primary);">Xem trang cá nhân</span>
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=user" class="identity-dropdown-item <?php echo $identity['type'] === 'user' ? 'active' : ''; ?>">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                <div style="flex: 1; min-width: 0; text-align: left;">
                                    <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                        <?php echo sanitize($me['full_name'] ?: $me['username']); ?>
                                    </div>
                                    <div style="font-size: 10px; color: var(--text-secondary);"><?php echo intval($me['is_page']) === 1 ? 'Trang cá nhân (Pro)' : 'Tài khoản cá nhân'; ?></div>
                                </div>
                            </a>
                            
                            <!-- Managed pages options -->
                            <?php foreach ($my_pages as $p): ?>
                                <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=page&id=<?php echo $p['id']; ?>" class="identity-dropdown-item <?php echo ($identity['type'] === 'page' && $identity['id'] == $p['id']) ? 'active' : ''; ?>">
                                    <img src="<?php echo AVATARS_URL . '/' . sanitize($p['avatar_filename']); ?>" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                    <div style="flex: 1; min-width: 0; text-align: left;">
                                        <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                            <?php echo sanitize($p['page_name']); ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--text-secondary);">Trang • <?php echo sanitize($p['category']); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            
                            <!-- Create new Page button -->
                            <a href="<?php echo SITE_URL; ?>/pages.php?show_create=1" class="identity-dropdown-create-btn">
                                <i class="fa-solid fa-circle-plus" style="font-size: 14px;"></i> Tạo Trang mới
                            </a>
                        </div>
                    </div>

                    <a href="<?php echo SITE_URL; ?>/settings.php" class="nav-logout-btn" title="Thiết lập tài khoản">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="nav-logout-btn" title="Đăng xuất">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn-primary" style="padding: 6px 16px; font-size: 13px; border-radius: var(--radius-sm); font-weight: 600;">
                        Đăng nhập
                    </a>
                <?php endif; ?>

                <!-- Theme Toggle Button -->
                <button id="theme-toggle-btn" class="theme-toggle" aria-label="Chuyển đổi giao diện">
                    <i class="fa-solid <?php echo $theme_icon; ?>"></i>
                </button>
            </div>
        </div>
    </header>



    <!-- Desktop Sidebar & Main Layout Wrapper -->
    <div class="app-layout">
        <!-- Left Sidebar (Desktop) -->
        <aside class="desktop-sidebar hide-mobile">
            <div class="sidebar-logo-container">
                <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
                    <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    <span>Frest</span>
                </a>
            </div>
            
            <?php if ($me): ?>
                <?php $identity = getCurrentIdentity(); ?>
                <div class="sidebar-user-card">
                    <div class="user-avatar-wrapper">
                        <img src="<?php echo AVATARS_URL . '/' . sanitize($identity['avatar']); ?>" class="avatar-img">
                        <span class="status-indicator online"></span>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $identity['type'] === 'page' ? sanitize($identity['name']) : sanitize($me['full_name'] ?: $me['username']); ?></div>
                        <div class="user-status">Trực tuyến</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <nav class="sidebar-nav">
                <a href="<?php echo SITE_URL; ?>/index.php" class="nav-item <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Trang chủ</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/search.php" class="nav-item <?php echo ($current_page === 'search.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-compass"></i>
                    <span>Khám phá</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/activity.php" class="nav-item <?php echo ($current_page === 'activity.php') ? 'active' : ''; ?>">
                    <i class="fa-regular fa-bell"></i>
                    <span>Thông báo</span>
                    <?php if ($_header_notif_count > 0): ?>
                        <span class="badge badge-purple"><?php echo min($_header_notif_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/chat.php" class="nav-item <?php echo ($current_page === 'chat.php') ? 'active' : ''; ?>">
                    <i class="fa-regular fa-comments"></i>
                    <span>Tin nhắn</span>
                    <?php if ($unread_chat_count > 0): ?>
                        <span class="badge badge-cyan"><?php echo min($unread_chat_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/bookmarks.php" class="nav-item <?php echo ($current_page === 'bookmarks.php') ? 'active' : ''; ?>">
                    <i class="fa-regular fa-bookmark"></i>
                    <span>Dấu trang</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/profile.php" class="nav-item <?php echo ($current_page === 'profile.php' && !isset($_GET['tab'])) ? 'active' : ''; ?>">
                    <i class="fa-regular fa-user"></i>
                    <span>Cá nhân</span>
                </a>
            </nav>
            
            <?php if ($me): ?>
                <!-- Create Post button (Screenshot-desktop style) -->
                <div class="sidebar-action-button-container">
                    <a href="<?php echo SITE_URL; ?>/create_post.php" class="sidebar-create-post-btn">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Đăng Frest</span>
                    </a>
                </div>
                
                <!-- Your Spaces Section -->
                <div class="sidebar-section-divider"></div>
                <div class="sidebar-section-header">
                    <span>Không gian của bạn</span>
                    <a href="<?php echo SITE_URL; ?>/pages.php" class="section-link" title="Quản lý tất cả"><i class="fa-solid fa-gear"></i></a>
                </div>
                <div class="sidebar-spaces-list">
                    <?php if (empty($my_pages)): ?>
                        <div class="sidebar-empty-state">Chưa có trang nào</div>
                    <?php else: ?>
                        <?php foreach (array_slice($my_pages, 0, 5) as $p): ?>
                            <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=page&id=<?php echo $p['id']; ?>" class="sidebar-space-item">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($p['avatar_filename']); ?>" class="space-avatar">
                                <span class="space-name"><?php echo sanitize($p['page_name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Friends Online Section -->
                <div class="sidebar-section-divider"></div>
                <div class="sidebar-section-header">
                    <span>Bạn bè trực tuyến</span>
                </div>
                <div class="sidebar-friends-list">
                    <?php if (empty($online_friends)): ?>
                        <div class="sidebar-empty-state">Không có bạn bè trực tuyến</div>
                    <?php else: ?>
                        <?php foreach ($online_friends as $friend): ?>
                            <a href="<?php echo SITE_URL; ?>/profile.php?username=<?php echo sanitize($friend['username']); ?>" class="sidebar-friend-item">
                                <div class="friend-avatar-wrapper">
                                    <img src="<?php echo AVATARS_URL . '/' . sanitize($friend['avatar_filename']); ?>" class="friend-avatar">
                                    <span class="status-indicator online"></span>
                                </div>
                                <span class="friend-name"><?php echo sanitize($friend['full_name'] ?: $friend['username']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($me): ?>
                <!-- Bottom Profile / Switch Identity / Settings / Logout -->
                <div class="sidebar-bottom-profile">
                    <!-- Dropdown identity trigger -->
                    <div class="identity-switch-container">
                        <div class="identity-trigger" id="sidebar-identity-trigger-btn">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($identity['avatar']); ?>" alt="Avatar" class="avatar-sm" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                            <span class="username-label">
                                <?php echo $identity['type'] === 'page' ? sanitize($identity['name']) : '@' . sanitize($identity['username']); ?>
                            </span>
                            <i class="fa-solid fa-chevron-up"></i>
                        </div>
                        
                        <div class="identity-dropdown" id="sidebar-identity-dropdown-menu" style="bottom: 110%; top: auto;">
                            <div class="identity-dropdown-title">Chuyển danh tính</div>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="identity-dropdown-item">
                                <i class="fa-regular fa-user"></i>
                                <span>Xem trang cá nhân</span>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=user" class="identity-dropdown-item <?php echo $identity['type'] === 'user' ? 'active' : ''; ?>">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <div class="name"><?php echo sanitize($me['full_name'] ?: $me['username']); ?></div>
                                    <div class="sub">Cá nhân</div>
                                </div>
                            </a>
                            <?php foreach ($my_pages as $p): ?>
                                <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=page&id=<?php echo $p['id']; ?>" class="identity-dropdown-item <?php echo ($identity['type'] === 'page' && $identity['id'] == $p['id']) ? 'active' : ''; ?>">
                                    <img src="<?php echo AVATARS_URL . '/' . sanitize($p['avatar_filename']); ?>" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div class="name"><?php echo sanitize($p['page_name']); ?></div>
                                        <div class="sub">Trang</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <a href="<?php echo SITE_URL; ?>/pages.php?show_create=1" class="identity-dropdown-create-btn">
                                <i class="fa-solid fa-circle-plus"></i> Tạo Trang mới
                            </a>
                        </div>
                    </div>
                    
                    <div class="sidebar-actions-row">
                        <a href="<?php echo SITE_URL; ?>/settings.php" class="nav-logout-btn" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:var(--text-secondary);" title="Thiết lập tài khoản">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                        <button id="sidebar-theme-toggle-btn" class="theme-toggle" title="Đổi giao diện">
                            <i class="fa-solid <?php echo $theme_icon; ?>"></i>
                        </button>
                        <a href="<?php echo SITE_URL; ?>/logout.php" title="Đăng xuất">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Bottom Profile / Settings for Guests -->
                <div class="sidebar-bottom-profile">
                    <div class="sidebar-actions-row">
                        <a href="<?php echo SITE_URL; ?>/login.php" title="Đăng nhập" style="flex: 2; background: var(--accent-gradient); color: #fff; border: none !important; box-shadow: 0 4px 12px var(--accent-glow); font-weight: 700; gap: 6px;">
                            <i class="fa-solid fa-right-to-bracket"></i> <span>Đăng nhập</span>
                        </a>
                        <button id="sidebar-theme-toggle-btn" class="theme-toggle" title="Đổi giao diện" style="flex: 1;">
                            <i class="fa-solid <?php echo $theme_icon; ?>"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Desktop Main Column Area -->
        <div class="desktop-main-wrapper">
            
            <!-- Desktop Top Bar -->
            <header class="desktop-top-bar hide-mobile">
                <!-- Left Nav Tabs (Feed, Discover, Messages) -->
                <div class="top-bar-tabs">
                    <a href="<?php echo SITE_URL; ?>/index.php" class="top-tab <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Bảng tin</a>
                    <a href="<?php echo SITE_URL; ?>/search.php" class="top-tab <?php echo $current_page === 'search.php' ? 'active' : ''; ?>">Khám phá</a>
                    <a href="<?php echo SITE_URL; ?>/chat.php" class="top-tab <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">Tin nhắn</a>
                </div>

                <div class="top-bar-search">
                    <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="header-search-input" name="q" placeholder="Tìm kiếm..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" autocomplete="off">
                    </form>
                    <div id="header-search-suggestions" class="search-suggestions-box"></div>
                </div>
                
                <div class="top-bar-actions">
                    <a href="<?php echo SITE_URL; ?>/chat.php" class="top-bar-btn" title="Tin nhắn">
                        <i class="fa-regular fa-envelope"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="badge-dot"></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="<?php echo SITE_URL; ?>/activity.php" class="top-bar-btn" title="Thông báo">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($_header_notif_count > 0): ?>
                            <span class="badge-dot"></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($me): ?>
                        <a href="<?php echo SITE_URL; ?>/profile.php" class="top-bar-user">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($identity['avatar']); ?>" alt="Avatar">
                            <span class="user-name"><?php echo $identity['type'] === 'page' ? sanitize($identity['name']) : sanitize($me['first_name'] ?: $me['username']); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <main class="main-content-wrapper">

