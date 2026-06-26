<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_msg = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                
                header("Location: index.php");
                exit;
            } else {
                $error_msg = "Tên đăng nhập hoặc mật khẩu không đúng.";
            }
        } catch (PDOException $e) {
            $error_msg = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<?php
$site_logo_val = getSystemSetting('site_logo', '');
$site_name_val = getSiteName();
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - <?php echo htmlspecialchars($site_name_val); ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="glass-auth-body" id="authBody" style="background-color: #030303;">

    <!-- Back Button -->
    <a href="../index.php" id="authBackBtn" title="Quay lại Trang chủ">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

    <div class="glass-bg-wrapper">
        <div class="glass-bg-circle circle-1"></div>
        <div class="glass-bg-circle circle-2"></div>
        <div class="glass-bg-circle circle-3"></div>
        
        <div class="login-card glassmorphism-card" style="margin: auto;">
            <div class="text-center" style="margin-bottom: 30px;">
                <a href="../index.php" class="logo" style="font-size: 32px; justify-content: center; margin-bottom: 8px; font-family: var(--font-heading); font-weight: 800; text-decoration: none;">
                    <i class="fa-solid fa-feather-pointed" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i> Frest <span style="color:var(--text-primary);">Admin</span>
                </a>
                <p style="color: var(--text-secondary); font-size: 13.5px;">Trang quản trị hệ thống frest tối ưu</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border-left: 4px solid var(--danger); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px);">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div style="background: rgba(16, 185, 129, 0.15); border-left: 4px solid var(--success); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13px; backdrop-filter: blur(10px); line-height: 1.5;">
                    <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> Đặt lại hệ thống thành công! Hãy đăng nhập bằng tài khoản quản trị mặc định:<br>
                    Tài khoản: <strong style="text-decoration: underline;">admin</strong><br>
                    Mật khẩu: <strong style="text-decoration: underline;">Admin@123</strong>
                </div>
            <?php endif; ?>

            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="username" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tên đăng nhập quản trị</label>
                    <input type="text" name="username" id="username" class="form-input glass-input" placeholder="Nhập tên đăng nhập admin..." required autofocus>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="password" class="form-label" style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Mật khẩu</label>
                    <div class="password-toggle-wrapper">
                        <input type="password" name="password" id="password" class="form-input glass-input" placeholder="Nhập mật khẩu..." required style="padding-right: 44px !important;">
                        <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-purchase-action" style="border: none; background: var(--accent-gradient); color: #fff; cursor: pointer; font-weight: 700; width: 100%; border-radius: var(--radius-full); margin-top: 10px; height: 46px; font-size: 14.5px; box-shadow: 0 4px 15px var(--accent-glow);">
                    Đăng nhập hệ thống
                </button>
            </form>
        </div>
    </div>

</body>
</html>

