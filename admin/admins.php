<?php
/**
 * Administrator Accounts Management - Frest App Admin
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Route guards
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit;
}

$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

try {
    $db = getDB();

    // Handle delete admin
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        $current_admin_id = intval($_SESSION['admin_id'] ?? 0); // Assuming session stores admin_id on login

        // Prevent self-deletion
        if ($delete_id === $current_admin_id) {
            $_SESSION['error_msg'] = "Bạn không thể tự xóa tài khoản quản trị viên của chính mình!";
        } else {
            // Count total admins to ensure we don't delete the last one
            $count_stmt = $db->query("SELECT COUNT(*) FROM admins");
            $total_admins = intval($count_stmt->fetchColumn());

            if ($total_admins <= 1) {
                $_SESSION['error_msg'] = "Không thể xóa quản trị viên duy nhất còn lại trong hệ thống!";
            } else {
                $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
                if ($stmt->execute([$delete_id])) {
                    $_SESSION['success_msg'] = "Đã xóa tài khoản quản trị viên #{$delete_id} thành công.";
                } else {
                    $_SESSION['error_msg'] = "Không thể xóa tài khoản quản trị viên.";
                }
            }
        }
        header("Location: admins.php");
        exit;
    }

    // Handle add new admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_admin'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['error_msg'] = "Tên đăng nhập và mật khẩu không được để trống.";
        } elseif ($password !== $confirm_password) {
            $_SESSION['error_msg'] = "Mật khẩu xác nhận không khớp.";
        } elseif (strlen($password) < 6) {
            $_SESSION['error_msg'] = "Mật khẩu phải chứa ít nhất 6 ký tự.";
        } else {
            // Check if username already exists
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
            $stmt_check->execute([$username]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['error_msg'] = "Tên đăng nhập quản trị viên này đã tồn tại.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $db->prepare("INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)");
                if ($stmt_insert->execute([$username, $password_hash, $email ?: null])) {
                    $_SESSION['success_msg'] = "Đã thêm mới tài khoản quản trị viên '@{$username}' thành công!";
                    // Clear post data to clear form
                    $_POST = [];
                } else {
                    $_SESSION['error_msg'] = "Không thể thêm tài khoản quản trị viên mới.";
                }
            }
        }
        header("Location: admins.php");
        exit;
    }

    // Load admins list
    $admins = $db->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý Quản trị viên</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Thêm mới, theo dõi danh sách và phân bổ tài khoản quản trị viên hệ thống
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

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start; margin-bottom: 40px;">
    <!-- Admins Table -->
    <div class="data-table-container" style="margin-bottom: 0;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách Admin</h3>
            <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($admins); ?> quản trị viên</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Email</th>
                        <th>Ngày tạo</th>
                        <th style="width: 80px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $admin['id']; ?></td>
                            <td style="font-weight: 600; color: var(--text-primary);">
                                <i class="fa-solid fa-user-shield" style="color: var(--accent-primary); margin-right: 6px;"></i>
                                @<?php echo sanitize($admin['username']); ?>
                            </td>
                            <td style="font-size: 13px;"><?php echo $admin['email'] ? sanitize($admin['email']) : '<em style="color:var(--text-muted)">Không có</em>'; ?></td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($admin['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <?php if (intval($admin['id']) !== intval($_SESSION['admin_id'] ?? 0)): ?>
                                    <a href="admins.php?delete=<?php echo $admin['id']; ?>" 
                                       class="action-btn delete" 
                                       title="Xóa tài khoản admin"
                                       data-confirm="Bạn có chắc chắn muốn xóa tài khoản Admin này? Hành động này không thể hoàn tác.">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted); padding: 4px 8px; border-radius: 4px; font-size: 11px;">Hiện tại</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Admin Form -->
    <div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
            <i class="fa-solid fa-user-plus" style="color: var(--accent-primary); margin-right: 6px;"></i> Thêm Quản trị viên mới
        </h3>

        <form action="" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action_add_admin" value="1">

            <div class="form-group" style="margin-bottom: 0;">
                <label for="username" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên đăng nhập (Username) *</label>
                <input type="text" name="username" id="username" class="form-input" placeholder="Nhập tên đăng nhập..." required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="email" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Địa chỉ Email</label>
                <input type="email" name="email" id="email" class="form-input" placeholder="Nhập địa chỉ email (tùy chọn)..." 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Mật khẩu *</label>
                <div class="password-toggle-wrapper">
                    <input type="password" name="password" id="password" class="form-input" placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)..." required style="padding-right: 44px !important;">
                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Xác nhận mật khẩu *</label>
                <div class="password-toggle-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Nhập lại mật khẩu..." required style="padding-right: 44px !important;">
                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px;">
                <i class="fa-solid fa-plus"></i> Tạo tài khoản
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
