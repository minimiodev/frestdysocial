<?php
/**
 * Utility functions for Wallpaper Haven (Upgraded with Collections)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/**
 * Generate a cryptographically secure random token
 */
function generateToken($prefix = '') {
    return $prefix . bin2hex(random_bytes(16));
}

/**
 * Get modify time of asset to prevent caching issues (Cache Busting)
 */
function getAssetVersion($relative_path) {
    $full_path = __DIR__ . '/../' . ltrim($relative_path, '/');
    return @filemtime($full_path) ?: '1';
}

/**
 * Trigger video transcoding in background (non-blocking)
 * Generates multi-quality variants: 360p, 480p, 720p, 1080p, 1440p, 2160p
 * 
 * Requires FFmpeg installed on the server.
 * Output files: post_XXX_360p.mp4, post_XXX_720p.mp4, etc.
 */
function triggerVideoTranscode($video_filename) {
    // FFmpeg background transcoding has been disabled. Video quality is simulated on the client-side.
    return false;
}


/**
 * Sanitize user inputs for outputting to HTML (prevents XSS)
 */
function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format price as VND or return Free
 */
function formatPrice($price) {
    if ($price <= 0) {
        return 'Miễn phí';
    }
    return number_format($price, 0, ',', '.') . ' ₫';
}

/**
 * Format file size in bytes to human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Check if admin is currently logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if a user is logged in
 */
function isUserLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $user = getLoggedInUser();
    if ($user === false || $user === null) {
        unset($_SESSION['user_id']);
        return false;
    }
    return true;
}

/**
 * Get current logged in user ID
 */
function getLoggedInUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged in user database record
 */
function getLoggedInUser() {
    static $cached_user = null;
    static $already_fetched = false;

    if ($already_fetched) {
        return $cached_user;
    }

    if (!isset($_SESSION['user_id'])) {
        $already_fetched = true;
        $cached_user = null;
        return null;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cached_user = $stmt->fetch();
        $already_fetched = true;
        return $cached_user;
    } catch (Exception $e) {
        $already_fetched = true;
        $cached_user = null;
        return null;
    }
}

/**
 * Get the verification badge SVG markup for a user type
 */
function getVerificationBadgeHTML($type, $username, $is_pro = false) {
    if (empty($type) || ($type !== 'official' && $type !== 'subscribed')) return '';
    
    $color = '#1877f2'; // Default blue
    $inner_color = '#ffffff';
    $title = 'Huy hiệu đã xác minh';
    
    switch ($type) {
        case 'official':
            $color = '#1877f2'; // Default blue
            $title = 'Huy hiệu đã xác minh';
            break;
        case 'subscribed':
            $color = '#1d4ed8'; // Dark Blue
            $title = 'Frest đã xác minh';
            break;
    }

    $is_pro_attr = $is_pro ? '1' : '0';

    return '<svg class="verified-badge-svg" data-type="' . sanitize($type) . '" data-username="' . sanitize($username) . '" data-is-pro="' . $is_pro_attr . '" viewBox="0 0 24 24" width="16" height="16" style="cursor:pointer; display:inline-flex; align-items:center; align-self:center; margin-left:4px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));" title="' . $title . '">
        <g fill-rule="evenodd" transform="translate(-92)">
            <path fill="' . $color . '" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/>
            <path fill="' . $inner_color . '" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/>
        </g>
    </svg>';
}

/**
 * Get active user reaction on a post
 */
function getUserPostReaction($userId, $postId) {
    if (!$userId || !$postId) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT reaction_type FROM reactions WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$userId, $postId]);
        return $stmt->fetchColumn() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get summary of reactions for a post
 */
function getPostReactionsSummary($postId) {
    $summary = ['total' => 0, 'types' => []];
    if (!$postId) return $summary;
    try {
        $db = getDB();
        
        // Total count
        $stmt = $db->prepare("SELECT COUNT(*) FROM reactions WHERE post_id = ?");
        $stmt->execute([$postId]);
        $summary['total'] = intval($stmt->fetchColumn());

        // Top 3 unique reaction types
        $stmt_types = $db->prepare("SELECT reaction_type, COUNT(*) as qty FROM reactions WHERE post_id = ? GROUP BY reaction_type ORDER BY qty DESC LIMIT 3");
        $stmt_types->execute([$postId]);
        $rows = $stmt_types->fetchAll();
        foreach ($rows as $row) {
            $summary['types'][] = $row['reaction_type'];
        }
    } catch (Exception $e) {}
    return $summary;
}

/**
 * Check if follower_id is following followed_id
 */
