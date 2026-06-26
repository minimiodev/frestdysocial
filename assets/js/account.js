/**
 * Account Settings, Identity Switcher, Avatar Cropper & Policy Modals - Frest App
 */

/**
 * Handle Settings Tab modal tabs, change password, and account deletion
 */
function initAccountSettings() {
    // Password Change handler
    const passwordForm = document.getElementById('settings-change-password-form');
    passwordForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(passwordForm);

        fetch(`${SITE_URL_JS()}/change_password.php`, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Đã đổi mật khẩu!');
                    passwordForm.reset();
                } else {
                    showToast(data.message || 'Không thể đổi mật khẩu.');
                }
            })
            .catch(err => {
                console.error('Password change error:', err);
                showToast('Lỗi mạng. Vui lòng thử lại.');
            });
    });

    // Account Deletion handler
    const deleteForm = document.getElementById('settings-delete-account-form');
    deleteForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa vĩnh viễn tài khoản này không? Tất cả các Tweet, ảnh/video đính kèm và phản hồi của bạn sẽ biến mất.')) {
            if (confirm('XÁC NHẬN LẦN CUỐI: Hành động này là KHÔNG THỂ HOÀN TÁC. Bạn có chắc chắn 100% không?')) {
                const formData = new FormData(deleteForm);

                fetch(`${SITE_URL_JS()}/delete_account.php`, {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            window.location.href = 'register.php';
                        } else {
                            showToast(data.message || 'Không thể xóa tài khoản.');
                        }
                    })
                    .catch(err => {
                        console.error('Account delete error:', err);
                        showToast('Lỗi mạng. Vui lòng thử lại.');
                    });
            }
        }
    });
}

/**
 * Avatar Cropper using Cropper.js
 */
