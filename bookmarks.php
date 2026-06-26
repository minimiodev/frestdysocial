<?php
/**
 * Bookmarked Posts (Saved) - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    header("Location: login.php");
    exit;
}

$me_id = intval($me['id']);
$db = getDB();

// Fetch bookmarked posts
try {
    $posts_stmt = $db->prepare("
        SELECT p.*, 
               COALESCE(pg.page_username, u.username) AS username, 
               COALESCE(pg.avatar_filename, u.avatar_filename) AS avatar_filename, 
               IF(p.page_id IS NOT NULL, 'none', u.verification_type) AS verification_type, 
               COALESCE(pg.page_name, u.full_name, u.username) AS full_name,
               u.is_page AS is_user_page,
               (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) AS replies_count,
               (SELECT COUNT(*) FROM posts rp WHERE rp.repost_of_post_id = p.id) AS reposts_count,
               (SELECT reaction_type FROM reactions re WHERE re.post_id = p.id AND re.user_id = ? LIMIT 1) AS active_reaction,
               (SELECT COUNT(*) FROM reactions re WHERE re.post_id = p.id) AS reactions_total
        FROM bookmarks b
        JOIN posts p ON b.post_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN pages pg ON p.page_id = pg.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $posts_stmt->execute([$me_id, $me_id]);
    $posts = $posts_stmt->fetchAll();
    
    // Eager load reaction types detail map (similar to index.php logic)
    $post_ids = array_map(function($p) { return intval($p['id']); }, $posts);
    $post_reactions_map = [];
    if (!empty($post_ids)) {
        $in_clause = implode(',', $post_ids);
        $reac_stmt = $db->query("
            SELECT post_id, reaction_type, COUNT(*) AS count 
            FROM reactions 
            WHERE post_id IN ($in_clause) 
            GROUP BY post_id, reaction_type
        ");
        while ($row = $reac_stmt->fetch()) {
            $post_reactions_map[intval($row['post_id'])][] = [
                'type' => $row['reaction_type'],
                'count' => intval($row['count'])
            ];
        }

        // Eager load original posts for reposts
        $original_posts_map = [];
        $repost_ids = array_filter(array_unique(array_column($posts, 'repost_of_post_id')));
        if (!empty($repost_ids)) {
            $repost_placeholders = implode(',', array_fill(0, count($repost_ids), '?'));
            $orig_stmt = $db->prepare("
                SELECT p.*, u.username, u.avatar_filename, u.verification_type, u.full_name, pg.page_name, pg.avatar_filename AS pg_avatar, pg.category AS pg_category, pg.is_verified AS pg_verified
                FROM posts p
                JOIN users u ON p.user_id = u.id
                LEFT JOIN pages pg ON p.page_id = pg.id
                WHERE p.id IN ($repost_placeholders)
            ");
            $orig_stmt->execute(array_values($repost_ids));
            while ($row = $orig_stmt->fetch(PDO::FETCH_ASSOC)) {
                $original_posts_map[intval($row['id'])] = $row;
            }
        }
    }
} catch (PDOException $e) {
    $posts = [];
    $error_msg = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
}

$page_title = "Frest đã lưu";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="max-width: 600px; margin: 0 auto; padding-bottom: 100px; padding-top: 24px;">
    <h3 style="font-family: var(--font-heading); font-size: 20px; font-weight: 800; color: var(--text-primary); margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
        <i class="fa-solid fa-bookmark" style="color: var(--accent-primary);"></i> Bài viết đã lưu
    </h3>

    <?php if (empty($posts)): ?>
        <div class="frest-card" style="padding: 48px 24px; text-align: center;">
            <i class="fa-regular fa-bookmark" style="font-size: 48px; color: var(--text-muted); opacity: 0.3; margin-bottom: 16px; display: block;"></i>
            <p style="font-size: 14.5px; color: var(--text-secondary); margin: 0;">Bạn chưa lưu bài viết nào.</p>
            <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 6px;">Hãy nhấp vào biểu tượng Lưu trên bất kỳ bài đăng nào để xem lại ở đây.</p>
        </div>
    <?php else: ?>
        <div class="feed-container">
            <?php foreach ($posts as $post): 
                $post_id = intval($post['id']);
                $post_url_id = !empty($post['post_token']) ? $post['post_token'] : $post['id'];
                $reacted_class = !empty($post['active_reaction']) ? 'active reacted-' . $post['active_reaction'] : '';
                $active_reaction = $post['active_reaction'];
                $replies_count = intval($post['replies_count']);
                $reposts_count = intval($post['reposts_count']);
                
                $reactions_summary = [
                    'total' => intval($post['reactions_total'] ?? 0),
                    'types' => $post_reactions_map[$post_id] ?? []
                ];
                
                $emojis = [
                    'like' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍">',
                    'love' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️">',
                    'haha' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂">',
                    'wow' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮">',
                    'sad' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢">',
                    'angry' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡">'
                ];
                
                $is_my_repost = false;
                $original_post = null;
                if (!empty($post['repost_of_post_id'])) {
                    $original_post = $original_posts_map[intval($post['repost_of_post_id'])] ?? null;
                }
                
                $glow_class = ($post_id % 2 === 0) ? 'glowing-card-cyan' : 'glowing-card-purple';
            ?>
                <div class="frest-card <?php echo $glow_class; ?> <?php echo $is_my_repost ? 'my-repost-card' : ''; ?>" data-post-id="<?php echo $post_id; ?>" data-post-token="<?php echo $post_url_id; ?>">
                    <div class="frest-left">
                        <a href="<?php echo getProfileUrl($post['username'], $post['page_id']); ?>">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($post['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
                        </a>
                        <div class="frest-line"></div>
                    </div>
                    <div class="frest-right">
                        <div class="frest-header">
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <a href="<?php echo getProfileUrl($post['username'], $post['page_id']); ?>" class="frest-author">
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
                        </div>

                        <?php if (!empty($post['content'])): ?>
                            <div class="frest-content" onclick="window.location.href='detail.php?id=<?php echo $post_url_id; ?>';" style="cursor: pointer; text-align: left;"><?php echo nl2br(parseHashtags(linkify(sanitize($post['content'])))); ?></div>
                        <?php endif; ?>

                        <?php 
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
                                $original_post_url_id = !empty($original_post['post_token']) ? $original_post['post_token'] : $original_post['id'];
                                
                                echo '<div class="repost-card" onclick="event.stopPropagation(); window.location.href=\'detail.php?id=' . $original_post_url_id . '\';" style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; background: rgba(255, 255, 255, 0.015); margin-top: 10px; cursor: pointer; transition: background 0.2s, border-color 0.2s; position: relative;">';
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

                        <!-- Interactive Social Action Bar -->
                        <div class="frest-actions">
                            <div class="reaction-container" data-post-id="<?php echo $post_id; ?>">
                                <button class="frest-action-btn react-btn <?php echo $reacted_class; ?>" 
                                        data-post-id="<?php echo $post_id; ?>" 
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
                            
                            <button class="frest-action-btn reply-btn" onclick="window.location.href='detail.php?id=<?php echo $post_url_id; ?>#reply-composer';">
                                <i class="fa-regular fa-comment"></i>
                                <?php if ($replies_count > 0): ?>
                                    <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $replies_count; ?></span>
                                  <?php endif; ?>
                            </button>

                            <button class="frest-action-btn repost-btn repost-action-trigger" data-post-id="<?php echo $post_id; ?>" title="Đăng lại bài viết">
                                <i class="fa-solid fa-retweet"></i>
                                <?php if ($reposts_count > 0): ?>
                                    <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reposts_count; ?></span>
                                <?php endif; ?>
                            </button>

                            <button class="frest-action-btn share-btn copy-share-link" data-url="<?php echo SITE_URL . '/detail.php?id=' . $post_url_id; ?>">
                                <i class="fa-regular fa-paper-plane"></i>
                            </button>

                            <button class="frest-action-btn bookmark-btn bookmarked" 
                                     onclick="event.stopPropagation(); toggleBookmark(this, <?php echo $post_id; ?>);" 
                                     title="Bỏ lưu bài viết"
                                     style="color: var(--accent-primary);">
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