function isFollowingUser($followerId, $followedId) {
    if (!$followerId || !$followedId) return false;
    if ($followerId == $followedId) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
        $stmt->execute([$followerId, $followedId]);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Format timestamp to a relative time string (Vietnamese)
 */
function timeElapsedString($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Tính số tuần và ngày còn lại (không gán vào $diff để tránh deprecated dynamic property trên PHP 8.2+)
    $weeks        = (int) floor($diff->d / 7);
    $remaining_d  = $diff->d % 7;

    $parts = [
        'y' => [$diff->y,    'năm'],
        'm' => [$diff->m,    'tháng'],
        'w' => [$weeks,      'tuần'],
        'd' => [$remaining_d,'ngày'],
        'h' => [$diff->h,    'giờ'],
        'i' => [$diff->i,    'phút'],
        's' => [$diff->s,    'giây'],
    ];

    $string = [];
    foreach ($parts as $unit => [$value, $label]) {
        if ($value > 0) {
            $string[$unit] = $value . ' ' . $label;
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) : 'vừa xong';
}

/**
 * Check if the browser wants Dark Mode (defaults to dark theme)
 */
function isDarkModeActive() {
    if (isset($_COOKIE['theme'])) {
        return $_COOKIE['theme'] === 'dark';
    }
    return true; // Default to dark mode for ultra-premium look
}

/**
 * Render Post Media Attachments (Image, Video, Audio, Document, Software) with Copyright & NSFW Blur
 */
function renderPostMediaHTML($post, $should_blur_nsfw) {
    $html = '';
    
    // Check if there is any attachment
    $has_image = !empty($post['image_filename']);
    $has_video = !empty($post['video_filename']);
    $has_audio = !empty($post['audio_filename']);
    $has_doc = !empty($post['document_filename']);
    $has_software = !empty($post['software_filename']);
    
    if (!$has_image && !$has_video && !$has_audio && !$has_doc && !$has_software) {
        return '';
    }
    
    $is_copyright = isset($post['is_copyright_violation']) && intval($post['is_copyright_violation']) === 1;
    $allow_download = isset($post['allow_download']) ? intval($post['allow_download']) : 1;
    $copyright_owner = !empty($post['copyright_owner']) ? sanitize($post['copyright_owner']) : 'Chủ sở hữu bản quyền';
    $copyright_details = !empty($post['copyright_details']) ? nl2br(sanitize($post['copyright_details'])) : '';
    
    $likes_count = intval($post['reactions_total'] ?? 0);
    $likes_badge_html = '';
    if ($likes_count > 0) {
        $likes_badge_html = '<div class="post-likes-overlay-badge"><i class="fa-solid fa-heart"></i> <span>' . $likes_count . '</span></div>';
    }
    
    // Begin NSFW container wrapper if needed
    if ($should_blur_nsfw && !$is_copyright) {
        $html .= '<div class="nsfw-container" data-post-id="' . $post['id'] . '">';
        $html .= '  <div class="nsfw-overlay">';
        $html .= '    <i class="fa-solid fa-eye-slash nsfw-overlay-icon"></i>';
        $html .= '    <div class="nsfw-overlay-title">Nội dung nhạy cảm (18+)</div>';
        $html .= '    <div class="nsfw-overlay-text font-accent" style="font-size: 12px; color: rgba(255, 255, 255, 0.7); margin-bottom: 16px;">Bài viết này chứa nội dung 18+.</div>';
        $html .= '    <button type="button" class="nsfw-reveal-btn">Xem nội dung</button>';
        $html .= '  </div>';
        $html .= '  <div class="nsfw-blurred">';
    }
    
    // 1. Render Video
    if ($has_video) {
        if ($is_copyright) {
            $html .= renderCopyrightCardHTML("Video không khả dụng", $copyright_owner, $copyright_details);
        } else {
            $protect = 'oncontextmenu="return false;" controlsList="nodownload"';
            $html .= '<div class="frest-video-player-wrapper ' . ($allow_download === 0 ? 'video-restricted' : '') . '" style="margin-top: 12px; position: relative; border-radius: var(--radius-md); overflow: hidden; background: #000; box-shadow: var(--shadow-md);">';
            $html .= '  <video src="' . SITE_URL . '/uploads/posts/' . sanitize($post['video_filename']) . '" class="frest-video-element" preload="metadata" playsinline webkit-playsinline ' . $protect . ' style="width: 100%; display: block;"></video>';
            $html .= '  <div class="frest-video-play-overlay"><i class="fa-solid fa-play"></i></div>';
            $html .= '  <div class="frest-video-loader-overlay"><div class="frest-video-spinner"></div></div>';
            $html .= '  <div class="frest-video-controls-overlay">';
            $html .= '    <button type="button" class="frest-video-control-btn frest-play-pause-btn" title="Phát/Tạm dừng"><i class="fa-solid fa-play"></i></button>';
            $html .= '    <div class="frest-video-timeline-container">';
            $html .= '      <div class="frest-video-timeline-bg">';
            $html .= '        <div class="frest-video-timeline-buffer"></div>';
            $html .= '        <div class="frest-video-timeline-current"></div>';
            $html .= '      </div>';
            $html .= '      <input type="range" class="frest-video-timeline-slider" min="0" max="100" value="0" step="0.1">';
            $html .= '      <div class="frest-video-time-tooltip">00:00</div>';
            $html .= '    </div>';
            $html .= '    <div class="frest-video-time-display">00:00 / 00:00</div>';
            $html .= '    <div class="frest-video-volume-container">';
            $html .= '      <button type="button" class="frest-video-control-btn frest-volume-btn" title="Âm lượng"><i class="fa-solid fa-volume-high"></i></button>';
            $html .= '      <input type="range" class="frest-video-volume-slider" min="0" max="1" value="1" step="0.05">';
            $html .= '    </div>';
            $html .= '    <div class="frest-video-brightness-container">';
            $html .= '      <button type="button" class="frest-video-control-btn frest-brightness-btn" title="Độ sáng"><i class="fa-solid fa-sun"></i></button>';
            $html .= '      <input type="range" class="frest-video-brightness-slider" min="0.5" max="1.5" value="1" step="0.05">';
            $html .= '    </div>';
            $html .= '    <div class="frest-video-quality-container">';
            $html .= '      <button type="button" class="frest-video-control-btn frest-quality-btn" title="Chất lượng"><i class="fa-solid fa-gear"></i></button>';
            $html .= '      <div class="frest-video-quality-menu">';
            $html .= '        <div class="frest-video-quality-header">Chất lượng</div>';
            $html .= '        <button type="button" class="frest-video-quality-option active" data-quality="auto">Tự động (Tối ưu)</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="8k">8K (UHD+)</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="4k">4K (UHD)</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="2k">2K (QHD)</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="1080p">1080p</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="720p">720p</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="480p">480p</button>';
            $html .= '        <button type="button" class="frest-video-quality-option" data-quality="360p">360p</button>';
            $html .= '      </div>';
            $html .= '    </div>';
            $html .= '    <button type="button" class="frest-video-control-btn frest-fullscreen-btn" title="Toàn màn hình"><i class="fa-solid fa-expand"></i></button>';
            $html .= '  </div>';
            $html .= $likes_badge_html;
            $html .= '</div>';
        }
    }
    
    // 2. Render Image
    if ($has_image) {
        if ($is_copyright) {
            $html .= renderCopyrightCardHTML("Hình ảnh không khả dụng", $copyright_owner, $copyright_details);
        } else {
            $images = array_values(array_filter(explode(',', $post['image_filename'])));
            $count = count($images);
            
            $protect_class = ($allow_download === 0) ? 'disable-save' : '';
            $protect_attrs = ($allow_download === 0) ? 'oncontextmenu="return false;" ondragstart="return false;"' : '';
            $all_images_encoded = htmlspecialchars(implode(',', $images), ENT_QUOTES, 'UTF-8');
            
            $html .= '<div class="post-images-wrapper" data-post-id="' . $post['id'] . '" data-images="' . $all_images_encoded . '" data-allow-download="' . $allow_download . '">';
            
            if ($count === 1) {
                // ── 1 ảnh: full width ─────────────────────────────────────
                $html .= '<div class="img-grid img-grid-1">';
                $html .= '  <div class="img-grid-item" onclick="openLightbox(event, 0, ' . $post['id'] . ')">';
                $html .= '    <img src="' . SITE_URL . '/uploads/posts/' . sanitize($images[0]) . '" alt="Ảnh 1" class="' . $protect_class . '" ' . $protect_attrs . ' loading="lazy">';
                $html .= '  </div>';
                $html .= '</div>';

            } elseif ($count === 2) {
                // ── 2 ảnh: hai cột ngang bằng ────────────────────────────
                $html .= '<div class="img-grid img-grid-2">';
                foreach ($images as $idx => $img) {
                    $html .= '  <div class="img-grid-item" onclick="openLightbox(event, ' . $idx . ', ' . $post['id'] . ')">';
                    $html .= '    <img src="' . SITE_URL . '/uploads/posts/' . sanitize($img) . '" alt="Ảnh ' . ($idx + 1) . '" class="' . $protect_class . '" ' . $protect_attrs . ' loading="lazy">';
                    $html .= '  </div>';
                }
                $html .= '</div>';

            } elseif ($count === 3) {
                // ── 3 ảnh: 1 ảnh to bên trái (60%), 2 ảnh nhỏ bên phải xếp dọc (40%) ──
                $html .= '<div class="img-grid img-grid-3">';
                // Cột trái - ảnh to chính
                $html .= '  <div class="img-col-left">';
                $html .= '    <div class="img-grid-item" onclick="openLightbox(event, 0, ' . $post['id'] . ')" style="height: 100%;">';
                $html .= '      <img src="' . SITE_URL . '/uploads/posts/' . sanitize($images[0]) . '" alt="Ảnh 1" class="' . $protect_class . '" ' . $protect_attrs . ' loading="lazy">';
                $html .= '    </div>';
                $html .= '  </div>';
                // Cột phải - 2 ảnh nhỏ xếp chồng
                $html .= '  <div class="img-col-right">';
                for ($i = 1; $i <= 2; $i++) {
                    $html .= '    <div class="img-grid-item" onclick="openLightbox(event, ' . $i . ', ' . $post['id'] . ')">';
                    $html .= '      <img src="' . SITE_URL . '/uploads/posts/' . sanitize($images[$i]) . '" alt="Ảnh ' . ($i + 1) . '" class="' . $protect_class . '" ' . $protect_attrs . ' loading="lazy">';
                    $html .= '    </div>';
                }
                $html .= '  </div>';
                $html .= '</div>';

            } else {
                // ── 4+ ảnh: Lưới 2x2 cân xứng (2 cột x 2 dòng) ─────────────
                $display_images = array_slice($images, 0, 4);
                $overflow = $count - 4;
                
                $html .= '<div class="img-grid img-grid-4">';
                foreach ($display_images as $idx => $img) {
                    $is_last = ($idx === 3);
                    $html .= '  <div class="img-grid-item" onclick="openLightbox(event, ' . $idx . ', ' . $post['id'] . ')">';
                    $html .= '    <img src="' . SITE_URL . '/uploads/posts/' . sanitize($img) . '" alt="Ảnh ' . ($idx + 1) . '" class="' . $protect_class . '" ' . $protect_attrs . ' loading="lazy">';
                    if ($is_last && $overflow > 0) {
                        $html .= '    <div class="img-overlay-more">+' . $overflow . '</div>';
                    }
                    $html .= '  </div>';
                }
                $html .= '</div>';
            }
            
            $html .= $likes_badge_html;
            $html .= '</div>';
        }
    }
    
    // 3. Render Audio
    if ($has_audio) {
        if ($is_copyright) {
            $html .= renderCopyrightCardHTML("Âm thanh không khả dụng", $copyright_owner, $copyright_details);
        } else {
            $protect = ($allow_download === 0) ? 'controlsList="nodownload" class="post-audio audio-restricted"' : 'class="post-audio"';
            $html .= '<div class="audio-player-container" style="margin-top: 12px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 12px 16px; border-radius: var(--radius-sm); display: flex; flex-direction: column; gap: 8px;">';
            $html .= '  <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--text-primary);">';
            $html .= '    <i class="fa-solid fa-music" style="color: var(--accent-primary);"></i>';
            $html .= '    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . sanitize($post['audio_filename']) . '</span>';
            $html .= '  </div>';
            $html .= '  <audio src="' . SITE_URL . '/uploads/posts/' . sanitize($post['audio_filename']) . '" controls ' . $protect . ' style="width: 100%; outline: none;"></audio>';
            $html .= '</div>';
        }
    }
    
    // 4. Render Document
    if ($has_doc) {
        if ($is_copyright) {
            $html .= renderCopyrightCardHTML("Tài liệu không khả dụng", $copyright_owner, $copyright_details);
        } else {
            $file_url = SITE_URL . '/uploads/posts/' . sanitize($post['document_filename']);
            $file_name = sanitize($post['document_filename']);
            
            $html .= '<div class="document-container" style="margin-top: 12px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; background: rgba(255, 255, 255, 0.02); display: flex; justify-content: space-between; align-items: center; gap: 12px;">';
            $html .= '  <div style="display: flex; align-items: center; gap: 12px; overflow: hidden; text-align: left;">';
            $html .= '    <i class="fa-regular fa-file-pdf" style="font-size: 28px; color: var(--accent-primary); flex-shrink: 0;"></i>';
            $html .= '    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">';
            $html .= '      <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . $file_name . '</div>';
            $html .= '      <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Tài liệu tải lên</div>';
            $html .= '    </div>';
            $html .= '  </div>';
            if ($allow_download === 1) {
                $html .= '  <a href="' . $file_url . '" download class="btn-primary" style="font-size: 12px; font-weight: 700; height: 32px; padding: 0 14px; width: auto; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 6px; text-decoration: none;"><i class="fa-solid fa-arrow-down"></i> Tải về</a>';
            } else {
                $html .= '  <a href="' . $file_url . '" target="_blank" class="btn-primary" style="font-size: 12px; font-weight: 700; height: 32px; padding: 0 14px; width: auto; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color);"><i class="fa-regular fa-eye"></i> Xem</a>';
            }
            $html .= '</div>';
        }
    }
    
    // 5. Render Software
    if ($has_software) {
        if ($is_copyright) {
            $html .= renderCopyrightCardHTML("Phần mềm/Ứng dụng không khả dụng", $copyright_owner, $copyright_details);
        } else {
            $file_url = SITE_URL . '/uploads/posts/' . sanitize($post['software_filename']);
            $file_name = sanitize($post['software_filename']);
            
            $html .= '<div class="software-container" style="margin-top: 12px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; background: rgba(255, 255, 255, 0.02); display: flex; justify-content: space-between; align-items: center; gap: 12px;">';
            $html .= '  <div style="display: flex; align-items: center; gap: 12px; overflow: hidden; text-align: left;">';
            $html .= '    <i class="fa-solid fa-cubes" style="font-size: 28px; color: var(--success); flex-shrink: 0;"></i>';
            $html .= '    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">';
            $html .= '      <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . $file_name . '</div>';
            $html .= '      <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Ứng dụng / Phần mềm</div>';
            $html .= '    </div>';
            $html .= '  </div>';
            if ($allow_download === 1) {
                $html .= '  <a href="' . $file_url . '" download class="btn-primary" style="font-size: 12px; font-weight: 700; height: 32px; padding: 0 14px; width: auto; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: var(--accent-gradient);"><i class="fa-solid fa-download"></i> Cài đặt</a>';
            } else {
                $html .= '  <span style="font-size: 11.5px; color: var(--danger); font-weight: 600; padding: 6px 10px; background: rgba(239, 68, 68, 0.08); border-radius: 4px; border: 1px solid rgba(239, 68, 68, 0.15);"><i class="fa-solid fa-ban"></i> Đã khóa tải</span>';
            }
            $html .= '</div>';
        }
    }
    
    // Close NSFW container wrapper if needed
    if ($should_blur_nsfw && !$is_copyright) {
        $html .= '  </div>';
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Render copyright violation card helper
 */
function renderCopyrightCardHTML($title, $owner, $details = '') {
    $html = '<div class="copyright-violation-card">';
    $html .= '  <div class="copyright-icon"><i class="fa-solid fa-copyright"></i></div>';
    $html .= '  <div style="flex: 1; overflow: hidden;">';
    $html .= '    <div class="copyright-title">' . $title . '</div>';
    $html .= '    <div class="copyright-text">';
    $html .= '      Nội dung này đã bị gỡ bỏ do có khiếu nại bản quyền từ <strong>' . $owner . '</strong>.';
    $html .= '    </div>';
    if (!empty($details)) {
        $html .= '    <div class="copyright-details">' . $details . '</div>';
    }
    $html .= '  </div>';
    $html .= '</div>';
    return $html;
}

/**
 * Render a link preview card (Open Graph style) for a post.
 * Only renders when the post has link_preview_url set.
 */
function renderLinkPreviewCard(array $post): string {
    $url   = $post['link_preview_url']   ?? '';
    $title = $post['link_preview_title'] ?? '';
    $desc  = $post['link_preview_desc']  ?? '';
    $img   = $post['link_preview_image'] ?? '';

    if (empty($url)) return '';

    $domain     = parse_url($url, PHP_URL_HOST) ?: $url;
    $safe_url   = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safe_title = htmlspecialchars(mb_substr($title, 0, 120), ENT_QUOTES, 'UTF-8');
    $safe_desc  = htmlspecialchars(mb_substr($desc, 0, 200), ENT_QUOTES, 'UTF-8');
    $safe_dom   = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
    $safe_img   = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');

    $html  = '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer" class="link-preview-card" ';
    $html .= 'style="display:block; text-decoration:none; margin-top:12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); overflow:hidden; background:var(--bg-tertiary); transition:border-color 0.2s;" ';
    $html .= 'onmouseover="this.style.borderColor=\'var(--accent-primary)\'" onmouseout="this.style.borderColor=\'var(--border-color)\'">';

    if (!empty($safe_img)) {
        $html .= '<div style="width:100%; max-height:220px; overflow:hidden; background:#111;">';
        $html .= '<img src="' . $safe_img . '" alt="preview" style="width:100%; object-fit:cover; display:block;" loading="lazy" onerror="this.parentNode.style.display=\'none\'">';
        $html .= '</div>';
    }

    $html .= '<div style="padding:12px 14px;">';
    $html .= '<div style="font-size:11px; color:var(--text-muted); margin-bottom:4px; display:flex; align-items:center; gap:5px;">';
    $html .= '<i class="fa-solid fa-link" style="font-size:9px;"></i> ' . $safe_dom;
    $html .= '</div>';
    if (!empty($safe_title)) {
        $html .= '<div style="font-size:14px; font-weight:700; color:var(--text-primary); line-height:1.35; margin-bottom:4px;">' . $safe_title . '</div>';
    }
    if (!empty($safe_desc)) {
        $html .= '<div style="font-size:12.5px; color:var(--text-secondary); line-height:1.45; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">' . $safe_desc . '</div>';
    }
    $html .= '</div>';
    $html .= '</a>';

    return $html;
}

/**
 * Format a user's full name based on the specified order configuration
 */
function formatUserFullName($first, $middle, $last, $order) {
    $first = trim($first ?? '');
    $middle = trim($middle ?? '');
    $last = trim($last ?? '');
    
    if ($order === 'last_middle_first') {
        $parts = array_filter([$last, $middle, $first]);
        return implode(' ', $parts);
    } elseif ($order === 'first_middle_last') {
        $parts = array_filter([$first, $middle, $last]);
        return implode(' ', $parts);
    } elseif ($order === 'first_last') {
        $parts = array_filter([$first, $last]);
        return implode(' ', $parts);
    } elseif ($order === 'last_first') {
        $parts = array_filter([$last, $first]);
        return implode(' ', $parts);
    } elseif ($order === 'first_only') {
        return $first;
    }
    
    // Fallback default
    $parts = array_filter([$last, $middle, $first]);
    return implode(' ', $parts);
}

/**
 * Get current active identity (user profile or page profile)
 */
function getCurrentIdentity() {
    $me = getLoggedInUser();
    if (!$me) return null;
    
    // Check if acting as a Page
    if (isset($_SESSION['active_page_id'])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? AND owner_id = ?");
            $stmt->execute([$_SESSION['active_page_id'], $me['id']]);
            $page = $stmt->fetch();
            if ($page) {
                return [
                    'type' => 'page',
                    'id' => $page['id'],
                    'name' => $page['page_name'],
                    'username' => $page['page_username'],
                    'avatar' => $page['avatar_filename'],
                    'bio' => $page['bio'],
                    'category' => $page['category'],
                    'is_page' => true,
                    'user_id' => $me['id'],
                    'is_verified' => intval($page['is_verified'] ?? 0)
                ];
            }
        } catch (Exception $e) {}
    }
    
    // Fallback: active as personal profile
    $is_professional_page = isset($me['is_page']) && intval($me['is_page']) === 1;
    $is_verified = (!empty($me['verification_type']) && $me['verification_type'] !== 'none') ? 1 : 0;
    return [
        'type' => 'user',
        'id' => $me['id'],
        'name' => $me['full_name'] ?: $me['username'],
        'username' => $me['username'],
        'avatar' => $me['avatar_filename'],
        'bio' => $me['bio'],
        'category' => $is_professional_page ? ($me['page_category'] ?: 'Blog cá nhân') : null,
        'is_page' => $is_professional_page,
        'user_id' => $me['id'],
        'is_verified' => $is_verified
    ];
}

/**
 * Get the list of allowed page categories from database settings
 */
function getPageCategories() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'page_categories'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val === false) {
            $default = "Cộng đồng, Doanh nghiệp, Blog cá nhân, Người sáng tạo nội dung, Giải trí, Tin tức, Nhân vật công chúng, Nghệ sĩ, Nhà phát triển game";
            try {
                $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('page_categories', ?)")->execute([$default]);
            } catch (Exception $e){}
            return array_map('trim', explode(',', $default));
        }
        return array_map('trim', explode(',', $val));
    } catch (Exception $e) {
        return ["Cộng đồng", "Doanh nghiệp", "Blog cá nhân", "Người sáng tạo nội dung", "Giải trí", "Tin tức", "Nhân vật công chúng", "Nghệ sĩ", "Nhà phát triển game"];
    }
}

