<?php
/**
 * Database Upgrade & Migration Script - Frest App
 * Run this script ONCE to initialize or upgrade the database schema.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo '<body style="background: #121214; color: #e1e1e6; font-family: sans-serif; padding: 40px; line-height: 1.6;">';
echo '<h2 style="color: #3b82f6;">Hệ thống nâng cấp & Di trú Cơ sở dữ liệu Frest</h2>';
echo '<hr style="border: 0; border-top: 1px solid #29292e; margin-bottom: 20px;">';

try {
    // 1. Connect without dbname to ensure the database exists
    echo "[1/4] Đang kết nối tới MySQL Server tại " . DB_HOST . "...<br>";
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[2/4] Đang kiểm tra/Tạo cơ sở dữ liệu `" . DB_NAME . "`...<br>";
    $conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. Connect directly to the database
    echo "[3/4] Đang kết nối trực tiếp vào cơ sở dữ liệu `" . DB_NAME . "`...<br>";
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "[4/4] Bắt đầu quá trình di trú cấu trúc bảng (Migration)...<br>";
    
    // Auto migration for users table
    $table_exists = $conn->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
    if ($table_exists) {
        $user_columns = [
            'dob' => 'DATE DEFAULT NULL',
            'id_proof_filename' => 'VARCHAR(255) DEFAULT NULL',
            'age_verification_status' => "VARCHAR(30) DEFAULT 'unverified'",
            'is_adult' => 'TINYINT(1) DEFAULT 0',
            'show_nsfw' => 'TINYINT(1) DEFAULT 0',
            'reset_token' => 'VARCHAR(255) DEFAULT NULL',
            'reset_token_expires' => 'DATETIME DEFAULT NULL',
            'first_name' => "VARCHAR(50) DEFAULT ''",
            'middle_name' => "VARCHAR(50) DEFAULT ''",
            'last_name' => "VARCHAR(50) DEFAULT ''",
            'name_display_order' => "VARCHAR(30) DEFAULT 'last_middle_first'",
            'display_name_last_updated' => 'DATETIME DEFAULT NULL',
            'username_last_updated' => 'DATETIME DEFAULT NULL',
            'pending_first_name' => 'VARCHAR(50) DEFAULT NULL',
            'pending_middle_name' => 'VARCHAR(50) DEFAULT NULL',
            'pending_last_name' => 'VARCHAR(50) DEFAULT NULL',
            'pending_name_display_order' => 'VARCHAR(30) DEFAULT NULL',
            'name_change_status' => "VARCHAR(20) DEFAULT 'none'",
            'phone_number' => 'VARCHAR(30) DEFAULT NULL',
            'show_email' => 'TINYINT(1) DEFAULT 1',
            'show_phone' => 'TINYINT(1) DEFAULT 1',
            'show_gender' => 'TINYINT(1) DEFAULT 1',
            'show_workplace' => 'TINYINT(1) DEFAULT 1',
            'show_lives_at' => 'TINYINT(1) DEFAULT 1',
            'show_country' => 'TINYINT(1) DEFAULT 1',
            'show_dob' => 'TINYINT(1) DEFAULT 1',
            'phone_verified' => 'TINYINT(1) DEFAULT 1',
            'phone_verification_code' => 'VARCHAR(10) DEFAULT NULL',
            'gender' => 'VARCHAR(30) DEFAULT NULL',
            'workplace' => 'VARCHAR(255) DEFAULT NULL',
            'is_page' => 'TINYINT(1) DEFAULT 0',
            'page_category' => 'VARCHAR(100) DEFAULT NULL',
            'status' => "VARCHAR(30) DEFAULT 'active'",
            'status_reason' => 'TEXT DEFAULT NULL',
            'lock_until' => 'DATETIME DEFAULT NULL',
            'lives_at' => 'VARCHAR(255) DEFAULT NULL',
            'country' => 'VARCHAR(100) DEFAULT NULL',
            'profile_updated_at' => 'DATETIME DEFAULT NULL',
            'sync_transparency_status' => 'TINYINT(1) DEFAULT 1'
        ];
        
        $run_name_split = false;
        foreach ($user_columns as $col => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'")->rowCount() > 0;
            if (!$check) {
                $conn->exec("ALTER TABLE users ADD COLUMN `$col` $definition");
                echo "-> Đã thêm cột `$col` vào bảng `users`.<br>";
                if (in_array($col, ['first_name', 'middle_name', 'last_name'])) {
                    $run_name_split = true;
                }
            }
        }

        if ($run_name_split) {
            // Split full_name for existing users
            $stmt = $conn->query("SELECT id, username, full_name FROM users");
            $existing_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($existing_users as $u) {
                $name = trim($u['full_name'] ?? '');
                if (empty($name)) {
                    $name = trim($u['username'] ?? '');
                }
                $parts = preg_split('/\s+/', $name);
                $count = count($parts);
                
                $last = '';
                $mid = '';
                $first = '';
                
                if ($count === 1) {
                    $first = $parts[0];
                } elseif ($count === 2) {
                    $last = $parts[0];
                    $first = $parts[1];
                } elseif ($count === 3) {
                    $last = $parts[0];
                    $mid = $parts[1];
                    $first = $parts[2];
                } elseif ($count > 3) {
                    $last = $parts[0];
                    $first = $parts[$count - 1];
                    $mid = implode(' ', array_slice($parts, 1, $count - 2));
                }
                
                $up_stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, name_display_order = 'last_middle_first' WHERE id = ?");
                $up_stmt->execute([$first, $mid, $last, $u['id']]);
            }
            echo "-> Đã chia tách dữ liệu tên đầy đủ (full_name) thành họ, tên đệm và tên.<br>";
        }
    } else {
        echo "<span style='color: #ef4444;'>[Lỗi] Không tìm thấy bảng `users`. Bạn cần khởi tạo DB trước bằng db_init.php</span><br>";
        exit;
    }

    // Create pages table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        page_name VARCHAR(100) NOT NULL,
        page_username VARCHAR(100) UNIQUE NOT NULL,
        avatar_filename VARCHAR(255) DEFAULT 'avatar_default.png',
        cover_filename VARCHAR(255) DEFAULT NULL,
        bio VARCHAR(255) DEFAULT '',
        category VARCHAR(100) DEFAULT 'Cộng đồng',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `pages` thành công.<br>";

    // Create page_follows table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS page_follows (
        user_id INT NOT NULL,
        page_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, page_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `page_follows` thành công.<br>";

    // Auto-migrate pages (is_verified)
    $check_page_verified = $conn->query("SHOW COLUMNS FROM pages LIKE 'is_verified'")->rowCount() > 0;
    if (!$check_page_verified) {
        $conn->exec("ALTER TABLE pages ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0");
        echo "-> Đã thêm cột `is_verified` vào bảng `pages`.<br>";
    }

    // Auto-migrate pages (verification_type)
    $check_page_vtype = $conn->query("SHOW COLUMNS FROM pages LIKE 'verification_type'")->rowCount() > 0;
    if (!$check_page_vtype) {
        $conn->exec("ALTER TABLE pages ADD COLUMN `verification_type` VARCHAR(30) DEFAULT NULL");
        echo "-> Đã thêm cột `verification_type` vào bảng `pages`.<br>";
    }

    // Auto-migrate replies (parent_reply_id)
    $check_parent_reply = $conn->query("SHOW COLUMNS FROM replies LIKE 'parent_reply_id'")->rowCount() > 0;
    if (!$check_parent_reply) {
        $conn->exec("ALTER TABLE replies ADD COLUMN `parent_reply_id` INT DEFAULT NULL");
        echo "-> Đã thêm cột `parent_reply_id` vào bảng `replies`.<br>";
        try {
            $conn->exec("ALTER TABLE replies ADD CONSTRAINT fk_reply_parent FOREIGN KEY (parent_reply_id) REFERENCES replies(id) ON DELETE CASCADE");
            echo "-> Đã tạo liên kết khóa ngoại cho `parent_reply_id`.<br>";
        } catch (PDOException $e) {
            echo "-> Không tạo được liên kết khóa ngoại: " . $e->getMessage() . "<br>";
        }
    }

    // Auto-migrate reactions (reply_id, auto-increment PK)
    $check_reactions_id = $conn->query("SHOW COLUMNS FROM reactions LIKE 'id'")->rowCount() > 0;
    if (!$check_reactions_id) {
        try {
            $conn->exec("ALTER TABLE reactions DROP FOREIGN KEY reactions_ibfk_1");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE reactions DROP FOREIGN KEY reactions_ibfk_2");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE reactions DROP PRIMARY KEY");
        } catch (PDOException $e) {}
        
        $conn->exec("ALTER TABLE reactions ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY");
        echo "-> Đã cập nhật khóa chính tự tăng `id` cho bảng `reactions`.<br>";
        
        try {
            $conn->exec("ALTER TABLE reactions ADD CONSTRAINT fk_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE reactions ADD CONSTRAINT fk_reactions_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE");
        } catch (PDOException $e) {}
    }
    $check_reactions_reply = $conn->query("SHOW COLUMNS FROM reactions LIKE 'reply_id'")->rowCount() > 0;
    if (!$check_reactions_reply) {
        $conn->exec("ALTER TABLE reactions ADD COLUMN `reply_id` INT DEFAULT NULL");
        echo "-> Đã thêm cột `reply_id` vào bảng `reactions`.<br>";
        try {
            $conn->exec("ALTER TABLE reactions ADD CONSTRAINT fk_reaction_reply FOREIGN KEY (reply_id) REFERENCES replies(id) ON DELETE CASCADE");
            echo "-> Đã tạo liên kết khóa ngoại cho `reply_id` trong `reactions`.<br>";
        } catch (PDOException $e) {}
    }
    $col_post_id = $conn->query("SHOW COLUMNS FROM reactions LIKE 'post_id'")->fetch();
    if ($col_post_id && strtolower($col_post_id['Null']) === 'no') {
        $conn->exec("ALTER TABLE reactions MODIFY COLUMN `post_id` INT NULL");
        echo "-> Đã cập nhật cột `post_id` có thể chứa giá trị NULL trong bảng `reactions`.<br>";
    }

    $posts_table_exists = $conn->query("SHOW TABLES LIKE 'posts'")->rowCount() > 0;
    if ($posts_table_exists) {
        // Check if image_filename needs to be upgraded to TEXT
        $col_info = $conn->query("SHOW COLUMNS FROM posts LIKE 'image_filename'")->fetch();
        if ($col_info && strpos(strtolower($col_info['Type']), 'text') === false) {
            $conn->exec("ALTER TABLE posts MODIFY COLUMN image_filename TEXT DEFAULT NULL");
            echo "-> Đã cập nhật kiểu dữ liệu cột `image_filename` sang TEXT trong bảng `posts`.<br>";
        }

        $posts_columns = [
            'audio_filename' => 'VARCHAR(255) DEFAULT NULL',
            'document_filename' => 'VARCHAR(255) DEFAULT NULL',
            'software_filename' => 'VARCHAR(255) DEFAULT NULL',
            'repost_of_post_id' => 'INT DEFAULT NULL',
            'page_id' => 'INT DEFAULT NULL',
            'is_pinned' => 'TINYINT(1) DEFAULT 0',
            'is_copyright_violation' => 'TINYINT(1) DEFAULT 0',
            'copyright_owner' => 'VARCHAR(255) DEFAULT NULL',
            'copyright_details' => 'TEXT DEFAULT NULL',
            'post_token' => 'VARCHAR(32) UNIQUE DEFAULT NULL'
        ];
        
        foreach ($posts_columns as $col => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM posts LIKE '$col'")->rowCount() > 0;
            if (!$check) {
                $conn->exec("ALTER TABLE posts ADD COLUMN `$col` $definition");
                echo "-> Đã thêm cột `$col` vào bảng `posts`.<br>";
                if ($col === 'repost_of_post_id') {
                    try {
                        $conn->exec("ALTER TABLE posts ADD CONSTRAINT fk_repost FOREIGN KEY (repost_of_post_id) REFERENCES posts(id) ON DELETE CASCADE");
                        echo "-> Đã tạo liên kết khóa ngoại cho `repost_of_post_id` trong `posts`.<br>";
                    } catch (PDOException $e) {}
                }
                if ($col === 'page_id') {
                    try {
                        $conn->exec("ALTER TABLE posts ADD CONSTRAINT fk_post_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE");
                        echo "-> Đã tạo liên kết khóa ngoại cho `page_id` trong `posts`.<br>";
                    } catch (PDOException $e) {}
                }
            }
        }

        // Tự động sinh mã token ngẫu nhiên cho các bài viết cũ chưa có token
        $stmt_empty = $conn->query("SELECT id FROM posts WHERE post_token IS NULL");
        $posts_without_token = $stmt_empty->fetchAll();
        if (count($posts_without_token) > 0) {
            $stmt_upd = $conn->prepare("UPDATE posts SET post_token = ? WHERE id = ?");
            foreach ($posts_without_token as $p) {
                $token = bin2hex(random_bytes(8)); // Sinh token 16 ký tự ngẫu nhiên
                try {
                    $stmt_upd->execute([$token, $p['id']]);
                } catch (Exception $e) {}
            }
            echo "-> Đã tạo mã định danh token ngẫu nhiên cho " . count($posts_without_token) . " bài viết cũ.<br>";
        }
    }

    // Migrate replies table
    $replies_table_exists = $conn->query("SHOW TABLES LIKE 'replies'")->rowCount() > 0;
    if ($replies_table_exists) {
        $check_reply_page = $conn->query("SHOW COLUMNS FROM replies LIKE 'page_id'")->rowCount() > 0;
        if (!$check_reply_page) {
            $conn->exec("ALTER TABLE replies ADD COLUMN `page_id` INT DEFAULT NULL");
            echo "-> Đã thêm cột `page_id` vào bảng `replies`.<br>";
            try {
                $conn->exec("ALTER TABLE replies ADD CONSTRAINT fk_reply_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE");
                echo "-> Đã tạo liên kết khóa ngoại cho `page_id` trong `replies`.<br>";
            } catch (PDOException $e) {}
        }
    }

    // Create notifications table for real-time notification system
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NOT NULL,
        actor_id      INT NOT NULL,
        type          VARCHAR(30) NOT NULL,
        ref_post_id   INT DEFAULT NULL,
        ref_reply_id  INT DEFAULT NULL,
        detail        VARCHAR(255) DEFAULT NULL,
        is_read       TINYINT(1) DEFAULT 0,
        is_dismissed  TINYINT(1) DEFAULT 0,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `notifications` thành công.<br>";

    // Ensure index exists for fast unread-count lookup
    try {
        $conn->exec("CREATE INDEX idx_notif_user ON notifications (user_id, is_dismissed, created_at)");
        echo "-> Tạo chỉ mục `idx_notif_user` cho bảng `notifications` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_notif_user` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_notif_unread ON notifications (user_id, is_dismissed, is_read)");
        echo "-> Tạo chỉ mục `idx_notif_unread` cho bảng `notifications` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_notif_unread` đã tồn tại hoặc không tạo được.<br>";
    }

    // Performance Optimization Indexes
    try {
        $conn->exec("CREATE INDEX idx_users_phone ON users (phone_number)");
        echo "-> Tạo chỉ mục `idx_users_phone` cho bảng `users` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_users_phone` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_msg_sender_receiver ON messages (sender_id, receiver_id)");
        echo "-> Tạo chỉ mục `idx_msg_sender_receiver` cho bảng `messages` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_msg_sender_receiver` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_msg_receiver_sender ON messages (receiver_id, sender_id)");
        echo "-> Tạo chỉ mục `idx_msg_receiver_sender` cho bảng `messages` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_msg_receiver_sender` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_reaction_user_post ON reactions (user_id, post_id)");
        echo "-> Tạo chỉ mục `idx_reaction_user_post` cho bảng `reactions` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_reaction_user_post` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_reaction_user_reply ON reactions (user_id, reply_id)");
        echo "-> Tạo chỉ mục `idx_reaction_user_reply` cho bảng `reactions` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_reaction_user_reply` đã tồn tại hoặc không tạo được.<br>";
    }

    try {
        $conn->exec("CREATE INDEX idx_posts_pinned_created ON posts (is_pinned, created_at)");
        echo "-> Tạo chỉ mục `idx_posts_pinned_created` cho bảng `posts` thành công.<br>";
    } catch (PDOException $e) {
        echo "-> Chỉ mục `idx_posts_pinned_created` đã tồn tại hoặc không tạo được.<br>";
    }

    // Auto-migrate users for new social columns
    $user_social_columns = [
        'website' => 'VARCHAR(255) DEFAULT NULL',
        'facebook_link' => 'VARCHAR(255) DEFAULT NULL',
        'instagram_link' => 'VARCHAR(255) DEFAULT NULL',
        'twitter_link' => 'VARCHAR(255) DEFAULT NULL'
    ];
    foreach ($user_social_columns as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'")->rowCount() > 0;
        if (!$check) {
            $conn->exec("ALTER TABLE users ADD COLUMN `$col` $definition");
            echo "-> Đã thêm cột `$col` vào bảng `users`.<br>";
        }
    }

    // Auto-migrate pages for new contact and social columns
    $page_new_columns = [
        'website'           => 'VARCHAR(255) DEFAULT NULL',
        'email'             => 'VARCHAR(255) DEFAULT NULL',
        'phone_number'      => 'VARCHAR(30) DEFAULT NULL',
        'facebook_link'     => 'VARCHAR(255) DEFAULT NULL',
        'instagram_link'    => 'VARCHAR(255) DEFAULT NULL',
        'twitter_link'      => 'VARCHAR(255) DEFAULT NULL',
        'lives_at'          => 'VARCHAR(255) DEFAULT NULL',
        'country'           => 'VARCHAR(100) DEFAULT NULL',
        // New detailed info columns
        'working_hours'     => 'VARCHAR(255) DEFAULT NULL',
        'services'          => 'VARCHAR(255) DEFAULT NULL',
        'founded_at'        => 'DATE DEFAULT NULL',
        // Privacy / visibility toggles (default 1 = show publicly)
        'show_email'          => 'TINYINT(1) DEFAULT 1',
        'show_phone'          => 'TINYINT(1) DEFAULT 1',
        'show_website'        => 'TINYINT(1) DEFAULT 1',
        'show_lives_at'       => 'TINYINT(1) DEFAULT 1',
        'show_country'        => 'TINYINT(1) DEFAULT 1',
        'show_socials'        => 'TINYINT(1) DEFAULT 1',
        'show_working_hours'  => 'TINYINT(1) DEFAULT 1',
        'show_services'       => 'TINYINT(1) DEFAULT 1',
        'show_founded_at'     => 'TINYINT(1) DEFAULT 1',
        'updated_at'          => 'DATETIME DEFAULT NULL',
        'sync_transparency_status' => 'TINYINT(1) DEFAULT 1'
    ];
    foreach ($page_new_columns as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM pages LIKE '$col'")->rowCount() > 0;
        if (!$check) {
            $conn->exec("ALTER TABLE pages ADD COLUMN `$col` $definition");
            echo "-> Đã thêm cột `$col` vào bảng `pages`.<br>";
        }
    }

    // Create blocks table for blocking system
    $conn->exec("CREATE TABLE IF NOT EXISTS blocks (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        blocker_type  VARCHAR(20) NOT NULL,
        blocker_id    INT NOT NULL,
        blocked_type  VARCHAR(20) NOT NULL,
        blocked_id    INT NOT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_block (blocker_type, blocker_id, blocked_type, blocked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `blocks` thành công.<br>";

    // Create reports table for reporting system
    $conn->exec("CREATE TABLE IF NOT EXISTS reports (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        reporter_type VARCHAR(20) NOT NULL,
        reporter_id   INT NOT NULL,
        target_type   VARCHAR(30) NOT NULL,
        target_id     INT NOT NULL,
        reason        VARCHAR(100) NOT NULL,
        details       TEXT DEFAULT NULL,
        status        VARCHAR(30) DEFAULT 'pending',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at   TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `reports` thành công.<br>";

    // Create copyright_complaints table for copyright claims
    $conn->exec("CREATE TABLE IF NOT EXISTS copyright_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_name VARCHAR(100) NOT NULL,
        reporter_email VARCHAR(100) NOT NULL,
        reporter_phone VARCHAR(30) DEFAULT NULL,
        post_url VARCHAR(2048) NOT NULL,
        description TEXT NOT NULL,
        evidence_filename VARCHAR(255) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `copyright_complaints` thành công.<br>";

    // Create login_history table for tracking logins and detecting anomalies
    $conn->exec("CREATE TABLE IF NOT EXISTS login_history (
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
    echo "-> Kiểm tra/Tạo bảng `login_history` thành công.<br>";

    // Create chat_groups table
    $conn->exec("CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        avatar_filename VARCHAR(255) DEFAULT 'group_default.png',
        description TEXT DEFAULT NULL,
        creator_type VARCHAR(20) NOT NULL,
        creator_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `chat_groups` thành công.<br>";

    // Create chat_group_members table
    $conn->exec("CREATE TABLE IF NOT EXISTS chat_group_members (
        group_id INT NOT NULL,
        member_type VARCHAR(20) NOT NULL,
        member_id INT NOT NULL,
        role VARCHAR(30) DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, member_type, member_id),
        FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `chat_group_members` thành công.<br>";

    // Create messages table
    $conn->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_type VARCHAR(20) NOT NULL,
        sender_id INT NOT NULL,
        receiver_type VARCHAR(20) NOT NULL,
        receiver_id INT NOT NULL,
        message_text TEXT DEFAULT NULL,
        image_filename VARCHAR(255) DEFAULT NULL,
        video_filename VARCHAR(255) DEFAULT NULL,
        audio_filename VARCHAR(255) DEFAULT NULL,
        document_filename VARCHAR(255) DEFAULT NULL,
        original_filename VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_edited TINYINT(1) DEFAULT 0,
        is_recalled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `messages` thành công.<br>";

    // Create message_reactions table
    $conn->exec("CREATE TABLE IF NOT EXISTS message_reactions (
        message_id INT NOT NULL,
        reactor_type VARCHAR(20) NOT NULL,
        reactor_id INT NOT NULL,
        reaction_emoji VARCHAR(10) NOT NULL,
        PRIMARY KEY (message_id, reactor_type, reactor_id),
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "-> Kiểm tra/Tạo bảng `message_reactions` thành công.<br>";

    // --- Features Upgrade (last_active, hashtags, bookmarks, polls) ---
    // 1. Add last_active column to users table
    $check_last_active = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'")->rowCount() > 0;
    if (!$check_last_active) {
        $conn->exec("ALTER TABLE users ADD COLUMN `last_active` DATETIME DEFAULT NULL");
        echo "-> Đã thêm cột `last_active` vào bảng `users`.<br>";
    }

    // 2. Create hashtags table
    $conn->exec("CREATE TABLE IF NOT EXISTS hashtags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tag VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `hashtags` thành công.<br>";

    // 3. Create post_hashtags table
    $conn->exec("CREATE TABLE IF NOT EXISTS post_hashtags (
        post_id INT NOT NULL,
        hashtag_id INT NOT NULL,
        PRIMARY KEY (post_id, hashtag_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `post_hashtags` thành công.<br>";

    // 4. Create bookmarks table
    $conn->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_bookmark (user_id, post_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `bookmarks` thành công.<br>";

    // 5. Create polls table
    $conn->exec("CREATE TABLE IF NOT EXISTS polls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        question VARCHAR(255) NOT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `polls` thành công.<br>";

    // 6. Create poll_options table
    $conn->exec("CREATE TABLE IF NOT EXISTS poll_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poll_id INT NOT NULL,
        option_text VARCHAR(150) NOT NULL,
        FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `poll_options` thành công.<br>";

    // 7. Create poll_votes table
    $conn->exec("CREATE TABLE IF NOT EXISTS poll_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poll_id INT NOT NULL,
        option_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_poll_vote (poll_id, user_id),
        FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `poll_votes` thành công.<br>";

    // 8. Create name_history table
    $conn->exec("CREATE TABLE IF NOT EXISTS name_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(20) NOT NULL,
        entity_id INT NOT NULL,
        old_name VARCHAR(100) DEFAULT NULL,
        new_name VARCHAR(100) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `name_history` thành công.<br>";

    // Auto populate creation event for users that don't have name_history record
    $check_users = $conn->query("SELECT id, full_name, username, created_at FROM users WHERE id NOT IN (SELECT entity_id FROM name_history WHERE entity_type = 'user')");
    $existing_users = $check_users->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($existing_users)) {
        $ins_stmt = $conn->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name, changed_at) VALUES ('user', ?, NULL, ?, ?)");
        foreach ($existing_users as $u) {
            $name = ($u['full_name'] ?? '') ?: $u['username'];
            $ins_stmt->execute([$u['id'], $name, $u['created_at']]);
        }
        echo "-> Đồng bộ lịch sử tạo tài khoản cho " . count($existing_users) . " người dùng.<br>";
    }

    // Auto populate creation event for pages that don't have name_history record
    $check_pages = $conn->query("SELECT id, page_name, created_at FROM pages WHERE id NOT IN (SELECT entity_id FROM name_history WHERE entity_type = 'page')");
    $existing_pages = $check_pages->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($existing_pages)) {
        $ins_stmt = $conn->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name, changed_at) VALUES ('page', ?, NULL, ?, ?)");
        foreach ($existing_pages as $p) {
            $ins_stmt->execute([$p['id'], $p['page_name'], $p['created_at']]);
        }
        echo "-> Đồng bộ lịch sử tạo trang cho " . count($existing_pages) . " Trang.<br>";
    }

    // Bổ sung các chỉ mục (Indexes) để tăng hiệu năng truy vấn
    $indexes = [
        ['users', 'idx_users_phone', 'phone_number'],
        ['messages', 'idx_msg_sender_receiver', 'sender_id, receiver_id'],
        ['messages', 'idx_msg_receiver_sender', 'receiver_id, sender_id'],
        ['messages', 'idx_msg_sender_type_id', 'sender_type, sender_id'],
        ['messages', 'idx_msg_receiver_type_id_unread', 'receiver_type, receiver_id, is_read'],
        ['messages', 'idx_msg_direct_lookup', 'sender_type, sender_id, receiver_type, receiver_id'],
        ['messages', 'idx_msg_direct_lookup_rev', 'receiver_type, receiver_id, sender_type, sender_id'],
        ['messages', 'idx_msg_receiver_type_id', 'receiver_type, receiver_id'],
        ['reactions', 'idx_reaction_user_post', 'user_id, post_id'],
        ['reactions', 'idx_reaction_user_reply', 'user_id, reply_id'],
        ['posts', 'idx_posts_pinned_created', 'is_pinned, created_at'],
        ['stories', 'idx_stories_expires_id', 'expires_at, id']
    ];
    foreach ($indexes as $idx) {
        list($table, $name, $cols) = $idx;
        try {
            $check = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$name'")->rowCount() > 0;
            if (!$check) {
                $conn->exec("CREATE INDEX `$name` ON `$table` ($cols)");
                echo "-> Đã tạo chỉ mục `$name` trên bảng `$table`.<br>";
            }
        } catch (Exception $ex) {
            echo "-> Bỏ qua/Lỗi tạo chỉ mục `$name` trên bảng `$table`: " . $ex->getMessage() . "<br>";
        }
    }

    // Create stories table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS stories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        media_type VARCHAR(20) NOT NULL,
        media_filename VARCHAR(255) DEFAULT NULL,
        text_content TEXT DEFAULT NULL,
        bg_color VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `stories` thành công.<br>";

    // Auto migration for stories table to ensure columns exist (resolves Column not found errors)
    $stories_table_exists = $conn->query("SHOW TABLES LIKE 'stories'")->rowCount() > 0;
    if ($stories_table_exists) {
        $stories_columns = [
            'media_type' => "VARCHAR(20) NOT NULL DEFAULT 'image'",
            'media_filename' => 'VARCHAR(255) DEFAULT NULL',
            'text_content' => 'TEXT DEFAULT NULL',
            'bg_color' => 'VARCHAR(255) DEFAULT NULL',
            'expires_at' => 'TIMESTAMP NULL DEFAULT NULL'
        ];
        foreach ($stories_columns as $col => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM stories LIKE '$col'")->rowCount() > 0;
            if (!$check) {
                $conn->exec("ALTER TABLE stories ADD COLUMN `$col` $definition");
                echo "-> Đã thêm cột `$col` vào bảng `stories`.<br>";
            }
        }
        
        // Force upgrade bg_color if it exists but is too small (resolves Data too long errors)
        try {
            $conn->exec("ALTER TABLE stories MODIFY COLUMN bg_color VARCHAR(255) DEFAULT NULL");
            echo "-> Đã cập nhật kích thước cột `bg_color` lên VARCHAR(255).<br>";
        } catch (Exception $e) {}
    }

    // Create story_views table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS story_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_story_view (story_id, user_id),
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `story_views` thành công.<br>";

    // Create story_reactions table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS story_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction_type VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_story_react (story_id, user_id),
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `story_reactions` thành công.<br>";

    // Create wiki_moods table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS wiki_moods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content VARCHAR(200) NOT NULL,
        emoji VARCHAR(30) NOT NULL,
        color VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "-> Kiểm tra/Tạo bảng `wiki_moods` thành công.<br>";

    // Đồng bộ biểu tượng thương hiệu
    ob_start();

    require_once __DIR__ . '/sync_icons.php';
    ob_end_clean();
    echo "-> Đồng bộ biểu tượng thương hiệu thành công.<br>";

    // Quét bảo vệ đệ quy toàn bộ thư mục uploads
    require_once __DIR__ . '/includes/functions.php';
    if (function_exists('protectUploadDirectoriesRecursively')) {
        protectUploadDirectoriesRecursively();
        echo "-> Quét bảo vệ toàn bộ thư mục uploads (Directory Listing Prevention) thành công.<br>";
    }

    echo "<br><span style='color: #10b981; font-weight: bold;'>[Thành công] Quá trình nâng cấp & di trú cơ sở dữ liệu đã hoàn tất!</span><br>";
    echo "Bạn có thể đóng trang này và tiếp tục sử dụng hệ thống.";

} catch (PDOException $e) {
    echo "<br><span style='color: #ef4444; font-weight: bold;'>[Lỗi nghiêm trọng] " . $e->getMessage() . "</span><br>";
}
echo '</body>';
