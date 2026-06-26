<?php
/**
 * Policy Settings Page - Wallpaper Haven Admin
 */
require_once __DIR__ . '/header.php';

$error_msg = '';
$success_msg = '';

try {
    $db = getDB();

    // Auto-create page_categories settings if not present
    $cat_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE key_name = 'page_categories'");
    $cat_check->execute();
    if ($cat_check->fetchColumn() == 0) {
        $default_categories = "Cộng đồng, Doanh nghiệp, Blog cá nhân, Người sáng tạo nội dung, Giải trí, Tin tức, Nhân vật công chúng, Nghệ sĩ, Nhà phát triển game";
        $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('page_categories', ?)")->execute([$default_categories]);
    }

    // 1. Process Update Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_settings'])) {
        $privacy_policy = $_POST['privacy_policy'] ?? '';
        $terms_of_service = $_POST['terms_of_service'] ?? '';
        $page_categories = trim($_POST['page_categories'] ?? '');

        // Validate
        if (empty($privacy_policy) || empty($terms_of_service) || empty($page_categories)) {
            $error_msg = "Nội dung chính sách, điều khoản và danh mục trang không được để trống.";
        } else {
            // Update Privacy Policy
            $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'privacy_policy'");
            $stmt->execute([$privacy_policy]);

            // Update Terms of Service
            $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'terms_of_service'");
            $stmt->execute([$terms_of_service]);

            // Update Page Categories
            $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'page_categories'");
            $stmt->execute([$page_categories]);

            $success_msg = "Cập nhật các cấu hình hệ thống thành công!";
        }
    }

    // 2. Fetch current values
    $privacy_stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'privacy_policy'");
    $privacy_stmt->execute();
    $privacy_policy_val = $privacy_stmt->fetchColumn() ?: '';

    $terms_stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'terms_of_service'");
    $terms_stmt->execute();
    $terms_of_service_val = $terms_stmt->fetchColumn() ?: '';

    $cat_stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'page_categories'");
    $cat_stmt->execute();
    $page_categories_val = $cat_stmt->fetchColumn() ?: '';

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc cập nhật CSDL: " . $e->getMessage();
}
?>

<div class="admin-header">
    <h1 class="admin-title">Cấu hình chính sách</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Chỉnh sửa nội dung Điều khoản dịch vụ và Chính sách bảo mật hiển thị ở dạng popup ở trang chủ và chân trang
    </div>
</div>

<?php if (!empty($error_msg)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
        <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
        <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<div class="checkout-card" style="max-width: 900px; margin-bottom: 40px;">
    <h3 style="font-family: var(--font-heading); font-size: 20px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
        <i class="fa-solid fa-file-contract"></i> Nội dung văn bản pháp lý
    </h3>

    <form action="" method="POST">
        <input type="hidden" name="action_update_settings" value="1">

        <div class="form-group" style="margin-bottom: 28px;">
            <label for="page_categories" class="form-label" style="font-size: 15px; margin-bottom: 10px;">
                <i class="fa-solid fa-tags" style="color: var(--accent-primary); margin-right: 4px;"></i> Danh mục Trang (Page Categories)
            </label>
            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Nhập danh sách các danh mục, phân tách bằng dấu phẩy. Các danh mục này sẽ xuất hiện trong phần thiết lập Trang cá nhân và Tạo trang mới.</div>
            <input type="text" name="page_categories" id="page_categories" class="form-input" 
                   style="padding: 12px 16px; font-size: 14.5px; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;" 
                   value="<?php echo htmlspecialchars($page_categories_val); ?>" required>
        </div>

        <div class="form-group" style="margin-bottom: 28px;">
            <label for="terms_of_service" class="form-label" style="font-size: 15px; margin-bottom: 10px;">
                <i class="fa-solid fa-gavel" style="color: var(--accent-primary); margin-right: 4px;"></i> Điều khoản sử dụng (Terms of Service)
            </label>
            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Hỗ trợ định dạng mã HTML (ví dụ: &lt;h3&gt;, &lt;p&gt;, &lt;strong&gt;, v.v.) để hiển thị đẹp mắt.</div>
            <textarea name="terms_of_service" id="terms_of_service" class="form-input" 
                      style="height: 220px; padding: 12px 16px; font-family: monospace; font-size: 13px; line-height: 1.6; resize: vertical;" 
                      placeholder="Nhập nội dung điều khoản..." required><?php echo htmlspecialchars($terms_of_service_val); ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 32px;">
            <label for="privacy_policy" class="form-label" style="font-size: 15px; margin-bottom: 10px;">
                <i class="fa-solid fa-shield-halved" style="color: var(--accent-primary); margin-right: 4px;"></i> Chính sách bảo mật (Privacy Policy)
            </label>
            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Hỗ trợ định dạng mã HTML để hiển thị đẹp mắt trong popup.</div>
            <textarea name="privacy_policy" id="privacy_policy" class="form-input" 
                      style="height: 220px; padding: 12px 16px; font-family: monospace; font-size: 13px; line-height: 1.6; resize: vertical;" 
                      placeholder="Nhập nội dung chính sách bảo mật..." required><?php echo htmlspecialchars($privacy_policy_val); ?></textarea>
        </div>

        <button type="submit" class="btn-purchase-action" 
                style="border: none; background: var(--accent-gradient); color: #fff; cursor: pointer; padding: 12px 36px; border-radius: var(--radius-full); font-weight: 700; width: auto; font-size: 14px;">
            <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi cấu hình
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