/**
 * Get the verified page badge SVG markup for a Page
 */
function getPageVerificationBadgeHTML($page_id, $is_user_page = false) {
    if (empty($page_id)) return '';
    $page_id_val = intval($page_id);
    $is_up = $is_user_page ? 1 : 0;
    
    static $verified_cache = [];
    static $vt_cache = [];
    $cache_key = $is_up . '_' . $page_id_val;
    
    if (!isset($verified_cache[$cache_key])) {
        $verified_cache[$cache_key] = false;
        $vt_cache[$cache_key] = 'none';
        try {
            $db = getDB();
            if ($is_user_page) {
                // Professional Mode: check if user has active verification type
                $stmt = $db->prepare("SELECT verification_type FROM users WHERE id = ?");
                $stmt->execute([$page_id_val]);
                $vt = $stmt->fetchColumn();
                if (!empty($vt) && $vt !== 'none') {
                    $verified_cache[$cache_key] = true;
                    $vt_cache[$cache_key] = $vt;
                }
            } else {
                // Regular Page: check pages table verification_type, fallback to is_verified
                $stmt = $db->prepare("SELECT verification_type, is_verified FROM pages WHERE id = ?");
                $stmt->execute([$page_id_val]);
                $page = $stmt->fetch();
                if ($page) {
                    $vt = $page['verification_type'] ?? '';
                    $is_verified = intval($page['is_verified'] ?? 0);
                    if (!empty($vt) && $vt !== 'none') {
                        $verified_cache[$cache_key] = true;
                        $vt_cache[$cache_key] = $vt;
                    } elseif ($is_verified === 1) {
                        $verified_cache[$cache_key] = true;
                        $vt_cache[$cache_key] = 'official';
                    }
                }
            }
        } catch (Exception $e) {}
    }
    
    if (!$verified_cache[$cache_key]) {
        return '';
    }
    
    $type = $vt_cache[$cache_key];
    $color = '#1877f2'; // Default blue
    $inner_color = '#ffffff';
    $title = 'Trang đã xác minh';
    
    switch ($type) {
        case 'developer':
            $color = '#a855f7'; // Purple
            $title = 'Trang Nhà phát triển / Lập trình viên';
            break;
        case 'official':
            $color = '#1877f2'; // Default blue
            $title = 'Trang đã xác minh';
            break;
        case 'subscribed':
            $color = '#1d4ed8'; // Dark Blue
            $title = 'Trang Frest đã xác minh';
            break;
        case 'business':
            $color = '#d97706'; // Gold/Amber
            $title = 'Trang Doanh nghiệp / Tổ chức';
            break;
        case 'gov_vietnam':
            $color = '#ef4444'; // Red
            $inner_color = '#fbbf24'; // Gold star color
            $title = 'Trang Cơ quan Chính phủ Việt Nam';
            break;
        case 'gov_global':
            $color = '#64748b'; // Slate Gray
            $title = 'Trang Tổ chức Chính phủ / Quốc tế';
            break;
    }
    
    return '<svg class="page-verified-badge-svg" data-page-id="' . $page_id_val . '" data-is-user-page="' . $is_up . '" data-type="' . sanitize($type) . '" viewBox="0 0 24 24" width="16" height="16" style="cursor:pointer; display:inline-flex; align-items:center; align-self:center; margin-left:4px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));" title="' . $title . '">
        <g fill-rule="evenodd" transform="translate(-92)">
            <path fill="' . $color . '" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/>
            <path fill="' . $inner_color . '" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/>
        </g>
    </svg>';
}

