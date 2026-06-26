<?php
/**
 * Direct Messaging (Chat) Page - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();
$identity = getCurrentIdentity();

// Pre-fill target contact from GET query if any
$pre_contact_type = isset($_GET['contact_type']) ? sanitize($_GET['contact_type']) : '';
$pre_contact_id   = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;

$pre_contact_data = null;
if ($pre_contact_type && $pre_contact_id > 0) {
    try {
        $db = getDB();
        if ($pre_contact_type === 'user') {
            $stmt = $db->prepare("SELECT id, username, full_name, avatar_filename, verification_type FROM users WHERE id = ?");
            $stmt->execute([$pre_contact_id]);
            $res = $stmt->fetch();
            if ($res) {
                $pre_contact_data = [
                    'contact_type' => 'user',
                    'contact_id' => intval($res['id']),
                    'username' => $res['username'],
                    'name' => $res['full_name'] ?: $res['username'],
                    'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($res['avatar_filename']),
                    'is_verified' => (!empty($res['verification_type']) && $res['verification_type'] !== 'none') ? 1 : 0
                ];
            }
        } elseif ($pre_contact_type === 'group') {
            $stmt = $db->prepare("SELECT id, name, avatar_filename FROM chat_groups WHERE id = ?");
            $stmt->execute([$pre_contact_id]);
            $res = $stmt->fetch();
            if ($res) {
                $pre_contact_data = [
                    'contact_type' => 'group',
                    'contact_id' => intval($res['id']),
                    'username' => 'group_' . $res['id'],
                    'name' => $res['name'],
                    'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($res['avatar_filename'] ?: 'group_default.png'),
                    'is_verified' => 0
                ];
            }
        } else {
            $stmt = $db->prepare("SELECT id, page_username, page_name, avatar_filename, is_verified FROM pages WHERE id = ?");
            $stmt->execute([$pre_contact_id]);
            $res = $stmt->fetch();
            if ($res) {
                $pre_contact_data = [
                    'contact_type' => 'page',
                    'contact_id' => intval($res['id']),
                    'username' => $res['page_username'],
                    'name' => $res['page_name'],
                    'avatar_url' => AVATARS_URL . '/' . htmlspecialchars($res['avatar_filename']),
                    'is_verified' => intval($res['is_verified'] ?? 0)
                ];
            }
        }
    } catch (PDOException $e) {}
}

$page_title = "Tin nhắn";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Premium chat container setup with Glassmorphism */
    .chat-wrapper {
        display: grid;
        grid-template-columns: 320px 1fr;
        height: calc(100vh - 107px);
        min-height: 480px;
        background: rgba(15, 23, 42, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg), inset 0 1px 1px rgba(255, 255, 255, 0.05);
        overflow: hidden;
        margin-top: 10px;
        position: relative;
        transition: all var(--transition-normal);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    body.light-theme .chat-wrapper {
        background: rgba(255, 255, 255, 0.65);
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), inset 0 1px 1px rgba(255, 255, 255, 0.8);
    }

    /* Sidebar Layout */
    .chat-sidebar {
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.01);
    }

    body.light-theme .chat-sidebar {
        border-right: 1px solid rgba(0, 0, 0, 0.08);
        background: rgba(15, 23, 42, 0.005);
    }

    .sidebar-header {
        padding: 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    body.light-theme .sidebar-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    }

    .active-identity-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding: 6px 0;
    }

    .active-identity-bar img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--accent-primary);
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
    }

    .active-identity-info h5 {
        font-family: var(--font-heading);
        font-size: 13.5px;
        font-weight: 800;
        margin: 0;
        color: var(--text-primary);
    }

    .active-identity-info span {
        font-size: 11px;
        color: var(--text-muted);
    }

    .chat-search-container {
        position: relative;
    }

    .chat-search-input {
        width: 100%;
        padding: 10px 14px 10px 38px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-size: 13px;
        outline: none;
        transition: all 0.25s ease;
    }

    body.light-theme .chat-search-input {
        background: rgba(15, 23, 42, 0.04);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: #0f172a;
    }

    .chat-search-input:focus {
        border-color: var(--accent-primary);
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    body.light-theme .chat-search-input:focus {
        background: rgba(15, 23, 42, 0.02);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .chat-search-container i.search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 13.5px;
        pointer-events: none;
    }

    body.light-theme .chat-search-container i.search-icon {
        color: rgba(15, 23, 42, 0.4);
    }

    /* List Containers */
    .chat-list-scrollable {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 10px 0;
    }

    .frest-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        margin: 2px 8px;
        border-radius: var(--radius-sm);
    }

    .frest-item:hover {
        background: rgba(255, 255, 255, 0.04);
        transform: translateX(3px);
    }

    body.light-theme .frest-item:hover {
        background: rgba(15, 23, 42, 0.03);
    }

    .frest-item.active {
        background: var(--accent-gradient) !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    }
    
    .frest-item.active .frest-name,
    .frest-item.active .frest-time,
    .frest-item.active .frest-preview {
        color: #ffffff !important;
    }

    .frest-avatar-wrapper {
        position: relative;
        flex-shrink: 0;
    }

    .frest-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    body.light-theme .frest-avatar {
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .identity-badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #1d9bf0 !important;
        color: #fff !important;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-secondary) !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        z-index: 6;
    }

    body.light-theme .identity-badge {
        border-color: #ffffff !important;
    }

    .frest-details {
        flex: 1;
        min-width: 0;
    }

    .frest-meta {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 4px;
    }

    .frest-name {
        font-family: var(--font-heading);
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    body.light-theme .frest-name {
        color: #0f172a;
    }

    .frest-time {
        font-size: 10.5px;
        color: var(--text-muted);
        flex-shrink: 0;
    }

    body.light-theme .frest-time {
        color: rgba(15, 23, 42, 0.5);
    }

    .frest-preview {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    body.light-theme .frest-preview {
        color: rgba(15, 23, 42, 0.6);
    }

    .frest-item.unread .frest-name {
        font-weight: 800;
    }

    .frest-item.unread .frest-preview {
        color: var(--text-primary);
        font-weight: 600;
    }

    body.light-theme .frest-item.unread .frest-preview {
        color: #0f172a;
    }

    .unread-counter {
        min-width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--accent-primary);
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    /* Main Chat Panel */
    .chat-panel {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.002);
    }

    /* Active chat state panel */
    .chat-panel-active {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        position: relative;
    }

    .chat-panel-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--text-secondary);
        padding: 24px;
        text-align: center;
    }

    body.light-theme .chat-panel-empty {
        color: rgba(15, 23, 42, 0.5);
    }

    .chat-panel-empty i {
        font-size: 48px;
        opacity: 0.15;
        margin-bottom: 18px;
    }

    .chat-header {
        flex-shrink: 0;
        height: 64px;
        padding: 0 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(15, 23, 42, 0.2);
        z-index: 10;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    body.light-theme .chat-header {
        background: rgba(255, 255, 255, 0.4);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    }

    .chat-contact-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chat-contact-profile img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    body.light-theme .chat-contact-profile img {
        border-color: rgba(0, 0, 0, 0.08);
    }

    .chat-contact-info h4 {
        font-family: var(--font-heading);
        font-size: 14.5px;
        font-weight: 800;
        margin: 0;
        color: var(--text-primary);
    }

    body.light-theme .chat-contact-info h4 {
        color: #0f172a;
    }

    .chat-contact-info span {
        font-size: 11.5px;
        color: var(--text-muted);
    }

    body.light-theme .chat-contact-info span {
        color: rgba(15, 23, 42, 0.5);
    }

    .chat-header-actions {
        display: flex;
        gap: 8px;
    }

    .chat-header-btn {
        flex: none !important;
        height: 32px !important;
        padding: 0 14px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        border-radius: var(--radius-full) !important;
        display: inline-flex;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        text-decoration: none !important;
    }

    body.light-theme .chat-header-btn {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }

    .chat-header-btn.secondary {
        background: rgba(255, 255, 255, 0.05) !important;
        color: var(--text-primary) !important;
    }

    body.light-theme .chat-header-btn.secondary {
        background: rgba(15, 23, 42, 0.04) !important;
        color: #0f172a !important;
    }

    .chat-header-btn.secondary:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        transform: translateY(-2px) !important;
    }

    body.light-theme .chat-header-btn.secondary:hover {
        background: rgba(15, 23, 42, 0.08) !important;
        border-color: rgba(0, 0, 0, 0.15) !important;
    }

    .mobile-back-btn {
        display: none;
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: 18px;
        cursor: pointer;
        padding: 8px;
        margin-right: 8px;
    }

    body.light-theme .mobile-back-btn {
        color: #0f172a;
    }

    /* Messages Area with minimal premium pattern */
    .messages-container {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        position: relative;
        background-color: rgba(15, 23, 42, 0.1);
        /* Subtle dots pattern */
        background-image: radial-gradient(rgba(255, 255, 255, 0.03) 1.5px, transparent 1.5px);
        background-size: 24px 24px;
        scroll-behavior: smooth;
    }

    body.light-theme .messages-container {
        background-color: rgba(248, 250, 252, 0.7);
        background-image: radial-gradient(rgba(15, 23, 42, 0.04) 1.5px, transparent 1.5px);
    }

    .msg-group {
        display: flex;
        flex-direction: column;
        max-width: 65%;
        opacity: 1;
        transition: opacity 0.3s;
    }

    .msg-group.my-msg {
        align-self: flex-end;
    }

    .msg-group.incoming-msg {
        align-self: flex-start;
    }

    /* Message appear animation class */
    @keyframes messageAppear {
        from {
            opacity: 0;
            transform: translateY(12px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .msg-group.message-appear {
        animation: messageAppear 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    .msg-bubble {
        padding: 12px 18px;
        border-radius: 20px;
        font-size: 13.5px;
        line-height: 1.5;
        word-break: break-word;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all var(--transition-normal);
        position: relative;
        user-select: text;
    }

    .incoming-msg .msg-bubble {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
        border-bottom-left-radius: 4px;
    }

    body.light-theme .incoming-msg .msg-bubble {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        color: #0f172a;
    }

    .my-msg .msg-bubble {
        background: var(--accent-gradient);
        color: #ffffff;
        border-bottom-right-radius: 4px;
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
    }

    .msg-time {
        font-size: 10px;
        color: var(--text-muted);
        margin-top: 4px;
        align-self: flex-end;
    }

    body.light-theme .msg-time {
        color: rgba(15, 23, 42, 0.4);
    }

    .incoming-msg .msg-time {
        align-self: flex-start;
    }

    /* Input Footer Area with Glassmorphism */
    .chat-footer {
        flex-shrink: 0;
        padding: 16px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    body.light-theme .chat-footer {
        background: rgba(255, 255, 255, 0.5);
        border-top: 1px solid rgba(0, 0, 0, 0.08);
    }

    .chat-input-form {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .chat-text-input {
        flex: 1;
        min-width: 0;
        height: 44px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 22px;
        color: var(--text-primary);
        font-size: 13.5px;
        outline: none;
        transition: all 0.25s ease;
    }

    body.light-theme .chat-text-input {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        color: #0f172a;
    }

    .chat-text-input:focus {
        border-color: var(--accent-primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        background: rgba(255, 255, 255, 0.08);
    }

    body.light-theme .chat-text-input:focus {
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .chat-send-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--accent-gradient);
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        flex-shrink: 0;
    }

    .chat-send-btn:hover {
        transform: scale(1.08) translateY(-1.5px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    }

    .chat-send-btn:active {
        transform: scale(0.95);
    }

    .mobile-home-back-btn {
        display: none;
    }

    /* Responsive Views */
    @media (max-width: 768px) {
        .mobile-bottom-nav {
            display: none !important;
        }

        .mobile-home-back-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            font-size: 15px;
            border-radius: 50%;
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
            flex-shrink: 0;
            margin-right: 4px;
            transition: all 0.2s;
        }
        .mobile-home-back-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        body.light-theme .mobile-home-back-btn {
            background: rgba(0, 0, 0, 0.05);
            border-color: rgba(0, 0, 0, 0.08);
            color: #0f172a;
        }
        body.light-theme .mobile-home-back-btn:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .header {
            display: none !important;
        }

        footer.footer, .footer-bottom, footer, .site-footer {
            display: none !important;
        }

        body {
            padding-bottom: 0 !important;
            overflow: hidden;
        }

        .container {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }

        .chat-wrapper {
            grid-template-columns: 1fr;
            height: 100dvh !important;
            max-height: 100dvh !important;
            min-height: 0 !important;
            margin-top: 0 !important;
            border: none !important;
            border-radius: 0 !important;
        }

        .chat-wrapper.active-chat-mode {
            height: 100dvh !important;
            max-height: 100dvh !important;
        }

        .messages-container {
            padding: 12px 10px !important;
            gap: 10px !important;
        }

        .chat-footer {
            padding: 10px 12px !important;
        }

        .chat-sidebar {
            display: flex;
        }

        .chat-panel {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 50;
        }

        .chat-wrapper.active-chat-mode .chat-sidebar {
            display: none;
        }

        .chat-wrapper.active-chat-mode .chat-panel {
            display: flex;
        }

        .mobile-back-btn {
            display: inline-block;
        }

        .msg-group {
            max-width: 85% !important;
        }

        .modal-content {
            padding: 16px !important;
            border-radius: var(--radius-md) !important;
            max-width: 95% !important;
        }

        .selected-member-badge {
            padding: 3px 6px !important;
            font-size: 11px !important;
        }

        .msg-bubble-wrapper.show-actions .msg-actions {
            opacity: 1 !important;
            visibility: visible !important;
        }

        .chat-header-btn {
            font-size: 11px !important;
            padding: 0 10px !important;
            height: 28px !important;
        }

        @media (max-width: 420px) {
            .chat-header-btn {
                padding: 0 !important;
                border-radius: 50% !important;
                width: 32px !important;
                height: 32px !important;
                flex: none !important;
                display: inline-flex;
                align-items: center !important;
                justify-content: center !important;
            }
            .chat-header-btn span { display: none !important; }
            .chat-header-btn i   { margin: 0 !important; font-size: 13px !important; }
        }
    }

    /* Media content constraints */
    .msg-bubble img {
        max-width: 100%;
        max-height: 320px;
        width: auto;
        height: auto;
        border-radius: 10px;
        display: block;
        object-fit: contain;
    }
    .msg-bubble video,
    .chat-attachment-video video {
        max-width: 340px;
        width: 100%;
        max-height: 220px;
        border-radius: 10px;
        display: block;
        background: #000;
        object-fit: contain;
    }

    /* Hover actions on message bubbles */
    .msg-bubble-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
    }

    .my-msg .msg-bubble-wrapper {
        flex-direction: row-reverse;
    }

    .msg-actions {
        opacity: 0;
        visibility: hidden;
        display: flex;
        gap: 4px;
        transition: opacity 0.2s, visibility 0.2s;
    }

    .msg-bubble-wrapper:hover .msg-actions {
        opacity: 1;
        visibility: visible;
    }

    .action-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        transition: all 0.2s;
    }

    body.light-theme .action-btn {
        background: rgba(15, 23, 42, 0.04);
        border-color: rgba(15, 23, 42, 0.08);
        color: rgba(15, 23, 42, 0.6);
    }

    .action-btn:hover {
        color: var(--text-primary);
        background: rgba(255, 255, 255, 0.15);
    }

    body.light-theme .action-btn:hover {
        color: #0f172a;
        background: rgba(15, 23, 42, 0.08);
    }

    /* Message reactions stack styling */
    .msg-reactions-stack {
        display: flex;
        gap: 3px;
        position: absolute;
        bottom: -10px;
        right: 12px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 2px 6px;
        box-shadow: var(--shadow-sm);
        z-index: 5;
    }

    body.light-theme .msg-reactions-stack {
        background: #ffffff;
        border-color: rgba(0, 0, 0, 0.08);
    }

    .my-msg .msg-reactions-stack {
        right: auto;
        left: 12px;
    }

    .reaction-badge {
        font-size: 11px;
        cursor: pointer;
        user-select: none;
    }

    /* Recalled message style */
    .recalled-text {
        font-style: italic;
        opacity: 0.7;
        color: var(--text-muted) !important;
    }

    .msg-bubble.recalled-bubble {
        background: rgba(255, 255, 255, 0.04) !important;
        color: var(--text-muted) !important;
        border: 1px dashed rgba(255, 255, 255, 0.1) !important;
        box-shadow: none !important;
    }

    body.light-theme .msg-bubble.recalled-bubble {
        background: rgba(15, 23, 42, 0.02) !important;
        border: 1px dashed rgba(0, 0, 0, 0.08) !important;
    }

    .msg-bubble.media-only-bubble {
        background: none !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
    }

    /* Emoji Picker Panel Slide Up Transition */
    .emoji-picker-panel {
        position: absolute;
        bottom: 100%;
        margin-bottom: 8px;
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 8px 12px;
        display: flex;
        gap: 8px;
        box-shadow: var(--shadow-lg);
        z-index: 100;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        opacity: 0;
        transform: translateY(10px) scale(0.95);
        pointer-events: none;
        transition: opacity 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .emoji-picker-panel.active {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .incoming-msg .emoji-picker-panel {
        left: 0;
        right: auto;
    }

    .my-msg .emoji-picker-panel {
        right: 0;
        left: auto;
    }

    body.light-theme .emoji-picker-panel {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .emoji-picker-panel span {
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .emoji-picker-panel span:hover {
        transform: scale(1.3) translateY(-2px);
    }

    /* Group modal & Attachment styles */
    .custom-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    .modal-content {
        width: 480px;
        max-width: 90%;
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-lg);
        color: var(--text-primary);
        position: relative;
    }
    body.light-theme .modal-content {
        background: rgba(255, 255, 255, 0.95);
        border-color: rgba(0,0,0,0.08);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        color: #0f172a;
    }
    .member-select-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: background 0.2s;
    }
    .member-select-item:hover {
        background: rgba(255,255,255,0.05);
    }
    body.light-theme .member-select-item:hover {
        background: rgba(15, 23, 42, 0.03);
    }
    .selected-member-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: rgba(59, 130, 246, 0.15);
        color: var(--accent-primary);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: var(--radius-full);
        font-size: 11.5px;
        font-weight: 600;
    }
    .chat-attachment-doc {
        background: rgba(255,255,255,0.05);
    }
    body.light-theme .chat-attachment-doc {
        background: rgba(15, 23, 42, 0.03);
    }

    /* Floating Heart Animation styling */
    @keyframes heartPop {
        0% {
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
        }
        50% {
            transform: translate(-50%, -50%) scale(1.4);
            opacity: 0.95;
        }
        80% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.85;
        }
        100% {
            transform: translate(-50%, -80%) scale(0.7);
            opacity: 0;
        }
    }
    .floating-heart {
        position: absolute;
        font-size: 32px;
        color: #ef4444;
        pointer-events: none;
        animation: heartPop 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        z-index: 999;
        text-shadow: 0 2px 10px rgba(239, 68, 68, 0.3);
    }

    /* Drag & Drop File Upload Overlay */
    .chat-drag-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
        color: #fff;
        border: 3px dashed var(--accent-primary);
        border-radius: var(--radius-lg);
    }
    body.light-theme .chat-drag-overlay {
        background: rgba(255, 255, 255, 0.9);
        color: #0f172a;
    }
    .chat-drag-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }
    .chat-drag-overlay i {
        font-size: 48px;
        color: var(--accent-primary);
        margin-bottom: 12px;
        animation: dragBounce 1.5s infinite ease-in-out;
    }
    @keyframes dragBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
</style>

<div class="container" style="max-width: 1000px; padding-top: 15px; padding-bottom: 20px;">
    
    <div class="chat-wrapper" id="chatWrapper">
        <!-- Sidebar containing frests -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <!-- Acting Identity -->
                <div class="active-identity-bar" style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <a href="index.php" class="mobile-home-back-btn" title="Quay lại trang chủ">
                            <i class="fa-solid fa-house"></i>
                        </a>
                        <img src="<?php echo AVATARS_URL . '/' . htmlspecialchars($identity['avatar'] ?: 'avatar_default.png'); ?>" alt="avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-primary); flex-shrink: 0;">
                        <div class="active-identity-info" style="min-width: 0;">
                            <h5 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; font-size: 13px; font-weight: 800; color: var(--text-primary);"><?php echo htmlspecialchars($identity['name']); ?></h5>
                            <span style="display: block; font-size: 11px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">@<?php echo htmlspecialchars($identity['username']); ?></span>
                        </div>
                    </div>
                    <button type="button" class="action-btn" onclick="openCreateGroupModal()" title="Tạo nhóm chat mới" style="width: 32px; height: 32px; font-size: 13px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); color: var(--accent-primary); cursor: pointer; transition: all 0.2s; flex-shrink: 0;">
                        <i class="fa-solid fa-user-group"></i>
                    </button>
                </div>

                <!-- Search box -->
                <div class="chat-search-container">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="chatSearch" class="chat-search-input" placeholder="Tìm người dùng hoặc trang..." autocomplete="off">
                </div>
            </div>

            <!-- Scrollable list -->
            <div class="chat-list-scrollable" id="chatListContainer">
                <!-- Frests list -->
                <div id="frestList">
                    <div style="padding:40px 20px; text-align:center; color:var(--text-muted); font-size:12.5px;">
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size:20px; margin-bottom:8px; display:block;"></i>
                        Đang tải danh sách...
                    </div>
                </div>
                <!-- Search results list -->
                <div id="searchResultsList" style="display: none;"></div>
            </div>
        </div>

        <!-- Chat dialog window -->
        <div class="chat-panel">
            <div class="chat-panel-empty" id="chatEmptyState">
                <i class="fa-regular fa-comments"></i>
                <h4 style="font-family:var(--font-heading); font-size:16px; font-weight:800; margin:0 0 6px 0; color:var(--text-primary);">Hộp thư Frest Chat</h4>
                <p style="font-size:13px; margin:0; line-height:1.5;">Hãy chọn một cuộc hội thoại ở thanh bên trái hoặc tìm kiếm bạn bè để bắt đầu trò chuyện ngay lập tức.</p>
            </div>

            <div class="chat-panel-active" id="chatActiveState" style="display: none; flex-direction: column; height: 100%; min-height: 0; overflow: hidden;">
                <!-- Drag and Drop Overlay -->
                <div class="chat-drag-overlay" id="chatDragOverlay">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <h4 style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; margin: 0 0 6px 0; color: inherit;">Thả tệp tin tại đây</h4>
                    <p style="font-size: 12.5px; margin: 0; opacity: 0.8;">Tự động đính kèm hình ảnh, video hoặc tài liệu vào tin nhắn</p>
                </div>

                <!-- Header -->
                <div class="chat-header">
                    <div class="chat-contact-profile">
                        <button class="mobile-back-btn" onclick="exitChatView()"><i class="fa-solid fa-arrow-left"></i></button>
                        <div class="chat-header-avatar-wrapper" style="position: relative; display: inline-flex; flex-shrink: 0;">
                            <img id="activeContactAvatar" src="" alt="contact-avatar">
                            <span id="activeContactOnlineDot" class="online-dot" title="Đang hoạt động" style="display: none; position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; border-radius: 50%; background-color: var(--success); border: 2px solid var(--bg-secondary); box-shadow: 0 0 4px var(--success); z-index: 5;"></span>
                        </div>
                        <div class="chat-contact-info">
                            <h4 id="activeContactName">--</h4>
                            <span id="activeContactHandle">--</span>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <a id="activeContactProfileLink" href="#" class="chat-header-btn secondary">
                            <i class="fa-regular fa-user"></i> <span>Xem hồ sơ</span>
                        </a>
                        <button id="groupInfoBtn" onclick="openGroupInfoModal()" class="chat-header-btn secondary" style="display:none;">
                            <i class="fa-solid fa-circle-info"></i> <span>Thông tin nhóm</span>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div class="messages-container" id="messagesContainer">
                    <!-- Dynamic messages -->
                </div>

                <!-- Footer input -->
                <div class="chat-footer">
                    <!-- Edit indicator -->
                    <div id="chatEditIndicator" style="display: none; align-items: center; justify-content: space-between; padding: 8px 14px; background: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--accent-primary); border-radius: var(--radius-sm); margin-bottom: 10px; font-size: 12px; color: var(--text-secondary);">
                        <span><i class="fa-solid fa-pen" style="margin-right: 6px; color: var(--accent-primary);"></i>Đang chỉnh sửa tin nhắn... <button type="button" onclick="cancelEditMessage()" style="background: none; border: none; color: var(--accent-primary); font-weight: 700; cursor: pointer; padding: 0 4px; margin-left: 6px;">Hủy (Esc)</button></span>
                    </div>

                    <!-- Attachment indicator -->
                    <div id="attachmentPreviewBar" style="display: none; align-items: center; justify-content: space-between; padding: 8px 14px; background: rgba(255, 255, 255, 0.05); border-left: 3px solid var(--accent-primary); border-radius: var(--radius-sm); margin-bottom: 10px; font-size: 12px;">
                        <span id="attachmentInfo" style="display: flex; align-items: center; gap: 8px; flex: 1; color: var(--text-secondary); min-width: 0;">
                            <i class="fa-solid fa-paperclip" style="color: var(--accent-primary);"></i>
                            <span id="attachmentName" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">tệp_đính_kèm.zip</span>
                        </span>
                        <button type="button" onclick="cancelAttachment()" style="background: none; border: none; color: var(--danger); font-weight: 700; cursor: pointer; padding: 0 4px; margin-left: 6px;">Hủy (X)</button>
                    </div>

                    <form id="chatSendForm" class="chat-input-form" onsubmit="handleSendMessage(event)">
                        <input type="file" id="chatFileInput" style="display: none;" onchange="handleFileSelected(event)">
                        <button type="button" onclick="document.getElementById('chatFileInput').click()" title="Đính kèm hình ảnh/tệp tin" style="width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; transition: all 0.2s; flex-shrink: 0;">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>
                        <input type="text" id="chatMessageInput" class="chat-text-input" placeholder="Nhập tin nhắn..." autocomplete="off">
                        <button type="submit" class="chat-send-btn" id="chatSendBtn"><i class="fa-regular fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Create Group Modal -->
    <div id="createGroupModal" class="custom-modal">
        <div class="modal-content">
            <button onclick="closeCreateGroupModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; color: var(--text-primary);"><i class="fa-solid fa-user-group" style="color: var(--accent-primary);"></i> Tạo nhóm chat mới</h3>
            
            <form id="createGroupForm" onsubmit="handleCreateGroup(event)">
                <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 14px;">
                    <div style="position: relative; width: 60px; height: 60px; border-radius: 50%; overflow: hidden; border: 2px dashed var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;" onclick="document.getElementById('groupAvatarInput').click()">
                        <img id="groupAvatarPreview" src="uploads/avatars/group_default.png" style="width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                            <i class="fa-solid fa-camera" style="color: #fff; font-size: 12px;"></i>
                        </div>
                    </div>
                    <input type="file" id="groupAvatarInput" name="avatar" accept="image/*" style="display: none;" onchange="previewGroupAvatar(event)">
                    
                    <div style="flex: 1;">
                        <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">TÊN NHÓM *</label>
                        <input type="text" id="groupNameInput" class="chat-search-input" placeholder="Nhập tên nhóm..." required style="padding: 10px 14px;">
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">MÔ TẢ NHÓM</label>
                    <textarea id="groupDescInput" class="chat-search-input" placeholder="Mô tả về nhóm chat..." style="padding: 10px 14px; height: 50px; resize: none; font-family: inherit;"></textarea>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">THÊM THÀNH VIÊN</label>
                    <div class="chat-search-container" style="margin-bottom: 8px;">
                        <i class="fa-solid fa-magnifying-glass search-icon" style="left: 12px; font-size: 12px;"></i>
                        <input type="text" id="modalMemberSearch" class="chat-search-input" placeholder="Tìm kiếm tên..." style="padding-left: 32px; padding-top: 8px; padding-bottom: 8px;">
                    </div>
                    
                    <div id="modalSearchMembersResults" style="max-height: 110px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 4px; margin-bottom: 8px; display: none;"></div>
                    
                    <div style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">THÀNH VIÊN ĐÃ CHỌN:</div>
                    <div id="selectedMembersContainer" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 70px; overflow-y: auto; padding: 4px 0;">
                        <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Chưa chọn thành viên nào</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="profile-btn secondary" onclick="closeCreateGroupModal()" style="font-size: 12px; padding: 6px 12px;">Hủy</button>
                    <button type="submit" class="profile-btn primary" style="font-size: 12px; padding: 6px 12px; background: var(--accent-gradient); color: #fff;">Tạo nhóm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Premium Group Info Modal -->
    <div id="groupInfoModal" class="custom-modal">
        <div class="modal-content" style="width: 440px;">
            <button onclick="closeGroupInfoModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; color: var(--text-primary);"><i class="fa-solid fa-circle-info" style="color: var(--accent-primary);"></i> Thông tin nhóm chat</h3>
            
            <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                <img id="infoGroupAvatar" src="uploads/avatars/group_default.png" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
                <div>
                    <h4 id="infoGroupName" style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; margin: 0; color: var(--text-primary);">Tên nhóm</h4>
                    <p id="infoGroupDesc" style="font-size: 12.5px; color: var(--text-secondary); margin: 6px 0 0 0; line-height: 1.4;">Mô tả nhóm</p>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <h5 style="font-size: 12px; font-weight: 800; color: var(--text-muted); margin: 0 0 8px 0;">DANH SÁCH THÀNH VIÊN (<span id="infoGroupCount">0</span>)</h5>
                <div id="infoGroupMembersList" style="max-height: 180px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 4px;">
                    <!-- Members row list -->
                </div>
            </div>

            <div id="groupInfoModalActions" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; border-top: 1px solid var(--border-color); padding-top: 16px; justify-content: flex-start;">
                <button type="button" id="leaveGroupBtn" class="profile-btn" style="font-size: 11.5px; padding: 6px 12px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer;" onclick="handleLeaveGroup()">
                    <i class="fa-solid fa-right-from-bracket" style="margin-right: 4px;"></i> Rời nhóm
                </button>
                <button type="button" id="deleteGroupBtn" class="profile-btn" style="font-size: 11.5px; padding: 6px 12px; background: var(--danger); color: #fff; border: 1px solid var(--danger); cursor: pointer; display: none;" onclick="handleDeleteGroup()">
                    <i class="fa-solid fa-trash-can" style="margin-right: 4px;"></i> Xóa nhóm
                </button>
                <button type="button" id="clearGroupConvBtn" class="profile-btn" style="font-size: 11.5px; padding: 6px 12px; background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); cursor: pointer;" onclick="handleClearGroupConversation()">
                    <i class="fa-solid fa-circle-minus" style="margin-right: 4px;"></i> Xóa cuộc trò chuyện
                </button>
            </div>

            <div style="display: flex; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 12px;">
                <button type="button" class="profile-btn secondary" onclick="closeGroupInfoModal()" style="font-size: 12px; padding: 6px 12px;">Đóng</button>
            </div>
        </div>
    </div>

</div>

<!-- Lightbox Modal for Chat Images -->
<div id="chat-lightbox-modal" class="chat-lightbox-overlay" onclick="closeChatLightbox()">
    <button class="chat-lightbox-close" onclick="closeChatLightbox()">&times;</button>
    <div class="chat-lightbox-content" onclick="event.stopPropagation()">
        <img id="chat-lightbox-img" src="" alt="Zoom Image">
    </div>
</div>

<script>
    var activeContact = null; // { type: 'user'|'page', id: INT, name: '', username: '', avatar_url: '' }
    var chatPollInterval = null;
    var chatEventSource = null;
    var chatSseTimeout = null; // Reconnect and logic cleanup timeout
    var windowLoaded = false;
    var lastMessageCount = 0;

    window.addEventListener('load', function() {
        windowLoaded = true;
        if (activeContact && !chatEventSource) {
            connectChatSSE();
        }
    });
    var lastMessagesJSONCached = "";
    var messageCache = {};
    var SITE_URL = '<?php echo SITE_URL; ?>';
    var myIdentity = { type: '<?php echo $identity['type']; ?>', id: <?php echo intval($identity['id']); ?> };

    // Load URL params if redirected from profile page
    var prefilledContact = <?php echo $pre_contact_data ? json_encode($pre_contact_data) : 'null'; ?>;

    document.addEventListener("DOMContentLoaded", function () {
        loadChatList();

        // Prefill contact logic
        if (prefilledContact) {
            selectContact(prefilledContact.contact_type, prefilledContact.contact_id, prefilledContact.name, prefilledContact.username, prefilledContact.avatar_url, prefilledContact.is_verified);
        }

        // Auto open create group modal if action=create_group is in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'create_group') {
            openCreateGroupModal();
        }

        // Search keyup debouncing
        const searchInput = document.getElementById("chatSearch");
        let searchTimeout = null;

        searchInput.addEventListener("input", function () {
            clearTimeout(searchTimeout);
            const query = searchInput.value.trim();

            if (query.length === 0) {
                document.getElementById("searchResultsList").style.display = "none";
                document.getElementById("frestList").style.display = "block";
                return;
            }

            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        // Handle Escape key to cancel editing
        document.getElementById("chatMessageInput").addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                cancelEditMessage();
            }
        });

        // Close emoji pickers when clicking outside
        document.addEventListener("click", function (e) {
            if (!e.target.closest(".action-btn") && !e.target.closest(".emoji-picker-panel")) {
                closeAllEmojiPickers();
            }
            if (!e.target.closest(".msg-bubble-wrapper")) {
                document.querySelectorAll(".msg-bubble-wrapper").forEach(w => w.classList.remove("show-actions"));
            }
        });

        // Drag and Drop File Upload for Active Chat Panel
        const chatActiveState = document.getElementById("chatActiveState");
        const chatDragOverlay = document.getElementById("chatDragOverlay");

        if (chatActiveState && chatDragOverlay) {
            let dragCounter = 0;

            chatActiveState.addEventListener("dragenter", function(e) {
                e.preventDefault();
                dragCounter++;
                chatDragOverlay.classList.add("active");
            });

            chatActiveState.addEventListener("dragover", function(e) {
                e.preventDefault();
            });

            chatActiveState.addEventListener("dragleave", function(e) {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    chatDragOverlay.classList.remove("active");
                }
            });

            chatActiveState.addEventListener("drop", function(e) {
                e.preventDefault();
                dragCounter = 0;
                chatDragOverlay.classList.remove("active");

                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    const file = e.dataTransfer.files[0];
                    uploadFile(file);
                }
            });
        }

        // Double click message bubble to React with ❤️ (Facebook Messenger Style)
        const messagesContainer = document.getElementById("messagesContainer");
        if (messagesContainer) {
            messagesContainer.addEventListener("dblclick", function(e) {
                const bubble = e.target.closest(".msg-bubble");
                if (bubble) {
                    // Do not react if message is recalled
                    if (bubble.classList.contains("recalled-bubble")) return;

                    const wrapper = bubble.closest(".msg-bubble-wrapper");
                    if (wrapper) {
                        const msgId = parseInt(wrapper.dataset.msgId);
                        if (!isNaN(msgId)) {
                            // Show floating heart at click coordinates
                            showFloatingHeart(e);
                            // Send reaction ❤️
                            sendChatMessageReaction(msgId, "❤️");
                        }
                    }
                }
            });
        }
    });

    // Render floating pop-up heart micro-interaction
    function showFloatingHeart(e) {
        const heart = document.createElement("div");
        heart.className = "floating-heart";
        heart.innerHTML = "❤️";
        heart.style.left = `${e.clientX}px`;
        heart.style.top = `${e.clientY}px`;
        heart.style.position = "fixed";
        document.body.appendChild(heart);
        setTimeout(() => {
            heart.remove();
        }, 800);
    }

    // Common file uploader function
    function uploadFile(file) {
        if (!file) return;
        
        const bar = document.getElementById("attachmentPreviewBar");
        const nameSpan = document.getElementById("attachmentName");
        const sendBtn = document.getElementById("chatSendBtn");
        
        nameSpan.textContent = `Đang tải lên: ${file.name}...`;
        bar.style.display = "flex";
        sendBtn.disabled = true;
        
        const formData = new FormData();
        formData.append("chat_file", file);
        
        fetch("upload_chat_file.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentAttachment = {
                    file_type: data.file_type,
                    filename: data.filename,
                    original_filename: data.original_filename
                };
                nameSpan.textContent = `${data.original_filename} (Sẵn sàng gửi)`;
                sendBtn.disabled = false;
            } else {
                alert("Tải lên thất bại: " + data.message);
                cancelAttachment();
            }
        })
        .catch(err => {
            console.error("Error uploading file:", err);
            alert("Lỗi kết nối khi tải lên tệp.");
            cancelAttachment();
        });
    }

    // Load conversations list
    function loadChatList() {
        fetch('get_chat_list.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderChatList(data.contacts);
                }
            })
            .catch(err => console.error("Error loading chat list:", err));
    }

    // Render active conversations
    function renderChatList(contacts) {
        const frestList = document.getElementById("frestList");
        frestList.innerHTML = "";

        if (contacts.length === 0) {
            frestList.innerHTML = `<div style="padding:60px 20px; text-align:center; color:var(--text-muted); font-size:12.5px;">Chưa có hội thoại nào.</div>`;
            return;
        }

        contacts.forEach(c => {
            const isActive = activeContact && activeContact.type === c.contact_type && activeContact.id === c.contact_id;
            const isUnread = c.unread_count > 0;
            const timeFormatted = formatTime(c.last_message_time);
            const previewText = c.is_my_message ? `Bạn: ${c.last_message}` : c.last_message;

            const badgeHTML = c.is_verified ? `<span class="identity-badge" title="Đã xác minh"><svg viewBox="0 0 24 24" width="9" height="9" style="fill: none; stroke: #ffffff; stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; display: block;"><polyline points="20 6 9 17 4 12"></polyline></svg></span>` : '';
            const unreadBadge = isUnread ? `<span class="unread-counter">${c.unread_count}</span>` : '';

            const item = document.createElement("div");
            item.className = `frest-item ${isActive ? 'active' : ''} ${isUnread ? 'unread' : ''}`;
            
            // Hold / Long-press logic for mobile and context menu for desktop
            let pressTimer;
            item.addEventListener('touchstart', (e) => {
                item.dataset.longPressed = "false";
                pressTimer = setTimeout(() => {
                    item.dataset.longPressed = "true";
                    handleDeleteConversation(c.contact_type, c.contact_id, c.name);
                }, 800);
            }, { passive: true });

            item.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
            }, { passive: true });

            item.addEventListener('touchmove', () => {
                clearTimeout(pressTimer);
            }, { passive: true });

            item.onclick = () => {
                if (item.dataset.longPressed === "true") {
                    item.dataset.longPressed = "false";
                    return;
                }
                selectContact(c.contact_type, c.contact_id, c.name, c.username, c.avatar_url, c.is_verified, c.is_online ? 1 : 0);
            };

            item.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                handleDeleteConversation(c.contact_type, c.contact_id, c.name);
            });

            item.innerHTML = `
                <div class="frest-avatar-wrapper" style="position: relative;">
                    <img class="frest-avatar" src="${c.avatar_url}" alt="avatar">
                    ${badgeHTML}
                    ${c.is_online ? `<span class="online-dot" title="Đang hoạt động" style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; border-radius: 50%; background-color: var(--success); border: 2px solid var(--bg-secondary); box-shadow: 0 0 4px var(--success); z-index: 5;"></span>` : ''}
                </div>
                <div class="frest-details">
                    <div class="frest-meta">
                        <h6 class="frest-name">${escapeHTML(c.name)}</h6>
                        <span class="frest-time">${timeFormatted}</span>
                    </div>
                    <p class="frest-preview">${escapeHTML(previewText)}</p>
                </div>
                ${unreadBadge}
            `;
            frestList.appendChild(item);
        });
    }

    // Select a contact to chat
    function selectContact(type, id, name, username, avatarUrl, isVerified = 0, isOnline = 0) {
        activeContact = { type, id, name, username, avatar_url: avatarUrl, is_verified: isVerified, is_online: isOnline };

        // Hide empty view, show active view
        document.getElementById("chatEmptyState").style.display = "none";
        document.getElementById("chatActiveState").style.display = "flex";
        document.getElementById("chatWrapper").classList.add("active-chat-mode");

        // Hide mobile bottom navigation to maximize screen space
        const mobNav = document.querySelector('.mobile-bottom-nav');
        if (mobNav) {
            mobNav.style.setProperty('display', 'none', 'important');
        }

        // Header info
        document.getElementById("activeContactAvatar").src = avatarUrl;
        const onlineDot = document.getElementById("activeContactOnlineDot");
        if (onlineDot) {
            onlineDot.style.display = (type === 'user' && parseInt(isOnline) === 1) ? 'block' : 'none';
        }
        
        const nameHeader = document.getElementById("activeContactName");
        nameHeader.innerHTML = escapeHTML(name);
        if (parseInt(isVerified) === 1 && type !== 'group') {
            nameHeader.innerHTML += ` <svg class="verified-badge-svg" viewBox="0 0 24 24" width="16" height="16" style="display:inline-flex; align-items:center; align-self:center; margin-left:4px; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15)); vertical-align: middle;" title="Đã xác minh"><g fill-rule="evenodd" transform="translate(-92)"><path fill="#1d9bf0" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/><path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/></g></svg>`;
        }

        // Toggle buttons based on contact type
        const profileLink = document.getElementById("activeContactProfileLink");
        const groupInfoBtn = document.getElementById("groupInfoBtn");

        if (type === 'group') {
            profileLink.style.display = "none";
            groupInfoBtn.style.display = "inline-flex";
            document.getElementById("activeContactHandle").textContent = "Nhóm chat";
        } else {
            profileLink.style.display = "inline-flex";
            groupInfoBtn.style.display = "none";
            profileLink.href = type === 'page' ? `page.php?username=${username}` : `profile.php?username=${username}`;
            document.getElementById("activeContactHandle").textContent = "@" + username;
        }

        // Clear search & reset view
        document.getElementById("chatSearch").value = "";
        document.getElementById("searchResultsList").style.display = "none";
        document.getElementById("frestList").style.display = "block";

        // Fetch messages immediately
        lastMessageCount = 0;
        lastMessagesJSONCached = "";
        document.getElementById("messagesContainer").innerHTML = `<div style="padding:40px; text-align:center; color:var(--text-muted);"><i class="fa-solid fa-circle-notch fa-spin"></i> Đang tải tin nhắn...</div>`;
        
        // Cancel any existing SSE connection and pending reconnect timeouts immediately
        if (chatEventSource) {
            chatEventSource.close();
            chatEventSource = null;
        }
        if (chatSseTimeout) {
            clearTimeout(chatSseTimeout);
            chatSseTimeout = null;
        }

        fetchMessages();
    }

    // Go back to frests list on mobile
    function exitChatView() {
        // Reset active chat mode
        document.querySelector(".chat-wrapper").classList.remove("active-chat-mode");
        
        if (chatEventSource) {
            chatEventSource.close();
            chatEventSource = null;
        }
        if (window.chatPollingInterval) {
            clearInterval(window.chatPollingInterval);
            window.chatPollingInterval = null;
        }
        
        activeContact = null;
        if (chatSseTimeout) {
            clearTimeout(chatSseTimeout);
            chatSseTimeout = null;
        }
        document.getElementById("chatWrapper").classList.remove("active-chat-mode");
        document.getElementById("chatEmptyState").style.display = "flex";
        document.getElementById("chatActiveState").style.display = "none";

        // Restore mobile bottom navigation
        const mobNav = document.querySelector('.mobile-bottom-nav');
        if (mobNav) {
            mobNav.style.removeProperty('display');
        }

        loadChatList();
    }

    var editingMessageId = null;

    // Fetch message history for the active contact
    function fetchMessages() {
        if (!activeContact) return;

        fetch(`get_messages.php?contact_type=${activeContact.type}&contact_id=${activeContact.id}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.blocked) {
                        // Ẩn form chat và hiển thị thông điệp bị chặn
                        document.getElementById("chatSendForm").style.display = "none";
                        let blockedMsg = document.getElementById("chatBlockedMessage");
                        if (!blockedMsg) {
                            blockedMsg = document.createElement("div");
                            blockedMsg.id = "chatBlockedMessage";
                            blockedMsg.style.cssText = "padding: 16px; text-align: center; color: var(--danger); background: rgba(239, 68, 68, 0.08); border-top: 1px solid var(--border-color); font-size: 13.5px; font-weight: 600; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;";
                            document.getElementById("chatSendForm").parentNode.appendChild(blockedMsg);
                        }
                        blockedMsg.innerText = data.message;
                        blockedMsg.style.display = "block";
                        
                        // Hiển thị thông báo trong container tin nhắn
                        document.getElementById("messagesContainer").innerHTML = `
                            <div style="padding: 60px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; box-sizing: border-box;">
                                <i class="fa-solid fa-user-slash" style="font-size: 44px; margin-bottom: 16px; color: var(--text-muted);"></i>
                                <p style="font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">Không thể tải tin nhắn</p>
                                <p style="max-width: 300px; margin: 0 auto; line-height: 1.4;">${data.message}</p>
                            </div>
                        `;
                        lastMessageCount = 0;
                    } else {
                        // Hiện lại form và ẩn thông điệp chặn
                        document.getElementById("chatSendForm").style.display = "flex";
                        const blockedMsg = document.getElementById("chatBlockedMessage");
                        if (blockedMsg) {
                            blockedMsg.style.display = "none";
                        }
                        renderMessages(data.messages);
                        
                        // Start real-time updates via SSE only after window load completes
                        if (document.readyState === 'complete' || windowLoaded) {
                            connectChatSSE();
                        }
                    }
                }
            })
            .catch(err => console.error("Error fetching messages:", err));
    }

    // Connect to SSE for real-time messages & updates
    function connectChatSSE() {
        if (!activeContact) return;

        // Đăng ký trình xử lý visibilitychange duy nhất 1 lần để quản lý kết nối khi ẩn/hiện tab
        if (!window.chatVisibilityHandlerRegistered) {
            window.chatVisibilityHandlerRegistered = true;
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    console.log("Chat Realtime: Tab bị ẩn, đóng kết nối hoặc tạm dừng Polling để giảm tải cho hosting.");
                    if (chatEventSource) {
                        chatEventSource.close();
                        chatEventSource = null;
                    }
                    if (chatSseTimeout) {
                        clearTimeout(chatSseTimeout);
                        chatSseTimeout = null;
                    }
                } else {
                    console.log("Chat Realtime: Tab hoạt động trở lại, khôi phục kết nối chat.");
                    if (activeContact) {
                        connectChatSSE();
                    }
                }
            });
        }

        let lastId = 0;
        const msgGroups = document.querySelectorAll("#messagesContainer .msg-group");
        msgGroups.forEach(el => {
            const id = parseInt(el.dataset.msgId);
            if (!isNaN(id) && id > lastId) {
                lastId = id;
            }
        });

        // Cancel previous EventSource and reconnect timeout
        if (chatEventSource) {
            chatEventSource.close();
            chatEventSource = null;
        }
        if (chatSseTimeout) {
            clearTimeout(chatSseTimeout);
            chatSseTimeout = null;
        }
        if (window.chatPollingInterval) {
            clearInterval(window.chatPollingInterval);
            window.chatPollingInterval = null;
        }

        if (window.FREST_CONFIG && window.FREST_CONFIG.disableSSE) {
            console.log("Chat Realtime: Kích hoạt AJAX Polling cho chat do máy chủ đơn luồng...");
            
            // Xây dựng cache state ban đầu của tin nhắn
            window.chatMsgStates = window.chatMsgStates || {};
            const msgBubbles = document.querySelectorAll("#messagesContainer .msg-group");
            msgBubbles.forEach(el => {
                const mid = parseInt(el.dataset.msgId);
                const edited = el.querySelector(".msg-edited-badge") ? "1" : "0";
                const recalled = el.classList.contains("recalled") ? "1" : "0";
                const reacts = Array.from(el.querySelectorAll(".msg-reaction")).map(x => x.textContent).sort().join("");
                window.chatMsgStates[mid] = `${edited}_${recalled}_` + hashString(reacts);
            });

            let globalMaxId = 0;

            const doChatPoll = () => {
                if (!activeContact) return;
                if (document.hidden) {
                    console.log("Chat Polling: Tab đang ẩn, tạm dừng Polling.");
                    return;
                }
                
                const fd = new FormData();
                fd.append('states', JSON.stringify(window.chatMsgStates || {}));

                fetch(`sse_messages.php?polling=1&contact_type=${activeContact.type}&contact_id=${activeContact.id}&last_id=${lastId}&global_max_id=${globalMaxId}`, {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        globalMaxId = data.global_max_id;
                        
                        // 1. Nhận tin nhắn mới
                        if (data.messages && data.messages.length > 0) {
                            let currentMessages = lastMessagesJSONCached ? JSON.parse(lastMessagesJSONCached) : [];
                            let added = false;
                            data.messages.forEach(msg => {
                                if (!currentMessages.some(x => x.id === msg.id)) {
                                    currentMessages.push(msg);
                                    lastId = Math.max(lastId, msg.id);
                                    added = true;
                                    
                                    const reactStr = JSON.stringify(msg.reactions || []);
                                    window.chatMsgStates[msg.id] = `${msg.is_edited}_${msg.is_recalled}_` + hashString(reactStr);
                                }
                            });
                            if (added) {
                                renderMessages(currentMessages);
                            }
                        }

                        // 2. Nhận cập nhật sửa/thu hồi/cảm xúc
                        if (data.updates && data.updates.length > 0) {
                            let currentMessages = lastMessagesJSONCached ? JSON.parse(lastMessagesJSONCached) : [];
                            let updated = false;
                            data.updates.forEach(msg => {
                                const idx = currentMessages.findIndex(x => x.id === msg.id);
                                if (idx !== -1) {
                                    currentMessages[idx] = msg;
                                    updated = true;
                                    
                                    const reactStr = JSON.stringify(msg.reactions || []);
                                    window.chatMsgStates[msg.id] = `${msg.is_edited}_${msg.is_recalled}_` + hashString(reactStr);
                                }
                            });
                            if (updated) {
                                renderMessages(currentMessages);
                            }
                        }

                        // 3. Tải lại sidebar nếu có tin nhắn mới bên ngoài
                        if (data.sidebar_update) {
                            loadChatList();
                        }
                    }
                })
                .catch(err => console.error("Chat polling error:", err));
            };

            function hashString(str) {
                let hash = 0;
                if (str.length === 0) return hash.toString();
                for (let i = 0; i < str.length; i++) {
                    const chr = str.charCodeAt(i);
                    hash = ((hash << 5) - hash) + chr;
                    hash |= 0;
                }
                return hash.toString();
            }

            doChatPoll();
            window.chatPollingInterval = setInterval(doChatPoll, 3500); // Giãn lên 3.5 giây để tránh quá tải hosting
            return;
        }

        if (document.hidden) {
            console.log("Chat SSE: Tab đang ẩn, hoãn kết nối SSE.");
            return;
        }

        const url = `sse_messages.php?contact_type=${activeContact.type}&contact_id=${activeContact.id}&last_id=${lastId}`;
        chatEventSource = new EventSource(url);

        chatEventSource.addEventListener('message', function(e) {
            try {
                const msg = JSON.parse(e.data);
                let currentMessages = lastMessagesJSONCached ? JSON.parse(lastMessagesJSONCached) : [];
                if (!currentMessages.some(x => x.id === msg.id)) {
                    currentMessages.push(msg);
                    renderMessages(currentMessages);
                }
            } catch (err) {
                console.error("Error parsing new message SSE:", err);
            }
        });

        chatEventSource.addEventListener('update', function(e) {
            try {
                const msg = JSON.parse(e.data);
                let currentMessages = lastMessagesJSONCached ? JSON.parse(lastMessagesJSONCached) : [];
                const idx = currentMessages.findIndex(x => x.id === msg.id);
                if (idx !== -1) {
                    currentMessages[idx] = msg;
                    renderMessages(currentMessages);
                }
            } catch (err) {
                console.error("Error parsing update message SSE:", err);
            }
        });

        chatEventSource.addEventListener('sidebar_update', function(e) {
            loadChatList();
        });

        chatEventSource.addEventListener('blocked', function(e) {
            fetchMessages(); // refresh view with block status
            if (chatEventSource) {
                chatEventSource.close();
                chatEventSource = null;
            }
            if (chatSseTimeout) {
                clearTimeout(chatSseTimeout);
                chatSseTimeout = null;
            }
        });

        chatEventSource.addEventListener('reconnect', function(e) {
            if (chatEventSource) {
                chatEventSource.close();
                chatEventSource = null;
            }
            clearTimeout(chatSseTimeout);
            chatSseTimeout = setTimeout(connectChatSSE, 1000);
        });

        chatEventSource.onerror = function() {
            if (chatEventSource) {
                chatEventSource.close();
                chatEventSource = null;
            }
            // Retry after 3 seconds
            clearTimeout(chatSseTimeout);
            chatSseTimeout = setTimeout(connectChatSSE, 3000);
        };
    }

    // Create single message DOM element
    function createMessageGroup(m) {
        const isMe = m.sender_type === myIdentity.type && parseInt(m.sender_id) === myIdentity.id;
        const timeText = formatTimeOnly(m.created_at);
        const isRecalled = parseInt(m.is_recalled) === 1;
        const isEdited = parseInt(m.is_edited) === 1 && !isRecalled;

        const msgGroup = document.createElement("div");
        msgGroup.className = `msg-group ${isMe ? 'my-msg' : 'incoming-msg'}`;
        msgGroup.dataset.msgId = m.id;

        // Build attachments content
        let attachmentContent = '';
        const hasText = m.message_text && m.message_text.trim() !== '';
        const mediaMarginTop = hasText ? '8px' : '0';

        if (!isRecalled) {
            if (m.image_filename) {
                attachmentContent += `
                    <div class="chat-attachment-image" style="margin-top: ${mediaMarginTop}; max-width: 280px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color);">
                        <a href="uploads/chat/${m.image_filename}" onclick="openChatLightbox(event, 'uploads/chat/${m.image_filename}')">
                            <img src="uploads/chat/${m.image_filename}" style="width: 100%; display: block; object-fit: cover; max-height: 200px;">
                        </a>
                    </div>
                `;
            }
            if (m.video_filename) {
                attachmentContent += `
                    <div class="chat-attachment-video frest-video-player-wrapper" style="margin-top: ${mediaMarginTop}; width: 100%; max-width: 320px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); background: #000; box-shadow: var(--shadow-sm); position: relative; aspect-ratio: 16/9;">
                        <video src="${SITE_URL_JS()}/uploads/chat/${m.video_filename}" class="frest-video-element" preload="metadata" playsinline webkit-playsinline oncontextmenu="return false;" controlsList="nodownload" style="width: 100%; height: 100%; display: block; object-fit: contain;"></video>
                        <div class="frest-video-play-overlay"><i class="fa-solid fa-play"></i></div>
                        <div class="frest-video-loader-overlay"><div class="frest-video-spinner"></div></div>
                        <div class="frest-video-controls-overlay">
                            <button type="button" class="frest-video-control-btn frest-play-pause-btn" title="Phát/Tạm dừng"><i class="fa-solid fa-play"></i></button>
                            <div class="frest-video-timeline-container">
                                <div class="frest-video-timeline-bg">
                                    <div class="frest-video-timeline-buffer"></div>
                                    <div class="frest-video-timeline-current"></div>
                                </div>
                                <input type="range" class="frest-video-timeline-slider" min="0" max="100" value="0" step="0.1">
                                <div class="frest-video-time-tooltip">00:00</div>
                            </div>
                            <div class="frest-video-time-display">00:00 / 00:00</div>
                            <div class="frest-video-volume-container">
                                <button type="button" class="frest-video-control-btn frest-volume-btn" title="Âm lượng"><i class="fa-solid fa-volume-high"></i></button>
                                <input type="range" class="frest-video-volume-slider" min="0" max="1" value="1" step="0.05">
                            </div>
                            <button type="button" class="frest-video-control-btn frest-fullscreen-btn" title="Toàn màn hình"><i class="fa-solid fa-expand"></i></button>
                        </div>
                    </div>
                `;
            }
            if (m.audio_filename) {
                attachmentContent += `
                    <div class="chat-attachment-audio" style="margin-top: ${mediaMarginTop}; width: 260px; background: rgba(255,255,255,0.06); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px var(--accent-glow); font-size: 13px;">
                            <i class="fa-solid fa-volume-high"></i>
                        </div>
                        <audio src="uploads/chat/${m.audio_filename}" controls style="flex: 1; height: 32px;"></audio>
                    </div>
                `;
            }
            if (m.document_filename) {
                attachmentContent += `
                    <div class="chat-attachment-doc" style="margin-top: ${mediaMarginTop}; display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); border-radius: var(--radius-md); min-width: 220px; max-width: 280px; box-shadow: var(--shadow-sm); transition: all 0.2s ease;">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; color: var(--accent-primary); flex-shrink: 0; font-size: 18px;">
                            <i class="fa-solid fa-file-arrow-down"></i>
                        </div>
                        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; text-align: left;">
                            <span style="font-size: 13px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-primary);" title="${escapeHTML(m.original_filename)}">${escapeHTML(m.original_filename)}</span>
                            <span style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Tài liệu đính kèm</span>
                        </div>
                        <a href="uploads/chat/${m.document_filename}" download="${escapeHTML(m.original_filename)}" style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='var(--accent-primary)'; this.style.color='#fff';" onmouseout="this.style.background='var(--bg-tertiary)'; this.style.color='var(--text-primary)';" title="Tải xuống">
                            <i class="fa-solid fa-download"></i>
                        </a>
                    </div>
                `;
            }
        }

        // Build bubble contents
        let bubbleContent = '';
        if (isRecalled) {
            bubbleContent = `<span class="recalled-text"><i class="fa-solid fa-ban" style="margin-right: 4px;"></i>Tin nhắn đã bị thu hồi</span>`;
        } else {
            bubbleContent = parseMarkdown(escapeHTML(m.message_text || ''));
            if (attachmentContent !== '') {
                if (hasText) {
                    bubbleContent += '<br>';
                }
                bubbleContent += attachmentContent;
            }
        }

        // Build edited indicator
        const editedText = isEdited ? ` <span style="font-size: 10px; opacity: 0.6; font-style: italic;">(đã chỉnh sửa)</span>` : '';

        // Build reactions HTML
        let reactionsHTML = '';
        if (!isRecalled && m.reactions && m.reactions.length > 0) {
            const grouped = {};
            m.reactions.forEach(r => {
                grouped[r.reaction_emoji] = (grouped[r.reaction_emoji] || 0) + 1;
            });
            
            reactionsHTML = `<div class="msg-reactions-stack">`;
            for (const emoji in grouped) {
                reactionsHTML += `<span class="reaction-badge" onclick="handleReactionClick(${m.id}, '${emoji}')" title="Nhấp để gỡ/thay đổi">${emoji} ${grouped[emoji] > 1 ? grouped[emoji] : ''}</span>`;
            }
            reactionsHTML += `</div>`;
        }

        // Build actions HTML
        let actionsHTML = '';
        let emojiPickerHTML = '';
        if (!isRecalled) {
            actionsHTML = `<div class="msg-actions" style="position: relative;">`;
            
            // Reaction trigger
            actionsHTML += `
                <button class="action-btn" onclick="toggleEmojiPicker(event, ${m.id})" title="Phản ứng">
                    <i class="fa-regular fa-face-smile"></i>
                </button>
            `;

            if (isMe) {
                actionsHTML += `
                    <button class="action-btn" onclick="startEditMessage(${m.id})" title="Sửa">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="action-btn" onclick="recallMessage(${m.id})" title="Thu hồi">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                `;
            }
            actionsHTML += `</div>`;

            emojiPickerHTML = `
                <div class="emoji-picker-panel" id="emoji-picker-${m.id}">
                    <span onclick="sendChatMessageReaction(${m.id}, '👍')">👍</span>
                    <span onclick="sendChatMessageReaction(${m.id}, '❤️')">❤️</span>
                    <span onclick="sendChatMessageReaction(${m.id}, '😂')">😂</span>
                    <span onclick="sendChatMessageReaction(${m.id}, '😮')">😮</span>
                    <span onclick="sendChatMessageReaction(${m.id}, '😢')">😢</span>
                    <span onclick="sendChatMessageReaction(${m.id}, '😡')">😡</span>
                </div>
            `;
        }

        // Cache message text for safe editing retrieval
        messageCache[m.id] = m.message_text;

        const isMediaOnly = !isRecalled && !hasText && (m.image_filename || m.video_filename) && !m.audio_filename && !m.document_filename;
        const bubbleClass = `msg-bubble ${isRecalled ? 'recalled-bubble' : ''} ${isMediaOnly ? 'media-only-bubble' : ''}`;

        // Render group avatars and nicknames beside incoming messages
        if (activeContact.type === 'group' && !isMe) {
            const senderAvatar = m.sender_avatar.indexOf('http') === 0 ? m.sender_avatar : `uploads/avatars/${m.sender_avatar}`;
            msgGroup.innerHTML = `
                <div style="display: flex; gap: 8px; align-items: flex-end; width: 100%;">
                    <img src="${senderAvatar}" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); flex-shrink: 0;" title="${escapeHTML(m.sender_name)}">
                    <div style="display: flex; flex-direction: column; min-width: 0; flex: 1;">
                        <span style="font-size: 10px; color: var(--text-muted); margin-bottom: 2px; margin-left: 4px; display: flex; align-items: center; gap: 4px; text-align: left;">${escapeHTML(m.sender_name)}${getVerificationBadgeHTML(m.sender_verification_type, m.sender_username)}</span>
                        <div class="msg-bubble-wrapper" style="position: relative;" onclick="toggleMobileActions(event)" data-msg-id="${m.id}">
                            <div class="${bubbleClass}">
                                ${bubbleContent}
                                ${reactionsHTML}
                            </div>
                            ${actionsHTML}
                            ${emojiPickerHTML}
                        </div>
                        <span class="msg-time" style="align-self: flex-start; margin-top: 4px;">${timeText}${editedText}</span>
                    </div>
                </div>
            `;
        } else {
            msgGroup.innerHTML = `
                <div class="msg-bubble-wrapper" style="position: relative;" onclick="toggleMobileActions(event)" data-msg-id="${m.id}">
                    <div class="${bubbleClass}">
                        ${bubbleContent}
                        ${reactionsHTML}
                    </div>
                    ${actionsHTML}
                    ${emojiPickerHTML}
                </div>
                <span class="msg-time">${timeText}${editedText}</span>
            `;
        }

        return msgGroup;
    }

    // Render message bubbles with in-place DOM updates (smooth and lag-free)
    function renderMessages(messages) {
        const container = document.getElementById("messagesContainer");
        
        // If it is a completely fresh load (loading notch visible) or the list is empty, wipe and draw
        const isFreshLoad = container.querySelector("div[style*='notch']") || lastMessagesJSONCached === "";
        if (isFreshLoad || messages.length === 0) {
            container.innerHTML = "";
            if (messages.length === 0) {
                container.innerHTML = `<div style="padding:40px 20px; text-align:center; color:var(--text-muted); font-size:13px; font-style:italic;">Hãy vẫy tay chào nhau để bắt đầu cuộc trò chuyện 👋</div>`;
                lastMessageCount = 0;
                lastMessagesJSONCached = JSON.stringify(messages);
                return;
            }
            messages.forEach(m => {
                container.appendChild(createMessageGroup(m));
            });
            if (typeof initCustomVideos === 'function') {
                initCustomVideos();
            }
            container.scrollTop = container.scrollHeight;
            lastMessageCount = messages.length;
            lastMessagesJSONCached = JSON.stringify(messages);
            loadChatList();
            return;
        }

        // In-place updates & appends
        const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
        
        messages.forEach(m => {
            const oldMsgGroup = container.querySelector(`.msg-group[data-msg-id="${m.id}"]`);
            if (oldMsgGroup) {
                // Check if message properties have changed
                let isChanged = true;
                try {
                    const cachedList = JSON.parse(lastMessagesJSONCached);
                    const cachedMsg = cachedList.find(x => x.id === m.id);
                    if (cachedMsg && JSON.stringify(cachedMsg) === JSON.stringify(m)) {
                        isChanged = false;
                    }
                } catch(e) {}

                if (isChanged) {
                    const newMsgGroup = createMessageGroup(m);
                    oldMsgGroup.replaceWith(newMsgGroup);
                }
            } else {
                // Append new message smoothly
                const newMsgGroup = createMessageGroup(m);
                newMsgGroup.classList.add("message-appear");
                container.appendChild(newMsgGroup);
            }
        });

        // Clean up messages no longer in the list
        const incomingIds = new Set(messages.map(m => m.id));
        container.querySelectorAll(".msg-group").forEach(el => {
            const id = parseInt(el.dataset.msgId);
            if (!isNaN(id) && !incomingIds.has(id)) {
                el.remove();
            }
        });

        if (typeof initCustomVideos === 'function') {
            initCustomVideos();
        }

        lastMessageCount = messages.length;
        lastMessagesJSONCached = JSON.stringify(messages);

        // Keep scroll at bottom if already there
        if (isAtBottom) {
            container.scrollTop = container.scrollHeight;
        }

        loadChatList();
    }

    // Toggle actions overlay on touch screen (mobile tap)
    function toggleMobileActions(e) {
        if (window.innerWidth <= 768) {
            if (e.target.closest(".msg-actions") || e.target.closest(".emoji-picker-panel")) {
                return;
            }
            e.stopPropagation();
            const wrapper = e.currentTarget;
            const wasShown = wrapper.classList.contains("show-actions");
            document.querySelectorAll(".msg-bubble-wrapper").forEach(w => w.classList.remove("show-actions"));
            if (!wasShown) {
                wrapper.classList.add("show-actions");
            }
        }
    }

    // Edit message actions
    function startEditMessage(id) {
        const text = messageCache[id] || "";
        editingMessageId = id;
        document.getElementById("chatEditIndicator").style.display = "flex";
        
        const input = document.getElementById("chatMessageInput");
        input.value = text;
        input.focus();
    }

    // Cancel edit indicator
    function cancelEditMessage() {
        editingMessageId = null;
        document.getElementById("chatEditIndicator").style.display = "none";
        document.getElementById("chatMessageInput").value = "";
    }

    // Recall message action
    function recallMessage(id) {
        if (!confirm("Bạn có chắc chắn muốn thu hồi tin nhắn này đối với mọi người?")) return;

        // Optimistic UI Update: immediately change message bubble content to recalled state
        const wrapper = document.querySelector(`.msg-bubble-wrapper[data-msg-id="${id}"]`);
        let oldContent = '';
        let oldReactions = null;
        let oldActions = null;

        if (wrapper) {
            const bubble = wrapper.querySelector(".msg-bubble");
            const reactions = wrapper.querySelector(".msg-reactions-stack");
            const actions = wrapper.querySelector(".msg-actions");

            if (bubble) {
                oldContent = bubble.innerHTML;
                bubble.classList.add("recalled-bubble");
                bubble.innerHTML = `<span class="recalled-text"><i class="fa-solid fa-ban" style="margin-right: 4px;"></i>Tin nhắn đã bị thu hồi</span>`;
            }
            if (reactions) {
                oldReactions = reactions.outerHTML;
                reactions.remove();
            }
            if (actions) {
                oldActions = actions.outerHTML;
                actions.remove();
            }
        }

        const params = new URLSearchParams();
        params.append("message_id", id);

        fetch("recall_message.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchMessages();
                loadChatList();
            } else {
                alert("Lỗi: " + data.message);
                // Rollback if failed
                if (wrapper) {
                    const bubble = wrapper.querySelector(".msg-bubble");
                    if (bubble) {
                        bubble.classList.remove("recalled-bubble");
                        bubble.innerHTML = oldContent;
                    }
                    if (oldReactions) {
                        wrapper.insertAdjacentHTML('beforeend', oldReactions);
                    }
                    if (oldActions) {
                        wrapper.insertAdjacentHTML('beforeend', oldActions);
                    }
                }
            }
        })
        .catch(err => {
            console.error("Error recalling message:", err);
            // Rollback if failed
            if (wrapper) {
                const bubble = wrapper.querySelector(".msg-bubble");
                if (bubble) {
                    bubble.classList.remove("recalled-bubble");
                    bubble.innerHTML = oldContent;
                }
                if (oldReactions) {
                    wrapper.insertAdjacentHTML('beforeend', oldReactions);
                }
                if (oldActions) {
                    wrapper.insertAdjacentHTML('beforeend', oldActions);
                }
            }
        });
    }

    // Delete conversation helper
    function handleDeleteConversation(type, id, name) {
        if (!confirm(`Bạn có chắc chắn muốn xóa toàn bộ cuộc trò chuyện với "${name}" không?\nHành động này không thể hoàn tác.`)) {
            return;
        }

        const params = new URLSearchParams();
        params.append("contact_type", type);
        params.append("contact_id", id);

        fetch("delete_conversation.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') {
                    showToast("Đã xóa cuộc trò chuyện.");
                } else {
                    alert("Đã xóa cuộc trò chuyện.");
                }
                
                if (activeContact && activeContact.type === type && activeContact.id === id) {
                    exitChatView();
                } else {
                    loadChatList();
                }
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error deleting conversation:", err);
            alert("Lỗi kết nối khi xóa cuộc trò chuyện.");
        });
    }

    // Reaction selector helpers
    function toggleEmojiPicker(e, id) {
        e.stopPropagation();
        const picker = document.getElementById(`emoji-picker-${id}`);
        const isCurrentlyOpen = picker.classList.contains("active");
        closeAllEmojiPickers();
        if (!isCurrentlyOpen) {
            // Check space at the top to prevent getting cut off by the chat header
            const button = e.currentTarget;
            const rect = button.getBoundingClientRect();
            const container = document.getElementById("messagesContainer");
            const containerRect = container.getBoundingClientRect();
            
            if (rect.top - containerRect.top < 80) {
                picker.style.bottom = "auto";
                picker.style.top = "100%";
            } else {
                picker.style.bottom = "100%";
                picker.style.top = "auto";
            }
            
            // Raise parent z-index so it renders on top of adjacent messages/reaction badges
            const msgGroup = button.closest(".msg-group");
            if (msgGroup) {
                msgGroup.style.zIndex = "50";
                msgGroup.style.position = "relative";
            }
            
            picker.classList.add("active");
        }
    }

    function closeAllEmojiPickers() {
        document.querySelectorAll(".emoji-picker-panel").forEach(p => {
            p.classList.remove("active");
        });
        document.querySelectorAll(".msg-group").forEach(g => {
            g.style.zIndex = "";
            g.style.position = "";
        });
    }

    function sendChatMessageReaction(messageId, emoji) {
        const params = new URLSearchParams();
        params.append("message_id", messageId);
        params.append("reaction_emoji", emoji);

        fetch("react_message.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                closeAllEmojiPickers();
                fetchMessages();
            }
        })
        .catch(err => console.error("Error reacting to message:", err));
    }

    function handleReactionClick(messageId, emoji) {
        // Toggle/remove the active reaction by sending empty string
        sendChatMessageReaction(messageId, "");
    }

    // Attachment uploading logic
    var currentAttachment = null;

    function handleFileSelected(e) {
        const file = e.target.files[0];
        if (file) {
            uploadFile(file);
        }
    }

    function cancelAttachment() {
        currentAttachment = null;
        document.getElementById("chatFileInput").value = "";
        document.getElementById("attachmentPreviewBar").style.display = "none";
        document.getElementById("chatSendBtn").disabled = false;
    }

    // Handle Send Button click
    function handleSendMessage(e) {
        e.preventDefault();
        if (!activeContact) return;

        const input = document.getElementById("chatMessageInput");
        const sendBtn = document.getElementById("chatSendBtn");
        const text = input.value.trim();
        if (text === "" && !currentAttachment) return;

        // Disable input & send button during send to prevent double send and losing text
        input.disabled = true;
        sendBtn.disabled = true;
        const originalSendBtnHTML = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

        const params = new URLSearchParams();
        params.append("receiver_type", activeContact.type);
        params.append("receiver_id", activeContact.id);
        params.append("message_text", text);

        if (currentAttachment) {
            params.append(currentAttachment.file_type + "_filename", currentAttachment.filename);
            params.append("original_filename", currentAttachment.original_filename);
        }

        const isEditing = (editingMessageId !== null);
        const url = isEditing ? "edit_message.php" : "send_message.php";
        
        if (isEditing) {
            params.append("message_id", editingMessageId);
        }

        fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalSendBtnHTML;

            if (data.status === 'success' || data.success) {
                input.value = ""; // Only clear on success!
                if (isEditing) {
                    cancelEditMessage();
                } else {
                    cancelAttachment();
                }
                fetchMessages();
                loadChatList();
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error sending message:", err);
            input.disabled = false;
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalSendBtnHTML;
            alert("Lỗi kết nối. Không thể gửi tin nhắn.");
        });
    }

    // Search users/pages using search_mention.php
    function performSearch(query) {
        const resultsList = document.getElementById("searchResultsList");
        const frestList = document.getElementById("frestList");

        resultsList.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12.5px;"><i class="fa-solid fa-circle-notch fa-spin"></i> Đang tìm kiếm...</div>`;
        resultsList.style.display = "block";
        frestList.style.display = "none";

        fetch(`search_mention.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsList.innerHTML = "";

                if (data.length === 0) {
                    resultsList.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12.5px;">Không tìm thấy kết quả.</div>`;
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement("div");
                    row.className = "frest-item";
                    row.onclick = () => selectContact(item.type, item.id, item.name, item.handle, item.avatar, item.is_verified);

                    const badgeHTML = getVerificationBadgeHTML(item.verification_type, item.handle);

                    row.innerHTML = `
                        <div class="frest-avatar-wrapper">
                            <img class="frest-avatar" src="${item.avatar}" alt="avatar">
                            ${parseInt(item.is_verified) === 1 ? '<span class="identity-badge" title="Đã xác minh"><svg viewBox="0 0 24 24" width="9" height="9" style="fill: none; stroke: #ffffff; stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; display: block;"><polyline points="20 6 9 17 4 12"></polyline></svg></span>' : ''}
                        </div>
                        <div class="frest-details">
                            <h6 class="frest-name" style="display:flex; align-items:center; gap:4px;">${escapeHTML(item.name)}${badgeHTML}</h6>
                            <p class="frest-preview">@${escapeHTML(item.handle)} (${item.type === 'page' ? 'Trang' : 'Thành viên'})</p>
                        </div>
                    `;
                    resultsList.appendChild(row);
                });
            })
            .catch(err => {
                console.error("Error searching contacts:", err);
                resultsList.innerHTML = `<div style="padding:20px; text-align:center; color:var(--danger); font-size:12.5px;">Lỗi tìm kiếm.</div>`;
            });
    }

    // Group Creation Modal logic
    var selectedMembers = [];

    function openCreateGroupModal() {
        selectedMembers = [];
        document.getElementById("groupNameInput").value = "";
        document.getElementById("groupDescInput").value = "";
        document.getElementById("groupAvatarInput").value = "";
        document.getElementById("groupAvatarPreview").src = "uploads/avatars/group_default.png";
        document.getElementById("modalMemberSearch").value = "";
        document.getElementById("modalSearchMembersResults").style.display = "none";
        renderSelectedMembers();
        document.getElementById("createGroupModal").style.display = "flex";
    }

    function closeCreateGroupModal() {
        document.getElementById("createGroupModal").style.display = "none";
    }

    function previewGroupAvatar(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                document.getElementById("groupAvatarPreview").src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Handle member search inside modal
    document.addEventListener("DOMContentLoaded", function() {
        const modalSearchInput = document.getElementById("modalMemberSearch");
        let modalSearchTimeout = null;

        modalSearchInput.addEventListener("input", function() {
            clearTimeout(modalSearchTimeout);
            const q = modalSearchInput.value.trim();
            const resultsBox = document.getElementById("modalSearchMembersResults");

            if (q.length === 0) {
                resultsBox.style.display = "none";
                return;
            }

            modalSearchTimeout = setTimeout(() => {
                fetch(`search_mention.php?q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(data => {
                        resultsBox.innerHTML = "";
                        if (data.length === 0) {
                            resultsBox.innerHTML = `<div style="padding:8px; font-size:12px; color:var(--text-muted); text-align:center;">Không tìm thấy kết quả</div>`;
                            resultsBox.style.display = "block";
                            return;
                        }
                        
                        data.forEach(item => {
                            const isSelf = item.type === myIdentity.type && parseInt(item.id) === myIdentity.id;
                            if (isSelf) return;

                            const isSelected = selectedMembers.some(m => m.id === item.id && m.type === item.type);
                            
                            const div = document.createElement("div");
                            div.className = "member-select-item";
                            div.style.display = "flex";
                            div.style.alignItems = "center";
                            div.style.justifyContent = "space-between";
                            
                            const badgeHTML = getVerificationBadgeHTML(item.verification_type, item.handle);
                            div.innerHTML = `
                                <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                                    <img src="${item.avatar}" style="width:28px; height:28px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                                    <div style="font-size:12px; text-align:left; color:var(--text-primary); min-width:0; flex:1; overflow:hidden;">
                                        <div style="font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center; gap:4px;">${escapeHTML(item.name)}${badgeHTML}</div>
                                        <div style="font-size:10.5px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">@${escapeHTML(item.handle)}</div>
                                    </div>
                                </div>
                                <button type="button" class="profile-btn primary" style="font-size:10.5px; padding:5px 12px; height:28px; border-radius:var(--radius-sm); flex:none !important; width:auto !important; flex-shrink:0; cursor:pointer;" ${isSelected ? 'disabled' : ''}>
                                    ${isSelected ? 'Đã thêm' : 'Thêm'}
                                </button>
                            `;
                            if (!isSelected) {
                                div.onclick = () => addSelectedMember(item);
                            }
                            resultsBox.appendChild(div);
                        });
                        resultsBox.style.display = "block";
                    });
            }, 300);
        });
    });

    function addSelectedMember(item) {
        selectedMembers.push(item);
        renderSelectedMembers();
        document.getElementById("modalMemberSearch").value = "";
        document.getElementById("modalSearchMembersResults").style.display = "none";
    }

    function removeSelectedMember(type, id) {
        selectedMembers = selectedMembers.filter(m => !(m.type === type && parseInt(m.id) === parseInt(id)));
        renderSelectedMembers();
    }

    function renderSelectedMembers() {
        const container = document.getElementById("selectedMembersContainer");
        container.innerHTML = "";
        if (selectedMembers.length === 0) {
            container.innerHTML = `<span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">Chưa chọn thành viên nào</span>`;
            return;
        }
        selectedMembers.forEach(m => {
            const badge = document.createElement("span");
            badge.className = "selected-member-badge";
            badge.innerHTML = `
                ${escapeHTML(m.name)}
                <i class="fa-solid fa-circle-xmark" style="cursor:pointer; margin-left:6px;" onclick="removeSelectedMember('${m.type}', ${m.id})"></i>
            `;
            container.appendChild(badge);
        });
    }

    function handleCreateGroup(e) {
        e.preventDefault();
        const groupName = document.getElementById("groupNameInput").value.trim();
        const groupDesc = document.getElementById("groupDescInput").value.trim();
        if (groupName === "") return;
        
        const formData = new FormData();
        formData.append("group_name", groupName);
        formData.append("group_description", groupDesc);
        formData.append("members", JSON.stringify(selectedMembers.map(m => ({type: m.type, id: m.id}))));
        
        const avatarFile = document.getElementById("groupAvatarInput").files[0];
        if (avatarFile) {
            formData.append("avatar", avatarFile);
        }
        
        fetch("create_group.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                closeCreateGroupModal();
                selectContact('group', data.group_id, data.group_name, 'group_' + data.group_id, data.avatar_url);
            } else {
                alert("Lỗi tạo nhóm: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error creating group:", err);
            alert("Lỗi kết nối máy chủ khi tạo nhóm.");
        });
    }

    // Group info details modal logic
    function openGroupInfoModal() {
        if (!activeContact || activeContact.type !== 'group') return;
        fetch(`get_group_info.php?group_id=${activeContact.id}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const g = data.group;
                document.getElementById("infoGroupAvatar").src = g.avatar_url;
                document.getElementById("infoGroupName").textContent = g.name;
                document.getElementById("infoGroupDesc").textContent = g.description || "Không có mô tả cho nhóm này.";
                document.getElementById("infoGroupCount").textContent = g.members.length;
                
                const mList = document.getElementById("infoGroupMembersList");
                mList.innerHTML = "";
                g.members.forEach(m => {
                    const row = document.createElement("div");
                    row.style.display = "flex";
                    row.style.alignItems = "center";
                    row.style.justifyContent = "space-between";
                    row.style.gap = "10px";
                    row.style.width = "100%";
                    
                    const roleBadge = m.role === 'creator' ? `<span style="font-size:9.5px; background:rgba(59,130,246,0.15); color:var(--accent-primary); border:1px solid rgba(59,130,246,0.2); border-radius:4px; padding:2px 6px; font-weight:700;">Trưởng nhóm</span>` : '';
                    
                    row.innerHTML = `
                        <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                            <img src="${m.avatar}" style="width:30px; height:30px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                            <div style="min-width:0; display:flex; flex-direction:column; text-align:left;">
                                <span style="font-size:12.5px; font-weight:700; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(m.name)}</span>
                                <span style="font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">@${escapeHTML(m.handle)}</span>
                            </div>
                        </div>
                        ${roleBadge}
                    `;
                });
                
                // Toggle Delete Group button if current user is creator
                const isCreator = g.members.some(m => m.type === myIdentity.type && parseInt(m.id) === myIdentity.id && m.role === 'creator');
                document.getElementById("deleteGroupBtn").style.display = isCreator ? "inline-block" : "none";

                document.getElementById("groupInfoModal").style.display = "flex";
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => console.error("Error fetching group info:", err));
    }

    function closeGroupInfoModal() {
        document.getElementById("groupInfoModal").style.display = "none";
    }

    function handleLeaveGroup() {
        if (!activeContact || activeContact.type !== 'group') return;
        if (!confirm("Bạn có chắc chắn muốn rời nhóm chat này không?")) return;

        const params = new URLSearchParams();
        params.append("group_id", activeContact.id);

        fetch("leave_group.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                closeGroupInfoModal();
                if (typeof showToast === 'function') {
                    showToast("Bạn đã rời nhóm.");
                } else {
                    alert("Bạn đã rời nhóm.");
                }
                exitChatView();
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error leaving group:", err);
            alert("Lỗi kết nối khi rời nhóm.");
        });
    }

    function handleDeleteGroup() {
        if (!activeContact || activeContact.type !== 'group') return;
        if (!confirm("Bạn có chắc chắn muốn xóa nhóm chat này không?\nTất cả tin nhắn và thành viên sẽ bị xóa và hành động này không thể hoàn tác.")) return;

        const params = new URLSearchParams();
        params.append("group_id", activeContact.id);

        fetch("delete_group.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                closeGroupInfoModal();
                if (typeof showToast === 'function') {
                    showToast("Đã xóa nhóm.");
                } else {
                    alert("Đã xóa nhóm.");
                }
                exitChatView();
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error deleting group:", err);
            alert("Lỗi kết nối khi xóa nhóm.");
        });
    }

    function handleClearGroupConversation() {
        if (!activeContact || activeContact.type !== 'group') return;
        handleDeleteConversation(activeContact.type, activeContact.id, activeContact.name);
        closeGroupInfoModal();
    }

    /* Helper functions */
    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function parseMarkdown(text) {
        if (!text) return '';
        let html = text;

        // Xử lý các khối code: ```language\n code \n```
        html = html.replace(/```(\w*)\n([\s\S]*?)\n```/g, function(match, lang, code) {
            return `<pre class="code-block" style="background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.08); padding: 12px; border-radius: var(--radius-sm); font-family: monospace; font-size: 12px; overflow-x: auto; margin: 8px 0; text-align: left; max-width: 100%; box-sizing: border-box;"><code class="language-${lang}">${code}</code></pre>`;
        });

        // Inline code blocks: `code`
        html = html.replace(/`([^`]+)`/g, '<code style="background: rgba(255, 255, 255, 0.1); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.95em;">$1</code>');

        // Chữ đậm: **text**
        html = html.replace(/\*\*([\s\S]+?)\*\*/g, '<strong>$1</strong>');

        // Chữ nghiêng: *text*
        html = html.replace(/\*([\s\S]+?)\*/g, '<em>$1</em>');

        // Đường gạch ngang: ~~text~~
        html = html.replace(/~~([\s\S]+?)~~/g, '<del>$1</del>');

        // Danh sách gạch đầu dòng
        let lines = html.split('\n');
        let inList = false;
        let newLines = [];
        
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            
            if (line.includes('<pre class="code-block"') || line.includes('</pre>')) {
                newLines.push(line);
                continue;
            }
            
            if (line.trim().startsWith('- ') || line.trim().startsWith('* ')) {
                let content = line.trim().substring(2);
                if (!inList) {
                    newLines.push('<ul style="margin: 8px 0; padding-left: 20px; text-align: left;">');
                    inList = true;
                }
                newLines.push(`<li>${content}</li>`);
            } else {
                if (inList) {
                    newLines.push('</ul>');
                    inList = false;
                }
                newLines.push(line);
            }
        }
        if (inList) {
            newLines.push('</ul>');
        }
        
        html = newLines.join('\n');

        // Parse tables
        if (html.indexOf('|') !== -1) {
            let tableLines = html.split('\n');
            let inTable = false;
            let tableNewLines = [];
            
            for (let i = 0; i < tableLines.length; i++) {
                let line = tableLines[i].trim();
                if (line.startsWith('|') && line.endsWith('|')) {
                    let cells = line.split('|').map(x => x.trim()).filter((x, idx, arr) => idx > 0 && idx < arr.length - 1);
                    if (!inTable) {
                        tableNewLines.push('<table style="border-collapse: collapse; width: 100%; margin: 10px 0; border: 1px solid rgba(255,255,255,0.08); font-size: 12px; text-align: left;">');
                        inTable = true;
                        tableNewLines.push('<thead><tr style="background: rgba(255,255,255,0.055);">');
                        cells.forEach(h => {
                            tableNewLines.push(`<th style="border: 1px solid rgba(255,255,255,0.08); padding: 8px; font-weight: bold;">${h}</th>`);
                        });
                        tableNewLines.push('</tr></thead><tbody>');
                    } else {
                        if (line.includes('---') || line.includes(':---')) continue;
                        
                        tableNewLines.push('<tr>');
                        cells.forEach(c => {
                            tableNewLines.push(`<td style="border: 1px solid rgba(255,255,255,0.08); padding: 8px;">${c}</td>`);
                        });
                        tableNewLines.push('</tr>');
                    }
                } else {
                    if (inTable) {
                        tableNewLines.push('</tbody></table>');
                        inTable = false;
                    }
                    tableNewLines.push(tableLines[i]);
                }
            }
            if (inTable) {
                tableNewLines.push('</tbody></table>');
            }
            html = tableNewLines.join('\n');
        }
        
        return html;
    }

    // Generate verified badge HTML dynamically in Javascript
    function getVerificationBadgeHTML(type, username) {
        if (!type || (type !== 'official' && type !== 'subscribed')) return '';
        
        let color = '#1877f2'; // Default blue
        let innerColor = '#ffffff';
        let title = 'Huy hiệu đã xác minh';
        
        switch (type) {
            case 'official':
                color = '#1877f2'; // Default blue
                title = 'Huy hiệu đã xác minh';
                break;
            case 'subscribed':
                color = '#1d4ed8'; // Dark Blue
                title = 'Frest đã xác minh';
                break;
        }

        return `<svg class="verified-badge-svg" data-type="${escapeHTML(type)}" data-username="${escapeHTML(username)}" viewBox="0 0 24 24" width="14" height="14" style="cursor:pointer; display:inline-flex; align-items:center; align-self:center; margin-left:4px; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15)); vertical-align: middle;" title="${escapeHTML(title)}">
            <g fill-rule="evenodd" transform="translate(-92)">
                <path fill="${color}" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/>
                <path fill="${innerColor}" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/>
            </g>
        </svg>`;
    }

    function formatTime(timeStr) {
        if (!timeStr) return "";
        const date = new Date(timeStr.replace(/-/g, "/"));
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHrs = Math.floor(diffMins / 60);

        if (diffMins < 1) return "Vừa xong";
        if (diffMins < 60) return `${diffMins} phút trước`;
        if (diffHrs < 24) return `${diffHrs} giờ trước`;
        
        // Return DD/MM
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        return `${dd}/${mm}`;
    }

    function formatTimeOnly(timeStr) {
        if (!timeStr) return "";
        const date = new Date(timeStr.replace(/-/g, "/"));
        const hh = String(date.getHours()).padStart(2, '0');
        const mm = String(date.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }

    function openChatLightbox(e, url) {
        if (e) e.preventDefault();
        const modal = document.getElementById("chat-lightbox-modal");
        const img = document.getElementById("chat-lightbox-img");
        if (modal && img) {
            img.src = url;
            modal.style.display = "flex";
            // Force reflow
            modal.offsetHeight;
            modal.classList.add("show");
        }
    }

    function closeChatLightbox() {
        const modal = document.getElementById("chat-lightbox-modal");
        if (modal) {
            modal.classList.remove("show");
            setTimeout(() => {
                modal.style.display = "none";
                const img = document.getElementById("chat-lightbox-img");
                if (img) img.src = "";
            }, 350);
        }
    }

    // Escape key listener for closing lightbox
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            closeChatLightbox();
        }
    });

    // SPA cleanup export to allow parent SPA to close connection on page transition
    window.cleanupChatPage = function() {
        if (chatEventSource) {
            console.log("SPA cleanup: Đóng kết nối SSE chat.");
            chatEventSource.close();
            chatEventSource = null;
        }
        if (chatSseTimeout) {
            clearTimeout(chatSseTimeout);
            chatSseTimeout = null;
        }
        window.cleanupChatPage = null;
    };

    // Gán đè trực tiếp lên window để khắc phục hoàn toàn no-op fallback của theme.js
    window.openCreateGroupModal = openCreateGroupModal;
    window.closeCreateGroupModal = closeCreateGroupModal;
    window.handleLeaveGroup = handleLeaveGroup;
    window.handleDeleteGroup = handleDeleteGroup;
    window.handleClearGroupConversation = handleClearGroupConversation;
    window.closeGroupInfoModal = closeGroupInfoModal;
    window.closeChatLightbox = closeChatLightbox;
    window.exitChatView = exitChatView;
    window.openGroupInfoModal = openGroupInfoModal;
    window.cancelEditMessage = cancelEditMessage;
    window.cancelAttachment = cancelAttachment;
    window.openChatLightbox = openChatLightbox;
    window.handleReactionClick = handleReactionClick;
    window.toggleEmojiPicker = toggleEmojiPicker;
    window.startEditMessage = startEditMessage;
    window.recallMessage = recallMessage;
    window.sendChatMessageReaction = sendChatMessageReaction;
    window.toggleMobileActions = toggleMobileActions;
    window.removeSelectedMember = removeSelectedMember;
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
