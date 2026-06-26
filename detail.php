<?php
/**
 * Post Detail & Replies Page - Frest App
 */
require_once __DIR__ . '/config.php';
$page_css = SITE_URL . '/assets/css/detail.css';
require_once __DIR__ . '/includes/header.php';

$id_param = isset($_GET['id']) ? trim($_GET['id']) : '';
$id = 0;
$error_msg = '';

if (empty($id_param)) {
    echo "<div class='container section text-center'><p style='color: var(--text-secondary);'>Bài viết không tồn tại.</p></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

try {
    $db = getDB();
    if (is_numeric($id_param)) {
        $id = intval($id_param);
    } else {
        $stmt_tok = $db->prepare("SELECT id FROM posts WHERE post_token = ?");
        $stmt_tok->execute([$id_param]);
        $id = intval($stmt_tok->fetchColumn() ?: 0);
    }
} catch (Exception $e) {}

if ($id <= 0) {
    echo "<div class='container section text-center'><p style='color: var(--text-secondary);'>Bài viết không tồn tại.</p></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

try {
    // Handle posting a reply
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_reply'])) {
        if (!isUserLoggedIn()) {
            header("Location: login.php");
            exit;
        }

        $reply_content = trim($_POST['reply_content'] ?? '');
        $user_id = getLoggedInUserId();

        if (empty($reply_content)) {
            $error_msg = "Nội dung phản hồi không được để trống.";
        } else {
            $identity = getCurrentIdentity();
            $page_id = ($identity && $identity['type'] === 'page') ? $identity['id'] : null;
            $parent_reply_id = isset($_POST['parent_reply_id']) ? intval($_POST['parent_reply_id']) : null;
            if ($parent_reply_id <= 0) $parent_reply_id = null;

            $stmt = $db->prepare("INSERT INTO replies (post_id, user_id, content, page_id, parent_reply_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $user_id, $reply_content, $page_id, $parent_reply_id]);

            // Notify the post owner
            $post_owner_stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
            $post_owner_stmt->execute([$id]);
            $post_owner_id = $post_owner_stmt->fetchColumn();
            if ($post_owner_id) {
                $snippet = mb_substr($reply_content, 0, 80);
                createNotification($post_owner_id, $user_id, 'reply', $id, null, $snippet);
            }

            // Get post token for redirect
            $post_tok_stmt = $db->prepare("SELECT post_token FROM posts WHERE id = ?");
            $post_tok_stmt->execute([$id]);
            $post_token_val = $post_tok_stmt->fetchColumn() ?: $id;

            echo "<script>
                localStorage.setItem('reply_created', '1');
                window.location.href = 'detail.php?id=" . $post_token_val . "';
            </script>";
            exit;
        }
    }

    // Fetch the main post details
    $stmt = $db->prepare("SELECT p.*, 
                                 COALESCE(pg.page_username, u.username) AS username, 
                                 COALESCE(pg.avatar_filename, u.avatar_filename) AS avatar_filename, 
                                 IF(p.page_id IS NOT NULL, 'none', u.verification_type) AS verification_type, 
                                 COALESCE(pg.page_name, u.full_name, u.username) AS full_name, 
                                 u.is_private,
                                 u.is_page AS is_user_page
                          FROM posts p 
                          JOIN users u ON p.user_id = u.id 
                          LEFT JOIN pages pg ON p.page_id = pg.id
                          WHERE p.id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo "<div class='container section text-center'><p style='color: var(--text-secondary);'>Bài viết không tồn tại hoặc đã bị xóa.</p></div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    // Check private account visibility
    $post_is_private = intval($post['is_private'] ?? 0) === 1;
    $viewer_can_see = !$post_is_private
        || ($me && intval($post['user_id']) === intval($me['id']))
        || isAdminLoggedIn()
        || ($me && isFollowingUser($me['id'], $post['user_id']));

    if (!$viewer_can_see) {
        echo "<div class='container section text-center' style='padding:48px 20px;'><i class='fa-solid fa-lock' style='font-size:36px;color:var(--text-muted);margin-bottom:16px;display:block;'></i><p style='color: var(--text-primary); font-weight:800; font-size:16px;'>Bài viết này thuộc tài khoản riêng tư.</p><p style='color: var(--text-secondary); font-size:13px;'>Theo dõi @" . htmlspecialchars($post['username']) . " để xem bài viết.</p></div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    // Fetch active user reaction
    $active_reaction = getUserPostReaction(getLoggedInUserId(), $id);
    $reacted_class = $active_reaction ? 'active' : '';

    // Fetch reactions summary
    $reactions_summary = getPostReactionsSummary($id);
    $emojis = [
        'like' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍">',
        'love' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️">',
        'haha' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂">',
        'wow' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮">',
        'sad' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢">',
        'angry' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡">'
    ];
    $reaction_labels = ['like' => 'Thích', 'love' => 'Yêu thích', 'haha' => 'Haha', 'wow' => 'Wow', 'sad' => 'Buồn', 'angry' => 'Phẫn nộ'];

    // Fetch reposts count
    $reposts_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE repost_of_post_id = ?");
    $reposts_stmt->execute([$id]);
    $reposts_count = $reposts_stmt->fetchColumn();

    // Check if current user reposted this under active identity
    $user_reposted = false;
    if ($me) {
        $identity = getCurrentIdentity();
        $target_repost_id = !empty($post['repost_of_post_id']) ? $post['repost_of_post_id'] : $id;
        if ($identity && $identity['type'] === 'page') {
            $user_repost_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND page_id = ? AND repost_of_post_id = ? AND (content = '' OR content IS NULL)");
            $user_repost_stmt->execute([$me['id'], $identity['id'], $target_repost_id]);
        } else {
            $user_repost_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND page_id IS NULL AND repost_of_post_id = ? AND (content = '' OR content IS NULL)");
            $user_repost_stmt->execute([$me['id'], $target_repost_id]);
        }
        $user_reposted = ($user_repost_stmt->fetchColumn() > 0);
    }

    // Fetch original post if it's a repost
    $original_post = null;
    if (!empty($post['repost_of_post_id'])) {
        $orig_stmt = $db->prepare("SELECT p.*, 
                                          COALESCE(pg.page_username, u.username) AS username, 
                                          COALESCE(pg.avatar_filename, u.avatar_filename) AS avatar_filename, 
                                          IF(p.page_id IS NOT NULL, 'none', u.verification_type) AS verification_type, 
                                          COALESCE(pg.page_name, u.full_name, u.username) AS full_name,
                                          u.is_page AS is_user_page
                                   FROM posts p 
                                   JOIN users u ON p.user_id = u.id 
                                   LEFT JOIN pages pg ON p.page_id = pg.id
                                   WHERE p.id = ?");
        $orig_stmt->execute([$post['repost_of_post_id']]);
        $original_post = $orig_stmt->fetch();
    }

    // Fetch replies with user details
    $replies_stmt = $db->prepare("SELECT r.*, 
                                         COALESCE(pg.page_username, u.username) AS username, 
                                         COALESCE(pg.avatar_filename, u.avatar_filename) AS avatar_filename, 
                                         IF(r.page_id IS NOT NULL, 'none', u.verification_type) AS verification_type,
                                         COALESCE(pg.page_name, u.full_name, u.username) AS full_name,
                                         u.is_page AS is_user_page
                                  FROM replies r 
                                  JOIN users u ON r.user_id = u.id 
                                  LEFT JOIN pages pg ON r.page_id = pg.id
                                  WHERE r.post_id = ? 
                                  ORDER BY r.created_at ASC");
    $replies_stmt->execute([$id]);
    $replies = $replies_stmt->fetchAll();

    // Eager load reactions for all replies
    $reply_reactions_summary_map = [];
    $reply_active_reaction_map = [];

    if (!empty($replies)) {
        $reply_ids = array_map(function($r) { return intval($r['id']); }, $replies);
        $placeholders = implode(',', array_fill(0, count($reply_ids), '?'));

        // 1. Fetch reaction counts and types
        $reac_stmt = $db->prepare("
            SELECT reply_id, reaction_type, COUNT(*) as qty 
            FROM reactions 
            WHERE reply_id IN ($placeholders)
            GROUP BY reply_id, reaction_type
            ORDER BY qty DESC
        ");
        $reac_stmt->execute($reply_ids);
        $raw_reacts = $reac_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw_reacts as $r) {
            $rep_id = intval($r['reply_id']);
            if (!isset($reply_reactions_summary_map[$rep_id])) {
                $reply_reactions_summary_map[$rep_id] = [
                    'total' => 0,
                    'types' => []
                ];
            }
            $reply_reactions_summary_map[$rep_id]['total'] += intval($r['qty']);
            if (count($reply_reactions_summary_map[$rep_id]['types']) < 3) {
                $reply_reactions_summary_map[$rep_id]['types'][] = $r['reaction_type'];
            }
        }

        // 2. Fetch active reaction for current user
        if ($me) {
            $active_stmt = $db->prepare("
                SELECT reply_id, reaction_type 
                FROM reactions 
                WHERE user_id = ? AND reply_id IN ($placeholders)
            ");
            $active_stmt->execute(array_merge([$me['id']], $reply_ids));
            while ($row = $active_stmt->fetch(PDO::FETCH_ASSOC)) {
                $reply_active_reaction_map[intval($row['reply_id'])] = $row['reaction_type'];
            }
        }
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
}
?>


<div class="container" style="max-width: 680px; padding-top: 24px;">
    
    <!-- Toast notifications -->
    <script>
        (function() {
            if (localStorage.getItem('reply_created')) {
                showToast('Đã đăng phản hồi của bạn! 💬');
                localStorage.removeItem('reply_created');
            }
        })();
    </script>

    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 20px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Back Navigation -->
    <div class="detail-back-wrapper" style="margin-bottom: 20px;">
        <a href="index.php" style="color: var(--text-secondary); font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-arrow-left"></i> Quay lại Bảng tin
        </a>
    </div>

    <!-- Main Original Post Card -->
    <?php 
    $is_my_repost = false;
    if ($me && !empty($post['repost_of_post_id']) && (empty($post['content']) || $post['content'] === '')) {
        $identity = getCurrentIdentity();
        if ($identity && $identity['type'] === 'page') {
            $is_my_repost = (intval($post['page_id'] ?? 0) === intval($identity['id']) && intval($post['user_id'] ?? 0) === intval($me['id']));
        } else {
            $is_my_repost = (empty($post['page_id']) && intval($post['user_id'] ?? 0) === intval($me['id']));
        }
    }
    ?>
    <?php 
    $glow_class = ($id % 2 === 0) ? 'glowing-card-cyan' : 'glowing-card-purple';
    ?>
    <div class="frest-card <?php echo $glow_class; ?> <?php echo $is_my_repost ? 'my-repost-card' : ''; ?>" data-post-id="<?php echo $id; ?>" <?php if (!empty($post['repost_of_post_id'])) { echo 'data-repost-of-id="' . $post['repost_of_post_id'] . '"'; } ?> style="border-bottom: 1px solid var(--border-color); padding-bottom: 24px; border-radius: var(--radius-md); padding: 24px;">
        <div class="frest-left">
            <a href="<?php echo getProfileUrl($post['username'], $post['page_id']); ?>">
                <img src="<?php echo AVATARS_URL . '/' . sanitize($post['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
            </a>
            <div class="frest-line" style="min-height: 80px;"></div>
        </div>
        <div class="frest-right">
            <div class="frest-header">
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <a href="<?php echo getProfileUrl($post['username'], $post['page_id']); ?>" class="frest-author" style="font-weight: 700; color: var(--text-primary); text-decoration: none;">
                            <?php echo !empty($post['full_name']) ? sanitize($post['full_name']) : sanitize($post['username']); ?>
                        </a>
                        <?php echo renderAuthorBadgeHTML($post['verification_type'], $post['username'], $post['page_id'], $post['is_user_page'] ?? false); ?>
                        <span style="color: var(--text-muted); font-size: 13.5px; font-weight: 600; margin: 0 2px;">·</span>
                        <span class="frest-time"><?php echo timeElapsedString($post['created_at']); ?></span>
                    </div>
                    <?php if (!empty($post['full_name'])): ?>
                        <span style="font-size: 12.5px; color: var(--text-muted); font-weight: 500; margin-top: -2px; text-align: left;">@<?php echo sanitize($post['username']); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php 
                $is_violation = (isset($post['is_copyright_violation']) && intval($post['is_copyright_violation']) === 1);
                $is_my_post = $me && intval($post['user_id']) === intval($me['id']);
                $can_edit = $is_my_post && !$is_violation;
                $can_delete = isAdminLoggedIn() || ($is_my_post && !$is_violation);
                $can_report = $me && !$is_my_post;
                
                if ($can_edit || $can_delete || $can_report):
                ?>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <!-- Ellipsis Actions Menu -->
                    <div class="ellipsis-menu-container">
                        <button class="ellipsis-btn"><i class="fa-solid fa-ellipsis"></i></button>
                        <div class="ellipsis-dropdown">
                            <?php if ($can_edit): ?>
                                <div class="ellipsis-item pin-post-trigger" data-post-id="<?php echo $id; ?>" data-pinned="<?php echo intval($post['is_pinned'] ?? 0); ?>">
                                    <i class="fa-solid fa-thumbtack"></i> 
                                    <span><?php echo (intval($post['is_pinned'] ?? 0) === 1) ? 'Bỏ ghim' : 'Ghim bài viết'; ?></span>
                                </div>
                                <div class="ellipsis-item edit-post-trigger" data-post-id="<?php echo $id; ?>" data-content="<?php echo sanitize($post['content']); ?>">
                                    <i class="fa-regular fa-pen-to-square"></i> Chỉnh sửa
                                </div>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <div class="ellipsis-item delete delete-post-trigger" data-post-id="<?php echo $id; ?>">
                                    <i class="fa-regular fa-trash-can"></i> Xóa bài
                                </div>
                            <?php endif; ?>
                            <?php if ($can_report): ?>
                                <div class="ellipsis-item report-trigger-post-btn" data-post-id="<?php echo $id; ?>" style="color: var(--danger);">
                                    <i class="fa-regular fa-flag"></i> Báo cáo Frest
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($post['repost_of_post_id'])): ?>
                 <div class="repost-header" style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-weight: 600; text-align: left;">
                     <i class="fa-solid fa-retweet" style="color: var(--success);"></i>
                     <span>@<?php echo htmlspecialchars($post['username']); ?> đã đăng lại</span>
                 </div>
            <?php endif; ?>

            <?php if (!empty($post['content']) || empty($post['repost_of_post_id'])): ?>
                 <div class="frest-content" style="font-size: 15.5px; margin-top: 8px; line-height: 1.6; text-align: left;"><?php echo nl2br(parseHashtags(linkify(sanitize($post['content'])))); ?></div>
            <?php endif; ?>

            <?php 
            $post_id = $id;
            $is_nsfw_post = (isset($post['is_nsfw']) && intval($post['is_nsfw']) === 1);
            $user_show_nsfw = false;
            if ($me) {
                $user_show_nsfw = (intval($me['show_nsfw'] ?? 0) === 1);
            }
            $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
            
            if (!empty($post['repost_of_post_id'])) {
                if ($original_post) {
                    // Render repost's own media first (if any)
                    echo renderPostMediaHTML($post, $should_blur_nsfw);

                    $orig_is_nsfw = (isset($original_post['is_nsfw']) && intval($original_post['is_nsfw']) === 1);
                    $orig_should_blur = $orig_is_nsfw && !$user_show_nsfw;
                    
                    echo '<div class="repost-card" onclick="event.stopPropagation(); window.location.href=\'detail.php?id=' . $original_post['id'] . '\';" style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; background: rgba(255, 255, 255, 0.015); margin-top: 10px; cursor: pointer; transition: background 0.2s, border-color 0.2s; position: relative; width: 100%; box-sizing: border-box;">';
                    echo '  <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; text-align: left;">';
                    echo '    <img src="' . AVATARS_URL . '/' . sanitize($original_post['avatar_filename']) . '" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">';
                    echo '    <span style="font-weight: 700; font-size: 13px; color: var(--text-primary);">' . (!empty($original_post['full_name']) ? htmlspecialchars($original_post['full_name']) : htmlspecialchars($original_post['username'])) . '</span>';
                    if (!empty($original_post['full_name'])) {
                        echo '    <span style="font-size: 11.5px; color: var(--text-muted);">@' . htmlspecialchars($original_post['username']) . '</span>';
                    }
                    echo renderAuthorBadgeHTML($original_post['verification_type'], $original_post['username'], $original_post['page_id'], $original_post['is_user_page'] ?? false);
                    echo '    <span style="color: var(--text-muted); font-size: 11px;">• ' . timeElapsedString($original_post['created_at']) . '</span>';
                    echo '  </div>';
                    echo '  <div style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 8px; text-align: left; line-height: 1.45;">' . nl2br(parseHashtags(linkify(sanitize($original_post['content'])))). '</div>';
                    echo renderPostMediaHTML($original_post, $orig_should_blur);
                    echo renderPollHTML($original_post['id'], $me['id'] ?? null);
                    echo renderLinkPreviewCard($original_post);
                    echo '</div>';
                } else {
                    echo '<div class="repost-card-deleted" style="border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 12px; background: rgba(255, 255, 255, 0.01); margin-top: 8px; font-style: italic; font-size: 12.5px; color: var(--text-muted); text-align: left;">Bài viết gốc không khả dụng hoặc đã bị xóa.</div>';
                }
            } else {
                echo renderPostMediaHTML($post, $should_blur_nsfw);
                echo renderPollHTML($post['id'], $me['id'] ?? null);
                echo renderLinkPreviewCard($post);
            }
            ?>

            <!-- Social Action Bar -->
            <div class="frest-actions" style="margin-top: 16px; display: flex; gap: 16px;">
                <div class="reaction-container" data-post-id="<?php echo $id; ?>">
                    <button class="frest-action-btn react-btn <?php echo $reacted_class; ?>" 
                            data-post-id="<?php echo $id; ?>" 
                            data-active-type="<?php echo $active_reaction ?: ''; ?>">
                        <?php if ($active_reaction): ?>
                            <?php echo $emojis[$active_reaction] ?? '👍'; ?>
                        <?php else: ?>
                            <i class="fa-regular fa-thumbs-up"></i>
                        <?php endif; ?>
                        <?php if ($reactions_summary['total'] > 0): ?>
                            <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reactions_summary['total']; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="reaction-picker-panel">
                        <span class="reaction-emoji" data-reaction="like"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍"></span>
                        <span class="reaction-emoji" data-reaction="love"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️"></span>
                        <span class="reaction-emoji" data-reaction="haha"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂"></span>
                        <span class="reaction-emoji" data-reaction="wow"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮"></span>
                        <span class="reaction-emoji" data-reaction="sad"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢"></span>
                        <span class="reaction-emoji" data-reaction="angry"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡"></span>
                    </div>
                </div>
                <button class="frest-action-btn reply-btn" onclick="document.getElementById('reply_content').focus();">
                    <i class="fa-regular fa-comment"></i>
                    <?php if (count($replies) > 0): ?>
                        <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo count($replies); ?></span>
                    <?php endif; ?>
                </button>
                <button class="frest-action-btn repost-btn repost-action-trigger <?php echo $user_reposted ? 'reposted' : ''; ?>" data-post-id="<?php echo !empty($post['repost_of_post_id']) ? $post['repost_of_post_id'] : $id; ?>" title="Đăng lại bài viết" style="<?php echo $user_reposted ? 'color: var(--success);' : ''; ?>">
                    <i class="fa-solid fa-retweet"></i>
                    <?php if ($reposts_count > 0): ?>
                        <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reposts_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="frest-action-btn share-btn copy-share-link" data-url="<?php echo SITE_URL . '/detail.php?id=' . $id; ?>">
                    <i class="fa-regular fa-paper-plane"></i>
                </button>
                
                <button class="frest-action-btn bookmark-btn <?php echo isPostBookmarked($id, $me_id) ? 'bookmarked' : ''; ?>" 
                        onclick="event.stopPropagation(); toggleBookmark(this, <?php echo $id; ?>);" 
                        title="Lưu bài viết"
                        style="<?php echo isPostBookmarked($id, $me_id) ? 'color: var(--accent-primary);' : ''; ?>">
                    <i class="<?php echo isPostBookmarked($id, $me_id) ? 'fa-solid' : 'fa-regular'; ?> fa-bookmark"></i>
                </button>

                <?php if ((!empty($post['image_filename']) || !empty($post['video_filename'])) && intval($post['is_copyright_violation'] ?? 0) === 0): ?>
                    <?php if (intval($post['allow_download']) === 1): ?>
                        <?php 
                        $dl_file = !empty($post['video_filename']) ? $post['video_filename'] : $post['image_filename'];
                        ?>
                        <a href="<?php echo POSTS_URL . '/' . $dl_file; ?>" download class="frest-action-btn download-btn" title="Tải phương tiện về máy" style="color: var(--text-secondary);">
                            <i class="fa-regular fa-circle-down"></i>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Reaction summaries -->
            <div class="frest-stats" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                <span class="likes-stat" style="cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                    <?php if ($reactions_summary['total'] > 0): ?>
                        <span class="reactions-badges" style="margin-right: 2px;">
                            <?php foreach ($reactions_summary['types'] as $type): ?>
                                <?php echo $emojis[$type] ?? ''; ?>
                            <?php endforeach; ?>
                        </span>
                        <span class="likes-count"><?php echo $reactions_summary['total']; ?></span> lượt tương tác
                    <?php else: ?>
                        <span class="likes-count">0</span> tương tác
                    <?php endif; ?>
                </span>
                <span>•</span>
                <span class="reposts-stat" style="cursor: pointer;" onclick="window.location.href='detail.php?id=<?php echo !empty($post['repost_of_post_id']) ? $post['repost_of_post_id'] : $id; ?>';">
                    <?php echo $reposts_count; ?> đăng lại
                </span>
                <span>•</span>
                <span><?php echo count($replies); ?> phản hồi</span>
            </div>
        </div>
    </div>

    <!-- Replies list section -->
    <div class="replies-section" style="margin-top: 24px;" id="repliesSection">
        <h3 class="replies-title" style="font-size: 16px; font-weight: 800; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px;">Phản hồi từ cộng đồng</h3>

        <?php 
        // Group replies into tree structure (1-level nesting)
        $top_level_replies = [];
        $sub_replies = [];
        foreach ($replies as $r) {
            if (empty($r['parent_reply_id'])) {
                $r['children'] = [];
                $top_level_replies[$r['id']] = $r;
            } else {
                $sub_replies[] = $r;
            }
        }
        foreach ($sub_replies as $sr) {
            $parent_id = intval($sr['parent_reply_id']);
            if (isset($top_level_replies[$parent_id])) {
                $top_level_replies[$parent_id]['children'][] = $sr;
            } else {
                $sr['children'] = [];
                $top_level_replies[$sr['id']] = $sr;
            }
        }
        ?>

        <?php if (empty($top_level_replies)): ?>
            <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 24px 0;">
                Chưa có phản hồi nào cho Frest này. Viết phản hồi đầu tiên nhé!
            </p>
        <?php else: ?>
            <?php foreach ($top_level_replies as $reply): 
                $reply_id_val = intval($reply['id']);
                $reply_is_mine = $me && intval($reply['user_id']) === intval($me['id']);
                $reply_can_delete = $reply_is_mine || isAdminLoggedIn();
                $is_edited = !empty($reply['updated_at']);
                
                // Get reaction summary for this reply from eager loaded map
                $reply_reactions_summary = $reply_reactions_summary_map[$reply_id_val] ?? ['total' => 0, 'types' => []];
                $reply_active_reaction = $reply_active_reaction_map[$reply_id_val] ?? false;
                $reply_reacted_class = $reply_active_reaction ? 'active' : '';
            ?>
                <div class="reply-card-wrapper" style="border-bottom: 1px solid var(--border-color); padding: 16px 0;">
                    <div class="reply-card frest-card" id="reply-<?php echo $reply_id_val; ?>" data-reply-id="<?php echo $reply_id_val; ?>" style="border-bottom: none; padding: 0;">
                        <div class="frest-left" style="width: 40px; margin-right: 12px;">
                            <a href="<?php echo getProfileUrl($reply['username'], $reply['page_id']); ?>">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($reply['avatar_filename']); ?>" 
                                     class="frest-avatar" 
                                     style="width: 36px; height: 36px;" 
                                     alt="Avatar">
                            </a>
                        </div>
                        <div class="frest-right" style="flex: 1; min-width: 0;">
                            <!-- Reply header -->
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                    <a href="<?php echo getProfileUrl($reply['username'], $reply['page_id']); ?>" class="frest-author" style="font-size: 13.5px; font-weight: 700;"><?php echo !empty($reply['full_name']) ? sanitize($reply['full_name']) : sanitize($reply['username']); ?></a>
                                    <?php echo renderAuthorBadgeHTML($reply['verification_type'], $reply['username'], $reply['page_id'], $reply['is_user_page'] ?? false); ?>
                                    <span class="frest-time" style="font-size: 11.5px; color: var(--text-muted);"><?php echo timeElapsedString($reply['created_at']); ?></span>
                                    <?php if ($is_edited): ?>
                                        <span class="edited-badge" style="font-size: 10.5px; color: var(--text-muted);">(đã chỉnh sửa)</span>
                                    <?php endif; ?>
                                </div>
                                <!-- Edit/Delete/Report menu -->
                                <?php 
                                $reply_can_report = $me && !$reply_is_mine;
                                if ($reply_is_mine || $reply_can_delete || $reply_can_report): 
                                ?>
                                <div class="reply-menu-container">
                                    <button class="reply-menu-btn" onclick="toggleReplyMenu(<?php echo $reply_id_val; ?>, event)" title="Tùy chọn">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <div class="reply-dropdown" id="reply-menu-<?php echo $reply_id_val; ?>">
                                        <?php if ($reply_is_mine): ?>
                                        <div class="reply-dropdown-item" onclick="startEditReply(<?php echo $reply_id_val; ?>)">
                                            <i class="fa-regular fa-pen-to-square"></i> Chỉnh sửa
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($reply_can_delete): ?>
                                        <div class="reply-dropdown-item delete-item" onclick="deleteReply(<?php echo $reply_id_val; ?>)">
                                            <i class="fa-regular fa-trash-can"></i> Xóa bình luận
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($reply_can_report): ?>
                                        <div class="reply-dropdown-item report-trigger-reply-btn" data-reply-id="<?php echo $reply_id_val; ?>" style="color: var(--danger);">
                                            <i class="fa-regular fa-flag"></i> Báo cáo bình luận
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Reply content (display mode) -->
                            <div class="reply-content-display" id="reply-content-display-<?php echo $reply_id_val; ?>" style="font-size: 14px; margin-top: 6px; color: var(--text-secondary); line-height: 1.55;"><?php echo nl2br(linkify(sanitize(trim($reply['content'])))); ?></div>

                            <!-- Reply edit area (hidden by default) -->
                            <?php if ($reply_is_mine): ?>
                            <div class="reply-edit-area" id="reply-edit-area-<?php echo $reply_id_val; ?>">
                                <textarea id="reply-edit-input-<?php echo $reply_id_val; ?>"><?php echo htmlspecialchars(trim($reply['content']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="reply-edit-actions">
                                    <button class="btn-cancel-reply" onclick="cancelEditReply(<?php echo $reply_id_val; ?>)">Hủy</button>
                                    <button class="btn-save-reply" onclick="saveEditReply(<?php echo $reply_id_val; ?>)">Lưu</button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Action Row (Reactions, Reply Button, and Likes Count) -->
                            <div class="reply-actions-row" style="margin-top: 8px; display: flex; align-items: center; gap: 14px; font-size: 12.5px; color: var(--text-secondary);">
                                <div class="reply-reaction-container" data-reply-id="<?php echo $reply_id_val; ?>">
                                    <button class="reply-action-btn reply-react-trigger-btn <?php echo $reply_reacted_class; ?>" 
                                            data-reply-id="<?php echo $reply_id_val; ?>" 
                                            data-active-type="<?php echo $reply_active_reaction ?: ''; ?>">
                                        <?php if ($reply_active_reaction): ?>
                                            <?php echo ($emojis[$reply_active_reaction] ?? '👍') . ' ' . ($reaction_labels[$reply_active_reaction] ?? 'Thích'); ?>
                                        <?php else: ?>
                                            <i class="fa-regular fa-thumbs-up"></i> Thích
                                        <?php endif; ?>
                                    </button>
                                    <div class="reply-reaction-picker-panel">
                                        <span class="reply-reaction-emoji" data-reaction="like"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍"></span>
                                        <span class="reply-reaction-emoji" data-reaction="love"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️"></span>
                                        <span class="reply-reaction-emoji" data-reaction="haha"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂"></span>
                                        <span class="reply-reaction-emoji" data-reaction="wow"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮"></span>
                                        <span class="reply-reaction-emoji" data-reaction="sad"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢"></span>
                                        <span class="reply-reaction-emoji" data-reaction="angry"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡"></span>
                                    </div>
                                </div>
                                
                                <button class="reply-action-btn show-reply-form-btn" onclick="toggleSubReplyForm(<?php echo $reply_id_val; ?>)">
                                    <i class="fa-regular fa-comment"></i> Phản hồi
                                </button>
                                
                                <span class="reply-likes-stat" id="reply-likes-stat-<?php echo $reply_id_val; ?>" style="<?php echo $reply_reactions_summary['total'] > 0 ? '' : 'display: none;'; ?>">
                                    • 
                                    <span class="reply-reactions-badges">
                                        <?php foreach ($reply_reactions_summary['types'] as $t): ?>
                                            <?php echo $emojis[$t] ?? ''; ?>
                                        <?php endforeach; ?>
                                    </span>
                                    <span class="reply-likes-count"><?php echo $reply_reactions_summary['total']; ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-replies List -->
                    <?php if (!empty($reply['children'])): ?>
                    <div class="sub-replies-list" style="margin-left: 48px; border-left: 1.5px solid var(--border-color); padding-left: 14px; margin-top: 10px;">
                        <?php foreach ($reply['children'] as $child): 
                            $child_id_val = intval($child['id']);
                            $child_is_mine = $me && intval($child['user_id']) === intval($me['id']);
                            $child_can_delete = $child_is_mine || isAdminLoggedIn();
                            $child_is_edited = !empty($child['updated_at']);
                            
                            // Get reaction summary for child reply from eager loaded map
                            $child_reactions_summary = $reply_reactions_summary_map[$child_id_val] ?? ['total' => 0, 'types' => []];
                            $child_active_reaction = $reply_active_reaction_map[$child_id_val] ?? false;
                            $child_reacted_class = $child_active_reaction ? 'active' : '';
                        ?>
                            <div class="reply-card frest-card sub-reply-card" id="reply-<?php echo $child_id_val; ?>" data-reply-id="<?php echo $child_id_val; ?>" style="padding: 10px 0; border-bottom: none;">
                                <div class="frest-left" style="width: 32px; margin-right: 10px;">
                                    <a href="<?php echo getProfileUrl($child['username'], $child['page_id']); ?>">
                                        <img src="<?php echo AVATARS_URL . '/' . sanitize($child['avatar_filename']); ?>" 
                                             class="frest-avatar" 
                                             style="width: 28px; height: 28px;" 
                                             alt="Avatar">
                                    </a>
                                </div>
                                <div class="frest-right" style="flex: 1; min-width: 0;">
                                    <!-- Child Reply header -->
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                        <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                            <a href="<?php echo getProfileUrl($child['username'], $child['page_id']); ?>" class="frest-author" style="font-size: 13px; font-weight: 700;"><?php echo !empty($child['full_name']) ? sanitize($child['full_name']) : sanitize($child['username']); ?></a>
                                            <?php echo renderAuthorBadgeHTML($child['verification_type'], $child['username'], $child['page_id'], $child['is_user_page'] ?? false); ?>
                                            <span class="frest-time" style="font-size: 11px; color: var(--text-muted);"><?php echo timeElapsedString($child['created_at']); ?></span>
                                            <?php if ($child_is_edited): ?>
                                                <span class="edited-badge" style="font-size: 10px; color: var(--text-muted);">(đã chỉnh sửa)</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Dropdown options for child reply -->
                                        <?php 
                                        $child_can_report = $me && !$child_is_mine;
                                        if ($child_is_mine || $child_can_delete || $child_can_report): 
                                        ?>
                                        <div class="reply-menu-container">
                                            <button class="reply-menu-btn" onclick="toggleReplyMenu(<?php echo $child_id_val; ?>, event)" title="Tùy chọn">
                                                <i class="fa-solid fa-ellipsis"></i>
                                            </button>
                                            <div class="reply-dropdown" id="reply-menu-<?php echo $child_id_val; ?>">
                                                <?php if ($child_is_mine): ?>
                                                <div class="reply-dropdown-item" onclick="startEditReply(<?php echo $child_id_val; ?>)">
                                                    <i class="fa-regular fa-pen-to-square"></i> Chỉnh sửa
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($child_can_delete): ?>
                                                <div class="reply-dropdown-item delete-item" onclick="deleteReply(<?php echo $child_id_val; ?>)">
                                                    <i class="fa-regular fa-trash-can"></i> Xóa bình luận
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($child_can_report): ?>
                                                <div class="reply-dropdown-item report-trigger-reply-btn" data-reply-id="<?php echo $child_id_val; ?>" style="color: var(--danger);">
                                                    <i class="fa-regular fa-flag"></i> Báo cáo bình luận
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Child Reply content -->
                                    <div class="reply-content-display" id="reply-content-display-<?php echo $child_id_val; ?>" style="font-size: 13.5px; margin-top: 4px; color: var(--text-secondary); line-height: 1.5;"><?php echo nl2br(linkify(sanitize(trim($child['content'])))); ?></div>
                                    
                                    <!-- Child Edit input area -->
                                    <?php if ($child_is_mine): ?>
                                    <div class="reply-edit-area" id="reply-edit-area-<?php echo $child_id_val; ?>">
                                        <textarea id="reply-edit-input-<?php echo $child_id_val; ?>"><?php echo htmlspecialchars(trim($child['content']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <div class="reply-edit-actions">
                                            <button class="btn-cancel-reply" onclick="cancelEditReply(<?php echo $child_id_val; ?>)">Hủy</button>
                                            <button class="btn-save-reply" onclick="saveEditReply(<?php echo $child_id_val; ?>)">Lưu</button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Child Action Row -->
                                    <div class="reply-actions-row" style="margin-top: 6px; display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-secondary);">
                                        <div class="reply-reaction-container" data-reply-id="<?php echo $child_id_val; ?>">
                                            <button class="reply-action-btn reply-react-trigger-btn <?php echo $child_reacted_class; ?>" 
                                                    data-reply-id="<?php echo $child_id_val; ?>" 
                                                    data-active-type="<?php echo $child_active_reaction ?: ''; ?>">
                                                <?php if ($child_active_reaction): ?>
                                                    <?php echo ($emojis[$child_active_reaction] ?? '👍') . ' ' . ($reaction_labels[$child_active_reaction] ?? 'Thích'); ?>
                                                <?php else: ?>
                                                    <i class="fa-regular fa-thumbs-up"></i> Thích
                                                <?php endif; ?>
                                            </button>
                                            <div class="reply-reaction-picker-panel">
                                                <span class="reply-reaction-emoji" data-reaction="like"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍"></span>
                                                <span class="reply-reaction-emoji" data-reaction="love"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️"></span>
                                                <span class="reply-reaction-emoji" data-reaction="haha"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂"></span>
                                                <span class="reply-reaction-emoji" data-reaction="wow"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮"></span>
                                                <span class="reply-reaction-emoji" data-reaction="sad"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢"></span>
                                                <span class="reply-reaction-emoji" data-reaction="angry"><img class="reaction-emoji-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡"></span>
                                            </div>
                                        </div>
                                        
                                        <span class="reply-likes-stat" id="reply-likes-stat-<?php echo $child_id_val; ?>" style="<?php echo $child_reactions_summary['total'] > 0 ? '' : 'display: none;'; ?>">
                                            • 
                                            <span class="reply-reactions-badges">
                                                <?php foreach ($child_reactions_summary['types'] as $t): ?>
                                                    <?php echo $emojis[$t] ?? ''; ?>
                                                <?php endforeach; ?>
                                            </span>
                                            <span class="reply-likes-count"><?php echo $child_reactions_summary['total']; ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Inline Sub-reply Form -->
                    <div class="sub-reply-form-container" id="sub-reply-form-<?php echo $reply_id_val; ?>" style="display: none; margin-left: 48px; margin-top: 10px; background: rgba(255, 255, 255, 0.02); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <form action="" method="POST" style="margin-bottom: 0;">
                            <input type="hidden" name="action_create_reply" value="1">
                            <input type="hidden" name="parent_reply_id" value="<?php echo $reply_id_val; ?>">
                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <?php 
                                $my_identity = getCurrentIdentity();
                                $avatar_url = $my_identity ? AVATARS_URL . '/' . sanitize($my_identity['avatar']) : AVATARS_URL . '/avatar_default.png';
                                ?>
                                <img src="<?php echo $avatar_url; ?>" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                                <div style="flex: 1;">
                                    <textarea name="reply_content" placeholder="Viết phản hồi cho bình luận này..." required style="width: 100%; min-height: 50px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); padding: 8px 10px; font-size: 13px; font-family: var(--font-body); resize: vertical; outline: none; box-sizing: border-box;"></textarea>
                                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px;">
                                        <button type="button" class="btn-cancel-reply" style="font-size: 12px; padding: 6px 12px; height: 28px; display: inline-flex; align-items: center; background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); cursor: pointer; border-radius: var(--radius-sm);" onclick="toggleSubReplyForm(<?php echo $reply_id_val; ?>)">Hủy</button>
                                        <button type="submit" class="btn-save-reply" style="font-size: 12px; padding: 6px 14px; height: 28px; display: inline-flex; align-items: center; background: var(--accent-gradient); border: none; color: #fff; cursor: pointer; border-radius: var(--radius-sm); font-weight: 700;">Gửi</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reply Composer Form -->
    <div id="reply-composer" class="checkout-card" style="margin-top: 32px; padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <h4 style="font-family: var(--font-heading); font-size: 15px; font-weight: 800; margin-bottom: 16px;">Gửi phản hồi của bạn</h4>
        
        <?php if ($me): ?>
            <form action="" method="POST" style="display: flex; gap: 12px; align-items: flex-start;">
                <input type="hidden" name="action_create_reply" value="1">
                
                <?php 
                $identity = getCurrentIdentity();
                $avatar_to_show = ($identity) ? $identity['avatar'] : $me['avatar_filename'];
                ?>
                <img src="<?php echo AVATARS_URL . '/' . sanitize($avatar_to_show); ?>" 
                     style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                     
                <div style="flex: 1; display: flex; flex-direction: column; gap: 12px;">
                    <textarea name="reply_content" id="reply_content" class="form-input" 
                              style="height: 80px; padding: 10px 14px; font-size: 14px; resize: none; width: 100%; background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-sizing: border-box;" 
                              placeholder="Bạn nghĩ gì về Frest này?..." required></textarea>
                    
                    <button type="submit" class="btn-primary" 
                            style="align-self: flex-end; padding: 8px 20px; font-size: 12.5px; border-radius: var(--radius-full); font-weight: 700; width: auto;">
                        Gửi phản hồi
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center" style="padding: 10px 0;">
                <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 12px;">Đăng nhập để viết phản hồi bài đăng này.</p>
                <a href="login.php" class="btn-primary" style="padding: 8px 24px; font-size: 12.5px; border-radius: var(--radius-full);">Đăng nhập ngay</a>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/detail.js?v=<?php echo time(); ?>"></script>

