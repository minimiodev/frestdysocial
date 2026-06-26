/**
 * Notification Badge Controller (SSE-based Real-time) - Frest App
 */

function initGlobalNotifBadge() {
    if (window.globalNotifBadgeInitialized) return;
    window.globalNotifBadgeInitialized = true;

    // Nếu chưa đăng nhập, không khởi chạy kết nối realtime để tránh spam lỗi 401
    if (!window.FREST_USER || !window.FREST_USER.id || window.FREST_USER.id <= 0) {
        console.log("Notif Badge: Chưa đăng nhập, bỏ qua kết nối realtime.");
        return;
    }

    const desktopBadge = document.getElementById('desktop-notif-badge');
    const mobileBadge  = document.getElementById('mobile-notif-badge');
    const desktopChatBadge = document.getElementById('desktop-chat-badge');
    const mobileChatBadge = document.getElementById('mobile-chat-badge');
    const chatTabBadge = document.querySelector('.badge.badge-cyan');
    const notifTabBadge = document.querySelector('.badge.badge-purple');

    if (!desktopBadge && !mobileBadge && !desktopChatBadge && !mobileChatBadge) return;

    // Define global helper function to update badges from anywhere (like activity.js)
    window.updateGlobalBadges = function(notifCount, chatCount) {
        // Update notification badges
        [desktopBadge, mobileBadge].forEach(b => {
            if (!b) return;
            if (notifCount > 0) {
                b.textContent = notifCount > 99 ? '99+' : notifCount;
                b.style.display = 'inline-flex';
            } else {
                b.style.display = 'none';
            }
        });
        if (notifTabBadge) {
            if (notifCount > 0) {
                notifTabBadge.textContent = notifCount > 99 ? '99+' : notifCount;
                notifTabBadge.style.display = 'inline-block';
            } else {
                notifTabBadge.style.display = 'none';
            }
        }

        // Update chat badges
        [desktopChatBadge, mobileChatBadge].forEach(b => {
            if (!b) return;
            if (chatCount > 0) {
                b.textContent = chatCount > 99 ? '99+' : chatCount;
                b.style.display = 'inline-flex';
            } else {
                b.style.display = 'none';
            }
        });
        if (chatTabBadge) {
            if (chatCount > 0) {
                chatTabBadge.textContent = chatCount > 99 ? '99+' : chatCount;
                chatTabBadge.style.display = 'inline-block';
            } else {
                chatTabBadge.style.display = 'none';
            }
        }
    };

    // A fallback AJAX fetch for initial values
    function fetchInitialBadgeCounts() {
        fetch(SITE_URL_JS() + '/get_badge_count.php')
            .then(res => res.json())
            .then(data => {
                if (data && typeof data.unread_count !== 'undefined') {
                    window.updateGlobalBadges(data.unread_count, data.unread_chat_count || 0);
                }
            })
            .catch(err => console.error('Badge fetch error:', err));
    }

    // Fetch initial values on page load
    fetchInitialBadgeCounts();

    // If we are on activity.php, let activity.js handle the SSE connection (prevents double SSE connections)
    if (document.getElementById('activityFeed')) {
        console.log("SSE Badge: activity.php detected, delegating SSE to activity.js");
        return;
    }

    // Setup SSE connection for badge-only pages
    let sseSource = null;
    let reconnectTimer = null;
    let reconnectDelay = 3000;

    function connectBadgeSSE() {
        if (window.FREST_CONFIG && window.FREST_CONFIG.disableSSE) {
            console.log("SSE Badge: Vô hiệu hóa SSE do máy chủ đơn luồng, chuyển sang AJAX Polling...");
            if (window.badgePollingInterval) {
                clearInterval(window.badgePollingInterval);
            }
            const doBadgePoll = () => {
                if (document.hidden) {
                    console.log("SSE Badge Polling: Tab đang ẩn, tạm dừng Polling.");
                    return;
                }
                fetch(SITE_URL_JS() + '/get_badge_count.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data && typeof data.unread_count !== 'undefined') {
                            window.updateGlobalBadges(data.unread_count, data.unread_chat_count || 0);
                        }
                    })
                    .catch(err => console.error('Badge polling error:', err));
            };
            doBadgePoll();
            window.badgePollingInterval = setInterval(doBadgePoll, 20000); // 20s để giảm tải tối đa cho hosting
            return;
        }

        // Nếu tab đang ẩn thì không kết nối SSE để giải phóng tài nguyên trên máy chủ online
        if (document.hidden) {
            console.log("SSE Badge: Tab đang ẩn, hoãn kết nối SSE.");
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
            return;
        }

        if (sseSource) {
            sseSource.close();
            sseSource = null;
        }
        clearTimeout(reconnectTimer);

        const url = SITE_URL_JS() + '/sse_stream.php'; // badge-only mode: last_id=0, no full param
        sseSource = new EventSource(url);

        sseSource.onopen = function () {
            reconnectDelay = 3000;
        };

        sseSource.onerror = function () {
            sseSource.close();
            sseSource = null;
            if (!document.hidden) {
                reconnectDelay = Math.min(reconnectDelay * 1.5, 30000);
                reconnectTimer = setTimeout(connectBadgeSSE, reconnectDelay);
            }
        };

        // Listen for sync event (emitted on first connect)
        sseSource.addEventListener('sync', function (e) {
            try {
                const data = JSON.parse(e.data);
                window.updateGlobalBadges(data.unread_count, data.unread_chat_count || 0);
            } catch (err) {}
        });

        // Listen for badge event
        sseSource.addEventListener('badge', function (e) {
            try {
                const data = JSON.parse(e.data);
                window.updateGlobalBadges(data.unread_count, data.unread_chat_count || 0);
            } catch (err) {}
        });

        // Reconnect event from server
        sseSource.addEventListener('reconnect', function () {
            sseSource.close();
            sseSource = null;
            if (!document.hidden) {
                reconnectTimer = setTimeout(connectBadgeSSE, 500);
            }
        });
    }

    // Connect SSE when window loaded or complete
    if (document.readyState === 'complete') {
        connectBadgeSSE();
    } else {
        window.addEventListener('load', connectBadgeSSE);
    }

    // Lắng nghe visibilitychange để tối ưu kết nối SSE/Polling trên hosting online
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log("SSE Badge: Tab bị ẩn, ngắt kết nối SSE hoặc hoãn Polling để tối ưu tài nguyên máy chủ.");
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
            clearTimeout(reconnectTimer);
        } else {
            console.log("SSE Badge: Tab hoạt động trở lại, kết nối lại/cập nhật dữ liệu.");
            if (window.FREST_CONFIG && window.FREST_CONFIG.disableSSE) {
                // Trigger cập nhật ngay lập tức thay vì đợi 20s
                fetch(SITE_URL_JS() + '/get_badge_count.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data && typeof data.unread_count !== 'undefined') {
                            window.updateGlobalBadges(data.unread_count, data.unread_chat_count || 0);
                        }
                    })
                    .catch(err => console.error('Badge polling error:', err));
            } else {
                connectBadgeSSE();
            }
        }
    });

    // Clean up SSE connection before unload
    window.addEventListener('beforeunload', () => {
        if (sseSource) {
            sseSource.close();
            sseSource = null;
        }
        clearTimeout(reconnectTimer);
    });
}
