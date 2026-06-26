/**
 * Frest Wiki - Floating Mood Cards Component
 * Allows users to share quick mood states floating around the screen.
 */
(function() {
    // 1. Cấu hình mặc định
    const presets = {
        emojis: ['😊', '😭', '😴', '😡', '🔥', '🥳', '🤔', '🤢', '🎈'],
        colors: [
            { name: 'Magic Purple', value: 'linear-gradient(135deg, #7F00FF 0%, #E100FF 100%)' },
            { name: 'Sunset Glow', value: 'linear-gradient(135deg, #FF512F 0%, #DD2476 100%)' },
            { name: 'Ocean Breeze', value: 'linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%)' },
            { name: 'Neon Forest', value: 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)' },
            { name: 'Golden Hour', value: 'linear-gradient(135deg, #f12711 0%, #f5af19 100%)' },
            { name: 'Cosmic Dark', value: 'linear-gradient(135deg, #0f2027 0%, #2c5364 100%)' }
        ]
    };

    let activeMoods = [];
    let isWikiHidden = localStorage.getItem('frest_wiki_hidden') !== 'false'; // Mặc định ẩn khi chưa có giá trị hoặc được lưu là 'true'

    // 2. Khởi tạo khi DOM sẵn sàng
    document.addEventListener('DOMContentLoaded', () => {
        initUI();
        if (!isWikiHidden) {
            loadActiveMoods();
        }
    });

    // 3. Xây dựng Giao diện (FAB, Toggle và Modal)
    function initUI() {
        // Tránh khởi tạo trùng lặp
        if (document.getElementById('frest-wiki-fab-container')) return;

        // a. Tạo FAB Container và các nút bấm nổi
        const fabContainer = document.createElement('div');
        fabContainer.id = 'frest-wiki-fab-container';
        fabContainer.className = 'frest-wiki-fab-container';

        // Nút Toggle Bật/Tắt Wiki
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = `frest-wiki-toggle ${isWikiHidden ? 'disabled' : ''}`;
        toggleBtn.title = isWikiHidden ? 'Hiện tâm trạng Frest Wiki' : 'Ẩn tâm trạng Frest Wiki';
        toggleBtn.innerHTML = isWikiHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        toggleBtn.addEventListener('click', toggleWikiVisibility);

        // Nút FAB tạo Mood
        const fabBtn = document.createElement('button');
        fabBtn.type = 'button';
        fabBtn.className = 'frest-wiki-fab';
        fabBtn.title = 'Chia sẻ tâm trạng lên Wiki';
        fabBtn.innerHTML = '🎈';
        fabBtn.addEventListener('click', openWikiModal);

        fabContainer.appendChild(toggleBtn);
        fabContainer.appendChild(fabBtn);
        document.body.appendChild(fabContainer);

        // b. Tạo cấu trúc Modal tạo mood
        const modalOverlay = document.createElement('div');
        modalOverlay.id = 'frest-wiki-modal';
        modalOverlay.className = 'wiki-modal-overlay';
        
        let emojiButtonsHTML = presets.emojis.map((em, idx) => 
            `<button type="button" class="wiki-emoji-btn ${idx === 0 ? 'active' : ''}" data-emoji="${em}">${em}</button>`
        ).join('');

        let colorButtonsHTML = presets.colors.map((col, idx) => 
            `<button type="button" class="wiki-color-btn ${idx === 0 ? 'active' : ''}" style="background: ${col.value}" data-color="${col.value}" title="${col.name}"></button>`
        ).join('');

        modalOverlay.innerHTML = `
            <div class="wiki-modal-content">
                <div class="wiki-modal-header">
                    <h3>Frest Wiki 🎈 Phát tán tâm trạng</h3>
                    <button type="button" class="wiki-modal-close">&times;</button>
                </div>
                <form id="frest-wiki-form">
                    <div class="wiki-preview-wrap">
                        <div class="wiki-preview-label">Xem trước thẻ lơ lửng:</div>
                        <div class="wiki-preview-card" id="wiki-preview-card" style="background: ${presets.colors[0].value}">
                            <span class="wiki-preview-emoji" id="wiki-preview-emoji">${presets.emojis[0]}</span>
                            <span class="wiki-preview-text" id="wiki-preview-text">Hôm nay thế nào?</span>
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <textarea class="wiki-textarea" id="wiki-content-input" placeholder="Chia sẻ nhanh tâm trạng hiện tại của bạn lên toàn hệ thống..." maxlength="150" required></textarea>
                        <div class="wiki-char-count"><span id="wiki-char-used">0</span> / 150</div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <div class="wiki-preview-label">Chọn Emoji biểu thị:</div>
                        <div class="wiki-emojis-container">
                            ${emojiButtonsHTML}
                        </div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div class="wiki-preview-label">Chọn Màu Gradient:</div>
                        <div class="wiki-colors-container">
                            ${colorButtonsHTML}
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; height: 42px; border-radius: 10px; font-weight: 700; font-size: 14px; background: var(--wiki-accent-gradient); box-shadow: var(--wiki-shadow); border: none; color: #fff; cursor: pointer;">
                        🚀 Phát tán tâm trạng
                    </button>
                </form>
            </div>
        `;
        document.body.appendChild(modalOverlay);

        // c. Lắng nghe sự kiện trên Modal
        const closeBtn = modalOverlay.querySelector('.wiki-modal-close');
        closeBtn.addEventListener('click', closeWikiModal);
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) closeWikiModal();
        });

        // TextArea input cập nhật preview
        const textarea = document.getElementById('wiki-content-input');
        const previewText = document.getElementById('wiki-preview-text');
        const charUsed = document.getElementById('wiki-char-used');

        textarea.addEventListener('input', () => {
            const val = textarea.value.trim();
            previewText.textContent = val || 'Hôm nay thế nào?';
            charUsed.textContent = textarea.value.length;
        });

        // Emoji click
        const emojiBtns = modalOverlay.querySelectorAll('.wiki-emoji-btn');
        const previewEmoji = document.getElementById('wiki-preview-emoji');
        let selectedEmoji = presets.emojis[0];

        emojiBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                emojiBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedEmoji = btn.dataset.emoji;
                previewEmoji.textContent = selectedEmoji;
            });
        });

        // Color click
        const colorBtns = modalOverlay.querySelectorAll('.wiki-color-btn');
        const previewCard = document.getElementById('wiki-preview-card');
        let selectedColor = presets.colors[0].value;

        colorBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                colorBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedColor = btn.dataset.color;
                previewCard.style.background = selectedColor;
            });
        });

        // Submit Form
        const form = document.getElementById('frest-wiki-form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Kiểm tra đăng nhập
            if (typeof window.FREST_USER === 'undefined' || !window.FREST_USER) {
                showToastMessage('Bạn cần đăng nhập để chia sẻ tâm trạng lên Wiki.');
                closeWikiModal();
                setTimeout(() => {
                    window.location.href = SITE_URL + '/login.php';
                }, 1200);
                return;
            }

            const content = textarea.value.trim();
            if (!content) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang phát tán...';

            const formData = new FormData();
            formData.append('content', content);
            formData.append('emoji', selectedEmoji);
            formData.append('color', selectedColor);

            fetch(SITE_URL + '/share_wiki_mood.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '🚀 Phát tán tâm trạng';
                
                if (data.success) {
                    showToastMessage(data.message);
                    form.reset();
                    previewText.textContent = 'Hôm nay thế nào?';
                    charUsed.textContent = '0';
                    closeWikiModal();
                    
                    // Nếu Wiki đang ẩn, tự động bật lại để người dùng xem thành quả
                    if (isWikiHidden) {
                        isWikiHidden = false;
                        localStorage.setItem('frest_wiki_hidden', 'false');
                        toggleBtn.classList.remove('disabled');
                        toggleBtn.title = 'Ẩn tâm trạng Frest Wiki';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';
                    }
                    loadActiveMoods();
                } else {
                    showToastMessage(data.message);
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '🚀 Phát tán tâm trạng';
                showToastMessage('Không thể kết nối đến máy chủ. Vui lòng thử lại.');
            });
        });
    }

    function openWikiModal() {
        document.getElementById('frest-wiki-modal').classList.add('show');
    }

    function closeWikiModal() {
        document.getElementById('frest-wiki-modal').classList.remove('show');
    }

    // 4. Bật/Tắt hiển thị Frest Wiki
    function toggleWikiVisibility() {
        const toggleBtn = document.querySelector('.frest-wiki-toggle');
        isWikiHidden = !isWikiHidden;
        localStorage.setItem('frest_wiki_hidden', isWikiHidden ? 'true' : 'false');

        if (isWikiHidden) {
            toggleBtn.classList.add('disabled');
            toggleBtn.title = 'Hiện tâm trạng Frest Wiki';
            toggleBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            clearWikiCards();
        } else {
            toggleBtn.classList.remove('disabled');
            toggleBtn.title = 'Ẩn tâm trạng Frest Wiki';
            toggleBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';
            loadActiveMoods();
        }
    }

    // Xóa sạch các thẻ lơ lửng
    function clearWikiCards() {
        const oldCards = document.querySelectorAll('.frest-wiki-card');
        oldCards.forEach(card => card.remove());
    }

    // 5. Tải dữ liệu các tâm trạng từ API
    function loadActiveMoods() {
        clearWikiCards();

        fetch(SITE_URL + '/get_wiki_moods.php')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.moods) {
                activeMoods = data.moods;
                renderWikiCards();
            }
        })
        .catch(err => console.error('FrestWiki: Error loading moods:', err));
    }

    // 6. Vẽ các thẻ lơ lửng trên màn hình
    function renderWikiCards() {
        const screenW = window.innerWidth;
        
        // Trên màn hình di động/máy tính bảng nhỏ (< 992px), ẩn hoàn toàn các thẻ bay để tối ưu hiệu năng và tránh vướng víu
        if (screenW < 992) {
            return;
        }

        // Chỉ vẽ tối đa 5 thẻ lơ lửng để giữ màn hình thông thoáng, không gây quá tải trình duyệt
        const limitMoods = activeMoods.slice(0, 5);
        
        limitMoods.forEach((m, index) => {
            const card = document.createElement('div');
            card.className = 'frest-wiki-card';
            card.style.background = m.color;
            card.dataset.id = m.id;

            const cardWidth = 220; // Kích thước thẻ nhỏ gọn hơn
            const marginX = 20;
            const marginY = 90;
            
            // Tính toán vị trí X ngẫu nhiên ở 2 bên rìa trái/phải, tránh Newfeed ở giữa màn hình
            let randomX = 0;
            const side = Math.random() < 0.5 ? 'left' : 'right';
            if (side === 'left') {
                // Rìa trái: từ 20px đến 22% chiều rộng màn hình
                const maxLeft = Math.floor(screenW * 0.22) - cardWidth;
                randomX = Math.max(20, Math.floor(Math.random() * (maxLeft > 20 ? maxLeft - 20 : 10) + 20));
            } else {
                // Rìa phải: từ 78% chiều rộng màn hình đến sát mép phải
                const minRight = Math.floor(screenW * 0.78);
                const maxRight = screenW - cardWidth - 20;
                randomX = Math.max(minRight, Math.floor(Math.random() * (maxRight > minRight ? maxRight - minRight : 10) + minRight));
            }

            const maxH = window.innerHeight - 80 - marginY;
            const boundH = Math.max(20, maxH);
            const randomY = Math.max(marginY, Math.floor(Math.random() * boundH));

            card.style.left = `${randomX}px`;
            card.style.top = `${randomY}px`;

            // Gán animation lệch nhịp (offset timing & duration)
            const duration = (Math.random() * 4 + 5).toFixed(2); // 5s - 9s
            const delay = -(Math.random() * 6).toFixed(2); // delay âm để chạy ngay lập tức
            card.style.animationDuration = `${duration}s`;
            card.style.animationDelay = `${delay}s`;

            // Xác định badge tích xác minh nếu có
            let verifyBadgeHTML = '';
            if (m.user.verification_type) {
                let badgeClass = 'fa-circle-check';
                let badgeColor = 'var(--accent-primary)';
                if (m.user.verification_type === 'developer') {
                    badgeClass = 'fa-code';
                    badgeColor = '#d946ef';
                } else if (m.user.verification_type === 'official') {
                    badgeColor = '#3b82f6';
                } else if (m.user.verification_type === 'business') {
                    badgeClass = 'fa-briefcase';
                    badgeColor = '#10b981';
                } else if (m.user.verification_type.includes('gov')) {
                    badgeClass = 'fa-building-shield';
                    badgeColor = '#f59e0b';
                }
                verifyBadgeHTML = `<i class="fa-solid ${badgeClass}" style="color: ${badgeColor}; font-size: 10.5px; margin-left: 2px;"></i>`;
            }

            card.innerHTML = `
                <span class="frest-wiki-card-emoji">${m.emoji}</span>
                <span class="frest-wiki-card-content">${m.content}</span>
                <button type="button" class="frest-wiki-card-close" title="Ẩn thẻ này">&times;</button>
                
                <div class="frest-wiki-card-tooltip">
                    <img src="${m.user.avatar_url}" class="frest-wiki-tooltip-avatar" alt="avatar">
                    <div class="frest-wiki-tooltip-info">
                        <span class="frest-wiki-tooltip-name">
                            ${m.user.full_name}
                            ${verifyBadgeHTML}
                        </span>
                        <span class="frest-wiki-tooltip-username">@${m.user.username}</span>
                    </div>
                    <button type="button" class="frest-wiki-tooltip-action" title="Thả tim tâm trạng">
                        <i class="fa-solid fa-heart"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(card);

            // Gán sự kiện Kéo thả
            makeDraggable(card);

            // Nút Close thẻ
            const closeBtn = card.querySelector('.frest-wiki-card-close');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                card.style.transform = 'scale(0)';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            });

            // Nút Thả tim nhanh
            const heartBtn = card.querySelector('.frest-wiki-tooltip-action');
            heartBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                createEmojiBurst(e.clientX, e.clientY, m.emoji);
                heartBtn.style.transform = 'scale(1.3)';
                setTimeout(() => heartBtn.style.transform = '', 200);
            });
        });
    }

    // 7. Hỗ trợ Kéo thả (Drag & Drop) mượt mà
    function makeDraggable(el) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        
        el.onmousedown = dragMouseDown;
        el.ontouchstart = dragMouseDown;

        function dragMouseDown(e) {
            e = e || window.event;
            // Cho phép đóng/thả tim bình thường
            if (e.target.closest('.frest-wiki-card-close') || e.target.closest('.frest-wiki-tooltip-action')) {
                return;
            }

            // Tạm dừng animation đung đưa khi đang kéo
            el.style.animation = 'none';

            let clientX = e.clientX;
            let clientY = e.clientY;
            if (e.touches && e.touches[0]) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            }

            pos3 = clientX;
            pos4 = clientY;

            document.onmouseup = closeDragElement;
            document.ontouchend = closeDragElement;
            
            document.onmousemove = elementDrag;
            document.ontouchmove = elementDrag;
        }

        function elementDrag(e) {
            e = e || window.event;
            
            let clientX = e.clientX;
            let clientY = e.clientY;
            if (e.touches && e.touches[0]) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            }

            pos1 = pos3 - clientX;
            pos2 = pos4 - clientY;
            pos3 = clientX;
            pos4 = clientY;

            // Tính toán vị trí mới
            let newTop = el.offsetTop - pos2;
            let newLeft = el.offsetLeft - pos1;

            // Giới hạn thẻ không đi ra ngoài mép màn hình
            const isMobile = window.innerWidth < 576;
            const minX = 10;
            const minY = 60;
            const maxX = window.innerWidth - el.offsetWidth - 10;
            const maxY = window.innerHeight - el.offsetHeight - (isMobile ? 120 : 80);

            newLeft = Math.max(minX, Math.min(newLeft, maxX));
            newTop = Math.max(minY, Math.min(newTop, maxY));

            el.style.top = `${newTop}px`;
            el.style.left = `${newLeft}px`;
        }

        function closeDragElement() {
            document.onmouseup = null;
            document.ontouchend = null;
            document.onmousemove = null;
            document.ontouchmove = null;
        }
    }

    // 8. Hiệu ứng bùng nổ Emoji (Burst Effect)
    function createEmojiBurst(x, y, emoji) {
        const particleCount = 12;
        const colors = ['❤️', '💖', '✨', emoji];
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'frest-wiki-emoji-particle';
            particle.textContent = colors[Math.floor(Math.random() * colors.length)];
            
            // Đặt vị trí xuất phát
            particle.style.left = `${x}px`;
            particle.style.top = `${y}px`;
            
            // Hướng bay ngẫu nhiên
            const angle = Math.random() * Math.PI * 2;
            const velocity = Math.random() * 80 + 40;
            const dx = Math.cos(angle) * velocity;
            const dy = Math.sin(angle) * velocity;
            const rot = Math.random() * 360;

            particle.style.setProperty('--dx', `${dx}px`);
            particle.style.setProperty('--dy', `${dy}px`);
            particle.style.setProperty('--rot', `${rot}deg`);

            document.body.appendChild(particle);

            // Tự động xóa particle sau khi hoàn thành animation
            particle.addEventListener('animationend', () => {
                particle.remove();
            });
        }
    }

    // Helper: hiển thị Toast trong dự án Frest
    function showToastMessage(msg) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg);
        } else {
            // Fallback nếu không có hàm showToast toàn cục
            const toast = document.createElement('div');
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            toast.style.background = 'rgba(0,0,0,0.85)';
            toast.style.color = '#fff';
            toast.style.padding = '10px 20px';
            toast.style.borderRadius = '30px';
            toast.style.zIndex = '9999';
            toast.style.fontSize = '13px';
            toast.style.fontWeight = '700';
            toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.3)';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }
    }
})();
