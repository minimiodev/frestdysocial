/**
 * Mention Autocomplete Controller - Frest App
 */

/**
 * Mention Autocomplete (@username suggestions)
 * Attaches to all <textarea> elements on the page.
 * Shows a floating popup when user types @ followed by characters.
 */
function initMentionAutocomplete() {
    if (window.mentionAutocompleteInitialized) return;
    window.mentionAutocompleteInitialized = true;

    let popup = null;
    let activeIndex = -1;
    let mentionStart = -1;
    let currentTextarea = null;
    let debounceTimer = null;

    function closePopup() {
        if (popup) {
            popup.remove();
            popup = null;
        }
        activeIndex = -1;
        mentionStart = -1;
        currentTextarea = null;
    }

    function buildPopup(items, textarea) {
        if (popup) popup.remove();
        if (!items.length) return;

        popup = document.createElement('div');
        popup.className = 'mention-popup';

        items.forEach((item, idx) => {
            const el = document.createElement('div');
            el.className = 'mention-popup-item';
            el.dataset.idx = idx;

            const img = document.createElement('img');
            img.src = item.avatar;
            img.alt = item.handle;
            img.onerror = () => { img.src = SITE_URL_JS() + '/uploads/avatars/avatar_default.png'; };

            const info = document.createElement('div');
            info.className = 'mention-info';

            const handleSpan = document.createElement('span');
            handleSpan.className = 'mention-handle';
            handleSpan.textContent = '@' + item.handle;

            const nameSpan = document.createElement('span');
            nameSpan.className = 'mention-name';
            nameSpan.textContent = item.name;

            info.appendChild(handleSpan);
            info.appendChild(nameSpan);
            el.appendChild(img);
            el.appendChild(info);

            if (item.type === 'page') {
                const badge = document.createElement('span');
                badge.className = 'mention-badge';
                badge.textContent = 'Trang';
                el.appendChild(badge);
            }

            el.addEventListener('mousedown', (e) => {
                e.preventDefault();
                insertMention(item.handle, textarea);
                closePopup();
            });

            popup.appendChild(el);
        });

        const rect = textarea.getBoundingClientRect();
        popup.style.position = 'fixed';
        popup.style.top = (rect.bottom + 4) + 'px';
        popup.style.left = rect.left + 'px';
        popup.style.width = Math.min(rect.width, 300) + 'px';
        document.body.appendChild(popup);
        activeIndex = -1;
    }

    function highlightItem(newIdx) {
        if (!popup) return;
        const items = popup.querySelectorAll('.mention-popup-item');
        items.forEach((el, i) => el.classList.toggle('active', i === newIdx));
        activeIndex = newIdx;
    }

    function insertMention(handle, textarea) {
        if (!textarea) return;
        const val = textarea.value;
        const before = val.substring(0, mentionStart);
        const after = val.substring(textarea.selectionStart);
        const inserted = '@' + handle + ' ';
        textarea.value = before + inserted + after;
        const newCaret = before.length + inserted.length;
        textarea.selectionStart = textarea.selectionEnd = newCaret;
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function getMentionQuery(textarea) {
        const caret = textarea.selectionStart;
        const textBefore = textarea.value.substring(0, caret);
        const match = textBefore.match(/(?:^|[\s\n])@([\p{L}\p{N}_\.]{0,50})$/u);
        if (!match) return null;
        mentionStart = textBefore.lastIndexOf('@');
        return match[1];
    }

    function handleInput(e) {
        const textarea = e.target;
        if (textarea.tagName !== 'TEXTAREA') return;

        const query = getMentionQuery(textarea);
        if (query === null) {
            closePopup();
            return;
        }

        currentTextarea = textarea;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (query.length === 0) {
                closePopup();
                return;
            }
            const url = SITE_URL_JS() + '/search_mention.php?q=' + encodeURIComponent(query);
            fetch(url)
                .then(r => r.json())
                .then(items => {
                    if (getMentionQuery(textarea) === null) return;
                    buildPopup(items, textarea);
                })
                .catch(() => closePopup());
        }, 200);
    }

    function handleKeydown(e) {
        if (!popup) return;
        const items = popup.querySelectorAll('.mention-popup-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightItem(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightItem(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (e.key === 'Escape') {
            closePopup();
        }
    }

    document.addEventListener('click', (e) => {
        if (popup && !popup.contains(e.target)) {
            closePopup();
        }
    });

    document.addEventListener('input', handleInput);
    document.addEventListener('keydown', handleKeydown);

    document.addEventListener('blur', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            setTimeout(() => {
                if (!popup || !document.activeElement || document.activeElement.tagName !== 'TEXTAREA') {
                    closePopup();
                }
            }, 150);
        }
    }, true);
}

/**
 * Global Search Autocomplete (Users, Pages, Hashtags suggestions)
 * Attaches to search inputs: #search-input and #header-search-input.
 */
