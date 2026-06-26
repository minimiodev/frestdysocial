<?php
/**
 * Create New Post Page - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    header("Location: login.php");
    exit;
}

$error_msg = '';

// Handle creating a new post (Frest)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_post'])) {
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
            
            // 1. Extract and save Hashtags
            extractAndSaveHashtags($post_id, $content);
            
            // 2. Poll options
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

// Now it's safe to include header
require_once __DIR__ . '/includes/header.php';
$identity = getCurrentIdentity();
?>

<style>
/* Reset composer modal styling when displayed as a standalone page card */
.compose-page-wrapper {
    display: block !important;
}

.compose-page-card {
    display: flex !important;
    flex-direction: column !important;
    overflow: visible !important; /* Allow card to expand naturally and prevent clipping */
    height: auto !important;
    max-height: none !important;
    border-radius: 20px !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.6) !important;
}

body.light-theme .compose-page-card {
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.1) !important;
}

/* Ensure the form has block layout and scales to its contents */
#compose-frest-form {
    display: block !important;
    height: auto !important;
}

/* Modal body on standalone page should not scroll internally */
.compose-page-card .compose-modal-body {
    display: flex !important;
    flex-direction: column !important;
    flex: none !important;
    overflow-y: visible !important; /* Scroll naturally with the page */
    height: auto !important;
    max-height: none !important;
    padding: 24px !important;
}

/* Perfect centering of the header title and absolute positioning of the close button */
.compose-page-card .compose-modal-header {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px 24px !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
}

body.light-theme .compose-page-card .compose-modal-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
}

.compose-page-card .frest-compose-title {
    font-size: 20px !important;
    font-weight: 800 !important;
    background: var(--accent-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-align: center !important;
    margin: 0 !important;
    flex: none !important;
}

.compose-page-card .close-compose-modal {
    position: absolute !important;
    right: 20px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 20px !important;
    color: var(--text-secondary) !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.compose-page-card .close-compose-modal:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    color: var(--text-primary) !important;
    transform: translateY(-50%) rotate(90deg) !important;
}

/* Redesign the author and text area inner container */
.compose-page-card .frest-compose-body-inner {
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 16px !important;
    padding: 16px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    margin-bottom: 12px !important;
}

body.light-theme .compose-page-card .frest-compose-body-inner {
    background: rgba(0, 0, 0, 0.015) !important;
    border: 1px solid rgba(0, 0, 0, 0.05) !important;
}

.compose-page-card .frest-compose-body-inner:focus-within {
    background: rgba(255, 255, 255, 0.04) !important;
    border-color: var(--accent-primary) !important;
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15) !important;
}

body.light-theme .compose-page-card .frest-compose-body-inner:focus-within {
    background: rgba(0, 0, 0, 0.03) !important;
    border-color: var(--accent-primary) !important;
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.08) !important;
}

/* Spacing and scrollbars for textarea */
.compose-page-card .frest-compose-textarea {
    margin-top: 12px !important;
    height: 160px !important;
    font-size: 16px !important;
    padding: 4px 0 !important;
}

.compose-page-card .frest-compose-textarea::-webkit-scrollbar {
    width: 6px;
}
.compose-page-card .frest-compose-textarea::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}
body.light-theme .compose-page-card .frest-compose-textarea::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
}

body.light-theme .compose-page-card .close-compose-modal {
    background: rgba(0, 0, 0, 0.04) !important;
}

body.light-theme .compose-page-card .close-compose-modal:hover {
    background: rgba(0, 0, 0, 0.08) !important;
}

/* Unified Top Toolbar Wrapper */
.compose-page-card .frest-top-toolbar-wrapper {
    display: inline-flex !important; /* Shrink to fit contents */
    align-items: center !important;
    justify-content: center !important; /* Centered layout for all 6 buttons */
    padding: 6px 10px !important;
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.06) !important;
    border-radius: 24px !important; /* Beautiful rounded pill */
    margin: 0 auto 16px auto !important; /* Horizontal centering in parent flex container */
    gap: 8px !important;
    flex-wrap: nowrap !important;
    width: max-content !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

body.light-theme .compose-page-card .frest-top-toolbar-wrapper {
    background: rgba(0, 0, 0, 0.015) !important;
    border: 1px solid rgba(0, 0, 0, 0.05) !important;
}

.compose-page-card .frest-compose-toolbar {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    border-right: 1px solid rgba(255, 255, 255, 0.12) !important; /* Subtle vertical divider */
    padding-right: 8px !important;
}

