<?php
/**
 * Activity / Notifications Feed Page - Frest App
 * Real-time via SSE. Reads from the notifications table.
 * Supports dismiss-all and dismiss per-item.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();
$user_id = $me['id'];
$notifications = [];
$error_msg = '';

// Handle AJAX clear-all from legacy button (also handled by dismiss_notification.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_activity') {
    ob_start();
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_dismissed=1 WHERE user_id=?");
        $stmt->execute([$user_id]);
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Mark ALL currently visible notifications as read when user opens this page
try {
    $db = getDB();
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_dismissed=0")
       ->execute([$user_id]);
    // Invalidate the session badge cache so badge resets to 0 immediately
    unset($_SESSION['notif_count_' . $user_id], $_SESSION['notif_count_ts_' . $user_id]);
} catch (PDOException $e) {}

// Load notifications (not dismissed), most recent first
try {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT n.id, n.type, n.ref_post_id, n.ref_reply_id, n.detail, n.is_read, n.created_at,
                u.username AS actor_username, u.full_name AS actor_name,
                u.avatar_filename AS actor_avatar, u.verification_type, u.is_page AS actor_is_page,
                p.post_token
         FROM notifications n
         JOIN users u ON u.id = n.actor_id
         LEFT JOIN posts p ON p.id = n.ref_post_id
         WHERE n.user_id = ? AND n.is_dismissed = 0
         ORDER BY n.created_at DESC
         LIMIT 80"
    );
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Không thể tải thông báo: " . $e->getMessage();
}

// Get the highest notification ID to pass to SSE on connect
$last_notif_id = !empty($notifications) ? intval($notifications[0]['id']) : 0;
if ($last_notif_id === 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=?");
        $stmt->execute([$user_id]);
        $last_notif_id = intval($stmt->fetchColumn());
    } catch (PDOException $e) {}
}

$emojis = [
    'like'  => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍"> Thích',
    'love'  => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️"> Yêu thích',
    'haha'  => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂"> Haha',
    'wow'   => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮"> Wow',
    'sad'   => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢"> Buồn',
    'angry' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡"> Phẫn nộ',
];

$page_css = SITE_URL . '/assets/css/activity.css';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 680px; padding-top: 24px;">

    <!-- Header -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <h3 style="font-family:var(--font-heading); font-size:22px; margin:0; font-weight:800;">
                <i class="fa-regular fa-bell" style="margin-right:8px; color:var(--accent-primary);"></i>
                Thông báo
            </h3>
            <!-- SSE Connection status -->
            <span class="sse-status" id="sseStatus" title="Trạng thái kết nối thời gian thực">
                <span class="sse-dot"></span>
                <span class="sse-label">Đang kết nối...</span>
            </span>
        </div>
        <?php if (!empty($notifications)): ?>
        <button class="clear-activity-btn" id="clearActivityBtn" onclick="clearActivity()">
            <i class="fa-regular fa-trash-can"></i> Xóa tất cả
        </button>
        <?php endif; ?>
    </div>


    <?php if (!empty($error_msg)): ?>
        <div style="background:rgba(239,68,68,0.1); border-left:4px solid var(--danger); color:var(--danger); padding:14px; border-radius:var(--radius-sm); margin-bottom:20px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:8px;"></i><?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Notification Feed -->
    <div class="activity-feed" id="activityFeed" style="display:flex; flex-direction:column; gap:12px;">

        <?php if (empty($notifications)): ?>
            <div id="emptyState" style="padding:60px 20px; text-align:center; color:var(--text-secondary); background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:var(--radius-md);">
                <i class="fa-regular fa-bell-slash" style="font-size:40px; margin-bottom:16px; opacity:0.2; display:block;"></i>
                <p style="margin:0;">Chưa có thông báo nào.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $i => $notif):
                $actor_avatar = AVATARS_URL . '/' . sanitize($notif['actor_avatar'] ?? 'avatar_default.png');
                $actor_username = sanitize($notif['actor_username']);
                $actor_name = sanitize($notif['actor_name'] ?: $notif['actor_username']);
                $time_ago = timeElapsedString($notif['created_at']);
                $is_unread = intval($notif['is_read']) === 0;

                // Build click target
                if ($notif['ref_post_id']) {
                    $click_target = "detail.php?id=" . (!empty($notif['post_token']) ? sanitize($notif['post_token']) : intval($notif['ref_post_id']));
                } else {
                    $click_target = "profile.php?username=" . $actor_username;
                }

                // Build message
                $msg = '';
                if ($notif['type'] === 'reaction') {
                    $r_label = $emojis[trim($notif['detail'] ?? '')] ?? 'cảm xúc';
                    $msg = ' đã thả <strong>' . $r_label . '</strong> cho bài viết của bạn';
                } elseif ($notif['type'] === 'reply') {
                    $msg = ' đã phản hồi bài viết của bạn';
                } elseif ($notif['type'] === 'follow') {
                    $msg = ' đã bắt đầu theo dõi bạn';
                } elseif ($notif['type'] === 'repost') {
                    $msg = ' đã đăng lại bài viết của bạn';
                } else {
                    $msg = ' đã tương tác với bạn';
                }
            ?>
            <a href="<?php echo $click_target; ?>"
               class="activity-item <?php echo $is_unread ? 'new-notif' : ''; ?>"
               id="notif-<?php echo intval($notif['id']); ?>"
               data-notif-id="<?php echo intval($notif['id']); ?>"
               style="animation-delay:<?php echo ($i * 0.03); ?>s; padding-right:42px; text-decoration:none; color:inherit;">

                <!-- Avatar + type badge -->
                <div style="position:relative; flex-shrink:0;">
                    <img src="<?php echo $actor_avatar; ?>"
                         onerror="this.src='<?php echo AVATARS_URL; ?>/avatar_default.png'"
                         style="width:46px; height:46px; border-radius:50%; object-fit:cover; border:1.5px solid var(--border-color);">
                    <?php if ($notif['type'] === 'reaction'): ?>
                        <div class="activity-icon-badge" style="background:#ef4444; color:#fff;"><i class="fa-solid fa-heart"></i></div>
                    <?php elseif ($notif['type'] === 'reply'): ?>
                        <div class="activity-icon-badge" style="background:#3b82f6; color:#fff;"><i class="fa-solid fa-comment"></i></div>
                    <?php elseif ($notif['type'] === 'follow'): ?>
                        <div class="activity-icon-badge" style="background:#10b981; color:#fff;"><i class="fa-solid fa-user-plus"></i></div>
                    <?php elseif ($notif['type'] === 'repost'): ?>
                        <div class="activity-icon-badge" style="background:#8b5cf6; color:#fff;"><i class="fa-solid fa-retweet"></i></div>
                    <?php endif; ?>
                </div>

                <!-- Text -->
                <div style="flex:1; min-width:0;">
                    <div style="font-size:14px; line-height:1.5; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <?php if ($is_unread): ?>
                            <span class="notif-new-dot" title="Chưa đọc"></span>
                        <?php endif; ?>
                        <span style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                            <strong style="color:var(--text-primary);">@<?php echo $actor_username; ?></strong>
                            <?php echo renderAuthorBadgeHTML($notif['verification_type'], $notif['actor_username'], null, intval($notif['actor_is_page'] ?? 0) === 1); ?>
                        </span>
                        <?php echo $msg; ?>
                    </div>
                    <div style="color:var(--text-muted); font-size:11.5px; margin-top:3px;">
                        <i class="fa-regular fa-clock" style="margin-right:3px;"></i><?php echo $time_ago; ?>
                    </div>
                    <?php if (!empty($notif['detail']) && $notif['type'] === 'reply'): ?>
                        <div style="font-size:12.5px; color:var(--text-secondary); margin-top:8px; background:var(--bg-tertiary); padding:8px 12px; border-radius:var(--radius-sm); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%;">
                            "<?php echo sanitize($notif['detail']); ?>"
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Dismiss button (stops anchor navigation) -->
                <button class="dismiss-notif-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); dismissNotification(<?php echo intval($notif['id']); ?>, this)"
                        title="Xóa thông báo này">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
    var ACTIVITY_LAST_ID = <?php echo json_encode($last_notif_id); ?>;
    var AVATARS_URL_PHP  = <?php echo json_encode(AVATARS_URL); ?>;
</script>
<script src="assets/js/activity.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/activity.js') ?: '1'; ?>"></script>