function initSearchAutocomplete() {
    const configs = [
        { inputId: 'search-input', suggestionsId: 'search-suggestions' },
        { inputId: 'header-search-input', suggestionsId: 'header-search-suggestions' }
    ];

    configs.forEach(cfg => {
        const input = document.getElementById(cfg.inputId);
        const suggestionsBox = document.getElementById(cfg.suggestionsId);
        if (!input || !suggestionsBox || input.dataset.autocompleteInitialized === 'true') return;
        input.dataset.autocompleteInitialized = 'true';

        let debounceTimer = null;
        let activeIndex = -1;

        function closeSuggestions() {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
            activeIndex = -1;
        }

        function highlightItem(newIdx) {
            const items = suggestionsBox.querySelectorAll('.search-suggestion-item');
            items.forEach((el, i) => el.classList.toggle('active', i === newIdx));
            activeIndex = newIdx;
            
            // Scroll highlighted item into view if needed
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        input.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(debounceTimer);
            
            if (query.length < 1) {
                closeSuggestions();
                return;
            }

            debounceTimer = setTimeout(() => {
                const apiURL = (typeof SITE_URL !== 'undefined' ? SITE_URL : '') + '/search_suggest.php?q=' + encodeURIComponent(query);
                fetch(apiURL)
                    .then(res => res.json())
                    .then(data => {
                        const hasUsers = data.users && data.users.length > 0;
                        const hasPages = data.pages && data.pages.length > 0;
                        const hasHashtags = data.hashtags && data.hashtags.length > 0;

                        if (!hasUsers && !hasPages && !hasHashtags) {
                            closeSuggestions();
                            return;
                        }

                        let html = '';

                        // Render Users
                        if (hasUsers) {
                            html += `<div class="search-suggestion-header">Thành viên</div>`;
                            data.users.forEach(user => {
                                html += `
                                    <a class="search-suggestion-item" href="profile.php?username=${user.username}">
                                        <img src="${user.avatar}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                                        <div style="flex: 1; min-width: 0; text-align: left;">
                                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                                                ${user.full_name} ${user.badge}
                                            </div>
                                            <div style="font-size: 10.5px; color: var(--text-muted);">@${user.username}</div>
                                        </div>
                                    </a>
                                `;
                            });
                        }

                        // Render Pages
                        if (hasPages) {
                            html += `<div class="search-suggestion-header">Trang</div>`;
                            data.pages.forEach(page => {
                                html += `
                                    <a class="search-suggestion-item" href="page.php?username=${page.username}">
                                        <img src="${page.avatar}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                                        <div style="flex: 1; min-width: 0; text-align: left;">
                                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                                                ${page.full_name} ${page.badge}
                                                <span class="badge" style="font-size: 8px; padding: 1px 4px; background: rgba(59, 130, 246, 0.15); color: var(--accent-primary); font-weight: 700; border-radius: 4px; margin-left: 4px;">Trang</span>
                                            </div>
                                            <div style="font-size: 10.5px; color: var(--text-muted);">${page.category} • @${page.username}</div>
                                        </div>
                                    </a>
                                `;
                            });
                        }

                        // Render Hashtags
                        if (hasHashtags) {
                            html += `<div class="search-suggestion-header">Hashtag</div>`;
                            data.hashtags.forEach(tag => {
                                html += `
                                    <a class="search-suggestion-item" href="search.php?q=%23${tag.tag}">
                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center; color: var(--accent-secondary); flex-shrink: 0; font-size: 13px;">
                                            <i class="fa-solid fa-hashtag"></i>
                                        </div>
                                        <div style="flex: 1; min-width: 0; text-align: left;">
                                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);">#${tag.tag}</div>
                                            <div style="font-size: 10.5px; color: var(--text-muted);">Tìm kiếm hashtag</div>
                                        </div>
                                    </a>
                                `;
                            });
                        }

                        suggestionsBox.innerHTML = html;
                        suggestionsBox.style.display = 'block';
                        activeIndex = -1;
                    })
                    .catch(() => closeSuggestions());
            }, 250);
        });

        // Keydown controls for navigation
        input.addEventListener('keydown', function(e) {
            const items = suggestionsBox.querySelectorAll('.search-suggestion-item');
            if (suggestionsBox.style.display === 'none' || !items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightItem(Math.min(activeIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightItem(Math.max(activeIndex - 1, 0));
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    window.location.href = items[activeIndex].getAttribute('href');
                }
            } else if (e.key === 'Escape') {
                closeSuggestions();
            }
        });

        // Click outside closes suggestions
        document.addEventListener('click', function(e) {
            if (e.target !== input && e.target !== suggestionsBox && !suggestionsBox.contains(e.target)) {
                closeSuggestions();
            }
        });

        // Refocusing shows suggestions again if input has content
        input.addEventListener('focus', function() {
            if (this.value.trim().length > 0 && suggestionsBox.children.length > 0) {
                suggestionsBox.style.display = 'block';
            }
        });
    });
}
