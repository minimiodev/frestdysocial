<?php
/**
 * Copyright Complaints Moderation - Frest App Admin
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

$complaints = [];

try {
    $db = getDB();

    // Auto-create copyright_complaints table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS copyright_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_name VARCHAR(100) NOT NULL,
        reporter_email VARCHAR(100) NOT NULL,
        reporter_phone VARCHAR(30) DEFAULT NULL,
        post_url VARCHAR(2048) NOT NULL,
        description TEXT NOT NULL,
        evidence_filename VARCHAR(255) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 1. Process Take Down action (Approve & Hide post)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_takedown'])) {
        $complaint_id = intval($_POST['complaint_id'] ?? 0);
        $post_id = intval($_POST['post_id'] ?? 0);
        $copyright_owner = trim($_POST['copyright_owner'] ?? '');
        $copyright_details = trim($_POST['copyright_details'] ?? '');

        if (empty($copyright_owner)) {
            $_SESSION['error_msg'] = "Vui lòng nhập tên chủ sở hữu bản quyền.";
        } else {
            // Verify post exists
            $stmt_post = $db->prepare("SELECT COUNT(*) FROM posts WHERE id = ?");
            $stmt_post->execute([$post_id]);
            
            if ($stmt_post->fetchColumn() == 0) {
                $_SESSION['error_msg'] = "Bài viết với ID #{$post_id} không tồn tại trong hệ thống.";
            } else {
                $db->beginTransaction();
                try {
                    // Update post
                    $stmt_up_post = $db->prepare("UPDATE posts SET is_copyright_violation = 1, copyright_owner = ?, copyright_details = ? WHERE id = ?");
                    $stmt_up_post->execute([$copyright_owner, $copyright_details ?: null, $post_id]);

                    // Update complaint status
                    $stmt_up_comp = $db->prepare("UPDATE copyright_complaints SET status = 'resolved' WHERE id = ?");
                    $stmt_up_comp->execute([$complaint_id]);

                    $db->commit();
                    $_SESSION['success_msg'] = "Đã phê duyệt khiếu nại. Bài viết #{$post_id} đã bị gỡ và ẩn phương tiện đính kèm do vi phạm bản quyền.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error_msg'] = "Lỗi xử lý gỡ bài viết: " . $e->getMessage();
                }
            }
        }
        header("Location: copyright_complaints.php");
        exit;
    }

    // 2. Process Reject action
    if (isset($_GET['reject'])) {
        $reject_id = intval($_GET['reject']);
        
        $stmt = $db->prepare("UPDATE copyright_complaints SET status = 'rejected' WHERE id = ?");
        if ($stmt->execute([$reject_id])) {
            $_SESSION['success_msg'] = "Đã từ chối khiếu nại bản quyền #{$reject_id}.";
        } else {
            $_SESSION['error_msg'] = "Không thể từ chối khiếu nại này.";
        }
        header("Location: copyright_complaints.php");
        exit;
    }

    // Load complaints list
    $complaints = $db->query("SELECT * FROM copyright_complaints ORDER BY created_at DESC")->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối hoặc truy vấn CSDL: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1 class="admin-title">Quản lý Khiếu nại Bản quyền</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Xem xét các thông tin khiếu nại sở hữu trí tuệ từ người dùng và xử lý gỡ bài viết vi phạm
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

<!-- Complaints Table -->
<div class="data-table-container" style="margin-bottom: 40px;">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Danh sách yêu cầu khiếu nại</h3>
        <span class="badge" style="background: var(--accent-gradient); color: #fff; padding: 4px 10px; border-radius: var(--radius-full); font-size: 12px;"><?php echo count($complaints); ?> khiếu nại</span>
    </div>

    <?php if (empty($complaints)): ?>
        <div style="padding: 60px; text-align: center; color: var(--text-secondary);">
            <i class="fa-solid fa-copyright" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
            <p>Không có khiếu nại bản quyền nào được ghi nhận.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">ID</th>
                        <th>Người khiếu nại</th>
                        <th>Bài viết vi phạm (URL)</th>
                        <th>Mô tả bằng chứng</th>
                        <th>Ngày gửi</th>
                        <th style="width: 100px; text-align: center;">Trạng thái</th>
                        <th style="width: 120px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $comp): 
                        // Try to auto-extract post ID from URL
                        $parsed_url = parse_url($comp['post_url']);
                        $extracted_post_id = 0;
                        if (isset($parsed_url['query'])) {
                            parse_str($parsed_url['query'], $query_params);
                            $extracted_post_id = intval($query_params['id'] ?? 0);
                        }
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">#<?php echo $comp['id']; ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary);"><?php echo sanitize($comp['reporter_name']); ?></div>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                    <i class="fa-solid fa-envelope" style="margin-right: 4px;"></i> <?php echo sanitize($comp['reporter_email']); ?>
                                </div>
                                <?php if (!empty($comp['reporter_phone'])): ?>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">
                                        <i class="fa-solid fa-phone" style="margin-right: 4px;"></i> <?php echo sanitize($comp['reporter_phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo sanitize($comp['post_url']); ?>" target="_blank" style="color: var(--accent-primary); font-weight: 600; font-size: 13px; text-decoration: none; word-break: break-all;">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px; margin-right: 4px;"></i>
                                    Xem bài viết vi phạm
                                </a>
                                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 4px;">
                                    ID trích xuất: <strong>#<?php echo $extracted_post_id ?: 'Không rõ'; ?></strong>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; line-height: 1.5; max-width: 250px; max-height: 80px; overflow-y: auto;" title="<?php echo htmlspecialchars($comp['description']); ?>">
                                    <?php echo nl2br(sanitize($comp['description'])); ?>
                                </div>
                                <?php if (!empty($comp['evidence_filename'])): ?>
                                    <div style="margin-top: 6px;">
                                        <a href="<?php echo SITE_URL . '/uploads/complaints/' . sanitize($comp['evidence_filename']); ?>" target="_blank" class="badge" style="background: rgba(59, 130, 246, 0.12); color: var(--accent-primary); padding: 2px 6px; border-radius: 4px; font-size: 11px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-file-shield"></i> Xem minh chứng
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($comp['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <?php if ($comp['status'] === 'pending'): ?>
                                    <span class="badge" style="background: rgba(235, 94, 40, 0.12); color: var(--accent-primary); padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;">Chờ xử lý</span>
                                <?php elseif ($comp['status'] === 'resolved'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: var(--success); padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;">Đã gỡ bài</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255, 255, 255, 0.06); color: var(--text-muted); padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;">Bác bỏ</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <?php if ($comp['status'] === 'pending'): ?>
                                    <!-- Open Takedown Form Button -->
                                    <button class="action-btn" 
                                            title="Phê duyệt & Gỡ bài"
                                            onclick="openTakedownModal(<?php echo $comp['id']; ?>, <?php echo $extracted_post_id; ?>, '<?php echo sanitize(addslashes($comp['reporter_name'])); ?>')"
                                            style="color: var(--success); border-color: var(--success); margin-right: 8px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: none; cursor: pointer;">
                                        <i class="fa-solid fa-gavel"></i>
                                    </button>
                                    <a href="copyright_complaints.php?reject=<?php echo $comp['id']; ?>" 
                                       class="action-btn delete" 
                                       title="Bác bỏ khiếu nại"
                                       style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;"
                                       data-confirm="Bạn có chắc chắn muốn bác bỏ khiếu nại này?">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">Đã xử lý</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Takedown Modal Form overlay -->
<div id="takedown-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div class="checkout-card" style="max-width: 480px; width: 90%; padding: 28px; position: relative; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <button onclick="closeTakedownModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">&times;</button>
        
        <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 8px; color: var(--text-primary); text-align: center;">
            <i class="fa-solid fa-gavel" style="color: var(--accent-primary); margin-right: 6px;"></i> Phê duyệt gỡ bài viết
        </h3>
        <p style="font-size: 12.5px; color: var(--text-secondary); text-align: center; margin-bottom: 20px;">
            Hãy xác nhận thông tin gỡ bài viết vi phạm bản quyền này.
        </p>

        <form action="" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action_takedown" value="1">
            <input type="hidden" name="complaint_id" id="takedown-complaint-id">

            <div class="form-group" style="margin-bottom: 0;">
                <label for="takedown-post-id" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">ID bài viết cần gỡ *</label>
                <input type="number" name="post_id" id="takedown-post-id" class="form-input" required placeholder="Nhập ID bài viết...">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="takedown-owner" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Chủ sở hữu bản quyền *</label>
                <input type="text" name="copyright_owner" id="takedown-owner" class="form-input" required placeholder="Tên chủ sở hữu hoặc người khiếu nại...">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="takedown-details" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Chi tiết vi phạm (Hiển thị công khai)</label>
                <textarea name="copyright_details" id="takedown-details" class="form-input" style="height: 80px; resize: vertical;" placeholder="Nhập lý do/chi tiết bản quyền (ví dụ: Vi phạm bản quyền hình ảnh, âm nhạc)..."></textarea>
            </div>

            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px;">
                <i class="fa-solid fa-ban"></i> Thực thi gỡ bài viết
            </button>
        </form>
    </div>
</div>

<script>
function openTakedownModal(complaintId, postId, reporterName) {
    document.getElementById('takedown-complaint-id').value = complaintId;
    document.getElementById('takedown-post-id').value = postId ? postId : '';
    document.getElementById('takedown-owner').value = reporterName;
    document.getElementById('takedown-modal').style.display = 'flex';
}

function closeTakedownModal() {
    document.getElementById('takedown-modal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
