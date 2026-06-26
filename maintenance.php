<?php
/**
 * System Maintenance Mode Page - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$maintenance_msg = getSystemSetting('maintenance_message', 'Hệ thống đang được bảo trì định kỳ để nâng cấp hiệu năng. Vui lòng quay lại sau ít phút.');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống bảo trì - Frest App</title>
    <!-- Google Fonts with Vietnamese subset support -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    <style>
        :root {
            --bg-primary: #070709;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --accent-color: #f59e0b;
            --accent-glow: rgba(245, 158, 11, 0.3);
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

        /* Abstract glowing background blobs */
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
            background: #f59e0b;
            top: -100px;
            left: -100px;
        }

        .blob-2 {
            width: 500px;
            height: 500px;
            background: #3b82f6;
            bottom: -150px;
            right: -100px;
            animation-delay: -3s;
        }

        .blob-3 {
            width: 300px;
            height: 300px;
            background: #ec4899;
            top: 40%;
            left: 60%;
            animation-delay: -6s;
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
            max-width: 500px;
            box-sizing: border-box;
        }

        .maintenance-card {
            background: rgba(15, 15, 20, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 36px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            text-align: center;
        }

        /* Glowing circular icon container */
        .icon-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.02) 100%);
            border: 1px solid rgba(245, 158, 11, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px auto;
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.15);
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
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 16px 0;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 40%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 32px 0;
        }

        .footer-info {
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 24px;
            margin-top: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .footer-info svg {
            color: var(--accent-color);
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <!-- Background glows -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="container">
        <div class="maintenance-card">
            <div class="icon-wrapper">
                <!-- Glowing Wrench SVG -->
                <svg viewBox="0 0 24 24" fill="none" stroke="url(#gradient)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <defs>
                        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fbbf24" />
                            <stop offset="100%" stop-color="#d97706" />
                        </linearGradient>
                    </defs>
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                </svg>
            </div>
            <h1>Hệ thống bảo trì</h1>
            <p><?php echo nl2br(htmlspecialchars($maintenance_msg)); ?></p>
            
            <div class="footer-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <span>Nếu cần hỗ trợ gấp, vui lòng liên hệ Ban quản trị.</span>
            </div>
        </div>
    </div>
</body>
</html>
