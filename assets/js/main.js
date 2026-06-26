/**
 * Main JS Controller - Frest App (Upgraded with SPA Engine & Security Shields)
 * Consolidates and initializes all separate functional modules.
 */

// Global Module Initializer
window.initializeAllModules = function() {
    if (window.modulesInitialized) {
        // Chỉ chạy lại các khởi tạo động cho phần tử mới (như video player)
        if (typeof initCustomVideos === 'function') initCustomVideos();
        
        // Khởi động lại các event listener trực tiếp của các tab, cài đặt, autocomplete và composer khi SPA tải trang mới
        if (typeof initProProfileTabs === 'function') initProProfileTabs();
        if (typeof initStandardProfileTabs === 'function') initStandardProfileTabs();
        if (typeof initAccountSettings === 'function') initAccountSettings();
        if (typeof initAvatarCropper === 'function') initAvatarCropper();
        if (typeof initComposeModal === 'function') initComposeModal();
        if (typeof initCircularUpload === 'function') initCircularUpload();
        if (typeof initMentionAutocomplete === 'function') initMentionAutocomplete();
        if (typeof initSearchAutocomplete === 'function') initSearchAutocomplete();
        
        initMobileNavBounce();
        return;
    }
    window.modulesInitialized = true;

    // 1. Theme and Utilities (from theme.js)
    if (typeof initTheme === 'function') initTheme();

    // 2. Compose modal & Upload progress (from compose.js)
    if (typeof initComposeModal === 'function') initComposeModal();
    if (typeof initCircularUpload === 'function') initCircularUpload();

    // 3. Post interactions, Lightbox & Video player (from post.js)
    if (typeof initPostActions === 'function') initPostActions();
    if (typeof initShareActions === 'function') initShareActions();
    if (typeof initRepostLogic === 'function') initRepostLogic();
    if (typeof initCustomVideos === 'function') initCustomVideos();
    if (typeof initLinkPreview === 'function') initLinkPreview();
    if (typeof initDynamicLinkPreviews === 'function') initDynamicLinkPreviews();

    // 4. Reactions & Reactors modal (from reaction.js)
    if (typeof initReactionActions === 'function') initReactionActions();
    if (typeof initReactorsModal === 'function') initReactorsModal();

    // 5. Follow actions & modals (from follow.js)
    if (typeof initFollowActions === 'function') initFollowActions();

    // 6. NSFW / Age verification (from nsfw.js)
    if (typeof initNSFWLogic === 'function') initNSFWLogic();

    // 7. Auto suggest mentions (from autocomplete.js)
    if (typeof initMentionAutocomplete === 'function') initMentionAutocomplete();
    if (typeof initSearchAutocomplete === 'function') initSearchAutocomplete();

    // 8. Global notification badge via SSE (from notif_badge.js)
    if (typeof initGlobalNotifBadge === 'function') initGlobalNotifBadge();

    // 9. Account settings switcher & policies (from account.js)
    if (typeof initAccountSettings === 'function') initAccountSettings();
    if (typeof initAvatarCropper === 'function') initAvatarCropper();
    if (typeof initIdentityDropdown === 'function') initIdentityDropdown();
    if (typeof initVerificationBadges === 'function') initVerificationBadges();
    if (typeof initVerificationPopups === 'function') initVerificationPopups();
    if (typeof initPolicyPopups === 'function') initPolicyPopups();
    if (typeof initProProfileTabs === 'function') initProProfileTabs();
    if (typeof initStandardProfileTabs === 'function') initStandardProfileTabs();

    // 10. Custom Security Shields and Mobile Nav Micro-interactions
    initAntiDevTools();
    initMobileNavBounce();
};

/**
 * 1. Chống mở DevTools (F12) và nhấp chuột phải để bảo vệ mã nguồn giao diện
 */
function initAntiDevTools() {
    // Ngăn chặn menu chuột phải
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    }, false);

    // Chặn các phím nóng DevTools phổ biến
    document.addEventListener('keydown', function(e) {
        // Chặn phím F12
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }
        // Chặn Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67 || e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }
        // Chặn Ctrl+U (Xem nguồn trang)
        if (e.ctrlKey && (e.keyCode === 85 || e.key === 'U' || e.key === 'u')) {
            e.preventDefault();
            return false;
        }
    }, false);
}

/**
 * 2. Hiệu ứng nảy sinh động (Bounce) khi chạm vào thanh điều hướng di động
 */
function initMobileNavBounce() {
    const navItems = document.querySelectorAll('.mobile-bottom-nav .mobile-nav-item');
    navItems.forEach(item => {
        // Remove previous listener to prevent duplication during SPA re-initialization
        item.removeEventListener('click', triggerBounce);
        item.addEventListener('click', triggerBounce);
    });

    function triggerBounce(e) {
        const item = e.currentTarget;
        item.classList.add('clicked');
        
        // Haptic feedback (Rung nhẹ trên thiết bị di động nếu trình duyệt hỗ trợ)
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(12);
        }
        
        setTimeout(() => {
            item.classList.remove('clicked');
        }, 400);
    }
}

