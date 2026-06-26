/**
 * Social Features JS Module (Hashtags, Carousel, Bookmark, Presence, Polls) - Frest App
 */

/**
 * 1. Multi-Image Carousel Helper functions
 */
function updateCarouselDots(carouselEl) {
    if (!carouselEl) return;
    const scrollLeft = carouselEl.scrollLeft;
    const width = carouselEl.clientWidth;
    if (width <= 0) return;
    
    const index = Math.round(scrollLeft / width);
    const container = carouselEl.closest('.post-carousel-container');
    if (container) {
        const dots = container.querySelectorAll('.post-carousel-dot');
        dots.forEach((dot, idx) => {
            if (idx === index) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }
}

function scrollCarouselTo(dotEl, index) {
    if (!dotEl) return;
    const container = dotEl.closest('.post-carousel-container');
    if (container) {
        const carousel = container.querySelector('.post-carousel');
        if (carousel) {
            const width = carousel.clientWidth;
            carousel.scrollTo({
                left: index * width,
                behavior: 'smooth'
            });
            
            // Cập nhật chấm active ngay lập tức
            const dots = container.querySelectorAll('.post-carousel-dot');
            dots.forEach((d, idx) => {
                if (idx === index) {
                    d.classList.add('active');
                } else {
                    d.classList.remove('active');
                }
            });
        }
    }
}

/**
 * 2. AJAX Bookmark Toggle
 */
function toggleBookmark(btn, postId) {
    if (!btn || !postId) return;
    
    // Ngăn chặn sự kiện click bọt lên post card (vì post card có thể có link đi tới trang chi tiết)
    if (window.event) window.event.stopPropagation();
    
    const formData = new FormData();
    formData.append('post_id', postId);
    
    // Tạm thời vô hiệu hóa nút bấm
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.5';
    
    fetch(SITE_URL + '/bookmark_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        
        if (data.success) {
            showToast(data.message);
            const icon = btn.querySelector('i');
            
            if (data.action === 'bookmarked') {
                btn.classList.add('bookmarked');
                btn.style.color = 'var(--accent-primary)';
                if (icon) {
                    icon.className = 'fa-solid fa-bookmark';
                }
            } else {
                btn.classList.remove('bookmarked');
                btn.style.color = '';
                if (icon) {
                    icon.className = 'fa-regular fa-bookmark';
                }
                
                // Nếu đang ở tab "Đã lưu" hoặc trang bookmarks.php, hãy ẩn/xóa bài viết này khỏi danh sách
                const currentTab = document.querySelector('.profile-tab-item.active');
                const isSavedTab = currentTab && (window.location.search.includes('tab=saved') || currentTab.href.includes('tab=saved'));
                const isBookmarksPage = window.location.pathname.includes('bookmarks.php');
                if (isSavedTab || isBookmarksPage) {
                    const postCard = btn.closest('.frest-card') || btn.closest('.post-card');
                    if (postCard) {
                        postCard.style.opacity = '0';
                        postCard.style.transform = 'scale(0.95)';
                        postCard.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                        setTimeout(() => {
                            postCard.remove();
                            // Kiểm tra xem có còn bài viết nào trong container không
                            const container = document.querySelector('.profile-card-col') || document.querySelector('.main-feed-container') || document.querySelector('.feed-container');
                            if (container && container.querySelectorAll('.frest-card').length === 0) {
                                container.innerHTML = `
                                    <div class="empty-state" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                        <i class="fa-regular fa-bookmark" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                        <p style="font-size: 14px; font-weight: 600; margin: 0;">Chưa có bài viết nào được lưu</p>
                                        <p style="font-size: 12px; margin: 4px 0 0 0; color: var(--text-muted);">Những bài viết bạn lưu sẽ xuất hiện ở đây.</p>
                                    </div>
                                `;
                            }
                        }, 300);
                    }
                }
            }
        } else {
            showToast(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        showToast('Có lỗi xảy ra kết nối với máy chủ.');
    });
}

/**
 * 3. AJAX Poll Vote Action
 */
function votePoll(event, pollId, optionId) {
    if (!pollId || !optionId) return;
    
    event.preventDefault();
    event.stopPropagation();
    
    const pollBox = event.currentTarget.closest('.post-poll-box');
    if (!pollBox) return;
    
    // Vô hiệu hóa tất cả các nút vote để tránh click nhiều lần
    const buttons = pollBox.querySelectorAll('.poll-vote-btn');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'default';
    });
    
    const formData = new FormData();
    formData.append('poll_id', pollId);
    formData.append('option_id', optionId);
    
    fetch(SITE_URL + '/vote_poll_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            
            // Vẽ lại options thành kết quả thanh phần trăm
            const optionsContainer = pollBox.querySelector('.poll-options-container');
            if (optionsContainer) {
                optionsContainer.dataset.showResults = "true";
                optionsContainer.innerHTML = '';
                
                data.options.forEach(opt => {
                    const item = document.createElement('div');
                    item.className = 'poll-result-item' + (opt.is_user_choice ? ' user-voted' : '');
                    item.style.cssText = 'position: relative; height: 40px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); overflow: hidden; display: flex; align-items: center; padding: 0 16px; margin-bottom: 8px; background: rgba(255, 255, 255, 0.01);';
                    if (opt.is_user_choice) {
                        item.style.borderColor = 'rgba(124, 58, 237, 0.3)';
                        item.style.background = 'rgba(124, 58, 237, 0.02)';
                    }
                    
                    // Thanh phần trăm chạy từ 0% lên
                    const bar = document.createElement('div');
                    bar.className = 'poll-result-bar';
                    bar.style.cssText = 'position: absolute; left: 0; top: 0; bottom: 0; width: 0%; background: rgba(124, 58, 237, 0.12); transition: width 0.8s cubic-bezier(0.1, 0.8, 0.2, 1); z-index: 1;';
                    if (opt.is_user_choice) {
                        bar.style.background = 'rgba(124, 58, 237, 0.22)';
                    }
                    
                    // Nhãn văn bản và tỷ lệ
                    const label = document.createElement('div');
                    label.className = 'poll-result-label';
                    label.style.cssText = 'display: flex; justify-content: space-between; width: 100%; z-index: 2; font-size: 13px; font-weight: 600; font-family: var(--font-heading);';
                    
                    const textSpan = document.createElement('span');
                    textSpan.style.cssText = 'color: var(--text-primary); display: flex; align-items: center; gap: 6px;';
                    textSpan.textContent = opt.text;
                    
                    if (opt.is_user_choice) {
                        const checkSpan = document.createElement('span');
                        checkSpan.className = 'poll-voted-badge';
                        checkSpan.style.color = 'var(--accent-primary)';
                        checkSpan.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                        textSpan.appendChild(checkSpan);
                    }
                    
                    const percentSpan = document.createElement('span');
                    percentSpan.style.cssText = 'color: var(--text-secondary);';
                    percentSpan.textContent = opt.percentage + '% (' + opt.votes + ' phiếu)';
                    
                    label.appendChild(textSpan);
                    label.appendChild(percentSpan);
                    
                    item.appendChild(bar);
                    item.appendChild(label);
                    optionsContainer.appendChild(item);
                    
                    // Hiệu ứng transition chạy thanh phần trăm sau khi chèn vào DOM
                    setTimeout(() => {
                        bar.style.width = opt.percentage + '%';
                    }, 50);
                });
            }
            
            // Cập nhật tổng số lượt bình chọn ở footer của poll
            const totalVotesSpan = pollBox.querySelector('.poll-total-votes');
            if (totalVotesSpan) {
                totalVotesSpan.textContent = data.total_votes + ' lượt bình chọn';
            }
        } else {
            showToast(data.message);
            // Kích hoạt lại các nút nếu lỗi xảy ra
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Có lỗi xảy ra kết nối với máy chủ.');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
    });
}
