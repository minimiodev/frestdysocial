/**
 * Compose Post Controller - Frest App
 */

/**
 * Handle Compose Post modal overlay triggers and image/video attachments preview
 */
function initComposeModal() {
    const modal = document.getElementById('compose-modal');
    if (!modal || modal.dataset.composeInitialized === 'true') return;
    modal.dataset.composeInitialized = 'true';

    // Open triggers
    document.querySelectorAll('.open-write-modal').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                document.getElementById('frest-content-input')?.focus();
            }, 100);
        });
    });

    // Close triggers
    const closeModal = () => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    modal.querySelector('.close-compose-modal')?.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Upload Preview logic for images, videos, audio, documents, and software
    const mediaUpload = document.getElementById('post_media_upload');
    const imgContainer = document.getElementById('image-attachment-preview');
    const videoContainer = document.getElementById('video-attachment-preview');
    const audioContainer = document.getElementById('audio-attachment-preview');
    const docContainer = document.getElementById('document-attachment-preview');
    const softContainer = document.getElementById('software-attachment-preview');

    const previewImg = imgContainer?.querySelector('img');
    const previewVideo = videoContainer?.querySelector('video');
    const previewAudio = audioContainer?.querySelector('audio');
    const previewDocName = docContainer?.querySelector('#document-preview-name');
    const previewDocSize = docContainer?.querySelector('#document-preview-size');
    const previewDocIcon = docContainer?.querySelector('#document-preview-icon');
    const previewSoftName = softContainer?.querySelector('#software-preview-name');
    const previewSoftSize = softContainer?.querySelector('#software-preview-size');

    const MAX_IMAGES = 9;

    if (mediaUpload) {
        mediaUpload.addEventListener('change', () => {
            const files = mediaUpload.files;
            if (!files || files.length === 0) return;

            // Hide all preview containers first
            if (imgContainer) imgContainer.style.display = 'none';
            if (videoContainer) videoContainer.style.display = 'none';
            if (audioContainer) audioContainer.style.display = 'none';
            if (docContainer) docContainer.style.display = 'none';
            if (softContainer) softContainer.style.display = 'none';

            // Remove old preview grid if any
            const oldGrid = document.getElementById('compose-img-preview-wrap');
            if (oldGrid) oldGrid.remove();

            const imageFiles = [];
            const videoFiles = [];
            const audioFiles = [];
            const docFiles = [];
            const softFiles = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const name = file.name.toLowerCase();
                if (file.type.startsWith('image/')) {
                    imageFiles.push(file);
                } else if (file.type.startsWith('video/')) {
                    videoFiles.push(file);
                } else if (file.type.startsWith('audio/') || name.endsWith('.mp3') || name.endsWith('.wav')) {
                    audioFiles.push(file);
                } else if (name.endsWith('.pdf') || name.endsWith('.docx') || name.endsWith('.txt')) {
                    docFiles.push(file);
                } else if (name.endsWith('.zip') || name.endsWith('.apk') || name.endsWith('.exe')) {
                    softFiles.push(file);
                }
            }

            // 1. Validation for other types (audio, doc, soft)
            if (audioFiles.length > 0 || docFiles.length > 0 || softFiles.length > 0) {
                if (files.length > 1) {
                    showToast('Tài liệu, âm thanh hoặc phần mềm chỉ có thể được tải lên độc lập.');
                    mediaUpload.value = '';
                    return;
                }
            }

            // 2. Validation for video limit
            if (videoFiles.length > 1) {
                showToast('Chỉ được phép chọn tối đa 1 video mỗi bài đăng.');
                mediaUpload.value = '';
                return;
            }

            // 3. Validation for images limit
            if (imageFiles.length > MAX_IMAGES) {
                showToast(`Tối đa ${MAX_IMAGES} hình ảnh mỗi bài đăng.`);
                mediaUpload.value = '';
                return;
            }

            // If we have images or a video, display unified grid preview
            if (imageFiles.length > 0 || videoFiles.length > 0) {
                const wrap = document.createElement('div');
                wrap.id = 'compose-img-preview-wrap';
                wrap.className = 'compose-img-preview-wrap';

                // Counter badge
                const badge = document.createElement('div');
                badge.className = 'compose-img-count-badge';
                
                let badgeText = '';
                if (imageFiles.length > 0 && videoFiles.length > 0) {
                    badgeText = `<i class="fa-regular fa-images"></i> <span>${imageFiles.length} ảnh & 1 video</span>`;
                } else if (videoFiles.length > 0) {
                    badgeText = `<i class="fa-solid fa-circle-play"></i> <span>1 video</span>`;
                } else {
                    badgeText = `<i class="fa-regular fa-images"></i> <span>${imageFiles.length}/${MAX_IMAGES} ảnh</span>`;
                }
                badge.innerHTML = badgeText;
                wrap.appendChild(badge);

                // Grid container
                const grid = document.createElement('div');
                grid.id = 'compose-preview-grid';
                grid.className = 'compose-preview-grid';
                
                const allMedia = [...videoFiles, ...imageFiles];
                const totalCount = allMedia.length;
                
                if (totalCount === 1) grid.classList.add('cols-1');
                else if (totalCount === 2) grid.classList.add('cols-2');
                wrap.appendChild(grid);

                const MAX_SHOW = 6;
                const overflow = totalCount - MAX_SHOW;
                let loaded = 0;

                allMedia.slice(0, MAX_SHOW).forEach((file, index) => {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'compose-preview-item';

                    if (file.type.startsWith('video/')) {
                        itemEl.classList.add('video-item');
                        
                        const video = document.createElement('video');
                        video.muted = true;
                        video.playsInline = true;
                        video.style.width = '100%';
                        video.style.height = '100%';
                        video.style.objectFit = 'cover';

                        const reader = new FileReader();
                        reader.onload = (e) => {
                            video.src = e.target.result;
                            loaded++;
                            if (loaded === Math.min(totalCount, MAX_SHOW)) {
                                if (imgContainer) imgContainer.style.display = 'block';
                            }
                        };
                        reader.readAsDataURL(file);
                        itemEl.appendChild(video);

                        // Play overlay
                        const playOverlay = document.createElement('div');
                        playOverlay.className = 'compose-video-play-icon';
                        playOverlay.innerHTML = '<i class="fa-solid fa-play"></i>';
                        itemEl.appendChild(playOverlay);

                    } else {
                        itemEl.classList.add('image-item');
                        const img = document.createElement('img');
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';

                        const reader = new FileReader();
                        reader.onload = (e) => {
                            img.src = e.target.result;
                            loaded++;
                            if (loaded === Math.min(totalCount, MAX_SHOW)) {
                                if (imgContainer) imgContainer.style.display = 'block';
                            }
                        };
                        reader.readAsDataURL(file);
                        itemEl.appendChild(img);
                    }

                    if (index === Math.min(totalCount, MAX_SHOW) - 1 && overflow > 0) {
                        const more = document.createElement('div');
                        more.className = 'compose-preview-more';
                        more.textContent = `+${overflow}`;
                        itemEl.appendChild(more);
                    }

                    grid.appendChild(itemEl);
                });

                if (previewImg) previewImg.style.display = 'none';
                imgContainer.insertBefore(wrap, imgContainer.firstChild);
                imgContainer.style.display = 'block';

            } else if (audioFiles.length > 0) {
                const file = audioFiles[0];
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (previewAudio) previewAudio.src = e.target.result;
                    if (audioContainer) audioContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);

            } else if (docFiles.length > 0) {
                const file = docFiles[0];
                const size = file.size;
                const formattedSize = size > 1024 * 1024
                    ? (size / (1024 * 1024)).toFixed(2) + ' MB'
                    : (size / 1024).toFixed(2) + ' KB';
                const name = file.name.toLowerCase();

                if (previewDocName) previewDocName.textContent = file.name;
                if (previewDocSize) previewDocSize.textContent = formattedSize;
                if (previewDocIcon) {
                    if (name.endsWith('.pdf')) {
                        previewDocIcon.className = 'fa-regular fa-file-pdf';
                        previewDocIcon.style.color = '#ef4444';
                    } else if (name.endsWith('.docx')) {
                        previewDocIcon.className = 'fa-regular fa-file-word';
                        previewDocIcon.style.color = '#3b82f6';
                    } else {
                        previewDocIcon.className = 'fa-regular fa-file-lines';
                        previewDocIcon.style.color = '#10b981';
                    }
                }
                if (docContainer) docContainer.style.display = 'flex';

            } else if (softFiles.length > 0) {
                const file = softFiles[0];
                const size = file.size;
                const formattedSize = size > 1024 * 1024
                    ? (size / (1024 * 1024)).toFixed(2) + ' MB'
                    : (size / 1024).toFixed(2) + ' KB';

                if (previewSoftName) previewSoftName.textContent = file.name;
                if (previewSoftSize) previewSoftSize.textContent = formattedSize;
                if (softContainer) softContainer.style.display = 'flex';
            }
        });

        const clearFileInput = () => {
            mediaUpload.value = '';

            // Remove new grid preview
            const oldGrid = document.getElementById('compose-img-preview-wrap');
            if (oldGrid) oldGrid.remove();

            if (previewImg) { previewImg.src = ''; previewImg.style.display = 'block'; }
            if (previewVideo) { previewVideo.src = ''; previewVideo.load(); }
            if (previewAudio) { previewAudio.src = ''; previewAudio.load(); }
            if (imgContainer) imgContainer.style.display = 'none';
            if (videoContainer) videoContainer.style.display = 'none';
            if (audioContainer) audioContainer.style.display = 'none';
            if (docContainer) docContainer.style.display = 'none';
            if (softContainer) softContainer.style.display = 'none';
        };

        imgContainer?.querySelector('.remove-attachment-btn')?.addEventListener('click', clearFileInput);
        videoContainer?.querySelector('.remove-video-btn')?.addEventListener('click', clearFileInput);
        audioContainer?.querySelector('.remove-audio-btn')?.addEventListener('click', clearFileInput);
        docContainer?.querySelector('.remove-document-btn')?.addEventListener('click', clearFileInput);
        softContainer?.querySelector('.remove-software-btn')?.addEventListener('click', clearFileInput);

        // Toolbar buttons triggers
        document.getElementById('frest-attach-media')?.addEventListener('click', () => {
            mediaUpload.setAttribute('accept', 'image/*,video/*');
            mediaUpload.click();
        });
        document.getElementById('frest-attach-doc')?.addEventListener('click', () => {
            mediaUpload.setAttribute('accept', '.pdf,.docx,.txt,.zip,.apk,.exe');
            mediaUpload.click();
        });
        document.getElementById('frest-attach-audio')?.addEventListener('click', () => {
            mediaUpload.setAttribute('accept', 'audio/*,.mp3,.wav');
            mediaUpload.click();
        });
        document.getElementById('frest-attach-link')?.addEventListener('click', () => {
            const url = prompt("Nhập địa chỉ liên kết (URL):", "https://");
            if (url && url.trim() !== "" && url.trim() !== "https://") {
                const textInput = document.getElementById('frest-content-input');
                if (textInput) {
                    textInput.value += (textInput.value ? ' ' : '') + url;
                    textInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });
    }
}