body.light-theme .compose-page-card .frest-compose-toolbar {
    border-right: 1px solid rgba(0, 0, 0, 0.08) !important;
}

.compose-page-card .frest-compose-options-pills {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
}

/* Redesign tool buttons */
.compose-page-card .frest-toolbar-btn {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.06) !important;
    width: 36px !important;
    height: 36px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    color: var(--text-secondary) !important;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
}

body.light-theme .compose-page-card .frest-toolbar-btn {
    background: rgba(0, 0, 0, 0.03) !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
}

/* Individual hover styles with branded colors and glows */
.compose-page-card #frest-attach-media:hover {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(69, 189, 98, 0.1) !important;
    border-color: #45bd62 !important;
    color: #45bd62 !important;
    box-shadow: 0 4px 12px rgba(69, 189, 98, 0.2) !important;
}
.compose-page-card #frest-attach-doc:hover {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(24, 119, 242, 0.1) !important;
    border-color: #1877f2 !important;
    color: #1877f2 !important;
    box-shadow: 0 4px 12px rgba(24, 119, 242, 0.2) !important;
}
.compose-page-card #frest-attach-audio:hover {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(192, 132, 252, 0.1) !important;
    border-color: #c084fc !important;
    color: #c084fc !important;
    box-shadow: 0 4px 12px rgba(192, 132, 252, 0.2) !important;
}
.compose-page-card #frest-attach-link:hover {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(247, 185, 36, 0.1) !important;
    border-color: #f7b924 !important;
    color: #f7b924 !important;
    box-shadow: 0 4px 12px rgba(247, 185, 36, 0.2) !important;
}

/* Option Pill Styling */
.compose-page-card .frest-option-pill {
    position: relative !important;
    display: inline-block !important;
    cursor: pointer !important;
    user-select: none !important;
}

.compose-page-card .frest-pill-checkbox {
    position: absolute !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
    margin: 0 !important;
}

/* Always circular layout for option pills content to achieve perfect symmetry */
.compose-page-card .frest-pill-content {
    width: 36px !important;
    height: 36px !important;
    padding: 0 !important;
    border-radius: 50% !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.06) !important;
    color: var(--text-secondary) !important;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    box-sizing: border-box !important;
}

body.light-theme .compose-page-card .frest-pill-content {
    background: rgba(0, 0, 0, 0.03) !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
}

/* Hide text globally in compose options to maintain circular toolbar balance */
.compose-page-card .frest-pill-content .pill-text {
    display: none !important;
}

.compose-page-card .frest-pill-content i {
    font-size: 14px !important;
    transition: transform 0.2s ease !important;
}

/* 18+ Text Badge Styling inside circle */
.compose-page-card .frest-nsfw-badge-icon {
    font-size: 11px !important;
    font-weight: 800 !important;
    border: none !important;
    padding: 0 !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-family: system-ui, -apple-system, sans-serif !important;
    transition: all 0.2s ease !important;
}

/* Default unchecked colors (grey/inactive) */
.compose-page-card #allow_download_toggle:not(:checked) + .frest-pill-content i {
    color: var(--text-secondary) !important;
}
.compose-page-card #is_nsfw_toggle:not(:checked) + .frest-pill-content .frest-nsfw-badge-icon {
    color: var(--text-secondary) !important;
}

/* Unchecked options hover states: light colored backgrounds and border colors */
.compose-page-card .frest-option-pill:hover #allow_download_toggle:not(:checked) + .frest-pill-content {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(56, 189, 248, 0.1) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2) !important;
}
.compose-page-card .frest-option-pill:hover #allow_download_toggle:not(:checked) + .frest-pill-content i {
    color: #38bdf8 !important;
}

.compose-page-card .frest-option-pill:hover #is_nsfw_toggle:not(:checked) + .frest-pill-content {
    transform: scale(1.08) translateY(-2px) !important;
    background: rgba(244, 63, 94, 0.1) !important;
    border-color: #f43f5e !important;
    box-shadow: 0 4px 12px rgba(244, 63, 94, 0.2) !important;
}
.compose-page-card .frest-option-pill:hover #is_nsfw_toggle:not(:checked) + .frest-pill-content .frest-nsfw-badge-icon {
    color: #f43f5e !important;
}

