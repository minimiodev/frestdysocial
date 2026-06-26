/**
 * Stories Feature Script - Frest App
 */
document.addEventListener('DOMContentLoaded', function() {
    const storiesScrollWrapper = document.getElementById('stories-scroll-wrapper');
    const storyViewerModal = document.getElementById('story-viewer-modal');
    
    if (!storiesScrollWrapper || !storyViewerModal) return;
    
    // Select modal child elements
    const progressContainer = document.getElementById('story-progress-container');
    const viewerAvatar = document.getElementById('story-viewer-avatar');
    const viewerUsername = document.getElementById('story-viewer-username');
    const viewerTime = document.getElementById('story-viewer-time');
    const viewerCloseBtn = document.getElementById('story-viewer-close-btn');
    const viewerDeleteBtn = document.getElementById('story-viewer-delete-btn');
    const mediaContainer = document.getElementById('story-viewer-media-container');
    const viewerFooter = document.getElementById('story-viewer-footer');
    const emojiContainer = document.getElementById('story-emoji-fly-container');
    const navPrev = document.getElementById('story-nav-prev');
    const navNext = document.getElementById('story-nav-next');
    
    // Thêm các phần tử mute & view list
    const viewerMuteBtn = document.getElementById('story-viewer-mute-btn');
    const storyViewsModal = document.getElementById('story-views-modal');
    const storyViewsCloseBtn = document.getElementById('story-views-close-btn');
    const storyViewsList = document.getElementById('story-views-list');
    
    let storiesData = [];
    let currentGroupIndex = 0;
    let currentStoryIndex = 0;
    
    let slideTimer = null;
    let progressInterval = null;
    let storyDuration = 5000; // 5 seconds default per slide
    let startTime = 0;
    let elapsedTime = 0;
    
    // Trạng thái mute & pause
    let isStoryMuted = localStorage.getItem('frest_story_muted') === 'true';
    let isProgressPaused = false;
    let pausedTime = 0;
    
    // Get current logged in user ID from PHP global variable
    const currentUserId = window.FREST_USER ? parseInt(window.FREST_USER.id) : 0;
    
    // Initialize Mute Event Listener
    if (viewerMuteBtn) {
        updateMuteButtonIcon();
        viewerMuteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            isStoryMuted = !isStoryMuted;
            localStorage.setItem('frest_story_muted', isStoryMuted);
            
            // Mute/unmute video đang phát
            const activeVideo = mediaContainer.querySelector('video');
            if (activeVideo) {
                activeVideo.muted = isStoryMuted;
            }
            updateMuteButtonIcon();
        });
    }

    // Initialize Views List Modal Close
    if (storyViewsCloseBtn) {
        storyViewsCloseBtn.addEventListener('click', function() {
            if (storyViewsModal) storyViewsModal.style.display = 'none';
            resumeStoryProgress();
        });
    }
    if (storyViewsModal) {
        storyViewsModal.addEventListener('click', function(e) {
            if (e.target === storyViewsModal) {
                storyViewsModal.style.display = 'none';
                resumeStoryProgress();
            }
        });
    }

    // Load stories from API
    loadStories();
    
    function loadStories() {
        fetch(SITE_URL + '/get_stories.php')
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    storiesData = res.data || [];
                    renderStoriesBar();
                } else {
                    checkAndToggleContainer();
                }
            })
            .catch(err => {
                console.error("Lỗi tải stories:", err);
                checkAndToggleContainer();
            });
    }
    
    function checkAndToggleContainer() {
        const container = storiesScrollWrapper.closest('.stories-container');
        if (container) {
            const totalItems = storiesScrollWrapper.querySelectorAll('.story-item').length;
            if (totalItems > 0) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    }
    
    // Helper function to format display name to "First Name + Last Initial"
    function getShortName(fullName) {
        if (!fullName) return '';
        const parts = fullName.trim().split(/\s+/);
        if (parts.length > 1) {
            const firstName = parts[0];
            const lastName = parts[parts.length - 1];
            const lastInitial = lastName.charAt(0).toUpperCase() + '.';
            return firstName + ' ' + lastInitial;
        }
        return parts[0];
    }

    function renderStoriesBar() {
        // Clear any old dynamically rendered story items
        const dynamicItems = storiesScrollWrapper.querySelectorAll('.story-item:not(.current-user-story)');
        dynamicItems.forEach(item => item.remove());
        
        const currentUserItem = document.querySelector('.current-user-story');
        
        if (currentUserItem) {
            // Luôn clone để xóa sạch Event Listener cũ
            const newObj = currentUserItem.cloneNode(true);
            currentUserItem.parentNode.replaceChild(newObj, currentUserItem);
            
            const avatarWrapper = newObj.querySelector('.story-avatar-wrapper');
            if (avatarWrapper) {
                // Nút Add Story cố định ở đầu hàng luôn phẳng không có viền phát sáng
                avatarWrapper.className = 'story-avatar-wrapper';
            }
            
            const badge = newObj.querySelector('.add-story-badge');
            if (badge) {
                badge.style.display = 'flex'; // luôn hiển thị dấu cộng để thêm story
            }
            
            const label = newObj.querySelector('.story-username');
            if (label) {
                label.textContent = 'Add Story'; // Đổi nhãn nút đầu thành Add Story cho khớp screenshot
            }
            
            newObj.addEventListener('click', function() {
                window.location.href = SITE_URL + '/create_story.php';
            });
        }
        
        storiesData.forEach((group, index) => {
            // KHÔNG skip user hiện tại nữa! 
            // Nếu chính user hiện tại đã đăng story, nó cũng sẽ hiển thị ở bên cạnh như một story item bình thường.
            
            const ringClass = group.all_viewed ? 'viewed-story-ring' : 'active-story-ring';
            
            const storyItem = document.createElement('div');
            storyItem.className = 'story-item';
            
            // Định dạng tên rút gọn dạng "First Name + Last Initial" (ví dụ: "Dũng N.", "Alex R.")
            const rawName = group.full_name || group.username;
            const displayName = getShortName(rawName);
            
            storyItem.innerHTML = `
                <div class="story-avatar-wrapper ${ringClass}">
                    <img src="${group.avatar_url}" class="story-avatar" alt="${displayName}">
                </div>
                <span class="story-username">${displayName}</span>
            `;
            
            storyItem.addEventListener('click', () => {
                // Tìm tin chưa đọc đầu tiên để bắt đầu chạy, hoặc chạy từ 0
                let startIdx = 0;
                for (let i = 0; i < group.stories.length; i++) {
                    if (!group.stories[i].viewed) {
                        startIdx = i;
                        break;
                    }
                }
                openStoryViewer(index, startIdx);
            });
            
            storiesScrollWrapper.appendChild(storyItem);
        });

        checkAndToggleContainer();
    }
    
    function openStoryViewer(groupIndex, storyIndex) {
        currentGroupIndex = groupIndex;
        currentStoryIndex = storyIndex;
        
        storyViewerModal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // block scrolling
        
        playCurrentStory();
    }
    
    function playCurrentStory() {
        // Reset trạng thái tạm dừng
        isProgressPaused = false;
        pausedTime = 0;
        
        // Clear old timers
        clearTimers();
        
        const group = storiesData[currentGroupIndex];
        const story = group.stories[currentStoryIndex];
        const isMyStory = parseInt(group.user_id) === currentUserId;
        
        // Show/hide mute button dựa vào media_type
        if (viewerMuteBtn) {
            if (story.media_type === 'video') {
                viewerMuteBtn.style.display = 'inline-block';
                updateMuteButtonIcon();
            } else {
                viewerMuteBtn.style.display = 'none';
            }
        }
        
        // Update user info
        viewerAvatar.src = group.avatar_url;
        viewerUsername.textContent = group.full_name;
        viewerTime.textContent = story.created_at;
        
        // Show/Hide delete button
        if (isMyStory) {
            viewerDeleteBtn.style.display = 'inline-block';
            viewerDeleteBtn.onclick = function() {
                if (confirm("Bạn có chắc chắn muốn xóa story này?")) {
                    deleteStory(story.story_id);
                }
            };
        } else {
            viewerDeleteBtn.style.display = 'none';
        }
        
        // Render progress bars
        progressContainer.innerHTML = '';
        group.stories.forEach((s, idx) => {
            const bar = document.createElement('div');
            bar.className = 'story-progress-bar';
            
            const fill = document.createElement('div');
            fill.className = 'story-progress-fill';
            
            if (idx < currentStoryIndex) {
                fill.style.width = '100%';
            } else if (idx > currentStoryIndex) {
                fill.style.width = '0%';
            } else {
                fill.id = 'active-progress-fill';
                fill.style.width = '0%';
            }
            
            bar.appendChild(fill);
            progressContainer.appendChild(bar);
        });
        
        // Clear media container
        mediaContainer.innerHTML = '';
        
        storyDuration = 5000; // default 5s
        
        if (story.media_type === 'image') {
            const img = document.createElement('img');
            img.src = story.media_url;
            img.className = 'story-media-img';
            mediaContainer.appendChild(img);
            
            startProgress(storyDuration);
            
        } else if (story.media_type === 'video') {
            const video = document.createElement('video');
            video.src = story.media_url;
            video.className = 'story-media-video';
            video.autoplay = true;
            video.playsInline = true;
            video.muted = isStoryMuted; // Thiết lập âm thanh mặc định
            
            mediaContainer.appendChild(video);
            
            // Set progress duration based on video duration when loaded
            video.addEventListener('loadedmetadata', function() {
                storyDuration = video.duration * 1000;
                startProgress(storyDuration);
            });
            
            video.addEventListener('ended', function() {
                nextStory();
            });
            
            // Fallback if video fails to load
            video.addEventListener('error', function() {
                startProgress(5000);
            });
            
        } else if (story.media_type === 'text') {
            const textCard = document.createElement('div');
            textCard.className = 'story-media-text-card';
            textCard.style.background = story.bg_color;
            textCard.innerHTML = `<div class="story-text-inner">${story.text_content}</div>`;
            mediaContainer.appendChild(textCard);
            
            startProgress(storyDuration);
        }
        
        // Configure Footer (Views vs Reactions bar)
        viewerFooter.innerHTML = '';
        if (isMyStory) {
            // Owner view: Show view count and aggregate reactions
            let reactsHtml = '';
            const emojisMap = {
                'like': '👍',
                'love': '❤️',
                'haha': '😂',
                'wow': '😮',
                'sad': '😢',
                'angry': '😡'
            };
            
            for (const [type, count] of Object.entries(story.reactions || {})) {
                if (count > 0) {
                    reactsHtml += `<span class="owner-react-badge">${emojisMap[type]} ${count}</span>`;
                }
            }
            
            viewerFooter.className = 'story-viewer-footer owner-mode';
            viewerFooter.innerHTML = `
                <div class="story-viewer-views" style="cursor: pointer;">
                    <i class="fa-regular fa-eye"></i>
                    <span>${story.view_count} lượt xem</span>
                </div>
                <div class="story-viewer-reactions-summary">
                    ${reactsHtml}
                </div>
            `;
            
            const viewsBtn = viewerFooter.querySelector('.story-viewer-views');
            if (viewsBtn) {
                viewsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openStoryViewsList(story.story_id);
                });
            }
        } else {
            // Viewer view: Show quick reaction bar
            viewerFooter.className = 'story-viewer-footer viewer-mode';
            viewerFooter.innerHTML = `
                <div class="quick-reactions-bar">
                    <button class="quick-react-btn" data-reaction="like">👍</button>
                    <button class="quick-react-btn" data-reaction="love">❤️</button>
                    <button class="quick-react-btn" data-reaction="haha">😂</button>
                    <button class="quick-react-btn" data-reaction="wow">😮</button>
                    <button class="quick-react-btn" data-reaction="sad">😢</button>
                    <button class="quick-react-btn" data-reaction="angry">😡</button>
                </div>
            `;
            
            // Add listener to quick reactions buttons
            const reactBtns = viewerFooter.querySelectorAll('.quick-react-btn');
            reactBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const rect = btn.getBoundingClientRect();
                    const reaction = btn.getAttribute('data-reaction');
                    
                    // Trigger flying emoji
                    createFlyingEmoji(btn.textContent, rect.left + rect.width / 2, rect.top);
                    
                    // Send reaction AJAX
                    sendReaction(story.story_id, reaction);
                });
            });
        }
        
        // Send AJAX request to mark story as viewed
        if (!story.viewed) {
            markStoryAsViewed(story.story_id);
            story.viewed = true;
            
            // Check if all stories in group are now viewed
            const allViewedNow = group.stories.every(s => s.viewed);
            if (allViewedNow) {
                group.all_viewed = true;
            }
        }
    }
    
    function startProgress(duration) {
        const fill = document.getElementById('active-progress-fill');
        if (!fill) return;
        
        startTime = Date.now();
        elapsedTime = 0;
        
        progressInterval = setInterval(() => {
            elapsedTime = Date.now() - startTime;
            let percent = Math.min((elapsedTime / duration) * 100, 100);
            fill.style.width = percent + '%';
            
            if (percent >= 100) {
                clearInterval(progressInterval);
            }
        }, 30);
        
        slideTimer = setTimeout(() => {
            nextStory();
        }, duration);
    }
    
    function markStoryAsViewed(storyId) {
        const formData = new FormData();
        formData.append('story_id', storyId);
        
        fetch(SITE_URL + '/view_story.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .catch(err => console.error("Lỗi AJAX view story:", err));
    }
    
    function deleteStory(storyId) {
        const formData = new FormData();
        formData.append('story_id', storyId);
        
        fetch(SITE_URL + '/delete_story.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Clear timers
                clearTimers();
                
                // Remove story locally from data
                const group = storiesData[currentGroupIndex];
                group.stories.splice(currentStoryIndex, 1);
                
                if (group.stories.length > 0) {
                    // Adjust index if we deleted the last item
                    if (currentStoryIndex >= group.stories.length) {
                        currentStoryIndex = group.stories.length - 1;
                    }
                    playCurrentStory();
                } else {
                    // Group has no stories left, remove the group
                    storiesData.splice(currentGroupIndex, 1);
                    closeStoryViewer();
                }
            } else {
                alert("Lỗi khi xóa: " + data.message);
            }
        })
        .catch(err => console.error("Lỗi AJAX delete story:", err));
    }
    
    function sendReaction(storyId, reaction) {
        const formData = new FormData();
        formData.append('story_id', storyId);
        formData.append('reaction_type', reaction);
        
        fetch(SITE_URL + '/react_story.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.warn("Lỗi phản ứng story:", data.message);
            }
        })
        .catch(err => console.error("Lỗi gửi reaction:", err));
    }
    
    function createFlyingEmoji(emoji, x, y) {
        // Create float emoji element
        const flyEl = document.createElement('div');
        flyEl.className = 'story-flying-emoji';
        flyEl.textContent = emoji;
        
        // Position relative to content window
        const viewerContent = document.querySelector('.story-viewer-content');
        const contentRect = viewerContent.getBoundingClientRect();
        
        const relativeX = x - contentRect.left;
        const relativeY = y - contentRect.top;
        
        flyEl.style.left = relativeX + 'px';
        flyEl.style.top = relativeY + 'px';
        
        // Slight random horizontal deflection
        const randomXShift = (Math.random() - 0.5) * 60;
        flyEl.style.setProperty('--x-shift', randomXShift + 'px');
        
        emojiContainer.appendChild(flyEl);
        
        // Remove after animation finishes (1s)
        setTimeout(() => {
            flyEl.remove();
        }, 1000);
    }
    
    function nextStory() {
        if (storiesData.length === 0) return;
        const group = storiesData[currentGroupIndex];
        
        if (currentStoryIndex < group.stories.length - 1) {
            currentStoryIndex++;
            playCurrentStory();
        } else {
            // Move to next user's stories group
            if (currentGroupIndex < storiesData.length - 1) {
                currentGroupIndex++;
                currentStoryIndex = 0;
                playCurrentStory();
            } else {
                // No more stories, close viewer
                closeStoryViewer();
            }
        }
    }
    
    function prevStory() {
        if (storiesData.length === 0) return;
        if (currentStoryIndex > 0) {
            currentStoryIndex--;
            playCurrentStory();
        } else {
            // Move to previous user's stories group
            if (currentGroupIndex > 0) {
                currentGroupIndex--;
                // Start from the last story of the previous group
                currentStoryIndex = storiesData[currentGroupIndex].stories.length - 1;
                playCurrentStory();
            } else {
                // First story of first group, restart it
                playCurrentStory();
            }
        }
    }
    
    function clearTimers() {
        if (slideTimer) clearTimeout(slideTimer);
        if (progressInterval) clearInterval(progressInterval);
    }
    
    function pauseStoryProgress() {
        if (isProgressPaused) return;
        isProgressPaused = true;
        
        clearTimers();
        pausedTime = Date.now() - startTime;
        
        // Tạm dừng video đang chạy nếu có
        const video = mediaContainer.querySelector('video');
        if (video) {
            video.pause();
        }
    }
    
    function resumeStoryProgress() {
        if (!isProgressPaused) return;
        isProgressPaused = false;
        
        const fill = document.getElementById('active-progress-fill');
        if (!fill) return;
        
        // Phát tiếp video đang chạy nếu có
        const video = mediaContainer.querySelector('video');
        if (video) {
            video.play().catch(() => {});
        }
        
        startTime = Date.now() - pausedTime;
        const remaining = storyDuration - pausedTime;
        
        progressInterval = setInterval(() => {
            let currentElapsed = Date.now() - startTime;
            let percent = Math.min((currentElapsed / storyDuration) * 100, 100);
            fill.style.width = percent + '%';
            
            if (percent >= 100) {
                clearInterval(progressInterval);
            }
        }, 30);
        
        slideTimer = setTimeout(() => {
            nextStory();
        }, remaining);
    }
    
    function updateMuteButtonIcon() {
        if (!viewerMuteBtn) return;
        if (isStoryMuted) {
            viewerMuteBtn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
            viewerMuteBtn.title = 'Bật tiếng';
        } else {
            viewerMuteBtn.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
            viewerMuteBtn.title = 'Tắt tiếng';
        }
    }
    
    function openStoryViewsList(storyId) {
        // Tạm dừng chạy slide
        pauseStoryProgress();
        
        if (storyViewsList) storyViewsList.innerHTML = '<div style="text-align: center; color: var(--text-secondary); padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải...</div>';
        if (storyViewsModal) storyViewsModal.style.display = 'flex';
        
        fetch(SITE_URL + '/get_story_views.php?story_id=' + storyId)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const views = res.data || [];
                    if (views.length === 0) {
                        storyViewsList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 30px 10px; font-size: 13.5px;">Chưa có lượt xem nào cho story này.</div>';
                    } else {
                        let html = '';
                        views.forEach(v => {
                            html += `
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 4px 0;">
                                    <a href="${SITE_URL}/profile.php?id=${v.user_id}" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex: 1; min-width: 0;">
                                        <img src="${v.avatar_url}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${v.full_name}</div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">@${v.username}</div>
                                        </div>
                                    </a>
                                    <span style="font-size: 11px; color: var(--text-muted); flex-shrink: 0;">${v.viewed_at}</span>
                                </div>
                            `;
                        });
                        storyViewsList.innerHTML = html;
                    }
                } else {
                    storyViewsList.innerHTML = `<div style="text-align: center; color: var(--danger); padding: 20px;">${res.message || 'Lỗi tải người xem.'}</div>`;
                }
            })
            .catch(err => {
                console.error("Lỗi lấy danh sách xem:", err);
                storyViewsList.innerHTML = '<div style="text-align: center; color: var(--danger); padding: 20px;">Lỗi kết nối máy chủ.</div>';
            });
    }
    
    function closeStoryViewer() {
        clearTimers();
        
        // Dừng và giải phóng tài nguyên video đang phát để tránh phát tiếng ngầm
        const video = mediaContainer.querySelector('video');
        if (video) {
            try {
                video.pause();
                video.removeAttribute('src'); // Xóa src để giải phóng hoàn toàn bộ nhớ đệm
                video.load();
            } catch (e) {
                console.warn("Lỗi giải phóng video story:", e);
            }
        }
        mediaContainer.innerHTML = ''; // Xóa sạch nội dung media trong container
        
        storyViewerModal.style.display = 'none';
        document.body.style.overflow = ''; // restore scrolling
        
        // Re-render stories bar to apply grey ring status for viewed stories
        renderStoriesBar();
    }
    
    // Click regions navigation listeners
    navPrev.addEventListener('click', prevStory);
    navNext.addEventListener('click', nextStory);
    
    // Close button
    viewerCloseBtn.addEventListener('click', closeStoryViewer);
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (storyViewerModal.style.display === 'flex') {
            if (e.key === 'ArrowRight') {
                nextStory();
            } else if (e.key === 'ArrowLeft') {
                prevStory();
            } else if (e.key === 'Escape') {
                if (storyViewsModal && storyViewsModal.style.display === 'flex') {
                    storyViewsModal.style.display = 'none';
                    resumeStoryProgress();
                } else {
                    closeStoryViewer();
                }
            }
        }
    });
});
