<?php
/**
 * Shared Admin Header Component - Frest App
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Route guards
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit;
}

$admin_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - <?php echo getSiteName(); ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php 
    $site_favicon_val = getSystemSetting('site_favicon', '');
    if (!empty($site_favicon_val)): 
    ?>
        <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_favicon_val); ?>">
    <?php endif; ?>
    <!-- Master CSS -->
    <?php $_admin_css_ver = @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1'; ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo $_admin_css_ver; ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1'; ?>">
    
    <!-- PWA Optimized Meta & Manifest -->
    <?php if (getSystemSetting('pwa_enabled', '1') === '1'): ?>
    <meta name="theme-color" content="#3b82f6">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
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
        window.FREST_CONFIG = {
            siteUrl: '<?php echo SITE_URL; ?>',
            pwaEnabled: <?php echo getSystemSetting('pwa_enabled', '1') === '1' ? 'true' : 'false'; ?>
        };
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/pwa.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/pwa.js') ?: '1'; ?>" defer></script>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('admin-hamburger-trigger');
            const overlay = document.getElementById('admin-sidebar-overlay');
            const sidebar = document.querySelector('.admin-sidebar');
            
            if (hamburger && sidebar && overlay) {
                hamburger.addEventListener('click', function() {
                    sidebar.classList.add('open');
                    overlay.classList.add('show');
                });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                });
                
                // Tự động đóng menu khi click vào item trên mobile
                const navItems = sidebar.querySelectorAll('.admin-nav-item');
                navItems.forEach(item => {
                    item.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('show');
                    });
                });
            }

            // Tự động bọc mọi .data-table bằng wrapper .data-table-responsive để cuộn ngang trên di động
            const tables = document.querySelectorAll('.data-table');
            tables.forEach(table => {
                if (!table.parentElement.classList.contains('data-table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'data-table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
    </script>
</head>
<body class="admin-body">
    <!-- Mobile Top Bar Header -->
    <div class="admin-mobile-header">
        <button type="button" class="admin-hamburger-btn" id="admin-hamburger-trigger" title="Mở menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="admin-mobile-title"><?php echo htmlspecialchars(getSiteName()); ?> Admin</span>
    </div>
    
    <!-- Sidebar Overlay for mobile -->
    <div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>

    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div>
                <?php 
                $site_logo_val = getSystemSetting('site_logo', '');
                $site_name_val = getSiteName();
                ?>
                <a href="<?php echo SITE_URL; ?>" class="logo" style="margin-bottom: 20px; gap: 8px; display: inline-flex; align-items: center; text-decoration: none;">
                    <?php if (!empty($site_logo_val)): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_logo_val); ?>" alt="Logo" style="height: 24px; max-width: 100px; object-fit: contain;">
                    <?php else: ?>
                        <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    <?php endif; ?>
                    <span style="font-weight: 800; font-family: var(--font-heading); font-size: 16px;"><?php echo htmlspecialchars($site_name_val); ?> <span style="font-size: 11px; font-weight: 500; opacity: 0.7;">Admin</span></span>
                </a>
                
                <div style="font-size: 13px; color: var(--text-muted); padding: 0 16px;">
                    Xin chào, <strong><?php echo sanitize($_SESSION['admin_username']); ?></strong>
                </div>
 
                <nav class="admin-nav">
                    <a href="index.php" class="admin-nav-item <?php echo $admin_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-line"></i> Bảng điều khiển
                    </a>
                    <a href="posts.php" class="admin-nav-item <?php echo $admin_page === 'posts.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-hashtag"></i> Quản lý bài viết
                    </a>
                    <a href="users.php" class="admin-nav-item <?php echo $admin_page === 'users.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users"></i> Quản lý người dùng
                    </a>
                    <a href="pages.php" class="admin-nav-item <?php echo $admin_page === 'pages.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-flag"></i> Quản lý Trang (Pages)
                    </a>
                    <a href="phone_verifications.php" class="admin-nav-item <?php echo $admin_page === 'phone_verifications.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-mobile-screen-button"></i> Kích hoạt SĐT
                    </a>
                    <a href="verifications.php" class="admin-nav-item <?php echo $admin_page === 'verifications.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-cake-candles"></i> Xác minh độ tuổi
                    </a>
                    <a href="name_requests.php" class="admin-nav-item <?php echo $admin_page === 'name_requests.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-check"></i> Duyệt đổi tên
                    </a>
                    <a href="admins.php" class="admin-nav-item <?php echo $admin_page === 'admins.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-shield"></i> Quản lý Admin
                    </a>
                    <a href="copyright_complaints.php" class="admin-nav-item <?php echo $admin_page === 'copyright_complaints.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-copyright"></i> Khiếu nại bản quyền
                    </a>
                    <a href="reports.php" class="admin-nav-item <?php echo $admin_page === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-circle-exclamation"></i> Báo cáo vi phạm
                    </a>
                    <a href="wiki_moods.php" class="admin-nav-item <?php echo $admin_page === 'wiki_moods.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-face-smile"></i> Quản lý Wiki Moods
                    </a>
                    <a href="system_settings.php" class="admin-nav-item <?php echo $admin_page === 'system_settings.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-sliders"></i> Cấu hình hệ thống
                    </a>
                    <a href="settings.php" class="admin-nav-item <?php echo $admin_page === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-gears"></i> Cấu hình chính sách
                    </a>
                    <a href="clear_demo.php" class="admin-nav-item <?php echo $admin_page === 'clear_demo.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-trash-can" style="color: var(--danger);"></i> Xóa dữ liệu Demo
                    </a>
                </nav>
            </div>

            <div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin-bottom: 16px;">
                <a href="<?php echo SITE_URL; ?>" class="admin-nav-item" style="color: var(--text-secondary);">
                    <i class="fa-solid fa-house"></i> Xem Trang chủ
                </a>
                <a href="logout.php" class="admin-nav-item" style="color: var(--danger);">
                    <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-content">

