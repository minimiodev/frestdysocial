<?php
/**
 * Pending Phone Registrations Moderation - Frest App Admin
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

    // Handle activate request
    if (isset($_GET['activate'])) {
        $activate_id = intval($_GET['activate']);
        
        $up_stmt = $db->prepare("UPDATE users SET phone_verified = 1, phone_verification_code = NULL WHERE id = ? AND phone_verified = 0");
        if ($up_stmt->execute([$activate_id])) {
            $_SESSION['success_msg'] = "Đã kích hoạt tài khoản người dùng #{$activate_id} thành công.";
        } else {
            $_SESSION['error_msg'] = "Không thể kích hoạt tài khoản này.";
        }
        header("Location: phone_verifications.php");
        exit;
    }

    // Handle delete user account
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        
        // Find avatar file to clean up
        $stmt_avatar = $db->prepare("SELECT avatar_filename FROM users WHERE id = ?");
        $stmt_avatar->execute([$delete_id]);
        $avatar = $stmt_avatar->fetchColumn();

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            // Remove user avatar file if not default
            if (!empty($avatar) && $avatar !== 'avatar_default.png') {
                @unlink(UPLOAD_DIR . 'avatars/' . $avatar);
            }
            $_SESSION['success_msg'] = "Đã xóa tài khoản chờ kích hoạt #{$delete_id} khỏi hệ thống.";
        } else {
            $_SESSION['error_msg'] = "Không thể xóa tài khoản này.";
        }
        header("Location: phone_verifications.php");
        exit;
    }

    // Load pending phone users
    $pending_users = $db->query("SELECT * FROM users WHERE phone_verified = 0 AND phone_number IS NOT NULL ORDER BY created_at DESC")->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Kích hoạt Số điện thoại</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Xem danh sách đăng ký bằng số điện thoại và cung cấp mã xác minh hoặc kích hoạt trực tiếp tài khoản thành viên
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

<!-- Pending Users Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách chờ cấp mã / kích hoạt</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($pending_users); ?> yêu cầu</span>
    </div>

    <?php if (empty($pending_users)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-mobile-screen" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không có tài khoản nào đang chờ kích hoạt bằng số điện thoại.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Họ tên thực tế</th>
                        <th>Tên người dùng</th>
                        <th>Số điện thoại</th>
                        <th>Email ảo hệ thống</th>
                        <th style="text-align: center;">Mã kích hoạt (OTP)</th>
                        <th>Thời gian đăng ký</th>
                        <th style="width: 150px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $user['id']; ?></td>
                            <td><?php echo sanitize($user['full_name']); ?></td>
                            <td style="font-weight: 600;">@<?php echo sanitize($user['username']); ?></td>
                            <td style="font-weight: 700; color: var(--accent-primary);"><?php echo sanitize($user['phone_number']); ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo sanitize($user['email']); ?></td>
                            <td style="text-align: center;">
                                <span style="font-size: 16px; font-weight: 800; background: rgba(235,94,40,0.12); color: var(--accent-primary); padding: 4px 12px; border-radius: var(--radius-sm); border: 1px dashed rgba(235,94,40,0.3); font-family: monospace; letter-spacing: 1px;">
                                    <?php echo sanitize($user['phone_verification_code']); ?>
                                </span>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="phone_verifications.php?activate=<?php echo $user['id']; ?>" 
                                       class="action-btn" 
                                       style="background: rgba(16,185,129,0.12); color: var(--success); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px;"
                                       title="Kích hoạt trực tiếp"
                                       data-confirm="Bạn có muốn duyệt kích hoạt trực tiếp tài khoản này mà không cần nhập mã?">
                                         <i class="fa-solid fa-check"></i>
                                     </a>
                                     <a href="phone_verifications.php?delete=<?php echo $user['id']; ?>" 
                                       class="action-btn delete" 
                                       style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px;"
                                       title="Hủy/Xóa đăng ký"
                                       data-confirm="Bạn có chắc muốn hủy đăng ký và xóa tài khoản chờ này?">
                                         <i class="fa-solid fa-trash-can"></i>
                                     </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

