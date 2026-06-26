<?php
/**
 * Global Footer Component - Frest App
 */
require_once __DIR__ . '/../config.php';
?>
    </main>
        </div> <!-- /desktop-main-wrapper -->
    </div> <!-- /app-layout -->

    <!-- Global Edit Post Modal -->
    <div class="compose-modal-overlay" id="edit-post-modal" style="display: none; z-index: 1002;">
        <div class="compose-modal-content glassmorphism-card">
            <div class="compose-modal-header">
                <h3 class="frest-compose-title">Chỉnh sửa Frest</h3>
                <button class="close-edit-modal" onclick="document.getElementById('edit-post-modal').style.display='none';">&times;</button>
            </div>
            <form id="edit-post-form">
                <input type="hidden" name="post_id" id="edit-post-id">
                <div class="frest-compose-body-inner">
                    <textarea name="content" id="edit-post-content" class="frest-compose-textarea" required></textarea>
                </div>
                <div class="compose-modal-footer frest-edit-footer">
                    <button type="submit" class="btn-primary frest-compose-submit-btn">
                        Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Verified Info Modal -->
    <div class="modal-overlay" id="verified-info-modal" style="display:none; z-index:1001; align-items:flex-end;">
        <div class="vim-wrap glassmorphism-card">
            <!-- Header -->
            <div class="vim-header">
                <h3 class="vim-name" id="verified-modal-header-name"></h3>
                <button class="vim-close-btn" onclick="document.getElementById('verified-info-modal').style.display='none';">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <!-- Body (scrollable) -->
            <div class="vim-body">
                <!-- Badge card -->
                <div class="vim-badge-card" id="verified-modal-badge-card">
                    <div class="vim-badge-top">
                        <div id="verified-modal-icon" class="vim-badge-icon"></div>
                        <h4 class="vim-badge-title" id="verified-modal-title"></h4>
                    </div>
                    <p class="vim-badge-desc" id="verified-modal-text"></p>
                    <div id="verified-modal-detail-panel" style="display:none;">
                        <p id="verified-modal-detail-text" class="vim-badge-desc"></p>
                    </div>
                    <button id="verified-info-learn-more-btn" class="vim-learn-btn" style="display:none;">Tìm hiểu thêm</button>
                </div>

                <!-- Info rows -->
                <div class="vim-rows">
                    <div class="vim-row">
                        <span class="vim-row-icon"><i class="fa-regular fa-calendar"></i></span>
                        <span id="verified-modal-joindate" class="vim-row-text"></span>
                    </div>
                    <div class="vim-row" id="verified-modal-updated-row" style="display:none;">
                        <span class="vim-row-icon"><i class="fa-regular fa-circle-user"></i></span>
                        <span id="verified-modal-updated-text" class="vim-row-text"></span>
                    </div>
                    <div class="vim-row">
                        <span class="vim-row-icon"><i class="fa-regular fa-folder-open"></i></span>
                        <span class="vim-row-text">Loại tài khoản: <strong id="verified-modal-category"></strong></span>
                    </div>
                    <div class="vim-row vim-row-link" id="verified-modal-transparency-link">
                        <span class="vim-row-icon vim-row-icon-accent"><i class="fa-solid fa-circle-info"></i></span>
                        <span class="vim-row-text vim-row-text-accent">Chính sách quyền riêng tư và tính minh bạch</span>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <div class="vim-footer">
                <button class="verified-info-close-btn" onclick="document.getElementById('verified-info-modal').style.display='none';">Đóng</button>
            </div>
        </div>
    </div>
    <!-- Page Transparency Modal (Facebook Page style) -->
    <div class="modal-overlay" id="page-transparency-modal" style="display: none; z-index: 1005;">
        <div class="transparency-modal-content glassmorphism-card" style="position: relative; max-width: 540px; width: 100%; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
            <div class="transparency-header" style="display: flex; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; gap: 10px; background: var(--bg-secondary);">
                <button id="transparency-back-btn" style="background: none; border: none; color: var(--text-primary); cursor: pointer; font-size: 15px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; opacity: 0.8; transition: opacity 0.2s; flex-shrink: 0; padding: 0;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <h3 class="transparency-title-text" id="transparency-modal-title" style="margin: 0; flex: 1; min-width: 0; font-size: 16px; font-weight: 800; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Tính minh bạch của Trang</h3>
            </div>
            
            <!-- Modal Body -->
            <div class="transparency-body" style="padding: 14px 20px; text-align: left; flex: 1 1 auto; min-height: 0; overflow-y: auto;">
                <p class="transparency-intro-text" style="font-size: 13px; color: var(--text-secondary); line-height: 1.4; margin-top: 0; margin-bottom: 12px;">
                    <span id="transparency-intro-text">Để đảm bảo sự an toàn trên Frest, chúng tôi sẽ hiển thị thông tin về mọi người và trang cá nhân của họ.</span>
                </p>
                
                <a href="#" class="policy-trigger" data-policy="privacy_policy" style="color: var(--accent-primary); font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 16px; display: inline-block;" onclick="document.getElementById('page-transparency-modal').style.display='none';">Tìm hiểu thêm</a>
                
                <!-- Verified Status Card -->
                <div class="transparency-verified-card" style="display: flex; flex-direction: column; gap: 8px; padding: 12px 14px; background: rgba(24, 119, 242, 0.08); border: 1px solid rgba(24, 119, 242, 0.2); border-radius: 10px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div id="transparency-modal-verified-icon" style="flex-shrink: 0; display: flex; align-items: center;">
                            <!-- SVG -->
                        </div>
                        <h4 id="transparency-modal-verified-text" style="margin: 0; font-size: 14.5px; font-weight: 700; color: var(--text-primary);">Đã xác minh chính chủ</h4>
                    </div>
                    <p id="transparency-modal-verified-desc" style="margin: 0; font-size: 12px; color: var(--text-secondary); line-height: 1.45; text-align: left;">
                        Trang đã xác minh danh tính và hoạt động.
                    </p>
                </div>
                
                <!-- History Section -->
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <span id="transparency-history-label">Lịch sử</span> <i class="fa-regular fa-circle-question" style="font-size: 12px; color: var(--text-muted); cursor: pointer;"></i>
                    </h4>
                    <div id="transparency-history-list" style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Dynamic name history items populated via AJAX -->
                    </div>
                        
                        <!-- Profile/Page update indicator -->
                        <div id="transparency-modal-update-item" style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); flex-shrink: 0;">
                                <i class="fa-regular fa-circle-user" style="font-size: 14px;"></i>
                            </div>
                            <div style="text-align: left;">
                                <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); line-height: 1.4;" id="transparency-modal-update-status">Cập nhật thông tin</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Gần đây (trong vòng 7 ngày qua)</div>
                            </div>
                        </div>
                    </div>


                <!-- Page Managers Section -->
                <div style="margin-bottom: 20px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <h4 style="font-size: 14px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <span id="transparency-managers-label">Thông tin vị trí</span> <i class="fa-regular fa-circle-question" style="font-size: 12px; color: var(--text-muted); cursor: pointer;"></i>
                    </h4>
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); flex-shrink: 0;">
                            <i class="fa-solid fa-location-dot" style="font-size: 14px;"></i>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.45; margin-bottom: 6px;" id="transparency-managers-desc">
                                Vị trí quốc gia/khu vực chính của chủ tài khoản này là:
                            </div>
                            <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);" id="transparency-managers-country">
                                Việt Nam (1)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support/Report Section -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-bottom: 10px;">
                    <div class="report-trigger-btn" style="display: flex; align-items: center; gap: 12px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'" id="transparency-report-btn">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); flex-shrink: 0;">
                            <i class="fa-solid fa-circle-exclamation" style="font-size: 14px;"></i>
                        </div>
                        <div style="text-align: left; flex: 1;">
                            <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);" id="transparency-report-label">Tìm hỗ trợ hoặc báo cáo</div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 12px; color: var(--text-muted);"></i>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: var(--bg-secondary); flex-shrink: 0;">
                <button class="verified-info-close-btn" onclick="document.getElementById('page-transparency-modal').style.display='none';" style="margin: 0; width: auto; padding: 8px 20px; border-radius: 6px; font-weight: 700;">Đóng</button>
            </div>
        </div>
    </div>

    <!-- Policy Modal -->
    <div class="modal-overlay" id="policy-modal" style="display: none; z-index: 1000;">
        <div class="modal-content glassmorphism-card" style="max-width: 650px; position: relative;">
            <button class="modal-close" onclick="document.getElementById('policy-modal').style.display='none';" style="top: 15px; right: 15px;">&times;</button>
            <div style="padding: 30px; max-height: 80vh; overflow-y: auto;" id="policy-modal-body">
                <!-- Content loaded dynamically via AJAX -->
            </div>
        </div>
    </div>

    <!-- Upload Progress Overlay Modal -->
    <div class="modal-overlay" id="upload-progress-overlay" style="display: none; z-index: 2000; background: rgba(0, 0, 0, 0.85); flex-direction: column; align-items: center; justify-content: center; gap: 16px;">
        <div class="progress-circle-container" style="position: relative; width: 100px; height: 100px;">
            <svg width="100" height="100" viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                <circle cx="50" cy="50" r="40" stroke="rgba(255, 255, 255, 0.1)" stroke-width="8" fill="transparent" />
                <circle id="upload-progress-circle" cx="50" cy="50" r="40" stroke="url(#progressGrad)" stroke-width="8" fill="transparent" stroke-dasharray="251.2" stroke-dashoffset="251.2" style="transition: stroke-dashoffset 0.1s ease; stroke-linecap: round;" />
                <defs>
                    <linearGradient id="progressGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#1d4ed8" />
                        <stop offset="100%" stop-color="#ffffff" />
                    </linearGradient>
                </defs>
            </svg>
            <div id="upload-progress-text" style="position: absolute; top: 0; left: 0; width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; color: #fff; font-family: var(--font-heading);">0%</div>
        </div>
        <div style="color: #fff; font-weight: 700; font-size: 15px; font-family: var(--font-heading);">Đang tải phương tiện lên...</div>
        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">Vui lòng giữ tab này hoạt động</div>
    </div>

    <!-- Reactors List Modal -->
    <div class="modal-overlay" id="reactors-modal" style="display: none; z-index: 1002;">
        <div class="modal-content glassmorphism-card" style="max-width: 440px; position: relative; padding: 24px; border-radius: var(--radius-md); width: 100%;">
            <button class="modal-close" onclick="document.getElementById('reactors-modal').style.display='none';" style="top: 15px; right: 15px;">&times;</button>
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 20px; text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
                Người đã tương tác
            </h3>
            <div id="reactors-modal-list" style="max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-right: 4px;">
                <!-- Populate dynamically via JS -->
            </div>
        </div>
    </div>

    <!-- Repost / Share Modal -->
    <div class="modal-overlay" id="repost-modal" style="display: none; z-index: 1002;">
        <div class="modal-content glassmorphism-card" style="max-width: 480px; position: relative; padding: 24px; border-radius: var(--radius-md); width: 100%;">
            <button class="modal-close" onclick="document.getElementById('repost-modal').style.display='none';" style="top: 15px; right: 15px;">&times;</button>
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 20px; text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-primary);">
                Chia sẻ bài đăng
            </h3>
            
            <form id="repost-modal-form" style="display: flex; flex-direction: column; gap: 16px;">
                <input type="hidden" name="post_id" id="repost-post-id" value="">
                
                <!-- Target Post Summary Preview Card -->
                <div id="repost-target-preview" style="background: rgba(255, 255, 255, 0.015); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; font-size: 13px; color: var(--text-secondary); max-height: 140px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; box-sizing: border-box; text-align: left;">
                    <!-- Filled dynamically -->
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="repost-comment" class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase;">Thêm bình luận (Tùy chọn - để Trích dẫn)</label>
                    <textarea name="comment" id="repost-comment" class="form-input" placeholder="Nhập ý kiến của bạn về bài viết này..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); resize: none; height: 80px; padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;"></textarea>
                </div>

                <!-- Attachment previews for repost -->
                <div id="repost-image-attachment-preview" class="frest-compose-preview-box image-preview" style="display: none; margin-top: 10px; position: relative;">
                    <img src="" class="frest-compose-preview-img" style="max-height: 120px;">
                    <button type="button" class="remove-repost-attachment-btn frest-compose-remove-btn">&times;</button>
                </div>

                <div id="repost-video-attachment-preview" class="frest-compose-preview-box video-preview" style="display: none; margin-top: 10px; position: relative;">
                    <video src="" controls class="frest-compose-preview-video" style="max-height: 120px;"></video>
                    <button type="button" class="remove-repost-video-btn frest-compose-remove-btn">&times;</button>
                </div>
                
                <!-- Toolbar with attachment buttons -->
                <div style="display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 12px; background: var(--bg-tertiary);">
                    <span style="font-size: 12.5px; font-weight: 700; color: var(--text-primary);">Đính kèm hình ảnh/video</span>
                    <button type="button" id="repost-attach-btn" style="background:none; border:none; font-size: 18px; cursor:pointer;" title="Chọn hình ảnh / video">
                        <i class="fa-regular fa-image" style="color: #45bd62;"></i>
                    </button>
                    <input type="file" name="repost_media[]" id="repost_media_upload" accept="image/*,video/*" multiple style="display: none;">
                </div>
                
                <div class="share-modal-buttons-row" style="display: flex; gap: 12px; margin-top: 6px;">
                    <button type="button" id="btn-submit-simple-repost" class="btn-primary" style="flex: 1; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 38px;">
                        <i class="fa-solid fa-retweet" style="margin-right: 6px; color: var(--success);"></i> Đăng lại ngay
                    </button>
                    <button type="submit" id="btn-submit-quote-repost" class="btn-primary" style="flex: 1; background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 13.5px; border-radius: var(--radius-full); height: 38px; box-shadow: 0 4px 12px var(--accent-glow);">
                        <i class="fa-solid fa-quote-left" style="margin-right: 6px;"></i> Trích dẫn
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Global Lightbox Modal -->
    <div id="global-lightbox" class="lightbox-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); z-index: 2100; align-items: center; justify-content: center;">
        <button class="lightbox-close" type="button" aria-label="Đóng">&times;</button>

        <button class="lightbox-nav-btn lightbox-prev" type="button" aria-label="Ảnh trước">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button class="lightbox-nav-btn lightbox-next" type="button" aria-label="Ảnh sau">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        
        <div class="lightbox-content">
            <img id="lightbox-img" src="" alt="Ảnh">
            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: center;">
                <span id="lightbox-counter" class="lightbox-counter">1 / 1</span>
                <a id="lightbox-download-btn" href="" download class="lightbox-download-btn">
                    <i class="fa-solid fa-download"></i> Tải về
                </a>
            </div>
        </div>
    </div>

    <!-- Copyright Complaint Modal -->
    <div class="modal-overlay" id="copyright-complaint-modal" style="display: none; z-index: 1004;">
        <div class="modal-content glassmorphism-card" style="max-width: 500px; position: relative; padding: 28px; border-radius: var(--radius-md); width: 100%;">
            <button class="modal-close" onclick="document.getElementById('copyright-complaint-modal').style.display='none';" style="top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">&times;</button>
            <h3 style="font-family: var(--font-heading); font-size: 20px; font-weight: 800; margin-bottom: 8px; text-align: center; color: var(--text-primary);">
                <i class="fa-solid fa-copyright" style="color: var(--accent-primary); margin-right: 6px;"></i> Khiếu nại bản quyền
            </h3>
            <p style="font-size: 12.5px; color: var(--text-secondary); text-align: center; margin-bottom: 20px;">
                Nếu bạn phát hiện nội dung trên hệ thống vi phạm bản quyền sở hữu trí tuệ của mình, vui lòng gửi thông tin chi tiết dưới đây.
            </p>
            
            <form id="copyright-complaint-form" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="complaint-name" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Họ và tên người khiếu nại *</label>
                    <input type="text" name="reporter_name" id="complaint-name" class="form-input" placeholder="Nhập tên đầy đủ của bạn..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;">
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="complaint-email" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Email liên hệ *</label>
                        <input type="email" name="reporter_email" id="complaint-email" class="form-input" placeholder="Nhập email của bạn..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="complaint-phone" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Số điện thoại</label>
                        <input type="text" name="reporter_phone" id="complaint-phone" class="form-input" placeholder="Nhập số điện thoại..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="complaint-post-url" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Đường dẫn (URL) bài viết vi phạm *</label>
                    <input type="url" name="post_url" id="complaint-post-url" class="form-input" placeholder="Ví dụ: <?php echo SITE_URL; ?>/detail.php?id=123" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="complaint-desc" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Mô tả chi tiết & lý do khiếu nại *</label>
                    <textarea name="description" id="complaint-desc" class="form-input" placeholder="Cung cấp chi tiết lý do và thông tin vi phạm bản quyền..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); resize: vertical; height: 80px; padding: 10px; font-size: 13.5px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="complaint-evidence" class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Tệp tài liệu minh chứng (Hình ảnh, PDF, Tài liệu...)</label>
                    <input type="file" name="evidence" id="complaint-evidence" class="form-input" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px; font-size: 13px; border-radius: var(--radius-sm); width: 100%; box-sizing: border-box; outline: none;">
                </div>
                
                <div id="complaint-message" style="display: none; padding: 10px; border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 500; line-height: 1.4;"></div>
                
                <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 700; font-size: 14px; border-radius: var(--radius-full); height: 42px; width: 100%; margin-top: 6px; box-shadow: 0 4px 12px var(--accent-glow); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-paper-plane"></i> Gửi khiếu nại
                </button>
            </form>
        </div>
    </div>

    <!-- Global Report & Block Modal -->
    <div class="modal-overlay" id="global-report-modal" style="display: none; z-index: 1004;">
        <div class="modal-content glassmorphism-card" style="max-width: 450px; position: relative; padding: 24px; border-radius: var(--radius-md); width: 100%;">
            <button class="modal-close" onclick="document.getElementById('global-report-modal').style.display='none';" style="top: 15px; right: 15px;">&times;</button>
            <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: var(--text-primary); text-align: left;">
                Báo cáo vi phạm
            </h3>
            <form id="global-report-form" style="display: flex; flex-direction: column; gap: 14px;">
                <input type="hidden" name="target_type" id="report-target-type">
                <input type="hidden" name="target_id" id="report-target-id">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase;">Lý do báo cáo</label>
                    <select name="reason" id="report-reason" class="form-input" style="width:100%; padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm);" required>
                        <option value="">-- Chọn lý do vi phạm --</option>
                        <option value="spam">Spam / Tin nhắn rác</option>
                        <option value="nsfw">Nội dung 18+ / Nhạy cảm</option>
                        <option value="hate_speech">Ngôn từ kích động thù hận</option>
                        <option value="harassment">Quấy rối / Đe dọa</option>
                        <option value="other">Lý do khác</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase;">Chi tiết thêm (Tùy chọn)</label>
                    <textarea name="details" id="report-details" class="form-input" placeholder="Mô tả cụ thể hành vi vi phạm..." style="width: 100%; height: 80px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); resize: none; padding: 10px; box-sizing: border-box;"></textarea>
                </div>
                
                <div id="report-action-message" style="display: none; padding: 10px; border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 500; line-height: 1.4;"></div>
                
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" class="btn-primary" style="flex: 1; font-weight: 700; border-radius: var(--radius-full); height: 38px; font-size: 13.5px;">
                        Gửi báo cáo
                    </button>
                </div>
                
                <!-- Nút chặn nhanh chỉ hiển thị khi báo cáo người dùng / trang -->
                <div id="quick-block-container" style="display: none; margin-top: 8px; padding-top: 12px; border-top: 1px solid var(--border-color); text-align: center;">
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">Hoặc bạn muốn ngừng nhìn thấy nội dung từ họ?</p>
                    <button type="button" id="quick-block-btn" class="btn-secondary" style="width: 100%; font-weight: 700; border-radius: var(--radius-full); height: 36px; font-size: 13px; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); transition: all 0.2s;">
                        <i class="fa-solid fa-user-slash"></i> Chặn tài khoản này
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    $footer_copyright_raw = '© 2026 Frest by Nguyễn Mạnh Dũng (v640.630.45.105.12)';
    $footer_links_raw = getSystemSetting('footer_links', '[]');
    $footer_links = json_decode($footer_links_raw, true);
    if (!is_array($footer_links)) {
        $footer_links = [];
    }
    ?>
    


    <footer class="footer-main" style="padding: 40px 0 60px 0; margin-top: 60px; border-top: 1px solid var(--border-color); background: none;">
        <div class="container text-center" style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
            <div style="display: flex; gap: 20px; font-size: 13px; color: var(--text-secondary); flex-wrap: wrap; justify-content: center;">
                <?php foreach ($footer_links as $link): 
                    $title = htmlspecialchars($link['title'] ?? '');
                    $url = htmlspecialchars($link['url'] ?? '#');
                    $type = $link['type'] ?? 'external';
                    $icon = htmlspecialchars($link['icon'] ?? '');
                    
                    if ($type === 'terms_of_service' || $type === 'privacy_policy') {
                        echo '<a href="#" class="policy-link" data-policy="' . $type . '" style="hover: color: var(--text-primary);">' . $title . '</a>';
                    } elseif ($type === 'copyright_complaint') {
                        echo '<a href="#" class="copyright-complaint-trigger" style="hover: color: var(--text-primary);">' . $title . '</a>';
                    } else {
                        $icon_html = !empty($icon) ? '<i class="' . $icon . '" style="margin-right: 4px;"></i>' : '';
                        echo '<a href="' . $url . '" target="_blank" style="hover: color: var(--text-primary);">' . $icon_html . $title . '</a>';
                    }
                endforeach; ?>
            </div>
            
            <p style="font-size: 12px; color: var(--text-muted);"><?php echo sanitize($footer_copyright_raw); ?></p>
        </div>
    </footer>

    <!-- Custom Script Setup -->
    <script>
        var SITE_URL = window.SITE_URL || '<?php echo SITE_URL; ?>';
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/theme.js?v=<?php echo getAssetVersion('assets/js/theme.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/compose.js?v=<?php echo getAssetVersion('assets/js/compose.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/post.js?v=<?php echo getAssetVersion('assets/js/post.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/reaction.js?v=<?php echo getAssetVersion('assets/js/reaction.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/follow.js?v=<?php echo getAssetVersion('assets/js/follow.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/nsfw.js?v=<?php echo getAssetVersion('assets/js/nsfw.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/autocomplete.js?v=<?php echo getAssetVersion('assets/js/autocomplete.js'); ?>"></script>
    <?php if (isUserLoggedIn()): ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/notif_badge.js?v=<?php echo getAssetVersion('assets/js/notif_badge.js'); ?>"></script>
    <?php endif; ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/account.js?v=<?php echo getAssetVersion('assets/js/account.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/report_block.js?v=<?php echo getAssetVersion('assets/js/report_block.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/features.js?v=<?php echo getAssetVersion('assets/js/features.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/frest_wiki.js?v=<?php echo getAssetVersion('assets/js/frest_wiki.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js?v=<?php echo getAssetVersion('assets/js/main.js'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const complaintTrigger = document.querySelector('.copyright-complaint-trigger');
            const complaintModal = document.getElementById('copyright-complaint-modal');
            
            if (complaintTrigger && complaintModal) {
                complaintTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    complaintModal.style.display = 'flex';
                });
            }
            
            const complaintForm = document.getElementById('copyright-complaint-form');
            if (complaintForm) {
                complaintForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const form = this;
                    const btn = form.querySelector('button[type="submit"]');
                    const msgDiv = document.getElementById('complaint-message');
                    
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
                    msgDiv.style.display = 'none';
                    
                    const formData = new FormData(form);
                    
                    fetch(SITE_URL + '/submit_complaint.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Gửi khiếu nại';
                        msgDiv.style.display = 'block';
                        if(data.success) {
                            msgDiv.style.background = 'rgba(16, 185, 129, 0.1)';
                            msgDiv.style.color = 'var(--success)';
                            msgDiv.style.border = '1px solid rgba(16, 185, 129, 0.2)';
                            msgDiv.textContent = data.message;
                            form.reset();
                            setTimeout(() => {
                                complaintModal.style.display = 'none';
                                msgDiv.style.display = 'none';
                            }, 3000);
                        } else {
                            msgDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                            msgDiv.style.color = 'var(--danger)';
                            msgDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                            msgDiv.textContent = data.message;
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Gửi khiếu nại';
                        msgDiv.style.display = 'block';
                        msgDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                        msgDiv.style.color = 'var(--danger)';
                        msgDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
                        msgDiv.textContent = 'Có lỗi xảy ra kết nối đến máy chủ. Vui lòng thử lại sau.';
                    });
                });
            }
        });
    </script>
</body>
</html>

