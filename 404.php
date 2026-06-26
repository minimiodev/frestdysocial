<?php
/**
 * 404.php - Premium Page Not Found (Glassmorphism)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$theme_class = isDarkModeActive() ? '' : 'light-theme';
?>
<!DOCTYPE html>
<html lang="vi" id="authHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Không tìm thấy trang | Frest App</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Master CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css') ?: '1'; ?>">
    <style>
        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            box-sizing: border-box;
            background-color: #070709;
            position: relative;
            overflow: hidden;
        }
        
        .light-theme.error-container {
            background-color: #e8ecf5 !important;
        }

        .error-card {
            width: 100%;
            max-width: 500px;
            padding: 48px 32px;
            text-align: center;
            border-radius: var(--radius-md);
            z-index: 2;
            position: relative;
        }

        .error-code {
            font-size: 110px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 16px;
            font-family: var(--font-heading);
            background: linear-gradient(135deg, #ef4444 0%, #f59e0b 50%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 4px 12px rgba(59, 130, 246, 0.2));
            animation: pulseGlow 3s infinite ease-in-out;
        }
        
        @keyframes pulseGlow {
            0%, 100% { filter: drop-shadow(0 4px 12px rgba(59, 130, 246, 0.2)); }
            50% { filter: drop-shadow(0 4px 24px rgba(59, 130, 246, 0.45)); }
        }

        .error-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 12px;
            font-family: var(--font-heading);
        }

        .error-desc {
            font-size: 14.5px;
            color: var(--text-secondary);
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-btn {
            font-size: 13.5px;
            font-weight: 700;
            padding: 11px 24px;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: auto;
            width: auto;
        }
        
        .error-btn.primary {
            background: var(--accent-gradient);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 14px rgba(59,130,246,0.3);
        }
        .error-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.45);
        }
        
        .error-btn.secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .error-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--text-secondary);
        }
        
        .light-theme .error-btn.secondary {
            background: rgba(0, 0, 0, 0.03);
            border-color: rgba(0, 0, 0, 0.08);
        }
        .light-theme .error-btn.secondary:hover {
            background: rgba(0, 0, 0, 0.06);
        }

        /* Ambient floating circles background */
        .ambient-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 1;
            opacity: 0.5;
            animation: floatCircle 8s infinite alternate ease-in-out;
        }
        .ambient-circle-1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59,130,246,0.25) 0%, transparent 70%);
            top: -50px;
            left: -50px;
        }
        .ambient-circle-2 {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(236,72,153,0.15) 0%, transparent 70%);
            bottom: -50px;
            right: -50px;
            animation-delay: -3s;
        }
        
        @keyframes floatCircle {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 30px) scale(1.1); }
        }
    </style>
</head>
<body class="<?php echo $theme_class; ?>">
    <div class="error-container <?php echo $theme_class; ?>">
        <!-- Background Ambient Elements -->
        <div class="ambient-circle ambient-circle-1"></div>
        <div class="ambient-circle ambient-circle-2"></div>

        <!-- Error Content Card -->
        <div class="error-card glassmorphism-card">
            <div class="error-code">404</div>
            <h1 class="error-title">Trang không tồn tại</h1>
            <p class="error-desc">
                Đường dẫn bạn yêu cầu không tồn tại, đã bị gỡ bỏ hoặc thông tin tài nguyên này không khả dụng. Vui lòng kiểm tra lại.
            </p>
            
            <div class="error-actions">
                <a href="<?php echo SITE_URL; ?>/index" class="error-btn primary">
                    <i class="fa-solid fa-house"></i> Về trang chủ
                </a>
                <button onclick="history.back()" class="error-btn secondary">
                    <i class="fa-solid fa-arrow-left"></i> Quay lại
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Set dynamic SITE_URL for javascript use if needed
        var SITE_URL = window.SITE_URL || '<?php echo SITE_URL; ?>';
        // Auto match light mode setting via document element
        const theme = '<?php echo isDarkModeActive() ? "dark" : "light"; ?>';
        if (theme === 'light') {
            document.documentElement.classList.add('light-theme');
        } else {
            document.documentElement.classList.remove('light-theme');
        }
    </script>
</body>
</html>
