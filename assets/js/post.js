/**
 * Post Interaction, Lightbox, Link Preview & Video Player - Frest App
 */

/**
 * Handle Post Action drop downs, AJAX delete & AJAX editing
 */
function initPostActions() {
    if (window.postActionsInitialized) {
        initReadMore();
        return;
    }
    window.postActionsInitialized = true;

    // Toggle Ellipsis menu dropdown
    document.body.addEventListener('click', (e) => {
        const ellipsisBtn = e.target.closest('.ellipsis-btn');
        if (ellipsisBtn) {
            e.stopPropagation();
            const dropdown = ellipsisBtn.nextElementSibling;
            if (dropdown) {
                // Close other dropdowns first
                document.querySelectorAll('.ellipsis-dropdown').forEach(d => {
                    if (d !== dropdown) d.style.display = 'none';
                });
                const isHidden = (window.getComputedStyle(dropdown).display === 'none');
                dropdown.style.display = isHidden ? 'flex' : 'none';
            }
        } else {
            document.querySelectorAll('.ellipsis-dropdown').forEach(d => {
                d.style.display = 'none';
            });
        }
    });

    // Delete Post handler
    document.body.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.delete-post-trigger');
        if (deleteBtn) {
            e.stopPropagation();
            const postId = deleteBtn.getAttribute('data-post-id');
            if (confirm('Bạn có chắc chắn muốn xóa bài viết này vĩnh viễn không?')) {
                const formData = new FormData();
                formData.append('post_id', postId);
                
                fetch(`${SITE_URL_JS()}/delete_post.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                       showToast(data.message || 'Đã xóa bài đăng.');
                       // Fade out and remove element
                       const card = deleteBtn.closest('.frest-card');
                       if (card) {
                           card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                           card.style.opacity = '0';
                           card.style.transform = 'translateY(20px)';
                           setTimeout(() => card.remove(), 400);
                       }
                    } else {
                       showToast(data.message || 'Không thể xóa bài đăng.');
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    showToast('Lỗi kết nối. Vui lòng thử lại.');
                });
            }
        }
    });

    // Pin/Unpin Post handler
    document.body.addEventListener('click', (e) => {
        const pinBtn = e.target.closest('.pin-post-trigger');
        if (pinBtn) {
            e.stopPropagation();
            const postId = pinBtn.getAttribute('data-post-id');
            const isPinned = pinBtn.getAttribute('data-pinned') === '1';
            const action = isPinned ? 'unpin' : 'pin';
            
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('action', action);
            
            fetch(`${SITE_URL_JS()}/pin_action.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Thành công.');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message || 'Không thể thực hiện yêu cầu.');
                }
            })
            .catch(err => {
                console.error('Pin error:', err);
                showToast('Lỗi kết nối. Vui lòng thử lại.');
            });
        }
    });

    // Edit Post Modal opening
    document.body.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-post-trigger');
        if (editBtn) {
            e.stopPropagation();
            const postId = editBtn.getAttribute('data-post-id');
            const content = editBtn.getAttribute('data-content');
            
            const editModal = document.getElementById('edit-post-modal');
            const editInput = document.getElementById('edit-post-content');
            const editIdInput = document.getElementById('edit-post-id');
            
            if (editModal && editInput && editIdInput) {
                editIdInput.value = postId;
                editInput.value = content;
                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
    });

    // Submit Edit form
    const editForm = document.getElementById('edit-post-form');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            
            fetch(`${SITE_URL_JS()}/edit_post.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    document.getElementById('edit-post-modal').style.display = 'none';
                    document.body.style.overflow = '';
                    
                    const postId = formData.get('post_id');
                    const frestCard = document.querySelector(`.frest-card[data-post-id="${postId}"]`);
                    // Update content dynamically on index/profile feeds
                    if (frestCard) {
                        const contentEl = frestCard.querySelector('.frest-content');
                        if (contentEl) {
                            contentEl.innerHTML = data.content;
                            // Clean up old read-more-btn if any
                            const oldBtn = contentEl.nextElementSibling;
                            if (oldBtn && oldBtn.classList.contains('read-more-btn')) {
                                oldBtn.remove();
                            }
                            contentEl.classList.remove('collapsed');
                            delete contentEl.dataset.readMoreInit;
                            contentEl.removeAttribute('data-read-more-init');
                            // Re-init read more logic for this post
                            setTimeout(initReadMore, 100);
                        }
                        // update cached trigger content
                        const editTrigger = frestCard.querySelector('.edit-post-trigger');
                        if (editTrigger) editTrigger.setAttribute('data-content', data.content);
                    } else {
                        window.location.reload();
                    }
                } else {
                    showToast(data.message || 'Không thể cập nhật bài viết.');
                }
            })
            .catch(err => {
                console.error('Edit error:', err);
                showToast('Lỗi kết nối. Vui lòng thử lại.');
            });
        });
    }

    // Auto collapse long posts
    initReadMore();

    // Global Frest Card click to navigate to detail.php (excluding buttons, links, etc.)
    document.body.addEventListener('click', (e) => {
        const card = e.target.closest('.frest-card');
        if (!card) return;
        
        // Ignore if we clicked on any interactive or media elements
        if (e.target.closest('a') || 
            e.target.closest('button') || 
            e.target.closest('.frest-actions') || 
            e.target.closest('.custom-video-container') || 
            e.target.closest('.post-images-wrapper') || 
            e.target.closest('.audio-player-container') || 
            e.target.closest('.document-container') || 
            e.target.closest('.software-container') || 
            e.target.closest('.repost-card') || 
            e.target.closest('.ellipsis-menu-container') || 
            e.target.closest('.reaction-container') || 
            e.target.closest('.poll-vote-btn') || 
            e.target.closest('.poll-result-item') ||
            e.target.closest('.reply-menu-container') ||
            e.target.closest('.reply-actions-row') ||
            e.target.closest('.reply-edit-area') ||
            e.target.closest('.sub-reply-form-container') ||
            e.target.closest('.compose-modal-content') || 
            e.target.closest('.modal-content') ||
            e.target.closest('.verified-badge-svg') ||
            e.target.closest('.page-verified-badge-svg')) {
            return;
        }
        
        // Don't trigger navigation if we are already on detail.php
        if (window.location.pathname.includes('detail.php')) {
            return;
        }
        
        const postToken = card.getAttribute('data-post-token') || card.getAttribute('data-post-id');
        if (postToken) {
            window.location.href = `detail.php?id=${postToken}`;
        }
    });
}
/**
 * Share / Copy links
 */
function initShareActions() {
    if (window.shareActionsInitialized) return;
    window.shareActionsInitialized = true;

    document.body.addEventListener('click', (e) => {
        const shareBtn = e.target.closest('.copy-share-link');
        if (!shareBtn) return;

        const url = shareBtn.getAttribute('data-url');
        if (!url) return;

        navigator.clipboard.writeText(url).then(() => {
            showToast('Đã sao chép liên kết chia sẻ Frest!');
        }).catch(err => {
            console.error('Failed to copy link:', err);
            showToast('Không thể tự động sao chép liên kết.');
        });
    });
}

/**
 * Repost / Quote logic
 */
function initRepostLogic() {
    if (window.repostLogicInitialized) return;
    window.repostLogicInitialized = true;

    // Create repost context menu if not exists
    var menu = document.getElementById('repost-context-menu');
    if (!menu) {
        menu = document.createElement('div');
        menu.id = 'repost-context-menu';
        menu.style.cssText = 'display:none;position:absolute;z-index:9999;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:6px;min-width:180px;box-shadow:var(--shadow-lg);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);transition:opacity 0.15s ease, transform 0.15s ease;';
        menu.innerHTML = [
            '<div id="repost-simple-btn" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:7px;cursor:pointer;font-size:13.5px;font-weight:700;color:var(--text-primary);transition:background 0.15s;" onmouseover="this.style.background=\'rgba(255,255,255,0.05)\'" onmouseout="this.style.background=\'transparent\'">',
            '<i class="fa-solid fa-retweet" style="font-size:15px;color:var(--success);\"></i> <span id="repost-simple-text">Đăng lại</span>',
            '</div>',
            '<div id="repost-quote-btn" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:7px;cursor:pointer;font-size:13.5px;font-weight:700;color:var(--text-primary);transition:background 0.15s;" onmouseover="this.style.background=\'rgba(255,255,255,0.05)\'" onmouseout="this.style.background=\'transparent\'">',
            '<i class="fa-regular fa-pen-to-square" style="font-size:15px;color:var(--accent-primary);\"></i> Trích dẫn (Quote)',
            '</div>'
        ].join('');
        document.body.appendChild(menu);
    }

    var currentPostId = null;
    var triggerButton = null;

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!menu.contains(e.target) && !e.target.closest('.repost-action-trigger')) {
            menu.style.display = 'none';
        }
    });

    // Handle clicking a repost button
    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest('.repost-action-trigger');
        if (!btn) return;
        e.stopPropagation();

        // Guest check
        var isLoggedIn = document.getElementById('identity-trigger-btn') || document.querySelector('.identity-trigger');
        if (!isLoggedIn) {
            if (confirm("Bạn cần đăng nhập để thực hiện chức năng này. Chuyển hướng đến trang đăng nhập?")) {
                window.location.href = "login.php";
            }
            return;
        }

        currentPostId = btn.getAttribute('data-post-id');
        triggerButton = btn;

        // Dynamic context menu labels & colors based on whether it is already reposted
        var isReposted = btn.classList.contains('reposted');
        var simpleBtn = document.getElementById('repost-simple-btn');
        if (simpleBtn) {
            var simpleText = simpleBtn.querySelector('#repost-simple-text');
            var simpleIcon = simpleBtn.querySelector('i');
            if (isReposted) {
                simpleBtn.style.color = 'var(--danger)';
                if (simpleIcon) simpleIcon.style.color = 'var(--danger)';
                if (simpleText) simpleText.textContent = 'Hủy đăng lại';
            } else {
                simpleBtn.style.color = 'var(--text-primary)';
                if (simpleIcon) simpleIcon.style.color = 'var(--success)';
                if (simpleText) simpleText.textContent = 'Đăng lại';
            }
        }

        // Position the context menu next to the clicked button
        var rect = btn.getBoundingClientRect();
        menu.style.display = 'block';
        var top = rect.bottom + window.scrollY + 6;
        // If it overflows the screen height, display it above the button
        if (rect.bottom + 120 > window.innerHeight) {
            top = rect.top + window.scrollY - 100;
        }
        var left = rect.left + window.scrollX;
        // Make sure it doesn't overflow horizontally
        if (left + 210 > window.innerWidth) {
            left = window.innerWidth - 220;
        }
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
    });

    // Handle Simple Repost click
    document.getElementById('repost-simple-btn').addEventListener('click', function() {
        menu.style.display = 'none';
        if (!currentPostId) return;

        var fd = new FormData();
        fd.append('post_id', currentPostId);
        fd.append('comment', '');

        fetch(`${SITE_URL_JS()}/repost.php`, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var msg = data.action === 'reposted' ? 'Đã đăng lại bài viết! ♻️' : 'Đã gỡ bỏ đăng lại.';
                    if (typeof showToast === 'function') showToast(msg);

                    // Update UI state for ALL matching post buttons on the page
                    var allBtns = document.querySelectorAll('.repost-action-trigger[data-post-id="' + currentPostId + '"]');
                    allBtns.forEach(function(b) {
                        var countSpan = b.querySelector('.action-count');
                        if (data.action === 'reposted') {
                            b.classList.add('reposted');
                            b.style.color = 'var(--success)';
                        } else {
                            b.classList.remove('reposted');
                            b.style.color = '';
                        }

                        if (data.repost_count > 0) {
                            if (countSpan) {
                                countSpan.textContent = data.repost_count;
                            } else {
                                var newSpan = document.createElement('span');
                                newSpan.className = 'action-count';
                                newSpan.style.cssText = 'font-size: 12.5px; margin-left: 6px; font-weight: 500;';
                                newSpan.textContent = data.repost_count;
                                b.appendChild(newSpan);
                            }
                        } else {
                            if (countSpan) countSpan.remove();
                        }
                    });

                    // UI updated dynamically, no reload needed

                    if (data.action === 'unreposted') {
                        var repostCards = document.querySelectorAll('.frest-card.my-repost-card[data-repost-of-id="' + currentPostId + '"]');
                        repostCards.forEach(function(card) {
                            card.style.transition = 'opacity 0.4s ease, transform 0.4s ease, margin 0.4s ease, padding 0.4s ease, height 0.4s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.95)';
                            setTimeout(function() {
                                card.remove();
                            }, 400);
                        });
                    }
                } else {
                    if (typeof showToast === 'function') showToast(data.message || 'Có lỗi xảy ra.');
                }
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast('Lỗi kết nối.');
            });
    });

    // Handle Quote Repost click
    document.getElementById('repost-quote-btn').addEventListener('click', function() {
        menu.style.display = 'none';
        if (!currentPostId) return;

        var repostModal = document.getElementById('repost-modal');
        if (repostModal) {
            if (typeof clearRepostMedia === 'function') clearRepostMedia();
            var postIdInput = document.getElementById('repost-post-id');
            if (postIdInput) postIdInput.value = currentPostId;

            var commentTextarea = document.getElementById('repost-comment');
            if (commentTextarea) commentTextarea.value = '';

            var previewEl = document.getElementById('repost-target-preview');
            if (previewEl) {
                var card = triggerButton ? triggerButton.closest('.frest-card') : null;
                var textPreview = '';
                if (card) {
                    var authorNameEl = card.querySelector('.frest-author');
                    var contentTextEl = card.querySelector('.frest-content');
                    var authorText = authorNameEl ? authorNameEl.textContent.trim() : '';
                    
                    var handleText = '';
                    var spans = card.querySelectorAll('span');
                    for (var i = 0; i < spans.length; i++) {
                        var txt = spans[i].textContent.trim();
                        if (txt.startsWith('@')) {
                            handleText = txt;
                            break;
                        }
                    }
                    if (!handleText && authorNameEl) {
                        var href = authorNameEl.getAttribute('href');
                        if (href && href.includes('username=')) {
                            handleText = '@' + href.split('username=')[1];
                        }
                    }
                    
                    var contentText = contentTextEl ? contentTextEl.textContent.trim() : '';
                    if (contentText.length > 180) {
                        contentText = contentText.substring(0, 180) + '...';
                    }
                    
                    textPreview = '<div style="font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:4px; display:flex; align-items:center; gap:6px;">' + 
                                  authorText + ' <span style="font-weight:500; color:var(--text-muted); font-size:11.5px;">' + handleText + '</span></div>' +
                                  '<div style="font-size:12px; color:var(--text-secondary); line-height:1.45; white-space:pre-wrap; max-height:80px; overflow:hidden; text-overflow:ellipsis;">' + contentText + '</div>';
                } else {
                    textPreview = '<i class="fa-solid fa-link" style="margin-right:6px;opacity:0.5;"></i>Bài viết #' + currentPostId;
                }
                previewEl.innerHTML = textPreview;
            }

            repostModal.style.display = 'flex';
        }
    });

    // Handle the quote modal form submission
    var quoteForm = document.getElementById('repost-modal-form');
    if (quoteForm) {
        quoteForm.onsubmit = function(e) {
            e.preventDefault();
            var commentVal = document.getElementById('repost-comment').value.trim();
            if (!commentVal) {
                if (typeof showToast === 'function') showToast('Vui lòng nhập nội dung trích dẫn.');
                return;
            }
            
            var fd = new FormData(quoteForm);
            fetch(`${SITE_URL_JS()}/repost.php`, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var repostModal = document.getElementById('repost-modal');
                    if (repostModal) repostModal.style.display = 'none';
                    if (data.success) {
                        if (typeof showToast === 'function') showToast('Đã trích dẫn bài viết thành công! ✨');
                        
                        var allBtns = document.querySelectorAll('.repost-action-trigger[data-post-id="' + currentPostId + '"]');
                        allBtns.forEach(function(b) {
                            var countSpan = b.querySelector('.action-count');
                            if (data.repost_count > 0) {
                                if (countSpan) {
                                    countSpan.textContent = data.repost_count;
                                } else {
                                    var newSpan = document.createElement('span');
                                    newSpan.className = 'action-count';
                                    newSpan.style.cssText = 'font-size: 12.5px; margin-left: 6px; font-weight: 500;';
                                    newSpan.textContent = data.repost_count;
                                    b.appendChild(newSpan);
                                }
                            }
                        });

                        // UI updated dynamically, no reload needed
                    } else {
                        if (typeof showToast === 'function') showToast(data.message || 'Có lỗi xảy ra.');
                    }
                })
                .catch(function() {
                    var repostModal = document.getElementById('repost-modal');
                    if (repostModal) repostModal.style.display = 'none';
                    if (typeof showToast === 'function') showToast('Lỗi kết nối.');
                });
        };
    }

    var simpleRepostInModal = document.getElementById('btn-submit-simple-repost');
    if (simpleRepostInModal) {
        simpleRepostInModal.onclick = function() {
            var repostModal = document.getElementById('repost-modal');
            if (repostModal) repostModal.style.display = 'none';
            
            var fd = new FormData();
            fd.append('post_id', currentPostId);
            fd.append('comment', '');

            fetch(`${SITE_URL_JS()}/repost.php`, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var msg = data.action === 'reposted' ? 'Đã đăng lại bài viết! ♻️' : 'Đã gỡ bỏ đăng lại.';
                        if (typeof showToast === 'function') showToast(msg);

                        var allBtns = document.querySelectorAll('.repost-action-trigger[data-post-id="' + currentPostId + '"]');
                        allBtns.forEach(function(b) {
                            var countSpan = b.querySelector('.action-count');
                            if (data.action === 'reposted') {
                                b.classList.add('reposted');
                                b.style.color = 'var(--success)';
                            } else {
                                b.classList.remove('reposted');
                                b.style.color = '';
                            }

                            if (data.repost_count > 0) {
                                if (countSpan) {
                                    countSpan.textContent = data.repost_count;
                                } else {
                                    var newSpan = document.createElement('span');
                                    newSpan.className = 'action-count';
                                    newSpan.style.cssText = 'font-size: 12.5px; margin-left: 6px; font-weight: 500;';
                                    newSpan.textContent = data.repost_count;
                                    b.appendChild(newSpan);
                                }
                            } else {
                                if (countSpan) countSpan.remove();
                            }
                        });

                        // UI updated dynamically, no reload needed
                    } else {
                        if (typeof showToast === 'function') showToast(data.message || 'Có lỗi xảy ra.');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Lỗi kết nối.');
                });
        };
    }

    // Repost attachment logic
    var repostAttachBtn = document.getElementById('repost-attach-btn');
    var repostMediaUpload = document.getElementById('repost_media_upload');
    var repostImgPreview = document.getElementById('repost-image-attachment-preview');
    var repostVidPreview = document.getElementById('repost-video-attachment-preview');

    function clearRepostMedia() {
        if (repostMediaUpload) repostMediaUpload.value = '';
        if (repostImgPreview) {
            repostImgPreview.style.display = 'none';
            var img = repostImgPreview.querySelector('img');
            if (img) img.src = '';
        }
        if (repostVidPreview) {
            repostVidPreview.style.display = 'none';
            var video = repostVidPreview.querySelector('video');
            if (video) video.src = '';
        }
    }

    if (repostAttachBtn && repostMediaUpload) {
        repostAttachBtn.addEventListener('click', function(e) {
            e.preventDefault();
            repostMediaUpload.click();
        });

        repostMediaUpload.addEventListener('change', function() {
            var files = repostMediaUpload.files;
            if (!files || files.length === 0) return;

            // Reset previews
            if (repostImgPreview) repostImgPreview.style.display = 'none';
            if (repostVidPreview) repostVidPreview.style.display = 'none';

            var file = files[0];
            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = repostImgPreview.querySelector('img');
                    if (img) img.src = e.target.result;
                    if (repostImgPreview) repostImgPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var video = repostVidPreview.querySelector('video');
                    if (video) video.src = e.target.result;
                    if (repostVidPreview) repostVidPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        var removeImgBtn = repostImgPreview ? repostImgPreview.querySelector('.remove-repost-attachment-btn') : null;
        if (removeImgBtn) {
            removeImgBtn.addEventListener('click', clearRepostMedia);
        }

        var removeVidBtn = repostVidPreview ? repostVidPreview.querySelector('.remove-repost-video-btn') : null;
        if (removeVidBtn) {
            removeVidBtn.addEventListener('click', clearRepostMedia);
        }
    }
}

/**
 * Lightbox Gallery Modal for multiple post images
 */
let currentLightboxImages = [];
let currentLightboxIndex = 0;
let currentLightboxAllowDownload = true;

window.openLightbox = function(event, index, postId) {
    event.stopPropagation();
    
    // Support both .post-images-wrapper (new grid) parent lookup
    const wrapper = event.currentTarget.closest('.post-images-wrapper');
    if (!wrapper) return;
    
    const imagesStr = wrapper.getAttribute('data-images');
    if (!imagesStr) return;
    
    currentLightboxImages = imagesStr.split(',').filter(Boolean);
    currentLightboxIndex = index;
    currentLightboxAllowDownload = wrapper.getAttribute('data-allow-download') === '1';
    
    updateLightbox(false); // no fade on initial open
    
    const lightbox = document.getElementById('global-lightbox');
    if (lightbox) {
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
};

window.openLightboxDirect = function(event, imgFilename, allowDownload) {
    event.stopPropagation();
    currentLightboxImages = [imgFilename];
    currentLightboxIndex = 0;
    currentLightboxAllowDownload = allowDownload === '1' || allowDownload === 1;
    
    updateLightbox(false);
    
    const lightbox = document.getElementById('global-lightbox');
    if (lightbox) {
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
};

function updateLightbox(withFade = true) {
    const imgEl = document.getElementById('lightbox-img');
    const counterEl = document.getElementById('lightbox-counter');
    const downloadBtn = document.getElementById('lightbox-download-btn');
    const prevBtn = document.querySelector('.lightbox-prev');
    const nextBtn = document.querySelector('.lightbox-next');
    
    if (!imgEl) return;
    
    const currentImg = currentLightboxImages[currentLightboxIndex];
    const imgUrl = `${SITE_URL_JS()}/uploads/posts/${currentImg}`;

    const applyNewImage = () => {
        imgEl.src = imgUrl;
        if (counterEl) {
            counterEl.textContent = `${currentLightboxIndex + 1} / ${currentLightboxImages.length}`;
        }
        if (downloadBtn) {
            if (currentLightboxAllowDownload) {
                downloadBtn.style.display = 'flex';
                downloadBtn.href = imgUrl;
                downloadBtn.setAttribute('download', currentImg);
            } else {
                downloadBtn.style.display = 'none';
            }
        }
    };

    if (withFade) {
        imgEl.classList.add('lb-fade');
        setTimeout(() => {
            applyNewImage();
            imgEl.classList.remove('lb-fade');
        }, 150);
    } else {
        applyNewImage();
    }
    
    if (currentLightboxImages.length <= 1) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
    } else {
        if (prevBtn) prevBtn.style.display = 'flex';
        if (nextBtn) nextBtn.style.display = 'flex';
    }
}

// Bind lightbox event listeners (keyboard, click, touch swipe)
document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('global-lightbox');
    if (!lightbox) return;
    
    const closeBtn = lightbox.querySelector('.lightbox-close');
    const prevBtn = lightbox.querySelector('.lightbox-prev');
    const nextBtn = lightbox.querySelector('.lightbox-next');
    
    const closeLightbox = () => {
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
    };

    const goPrev = (e) => {
        if (e) e.stopPropagation();
        if (currentLightboxImages.length <= 1) return;
        currentLightboxIndex = (currentLightboxIndex - 1 + currentLightboxImages.length) % currentLightboxImages.length;
        updateLightbox();
    };

    const goNext = (e) => {
        if (e) e.stopPropagation();
        if (currentLightboxImages.length <= 1) return;
        currentLightboxIndex = (currentLightboxIndex + 1) % currentLightboxImages.length;
        updateLightbox();
    };
    
    closeBtn?.addEventListener('click', closeLightbox);

    // Click on backdrop to close (but not on controls or image)
    lightbox.addEventListener('click', (e) => {
        const inContent = e.target.closest('.lightbox-content');
        const inNav = e.target.closest('.lightbox-nav-btn') || e.target.closest('.lightbox-prev') || e.target.closest('.lightbox-next');
        if (!inContent && !inNav) closeLightbox();
    });
    
    prevBtn?.addEventListener('click', goPrev);
    nextBtn?.addEventListener('click', goNext);
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (lightbox.style.display !== 'flex') return;
        if (e.key === 'Escape') { closeLightbox(); return; }
        if (e.key === 'ArrowLeft')  { goPrev(); return; }
        if (e.key === 'ArrowRight') { goNext(); return; }
        if (e.key === 'Home') {
            currentLightboxIndex = 0;
            updateLightbox();
        }
        if (e.key === 'End') {
            currentLightboxIndex = currentLightboxImages.length - 1;
            updateLightbox();
        }
    });

    // ── Touch / Swipe support for mobile ────────────────────────────
    let touchStartX = 0;
    let touchStartY = 0;
    let touchDeltaX = 0;
    const SWIPE_THRESHOLD = 50; // px
    const LOCK_AXIS_RATIO = 1.2; // only trigger horizontal swipe if dx > dy * ratio

    lightbox.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].clientX;
        touchStartY = e.changedTouches[0].clientY;
        touchDeltaX = 0;
    }, { passive: true });

    lightbox.addEventListener('touchmove', (e) => {
        touchDeltaX = e.changedTouches[0].clientX - touchStartX;
    }, { passive: true });

    lightbox.addEventListener('touchend', (e) => {
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = e.changedTouches[0].clientY - touchStartY;

        // Only trigger if predominantly horizontal
        if (Math.abs(dx) < SWIPE_THRESHOLD) return;
        if (Math.abs(dx) < Math.abs(dy) * LOCK_AXIS_RATIO) return;

        if (dx < 0) {
            goNext(); // swipe left → next
        } else {
            goPrev(); // swipe right → prev
        }
    }, { passive: true });
});

function initCustomVideos() {
    const wrappers = document.querySelectorAll('.frest-video-player-wrapper');
    wrappers.forEach(wrapper => {
        if (wrapper.classList.contains('frest-player-initialized')) return;
        wrapper.classList.add('frest-player-initialized');

        const video = wrapper.querySelector('.frest-video-element');
        const playOverlay = wrapper.querySelector('.frest-video-play-overlay');
        const controls = wrapper.querySelector('.frest-video-controls-overlay');
        const playPauseBtn = wrapper.querySelector('.frest-play-pause-btn');
        const timelineSlider = wrapper.querySelector('.frest-video-timeline-slider');
        const timelineCurrent = wrapper.querySelector('.frest-video-timeline-current');
        const timelineBuffer = wrapper.querySelector('.frest-video-timeline-buffer');
        const timeDisplay = wrapper.querySelector('.frest-video-time-display');
        const volumeBtn = wrapper.querySelector('.frest-volume-btn');
        const volumeSlider = wrapper.querySelector('.frest-video-volume-slider');
        const fullscreenBtn = wrapper.querySelector('.frest-fullscreen-btn');
        const tooltip = wrapper.querySelector('.frest-video-time-tooltip');

        // New elements for brightness and quality
        const brightnessSlider = wrapper.querySelector('.frest-video-brightness-slider');
        const brightnessBtn = wrapper.querySelector('.frest-brightness-btn');
        const qualityBtn = wrapper.querySelector('.frest-quality-btn');
        const qualityMenu = wrapper.querySelector('.frest-video-quality-menu');
        const qualityOptions = wrapper.querySelectorAll('.frest-video-quality-option');
        const loaderOverlay = wrapper.querySelector('.frest-video-loader-overlay');

        if (!video) return;

        let isSeeking = false;

        // Khởi tạo các giá trị bộ lọc mặc định
        video.dataset.brightness = '1';
        video.dataset.blur = '0px';
        video.dataset.contrast = '1';
        video.dataset.saturate = '1';

        // Bộ lọc hợp nhất các hiệu ứng để giả lập chất lượng
        const applyVideoFilters = () => {
            const brightness = video.dataset.brightness || '1';
            const blur = video.dataset.blur || '0px';
            const contrast = video.dataset.contrast || '1';
            const saturate = video.dataset.saturate || '1';
            video.style.filter = `brightness(${brightness}) blur(${blur}) contrast(${contrast}) saturate(${saturate})`;
        };

        // Toggle play/pause
        const togglePlay = () => {
            if (video.paused) {
                video.play().catch(err => {
                    console.error("FrestVideoPlayer error:", err);
                });
            } else {
                video.pause();
            }
        };

        playOverlay?.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePlay();
        });
        let clickTimeout;
        let lastClickTime = 0;
        video.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePlay();
        });
        playPauseBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePlay();
        });

        // Track play/pause state to update UI
        video.addEventListener('play', () => {
            if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            if (playOverlay) {
                playOverlay.style.opacity = '0';
                playOverlay.style.transform = 'translate(-50%, -50%) scale(1.5)';
            }
        });

        video.addEventListener('pause', () => {
            if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
            if (playOverlay) {
                playOverlay.style.opacity = '1';
                playOverlay.style.transform = 'translate(-50%, -50%) scale(1)';
            }
        });

        // Format time helper (HH:MM:SS or MM:SS)
        const formatTime = (time) => {
            if (isNaN(time)) return '00:00';
            const hours = Math.floor(time / 3600);
            const minutes = Math.floor((time % 3600) / 60);
            const seconds = Math.floor(time % 60);
            
            const pad = (n) => n.toString().padStart(2, '0');
            if (hours > 0) {
                return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
            }
            return `${pad(minutes)}:${pad(seconds)}`;
        };

        // Time updates
        video.addEventListener('timeupdate', () => {
            if (!isSeeking && video.duration) {
                const percent = (video.currentTime / video.duration) * 100;
                if (timelineSlider) timelineSlider.value = percent;
                if (timelineCurrent) timelineCurrent.style.width = `${percent}%`;
                if (timeDisplay) {
                    timeDisplay.textContent = `${formatTime(video.currentTime)} / ${formatTime(video.duration)}`;
                }
            }
        });

        video.addEventListener('progress', () => {
            if (video.buffered.length > 0 && video.duration) {
                const bufferedEnd = video.buffered.end(video.buffered.length - 1);
                const percent = (bufferedEnd / video.duration) * 100;
                if (timelineBuffer) timelineBuffer.style.width = `${percent}%`;
            }
        });

        // Handle load/play error
        video.addEventListener('error', () => {
            const errCode = video.error ? video.error.code : 'unknown';
            const errMsg = video.error ? video.error.message : 'unknown';
            const srcUrl = video.currentSrc || video.src;
            console.error(`FrestVideoPlayer ERROR → Code:${errCode} | Msg:"${errMsg}" | Src:"${srcUrl}"`);
            // MediaError codes: 1=ABORTED, 2=NETWORK, 3=DECODE, 4=SRC_NOT_SUPPORTED
            if (loaderOverlay) loaderOverlay.classList.remove('show');
            
            // Hiển thị màn hình báo lỗi trực tiếp trên trình phát kèm nút tải xuống
            let errorOverlay = wrapper.querySelector('.frest-video-error-overlay');
            if (!errorOverlay) {
                errorOverlay = document.createElement('div');
                errorOverlay.className = 'frest-video-error-overlay';
                errorOverlay.innerHTML = `
                    <div style="text-align: center; color: #fff; padding: 12px; z-index: 16; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10,10,15,0.92); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 22px; color: var(--danger);"></i>
                        <span style="font-size: 11.5px; font-weight: 700; max-width: 90%; line-height: 1.4; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">Định dạng video không hỗ trợ hoặc tệp bị lỗi</span>
                    </div>
                `;
                wrapper.appendChild(errorOverlay);
            }
            if (playOverlay) playOverlay.style.display = 'none';
        });

        // Seek video
        timelineSlider?.addEventListener('input', () => {
            isSeeking = true;
            const percent = timelineSlider.value;
            if (timelineCurrent) timelineCurrent.style.width = `${percent}%`;
            if (video.duration && timeDisplay) {
                const time = (percent / 100) * video.duration;
                timeDisplay.textContent = `${formatTime(time)} / ${formatTime(video.duration)}`;
            }
        });

        timelineSlider?.addEventListener('change', () => {
            if (video.duration) {
                const time = (timelineSlider.value / 100) * video.duration;
                video.currentTime = time;
            }
            isSeeking = false;
        });

        // Tooltip position on timeline hover
        timelineSlider?.addEventListener('mousemove', (e) => {
            const rect = timelineSlider.getBoundingClientRect();
            const pos = (e.clientX - rect.left) / rect.width;
            const time = pos * video.duration;
            if (tooltip) {
                tooltip.textContent = formatTime(time);
                tooltip.style.left = `${pos * 100}%`;
                tooltip.style.opacity = '1';
            }
        });

        timelineSlider?.addEventListener('mouseleave', () => {
            if (tooltip) tooltip.style.opacity = '0';
        });

        // Volume control
        const updateVolumeIcon = (val) => {
            if (val === 0 || video.muted) {
                volumeBtn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
            } else if (val < 0.5) {
                volumeBtn.innerHTML = '<i class="fa-solid fa-volume-low"></i>';
            } else {
                volumeBtn.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
            }
        };

        volumeBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            video.muted = !video.muted;
            updateVolumeIcon(video.volume);
            if (volumeSlider) volumeSlider.value = video.muted ? 0 : video.volume;
        });

        volumeSlider?.addEventListener('input', () => {
            video.volume = volumeSlider.value;
            video.muted = (volumeSlider.value == 0);
            updateVolumeIcon(video.volume);
        });

        // Brightness control
        brightnessSlider?.addEventListener('input', () => {
            const val = brightnessSlider.value;
            video.dataset.brightness = val;
            applyVideoFilters();
        });

        brightnessBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            let currentVal = parseFloat(brightnessSlider ? brightnessSlider.value : 1.0);
            if (isNaN(currentVal)) currentVal = 1.0;
            
            let newVal = 1.0;
            if (currentVal >= 0.95 && currentVal <= 1.05) {
                newVal = 1.3;
            } else if (currentVal > 1.05) {
                newVal = 0.7;
            } else {
                newVal = 1.0;
            }
            
            video.dataset.brightness = newVal;
            applyVideoFilters();
            if (brightnessSlider) brightnessSlider.value = newVal;
            
            if (typeof showToast === 'function') {
                let text = 'Độ sáng: Bình thường (100%)';
                if (newVal === 1.3) text = 'Độ sáng: Tăng cường (130%) ☀️';
                if (newVal === 0.7) text = 'Độ sáng: Rạp phim (70%) 🌙';
                showToast(text);
            }
        });

        // Quality selector popover toggle
        qualityBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // Định tuyến vị trí của qualityMenu trong DOM để hiển thị tốt nhất
            if (document.fullscreenElement) {
                // Trong chế độ toàn màn hình, bắt buộc menu phải nằm trong wrapper để trình duyệt hiển thị
                if (qualityMenu && qualityMenu.parentNode !== wrapper) {
                    wrapper.appendChild(qualityMenu);
                }
            } else if (window.innerWidth <= 576) {
                // Trên điện thoại ở chế độ thường, đưa lên body để không bị cắt bởi card overflow:hidden
                if (qualityMenu && qualityMenu.parentNode !== document.body) {
                    document.body.appendChild(qualityMenu);
                }
            } else {
                // Trên desktop thường, đưa về container ban đầu
                const qualityContainer = wrapper.querySelector('.frest-video-quality-container');
                if (qualityMenu && qualityContainer && qualityMenu.parentNode !== qualityContainer) {
                    qualityContainer.appendChild(qualityMenu);
                }
            }
            
            const isOpen = qualityMenu?.classList.toggle('show');
            if (isOpen) {
                wrapper.classList.add('quality-menu-open');
            } else {
                wrapper.classList.remove('quality-menu-open');
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!qualityBtn?.contains(e.target) && !qualityMenu?.contains(e.target)) {
                qualityMenu?.classList.remove('show');
                wrapper.classList.remove('quality-menu-open');
            }
        });

        // Quality options click handling with buffering simulation and filter simulation
        qualityOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const quality = option.getAttribute('data-quality');
                
                // Highlight option
                qualityOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                qualityMenu?.classList.remove('show');
                wrapper.classList.remove('quality-menu-open');

                // Simulate buffering
                const wasPlaying = !video.paused;
                if (wasPlaying) {
                    video.pause();
                }
                
                if (loaderOverlay) loaderOverlay.classList.add('show');
                if (playOverlay) playOverlay.style.opacity = '0';

                // Buffer delay based on resolution quality selected
                let delay = 600;
                if (quality === '8k') delay = 2200;
                else if (quality === '4k') delay = 1600;
                else if (quality === '2k') delay = 1200;
                else if (quality === '1080p') delay = 800;
                else if (quality === '720p') delay = 600;
                else if (quality === '480p') delay = 400;
                else if (quality === '360p') delay = 300;
                else if (quality === 'auto') delay = 500;

                setTimeout(() => {
                    if (loaderOverlay) loaderOverlay.classList.remove('show');
                    if (wasPlaying) {
                        video.play().catch(() => {});
                    } else {
                        if (playOverlay) {
                            playOverlay.style.opacity = '1';
                        }
                    }
                    
                    // Simulate resolution filter (blur & color grading)
                    let blurVal = '0px';
                    let contrastVal = '1';
                    let saturateVal = '1';
                    let toastMsg = 'Chất lượng: Tự động';
                    
                    if (quality === '8k') {
                        blurVal = '0px';
                        contrastVal = '1.08';
                        saturateVal = '1.08';
                        toastMsg = 'Chất lượng: 8K UHD+ (Siêu sắc nét) 🔥';
                    } else if (quality === '4k') {
                        blurVal = '0px';
                        contrastVal = '1.04';
                        saturateVal = '1.04';
                        toastMsg = 'Chất lượng: 4K UHD (Cực nét) ✨';
                    } else if (quality === '2k') {
                        blurVal = '0px';
                        contrastVal = '1.02';
                        saturateVal = '1.02';
                        toastMsg = 'Chất lượng: 2K QHD';
                    } else if (quality === '1080p') {
                        blurVal = '0px';
                        contrastVal = '1';
                        saturateVal = '1';
                        toastMsg = 'Chất lượng: 1080p Full HD';
                    } else if (quality === '720p') {
                        blurVal = '0.5px';
                        contrastVal = '0.98';
                        saturateVal = '0.98';
                        toastMsg = 'Chất lượng: 720p HD';
                    } else if (quality === '480p') {
                        blurVal = '1.4px';
                        contrastVal = '0.94';
                        saturateVal = '0.94';
                        toastMsg = 'Chất lượng: 480p SD (Tiết kiệm dữ liệu)';
                    } else if (quality === '360p') {
                        blurVal = '2.4px';
                        contrastVal = '0.88';
                        saturateVal = '0.88';
                        toastMsg = 'Chất lượng: 360p SD (Tốc độ tối đa) ⚡';
                    }
                    
                    video.dataset.blur = blurVal;
                    video.dataset.contrast = contrastVal;
                    video.dataset.saturate = saturateVal;
                    applyVideoFilters();
                    
                    if (typeof showToast === 'function') {
                        showToast(toastMsg);
                    }
                    
                    // Toggle HD/Quality Badge on Gear Icon
                    if (quality === '8k') {
                        qualityBtn.innerHTML = '<i class="fa-solid fa-gear"></i><span class="hd-badge" style="background:#ef4444 !important; color:#fff !important; font-weight:800 !important; font-size:6px !important; padding:1px 2px !important; border-radius:3px !important; margin-left:2px !important;">8K</span>';
                    } else if (quality === '4k') {
                        qualityBtn.innerHTML = '<i class="fa-solid fa-gear"></i><span class="hd-badge" style="background:#f97316 !important; color:#fff !important; font-weight:800 !important; font-size:6px !important; padding:1px 2px !important; border-radius:3px !important; margin-left:2px !important;">4K</span>';
                    } else if (quality === '2k') {
                        qualityBtn.innerHTML = '<i class="fa-solid fa-gear"></i><span class="hd-badge" style="background:#3b82f6 !important; color:#fff !important; font-weight:800 !important; font-size:6px !important; padding:1px 2px !important; border-radius:3px !important; margin-left:2px !important;">2K</span>';
                    } else if (quality === '1080p' || quality === '720p') {
                        qualityBtn.innerHTML = '<i class="fa-solid fa-gear"></i><span class="hd-badge" style="background:#10b981 !important; color:#fff !important; font-weight:800 !important; font-size:6.5px !important; padding:1px 2.5px !important; border-radius:3px !important; margin-left:2px !important;">HD</span>';
                    } else {
                        qualityBtn.innerHTML = '<i class="fa-solid fa-gear"></i>';
                    }
                }, delay);
            });
        });

        // Fullscreen
        fullscreenBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!document.fullscreenElement) {
                wrapper.requestFullscreen().catch(err => {
                    console.log('Error attempting to enable full-screen mode:', err);
                });
            } else {
                document.exitFullscreen();
            }
        });

        // Show/hide controls on activity
        let controlsTimeout;
        const showControls = () => {
            wrapper.classList.add('show-controls');
            wrapper.classList.remove('hide-cursor');
            clearTimeout(controlsTimeout);
            if (!video.paused) {
                controlsTimeout = setTimeout(() => {
                    wrapper.classList.remove('show-controls');
                    wrapper.classList.add('hide-cursor');
                }, 2000);
            }
        };

        wrapper.addEventListener('mousemove', showControls);
        video.addEventListener('play', showControls);
        video.addEventListener('pause', showControls);
        
        wrapper.addEventListener('mouseleave', () => {
            if (!video.paused) {
                wrapper.classList.remove('show-controls');
                wrapper.classList.add('hide-cursor');
            }
        });

        // Thiết lập phím tắt điều khiển (nút Space để dừng/phát, phím mũi tên trái/phải để tua, lên/xuống để chỉnh âm lượng)
        wrapper.setAttribute('tabindex', '0');
        wrapper.style.outline = 'none';

        wrapper.addEventListener('click', (e) => {
            e.stopPropagation();
            wrapper.focus();
        });

        wrapper.addEventListener('keydown', (e) => {
            const activeEl = document.activeElement;
            if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable)) {
                return;
            }

            if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                togglePlay();
                showControls();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                video.currentTime = Math.max(0, video.currentTime - 5);
                showControls();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                video.currentTime = Math.min(video.duration, video.currentTime + 5);
                showControls();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.05);
                video.muted = false;
                if (volumeSlider) volumeSlider.value = video.volume;
                updateVolumeIcon(video.volume);
                showControls();
                if (typeof showToast === 'function') showToast(`Âm lượng: ${Math.round(video.volume * 100)}%`);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.05);
                if (volumeSlider) volumeSlider.value = video.volume;
                updateVolumeIcon(video.volume);
                showControls();
                if (typeof showToast === 'function') showToast(`Âm lượng: ${Math.round(video.volume * 100)}%`);
            }
        });
    });
}


/**
 * Detect URL inside text and fetch link preview
 */
function initLinkPreview() {
    const textInput = document.getElementById('frest-content-input');
    const previewCard = document.getElementById('link-preview-attachment');
    if (!textInput || !previewCard) return;

    let lastFetchedUrl = '';
    let fetchedData = null;
    let urlRemoved = false;
    let timeout = null;

    const removeBtn = document.getElementById('remove-link-preview-btn');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => {
            previewCard.style.display = 'none';
            document.getElementById('link-preview-input-url').value = '';
            document.getElementById('link-preview-input-title').value = '';
            document.getElementById('link-preview-input-desc').value = '';
            document.getElementById('link-preview-input-image').value = '';
            urlRemoved = true;
        });
    }

    textInput.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const text = textInput.value;
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const match = text.match(urlRegex);

            if (match && match.length > 0) {
                const url = match[0];
                
                if (url !== lastFetchedUrl) {
                    lastFetchedUrl = url;
                    urlRemoved = false;
                    
                    const formData = new FormData();
                    formData.append('url', url);

                    fetch(`${SITE_URL_JS()}/fetch_link_preview.php`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.preview && !urlRemoved) {
                            fetchedData = data.preview;
                            
                            const imgEl = document.getElementById('link-preview-img');
                            const domEl = document.getElementById('link-preview-dom');
                            const titleEl = document.getElementById('link-preview-title');
                            const descEl = document.getElementById('link-preview-desc');
                            
                            if (fetchedData.image) {
                                imgEl.src = fetchedData.image;
                                imgEl.style.display = 'block';
                            } else {
                                imgEl.style.display = 'none';
                            }
                            
                            domEl.textContent = fetchedData.domain || '';
                            titleEl.textContent = fetchedData.title || '';
                            descEl.textContent = fetchedData.description || '';
                            
                            previewCard.style.display = 'block';
                            
                            document.getElementById('link-preview-input-url').value = fetchedData.url || '';
                            document.getElementById('link-preview-input-title').value = fetchedData.title || '';
                            document.getElementById('link-preview-input-desc').value = fetchedData.description || '';
                            document.getElementById('link-preview-input-image').value = fetchedData.image || '';
                        }
                    })
                    .catch(err => console.error('Link preview error:', err));
                }
            } else {
                previewCard.style.display = 'none';
                document.getElementById('link-preview-input-url').value = '';
                document.getElementById('link-preview-input-title').value = '';
                document.getElementById('link-preview-input-desc').value = '';
                document.getElementById('link-preview-input-image').value = '';
                lastFetchedUrl = '';
                urlRemoved = false;
            }
        }, 800);
    });
}

/**
 * Scan all posts on the page, find any that contain a URL in their text content
 * but don't have a link preview card, and dynamically fetch/render one.
 */
function initDynamicLinkPreviews() {
    const frestRights = document.querySelectorAll('.frest-right');
    if (!frestRights.length) return;

    frestRights.forEach(container => {
        if (container.querySelector('.link-preview-card')) return;
        if (container.querySelector('.repost-card-deleted')) return;

        const contentEl = container.querySelector('.frest-content');
        if (!contentEl) return;

        const linkEl = contentEl.querySelector('a.content-link');
        let url = '';
        if (linkEl) {
            url = linkEl.getAttribute('href');
        } else {
            const text = contentEl.textContent;
            const urlRegex = /(https?:\/\/[^\s<]+)/i;
            const match = text.match(urlRegex);
            if (match && match.length > 0) {
                url = match[0];
            }
        }

        if (!url) return;

        try {
            const parsedUrl = new URL(url);
            const currentHost = window.location.host;
            if (parsedUrl.host === currentHost) {
                const isProfileLink = parsedUrl.pathname.includes('/profile.php');
                const isDetailLink = parsedUrl.pathname.includes('/detail.php');
                if (!isProfileLink && !isDetailLink) {
                    return;
                }
            }
        } catch(e) {
            return;
        }

        const formData = new FormData();
        formData.append('url', url);

        fetch(`${SITE_URL_JS()}/fetch_link_preview.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.preview && data.preview.title) {
                const preview = data.preview;
                
                const safeUrl = escapeHTMLForPreview(preview.url);
                const safeTitle = escapeHTMLForPreview(preview.title || '');
                const safeDesc = escapeHTMLForPreview(preview.description || '');
                const safeImg = escapeHTMLForPreview(preview.image || '');
                const safeDom = escapeHTMLForPreview(preview.domain || '');

                let cardHtml = `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="link-preview-card" ` +
                    `style="display:block; text-decoration:none; margin-top:12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); overflow:hidden; background:var(--bg-tertiary); transition:border-color 0.2s;" ` +
                    `onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='var(--border-color)'">`;

                if (safeImg) {
                    cardHtml += `<div style="width:100%; max-height:220px; overflow:hidden; background:#111;">` +
                        `<img src="${safeImg}" alt="preview" style="width:100%; object-fit:cover; display:block;" loading="lazy" onerror="this.parentNode.style.display='none'">` +
                        `</div>`;
                }

                cardHtml += `<div style="padding:12px 14px;">` +
                    `<div style="font-size:11px; color:var(--text-muted); margin-bottom:4px; display:flex; align-items:center; gap:5px;">` +
                    `<i class="fa-solid fa-link" style="font-size:9px;"></i> ${safeDom}` +
                    `</div>`;

                if (safeTitle) {
                    cardHtml += `<div style="font-size:14px; font-weight:700; color:var(--text-primary); line-height:1.35; margin-bottom:4px;">${safeTitle}</div>`;
                }
                if (safeDesc) {
                    cardHtml += `<div style="font-size:12.5px; color:var(--text-secondary); line-height:1.45; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">${safeDesc}</div>`;
                }

                cardHtml += `</div></a>`;

                const actionsEl = container.querySelector('.frest-actions');
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cardHtml;
                const cardNode = tempDiv.firstChild;

                if (actionsEl) {
                    container.insertBefore(cardNode, actionsEl);
                } else {
                    container.appendChild(cardNode);
                }
            }
        })
        .catch(err => console.error('Dynamic link preview error:', err));
    });
}

