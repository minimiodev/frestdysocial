/**
 * activity.js — Real-time notification feed powered by Server-Sent Events (SSE)
 *
 * Features:
 * - Connects to sse_stream.php on page load
 * - Prepends new notifications to the feed in real-time
 * - Updates the header badge count across all nav elements
 * - Dismisses individual notifications with animation
 * - Clear-all via dismiss_notification.php
 * - Auto-reconnect on disconnect
 */

(function () {
    'use strict';

    // ─── State ──────────────────────────────────────────────────────────────
    let lastId        = (typeof ACTIVITY_LAST_ID !== 'undefined') ? ACTIVITY_LAST_ID : 0;
    let sseSource     = null;
    let reconnectTimer = null;
    let reconnectDelay = 3000; // ms, exponential backoff

    // ─── DOM references ──────────────────────────────────────────────────────
    const feed        = document.getElementById('activityFeed');
    const statusEl    = document.getElementById('sseStatus');
    const statusLabel = statusEl ? statusEl.querySelector('.sse-label') : null;
    const clearBtn    = document.getElementById('clearActivityBtn');

    // ─── Badge helpers ───────────────────────────────────────────────────────
    function updateBadges(count, chatCount = 0) {
        if (typeof window.updateGlobalBadges === 'function') {
            window.updateGlobalBadges(count, chatCount);
        } else {
            const badges = document.querySelectorAll('.notif-badge');
            badges.forEach(b => {
                if (count > 0) {
                    b.textContent = count > 99 ? '99+' : count;
                    b.style.display = 'inline-flex';
                    // Re-trigger animation
                    b.style.animation = 'none';
                    void b.offsetWidth;
                    b.style.animation = '';
                } else {
                    b.style.display = 'none';
                }
            });
        }
    }

    // ─── SSE Connection status helpers ──────────────────────────────────────
    function setStatus(state, text) {
        if (!statusEl) return;
        statusEl.className = 'sse-status ' + state;
        if (statusLabel) statusLabel.textContent = text;
    }

    // ─── Build a notification HTML element from JSON data ────────────────────
    const REACTION_LABELS = {
        like: '👍 Thích', love: '❤️ Yêu thích', haha: '😂 Haha',
        wow: '😮 Wow',   sad: '😢 Buồn',        angry: '😡 Phẫn nộ',
    };

    const TYPE_BADGES = {
        reaction: { bg: '#ef4444', icon: 'fa-heart' },
        reply:    { bg: '#3b82f6', icon: 'fa-comment' },
        follow:   { bg: '#10b981', icon: 'fa-user-plus' },
        repost:   { bg: '#8b5cf6', icon: 'fa-retweet' },
    };

    function buildMessage(type, detail) {
        if (type === 'reaction') {
            const label = REACTION_LABELS[detail] || 'cảm xúc';
            return ' đã thả <strong>' + label + '</strong> cho bài viết của bạn';
        }
        if (type === 'reply')  return ' đã phản hồi bài viết của bạn';
        if (type === 'follow') return ' đã bắt đầu theo dõi bạn';
        if (type === 'repost') return ' đã đăng lại bài viết của bạn';
        return ' đã tương tác với bạn';
    }

    function buildNotifEl(data) {
        const clickTarget = data.ref_post_id
            ? 'detail.php?id=' + (data.post_token || data.ref_post_id)
            : 'profile.php?username=' + encodeURIComponent(data.actor_username);

        const badge  = TYPE_BADGES[data.type] || { bg: '#6b7280', icon: 'fa-bell' };
        const avatar = data.actor_avatar || (AVATARS_URL_PHP + '/avatar_default.png');

        const el = document.createElement('a');
        el.className = 'activity-item new-notif';
        el.id = 'notif-' + data.id;
        el.dataset.notifId = data.id;
        el.href = clickTarget;
        el.style.cssText = 'padding-right:42px; animation: slideInTop 0.35s ease both; text-decoration:none; color:inherit;';

        el.innerHTML = `
            <div style="position:relative; flex-shrink:0;">
                <img src="${escapeHTML(avatar)}"
                     onerror="this.src='${escapeHTML(AVATARS_URL_PHP)}/avatar_default.png'"
                     style="width:46px; height:46px; border-radius:50%; object-fit:cover; border:1.5px solid var(--border-color);">
                <div class="activity-icon-badge" style="background:${badge.bg}; color:#fff;">
                    <i class="fa-solid ${badge.icon}"></i>
                </div>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="font-size:14px; line-height:1.5; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                    <span class="notif-new-dot" title="Mới"></span>
                    <span style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                        <strong style="color:var(--text-primary);">@${escapeHTML(data.actor_username)}</strong>
                    </span>
                    ${buildMessage(data.type, data.detail)}
                </div>
                <div style="color:var(--text-muted); font-size:11.5px; margin-top:3px;">
                    <i class="fa-regular fa-clock" style="margin-right:3px;"></i>Vừa xong
                </div>
                ${data.type === 'reply' && data.detail ? `
                    <div style="font-size:12.5px; color:var(--text-secondary); margin-top:8px; background:var(--bg-tertiary); padding:8px 12px; border-radius:var(--radius-sm); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%;">
                        "${escapeHTML(data.detail.substring(0, 100))}"
                    </div>` : ''}
            </div>
            <button class="dismiss-notif-btn"
               onclick="event.preventDefault(); event.stopPropagation(); dismissNotification(${data.id}, this)"
               title="Xóa thông báo này">
                <i class="fa-solid fa-xmark"></i>
            </button>`;

        return el;
    }

    function escapeHTML(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // ─── Remove empty state placeholder if present ───────────────────────────
    function removeEmptyState() {
        const empty = document.getElementById('emptyState');
        if (empty) empty.remove();

        // Show clear-all button if hidden
        if (clearBtn && clearBtn.style.display === 'none') {
            clearBtn.style.display = '';
        }
    }

    // ─── SSE Connect ─────────────────────────────────────────────────────────
    function connectSSE() {
        if (window.FREST_CONFIG && window.FREST_CONFIG.disableSSE) {
            console.log("SSE Activity: Vô hiệu hóa SSE do máy chủ đơn luồng, chuyển sang AJAX Polling...");
            if (window.activityPollingInterval) {
                clearInterval(window.activityPollingInterval);
            }
            setStatus('connected', 'Thời gian thực (Polling)');
            const doActivityPoll = () => {
                if (document.hidden) {
                    console.log("SSE Activity Polling: Tab đang ẩn, tạm dừng Polling.");
                    return;
                }
                fetch(SITE_URL_JS() + '/sse_stream.php?full=1&polling=1&last_id=' + lastId)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            updateBadges(data.unread_count, data.unread_chat_count || 0);
                            if (data.notifications && data.notifications.length > 0) {
                                data.notifications.forEach(notif => {
                                    lastId = Math.max(lastId, notif.id);
                                    removeEmptyState();
                                    const el = buildNotifEl(notif);
                                    if (feed) {
                                        feed.insertBefore(el, feed.firstChild);
                                    }
                                });
                                if ('vibrate' in navigator) {
                                    navigator.vibrate(30);
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Activity polling error:', err));
            };
            doActivityPoll();
            window.activityPollingInterval = setInterval(doActivityPoll, 15000); // 15s để giảm tải tối đa cho hosting
            return;
        }

        // Nếu tab ẩn, hoãn kết nối SSE để giải phóng kết nối cho máy chủ online
        if (document.hidden) {
            console.log("SSE Activity: Tab đang ẩn, hoãn kết nối SSE.");
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
            setStatus('reconnecting', 'Tạm ngắt kết nối (Tab ẩn)');
            return;
        }

        if (sseSource) {
            sseSource.close();
            sseSource = null;
        }

        clearTimeout(reconnectTimer);
        setStatus('reconnecting', 'Đang kết nối...');

        const url = SITE_URL_JS() + '/sse_stream.php?full=1&last_id=' + lastId;
        sseSource = new EventSource(url);

        sseSource.onopen = function () {
            setStatus('connected', 'Thời gian thực');
            reconnectDelay = 3000; // reset backoff
        };

        sseSource.onerror = function () {
            setStatus('reconnecting', 'Đang kết nối lại...');
            sseSource.close();
            sseSource = null;
            if (!document.hidden) {
                reconnectDelay = Math.min(reconnectDelay * 1.5, 30000);
                reconnectTimer = setTimeout(connectSSE, reconnectDelay);
            }
        };

        // New notification event
        sseSource.addEventListener('notification', function (e) {
            try {
                const data = JSON.parse(e.data);
                lastId = Math.max(lastId, data.id);
                removeEmptyState();

                const el = buildNotifEl(data);
                if (feed) {
                    feed.insertBefore(el, feed.firstChild);
                }

                // Subtle sound / vibration (optional, respects user preferences)
                if ('vibrate' in navigator) {
                    navigator.vibrate(30);
                }
            } catch (err) {
                console.warn('SSE notification parse error:', err);
            }
        });

        // Badge sync event
        sseSource.addEventListener('badge', function (e) {
            try {
                const data = JSON.parse(e.data);
                updateBadges(data.unread_count, data.unread_chat_count || 0);
            } catch (err) {}
        });

        // Initial sync event (on first connect)
        sseSource.addEventListener('sync', function (e) {
            try {
                const data = JSON.parse(e.data);
                updateBadges(data.unread_count, data.unread_chat_count || 0);
                if (data.last_id > lastId) lastId = data.last_id;
                setStatus('connected', 'Thời gian thực');
            } catch (err) {}
        });

        // Server-triggered reconnect (after timeout)
        sseSource.addEventListener('reconnect', function () {
            sseSource.close();
            sseSource = null;
            if (!document.hidden) {
                reconnectTimer = setTimeout(connectSSE, 500);
            }
        });
    }

    // ─── Dismiss a single notification ───────────────────────────────────────
    window.dismissNotification = function (id, btnEl) {
        const item = document.getElementById('notif-' + id);
        if (!item) return;

        // Animate out
        item.classList.add('activity-item-removing');
        setTimeout(function () {
            if (item.parentNode) item.parentNode.removeChild(item);

            // Show empty state if feed is now empty
            if (feed && feed.children.length === 0) {
                feed.innerHTML = `
                    <div id="emptyState" style="padding:60px 20px; text-align:center; color:var(--text-secondary); background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:var(--radius-md);">
                        <i class="fa-regular fa-bell-slash" style="font-size:40px; margin-bottom:16px; opacity:0.2; display:block;"></i>
                        <p style="margin:0;">Không còn thông báo nào.</p>
                    </div>`;
            }
        }, 300);

        // Call dismiss API
        fetch(SITE_URL_JS() + '/dismiss_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const chatBadgeEl = document.getElementById('desktop-chat-badge');
                const existingChatCount = chatBadgeEl ? parseInt(chatBadgeEl.textContent) || 0 : 0;
                updateBadges(data.unread_count, existingChatCount);
            }
        })
        .catch(err => console.warn('Dismiss error:', err));
    };

    // ─── Clear all ───────────────────────────────────────────────────────────
    window.clearActivity = function () {
        if (!confirm('Xóa toàn bộ thông báo? Hành động này không thể hoàn tác.')) return;

        if (clearBtn) {
            clearBtn.disabled = true;
            clearBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xóa...';
        }

        fetch(SITE_URL_JS() + '/dismiss_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=clear_all',
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (feed) {
                    feed.style.transition = 'opacity 0.4s ease';
                    feed.style.opacity = '0';
                    setTimeout(function () {
                        feed.innerHTML = `
                            <div id="emptyState" style="padding:60px 20px; text-align:center; color:var(--text-secondary); background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:var(--radius-md);">
                                <i class="fa-regular fa-bell-slash" style="font-size:40px; margin-bottom:16px; opacity:0.2; display:block;"></i>
                                <p style="margin:0;">Đã xóa tất cả thông báo.</p>
                            </div>`;
                        feed.style.opacity = '1';
                    }, 400);
                }
                if (clearBtn) clearBtn.style.display = 'none';
                updateBadges(0);
            } else {
                if (clearBtn) {
                    clearBtn.disabled = false;
                    clearBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Xóa tất cả';
                }
            }
        })
        .catch(function () {
            if (clearBtn) {
                clearBtn.disabled = false;
                clearBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Xóa tất cả';
            }
        });
    };

    // Start SSE only after the window has fully loaded to prevent the browser tab loading spinner from spinning forever
    var sseStarted = false;
    var startSSE = function() {
        if (sseStarted) return;
        sseStarted = true;
        connectSSE();
    };
    if (document.readyState === 'complete') {
        startSSE();
    } else {
        window.addEventListener('load', startSSE);
        // Fallback: connect after 3 seconds anyway if load event is delayed
        setTimeout(startSSE, 3000);
    }

    // Close SSE connection when user leaves the page to free resources
    window.addEventListener('beforeunload', function () {
        if (sseSource) {
            sseSource.close();
            sseSource = null;
        }
        clearTimeout(reconnectTimer);
    });

    // Lắng nghe visibilitychange để tối ưu hóa kết nối SSE/Polling trên hosting online
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log("SSE Activity: Tab bị ẩn, ngắt kết nối SSE hoặc hoãn Polling để tối ưu tài nguyên máy chủ.");
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
            clearTimeout(reconnectTimer);
            setStatus('reconnecting', 'Tạm ngắt kết nối (Tab ẩn)');
        } else {
            console.log("SSE Activity: Tab hoạt động trở lại, kết nối lại/cập nhật dữ liệu.");
            if (window.FREST_CONFIG && window.FREST_CONFIG.disableSSE) {
                // Trigger cập nhật ngay lập tức thay vì đợi 15s
                fetch(SITE_URL_JS() + '/sse_stream.php?full=1&polling=1&last_id=' + lastId)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            updateBadges(data.unread_count, data.unread_chat_count || 0);
                            if (data.notifications && data.notifications.length > 0) {
                                data.notifications.forEach(notif => {
                                    lastId = Math.max(lastId, notif.id);
                                    removeEmptyState();
                                    const el = buildNotifEl(notif);
                                    if (feed) {
                                        feed.insertBefore(el, feed.firstChild);
                                    }
                                });
                                if ('vibrate' in navigator) {
                                    navigator.vibrate(30);
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Activity polling error:', err));
            } else {
                connectSSE();
            }
        }
    });

    // SPA cleanup export to allow parent SPA to close connection on page transition
    window.cleanupActivityPage = function() {
        if (sseSource) {
            console.log("SPA cleanup: Đóng kết nối SSE thông báo.");
            sseSource.close();
            sseSource = null;
        }
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        window.cleanupActivityPage = null;
    };

})();
