/**
 * Follow Actions & Follows Modal Controller - Frest App
 */

/**
 * Handle AJAX Follow actions
 */
function initFollowActions() {
    if (window.followActionsInitialized) return;
    window.followActionsInitialized = true;

    document.body.addEventListener('click', (e) => {
        const followBtn = e.target.closest('.follow-action-btn');
        if (!followBtn) return;

        const userId = followBtn.getAttribute('data-user-id');
        const pageId = followBtn.getAttribute('data-page-id');
        if (!userId && !pageId) return;

        let url = '';
        if (pageId) {
            url = `${SITE_URL_JS()}/follow.php?page_id=${pageId}`;
        } else {
            url = `${SITE_URL_JS()}/follow.php?user_id=${userId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const btnText = followBtn.querySelector('.btn-text');
                    const iconEl = followBtn.querySelector('i.fa-solid');
                    const followersSpan = document.getElementById('followers-count');
                    
                    if (data.status === 'followed') {
                        if (iconEl) {
                            iconEl.classList.remove('fa-circle-plus');
                            iconEl.classList.add('fa-circle-check');
                        }
                        if (btnText) btnText.innerText = pageId ? 'Đã thích' : 'Theo dõi';
                        showToast(pageId ? 'Đã thích Trang! 👍' : 'Đã bắt đầu theo dõi! 🤝');
                    } else {
                        if (iconEl) {
                            iconEl.classList.remove('fa-circle-check');
                            iconEl.classList.add('fa-circle-plus');
                        }
                        if (btnText) btnText.innerText = pageId ? 'Thích' : 'Theo dõi';
                        showToast(pageId ? 'Đã bỏ thích Trang.' : 'Đã bỏ theo dõi.');
                    }

                    if (followersSpan) {
                        followersSpan.innerText = data.followers_count;
                    }
                } else {
                    showToast(data.message || 'Không thể thực hiện theo dõi.');
                }
            })
            .catch(err => {
                console.error('Follow error:', err);
                showToast('Lỗi kết nối. Vui lòng thử lại.');
            });
    });
}

/**
 * Followers / Following modal functions
 */
function openFollowsModal(type, userId, username) {
    const modal = document.getElementById('follows-modal');
    const title = document.getElementById('follows-modal-title');
    const listContainer = document.getElementById('follows-modal-list');
    
    if (!modal || !title || !listContainer) return;
    
    title.innerText = type === 'following' ? `Đang theo dõi` : `Người theo dõi`;
    listContainer.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin" style="margin-right:8px;"></i>Đang tải...</div>';
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    fetch(`${SITE_URL_JS()}/get_follows.php?user_id=${userId}&type=${type}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.users.length === 0) {
                    listContainer.innerHTML = `<div style="text-align:center; padding:32px 20px; color:var(--text-muted); font-size:13.5px;">Chưa có ai.</div>`;
                    return;
                }
                
                let html = '';
                data.users.forEach(user => {
                    html += `
                        <div class="user-item" style="display:flex; align-items:center; justify-content:space-between; padding:10px 20px; border-bottom:1px solid rgba(255,255,255,0.02); text-align:left;">
                            <div style="display:flex; align-items:center; gap:12px; overflow:hidden;">
                                <a href="${user.profile_url}">
                                    <img src="${user.avatar_url}" style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid var(--border-color);">
                                </a>
                                <div style="overflow:hidden; text-align:left;">
                                    <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                        <a href="${user.profile_url}" style="font-weight:700; font-size:13.5px; color:var(--text-primary); text-decoration:none;">${user.full_name || user.username}</a>
                                        ${user.badge_html || ''}
                                    </div>
                                    <div style="font-size:11.5px; color:var(--text-muted);">@${user.username}</div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = `<div style="text-align:center; padding:20px; color:var(--danger); font-size:13px;">Lỗi: ${data.error}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            listContainer.innerHTML = `<div style="text-align:center; padding:20px; color:var(--danger); font-size:13px;">Lỗi kết nối mạng.</div>`;
        });
}

function closeFollowsModal() {
    const modal = document.getElementById('follows-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Bind close events
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('follows-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.closest('.modal-close')) {
                closeFollowsModal();
            }
        });
    }
});