/* Active style for checked checkboxes (gradient backgrounds, white icons) */
.compose-page-card #allow_download_toggle:checked + .frest-pill-content {
    background: var(--accent-gradient) !important;
    border-color: transparent !important;
    color: #fff !important;
    box-shadow: 0 4px 12px var(--accent-glow) !important;
}
.compose-page-card #allow_download_toggle:checked + .frest-pill-content i {
    color: #fff !important;
}

.compose-page-card #is_nsfw_toggle:checked + .frest-pill-content {
    background: linear-gradient(135deg, #ef4444, #f43f5e) !important;
    border-color: transparent !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4) !important;
}
.compose-page-card #is_nsfw_toggle:checked + .frest-pill-content .frest-nsfw-badge-icon {
    color: #fff !important;
    border-color: #fff !important;
}

/* Checked options hover states: keep active gradients and add brightness + extra glow shadow */
.compose-page-card .frest-option-pill:hover #allow_download_toggle:checked + .frest-pill-content {
    transform: scale(1.08) translateY(-2px) !important;
    background: var(--accent-gradient) !important;
    filter: brightness(1.08) !important;
    box-shadow: 0 6px 16px var(--accent-glow) !important;
}
.compose-page-card .frest-option-pill:hover #is_nsfw_toggle:checked + .frest-pill-content {
    transform: scale(1.08) translateY(-2px) !important;
    background: linear-gradient(135deg, #ef4444, #f43f5e) !important;
    filter: brightness(1.08) !important;
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.45) !important;
}

/* Responsive adjustment for toolbar on mobile viewport */
@media (max-width: 480px) {
    .compose-page-card .frest-top-toolbar-wrapper {
        padding: 5px 8px !important;
        gap: 6px !important;
        border-radius: 20px !important;
    }
    
    .compose-page-card .frest-compose-toolbar {
        gap: 6px !important;
        padding-right: 6px !important;
    }
    
    .compose-page-card .frest-compose-options-pills {
        gap: 6px !important;
    }
    
    .compose-page-card .frest-toolbar-btn {
        width: 34px !important;
        height: 34px !important;
        font-size: 14px !important;
    }
    
    .compose-page-card .frest-pill-content {
        width: 34px !important;
        height: 34px !important;
    }
    
    .compose-page-card .frest-pill-content i {
        font-size: 13px !important;
    }
    
    .compose-page-card .frest-pill-content .frest-nsfw-badge-icon {
        font-size: 10px !important;
    }
}

/* Ensure footer and submit button are positioned naturally at the bottom */
.compose-page-card .compose-modal-footer {
    display: flex !important;
    flex-direction: column !important;
    flex: none !important;
    height: auto !important;
    padding: 24px !important;
    border-radius: 0 0 20px 20px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
}

body.light-theme .compose-page-card .compose-modal-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.05) !important;
}

/* Submit button should be fully visible with normal margin/padding */
.compose-page-card .frest-compose-submit-btn {
    background: var(--accent-gradient) !important;
    border: none !important;
    color: #fff !important;
    font-weight: 800 !important;
    padding: 14px 28px !important;
    border-radius: 12px !important;
    font-size: 15px !important;
    cursor: pointer !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 4px 15px var(--accent-glow) !important;
    position: relative !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    width: 100% !important;
    margin-top: 10px !important;
}

.compose-page-card .frest-compose-submit-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px var(--accent-glow) !important;
    filter: brightness(1.1) !important;
}

.compose-page-card .frest-compose-submit-btn:active {
    transform: translateY(1px) !important;
}

/* Adjust toolbar on mobile viewports */
@media (max-width: 480px) {
    .compose-page-card .compose-modal-footer {
        padding: 16px !important;
        gap: 12px !important;
    }
}

