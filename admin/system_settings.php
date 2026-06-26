<?php
/**
 * System Settings & Branding Panel - Frest App Admin
 */
require_once __DIR__ . '/header.php';

$error_msg = '';
$success_msg = '';

try {
    $db = getDB();

    // Ensure uploads/system directory exists
    $system_uploads_dir = __DIR__ . '/../uploads/system/';
    if (!is_dir($system_uploads_dir)) {
        mkdir($system_uploads_dir, 0777, true);
    }

    // Helper function to update site name and sync with config.php, PWA config, and manifest.json
    $syncSiteName = function($new_site_name) use ($db) {
        $old_site_name = getSystemSetting('site_name', 'Frest App');
        $current_pwa_name = getSystemSetting('pwa_name', 'Frest App - Split-View Canvas Social Network');
        $current_pwa_short_name = getSystemSetting('pwa_short_name', 'Frest');
        $pwa_description = getSystemSetting('pwa_description', 'Mạng xã hội tối giản, hiện đại với giao diện Split-View Canvas và trải nghiệm đa phương tiện.');

        // 1. Update site_name in settings table
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$new_site_name]);

        // 2. Update site_name_last_updated in settings table
        $stmt_ts = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_name_last_updated', NOW()) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt_ts->execute();

        // 3. Sync config.php define('SITE_NAME', '...')
        $config_path = __DIR__ . '/../config.php';
        if (file_exists($config_path)) {
            $config_content = @file_get_contents($config_path);
            if ($config_content !== false) {
                $pattern = "/define\s*\(\s*['\"]SITE_NAME['\"]\s*,\s*['\"].*?['\"]\s*\)\s*;/i";
                $replacement = "define('SITE_NAME', '" . addslashes($new_site_name) . "');";
                $config_content = preg_replace($pattern, $replacement, $config_content);
                @file_put_contents($config_path, $config_content);
            }
        }

        // 4. Sync pwa_name
        if (!empty($old_site_name) && strpos($current_pwa_name, $old_site_name) !== false) {
            $new_pwa_name = str_replace($old_site_name, $new_site_name, $current_pwa_name);
        } else {
            $new_pwa_name = $new_site_name . " - Split-View Canvas Social Network";
        }

        // 5. Sync pwa_short_name
        if (empty($old_site_name) || $current_pwa_short_name === $old_site_name || $current_pwa_short_name === 'Frest') {
            $new_pwa_short_name = $new_site_name;
        } else if (strpos($current_pwa_short_name, $old_site_name) !== false) {
            $new_pwa_short_name = str_replace($old_site_name, $new_site_name, $current_pwa_short_name);
        } else {
            $new_pwa_short_name = $new_site_name;
        }

        // Update settings database
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_name', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$new_pwa_name]);
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_short_name', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$new_pwa_short_name]);

        // 6. Write manifest.json
        $pwa_icon_base = getSystemSetting('pwa_icon', '');
        $manifest = [
            "name" => $new_pwa_name,
            "short_name" => $new_pwa_short_name,
            "description" => $pwa_description,
            "start_url" => "./index.php",
            "display" => "standalone",
            "background_color" => "#070709",
            "theme_color" => "#3b82f6",
            "orientation" => "portrait-primary",
            "scope" => "./",
            "icons" => !empty($pwa_icon_base) ? [
                ["src" => "uploads/system/" . $pwa_icon_base . "_72.png", "sizes" => "72x72", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_96.png", "sizes" => "96x96", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_144.png", "sizes" => "144x144", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_152.png", "sizes" => "152x152", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_384.png", "sizes" => "384x384", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_512_maskable.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
            ] : [
                ["src" => "assets/images/icons/icon-72x72.png", "sizes" => "72x72", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-96x96.png", "sizes" => "96x96", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-128x128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-144x144.png", "sizes" => "144x144", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-152x152.png", "sizes" => "152x152", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-192x192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-384x384.png", "sizes" => "384x384", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-512x512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-512x512-maskable.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
            ]
        ];
        @file_put_contents(__DIR__ . '/../manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    };

    // 1. Handle Approve site name request
    if (isset($_GET['approve_name'])) {
        $pending_name = getSystemSetting('pending_site_name', '');
        $status = getSystemSetting('pending_site_name_status', '');
        
        if (!empty($pending_name) && $status === 'pending') {
            $syncSiteName($pending_name);

            // Clear pending fields
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name_status', 'approved') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name', NULL) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();
            
            getSystemSetting(null, '', true);
            $success_msg = "Đã phê duyệt và đồng bộ thay đổi tên hệ thống thành công thành: '{$pending_name}'.";
        } else {
            $error_msg = "Yêu cầu phê duyệt không hợp lệ hoặc không có yêu cầu nào đang chờ.";
        }
    }

    // 2. Handle Reject site name request
    if (isset($_GET['reject_name'])) {
        $status = getSystemSetting('pending_site_name_status', '');
        if ($status === 'pending') {
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name_status', 'rejected') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name', NULL) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();
            getSystemSetting(null, '', true);
            $success_msg = "Đã từ chối yêu cầu thay đổi tên hệ thống.";
        } else {
            $error_msg = "Không có yêu cầu đổi tên nào đang chờ để từ chối.";
        }
    }

    // 3. Process Save settings (Logo, Favicon, Site Name)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_system'])) {
        $new_site_name = trim($_POST['site_name'] ?? '');
        
        // Handle logo upload
        $logo_updated = false;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_name = $_FILES['logo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {
                $new_logo_name = 'logo_' . uniqid() . '.' . $file_ext;
                $dest = $system_uploads_dir . $new_logo_name;
                if (move_uploaded_file($file_tmp, $dest)) {
                    // Delete old custom logo if it exists to avoid accumulation
                    $old_logo = getSystemSetting('site_logo', '');
                    if (!empty($old_logo) && $old_logo !== 'site_logo.png' && file_exists($system_uploads_dir . $old_logo)) {
                        @unlink($system_uploads_dir . $old_logo);
                    }
                    // Update site_logo key in settings
                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$new_logo_name]);
                    $logo_updated = true;
                }
            } else {
                $error_msg = "Định dạng file Logo không hợp lệ (chỉ chấp nhận JPG, PNG, SVG, WEBP). ";
            }
        }

        // Handle favicon upload
        $favicon_updated = false;
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['favicon']['tmp_name'];
            $file_name = $_FILES['favicon']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['ico', 'png', 'jpg', 'jpeg'];

            if (in_array($file_ext, $allowed_exts)) {
                $new_fav_name = 'favicon_' . uniqid() . '.' . $file_ext;
                $dest = $system_uploads_dir . $new_fav_name;
                if (move_uploaded_file($file_tmp, $dest)) {
                    // Delete old custom favicon if it exists to avoid accumulation
                    $old_fav = getSystemSetting('site_favicon', '');
                    if (!empty($old_fav) && $old_fav !== 'site_favicon.png' && file_exists($system_uploads_dir . $old_fav)) {
                        @unlink($system_uploads_dir . $old_fav);
                    }
                    // Update site_favicon key in settings
                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_favicon', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$new_fav_name]);
                    $favicon_updated = true;
                }
            } else {
                $error_msg .= "Định dạng file Favicon không hợp lệ (chỉ chấp nhận ICO, PNG, JPG). ";
            }
        }

        // Handle Site Name update (60-day logic removed, direct change and sync)
        $site_name_changed = false;
        $current_site_name = getSystemSetting('site_name', 'Frest App');
        
        if (!empty($new_site_name) && $new_site_name !== $current_site_name) {
            $syncSiteName($new_site_name);

            // Clean up any stale pending site name requests
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name_status', 'approved') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();
            $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pending_site_name', NULL) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)")->execute();

            $success_msg .= "Đã thay đổi tên nền tảng và đồng bộ thành công thành: '{$new_site_name}'. ";
            $site_name_changed = true;
        }

        if ($logo_updated || $favicon_updated) {
            $success_msg .= "Đã cập nhật Logo/Favicon mới thành công. ";
        }
        getSystemSetting(null, '', true);
    }

    // 4. Process Save Footer settings (Copyright, Links)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_footer'])) {
        $new_copyright = trim($_POST['footer_copyright'] ?? '');
        
        // Update footer_copyright key in settings
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('footer_copyright', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$new_copyright]);
        
        // Process links
        $link_titles = $_POST['link_title'] ?? [];
        $link_urls = $_POST['link_url'] ?? [];
        $link_types = $_POST['link_type'] ?? [];
        $link_icons = $_POST['link_icon'] ?? [];
        
        $links = [];
        for ($i = 0; $i < count($link_titles); $i++) {
            $title = trim($link_titles[$i]);
            if (!empty($title)) {
                $links[] = [
                    'title' => $title,
                    'url' => trim($link_urls[$i] ?? '#'),
                    'type' => trim($link_types[$i] ?? 'external'),
                    'icon' => trim($link_icons[$i] ?? '')
                ];
            }
        }
        
        $links_json = json_encode($links, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('footer_links', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$links_json]);
        
        getSystemSetting(null, '', true);
        $success_msg = "Đã cập nhật cấu hình chân trang thành công.";
    }

    // 5. Process Save PWA settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_pwa'])) {
        $pwa_enabled = isset($_POST['pwa_enabled']) ? '1' : '0';
        $pwa_name = trim($_POST['pwa_name'] ?? '');
        $pwa_short_name = trim($_POST['pwa_short_name'] ?? '');
        $pwa_description = trim($_POST['pwa_description'] ?? '');
        $pwa_version = trim($_POST['pwa_version'] ?? '1.0.0');

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_enabled', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$pwa_enabled]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_name', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$pwa_name]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_short_name', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$pwa_short_name]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_description', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$pwa_description]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_version', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$pwa_version]);

        // Process PWA icon file upload if any
        if (isset($_FILES['pwa_icon']) && $_FILES['pwa_icon']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['pwa_icon']['tmp_name'];
            $file_name = $_FILES['pwa_icon']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($file_ext === 'png') {
                $pwa_icon_base = 'pwa_icon_' . uniqid();
                $dest_base = $system_uploads_dir . $pwa_icon_base;

                $temp_uploaded = $system_uploads_dir . 'temp_pwa_icon.png';
                if (move_uploaded_file($file_tmp, $temp_uploaded)) {
                    $sizes = [
                        '72' => [72, 72],
                        '96' => [96, 96],
                        '128' => [128, 128],
                        '144' => [144, 144],
                        '152' => [152, 152],
                        '192' => [192, 192],
                        '384' => [384, 384],
                        '512' => [512, 512],
                        '512_maskable' => [512, 512]
                    ];

                    $resize_helper = function($src_path, $dest_path, $w, $h) {
                        if (!extension_loaded('gd')) {
                            return copy($src_path, $dest_path);
                        }
                        list($src_w, $src_h) = getimagesize($src_path);
                        if ($src_w <= 0 || $src_h <= 0) {
                            return copy($src_path, $dest_path);
                        }
                        $src_img = @imagecreatefrompng($src_path);
                        if (!$src_img) {
                            $src_img = @imagecreatefromstring(file_get_contents($src_path));
                            if (!$src_img) {
                                return copy($src_path, $dest_path);
                            }
                        }
                        $dst_img = imagecreatetruecolor($w, $h);
                        imagealphablending($dst_img, false);
                        imagesavealpha($dst_img, true);
                        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $w, $h, $src_w, $src_h);
                        $result = imagepng($dst_img, $dest_path);
                        imagedestroy($src_img);
                        imagedestroy($dst_img);
                        return $result;
                    };

                    // Delete old custom files if they exist
                    $old_pwa_icon = getSystemSetting('pwa_icon', '');
                    if (!empty($old_pwa_icon)) {
                        foreach (['72', '96', '128', '144', '152', '192', '384', '512', '512_maskable'] as $s) {
                            $old_file = $system_uploads_dir . $old_pwa_icon . '_' . $s . '.png';
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }
                    }

                    // Generate resized versions
                    foreach ($sizes as $s_name => $dim) {
                        $resize_helper($temp_uploaded, $dest_base . '_' . $s_name . '.png', $dim[0], $dim[1]);
                    }
                    @unlink($temp_uploaded);

                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_icon', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$pwa_icon_base]);
                }
            } else {
                $error_msg .= "Định dạng file biểu tượng PWA không hợp lệ (chỉ chấp nhận PNG). ";
            }
        }

        $pwa_icon_base = getSystemSetting('pwa_icon', '');

        // Write manifest.json
        $manifest = [
            "name" => $pwa_name,
            "short_name" => $pwa_short_name,
            "description" => $pwa_description,
            "start_url" => "./index.php",
            "display" => "standalone",
            "background_color" => "#070709",
            "theme_color" => "#3b82f6",
            "orientation" => "portrait-primary",
            "scope" => "./",
            "icons" => !empty($pwa_icon_base) ? [
                ["src" => "uploads/system/" . $pwa_icon_base . "_72.png", "sizes" => "72x72", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_96.png", "sizes" => "96x96", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_144.png", "sizes" => "144x144", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_152.png", "sizes" => "152x152", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_384.png", "sizes" => "384x384", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
                ["src" => "uploads/system/" . $pwa_icon_base . "_512_maskable.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
            ] : [
                ["src" => "assets/images/icons/icon-72x72.png", "sizes" => "72x72", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-96x96.png", "sizes" => "96x96", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-128x128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-144x144.png", "sizes" => "144x144", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-152x152.png", "sizes" => "152x152", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-192x192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-384x384.png", "sizes" => "384x384", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-512x512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
                ["src" => "assets/images/icons/icon-512x512-maskable.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
            ]
        ];
        file_put_contents(__DIR__ . '/../manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Regenerate sw.js with updated cache version and updated dynamic icons
        $sw_file = __DIR__ . '/../sw.js';
        if (file_exists($sw_file)) {
            $sw_content = file_get_contents($sw_file);
            $sw_content = preg_replace("/const CACHE_NAME = 'frest-static-v[^']*';/", "const CACHE_NAME = 'frest-static-v{$pwa_version}';", $sw_content);
            $sw_content = preg_replace("/const DYNAMIC_CACHE_NAME = 'frest-dynamic-v[^']*';/", "const DYNAMIC_CACHE_NAME = 'frest-dynamic-v{$pwa_version}';", $sw_content);

            $icons_cache_block = '';
            if (!empty($pwa_icon_base)) {
                $icons_cache_block = "\n  './uploads/system/" . $pwa_icon_base . "_72.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_96.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_128.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_144.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_152.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_192.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_384.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_512.png',\n" .
                                    "  './uploads/system/" . $pwa_icon_base . "_512_maskable.png'\n  ";
            } else {
                $icons_cache_block = "\n  './assets/images/icons/icon-72x72.png',\n" .
                                    "  './assets/images/icons/icon-96x96.png',\n" .
                                    "  './assets/images/icons/icon-128x128.png',\n" .
                                    "  './assets/images/icons/icon-144x144.png',\n" .
                                    "  './assets/images/icons/icon-152x152.png',\n" .
                                    "  './assets/images/icons/icon-192x192.png',\n" .
                                    "  './assets/images/icons/icon-384x384.png',\n" .
                                    "  './assets/images/icons/icon-512x512.png',\n" .
                                    "  './assets/images/icons/icon-512x512-maskable.png'\n  ";
            }
            $sw_content = preg_replace("/\/\/ PWA_ICONS_START.*?\/\/ PWA_ICONS_END/s", "// PWA_ICONS_START" . $icons_cache_block . "// PWA_ICONS_END", $sw_content);

            file_put_contents($sw_file, $sw_content);
        }

        getSystemSetting(null, '', true);
        $success_msg = "Cập nhật cấu hình PWA thành công và đã nâng cấp Cache phiên bản: v{$pwa_version}.";
    }

    // 6. Process Save Maintenance settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_maintenance'])) {
        $maintenance_enabled = isset($_POST['maintenance_enabled']) ? '1' : '0';
        $maintenance_message = trim($_POST['maintenance_message'] ?? '');

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('maintenance_enabled', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$maintenance_enabled]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('maintenance_message', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$maintenance_message]);

        getSystemSetting(null, '', true);
        $success_msg = "Cập nhật cấu hình bảo trì hệ thống thành công.";
    }

    // 7. Process Save Verification & Transparency settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_verification'])) {
        $verified_official_title = trim($_POST['verified_official_title'] ?? '');
        $verified_official_desc = trim($_POST['verified_official_desc'] ?? '');
        $verified_subscribed_title = trim($_POST['verified_subscribed_title'] ?? '');
        $verified_subscribed_desc = trim($_POST['verified_subscribed_desc'] ?? '');
        $verified_learn_more_url = trim($_POST['verified_learn_more_url'] ?? '');
        $transparency_user_updated_text = trim($_POST['transparency_user_updated_text'] ?? '');
        $transparency_page_updated_text = trim($_POST['transparency_page_updated_text'] ?? '');

        $keys = [
            'verified_official_title' => $verified_official_title,
            'verified_official_desc' => $verified_official_desc,
            'verified_subscribed_title' => $verified_subscribed_title,
            'verified_subscribed_desc' => $verified_subscribed_desc,
            'verified_learn_more_url' => $verified_learn_more_url,
            'transparency_user_updated_text' => $transparency_user_updated_text,
            'transparency_page_updated_text' => $transparency_page_updated_text
        ];

        foreach ($keys as $key => $val) {
            $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
            $stmt->execute([$key, $val]);
        }

        getSystemSetting(null, '', true);
        $success_msg = "Cập nhật cấu hình tích xác minh và tính minh bạch thành công.";
    }

    // Load configurations
    $site_name = getSystemSetting('site_name', 'Frest App');
    $site_logo = getSystemSetting('site_logo', '');
    $site_favicon = getSystemSetting('site_favicon', '');
    $last_updated = getSystemSetting('site_name_last_updated', '');
    
    // Load pending request
    $pending_name = getSystemSetting('pending_site_name', '');
    $pending_status = getSystemSetting('pending_site_name_status', '');
    $pending_by = getSystemSetting('pending_site_name_requested_by', '');
    $pending_at = getSystemSetting('pending_site_name_requested_at', '');

    // Load footer configurations
    $footer_copyright = getSystemSetting('footer_copyright', '© ' . date('Y') . ' Frest App. Được thiết kế tối giản cho trải nghiệm di động & webapp.');
    $footer_links_raw = getSystemSetting('footer_links', '[]');
    $footer_links = json_decode($footer_links_raw, true);
    if (!is_array($footer_links)) {
        $footer_links = [];
    }

    // Load PWA configurations
    $pwa_enabled = getSystemSetting('pwa_enabled', '1');
    $pwa_name = getSystemSetting('pwa_name', 'Frest App - Split-View Canvas Social Network');
    $pwa_short_name = getSystemSetting('pwa_short_name', 'Frest');
    $pwa_description = getSystemSetting('pwa_description', 'Mạng xã hội tối giản, hiện đại với giao diện Split-View Canvas và trải nghiệm đa phương tiện.');
    $pwa_version = getSystemSetting('pwa_version', '1.0.0');

    // Load Maintenance configurations
    $maintenance_enabled = getSystemSetting('maintenance_enabled', '0');
    $maintenance_message = getSystemSetting('maintenance_message', 'Hệ thống đang được bảo trì định kỳ để nâng cấp hiệu năng. Vui lòng quay lại sau ít phút.');

    // Load Verification & Transparency configurations
    $verified_official_title = getSystemSetting('verified_official_title', 'Huy hiệu đã xác minh');
    $verified_official_desc = getSystemSetting('verified_official_desc', 'Huy hiệu đã xác minh cho thấy Frest đã xác minh tài khoản dựa vào hoạt động của tài khoản đó trên sản phẩm của chúng tôi và thông tin hoặc giấy tờ mà tài khoản cung cấp.');
    $verified_subscribed_title = getSystemSetting('verified_subscribed_title', 'Frest đã xác minh');
    $verified_subscribed_desc = getSystemSetting('verified_subscribed_desc', 'Frest đã xác minh là gói đăng ký trả phí mang lại nhiều lợi ích như huy hiệu đã xác minh, dịch vụ hỗ trợ nâng cao, khả năng chống mạo danh và hơn thế nữa.');
    $verified_learn_more_url = getSystemSetting('verified_learn_more_url', '');
    $transparency_user_updated_text = getSystemSetting('transparency_user_updated_text', 'Đã cập nhật thông tin cá nhân gần đây');
    $transparency_page_updated_text = getSystemSetting('transparency_page_updated_text', 'Đã cập nhật thông tin Trang gần đây');

} catch (PDOException $e) {
    $error_msg = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}
