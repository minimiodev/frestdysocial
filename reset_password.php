<?php
/**
 * Reset Password - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg = '';
$success_msg = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    $error_msg = "Mã khôi phục (Token) không hợp lệ hoặc thiếu.";
} else {
    try {
        $db = getDB();

        // Find user by token
        $stmt = $db->prepare("SELECT id, username, reset_token_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $error_msg = "Mã khôi phục không tồn tại hoặc đã được sử dụng.";
        } else {
            // Check expiry
            $expires = strtotime($user['reset_token_expires']);
            if ($expires < time()) {
                $error_msg = "Mã khôi phục mật khẩu này đã hết hạn. Vui lòng gửi lại yêu cầu.";
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
    }
}

// Handle Form Submission
if (empty($error_msg) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error_msg = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Mật khẩu xác nhận không khớp.";
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Update user password and clear token
            $update = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            if ($update->execute([$password_hash, $user['id']])) {
                $success_msg = "Mật khẩu của bạn đã được đặt lại thành công! Bạn có thể sử dụng mật khẩu mới để đăng nhập ngay bây giờ.";
            } else {
                $error_msg = "Có lỗi xảy ra trong quá trình cập nhật mật khẩu.";
            }
        } catch (PDOException $e) {
            $error_msg = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" id="authHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - Frest App</title>
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
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
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
            max-width: 440px;
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
            font-size: 12px;
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
    </style>
</head>
<body class="glass-auth-body" id="authBody" style="background-color: #030303;">

    <!-- Theme Toggle -->
    <button id="authThemeToggle" title="Chuyển giao diện" onclick="toggleAuthTheme()">
        <i class="fa-solid fa-sun" id="authThemeIcon"></i>
    </button>

    <div class="glass-bg-wrapper">
        <div class="glass-bg-circle circle-1"></div>
        <div class="glass-bg-circle circle-2"></div>
        <div class="glass-bg-circle circle-3"></div>
    </div>
        
    <div class="login-card glassmorphism-card">
        <div class="text-center" style="margin-bottom: 30px; text-align: center;">
            <a href="index.php" class="logo" style="font-size: 32px; justify-content: center; margin-bottom: 8px; font-family: var(--font-heading); font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i> <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Frest</span>
            </a>
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; color: var(--text-primary); margin-top: 12px; margin-bottom: 6px;">Đặt lại mật khẩu mới</h3>
            <?php if ($user && empty($success_msg)): ?>
                <p style="color: var(--text-secondary); font-size: 13.5px; margin: 0; line-height: 1.4;">Thiết lập mật khẩu mới cho tài khoản của thành viên <strong>@<?php echo htmlspecialchars($user['username']); ?></strong>.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px); display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);">
                <i class="fa-solid fa-circle-exclamation" style="flex-shrink: 0;"></i> <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div style="background: rgba(16, 185, 129, 0.12); border-left: 4px solid var(--success); color: var(--success); padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13.5px; backdrop-filter: blur(10px); line-height: 1.5; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);">
                <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?php echo $success_msg; ?>
                <div style="margin-top: 14px;">
                    <a href="login.php" class="btn-submit-premium" style="height: 38px; font-size: 13px; border-radius: 8px; text-decoration: none;">Đăng nhập ngay &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user && empty($success_msg)): ?>
            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
                <input type="hidden" name="action_reset" value="1">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label for="new_password" class="form-label">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                    <div class="password-toggle-wrapper">
                        <input type="password" name="new_password" id="new_password" class="glass-input" placeholder="Nhập mật khẩu mới..." required style="padding-right: 46px !important;">
                        <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                    <div class="password-toggle-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="glass-input" placeholder="Xác nhận lại mật khẩu mới..." required style="padding-right: 46px !important;">
                        <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit-premium" style="margin-top: 10px;">
                    Đặt lại mật khẩu
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center" style="margin-top: 24px; font-size: 13.5px; color: var(--text-secondary);">
            Quay lại <a href="login.php" style="color: var(--accent-primary); font-weight: 600; ">Đăng nhập</a>
        </div>
    </div>

<script>
(function () {
    var DARK_BG  = '#030303';
    var LIGHT_BG = '#e8ecf5';
    var html     = document.getElementById('authHtml');
    var body     = document.getElementById('authBody');
    var icon     = document.getElementById('authThemeIcon');

    function applyTheme(theme) {
        if (!html || !body) return;
        if (theme === 'light') {
            html.classList.add('light-theme');
            body.classList.add('light-theme');
            body.style.backgroundColor = LIGHT_BG;
            if (icon) icon.className = 'fa-solid fa-moon';
        } else {
            html.classList.remove('light-theme');
            body.classList.remove('light-theme');
            body.style.backgroundColor = DARK_BG;
            if (icon) icon.className = 'fa-solid fa-sun';
        }
    }

    var saved = localStorage.getItem('theme') || 'dark';
    applyTheme(saved);

    window.toggleAuthTheme = function () {
        var current = localStorage.getItem('theme') || 'dark';
        var next    = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        applyTheme(next);
        document.cookie = 'theme=' + next + ';path=/;max-age=31536000';
    };
})();
</script>

</body>
</html>