/**
 * Implement AJAX-based circular progress bar for post media uploading
 */
function initCircularUpload() {
    const form = document.getElementById('compose-frest-form');
    if (!form || form.dataset.uploadInitialized === 'true') return;
    form.dataset.uploadInitialized = 'true';
    
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const progressOverlay = document.getElementById('upload-progress-overlay');
        const progressCircle = document.getElementById('upload-progress-circle');
        const progressText = document.getElementById('upload-progress-text');
        
        if (progressOverlay) progressOverlay.style.display = 'flex';
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action || 'index.php', true);
        
        // Track upload progress
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                if (progressText) progressText.innerText = `${percent}%`;
                
                if (progressCircle) {
                    // Circumference = 2 * PI * r = 251.2
                    const offset = 251.2 - (percent / 100) * 251.2;
                    progressCircle.style.strokeDashoffset = offset;
                }
            }
        };
        
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 400) {
                localStorage.setItem('post_created', '1');
                window.location.href = 'index.php';
            } else {
                showToast('Lỗi tải phương tiện lên máy chủ. Vui lòng thử lại.');
                if (progressOverlay) progressOverlay.style.display = 'none';
            }
        };
        
        xhr.onerror = () => {
            showToast('Lỗi mạng. Vui lòng kiểm tra lại kết nối.');
            if (progressOverlay) progressOverlay.style.display = 'none';
        };
        
        const formData = new FormData(form);
        formData.append('action_create_post', '1');
        xhr.send(formData);
    });
}
