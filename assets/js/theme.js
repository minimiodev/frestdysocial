/**
 * Theme Controller & Global Utility Functions - Frest App
 */

/**
 * Initialize Light/Dark theme toggling
 */
function initTheme() {
    if (window.themeInitialized) return;
    window.themeInitialized = true;

    const themeBtns = document.querySelectorAll('.theme-toggle, #theme-toggle-btn, #sidebar-theme-toggle-btn');
    if (themeBtns.length === 0) return;

    themeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const isLight = document.body.classList.toggle('light-theme');
            document.cookie = `theme=${isLight ? 'light' : 'dark'};path=/;max-age=31536000`; // 1 year
            
            themeBtns.forEach(b => {
                const icon = b.querySelector('i');
                if (icon) {
                    icon.className = isLight ? 'fas fa-moon' : 'fas fa-sun';
                }
            });
            
            const html = document.documentElement;
            if (isLight) {
                html.classList.add('light-theme');
            } else {
                html.classList.remove('light-theme');
            }
        });
    });
}

/**
 * Show Toast Notification
 */
function showToast(message, duration = 3000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<i class="fa-solid fa-circle-info" style="color: var(--accent-primary); font-size: 14px; flex-shrink: 0;"></i> <span style="line-height: 1.4;">${message}</span>`;
    
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px) scale(0.95)';
        toast.style.transition = 'all 0.22s cubic-bezier(0.16, 1, 0.3, 1)';
        setTimeout(() => toast.remove(), 230);
    }, duration);
}

/**
 * JS helper to get base folder URL
 */
function SITE_URL_JS() {
    const path = window.location.pathname;
    if (path.includes('/admin/')) {
        return '..';
    }
    return '.';
}

/**
 * Simple HTML Sanitizer to prevent Stored/DOM XSS
 * while preserving styling tags (h1-h6, p, br, strong, em, ul, ol, li)
 */
function sanitizeHTML(htmlString) {
    if (!htmlString) return '';
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlString, 'text/html');
        
        // Remove strictly forbidden elements
        const blockedTags = ['script', 'iframe', 'object', 'embed', 'applet', 'link', 'meta', 'style', 'form', 'button', 'input', 'textarea', 'svg', 'canvas'];
        blockedTags.forEach(tag => {
            doc.querySelectorAll(tag).forEach(el => el.remove());
        });
        
        // Clean all remaining elements of event handlers and javascript: URIs
        doc.querySelectorAll('*').forEach(el => {
            Array.from(el.attributes).forEach(attr => {
                const name = attr.name.toLowerCase();
                const value = attr.value.toLowerCase();
                if (name.startsWith('on') || value.includes('javascript:')) {
                    el.removeAttribute(attr.name);
                }
            });
        });
        
        return doc.body.innerHTML;
    } catch (e) {
        console.error('Sanitization error:', e);
        // Fallback to text matching or basic escape if parsing fails
        return htmlString.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, '');
    }
}

// SPA Fallbacks to prevent ReferenceErrors when switching pages
window.openCreateGroupModal = window.openCreateGroupModal || function() {};
window.closeCreateGroupModal = window.closeCreateGroupModal || function() {};
window.handleLeaveGroup = window.handleLeaveGroup || function() {};
window.handleDeleteGroup = window.handleDeleteGroup || function() {};
window.handleClearGroupConversation = window.handleClearGroupConversation || function() {};
window.closeGroupInfoModal = window.closeGroupInfoModal || function() {};
window.closeChatLightbox = window.closeChatLightbox || function() {};
window.exitChatView = window.exitChatView || function() {};
window.closeFollowsModal = window.closeFollowsModal || function() {};
window.openFollowsModal = window.openFollowsModal || function() {};
