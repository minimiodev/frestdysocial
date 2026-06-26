<?php
/**
 * Register Page - Frest App (Upgraded Glassmorphism)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $reg_type = trim($_POST['reg_type'] ?? 'email');
    $password = $_POST['password'] ?? '';

    $email = '';
    $phone_number = '';
    $phone_verified = 1;
    $phone_verification_code = null;

    if ($reg_type === 'phone') {
        $phone_number = trim($_POST['phone_number'] ?? '');
    } else {
        $email = sanitize($_POST['email'] ?? '');
    }

    if (empty($first_name) || empty($last_name) || empty($password) || ($reg_type === 'email' && empty($email)) || ($reg_type === 'phone' && empty($phone_number))) {
        $error_msg = "Vui lòng nhập đầy đủ Họ, Tên, Thông tin liên hệ và Mật khẩu.";
    } elseif ($reg_type === 'email' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Địa chỉ email không hợp lệ.";
    } elseif ($reg_type === 'phone' && !preg_match('/^[0-9]{9,15}$/', preg_replace('/[^0-9]/', '', $phone_number))) {
        $error_msg = "Số điện thoại không hợp lệ (phải từ 9 đến 15 chữ số).";
    } elseif (strlen($password) < 6) {
        $error_msg = "Mật khẩu phải chứa ít nhất 6 ký tự.";
    } else {
        try {
            $db = getDB();

            if ($reg_type === 'phone') {
                $phone_clean = preg_replace('/[^0-9]/', '', $phone_number);
                $email = $phone_clean . '@phone.local';
                
                // Check if phone number exists or mock email exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE phone_number = ? OR email = ?");
                $stmt->execute([$phone_number, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $error_msg = "Số điện thoại này đã được đăng ký.";
                } else {
                    $phone_verified = 1;
                    $phone_verification_code = null;
                }
            } else {
                // Check if email exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error_msg = "Địa chỉ email đã được đăng ký.";
                }
            }

            if (empty($error_msg)) {
                // Auto-generate unique numeric username like Facebook ID
                $username = '';
                do {
                    $username = '1000' . strval(rand(10000000000, 99999999999));
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $exists = ($stmt->fetchColumn() > 0);
                } while ($exists);

                $avatar_filename = 'avatar_default.png';
                $bio = '';
                $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                $full_name = formatUserFullName($first_name, $middle_name, $last_name, 'last_middle_first');

                $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, avatar_filename, bio, first_name, middle_name, last_name, full_name, name_display_order, phone_number, phone_verified, phone_verification_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'last_middle_first', ?, ?, ?)");
                $stmt->execute([$username, $pass_hash, $email, $avatar_filename, $bio, $first_name, $middle_name, $last_name, $full_name, $phone_number ?: null, $phone_verified, $phone_verification_code]);
                
                $user_id = $db->lastInsertId();

                // Tạo các thư mục upload riêng cho người dùng mới
                createUserUploadDirectories($user_id);

                // Log account creation name history
                $stmt_hist = $db->prepare("INSERT INTO name_history (entity_type, entity_id, old_name, new_name) VALUES ('user', ?, NULL, ?)");
                $stmt_hist->execute([$user_id, $full_name]);
                
                if ($phone_verified === 0) {
                    // Redirect to verification page
                    header("Location: verify_phone.php?username=" . urlencode($username));
                    exit;
                } else {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['avatar'] = $avatar_filename;
                    
                    // Record login history
                    recordLoginHistory($user_id);
                    
                    echo "<script>
                        localStorage.setItem('register_success', '1');
                        window.location.href = 'index.php';
                    </script>";
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error_msg = "Lỗi đăng ký tài khoản: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" id="authHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Frest App</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    <!-- Master CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css') ?: '1'; ?>">
    <style>
        /* ── Auth page styling ── */
        body.glass-auth-body {
            font-family: 'Inter', 'Be Vietnam Pro', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 20px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            overflow-y: auto;
            position: relative;
            box-sizing: border-box;
        }

        .glass-bg-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        /* Glowing blur circles with animations */
        .glass-bg-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            mix-blend-mode: screen;
            pointer-events: none;
            transition: all 0.8s ease;
        }
        .circle-1 {
            top: -10%;
            left: -10%;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.35) 0%, transparent 70%);
            animation: floatCircle1 25s infinite alternate ease-in-out;
        }
        .circle-2 {
            bottom: -10%;
            right: -10%;
            width: 45vw;
            height: 45vw;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
            animation: floatCircle2 30s infinite alternate ease-in-out;
        }
        .circle-3 {
            top: 40%;
            left: 60%;
            width: 35vw;
            height: 35vw;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.2) 0%, transparent 70%);
            animation: floatCircle3 20s infinite alternate ease-in-out;
        }

        @keyframes floatCircle1 {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(6%, 8%) scale(1.08); }
        }
        @keyframes floatCircle2 {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-8%, -6%) scale(1.04); }
        }
        @keyframes floatCircle3 {
            0% { transform: translate(0, 0) scale(0.95); }
            100% { transform: translate(-4%, 6%) scale(1.08); }
        }

        /* Light mode overrides for circles */
        .light-theme .glass-bg-circle {
            mix-blend-mode: multiply;
            opacity: 0.35;
        }
        .light-theme .circle-1 { background: radial-gradient(circle, rgba(124, 58, 237, 0.18) 0%, transparent 70%); }
        .light-theme .circle-2 { background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%); }
        .light-theme .circle-3 { background: radial-gradient(circle, rgba(236, 72, 153, 0.12) 0%, transparent 70%); }

        /* Light theme overrides to fix readability (pointer-events and high contrast text) */
        .glass-auth-body.light-theme {
            --bg-primary: #e8ecf5 !important;
            --text-primary: #0f172a !important;
            --text-secondary: #334155 !important;
            --text-muted: #475569 !important;
            --border-color: rgba(0, 0, 0, 0.12) !important;
        }
        .glass-auth-body.light-theme .login-card.glassmorphism-card {
            background: rgba(255, 255, 255, 0.85) !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.6) !important;
            color: #0f172a !important;
        }
        .glass-auth-body.light-theme .form-label {
            color: #1e293b !important;
        }
        .glass-auth-body.light-theme .glass-input {
            background: rgba(0, 0, 0, 0.03) !important;
            border: 1px solid rgba(0, 0, 0, 0.15) !important;
            color: #0f172a !important;
        }
        .glass-auth-body.light-theme .glass-input::placeholder {
            color: rgba(0, 0, 0, 0.45) !important;
        }
        .glass-auth-body.light-theme .password-toggle-btn {
            color: rgba(0, 0, 0, 0.45) !important;
        }
        .glass-auth-body.light-theme .password-toggle-btn:hover {
            color: #0f172a !important;
        }

        /* Card style */
        .login-card.glassmorphism-card {
            position: relative;
            z-index: 5;
            width: 100%;
            max-width: 460px;
            padding: 40px;
            margin: 24px auto;
            border-radius: 24px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(18, 18, 18, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.1);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
        }

        /* Inputs styling */
        .glass-input {
            width: 100%;
            padding: 13px 18px;
            font-size: 14px;
            border-radius: 12px;
            outline: none;
            font-family: 'Inter', sans-serif;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .glass-input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }
        .glass-input:hover {
            border-color: rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.05);
        }
        .glass-input:focus {
            border-color: var(--accent-primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 14px var(--accent-glow), inset 0 1px 1px rgba(255, 255, 255, 0.02);
        }

        .light-theme .glass-input {
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.12);
            color: #0f172a;
        }
        .light-theme .glass-input::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }
        .light-theme .glass-input:hover {
            border-color: rgba(0, 0, 0, 0.2);
            background: rgba(0, 0, 0, 0.03);
        }
        .light-theme .glass-input:focus {
            border-color: var(--accent-primary);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 10px rgba(124, 58, 237, 0.15);
        }

        /* Password input wrapper */
        .password-toggle-wrapper {
            position: relative;
            width: 100%;
        }
        .password-toggle-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .password-toggle-btn:hover {
            color: #fff;
        }
        .light-theme .password-toggle-btn {
            color: rgba(0, 0, 0, 0.4);
        }
        .light-theme .password-toggle-btn:hover {
            color: #0f172a;
        }

        /* Form groups & labels */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-label {
            font-size: 11.5px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            transition: color 0.2s;
        }
        .light-theme .form-label {
            color: #334155;
        }

        /* Theme switch & Back buttons */
        #authThemeToggle, #authBackBtn {
            position: fixed;
            top: 18px;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(18, 18, 18, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: #fff;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }
        #authThemeToggle { right: 18px; }
        #authBackBtn { left: 18px; }

        #authThemeToggle:hover, #authBackBtn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.16);
            transform: scale(1.08);
        }
        #authThemeToggle:hover {
            transform: scale(1.08) rotate(15deg);
        }

        .light-theme #authThemeToggle, .light-theme #authBackBtn {
            border-color: rgba(0, 0, 0, 0.08);
            background: rgba(255, 255, 255, 0.75);
            color: #334155;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .light-theme #authThemeToggle:hover, .light-theme #authBackBtn:hover {
            background: rgba(0, 0, 0, 0.03);
            border-color: rgba(0, 0, 0, 0.15);
        }

        /* Registration Type Tabs */
        .frest-auth-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 4px;
            border-radius: 14px;
            margin-bottom: 24px;
        }
        .light-theme .frest-auth-tabs {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
        .frest-auth-tab {
            flex: 1;
            border: none;
            background: none;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .frest-auth-tab.active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .light-theme .frest-auth-tab {
            color: rgba(0, 0, 0, 0.5);
        }
        .light-theme .frest-auth-tab.active {
            background: #fff;
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        /* Submit button override */
        .btn-submit-premium {
            border: none;
            background: var(--accent-gradient);
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            width: 100%;
            border-radius: 50px;
            height: 48px;
            font-size: 14.5px;
            box-shadow: 0 4px 16px var(--accent-glow);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--accent-glow);
            filter: brightness(1.08);
        }
        .btn-submit-premium:active {
            transform: translateY(0);
        }

        /* Responsive optimizations for small screen height and widths */
        @media (max-width: 480px), (max-height: 740px) {
            body.glass-auth-body {
                align-items: flex-start;
                padding: 72px 12px 30px 12px;
            }
            .login-card.glassmorphism-card {
                padding: 24px 20px;
                margin: 0 auto;
                border-radius: 18px;
            }
            #authThemeToggle, #authBackBtn {
                position: absolute;
                top: 14px;
            }
        }
    </style>
</head>
<body class="glass-auth-body" id="authBody" style="background-color: #030303;">

    <!-- Back Button -->
    <a href="index.php" id="authBackBtn" title="Quay lại Trang chủ">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

    <button id="authThemeToggle" title="Chuyển giao diện" onclick="toggleAuthTheme()">
        <i class="fa-solid fa-sun" id="authThemeIcon"></i>
    </button>

    <div class="glass-bg-wrapper">
        <div class="glass-bg-circle circle-1"></div>
        <div class="glass-bg-circle circle-2"></div>
        <div class="glass-bg-circle circle-3"></div>
    </div>
        
    <div class="login-card glassmorphism-card">
        <div class="text-center" style="margin-bottom: 24px; text-align: center;">
            <a href="index.php" class="logo" style="font-size: 32px; justify-content: center; margin-bottom: 8px; font-family: var(--font-heading); font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i> <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Frest</span>
            </a>
            <p style="color: var(--text-secondary); font-size: 13.5px; margin: 0;">Bắt đầu kết nối, chia sẻ Frest ảnh & video đa phương tiện</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px); display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);">
                <i class="fa-solid fa-circle-exclamation" style="flex-shrink: 0;"></i> <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Registration Type Tabs -->
        <div class="frest-auth-tabs">
            <button type="button" id="tab-btn-email" class="frest-auth-tab active" onclick="switchRegType('email')">
                <i class="fa-solid fa-envelope"></i> Email
            </button>
            <button type="button" id="tab-btn-phone" class="frest-auth-tab" onclick="switchRegType('phone')">
                <i class="fa-solid fa-phone"></i> Số điện thoại
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
            <input type="hidden" name="action_register" value="1">
            <input type="hidden" name="reg_type" id="reg-type-input" value="email">

            <div style="display: flex; gap: 10px;">
                <div class="form-group" style="flex: 1;">
                    <label for="last_name" class="form-label">Họ</label>
                    <input type="text" name="last_name" id="last_name" class="glass-input" placeholder="Họ..." required style="padding: 11px 14px;">
                </div>
                <div class="form-group" style="flex: 1.2;">
                    <label for="middle_name" class="form-label">Tên đệm</label>
                    <input type="text" name="middle_name" id="middle_name" class="glass-input" placeholder="Đệm (tùy chọn)..." style="padding: 11px 14px;">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="first_name" class="form-label">Tên</label>
                    <input type="text" name="first_name" id="first_name" class="glass-input" placeholder="Tên..." required style="padding: 11px 14px;">
                </div>
            </div>

            <div class="form-group" id="email-group">
                <label for="email" class="form-label">Địa chỉ Email</label>
                <input type="email" name="email" id="email" class="glass-input" placeholder="ví dụ: dung@gmail.com" required>
            </div>

            <div class="form-group" id="phone-group" style="display: none;">
                <label for="phone_number" class="form-label">Số điện thoại</label>
                <input type="text" name="phone_number" id="phone_number" class="glass-input" placeholder="ví dụ: 0987654321">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu (tối thiểu 6 ký tự)</label>
                <div class="password-toggle-wrapper">
                    <input type="password" name="password" id="password" class="glass-input" placeholder="Nhập mật khẩu bí mật..." required style="padding-right: 46px !important;">
                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit-premium" style="margin-top: 10px;">
                Đăng ký tài khoản mới
            </button>
        </form>

        <div class="text-center" style="margin-top: 20px; font-size: 13.5px; color: var(--text-secondary); text-align: center;">
            Đã có tài khoản? <a href="login.php" style="color: var(--accent-primary); font-weight: 600; text-decoration: none; transition: color 0.2s;">Đăng nhập ngay</a>
        </div>
    </div>
    <script src="assets/js/register.js"></script>
    <script>
    (function () {
        var html = document.getElementById('authHtml');
        var body = document.getElementById('authBody');
        var icon = document.getElementById('authThemeIcon');
        function applyTheme(t) {
            if (!html || !body) return;
            if (t === 'light') { 
                html.classList.add('light-theme'); 
                body.classList.add('light-theme');
                body.style.backgroundColor = '#e8ecf5'; 
                if (icon) icon.className = 'fa-solid fa-moon'; 
            }
            else { 
                html.classList.remove('light-theme'); 
                body.classList.remove('light-theme');
                body.style.backgroundColor = '#030303'; 
                if (icon) icon.className = 'fa-solid fa-sun'; 
            }
        }
        applyTheme(localStorage.getItem('theme') || 'dark');
        window.toggleAuthTheme = function () {
            var next = (localStorage.getItem('theme') || 'dark') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', next);
            applyTheme(next);
            document.cookie = 'theme=' + next + ';path=/;max-age=31536000';
        };
    })();
    </script>
</body>
</html>