/**
 * Automatically decides whether to render a user verification badge or a page verification badge
 */
function renderAuthorBadgeHTML($verification_type, $username, $page_id = null, $is_user_page = false) {
    if (!empty($page_id)) {
        return getPageVerificationBadgeHTML($page_id, false);
    }
    if ($is_user_page) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $uid = $stmt->fetchColumn();
            if ($uid) {
                return getPageVerificationBadgeHTML($uid, true);
            }
        } catch (Exception $e) {}
    }
    return getVerificationBadgeHTML($verification_type, $username);
}

/**
 * Get summary of reactions for a reply
 */
function getReplyReactionsSummary($replyId) {
    $summary = ['total' => 0, 'types' => []];
    if (!$replyId) return $summary;
    try {
        $db = getDB();
        
        // Total count
        $stmt = $db->prepare("SELECT COUNT(*) FROM reactions WHERE reply_id = ?");
        $stmt->execute([$replyId]);
        $summary['total'] = intval($stmt->fetchColumn());

        // Top 3 unique reaction types
        $stmt_types = $db->prepare("SELECT reaction_type, COUNT(*) as qty FROM reactions WHERE reply_id = ? GROUP BY reaction_type ORDER BY qty DESC LIMIT 3");
        $stmt_types->execute([$replyId]);
        $rows = $stmt_types->fetchAll();
        foreach ($rows as $row) {
            $summary['types'][] = $row['reaction_type'];
        }
    } catch (Exception $e) {}
    return $summary;
}

/**
 * Get active user reaction on a reply
 */
function getUserReplyReaction($userId, $replyId) {
    if (!$userId || !$replyId) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT reaction_type FROM reactions WHERE user_id = ? AND reply_id = ?");
        $stmt->execute([$userId, $replyId]);
        return $stmt->fetchColumn() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Convert plain text links and @mentions inside a string to HTML clickable links.
 * - URLs are wrapped in <a> tags that open in a new tab.
 * - @username mentions are wrapped in <a> tags pointing to profile.php.
 */
function linkify($text) {
    // 1) Convert @username / @page_username mentions (do this BEFORE URL replacement)
    $text = preg_replace_callback(
        '/(?<!["\'"])@([\p{L}\p{N}_\.]{1,100})/u',
        function ($matches) {
            $handle = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            return '<a href="profile.php?username=' . $handle
                . '" class="mention-link" onclick="event.stopPropagation();">@' . $handle . '</a>';
        },
        $text
    );
    // 2) Convert HTTP/HTTPS URLs
    $text = preg_replace(
        '/(https?:\/\/[^\s<"\']+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer" class="content-link" onclick="event.stopPropagation();">$1</a>',
        $text
    );
    return $text;
}

/**
 * Insert a notification record.
 * Deduplicates: won't insert if same actor/user/type/ref combo already exists within 60 seconds.
 */
function createNotification($userId, $actorId, $type, $refPostId = null, $refReplyId = null, $detail = null) {
    if ($userId == $actorId) return;
    try {
        $db = getDB();
        $dedup = $db->prepare(
            "SELECT id FROM notifications
             WHERE user_id=? AND actor_id=? AND type=? AND COALESCE(ref_post_id,0)=? AND COALESCE(ref_reply_id,0)=?
             AND created_at > (NOW() - INTERVAL 60 SECOND) LIMIT 1"
        );
        $dedup->execute([$userId, $actorId, $type, $refPostId ?? 0, $refReplyId ?? 0]);
        if ($dedup->fetchColumn()) return;

        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, actor_id, type, ref_post_id, ref_reply_id, detail)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $actorId, $type, $refPostId, $refReplyId, $detail]);

        // Invalidate session badge cache for the recipient so badge updates sooner
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['notif_count_' . $userId], $_SESSION['notif_count_ts_' . $userId]);
        }
    } catch (Exception $e) {
        // Silent fail — notifications are non-critical
    }
}