/* Optimize Post media previews */
.compose-page-card .compose-preview-grid {
    display: grid !important;
    gap: 10px !important;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important;
    margin-top: 14px !important;
}
.compose-page-card .compose-preview-grid.cols-1 {
    grid-template-columns: 1fr !important;
}
.compose-page-card .compose-preview-grid.cols-2 {
    grid-template-columns: repeat(2, 1fr) !important;
}
.compose-page-card .compose-preview-item {
    position: relative !important;
    aspect-ratio: 16 / 9 !important; /* Premium widescreen look */
    border-radius: 12px !important;
    overflow: hidden !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    background: rgba(0, 0, 0, 0.2) !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
body.light-theme .compose-page-card .compose-preview-item {
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
    background: rgba(0, 0, 0, 0.03) !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
}
.compose-page-card .compose-preview-item:hover {
    transform: translateY(-2px) scale(1.02) !important;
    border-color: var(--accent-primary) !important;
    box-shadow: 0 8px 25px rgba(139, 92, 246, 0.25) !important;
}
.compose-page-card .compose-video-play-icon {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    font-size: 24px !important;
    color: rgba(255, 255, 255, 0.95) !important;
    background: rgba(0, 0, 0, 0.4) !important;
    width: 44px !important;
    height: 44px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    backdrop-filter: blur(4px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    pointer-events: none !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.2s ease !important;
}
.compose-page-card .compose-preview-item:hover .compose-video-play-icon {
    transform: translate(-50%, -50%) scale(1.1) !important;
    background: var(--accent-gradient) !important;
    border-color: transparent !important;
    color: #fff !important;
    box-shadow: 0 4px 15px var(--accent-glow) !important;
}
.compose-page-card .compose-img-count-badge {
    margin-bottom: 12px !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: var(--text-secondary) !important;
    padding: 6px 14px !important;
    border-radius: 20px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
}
body.light-theme .compose-page-card .compose-img-count-badge {
    background: rgba(0, 0, 0, 0.03) !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
}

/* Custom transitions and hover states for attachment remove buttons */
.compose-page-card .frest-compose-remove-btn,
.compose-page-card .remove-attachment-btn {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.compose-page-card .frest-compose-remove-btn:hover,
.compose-page-card .remove-attachment-btn:hover {
    background: var(--danger) !important;
    transform: scale(1.15) !important;
    color: #fff !important;
}

/* Page container layout structure and responsive adjustments */
.compose-page-container {
    max-width: 600px;
    margin: 40px auto 0;
    padding-bottom: 100px;
}

@media (max-width: 768px) {
    .compose-page-container {
        margin: 16px auto 0 !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
        padding-bottom: 60px !important;
    }
}
</style>

<div class="container section compose-page-container">
    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- We wrap the composer in compose-modal ID to let compose.js detect it automatically -->
    <div id="compose-modal" class="compose-page-wrapper">
        <div class="compose-page-card glassmorphism-card frest-card">
            <div class="compose-modal-header">
                <h3 class="frest-compose-title">Tạo bài viết</h3>
                <a href="<?php echo SITE_URL; ?>/index.php" class="close-compose-modal" style="text-decoration: none;">&times;</a>
            </div>
            
            <form action="create_post.php" method="POST" enctype="multipart/form-data" id="compose-frest-form">
                <input type="hidden" name="action_create_post" value="1">

                <div class="compose-modal-body">
                    <!-- Unified Toolbar (Attachments and Settings) - Placed at the top for accessibility -->
                    <div class="frest-top-toolbar-wrapper">
                        <div class="frest-compose-toolbar">
                            <button type="button" class="frest-toolbar-btn" id="frest-attach-media" title="Đính kèm Hình ảnh / Video">
                                <i class="fa-regular fa-image" style="color: #45bd62;"></i>
                            </button>
                            <button type="button" class="frest-toolbar-btn" id="frest-attach-doc" title="Đính kèm Tài liệu / Zip / Phần mềm">
                                <i class="fa-regular fa-file-lines" style="color: #1877f2;"></i>
                            </button>
                            <button type="button" class="frest-toolbar-btn" id="frest-attach-audio" title="Đính kèm Âm thanh">
                                <i class="fa-solid fa-music" style="color: #c084fc;"></i>
                            </button>
                            <button type="button" class="frest-toolbar-btn" id="frest-attach-link" title="Đính kèm Liên kết">
                                <i class="fa-solid fa-link" style="color: #f7b924;"></i>
                            </button>
                        </div>
                        
                        <div class="frest-compose-options-pills">
                            <!-- Allow Download Toggle Pill -->
                            <label class="frest-option-pill" title="Cho phép tải phương tiện/tập tin về">
                                <input type="checkbox" name="allow_download" value="1" checked id="allow_download_toggle" class="frest-pill-checkbox">
                                <span class="frest-pill-content">
                                    <i class="fa-solid fa-download"></i> <span class="pill-text">Tải về</span>
                                </span>
                            </label>
                            
                            <!-- NSFW Toggle Pill -->
                            <label class="frest-option-pill" title="Đánh dấu nội dung 18+ nhạy cảm">
                                <input type="checkbox" name="is_nsfw" value="1" id="is_nsfw_toggle" class="frest-pill-checkbox">
                                <span class="frest-pill-content">
                                    <span class="frest-nsfw-badge-icon">18+</span> <span class="pill-text">18+</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="frest-compose-body-inner">
                        <div class="frest-compose-author-row">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($identity['avatar']); ?>" 
                                 class="frest-compose-author-avatar">
                            <span class="frest-compose-author-name"><?php echo $identity['type'] === 'page' ? sanitize($identity['name']) : '@' . sanitize($identity['username']); ?></span>
                            <?php if ($identity['type'] === 'page'): ?>
                                 <span class="frest-compose-badge">Trang</span>
                            <?php endif; ?>
                        </div>

                        <textarea name="content" id="frest-content-input" class="frest-compose-textarea" placeholder="Hôm nay bạn muốn chia sẻ điều gì?..." required style="height: 160px; font-size: 16px;"></textarea>
                    </div>

                    <!-- Attachment Previews (handled dynamically by compose.js) -->
                    <div id="image-attachment-preview" class="frest-compose-preview-box image-preview" style="display: none;">
                        <img src="" class="frest-compose-preview-img">
                        <button type="button" class="remove-attachment-btn">&times;</button>
                    </div>

                    <div id="video-attachment-preview" class="frest-compose-preview-box video-preview" style="display: none;">
                        <video src="" controls class="frest-compose-preview-video"></video>
                        <button type="button" class="remove-video-btn frest-compose-remove-btn">&times;</button>
                    </div>

                    <div id="audio-attachment-preview" class="frest-compose-preview-box audio-preview" style="display: none;">
                        <div class="frest-compose-preview-header">
                            <span class="frest-compose-preview-title"><i class="fa-solid fa-music"></i> File âm thanh đã chọn</span>
                            <button type="button" class="remove-audio-btn frest-compose-remove-btn">&times;</button>
                        </div>
                        <audio src="" controls class="frest-compose-preview-audio"></audio>
                    </div>

                    <div id="document-attachment-preview" class="frest-compose-preview-box document-preview" style="display: none;">
                        <div class="frest-compose-doc-info">
                            <i class="fa-regular fa-file-pdf" id="document-preview-icon"></i>
                            <div class="frest-compose-doc-meta-wrap">
                                <div class="frest-compose-doc-meta" id="document-preview-name">file.pdf</div>
                                <div class="frest-compose-doc-size" id="document-preview-size">0 KB</div>
                            </div>
                        </div>
                        <button type="button" class="remove-document-btn frest-compose-remove-btn">&times;</button>
                    </div>

                    <div id="software-attachment-preview" class="frest-compose-preview-box software-preview" style="display: none;">
                        <div class="frest-compose-doc-info">
                            <i class="fa-solid fa-cubes" id="software-preview-icon"></i>
                            <div class="frest-compose-doc-meta-wrap">
                                <div class="frest-compose-doc-meta" id="software-preview-name">app.zip</div>
                                <div class="frest-compose-doc-size" id="software-preview-size">0 KB</div>
                            </div>
                        </div>
                        <button type="button" class="remove-software-btn frest-compose-remove-btn">&times;</button>
                    </div>

                    <div id="link-preview-attachment" class="frest-compose-preview-box link-preview" style="display: none;">
                        <button type="button" id="remove-link-preview-btn" class="frest-compose-remove-btn">&times;</button>
                        <div class="frest-compose-link-layout">
                            <img id="link-preview-img" src="" class="frest-compose-link-img" style="display: none;">
                            <div class="frest-compose-link-info">
                                <div class="frest-compose-link-domain-wrap">
                                    <i class="fa-solid fa-link"></i> <span id="link-preview-dom"></span>
                                </div>
                                <div id="link-preview-title" class="frest-compose-link-title"></div>
                                <div id="link-preview-desc" class="frest-compose-link-desc"></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="link_preview_url" id="link-preview-input-url">
                    <input type="hidden" name="link_preview_title" id="link-preview-input-title">
                    <input type="hidden" name="link_preview_desc" id="link-preview-input-desc">
                    <input type="hidden" name="link_preview_image" id="link-preview-input-image">
                </div>

                <div class="compose-modal-footer">
                    <input type="file" name="post_media[]" id="post_media_upload" accept="image/*,video/*,audio/*,.pdf,.docx,.txt,.zip,.apk,.exe" multiple style="display: none;">
                    
                    <button type="submit" class="btn-primary frest-compose-submit-btn">
                        Frest ngay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- compose.js and initComposeModal() are automatically loaded and executed in footer.php and main.js -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
