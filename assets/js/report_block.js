/**
 * Report & Block Actions Controller - Frest App
 */

document.addEventListener('DOMContentLoaded', () => {
    const reportModal = document.getElementById('global-report-modal');
    const reportForm = document.getElementById('global-report-form');
    const reportTargetType = document.getElementById('report-target-type');
    const reportTargetId = document.getElementById('report-target-id');
    const reportReason = document.getElementById('report-reason');
    const reportDetails = document.getElementById('report-details');
    const reportMessage = document.getElementById('report-action-message');
    const quickBlockContainer = document.getElementById('quick-block-container');
    const quickBlockBtn = document.getElementById('quick-block-btn');

    if (!reportModal || !reportForm) return;

    // Helper to show modal
    function openReportModal(targetType, targetId) {
        reportTargetType.value = targetType;
        reportTargetId.value = targetId;
        reportReason.value = '';
        reportDetails.value = '';
        reportMessage.style.display = 'none';
        
        // Hide transparency modal or verified info modal if they are open
        const transparencyModal = document.getElementById('page-transparency-modal');
        if (transparencyModal) transparencyModal.style.display = 'none';
        const verifiedInfoModal = document.getElementById('verified-info-modal');
        if (verifiedInfoModal) verifiedInfoModal.style.display = 'none';

        // Hiển thị phần Chặn nhanh nếu báo cáo Người dùng hoặc Trang
        if (targetType === 'user' || targetType === 'page') {
            quickBlockContainer.style.display = 'block';
            quickBlockBtn.setAttribute('data-target-type', targetType);
            quickBlockBtn.setAttribute('data-target-id', targetId);
        } else {
            quickBlockContainer.style.display = 'none';
        }

        reportModal.style.display = 'flex';
    }

    // Event Delegation for triggers
    document.body.addEventListener('click', (e) => {
        // 1. Trigger Report User/Page (từ profile.php)
        const profileReportBtn = e.target.closest('.report-trigger-btn');
        if (profileReportBtn) {
            e.preventDefault();
            const targetType = profileReportBtn.getAttribute('data-target-type');
            const targetId = profileReportBtn.getAttribute('data-target-id');
            openReportModal(targetType, targetId);
            return;
        }

        // 2. Trigger Report Post
        const postReportBtn = e.target.closest('.report-trigger-post-btn');
        if (postReportBtn) {
            e.preventDefault();
            const postId = postReportBtn.getAttribute('data-post-id');
            openReportModal('post', postId);
            return;
        }

        // 3. Trigger Report Reply
        const replyReportBtn = e.target.closest('.report-trigger-reply-btn');
        if (replyReportBtn) {
            e.preventDefault();
            const replyId = replyReportBtn.getAttribute('data-reply-id');
            openReportModal('reply', replyId);
            return;
        }

        // 4. Handle Unblock Action
        const unblockBtn = e.target.closest('.unblock-action-btn');
        if (unblockBtn) {
            e.preventDefault();
            const targetType = unblockBtn.hasAttribute('data-page-id') ? 'page' : 'user';
            const targetId = unblockBtn.getAttribute(targetType === 'page' ? 'data-page-id' : 'data-user-id');
            
            if (confirm('Bạn có chắc chắn muốn hủy chặn tài khoản này?')) {
                const formData = new FormData();
                formData.append('action', 'unblock');
                formData.append('target_type', targetType);
                formData.append('target_id', targetId);

                fetch(SITE_URL + '/block_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Đã hủy chặn thành công! ✨');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Có lỗi xảy ra.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Lỗi kết nối đến máy chủ.');
                });
            }
            return;
        }
    });

    // Handle Quick Block inside modal
    if (quickBlockBtn) {
        quickBlockBtn.addEventListener('click', () => {
            const targetType = quickBlockBtn.getAttribute('data-target-type');
            const targetId = quickBlockBtn.getAttribute('data-target-id');

            if (confirm(`Bạn có chắc chắn muốn chặn tài khoản này? Bạn sẽ không thể xem nội dung hoặc gửi tin nhắn cho nhau nữa.`)) {
                const formData = new FormData();
                formData.append('action', 'block');
                formData.append('target_type', targetType);
                formData.append('target_id', targetId);

                fetch(SITE_URL + '/block_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Đã chặn thành công! 🔒');
                        reportModal.style.display = 'none';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Không thể chặn tài khoản này.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Lỗi kết nối đến máy chủ.');
                });
            }
        });
    }

    // Handle Report Form Submit
    reportForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const submitBtn = reportForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';
        reportMessage.style.display = 'none';

        const formData = new FormData(reportForm);

        fetch(SITE_URL + '/report_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Gửi báo cáo';
            
            reportMessage.style.display = 'block';
            if (data.success) {
                reportMessage.style.background = 'rgba(16, 185, 129, 0.1)';
                reportMessage.style.color = 'var(--success)';
                reportMessage.style.border = '1px solid rgba(16, 185, 129, 0.2)';
                reportMessage.textContent = data.message;
                
                setTimeout(() => {
                    reportModal.style.display = 'none';
                    reportMessage.style.display = 'none';
                }, 2500);
            } else {
                reportMessage.style.background = 'rgba(239, 68, 68, 0.1)';
                reportMessage.style.color = 'var(--danger)';
                reportMessage.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                reportMessage.textContent = data.message;
            }
        })
        .catch(err => {
            console.error(err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Gửi báo cáo';
            
            reportMessage.style.display = 'block';
            reportMessage.style.background = 'rgba(239, 68, 68, 0.1)';
            reportMessage.style.color = 'var(--danger)';
            reportMessage.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            reportMessage.textContent = 'Lỗi kết nối máy chủ. Vui lòng thử lại sau.';
        });
    });
});
