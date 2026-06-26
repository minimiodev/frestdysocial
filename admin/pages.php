<?php
/**
 * Page Moderation - Frest App Admin
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

    // Handle verification update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_page_badge'])) {
        $page_id = intval($_POST['page_id'] ?? 0);
        $v_type = trim($_POST['verification_type'] ?? '');
        if ($v_type === 'none' || empty($v_type)) {
            $v_type = null;
            $is_verified = 0;
        } else {
            $is_verified = 1;
        }
        
        $update_stmt = $db->prepare("UPDATE pages SET verification_type = ?, is_verified = ? WHERE id = ?");
        $update_stmt->execute([$v_type, $is_verified, $page_id]);
        $_SESSION['success_msg'] = "Đã cập nhật loại tích xác minh thành công cho Trang #{$page_id}.";
        header("Location: pages.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle delete page
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        
        // Find avatar and cover files to clean up
        $stmt_files = $db->prepare("SELECT avatar_filename, cover_filename FROM pages WHERE id = ?");
        $stmt_files->execute([$delete_id]);
        $page_files = $stmt_files->fetch();
        
        if ($page_files) {
            $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                if (!empty($page_files['avatar_filename']) && $page_files['avatar_filename'] !== 'avatar_default.png') {
                    @unlink(UPLOAD_DIR . 'avatars/' . $page_files['avatar_filename']);
                }
                if (!empty($page_files['cover_filename'])) {
                    @unlink(UPLOAD_DIR . 'avatars/' . $page_files['cover_filename']);
                }
                $_SESSION['success_msg'] = "Đã xóa hoàn toàn Trang #{$delete_id} và mọi dữ liệu liên quan.";
            } else {
                $_SESSION['error_msg'] = "Không thể xóa Trang này.";
            }
        } else {
            $_SESSION['error_msg'] = "Trang không tồn tại.";
        }
        header("Location: pages.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Load pages list
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT p.*, u.username AS owner_username 
                              FROM pages p 
                              JOIN users u ON p.owner_id = u.id 
                              WHERE p.page_name LIKE ? OR p.page_username LIKE ? OR u.username LIKE ?
                              ORDER BY p.created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
        $pages = $stmt->fetchAll();
    } else {
        $pages = $db->query("SELECT p.*, u.username AS owner_username 
                              FROM pages p 
                              JOIN users u ON p.owner_id = u.id 
                              ORDER BY p.created_at DESC")->fetchAll();
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý Trang (Pages)</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Kiểm duyệt các Trang cộng đồng, doanh nghiệp và cấp tích xanh xác thực chính chủ cho Trang
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
        <input type="text" name="search" class="form-input" placeholder="Tìm kiếm theo tên Trang, handle Trang hoặc chủ sở hữu..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; border-radius: var(--radius-sm);">
        <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px;">Tìm kiếm</button>
    </form>
</div>

<!-- Pages Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách các Trang</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($pages); ?> Trang</span>
    </div>

    <?php if (empty($pages)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không tìm thấy Trang nào phù hợp.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Avatar</th>
                        <th>Tên Trang</th>
                        <th>Handle Trang</th>
                        <th>Chủ sở hữu</th>
                        <th>Danh mục</th>
                        <th>Trạng thái xác minh</th>
                        <th>Ngày tạo</th>
                        <th style="width: 100px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $p): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $p['id']; ?></td>
                            <td>
                                <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $p['avatar_filename']; ?>" style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 1px solid var(--border-color);">
                            </td>
                            <td style="font-weight: 700;">
                                <span style="display: inline-flex; align-items: center; gap: 4px;">
                                    <?php echo sanitize($p['page_name']); ?>
                                    <?php 
                                    if (intval($p['is_verified'] ?? 0) === 1) {
                                        echo getPageVerificationBadgeHTML($p['id'], false);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; font-weight: 600; color: var(--text-muted);">@<?php echo sanitize($p['page_username']); ?></td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>/profile.php?username=<?php echo sanitize($p['owner_username']); ?>" target="_blank" style="font-weight: 600; color: var(--accent-primary); text-decoration: none;">
                                    @<?php echo sanitize($p['owner_username']); ?>
                                </a>
                            </td>
                            <td>
                                <span style="font-size: 12px; font-weight: 600; color: var(--text-primary); background: rgba(59, 130, 246, 0.08); padding: 4px 8px; border-radius: 4px;">
                                    <?php echo sanitize($p['category']); ?>
                                </span>
                            </td>
                            <td>
                                 <form action="" method="POST" style="display: inline-flex; align-items: center; margin-bottom: 0;">
                                     <input type="hidden" name="action_update_page_badge" value="1">
                                     <input type="hidden" name="page_id" value="<?php echo $p['id']; ?>">
                                     <select name="verification_type" onchange="this.form.submit();" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 5px 8px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer;">
                                         <option value="none" <?php echo empty($p['verification_type']) || $p['verification_type'] === 'none' ? 'selected' : ''; ?>>Không tích</option>
                                         <option value="official" <?php echo $p['verification_type'] === 'official' ? 'selected' : ''; ?>>Huy hiệu đã xác minh (Xanh dương)</option>
                                         <option value="subscribed" <?php echo $p['verification_type'] === 'subscribed' ? 'selected' : ''; ?>>Frest đã xác minh (Trả phí - Xanh dương)</option>
                                     </select>
                                 </form>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <a href="pages.php?delete=<?php echo $p['id']; ?>" 
                                   class="action-btn delete" 
                                   title="Xóa Trang"
                                   data-confirm="CẢNH BÁO: Bạn chắc chắn muốn xóa Trang này? Mọi bài đăng và bình luận dưới danh nghĩa Trang này sẽ bị xóa khỏi hệ thống!">
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

<?php require_once __DIR__ . '/footer.php'; ?>

