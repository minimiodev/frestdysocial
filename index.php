<?php
/**
 * Main Feed Page - Frest App
 */
require_once __DIR__ . '/includes/header.php';

$error_msg = '';

// Handle creating a new post (Frest)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_post'])) {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    $content = trim($_POST['content'] ?? '');
    $user_id = getLoggedInUserId();

    if (empty($content)) {
        $error_msg = "Nội dung bài viết không được trống.";
    } else {
        try {
            $db = getDB();
            
            // Handle media files upload (image/video/audio/document/software)
            $uploaded_images = [];
            $video_filename = null;
            $audio_filename = null;
            $document_filename = null;
            $software_filename = null;

            if (isset($_FILES['post_media'])) {
                $files = [];
                if (is_array($_FILES['post_media']['name'])) {
                    $file_count = count($_FILES['post_media']['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['post_media']['error'][$i] === UPLOAD_ERR_OK) {
                            $files[] = [
                                'name' => $_FILES['post_media']['name'][$i],
                                'type' => $_FILES['post_media']['type'][$i],
                                'tmp_name' => $_FILES['post_media']['tmp_name'][$i],
                                'error' => $_FILES['post_media']['error'][$i],
                                'size' => $_FILES['post_media']['size'][$i]
                            ];
                        }
                    }
                } else {
                    if ($_FILES['post_media']['error'] === UPLOAD_ERR_OK) {
                        $files[] = $_FILES['post_media'];
                    }
                }

                foreach ($files as $file) {
                    $file_tmp = $file['tmp_name'];
                    $file_name = $file['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $video_exts = ['mp4', 'webm', 'mov', 'ogg'];
                    $audio_exts = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
                    $document_exts = ['pdf', 'docx', 'doc', 'txt', 'xlsx', 'pptx'];
                    $software_exts = ['zip', 'apk', 'exe', 'rar', 'tar', 'gz'];

                    $user_posts_dir = getUserUploadPath($me['username'], 'posts');
                    $db_save_prefix = 'users/' . $me['username'] . '/';

                    if (in_array($file_ext, $image_exts)) {
                        $new_name = 'post_' . uniqid() . '.' . $file_ext;
                        $dest = $user_posts_dir . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $uploaded_images[] = $db_save_prefix . $new_name;
                        }
                    } elseif (in_array($file_ext, $video_exts) && empty($video_filename)) {
                        $new_name = 'post_' . uniqid() . '.' . $file_ext;
                        $dest = $user_posts_dir . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $video_filename = $db_save_prefix . $new_name;
                            triggerVideoTranscode($video_filename);
                        }
                    } elseif (in_array($file_ext, $audio_exts) && empty($audio_filename)) {
                        $new_name = 'post_' . uniqid() . '.' . $file_ext;
                        $dest = $user_posts_dir . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $audio_filename = $db_save_prefix . $new_name;
                        }
                    } elseif (in_array($file_ext, $document_exts) && empty($document_filename)) {
                        $new_name = 'post_' . uniqid() . '.' . $file_ext;
                        $dest = $user_posts_dir . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $document_filename = $db_save_prefix . $new_name;
                        }
                    } elseif (in_array($file_ext, $software_exts) && empty($software_filename)) {
                        $new_name = 'post_' . uniqid() . '.' . $file_ext;
                        $dest = $user_posts_dir . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $software_filename = $db_save_prefix . $new_name;
                        }
                    }
                }
            }
            $image_filename = !empty($uploaded_images) ? implode(',', $uploaded_images) : null;

            $link_preview_url   = !empty($_POST['link_preview_url']) ? trim($_POST['link_preview_url']) : null;
            $link_preview_title = !empty($_POST['link_preview_title']) ? trim($_POST['link_preview_title']) : null;
            $link_preview_desc  = !empty($_POST['link_preview_desc']) ? trim($_POST['link_preview_desc']) : null;
            $link_preview_image = !empty($_POST['link_preview_image']) ? trim($_POST['link_preview_image']) : null;

            if (empty($link_preview_url)) {
                if (preg_match('/https?:\/\/[^\s]+/i', $content, $matches)) {
                    $detected_url = $matches[0];
                    require_once __DIR__ . '/fetch_link_preview.php';
                    $preview = fetchLinkPreview($detected_url);
                    if (!empty($preview['title'])) {
                        $link_preview_url   = $preview['url'] ?? null;
                        $link_preview_title = $preview['title'] ?? null;
                        $link_preview_desc  = $preview['description'] ?? null;
                        $link_preview_image = $preview['image'] ?? null;
                    }
                }
            }

            $identity = getCurrentIdentity();
            $page_id = ($identity && $identity['type'] === 'page') ? $identity['id'] : null;

            $allow_download = isset($_POST['allow_download']) ? 1 : 0;
            $is_nsfw = isset($_POST['is_nsfw']) ? 1 : 0;
            
            $post_token = bin2hex(random_bytes(8));
            $stmt = $db->prepare("INSERT INTO posts (user_id, content, image_filename, video_filename, audio_filename, document_filename, software_filename, allow_download, is_nsfw, link_preview_url, link_preview_title, link_preview_desc, link_preview_image, page_id, post_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $content, $image_filename, $video_filename, $audio_filename, $document_filename, $software_filename, $allow_download, $is_nsfw, $link_preview_url, $link_preview_title, $link_preview_desc, $link_preview_image, $page_id, $post_token]);
            
            $post_id = $db->lastInsertId();
            
            // 1. Trích xuất và lưu Hashtag
            extractAndSaveHashtags($post_id, $content);
            
            // 2. Lưu Thăm dò ý kiến (Poll) nếu có
            $poll_question = !empty($_POST['poll_question']) ? trim($_POST['poll_question']) : '';
            $poll_options = !empty($_POST['poll_options']) ? $_POST['poll_options'] : [];
            if (!empty($poll_question) && count($poll_options) >= 2) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt_poll = $db->prepare("INSERT INTO polls (post_id, question, expires_at) VALUES (?, ?, ?)");
                $stmt_poll->execute([$post_id, $poll_question, $expires_at]);
                $poll_id = $db->lastInsertId();
                
                foreach ($poll_options as $opt_text) {
                    $opt_text = trim($opt_text);
                    if (empty($opt_text)) continue;
                    $stmt_opt = $db->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
                    $stmt_opt->execute([$poll_id, $opt_text]);
                }
            }

            echo "<script>
                localStorage.setItem('post_created', '1');
                window.location.href = 'index.php';
            </script>";
            exit;

        } catch (PDOException $e) {
            $error_msg = "Lỗi đăng bài viết: " . $e->getMessage();
        }
    }
}

