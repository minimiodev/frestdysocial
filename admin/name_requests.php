<?php
/**
 * Display Name Change Requests Moderation - Frest App Admin
 */
require_once __DIR__ . '/header.php';

$error_msg = '';
$success_msg = '';

try {
    $db = getDB();

    // Handle Approve Request
    if (isset($_GET['approve'])) {
        $approve_id = intval($_GET['approve']);
        
        // Fetch pending name components
        $stmt_user = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_user->execute([$approve_id]);
        $user = $stmt_user->fetch();
        
        if ($user && $user['name_change_status'] === 'pending') {
            $new_first_name = $user['pending_first_name'];
            $new_middle_name = $user['pending_middle_name'];
            $new_last_name = $user['pending_last_name'];
            $new_display_order = $user['pending_name_display_order'];
            
            $formatted_full_name = formatUserFullName($new_first_name, $new_middle_name, $new_last_name, $new_display_order);
            
            $stmt = $db->prepare("UPDATE users SET 
                                    first_name = ?, 
                                    middle_name = ?, 
                                    last_name = ?, 
                                    name_display_order = ?, 
                                    full_name = ?, 
                                    display_name_last_updated = NOW(),
                                    pending_first_name = NULL,
                                    pending_middle_name = NULL,
                                    pending_last_name = NULL,
                                    pending_name_display_order = NULL,
                                    name_change_status = 'none' 
                                  WHERE id = ?");
            if ($stmt->execute([$new_first_name, $new_middle_name, $new_last_name, $new_display_order, $formatted_full_name, $approve_id])) {
                // Log approved name change history
                $old_full_name = ($user['full_name'] ?? '') ?: $user['username'];
                $stmt_hist = $db->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name) VALUES ('user', ?, ?, ?)");
                $stmt_hist->execute([$approve_id, $old_full_name, $formatted_full_name]);

                $success_msg = "Đã phê duyệt yêu cầu đổi tên thành công cho @{$user['username']} thành '{$formatted_full_name}'.";
            } else {
                $error_msg = "Không thể phê duyệt yêu cầu đổi tên.";
            }
        } else {
            $error_msg = "Yêu cầu đổi tên không tồn tại hoặc không hợp lệ.";
        }
    }

    // Handle Reject Request
    if (isset($_GET['reject'])) {
        $reject_id = intval($_GET['reject']);
        
        $stmt_user = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_user->execute([$reject_id]);
        $username = $stmt_user->fetchColumn();
        
        if ($username) {
            $stmt = $db->prepare("UPDATE users SET 
                                    pending_first_name = NULL,
                                    pending_middle_name = NULL,
                                    pending_last_name = NULL,
                                    pending_name_display_order = NULL,
                                    name_change_status = 'none' 
                                  WHERE id = ?");
            if ($stmt->execute([$reject_id])) {
                $success_msg = "Đã từ chối yêu cầu đổi tên của @{$username}.";
            } else {
                $error_msg = "Không thể từ chối yêu cầu đổi tên.";
            }
        } else {
            $error_msg = "Người dùng không tồn tại.";
        }
    }

    // Load pending name change requests
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM users 
                              WHERE name_change_status = 'pending' 
                                AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)
                              ORDER BY created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
        $pending_users = $stmt->fetchAll();
    } else {
        $pending_users = $db->query("SELECT * FROM users 
                                     WHERE name_change_status = 'pending'
                                     ORDER BY created_at DESC")->fetchAll();
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}
?>

<div class="admin-header">
    <h1 class="admin-title">Duyệt yêu cầu đổi tên</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Kiểm duyệt và phê duyệt các yêu cầu thay đổi tên hiển thị trong vòng 60 ngày của thành viên
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
        <input type="text" name="search" class="form-input" placeholder="Tìm kiếm theo tên người dùng, email, tên đầy đủ..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; border-radius: var(--radius-sm);">
        <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px;">Tìm kiếm</button>
    </form>
</div>

<!-- Requests Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Yêu cầu đổi tên đang chờ duyệt</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($pending_users); ?> yêu cầu</span>
    </div>

    <?php if (empty($pending_users)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-user-check" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không có yêu cầu thay đổi tên nào đang chờ kiểm duyệt.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Thành viên</th>
                        <th>Tên hiện tại</th>
                        <th>Tên mới yêu cầu</th>
                        <th>Ngày cập nhật gần nhất</th>
                        <th style="width: 120px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user): 
                        $new_name = formatUserFullName(
                            $user['pending_first_name'],
                            $user['pending_middle_name'],
                            $user['pending_last_name'],
                            $user['pending_name_display_order']
                        );
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
                            <td style="font-size: 13.5px; font-weight: 600; color: var(--text-secondary);">
                                <?php echo !empty($user['full_name']) ? sanitize($user['full_name']) : '<span style="font-style:italic;color:var(--text-muted);">Chưa đặt</span>'; ?>
                            </td>
                            <td style="font-size: 14.5px; font-weight: 700; color: var(--accent-primary);">
                                <?php echo htmlspecialchars($new_name); ?>
                                <div style="font-size: 10px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">
                                    Kiểu: <?php echo htmlspecialchars($user['pending_name_display_order']); ?>
                                </div>
                            </td>
                            <td style="font-size: 13px; color: var(--text-secondary);">
                                <?php echo $user['display_name_last_updated'] ? date('d/m/Y H:i', strtotime($user['display_name_last_updated'])) : 'Chưa từng đổi'; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="name_requests.php?approve=<?php echo $user['id']; ?>" 
                                   class="action-btn" 
                                   title="Phê duyệt đổi tên"
                                   style="color: var(--success); border-color: var(--success); margin-right: 8px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                                <a href="name_requests.php?reject=<?php echo $user['id']; ?>" 
                                   class="action-btn delete" 
                                   title="Từ chối yêu cầu"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;"
                                   onclick="return confirm('Bạn có chắc chắn muốn từ chối yêu cầu thay đổi tên này?');">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

