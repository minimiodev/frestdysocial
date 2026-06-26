<?php
/**
 * Report Moderation Panel - Frest App Admin
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

    // Handle report actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $report_id = intval($_POST['report_id'] ?? 0);
        $action = trim($_POST['action']);
        
        // Fetch report detail
        $rep_stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
        $rep_stmt->execute([$report_id]);
        $report = $rep_stmt->fetch();
        
        if (!$report) {
            $_SESSION['error_msg'] = "Không tìm thấy báo cáo yêu cầu.";
            header("Location: reports.php");
            exit;
        }

        $target_type = $report['target_type'];
        $target_id = intval($report['target_id']);

        if ($action === 'dismiss') {
            // Dismiss report
            $stmt = $db->prepare("UPDATE reports SET status = 'dismissed', resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$report_id]);
            $_SESSION['success_msg'] = "Đã bỏ qua báo cáo vi phạm #{$report_id}.";
        } 
        elseif ($action === 'delete_content') {
            // Delete Content (Post or Reply)
            if ($target_type === 'post') {
                // Fetch post file details
                $post_stmt = $db->prepare("SELECT image_filename, video_filename FROM posts WHERE id = ?");
                $post_stmt->execute([$target_id]);
                $post = $post_stmt->fetch();
                if ($post) {
                    if (!empty($post['image_filename'])) {
                        @unlink(UPLOAD_DIR . 'posts/' . $post['image_filename']);
                    }
                    if (!empty($post['video_filename'])) {
                        @unlink(UPLOAD_DIR . 'posts/' . $post['video_filename']);
                    }
                    $del_stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
                    $del_stmt->execute([$target_id]);
                }
            } elseif ($target_type === 'reply') {
                $del_stmt = $db->prepare("DELETE FROM replies WHERE id = ?");
                $del_stmt->execute([$target_id]);
            }

            // Mark all pending reports for this content as resolved
            $stmt = $db->prepare("UPDATE reports SET status = 'resolved', resolved_at = NOW() WHERE target_type = ? AND target_id = ? AND status = 'pending'");
            $stmt->execute([$target_type, $target_id]);
            
            $_SESSION['success_msg'] = "Đã xóa nội dung vi phạm thành công và giải quyết các báo cáo liên quan.";
        } 
        elseif ($action === 'ban_user') {
            // Ban the author or the user target
            $user_id_to_ban = 0;

            if ($target_type === 'user') {
                $user_id_to_ban = $target_id;
            } elseif ($target_type === 'page') {
                $page_stmt = $db->prepare("SELECT owner_id FROM pages WHERE id = ?");
                $page_stmt->execute([$target_id]);
                $user_id_to_ban = intval($page_stmt->fetchColumn());
            } elseif ($target_type === 'post') {
                $post_stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
                $post_stmt->execute([$target_id]);
                $user_id_to_ban = intval($post_stmt->fetchColumn());
            } elseif ($target_type === 'reply') {
                $reply_stmt = $db->prepare("SELECT user_id FROM replies WHERE id = ?");
                $reply_stmt->execute([$target_id]);
                $user_id_to_ban = intval($reply_stmt->fetchColumn());
            }

            if ($user_id_to_ban > 0) {
                // Update user status to banned
                $ban_stmt = $db->prepare("UPDATE users SET status = 'banned', status_reason = ? WHERE id = ?");
                $ban_stmt->execute(['Vi phạm chính sách cộng đồng nghiêm trọng (bị báo cáo)', $user_id_to_ban]);
                
                // Mark reports related to this target user/page or their posts/replies as resolved
                // For simplicity, resolve all reports targeting this user/page, and current report target
                $stmt1 = $db->prepare("UPDATE reports SET status = 'resolved', resolved_at = NOW() WHERE target_type = ? AND target_id = ? AND status = 'pending'");
                $stmt1->execute([$target_type, $target_id]);
                
                // Also resolve general pending reports for this user
                $stmt2 = $db->prepare("UPDATE reports SET status = 'resolved', resolved_at = NOW() WHERE target_type = 'user' AND target_id = ? AND status = 'pending'");
                $stmt2->execute([$user_id_to_ban]);

                $_SESSION['success_msg'] = "Đã khóa tài khoản thành viên #{$user_id_to_ban} thành công.";
            } else {
                $_SESSION['error_msg'] = "Không thể tìm thấy tài khoản người dùng để khóa.";
            }
        }

        header("Location: reports.php" . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    }

    // Filter values
    $filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
    $filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';

    $query_str = "
        SELECT r.*,
               -- Reporter identity
               (CASE 
                    WHEN r.reporter_type = 'user' THEN u1.username
                    WHEN r.reporter_type = 'page' THEN pg1.page_username
                END) AS reporter_username,
               (CASE 
                    WHEN r.reporter_type = 'user' THEN u1.full_name
                    WHEN r.reporter_type = 'page' THEN pg1.page_name
                END) AS reporter_display_name,
                
               -- Target user / page identity
               (CASE 
                    WHEN r.target_type = 'user' THEN u2.username
                    WHEN r.target_type = 'page' THEN pg2.page_username
                END) AS target_username,
               (CASE 
                    WHEN r.target_type = 'user' THEN u2.full_name
                    WHEN r.target_type = 'page' THEN pg2.page_name
                END) AS target_display_name,
                
               -- Target post details
               p.content AS post_content,
               p.user_id AS post_author_id,
               up.username AS post_author_username,
               up.full_name AS post_author_display_name,
               
               -- Target reply details
               rp.content AS reply_content,
               rp.user_id AS reply_author_id,
               ur.username AS reply_author_username,
               ur.full_name AS reply_author_display_name
               
        FROM reports r
        LEFT JOIN users u1 ON r.reporter_type = 'user' AND r.reporter_id = u1.id
        LEFT JOIN pages pg1 ON r.reporter_type = 'page' AND r.reporter_id = pg1.id
        
        LEFT JOIN users u2 ON r.target_type = 'user' AND r.target_id = u2.id
        LEFT JOIN pages pg2 ON r.target_type = 'page' AND r.target_id = pg2.id
        
        LEFT JOIN posts p ON r.target_type = 'post' AND r.target_id = p.id
        LEFT JOIN users up ON p.user_id = up.id
        
        LEFT JOIN replies rp ON r.target_type = 'reply' AND r.target_id = rp.id
        LEFT JOIN users ur ON rp.user_id = ur.id
        WHERE 1=1
    ";

    $params = [];
    if (!empty($filter_status)) {
        $query_str .= " AND r.status = ? ";
        $params[] = $filter_status;
    }
    if (!empty($filter_type)) {
        $query_str .= " AND r.target_type = ? ";
        $params[] = $filter_type;
    }

    $query_str .= " ORDER BY r.created_at DESC";

    $stmt = $db->prepare($query_str);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc xử lý CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý Báo cáo vi phạm</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Xem xét các báo cáo từ cộng đồng và thực hiện hành động kiểm duyệt nhanh chóng để bảo vệ hệ thống.
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

<!-- Filter Bar -->
<div class="checkout-card" style="padding: 24px; margin-bottom: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
    <form action="" method="GET" style="display: flex; gap: 12px; margin-bottom: 0; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Trạng thái</label>
            <select name="status" class="form-input" style="padding: 8px 12px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 160px;">
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Đang chờ xử lý</option>
                <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Đã giải quyết</option>
                <option value="dismissed" <?php echo $filter_status === 'dismissed' ? 'selected' : ''; ?>>Đã bỏ qua</option>
                <option value="" <?php echo $filter_status === '' ? 'selected' : ''; ?>>Tất cả</option>
            </select>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Loại nội dung</label>
            <select name="type" class="form-input" style="padding: 8px 12px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 160px;">
                <option value="" <?php echo $filter_type === '' ? 'selected' : ''; ?>>Tất cả</option>
                <option value="post" <?php echo $filter_type === 'post' ? 'selected' : ''; ?>>Bài viết (Post)</option>
                <option value="reply" <?php echo $filter_type === 'reply' ? 'selected' : ''; ?>>Bình luận (Reply)</option>
                <option value="user" <?php echo $filter_type === 'user' ? 'selected' : ''; ?>>Tài khoản cá nhân</option>
                <option value="page" <?php echo $filter_type === 'page' ? 'selected' : ''; ?>>Trang (Page)</option>
            </select>
        </div>

        <div style="margin-top: 18px;">
            <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-sm); font-size: 13px; height: 36px; display: inline-flex; align-items: center;">Lọc báo cáo</button>
        </div>
    </form>
</div>

<!-- Reports Table -->
<div class="data-table-container">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách báo cáo</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($reports); ?> báo cáo</span>
    </div>

    <?php if (empty($reports)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-flag" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không có báo cáo vi phạm nào phù hợp với bộ lọc.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">ID</th>
                        <th>Người báo cáo</th>
                        <th>Loại đối tượng</th>
                        <th>Nội dung / Đối tượng vi phạm</th>
                        <th>Lý do vi phạm</th>
                        <th style="width: 120px;">Trạng thái</th>
                        <th>Ngày gửi</th>
                        <th style="width: 180px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): 
                        $rep_id = $rep['id'];
                        $t_type = $rep['target_type'];
                        $t_id = $rep['target_id'];
                        
                        // Human-readable target type
                        $target_label = '';
                        if ($t_type === 'post') $target_label = '<span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); border: 1px solid rgba(59, 130, 246, 0.2);">Bài viết</span>';
                        elseif ($t_type === 'reply') $target_label = '<span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">Bình luận</span>';
                        elseif ($t_type === 'user') $target_label = '<span class="badge" style="background: rgba(124, 58, 237, 0.1); color: #8b5cf6; border: 1px solid rgba(124, 58, 237, 0.2);">Người dùng</span>';
                        elseif ($t_type === 'page') $target_label = '<span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">Trang</span>';

                        // Human-readable reason
                        $reason_label = '';
                        if ($rep['reason'] === 'spam') $reason_label = 'Spam / Tin rác';
                        elseif ($rep['reason'] === 'nsfw') $reason_label = 'Nội dung 18+';
                        elseif ($rep['reason'] === 'hate_speech') $reason_label = 'Ngôn từ kích động';
                        elseif ($rep['reason'] === 'harassment') $reason_label = 'Quấy rối / Đe dọa';
                        else $reason_label = 'Lý do khác';
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $rep_id; ?></td>
                            <td>
                                <strong>
                                    <?php echo !empty($rep['reporter_display_name']) ? htmlspecialchars($rep['reporter_display_name']) : '@' . htmlspecialchars($rep['reporter_username']); ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-muted);">@<?php echo htmlspecialchars($rep['reporter_username']); ?> (<?php echo htmlspecialchars($rep['reporter_type']); ?>)</div>
                            </td>
                            <td><?php echo $target_label; ?></td>
                            <td style="max-width: 280px; word-break: break-word;">
                                <?php if ($t_type === 'post'): ?>
                                    <div style="font-size: 13px; line-height: 1.4;">
                                        <?php if (!empty($rep['post_content'])): ?>
                                            <em>"<?php echo htmlspecialchars(mb_substr($rep['post_content'], 0, 80)) . (mb_strlen($rep['post_content']) > 80 ? '...' : ''); ?>"</em>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-style:italic;">[Bài viết không có văn bản hoặc đã bị xóa]</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                        Tác giả: <strong><?php echo htmlspecialchars($rep['post_author_display_name'] ?? ''); ?></strong> (@<?php echo htmlspecialchars($rep['post_author_username'] ?? ''); ?>) 
                                        • <a href="<?php echo SITE_URL; ?>/detail.php?id=<?php echo $t_id; ?>" target="_blank" style="color: var(--accent-primary);">Xem bài viết <i class="fa-solid fa-up-right-from-square" style="font-size:9px;"></i></a>
                                    </div>
                                <?php elseif ($t_type === 'reply'): ?>
                                    <div style="font-size: 13px; line-height: 1.4;">
                                        <em>"<?php echo htmlspecialchars(mb_substr($rep['reply_content'] ?? '', 0, 80)) . (mb_strlen($rep['reply_content'] ?? '') > 80 ? '...' : ''); ?>"</em>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                        Tác giả: <strong><?php echo htmlspecialchars($rep['reply_author_display_name'] ?? ''); ?></strong> (@<?php echo htmlspecialchars($rep['reply_author_username'] ?? ''); ?>)
                                        • <a href="<?php echo SITE_URL; ?>/detail.php?id=<?php echo $rep['target_id']; // For replies, check container post if possible or link to detail ?>" target="_blank" style="color: var(--accent-primary);">Xem <i class="fa-solid fa-up-right-from-square" style="font-size:9px;"></i></a>
                                    </div>
                                <?php elseif ($t_type === 'user'): ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($rep['target_display_name'] ?? ''); ?></strong>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        Tên tài khoản: @<?php echo htmlspecialchars($rep['target_username'] ?? ''); ?> 
                                        • <a href="<?php echo SITE_URL; ?>/profile.php?username=<?php echo htmlspecialchars($rep['target_username'] ?? ''); ?>" target="_blank" style="color: var(--accent-primary);">Trang cá nhân <i class="fa-solid fa-up-right-from-square" style="font-size:9px;"></i></a>
                                    </div>
                                <?php elseif ($t_type === 'page'): ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($rep['target_display_name'] ?? ''); ?></strong>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        Định danh Trang: @<?php echo htmlspecialchars($rep['target_username'] ?? ''); ?> 
                                        • <a href="<?php echo SITE_URL; ?>/page.php?username=<?php echo htmlspecialchars($rep['target_username'] ?? ''); ?>" target="_blank" style="color: var(--accent-primary);">Xem Trang <i class="fa-solid fa-up-right-from-square" style="font-size:9px;"></i></a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><strong><?php echo $reason_label; ?></strong></div>
                                <?php if (!empty($rep['details'])): ?>
                                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px; background: rgba(0,0,0,0.08); padding: 4px 8px; border-radius: 4px; max-height: 50px; overflow-y: auto;">
                                        <?php echo htmlspecialchars($rep['details']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rep['status'] === 'pending'): ?>
                                    <span class="status-badge status-pending" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 12px; display: inline-block;">Đang chờ</span>
                                <?php elseif ($rep['status'] === 'resolved'): ?>
                                    <span class="status-badge status-resolved" style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 12px; display: inline-block;">Đã giải quyết</span>
                                <?php else: ?>
                                    <span class="status-badge status-dismissed" style="background: rgba(107, 114, 128, 0.15); color: #6b7280; padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 12px; display: inline-block;">Đã bỏ qua</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-muted);"><?php echo date('H:i d/m/Y', strtotime($rep['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                    <?php if ($rep['status'] === 'pending'): ?>
                                        <form action="" method="POST" style="margin:0; display:inline;">
                                            <input type="hidden" name="report_id" value="<?php echo $rep_id; ?>">
                                            <input type="hidden" name="action" value="dismiss">
                                            <button type="submit" class="btn-primary" style="background: rgba(107, 114, 128, 0.15); border: 1px solid rgba(107,114,128,0.25); color: var(--text-primary); font-size: 11px; padding: 5px 10px; border-radius: 4px; font-weight: 700; width: auto; cursor:pointer;" title="Bỏ qua báo cáo vi phạm">
                                                <i class="fa-solid fa-eye-slash"></i> Bỏ qua
                                            </button>
                                        </form>

                                        <?php if (in_array($t_type, ['post', 'reply'])): ?>
                                            <form action="" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA vĩnh viễn nội dung này khỏi hệ thống?')" style="margin:0; display:inline;">
                                                <input type="hidden" name="report_id" value="<?php echo $rep_id; ?>">
                                                <input type="hidden" name="action" value="delete_content">
                                                <button type="submit" class="btn-primary" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--danger); font-size: 11px; padding: 5px 10px; border-radius: 4px; font-weight: 700; width: auto; cursor:pointer;" title="Xóa bài viết / bình luận vi phạm">
                                                    <i class="fa-regular fa-trash-can"></i> Xóa nội dung
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn KHÓA tài khoản của thành viên vi phạm này?')" style="margin:0; display:inline;">
                                            <input type="hidden" name="report_id" value="<?php echo $rep_id; ?>">
                                            <input type="hidden" name="action" value="ban_user">
                                            <button type="submit" class="btn-primary" style="background: var(--danger); border: none; font-size: 11px; padding: 5px 10px; border-radius: 4px; font-weight: 700; width: auto; cursor:pointer;" title="Khóa tài khoản vi phạm">
                                                <i class="fa-solid fa-user-slash"></i> Khóa tài khoản
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:11.5px; color: var(--text-muted); font-style:italic;">
                                            <?php if (!empty($rep['resolved_at'])) {
                                                echo 'Xử lý lúc: ' . date('H:i d/m/Y', strtotime($rep['resolved_at']));
                                            } else {
                                                echo 'Đã xử lý';
                                            } ?>
                                        </span>
                                    <?php endif; ?>
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
