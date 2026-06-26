/**
 * Reactions Controller - Frest App
 */

/**
 * Handle AJAX Reactions & picker popover
 */
function initReactionActions() {
    if (window.reactionActionsInitialized) return;
    window.reactionActionsInitialized = true;

    document.body.addEventListener('click', (e) => {
        const emojiEl = e.target.closest('.reaction-emoji');
        if (emojiEl) {
            e.stopPropagation();
            e.preventDefault();
            const container = emojiEl.closest('.reaction-container');
            if (container) {
                const postId = container.getAttribute('data-post-id');
                const type = emojiEl.getAttribute('data-reaction');
                
                const picker = container.querySelector('.reaction-picker-panel');
                if (picker) picker.classList.remove('active');
                
                sendReaction(postId, type, container);
            }
            return;
        }

        const reactBtn = e.target.closest('.react-btn');
        if (reactBtn) {
            e.stopPropagation();
            e.preventDefault();
            const container = reactBtn.closest('.reaction-container');
            const postId = reactBtn.getAttribute('data-post-id');
            const activeType = reactBtn.getAttribute('data-active-type');
            
            const picker = container?.querySelector('.reaction-picker-panel');
            if (picker && window.innerWidth <= 768) {
                const isActive = picker.classList.toggle('active');
                if (isActive) return;
            }
            
            const type = activeType ? activeType : 'like';
            sendReaction(postId, type, container);
        }
    });
}

function sendReaction(postId, type, container) {
    if (!container) return;
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('type', type);

    fetch(`${SITE_URL_JS()}/react.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = container.querySelector('.react-btn');
            const statsContainer = container.closest('.frest-right')?.querySelector('.likes-stat');
            
            const emojis = {
                like: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍">',
                love: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️">',
                haha: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂">',
                wow: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮">',
                sad: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢">',
                angry: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡">'
            };
            const emojiLabels = {like: 'Thích', love: 'Yêu thích', haha: 'Haha', wow: 'Wow', sad: 'Buồn', angry: 'Phẫn nộ'};
            
            if (data.status === 'unreacted') {
                btn.classList.remove('active');
                btn.removeAttribute('data-active-type');
                btn.innerHTML = '<i class="fa-regular fa-thumbs-up"></i>';
            } else {
                btn.classList.add('active');
                btn.setAttribute('data-active-type', data.active_type);
                btn.innerHTML = emojis[data.active_type] || '👍';
                
                if (data.status === 'reacted' || data.status === 'updated') {
                    showToast(`Đã thả cảm xúc ${emojiLabels[data.active_type]}!`);
                }
            }

            if (data.total > 0) {
                const countSpan = document.createElement('span');
                countSpan.className = 'action-count';
                countSpan.style.fontSize = '12.5px';
                countSpan.style.marginLeft = '6px';
                countSpan.style.fontWeight = '500';
                countSpan.textContent = data.total;
                btn.appendChild(countSpan);
            }

            if (statsContainer) {
                if (data.total > 0) {
                    let badgesHtml = '<span class="reactions-badges">';
                    data.types.forEach(t => {
                        badgesHtml += emojis[t] ?? '';
                    });
                    badgesHtml += `</span> <span class="likes-count">${data.total}</span> lượt tương tác`;
                    statsContainer.innerHTML = badgesHtml;
                } else {
                    statsContainer.innerHTML = '<span class="likes-count">0</span> tương tác';
                }
            }
        } else {
            showToast(data.message || 'Không thể thực hiện tương tác.');
        }
    })
    .catch(err => {
        console.error('Reaction error:', err);
        showToast('Lỗi kết nối. Vui lòng thử lại.');
    });
}

/**
 * AJAX Reactors List populator
 */
function initReactorsModal() {
    if (window.reactorsModalInitialized) return;
    window.reactorsModalInitialized = true;

    document.body.addEventListener('click', (e) => {
        const likesStat = e.target.closest('.likes-stat');
        if (!likesStat) return;
        
        e.stopPropagation();
        const card = likesStat.closest('.frest-card');
        if (!card) return;
        
        const postId = card.getAttribute('data-post-id');
        if (!postId) return;
        
        fetch(`${SITE_URL_JS()}/get_reactions.php?post_id=${postId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('reactors-modal');
                    const listContainer = document.getElementById('reactors-modal-list');
                    if (modal && listContainer) {
                        listContainer.innerHTML = '';
                        if (data.reactors.length === 0) {
                            listContainer.innerHTML = '<p style="text-align:center; color: var(--text-secondary); font-size: 13px; font-style:italic; padding: 20px 0;">Chưa có tương tác nào.</p>';
                        } else {
                            const emojis = {
                                like: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Thumbs%20up/Default/3D/thumbs_up_3d_default.png" alt="👍">',
                                love: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Red%20heart/3D/red_heart_3d.png" alt="❤️">',
                                haha: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20with%20tears%20of%20joy/3D/face_with_tears_of_joy_3d.png" alt="😂">',
                                wow: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Face%20screaming%20in%20fear/3D/face_screaming_in_fear_3d.png" alt="😮">',
                                sad: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Loudly%20crying%20face/3D/loudly_crying_face_3d.png" alt="😢">',
                                angry: '<img class="reaction-badge-img" src="https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@latest/assets/Angry%20face/3D/angry_face_3d.png" alt="😡">'
                            };
                            data.reactors.forEach(r => {
                                const row = document.createElement('div');
                                row.style.display = 'flex';
                                row.style.alignItems = 'center';
                                row.style.justifyContent = 'space-between';
                                row.style.padding = '8px 0';
                                row.style.borderBottom = '1px solid var(--border-color)';
                                
                                row.innerHTML = `
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <a href="profile.php?username=${r.username}">
                                            <img src="${r.avatar_url}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                                        </a>
                                        <div style="display: flex; align-items: center; gap: 4px;">
                                            <a href="profile.php?username=${r.username}" style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);">
                                                @${r.username}
                                            </a>
                                            ${r.badge_html}
                                        </div>
                                    </div>
                                    <span style="font-size: 20px; user-select: none; display: inline-flex; align-items: center;">${emojis[r.reaction_type] || '👍'}</span>
                                `;
                                listContainer.appendChild(row);
                            });
                        }
                        modal.style.display = 'flex';
                    }
                } else {
                    showToast(data.message || 'Không thể tải danh sách tương tác.');
                }
            })
            .catch(err => {
                console.error('Error fetching reactors:', err);
                showToast('Lỗi kết nối. Vui lòng thử lại.');
            });
    });
}
