<?php
/**
 * Shared Admin Footer Component
 */
?>
        </main>
    </div>

    <!-- Verified Info Modal -->
    <div class="modal-overlay" id="verified-info-modal" style="display: none; z-index: 1001;">
        <div class="modal-content verified-info-modal glassmorphism-card" style="position: relative;">
            <button class="modal-close" onclick="document.getElementById('verified-info-modal').style.display='none';" style="top: 15px; right: 15px;">&times;</button>
            <div class="verified-info-body">
                <div class="verified-info-icon-wrapper" id="verified-modal-icon">
                    <!-- Dynamic SVG Badge -->
                </div>
                <h3 class="verified-info-title" id="verified-modal-title">Tiêu đề xác minh</h3>
                <p class="verified-info-text" id="verified-modal-text">Nội dung mô tả...</p>

                <!-- Extended detail panel (hidden by default, shown on "Tìm hiểu thêm") -->
                <div id="verified-modal-detail-panel" style="display:none; margin-top: 14px; padding: 12px 14px; background: rgba(255,255,255,0.04); border: 1px solid var(--border-color); border-radius: 8px; text-align: left;">
                    <p id="verified-modal-detail-text" style="margin: 0; font-size: 12.5px; color: var(--text-secondary); line-height: 1.6;"></p>
                </div>

                <!-- Page Transparency Section inside Verified Modal -->
                <div id="verified-modal-transparency-link" style="margin-top: 16px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                    <i class="fa-solid fa-circle-info" style="color: var(--accent-primary); font-size: 16px; flex-shrink: 0;"></i>
                    <span style="font-size: 13.5px; font-weight: 700; color: var(--accent-primary);">Chính sách quyền riêng tư và tính minh bạch</span>
                </div>

                <div style="display: flex; gap: 8px; justify-content: center; margin-top: 16px;">
                    <button id="verified-info-learn-more-btn" style="display:none; font-size: 12px; padding: 6px 14px; border-radius: 6px; font-weight: 700; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">Tìm hiểu thêm</button>
                    <button class="verified-info-close-btn" onclick="document.getElementById('verified-info-modal').style.display='none';">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Transparency Modal (Facebook Page style) -->
    <div class="modal-overlay" id="page-transparency-modal" style="display: none; z-index: 1005;">
        <div class="modal-content transparency-modal-content glassmorphism-card" style="position: relative; max-width: 540px; padding: 0; width: 100%; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
            <!-- Modal Header -->
            <div class="transparency-header" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid var(--border-color); text-align: left; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                    <button id="transparency-back-btn" style="background: none; border: none; color: var(--text-primary); cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; padding: 0 4px; opacity: 0.8; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <h3 class="transparency-title-text" id="transparency-modal-title" style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-primary); word-break: break-all;">Tính minh bạch của Trang</h3>
                </div>
                <button class="modal-close-circle" onclick="document.getElementById('page-transparency-modal').style.display='none';" style="background: var(--bg-tertiary); border: none; color: var(--text-primary); cursor: pointer; font-size: 16px; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="transparency-body" style="padding: 14px 20px; text-align: left; flex: 1 1 auto; min-height: 0; overflow-y: auto;">
                <p class="transparency-intro-text" style="font-size: 13px; color: var(--text-secondary); line-height: 1.4; margin-top: 0; margin-bottom: 12px;">
                    Để đảm bảo sự an toàn trên Frest, chúng tôi sẽ hiển thị thông tin về mọi người và trang cá nhân của họ.
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
                        Lịch sử <i class="fa-regular fa-circle-question" style="font-size: 12px; color: var(--text-muted); cursor: pointer;"></i>
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
                                <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); line-height: 1.4;" id="transparency-modal-update-status">Cập nhật thông tin Trang</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Gần đây (trong vòng 7 ngày qua)</div>
                            </div>
                        </div>
                    </div>


                <!-- Page Managers Section -->
                <div style="margin-bottom: 20px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <h4 style="font-size: 14px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        Những người quản lý Trang này <i class="fa-regular fa-circle-question" style="font-size: 12px; color: var(--text-muted); cursor: pointer;"></i>
                    </h4>
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-primary); flex-shrink: 0;">
                            <i class="fa-solid fa-location-dot" style="font-size: 14px;"></i>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.45; margin-bottom: 6px;">
                                Vị trí quốc gia/khu vực chính của những người quản lý Trang này là:
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
                            <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Tìm hỗ trợ hoặc báo cáo Trang</div>
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

    <script>
        var SITE_URL = window.SITE_URL || '<?php echo SITE_URL; ?>';
        
        // Bind confirmation prompts directly to elements to ensure reliable browser navigation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-confirm]').forEach(function(el) {
                el.onclick = function() {
                    return confirm(this.getAttribute('data-confirm'));
                };
            });
        });
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
</body>
</html>