function initAvatarCropper() {
    const avatarInput = document.getElementById('avatar');
    const pageAvatarInput = document.getElementById('page-avatar');
    if (!avatarInput && !pageAvatarInput) return;

    const cropModal = document.getElementById('avatar-crop-modal');
    const cropImagePreview = document.getElementById('crop-image-preview');
    const btnCancelCrop = document.getElementById('btn-cancel-crop');
    const btnApplyCrop = document.getElementById('btn-apply-crop');
    const croppedAvatarInput = document.getElementById('cropped_avatar_input');

    let cropper = null;
    let currentInput = null;

    const handleFileSelect = (inputEl) => {
        const file = inputEl.files[0];
        if (file) {
            currentInput = inputEl;
            const reader = new FileReader();
            reader.onload = (e) => {
                cropImagePreview.src = e.target.result;
                cropModal.style.display = 'flex';

                if (cropper) {
                    cropper.destroy();
                }

                cropper = new Cropper(cropImagePreview, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
            };
            reader.readAsDataURL(file);
        }
    };

    avatarInput?.addEventListener('change', () => handleFileSelect(avatarInput));
    pageAvatarInput?.addEventListener('change', () => handleFileSelect(pageAvatarInput));

    btnCancelCrop?.addEventListener('click', () => {
        cropModal.style.display = 'none';
        if (avatarInput) avatarInput.value = '';
        if (pageAvatarInput) pageAvatarInput.value = '';
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        currentInput = null;
    });

    btnApplyCrop?.addEventListener('click', () => {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({
                width: 256,
                height: 256
            });

            const croppedBase64 = canvas.toDataURL('image/jpeg', 0.9);
            
            btnApplyCrop.disabled = true;
            const originalText = btnApplyCrop.textContent;
            btnApplyCrop.textContent = 'Đang tải...';

            const formData = new FormData();
            formData.append('cropped_avatar', croppedBase64);

            if (currentInput === pageAvatarInput) {
                const pageId = pageAvatarInput.getAttribute('data-page-id');
                if (pageId) {
                    formData.append('page_id', pageId);
                }
            }

            fetch(`${SITE_URL_JS()}/upload_avatar_ajax.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnApplyCrop.disabled = false;
                btnApplyCrop.textContent = originalText;

                if (data.success) {
                    const newAvatarUrl = data.avatar_url;

                    const profileAvatarLarge = document.querySelector('.profile-avatar-large, .profile-avatar-outer img');
                    if (profileAvatarLarge) {
                        profileAvatarLarge.src = newAvatarUrl;
                    }

                    if (currentInput === avatarInput) {
                        const navAvatars = document.querySelectorAll('.nav-user-avatar, .user-avatar');
                        navAvatars.forEach(img => {
                            img.src = newAvatarUrl;
                        });
                    }

                    if (croppedAvatarInput) {
                        croppedAvatarInput.value = '';
                    }

                    cropModal.style.display = 'none';
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }

                    showToast('Đã cập nhật ảnh đại diện thành công! ✨');
                } else {
                    showToast(data.message || 'Lỗi khi cập nhật ảnh đại diện.');
                }
            })
            .catch(err => {
                btnApplyCrop.disabled = false;
                btnApplyCrop.textContent = originalText;
                console.error('Avatar upload error:', err);
                showToast('Lỗi kết nối. Vui lòng thử lại.');
            });
        }
    });
}

/**
 * Handle Identity Switcher Dropdown in header
 */
function initIdentityDropdown() {
    if (window.identityDropdownInitialized) return;
    window.identityDropdownInitialized = true;

    // Top bar dropdown (mobile & legacy)
    var trigger = document.getElementById('identity-trigger-btn');
    var dropdown = document.getElementById('identity-dropdown-menu');
    if (trigger && dropdown) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function (e) {
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    // Sidebar dropdown (desktop)
    var sidebarTrigger = document.getElementById('sidebar-identity-trigger-btn');
    var sidebarDropdown = document.getElementById('sidebar-identity-dropdown-menu');
    if (sidebarTrigger && sidebarDropdown) {
        sidebarTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebarDropdown.style.display = sidebarDropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function (e) {
            if (!sidebarTrigger.contains(e.target) && !sidebarDropdown.contains(e.target)) {
                sidebarDropdown.style.display = 'none';
            }
        });
    }
}

var transparencyOpenedFromVerifiedInfo = false;

function openPageTransparencyModal(pageId, isUserPage, username, openedFromVerifiedInfo) {
    transparencyOpenedFromVerifiedInfo = !!openedFromVerifiedInfo;
    
    var modal2 = document.getElementById('page-transparency-modal');
    if (!modal2) return;

    var backBtn = document.getElementById('transparency-back-btn');
    if (backBtn) {
        backBtn.style.display = transparencyOpenedFromVerifiedInfo ? 'flex' : 'none';
    }

    var titleEl = document.getElementById('transparency-modal-title');
    var historyListEl = document.getElementById('transparency-history-list');
    var managersCountryEl = document.getElementById('transparency-managers-country');
    var verifiedTextEl = document.getElementById('transparency-modal-verified-text');
    var verifiedDescEl = document.getElementById('transparency-modal-verified-desc');
    var verifiedIconEl = document.getElementById('transparency-modal-verified-icon');
    var updateStatusEl = document.getElementById('transparency-modal-update-status');

    if (historyListEl) {
        historyListEl.innerHTML = '<div style="font-size: 13.5px; color: var(--text-secondary);">Đang tải...</div>';
    }
    modal2.style.display = 'flex';

    var url = '';
    if (pageId) {
        url = SITE_URL_JS() + '/get_page_transparency.php?page_id=' + pageId + '&is_user_page=' + (isUserPage ? 1 : 0);
    } else if (username) {
        url = SITE_URL_JS() + '/get_page_transparency.php?username=' + encodeURIComponent(username);
    } else {
        return;
    }

    fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var isPageEntity = !!data.is_page_entity;

                // Update modal title based on account type
                if (titleEl) {
                    titleEl.innerText = isPageEntity ? 'Tính minh bạch của Trang' : 'Thông tin tài khoản';
                }

                // Update intro text
                var introTextEl = document.getElementById('transparency-intro-text');
                if (introTextEl) {
                    introTextEl.innerText = isPageEntity
                        ? 'Để đảm bảo sự an toàn trên Frest, chúng tôi sẽ hiển thị thông tin về Trang này.'
                        : 'Để đảm bảo sự an toàn trên Frest, chúng tôi sẽ hiển thị thông tin về tài khoản này.';
                }

                // Update history section label
                var historyLabelEl = document.getElementById('transparency-history-label');
                if (historyLabelEl) {
                    historyLabelEl.innerText = isPageEntity ? 'Lịch sử Trang' : 'Lịch sử tài khoản';
                }

                // Update managers/location section labels
                var managersLabelEl = document.getElementById('transparency-managers-label');
                if (managersLabelEl) {
                    managersLabelEl.innerText = isPageEntity ? 'Những người quản lý Trang này' : 'Thông tin vị trí';
                }
                var managersDescEl = document.getElementById('transparency-managers-desc');
                if (managersDescEl) {
                    managersDescEl.innerText = isPageEntity
                        ? 'Vị trí quốc gia/khu vực chính của những người quản lý Trang này là:'
                        : 'Vị trí quốc gia/khu vực của tài khoản này là:';
                }

                // Update report button label
                var reportLabelEl = document.getElementById('transparency-report-label');
                if (reportLabelEl) {
                    reportLabelEl.innerText = isPageEntity ? 'Tìm hỗ trợ hoặc báo cáo Trang' : 'Tìm hỗ trợ hoặc báo cáo tài khoản';
                }

                if (historyListEl) {
                    historyListEl.innerHTML = '';
                    if (data.history && data.history.length > 0) {
                        data.history.forEach(function (item) {
                            var escapedText = item.text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                            var escapedDate = item.date.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                            var itemHtml = 
                                '<div style="display: flex; align-items: flex-start; gap: 12px;">' +
                                    '<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); flex-shrink: 0;">' +
                                        '<i class="fa-solid fa-pen-to-square" style="font-size: 13px;"></i>' +
                                    '</div>' +
                                    '<div style="text-align: left;">' +
                                        '<div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); line-height: 1.4;">' + escapedText + '</div>' +
                                        '<div style="font-size: 12px; color: var(--text-muted);">' + escapedDate + '</div>' +
                                    '</div>' +
                                '</div>';
                            historyListEl.insertAdjacentHTML('beforeend', itemHtml);
                        });
                    }
                }
                if (managersCountryEl) {
                    managersCountryEl.innerText = (data.country || 'Việt Nam') + ' (1)';
                }

                if (updateStatusEl) {
                    updateStatusEl.innerText = data.update_status_text;
                }
                var updateItemWrapper = document.getElementById('transparency-modal-update-item');
                if (updateItemWrapper) {
                    if (data.recently_updated) {
                        updateItemWrapper.style.display = 'flex';
                    } else {
                        updateItemWrapper.style.display = 'none';
                    }
                }

                var badgeType = data.verification_type || 'none';
                var badgeColor = badgeType === 'subscribed' ? '#1d4ed8' : '#1877f2';
                var checkColor = '#ffffff';
                var badgeTitle = data.badge_title || 'Huy hiệu đã xác minh';
                var badgeDesc = data.badge_desc || '';

                if (verifiedTextEl) {
                    verifiedTextEl.innerText = badgeTitle;
                }
                if (verifiedDescEl) {
                    verifiedDescEl.innerText = badgeDesc;
                }
                if (verifiedIconEl) {
                    verifiedIconEl.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><g fill-rule="evenodd" transform="translate(-92)"><path fill="' + badgeColor + '" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/><path fill="' + checkColor + '" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/></g></svg>';
                }

                // Add transparency link logic on verified info modal transparency button
                var transparencyLink = document.getElementById('verified-modal-transparency-link');
                if (transparencyLink) {
                    transparencyLink.setAttribute('data-page-id', pageId || '');
                    transparencyLink.setAttribute('data-is-user-page', isUserPage ? '1' : '0');
                    transparencyLink.setAttribute('data-username', username || '');
                }

                // Set attributes on transparency report button
                var reportBtn = document.getElementById('transparency-report-btn');
                if (reportBtn) {
                    reportBtn.setAttribute('data-target-type', data.entity_type || '');
                    reportBtn.setAttribute('data-target-id', data.entity_id || '');
                }
            } else {
                modal2.style.display = 'none';
            }
        })
        .catch(function () { modal2.style.display = 'none'; });
}

function initVerificationBadges() {
    if (window.verificationBadgesInitialized) return;
    window.verificationBadgesInitialized = true;

    document.body.addEventListener('click', function (e) {
        var pageBadge = e.target.closest('.page-verified-badge-svg');
        if (!pageBadge) return;

        e.stopPropagation();

        var pageId = pageBadge.getAttribute('data-page-id');
        var isUserPage = pageBadge.getAttribute('data-is-user-page') === '1';
        
        openPageTransparencyModal(pageId, isUserPage, null, false);
    });
}

/**
 * Verification Badges Dynamic explain popup modals
 */
function initVerificationPopups() {
    if (window.verificationPopupsInitialized) return;
    window.verificationPopupsInitialized = true;

    // Bind back button inside Page Transparency Modal
    var backBtn = document.getElementById('transparency-back-btn');
    if (backBtn) {
        backBtn.onclick = function() {
            var modal2 = document.getElementById('page-transparency-modal');
            if (modal2) modal2.style.display = 'none';
            if (transparencyOpenedFromVerifiedInfo) {
                var infoModal = document.getElementById('verified-info-modal');
                if (infoModal) infoModal.style.display = 'flex';
            }
        };
    }

    // Bind Transparency Link inside Verified Info Modal
    var transparencyLink = document.getElementById('verified-modal-transparency-link');
    if (transparencyLink) {
        transparencyLink.onclick = function() {
            var pId = this.getAttribute('data-page-id');
            var isUp = this.getAttribute('data-is-user-page') === '1';
            var uName = this.getAttribute('data-username');
            
            // Hide verified modal
            document.getElementById('verified-info-modal').style.display = 'none';
            
            // Open Page Transparency modal (setting openedFromVerifiedInfo to true)
            openPageTransparencyModal(pId ? pId : null, isUp, uName ? uName : null, true);
        };
    }

    document.body.addEventListener('click', (e) => {
        const badge = e.target.closest('.verified-badge-svg');
        if (badge) {
            e.stopPropagation();
            const type = badge.getAttribute('data-type');
            const username = badge.getAttribute('data-username');
            const isPro = badge.getAttribute('data-is-pro') === '1';

            // Nếu là tài khoản Pro / trang chuyên nghiệp → mở modal Tính minh bạch (kiểu Facebook Page)
            if (isPro) {
                openPageTransparencyModal(null, true, username, false);
                return;
            }

            // Tài khoản cá nhân thường → mở verified-info-modal như cũ
            const modal = document.getElementById('verified-info-modal');
            const modalIcon = document.getElementById('verified-modal-icon');
            const modalTitle = document.getElementById('verified-modal-title');
            const modalText = document.getElementById('verified-modal-text');
            var lmBtn2 = document.getElementById('verified-info-learn-more-btn');

            if (modalTitle) modalTitle.innerText = 'Đang tải...';
            if (modalText) modalText.innerText = '';
            if (modal) modal.style.display = 'flex';

            fetch(SITE_URL_JS() + '/get_page_transparency.php?username=' + encodeURIComponent(username))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const badgeColor = type === 'subscribed' ? '#1d4ed8' : '#1877f2';
                        const checkColor = '#ffffff';

                        // Header: user's display name
                        const headerName = document.getElementById('verified-modal-header-name');
                        if (headerName) headerName.innerText = data.page_name || '';

                        // Badge card: title + description
                        if (modalTitle) modalTitle.innerText = data.badge_title;
                        if (modalText) modalText.innerText = data.badge_desc;

                        // Badge card color based on type
                        const badgeCard = document.getElementById('verified-modal-badge-card');
                        if (badgeCard) {
                            if (type === 'subscribed') {
                                badgeCard.style.background = 'rgba(109, 40, 217, 0.07)';
                                badgeCard.style.borderColor = 'rgba(109, 40, 217, 0.2)';
                            } else {
                                badgeCard.style.background = 'rgba(24, 119, 242, 0.07)';
                                badgeCard.style.borderColor = 'rgba(24, 119, 242, 0.18)';
                            }
                        }

                        // Badge icon
                        if (modalIcon) {
                            modalIcon.innerHTML = `<svg viewBox="0 0 24 24" width="32" height="32" style="filter:drop-shadow(0 2px 6px ${badgeColor}55);"><g fill-rule="evenodd" transform="translate(-92)"><path fill="${badgeColor}" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324"/><path fill="${checkColor}" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414"/></g></svg>`;
                        }

                        // Join date
                        const joinDateEl = document.getElementById('verified-modal-joindate');
                        if (joinDateEl) joinDateEl.innerText = 'Ngày tham gia: ' + (data.created_at || '');

                        // Recently updated row
                        const updatedRow = document.getElementById('verified-modal-updated-row');
                        const updatedText = document.getElementById('verified-modal-updated-text');
                        if (updatedRow) {
                            if (data.recently_updated) {
                                updatedRow.style.display = 'flex';
                                if (updatedText) updatedText.innerText = data.update_status_text || 'Đã cập nhật thông tin gần đây';
                            } else {
                                updatedRow.style.display = 'none';
                            }
                        }

                        // Account category
                        const categoryEl = document.getElementById('verified-modal-category');
                        if (categoryEl) categoryEl.innerText = data.category || '';

                        // Learn more button
                        if (data.learn_more_url) {
                            if (lmBtn2) {
                                lmBtn2.style.display = 'inline-block';
                                lmBtn2.onclick = function() {
                                    window.open(data.learn_more_url, '_blank');
                                };
                            }
                        } else {
                            if (lmBtn2) lmBtn2.style.display = 'none';
                        }

                        // Set attributes on verified modal transparency link
                        var transparencyLink = document.getElementById('verified-modal-transparency-link');
                        if (transparencyLink) {
                            transparencyLink.setAttribute('data-username', username || '');
                            transparencyLink.removeAttribute('data-page-id');
                            transparencyLink.removeAttribute('data-is-user-page');
                        }
                    } else {
                        if (modal) modal.style.display = 'none';
                    }
                })
                .catch(() => {
                    if (modal) modal.style.display = 'none';
                });
        }
    });
}

/**
 * Policies dynamic popup modals loader
 */
function initPolicyPopups() {
    if (window.policyPopupsInitialized) return;
    window.policyPopupsInitialized = true;

    document.body.addEventListener('click', (e) => {
        const policyLink = e.target.closest('.policy-link');
        if (!policyLink) return;

        e.preventDefault();
        const key = policyLink.getAttribute('data-policy');

        fetch(`${SITE_URL_JS()}/get_setting.php?key=${key}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('policy-modal');
                    const body = document.getElementById('policy-modal-body');
                    if (modal && body) {
                        body.innerHTML = sanitizeHTML(data.value);
                        modal.style.display = 'flex';
                    }
                } else {
                    showToast(data.message || 'Không thể tải nội dung.');
                }
            })
            .catch(err => {
                console.error('Error fetching policy:', err);
                showToast('Lỗi kết nối. Không thể mở chính sách.');
            });
    });
}

