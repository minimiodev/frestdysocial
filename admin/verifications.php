<?php
/**
 * Age Verification Requests Moderation - Frest App Admin
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

    // Handle Approve Request
    if (isset($_GET['approve'])) {
        $approve_id = intval($_GET['approve']);
        $stmt = $db->prepare("UPDATE users SET age_verification_status = 'verified', is_adult = 1, show_nsfw = 1 WHERE id = ?");
        if ($stmt->execute([$approve_id])) {
            $_SESSION['success_msg'] = "Đã phê duyệt xác minh độ tuổi thành công cho người dùng #{$approve_id}.";
        } else {
            $_SESSION['error_msg'] = "Không thể phê duyệt yêu cầu này.";
        }
        header("Location: verifications.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle Reject Request
    if (isset($_GET['reject'])) {
        $reject_id = intval($_GET['reject']);
        
        // Fetch and remove proof file to save disk space
        $stmt_proof = $db->prepare("SELECT id_proof_filename FROM users WHERE id = ?");
        $stmt_proof->execute([$reject_id]);
        $proof_file = $stmt_proof->fetchColumn();
        
        $stmt = $db->prepare("UPDATE users SET age_verification_status = 'rejected', is_adult = 0, show_nsfw = 0, id_proof_filename = NULL WHERE id = ?");
        if ($stmt->execute([$reject_id])) {
            if (!empty($proof_file)) {
                @unlink(UPLOAD_DIR . 'proofs/' . $proof_file);
            }
            $_SESSION['success_msg'] = "Đã từ chối yêu cầu xác minh cho người dùng #{$reject_id}. Dữ liệu ảnh minh chứng đã được xóa bảo mật.";
        } else {
            $_SESSION['error_msg'] = "Không thể từ chối yêu cầu này.";
        }
        header("Location: verifications.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Load age verification requests (all users with status in pending, verified, rejected)
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM users 
                              WHERE age_verification_status IN ('pending', 'verified', 'rejected') 
                                AND (username LIKE ? OR email LIKE ?)
                              ORDER BY FIELD(age_verification_status, 'pending', 'rejected', 'verified'), created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        $users = $stmt->fetchAll();
    } else {
        $users = $db->query("SELECT * FROM users 
                             WHERE age_verification_status IN ('pending', 'verified', 'rejected')
                             ORDER BY FIELD(age_verification_status, 'pending', 'rejected', 'verified'), created_at DESC")->fetchAll();
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Xác minh độ tuổi 18+</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Kiểm duyệt các yêu cầu xác minh độ tuổi để cho phép xem hoặc tải nội dung NSFW nhạy cảm
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
        <input type="text" name="search" class="form-input" placeholder="Tìm kiếm theo tên người dùng hoặc email..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; border-radius: var(--radius-sm);">
        <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px;">Tìm kiếm</button>
    </form>
</div>

<!-- Requests Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách yêu cầu xác minh</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($users); ?> yêu cầu</span>
    </div>

    <?php if (empty($users)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-cake-candles" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không có yêu cầu xác minh nào cần kiểm duyệt.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Thành viên</th>
                        <th>Ngày sinh (DOB)</th>
                        <th>Tuổi thực tế</th>
                        <th>Ảnh minh chứng</th>
                        <th>Trạng thái</th>
                        <th style="width: 120px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        // Calculate age
                        $age = 'N/A';
                        if (!empty($user['dob'])) {
                            $birthDate = new DateTime($user['dob']);
                            $today = new DateTime();
                            $age = $today->diff($birthDate)->y;
                        }
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $user['id']; ?></td>
                            <td style="font-weight: 600;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $user['avatar_filename']; ?>" style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%;">
                                    <div>
                                        <div style="font-size: 13.5px; color: var(--text-primary);">@<?php echo sanitize($user['username']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size: 13px; font-weight: 600;"><?php echo (!empty($user['dob']) && ($dob_time = strtotime($user['dob'])) > 0) ? date('d/m/Y', $dob_time) : 'Chưa nhập'; ?></td>
                            <td style="font-size: 13.5px; font-weight: 700; color: <?php echo (is_numeric($age) && $age >= 18) ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo $age; ?> tuổi
                            </td>
                            <td>
                                <?php if (!empty($user['id_proof_filename'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/uploads/proofs/<?php echo $user['id_proof_filename']; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--accent-primary);">
                                        <i class="fa-regular fa-image" style="font-size: 14px;"></i> Xem tài liệu
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Không có ảnh</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['age_verification_status'] === 'pending'): ?>
                                    <span style="background: rgba(235,94,40,0.15); color: var(--accent-primary); border: 1px solid rgba(235,94,40,0.3); padding: 4px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-hourglass-half"></i> Chờ duyệt
                                    </span>
                                <?php elseif ($user['age_verification_status'] === 'verified'): ?>
                                    <span style="background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); padding: 4px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-circle-check"></i> Đã duyệt
                                    </span>
                                <?php else: ?>
                                    <span style="background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); padding: 4px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-circle-xmark"></i> Từ chối
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <?php if ($user['age_verification_status'] === 'pending'): ?>
                                    <a href="verifications.php?approve=<?php echo $user['id']; ?>" 
                                       class="action-btn" 
                                       title="Phê duyệt"
                                       style="color: var(--success); border-color: var(--success); margin-right: 8px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                    <a href="verifications.php?reject=<?php echo $user['id']; ?>" 
                                       class="action-btn delete" 
                                       title="Từ chối"
                                       style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;"
                                       data-confirm="Bạn chắc chắn muốn từ chối yêu cầu xác minh độ tuổi của người dùng này?">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                <?php else: ?>
                                    <!-- Reset status to let them re-verify -->
                                    <a href="verifications.php?reject=<?php echo $user['id']; ?>" 
                                       class="action-btn" 
                                       title="Yêu cầu xác minh lại"
                                       style="color: var(--text-secondary); border-color: var(--border-color); display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);"
                                       data-confirm="Bạn muốn hủy kết quả hiện tại và yêu cầu người dùng xác minh lại?">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