// Load all posts/frests (Optimized with Eager Loading & Pagination)
$posts = [];
$post_reactions_map = [];
$original_posts_map = [];
$user_reposted_map = [];

try {
    $db = getDB();
    $me_id = $me ? intval($me['id']) : 0;
    $is_admin = isAdminLoggedIn() ? 1 : 0;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $identity = getCurrentIdentity();
    $block_cond = getBlockConditionsSQL($identity, 'p.user_id', 'p.page_id');

    // Get total posts for pagination
    $total_posts_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN pages pg ON p.page_id = pg.id
        WHERE (p.page_id IS NOT NULL 
           OR u.is_private = 0 
           OR p.user_id = ? 
           OR ? = 1 
           OR p.user_id IN (SELECT followed_id FROM follows WHERE follower_id = ?))
           AND $block_cond
    ");
    $total_posts_stmt->execute([$me_id, $is_admin, $me_id]);
    $total_posts = intval($total_posts_stmt->fetchColumn());
    $total_pages = ceil($total_posts / $limit);

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
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN pages pg ON p.page_id = pg.id
        WHERE (p.page_id IS NOT NULL 
           OR u.is_private = 0 
           OR p.user_id = ? 
           OR ? = 1 
           OR p.user_id IN (SELECT followed_id FROM follows WHERE follower_id = ?))
           AND $block_cond
        ORDER BY p.created_at DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ");
    $posts_stmt->execute([$me_id, $me_id, $is_admin, $me_id]);

    $posts = $posts_stmt->fetchAll();

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
} catch (PDOException $e) {
    $error_msg = "Không thể tải bảng tin: " . $e->getMessage();
    $posts = [];
}

// Get suggestions for who to follow
$recommendations = [];
try {
    if ($me) {
        $rec_stmt = $db->prepare("SELECT * FROM users WHERE id != ? AND id NOT IN (SELECT followed_id FROM follows WHERE follower_id = ?) ORDER BY RAND() LIMIT 3");
        $rec_stmt->execute([$me['id'], $me['id']]);
    } else {
        $rec_stmt = $db->query("SELECT * FROM users ORDER BY RAND() LIMIT 3");
    }
    $recommendations = $rec_stmt->fetchAll();
} catch (Exception $e) {}

