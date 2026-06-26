<?php
/**
 * Search Page - Frest App
 */
require_once __DIR__ . '/includes/header.php';

$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$users = [];
$posts = [];
$error_msg = '';

try {
    $db = getDB();

    if (!empty($query)) {
        // 1. Search Users
        $user_stmt = $db->prepare("SELECT * FROM users WHERE username LIKE ? OR bio LIKE ? OR full_name LIKE ? LIMIT 10");
        $user_stmt->execute(['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']);
        $users = $user_stmt->fetchAll();

        // 1.b Search Pages
        $pages_stmt = $db->prepare("SELECT * FROM pages WHERE page_name LIKE ? OR page_username LIKE ? OR bio LIKE ? LIMIT 10");
        $pages_stmt->execute(['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']);
        $pages = $pages_stmt->fetchAll();

        // 2. Search Posts
        $me_id = $me ? intval($me['id']) : 0;
        $is_admin = isAdminLoggedIn() ? 1 : 0;
        $post_stmt = $db->prepare("
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
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN pages pg ON p.page_id = pg.id
            WHERE p.content LIKE ? 
              AND (p.page_id IS NOT NULL
                   OR u.is_private = 0 
                   OR p.user_id = ? 
                   OR ? = 1 
                   OR p.user_id IN (SELECT followed_id FROM follows WHERE follower_id = ?))
            ORDER BY p.created_at DESC LIMIT 20
        ");
        $post_stmt->execute([$me_id, '%' . $query . '%', $me_id, $is_admin, $me_id]);
        $posts = $post_stmt->fetchAll();

        $post_reactions_map = [];
        $original_posts_map = [];
        $user_reposted_map = [];

        if (!empty($posts)) {
            $post_ids = array_column($posts, 'id');
            $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

            // 1. Fetch reactions summary
            $react_stmt = $db->prepare("
                SELECT post_id, reaction_type, COUNT(*) as qty 
                FROM reactions 
                WHERE post_id IN ($placeholders)
                GROUP BY post_id, reaction_type
                ORDER BY qty DESC
            ");
            $react_stmt->execute($post_ids);
            $raw_reacts = $react_stmt->fetchAll();
            foreach ($raw_reacts as $r) {
                $pid = intval($r['post_id']);
                if (!isset($post_reactions_map[$pid])) {
                    $post_reactions_map[$pid] = [];
                }
                if (count($post_reactions_map[$pid]) < 3) {
                    $post_reactions_map[$pid][] = $r['reaction_type'];
                }
            }

            // 2. Fetch original posts for reposts
            $repost_ids = array_filter(array_unique(array_column($posts, 'repost_of_post_id')));
            if (!empty($repost_ids)) {
                $repost_placeholders = implode(',', array_fill(0, count($repost_ids), '?'));
                $orig_stmt = $db->prepare("
                    SELECT p.*, 
                           COALESCE(pg.page_username, u.username) AS username, 
                           COALESCE(pg.avatar_filename, u.avatar_filename) AS avatar_filename, 
                           IF(p.page_id IS NOT NULL, 'none', u.verification_type) AS verification_type, 
                           COALESCE(pg.page_name, u.full_name, u.username) AS full_name,
                           u.is_page AS is_user_page
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN pages pg ON p.page_id = pg.id
                    WHERE p.id IN ($repost_placeholders)
                ");
                $orig_stmt->execute(array_values($repost_ids));
                $orig_rows = $orig_stmt->fetchAll();
                foreach ($orig_rows as $row) {
                    $original_posts_map[intval($row['id'])] = $row;
                }
            }

            // 3. Fetch user reposted status
            if ($me) {
                $identity = getCurrentIdentity();
                if ($identity && $identity['type'] === 'page') {
                    $user_repost_stmt = $db->prepare("
                        SELECT repost_of_post_id 
                        FROM posts 
                        WHERE user_id = ? AND page_id = ? AND repost_of_post_id IN ($placeholders) AND (content = '' OR content IS NULL)
                    ");
                    $user_repost_stmt->execute(array_merge([$me['id'], $identity['id']], $post_ids));
                } else {
                    $user_repost_stmt = $db->prepare("
                        SELECT repost_of_post_id 
                        FROM posts 
                        WHERE user_id = ? AND page_id IS NULL AND repost_of_post_id IN ($placeholders) AND (content = '' OR content IS NULL)
                    ");
                    $user_repost_stmt->execute(array_merge([$me['id']], $post_ids));
                }
                $reposted_ids = $user_repost_stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($reposted_ids as $rpid) {
                    $user_reposted_map[intval($rpid)] = true;
                }
            }
        }
    } else {
        // If query is empty, suggest some popular users to follow
        $user_stmt = $db->query("SELECT * FROM users ORDER BY RAND() LIMIT 5");
        $users = $user_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Lỗi truy vấn tìm kiếm: " . $e->getMessage();
}
?>

<div class="container" style="max-width: 680px; padding-top: 24px;">
    
    <!-- Search Bar Form -->
    <div class="checkout-card" style="padding: 24px; margin-bottom: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <form action="search.php" method="GET" style="display: flex; gap: 12px; margin-bottom: 0; align-items: center;">
            <div style="flex: 1; position: relative;">
                <input type="text" name="q" id="search-input" class="form-input" autocomplete="off" placeholder="Tìm kiếm người dùng hoặc Frest..." value="<?php echo htmlspecialchars($query); ?>" style="width: 100%; border-radius: var(--radius-full); background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); padding: 10px 18px; box-sizing: border-box;" required>
                <div id="search-suggestions" class="search-suggestions-box"></div>
            </div>
            <button type="submit" class="btn-primary" style="width: auto; padding: 0 24px; border-radius: var(--radius-full); font-weight: 700; font-size: 13.5px; height: 42px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px var(--accent-glow);">Tìm kiếm</button>
        </form>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 20px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- User Search Results -->
    <h3 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
        <?php echo empty($query) ? 'Đề xuất thành viên nổi bật' : 'Người dùng'; ?>
    </h3>
    
    <div style="margin-bottom: 32px; display: flex; flex-direction: column; gap: 14px;">
        <?php if (empty($users)): ?>
            <p style="color: var(--text-muted); font-size: 13.5px; font-style: italic; text-align: center; padding: 10px 0;">Không tìm thấy người dùng nào.</p>
        <?php else: ?>
            <?php foreach ($users as $user): 
                $user_id = $user['id'];
                $is_me = ($me && $me['id'] === $user_id);
                $is_following = false;
                if ($me && !$is_me) {
                    $is_following = isFollowingUser($me['id'], $user_id);
                }
            ?>
                <div class="user-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <div class="user-item-info" style="display: flex; align-items: center; gap: 12px; overflow: hidden; flex: 1;">
                        <a href="profile.php?username=<?php echo sanitize($user['username']); ?>">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($user['avatar_filename']); ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                        </a>
                        <div style="overflow: hidden;">
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <a href="profile.php?username=<?php echo sanitize($user['username']); ?>" class="user-item-name" style="font-weight: 700; font-size: 14.5px; color: var(--text-primary); text-decoration: none;">
                                    <?php echo !empty($user['full_name']) ? sanitize($user['full_name']) : '@' . sanitize($user['username']); ?>
                                </a>
                                <?php if (!empty($user['full_name'])): ?>
                                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">@<?php echo sanitize($user['username']); ?></span>
                                <?php endif; ?>
                                <?php echo renderAuthorBadgeHTML($user['verification_type'], $user['username'], null, intval($user['is_page'] ?? 0) === 1); ?>
                            </div>
                            <div class="user-item-bio" style="font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;"><?php echo empty($user['bio']) ? 'Chưa có tiểu sử.' : sanitize($user['bio']); ?></div>
                        </div>
                    </div>
                    
                    <div style="margin-left: 12px; flex-shrink: 0;">
                        <?php if ($is_me): ?>
                            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Bạn</span>
                        <?php else: ?>
                            <?php if ($me): ?>
                                <button class="follow-action-btn" 
                                        data-user-id="<?php echo $user_id; ?>">
                                    <i class="fa-solid <?php echo $is_following ? 'fa-circle-check' : 'fa-circle-plus'; ?>"></i>
                                    <span class="btn-text">Theo dõi</span>
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="follow-action-btn" style="display: inline-flex; text-decoration: none; gap: 6px;">
                                    <i class="fa-solid fa-circle-plus"></i> Theo dõi
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Page Search Results -->
    <?php if (!empty($query)): ?>
        <h3 style="font-family: var(--font-heading); font-size: 16px; margin-top: 24px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
            Trang (Pages)
        </h3>
        
        <div style="margin-bottom: 32px; display: flex; flex-direction: column; gap: 14px;">
            <?php if (empty($pages)): ?>
                <p style="color: var(--text-muted); font-size: 13.5px; font-style: italic; text-align: center; padding: 10px 0;">Không tìm thấy trang nào.</p>
            <?php else: ?>
                <?php foreach ($pages as $page): 
                    $page_id = $page['id'];
                    $is_following_page = false;
                    if ($me) {
                        $is_following_page_stmt = $db->prepare("SELECT COUNT(*) FROM page_follows WHERE user_id = ? AND page_id = ?");
                        $is_following_page_stmt->execute([$me['id'], $page_id]);
                        $is_following_page = ($is_following_page_stmt->fetchColumn() > 0);
                    }
                ?>
                    <div class="user-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <div class="user-item-info" style="display: flex; align-items: center; gap: 12px; overflow: hidden; flex: 1;">
                            <a href="page.php?username=<?php echo sanitize($page['page_username']); ?>">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($page['avatar_filename']); ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                            </a>
                            <div style="overflow: hidden;">
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <a href="page.php?username=<?php echo sanitize($page['page_username']); ?>" class="user-item-name" style="font-weight: 700; font-size: 14.5px; color: var(--text-primary); text-decoration: none;">
                                        <?php echo sanitize($page['page_name']); ?>
                                    </a>
                                    <?php echo getPageVerificationBadgeHTML($page['id'], false); ?>
                                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">@<?php echo sanitize($page['page_username']); ?></span>
                                    <span class="badge" style="font-size: 9.5px; padding: 1px 6px; background: rgba(59, 130, 246, 0.15); color: var(--accent-primary); font-weight: 700; border-radius: 50px; border: 1px solid rgba(59, 130, 246, 0.25);">Trang</span>
                                </div>
                                <div style="font-size: 11.5px; color: var(--accent-primary); font-weight: 600; margin-top: 2px; text-align: left;">
                                    <?php echo sanitize($page['category'] ?: 'Cộng đồng'); ?>
                                </div>
                                <div class="user-item-bio" style="font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px; margin-top: 4px;"><?php echo empty($page['bio']) ? 'Chưa có tiểu sử.' : sanitize($page['bio']); ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-left: 12px; flex-shrink: 0;">
                            <?php if ($me): ?>
                                <button class="follow-action-btn" 
                                        data-page-id="<?php echo $page_id; ?>">
                                    <i class="fa-solid <?php echo $is_following_page ? 'fa-circle-check' : 'fa-circle-plus'; ?>"></i>
                                    <span class="btn-text">Theo dõi</span>
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="follow-action-btn" style="display: inline-flex; text-decoration: none; gap: 6px;">
                                    <i class="fa-solid fa-circle-plus"></i> Theo dõi
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Posts Search Results -->
    <?php if (!empty($query)): ?>
        <h3 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Bài viết liên quan</h3>
        
        <div class="feed-container">
            <?php if (empty($posts)): ?>
                <p style="color: var(--text-muted); font-size: 13.5px; font-style: italic; text-align: center; padding: 20px 0;">Không tìm thấy bài viết nào chứa từ khóa này.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): 
                    $post_id = $post['id'];
                    $post_url_id = !empty($post['post_token']) ? $post['post_token'] : $post['id'];
                    
                    // Get replies count (From eager loaded query)
                    $replies_count = intval($post['replies_count'] ?? 0);

                    // Get active user reaction (From eager loaded query)
                    $active_reaction = $post['active_reaction'] ?: false;
                    $reacted_class = $active_reaction ? 'active' : '';

                    // Get post reactions summary (From eager loaded maps/variables)
                    $reactions_summary = [
                        'total' => intval($post['reactions_total'] ?? 0),
                        'types' => $post_reactions_map[intval($post_id)] ?? []
                    ];
                    $emojis = [
                        'like' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍">',
                        'love' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️">',
                        'haha' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂">',
                        'wow' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮">',
                        'sad' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢">',
                        'angry' => '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡">'
                    ];

                    // Get reposts count (From eager loaded query)
                    $reposts_count = intval($post['reposts_count'] ?? 0);

                    // Check if current user reposted this under active identity (From eager loaded status map)
                    $target_repost_id = !empty($post['repost_of_post_id']) ? intval($post['repost_of_post_id']) : intval($post_id);
                    $user_reposted = isset($user_reposted_map[$target_repost_id]);

                    // Fetch original post if it's a repost (From eager loaded map)
                    $original_post = !empty($post['repost_of_post_id']) ? ($original_posts_map[intval($post['repost_of_post_id'])] ?? null) : null;
                ?>
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
                    $glow_class = ($post_id % 2 === 0) ? 'glowing-card-cyan' : 'glowing-card-purple';
                    ?>
                    <div class="frest-card <?php echo $glow_class; ?> <?php echo $is_my_repost ? 'my-repost-card' : ''; ?>" data-post-id="<?php echo $post_id; ?>" data-post-token="<?php echo $post_url_id; ?>" <?php if (!empty($post['repost_of_post_id'])) { echo 'data-repost-of-id="' . $post['repost_of_post_id'] . '"'; } ?>>
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
                                     <!-- Ellipsis dropdown -->
                                     <div class="ellipsis-menu-container">
                                         <button class="ellipsis-btn"><i class="fa-solid fa-ellipsis"></i></button>
                                         <div class="ellipsis-dropdown">
                                              <?php if ($can_edit): ?>
                                                  <div class="ellipsis-item pin-post-trigger" data-post-id="<?php echo $post_id; ?>" data-pinned="<?php echo intval($post['is_pinned'] ?? 0); ?>">
                                                      <i class="fa-solid fa-thumbtack"></i> 
                                                      <span><?php echo (intval($post['is_pinned'] ?? 0) === 1) ? 'Bỏ ghim' : 'Ghim bài viết'; ?></span>
                                                  </div>
                                                  <div class="ellipsis-item edit-post-trigger" data-post-id="<?php echo $post_id; ?>" data-content="<?php echo sanitize($post['content']); ?>">
                                                      <i class="fa-regular fa-pen-to-square"></i> Chỉnh sửa
                                                  </div>
                                              <?php endif; ?>
                                              <?php if ($can_delete): ?>
                                                  <div class="ellipsis-item delete delete-post-trigger" data-post-id="<?php echo $post_id; ?>">
                                                      <i class="fa-regular fa-trash-can"></i> Xóa bài
                                                  </div>
                                              <?php endif; ?>
                                              <?php if ($can_report): ?>
                                                  <div class="ellipsis-item report-trigger-post-btn" data-post-id="<?php echo $post_id; ?>" style="color: var(--danger);">
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
                                 <div class="frest-content" onclick="window.location.href='detail.php?id=<?php echo $post_url_id; ?>';" style="cursor: pointer; text-align: left;"><?php echo nl2br(parseHashtags(linkify(sanitize($post['content'])))); ?></div>
                             <?php endif; ?>

                             <?php 
                             $is_nsfw_post = (isset($post['is_nsfw']) && intval($post['is_nsfw']) === 1);
                             $user_show_nsfw = false;
                             if ($me) {
                                 $user_show_nsfw = (intval($me['show_nsfw'] ?? 0) === 1);
                             }
                             $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                             
                             // Render media or repost card
                             if (!empty($post['repost_of_post_id'])) {
                                 if ($original_post) {
                                     // Render repost's own media first (if any)
                                     echo renderPostMediaHTML($post, $should_blur_nsfw);

                                     // Render original post as embedded card
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
                                     echo '  <div style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 8px; text-align: left; line-height: 1.45;">' . nl2br(parseHashtags(linkify(sanitize($original_post['content'])))) . '</div>';
                                     // Original post media
                                     echo renderPostMediaHTML($original_post, $orig_should_blur);
                                     echo renderPollHTML($original_post['id'], $me['id'] ?? null);
                                     echo renderLinkPreviewCard($original_post);
                                     echo '</div>';
                                 } else {
                                     echo '<div class="repost-card-deleted" style="border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 12px; background: rgba(255, 255, 255, 0.01); margin-top: 8px; font-style: italic; font-size: 12.5px; color: var(--text-muted); text-align: left;">Bài viết gốc không khả dụng hoặc đã bị xóa.</div>';
                                 }
                             } else {
                                 // Render this post's media
                                 echo renderPostMediaHTML($post, $should_blur_nsfw);
                                 echo renderPollHTML($post['id'], $me['id'] ?? null);
                                 echo renderLinkPreviewCard($post);
                             }
                             ?>
                             <!-- Social Actions -->
                             <div class="frest-actions" style="margin-top: 14px; display: flex; gap: 16px;">
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
                                 
                                 <button class="frest-action-btn repost-btn repost-action-trigger <?php echo $user_reposted ? 'reposted' : ''; ?>" data-post-id="<?php echo !empty($post['repost_of_post_id']) ? $post['repost_of_post_id'] : $post_id; ?>" title="Đăng lại bài viết" style="<?php echo $user_reposted ? 'color: var(--success);' : ''; ?>">
                                     <i class="fa-solid fa-retweet"></i>
                                     <?php if ($reposts_count > 0): ?>
                                         <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reposts_count; ?></span>
                                     <?php endif; ?>
                                 </button>
  
                                  <button class="frest-action-btn share-btn copy-share-link" data-url="<?php echo SITE_URL . '/detail.php?id=' . $post_url_id; ?>">
                                      <i class="fa-regular fa-paper-plane"></i>
                                  </button>
                                  
                                  <button class="frest-action-btn bookmark-btn <?php echo isPostBookmarked($post_id, $me_id) ? 'bookmarked' : ''; ?>" 
                                          onclick="event.stopPropagation(); toggleBookmark(this, <?php echo $post_id; ?>);" 
                                          title="Lưu bài viết"
                                          style="<?php echo isPostBookmarked($post_id, $me_id) ? 'color: var(--accent-primary);' : ''; ?>">
                                      <i class="<?php echo isPostBookmarked($post_id, $me_id) ? 'fa-solid' : 'fa-regular'; ?> fa-bookmark"></i>
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
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

