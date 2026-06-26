<?php
/**
 * User Profile & Edit Screen - Frest App
 * NOTE: All data loading and redirects MUST happen before including header.php
 * because header.php outputs HTML which prevents header() calls.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$username = isset($_GET['username']) ? sanitize($_GET['username']) : '';
$error_msg = '';
$success_msg = isset($_GET['success']) ? sanitize($_GET['success']) : '';

$profile_user = null;
$user_posts = [];
$followers_count = 0;
$following_count = 0;
$is_following = false;
$is_my_profile = false;
$profile_id = null;

// Must initialize $me here since header.php (which normally defines $me) is included AFTER this block
$me = getLoggedInUser();

// Redirect old tabs to their new dedicated pages
if (isset($_GET['tab'])) {
    $redir_tab = trim($_GET['tab']);
    if ($redir_tab === 'saved') {
        header("Location: bookmarks.php");
        exit;
    } elseif ($redir_tab === 'pages') {
        $query_params = [];
        if (isset($_GET['error'])) $query_params['error'] = $_GET['error'];
        if (isset($_GET['success'])) $query_params['success'] = $_GET['success'];
        $query_str = !empty($query_params) ? '?' . http_build_query($query_params) : '';
        header("Location: pages.php" . $query_str);
        exit;
    } elseif ($redir_tab === 'settings') {
        header("Location: settings.php");
        exit;
    }
}

try {
    $db = getDB();
    try { $db->exec("ALTER TABLE users ADD COLUMN cover_filename VARCHAR(255) NULL DEFAULT NULL"); } catch(PDOException $ig){}

    // 1. Resolve Profile Identity
    $is_page_profile = false;
    $profile_page = null;
    $profile_user = null;

    if (empty($username)) {
        if (!isUserLoggedIn()) {
            header("Location: login.php");
            exit;
        }
        $identity = getCurrentIdentity();
        if ($identity && $identity['type'] === 'page') {
            $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
            $stmt->execute([$identity['id']]);
            $profile_page = $stmt->fetch();
            if ($profile_page) {
                $is_page_profile = true;
            }
        } else {
            $profile_user = $me;
        }
    } else {
        // Try page first
        $stmt = $db->prepare("SELECT * FROM pages WHERE page_username = ?");
        $stmt->execute([$username]);
        $profile_page = $stmt->fetch();
        if ($profile_page) {
            $is_page_profile = true;
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $profile_user = $stmt->fetch();
        }
    }

    // Redirect logic: page profile -> page.php, user profile -> profile.php
    $current_script = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    
    if ($is_page_profile && $current_script === 'profile.php') {
        $redir_username = !empty($username) ? $username : ($profile_page ? $profile_page['page_username'] : '');
        if (!empty($redir_username)) {
            header("Location: page.php?username=" . urlencode($redir_username) . (!empty($success_msg) ? "&success=" . urlencode($success_msg) : ""));
            exit;
        }
    }
    
    if (!$is_page_profile && $current_script === 'page.php') {
        $redir_username = !empty($username) ? $username : ($profile_user ? $profile_user['username'] : '');
        if (!empty($redir_username)) {
            header("Location: profile.php?username=" . urlencode($redir_username) . (!empty($success_msg) ? "&success=" . urlencode($success_msg) : ""));
            exit;
        }
    }

    if (!$is_page_profile && $current_script === 'profile.php' && empty($username) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $redir_username = $profile_user ? $profile_user['username'] : '';
        if (!empty($redir_username)) {
            header("Location: profile.php?username=" . urlencode($redir_username) . (!empty($success_msg) ? "&success=" . urlencode($success_msg) : ""));
            exit;
        }
    }

    // Map common variables for easy UI rendering
    if ($is_page_profile) {
        $profile_id = $profile_page['id'];
        $profile_name = $profile_page['page_name'];
        $profile_handle = $profile_page['page_username'];
        $profile_avatar = $profile_page['avatar_filename'];
        $profile_cover = $profile_page['cover_filename'];
        $profile_bio = $profile_page['bio'];
        $profile_category = $profile_page['category'] ?: 'Cộng đồng';
        $is_my_profile = ($me && $profile_page['owner_id'] === $me['id']);
        $profile_is_private = false;
        $viewer_can_see_posts = true;
        
        // Handle edit Page request
        if ($is_my_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_page'])) {
            $new_page_name = trim($_POST['page_name'] ?? '');
            $new_bio = trim($_POST['bio'] ?? '');
            $new_category = trim($_POST['category'] ?? 'Cộng đồng');
            
            // Contact and social fields
            $new_page_website = trim($_POST['website'] ?? '');
            $new_page_email = trim($_POST['email'] ?? '');
            $new_page_phone = trim($_POST['phone_number'] ?? '');
            $new_page_facebook = trim($_POST['facebook_link'] ?? '');
            $new_page_instagram = trim($_POST['instagram_link'] ?? '');
            $new_page_twitter = trim($_POST['twitter_link'] ?? '');
            $new_page_lives_at = trim($_POST['lives_at'] ?? '');
            $new_page_country = trim($_POST['country'] ?? '');

            // New detail fields
            $new_page_working_hours = trim($_POST['working_hours'] ?? '');
            $new_page_services = trim($_POST['services'] ?? '');
            $new_page_founded_at = trim($_POST['founded_at'] ?? '');

            // Visibility toggles
            $new_show_email         = isset($_POST['show_email'])         ? 1 : 0;
            $new_show_phone         = isset($_POST['show_phone'])         ? 1 : 0;
            $new_show_website       = isset($_POST['show_website'])       ? 1 : 0;
            $new_show_lives_at      = isset($_POST['show_lives_at'])      ? 1 : 0;
            $new_show_country       = isset($_POST['show_country'])       ? 1 : 0;
            $new_show_socials       = isset($_POST['show_socials'])       ? 1 : 0;
            $new_show_working_hours = isset($_POST['show_working_hours']) ? 1 : 0;
            $new_show_services      = isset($_POST['show_services'])      ? 1 : 0;
            $new_show_founded_at    = isset($_POST['show_founded_at'])    ? 1 : 0;

            if (empty($new_page_name) || strlen($new_page_name) < 2 || strlen($new_page_name) > 100) {
                $error_msg = "Tên Trang phải từ 2 đến 100 ký tự.";
            } else {
                // Handle avatar upload
                $avatar_filename = $profile_page['avatar_filename'];
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['avatar']['tmp_name'];
                    $file_name = $_FILES['avatar']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $new_name = 'page_avatar_' . uniqid() . '.' . $file_ext;
                        $db_save_avatar = 'users/' . $me['username'] . '/' . $new_name;
                        $dest = getUserUploadPath($me['username'], 'avatars') . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $avatar_filename = $db_save_avatar;
                        }
                    }
                }

                // Handle cover upload
                $cover_filename = $profile_page['cover_filename'];
                if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['cover']['tmp_name'];
                    $file_name = $_FILES['cover']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $new_name = 'page_cover_' . uniqid() . '.' . $file_ext;
                        $db_save_cover = 'users/' . $me['username'] . '/' . $new_name;
                        $dest = getUserUploadPath($me['username'], 'avatars') . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $cover_filename = $db_save_cover;
                        }
                    }
                }

                $new_sync_status = isset($_POST['sync_transparency_status']) ? 1 : 0;
                $page_name_changed = ($new_page_name !== $profile_page['page_name']);

                if ($page_name_changed) {
                    $stmt_hist = $db->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name) VALUES ('page', ?, ?, ?)");
                    $stmt_hist->execute([$profile_page['id'], $profile_page['page_name'], $new_page_name]);
                }

                $stmt = $db->prepare("UPDATE pages SET page_name = ?, bio = ?, category = ?, avatar_filename = ?, cover_filename = ?,
                    website = ?, email = ?, phone_number = ?, facebook_link = ?, instagram_link = ?, twitter_link = ?,
                    lives_at = ?, country = ?, working_hours = ?, services = ?, founded_at = ?,
                    show_email = ?, show_phone = ?, show_website = ?, show_lives_at = ?, show_country = ?,
                    show_socials = ?, show_working_hours = ?, show_services = ?, show_founded_at = ?,
                    updated_at = NOW(), sync_transparency_status = ?
                    WHERE id = ?");
                $stmt->execute([
                    $new_page_name, $new_bio, $new_category, $avatar_filename, $cover_filename,
                    $new_page_website ?: null, $new_page_email ?: null, $new_page_phone ?: null,
                    $new_page_facebook ?: null, $new_page_instagram ?: null, $new_page_twitter ?: null,
                    $new_page_lives_at ?: null, $new_page_country ?: null,
                    $new_page_working_hours ?: null, $new_page_services ?: null,
                    !empty($new_page_founded_at) ? $new_page_founded_at : null,
                    $new_show_email, $new_show_phone, $new_show_website, $new_show_lives_at, $new_show_country,
                    $new_show_socials, $new_show_working_hours, $new_show_services, $new_show_founded_at,
                    $new_sync_status,
                    $profile_page['id']
                ]);
                
                header("Location: page.php?username=" . urlencode($profile_page['page_username']) . "&success=" . urlencode("Cập nhật thông tin Trang thành công!"));
                exit;
            }
        }
    } else {
        if ($profile_user) {
            if (!empty($profile_user['qr_reset_at'])) {
                $db_qrr = strtotime($profile_user['qr_reset_at']);
                if (isset($_GET['qrr']) && intval($_GET['qrr']) < $db_qrr) {
                    $error_msg = "Mã QR này đã hết hạn hoặc đã được đặt lại.";
                    $profile_user = null;
                }
            }
        }

        if ($profile_user) {
            $profile_id = $profile_user['id'];
            $profile_name = $profile_user['full_name'] ?: $profile_user['username'];
            $profile_handle = $profile_user['username'];
            $profile_avatar = $profile_user['avatar_filename'];
            $profile_cover = $profile_user['cover_filename'] ?? null;
            $profile_bio = $profile_user['bio'];
            $profile_category = (intval($profile_user['is_page'] ?? 0) === 1) ? ($profile_user['page_category'] ?: 'Blog cá nhân') : null;
            $is_my_profile = ($me && $me['id'] === $profile_id);
            $profile_is_private = intval($profile_user['is_private'] ?? 0) === 1;
            $viewer_can_see_posts = !$profile_is_private
                || $is_my_profile
                || isAdminLoggedIn()
                || ($me && isFollowingUser($me['id'], $profile_id));

            // Handle Professional Mode conversion POST
            if ($me && $is_my_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_toggle_professional_mode'])) {
                $is_page = isset($_POST['is_page']) ? 1 : 0;
                $page_category = trim($_POST['page_category'] ?? 'Blog cá nhân');
                
                $stmt = $db->prepare("UPDATE users SET is_page = ?, page_category = ? WHERE id = ?");
                $stmt->execute([$is_page, $page_category, $me['id']]);
                
                header("Location: profile.php?success=" . urlencode("Cập nhật chế độ chuyên nghiệp thành công!"));
                exit;
            }
        } else {
            $profile_id = null;
            $is_my_profile = false;
        }
    }

    if ($profile_user || $profile_page) {
        $is_professional_user = (!$is_page_profile && $profile_user && intval($profile_user['is_page'] ?? 0) === 1);
        $is_pro_layout = ($is_page_profile || $is_professional_user);

        // 2. Handle Profile Edit Request
        // 2. Handle Profile Edit Request
        if ($is_my_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_profile'])) {
            $new_bio       = trim($_POST['bio'] ?? '');
            $new_private   = isset($_POST['is_private']) ? 1 : 0;
            $new_username  = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['username'] ?? ''));

            // Display Name inputs
            $new_first_name = trim($_POST['first_name'] ?? '');
            $new_middle_name = trim($_POST['middle_name'] ?? '');
            $new_last_name = trim($_POST['last_name'] ?? '');
            $new_display_order = trim($_POST['name_display_order'] ?? 'last_middle_first');

            // Email and Phone number inputs & settings
            $new_email = trim($_POST['email'] ?? '');
            $new_phone = trim($_POST['phone_number'] ?? '');
            $new_show_email = isset($_POST['show_email']) ? 1 : 0;
            $new_show_phone = isset($_POST['show_phone']) ? 1 : 0;
            
            $new_gender = trim($_POST['gender'] ?? '');
            $new_workplace = trim($_POST['workplace'] ?? '');
            $new_lives_at = trim($_POST['lives_at'] ?? '');
            $new_country = trim($_POST['country'] ?? '');
            $new_dob = trim($_POST['dob'] ?? '');
            
            $new_show_gender = isset($_POST['show_gender']) ? 1 : 0;
            $new_show_workplace = isset($_POST['show_workplace']) ? 1 : 0;
            $new_show_lives_at = isset($_POST['show_lives_at']) ? 1 : 0;
            $new_show_country = isset($_POST['show_country']) ? 1 : 0;
            $new_show_dob = isset($_POST['show_dob']) ? 1 : 0;
            
            // New fields for user social links
            $new_website = trim($_POST['website'] ?? '');
            $new_facebook = trim($_POST['facebook_link'] ?? '');
            $new_instagram = trim($_POST['instagram_link'] ?? '');
            $new_twitter = trim($_POST['twitter_link'] ?? '');
            $new_sync_status = isset($_POST['sync_transparency_status']) ? 1 : 0;

            // Handle username update validation
            $username_changed = ($new_username !== $profile_user['username']);
            $username_conflict = false;
            $username_to_save = $profile_user['username'];
            if ($username_changed) {
                if (!isAdminLoggedIn() && !empty($profile_user['username_last_updated'])) {
                    $last_un_update = strtotime($profile_user['username_last_updated']);
                    $un_days_elapsed = (time() - $last_un_update) / 86400;
                    if ($un_days_elapsed < 60) {
                        $un_remaining = ceil(60 - $un_days_elapsed);
                        $error_msg = "Bạn chỉ có thể thay đổi tên người dùng (username) 60 ngày một lần. Vui lòng đợi thêm " . $un_remaining . " ngày.";
                    }
                }
                if (empty($error_msg)) {
                    if (empty($new_username) || strlen($new_username) < 3 || strlen($new_username) > 30) {
                        $error_msg = "Tên người dùng mới phải dài từ 3 đến 30 ký tự và không chứa ký tự đặc biệt.";
                    } else {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                        $stmt->execute([$new_username, $profile_id]);
                        if ($stmt->fetchColumn() > 0) {
                            $error_msg = "Tên người dùng này đã được sử dụng bởi người khác.";
                            $username_conflict = true;
                        }
                    }
                }
                if (empty($error_msg) && !$username_conflict) {
                    $username_to_save = $new_username;
                }
            }

            // Sync mock email if username changed and it is a local mock email (e.g. contains @frest.local or @phone.local)
            if ($username_changed && empty($error_msg) && !$username_conflict) {
                $is_old_email_mock = (strpos($profile_user['email'], '@frest.local') !== false || strpos($profile_user['email'], '@phone.local') !== false);
                $is_post_email_same_as_old = ($new_email === $profile_user['email']);
                if ($is_old_email_mock || $is_post_email_same_as_old) {
                    if (!empty($new_phone) && strpos($profile_user['email'], '@phone.local') !== false) {
                        $phone_clean = preg_replace('/[^0-9]/', '', $new_phone);
                        $new_email = $phone_clean . '@phone.local';
                    } else {
                        $new_email = $new_username . '@frest.local';
                    }
                }
            }

            // Email and phone input validation
            if (empty($error_msg) && !$username_conflict) {
                if (empty($new_email)) {
                    $error_msg = "Địa chỉ email không được để trống.";
                } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $error_msg = "Địa chỉ email không hợp lệ.";
                } else {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$new_email, $profile_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error_msg = "Địa chỉ email này đã được sử dụng.";
                    }
                }
            }

            if (empty($error_msg) && !$username_conflict && !empty($new_phone)) {
                if (!preg_match('/^[0-9]{9,15}$/', preg_replace('/[^0-9]/', '', $new_phone))) {
                    $error_msg = "Số điện thoại không hợp lệ (phải từ 9 đến 15 chữ số).";
                } else {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE phone_number = ? AND id != ?");
                    $stmt->execute([$new_phone, $profile_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error_msg = "Số điện thoại này đã được sử dụng.";
                    }
                }
            }

            if (empty($error_msg) && !$username_conflict) {
                // Handle display name change
                $name_changed = ($new_first_name !== ($profile_user['first_name'] ?? '') || 
                                 $new_middle_name !== ($profile_user['middle_name'] ?? '') || 
                                 $new_last_name !== ($profile_user['last_name'] ?? '') ||
                                 $new_display_order !== ($profile_user['name_display_order'] ?? 'last_middle_first'));

                $can_change_immediately = true;
                if (!isAdminLoggedIn() && !empty($profile_user['display_name_last_updated'])) {
                    $last_update = strtotime($profile_user['display_name_last_updated']);
                    $days_elapsed = (time() - $last_update) / 86400;
                    if ($days_elapsed < 60) {
                        $can_change_immediately = false;
                    }
                }

                // Handle avatar upload
                $avatar_filename = $profile_user['avatar_filename'];
                if (isset($_POST['cropped_avatar']) && !empty($_POST['cropped_avatar'])) {
                    $data = $_POST['cropped_avatar'];
                    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                        $data = substr($data, strpos($data, ',') + 1);
                        $type = strtolower($type[1]);
                        if ($type === 'jpeg') $type = 'jpg';
                        if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $decoded_data = base64_decode($data);
                            if ($decoded_data !== false) {
                                $new_name = 'avatar_' . uniqid() . '.' . $type;
                                $db_save_avatar = 'users/' . $profile_user['username'] . '/' . $new_name;
                                $dest = getUserUploadPath($profile_user['username'], 'avatars') . $new_name;
                                if (file_put_contents($dest, $decoded_data)) {
                                    $avatar_filename = $db_save_avatar;
                                    $_SESSION['avatar'] = $db_save_avatar;
                                }
                            }
                        }
                    }
                } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['avatar']['tmp_name'];
                    $file_name = $_FILES['avatar']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $new_name = 'avatar_' . uniqid() . '.' . $file_ext;
                        $db_save_avatar = 'users/' . $profile_user['username'] . '/' . $new_name;
                        $dest = getUserUploadPath($profile_user['username'], 'avatars') . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $avatar_filename = $db_save_avatar;
                            $_SESSION['avatar'] = $db_save_avatar;
                        }
                    }
                }

                // Handle cover upload
                $cover_filename = $profile_user['cover_filename'] ?? null;
                if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['cover']['tmp_name'];
                    $file_name = $_FILES['cover']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $new_name = 'cover_' . uniqid() . '.' . $file_ext;
                        $db_save_cover = 'users/' . $profile_user['username'] . '/' . $new_name;
                        $dest = getUserUploadPath($profile_user['username'], 'avatars') . $new_name;
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $cover_filename = $db_save_cover;
                        }
                    }
                }

                try {
                    $db->beginTransaction();

                    if ($name_changed) {
                        if ($can_change_immediately) {
                            $formatted_full_name = formatUserFullName($new_first_name, $new_middle_name, $new_last_name, $new_display_order);
                            $stmt = $db->prepare("UPDATE users SET username = ?, bio = ?, avatar_filename = ?, cover_filename = ?, is_private = ?, first_name = ?, middle_name = ?, last_name = ?, name_display_order = ?, full_name = ?, display_name_last_updated = NOW(), name_change_status = 'none', email = ?, phone_number = ?, show_email = ?, show_phone = ?, show_gender = ?, show_workplace = ?, show_lives_at = ?, show_country = ?, show_dob = ?, gender = ?, workplace = ?, lives_at = ?, country = ?, website = ?, facebook_link = ?, instagram_link = ?, twitter_link = ?, dob = ?, profile_updated_at = NOW(), sync_transparency_status = ? WHERE id = ?");
                            $stmt->execute([$username_to_save, $new_bio, $avatar_filename, $cover_filename, $new_private, $new_first_name, $new_middle_name, $new_last_name, $new_display_order, $formatted_full_name, $new_email, $new_phone ?: null, $new_show_email, $new_show_phone, $new_show_gender, $new_show_workplace, $new_show_lives_at, $new_show_country, $new_show_dob, $new_gender ?: null, $new_workplace ?: null, $new_lives_at ?: null, $new_country ?: null, $new_website ?: null, $new_facebook ?: null, $new_instagram ?: null, $new_twitter ?: null, $new_dob ?: null, $new_sync_status, $profile_id]);
                            
                            // Log user name change history
                            $old_full_name = ($profile_user['full_name'] ?? '') ?: $profile_user['username'];
                            $stmt_hist = $db->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name) VALUES ('user', ?, ?, ?)");
                            $stmt_hist->execute([$profile_id, $old_full_name, $formatted_full_name]);

                            $success_msg = "Cập nhật thông tin cá nhân và tên mới thành công!";
                            $profile_user['first_name'] = $new_first_name;
                            $profile_user['middle_name'] = $new_middle_name;
                            $profile_user['last_name'] = $new_last_name;
                            $profile_user['name_display_order'] = $new_display_order;
                            $profile_user['full_name'] = $formatted_full_name;
                            $profile_user['display_name_last_updated'] = date('Y-m-d H:i:s');
                            $profile_user['name_change_status'] = 'none';
                        } else {
                            // Save as pending change request
                            $stmt = $db->prepare("UPDATE users SET username = ?, bio = ?, avatar_filename = ?, cover_filename = ?, is_private = ?, pending_first_name = ?, pending_middle_name = ?, pending_last_name = ?, pending_name_display_order = ?, name_change_status = 'pending', email = ?, phone_number = ?, show_email = ?, show_phone = ?, show_gender = ?, show_workplace = ?, show_lives_at = ?, show_country = ?, show_dob = ?, gender = ?, workplace = ?, lives_at = ?, country = ?, website = ?, facebook_link = ?, instagram_link = ?, twitter_link = ?, dob = ?, profile_updated_at = NOW(), sync_transparency_status = ? WHERE id = ?");
                            $stmt->execute([$username_to_save, $new_bio, $avatar_filename, $cover_filename, $new_private, $new_first_name, $new_middle_name, $new_last_name, $new_display_order, $new_email, $new_phone ?: null, $new_show_email, $new_show_phone, $new_show_gender, $new_show_workplace, $new_show_lives_at, $new_show_country, $new_show_dob, $new_gender ?: null, $new_workplace ?: null, $new_lives_at ?: null, $new_country ?: null, $new_website ?: null, $new_facebook ?: null, $new_instagram ?: null, $new_twitter ?: null, $new_dob ?: null, $new_sync_status, $profile_id]);
                            
                            $success_msg = "Thông tin cá nhân đã cập nhật. Yêu cầu đổi tên của bạn đã được gửi cho Quản trị viên phê duyệt do chưa đủ 60 ngày.";
                            $profile_user['pending_first_name'] = $new_first_name;
                            $profile_user['pending_middle_name'] = $new_middle_name;
                            $profile_user['pending_last_name'] = $new_last_name;
                            $profile_user['pending_name_display_order'] = $new_display_order;
                            $profile_user['name_change_status'] = 'pending';
                        }
                    } else {
                        // Display name did not change, just update details
                        $stmt = $db->prepare("UPDATE users SET username = ?, bio = ?, avatar_filename = ?, cover_filename = ?, is_private = ?, email = ?, phone_number = ?, show_email = ?, show_phone = ?, show_gender = ?, show_workplace = ?, show_lives_at = ?, show_country = ?, show_dob = ?, gender = ?, workplace = ?, lives_at = ?, country = ?, website = ?, facebook_link = ?, instagram_link = ?, twitter_link = ?, dob = ?, profile_updated_at = NOW(), sync_transparency_status = ? WHERE id = ?");
                        $stmt->execute([$username_to_save, $new_bio, $avatar_filename, $cover_filename, $new_private, $new_email, $new_phone ?: null, $new_show_email, $new_show_phone, $new_show_gender, $new_show_workplace, $new_show_lives_at, $new_show_country, $new_show_dob, $new_gender ?: null, $new_workplace ?: null, $new_lives_at ?: null, $new_country ?: null, $new_website ?: null, $new_facebook ?: null, $new_instagram ?: null, $new_twitter ?: null, $new_dob ?: null, $new_sync_status, $profile_id]);
                        $success_msg = "Cập nhật thông tin cá nhân thành công!";
                    }
 
                    // Keep local array in sync
                    $profile_user['gender'] = $new_gender;
                    $profile_user['workplace'] = $new_workplace;
                    $profile_user['lives_at'] = $new_lives_at;
                    $profile_user['country'] = $new_country;
                    $profile_user['website'] = $new_website;
                    $profile_user['facebook_link'] = $new_facebook;
                    $profile_user['instagram_link'] = $new_instagram;
                    $profile_user['twitter_link'] = $new_twitter;
                    $profile_user['dob'] = $new_dob ?: null;
 
                    if ($username_changed) {
                        $stmt_un = $db->prepare("UPDATE users SET username_last_updated = NOW() WHERE id = ?");
                        $stmt_un->execute([$profile_id]);
                        $profile_user['username_last_updated'] = date('Y-m-d H:i:s');
                    }
 
                    $db->commit();
 
                    if ($username_changed) {
                        $_SESSION['username'] = $username_to_save;
                    }
 
                    $profile_user['username'] = $username_to_save;
                    $profile_user['bio'] = $new_bio;
                    $profile_user['is_private'] = $new_private;
                    $profile_user['avatar_filename'] = $avatar_filename;
                    $profile_user['cover_filename'] = $cover_filename;
                    $profile_user['email'] = $new_email;
                    $profile_user['phone_number'] = $new_phone;
                    $profile_user['show_email'] = $new_show_email;
                    $profile_user['show_phone'] = $new_show_phone;
                    $profile_user['show_gender'] = $new_show_gender;
                    $profile_user['show_workplace'] = $new_show_workplace;
                    $profile_user['show_lives_at'] = $new_show_lives_at;
                    $profile_user['show_country'] = $new_show_country;
                    $profile_user['show_dob'] = $new_show_dob;
                    $me = $profile_user;

                    // Luôn luôn chuyển hướng sau khi cập nhật thành công để tải lại dữ liệu mới nhất và tránh gửi lại form khi F5
                    header("Location: profile.php?username=" . urlencode($username_to_save) . "&success=" . urlencode($success_msg));
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error_msg = "Có lỗi xảy ra: " . $e->getMessage();
                }
            }
        }

        // Handle QR reset
        if ($is_my_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset_qr'])) {
            try { $db->exec("ALTER TABLE users ADD COLUMN qr_reset_at DATETIME NULL DEFAULT NULL"); } catch(PDOException $ig){}
            $stmt = $db->prepare("UPDATE users SET qr_reset_at = NOW() WHERE id = ?");
            $stmt->execute([$profile_id]);
            $success_msg = "Mã QR đã được đặt lại thành công!";
            $profile_user['qr_reset_at'] = date('Y-m-d H:i:s');
        }

        // 3. Get Followers & Following stats
        if ($is_page_profile) {
            $followers_stmt = $db->prepare("SELECT COUNT(*) FROM page_follows WHERE page_id = ?");
            $followers_stmt->execute([$profile_id]);
            $followers_count = $followers_stmt->fetchColumn();
            
            $following_count = 0; // Pages do not follow anyone

            // Check if I am following this page
            if ($me) {
                $is_following_stmt = $db->prepare("SELECT COUNT(*) FROM page_follows WHERE user_id = ? AND page_id = ?");
                $is_following_stmt->execute([$me['id'], $profile_id]);
                $is_following = ($is_following_stmt->fetchColumn() > 0);
            }
        } else {
            $followers_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
            $followers_stmt->execute([$profile_id]);
            $followers_count = $followers_stmt->fetchColumn();

            $following_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
            $following_stmt->execute([$profile_id]);
            $following_count = $following_stmt->fetchColumn();

            // Check if I am following this user
            if ($me && !$is_my_profile) {
                $is_following = isFollowingUser($me['id'], $profile_id);
            }
        }

        $pro_followers = [];
        $pro_following = [];
        if ($is_pro_layout) {
            if ($is_page_profile) {
                $f_stmt = $db->prepare("SELECT u.* FROM users u JOIN page_follows pf ON u.id = pf.user_id WHERE pf.page_id = ? LIMIT 100");
                $f_stmt->execute([$profile_id]);
                $pro_followers = $f_stmt->fetchAll() ?: [];
            } else {
                $f_stmt = $db->prepare("SELECT u.* FROM users u JOIN follows f ON u.id = f.follower_id WHERE f.followed_id = ? LIMIT 100");
                $f_stmt->execute([$profile_id]);
                $pro_followers = $f_stmt->fetchAll() ?: [];
                
                $fg_stmt = $db->prepare("SELECT u.* FROM users u JOIN follows f ON u.id = f.followed_id WHERE f.follower_id = ? LIMIT 100");
                $fg_stmt->execute([$profile_id]);
                $pro_following = $fg_stmt->fetchAll() ?: [];
            }
        }
        
        $pro_saved_posts = [];
        if ($is_my_profile) {
            try {
                $saved_stmt = $db->prepare("
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
                    LIMIT 50
                ");
                $saved_stmt->execute([$profile_id, $profile_id]);
                $pro_saved_posts = $saved_stmt->fetchAll() ?: [];
            } catch (Exception $e) {}
        }

        // 4. Check private account visibility
        if ($is_page_profile) {
            $viewer_can_see_posts = true;
        } else {
            $viewer_can_see_posts = !$profile_is_private
                || $is_my_profile
                || isAdminLoggedIn();
        }

        // Khởi tạo các biến kiểm tra chặn
        $am_i_blocking = false;
        $am_i_blocked = false;
        $profile_identity = [
            'type' => $is_page_profile ? 'page' : 'user',
            'id' => $profile_id
        ];
        $me_identity = getCurrentIdentity();
        
        if ($me && $profile_id) {
            $am_i_blocking = hasBlocked($me_identity, $profile_identity);
            $am_i_blocked = hasBlocked($profile_identity, $me_identity);
        }

        // Ghi đè khả năng xem bài viết và thông tin nếu bị chặn hoặc đã chặn
        if ($am_i_blocking || $am_i_blocked) {
            $viewer_can_see_posts = false;
            $followers_count = 0;
            $following_count = 0;
            $is_following = false;
        }

        // 5. Load posts of this user/page (only if viewer can see them - Optimized with Eager Loading & Pagination)
        $post_reactions_map = [];
        $original_posts_map = [];
        $user_reposted_map = [];
        $photos = [];
        $videos = [];
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        $total_posts = 0;
        $total_pages = 0;

        if ($viewer_can_see_posts) {
            $me_id = $me ? intval($me['id']) : 0;
            $current_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'posts';

            if ($is_page_profile) {
                // Get total posts count for page
                $total_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE page_id = ?");
                $total_stmt->execute([$profile_id]);
                $total_posts = intval($total_stmt->fetchColumn());
                $total_pages = ceil($total_posts / $limit);

                $posts_stmt = $db->prepare("
                    SELECT p.*, 
                           pg.page_username AS username, 
                           pg.avatar_filename, 
                           'none' AS verification_type, 
                           pg.page_name AS full_name,
                           0 AS is_user_page,
                           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) AS replies_count,
                           (SELECT COUNT(*) FROM posts rp WHERE rp.repost_of_post_id = p.id) AS reposts_count,
                           (SELECT reaction_type FROM reactions re WHERE re.post_id = p.id AND re.user_id = ? LIMIT 1) AS active_reaction,
                           (SELECT COUNT(*) FROM reactions re WHERE re.post_id = p.id) AS reactions_total
                    FROM posts p 
                    JOIN pages pg ON p.page_id = pg.id 
                    WHERE p.page_id = ? 
                    ORDER BY p.is_pinned DESC, p.created_at DESC
                    LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
                ");
                $posts_stmt->execute([$me_id, $profile_id]);
                $user_posts = $posts_stmt->fetchAll();
            } else {
                // Get total posts count for user
                $total_stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND page_id IS NULL");
                $total_stmt->execute([$profile_id]);
                $total_posts = intval($total_stmt->fetchColumn());
                $total_pages = ceil($total_posts / $limit);

                $posts_stmt = $db->prepare("
                    SELECT p.*, 
                           u.username, 
                           u.avatar_filename, 
                           u.verification_type, 
                           u.full_name, 
                           u.is_page AS is_user_page,
                           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) AS replies_count,
                           (SELECT COUNT(*) FROM posts rp WHERE rp.repost_of_post_id = p.id) AS reposts_count,
                           (SELECT reaction_type FROM reactions re WHERE re.post_id = p.id AND re.user_id = ? LIMIT 1) AS active_reaction,
                           (SELECT COUNT(*) FROM reactions re WHERE re.post_id = p.id) AS reactions_total
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.user_id = ? AND p.page_id IS NULL
                    ORDER BY p.is_pinned DESC, p.created_at DESC
                    LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
                ");
                $posts_stmt->execute([$me_id, $profile_id]);
                $user_posts = $posts_stmt->fetchAll();
            }

            if (!empty($user_posts)) {
                $post_ids = array_column($user_posts, 'id');
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
                $repost_ids = array_filter(array_unique(array_column($user_posts, 'repost_of_post_id')));
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

            // Query photos
            try {
                if ($is_page_profile) {
                    $photo_stmt = $db->prepare("
                        SELECT id, image_filename, content, created_at, is_nsfw, allow_download 
                        FROM posts 
                        WHERE page_id = ? AND image_filename IS NOT NULL AND image_filename != '' 
                        ORDER BY created_at DESC
                    ");
                    $photo_stmt->execute([$profile_id]);
                } else {
                    $photo_stmt = $db->prepare("
                        SELECT id, image_filename, content, created_at, is_nsfw, allow_download 
                        FROM posts 
                        WHERE user_id = ? AND page_id IS NULL AND image_filename IS NOT NULL AND image_filename != '' 
                        ORDER BY created_at DESC
                    ");
                    $photo_stmt->execute([$profile_id]);
                }
                $photos = $photo_stmt->fetchAll() ?: [];
            } catch (Exception $e) {}

            // Query videos
            try {
                if ($is_page_profile) {
                    $video_stmt = $db->prepare("
                        SELECT p.id, p.video_filename, p.content, p.created_at, p.is_nsfw, p.allow_download,
                               pg.page_username AS username, pg.avatar_filename, pg.page_name AS full_name
                        FROM posts p
                        JOIN pages pg ON p.page_id = pg.id
                        WHERE p.page_id = ? AND p.video_filename IS NOT NULL AND p.video_filename != '' 
                        ORDER BY p.created_at DESC
                    ");
                    $video_stmt->execute([$profile_id]);
                } else {
                    $video_stmt = $db->prepare("
                        SELECT p.id, p.video_filename, p.content, p.created_at, p.is_nsfw, p.allow_download,
                               u.username, u.avatar_filename, u.full_name
                        FROM posts p
                        JOIN users u ON p.user_id = u.id
                        WHERE p.user_id = ? AND p.page_id IS NULL AND p.video_filename IS NOT NULL AND p.video_filename != '' 
                        ORDER BY p.created_at DESC
                    ");
                    $video_stmt->execute([$profile_id]);
                }
                $videos = $video_stmt->fetchAll() ?: [];
            } catch (Exception $e) {}
        }
    }

} catch (PDOException $e) {
    $error_msg = "Lỗi truy vấn dữ liệu trang cá nhân: " . $e->getMessage();
    $user_posts = [];
}

// Now it's safe to include header (outputs HTML)
require_once __DIR__ . '/includes/header.php';

if (!$profile_user && !$profile_page) {
    echo "<div class='container section text-center'><p style='color: var(--text-secondary);'>Người dùng không tồn tại.</p></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>
<div class="profile-page-container">
<style>
/* Frest Custom Photos & Videos Tabs Styling */
.profile-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    padding: 10px 0;
}
.profile-photo-card {
    position: relative;
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-md);
    overflow: hidden;
    cursor: pointer;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s ease;
}
.profile-photo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
}
.profile-photo-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-video-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 10px 0;
}
.profile-video-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 16px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s ease;
}
.profile-video-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.video-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    text-align: left;
}
.video-card-header img.frest-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
}
.video-card-header .frest-time {
    color: var(--text-muted);
    font-size: 12px;
}
.video-card-caption {
    font-size: 13.5px;
    color: var(--text-secondary);
    margin-bottom: 10px;
    text-align: left;
}
.video-card-body {
    border-radius: var(--radius-sm);
    overflow: hidden;
}
/* Style adjustments for standard layout tab buttons to align with global design */
.profile-tabs button.profile-tab-item {
    background: none;
    border: none;
    outline: none;
    font-family: var(--font-heading);
    font-size: 13.5px;
    font-weight: 700;
    padding: 10px 18px;
    color: var(--text-secondary);
    border-radius: 20px;
    transition: all var(--transition-normal);
    cursor: pointer;
}
.profile-tabs button.profile-tab-item.active {
    color: #ffffff !important;
    background: var(--accent-gradient);
    box-shadow: 0 4px 12px var(--accent-glow);
}
.profile-tabs button.profile-tab-item:hover:not(.active) {
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.04);
}
body.light-theme .profile-tabs button.profile-tab-item:hover:not(.active) {
    background: rgba(15, 23, 42, 0.03);
}
</style>


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

    <!-- Followers/Following Modal -->
    <div id="follows-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:3000; align-items:center; justify-content:center; padding:16px;" onclick="if(event.target===this)closeFollowsModal()">
        <div style="background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:var(--radius-md); width:100%; max-width:420px; max-height:80vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,0.5);">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-color);">
                <h3 id="follows-modal-title" style="margin:0; font-size:16px; font-weight:800;">Người theo dõi</h3>
                <button onclick="closeFollowsModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="follows-modal-list" style="overflow-y:auto; flex:1; padding:12px 0;"></div>
        </div>
    </div>

    <?php if ($am_i_blocked): ?>
        <div class="profile-header-card" style="padding: 48px 24px; text-align: center;">
            <div style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"><i class="fa-solid fa-user-slash"></i></div>
            <h2 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">Trang cá nhân không khả dụng</h2>
            <p style="color: var(--text-secondary); max-width: 400px; margin: 0 auto;">Không tìm thấy trang cá nhân này hoặc bạn không có quyền xem nội dung của họ.</p>
        </div>
    <?php else: ?>

        <!-- 1. Profile Header Card -->
        <div class="profile-header-card">
            <!-- Cover Photo -->
            <div class="profile-cover-banner">
                <?php if ($profile_cover): ?>
                    <div class="profile-cover-img" style="background-image: url('<?php echo AVATARS_URL . '/' . sanitize($profile_cover); ?>');"></div>
                <?php else: ?>
                    <div class="profile-cover-img default-cover-gradient"></div>
                <?php endif; ?>
            </div>

            <!-- Overlapping Avatar, Details and Action Buttons -->
            <div class="profile-header-content">
                <div class="profile-header-left">
                    <div class="profile-avatar-outer">
                        <img src="<?php echo AVATARS_URL . '/' . sanitize($profile_avatar); ?>" alt="Avatar">
                        <?php if (!$is_page_profile && isset($profile_user['last_active']) && isUserOnline($profile_user['last_active'])): ?>
                            <span class="online-dot" title="Đang hoạt động"></span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-info-column">
                        <div class="profile-name-row">
                            <h2>
                                <?php 
                                if ($is_page_profile) {
                                    echo sanitize($profile_name);
                                } else {
                                    echo !empty($profile_user['full_name']) ? sanitize($profile_user['full_name']) : '@' . sanitize($profile_handle);
                                }
                                ?>
                            </h2>
                            <?php if ($is_page_profile): ?>
                                <?php echo getPageVerificationBadgeHTML($profile_id, false); ?>
                            <?php else: ?>
                                <?php echo renderAuthorBadgeHTML($profile_user['verification_type'], $profile_user['username'], null, intval($profile_user['is_page'] ?? 0) === 1); ?>
                            <?php endif; ?>

                            <div class="profile-badges">
                                <?php if ($is_page_profile): ?>
                                    <span class="badge page-badge">Trang</span>
                                <?php else: ?>
                                    <?php if (intval($profile_user['is_private'] ?? 0)): ?>
                                        <span class="badge private-badge"><i class="fa-solid fa-lock"></i> Riêng tư</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-handle-sub">@<?php echo sanitize($profile_handle); ?></div>
                        <?php if ($is_pro_layout && !empty($profile_category)): ?>
                            <div class="pro-category-badge" style="margin-top: 4px;">
                                <i class="fa-solid fa-briefcase"></i> <?php echo sanitize($profile_category); ?>
                            </div>
                        <?php endif; ?>

                        <div class="profile-stats-summary">
                            <?php if ($is_page_profile): ?>
                                <span><strong id="followers-count"><?php echo $followers_count; ?></strong> lượt thích & theo dõi</span>
                            <?php else: ?>
                                <button onclick="openFollowsModal('followers', <?php echo $profile_id; ?>, '<?php echo sanitize($profile_handle); ?>')">
                                    <strong id="followers-count"><?php echo $followers_count; ?></strong> người theo dõi
                                </button>
                                <span>•</span>
                                <button onclick="openFollowsModal('following', <?php echo $profile_id; ?>, '<?php echo sanitize($profile_handle); ?>')">
                                    <strong><?php echo $following_count; ?></strong> đang theo dõi
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-header-right">
                    <?php if ($is_my_profile): ?>
                        <?php if ($is_page_profile): ?>
                            <button class="profile-btn secondary" onclick="document.getElementById('edit-page-form-section').style.display = 'block'; window.scrollTo({top: document.getElementById('edit-page-form-section').offsetTop - 100, behavior: 'smooth'});">
                                <i class="fa-solid fa-pen-to-square"></i> Thiết lập Trang
                            </button>
                            <?php 
                            $current_active = getCurrentIdentity();
                            if ($current_active['type'] !== 'page' || $current_active['id'] != $profile_id):
                            ?>
                                <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=page&id=<?php echo $profile_id; ?>" class="profile-btn primary">
                                    <i class="fa-solid fa-right-from-bracket"></i> Hoạt động
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="profile-btn secondary" onclick="document.getElementById('edit-profile-form-section').style.display = 'block'; window.scrollTo({top: document.getElementById('edit-profile-form-section').offsetTop - 100, behavior: 'smooth'});">
                                <i class="fa-solid fa-user-pen"></i> Sửa thông tin
                            </button>
                            <a href="settings.php" class="profile-btn secondary">
                                <i class="fa-solid fa-gear"></i> Thiết lập & Mã QR
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($me): ?>
                            <?php if ($am_i_blocking): ?>
                                <button class="profile-btn primary unblock-action-btn" <?php echo $is_page_profile ? 'data-page-id="' . $profile_id . '"' : 'data-user-id="' . $profile_id . '"'; ?>>
                                    <i class="fa-solid fa-unlock"></i> Hủy chặn
                                </button>
                            <?php else: ?>
                                <button class="profile-btn primary follow-action-btn" <?php echo $is_page_profile ? 'data-page-id="' . $profile_id . '"' : 'data-user-id="' . $profile_id . '"'; ?>>
                                    <i class="fa-solid <?php echo $is_following ? 'fa-circle-check' : 'fa-circle-plus'; ?>"></i> 
                                    <span class="btn-text">Theo dõi</span>
                                </button>
                                <a href="<?php echo SITE_URL; ?>/chat.php?contact_type=<?php echo $is_page_profile ? 'page' : 'user'; ?>&contact_id=<?php echo $profile_id; ?>" class="profile-btn secondary">
                                    <i class="fa-regular fa-comment-dots"></i> Nhắn tin
                                </a>
                                <button class="profile-dots-btn report-trigger-btn" data-target-type="<?php echo $is_page_profile ? 'page' : 'user'; ?>" data-target-id="<?php echo $profile_id; ?>" title="Báo cáo / Chặn">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="profile-btn primary">
                                <i class="fa-solid fa-circle-plus"></i> Theo dõi
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($is_pro_layout): ?>
                <div class="fb-profile-nav">
                    <button class="fb-tab-btn active" data-tab="posts">Bài viết</button>
                    <button class="fb-tab-btn" data-tab="about">Giới thiệu</button>
                    <button class="fb-tab-btn" data-tab="photos">Ảnh</button>
                    <button class="fb-tab-btn" data-tab="videos">Video</button>
                    <button class="fb-tab-btn" data-tab="followers">Người theo dõi</button>
                    <?php if ($is_my_profile): ?>
                        <button class="fb-tab-btn" data-tab="saved">Đã lưu</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Main Two-Column Layout -->
        <div id="pro-section-posts" class="pro-section-tab-content">
            <div class="profile-main-grid">
            <!-- Left Column: Intro Details -->
            <div class="profile-intro-widget">
                <h3>Giới thiệu</h3>
                <div class="bio-section <?php echo empty($profile_bio) ? 'empty-bio' : ''; ?>">
                    <?php echo empty($profile_bio) ? 'Chưa có tiểu sử.' : nl2br(sanitize($profile_bio)); ?>
                </div>

                <div class="profile-details-list">
                    <?php if (!$is_page_profile): ?>
                        <!-- User Intro Details -->
                        <?php 
                        $show_email_to_viewer = (intval($profile_user['show_email'] ?? 1) === 1 || $is_my_profile);
                        if ($show_email_to_viewer && !empty($profile_user['email'])):
                            $is_email_hidden = (intval($profile_user['show_email'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-envelope"></i>
                                <div>
                                    <span><?php echo sanitize($profile_user['email']); ?></span>
                                    <?php if ($is_my_profile): ?>
                                        <?php if ($is_email_hidden): ?>
                                            <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                        <?php else: ?>
                                            <span class="detail-public-badge"><i class="fa-solid fa-eye" style="font-size: 8px;"></i> Công khai</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $show_phone_to_viewer = (!empty($profile_user['phone_number']) && (intval($profile_user['show_phone'] ?? 1) === 1 || $is_my_profile));
                        if ($show_phone_to_viewer):
                            $is_phone_hidden = (intval($profile_user['show_phone'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-phone"></i>
                                <div>
                                    <span><?php echo sanitize($profile_user['phone_number']); ?></span>
                                    <?php if ($is_my_profile): ?>
                                        <?php if ($is_phone_hidden): ?>
                                            <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                        <?php else: ?>
                                            <span class="detail-public-badge"><i class="fa-solid fa-eye" style="font-size: 8px;"></i> Công khai</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $show_gender_to_viewer = (!empty($profile_user['gender']) && (intval($profile_user['show_gender'] ?? 1) === 1 || $is_my_profile));
                        if ($show_gender_to_viewer):
                            $is_gender_hidden = (intval($profile_user['show_gender'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-venus-mars"></i>
                                <div>
                                    <span>Giới tính: <strong><?php echo sanitize($profile_user['gender']); ?></strong></span>
                                    <?php if ($is_my_profile && $is_gender_hidden): ?>
                                        <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $show_dob_to_viewer = (!empty($profile_user['dob']) && (intval($profile_user['show_dob'] ?? 1) === 1 || $is_my_profile));
                        if ($show_dob_to_viewer):
                            $dob_time = strtotime($profile_user['dob']);
                            if ($dob_time > 0):
                                $is_dob_hidden = (intval($profile_user['show_dob'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-cake-candles"></i>
                                <div>
                                    <span>Ngày sinh: <strong><?php echo date('d/m/Y', $dob_time); ?></strong></span>
                                    <?php if ($is_my_profile && $is_dob_hidden): ?>
                                        <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endif; 
                        ?>

                        <?php 
                        $show_workplace_to_viewer = (!empty($profile_user['workplace']) && (intval($profile_user['show_workplace'] ?? 1) === 1 || $is_my_profile));
                        if ($show_workplace_to_viewer):
                            $is_workplace_hidden = (intval($profile_user['show_workplace'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-briefcase"></i>
                                <div>
                                    <span>Làm việc tại: <?php echo getWorkplaceLinkHTML($profile_user['workplace']); ?></span>
                                    <?php if ($is_my_profile && $is_workplace_hidden): ?>
                                        <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $show_lives_at_to_viewer = (!empty($profile_user['lives_at']) && (intval($profile_user['show_lives_at'] ?? 1) === 1 || $is_my_profile));
                        if ($show_lives_at_to_viewer):
                            $is_lives_at_hidden = (intval($profile_user['show_lives_at'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-house-chimney"></i>
                                <div>
                                    <span>Sống tại: <strong><?php echo sanitize($profile_user['lives_at']); ?></strong></span>
                                    <?php if ($is_my_profile && $is_lives_at_hidden): ?>
                                        <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $show_country_to_viewer = (!empty($profile_user['country']) && (intval($profile_user['show_country'] ?? 1) === 1 || $is_my_profile));
                        if ($show_country_to_viewer):
                            $is_country_hidden = (intval($profile_user['show_country'] ?? 1) === 0);
                        ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-earth-americas"></i>
                                <div>
                                    <span>Quốc gia: <strong><?php echo sanitize($profile_user['country']); ?></strong></span>
                                    <?php if ($is_my_profile && $is_country_hidden): ?>
                                        <span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size: 8px;"></i> Chỉ mình tôi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Page Intro Details -->
                        <?php 
                        $pg_show_email = intval($profile_page['show_email'] ?? 1) === 1;
                        if (!empty($profile_page['email']) && ($pg_show_email || $is_my_profile)): ?>
                            <?php 
                            if (!function_exists('pgPrivacyBadgeLocal')) {
                                function pgPrivacyBadgeLocal() {
                                    return '<span class="detail-privacy-badge"><i class="fa-solid fa-lock" style="font-size:8px;"></i> Chỉ quản trị viên</span>';
                                }
                            }
                            ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-envelope"></i>
                                <div>
                                    <span>Email: <strong><?php echo sanitize($profile_page['email']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_email) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_phone = intval($profile_page['show_phone'] ?? 1) === 1;
                        if (!empty($profile_page['phone_number']) && ($pg_show_phone || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-phone"></i>
                                <div>
                                    <span>Điện thoại: <strong><?php echo sanitize($profile_page['phone_number']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_phone) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_website = intval($profile_page['show_website'] ?? 1) === 1;
                        if (!empty($profile_page['website']) && ($pg_show_website || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-globe"></i>
                                <div>
                                    <span>Website: <a href="<?php echo htmlspecialchars($profile_page['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($profile_page['website']); ?></a></span>
                                    <?php if ($is_my_profile && !$pg_show_website) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_lives_at = intval($profile_page['show_lives_at'] ?? 1) === 1;
                        if (!empty($profile_page['lives_at']) && ($pg_show_lives_at || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-location-dot"></i>
                                <div>
                                    <span>Trụ sở: <strong><?php echo sanitize($profile_page['lives_at']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_lives_at) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_country = intval($profile_page['show_country'] ?? 1) === 1;
                        if (!empty($profile_page['country']) && ($pg_show_country || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-earth-americas"></i>
                                <div>
                                    <span>Quốc gia: <strong><?php echo sanitize($profile_page['country']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_country) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_working_hours = intval($profile_page['show_working_hours'] ?? 1) === 1;
                        if (!empty($profile_page['working_hours']) && ($pg_show_working_hours || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-regular fa-clock"></i>
                                <div>
                                    <span>Giờ hoạt động: <strong><?php echo sanitize($profile_page['working_hours']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_working_hours) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_services = intval($profile_page['show_services'] ?? 1) === 1;
                        if (!empty($profile_page['services']) && ($pg_show_services || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-briefcase"></i>
                                <div>
                                    <span>Dịch vụ: <strong><?php echo sanitize($profile_page['services']); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_services) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $pg_show_founded_at = intval($profile_page['show_founded_at'] ?? 1) === 1;
                        if (!empty($profile_page['founded_at']) && ($pg_show_founded_at || $is_my_profile)): ?>
                            <div class="detail-item">
                                <i class="fa-solid fa-calendar-days"></i>
                                <div>
                                    <span>Thành lập: <strong><?php echo date('d/m/Y', strtotime($profile_page['founded_at'])); ?></strong></span>
                                    <?php if ($is_my_profile && !$pg_show_founded_at) echo pgPrivacyBadgeLocal(); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Social links row (Premium UI) -->
                <?php if (!empty($socials)): ?>
                    <div class="profile-socials-widget">
                        <div class="profile-socials-title">Mạng xã hội</div>
                        <div class="profile-social-buttons">
                            <?php foreach ($socials as $name => $data): ?>
                                <a href="<?php echo htmlspecialchars($data['link']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-btn" style="--social-color: <?php echo $data['color']; ?>; --social-hover-bg: <?php echo $data['hover_bg']; ?>;" title="<?php echo $data['title'] ?? ucfirst($name); ?>">
                                    <i class="<?php echo $data['icon']; ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Feed and Forms -->
            <div class="profile-feed-column">

    <!-- Edit Profile Form Section (Hidden by default) -->
    <?php if ($is_my_profile): ?>
        <div id="edit-profile-form-section" class="checkout-card" style="display: none; margin-bottom: 24px; padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); animation: scaleUp 0.25s ease;">
            <h4 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Chỉnh sửa thông tin cá nhân</h4>
            
            <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
                <input type="hidden" name="action_edit_profile" value="1">

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên người dùng (Username) / Liên kết cá nhân</label>
                     <?php 
                    $username_days_to_wait = 0;
                    if (!empty($profile_user['username_last_updated'])) {
                        $last_username_update = strtotime($profile_user['username_last_updated']);
                        $username_days_elapsed = (time() - $last_username_update) / 86400;
                        if ($username_days_elapsed < 60) {
                            $username_days_to_wait = ceil(60 - $username_days_elapsed);
                        }
                    }
                    $is_username_readonly = ($username_days_to_wait > 0 && !isAdminLoggedIn());
                    ?>
                    <input type="text" name="username" class="form-input" style="padding: 10px; <?php echo $is_username_readonly ? 'background: var(--bg-tertiary); cursor: not-allowed;' : ''; ?>" placeholder="Tên người dùng mới..." maxlength="50" value="<?php echo htmlspecialchars($profile_user['username']); ?>" required oninput="document.getElementById('preview-username').textContent = this.value.toLowerCase().replace(/[^a-zA-Z0-9_]/g, '')" <?php echo $is_username_readonly ? 'readonly' : ''; ?>>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Liên kết của bạn: <?php echo SITE_URL; ?>/profile.php?username=<span id="preview-username"><?php echo htmlspecialchars($profile_user['username']); ?></span></div>
                    
                    <?php if ($username_days_to_wait > 0 && !isAdminLoggedIn()): ?>
                        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 8px 12px; border-radius: var(--radius-sm); font-size: 12px; margin-top: 6px; line-height: 1.4;">
                            <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> Bạn đã thay đổi tên người dùng gần đây. Bạn chỉ có thể đổi lại sau <?php echo $username_days_to_wait; ?> ngày nữa.
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 100px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Họ</label>
                        <input type="text" name="last_name" class="form-input" style="padding: 10px;" placeholder="Họ..." maxlength="50" value="<?php echo htmlspecialchars($profile_user['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1.2; min-width: 100px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Tên đệm</label>
                        <input type="text" name="middle_name" class="form-input" style="padding: 10px;" placeholder="Tên đệm..." maxlength="50" value="<?php echo htmlspecialchars($profile_user['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 100px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Tên</label>
                        <input type="text" name="first_name" class="form-input" style="padding: 10px;" placeholder="Tên..." maxlength="50" value="<?php echo htmlspecialchars($profile_user['first_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Thứ tự hiển thị tên</label>
                    <select name="name_display_order" class="form-input" style="padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <option value="last_middle_first" <?php echo ($profile_user['name_display_order'] ?? 'last_middle_first') === 'last_middle_first' ? 'selected' : ''; ?>>Họ Tên đệm Tên (Nguyễn Văn A)</option>
                        <option value="first_middle_last" <?php echo ($profile_user['name_display_order'] ?? '') === 'first_middle_last' ? 'selected' : ''; ?>>Tên Tên đệm Họ (A Văn Nguyễn)</option>
                        <option value="first_last" <?php echo ($profile_user['name_display_order'] ?? '') === 'first_last' ? 'selected' : ''; ?>>Tên Họ (A Nguyễn)</option>
                        <option value="last_first" <?php echo ($profile_user['name_display_order'] ?? '') === 'last_first' ? 'selected' : ''; ?>>Họ Tên (Nguyễn A)</option>
                        <option value="first_only" <?php echo ($profile_user['name_display_order'] ?? '') === 'first_only' ? 'selected' : ''; ?>>Chỉ hiển thị Tên (A)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Địa chỉ Email</label>
                    <input type="email" name="email" class="form-input" style="padding: 10px;" placeholder="Email..." value="<?php echo htmlspecialchars($profile_user['email'] ?? ''); ?>" required>
                    <label class="form-label" style="font-size: 12.5px; font-weight: 500; display: flex; align-items: center; gap: 8px; margin-top: 6px; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="show_email" value="1" style="width:15px; height:15px;" <?php echo intval($profile_user['show_email'] ?? 1) ? 'checked' : ''; ?>>
                        Hiển thị Email công khai trên trang cá nhân
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Số điện thoại</label>
                    <input type="text" name="phone_number" class="form-input" style="padding: 10px;" placeholder="Số điện thoại..." value="<?php echo htmlspecialchars($profile_user['phone_number'] ?? ''); ?>">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 500; display: flex; align-items: center; gap: 8px; margin-top: 6px; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="show_phone" value="1" style="width:15px; height:15px;" <?php echo intval($profile_user['show_phone'] ?? 1) ? 'checked' : ''; ?>>
                        Hiển thị Số điện thoại công khai trên trang cá nhân
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Website cá nhân</label>
                    <input type="url" name="website" class="form-input" style="padding: 10px;" placeholder="https://example.com" value="<?php echo htmlspecialchars($profile_user['website'] ?? ''); ?>">
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-rocket" style="color: var(--accent-primary); margin-right: 4px;"></i> Frest Pro Link</label>
                        <input type="url" name="facebook_link" class="form-input" style="padding: 10px;" placeholder="https://frestpro.com/username" value="<?php echo htmlspecialchars($profile_user['facebook_link'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-brands fa-instagram" style="color: #e1306c; margin-right: 4px;"></i> Instagram Link</label>
                        <input type="url" name="instagram_link" class="form-input" style="padding: 10px;" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($profile_user['instagram_link'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-brands fa-twitter" style="color: #1da1f2; margin-right: 4px;"></i> Twitter/X Link</label>
                        <input type="url" name="twitter_link" class="form-input" style="padding: 10px;" placeholder="https://x.com/username" value="<?php echo htmlspecialchars($profile_user['twitter_link'] ?? ''); ?>">
                    </div>
                </div>

                <?php 
                $days_to_wait = 0;
                if (!empty($profile_user['display_name_last_updated'])) {
                    $last_update = strtotime($profile_user['display_name_last_updated']);
                    $days_elapsed = (time() - $last_update) / 86400;
                    if ($days_elapsed < 60) {
                        $days_to_wait = ceil(60 - $days_elapsed);
                    }
                }
                if ($days_to_wait > 0 && !isAdminLoggedIn()): 
                ?>
                    <div style="background: rgba(235, 94, 40, 0.1); border-left: 4px solid var(--accent-primary); color: var(--accent-primary); padding: 12px; border-radius: var(--radius-sm); font-size: 12px; line-height: 1.4;">
                        <i class="fa-solid fa-circle-info" style="margin-right: 6px;"></i> Bạn đã đổi tên trong 60 ngày qua. Nếu bạn đổi tên bây giờ, thay đổi sẽ được gửi đến Quản trị viên duyệt và xem xét (cần thêm <?php echo $days_to_wait; ?> ngày nữa để tự động đổi ngay).
                    </div>
                <?php endif; ?>
                <?php if (($profile_user['name_change_status'] ?? 'none') === 'pending'): ?>
                    <div style="background: rgba(235, 94, 40, 0.05); border: 1px dashed var(--accent-primary); color: var(--text-primary); padding: 12px; border-radius: var(--radius-sm); font-size: 12px; line-height: 1.4;">
                        <i class="fa-solid fa-clock" style="margin-right: 6px; color: var(--accent-primary);"></i> Bạn có yêu cầu đổi tên đang chờ duyệt: <strong>
                        <?php 
                        echo htmlspecialchars(formatUserFullName(
                            $profile_user['pending_first_name'],
                            $profile_user['pending_middle_name'],
                            $profile_user['pending_last_name'],
                            $profile_user['pending_name_display_order']
                        ));
                        ?></strong>
                    </div>
                <?php endif; ?>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="bio" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tiểu sử (Bio)</label>
                    <textarea name="bio" id="bio" class="form-input" style="height: 80px; padding: 10px; resize: none;" placeholder="Giới thiệu đôi nét về bản thân..."><?php echo htmlspecialchars($profile_user['bio']); ?></textarea>
                </div>

                <div style="display: flex; gap: 14px; margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="gender" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Giới tính</label>
                        <select name="gender" id="gender" class="form-input" style="padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 13.5px; height: 40px; box-sizing: border-box;">
                            <option value="" <?php echo empty($profile_user['gender']) ? 'selected' : ''; ?>>Không tiết lộ</option>
                            <option value="Nam" <?php echo ($profile_user['gender'] ?? '') === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?php echo ($profile_user['gender'] ?? '') === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                            <option value="Khác" <?php echo ($profile_user['gender'] ?? '') === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="show_gender" value="1" style="width:14px; height:14px;" <?php echo intval($profile_user['show_gender'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="dob" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Ngày sinh</label>
                        <input type="date" name="dob" id="dob" class="form-input" style="padding: 10px; font-size: 13.5px; height: 40px; box-sizing: border-box; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm);" value="<?php echo htmlspecialchars($profile_user['dob'] ?? ''); ?>">
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="show_dob" value="1" style="width:14px; height:14px;" <?php echo intval($profile_user['show_dob'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0; position: relative;">
                    <label for="workplace" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Nơi làm việc</label>
                    <input type="text" name="workplace" id="workplace" autocomplete="off" class="form-input" style="padding: 10px; font-size: 13.5px; height: 40px; box-sizing: border-box;" placeholder="Tên công ty, trường học..." value="<?php echo htmlspecialchars($profile_user['workplace'] ?? ''); ?>">
                    <div id="workplace-suggestions" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: var(--shadow-md); z-index: 1000; max-height: 200px; overflow-y: auto; backdrop-filter: blur(16px); padding: 6px;"></div>
                    <div id="workplace-preview-container" style="margin-top: 6px; font-size: 12.5px; color: var(--text-muted); display: none; text-align: left;"></div>
                    <label class="form-label" style="font-size: 11.5px; font-weight: 500; display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="show_workplace" value="1" style="width:14px; height:14px;" <?php echo intval($profile_user['show_workplace'] ?? 1) ? 'checked' : ''; ?>>
                        Hiển thị công khai
                    </label>
                </div>

                <div style="display: flex; gap: 14px; margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="lives_at" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Sống tại</label>
                        <input type="text" name="lives_at" id="lives_at" class="form-input" style="padding: 10px; font-size: 13.5px; height: 40px; box-sizing: border-box;" placeholder="Ví dụ: Hà Nội, Việt Nam" value="<?php echo htmlspecialchars($profile_user['lives_at'] ?? ''); ?>">
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="show_lives_at" value="1" style="width:14px; height:14px;" <?php echo intval($profile_user['show_lives_at'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="country" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Quốc gia</label>
                        <input type="text" name="country" id="country" class="form-input" style="padding: 10px; font-size: 13.5px; height: 40px; box-sizing: border-box;" placeholder="Ví dụ: Việt Nam" value="<?php echo htmlspecialchars($profile_user['country'] ?? ''); ?>">
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="show_country" value="1" style="width:14px; height:14px;" <?php echo intval($profile_user['show_country'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="sync_transparency_status" value="1" style="width:15px; height:15px;" <?php echo intval($profile_user['sync_transparency_status'] ?? 1) ? 'checked' : ''; ?>>
                        Đồng bộ trạng thái cập nhật cá nhân lên tính minh bạch
                    </label>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; padding-left: 23px;">Hiển thị thông báo khi bạn cập nhật thông tin cá nhân gần đây.</div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_private" value="1" style="width:15px; height:15px;" <?php echo intval($profile_user['is_private'] ?? 0) ? 'checked' : ''; ?>>
                        Chế độ tài khoản riêng tư
                    </label>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; padding-left: 23px;">Chỉ người theo dõi mới xem được bài viết của bạn.</div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="avatar" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Thay đổi ảnh đại diện</label>
                    <input type="file" id="avatar" accept="image/*" class="form-input" style="padding: 8px;">
                    <input type="hidden" name="cropped_avatar" id="cropped_avatar_input">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="cover" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Thay đổi ảnh bìa (Cover)</label>
                    <input type="file" name="cover" id="cover" accept="image/*" class="form-input" style="padding: 8px;">
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn-primary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full);">Lưu thông tin</button>
                    <button type="button" class="btn-secondary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full);" onclick="document.getElementById('edit-profile-form-section').style.display = 'none'; document.querySelector('.profile-actions-row div').style.display = 'flex';">Hủy bỏ</button>
                </div>
            </form>

            <!-- QR Reset form (separate POST) -->
            <form action="" method="POST" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <input type="hidden" name="action_reset_qr" value="1">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);"><i class="fa-solid fa-qrcode" style="margin-right: 6px; color: var(--accent-primary);"></i>Mã QR tài khoản</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Đặt lại sẽ tạo mã QR mới cho trang cá nhân.</div>
                    </div>
                    <button type="submit" style="padding: 7px 16px; font-size: 12.5px; font-weight: 700; border-radius: var(--radius-full); background: rgba(124,58,237,0.1); color: var(--accent-primary); border: 1px solid rgba(124,58,237,0.3); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(124,58,237,0.2)'" onmouseout="this.style.background='rgba(124,58,237,0.1)'">
                        <i class="fa-solid fa-arrows-rotate"></i> Đặt lại mã QR
                    </button>
                </div>
            </form>
        </div>

        <!-- Avatar Crop Modal -->
        <div class="modal-overlay" id="avatar-crop-modal" style="display: none; z-index: 2005; background: rgba(0,0,0,0.85); align-items: center; justify-content: center;">
            <div class="modal-content glassmorphism-card" style="max-width: 450px; padding: 24px; border-radius: var(--radius-md); width: 100%; box-sizing: border-box; position: relative;">
                <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 16px; text-align: center; color: var(--text-primary);">Cắt ảnh đại diện</h3>
                <div style="max-height: 300px; width: 100%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #000; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <img id="crop-image-preview" style="max-width: 100%; max-height: 300px; display: block;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" id="btn-cancel-crop" style="padding: 8px 20px; border-radius: var(--radius-full); font-size: 13px;">Hủy bỏ</button>
                    <button type="button" class="btn-primary" id="btn-apply-crop" style="padding: 8px 20px; border-radius: var(--radius-full); font-size: 13px;">Cắt ảnh</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Page Form Section (Hidden by default) -->
    <?php if ($is_page_profile && $is_my_profile): ?>
        <div id="edit-page-form-section" class="checkout-card" style="display: none; margin-bottom: 24px; padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); animation: scaleUp 0.25s ease;">
            <h4 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Thiết lập Trang của bạn</h4>
            
            <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
                <input type="hidden" name="action_edit_page" value="1">

                <!-- === BASIC INFO === -->
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); padding:6px 0 2px; border-bottom:1px solid var(--border-color);">Thông tin cơ bản</div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên Trang</label>
                    <input type="text" name="page_name" class="form-input" style="padding: 10px;" placeholder="Tên Trang mới..." maxlength="100" value="<?php echo htmlspecialchars($profile_page['page_name']); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Danh mục</label>
                    <select name="category" class="form-input" style="padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                        <?php foreach (getPageCategories() as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $profile_page['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tiểu sử (Mô tả ngắn về Trang)</label>
                    <textarea name="bio" class="form-input" style="padding: 10px; resize: none; height: 70px;" placeholder="Nhập tiểu sử của Trang..." maxlength="255"><?php echo htmlspecialchars($profile_page['bio'] ?? ''); ?></textarea>
                </div>

                <!-- === CONTACT INFO === -->
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); padding:10px 0 2px; border-bottom:1px solid var(--border-color); margin-top:4px;">Thông tin liên hệ</div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Địa chỉ Email liên hệ</label>
                    <input type="email" name="email" class="form-input" style="padding: 10px;" placeholder="Liên hệ email của Trang..." value="<?php echo htmlspecialchars($profile_page['email'] ?? ''); ?>">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_email" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_email'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Email công khai trên Trang
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Số điện thoại liên hệ</label>
                    <input type="text" name="phone_number" class="form-input" style="padding: 10px;" placeholder="Số điện thoại của Trang..." value="<?php echo htmlspecialchars($profile_page['phone_number'] ?? ''); ?>">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_phone" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_phone'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Số điện thoại công khai trên Trang
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Website của Trang</label>
                    <input type="url" name="website" class="form-input" style="padding: 10px;" placeholder="https://example.com" value="<?php echo htmlspecialchars($profile_page['website'] ?? ''); ?>">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_website" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_website'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Website công khai trên Trang
                    </label>
                </div>

                <!-- === LOCATION === -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Địa điểm / Trụ sở</label>
                        <input type="text" name="lives_at" class="form-input" style="padding: 10px;" placeholder="Ví dụ: Hà Nội, Việt Nam" value="<?php echo htmlspecialchars($profile_page['lives_at'] ?? ''); ?>">
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display:flex; align-items:center; gap:6px; margin-top:6px; cursor:pointer; user-select:none;">
                            <input type="checkbox" name="show_lives_at" value="1" style="width:13px; height:13px;" <?php echo intval($profile_page['show_lives_at'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Quốc gia</label>
                        <input type="text" name="country" class="form-input" style="padding: 10px;" placeholder="Ví dụ: Việt Nam" value="<?php echo htmlspecialchars($profile_page['country'] ?? ''); ?>">
                        <label class="form-label" style="font-size: 11.5px; font-weight: 500; display:flex; align-items:center; gap:6px; margin-top:6px; cursor:pointer; user-select:none;">
                            <input type="checkbox" name="show_country" value="1" style="width:13px; height:13px;" <?php echo intval($profile_page['show_country'] ?? 1) ? 'checked' : ''; ?>>
                            Hiển thị công khai
                        </label>
                    </div>
                </div>

                <!-- === NEW DETAIL FIELDS === -->
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); padding:10px 0 2px; border-bottom:1px solid var(--border-color); margin-top:4px;">Thông tin chi tiết Trang</div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;"><i class="fa-regular fa-clock" style="margin-right:4px;"></i> Giờ hoạt động</label>
                    <input type="text" name="working_hours" class="form-input" style="padding: 10px;" placeholder="Ví dụ: Thứ 2 - Thứ 6: 08:00 - 17:00" value="<?php echo htmlspecialchars($profile_page['working_hours'] ?? ''); ?>" maxlength="255">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_working_hours" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_working_hours'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Giờ hoạt động công khai
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-briefcase" style="margin-right:4px;"></i> Dịch vụ cung cấp</label>
                    <input type="text" name="services" class="form-input" style="padding: 10px;" placeholder="Ví dụ: Thiết kế, Tư vấn, Giao hàng..." value="<?php echo htmlspecialchars($profile_page['services'] ?? ''); ?>" maxlength="255">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_services" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_services'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Dịch vụ công khai
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-calendar-days" style="margin-right:4px;"></i> Ngày thành lập / Bắt đầu hoạt động</label>
                    <input type="date" name="founded_at" class="form-input" style="padding: 10px; background:var(--bg-tertiary); color:var(--text-primary);" value="<?php echo htmlspecialchars($profile_page['founded_at'] ?? ''); ?>">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:6px; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="show_founded_at" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_founded_at'] ?? 1) ? 'checked' : ''; ?>>
                        <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị Ngày thành lập công khai
                    </label>
                </div>

                <!-- === SOCIAL LINKS === -->
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); padding:10px 0 2px; border-bottom:1px solid var(--border-color); margin-top:4px;">Mạng xã hội &amp; Liên kết</div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-rocket" style="color: var(--accent-primary); margin-right: 4px;"></i> Frest Pro Link</label>
                        <input type="url" name="facebook_link" class="form-input" style="padding: 10px;" placeholder="https://frestpro.com/page" value="<?php echo htmlspecialchars($profile_page['facebook_link'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-brands fa-instagram" style="color: #e1306c; margin-right: 4px;"></i> Instagram Link</label>
                        <input type="url" name="instagram_link" class="form-input" style="padding: 10px;" placeholder="https://instagram.com/page" value="<?php echo htmlspecialchars($profile_page['instagram_link'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;"><i class="fa-brands fa-twitter" style="color: #1da1f2; margin-right: 4px;"></i> Twitter/X Link</label>
                        <input type="url" name="twitter_link" class="form-input" style="padding: 10px;" placeholder="https://x.com/page" value="<?php echo htmlspecialchars($profile_page['twitter_link'] ?? ''); ?>">
                    </div>
                </div>

                <label class="form-label" style="font-size: 12px; font-weight: 500; display:flex; align-items:center; gap:7px; margin-top:-4px; cursor:pointer; user-select:none;">
                    <input type="checkbox" name="show_socials" value="1" style="width:14px; height:14px;" <?php echo intval($profile_page['show_socials'] ?? 1) ? 'checked' : ''; ?>>
                    <i class="fa-solid fa-eye" style="font-size:11px; color:var(--text-muted);"></i> Hiển thị các icon mạng xã hội công khai trên Trang
                </label>

                <div class="form-group" style="margin-top: 14px; margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="sync_transparency_status" value="1" style="width:15px; height:15px;" <?php echo intval($profile_page['sync_transparency_status'] ?? 1) ? 'checked' : ''; ?>>
                        Đồng bộ trạng thái cập nhật trang lên tính minh bạch
                    </label>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; padding-left: 23px;">Hiển thị thông báo khi Trang cập nhật thông tin gần đây.</div>
                </div>

                <!-- === AVATAR & COVER === -->
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); padding:10px 0 2px; border-bottom:1px solid var(--border-color); margin-top:4px;">Hình ảnh Trang</div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="page-avatar" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Ảnh đại diện (Avatar)</label>
                    <input type="file" id="page-avatar" accept="image/*" class="form-input" style="padding: 8px;" data-page-id="<?php echo $profile_page['id']; ?>">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Ảnh bìa (Cover)</label>
                    <input type="file" name="cover" accept="image/*" class="form-input" style="padding: 8px;">
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; align-items: center;">
                    <button type="submit" class="btn-primary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full);">Lưu thông tin</button>
                    <button type="button" class="btn-secondary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full);" onclick="document.getElementById('edit-page-form-section').style.display = 'none'; document.querySelector('.profile-actions-row div').style.display = 'flex';">Hủy bỏ</button>
                    <div style="flex-grow: 1;"></div>
                    <button type="submit" form="delete-page-direct-form" class="btn-primary" style="padding: 8px 20px; font-size: 13px; border-radius: var(--radius-full); background: rgba(239, 68, 68, 0.12); border: 1px solid var(--danger); color: var(--danger); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; width: auto; height: auto;">
                        <i class="fa-solid fa-trash-can"></i> Xóa Trang
                    </button>
                </div>
            </form>
            <form id="delete-page-direct-form" action="<?php echo SITE_URL; ?>/delete_page.php" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA vĩnh viễn Trang này? Tất cả bài viết, phản hồi và người theo dõi của Trang sẽ bị xóa vĩnh viễn và không thể khôi phục!');" style="display: none;"></form>
            <script>
                document.getElementById('delete-page-direct-form').innerHTML = '<input type="hidden" name="page_id" value="<?php echo $profile_page['id']; ?>">';
            </script>
        </div>
    <?php endif; ?>



    <!-- User's Posts list (Feed) -->
    <?php if (!$is_pro_layout): ?>
        <div class="profile-tabs" style="margin-bottom: 20px;">
            <button type="button" class="profile-tab-item active" data-tab="posts">Bài đăng</button>
            <button type="button" class="profile-tab-item" data-tab="photos">Ảnh</button>
            <button type="button" class="profile-tab-item" data-tab="videos">Video</button>
            <?php if ($is_my_profile): ?>
                <button type="button" class="profile-tab-item" data-tab="saved">Đã lưu</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <?php if ($am_i_blocking): ?>
        <div style="padding: 48px 20px; text-align: center; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
            <i class="fa-solid fa-user-slash" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
            <div style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">Bạn đã chặn tài khoản này</div>
            <div style="font-size: 14px; color: var(--text-secondary);">Hãy hủy chặn để xem bài đăng của họ và tương tác.</div>
        </div>
    <?php elseif (isset($profile_is_private) && $profile_is_private && !$viewer_can_see_posts): ?>
        <div style="padding: 48px 20px; text-align: center; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
            <i class="fa-solid fa-lock" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
            <div style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">Tài khoản này ở chế độ riêng tư</div>
            <div style="font-size: 14px; color: var(--text-secondary);">Theo dõi @<?php echo sanitize($profile_handle); ?> để xem bài viết.</div>
        </div>
    <?php else: ?>
    <div id="standard-section-posts" class="standard-section-tab-content">
    <div class="feed-container">
        <?php if (empty($user_posts)): ?>
            <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 32px 0;">
                <?php echo ($current_tab === 'saved') ? 'Bạn chưa lưu bài viết nào.' : 'Chưa có Frest nào được đăng tải.'; ?>
            </p>
        <?php else: ?>
            <?php foreach ($user_posts as $post): 
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
                        <img src="<?php echo AVATARS_URL . '/' . sanitize($post['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
                        <div class="frest-line"></div>
                    </div>
                    <div class="frest-right">
                        <div class="frest-header">
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <?php if (intval($post['is_pinned'] ?? 0) === 1): ?>
                                        <span class="pinned-badge" style="font-size: 11px; background: rgba(235, 94, 40, 0.12); color: var(--accent-primary); border: 1px solid rgba(235, 94, 40, 0.25); border-radius: 50px; padding: 2px 8px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700; margin-right: 4px;">
                                            <i class="fa-solid fa-thumbtack" style="font-size: 10px;"></i> Đã ghim
                                        </span>
                                    <?php endif; ?>
                                    <span class="frest-author" style="font-weight: 700; color: var(--text-primary);">
                                        <?php echo !empty($post['full_name']) ? sanitize($post['full_name']) : sanitize($post['username']); ?>
                                    </span>
                                    <?php echo renderAuthorBadgeHTML($post['verification_type'], $post['username'], $post['page_id'], $post['is_user_page'] ?? false); ?>
                                    <span style="color: var(--text-muted); font-size: 13.5px; font-weight: 600; margin: 0 2px;">·</span>
                                    <span class="frest-time"><?php echo timeElapsedString($post['created_at']); ?></span>
                                </div>
                                <?php if (!empty($post['full_name'])): ?>
                                    <span style="font-size: 12.5px; color: var(--text-muted); font-weight: 500; margin-top: -2px; text-align: left;">@<?php echo sanitize($post['username']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            $is_my_post = $me && intval($post['user_id']) === intval($me['id']);
                            $is_violation = (isset($post['is_copyright_violation']) && intval($post['is_copyright_violation']) === 1);
                            $can_edit = $is_my_post && !$is_violation;
                            $can_delete = isAdminLoggedIn() || ($is_my_post && !$is_violation);
                            $can_report = $me && !$is_my_post;
                            
                            if ($can_edit || $can_delete || $can_report):
                            ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <!-- Ellipsis Menu -->
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
                            echo renderLinkPreviewCard($post);
                        }
                        ?>

                        <!-- Interactive Social Action Bar -->
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
        
        <!-- Pagination Grid -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <?php $user_param = !empty($profile_handle) ? '&username=' . urlencode($profile_handle) : ''; ?>
            <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 24px 0 10px 0; padding: 10px; background: rgba(255,255,255,0.02); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <?php if ($page > 1): ?>
                    <a href="profile.php?page=<?php echo $page - 1; ?><?php echo $user_param; ?>" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);"><i class="fa-solid fa-chevron-left"></i> Trước</a>
                <?php endif; ?>
                
                <div style="display: flex; gap: 4px;">
                    <?php 
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    if ($start_p > 1) {
                        echo '<a href="profile.php?page=1' . $user_param . '" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">1</a>';
                        if ($start_p > 2) {
                            echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                        }
                    }
                    for ($i = $start_p; $i <= $end_p; $i++) {
                        if ($i === $page) {
                            echo '<span class="btn-primary" style="padding: 6px 12px; font-size: 13px; font-weight: 700; border-radius: var(--radius-sm);">' . $i . '</span>';
                        } else {
                            echo '<a href="profile.php?page=' . $i . $user_param . '" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">' . $i . '</a>';
                        }
                    }
                    if ($end_p < $total_pages) {
                        if ($end_p < $total_pages - 1) {
                            echo '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
                        }
                        echo '<a href="profile.php?page=' . $total_pages . $user_param . '" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">' . $total_pages . '</a>';
                    }
                    ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="profile.php?page=<?php echo $page + 1; ?><?php echo $user_param; ?>" class="btn-secondary" style="padding: 6px 12px; text-decoration: none; font-size: 13px; border-radius: var(--radius-sm);">Sau <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    </div>

    <!-- Photos Section for Standard Profile -->
    <div id="standard-section-photos" class="standard-section-tab-content" style="display: none;">
        <div class="profile-media-grid">
            <?php if (empty($photos)): ?>
                <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 32px 0; grid-column: 1 / -1; width: 100%;">
                    Chưa có ảnh nào được đăng tải.
                </p>
            <?php else: ?>
                <?php foreach ($photos as $photo_post): ?>
                    <?php 
                    $images = array_values(array_filter(explode(',', $photo_post['image_filename'])));
                    $is_nsfw_post = (isset($photo_post['is_nsfw']) && intval($photo_post['is_nsfw']) === 1);
                    $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                    $allow_download = isset($photo_post['allow_download']) ? intval($photo_post['allow_download']) : 1;
                    
                    foreach ($images as $img):
                    ?>
                        <?php if ($should_blur_nsfw): ?>
                            <div class="nsfw-container" data-post-id="<?php echo $photo_post['id']; ?>" style="margin-top: 0; aspect-ratio: 1/1; border-radius: var(--radius-md); overflow: hidden;">
                                <div class="nsfw-overlay" style="padding: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                    <i class="fa-solid fa-eye-slash nsfw-overlay-icon" style="font-size: 20px; margin-bottom: 6px;"></i>
                                    <div class="nsfw-overlay-title" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">18+</div>
                                    <button type="button" class="nsfw-reveal-btn" style="padding: 4px 8px; font-size: 10px; border-radius: var(--radius-sm);">Xem</button>
                                </div>
                                <div class="nsfw-blurred" style="width: 100%; height: 100%;">
                                    <div class="profile-photo-card" onclick="window.openLightboxDirect(event, '<?php echo htmlspecialchars($img); ?>', <?php echo $allow_download; ?>)" style="width: 100%; height: 100%;">
                                        <img src="<?php echo SITE_URL . '/uploads/posts/' . sanitize($img); ?>" alt="Photo" loading="lazy">
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="profile-photo-card" onclick="window.openLightboxDirect(event, '<?php echo htmlspecialchars($img); ?>', <?php echo $allow_download; ?>)">
                                <img src="<?php echo SITE_URL . '/uploads/posts/' . sanitize($img); ?>" alt="Photo" loading="lazy">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Videos Section for Standard Profile -->
    <div id="standard-section-videos" class="standard-section-tab-content" style="display: none;">
        <div class="profile-video-list">
            <?php if (empty($videos)): ?>
                <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 32px 0;">
                    Chưa có video nào được đăng tải.
                </p>
            <?php else: ?>
                <?php foreach ($videos as $video_post): ?>
                    <div class="profile-video-card">
                        <div class="video-card-header">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($video_post['avatar_filename'] ?? ''); ?>" class="frest-avatar" alt="Avatar">
                            <div style="display: flex; flex-direction: column;">
                                <span class="frest-author" style="font-weight: 700; color: var(--text-primary);">
                                    <?php echo sanitize(($video_post['full_name'] ?? '') ?: ($video_post['username'] ?? '')); ?>
                                </span>
                                <span class="frest-time" style="font-size: 11.5px; color: var(--text-muted);"><?php echo timeElapsedString($video_post['created_at'] ?? 'now'); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($video_post['content'])): ?>
                            <div class="video-card-caption"><?php echo nl2br(parseHashtags(linkify(sanitize($video_post['content'])))); ?></div>
                        <?php endif; ?>
                        <div class="video-card-body">
                            <?php 
                            $is_nsfw_post = (isset($video_post['is_nsfw']) && intval($video_post['is_nsfw']) === 1);
                            $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                            echo renderPostMediaHTML($video_post, $should_blur_nsfw); 
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Saved Section for Standard Profile -->
    <?php if ($is_my_profile): ?>
        <div id="standard-section-saved" class="standard-section-tab-content" style="display: none;">
            <div class="feed-container">
                <?php if (empty($pro_saved_posts)): ?>
                    <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 32px 0;">
                        Bạn chưa lưu bài viết nào.
                    </p>
                <?php else: ?>
                    <?php 
                    // Temporarily swap $user_posts to reuse standard post list layout
                    $backup_user_posts = $user_posts;
                    $user_posts = $pro_saved_posts;
                    foreach ($user_posts as $post):
                        $post_id = $post['id'];
                        $post_url_id = !empty($post['post_token']) ? $post['post_token'] : $post['id'];
                        $replies_count = intval($post['replies_count'] ?? 0);
                        $active_reaction = $post['active_reaction'] ?: false;
                        $reacted_class = $active_reaction ? 'active' : '';
                        $reactions_summary = [
                            'total' => intval($post['reactions_total'] ?? 0),
                            'types' => []
                        ];
                        $reposts_count = intval($post['reposts_count'] ?? 0);
                        $user_reposted = false;
                        $original_post = null;
                        $is_my_repost = false;
                        $glow_class = ($post_id % 2 === 0) ? 'glowing-card-cyan' : 'glowing-card-purple';
                    ?>
                        <div class="frest-card <?php echo $glow_class; ?>" data-post-id="<?php echo $post_id; ?>" data-post-token="<?php echo $post_url_id; ?>">
                            <div class="frest-left">
                                <img src="<?php echo AVATARS_URL . '/' . sanitize($post['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
                                <div class="frest-line"></div>
                            </div>
                            <div class="frest-right">
                                <div class="frest-header">
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="frest-author" style="font-weight: 700; color: var(--text-primary);">
                                                <?php echo !empty($post['full_name']) ? sanitize($post['full_name']) : sanitize($post['username']); ?>
                                            </span>
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
                                $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                                echo renderPostMediaHTML($post, $should_blur_nsfw);
                                echo renderLinkPreviewCard($post);
                                ?>
                                
                                <div class="frest-actions" style="margin-top: 14px; display: flex; gap: 16px;">
                                    <div class="reaction-container" data-post-id="<?php echo $post_id; ?>">
                                        <button class="frest-action-btn react-btn <?php echo $reacted_class; ?>" data-post-id="<?php echo $post_id; ?>" data-active-type="<?php echo $active_reaction ?: ''; ?>">
                                            <i class="fa-regular fa-thumbs-up"></i>
                                            <?php if ($reactions_summary['total'] > 0): ?>
                                                <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reactions_summary['total']; ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                    
                                    <button class="frest-action-btn reply-btn" onclick="window.location.href='detail.php?id=<?php echo $post_url_id; ?>#reply-composer';">
                                        <i class="fa-regular fa-comment"></i>
                                        <?php if ($replies_count > 0): ?>
                                            <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $replies_count; ?></span>
                                        <?php endif; ?>
                                    </button>
                                    
                                    <button class="frest-action-btn bookmark-btn bookmarked" onclick="event.stopPropagation(); toggleBookmark(this, <?php echo $post_id; ?>);" title="Lưu bài viết" style="color: var(--accent-primary);">
                                        <i class="fa-solid fa-bookmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php $user_posts = $backup_user_posts; // Restore ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
 <!-- profile-feed-column -->
            </div> <!-- profile-main-grid -->
        </div> <!-- pro-section-posts -->

        <?php if ($is_pro_layout): ?>
            <!-- About Section -->
            <div id="pro-section-about" class="pro-section-tab-content" style="display: none;">
                <div class="pro-about-card">
                    <div class="pro-about-grid">
                        <div class="pro-about-sidebar">
                            <button class="pro-about-sidebar-btn active" data-subtab="overview">
                                <i class="fa-solid fa-circle-info"></i> Tổng quan
                            </button>
                            <button class="pro-about-sidebar-btn" data-subtab="contact">
                                <i class="fa-solid fa-address-book"></i> Liên hệ &amp; Thông tin cơ bản
                            </button>
                            <?php if ($is_page_profile): ?>
                               <button class="pro-about-sidebar-btn" data-subtab="page-details">
                                   <i class="fa-solid fa-circle-info"></i> Chi tiết về Trang
                               </button>
                            <?php endif; ?>
                            <button class="pro-about-sidebar-btn" data-subtab="transparency">
                                <i class="fa-solid fa-circle-exclamation"></i> Tính minh bạch
                            </button>
                        </div>
                        <div class="pro-about-content">
                            <!-- Overview subtab -->
                            <div id="about-subtab-overview" class="about-subtab-content">
                                <h3 class="pro-about-section-title">Tổng quan</h3>
                                <div class="pro-about-list">
                                    <?php if (!$is_page_profile && !empty($profile_user['workplace'])): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-briefcase"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Công việc</span>
                                                <span class="pro-about-value">Làm việc tại <?php echo getWorkplaceLinkHTML($profile_user['workplace']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $show_lives = $is_page_profile ? !empty($profile_page['lives_at']) : !empty($profile_user['lives_at']);
                                    $lives_val = $is_page_profile ? $profile_page['lives_at'] : $profile_user['lives_at'];
                                    if ($show_lives): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-house-chimney"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Nơi sống</span>
                                                <span class="pro-about-value">Sống tại <?php echo sanitize($lives_val); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $show_country = $is_page_profile ? !empty($profile_page['country']) : !empty($profile_user['country']);
                                    $country_val = $is_page_profile ? $profile_page['country'] : $profile_user['country'];
                                    if ($show_country): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-location-dot"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Quê quán / Quốc gia</span>
                                                <span class="pro-about-value">Đến từ <?php echo sanitize($country_val); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $created_val = $is_page_profile ? $profile_page['created_at'] : $profile_user['created_at'];
                                    if (!empty($created_val)):
                                    ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-calendar-days"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Ngày tham gia</span>
                                                <span class="pro-about-value">Đã tham gia vào <?php echo date('m/Y', strtotime($created_val)); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Contact subtab -->
                            <div id="about-subtab-contact" class="about-subtab-content" style="display: none;">
                                <h3 class="pro-about-section-title">Thông tin liên hệ &amp; cơ bản</h3>
                                <div class="pro-about-list">
                                    <?php 
                                    $email_val = $is_page_profile ? $profile_page['email'] : $profile_user['email'];
                                    $show_email = $is_page_profile ? (intval($profile_page['show_email'] ?? 1) === 1 || $is_my_profile) : (intval($profile_user['show_email'] ?? 1) === 1 || $is_my_profile);
                                    if (!empty($email_val) && $show_email): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-envelope"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Email</span>
                                                <span class="pro-about-value"><?php echo sanitize($email_val); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $phone_val = $is_page_profile ? $profile_page['phone_number'] : $profile_user['phone_number'];
                                    $show_phone = $is_page_profile ? (intval($profile_page['show_phone'] ?? 1) === 1 || $is_my_profile) : (intval($profile_user['show_phone'] ?? 1) === 1 || $is_my_profile);
                                    if (!empty($phone_val) && $show_phone): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-phone"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Điện thoại</span>
                                                <span class="pro-about-value"><?php echo sanitize($phone_val); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $web_val = $is_page_profile ? $profile_page['website'] : $profile_user['website'];
                                    $show_web = $is_page_profile ? (intval($profile_page['show_website'] ?? 1) === 1 || $is_my_profile) : true;
                                    if (!empty($web_val) && $show_web): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-globe"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Website</span>
                                                <span class="pro-about-value"><a href="<?php echo htmlspecialchars($web_val); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($web_val); ?></a></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    if (!$is_page_profile):
                                        $show_gender = (intval($profile_user['show_gender'] ?? 1) === 1 || $is_my_profile);
                                        if (!empty($profile_user['gender']) && $show_gender): ?>
                                            <div class="pro-about-item">
                                                <div class="pro-about-icon-box"><i class="fa-solid fa-venus-mars"></i></div>
                                                <div class="pro-about-text-box">
                                                    <span class="pro-about-label">Giới tính</span>
                                                    <span class="pro-about-value"><?php echo sanitize($profile_user['gender']); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        $show_dob = (intval($profile_user['show_dob'] ?? 1) === 1 || $is_my_profile);
                                        if (!empty($profile_user['dob']) && $show_dob): ?>
                                            <div class="pro-about-item">
                                                <div class="pro-about-icon-box"><i class="fa-solid fa-cake-candles"></i></div>
                                                <div class="pro-about-text-box">
                                                    <span class="pro-about-label">Ngày sinh</span>
                                                    <span class="pro-about-value"><?php echo date('d/m/Y', strtotime($profile_user['dob'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($socials)): ?>
                                        <div class="pro-about-item">
                                            <div class="pro-about-icon-box"><i class="fa-solid fa-share-nodes"></i></div>
                                            <div class="pro-about-text-box">
                                                <span class="pro-about-label">Liên kết mạng xã hội</span>
                                                <div style="display: flex; gap: 10px; margin-top: 6px;">
                                                    <?php foreach ($socials as $name => $data): ?>
                                                        <a href="<?php echo htmlspecialchars($data['link']); ?>" target="_blank" rel="noopener noreferrer" class="social-icon-btn" style="--social-color: <?php echo $data['color']; ?>; --social-hover-bg: <?php echo $data['hover_bg']; ?>; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color);" title="<?php echo $data['title'] ?? ucfirst($name); ?>">
                                                            <i class="<?php echo $data['icon']; ?>" style="font-size: 13px;"></i>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Page Details subtab -->
                            <?php if ($is_page_profile): ?>
                                <div id="about-subtab-page-details" class="about-subtab-content" style="display: none;">
                                    <h3 class="pro-about-section-title">Chi tiết về Trang</h3>
                                    <div class="pro-about-list">
                                        <?php if (!empty($profile_page['working_hours'])): ?>
                                            <div class="pro-about-item">
                                                <div class="pro-about-icon-box"><i class="fa-regular fa-clock"></i></div>
                                                <div class="pro-about-text-box">
                                                    <span class="pro-about-label">Giờ mở cửa</span>
                                                    <span class="pro-about-value"><?php echo sanitize($profile_page['working_hours']); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile_page['services'])): ?>
                                            <div class="pro-about-item">
                                                <div class="pro-about-icon-box"><i class="fa-solid fa-briefcase"></i></div>
                                                <div class="pro-about-text-box">
                                                    <span class="pro-about-label">Dịch vụ cung cấp</span>
                                                    <span class="pro-about-value"><?php echo sanitize($profile_page['services']); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile_page['founded_at'])): ?>
                                            <div class="pro-about-item">
                                                <div class="pro-about-icon-box"><i class="fa-solid fa-calendar-days"></i></div>
                                                <div class="pro-about-text-box">
                                                    <span class="pro-about-label">Thành lập</span>
                                                    <span class="pro-about-value"><?php echo date('d/m/Y', strtotime($profile_page['founded_at'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Transparency subtab -->
                            <div id="about-subtab-transparency" class="about-subtab-content" style="display: none;">
                                <h3 class="pro-about-section-title">Tính minh bạch</h3>
                                <div class="pro-about-list" style="text-align: left;">
                                    <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 20px;">
                                        Frest hiển thị thông tin này để giúp bạn hiểu rõ hơn về các trang và tài khoản cá nhân.
                                    </p>
                                    
                                    <!-- Badges info -->
                                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; display: flex; flex-direction: column; gap: 14px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center; color: var(--accent-primary); font-size: 18px; flex-shrink: 0;">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </div>
                                            <div>
                                                <h4 style="margin: 0; font-size: 14.5px; font-weight: 700; color: var(--text-primary);">Trạng thái xác minh</h4>
                                                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                                                    <?php 
                                                    $is_user_verified = !$is_page_profile && !empty($profile_user['verification_type']) && $profile_user['verification_type'] !== 'none';
                                                    $is_page_verified = $is_page_profile && (intval($profile_page['is_verified'] ?? 0) === 1 || (!empty($profile_page['verification_type']) && $profile_page['verification_type'] !== 'none'));
                                                    
                                                    if ($is_user_verified) {
                                                        echo 'Tài khoản này đã được xác minh huy hiệu ' . ($profile_user['verification_type'] === 'subscribed' ? 'Frest đã xác minh' : 'Xác minh chính chủ') . '.';
                                                    } elseif ($is_page_verified) {
                                                        echo 'Trang này đã được xác minh.';
                                                    } else {
                                                        echo 'Tài khoản/Trang này chưa được xác minh tích xanh.';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; align-items: center; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; color: var(--success); font-size: 18px; flex-shrink: 0;">
                                                <i class="fa-solid fa-calendar-days"></i>
                                            </div>
                                            <div>
                                                <h4 style="margin: 0; font-size: 14.5px; font-weight: 700; color: var(--text-primary);">Ngày thành lập / Tham gia</h4>
                                                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                                                    <?php 
                                                    if (!empty($created_val)) {
                                                        echo 'Được tạo/Tham gia vào ngày ' . date('d/m/Y', strtotime($created_val)) . '.';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        $sync_status = $is_page_profile ? intval($profile_page['sync_transparency_status'] ?? 0) : intval($profile_user['sync_transparency_status'] ?? 0);
                                        $last_up = $is_page_profile ? $profile_page['updated_at'] : $profile_user['profile_updated_at'];
                                        $is_recent = false;
                                        if ($sync_status === 1 && !empty($last_up)) {
                                            $diff = time() - strtotime($last_up);
                                            if ($diff >= 0 && $diff <= 7 * 86400) {
                                                $is_recent = true;
                                            }
                                        }
                                        if ($is_recent):
                                        ?>
                                            <div style="display: flex; align-items: center; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(235, 94, 40, 0.1); display: flex; align-items: center; justify-content: center; color: var(--accent-primary); font-size: 18px; flex-shrink: 0;">
                                                    <i class="fa-solid fa-user-pen"></i>
                                                </div>
                                                <div>
                                                    <h4 style="margin: 0; font-size: 14.5px; font-weight: 700; color: var(--text-primary);">Hoạt động cập nhật</h4>
                                                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                                                        <?php 
                                                        if ($is_page_profile) {
                                                            echo 'Trang đã cập nhật thông tin gần đây.';
                                                        } else {
                                                            echo 'Người dùng đã cập nhật thông tin cá nhân gần đây.';
                                                        }
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Followers Section -->
            <div id="pro-section-followers" class="pro-section-tab-content" style="display: none;">
                <div class="pro-followers-container">
                    <h3 style="font-family: var(--font-heading); font-size: 16px; margin: 0 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; text-align: left;">Người theo dõi</h3>
                    <?php if (empty($pro_followers)): ?>
                        <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 24px 0;">Chưa có người theo dõi nào.</p>
                    <?php else: ?>
                        <div class="pro-followers-grid">
                            <?php foreach ($pro_followers as $fl): ?>
                                <div class="pro-follower-card">
                                    <img src="<?php echo AVATARS_URL . '/' . sanitize($fl['avatar_filename']); ?>" alt="Avatar">
                                    <div class="pro-follower-details">
                                        <a href="profile.php?username=<?php echo sanitize($fl['username']); ?>" class="pro-follower-name">
                                            <?php echo !empty($fl['full_name']) ? sanitize($fl['full_name']) : sanitize($fl['username']); ?>
                                        </a>
                                        <div class="pro-follower-username">@<?php echo sanitize($fl['username']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$is_page_profile && !empty($pro_following)): ?>
                        <h3 style="font-family: var(--font-heading); font-size: 16px; margin: 32px 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; text-align: left;">Đang theo dõi</h3>
                        <div class="pro-followers-grid">
                            <?php foreach ($pro_following as $fg): ?>
                                <div class="pro-follower-card">
                                    <img src="<?php echo AVATARS_URL . '/' . sanitize($fg['avatar_filename']); ?>" alt="Avatar">
                                    <div class="pro-follower-details">
                                        <a href="profile.php?username=<?php echo sanitize($fg['username']); ?>" class="pro-follower-name">
                                            <?php echo !empty($fg['full_name']) ? sanitize($fg['full_name']) : sanitize($fg['username']); ?>
                                        </a>
                                        <div class="pro-follower-username">@<?php echo sanitize($fg['username']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Saved Section (only for owner) -->
            <?php if ($is_my_profile): ?>
                <div id="pro-section-saved" class="pro-section-tab-content" style="display: none;">
                    <div class="pro-followers-container">
                        <h3 style="font-family: var(--font-heading); font-size: 16px; margin: 0 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; text-align: left;">Frest đã lưu</h3>
                        <?php if (empty($pro_saved_posts)): ?>
                            <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 24px 0;">Không có bài viết nào đã lưu.</p>
                        <?php else: ?>
                            <div class="feed-container">
                                <?php 
                                $saved_original_posts = $user_posts;
                                $user_posts = $pro_saved_posts;
                                foreach ($user_posts as $post): 
                                    $post_id = $post['id'];
                                    $post_url_id = !empty($post['post_token']) ? $post['post_token'] : $post['id'];
                                    $replies_count = intval($post['replies_count'] ?? 0);
                                    $active_reaction = $post['active_reaction'] ?: false;
                                    $reacted_class = $active_reaction ? 'active' : '';
                                    $reactions_summary = [
                                        'total' => intval($post['reactions_total'] ?? 0),
                                        'types' => []
                                    ];
                                    $reposts_count = intval($post['reposts_count'] ?? 0);
                                    $user_reposted = false;
                                    $original_post = null;
                                    $is_my_repost = false;
                                    $glow_class = ($post_id % 2 === 0) ? 'glowing-card-cyan' : 'glowing-card-purple';
                                ?>
                                    <div class="frest-card <?php echo $glow_class; ?>" data-post-id="<?php echo $post_id; ?>" data-post-token="<?php echo $post_url_id; ?>">
                                        <div class="frest-left">
                                            <img src="<?php echo AVATARS_URL . '/' . sanitize($post['avatar_filename']); ?>" class="frest-avatar" alt="Avatar">
                                            <div class="frest-line"></div>
                                        </div>
                                        <div class="frest-right">
                                            <div class="frest-header">
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <div style="display: flex; align-items: center; gap: 6px;">
                                                        <span class="frest-author" style="font-weight: 700; color: var(--text-primary);">
                                                            <?php echo !empty($post['full_name']) ? sanitize($post['full_name']) : sanitize($post['username']); ?>
                                                        </span>
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
                                                 <div class="frest-content" onclick="window.location.href='detail.php?id=<?php echo $post_id; ?>';" style="cursor: pointer; text-align: left;"><?php echo nl2br(parseHashtags(linkify(sanitize($post['content'])))); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            $is_nsfw_post = (isset($post['is_nsfw']) && intval($post['is_nsfw']) === 1);
                                            $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                                            echo renderPostMediaHTML($post, $should_blur_nsfw);
                                            echo renderLinkPreviewCard($post);
                                            ?>
                                            
                                            <div class="frest-actions" style="margin-top: 14px; display: flex; gap: 16px;">
                                                <div class="reaction-container" data-post-id="<?php echo $post_id; ?>">
                                                    <button class="frest-action-btn react-btn <?php echo $reacted_class; ?>" data-post-id="<?php echo $post_id; ?>" data-active-type="<?php echo $active_reaction ?: ''; ?>">
                                                        <i class="fa-regular fa-thumbs-up"></i>
                                                        <?php if ($reactions_summary['total'] > 0): ?>
                                                            <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $reactions_summary['total']; ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                </div>
                                                
                                                <button class="frest-action-btn reply-btn" onclick="window.location.href='detail.php?id=<?php echo $post_url_id; ?>#reply-composer';">
                                                    <i class="fa-regular fa-comment"></i>
                                                    <?php if ($replies_count > 0): ?>
                                                        <span class="action-count" style="font-size: 12.5px; margin-left: 6px; font-weight: 500;"><?php echo $replies_count; ?></span>
                                                    <?php endif; ?>
                                                </button>
                                                
                                                <button class="frest-action-btn bookmark-btn bookmarked" onclick="event.stopPropagation(); toggleBookmark(this, <?php echo $post_id; ?>);" title="Lưu bài viết" style="color: var(--accent-primary);">
                                                    <i class="fa-solid fa-bookmark"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                endforeach; 
                                $user_posts = $saved_original_posts;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Photos Section (Pro Layout) -->
            <div id="pro-section-photos" class="pro-section-tab-content" style="display: none;">
                <div class="pro-followers-container">
                    <h3 style="font-family: var(--font-heading); font-size: 16px; margin: 0 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; text-align: left;">Ảnh</h3>
                    <div class="profile-media-grid">
                        <?php if (empty($photos)): ?>
                            <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 24px 0; grid-column: 1 / -1; width: 100%;">Chưa có ảnh nào được đăng tải.</p>
                        <?php else: ?>
                            <?php foreach ($photos as $photo_post): ?>
                                <?php 
                                $images = array_values(array_filter(explode(',', $photo_post['image_filename'])));
                                $is_nsfw_post = (isset($photo_post['is_nsfw']) && intval($photo_post['is_nsfw']) === 1);
                                $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                                $allow_download = isset($photo_post['allow_download']) ? intval($photo_post['allow_download']) : 1;
                                
                                foreach ($images as $img):
                                ?>
                                    <?php if ($should_blur_nsfw): ?>
                                        <div class="nsfw-container" data-post-id="<?php echo $photo_post['id']; ?>" style="margin-top: 0; aspect-ratio: 1/1; border-radius: var(--radius-md); overflow: hidden;">
                                            <div class="nsfw-overlay" style="padding: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                                <i class="fa-solid fa-eye-slash nsfw-overlay-icon" style="font-size: 20px; margin-bottom: 6px;"></i>
                                                <div class="nsfw-overlay-title" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">18+</div>
                                                <button type="button" class="nsfw-reveal-btn" style="padding: 4px 8px; font-size: 10px; border-radius: var(--radius-sm);">Xem</button>
                                            </div>
                                            <div class="nsfw-blurred" style="width: 100%; height: 100%;">
                                                <div class="profile-photo-card" onclick="window.openLightboxDirect(event, '<?php echo htmlspecialchars($img); ?>', <?php echo $allow_download; ?>)" style="width: 100%; height: 100%;">
                                                    <img src="<?php echo SITE_URL . '/uploads/posts/' . sanitize($img); ?>" alt="Photo" loading="lazy">
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="profile-photo-card" onclick="window.openLightboxDirect(event, '<?php echo htmlspecialchars($img); ?>', <?php echo $allow_download; ?>)">
                                            <img src="<?php echo SITE_URL . '/uploads/posts/' . sanitize($img); ?>" alt="Photo" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Videos Section (Pro Layout) -->
            <div id="pro-section-videos" class="pro-section-tab-content" style="display: none;">
                <div class="pro-followers-container">
                    <h3 style="font-family: var(--font-heading); font-size: 16px; margin: 0 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; text-align: left;">Video</h3>
                    <div class="profile-video-list">
                        <?php if (empty($videos)): ?>
                            <p style="color: var(--text-muted); font-size: 14px; font-style: italic; text-align: center; padding: 24px 0;">Chưa có video nào được đăng tải.</p>
                        <?php else: ?>
                            <?php foreach ($videos as $video_post): ?>
                                <div class="profile-video-card">
                                    <div class="video-card-header">
                                        <img src="<?php echo AVATARS_URL . '/' . sanitize($video_post['avatar_filename'] ?? ''); ?>" class="frest-avatar" alt="Avatar">
                                        <div style="display: flex; flex-direction: column;">
                                            <span class="frest-author" style="font-weight: 700; color: var(--text-primary);">
                                                <?php echo sanitize(($video_post['full_name'] ?? '') ?: ($video_post['username'] ?? '')); ?>
                                            </span>
                                            <span class="frest-time" style="font-size: 11.5px; color: var(--text-muted);"><?php echo timeElapsedString($video_post['created_at'] ?? 'now'); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($video_post['content'])): ?>
                                        <div class="video-card-caption"><?php echo nl2br(parseHashtags(linkify(sanitize($video_post['content'])))); ?></div>
                                    <?php endif; ?>
                                    <div class="video-card-body">
                                        <?php 
                                        $is_nsfw_post = (isset($video_post['is_nsfw']) && intval($video_post['is_nsfw']) === 1);
                                        $should_blur_nsfw = $is_nsfw_post && !$user_show_nsfw;
                                        echo renderPostMediaHTML($video_post, $should_blur_nsfw); 
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        </div> <!-- pro-section-posts -->
    <?php endif; ?>
    <?php endif; ?>
    <!-- Account Settings Modal (Moved to footer.php) -->

</div>

<script>
(function() {
    const workplaceInput = document.getElementById('workplace');
    const suggestionsDiv = document.getElementById('workplace-suggestions');
    const previewContainer = document.getElementById('workplace-preview-container');
    let debounceTimer;
    let previewDebounceTimer;

    function checkWorkplacePage(val) {
        if (!previewContainer) return;
        const query = val.trim();
        if (query.length < 1) {
            previewContainer.style.display = 'none';
            previewContainer.innerHTML = '';
            return;
        }
        fetch(SITE_URL + '/check_workplace_page.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    previewContainer.innerHTML = `<i class="fa-solid fa-link" style="color: var(--accent-primary); margin-right: 4px;"></i> Trang liên kết: <a href="page.php?username=${encodeURIComponent(data.username)}" style="color: var(--accent-primary); font-weight: 700; text-decoration: none;">${data.name}</a>`;
                    previewContainer.style.display = 'block';
                } else {
                    previewContainer.style.display = 'none';
                    previewContainer.innerHTML = '';
                }
            })
            .catch(() => {
                previewContainer.style.display = 'none';
            });
    }

    if (workplaceInput && suggestionsDiv) {
        // Run check on initial load
        checkWorkplacePage(workplaceInput.value);

        workplaceInput.addEventListener('input', function() {
            const val = this.value;
            
            // Check preview
            clearTimeout(previewDebounceTimer);
            previewDebounceTimer = setTimeout(() => {
                checkWorkplacePage(val);
            }, 300);

            const atIndex = val.lastIndexOf('@');
            
            if (atIndex === -1) {
                suggestionsDiv.style.display = 'none';
                suggestionsDiv.innerHTML = '';
                return;
            }

            const query = val.substring(atIndex + 1).trim();
            
            if (query.includes(' ') || query.length < 1) {
                clearTimeout(debounceTimer);
                suggestionsDiv.style.display = 'none';
                suggestionsDiv.innerHTML = '';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetch(SITE_URL + '/search_mention.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        const pages = data.filter(item => item.type === 'page');
                        
                        if (pages.length === 0) {
                            suggestionsDiv.style.display = 'none';
                            suggestionsDiv.innerHTML = '';
                            return;
                        }

                        let html = '';
                        pages.forEach(page => {
                            html += `
                                <div class="wp-suggestion-item" data-username="${page.handle}" style="display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: var(--radius-sm); cursor: pointer; transition: background 0.2s;">
                                    <img src="${page.avatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                    <div style="flex: 1; min-width: 0; text-align: left;">
                                        <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${page.name}</div>
                                        <div style="font-size: 10.5px; color: var(--text-muted);">@${page.handle}</div>
                                    </div>
                                </div>
                            `;
                        });

                        suggestionsDiv.innerHTML = html;
                        suggestionsDiv.style.display = 'block';

                        const items = suggestionsDiv.querySelectorAll('.wp-suggestion-item');
                        items.forEach(item => {
                            item.addEventListener('mouseover', function() {
                                this.style.background = 'rgba(255, 255, 255, 0.04)';
                            });
                            item.addEventListener('mouseout', function() {
                                this.style.background = 'transparent';
                            });
                            
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault(); // Prevent input blur
                                const username = this.getAttribute('data-username');
                                const currentVal = workplaceInput.value;
                                const newVal = currentVal.substring(0, atIndex) + '@' + username;
                                workplaceInput.value = newVal;
                                
                                suggestionsDiv.style.display = 'none';
                                suggestionsDiv.innerHTML = '';
                                checkWorkplacePage(newVal); // Check preview immediately
                                workplaceInput.focus();
                            });
                        });
                    })
                    .catch(() => {
                        suggestionsDiv.style.display = 'none';
                    });
            }, 200);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== workplaceInput && e.target !== suggestionsDiv && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.style.display = 'none';
            }
        });
        
        workplaceInput.addEventListener('focus', function() {
            const val = this.value;
            const atIndex = val.lastIndexOf('@');
            if (atIndex !== -1) {
                const query = val.substring(atIndex + 1).trim();
                if (!query.includes(' ') && query.length > 0 && suggestionsDiv.children.length > 0) {
                    suggestionsDiv.style.display = 'block';
                }
            }
        });
    }
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