/**
 * Handle Professional Profile Tabs & About Sub-tabs switching dynamically
 */
function initProProfileTabs() {
    // Main profile tabs
    const tabButtons = document.querySelectorAll('.fb-tab-btn');
    const sections = document.querySelectorAll('.pro-section-tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');
            if (!targetTab) return;

            // Update tab buttons active state
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Show/hide main sections
            sections.forEach(sec => {
                if (sec.id === `pro-section-${targetTab}`) {
                    sec.style.display = 'block';
                } else {
                    sec.style.display = 'none';
                }
            });
        });
    });

    // About sub-tabs inside Giới thiệu
    const aboutSubButtons = document.querySelectorAll('.pro-about-sidebar-btn');
    const aboutSubContents = document.querySelectorAll('.about-subtab-content');

    aboutSubButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetSubtab = btn.getAttribute('data-subtab');
            if (!targetSubtab) return;

            // Update sub-tab buttons active state
            aboutSubButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Show/hide sub-sections
            aboutSubContents.forEach(sub => {
                if (sub.id === `about-subtab-${targetSubtab}`) {
                    sub.style.display = 'block';
                } else {
                    sub.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Handle Standard Profile Tabs switching dynamically
 */
function initStandardProfileTabs() {
    const tabButtons = document.querySelectorAll('.profile-tabs .profile-tab-item');
    const sections = document.querySelectorAll('.standard-section-tab-content');

    if (tabButtons.length === 0) return;

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');
            if (!targetTab) return;

            // Update tab buttons active state
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Show/hide main sections
            sections.forEach(sec => {
                if (sec.id === `standard-section-${targetTab}`) {
                    sec.style.display = 'block';
                } else {
                    sec.style.display = 'none';
                }
            });
        });
    });
}


