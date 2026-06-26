<?php
/**
 * Admin Dashboard Overview - Frest App
 */
require_once __DIR__ . '/header.php';

try {
    $db = getDB();

    // 1. Calculate Statistics Metrics
    $total_posts = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_reactions = $db->query("SELECT COUNT(*) FROM reactions")->fetchColumn();
    $total_replies = $db->query("SELECT COUNT(*) FROM replies")->fetchColumn();

    // 2. Fetch Recent Registered Users
    $recent_users_stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $recent_users_stmt->fetchAll();

    // 3. Fetch Recent Frests / Posts
    $recent_posts_stmt = $db->query("SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 5");
    $recent_posts = $recent_posts_stmt->fetchAll();

} catch (PDOException $e) {
    die("Lỗi truy vấn dữ liệu thống kê quản trị: " . $e->getMessage());
}
?>

<div class="admin-header">
    <h1 class="admin-title">Bảng điều khiển</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Hệ thống mạng xã hội hoạt động ổn định <span style="display:inline-block; width:8px; height:8px; background:var(--success); border-radius:50%; margin-left:4px;"></span>
    </div>
</div>

<!-- Statistics Cards Grid -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Tổng số bài viết</div>
        <div class="stat-value"><?php echo number_format($total_posts); ?></div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;"><i class="fa-solid fa-hashtag"></i> Frests đã đăng</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Tổng người dùng</div>
        <div class="stat-value"><?php echo number_format($total_users); ?></div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;"><i class="fa-solid fa-users"></i> Tài khoản đăng ký</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Tổng lượt tương tác</div>
        <div class="stat-value"><?php echo number_format($total_reactions); ?></div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;"><i class="fa-solid fa-face-smile"></i> Thả cảm xúc / Thích</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Tổng phản hồi</div>
        <div class="stat-value" style="color: var(--accent-primary); -webkit-text-fill-color: var(--accent-primary);"><?php echo number_format($total_replies); ?></div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;"><i class="fa-solid fa-comments"></i> Lượt bình luận</div>
    </div>
</div>

<div class="checkout-grid" style="grid-template-columns: 1.2fr 1fr; gap: 30px;">
    <!-- Recent Posts Box -->
    <div class="data-table-container" style="margin-bottom: 0;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Bài viết gần đây</h3>
            <a href="posts.php" style="font-size: 12px; color: var(--accent-primary); font-weight: 600;">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        
        <?php if (empty($recent_posts)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">Chưa có bài đăng nào.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tác giả</th>
                        <th>Nội dung</th>
                        <th>Ngày đăng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_posts as $post): ?>
                        <tr>
                            <td style="font-weight: 700; font-size: 13px;">@<?php echo sanitize($post['username']); ?></td>
                            <td>
                                <div style="font-size: 13px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo sanitize($post['content']); ?>
                                </div>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Registered Users Box -->
    <div class="data-table-container" style="margin-bottom: 0;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700;">Thành viên mới</h3>
            <a href="users.php" style="font-size: 12px; color: var(--accent-primary); font-weight: 600;">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        
        <?php if (empty($recent_users)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">Chưa có thành viên nào.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Avatar</th>
                        <th>Tên tài khoản</th>
                        <th>Ngày tham gia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>
                                <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $user['avatar_filename']; ?>" 
                                     style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%; border: 1px solid var(--border-color);">
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;">@<?php echo sanitize($user['username']); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($user['email']); ?></div>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