function escapeHTMLForPreview(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
}

/**
 * Premium collapsible Read More/Read Less for long text content
 */
function initReadMore() {
    if (window.location.pathname.includes('detail.php')) {
        return; // Don't collapse on detail page
    }

    const contents = document.querySelectorAll('.frest-card .frest-content');
    contents.forEach(contentEl => {
        if (contentEl.dataset.readMoreInit) return;
        contentEl.dataset.readMoreInit = "true";

        const scrollHeight = contentEl.scrollHeight;
        const limitHeight = 130; // 130px limit (around 5 lines)
        
        if (scrollHeight > limitHeight + 20) {
            contentEl.classList.add('collapsed');
            
            const readMoreBtn = document.createElement('button');
            readMoreBtn.className = 'read-more-btn';
            readMoreBtn.type = 'button';
            readMoreBtn.innerHTML = 'Xem thêm <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>';
            
            // Append button right after contentEl
            contentEl.parentNode.insertBefore(readMoreBtn, contentEl.nextSibling);
            
            readMoreBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                
                if (contentEl.classList.contains('collapsed')) {
                    contentEl.classList.remove('collapsed');
                    readMoreBtn.innerHTML = 'Thu gọn <i class="fa-solid fa-chevron-up" style="font-size: 10px;"></i>';
                } else {
                    contentEl.classList.add('collapsed');
                    readMoreBtn.innerHTML = 'Xem thêm <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>';
                    contentEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
    });
}

/**
 * Optimized Autoplay for Videos when visible in viewport
 */
function initVideoAutoplay() {
    const videos = document.querySelectorAll('.frest-video-element');
    if (!videos.length) return;

    // Track if a video was manually paused by the user to avoid autoplaying it again
    videos.forEach(v => {
        v.dataset.manuallyPaused = "false";
        v.addEventListener('pause', (e) => {
            // Only count as manual pause if it wasn't triggered by our autoplay pause
            if (!v.dataset.autoPausing) {
                v.dataset.manuallyPaused = "true";
            }
        });
        v.addEventListener('play', () => {
            v.dataset.manuallyPaused = "false";
            
            // Pause all other videos when this one starts playing
            videos.forEach(otherV => {
                if (otherV !== v && !otherV.paused) {
                    otherV.dataset.autoPausing = "true";
                    otherV.pause();
                    setTimeout(() => {
                        otherV.dataset.autoPausing = "";
                    }, 50);
                }
            });
        });
    });

    const options = {
        root: null, // viewport
        rootMargin: '0px',
        threshold: 0.6 // 60% of the video container is visible
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;
            const container = video.closest('.frest-video-player-wrapper');
            
            if (entry.isIntersecting) {
                // Autoplay only if it wasn't manually paused by the user
                if (video.dataset.manuallyPaused === "false") {
                    // Mute by default for autoplay (browser policy requirement)
                    video.muted = true;
                    
                    // Update custom UI elements to reflect muted state
                    if (container) {
                        const volumeSlider = container.querySelector('.frest-video-volume-slider');
                        const volumeBtn = container.querySelector('.frest-volume-btn');
                        if (volumeSlider) volumeSlider.value = 0;
                        if (volumeBtn) volumeBtn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
                    }
                    
                    // Play the video
                    video.play().catch(err => {
                        console.log('Autoplay blocked by browser policy:', err);
                    });
                }
            } else {
                // Pause video when out of viewport
                if (!video.paused) {
                    video.dataset.autoPausing = "true";
                    video.pause();
                    setTimeout(() => {
                        video.dataset.autoPausing = "";
                    }, 50);
                }
            }
        });
    }, options);

    videos.forEach(video => {
        observer.observe(video);
    });
}