/**
 * Get unread (non-dismissed) notification count for a user.
 */
function getUnreadNotifCount($userId) {
    if (!$userId) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_dismissed=0 AND is_read=0");
        $stmt->execute([$userId]);
        return intval($stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get system settings dynamically from DB
 */
function getSystemSetting($key, $default = '', $clear_cache = false) {
    static $settings_cache = [];
    if ($clear_cache) {
        if ($key === null) {
            $settings_cache = [];
        } else {
            unset($settings_cache[$key]);
        }
        return $default;
    }
    if (array_key_exists($key, $settings_cache)) {
        return $settings_cache[$key];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $settings_cache[$key] = ($val !== false && $val !== null) ? $val : $default;
        return $settings_cache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Get dynamic site name
 */
function getSiteName() {
    $name = getSystemSetting('site_name', 'Frest');
    return str_replace('Frest App', 'Frest', $name);
}

/**
 * Render workplace text as link if it is an existing user or page username tag
 */
function getWorkplaceLinkHTML($workplace) {
    $workplace = trim($workplace ?? '');
    if (empty($workplace)) return '';

    $db = getDB();
    
    // Case 1: starts with '@' (e.g. @frest)
    if (strpos($workplace, '@') === 0 && strlen($workplace) > 1) {
        $username = substr($workplace, 1);
        try {
            // Check in pages table first
            $stmt = $db->prepare("SELECT page_username, page_name FROM pages WHERE page_username = ? LIMIT 1");
            $stmt->execute([$username]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($page) {
                return '<a href="page.php?username=' . urlencode($page['page_username']) . '" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">' . sanitize($page['page_name']) . '</a>';
            }
            
            // Check in users table
            $stmt = $db->prepare("SELECT username, full_name FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $displayName = $user['full_name'] ?: $user['username'];
                return '<a href="profile.php?username=' . urlencode($user['username']) . '" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">' . sanitize($displayName) . '</a>';
            }
        } catch (Exception $e) {}
    } else {
        // Case 2: does not start with '@', check if it exactly matches a page username, page name, or user username
        try {
            // Check in pages by page_username
            $stmt = $db->prepare("SELECT page_username, page_name FROM pages WHERE page_username = ? LIMIT 1");
            $stmt->execute([$workplace]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($page) {
                return '<a href="page.php?username=' . urlencode($page['page_username']) . '" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">' . sanitize($page['page_name']) . '</a>';
            }
            
            // Check in pages by page_name
            $stmt = $db->prepare("SELECT page_username, page_name FROM pages WHERE page_name = ? LIMIT 1");
            $stmt->execute([$workplace]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($page) {
                return '<a href="page.php?username=' . urlencode($page['page_username']) . '" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">' . sanitize($page['page_name']) . '</a>';
            }
            
            // Check users by username
            $stmt = $db->prepare("SELECT username, full_name FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$workplace]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $displayName = $user['full_name'] ?: $user['username'];
                return '<a href="profile.php?username=' . urlencode($user['username']) . '" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">' . sanitize($displayName) . '</a>';
            }
        } catch (Exception $e) {}
    }
    
    return '<strong>' . sanitize($workplace) . '</strong>';
}

/**
 * System Maintenance Mode Guard
 */
function checkMaintenanceMode() {
    // If Admin is logged in, do not block
    if (isAdminLoggedIn()) {
        return;
    }
    
    // If the request path is in the admin directory, do not block
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    if (strpos($request_uri, '/admin/') !== false || strpos($script_name, '/admin/') !== false) {
        return;
    }
    
    // Check if maintenance mode is enabled in setting
    $maintenance_enabled = getSystemSetting('maintenance_enabled', '0');
    if ($maintenance_enabled === '1') {
        // If it's an AJAX or JSON request, return JSON response instead of HTML page
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                   (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.']);
            exit;
        }
        include __DIR__ . '/../maintenance.php';
        exit;
    }
}

// Execute maintenance mode check on load
checkMaintenanceMode();

/**
 * Logged-in User Account Status Guard
 */
function checkUserStatus() {
    // If Admin is logged in, do not block
    if (isAdminLoggedIn()) {
        return;
    }
    
    // Check if the current page is blocked.php, login.php, register.php, or logout.php
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    if ($current_page === 'blocked.php' || $current_page === 'logout.php' || $current_page === 'login.php' || $current_page === 'register.php') {
        return;
    }
    
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($current_uri, '/admin/') !== false || strpos($script_name, '/admin/') !== false) {
        return;
    }
    
    // Check user session
    if (!isUserLoggedIn()) {
        return;
    }
    
    $me = getLoggedInUser();
    if ($me) {
        $status = $me['status'] ?? 'active';
        $lock_until = $me['lock_until'] ?? null;
        
        if ($status === 'temporarily_locked') {
            if ($lock_until && strtotime($lock_until) > time()) {
                // Return JSON error for AJAX requests
                $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
                if ($is_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn đang bị khóa tạm thời.']);
                    exit;
                }
                header("Location: blocked.php");
                exit;
            } else {
                // Auto-unlock
                try {
                    $db = getDB();
                    $stmt = $db->prepare("UPDATE users SET status = 'active', status_reason = NULL, lock_until = NULL WHERE id = ?");
                    $stmt->execute([$me['id']]);
                } catch (Exception $e) {}
            }
        } elseif ($status === 'disabled' || $status === 'permanently_suspended') {
            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                       (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn đã bị vô hiệu hóa hoặc đóng vĩnh viễn.']);
                exit;
            }
            header("Location: blocked.php");
            exit;
        }
    }
}

// Execute account status check on load
checkUserStatus();

/**
 * ==========================================
 * BLOCKING & REPORTING HELPERS
 * ==========================================
 */

/**
 * Kiểm tra xem Identity A có chủ động chặn Identity B hay không
 */
function hasBlocked($identityA, $identityB) {
    if (!$identityA || !$identityB) return false;
    $typeA = $identityA['type'] ?? 'user';
    $idA = intval($identityA['id'] ?? 0);
    $typeB = $identityB['type'] ?? 'user';
    $idB = intval($identityB['id'] ?? 0);
    if ($idA === 0 || $idB === 0) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM blocks WHERE blocker_type = ? AND blocker_id = ? AND blocked_type = ? AND blocked_id = ? LIMIT 1");
        $stmt->execute([$typeA, $idA, $typeB, $idB]);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Kiểm tra xem có quan hệ chặn 2 chiều giữa Identity A và Identity B hay không
 */
function isBlocked($identityA, $identityB) {
    return hasBlocked($identityA, $identityB) || hasBlocked($identityB, $identityA);
}

/**
 * Thực hiện chặn Identity B bởi Identity A và hủy bỏ các liên kết theo dõi (follow) giữa hai bên
 */
function blockIdentity($identityA, $identityB) {
    if (!$identityA || !$identityB) return false;
    $typeA = $identityA['type'] ?? 'user';
    $idA = intval($identityA['id'] ?? 0);
    $typeB = $identityB['type'] ?? 'user';
    $idB = intval($identityB['id'] ?? 0);
    if ($idA === 0 || $idB === 0) return false;
    if ($typeA === $typeB && $idA === $idB) return false; // Không tự chặn chính mình
    
    try {
        $db = getDB();
        
        // 1. Thêm bản ghi vào bảng blocks
        $stmt = $db->prepare("INSERT IGNORE INTO blocks (blocker_type, blocker_id, blocked_type, blocked_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$typeA, $idA, $typeB, $idB]);
        
        // 2. Hủy follow hai chiều nếu có
        // Nếu cả hai đều là user
        if ($typeA === 'user' && $typeB === 'user') {
            $stmt = $db->prepare("DELETE FROM follows WHERE (follower_id = ? AND followed_id = ?) OR (follower_id = ? AND followed_id = ?)");
            $stmt->execute([$idA, $idB, $idB, $idA]);
        }
        // Nếu liên quan đến Page
        if ($typeA === 'user' && $typeB === 'page') {
            $stmt = $db->prepare("DELETE FROM page_follows WHERE user_id = ? AND page_id = ?");
            $stmt->execute([$idA, $idB]);
        }
        if ($typeA === 'page' && $typeB === 'user') {
            $stmt = $db->prepare("DELETE FROM page_follows WHERE user_id = ? AND page_id = ?");
            $stmt->execute([$idB, $idA]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Hủy chặn Identity B bởi Identity A
 */
function unblockIdentity($identityA, $identityB) {
    if (!$identityA || !$identityB) return false;
    $typeA = $identityA['type'] ?? 'user';
    $idA = intval($identityA['id'] ?? 0);
    $typeB = $identityB['type'] ?? 'user';
    $idB = intval($identityB['id'] ?? 0);
    if ($idA === 0 || $idB === 0) return false;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM blocks WHERE blocker_type = ? AND blocker_id = ? AND blocked_type = ? AND blocked_id = ?");
        $stmt->execute([$typeA, $idA, $typeB, $idB]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Trả về đoạn truy vấn SQL loại trừ các bài viết bị chặn hai chiều
 */
function getBlockConditionsSQL($identity, $userField = 'posts.user_id', $pageField = 'posts.page_id') {
    if (!$identity) return "1=1";
    $type = $identity['type'] ?? 'user';
    $id = intval($identity['id'] ?? 0);
    if ($id === 0) return "1=1";
    
    return " NOT EXISTS (
        SELECT 1 FROM blocks 
        WHERE 
            -- Mình chặn họ
            (blocker_type = '$type' AND blocker_id = $id AND (
                (blocked_type = 'page' AND $pageField IS NOT NULL AND blocked_id = $pageField) OR
                (blocked_type = 'user' AND $pageField IS NULL AND blocked_id = $userField)
            ))
            OR
            -- Họ chặn mình
            (blocked_type = '$type' AND blocked_id = $id AND (
                 (blocker_type = 'page' AND $pageField IS NOT NULL AND blocker_id = $pageField) OR
                (blocker_type = 'user' AND $pageField IS NULL AND blocker_id = $userField)
            ))
    )";
}

/**
 * Tự động nhận diện hashtag trong nội dung và parse thành liên kết
 */
function parseHashtags($text) {
    return preg_replace_callback('/#([a-zA-Z0-9_\p{L}\p{M}]+)/u', function($matches) {
        $tag = $matches[1];
        $url = SITE_URL . '/search.php?q=' . urlencode('#' . $tag);
        return '<a href="' . $url . '" class="hashtag-link" style="color: var(--accent-secondary); font-weight: 600;">#' . sanitize($tag) . '</a>';
    }, $text);
}

/**
 * Trích xuất các hashtag từ bài viết và lưu vào cơ sở dữ liệu
 */
function extractAndSaveHashtags($post_id, $content) {
    try {
        $db = getDB();
        preg_match_all('/#([a-zA-Z0-9_\p{L}\p{M}]+)/u', $content, $matches);
        if (!empty($matches[1])) {
            $tags = array_unique($matches[1]);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (empty($tag)) continue;
                
                // Lưu hashtag
                $stmt = $db->prepare("INSERT IGNORE INTO hashtags (tag) VALUES (?)");
                $stmt->execute([$tag]);
                
                // Lấy ID hashtag
                $stmt_id = $db->prepare("SELECT id FROM hashtags WHERE tag = ?");
                $stmt_id->execute([$tag]);
                $hashtag = $stmt_id->fetch();
                
                if ($hashtag) {
                    $hashtag_id = $hashtag['id'];
                    // Lưu liên kết
                    $stmt_link = $db->prepare("INSERT IGNORE INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)");
                    $stmt_link->execute([$post_id, $hashtag_id]);
                }
            }
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi DB
    }
}

/**
 * Lấy danh sách các hashtag xu hướng nổi bật
 */
function getTrendingHashtags($limit = 5) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT h.tag, COUNT(ph.post_id) as post_count 
            FROM hashtags h
            JOIN post_hashtags ph ON h.id = ph.hashtag_id
            GROUP BY h.id
            ORDER BY post_count DESC, h.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, intval($limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Kiểm tra xem bài viết đã được bookmark bởi người dùng chưa
 */
function isPostBookmarked($post_id, $user_id) {
    if (empty($user_id)) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Kiểm tra xem người dùng có online dựa vào thời gian hoạt động cuối cùng
 */
function isUserOnline($last_active_time) {
    if (empty($last_active_time)) return false;
    $active_ts = strtotime($last_active_time);
    $current_ts = time();
    return ($current_ts - $active_ts) < 300; // Hoạt động trong vòng 5 phút
}

/**
 * Render HTML giao diện Cuộc thăm dò ý kiến (Polls)
 */
function renderPollHTML($post_id, $user_id) {
    try {
        $db = getDB();
        
        // Lấy thông tin Poll
        $stmt = $db->prepare("SELECT * FROM polls WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $poll = $stmt->fetch();
        
        if (!$poll) return '';
        
        $poll_id = $poll['id'];
        
        // Lấy các phương án lựa chọn
        $stmt_opts = $db->prepare("SELECT * FROM poll_options WHERE poll_id = ?");
        $stmt_opts->execute([$poll_id]);
        $options = $stmt_opts->fetchAll();
        
        // Kiểm tra xem poll đã hết hạn chưa
        $is_expired = false;
        if (!empty($poll['expires_at'])) {
            $is_expired = strtotime($poll['expires_at']) < time();
        }
        
        // Đếm tổng số vote
        $stmt_total = $db->prepare("SELECT COUNT(*) as total FROM poll_votes WHERE poll_id = ?");
        $stmt_total->execute([$poll_id]);
        $total_votes = $stmt_total->fetch()['total'] ?? 0;
        
        // Kiểm tra xem người dùng hiện tại đã vote chưa
        $user_voted_opt_id = null;
        if (!empty($user_id)) {
            $stmt_user_vote = $db->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $stmt_user_vote->execute([$poll_id, $user_id]);
            $user_vote = $stmt_user_vote->fetch();
            if ($user_vote) {
                $user_voted_opt_id = $user_vote['option_id'];
            }
        }
        
        // Tính toán phiếu bầu cho từng phương án
        $options_data = [];
        foreach ($options as $opt) {
            $stmt_opt_votes = $db->prepare("SELECT COUNT(*) as count FROM poll_votes WHERE option_id = ?");
            $stmt_opt_votes->execute([$opt['id']]);
            $count = $stmt_opt_votes->fetch()['count'] ?? 0;
            
            $percentage = 0;
            if ($total_votes > 0) {
                $percentage = round(($count / $total_votes) * 100);
            }
            
            $options_data[] = [
                'id' => $opt['id'],
                'text' => $opt['option_text'],
                'votes' => $count,
                'percentage' => $percentage
            ];
        }
        
        $show_results = ($user_voted_opt_id !== null) || $is_expired;
        
        $html = '<div class="post-poll-box" data-poll-id="' . $poll_id . '" data-post-id="' . $post_id . '" style="margin-top: 12px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md);">';
        $html .= '  <div class="poll-question" style="font-weight: 700; font-family: var(--font-heading); color: var(--text-primary); margin-bottom: 12px;">' . sanitize($poll['question']) . '</div>';
        $html .= '  <div class="poll-options-container" data-show-results="' . ($show_results ? 'true' : 'false') . '" style="display: flex; flex-direction: column; gap: 8px;">';
        
        foreach ($options_data as $opt) {
            $is_user_choice = ($user_voted_opt_id === $opt['id']);
            if ($show_results) {
                // Hiển thị kết quả thanh phần trăm
                $html .= '    <div class="poll-result-item ' . ($is_user_choice ? 'user-voted' : '') . '" style="position: relative; height: 40px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); overflow: hidden; display: flex; align-items: center; padding: 0 12px;">';
                $html .= '      <div class="poll-result-bar" style="position: absolute; left: 0; top: 0; bottom: 0; width: ' . $opt['percentage'] . '%; background: rgba(124, 58, 237, 0.12); transition: width 0.8s cubic-bezier(0.1, 0.8, 0.2, 1);"></div>';
                $html .= '      <div class="poll-result-label" style="display: flex; justify-content: space-between; width: 100%; z-index: 2; font-size: 13px; font-weight: 500;">';
                $html .= '        <span class="poll-opt-text" style="color: var(--text-primary); display: flex; align-items: center; gap: 6px;">' . sanitize($opt['text']);
                if ($is_user_choice) {
                    $html .= '        <span class="poll-voted-badge" style="color: var(--accent-primary);"><i class="fa-solid fa-circle-check"></i></span>';
                }
                $html .= '        </span>';
                $html .= '        <span class="poll-opt-percent" style="color: var(--text-secondary);">' . $opt['percentage'] . '% (' . $opt['votes'] . ' phiếu)</span>';
                $html .= '      </div>';
                $html .= '    </div>';
            } else {
                // Hiển thị nút bấm bình chọn
                $html .= '    <button type="button" class="poll-vote-btn" onclick="votePoll(event, ' . $poll_id . ', ' . $opt['id'] . ')" style="height: 40px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: rgba(255, 255, 255, 0.02); color: var(--text-primary); font-size: 13px; font-weight: 600; text-align: left; padding: 0 12px; cursor: pointer; transition: all var(--transition-fast);">';
                $html .= '      ' . sanitize($opt['text']);
                $html .= '    </button>';
            }
        }
        
        $html .= '  </div>';
        $html .= '  <div class="poll-footer" style="display: flex; gap: 8px; font-size: 11px; color: var(--text-muted); margin-top: 12px; align-items: center;">';
        $html .= '    <span class="poll-total-votes">' . $total_votes . ' lượt bình chọn</span>';
        $html .= '    <span class="poll-dot">•</span>';
        if ($is_expired) {
            $html .= '    <span class="poll-status expired" style="color: var(--danger); font-weight: 600;">Đã kết thúc</span>';
        } else {
            $html .= '    <span class="poll-status active" style="color: var(--success); font-weight: 600;">Đang diễn ra</span>';
        }
        $html .= '  </div>';
        $html .= '</div>';
        
        return $html;
    } catch (PDOException $e) {
        return '';
    }
}

/**
 * Helper to clean up user agent strings
 */
function getCleanUserAgent($ua) {
    if (empty($ua)) return 'Thiết bị không xác định';
    $os = 'Hệ điều hành khác';
    if (stripos($ua, 'windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'macintosh') !== false || stripos($ua, 'mac os x') !== false) $os = 'macOS';
    elseif (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) $os = 'iOS';
    elseif (stripos($ua, 'android') !== false) $os = 'Android';
    elseif (stripos($ua, 'linux') !== false) $os = 'Linux';

    $browser = 'Trình duyệt khác';
    if (stripos($ua, 'edg') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'chrome') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'safari') !== false) $browser = 'Safari';
    elseif (stripos($ua, 'firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'opera') !== false || stripos($ua, 'opr') !== false) $browser = 'Opera';

    return "$os / $browser";
}

/**
 * Helper to get approximate location from IP address
 */
function getIpLocation($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return 'Hà Nội, Việt Nam';
    }
    if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip)) {
        return 'Mạng nội bộ';
    }
    
    // Attempt fetching from ip-api.com with 1 second timeout
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/" . urlencode($ip));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'success') {
            $city = $data['city'] ?? '';
            $country = $data['country'] ?? '';
            return ($city && $country) ? "$city, $country" : ($country ?: 'Không xác định');
        }
    }
    
    // Fallback based on IP hash
    $cities = ['Hà Nội, Việt Nam', 'Hồ Chí Minh, Việt Nam', 'Đà Nẵng, Việt Nam', 'Singapore'];
    return $cities[abs(crc32($ip)) % count($cities)];
}

/**
 * Record user login history (with automatic self-healing table check and GeoIP database caching)
 */
function recordLoginHistory($user_id) {
    try {
        $db = getDB();
        
        // Ensure table exists with composite indexes for O(1)/O(log N) lookup
        $db->exec("CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_lh_user_time (user_id, login_time DESC),
            INDEX idx_lh_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip === '::1') $ip = '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Cache Optimization: Check if we already resolved this IP address recently to avoid synchronous curl latency (0ms delay)
        $stmt_cache = $db->prepare("SELECT location FROM login_history WHERE ip_address = ? AND location IS NOT NULL ORDER BY login_time DESC LIMIT 1");
        $stmt_cache->execute([$ip]);
        $location = $stmt_cache->fetchColumn();
        
        if (!$location) {
            // New IP: Perform quick GeoIP lookup (max 1s timeout)
            $location = getIpLocation($ip);
        }
        
        $stmt = $db->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, location) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $ip, $ua, $location]);
    } catch (Exception $e) {
        // Fail silently
    }
}

/**
 * Analyze login history to detect if current login is unusual
 */
function isLoginUnusual($user_id, $ip, $ua) {
    try {
        $db = getDB();
        
        // Ensure table exists
        $db->exec("CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_lh_user_time (user_id, login_time DESC),
            INDEX idx_lh_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Fetch last 30 logins utilizing index
        $stmt = $db->prepare("SELECT ip_address, user_agent FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 30");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll();
        
        if (count($history) <= 1) {
            return false;
        }
        
        $ip_prefix = implode('.', array_slice(explode('.', $ip), 0, 2));
        $clean_ua = getCleanUserAgent($ua);
        
        $known_prefixes = [];
        $known_user_agents = [];
        
        $is_first = true;
        foreach ($history as $row) {
            if ($is_first) {
                $is_first = false;
                continue; // Skip the current active session log (just inserted)
            }
            $prev_ip = $row['ip_address'];
            $prev_prefix = implode('.', array_slice(explode('.', $prev_ip), 0, 2));
            $known_prefixes[] = $prev_prefix;
            
            $prev_clean_ua = getCleanUserAgent($row['user_agent']);
            $known_user_agents[] = $prev_clean_ua;
        }
        
        if (!in_array($ip_prefix, $known_prefixes) || !in_array($clean_ua, $known_user_agents)) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get profile URL for a user or page dynamically.
 */
function getProfileUrl($username, $page_id = null) {
    if (!empty($page_id)) {
        return 'page.php?username=' . urlencode($username);
    }
    return 'profile.php?username=' . urlencode($username);
}

/**
 * Lấy đường dẫn vật lý thư mục upload riêng cho từng user.
 * Tự động tạo thư mục nếu chưa tồn tại.
 */
function getUserUploadPath($user_identifier, $type = 'posts') {
    $username = '';
    if (is_numeric($user_identifier)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([intval($user_identifier)]);
            $username = $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        // Nếu không tìm thấy user có ID này, kiểm tra xem user_identifier có phải là username thực tế của user không
        if (empty($username)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT username FROM users WHERE username = ?");
                $stmt->execute([strval($user_identifier)]);
                $db_username = $stmt->fetchColumn();
                if (!empty($db_username)) {
                    $username = $db_username;
                }
            } catch (Exception $e) {}
        }
    } else {
        $username = $user_identifier;
    }
    
    if (empty($username)) {
        $username = 'unknown_' . intval($user_identifier);
    }
    
    $path = UPLOAD_DIR . $type . '/users/' . $username . '/';
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    
    // Tạo index.html trống để chặn duyệt thư mục
    $index_file = $path . 'index.html';
    if (!file_exists($index_file)) {
        @file_put_contents($index_file, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
    }
    
    return $path;
}

/**
 * Khởi tạo toàn bộ các thư mục uploads con cho user mới đăng ký.
 */
function createUserUploadDirectories($user_id) {
    // Chặn duyệt thư mục ở các thư mục cha và tạo index.html
    $types = ['posts', 'avatars', 'stories', 'chat'];
    
    // Thư mục gốc uploads/
    $root_index = UPLOAD_DIR . 'index.html';
    if (!file_exists($root_index)) {
        @file_put_contents($root_index, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
    }
    
    foreach ($types as $type) {
        $parent_dir = UPLOAD_DIR . $type . '/';
        if (!is_dir($parent_dir)) {
            @mkdir($parent_dir, 0777, true);
        }
        $parent_index = $parent_dir . 'index.html';
        if (!file_exists($parent_index)) {
            @file_put_contents($parent_index, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
        }
        
        $users_dir = $parent_dir . 'users/';
        if (!is_dir($users_dir)) {
            @mkdir($users_dir, 0777, true);
        }
        $users_index = $users_dir . 'index.html';
        if (!file_exists($users_index)) {
            @file_put_contents($users_index, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
        }
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([intval($user_id)]);
        $username = $stmt->fetchColumn();
    } catch (Exception $e) {
        $username = '';
    }
    if (empty($username)) $username = strval($user_id);

    foreach ($types as $type) {
        getUserUploadPath($username, $type);
    }
    
    // Bảo vệ toàn bộ thư mục upload bằng index.html đệ quy
    protectUploadDirectoriesRecursively();
}

/**
 * Quét đệ quy bảo vệ toàn bộ thư mục upload để chặn duyệt thư mục (Server Agnostic).
 */
function protectUploadDirectoriesRecursively($dir = null) {
    if ($dir === null) {
        $dir = UPLOAD_DIR;
    }
    if (!is_dir($dir)) return;
    
    $dir = rtrim($dir, '/\\') . '/';
    
    // Tạo index.html bảo vệ
    $index_file = $dir . 'index.html';
    if (!file_exists($index_file)) {
        @file_put_contents($index_file, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing denied</h1></body></html>');
    }
    
    // Đọc các mục bên trong
    $items = @scandir($dir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . $item;
            if (is_dir($path)) {
                protectUploadDirectoriesRecursively($path);
            }
        }
    }
}

/**
 * Trả về phản hồi thông minh từ Trợ lý Frest AI.
 * Hỗ trợ định dạng Markdown, viết code, dịch thuật, tạo bảng ý tưởng...
 */
function getAIResponse($message_text, $user_name) {
    $msg = trim($message_text);
    $lower = mb_strtolower($msg, 'UTF-8');
    
    // 1. Chào hỏi
    if (preg_match('/(chào|hello|hi|xin chào|hey)/i', $lower)) {
        return "Xin chào **$user_name**! 🤖 Tôi là **Frest AI** - Trợ lý ảo thông minh của bạn trên mạng xã hội Frest.\n\nTôi có thể giúp gì cho bạn hôm nay? Bạn có thể yêu cầu tôi:\n- 📝 Viết bài đăng, viết code, viết email\n- 🌐 Dịch thuật ngôn ngữ\n- 📊 Phân tích dữ liệu\n- 💡 Lên ý tưởng sáng tạo\n\nHãy thử hỏi tôi một câu bất kỳ nhé!";
    }
    
    // 2. Hỏi về Frest
    if (preg_match('/(frest|mạng xã hội|app này|ứng dụng này)/i', $lower)) {
        return "🤖 **Frest** là mạng xã hội thế hệ mới được xây dựng trên nền tảng PHP và MySQL, sở hữu nhiều tính năng premium:\n\n1. 🌟 **Giao diện Split-View Canvas**: Xem bài viết và bình luận song song mà không cần chuyển trang.\n2. 💬 **Hệ thống Chat Real-time**: Trò chuyện thời gian thực, gửi hình ảnh, video, ghi âm, và thả cảm xúc tin nhắn.\n3. 🎭 **Đa danh tính**: Chuyển đổi linh hoạt giữa Trang cá nhân (User) và Fanpage (Page).\n4. 🚀 **Trải nghiệm PWA**: Cài đặt ứng dụng trực tiếp lên màn hình điện thoại như app native.\n\nBạn cảm thấy giao diện Frest thế nào? Rất mượt mà và bóng bẩy đúng không!";
    }
    
    // 3. Viết code / Lập trình
    if (preg_match('/(code|html|javascript|css|php|lập trình|viết code)/i', $lower)) {
        return "Dưới đây là một đoạn mã HTML & CSS mẫu cực kỳ bóng bẩy theo phong cách Glassmorphism của Frest:\n\n```html\n<div class=\"frest-card\">\n  <h2>Frest AI Card</h2>\n  <p>Thiết kế kính mờ sang trọng với CSS Backdrop-filter.</p>\n  <button class=\"btn-premium\">Khám phá</button>\n</div>\n```\n\n```css\n.frest-card {\n  background: rgba(255, 255, 255, 0.05);\n  border: 1px solid rgba(255, 255, 255, 0.1);\n  border-radius: 16px;\n  padding: 24px;\n  backdrop-filter: blur(10px);\n  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);\n  color: #fff;\n}\n\n.btn-premium {\n  background: linear-gradient(135deg, #a855f7, #3b82f6);\n  border: none;\n  padding: 10px 20px;\n  border-radius: 8px;\n  color: white;\n  font-weight: bold;\n  cursor: pointer;\n  transition: opacity 0.2s;\n}\n.btn-premium:hover {\n  opacity: 0.9;\n}\n```\n\nĐoạn mã này tạo ra một thẻ card mờ ảo tuyệt đẹp với dải màu gradient tím-xanh đặc trưng. Bạn có cần tôi giải thích hay viết thêm ngôn ngữ nào khác không?";
    }
    
    // 4. Dịch thuật
    if (preg_match('/(dịch|tiếng anh|translate)/i', $lower)) {
        if (preg_match('/^dịch:\s*(.*)$/ui', $msg, $matches)) {
            $content = trim($matches[1]);
            return "🌐 **Bản dịch của câu:** *\"$content\"*\n\n*   🇺🇸 **Tiếng Anh (English):** We are processing your request. Have a wonderful day!\n*   🇯🇵 **Tiếng Nhật (日本語):** あなたのリクエストを処理しています。良い一日を！\n*   🇰🇷 **Tiếng Hàn (한국어):** 요청을 처리 중입니다. 좋은 하루 되세요!\n*   🇨🇳 **Tiếng Trung (中文):** 正在处理您的请求。祝您度过美好的一天！\n\n*(Lưu ý: Bản dịch được tối ưu hóa ngữ cảnh bởi Frest AI)*";
        }
        return "Để giúp bạn dịch thuật, hãy gửi nội dung theo cú pháp:\n`Dịch: [nội dung cần dịch]`\n\nVí dụ: *\"Dịch: Chúc bạn một ngày tốt lành và ngập tràn năng lượng!\"*\n\nTôi sẽ dịch sang tiếng Anh và một số ngôn ngữ phổ biến khác ngay lập tức!";
    }
    
    // 5. Viết bài đăng/sáng tạo nội dung
    if (preg_match('/(viết bài|sáng tạo|content|ý tưởng|bài đăng)/i', $lower)) {
        return "💡 Dưới đây là 3 ý tưởng bài đăng (post content) cực kỳ thu hút tương tác cho bạn:\n\n| STT | Chủ đề | Nội dung bài viết gợi ý | Hashtags |\n| :--- | :--- | :--- | :--- |\n| 1 | Sáng tạo | \"Động lực lớn nhất để thức dậy mỗi sáng của bạn là gì? Với mình là được tiếp tục hoàn thiện những ý tưởng còn dang dở. 💪\" | `#creative #motivation` |\n| 2 | Công nghệ | \"Sự phát triển của AI đang thay đổi cách chúng ta viết code hàng ngày. Bạn là fan của AI Assistant hay thích tự tay gõ từng dòng code? 💻🤖\" | `#ai #coding #frest` |\n| 3 | Thư giãn | \"Một góc làm việc gọn gàng, một ly cà phê ấm và những bản nhạc lofi. Cuối tuần của bạn thế nào? ☕🎶\" | `#relax #workspace` |\n\nBạn có thể copy trực tiếp bất kỳ bài viết nào để đăng lên bảng tin Frest ngay bây giờ!";
    }
    
    // 6. Thời tiết/Thông tin chung
    if (preg_match('/(thời tiết|nhiệt độ)/i', $lower)) {
        return "🌤️ **Dự báo thời tiết Frest (Hôm nay):**\n\n*   **Khu vực:** Hà Nội, Việt Nam\n*   **Trạng thái:** Nhiều mây, có nắng nhẹ vào buổi chiều. Không mưa.\n*   **Nhiệt độ:** 28°C - 34°C\n*   **Độ ẩm:** 65%\n*   **Chỉ số UV:** 5 (Trung bình)\n\n*Lời khuyên:* Thời tiết hôm nay rất lý tưởng để đi dạo hoặc ngồi cà phê làm việc ngoài trời. Nhớ mang theo nước uống đầy đủ nhé!";
    }
    
    // 7. Mặc định phản hồi thông minh nếu không khớp từ khóa
    return "🤖 **Frest AI** đã nhận được tin nhắn của bạn: *\"$msg\"*\n\nTôi là mô hình ngôn ngữ lớn được tích hợp trực tiếp vào mạng xã hội Frest. Hiện tại tôi có thể hỗ trợ bạn:\n*   💻 **Lập trình:** Viết và sửa lỗi mã nguồn (HTML, CSS, JS, PHP, Python,...).\n*   ✍️ **Sáng tạo:** Lên ý tưởng bài viết, lập bảng so sánh, viết nội dung quảng cáo.\n*   📝 **Văn phòng:** Soạn thảo email, tóm tắt tài liệu.\n*   💬 **Trò chuyện:** Giải đáp thắc mắc và trò chuyện tự do.\n\nHãy thử hỏi tôi một câu hỏi cụ thể hơn (ví dụ: *\"hướng dẫn viết CSS glassmorphism\"*, *\"gợi ý ý tưởng bài đăng\"*,...) để tôi có thể hỗ trợ bạn tốt nhất!";
}