// Get page suggestions (Pages to follow)
$page_recommendations = [];
try {
    if ($me) {
        $page_rec_stmt = $db->prepare("SELECT * FROM pages WHERE owner_id != ? AND id NOT IN (SELECT page_id FROM page_follows WHERE user_id = ?) ORDER BY RAND() LIMIT 3");
        $page_rec_stmt->execute([$me['id'], $me['id']]);
    } else {
        $page_rec_stmt = $db->query("SELECT * FROM pages ORDER BY RAND() LIMIT 3");
    }
    $page_recommendations = $page_rec_stmt->fetchAll();
} catch (Exception $e) {}

// Get system metrics
$stats_frests = 0;
$stats_users = 0;
try {
    $stats_frests = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stats_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {}
?>

<div class="container" style="padding-top: 24px;">


    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 20px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Split-View Layout Grid -->
    <div class="split-layout-grid">
        
        <!-- Left Panel: Feed Column -->
        <div class="feed-column">
            
            <!-- Stories Bar -->
            <div class="stories-container">
                <div class="stories-scroll-wrapper" id="stories-scroll-wrapper">
                    <?php if ($me): ?>
                        <div class="story-item current-user-story">
                            <div class="story-avatar-wrapper">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" class="story-avatar" alt="My Avatar">
                                <div class="add-story-badge"><i class="fa-solid fa-plus"></i></div>
                            </div>
                            <span class="story-username">Tin của bạn</span>
                        </div>
                    <?php endif; ?>
                    <!-- Danh sách stories từ người dùng khác sẽ được load qua AJAX trong stories.js -->
                </div>
            </div>
            
            <!-- Quick composer placeholder box -->
            <?php if ($me): ?>
                <div class="frest-card" style="border-bottom: 1px solid var(--border-color); padding-bottom: 24px; cursor: pointer; margin-bottom: 20px;" onclick="window.location.href = 'create_post.php';">
                    <div class="frest-left">
                        <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
                    </div>
                    <div class="frest-right" style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; flex: 1; min-width: 0; gap: 10px;">
                        <span style="color: var(--text-muted); font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Có gì mới? Đăng Frest ngay...</span>
                        <button class="btn-primary" style="padding: 6px 16px; font-size: 13px; border-radius: var(--radius-full); flex-shrink: 0;">Frest</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Feed List -->
            <div class="feed-container">
                <?php if (empty($posts)): ?>
                    <div style="padding: 60px 20px; text-align: center; color: var(--text-secondary);">
                        <i class="fa-solid fa-hashtag" style="font-size: 40px; margin-bottom: 16px; opacity: 0.2;"></i>
                        <p>Chưa có Frest nào được đăng. Hãy là người đầu tiên chia sẻ!</p>
                    </div>
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
                                        <!-- Ellipsis Dropdown Trigger -->
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
                                         echo '  <div style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 8px; text-align: left; line-height: 1.45;">' . nl2br(parseHashtags(linkify(sanitize($original_post['content'])))). '</div>';
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

                                <!-- Interactive Social Action Bar -->
                                <div class="frest-actions" style="margin-top: 14px; display: flex; gap: 16px;">
                                    
                                    <!-- Reaction container with picker -->
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

            <!-- Pagination Grid -->
            <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 24px 0 10px 0; padding: 10px; background: rgba(255,255,255,0.02); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=<?php echo $page - 1; ?>" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);"><i class="fa-solid fa-chevron-left"></i> Trước</a>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 4px;">
                        <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        if ($start_p > 1) {
                            echo '<a href="index.php?page=1" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">1</a>';
                            if ($start_p > 2) {
                                echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                            }
                        }
                        for ($i = $start_p; $i <= $end_p; $i++) {
                            if ($i === $page) {
                                echo '<span class="btn-primary" style="padding: 6px 12px; font-size: 13px; font-weight: 700; border-radius: var(--radius-sm);">' . $i . '</span>';
                            } else {
                                echo '<a href="index.php?page=' . $i . '" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">' . $i . '</a>';
                            }
                        }
                        if ($end_p < $total_pages) {
                            if ($end_p < $total_pages - 1) {
                                echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                            }
                            echo '<a href="index.php?page=' . $total_pages . '" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">' . $total_pages . '</a>';
                        }
                        ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=<?php echo $page + 1; ?>" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">Sau <i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Panel: Desktop Sidebar Widgets (Split-View Canvas) -->
        <div class="sidebar-section">
            
            <!-- Active Groups Widget -->
            <div class="sidebar-widget">
                <div class="widget-title-row">
                    <h4 class="widget-title">Nhóm đang hoạt động</h4>
                    <a href="chat.php" style="font-size: 11.5px; color: var(--accent-primary); text-decoration: none; font-weight: 700; transition: opacity 0.15s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">Xem tất cả</a>
                </div>
                
                <div class="widget-item-list">
                    <?php 
                     try {
                         $db = getDB();
                         $sidebar_groups = $db->query("SELECT * FROM chat_groups LIMIT 3")->fetchAll();
                     } catch (Exception $e) {
                         $sidebar_groups = [];
                     }
                     if (empty($sidebar_groups)) {
                         ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 13px;">
                            <i class="fa-solid fa-users" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                            Chưa có nhóm nào hoạt động
                        </div>
                        <?php
                     } else {
                         foreach ($sidebar_groups as $g) {
                             // Fetch random online count for visual fidelity
                             $mock_online = (intval($g['id']) * 3 + 4) % 15 + 2;
                             ?>
                            <div class="widget-item" onclick="location.href='chat.php?contact_type=group&contact_id=<?php echo $g['id']; ?>'" style="cursor: pointer;">
                                <div class="item-left">
                                    <div class="item-avatar-wrapper">
                                        <img src="<?php echo AVATARS_URL . '/' . sanitize($g['avatar_filename']); ?>" class="item-avatar" onerror="this.src='<?php echo AVATARS_URL; ?>/group_default.png'">
                                        <span class="item-status-dot"></span>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo sanitize($g['name']); ?></div>
                                        <div class="item-meta"><?php echo $mock_online; ?> Trực tuyến</div>
                                    </div>
                                </div>
                            </div>
                            <?php
                         }
                     }
                     ?>
                </div>
                
                <a href="chat.php?action=create_group" class="widget-btn-primary">
                     <i class="fa-solid fa-plus"></i> Tạo nhóm
                </a>
            </div>

            <!-- Notifications Widget -->
            <div class="sidebar-widget">
                <div class="widget-title-row">
                    <h4 class="widget-title">Thông báo</h4>
                    <a href="activity.php" style="font-size: 11.5px; color: var(--accent-primary); text-decoration: none; font-weight: 700; transition: opacity 0.15s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">Xem tất cả</a>
                </div>
                
                <div class="widget-item-list">
                    <?php 
                     $sidebar_notifs = [];
                     if ($me) {
                         try {
                             $db = getDB();
                             $sidebar_notifs_stmt = $db->prepare("
                                SELECT n.*, u.username as actor_username, u.avatar_filename as actor_avatar, u.full_name as actor_name
                                FROM notifications n
                                JOIN users u ON n.actor_id = u.id
                                WHERE n.user_id = ? AND n.is_dismissed = 0
                                ORDER BY n.created_at DESC LIMIT 3
                            ");
                             $sidebar_notifs_stmt->execute([$me['id']]);
                             $sidebar_notifs = $sidebar_notifs_stmt->fetchAll();
                         } catch (Exception $e) {}
                     }
                     
                     if (!$me) {
                         ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 13px;">
                            <i class="fa-regular fa-bell-slash" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                            Vui lòng đăng nhập để xem thông báo
                        </div>
                        <?php
                     } elseif (empty($sidebar_notifs)) {
                         ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 13px;">
                            <i class="fa-regular fa-bell" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                            Chưa có thông báo mới
                        </div>
                        <?php
                     } else {
                         foreach ($sidebar_notifs as $n) {
                             $actor_name = $n['actor_name'] ?: $n['actor_username'];
                             $msg = 'đã tương tác với bạn';
                             if ($n['type'] === 'reaction') {
                                 $msg = 'đã bày tỏ cảm xúc với bài viết của bạn';
                             } elseif ($n['type'] === 'reply') {
                                 $msg = 'đã phản hồi bài viết của bạn';
                             } elseif ($n['type'] === 'follow') {
                                 $msg = 'đã bắt đầu theo dõi bạn';
                             } elseif ($n['type'] === 'repost') {
                                 $msg = 'đã đăng lại bài viết của bạn';
                             }
                             
                             $click_target = 'profile.php?username=' . urlencode($n['actor_username']);
                             if (!empty($n['ref_post_id'])) {
                                 $click_target = 'detail.php?id=' . intval($n['ref_post_id']);
                             }
                             ?>
                            <div class="widget-item" onclick="location.href='<?php echo $click_target; ?>'" style="cursor: pointer;">
                                <div class="item-left">
                                    <div class="item-avatar-wrapper">
                                        <img src="<?php echo AVATARS_URL . '/' . sanitize($n['actor_avatar']); ?>" class="item-avatar rounded-full" onerror="this.src='<?php echo AVATARS_URL; ?>/avatar_default.png'">
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo sanitize($actor_name); ?></div>
                                        <div class="item-meta"><?php echo $msg; ?></div>
                                    </div>
                                </div>
                                <?php if (intval($n['is_read']) === 0): ?>
                                    <span class="item-badge">1</span>
                                <?php endif; ?>
                            </div>
                            <?php
                         }
                     }
                     ?>
                </div>
            </div>

        </div>

    </div>
</div>

    <!-- Story Viewer Modal -->
    <div id="story-viewer-modal" class="story-viewer-modal" style="display: none;">
        <div class="story-viewer-content">
            <!-- Progress indicators at the top -->
            <div class="story-progress-container" id="story-progress-container"></div>
            
            <!-- User Info Header -->
            <div class="story-viewer-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="" id="story-viewer-avatar" class="story-viewer-avatar" alt="Avatar">
                    <div style="display: flex; flex-direction: column; line-height: 1.2;">
                        <span id="story-viewer-username" class="story-viewer-username"></span>
                        <span id="story-viewer-time" class="story-viewer-time"></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 14px; z-index: 35;">
                    <button type="button" class="story-viewer-mute-btn" id="story-viewer-mute-btn" style="display: none; background: none; border: none; color: #fff; font-size: 18px; cursor: pointer; outline: none; padding: 4px; opacity: 0.8; transition: opacity 0.2s;"><i class="fa-solid fa-volume-high"></i></button>
                    <button type="button" class="story-viewer-delete-btn" id="story-viewer-delete-btn" style="display: none; background: none; border: none; color: #ff453a; font-size: 18px; cursor: pointer; outline: none; padding: 4px; opacity: 0.8; transition: opacity 0.2s;"><i class="fa-regular fa-trash-can"></i></button>
                    <button type="button" class="story-viewer-close-btn" id="story-viewer-close-btn">&times;</button>
                </div>
            </div>
            
            <!-- Story Media Display -->
            <div class="story-viewer-media-container" id="story-viewer-media-container"></div>
            
            <!-- Story Footer (Views & Reacts for Owner, Reaction Buttons for Viewer) -->
            <div class="story-viewer-footer" id="story-viewer-footer"></div>
            
            <!-- Navigation controls (Left & Right regions) -->
            <div class="story-nav-region left" id="story-nav-prev"></div>
            <div class="story-nav-region right" id="story-nav-next"></div>
            
            <!-- Emoji Floating Effects Container -->
            <div class="story-emoji-fly-container" id="story-emoji-fly-container"></div>
        </div>
    </div>
    
    <!-- Story Views Modal (Danh sách người xem) -->
    <div id="story-views-modal" class="modal-overlay" style="display: none; z-index: 100000; background: rgba(0, 0, 0, 0.75);">
        <div class="modal-content glassmorphism-card" style="max-width: 420px; width: 90%; padding: 20px; position: relative;">
            <button class="modal-close" id="story-views-close-btn" style="top: 15px; right: 15px; background: none; border: none; color: var(--text-primary); font-size: 24px; cursor: pointer;">&times;</button>
            <h3 style="margin-top: 0; margin-bottom: 16px; font-family: var(--font-heading); font-size: 16px; font-weight: 800; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                <i class="fa-regular fa-eye" style="color: var(--accent-primary);"></i> Người đã xem
            </h3>
            <div id="story-views-list" style="max-height: 350px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-right: 4px;">
                <!-- Dynamically loaded -->
            </div>
        </div>
    </div>
    
    <script>
        window.FREST_USER = {
            id: <?php echo $me ? intval($me['id']) : 0; ?>,
            username: '<?php echo $me ? sanitize($me['username']) : ''; ?>'
        };
    </script>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/stories.js?v=<?php echo time(); ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/index.js?v=<?php echo time(); ?>"></script>

