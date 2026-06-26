<?php
/**
 * Database Initialization Script for Frest App
 * Run this file in your browser or CLI to setup the MySQL database structure and seed data.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    echo "<pre>";
    echo "Connecting to MySQL server successful.\n";
    echo "Database: " . DB_NAME . "\n\n";

    // Disable foreign keys temporarily for clean dropping
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    echo "Dropping existing tables if any... ";
    $db->exec("DROP TABLE IF EXISTS follows;");
    $db->exec("DROP TABLE IF EXISTS page_follows;");
    $db->exec("DROP TABLE IF EXISTS likes;");
    $db->exec("DROP TABLE IF EXISTS reactions;");
    $db->exec("DROP TABLE IF EXISTS replies;");
    $db->exec("DROP TABLE IF EXISTS posts;");
    $db->exec("DROP TABLE IF EXISTS pages;");
    $db->exec("DROP TABLE IF EXISTS users;");
    $db->exec("DROP TABLE IF EXISTS admins;");
    $db->exec("DROP TABLE IF EXISTS settings;");
    $db->exec("DROP TABLE IF EXISTS copyright_complaints;");
    $db->exec("DROP TABLE IF EXISTS message_reactions;");
    $db->exec("DROP TABLE IF EXISTS messages;");
    $db->exec("DROP TABLE IF EXISTS chat_group_members;");
    $db->exec("DROP TABLE IF EXISTS chat_groups;");
    $db->exec("DROP TABLE IF EXISTS poll_votes;");
    $db->exec("DROP TABLE IF EXISTS poll_options;");
    $db->exec("DROP TABLE IF EXISTS polls;");
    $db->exec("DROP TABLE IF EXISTS bookmarks;");
    $db->exec("DROP TABLE IF EXISTS post_hashtags;");
    $db->exec("DROP TABLE IF EXISTS hashtags;");
    $db->exec("DROP TABLE IF EXISTS wiki_moods;");
    $db->exec("DROP TABLE IF EXISTS stories;");
    $db->exec("DROP TABLE IF EXISTS story_views;");
    $db->exec("DROP TABLE IF EXISTS story_reactions;");
    $db->exec("DROP TABLE IF EXISTS blocks;");
    $db->exec("DROP TABLE IF EXISTS reports;");
    $db->exec("DROP TABLE IF EXISTS login_history;");
    $db->exec("DROP TABLE IF EXISTS name_history;");
    $db->exec("DROP TABLE IF EXISTS notifications;");
    echo "Done.\n";
    
    // Enable foreign keys back
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 1. Create users table
    echo "Creating 'users' table... ";
    $db->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        full_name VARCHAR(100) DEFAULT '',
        first_name VARCHAR(50) DEFAULT '',
        middle_name VARCHAR(50) DEFAULT '',
        last_name VARCHAR(50) DEFAULT '',
        name_display_order VARCHAR(30) DEFAULT 'last_middle_first',
        display_name_last_updated DATETIME DEFAULT NULL,
        username_last_updated DATETIME DEFAULT NULL,
        pending_first_name VARCHAR(50) DEFAULT NULL,
        pending_middle_name VARCHAR(50) DEFAULT NULL,
        pending_last_name VARCHAR(50) DEFAULT NULL,
        pending_name_display_order VARCHAR(30) DEFAULT NULL,
        name_change_status VARCHAR(20) DEFAULT 'none',
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        avatar_filename VARCHAR(255) DEFAULT 'avatar_default.png',
        bio VARCHAR(255) DEFAULT '',
        is_private TINYINT(1) DEFAULT 0,
        show_nsfw TINYINT(1) DEFAULT 0,
        is_adult TINYINT(1) DEFAULT 0,
        dob DATE DEFAULT NULL,
        id_proof_filename VARCHAR(255) DEFAULT NULL,
        age_verification_status VARCHAR(30) DEFAULT 'unverified',
        reset_token VARCHAR(255) DEFAULT NULL,
        reset_token_expires DATETIME DEFAULT NULL,
        activity_dismissed_at DATETIME DEFAULT NULL,
        qr_reset_at DATETIME DEFAULT NULL,
        verification_type VARCHAR(30) DEFAULT NULL,
        phone_number VARCHAR(30) DEFAULT NULL,
        show_email TINYINT(1) DEFAULT 1,
        show_phone TINYINT(1) DEFAULT 1,
        show_gender TINYINT(1) DEFAULT 1,
        show_workplace TINYINT(1) DEFAULT 1,
        show_lives_at TINYINT(1) DEFAULT 1,
        show_country TINYINT(1) DEFAULT 1,
        show_dob TINYINT(1) DEFAULT 1,
        phone_verified TINYINT(1) DEFAULT 1,
        phone_verification_code VARCHAR(10) DEFAULT NULL,
        gender VARCHAR(30) DEFAULT NULL,
        workplace VARCHAR(255) DEFAULT NULL,
        lives_at VARCHAR(255) DEFAULT NULL,
        country VARCHAR(100) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'active',
        status_reason TEXT DEFAULT NULL,
        lock_until DATETIME DEFAULT NULL,
        last_active DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 2. Create posts table
    echo "Creating 'posts' table... ";
    $db->exec("CREATE TABLE posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        image_filename TEXT DEFAULT NULL,
        video_filename VARCHAR(255) DEFAULT NULL,
        audio_filename VARCHAR(255) DEFAULT NULL,
        document_filename VARCHAR(255) DEFAULT NULL,
        software_filename VARCHAR(255) DEFAULT NULL,
        allow_download TINYINT(1) DEFAULT 1,
        is_copyright_violation TINYINT(1) DEFAULT 0,
        copyright_owner VARCHAR(255) DEFAULT NULL,
        copyright_details TEXT DEFAULT NULL,
        is_nsfw TINYINT(1) DEFAULT 0,
        repost_of_post_id INT DEFAULT NULL,
        link_preview_url VARCHAR(2048) DEFAULT NULL,
        link_preview_title VARCHAR(512) DEFAULT NULL,
        link_preview_desc VARCHAR(1024) DEFAULT NULL,
        link_preview_image VARCHAR(2048) DEFAULT NULL,
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (repost_of_post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 3. Create replies table
    echo "Creating 'replies' table... ";
    $db->exec("CREATE TABLE replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 4. Create reactions table (replacing likes)
    echo "Creating 'reactions' table... ";
    $db->exec("CREATE TABLE reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT DEFAULT NULL,
        reply_id INT DEFAULT NULL,
        reaction_type VARCHAR(20) NOT NULL DEFAULT 'like',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_id) REFERENCES replies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 5. Create follows table
    echo "Creating 'follows' table... ";
    $db->exec("CREATE TABLE follows (
        follower_id INT NOT NULL,
        followed_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, followed_id),
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 6. Create admins table
    echo "Creating 'admins' table... ";
    $db->exec("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7. Create settings table
    echo "Creating 'settings' table... ";
    $db->exec("CREATE TABLE settings (
        key_name VARCHAR(50) PRIMARY KEY,
        key_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.5. Create copyright_complaints table
    echo "Creating 'copyright_complaints' table... ";
    $db->exec("CREATE TABLE copyright_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_name VARCHAR(100) NOT NULL,
        reporter_email VARCHAR(100) NOT NULL,
        reporter_phone VARCHAR(30) DEFAULT NULL,
        post_url VARCHAR(2048) NOT NULL,
        description TEXT NOT NULL,
        evidence_filename VARCHAR(255) DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.6. Create chat_groups table
    echo "Creating 'chat_groups' table... ";
    $db->exec("CREATE TABLE chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        avatar_filename VARCHAR(255) DEFAULT 'group_default.png',
        description TEXT DEFAULT NULL,
        creator_type VARCHAR(20) NOT NULL,
        creator_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.7. Create chat_group_members table
    echo "Creating 'chat_group_members' table... ";
    $db->exec("CREATE TABLE chat_group_members (
        group_id INT NOT NULL,
        member_type VARCHAR(20) NOT NULL,
        member_id INT NOT NULL,
        role VARCHAR(30) DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, member_type, member_id),
        FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.8. Create messages table
    echo "Creating 'messages' table... ";
    $db->exec("CREATE TABLE messages (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.9. Create message_reactions table
    echo "Creating 'message_reactions' table... ";
    $db->exec("CREATE TABLE message_reactions (
        message_id INT NOT NULL,
        reactor_type VARCHAR(20) NOT NULL,
        reactor_id INT NOT NULL,
        reaction_emoji VARCHAR(10) NOT NULL,
        PRIMARY KEY (message_id, reactor_type, reactor_id),
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.10. Create hashtags table
    echo "Creating 'hashtags' table... ";
    $db->exec("CREATE TABLE hashtags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tag VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.11. Create post_hashtags table
    echo "Creating 'post_hashtags' table... ";
    $db->exec("CREATE TABLE post_hashtags (
        post_id INT NOT NULL,
        hashtag_id INT NOT NULL,
        PRIMARY KEY (post_id, hashtag_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.12. Create bookmarks table
    echo "Creating 'bookmarks' table... ";
    $db->exec("CREATE TABLE bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_bookmark (user_id, post_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.13. Create polls table
    echo "Creating 'polls' table... ";
    $db->exec("CREATE TABLE polls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        question VARCHAR(255) NOT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.14. Create poll_options table
    echo "Creating 'poll_options' table... ";
    $db->exec("CREATE TABLE poll_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poll_id INT NOT NULL,
        option_text VARCHAR(150) NOT NULL,
        FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 7.15. Create poll_votes table
    echo "Creating 'poll_votes' table... ";
    $db->exec("CREATE TABLE poll_votes (
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
    echo "Done.\n";

    // 7.16. Create wiki_moods table
    echo "Creating 'wiki_moods' table... ";
    $db->exec("CREATE TABLE wiki_moods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content VARCHAR(200) NOT NULL,
        emoji VARCHAR(30) NOT NULL,
        color VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Done.\n";

    // 8. Create needed directories
    echo "Creating directories... ";
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    $avatars_dir = UPLOAD_DIR . 'avatars/';
    $posts_dir = UPLOAD_DIR . 'posts/';
    if (!is_dir($avatars_dir)) {
        mkdir($avatars_dir, 0777, true);
    }
    if (!is_dir($posts_dir)) {
        mkdir($posts_dir, 0777, true);
    }
    echo "Done.\n";

    // 9. Copy dummy assets
    echo "Copying assets... ";
    $preview_dir = UPLOAD_DIR . 'preview/';
    if (is_dir($preview_dir)) {
        // Copy avatars
        @copy($preview_dir . 'wp_anime_study.png', $avatars_dir . 'avatar_hoangdung.png');
        @copy($preview_dir . 'wp_aurora_gradient.png', $avatars_dir . 'avatar_anhtuan.png');
        @copy($preview_dir . 'wp_minimal_mountains.png', $avatars_dir . 'avatar_linhchi.png');
        @copy($preview_dir . 'wp_festival_lights.png', $avatars_dir . 'avatar_minhquan.png');
        @copy($preview_dir . 'wp_glassmorphic_3d.png', $avatars_dir . 'avatar_default.png');
        
        // Copy post images
        @copy($preview_dir . 'wp_minimal_mountains.png', $posts_dir . 'post_mountains.png');
        @copy($preview_dir . 'wp_cyberpunk_alley.png', $posts_dir . 'post_cyberpunk.png');
        
        // Setup a dummy MP4 file placeholder for video posting tests
        @copy($preview_dir . 'wp_aurora_gradient.png', $posts_dir . 'post_video.mp4');
    }
    echo "Done.\n";

    // 10. Seed default Admin
    echo "Seeding default admin... ";
    if (!defined('DEFAULT_ADMIN_USER')) {
        define('DEFAULT_ADMIN_USER', 'admin');
    }
    if (!defined('DEFAULT_ADMIN_PASS')) {
        define('DEFAULT_ADMIN_PASS', 'Admin@123');
    }
    $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)");
    $stmt->execute([DEFAULT_ADMIN_USER, $hash, 'admin@frest.local']);
    echo "Created admin (Username: " . DEFAULT_ADMIN_USER . ", Password: " . DEFAULT_ADMIN_PASS . ").\n";

    // 11. Seed Users with multi-tier verification badges
    echo "Seeding users... ";
    $users = [
        ['username' => 'hoangdung', 'email' => 'hoangdung@frest.local', 'avatar' => 'avatar_hoangdung.png', 'bio' => 'Lập trình viên sáng lập Frest App. Đam mê thiết kế tinh gọn.', 'v_type' => 'developer', 'first' => 'Dũng', 'mid' => 'Hoàng', 'last' => 'Nguyễn', 'full' => 'Nguyễn Hoàng Dũng'],
        ['username' => 'anhtuan', 'email' => 'anhtuan@frest.local', 'avatar' => 'avatar_anhtuan.png', 'bio' => 'Nhiếp ảnh gia đường phố & người nổi tiếng truyền cảm hứng. 📸', 'v_type' => 'official', 'first' => 'Tuấn', 'mid' => 'Anh', 'last' => 'Lê', 'full' => 'Lê Anh Tuấn'],
        ['username' => 'linhchi', 'email' => 'linhchi@frest.local', 'avatar' => 'avatar_linhchi.png', 'bio' => 'Cửa hàng thiết kế đồ họa & Tổ chức Mỹ thuật đương đại.', 'v_type' => 'business', 'first' => 'Chi', 'mid' => 'Linh', 'last' => 'Trần', 'full' => 'Trần Linh Chi'],
        ['username' => 'minhquan', 'email' => 'minhquan@frest.local', 'avatar' => 'avatar_minhquan.png', 'bio' => 'Cổng thông tin Điện tử thuộc Bộ thông tin và truyền thông Việt Nam 🇻🇳', 'v_type' => 'gov_vietnam', 'first' => 'Quân', 'mid' => 'Minh', 'last' => 'Phạm', 'full' => 'Phạm Minh Quân'],
        ['username' => 'tuongvy', 'email' => 'tuongvy@frest.local', 'avatar' => 'avatar_default.png', 'bio' => 'Văn phòng đại diện Ngoại giao Quốc tế 🌐', 'v_type' => 'gov_global', 'first' => 'Vy', 'mid' => 'Tường', 'last' => 'Lâm', 'full' => 'Lâm Tường Vy'],
        ['username' => 'frest_ai', 'email' => 'ai@frest.vn', 'avatar' => 'ai_avatar.png', 'bio' => 'Trợ lý ảo thông minh Frest AI. Hỏi tôi bất cứ điều gì!', 'v_type' => 'official', 'first' => 'AI', 'mid' => 'Frest', 'last' => 'Trợ Lý', 'full' => 'Trợ Lý Frest AI']
    ];

    $user_ids = [];
    $user_pass = password_hash('User@123', PASSWORD_DEFAULT);
    foreach ($users as $u) {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, avatar_filename, bio, verification_type, first_name, middle_name, last_name, full_name, name_display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'last_middle_first')");
        $stmt->execute([$u['username'], $user_pass, $u['email'], $u['avatar'], $u['bio'], $u['v_type'], $u['first'], $u['mid'], $u['last'], $u['full']]);
        $user_ids[$u['username']] = $db->lastInsertId();
    }
    echo "Users seeded.\n";

    // 12. Seed Posts (incorporating a video post)
    echo "Seeding posts... ";
    $posts = [
        [
            'username' => 'hoangdung',
            'content' => "Chào mừng mọi người đến với Frest App! 🐦 Đây là mạng xã hội nâng cấp hoàn toàn mới với giao diện Split-View Canvas, thả cảm xúc đa dạng (reactions), đăng video và tích xanh xác minh đầy đủ cấp độ. Hãy trải nghiệm nhé!",
            'image' => null,
            'video' => null,
            'allow_download' => 1
        ],
        [
            'username' => 'anhtuan',
            'content' => "Hành trình leo núi buổi sáng sớm ngắm mây trôi. Cảnh sắc thiên nhiên bao la giúp nạp đầy năng lượng làm việc. 🌄🏔️",
            'image' => 'post_mountains.png',
            'video' => null,
            'allow_download' => 1
        ],
        [
            'username' => 'hoangdung',
            'content' => "Đoạn video ngắn quay cực quang mượt mà chuyển động. Hãy bật tiếng để cảm nhận không gian huyền ảo nhé! 🌀✨",
            'image' => null,
            'video' => 'post_video.mp4',
            'allow_download' => 0
        ],
        [
            'username' => 'minhquan',
            'content' => "Thông cáo báo chí chính thức: Triển khai chiến dịch truyền thông xanh bảo vệ nguồn nước sạch quốc gia. Vui lòng đón đọc trên cổng thông tin điện tử.",
            'image' => null,
            'video' => null,
            'allow_download' => 1
        ],
        [
            'username' => 'linhchi',
            'content' => "Chúng tôi vừa hoàn thiện bộ nhận diện thương hiệu cho Frest App. Sự kết hợp giữa tối giản và màu sắc neon cá tính mang lại vẻ đẹp vượt thời gian.",
            'image' => 'post_cyberpunk.png',
            'video' => null,
            'allow_download' => 1,
            'is_nsfw' => 1
        ]
    ];

    $post_ids = [];
    foreach ($posts as $p) {
        $is_ns = $p['is_nsfw'] ?? 0;
        $stmt = $db->prepare("INSERT INTO posts (user_id, content, image_filename, video_filename, allow_download, is_nsfw) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_ids[$p['username']], $p['content'], $p['image'], $p['video'], $p['allow_download'], $is_ns]);
        $post_ids[] = $db->lastInsertId();
    }
    echo "Posts seeded.\n";

    // 13. Seed Replies
    echo "Seeding replies... ";
    $replies = [
        ['post_idx' => 0, 'username' => 'anhtuan', 'content' => 'Tính năng thả cảm xúc và tích xác minh đẹp quá anh Dũng ơi! 🔥'],
        ['post_idx' => 0, 'username' => 'minhquan', 'content' => 'Giao diện Split-View chia cột này rất tiện theo dõi bảng tin và thông báo cùng một lúc.'],
        ['post_idx' => 1, 'username' => 'linhchi', 'content' => 'Bức ảnh núi đẹp ngỡ ngàng. Rất thích hợp làm hình nền.'],
        ['post_idx' => 2, 'username' => 'tuongvy', 'content' => 'Màu sắc cực quang chuyển động mượt ghê! Trình phát video chạy rất mượt.'],
        ['post_idx' => 4, 'username' => 'hoangdung', 'content' => 'Thiết kế thương hiệu này cực kỳ ấn tượng, cảm ơn Linh Chi Studio nhé.']
    ];

    foreach ($replies as $r) {
        $p_id = $post_ids[$r['post_idx']];
        $u_id = $user_ids[$r['username']];
        $stmt = $db->prepare("INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$p_id, $u_id, $r['content']]);
    }
    echo "Replies seeded.\n";

    // 14. Seed Reactions (Replaces likes)
    echo "Seeding reactions... ";
    $reactions = [
        ['post_idx' => 0, 'username' => 'anhtuan', 'type' => 'love'],
        ['post_idx' => 0, 'username' => 'linhchi', 'type' => 'haha'],
        ['post_idx' => 0, 'username' => 'minhquan', 'type' => 'like'],
        ['post_idx' => 0, 'username' => 'tuongvy', 'type' => 'wow'],
        ['post_idx' => 1, 'username' => 'hoangdung', 'type' => 'love'],
        ['post_idx' => 1, 'username' => 'linhchi', 'type' => 'like'],
        ['post_idx' => 2, 'username' => 'anhtuan', 'type' => 'wow'],
        ['post_idx' => 2, 'username' => 'tuongvy', 'type' => 'like'],
        ['post_idx' => 3, 'username' => 'hoangdung', 'type' => 'like'],
        ['post_idx' => 4, 'username' => 'minhquan', 'type' => 'haha']
    ];

    foreach ($reactions as $react) {
        $p_id = $post_ids[$react['post_idx']];
        $u_id = $user_ids[$react['username']];
        $stmt = $db->prepare("INSERT IGNORE INTO reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$u_id, $p_id, $react['type']]);
    }
    echo "Reactions seeded.\n";

    // 15. Seed Follows
    echo "Seeding follows... ";
    $follows = [
        ['follower' => 'hoangdung', 'followed' => 'anhtuan'],
        ['follower' => 'hoangdung', 'followed' => 'linhchi'],
        ['follower' => 'anhtuan', 'followed' => 'hoangdung'],
        ['follower' => 'linhchi', 'followed' => 'hoangdung'],
        ['follower' => 'minhquan', 'followed' => 'hoangdung'],
        ['follower' => 'tuongvy', 'followed' => 'hoangdung']
    ];

    foreach ($follows as $f) {
        $fl_id = $user_ids[$f['follower']];
        $fd_id = $user_ids[$f['followed']];
        $stmt = $db->prepare("INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)");
        $stmt->execute([$fl_id, $fd_id]);
    }
    echo "Follows seeded.\n";

    // 16. Seed Default Settings (Synced to Frest App)
    echo "Seeding policies settings... ";
    $settings = [
        [
            'key_name' => 'privacy_policy',
            'key_value' => '<h3>Chính sách bảo mật</h3><p>Chào mừng bạn đến với mạng xã hội <strong>Frest App</strong>. Chúng tôi cam kết bảo vệ thông tin cá nhân của bạn. Dữ liệu của bạn bao gồm tài khoản, email và nội dung bài đăng (Frests) chỉ được sử dụng cho các tính năng cốt lõi của mạng xã hội như đăng bài, bình luận, và theo dõi. Chúng tôi không bao giờ chia sẻ thông tin cá nhân của bạn cho bên thứ ba.</p>'
        ],
        [
            'key_name' => 'terms_of_service',
            'key_value' => '<h3>Điều khoản dịch vụ</h3><p>Khi sử dụng <strong>Frest App</strong>, bạn đồng ý tuân thủ bộ quy tắc ứng xử văn minh: Không đăng tải các nội dung độc hại, khiêu dâm, bạo lực hoặc vi phạm pháp luật. Admin có toàn quyền xóa các bài đăng vi phạm hoặc khóa vĩnh viễn tài khoản người dùng vi phạm tiêu chuẩn cộng đồng mà không cần báo trước.</p>'
        ]
    ];

    foreach ($settings as $set) {
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES (?, ?)");
        $stmt->execute([$set['key_name'], $set['key_value']]);
    }
    echo "Settings seeded.\n";

    // 17. Synchronize default brand icons
    echo "Synchronizing platform brand icons... ";
    ob_start();
    require __DIR__ . '/sync_icons.php';
    ob_end_clean();
    echo "Done.\n";

    echo "\nDatabase initialized successfully for Frest App!\n";
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error during database initialization: " . $e->getMessage();
}

