<?php
/**
 * Account Settings Page - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    header("Location: login.php");
    exit;
}

// Fetch fresh user data
try {
    $db = getDB();
    $fresh_me_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $fresh_me_stmt->execute([$me['id']]);
    $me = $fresh_me_stmt->fetch() ?: $me;
} catch (Exception $e) {}

$active_identity = getCurrentIdentity();
$is_acting_as_page = ($active_identity && $active_identity['type'] === 'page');

// Handle form submissions or status messages
$error_msg = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$success_msg = isset($_GET['success']) ? sanitize($_GET['success']) : '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="max-width: 800px; margin: 0 auto; padding-bottom: 100px; padding-top: 24px;">
    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div style="background: rgba(0, 186, 124, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 24px;">
            <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <div class="settings-container-card" id="account-settings-page-wrapper">
        <!-- Sidebar Navigation Left (Settings Tabs) -->
        <div class="settings-sidebar-modern">
            <!-- Sidebar User Summary Profile Header -->
            <div class="settings-profile-summary">
                <div class="settings-profile-avatar-wrap">
                    <img src="<?php echo AVATARS_URL . '/' . sanitize($me['avatar_filename']); ?>" alt="Avatar">
                </div>
                <div class="settings-profile-name"><?php echo sanitize($me['full_name'] ?: $me['username']); ?></div>
                <div class="settings-profile-username">@<?php echo sanitize($me['username']); ?></div>
                <span class="settings-profile-badge <?php echo intval($me['is_page']) === 1 ? 'page-badge' : ''; ?>">
                    <?php echo intval($me['is_page']) === 1 ? 'Trang Pro' : 'Cá nhân'; ?>
                </span>
            </div>
            
            <button class="settings-tab-btn-modern active" data-tab="tab-qrcode">
                <i class="fa-solid fa-qrcode"></i> Mã QR
            </button>
            
            <?php if (!$is_acting_as_page): ?>
                <button class="settings-tab-btn-modern" data-tab="tab-password">
                    <i class="fa-solid fa-key"></i> Đổi Mật Khẩu
                </button>
                <button class="settings-tab-btn-modern" data-tab="tab-nsfw">
                    <i class="fa-solid fa-eye-slash"></i> NSFW (18+)
                </button>
                <button class="settings-tab-btn-modern" data-tab="tab-pro-mode">
                    <i class="fa-solid fa-user-gear"></i> Chế độ chuyên nghiệp
                </button>
                <button class="settings-tab-btn-modern" data-tab="tab-repair">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Bảo trì tài khoản
                </button>
                <button class="settings-tab-btn-modern" data-tab="tab-login-history">
                    <i class="fa-solid fa-shield-halved"></i> Lịch sử & Bảo mật
                </button>
                <button class="settings-tab-btn-modern danger-tab" data-tab="tab-delete">
                    <i class="fa-solid fa-user-xmark"></i> Xóa tài khoản
                </button>
            <?php endif; ?>
        </div>

        <!-- Content Area Right -->
        <div class="settings-content-modern">
            <!-- Tab content: QR Code (Centered & Premium) -->
            <div class="settings-tab-content" id="tab-qrcode" style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                <div class="settings-tab-title" style="width: 100%; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-qrcode" style="color: var(--accent-primary);"></i>
                    <span><?php echo $is_acting_as_page ? 'Mã QR của Trang' : 'Mã QR cá nhân'; ?></span>
                </div>
                
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px;">
                    <?php echo $is_acting_as_page ? 'Đưa mã này cho người khác quét để truy cập nhanh Trang của bạn' : 'Đưa mã này cho người khác quét để kết bạn và truy cập nhanh hồ sơ của bạn'; ?>
                </p>
                
                <?php 
                if ($is_acting_as_page) {
                    $qr_url = SITE_URL . '/page.php?username=' . urlencode($active_identity['username']);
                } else {
                    $qr_url = SITE_URL . '/profile.php?username=' . urlencode($me['username']);
                    if (!empty($me['qr_reset_at'])) {
                        $qr_url .= '&qrr=' . strtotime($me['qr_reset_at']);
                    }
                }
                ?>

                <div class="settings-premium-qr-box">
                    <div class="settings-premium-qr-inner">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($qr_url); ?>" alt="QR Code">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; width: 100%; max-width: 440px; margin-bottom: 12px;">
                    <input type="text" readonly value="<?php echo htmlspecialchars($qr_url); ?>" id="qr-profile-url-input" class="settings-glass-input" style="text-align: center;">
                    <button type="button" id="btn-copy-qr-url" class="settings-modern-btn" style="width: auto; padding: 0 20px; flex-shrink: 0; white-space: nowrap;">
                        <i class="fa-regular fa-copy"></i> Sao chép
                    </button>
                </div>
            </div>

            <?php if (!$is_acting_as_page): ?>
                <!-- Tab content: Change Password -->
                <div class="settings-tab-content" id="tab-password" style="display: none;">
                    <div class="settings-tab-title">
                        <i class="fa-solid fa-key" style="color: var(--accent-primary);"></i>
                        <span>Thay đổi mật khẩu</span>
                    </div>
                    <form id="settings-change-password-form" style="display: flex; flex-direction: column; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Mật khẩu hiện tại</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" name="current_password" class="settings-glass-input" placeholder="Nhập mật khẩu hiện tại..." required style="padding-right: 44px !important;">
                                <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" name="new_password" class="settings-glass-input" placeholder="Nhập mật khẩu mới..." required style="padding-right: 44px !important;">
                                <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Xác nhận mật khẩu mới</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" name="confirm_password" class="settings-glass-input" placeholder="Xác nhận lại mật khẩu mới..." required style="padding-right: 44px !important;">
                                <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="settings-modern-btn">
                            Cập nhật mật khẩu
                        </button>
                    </form>
                </div>

                <!-- Tab content: NSFW Settings & Age Verification -->
                <div class="settings-tab-content" id="tab-nsfw" style="display: none;">
                    <div class="settings-tab-title">
                        <i class="fa-solid fa-eye-slash" style="color: var(--accent-primary);"></i>
                        <span>Nội dung nhạy cảm (NSFW)</span>
                    </div>
                    
                    <label class="settings-switch-card">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="padding-right: 12px; min-width: 0;">
                                <div style="font-size: 14px; font-weight: 700; color: var(--text-primary);">Hiển thị nội dung NSFW (18+)</div>
                                <div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.45; margin-top: 2px;">Tự động hiển thị và bỏ che mờ các hình ảnh, video nhạy cảm trên bảng tin.</div>
                            </div>
                            <div class="switch-container" style="position: relative; display: inline-block; width: 44px; height: 24px; margin-bottom: 0; flex-shrink: 0;">
                                <input type="checkbox" id="nsfw-toggle" <?php echo intval($me['show_nsfw'] ?? 0) === 1 ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                <span class="switch-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;"></span>
                            </div>
                        </div>
                    </label>

                    <div id="nsfw-verification-form-box" style="display: <?php echo ($me['age_verification_status'] === 'unverified' || $me['age_verification_status'] === 'rejected' || empty($me['age_verification_status'])) ? 'block' : 'none'; ?>; background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-top: 12px;">
                        <form id="age-verification-form" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 16px;">
                            <h5 style="font-size: 14px; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;"><i class="fa-solid fa-shield-halved" style="color: var(--accent-primary); margin-right: 6px;"></i> Yêu cầu xác minh tuổi tác</h5>
                            <p style="font-size: 12px; color: var(--text-secondary); line-height: 1.45; margin-bottom: 12px;">Do chính sách nội dung 18+ của chúng tôi và cơ quan quản lý, bạn cần tải lên hình ảnh giấy tờ tùy thân chứng minh mình trên 18 tuổi để bật tính năng này.</p>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Ngày sinh (DOB)</label>
                                <input type="date" name="dob" id="verification-dob" class="settings-glass-input" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Ảnh chụp CMND/CCCD hoặc Hộ chiếu</label>
                                <input type="file" name="id_proof" id="verification-id-proof" accept="image/*" class="settings-glass-input" required style="padding: 8px 16px !important;">
                                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 4px;">Tải lên ảnh chụp mặt trước giấy tờ tùy thân của bạn để đối chiếu độ tuổi.</div>
                            </div>

                            <button type="submit" class="settings-modern-btn">Gửi xác minh</button>
                        </form>
                    </div>

                    <div id="nsfw-pending-box" style="display: <?php echo ($me['age_verification_status'] ?? '') === 'pending' ? 'block' : 'none'; ?>; background: rgba(235, 94, 40, 0.05); border: 1px dashed var(--accent-primary); border-radius: 16px; padding: 18px; margin-top: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <i class="fa-solid fa-hourglass-half" style="color: var(--accent-primary); font-size: 18px; margin-top: 2px;"></i>
                            <div>
                                <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Đang chờ phê duyệt</div>
                                <div style="font-size: 11.5px; color: var(--text-secondary); line-height: 1.45; margin-top: 4px;">
                                    Yêu cầu xác minh độ tuổi của bạn đang được quản trị viên xét duyệt. Chúng tôi sẽ thông báo cho bạn sớm nhất có thể.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="nsfw-verified-box" style="display: <?php echo ($me['age_verification_status'] ?? '') === 'verified' || intval($me['is_adult'] ?? 0) === 1 ? 'block' : 'none'; ?>; background: rgba(0, 186, 124, 0.05); border: 1px solid var(--success); border-radius: 16px; padding: 18px; margin-top: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <i class="fa-solid fa-circle-check" style="color: var(--success); font-size: 18px;"></i>
                            <div>
                                <div style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Đã xác minh trên 18 tuổi</div>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Cảm ơn bạn. Độ tuổi của bạn đã được xác minh trên hệ thống.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab content: Convert to Page (Professional Mode) -->
                <div class="settings-tab-content" id="tab-pro-mode" style="display: none;">
                    <div class="settings-tab-title">
                        <i class="fa-solid fa-user-gear" style="color: var(--accent-primary);"></i>
                        <span>Chế độ chuyên nghiệp</span>
                    </div>
                    <div style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <h5 style="font-size: 14.5px; font-weight: 800; color: var(--text-primary); margin: 0 0 8px 0; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-user-gear" style="color: var(--accent-primary);"></i> Chế độ chuyên nghiệp (Professional Mode)
                        </h5>
                        <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin: 0 0 20px 0;">
                            Chuyển đổi tài khoản cá nhân của bạn sang dạng Trang để hiển thị số người theo dõi, danh mục cộng đồng và tiếp cận công chúng giống như một Trang Frest Pro.
                        </p>
                        
                        <form action="<?php echo SITE_URL; ?>/profile.php?username=<?php echo urlencode($me['username']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                            <input type="hidden" name="action_toggle_professional_mode" value="1">
                            
                            <label class="settings-switch-card" style="border-radius: 12px; padding: 12px 16px; margin-bottom: 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Bật Chế độ chuyên nghiệp</span>
                                    <div class="switch-container" style="position: relative; display: inline-block; width: 44px; height: 24px; margin-bottom: 0; flex-shrink: 0;">
                                        <input type="checkbox" name="is_page" value="1" <?php echo intval($me['is_page'] ?? 0) === 1 ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;" id="pro_mode_toggle_input">
                                        <span class="switch-slider"></span>
                                    </div>
                                </div>
                            </label>
                            
                            <div class="form-group" id="pro_category_container" style="margin-bottom: 0; display: <?php echo intval($me['is_page'] ?? 0) === 1 ? 'block' : 'none'; ?>;">
                                <label class="form-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Danh mục Trang cá nhân</label>
                                <select name="page_category" class="settings-glass-input">
                                    <?php foreach (getPageCategories() as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($me['page_category'] ?? '') === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="settings-modern-btn">Lưu thiết lập chế độ</button>
                        </form>
                    </div>
                </div>

                <!-- Tab content: Delete Account -->
                <div class="settings-tab-content" id="tab-delete" style="display: none; text-align: center;">
                    <div class="settings-tab-title" style="text-align: left;">
                        <i class="fa-solid fa-user-xmark" style="color: var(--danger);"></i>
                        <span>Xóa tài khoản</span>
                    </div>
                    
                    <div style="background: rgba(239, 68, 68, 0.04); border: 1px solid rgba(239, 68, 68, 0.15); border-radius: 16px; padding: 28px; margin-top: 10px; box-shadow: 0 4px 16px rgba(239,68,68,0.06);">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 44px; color: var(--danger); margin-bottom: 16px; display: block; filter: drop-shadow(0 4px 8px rgba(239,68,68,0.2));"></i>
                        <h4 style="font-family: var(--font-heading); font-size: 16px; margin-bottom: 8px; color: var(--danger); font-weight: 800;">Xóa tài khoản vĩnh viễn</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.5;">
                            Hành động này <strong>không thể hoàn tác</strong>. Tất cả dữ liệu của bạn bao gồm bài đăng, tin nhắn và thông tin cá nhân sẽ bị xóa sạch khỏi hệ thống vĩnh viễn.
                        </p>
                        <form id="settings-delete-account-form" style="display: flex; flex-direction: column; gap: 16px; text-align: left;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Xác nhận mật khẩu của tài khoản</label>
                                <div class="password-toggle-wrapper">
                                    <input type="password" name="confirm_password" id="delete-account-password" class="settings-glass-input" placeholder="Nhập mật khẩu hiện tại của bạn..." required style="padding-right: 44px !important;">
                                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" id="btn-delete-my-account" class="settings-modern-btn btn-danger-modern" style="margin-top: 10px;">
                                Tôi đồng ý và muốn xóa tài khoản này
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab content: Account Maintenance & Repair -->
                <div class="settings-tab-content" id="tab-repair" style="display: none;">
                    <div class="settings-tab-title">
                        <i class="fa-solid fa-screwdriver-wrench" style="color: var(--accent-primary);"></i>
                        <span>Bảo trì tài khoản</span>
                    </div>
                    
                    <div style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <h5 style="font-size: 14.5px; font-weight: 800; color: var(--text-primary); margin: 0 0 8px 0; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-screwdriver-wrench" style="color: var(--accent-primary);"></i> Kiểm tra và Sửa lỗi tự động
                        </h5>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin: 0 0 20px 0;">
                            Hệ thống sẽ tiến hành rà soát các lỗi cấu hình tài khoản của bạn (như thiếu trường dữ liệu thiết lập, lỗi đường dẫn tệp tin đa phương tiện, các liên kết lượt theo dõi hoặc tương tác bị trùng lặp/hỏng) và tự động khắc phục. Toàn bộ dữ liệu bài viết, tin nhắn và thông tin gốc của bạn sẽ được giữ nguyên hoàn toàn.
                        </p>
                        
                        <button type="button" id="btn-run-repair" class="settings-modern-btn">
                            <i class="fa-solid fa-play"></i> Bắt đầu kiểm tra và sửa lỗi
                        </button>

                        <!-- Circular Progress Scan Overlay/Inline -->
                        <div id="repair-progress-wrapper" style="margin-top: 24px; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 24px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 16px;">
                            <div class="progress-circle-container" style="position: relative; width: 110px; height: 110px;">
                                <svg width="110" height="110" viewBox="0 0 110 110" style="transform: rotate(-90deg);">
                                    <circle cx="55" cy="55" r="46" stroke="rgba(255, 255, 255, 0.05)" stroke-width="8" fill="transparent" />
                                    <circle id="repair-progress-circle" cx="55" cy="55" r="46" stroke="url(#repairProgressGrad)" stroke-width="8" fill="transparent" stroke-dasharray="289.02" stroke-dashoffset="289.02" style="transition: stroke-dashoffset 0.05s linear; stroke-linecap: round;" />
                                    <defs>
                                        <linearGradient id="repairProgressGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="var(--accent-primary)" />
                                            <stop offset="100%" stop-color="#ec4899" />
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <div id="repair-progress-text" style="position: absolute; top: 0; left: 0; width: 110px; height: 110px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: var(--text-primary); font-family: var(--font-heading);">0%</div>
                            </div>
                            <div id="repair-progress-status" style="font-weight: 700; font-size: 13.5px; color: var(--text-secondary); text-align: center; height: 20px; font-family: var(--font-heading);">Khởi động...</div>
                            <div style="color: var(--text-muted); font-size: 11px; text-align: center;">Hệ thống đang quét phân tích tài khoản của bạn, vui lòng đợi...</div>
                        </div>

                        <div id="repair-results-wrapper" style="margin-top: 24px; display: none;">
                            <h6 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                                Kết quả kiểm tra hệ thống:
                            </h6>
                            <div id="repair-results-list" style="display: flex; flex-direction: column; gap: 10px;">
                                <!-- Dynamic items here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab content: Login History & Security -->
                <div class="settings-tab-content" id="tab-login-history" style="display: none;">
                    <div class="settings-tab-title">
                        <i class="fa-solid fa-shield-halved" style="color: var(--accent-primary);"></i>
                        <span>Lịch sử đăng nhập & Thiết bị</span>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                        Xem danh sách các thiết bị và vị trí đã đăng nhập vào tài khoản của bạn. Nếu phát hiện bất kỳ hoạt động đáng ngờ nào, hãy sử dụng tính năng báo động khẩn cấp để đổi mật khẩu và đăng xuất.
                    </p>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;" id="login-history-list">
                        <?php
                        try {
                            $db = getDB();
                            // Ensure table exists (self-healing)
                            $db->exec("CREATE TABLE IF NOT EXISTS login_history (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT NOT NULL,
                                ip_address VARCHAR(45) NOT NULL,
                                user_agent VARCHAR(255) DEFAULT NULL,
                                location VARCHAR(100) DEFAULT NULL,
                                login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                            
                            $stmt_lh = $db->prepare("SELECT * FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 15");
                            $stmt_lh->execute([$me['id']]);
                            $logins = $stmt_lh->fetchAll();
                            
                            if (empty($logins)) {
                                echo '<div style="text-align: center; color: var(--text-muted); padding: 30px; font-size: 13.5px;">Chưa ghi nhận lịch sử đăng nhập nào.</div>';
                            } else {
                                $current_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                                if ($current_ip === '::1') $current_ip = '127.0.0.1';
                                $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                
                                foreach ($logins as $idx => $log) {
                                    $log_ip = $log['ip_address'];
                                    $log_ua = $log['user_agent'] ?? '';
                                    $is_current = false;
                                    
                                    if ($idx === 0 && $log_ip === $current_ip) {
                                        $is_current = true;
                                    }
                                    
                                    $clean_device = getCleanUserAgent($log_ua);
                                    $time_formatted = date('H:i - d/m/Y', strtotime($log['login_time']));
                                    
                                    echo '<div class="settings-login-item-modern">';
                                    echo '  <div style="display: flex; gap: 14px; align-items: flex-start; min-width: 0; flex: 1;">';
                                    echo '    <div style="width: 40px; height: 40px; border-radius: 50%; background: ' . ($is_current ? 'rgba(16, 185, 129, 0.1)' : 'rgba(139, 92, 246, 0.1)') . '; display: flex; align-items: center; justify-content: center; color: ' . ($is_current ? 'var(--success)' : 'var(--accent-primary)') . '; font-size: 18px; flex-shrink: 0;">';
                                    echo '      <i class="fa-solid ' . ($is_current ? 'fa-laptop' : 'fa-triangle-exclamation') . '"></i>';
                                    echo '    </div>';
                                    echo '    <div style="min-width: 0; flex: 1;">';
                                    echo '      <div style="font-weight: 700; font-size: 14px; color: var(--text-primary); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">';
                                    echo '        ' . htmlspecialchars($clean_device);
                                    if ($is_current) {
                                        echo '      <span style="font-size: 10px; font-weight: 700; color: var(--success); background: rgba(16, 185, 129, 0.08); padding: 2px 8px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2); white-space: nowrap;">Đang hoạt động</span>';
                                    }
                                    echo '      </div>';
                                    echo '      <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; display: flex; flex-direction: column; gap: 2px;">';
                                    echo '        <span style="word-break: break-all;">Địa chỉ IP: <strong>' . htmlspecialchars($log_ip) . '</strong> (' . htmlspecialchars($log['location'] ?? 'Không xác định') . ')</span>';
                                    echo '        <span>Thời gian: ' . $time_formatted . '</span>';
                                    echo '      </div>';
                                    echo '    </div>';
                                    echo '  </div>';
                                    
                                    echo '  <div class="login-item-actions" style="display: flex; gap: 8px; align-items: center;">';
                                    if (!$is_current) {
                                        echo '    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--success); background: rgba(16, 185, 129, 0.08); padding: 4px 10px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2); display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;"><i class="fa-solid fa-circle-check"></i> An toàn</span>';
                                        echo '    <button type="button" onclick="openSecurityResetModal()" class="settings-modern-btn btn-danger-modern" style="padding: 0 12px; height: 32px; font-size: 11.5px; border-radius: 10px !important; width: auto;"><i class="fa-solid fa-triangle-exclamation"></i> Không phải tôi</button>';
                                    } else {
                                        echo '    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--success); background: rgba(16, 185, 129, 0.08); padding: 4px 10px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2); display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;"><i class="fa-solid fa-circle-check"></i> Hoạt động</span>';
                                    }
                                    echo '  </div>';
                                    echo '</div>';
                                }
                            }
                        } catch (Exception $ex) {
                            echo '<div style="color: var(--danger); font-size: 13.5px;">Lỗi tải dữ liệu lịch sử đăng nhập.</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add the script directly in settings.php for clean tab logic -->
<script>
(function() {
    // 1. Tab Switching client-side (Preserving layout displays)
    const tabs = document.querySelectorAll('.settings-tab-btn-modern');
    const contents = document.querySelectorAll('.settings-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.style.display = 'none');

            tab.classList.add('active');
            const targetContent = document.getElementById(tab.getAttribute('data-tab'));
            if (targetContent) {
                if (tab.getAttribute('data-tab') === 'tab-qrcode') {
                    targetContent.style.display = 'flex';
                } else {
                    targetContent.style.display = 'block';
                }
            }
        });
    });

    // Copy QR Profile URL handler
    const copyBtn = document.getElementById('btn-copy-qr-url');
    const qrInput = document.getElementById('qr-profile-url-input');
    copyBtn?.addEventListener('click', () => {
        if (qrInput) {
            qrInput.select();
            qrInput.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(qrInput.value)
            .then(() => {
                showToast('Đã sao chép liên kết hồ sơ!');
            })
            .catch(err => {
                console.error('Copy failed:', err);
                showToast('Không thể sao chép liên kết.');
            });
        }
    });

    // 2. NSFW toggle event handler
    const nsfwToggle = document.getElementById('nsfw-toggle');
    nsfwToggle?.addEventListener('change', () => {
        const val = nsfwToggle.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('show_nsfw', val);

        fetch(`${SITE_URL_JS()}/update_nsfw_settings.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Đã lưu cấu hình NSFW!');
            } else {
                showToast(data.message || 'Lỗi khi cập nhật cấu hình NSFW.');
                nsfwToggle.checked = !nsfwToggle.checked; // Revert
            }
        })
        .catch(err => {
            console.error('NSFW settings error:', err);
            showToast('Lỗi mạng. Vui lòng thử lại.');
            nsfwToggle.checked = !nsfwToggle.checked; // Revert
        });
    });

    // 3. Pro mode toggle UI handler
    const proModeInput = document.getElementById('pro_mode_toggle_input');
    const proCatContainer = document.getElementById('pro_category_container');
    proModeInput?.addEventListener('change', () => {
        if (proCatContainer) {
            proCatContainer.style.display = proModeInput.checked ? 'block' : 'none';
        }
    });

    // 4. Age Verification Form handler
    const ageForm = document.getElementById('age-verification-form');
    ageForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(ageForm);
        formData.append('action_age_verification', '1');

        fetch(`${SITE_URL_JS()}/update_nsfw_settings.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Gửi yêu cầu xác minh tuổi thành công!');
                const nsfwFormBox = document.getElementById('nsfw-verification-form-box');
                const nsfwPendingBox = document.getElementById('nsfw-pending-box');
                if (nsfwFormBox) nsfwFormBox.style.display = 'none';
                if (nsfwPendingBox) nsfwPendingBox.style.display = 'block';
            } else {
                showToast(data.message || 'Lỗi gửi yêu cầu xác minh tuổi.');
            }
        })
        .catch(err => {
            console.error('Age verification error:', err);
            showToast('Lỗi mạng. Vui lòng thử lại.');
        });
    });

    // 5. Account Repair Handler with Premium Circular Progress Scan
    const runRepairBtn = document.getElementById('btn-run-repair');
    const repairProgressWrapper = document.getElementById('repair-progress-wrapper');
    const repairProgressCircle = document.getElementById('repair-progress-circle');
    const repairProgressText = document.getElementById('repair-progress-text');
    const repairProgressStatus = document.getElementById('repair-progress-status');
    const repairResultsWrapper = document.getElementById('repair-results-wrapper');
    const repairResultsList = document.getElementById('repair-results-list');

    runRepairBtn?.addEventListener('click', () => {
        runRepairBtn.disabled = true;
        runRepairBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang chuẩn bị quét...';
        
        if (repairResultsWrapper) repairResultsWrapper.style.display = 'none';
        if (repairProgressWrapper) repairProgressWrapper.style.display = 'flex';
        if (repairResultsList) repairResultsList.innerHTML = '';

        // Reset progress ring
        if (repairProgressCircle) repairProgressCircle.style.strokeDashoffset = '289.02';
        if (repairProgressText) repairProgressText.innerText = '0%';
        if (repairProgressStatus) repairProgressStatus.innerText = 'Khởi động chẩn đoán...';

        let progress = 0;
        let ajaxFinished = false;
        let ajaxSuccess = false;
        let ajaxData = null;
        let progressInterval = null;

        // Perform the actual backend repair query
        fetch(`${SITE_URL_JS()}/repair_account.php`)
        .then(res => res.json())
        .then(data => {
            ajaxFinished = true;
            ajaxSuccess = data.success;
            ajaxData = data;
            
            // If the progress bar is already at 100%, trigger final visualization
            if (progress >= 100) {
                finalizeRepairProcess(ajaxSuccess, ajaxData);
            }
        })
        .catch(err => {
            console.error('Account repair error:', err);
            ajaxFinished = true;
            ajaxSuccess = false;
            if (progress >= 100) {
                finalizeRepairProcess(false, null);
            }
        });

        // Simulate a smooth circular loading diagnostic scan from 0% to 100% over 2500ms
        const totalDuration = 2500;
        const intervalTime = 25; // update every 25ms
        const totalSteps = totalDuration / intervalTime;
        const increment = 100 / totalSteps;

        progressInterval = setInterval(() => {
            progress += increment;
            if (progress > 100) progress = 100;

            const roundedProgress = Math.round(progress);
            if (repairProgressText) repairProgressText.innerText = `${roundedProgress}%`;
            
            if (repairProgressCircle) {
                const offset = 289.02 - (roundedProgress / 100) * 289.02;
                repairProgressCircle.style.strokeDashoffset = offset;
            }

            // Update loading status text dynamically to match the scanning percentage
            if (repairProgressStatus) {
                if (roundedProgress < 20) {
                    repairProgressStatus.innerText = 'Đang quét cấu trúc thư mục uploads...';
                } else if (roundedProgress < 45) {
                    repairProgressStatus.innerText = 'Kiểm tra ảnh đại diện & ảnh bìa...';
                } else if (roundedProgress < 70) {
                    repairProgressStatus.innerText = 'Đối chiếu email & cấu hình mặc định...';
                } else if (roundedProgress < 90) {
                    repairProgressStatus.innerText = 'Chuẩn hóa danh sách người theo dõi...';
                } else if (roundedProgress < 100) {
                    repairProgressStatus.innerText = 'Dọn dẹp lượt bày tỏ cảm xúc mồ côi...';
                } else {
                    repairProgressStatus.innerText = 'Hoàn tất phân tích hệ thống!';
                }
            }

            if (roundedProgress >= 100) {
                clearInterval(progressInterval);
                // If AJAX request is also finished, finalize
                if (ajaxFinished) {
                    finalizeRepairProcess(ajaxSuccess, ajaxData);
                }
            }
        }, intervalTime);

        function finalizeRepairProcess(success, data) {
            runRepairBtn.disabled = false;
            runRepairBtn.innerHTML = '<i class="fa-solid fa-check"></i> Đã hoàn thành kiểm tra';
            if (repairProgressWrapper) repairProgressWrapper.style.display = 'none';

            if (success && data) {
                showToast(data.message || 'Kiểm tra và sửa lỗi tài khoản thành công!');
                if (repairResultsWrapper) {
                    repairResultsWrapper.style.display = 'block';
                    repairResultsWrapper.style.animation = 'fadeIn 0.4s ease';
                }
                
                if (data.report && repairResultsList) {
                    data.report.forEach(item => {
                        const row = document.createElement('div');
                        row.style.cssText = 'background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; display: flex; flex-direction: column; gap: 4px; text-align: left; animation: vimSlideUp 0.3s ease;';
                        
                        const isOk = item.status === 'OK';
                        const statusColor = isOk ? 'var(--success)' : 'var(--accent-primary)';
                        const statusBg = isOk ? 'rgba(0, 186, 124, 0.08)' : 'rgba(139, 92, 246, 0.08)';
                        const iconClass = isOk ? 'fa-solid fa-circle-check' : 'fa-solid fa-screwdriver-wrench';

                        row.innerHTML = `
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                <span style="font-weight: 700; color: var(--text-primary); font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                                    <i class="${iconClass}" style="color: ${statusColor};"></i>
                                    ${escapeHTML(item.name)}
                                </span>
                                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: ${statusColor}; background: ${statusBg}; padding: 4px 10px; border-radius: var(--radius-full); border: 1px solid ${statusColor}44;">
                                    ${escapeHTML(item.status)}
                                </span>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.45; margin-top: 4px; padding-left: 20px;">
                                ${escapeHTML(item.details)}
                            </div>
                        `;
                        repairResultsList.appendChild(row);
                    });
                }
            } else {
                showToast((data && data.message) || 'Lỗi khi kiểm tra sửa lỗi tài khoản hoặc mất kết nối.');
            }
        }
    });

    function escapeHTML(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // Check query params to switch tab on load
    const urlParams = new URLSearchParams(window.location.search);
    const targetTab = urlParams.get('select_tab');
    if (targetTab) {
        const btn = document.querySelector(`.settings-tab-btn-modern[data-tab="tab-${targetTab}"]`);
        if (btn) btn.click();
    }

    // Security Reset Modal functions
    window.openSecurityResetModal = function() {
        const modal = document.getElementById('security-reset-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.closeSecurityResetModal = function() {
        const modal = document.getElementById('security-reset-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    const securityResetForm = document.getElementById('security-reset-form');
    securityResetForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(securityResetForm);
        formData.append('action_security_reset', '1');

        fetch(`${SITE_URL_JS()}/handle_suspicious_login.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Thay đổi mật khẩu thành công! Đang đăng xuất...');
                setTimeout(() => {
                    window.location.href = 'logout.php';
                }, 1500);
            } else {
                showToast(data.message || 'Lỗi khi đặt lại mật khẩu: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Security reset error:', err);
            showToast('Lỗi mạng. Vui lòng thử lại.');
        });
    });
})();
</script>

<!-- Glassmorphic Security Reset Modal -->
<div id="security-reset-modal" class="modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px; animation: fadeIn 0.3s ease;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 20px; max-width: 460px; width: 100%; padding: 28px; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5); text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; color: var(--danger); font-size: 24px; margin: 0 auto 16px auto;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h4 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">Cảnh báo bảo mật tài khoản!</h4>
        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 24px;">
            Nếu bạn không nhận ra thiết bị này, tài khoản của bạn có thể đã bị truy cập trái phép. Để bảo vệ dữ liệu, hệ thống yêu cầu bạn <strong>đặt lại mật khẩu mới ngay lập tức</strong>. Sau khi xác nhận, toàn bộ các phiên đăng nhập khác sẽ bị hủy và bạn sẽ được đăng xuất để đăng nhập lại bằng mật khẩu mới.
        </p>
        
        <form id="security-reset-form" style="display: flex; flex-direction: column; gap: 16px; text-align: left;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                <div class="password-toggle-wrapper">
                    <input type="password" name="new_password" required placeholder="Nhập mật khẩu mới an toàn..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: var(--radius-sm); width: 100%; outline: none; box-sizing: border-box; padding-right: 44px !important;">
                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Xác nhận mật khẩu mới</label>
                <div class="password-toggle-wrapper">
                    <input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới để xác nhận..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: var(--radius-sm); width: 100%; outline: none; box-sizing: border-box; padding-right: 44px !important;">
                    <button type="button" class="password-toggle-btn" onclick="let input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.querySelector('i').className = 'fa-regular fa-eye-slash'; } else { input.type = 'password'; this.querySelector('i').className = 'fa-regular fa-eye'; }" title="Hiển thị/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="security-modal-buttons">
                <button type="button" onclick="closeSecurityResetModal()" class="btn-primary" style="flex: 1; background: rgba(255,255,255,0.06); border: 1px solid var(--border-color); color: var(--text-primary); height: 40px; border-radius: var(--radius-full); font-weight: 700; cursor: pointer;">Hủy</button>
                <button type="submit" class="btn-purchase-action" style="flex: 1; border: none; background: var(--accent-gradient); color: #fff; height: 40px; border-radius: var(--radius-full); font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px var(--accent-glow);">Đổi mật khẩu & Đăng xuất</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Settings Specific Styling */
