<?php
/**
 * Wiki Moods Moderation Panel - Frest App Admin
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit;
}

$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

try {
    $db = getDB();

    // 1. Xử lý xóa tâm trạng
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        
        $del_stmt = $db->prepare("DELETE FROM wiki_moods WHERE id = ?");
        if ($del_stmt->execute([$delete_id])) {
            $_SESSION['success_msg'] = "Đã xóa thành công tâm trạng #{$delete_id} khỏi hệ thống.";
        } else {
            $_SESSION['error_msg'] = "Không thể xóa tâm trạng này.";
        }
        
        header("Location: wiki_moods.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // 2. Tải danh sách Wiki Moods kèm tìm kiếm
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT wm.*, u.username, u.full_name, u.avatar_filename, u.verification_type
            FROM wiki_moods wm
            JOIN users u ON wm.user_id = u.id
            WHERE u.username LIKE ? 
               OR u.full_name LIKE ? 
               OR wm.content LIKE ?
            ORDER BY wm.created_at DESC
        ");
        $like_search = "%{$search}%";
        $stmt->execute([$like_search, $like_search, $like_search]);
    } else {
        $stmt = $db->prepare("
            SELECT wm.*, u.username, u.full_name, u.avatar_filename, u.verification_type
            FROM wiki_moods wm
            JOIN users u ON wm.user_id = u.id
            ORDER BY wm.created_at DESC
        ");
        $stmt->execute();
    }
    
    $moods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi truy vấn dữ liệu tâm trạng Wiki: " . $e->getMessage());
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý Wiki Moods</h1>
    <div style="font-size: 14px; color: var(--admin-text-secondary);">
        Kiểm duyệt các thẻ tâm trạng lơ lửng trên toàn hệ thống.
    </div>
</div>

<!-- Trạng thái báo lỗi / Thành công -->
<?php if (!empty($success_msg)): ?>
    <div style="padding: 14px 20px; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success, #10b981); border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600;">
        <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div style="padding: 14px 20px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger, #ef4444); border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600;">
        <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<!-- Thanh tìm kiếm & bộ lọc -->
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
    <form method="GET" action="wiki_moods.php" style="display: flex; gap: 10px; width: 100%; max-width: 400px;">
        <input type="text" name="search" class="admin-search-input" placeholder="Tìm tên người dùng, nội dung..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
        <button type="submit" class="btn-primary" style="padding: 0 20px; height: 42px; border-radius: 10px; font-weight: 700; border: none; color: #fff; background: var(--admin-accent); cursor: pointer;">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        <?php if (!empty($search)): ?>
            <a href="wiki_moods.php" class="action-btn" style="width: 42px !important; height: 42px !important; border-radius: 10px !important; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05);" title="Xóa bộ lọc">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>
    </form>
    
    <div style="font-size: 13.5px; color: var(--admin-text-secondary); font-weight: 600;">
        Tìm thấy: <strong style="color: var(--admin-text-primary);"><?php echo count($moods); ?></strong> tâm trạng.
    </div>
</div>

<!-- Bảng dữ liệu Wiki Moods -->
<div class="data-table-container">
    <div class="data-table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Người đăng</th>
                    <th>Tâm trạng</th>
                    <th style="width: 80px; text-align: center;">Emoji</th>
                    <th style="width: 120px;">Màu nền</th>
                    <th>Ngày đăng</th>
                    <th>Ngày hết hạn</th>
                    <th>Trạng thái</th>
                    <th style="width: 80px; text-align: center;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($moods)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                            Không tìm thấy tâm trạng nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($moods as $m): 
                        // Kiểm tra trạng thái hoạt động (còn hạn hay hết hạn)
                        $is_active = strtotime($m['expires_at']) > time();
                        $avatar_url = SITE_URL . '/uploads/avatars/' . ($m['avatar_filename'] ?: 'avatar_default.png');
                        
                        // Xác định badge tích xanh
                        $verify_badge = '';
                        if ($m['verification_type']) {
                            $badge_class = 'fa-circle-check';
                            $badge_color = 'var(--accent-primary)';
                            if ($m['verification_type'] === 'developer') {
                                $badge_class = 'fa-code';
                                $badge_color = '#d946ef';
                            } else if ($m['verification_type'] === 'official') {
                                $badge_color = '#3b82f6';
                            } else if ($m['verification_type'] === 'business') {
                                $badge_class = 'fa-briefcase';
                                $badge_color = '#10b981';
                            } else if ($m['verification_type'] === 'gov_vietnam' || $m['verification_type'] === 'gov_global') {
                                $badge_class = 'fa-building-shield';
                                $badge_color = '#f59e0b';
                            }
                            $verify_badge = '<i class="fa-solid ' . $badge_class . '" style="color: ' . $badge_color . '; font-size: 11px; margin-left: 4px;" title="Tài khoản xác minh"></i>';
                        }
                    ?>
                        <tr>
                            <td>#<?php echo $m['id']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo $avatar_url; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.08);">
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 700; color: var(--admin-text-primary); display: flex; align-items: center;">
                                            <?php echo sanitize($m['full_name']); ?>
                                            <?php echo $verify_badge; ?>
                                        </span>
                                        <span style="font-size: 11px; color: var(--admin-text-secondary);">@<?php echo sanitize($m['username']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--admin-text-primary); max-width: 260px; line-height: 1.4;">
                                    <?php echo sanitize($m['content']); ?>
                                </div>
                            </td>
                            <td style="text-align: center; font-size: 20px;"><?php echo sanitize($m['emoji']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 18px; height: 18px; border-radius: 50%; background: <?php echo $m['color']; ?>; border: 1px solid rgba(255,255,255,0.2);"></div>
                                    <span style="font-size: 10.5px; font-family: monospace; opacity: 0.8; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($m['color']); ?>">
                                        Gradient
                                    </span>
                                </div>
                            </td>
                            <td style="font-size: 12px; opacity: 0.8;">
                                <?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?>
                            </td>
                            <td style="font-size: 12px; opacity: 0.8;">
                                <?php echo date('d/m/Y H:i', strtotime($m['expires_at'])); ?>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="status-badge status-resolved">
                                        <span style="width: 6px; height: 6px; background: var(--success, #10b981); border-radius: 50%; display: inline-block;"></span>
                                        Hoạt động
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-dismissed">
                                        <span style="width: 6px; height: 6px; background: var(--admin-text-secondary); border-radius: 50%; display: inline-block;"></span>
                                        Hết hạn
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="wiki_moods.php?delete=<?php echo $m['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="action-btn delete" 
                                   title="Xóa tâm trạng này"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn tâm trạng của @<?php echo sanitize($m['username']); ?> khỏi hệ thống?');">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
