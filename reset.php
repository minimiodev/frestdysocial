<?php
/**
 * System Factory Reset Utility - Frest App
 * Clears database, seeds default data, deletes upload files, and clears session files.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$is_cli = (php_sapi_name() === 'cli');
$success = false;
$error = '';
$logs = [];

if ($is_cli || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset']))) {
    try {
        $db = getDB();
        $logs[] = "Kết nối cơ sở dữ liệu thành công.";

        // 1. Run db_init.php (Factory reset schema & seeders)
        $logs[] = "Đang chạy khởi tạo lại toàn bộ bảng cơ sở dữ liệu (db_init.php)...";
        ob_start();
        require __DIR__ . '/db_init.php';
        $init_output = ob_get_clean();
        $logs[] = "Đã cấu hình lại lược đồ DB và nạp dữ liệu mặc định ban đầu.";

        // 2. Run db_upgrade.php (Ensure chat and other newer tables are created)
        $logs[] = "Đang chạy nâng cấp cấu trúc bảng phụ (db_upgrade.php)...";
        ob_start();
        require __DIR__ . '/db_upgrade.php';
        $upgrade_output = ob_get_clean();
        $logs[] = "Đã đồng bộ đầy đủ cấu trúc bảng nâng cấp.";

        // 2.5. Truncate/Delete all user-generated content and demo users (Start 100% fresh)
        $logs[] = "Đang dọn sạch toàn bộ dữ liệu demo (người dùng, bài viết, bình luận, trang tự lập, chat)...";
        $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables_to_truncate = [
            'copyright_complaints',
            'message_reactions',
            'messages',
            'chat_group_members',
            'chat_groups',
            'follows',
            'page_follows',
            'notifications',
            'reactions',
            'replies',
            'posts',
            'pages',
            'users',
            'likes',
            'wiki_moods',
            'stories',
            'story_views',
            'story_reactions',
            'blocks',
            'reports',
            'login_history',
            'name_history',
            'hashtags',
            'post_hashtags',
            'polls',
            'poll_options',
            'poll_votes',
            'bookmarks'
        ];
        foreach ($tables_to_truncate as $table) {
            try {
                $db->exec("TRUNCATE TABLE `$table`;");
            } catch (PDOException $ex) {}
        }
        $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $logs[] = "Đã xóa sạch dữ liệu demo (chỉ giữ lại cấu hình hệ thống & tài khoản admin).";

        // 3. Clear Upload Directories
        $logs[] = "Bắt đầu dọn dẹp các tệp tin tải lên (uploads/)...";
        if (!function_exists('emptyDirectory')) {
            function emptyDirectory($dir, $keep_files = []) {
                if (!is_dir($dir)) return 0;
                $files = scandir($dir);
                $deleted_count = 0;
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    if (in_array($file, $keep_files)) continue;
                    
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        $deleted_count += emptyDirectory($path, $keep_files);
                        @rmdir($path);
                    } else {
                        if (@unlink($path)) {
                            $deleted_count++;
                        }
                    }
                }
                return $deleted_count;
            }
        }

        $upload_dirs = [
            'posts' => [],
            'chat' => [],
            'complaints' => [],
            'proofs' => [],
            'avatars' => ['avatar_default.png', 'group_default.png']
        ];

        foreach ($upload_dirs as $folder => $keep) {
            $dir_path = UPLOAD_DIR . $folder;
            if (is_dir($dir_path)) {
                $count = emptyDirectory($dir_path, $keep);
                $logs[] = "Đã dọn dẹp thư mục: `uploads/{$folder}/` (Đã xóa {$count} tệp tin).";
            }
        }

        // 4. Clear Session files
        $logs[] = "Đang xóa các phiên hoạt động (sessions)...";
        $session_dir = __DIR__ . '/sessions';
        if (is_dir($session_dir)) {
            $count = emptyDirectory($session_dir, ['.htaccess']);
            $logs[] = "Đã xóa {$count} tệp tin phiên hoạt động cũ.";
        }

        // Logout current session if running in web
        if (!$is_cli) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            @session_destroy();
        }

        $success = true;
        $logs[] = "ĐẶT LẠI HỆ THỐNG THÀNH CÔNG!";

    } catch (Exception $e) {
        $error = $e->getMessage();
        $logs[] = "LỖI: " . $error;
    }

    if ($is_cli) {
        foreach ($logs as $log) {
            echo "[" . date('H:i:s') . "] " . strip_tags($log) . "\n";
        }
        exit($success ? 0 : 1);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại & Khởi tạo lại Hệ thống - Frest</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f0c1b 0%, #15102a 50%, #0c0817 100%);
            --card-bg: rgba(22, 18, 45, 0.45);
            --card-border: rgba(255, 255, 255, 0.08);
            --accent-glow: radial-gradient(circle at 50% 50%, rgba(235, 94, 40, 0.15), transparent 60%);
            --text-primary: #f8f9fa;
            --text-secondary: #a0aec0;
            --text-muted: #718096;
            --accent-primary: #eb5e28;
            --accent-gradient: linear-gradient(135deg, #ff7a45 0%, #eb5e28 100%);
            --success: #10b981;
            --danger: #ef4444;
            --radius-md: 20px;
            --radius-sm: 10px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--accent-glow);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            width: 100%;
            max-width: 680px;
            z-index: 1;
            position: relative;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-md);
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            font-size: 48px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 16px;
            display: inline-block;
            filter: drop-shadow(0 4px 10px rgba(235, 94, 40, 0.3));
            animation: pulse 2s infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.06); }
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        .warning-box {
            background: rgba(239, 68, 68, 0.08);
            border-left: 4px solid var(--danger);
            padding: 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 30px;
            font-size: 13.5px;
            line-height: 1.6;
        }

        .warning-box strong {
            color: #ff6b6b;
            display: block;
            margin-bottom: 4px;
        }

        .features-list {
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.01);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 14px;
            color: var(--text-secondary);
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-item i {
            color: var(--accent-primary);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-secondary);
            user-select: none;
        }

        .form-check input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--card-border);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .form-check input[type="checkbox"]:checked {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .form-check input[type="checkbox"]:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            font-size: 10px;
        }

        .btn-reset {
            width: 100%;
            padding: 16px;
            border: none;
            background: var(--accent-gradient);
            color: white;
            font-size: 15px;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(235, 94, 40, 0.35);
            transition: var(--transition);
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(235, 94, 40, 0.5);
        }

        .btn-reset:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Success Logs & Results */
        .success-animation {
            text-align: center;
            margin-bottom: 24px;
        }

        .success-badge {
            width: 72px;
            height: 72px;
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--success);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--success);
            font-size: 32px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        .log-terminal {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            padding: 18px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.7;
            color: #e2e8f0;
            margin-bottom: 30px;
            max-height: 240px;
            overflow-y: auto;
            text-align: left;
        }

        .log-line {
            margin-bottom: 6px;
            display: flex;
            gap: 8px;
        }

        .log-time {
            color: var(--text-muted);
        }

        .log-text.success-msg {
            color: var(--success);
            font-weight: 700;
        }

        .credentials-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .credentials-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .credentials-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .credential-box {
            background: rgba(0, 0, 0, 0.15);
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
        }

        .credential-box h5 {
            color: var(--accent-primary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .credential-row:last-child {
            margin-bottom: 0;
        }

        .cred-label {
            color: var(--text-muted);
        }

        .cred-val {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--text-primary);
        }

        .action-links {
            display: flex;
            gap: 15px;
        }

        .btn-link {
            flex: 1;
            padding: 14px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-link-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(235, 94, 40, 0.25);
        }

        .btn-link-primary:hover {
            box-shadow: 0 6px 20px rgba(235, 94, 40, 0.4);
            transform: translateY(-1px);
        }

        .btn-link-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
        }

        .btn-link-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="glass-card">
        
        <?php if ($success): ?>
            <!-- RESET COMPLETED SUCCESS VIEW -->
            <div class="success-animation">
                <div class="success-badge">
                    <i class="fa-solid fa-check"></i>
                </div>
            </div>

            <div class="header">
                <h1>Khởi tạo lại Thành công</h1>
                <p class="subtitle" style="margin-top: 6px;">Hệ thống Frest đã được khôi phục về cài đặt gốc nguyên bản sạch sẽ.</p>
            </div>

            <div class="log-terminal">
                <?php foreach ($logs as $log): ?>
                    <div class="log-line">
                        <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                        <span class="log-text <?php echo ($log === 'ĐẶT LẠI HỆ THỐNG THÀNH CÔNG!') ? 'success-msg' : ''; ?>">
                            <?php echo htmlspecialchars($log); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="credentials-card">
                <div class="credentials-title">
                    <i class="fa-solid fa-key" style="color: var(--accent-primary);"></i> Thông tin Đăng nhập Thử nghiệm
                </div>
                <div class="credentials-grid">
                    <div class="credential-box">
                        <h5>Tài khoản Quản trị</h5>
                        <div class="credential-row">
                            <span class="cred-label">Username:</span>
                            <span class="cred-val"><?php echo DEFAULT_ADMIN_USER; ?></span>
                        </div>
                        <div class="credential-row">
                            <span class="cred-label">Password:</span>
                            <span class="cred-val"><?php echo DEFAULT_ADMIN_PASS; ?></span>
                        </div>
                    </div>
                    <div class="credential-box">
                        <h5>Thành viên</h5>
                        <div class="credential-row" style="margin-bottom: 0;">
                            <span class="cred-label" style="font-size: 11.5px; line-height: 1.5;">Hệ thống trống hoàn toàn. Đăng ký tài khoản mới trực tiếp trên trang chủ.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-links">
                <a href="<?php echo SITE_URL; ?>/admin/login.php" class="btn-link btn-link-primary">
                    <i class="fa-solid fa-shield-halved"></i> Đăng nhập Quản trị
                </a>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn-link btn-link-secondary">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Đăng nhập User
                </a>
            </div>

        <?php else: ?>
            <!-- WARNING & CONFIRMATION VIEW -->
            <div class="header">
                <div class="logo-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h1>Reset & Khởi tạo lại Hệ thống</h1>
                <p class="subtitle">Tiện ích phục vụ dọn dẹp môi trường thử nghiệm và đặt lại dữ liệu gốc của Frest.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="warning-box" style="border-left-color: var(--danger); background: rgba(239, 68, 68, 0.08);">
                    <strong style="color: #ff6b6b;"><i class="fa-solid fa-circle-xmark"></i> Lỗi Đặt lại Hệ thống:</strong>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="warning-box">
                <strong><i class="fa-solid fa-circle-exclamation"></i> CẢNH BÁO MẤT DỮ LIỆU VĨNH VIỄN:</strong>
                Tác vụ này sẽ dọn sạch toàn bộ cơ sở dữ liệu và các tệp tải lên cũ để bắt đầu lại. Bạn không thể hoàn tác thao tác này sau khi đã chạy.
            </div>

            <div class="features-list">
                <div class="feature-item">
                    <i class="fa-solid fa-database"></i>
                    <span>Khởi tạo cơ sở dữ liệu trống sạch hoàn toàn (chỉ giữ lại tài khoản admin gốc).</span>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-image"></i>
                    <span>Xóa toàn bộ ảnh/video đính kèm trong bài viết, nội dung chat, khiếu nại.</span>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Tạo lại tài khoản admin mặc định và dọn dẹp sạch sẽ tài khoản người dùng cũ.</span>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-cookie-bite"></i>
                    <span>Xóa sạch các tệp tin phiên hoạt động (sessions) để đăng xuất toàn bộ thiết bị.</span>
                </div>
            </div>

            <form action="" method="POST" id="reset-form">
                <input type="hidden" name="action_reset" value="1">
                
                <label class="form-check" id="confirm-label">
                    <input type="checkbox" id="confirm-checkbox">
                    <span>Tôi hiểu tác vụ này sẽ xóa sạch dữ liệu và muốn bắt đầu lại.</span>
                </label>

                <button type="submit" class="btn-reset" id="submit-btn" disabled>
                    <i class="fa-solid fa-power-off"></i> Bắt đầu Đặt lại Hệ thống
                </button>
            </form>

            <script>
                const checkbox = document.getElementById('confirm-checkbox');
                const submitBtn = document.getElementById('submit-btn');
                const form = document.getElementById('reset-form');

                checkbox.addEventListener('change', function() {
                    submitBtn.disabled = !this.checked;
                });

                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang đặt lại hệ thống... Vui lòng đợi';
                });
            </script>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
