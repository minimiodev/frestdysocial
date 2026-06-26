<?php
/**
 * Maintenance & System Reset Admin Tool - Frest App
 * Allows clearing demo data OR performing a full system factory reset.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Authentication Guard (Must be run before any HTML output for redirect capability)
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit;
}

$error_msg = '';
$success_msg = '';
$logs = [];

// Action 1: Clear Demo Data (Keep existing Admin & Settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_demo'])) {
    $confirm_code = trim($_POST['confirm_code'] ?? '');

    if ($confirm_code !== 'DELETE') {
        $error_msg = "Xác nhận không chính xác. Bạn phải nhập chính xác từ 'DELETE' để thực hiện.";
    } else {
        try {
            $db = getDB();

            // Disable foreign key checks for clean truncation
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $logs[] = "Tạm thời vô hiệu hóa kiểm tra khóa ngoại (Foreign Key Checks = 0).";

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
                'likes'
            ];

            $cleared_tables = [];
            foreach ($tables_to_truncate as $table) {
                try {
                    $db->exec("DELETE FROM `$table`;");
                    try {
                        $db->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1;");
                    } catch (PDOException $ex) {}
                    $cleared_tables[] = "`$table`";
                } catch (PDOException $e) {
                    $logs[] = "Lưu ý: Không thể dọn dẹp bảng `$table` (có thể bảng chưa tồn tại): " . htmlspecialchars($e->getMessage());
                }
            }
            $logs[] = "Đã dọn dẹp các bảng cơ sở dữ liệu: " . implode(', ', $cleared_tables);

            // Enable foreign key checks back
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $logs[] = "Khôi phục kiểm tra khóa ngoại (Foreign Key Checks = 1).";

            // Helper to clean directories
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
                    $logs[] = "Đã dọn dẹp thư mục tải lên: `uploads/$folder/` (Đã xóa $count tệp tin).";
                }
            }

            $success_msg = "Xóa sạch dữ liệu demo hệ thống thành công!";

        } catch (Exception $e) {
            $error_msg = "Có lỗi xảy ra trong quá trình xóa dữ liệu: " . $e->getMessage();
        }
    }
}

// Action 2: Factory Reset / Full System Reset (Drop all tables and run seeders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_factory_reset'])) {
    $confirm_code = trim($_POST['confirm_code_reset'] ?? '');

    if ($confirm_code !== 'RESET') {
        $error_msg = "Xác nhận không chính xác. Bạn phải nhập chính xác từ 'RESET' để khôi phục cài đặt gốc.";
    } else {
        try {
            $db = getDB();

            // 1. Drop all tables
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $tables_to_drop = [
                'follows', 'likes', 'reactions', 'replies', 'posts', 'users', 'admins', 'settings',
                'copyright_complaints', 'messages', 'message_reactions', 'chat_groups', 'chat_group_members',
                'pages', 'page_follows', 'notifications'
            ];
            foreach ($tables_to_drop as $t) {
                $db->exec("DROP TABLE IF EXISTS `$t`;");
            }
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

            // 2. Clear upload directories (keeping baseline files)
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

            emptyDirectory(UPLOAD_DIR . 'posts');
            emptyDirectory(UPLOAD_DIR . 'chat');
            emptyDirectory(UPLOAD_DIR . 'complaints');
            emptyDirectory(UPLOAD_DIR . 'proofs');
            emptyDirectory(UPLOAD_DIR . 'avatars', ['avatar_default.png', 'group_default.png']);

            // 3. Temporarily mock GET method so seeders run standard flow
            $original_method = $_SERVER['REQUEST_METHOD'];
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Run database initialization and upgrade scripts to rebuild schema and default seed data
            ob_start();
            require __DIR__ . '/../db_init.php';
            require __DIR__ . '/../db_upgrade.php';
            ob_end_clean();

            // Restore request method
            $_SERVER['REQUEST_METHOD'] = $original_method;

            // Log current admin user out since admins table was recreated
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            // Redirect to login page with success notification
            header("Location: login.php?reset=success");
            exit;

        } catch (Exception $e) {
            $error_msg = "Có lỗi xảy ra khi khôi phục cài đặt gốc: " . $e->getMessage();
        }
    }
}

// Now include header.php to render the page layout HTML
require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Bảo trì & Đặt lại hệ thống</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Các công cụ quản trị hệ thống nâng cao để dọn dẹp dữ liệu rác hoặc khôi phục cài đặt gốc toàn bộ cơ sở dữ liệu.
    </div>
</div>

<?php if (!empty($error_msg)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
        <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
        <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <strong><?php echo $success_msg; ?></strong>
    </div>
    
    <div class="checkout-card" style="max-width: 900px; margin-bottom: 40px; background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 24px; border-radius: var(--radius-md);">
        <h4 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 16px; color: var(--text-primary);">
            <i class="fa-solid fa-terminal" style="color: var(--accent-primary);"></i> Nhật ký thực thi (Execution Logs):
        </h4>
        <div style="font-family: monospace; font-size: 13px; background: var(--bg-tertiary); padding: 16px; border-radius: var(--radius-sm); color: var(--text-secondary); line-height: 1.6; border: 1px solid var(--border-color); max-height: 300px; overflow-y: auto;">
            <?php foreach ($logs as $log): ?>
                <div style="margin-bottom: 6px;">[<?php echo date('H:i:s'); ?>] <?php echo $log; ?></div>
            <?php endforeach; ?>
            <div style="color: var(--success); font-weight: 700; margin-top: 12px;"><i class="fa-solid fa-check"></i> Đã hoàn tất xử lý yêu cầu dọn dẹp hệ thống!</div>
        </div>
        <div style="margin-top: 24px;">
            <a href="index.php" class="btn-purchase-action" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none; background: var(--accent-gradient); color: #fff; padding: 12px 28px; border-radius: var(--radius-full); font-weight: 700; font-size: 14px;">
                <i class="fa-solid fa-house"></i> Quay lại Bảng điều khiển
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="checkout-grid" style="grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px;">
        
        <!-- CARD 1: Clear Demo Data Only (Keep Admin & Settings) -->
        <div class="checkout-card" style="border: 1px solid var(--border-color); background: var(--bg-secondary); padding: 24px; border-radius: var(--radius-md); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 16px; color: var(--text-primary); font-weight: 700; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    <i class="fa-solid fa-broom" style="color: var(--accent-primary);"></i> 1. Dọn sạch dữ liệu Demo
                </h3>
                <p style="font-size: 13px; line-height: 1.6; color: var(--text-secondary); margin-bottom: 16px;">
                    Chỉ xóa sạch các thông tin của người dùng demo thử nghiệm. Thích hợp khi bạn muốn giữ lại tài khoản quản trị hiện tại và các cấu hình chính sách đã thiết lập.
                </p>
                <div style="background: rgba(255,255,255,0.02); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 12px; line-height: 1.5; color: var(--text-muted);">
                    <strong style="color: var(--danger);"><i class="fa-solid fa-circle-minus"></i> Bị xóa:</strong> Bài viết, bình luận, tin nhắn chat, theo dõi, khiếu nại, tài khoản người dùng, trang tự lập, tệp tin đính kèm tải lên.<br>
                    <strong style="color: var(--success);"><i class="fa-solid fa-circle-plus"></i> Giữ lại:</strong> Tài khoản Admin hiện tại, Toàn bộ cấu hình hệ thống & chính sách, ảnh đại diện mặc định.
                </div>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="action_clear_demo" value="1">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="confirm_code" class="form-label" style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Gõ chữ <strong style="color: var(--danger);">DELETE</strong> để xác nhận:
                    </label>
                    <input type="text" name="confirm_code" id="confirm_code" autocomplete="off" class="form-input" 
                           style="width: 100%; padding: 10px 14px; font-size: 13px; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: var(--radius-sm); box-sizing: border-box;" 
                           placeholder="Nhập chữ DELETE" required>
                </div>

                <button type="submit" class="btn-purchase-action" 
                        style="width: 100%; border: none; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #fff; cursor: pointer; padding: 12px; border-radius: var(--radius-full); font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fa-solid fa-trash-can"></i> Dọn sạch dữ liệu Demo
                </button>
            </form>
        </div>

        <!-- CARD 2: Factory Reset / Reset entire system -->
        <div class="checkout-card" style="border: 1px solid var(--border-color); background: var(--bg-secondary); padding: 24px; border-radius: var(--radius-md); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 16px; color: var(--danger); font-weight: 700; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    <i class="fa-solid fa-rotate" style="color: var(--danger);"></i> 2. Đặt lại toàn bộ hệ thống
                </h3>
                <p style="font-size: 13px; line-height: 1.6; color: var(--text-secondary); margin-bottom: 16px;">
                    Khôi phục hệ thống về cài đặt gốc nguyên bản. Xóa bỏ hoàn toàn cơ sở dữ liệu cũ, khởi tạo lại toàn bộ cấu trúc và nạp dữ liệu mẫu ban đầu của Frest App.
                </p>
                <div style="background: rgba(239, 68, 68, 0.04); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 12px; line-height: 1.5; color: var(--text-muted); border: 1px solid rgba(239, 68, 68, 0.1);">
                    <strong style="color: var(--danger);"><i class="fa-solid fa-circle-exclamation"></i> Chú ý:</strong> 
                    Toàn bộ tài khoản quản trị sẽ bị xóa. Hệ thống sẽ tạo lại tài khoản admin mặc định. Bạn sẽ bị đăng xuất ngay lập tức và cần đăng nhập lại bằng thông tin tài khoản gốc.
                </div>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="action_factory_reset" value="1">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="confirm_code_reset" class="form-label" style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Gõ chữ <strong style="color: var(--danger);">RESET</strong> để xác nhận:
                    </label>
                    <input type="text" name="confirm_code_reset" id="confirm_code_reset" autocomplete="off" class="form-input" 
                           style="width: 100%; padding: 10px 14px; font-size: 13px; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: var(--radius-sm); box-sizing: border-box;" 
                           placeholder="Nhập chữ RESET" required>
                </div>

                <button type="submit" class="btn-purchase-action" 
                        style="width: 100%; border: none; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #fff; cursor: pointer; padding: 12px; border-radius: var(--radius-full); font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fa-solid fa-power-off"></i> Khôi phục cài đặt gốc
                </button>
            </form>
        </div>

    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