.qr-code-premium-container {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}
body.light-theme .qr-code-premium-container {
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
}
.qr-code-premium-container:hover {
    box-shadow: 0 12px 40px var(--accent-glow);
}
#account-settings-page-wrapper .settings-tab-btn-modern.active {
    background: var(--border-color);
    color: var(--accent-primary) !important;
}
#account-settings-page-wrapper .settings-tab-btn-modern:hover:not(.active) {
    background: var(--border-hover);
}
@keyframes settingsFadeSlide {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.settings-tab-content {
    width: 100%;
    animation: settingsFadeSlide 0.22s cubic-bezier(0.1, 0.8, 0.2, 1) forwards;
}
.login-history-item {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    transition: all var(--transition-fast);
}
.login-item-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.security-modal-buttons {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}
@media (max-width: 580px) {
    .login-history-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .login-history-item .login-item-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid var(--border-color);
        padding-top: 12px;
        margin-top: 4px;
    }
}
@media (max-width: 480px) {
    .security-modal-buttons {
        flex-direction: column-reverse;
    }
    .security-modal-buttons button {
        width: 100% !important;
    }
}
@media (max-width: 650px) {
    #account-settings-page-wrapper {
        flex-direction: column !important;
        min-height: auto !important;
    }
    #account-settings-page-wrapper .settings-sidebar {
        width: 100% !important;
        border-right: none !important;
        border-bottom: 1px solid var(--border-color) !important;
        flex-direction: row !important;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding: 10px 8px !important;
        gap: 8px !important;
    }
    #account-settings-page-wrapper .settings-sidebar::-webkit-scrollbar {
        height: 3px;
        display: block !important;
    }
    #account-settings-page-wrapper .settings-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    #account-settings-page-wrapper .settings-sidebar::-webkit-scrollbar-thumb {
        background: var(--accent-primary);
        border-radius: 3px;
    }
    #account-settings-page-wrapper .settings-sidebar h4 {
        display: none !important;
    }
    #account-settings-page-wrapper .settings-sidebar button {
        white-space: nowrap;
        width: auto !important;
        flex-shrink: 0;
        padding: 8px 12px !important;
        font-size: 13px !important;
    }
    #account-settings-page-wrapper .settings-content-wrapper {
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 20px 16px !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