?>

<div class="admin-header">
    <h1 class="admin-title">Cấu hình Hệ thống & Thương hiệu</h1>
    <div style="font-size: 14px; color: var(--text-secondary);">
        Thiết lập tên nền tảng toàn hệ thống, upload logo ảnh, favicon và phê duyệt các yêu cầu thay đổi tên
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

<?php
$has_pending_request = ($pending_status === 'pending' && !empty($pending_name));
?>
<div style="display: <?php echo $has_pending_request ? 'grid' : 'block'; ?>; <?php echo $has_pending_request ? 'grid-template-columns: 1.2fr 1fr; gap: 24px;' : ''; ?> align-items: start; margin-bottom: 40px;">
    <!-- System Configurations Form -->
    <div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); <?php echo !$has_pending_request ? 'max-width: 700px;' : ''; ?>">
        <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
            <i class="fa-solid fa-sliders" style="color: var(--accent-primary); margin-right: 6px;"></i> Thiết lập thông tin
        </h3>

        <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action_save_system" value="1">

            <!-- Site Name -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="site_name" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên nền tảng (Site Name) *</label>
                <input type="text" name="site_name" id="site_name" class="form-input" placeholder="Nhập tên nền tảng..." required 
                       value="<?php echo htmlspecialchars($site_name); ?>">
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
                    <?php if (!empty($last_updated)): ?>
                        Cập nhật gần nhất: <strong><?php echo date('d/m/Y H:i', strtotime($last_updated)); ?></strong>. Thay đổi sẽ có hiệu lực trực tiếp và đồng bộ trên toàn hệ thống ngay lập tức.
                    <?php else: ?>
                        Chưa từng thay đổi tên nền tảng. Thay đổi sẽ có hiệu lực trực tiếp và đồng bộ ngay lập tức.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Logo Upload -->
            <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <label for="logo" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Logo ảnh nền tảng</label>
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 8px; border-radius: var(--radius-sm); min-height: 48px; display: flex; align-items: center; justify-content: center; width: 140px; box-sizing: border-box;">
                        <?php if (!empty($site_logo)): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_logo); ?>" style="max-height: 32px; max-width: 120px; object-fit: contain;">
                        <?php else: ?>
                            <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Chưa có logo ảnh</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo" id="logo" accept="image/*" class="form-input" style="padding: 6px;">
                </div>
                <div style="font-size: 11px; color: var(--text-muted);">Định dạng hỗ trợ: JPG, PNG, SVG, WEBP. Hiển thị thay thế icon lông vũ mặc định.</div>
            </div>

            <!-- Favicon Upload -->
            <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <label for="favicon" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Favicon (.ico, .png)</label>
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 8px; border-radius: var(--radius-sm); height: 48px; width: 48px; display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
                        <?php if (!empty($site_favicon)): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($site_favicon); ?>" style="height: 24px; width: 24px; object-fit: contain;">
                        <?php else: ?>
                            <i class="fa-regular fa-image" style="font-size: 18px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="favicon" id="favicon" accept="image/x-icon,image/png,image/jpeg" class="form-input" style="padding: 6px;">
                </div>
                <div style="font-size: 11px; color: var(--text-muted);">Biểu tượng nhỏ hiển thị trên tab trình duyệt (.ico, .png, .jpg).</div>
            </div>

            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px; width: auto; padding: 0 32px;">
                <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi
            </button>
        </form>
    </div>

    <?php if ($has_pending_request): ?>
    <!-- Pending Site Name Requests Area -->
    <div class="data-table-container" style="margin-bottom: 0; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; color: var(--text-primary);">
                <i class="fa-solid fa-user-check" style="color: var(--accent-primary); margin-right: 6px;"></i> Phê duyệt đổi tên hệ thống
            </h3>
        </div>

        <div style="padding: 24px;">
            <div style="background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--accent-primary); padding: 20px; border-radius: var(--radius-sm); text-align: left;">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Yêu cầu thay đổi đang chờ duyệt:</div>
                <div style="font-size: 20px; font-weight: 800; color: var(--accent-primary); margin-top: 6px; margin-bottom: 14px;">
                    <?php echo htmlspecialchars($pending_name); ?>
                </div>
                
                <div style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 20px;">
                    <ul style="padding-left: 18px; margin: 6px 0 0 0; display: flex; flex-direction: column; gap: 4px;">
                        <li>Người yêu cầu: <strong>@<?php echo htmlspecialchars($pending_by); ?></strong></li>
                        <li>Thời gian gửi: <strong><?php echo date('d/m/Y H:i', strtotime($pending_at)); ?></strong></li>
                    </ul>
                </div>

                <div style="display: flex; gap: 12px;">
                    <a href="system_settings.php?approve_name=1" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 700; background: var(--success); height: 36px; border-radius: var(--radius-full);">
                        <i class="fa-solid fa-check"></i> Phê duyệt ngay
                    </a>
                    <a href="system_settings.php?reject_name=1" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 700; background: var(--danger); height: 36px; border-radius: var(--radius-full);" onclick="return confirm('Bạn chắc chắn muốn từ chối yêu cầu đổi tên này?');">
                        <i class="fa-solid fa-xmark"></i> Từ chối
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 40px;">
    <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
        <i class="fa-solid fa-certificate" style="color: var(--accent-primary); margin-right: 6px;"></i> Cấu hình Tích xác minh & Tính minh bạch (Verification & Transparency)
    </h3>
    
    <form action="" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="action_save_verification" value="1">
        
        <!-- 1. Huy hiệu đã xác minh (Xanh dương) -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px; align-items: start;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="verified_official_title" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tiêu đề tích chính chủ *</label>
                <input type="text" name="verified_official_title" id="verified_official_title" class="form-input" required 
                       value="<?php echo htmlspecialchars($verified_official_title); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="verified_official_desc" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Nội dung giải thích tích chính chủ *</label>
                <textarea name="verified_official_desc" id="verified_official_desc" class="form-input" required style="resize: vertical; height: 60px; padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;"><?php echo htmlspecialchars($verified_official_desc); ?></textarea>
            </div>
        </div>

        <!-- 2. Frest đã xác minh (Trả phí - Xanh dương) -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px; align-items: start; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="verified_subscribed_title" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tiêu đề tích trả phí *</label>
                <input type="text" name="verified_subscribed_title" id="verified_subscribed_title" class="form-input" required 
                       value="<?php echo htmlspecialchars($verified_subscribed_title); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="verified_subscribed_desc" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Nội dung giải thích tích trả phí *</label>
                <textarea name="verified_subscribed_desc" id="verified_subscribed_desc" class="form-input" required style="resize: vertical; height: 60px; padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;"><?php echo htmlspecialchars($verified_subscribed_desc); ?></textarea>
            </div>
        </div>

        <!-- 3. Liên kết chuyển hướng (Tìm hiểu thêm) -->
        <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <label for="verified_learn_more_url" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Liên kết chuyển hướng (Tìm hiểu thêm URL)</label>
            <input type="url" name="verified_learn_more_url" id="verified_learn_more_url" class="form-input" placeholder="Ví dụ: https://... hoặc để trống để ẩn nút" 
                   value="<?php echo htmlspecialchars($verified_learn_more_url); ?>">
        </div>

        <!-- 4. Đồng bộ Trạng thái trang cá nhân -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="transparency_user_updated_text" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Trạng thái cập nhật thông tin cá nhân *</label>
                <input type="text" name="transparency_user_updated_text" id="transparency_user_updated_text" class="form-input" required 
                       value="<?php echo htmlspecialchars($transparency_user_updated_text); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="transparency_page_updated_text" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Trạng thái cập nhật thông tin trang *</label>
                <input type="text" name="transparency_page_updated_text" id="transparency_page_updated_text" class="form-input" required 
                       value="<?php echo htmlspecialchars($transparency_page_updated_text); ?>">
            </div>
        </div>

        <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px; width: auto; padding: 0 32px; align-self: start;">
            <i class="fa-solid fa-floppy-disk"></i> Lưu cấu hình xác minh
        </button>
    </form>
</div>

<div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 40px;">
    <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
        <i class="fa-solid fa-shoe-prints" style="color: var(--accent-primary); margin-right: 6px; transform: rotate(-90deg);"></i> Cấu hình chân trang (Footer Settings)
    </h3>
    
    <form action="" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="action_save_footer" value="1">
        
        <!-- Footer Copyright -->
        <div class="form-group" style="margin-bottom: 0;">
            <label for="footer_copyright" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Thông tin bản quyền (Copyright text) *</label>
            <input type="text" name="footer_copyright" id="footer_copyright" class="form-input" placeholder="Ví dụ: © 2026 Frest App. Được thiết kế tối giản..." required 
                   value="<?php echo htmlspecialchars($footer_copyright); ?>">
        </div>
        
        <!-- Footer Links Manager -->
        <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 0;">Các liên kết chân trang (Footer Links)</label>
                <button type="button" class="btn-primary" onclick="addFooterLinkRow();" style="font-size: 12px; height: 32px; padding: 0 16px; border-radius: var(--radius-full); width: auto; background: var(--accent-gradient); border: none; font-weight: 700;">
                    <i class="fa-solid fa-plus" style="margin-right: 4px;"></i> Thêm liên kết
                </button>
            </div>
            
            <div id="footer-links-list">
                <!-- Dynamically generated rows -->
            </div>
            
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 12px; line-height: 1.5; background: rgba(255,255,255,0.02); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <i class="fa-solid fa-circle-info" style="color: var(--accent-primary); margin-right: 4px;"></i> 
                <strong>Hướng dẫn biểu tượng (Icon):</strong> Nhập class FontAwesome của biểu tượng mong muốn. Ví dụ:<br>
                • Facebook: <code>fa-brands fa-facebook-f</code><br>
                • X (Twitter): <code>fa-brands fa-x-twitter</code><br>
                • Instagram: <code>fa-brands fa-instagram</code><br>
                • Threads: <code>fa-brands fa-threads</code><br>
                • GitHub: <code>fa-brands fa-github</code><br>
                • LinkedIn: <code>fa-brands fa-linkedin-in</code>
            </div>
        </div>
        
        <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px; width: auto; padding: 0 32px; align-self: start;">
            <i class="fa-solid fa-floppy-disk"></i> Lưu cấu hình chân trang
        </button>
    </form>
</div>

<!-- Footer links dynamic JS handler -->
<script>
function addFooterLinkRow(title = '', url = '', type = 'external', icon = '') {
    const container = document.getElementById('footer-links-list');
    const rowId = 'link-row-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    
    const rowHtml = `
        <div class="link-row" id="${rowId}" style="display: grid; grid-template-columns: 1fr 1.2fr 1fr 1fr auto; gap: 12px; align-items: center; background: rgba(255,255,255,0.015); border: 1px solid var(--border-color); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px;">
            <div>
                <label class="form-label" style="font-size: 11px; margin-bottom: 4px;">Tiêu đề</label>
                <input type="text" name="link_title[]" value="${escapeHtml(title)}" class="form-input" placeholder="Ví dụ: Facebook, X, Điều khoản..." required style="padding: 8px;">
            </div>
            <div>
                <label class="form-label" style="font-size: 11px; margin-bottom: 4px;">Đường dẫn (URL)</label>
                <input type="text" name="link_url[]" value="${escapeHtml(url)}" class="form-input" placeholder="Ví dụ: https://... hoặc #" required style="padding: 8px;">
            </div>
            <div>
                <label class="form-label" style="font-size: 11px; margin-bottom: 4px;">Loại liên kết</label>
                <select name="link_type[]" class="form-input" style="padding: 8px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); width: 100%;">
                    <option value="external" ${type === 'external' ? 'selected' : ''}>Liên kết ngoài (Social, v.v.)</option>
                    <option value="terms_of_service" ${type === 'terms_of_service' ? 'selected' : ''}>Điều khoản sử dụng (Modal)</option>
                    <option value="privacy_policy" ${type === 'privacy_policy' ? 'selected' : ''}>Chính sách bảo mật (Modal)</option>
                    <option value="copyright_complaint" ${type === 'copyright_complaint' ? 'selected' : ''}>Khiếu nại bản quyền (Modal)</option>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size: 11px; margin-bottom: 4px;">Icon FontAwesome</label>
                <input type="text" name="link_icon[]" value="${escapeHtml(icon)}" class="form-input" placeholder="Ví dụ: fa-brands fa-facebook-f" style="padding: 8px;">
            </div>
            <div style="align-self: end; padding-bottom: 2px;">
                <button type="button" class="btn-primary" onclick="document.getElementById('${rowId}').remove();" style="background: rgba(239, 68, 68, 0.12); border: 1px solid var(--danger); color: var(--danger); width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; cursor: pointer;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    const initialLinks = <?php echo json_encode($footer_links); ?>;
    if (initialLinks && initialLinks.length > 0) {
        initialLinks.forEach(link => {
            addFooterLinkRow(link.title || '', link.url || '', link.type || 'external', link.icon || '');
        });
    }
});
</script>

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start; margin-bottom: 40px;">
    <!-- PWA Settings Card -->
    <div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
            <i class="fa-solid fa-mobile-screen-button" style="color: var(--accent-primary); margin-right: 6px;"></i> Thiết lập PWA (Progressive Web App)
        </h3>
        
        <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action_save_pwa" value="1">
            
            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; background: rgba(255,255,255,0.01); border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm);">
                <div>
                    <span style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); display: block;">Kích hoạt PWA</span>
                    <span style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 2px;">Cho phép người dùng cài đặt ứng dụng Frest trực tiếp vào điện thoại & máy tính.</span>
                </div>
                <div class="switch-container" style="position: relative; display: inline-block; width: 44px; height: 24px; margin-bottom: 0; flex-shrink: 0;">
                    <input type="checkbox" name="pwa_enabled" value="1" <?php echo $pwa_enabled === '1' ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                    <span class="switch-slider"></span>
                </div>
            </label>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="pwa_name" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên ứng dụng (App Name) *</label>
                <input type="text" name="pwa_name" id="pwa_name" class="form-input" placeholder="Ví dụ: Frest App - Mạng xã hội" required 
                       value="<?php echo htmlspecialchars($pwa_name); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="pwa_short_name" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Tên viết tắt (Short Name) *</label>
                <input type="text" name="pwa_short_name" id="pwa_short_name" class="form-input" placeholder="Ví dụ: Frest" required 
                       value="<?php echo htmlspecialchars($pwa_short_name); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="pwa_description" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Mô tả ngắn của App *</label>
                <textarea name="pwa_description" id="pwa_description" class="form-input" placeholder="Nhập mô tả cho PWA..." required style="resize: vertical; height: 80px;"><?php echo htmlspecialchars($pwa_description); ?></textarea>
            </div>

            <!-- PWA Icon Upload -->
            <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <label for="pwa_icon" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Biểu tượng PWA (PWA Icon - PNG, 512x512)</label>
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 8px; border-radius: var(--radius-sm); height: 48px; width: 48px; display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
                        <?php 
                        $pwa_icon_val = getSystemSetting('pwa_icon', '');
                        if (!empty($pwa_icon_val)): 
                        ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/system/<?php echo sanitize($pwa_icon_val); ?>_192.png" style="height: 32px; width: 32px; object-fit: contain;">
                        <?php else: ?>
                            <img src="<?php echo SITE_URL; ?>/assets/images/icons/icon-192x192.png" style="height: 32px; width: 32px; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="pwa_icon" id="pwa_icon" accept="image/png" class="form-input" style="padding: 6px;">
                </div>
                <div style="font-size: 11px; color: var(--text-muted);">Biểu tượng ứng dụng hiển thị trên màn hình điện thoại/máy tính (Định dạng PNG, khuyên dùng kích thước 512x512px).</div>
            </div>

            <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <label for="pwa_version" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Phiên bản Cache PWA (Version) *</label>
                <input type="text" name="pwa_version" id="pwa_version" class="form-input" placeholder="Ví dụ: 1.0.0, 1.0.1..." required 
                       value="<?php echo htmlspecialchars($pwa_version); ?>">
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Nâng phiên bản này (ví dụ từ 1.0.0 lên 1.0.1) khi bạn cập nhật mã nguồn CSS/JS để bắt buộc trình duyệt của người dùng cập nhật cache Service Worker mới.</div>
            </div>

            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px; width: auto; padding: 0 32px; align-self: start;">
                <i class="fa-solid fa-floppy-disk"></i> Lưu cấu hình PWA
            </button>
        </form>
    </div>

    <!-- Maintenance Settings Card -->
    <div class="checkout-card" style="padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <h3 style="font-family: var(--font-heading); font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
            <i class="fa-solid fa-screwdrivers-wrench" style="color: var(--accent-primary); margin-right: 6px;"></i> Chế độ bảo trì hệ thống (Maintenance Mode)
        </h3>
        
        <form action="" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action_save_maintenance" value="1">
            
            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; background: rgba(255,255,255,0.01); border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm);">
                <div>
                    <span style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); display: block;">Bật chế độ bảo trì</span>
                    <span style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 2px;">Khi kích hoạt, người dùng thường không thể truy cập, chỉ Admin có quyền vào.</span>
                </div>
                <div class="switch-container" style="position: relative; display: inline-block; width: 44px; height: 24px; margin-bottom: 0; flex-shrink: 0;">
                    <input type="checkbox" name="maintenance_enabled" value="1" <?php echo $maintenance_enabled === '1' ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                    <span class="switch-slider"></span>
                </div>
            </label>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="maintenance_message" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Thông báo bảo trì *</label>
                <textarea name="maintenance_message" id="maintenance_message" class="form-input" placeholder="Nhập thông báo bảo trì hiển thị ngoài trang chủ..." required style="resize: vertical; height: 120px;"><?php echo htmlspecialchars($maintenance_message); ?></textarea>
            </div>

            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 40px; margin-top: 8px; width: auto; padding: 0 32px; align-self: start;">
                <i class="fa-solid fa-floppy-disk"></i> Lưu cấu hình bảo trì
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
