<?php
/**
 * Moderation Dashboard for User Posts - Frest App Admin
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

    // Handle copyright update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_copyright'])) {
        $p_id = intval($_POST['post_id'] ?? 0);
        $is_violation = isset($_POST['is_copyright_violation']) ? 1 : 0;
        $owner = sanitize($_POST['copyright_owner'] ?? '');
        $details = sanitize($_POST['copyright_details'] ?? '');
        
        $update_stmt = $db->prepare("UPDATE posts SET is_copyright_violation = ?, copyright_owner = ?, copyright_details = ? WHERE id = ?");
        $update_stmt->execute([$is_violation, $owner, $details, $p_id]);
        $_SESSION['success_msg'] = "Cập nhật thông tin bản quyền thành công cho bài viết #{$p_id}.";
        header("Location: posts.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle toggle NSFW
    if (isset($_GET['toggle_nsfw'])) {
        $nsfw_id = intval($_GET['toggle_nsfw']);
        
        // Get current status
        $check_stmt = $db->prepare("SELECT is_nsfw FROM posts WHERE id = ?");
        $check_stmt->execute([$nsfw_id]);
        $current_nsfw = $check_stmt->fetchColumn();
        
        if ($current_nsfw !== false) {
            $new_nsfw = intval($current_nsfw) === 1 ? 0 : 1;
            $update_stmt = $db->prepare("UPDATE posts SET is_nsfw = ? WHERE id = ?");
            if ($update_stmt->execute([$new_nsfw, $nsfw_id])) {
                $status_str = $new_nsfw === 1 ? 'nhạy cảm (NSFW)' : 'bình thường (SFW)';
                $_SESSION['success_msg'] = "Đã cập nhật trạng thái bài viết #{$nsfw_id} thành {$status_str}.";
            } else {
                $_SESSION['error_msg'] = "Không thể cập nhật trạng thái bài viết.";
            }
        }
        header("Location: posts.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Handle delete post
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        
        // Find if post has media files first to clean up
        $stmt_media = $db->prepare("SELECT image_filename, video_filename, audio_filename, document_filename, software_filename FROM posts WHERE id = ?");
        $stmt_media->execute([$delete_id]);
        $post_media = $stmt_media->fetch();

        $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            if ($post_media) {
                if (!empty($post_media['image_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $post_media['image_filename']);
                }
                if (!empty($post_media['video_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $post_media['video_filename']);
                }
                if (!empty($post_media['audio_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $post_media['audio_filename']);
                }
                if (!empty($post_media['document_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $post_media['document_filename']);
                }
                if (!empty($post_media['software_filename'])) {
                    @unlink(UPLOAD_DIR . 'posts/' . $post_media['software_filename']);
                }
            }
            $_SESSION['success_msg'] = "Đã xóa thành công bài viết #{$delete_id} và các tài nguyên liên quan.";
        } else {
            $_SESSION['error_msg'] = "Không thể xóa bài viết này.";
        }
        header("Location: posts.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    // Load posts
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT p.*, u.username 
                              FROM posts p 
                              JOIN users u ON p.user_id = u.id 
                              WHERE p.content LIKE ? OR u.username LIKE ? 
                              ORDER BY p.created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        $posts = $stmt->fetchAll();
    } else {
        $posts = $db->query("SELECT p.*, u.username 
                              FROM posts p 
                              JOIN users u ON p.user_id = u.id 
                              ORDER BY p.created_at DESC")->fetchAll();
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý bài viết</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Kiểm duyệt các bài viết (Frests) do người dùng đăng tải lên mạng xã hội
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

<!-- Edit Copyright Form -->
<?php 
if (isset($_GET['edit_copyright'])): 
    $edit_id = intval($_GET['edit_copyright']);
    $stmt = $db->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$edit_id]);
    $edit_post = $stmt->fetch();
    if ($edit_post):
?>
<div class="checkout-card" style="padding: 24px; margin-bottom: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); max-width: 600px; margin-left: auto; margin-right: auto; animation: scaleUp 0.25s ease;">
    <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; color: var(--text-primary);">Cập nhật bản quyền bài viết #<?php echo $edit_id; ?></h3>
    <div style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 16px; line-height: 1.6;">
        <strong>Tác giả:</strong> @<?php echo sanitize($edit_post['username']); ?><br>
        <strong>Nội dung:</strong> <?php echo nl2br(sanitize($edit_post['content'])); ?>
    </div>
    
    <form action="posts.php" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
        <input type="hidden" name="action_update_copyright" value="1">
        <input type="hidden" name="post_id" value="<?php echo $edit_id; ?>">
        
        <label style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); user-select: none; cursor: pointer; width: 100%; box-sizing: border-box;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger); font-size: 16px;"></i>
                <div>
                    <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);">Đánh dấu vi phạm bản quyền</div>
                    <div style="font-size: 11px; color: var(--text-muted);">Ẩn trình phát và hiển thị thông báo bản quyền như Youtube</div>
                </div>
            </div>
            <div class="switch-container" style="position: relative; display: inline-block; width: 44px; height: 24px; margin-bottom: 0; flex-shrink: 0;">
                <input type="checkbox" name="is_copyright_violation" value="1" <?php echo intval($edit_post['is_copyright_violation']) === 1 ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                <span class="switch-slider"></span>
            </div>
        </label>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase;">Chủ sở hữu bản quyền</label>
            <input type="text" name="copyright_owner" class="form-input" placeholder="Tên tổ chức hoặc cá nhân khiếu nại bản quyền..." value="<?php echo htmlspecialchars($edit_post['copyright_owner'] ?? ''); ?>" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase;">Chi tiết vi phạm</label>
            <textarea name="copyright_details" class="form-input" placeholder="Thông tin bổ sung chi tiết về vi phạm..." style="height: 100px; padding: 10px; resize: none; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);"><?php echo htmlspecialchars($edit_post['copyright_details'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 12px; margin-top: 10px;">
            <button type="submit" class="btn-primary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full);">Lưu thông tin</button>
            <a href="posts.php" class="btn-secondary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full); text-align: center; display: inline-flex; align-items: center; justify-content: center;">Hủy bỏ</a>
        </div>
    </form>
</div>
<?php endif; endif; ?>

<!-- Search Bar -->
<div class="checkout-card" style="padding: 24px; margin-bottom: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
    <form action="" method="GET" style="display: flex; gap: 12px; margin-bottom: 0;">
        <input type="text" name="search" class="form-input" placeholder="Tìm kiếm nội dung bài viết hoặc tên người dùng..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; border-radius: var(--radius-sm);">
        <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px;">Tìm kiếm</button>
    </form>
</div>

<!-- Posts Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách bài viết</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($posts); ?> Frests</span>
    </div>

    <?php if (empty($posts)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-hashtag" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không tìm thấy bài viết nào phù hợp.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Tác giả</th>
                        <th>Nội dung</th>
                        <th>Phương tiện</th>
                        <th>Ngày đăng</th>
                        <th style="width: 100px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $post['id']; ?></td>
                            <td style="font-weight: 600;">@<?php echo sanitize($post['username']); ?></td>
                            <td>
                                <div style="font-size: 13.5px; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: normal; line-height: 1.5;">
                                    <?php echo sanitize($post['content']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($post['video_filename'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 4px; color: var(--accent-primary);">
                                            <i class="fa-solid fa-video" style="font-size: 14px;"></i>
                                            <span style="font-size: 11px; font-weight: 700;">VIDEO</span>
                                        </div>
                                        <span style="font-size: 11px; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo $post['video_filename']; ?></span>
                                    </div>
                                <?php elseif (!empty($post['image_filename'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/uploads/posts/<?php echo $post['image_filename']; ?>" target="_blank">
                                        <img src="<?php echo SITE_URL; ?>/uploads/posts/<?php echo $post['image_filename']; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                    </a>
                                <?php elseif (!empty($post['audio_filename'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 4px; color: #10b981;">
                                            <i class="fa-solid fa-music" style="font-size: 14px;"></i>
                                            <span style="font-size: 11px; font-weight: 700;">AUDIO</span>
                                        </div>
                                        <span style="font-size: 11px; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo $post['audio_filename']; ?></span>
                                    </div>
                                <?php elseif (!empty($post['document_filename'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 4px; color: #3b82f6;">
                                            <i class="fa-regular fa-file-pdf" style="font-size: 14px;"></i>
                                            <span style="font-size: 11px; font-weight: 700;">TÀI LIỆU</span>
                                        </div>
                                        <span style="font-size: 11px; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo $post['document_filename']; ?></span>
                                    </div>
                                <?php elseif (!empty($post['software_filename'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 4px; color: #a855f7;">
                                            <i class="fa-solid fa-cubes" style="font-size: 14px;"></i>
                                            <span style="font-size: 11px; font-weight: 700;">PHẦN MỀM</span>
                                        </div>
                                        <span style="font-size: 11px; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo $post['software_filename']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--text-muted);">Không có</span>
                                <?php endif; ?>
                                
                                <?php if (intval($post['is_copyright_violation'] ?? 0) === 1): ?>
                                    <div style="margin-top: 4px;">
                                        <span style="background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); padding: 2px 6px; border-radius: var(--radius-sm); font-size: 9.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; width: fit-content;">
                                            <i class="fa-solid fa-copyright"></i> Khiếu nại
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (intval($post['is_nsfw'] ?? 0) === 1): ?>
                                    <div style="margin-top: 6px;">
                                        <span style="background: rgba(235,94,40,0.15); color: var(--accent-primary); border: 1px solid rgba(235,94,40,0.3); padding: 2px 6px; border-radius: var(--radius-sm); font-size: 9.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; width: fit-content;">
                                            <i class="fa-solid fa-eye-slash"></i> NSFW
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="posts.php?toggle_nsfw=<?php echo $post['id']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                                   class="action-btn" 
                                   title="<?php echo intval($post['is_nsfw'] ?? 0) === 1 ? 'Đánh dấu bình thường (SFW)' : 'Đánh dấu nhạy cảm (NSFW)'; ?>"
                                   style="color: <?php echo intval($post['is_nsfw'] ?? 0) === 1 ? 'var(--accent-primary)' : 'var(--text-secondary)'; ?>; border-color: <?php echo intval($post['is_nsfw'] ?? 0) === 1 ? 'var(--accent-primary)' : 'var(--border-color)'; ?>; margin-right: 8px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                    <i class="fa-solid <?php echo intval($post['is_nsfw'] ?? 0) === 1 ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </a>
                                <?php if (!empty($post['image_filename']) || !empty($post['video_filename']) || !empty($post['audio_filename']) || !empty($post['document_filename']) || !empty($post['software_filename'])): ?>
                                    <a href="posts.php?edit_copyright=<?php echo $post['id']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                                       class="action-btn" 
                                       title="Quản lý bản quyền"
                                       style="color: var(--accent-primary); border-color: var(--accent-primary); margin-right: 8px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                        <i class="fa-solid fa-copyright"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="posts.php?delete=<?php echo $post['id']; ?>" 
                                   class="action-btn delete" 
                                   title="Xóa bài viết"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;"
                                   data-confirm="Bạn chắc chắn muốn xóa bài viết này và mọi lượt tương tác, phản hồi liên quan?">
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

