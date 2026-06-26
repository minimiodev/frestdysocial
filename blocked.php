<?php
/**
 * Account Suspended / Locked Notification Page - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Force login check
$me = getLoggedInUser();
if (!$me) {
    header("Location: login.php");
    exit;
}

$status = $me['status'] ?? 'active';
$reason = $me['status_reason'] ?? 'Không có lý do cụ thể.';
$lock_until = $me['lock_until'] ?? null;

// If active, redirect to home
if ($status === 'active') {
    header("Location: index.php");
    exit;
}

// Format status title and description
$status_title = 'Tài khoản bị khóa';
$status_color = '#f59e0b'; // Amber
$status_desc = '';

if ($status === 'temporarily_locked') {
    $status_title = 'Tài khoản bị khóa tạm thời';
    $status_color = '#f59e0b';
    
    if ($lock_until) {
        $remaining_seconds = strtotime($lock_until) - time();
        if ($remaining_seconds <= 0) {
            // Auto unlock and redirect
            try {
                $db = getDB();
                $db->prepare("UPDATE users SET status = 'active', status_reason = NULL, lock_until = NULL WHERE id = ?")->execute([$me['id']]);
                header("Location: index.php");
                exit;
            } catch (Exception $e) {}
        }
        
        $lock_time_str = date('d/m/Y H:i:s', strtotime($lock_until));
        $status_desc = 'Tài khoản của bạn tạm thời không thể sử dụng cho đến: <strong>' . $lock_time_str . '</strong>.';
    } else {
        $status_desc = 'Tài khoản của bạn đang bị khóa tạm thời.';
    }
} elseif ($status === 'disabled') {
    $status_title = 'Tài khoản đã bị vô hiệu hóa';
    $status_color = '#64748b'; // Gray
    $status_desc = 'Tài khoản của bạn đã bị vô hiệu hóa bởi quản trị viên.';
} elseif ($status === 'permanently_suspended') {
    $status_title = 'Tài khoản bị đóng vĩnh viễn';
    $status_color = '#ef4444'; // Red
    $status_desc = 'Tài khoản của bạn đã bị đóng vĩnh viễn do vi phạm nghiêm trọng tiêu chuẩn cộng đồng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $status_title; ?> - Frest App</title>
    <!-- Google Fonts with Vietnamese subset support -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    <style>
        :root {
            --bg-primary: #070709;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --accent-color: <?php echo $status_color; ?>;
            --accent-glow: <?php echo $status_color; ?>4D;
            --font-primary: 'Inter', sans-serif;
            --font-heading: 'Be Vietnam Pro', sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: var(--font-primary);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Abstract background blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            z-index: 1;
            animation: float 12s infinite alternate ease-in-out;
        }

        .blob-1 {
            width: 400px;
            height: 400px;
            background: var(--accent-color);
            top: -100px;
            left: -100px;
        }

        .blob-2 {
            width: 450px;
            height: 450px;
            background: #3b82f6;
            bottom: -150px;
            right: -100px;
            animation-delay: -3s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(45px, 35px) scale(1.1); }
        }

        .container {
            position: relative;
            z-index: 10;
            padding: 24px;
            width: 100%;
            max-width: 480px;
            box-sizing: border-box;
        }

        .blocked-card {
            background: rgba(15, 15, 20, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 36px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .icon-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 24px;
            background: linear-gradient(135deg, <?php echo $status_color; ?>1A 0%, <?php echo $status_color; ?>05 100%);
            border: 1px solid <?php echo $status_color; ?>40;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px auto;
            box-shadow: 0 12px 30px <?php echo $status_color; ?>26;
            position: relative;
            transform: rotate(-5deg);
            animation: rotateGlow 6s infinite alternate ease-in-out;
        }

        @keyframes rotateGlow {
            0% { transform: rotate(-5deg) scale(1); }
            100% { transform: rotate(5deg) scale(1.05); }
        }

        .icon-wrapper svg {
            width: 44px;
            height: 44px;
            filter: drop-shadow(0 0 8px var(--accent-color));
        }

        h1 {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            margin: 0 0 16px 0;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 40%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--text-secondary);
            font-size: 14.5px;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }

        .reason-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .reason-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .reason-content {
            font-size: 13.5px;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .buttons-row {
            display: flex;
            gap: 12px;
        }

        .btn-action {
            flex: 1;
            border: none;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 9999px;
            height: 44px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: opacity 0.2s ease, transform 0.1s ease;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .btn-action:hover {
            opacity: 0.9;
        }

        .btn-action:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <!-- Background glows -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container">
        <div class="blocked-card">
            <div class="icon-wrapper">
                <?php if ($status === 'temporarily_locked'): ?>
                    <!-- Clock SVG -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-color)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                <?php elseif ($status === 'disabled'): ?>
                    <!-- Ban/Slash SVG -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-color)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                    </svg>
                <?php else: ?>
                    <!-- Permanent Ban Cross User SVG -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-color)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <line x1="17" y1="8" x2="22" y2="13" />
                        <line x1="22" y1="8" x2="17" y2="13" />
                    </svg>
                <?php endif; ?>
            </div>
            
            <h1><?php echo htmlspecialchars($status_title); ?></h1>
            <p><?php echo $status_desc; ?></p>
            
            <div class="reason-box">
                <div class="reason-title">Lý do từ quản trị viên:</div>
                <div class="reason-content"><?php echo nl2br(htmlspecialchars($reason)); ?></div>
            </div>
            
            <div class="buttons-row">
                <a href="mailto:support@frest.local" class="btn-action btn-primary-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <span>Hỗ trợ</span>
                </a>
                <a href="logout.php" class="btn-action btn-logout">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
