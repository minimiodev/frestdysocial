/**
 * NSFW & Age Verification Controller - Frest App
 */

/**
 * NSFW Filter & Age Verification Logic
 */
function initNSFWLogic() {
    // 1. Listen to .nsfw-reveal-btn clicks in the feed
    document.body.addEventListener('click', (e) => {
        const revealBtn = e.target.closest('.nsfw-reveal-btn');
        if (!revealBtn) return;
        
        const container = revealBtn.closest('.nsfw-container');
        if (!container) return;
        
        const isGuest = !document.getElementById('nsfw-toggle');
        
        if (isGuest) {
            if (confirm('Bạn cần đăng nhập và xác minh độ tuổi trên 18 tuổi để xem nội dung nhạy cảm. Bạn có muốn đi tới trang Đăng nhập không?')) {
                window.location.href = `${SITE_URL_JS()}/login.php`;
            }
        } else {
            const verifiedBox = document.getElementById('nsfw-verified-box');
            const pendingBox = document.getElementById('nsfw-pending-box');
            
            const isVerified = verifiedBox && verifiedBox.style.display === 'block';
            const isPending = pendingBox && pendingBox.style.display === 'block';
            
            if (isVerified) {
                container.classList.toggle('unblurred');
            } else if (isPending) {
                showToast('Yêu cầu xác minh độ tuổi của bạn đang chờ phê duyệt từ quản trị viên. Bạn không thể xem nội dung nhạy cảm lúc này.');
            } else {
                if (confirm('Bạn cần gửi yêu cầu xác minh độ tuổi trên 18 trong phần Cài đặt để xem nội dung này. Bạn có muốn mở Cài đặt thiết lập ngay bây giờ không?')) {
                    window.location.href = `${SITE_URL_JS()}/settings.php?select_tab=nsfw`;
                }
            }
        }
    });

    // 2. Listen to #nsfw-toggle change
    const nsfwToggle = document.getElementById('nsfw-toggle');
    if (nsfwToggle) {
        nsfwToggle.addEventListener('change', () => {
            const isChecked = nsfwToggle.checked;
            const verifiedBox = document.getElementById('nsfw-verified-box');
            const isVerified = verifiedBox && verifiedBox.style.display === 'block';
            
            if (isChecked && !isVerified) {
                showToast('Bạn cần gửi yêu cầu xác minh độ tuổi trên 18 và được admin duyệt trước khi bật chế độ này.');
                nsfwToggle.checked = false;
            } else {
                const formData = new FormData();
                formData.append('action', 'toggle_nsfw');
                formData.append('value', isChecked ? '1' : '0');
                
                fetch(`${SITE_URL_JS()}/update_nsfw_settings.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        if (isChecked) {
                            document.querySelectorAll('.nsfw-container').forEach(el => {
                                el.classList.add('unblurred');
                            });
                        } else {
                            document.querySelectorAll('.nsfw-container').forEach(el => {
                                el.classList.remove('unblurred');
                            });
                            if (data.reset_verification) {
                                if (verifiedBox) verifiedBox.style.display = 'none';
                                const formBox = document.getElementById('nsfw-verification-form-box');
                                if (formBox) formBox.style.display = 'block';
                            }
                        }
                    } else {
                        showToast(data.message || 'Không thể cập nhật cấu hình.');
                        nsfwToggle.checked = !isChecked;
                    }
                })
                .catch(err => {
                    console.error('Toggle NSFW error:', err);
                    showToast('Lỗi mạng. Vui lòng thử lại.');
                    nsfwToggle.checked = !isChecked;
                });
            }
        });
    }

    // 3. Listen to #age-verification-form submit
    const ageForm = document.getElementById('age-verification-form');
    if (ageForm) {
        ageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const dobInput = document.getElementById('verification-dob');
            if (!dobInput || !dobInput.value) {
                showToast('Vui lòng nhập ngày sinh.');
                return;
            }
            
            const dob = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < 18) {
                showToast('Bạn phải trên 18 tuổi để gửi yêu cầu xác minh!');
                return;
            }
            
            const fileInput = document.getElementById('verification-id-proof');
            if (!fileInput || fileInput.files.length === 0) {
                showToast('Vui lòng chọn ảnh chụp CMND/CCCD hoặc Hộ chiếu để đối chiếu.');
                return;
            }
            
            const formData = new FormData(ageForm);
            formData.append('action', 'submit_verification');
            
            fetch(`${SITE_URL_JS()}/update_nsfw_settings.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    const formBox = document.getElementById('nsfw-verification-form-box');
                    if (formBox) formBox.style.display = 'none';
                    const pendingBox = document.getElementById('nsfw-pending-box');
                    if (pendingBox) pendingBox.style.display = 'block';
                } else {
                    showToast(data.message || 'Lỗi gửi yêu cầu.');
                }
            })
            .catch(err => {
                console.error('Submit age verify error:', err);
                showToast('Lỗi mạng. Vui lòng thử lại.');
            });
        });
    }
}
