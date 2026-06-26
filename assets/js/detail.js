/* ── Reply menu toggle ── */
function toggleReplyMenu(replyId, e) {
    e.stopPropagation();
    const menu = document.getElementById('reply-menu-' + replyId);
    const isOpen = menu.classList.contains('open');
    // close all others
    document.querySelectorAll('.reply-dropdown.open').forEach(m => m.classList.remove('open'));
    if (!isOpen) menu.classList.add('open');
}
document.addEventListener('click', (e) => {
    document.querySelectorAll('.reply-dropdown.open').forEach(m => m.classList.remove('open'));
    if (!e.target.closest('.reply-reaction-container')) {
        document.querySelectorAll('.reply-reaction-picker-panel.active').forEach(p => p.classList.remove('active'));
    }
});

/* ── Edit reply ── */
function startEditReply(replyId) {
    document.getElementById('reply-menu-' + replyId).classList.remove('open');
    document.getElementById('reply-content-display-' + replyId).style.display = 'none';
    const editArea = document.getElementById('reply-edit-area-' + replyId);
    editArea.classList.add('active');
    const ta = document.getElementById('reply-edit-input-' + replyId);
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
}

function cancelEditReply(replyId) {
    document.getElementById('reply-content-display-' + replyId).style.display = '';
    document.getElementById('reply-edit-area-' + replyId).classList.remove('active');
}

function saveEditReply(replyId) {
    const ta = document.getElementById('reply-edit-input-' + replyId);
    const newContent = ta.value.trim();
    if (!newContent) { showToast('Nội dung không được để trống.'); return; }

    const saveBtn = document.querySelector(`#reply-${replyId} .btn-save-reply`);
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Đang lưu...'; }

    fetch(`${SITE_URL_JS()}/reply_action.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=edit_reply&reply_id=${replyId}&content=${encodeURIComponent(newContent)}`
    })
    .then(r => r.json())
    .then(data => {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Lưu'; }
        if (data.success) {
            // Update display
            const display = document.getElementById('reply-content-display-' + replyId);
            display.innerHTML = data.new_content.replace(/\n/g, '<br>');
            display.style.display = '';
            // Mark as edited
            const card = document.getElementById('reply-' + replyId);
            if (!card.querySelector('.edited-badge')) {
                const badge = document.createElement('span');
                badge.className = 'edited-badge';
                badge.textContent = '(đã chỉnh sửa)';
                card.querySelector('.frest-time').insertAdjacentElement('afterend', badge);
            }
            document.getElementById('reply-edit-area-' + replyId).classList.remove('active');
            showToast('Đã cập nhật bình luận ✏️');
        } else {
            showToast('Lỗi: ' + (data.error || 'Không thể lưu'));
        }
    })
    .catch(() => {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Lưu'; }
        showToast('Lỗi kết nối.');
    });
}

/* ── Delete reply ── */
function deleteReply(replyId) {
    if (!confirm('Xóa bình luận này?')) return;

    fetch(`${SITE_URL_JS()}/reply_action.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_reply&reply_id=${replyId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('reply-' + replyId);
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'translateX(-10px)';
                setTimeout(() => card.remove(), 320);
            }
            showToast('Đã xóa bình luận.');
        } else {
            showToast('Lỗi: ' + (data.error || 'Không thể xóa'));
        }
    })
    .catch(() => showToast('Lỗi kết nối.'));
}

/* ── Sub-reply form toggle ── */
function toggleSubReplyForm(replyId) {
    const form = document.getElementById('sub-reply-form-' + replyId);
    if (form) {
        if (form.style.display === 'none') {
            form.style.display = 'block';
            const ta = form.querySelector('textarea');
            if (ta) ta.focus();
        } else {
            form.style.display = 'none';
        }
    }
}

/* ── Reply reactions ── */
document.body.addEventListener('click', (e) => {
    // 1. Handle emoji selection in reply reaction picker
    const emojiEl = e.target.closest('.reply-reaction-emoji');
    if (emojiEl) {
        e.stopPropagation();
        e.preventDefault();
        const container = emojiEl.closest('.reply-reaction-container');
        if (container) {
            const replyId = container.getAttribute('data-reply-id');
            const type = emojiEl.getAttribute('data-reaction');
            
            const picker = container.querySelector('.reply-reaction-picker-panel');
            if (picker) {
                picker.classList.remove('active');
            }
            
            sendReplyReaction(replyId, type, container);
        }
        return;
    }

    // 2. Handle reply react button click
    const reactBtn = e.target.closest('.reply-react-trigger-btn');
    if (reactBtn) {
        e.stopPropagation();
        e.preventDefault();
        const container = reactBtn.closest('.reply-reaction-container');
        if (container) {
            const replyId = reactBtn.getAttribute('data-reply-id');
            const activeType = reactBtn.getAttribute('data-active-type');
            
            const picker = container.querySelector('.reply-reaction-picker-panel');
            if (picker && window.innerWidth <= 768) {
                const isActive = picker.classList.toggle('active');
                if (isActive) return;
            }
            
            const type = activeType ? activeType : 'like';
            sendReplyReaction(replyId, type, container);
        }
    }
});

function sendReplyReaction(replyId, type, container) {
    if (!container) return;
    const formData = new FormData();
    formData.append('reply_id', replyId);
    formData.append('type', type);

    fetch(`${SITE_URL_JS()}/react.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = container.querySelector('.reply-react-trigger-btn');
            const statsContainer = document.getElementById('reply-likes-stat-' + replyId);
            const emojis = {like: '👍', love: '❤️', haha: '😂', wow: '😮', sad: '😢', angry: '😡'};
            const labels = {like: 'Thích', love: 'Yêu thích', haha: 'Haha', wow: 'Wow', sad: 'Buồn', angry: 'Phẫn nộ'};
            
            if (data.status === 'unreacted') {
                btn.classList.remove('active');
                btn.removeAttribute('data-active-type');
                btn.innerHTML = '<i class="fa-regular fa-thumbs-up"></i> Thích';
            } else {
                btn.classList.add('active');
                btn.setAttribute('data-active-type', data.active_type);
                btn.innerHTML = (emojis[data.active_type] || '👍') + ' ' + (labels[data.active_type] || 'Thích');
                
                if (data.status === 'reacted' || data.status === 'updated') {
                    showToast(`Đã bày tỏ cảm xúc ${emojis[data.active_type]}!`);
                }
            }

            // Update stats text
            if (statsContainer) {
                if (data.total > 0) {
                    statsContainer.style.display = '';
                    const badgesContainer = statsContainer.querySelector('.reply-reactions-badges');
                    const countSpan = statsContainer.querySelector('.reply-likes-count');
                    
                    if (badgesContainer) {
                        let badgesHtml = '';
                        data.types.forEach(t => {
                            badgesHtml += emojis[t] ?? '';
                        });
                        badgesContainer.innerHTML = badgesHtml;
                    }
                    if (countSpan) {
                        countSpan.textContent = data.total;
                    }
                } else {
                    statsContainer.style.display = 'none';
                }
            }
        } else {
            showToast(data.message || 'Không thể thực hiện tương tác.');
        }
    })
    .catch(err => {
        console.error('Reply reaction error:', err);
        showToast('Lỗi kết nối. Vui lòng thử lại.');
    });
}
