<?php
/**
 * Forgot Password - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (isUserLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg    = '';
$success_msg  = '';
$reset_link   = '';  // only populated when SMTP not configured (fallback mode)
$email_sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_forgot'])) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error_msg = "Vui lòng nhập địa chỉ email của bạn.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Định dạng email không hợp lệ.";
    } else {
        try {
            $db = getDB();

            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate token and expiry
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $update = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $update->execute([$token, $expires, $user['id']]);

                $link = SITE_URL . "/reset_password.php?token=" . $token;

                // Try to send real email
                $mail_result = sendResetEmail($email, $user['username'], $link);

                if ($mail_result['sent']) {
                    $email_sent  = true;
                    $success_msg = "✅ Email khôi phục đã được gửi đến <strong>{$email}</strong>. Vui lòng kiểm tra hộp thư (kể cả thư mục spam).";
                } else {
                    // Fallback: show link on screen
                    $reset_link  = $link;
                    $success_msg = "Yêu cầu khôi phục đã xử lý. " .
                        (SMTP_HOST === 'smtp.example.com'
                            ? "SMTP chưa cấu hình — dùng liên kết bên dưới để đặt lại mật khẩu:"
                            : "Không thể gửi email ({$mail_result['error']}) — dùng liên kết bên dưới:");
                }
            } else {
                // Don't reveal whether email exists (security)
                $success_msg = "Nếu email này tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.";
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
    <title>Quên mật khẩu - Frest App</title>
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

        /* Envelope Animation styling */
        .frest-envelope-animation-wrap {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .frest-envelope-icon {
            font-size: 36px;
            color: #fff;
            background: var(--accent-gradient);
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px var(--accent-glow);
            z-index: 2;
            animation: floatAirplane 3s infinite ease-in-out;
        }
        .frest-envelope-glow {
            position: absolute;
            top: 5px;
            left: 5px;
            width: 70px;
            height: 70px;
            background: var(--accent-primary);
            filter: blur(15px);
            opacity: 0.5;
            border-radius: 50%;
            z-index: 1;
            animation: pulseGlow 2s infinite alternate ease-in-out;
        }

        @keyframes floatAirplane {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(-5deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }
        @keyframes pulseGlow {
            0% { transform: scale(0.9); opacity: 0.4; }
            100% { transform: scale(1.15); opacity: 0.65; }
        }

        .frest-email-success-container {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .frest-email-success-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .frest-email-success-desc {
            color: var(--text-secondary);
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .frest-email-success-actions {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
        }
        .frest-back-to-login {
            font-size: 13.5px;
            color: var(--text-secondary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .frest-back-to-login:hover {
            color: var(--accent-primary);
        }
    </style>
</head>
<body class="glass-auth-body" id="authBody" style="background-color: #030303;">

    <!-- Back Button -->
    <a href="login.php" id="authBackBtn" title="Quay lại Đăng nhập">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

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
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 700; color: var(--text-primary); margin-top: 12px; margin-bottom: 6px;">Khôi phục mật khẩu</h3>
            <p style="color: var(--text-secondary); font-size: 13.5px; margin: 0; line-height: 1.4;">Nhập email liên kết với tài khoản của bạn để nhận mã khôi phục.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px); display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);">
                <i class="fa-solid fa-circle-exclamation" style="flex-shrink: 0;"></i> <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($email_sent): ?>
            <div class="frest-email-success-container">
                <div class="frest-envelope-animation-wrap">
                    <div class="frest-envelope-icon">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <div class="frest-envelope-glow"></div>
                </div>
                
                <h3 class="frest-email-success-title">Email đã được gửi!</h3>
                <p class="frest-email-success-desc">
                    Một liên kết đặt lại mật khẩu đã được gửi thành công đến <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($email); ?></strong>. Vui lòng kiểm tra hộp thư đến và cả thư mục thư rác (spam).
                </p>
                
                <div class="frest-email-success-actions">
                    <form action="" method="POST" style="margin: 0; width: 100%;">
                        <input type="hidden" name="action_forgot" value="1">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <button type="submit" class="btn-submit-premium" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255,255,255,0.08); color: #fff; box-shadow: none;">
                            <i class="fa-solid fa-rotate-right" style="margin-right: 6px;"></i> Gửi lại email
                        </button>
                    </form>
                    
                    <a href="login.php" class="frest-back-to-login">
                        <i class="fa-solid fa-arrow-left"></i> Quay lại Đăng nhập
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($success_msg)): ?>
                <div style="background: rgba(16, 185, 129, 0.12); border-left: 4px solid var(--success); color: var(--success); padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13.5px; backdrop-filter: blur(10px); line-height: 1.5; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);">
                    <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?php echo $success_msg; ?>
                    
                    <?php if (!empty($reset_link)): ?>
                        <div style="margin-top: 14px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 12px;">
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--accent-primary); margin-bottom: 8px; letter-spacing: 0.5px;">Liên kết khôi phục (Demo):</div>
                            <input type="text" id="simulated-link" value="<?php echo htmlspecialchars($reset_link); ?>" style="width: 100%; font-size: 12px; padding: 10px 12px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); color: var(--text-primary); border-radius: 8px; box-sizing: border-box; margin-bottom: 10px; outline: none;" readonly onclick="this.select();">
                            <a href="<?php echo $reset_link; ?>" class="btn-submit-premium" style="height: 38px; font-size: 13px; border-radius: 8px; text-decoration: none;">Đi tới đặt lại mật khẩu &rarr;</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
                <input type="hidden" name="action_forgot" value="1">

                <div class="form-group">
                    <label for="email" class="form-label">Địa chỉ Email</label>
                    <input type="email" name="email" id="email" class="glass-input" placeholder="Nhập địa chỉ email của bạn..." required>
                </div>

                <button type="submit" class="btn-submit-premium" style="margin-top: 10px;">
                    Gửi yêu cầu khôi phục
                </button>
            </form>

            <div class="text-center" style="margin-top: 24px; font-size: 13.5px; color: var(--text-secondary); text-align: center;">
                Quay lại <a href="login.php" style="color: var(--accent-primary); font-weight: 600; text-decoration: none; transition: color 0.2s;">Đăng nhập</a>
            </div>
        <?php endif; ?>
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

