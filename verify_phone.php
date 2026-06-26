<?php
/**
 * Verify Phone Page - Frest App (Glassmorphism)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) {
    header("Location: index.php");
    exit;
}

$username = isset($_GET['username']) ? sanitize($_GET['username']) : '';
$error_msg = '';
$success_msg = '';

if (empty($username)) {
    header("Location: login.php");
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error_msg = "Không tìm thấy thông tin tài khoản.";
    } elseif (intval($user['phone_verified'] ?? 1) === 1) {
        $success_msg = "Tài khoản của bạn đã được kích hoạt thành công. Bạn có thể đăng nhập ngay.";
    }
} catch (PDOException $e) {
    $error_msg = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_verify']) && !$success_msg) {
    $code = trim($_POST['verification_code'] ?? '');
    
    if (empty($code)) {
        $error_msg = "Vui lòng nhập mã kích hoạt.";
    } elseif ($user) {
        if ($user['phone_verification_code'] === $code) {
            try {
                $up_stmt = $db->prepare("UPDATE users SET phone_verified = 1, phone_verification_code = NULL WHERE id = ?");
                $up_stmt->execute([$user['id']]);
                
                // Auto log in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['avatar'] = $user['avatar_filename'];
                
                // Record login history
                recordLoginHistory($user['id']);
                
                echo "<script>
                    localStorage.setItem('login_success', '1');
                    window.location.href = 'index.php';
                </script>";
                exit;
            } catch (PDOException $e) {
                $error_msg = "Lỗi kích hoạt tài khoản: " . $e->getMessage();
            }
        } else {
            $error_msg = "Mã kích hoạt không chính xác. Vui lòng liên hệ Quản trị viên để nhận lại mã.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" id="authHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kích hoạt tài khoản - Frest App</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    <!-- Master CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css') ?: '1'; ?>">
    <style>
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

        /* ── Dark/Light toggle button ── */
        #authThemeToggle {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: #fff;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        }
        #authThemeToggle:hover {
            background: rgba(255,255,255,0.18);
            transform: scale(1.1) rotate(15deg);
        }
        .light-theme #authThemeToggle {
            border-color: rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.7);
            color: #374151;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        }
    </style>
</head>
<body class="glass-auth-body" id="authBody" style="background-color: #030303; overflow-x: hidden;">

    <!-- Theme Toggle -->
    <button id="authThemeToggle" title="Chuyển giao diện" onclick="toggleAuthTheme()">
        <i class="fa-solid fa-sun" id="authThemeIcon"></i>
    </button>

    <div class="glass-bg-wrapper">
        <div class="glass-bg-circle circle-1"></div>
        <div class="glass-bg-circle circle-2"></div>
        <div class="glass-bg-circle circle-3"></div>
    </div>
        
    <div class="login-card glassmorphism-card" style="max-width: 480px; margin: 40px 20px;">
        <div class="text-center" style="margin-bottom: 24px;">
            <a href="index.php" class="logo" style="font-size: 32px; justify-content: center; margin-bottom: 8px; font-family: var(--font-heading); font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i> <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Frest</span>
            </a>
                <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: var(--text-primary); margin-top: 12px; margin-bottom: 6px;">Kích hoạt tài khoản</h3>
                <p style="color: var(--text-secondary); font-size: 13.5px; line-height: 1.5;">Tài khoản đăng ký bằng Số điện thoại cần có mã xác minh của quản trị viên để kích hoạt.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border-left: 4px solid var(--danger); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px);">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div style="background: rgba(16, 185, 129, 0.15); border-left: 4px solid var(--success); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13.5px; backdrop-filter: blur(10px); text-align: center;">
                    <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?php echo $success_msg; ?>
                    <div style="margin-top: 12px;">
                        <a href="login.php" class="btn-primary" style="display: inline-block; padding: 8px 18px; font-size: 13px; border-radius: var(--radius-full); text-decoration: none;">Đăng nhập ngay</a>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: rgba(235, 94, 40, 0.08); border: 1px dashed var(--accent-primary); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; font-size: 13px; color: var(--text-secondary); line-height: 1.5; text-align: center;">
                    <i class="fa-solid fa-circle-info" style="color: var(--accent-primary); font-size: 16px; margin-bottom: 6px; display: block;"></i>
                    Hệ thống đã gửi yêu cầu cấp mã xác minh cho số điện thoại: 
                    <strong style="color: var(--text-primary); font-size: 14px; display: block; margin-top: 4px;"><?php echo htmlspecialchars($user['phone_number'] ?? ''); ?></strong>
                    Vui lòng liên hệ với Quản trị viên để nhận mã kích hoạt.
                    <div style="margin-top: 8px; padding: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-sm); color: var(--success); font-weight: bold; font-size: 12.5px;">
                        [Chế độ Demo] Mã kích hoạt của bạn là: <?php echo htmlspecialchars($user['phone_verification_code'] ?? ''); ?>
                    </div>
                </div>

                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
                    <input type="hidden" name="action_verify" value="1">

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="verification_code" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; text-align: center;">Nhập Mã Kích Hoạt (6 chữ số)</label>
                        <input type="text" name="verification_code" id="verification_code" class="form-input glass-input" placeholder="ví dụ: 123456" required pattern="[0-9]{6}" maxlength="6" style="text-align: center; font-size: 20px; font-weight: 800; letter-spacing: 4px; padding: 12px;">
                    </div>

                    <button type="submit" class="btn-purchase-action" style="border: none; background: var(--accent-gradient); color: #fff; cursor: pointer; font-weight: 700; width: 100%; border-radius: var(--radius-full); margin-top: 5px; height: 46px; font-size: 14.5px; box-shadow: 0 4px 15px var(--accent-glow);">
                        Kích hoạt tài khoản
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center" style="margin-top: 24px; font-size: 13.5px; color: var(--text-secondary);">
                Quay lại trang <a href="login.php" style="color: var(--accent-primary); font-weight: 600; ">Đăng nhập</a> hoặc <a href="register.php" style="color: var(--accent-primary); font-weight: 600; ">Đăng ký</a>
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

