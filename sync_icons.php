<?php
/**
 * Branding Icon Synchronization Utility - Frest App
 * Resizes and synchronizes assets/images/icons/icon.png across the database settings, uploads, and manifest.json.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = getDB();
    $src = __DIR__ . '/assets/images/icons/icon.png';
    if (!file_exists($src)) {
        echo "Error: Source icon.png not found at $src\n";
        exit(1);
    }

    $system_dir = __DIR__ . '/uploads/system/';
    if (!is_dir($system_dir)) {
        mkdir($system_dir, 0777, true);
    }

    // 1. Copy files to standard uploads location
    copy($src, $system_dir . 'site_logo.png');
    copy($src, $system_dir . 'site_favicon.png');

    // 2. Set database keys to point to these new brand assets
    $stmt_logo = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_logo', 'site_logo.png') ON DUPLICATE KEY UPDATE key_value = 'site_logo.png'");
    $stmt_logo->execute();

    $stmt_fav = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('site_favicon', 'site_favicon.png') ON DUPLICATE KEY UPDATE key_value = 'site_favicon.png'");
    $stmt_fav->execute();

    $stmt_pwa = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES ('pwa_icon', 'pwa_icon') ON DUPLICATE KEY UPDATE key_value = 'pwa_icon'");
    $stmt_pwa->execute();

    // Helper function to resize images using GD library with transparency preservation
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

    // 3. Generate system PWA icons
    foreach ($sizes as $s_name => $dim) {
        $resize_helper($src, $system_dir . 'pwa_icon_' . $s_name . '.png', $dim[0], $dim[1]);
    }

    // 4. Generate local assets backup fallback icons
    $icons_dir = __DIR__ . '/assets/images/icons/';
    if (!is_dir($icons_dir)) {
        mkdir($icons_dir, 0777, true);
    }
    $resize_helper($src, $icons_dir . 'icon-72x72.png', 72, 72);
    $resize_helper($src, $icons_dir . 'icon-96x96.png', 96, 96);
    $resize_helper($src, $icons_dir . 'icon-128x128.png', 128, 128);
    $resize_helper($src, $icons_dir . 'icon-144x144.png', 144, 144);
    $resize_helper($src, $icons_dir . 'icon-152x152.png', 152, 152);
    $resize_helper($src, $icons_dir . 'icon-192x192.png', 192, 192);
    $resize_helper($src, $icons_dir . 'icon-384x384.png', 384, 384);
    $resize_helper($src, $icons_dir . 'icon-512x512.png', 512, 512);
    $resize_helper($src, $icons_dir . 'icon-512x512-maskable.png', 512, 512);

    // 5. Generate and rewrite manifest.json to sync PWA configs
    $pwa_name = getSystemSetting('pwa_name', 'Frest App - Split-View Canvas Social Network');
    $pwa_short_name = getSystemSetting('pwa_short_name', 'Frest');
    $pwa_description = getSystemSetting('pwa_description', 'Mạng xã hội tối giản, hiện đại với giao diện Split-View Canvas và trải nghiệm đa phương tiện.');

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
        "icons" => [
            ["src" => "uploads/system/pwa_icon_72.png", "sizes" => "72x72", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_96.png", "sizes" => "96x96", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_144.png", "sizes" => "144x144", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_152.png", "sizes" => "152x152", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_384.png", "sizes" => "384x384", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
            ["src" => "uploads/system/pwa_icon_512_maskable.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
        ]
    ];
    file_put_contents(__DIR__ . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // 6. Regenerate sw.js with updated cached icons
    $sw_file = __DIR__ . '/sw.js';
    if (file_exists($sw_file)) {
        $sw_content = file_get_contents($sw_file);
        $icons_cache_block = "\n  './uploads/system/pwa_icon_72.png',\n" .
                            "  './uploads/system/pwa_icon_96.png',\n" .
                            "  './uploads/system/pwa_icon_128.png',\n" .
                            "  './uploads/system/pwa_icon_144.png',\n" .
                            "  './uploads/system/pwa_icon_152.png',\n" .
                            "  './uploads/system/pwa_icon_192.png',\n" .
                            "  './uploads/system/pwa_icon_384.png',\n" .
                            "  './uploads/system/pwa_icon_512.png',\n" .
                            "  './uploads/system/pwa_icon_512_maskable.png'\n  ";
        $sw_content = preg_replace("/\/\/ PWA_ICONS_START.*?\/\/ PWA_ICONS_END/s", "// PWA_ICONS_START" . $icons_cache_block . "// PWA_ICONS_END", $sw_content);
        file_put_contents($sw_file, $sw_content);
    }

    // Clear system cache in memory so new settings values load immediately
    getSystemSetting(null, '', true);

    echo "Synchronization completed successfully!\n";

} catch (Exception $e) {
    echo "Error during synchronization: " . $e->getMessage() . "\n";
    exit(1);
}