/**
 * 3. SPA AJAX Navigation Engine - Chuyển trang không reload mượt mà
 */
function initSPAEngine() {
    if (window.spaEngineInitialized) return;
    window.spaEngineInitialized = true;

    // Tiến trình progress bar (Đã loại bỏ hoàn toàn theo yêu cầu của người dùng)
    function startProgressBar() {}
    function completeProgressBar() {}

    // Kiểm tra tính hợp lệ của link trước khi SPA chuyển hướng
    function shouldHandleLink(link) {
        // Nếu chưa đăng nhập, không dùng SPA Engine để tránh fetch vòng vo gây delay chuyển hướng
        if (!window.FREST_USER || !window.FREST_USER.id || window.FREST_USER.id <= 0) {
            return false;
        }

        if (!link.href) return false;
        
        // Bỏ qua các giao thức đặc biệt hoặc anchor link nội bộ
        if (link.href.startsWith('javascript:') || link.href.startsWith('#') || link.href.includes('mailto:') || link.href.includes('tel:')) {
            return false;
        }
        
        // Chỉ xử lý các link cùng tên miền gốc (origin)
        if (link.origin !== window.location.origin) return false;
        
        // Tránh tải file dạng download, liên kết mở tab mới hoặc loại trừ cụ thể
        if (link.hasAttribute('download') || link.getAttribute('target') === '_blank' || link.classList.contains('no-pjax')) {
            return false;
        }
        
        // Tránh thư mục admin hoặc một số file PHP nghiệp vụ reload hoàn toàn
        const path = link.pathname;
        if (path.includes('/admin/') || 
            path.endsWith('logout.php') || 
            path.endsWith('db_upgrade.php') || 
            path.endsWith('download.php') ||
            path.endsWith('login.php') || 
            path.endsWith('register.php') || 
            path.endsWith('forgot_password.php') || 
            path.endsWith('reset_password.php') || 
            path.endsWith('verify_phone.php')
        ) {
            return false;
        }
        
        return true;
    }

    // Đồng bộ class "active" cho thanh bottom nav di động, sidebar và desktop top bar
    function updateNavActiveStates(url) {
        const parser = new URL(url);
        const currentPage = parser.pathname.split('/').pop() || 'index.php';
        
        // Mobile Bottom Nav
        document.querySelectorAll('.mobile-bottom-nav .mobile-nav-item').forEach(item => {
            const itemUrl = new URL(item.href);
            const itemPage = itemUrl.pathname.split('/').pop() || 'index.php';
            if (itemPage === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
        
        // Sidebar Nav (Desktop)
        document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
            const itemUrl = new URL(item.href);
            const itemPage = itemUrl.pathname.split('/').pop() || 'index.php';
            if (itemPage === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Top Bar Tabs (Desktop)
        document.querySelectorAll('.top-bar-tabs .top-tab').forEach(item => {
            const itemUrl = new URL(item.href);
            const itemPage = itemUrl.pathname.split('/').pop() || 'index.php';
            if (itemPage === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    // Chặn và thu thập các hàm listener của sự kiện DOMContentLoaded của trang mới
    let pjaxDOMListeners = [];
    const originalAddEventListener = document.addEventListener;
    
    document.addEventListener = function(type, listener, options) {
        if (type === 'DOMContentLoaded') {
            pjaxDOMListeners.push(listener);
        }
        originalAddEventListener.call(document, type, listener, options);
    };

    // Tải và chạy các script riêng biệt đi kèm theo trang đích
    function executePageScripts(newDoc, currentUrl) {
        pjaxDOMListeners = []; // Clear
        
        const commonScripts = [
            'theme.js', 'compose.js', 'post.js', 'reaction.js', 'follow.js', 
            'nsfw.js', 'autocomplete.js', 'notif_badge.js', 'account.js', 
            'report_block.js', 'features.js', 'main.js', 'pwa.js'
        ];
        
        const newScripts = Array.from(newDoc.querySelectorAll('script'));
        const scriptsToLoad = [];
        
        newScripts.forEach(script => {
            const src = script.getAttribute('src');
            if (src) {
                const isCommon = commonScripts.some(s => src.includes(s));
                if (!isCommon) {
                    scriptsToLoad.push({ type: 'external', value: src });
                }
            } else if (script.textContent.trim()) {
                // Chỉ loại bỏ các script cấu hình global hệ thống ở header (đã được chạy lúc đầu)
                const isGlobalConfig = script.textContent.includes('window.FREST_CONFIG') || 
                                       script.textContent.includes('cat-page-loader') || 
                                       script.textContent.includes('frest-progress-bar');
                if (!isGlobalConfig) {
                    scriptsToLoad.push({ type: 'inline', value: script.textContent });
                }
            }
        });

        let loadedCount = 0;
        function loadNextExternal() {
            const extScripts = scriptsToLoad.filter(s => s.type === 'external');
            if (loadedCount < extScripts.length) {
                const s = extScripts[loadedCount];
                // Thêm timestamp để trình duyệt load lại mã nguồn
                const cacheBustSrc = s.value + (s.value.includes('?') ? '&' : '?') + 'pjax=' + Date.now();
                
                const newScriptEl = document.createElement('script');
                newScriptEl.src = cacheBustSrc;
                newScriptEl.async = false;
                newScriptEl.onload = function() {
                    loadedCount++;
                    loadNextExternal();
                };
                newScriptEl.onerror = function() {
                    loadedCount++;
                    loadNextExternal();
                };
                document.body.appendChild(newScriptEl);
            } else {
                // Chạy các script inline
                scriptsToLoad.filter(s => s.type === 'inline').forEach(s => {
                    try {
                        const inlineScriptEl = document.createElement('script');
                        inlineScriptEl.textContent = s.value;
                        document.body.appendChild(inlineScriptEl);
                        inlineScriptEl.remove();
                    } catch (e) {
                        console.error("Lỗi thực thi inline script:", e);
                    }
                });

                // Chạy các listener DOMContentLoaded đã lưu lại
                pjaxDOMListeners.forEach(listener => {
                    try {
                        listener(new Event('DOMContentLoaded'));
                    } catch (e) {
                        console.error("Lỗi chạy listener DOMContentLoaded mới:", e);
                    }
                });
                
                // Khởi động lại các sự kiện toàn cục trên DOM mới
                if (typeof window.initializeAllModules === 'function') {
                    window.initializeAllModules();
                }
            }
        }

        loadNextExternal();
    }

    // Hàm chuyển trang chính
    function loadPage(url, isPopState = false) {
        // Dọn dẹp tài nguyên trang cũ trước khi tải trang mới qua SPA
        if (typeof window.cleanupChatPage === 'function') {
            try { window.cleanupChatPage(); } catch(e) { console.error("Lỗi dọn dẹp trang chat:", e); }
        }
        if (typeof window.cleanupActivityPage === 'function') {
            try { window.cleanupActivityPage(); } catch(e) { console.error("Lỗi dọn dẹp trang hoạt động:", e); }
        }

        startProgressBar();
        
        const contentWrapper = document.querySelector('.main-content-wrapper');
        if (contentWrapper) {
            contentWrapper.style.transition = 'opacity 0.15s ease';
            contentWrapper.style.opacity = '0.35';
        }
        
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                
                // Tự động chuyển hướng trình duyệt nếu fetch bị redirect tới trang đăng nhập/đăng ký
                if (res.url && (res.url.includes('/login.php') || res.url.includes('/verify_phone.php') || res.url.includes('/register.php') || res.url.includes('/forgot_password.php'))) {
                    window.location.href = res.url;
                    return;
                }
                
                return res.text();
            })
            .then(html => {
                if (!html) return;
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                
                // Cập nhật tiêu đề trang
                document.title = newDoc.title;
                
                // Cập nhật nội dung
                const newContent = newDoc.querySelector('.main-content-wrapper');
                if (contentWrapper && newContent) {
                    contentWrapper.innerHTML = newContent.innerHTML;
                    
                    // Copy class/attributes của wrapper mới
                    Array.from(newContent.attributes).forEach(attr => {
                        contentWrapper.setAttribute(attr.name, attr.value);
                    });
                }
                
                // Đẩy URL mới
                if (!isPopState) {
                    history.pushState(null, '', url);
                }
                
                // Cập nhật trạng thái active
                updateNavActiveStates(url);
                
                // Cuộn trang lên đầu mượt mà
                window.scrollTo({ top: 0, behavior: 'instant' });
                
                // Gỡ và nạp lại script
                executePageScripts(newDoc, url);
                
                // Hiển thị lại nội dung
                if (contentWrapper) {
                    contentWrapper.style.opacity = '1';
                }
                completeProgressBar();
            })
            .catch(err => {
                console.error("Lỗi chuyển trang SPA:", err);
                completeProgressBar();
                // Fallback tải trang truyền thống nếu lỗi
                window.location.href = url;
            });
    }

    // Sự kiện Click chuyển trang
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        
        if (shouldHandleLink(link)) {
            e.preventDefault();
            loadPage(link.href);
        }
    });

    // Sự kiện bấm nút Back/Forward
    window.addEventListener('popstate', function() {
        loadPage(window.location.href, true);
    });
}

// Khởi chạy hệ thống lúc ban đầu tải trang
document.addEventListener('DOMContentLoaded', () => {
    // Khởi tạo các module
    window.initializeAllModules();
    
    // Khởi chạy cơ chế chuyển trang mượt mà SPA
    initSPAEngine();
});
