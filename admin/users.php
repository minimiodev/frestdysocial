<?php
/**
 * User Account Moderation - Frest App Admin
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

    // Handle verification badge update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_badge'])) {
        $u_id = intval($_POST['user_id'] ?? 0);
        $v_type = trim($_POST['verification_type'] ?? '');
        if ($v_type === 'none' || empty($v_type)) {
            $v_type = null;
        }
        
        $update_stmt = $db->prepare("UPDATE users SET verification_type = ? WHERE id = ?");
        $update_stmt->execute([$v_type, $u_id]);
        $_SESSION['success_msg'] = "Đã cập nhật loại tích xác thực thành công cho người dùng #{$u_id}.";
        header("Location: users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle status & ban update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_status'])) {
        $u_id = intval($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        $reason = trim($_POST['status_reason'] ?? '');
        $lock_until = trim($_POST['lock_until'] ?? '');
        
        if ($status !== 'temporarily_locked') {
            $lock_until = null;
        } else {
            if (empty($lock_until)) {
                $lock_until = date('Y-m-d H:i:s', time() + 86400);
            } else {
                $lock_until = date('Y-m-d H:i:s', strtotime($lock_until));
            }
        }
        
        if (empty($reason)) {
            $reason = null;
        }
        
        $update_stmt = $db->prepare("UPDATE users SET status = ?, status_reason = ?, lock_until = ? WHERE id = ?");
        $update_stmt->execute([$status, $reason, $lock_until, $u_id]);
        
        $_SESSION['success_msg'] = "Đã cập nhật trạng thái tài khoản thành công cho thành viên #{$u_id}.";
        header("Location: users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle delete user account
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        
        // Find avatar file to clean up
        $stmt_avatar = $db->prepare("SELECT avatar_filename FROM users WHERE id = ?");
        $stmt_avatar->execute([$delete_id]);
        $avatar = $stmt_avatar->fetchColumn();

        // Also clean up any post images and videos written by this user
        $posts_media_stmt = $db->prepare("SELECT image_filename, video_filename FROM posts WHERE user_id = ?");
        $posts_media_stmt->execute([$delete_id]);
        $posts_media = $posts_media_stmt->fetchAll();

        // Run deletion (cascades to posts, replies, follows, reactions)
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            // Remove user avatar file if not default
            if (!empty($avatar) && $avatar !== 'avatar_default.png') {
                @unlink(UPLOAD_DIR . 'avatars/' . $avatar);
            }
            
            // Remove user post media files (both image and video)
            foreach ($posts_media as $media) {
                if (!empty($media['image_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $media['image_filename']);
                }
                if (!empty($media['video_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $media['video_filename']);
                }
            }
            $_SESSION['success_msg'] = "Đã xóa hoàn toàn tài khoản người dùng #{$delete_id} và mọi dữ liệu liên quan.";
        } else {
            $_SESSION['error_msg'] = "Không thể xóa tài khoản người dùng này.";
        }
        header("Location: users.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Load users list
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        $users = $stmt->fetchAll();
    } else {
        $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý người dùng</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Kiểm duyệt các tài khoản thành viên và phân bổ các loại tích xác minh đa màu sắc
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

<!-- Search Bar -->
<div class="checkout-card" style="padding: 24px; margin-bottom: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
    <form action="" method="GET" style="display: flex; gap: 12px; margin-bottom: 0;">
        <input type="text" name="search" class="form-input" placeholder="Tìm kiếm tên tài khoản hoặc địa chỉ email..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; border-radius: var(--radius-sm);">
        <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px;">Tìm kiếm</button>
    </form>
</div>

<!-- Users Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách thành viên</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($users); ?> người dùng</span>
    </div>

    <?php if (empty($users)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-users-slash" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không tìm thấy thành viên nào phù hợp.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Avatar</th>
                        <th>Tên người dùng</th>
                        <th>Email</th>
                        <th>Tích xác minh</th>
                        <th>Trạng thái</th>
                        <th>Ngày tham gia</th>
                        <th style="width: 100px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $user['id']; ?></td>
                            <td>
                                <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $user['avatar_filename']; ?>" style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 1px solid var(--border-color);">
                            </td>
                            <td style="font-weight: 600;">
                                <span style="display: inline-flex; align-items: center; gap: 4px;">
                                    @<?php echo sanitize($user['username']); ?>
                                    <?php echo getVerificationBadgeHTML($user['verification_type'], $user['username']); ?>
                                </span>
                            </td>
                            <td style="font-size: 13px;"><?php echo sanitize($user['email']); ?></td>
                            <td>
                                <form action="" method="POST" style="display: inline-flex; align-items: center; margin-bottom: 0;">
                                    <input type="hidden" name="action_update_badge" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="verification_type" onchange="this.form.submit();" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 5px 8px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer;">
                                        <option value="none" <?php echo empty($user['verification_type']) || $user['verification_type'] === 'none' ? 'selected' : ''; ?>>Không tích</option>
                                        <option value="official" <?php echo $user['verification_type'] === 'official' ? 'selected' : ''; ?>>Huy hiệu đã xác minh (Xanh dương)</option>
                                        <option value="subscribed" <?php echo $user['verification_type'] === 'subscribed' ? 'selected' : ''; ?>>Frest đã xác minh (Trả phí - Xanh dương)</option>
                                    </select>
                                </form>
                            </td>
                             <td>
                                <?php 
                                $status = $user['status'] ?? 'active';
                                $reason_esc = htmlspecialchars($user['status_reason'] ?? '', ENT_QUOTES);
                                $lock_val = ($user['lock_until'] ?? null) ? date('Y-m-d\TH:i', strtotime($user['lock_until'])) : '';
                                
                                if ($status === 'active') {
                                    echo '<span class="badge-status active-status" style="background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" onclick="openStatusModal(' . $user['id'] . ', \'active\', \'' . $reason_esc . '\', \'' . $lock_val . '\')"><i class="fa-solid fa-circle-check"></i> Hoạt động</span>';
                                } elseif ($status === 'temporarily_locked') {
                                    echo '<span class="badge-status locked-status" style="background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" onclick="openStatusModal(' . $user['id'] . ', \'temporarily_locked\', \'' . $reason_esc . '\', \'' . $lock_val . '\')"><i class="fa-solid fa-clock"></i> Khóa tạm thời</span>';
                                } elseif ($status === 'disabled') {
                                    echo '<span class="badge-status disabled-status" style="background: rgba(100, 116, 139, 0.1); color: var(--text-secondary); border: 1px solid rgba(100, 116, 139, 0.2); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" onclick="openStatusModal(' . $user['id'] . ', \'disabled\', \'' . $reason_esc . '\', \'' . $lock_val . '\')"><i class="fa-solid fa-ban"></i> Vô hiệu hóa</span>';
                                } elseif ($status === 'permanently_suspended') {
                                    echo '<span class="badge-status suspended-status" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" onclick="openStatusModal(' . $user['id'] . ', \'permanently_suspended\', \'' . $reason_esc . '\', \'' . $lock_val . '\')"><i class="fa-solid fa-user-xmark"></i> Khóa vĩnh viễn</span>';
                                }
                                ?>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <a href="users.php?delete=<?php echo $user['id']; ?>" 
                                   class="action-btn delete" 
                                   title="Xóa tài khoản"
                                   data-confirm="CẢNH BÁO: Bạn chắc chắn muốn xóa tài khoản người dùng này? Toàn bộ bài đăng, bình luận, tương tác và theo dõi của họ sẽ bị xóa sạch khỏi hệ thống vĩnh viễn!">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Status Update Modal -->
<div class="modal-overlay" id="status-update-modal" style="display: none; z-index: 1005; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85);">
    <div class="modal-content glassmorphism-card" style="max-width: 480px; width: 100%; padding: 24px; border-radius: var(--radius-md); text-align: left; position: relative; margin: 20px;">
        <button class="modal-close" onclick="closeStatusModal();" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
            <i class="fa-solid fa-user-shield" style="color: var(--accent-primary); margin-right: 6px;"></i> Thiết lập Trạng thái Tài khoản
        </h3>
        
        <form action="" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action_update_status" value="1">
            <input type="hidden" name="user_id" id="modal-user-id" value="">
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="modal-status" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Trạng thái tài khoản *</label>
                <select name="status" id="modal-status" onchange="toggleLockTimeInput();" class="form-input" style="padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); width: 100%; border-radius: var(--radius-sm);">
                    <option value="active">Hoạt động bình thường (Active)</option>
                    <option value="temporarily_locked">Khóa tạm thời (Temporarily Locked)</option>
                    <option value="disabled">Vô hiệu hóa (Disabled)</option>
                    <option value="permanently_suspended">Đóng vĩnh viễn (Permanently Suspended)</option>
                </select>
            </div>
            
            <div class="form-group" id="lock-until-group" style="display: none; margin-bottom: 0;">
                <label for="modal-lock-until" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Khóa cho đến khi nào (Lock Until) *</label>
                <input type="datetime-local" name="lock_until" id="modal-lock-until" class="form-input" style="padding: 10px; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); width: 100%; box-sizing: border-box; border-radius: var(--radius-sm);">
                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 4px;">Thời gian mở khóa tài khoản tự động.</div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="modal-reason" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Lý do hạn chế (Reason) *</label>
                <textarea name="status_reason" id="modal-reason" class="form-input" placeholder="Nhập lý do hạn chế để thông báo cho người dùng..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); resize: vertical; height: 100px; padding: 10px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;"></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn-primary" onclick="closeStatusModal();" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px 16px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px; width: auto; height: 36px;">Đóng</button>
                <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; padding: 8px 24px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px; width: auto; height: 36px;">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(userId, currentStatus, currentReason, lockUntil) {
    document.getElementById('modal-user-id').value = userId;
    document.getElementById('modal-status').value = currentStatus;
    document.getElementById('modal-reason').value = currentReason;
    
    // Set custom lock datetime picker if present
    const lockUntilInput = document.getElementById('modal-lock-until');
    if (lockUntil) {
        lockUntilInput.value = lockUntil;
    } else {
        // Default: set to tomorrow at current time
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        const hh = String(tomorrow.getHours()).padStart(2, '0');
        const min = String(tomorrow.getMinutes()).padStart(2, '0');
        lockUntilInput.value = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    }
    
    toggleLockTimeInput();
    
    const modal = document.getElementById('status-update-modal');
    modal.style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('status-update-modal').style.display = 'none';
}

function toggleLockTimeInput() {
    const status = document.getElementById('modal-status').value;
    const lockGroup = document.getElementById('lock-until-group');
    const reasonInput = document.getElementById('modal-reason');
    
    if (status === 'temporarily_locked') {
        lockGroup.style.display = 'block';
        reasonInput.required = true;
    } else if (status === 'active') {
        lockGroup.style.display = 'none';
        reasonInput.required = false;
    } else {
        lockGroup.style.display = 'none';
        reasonInput.required = true;
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

