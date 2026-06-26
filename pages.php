<?php
/**
 * Pages Management - Frest App
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$me = getLoggedInUser();
if (!$me) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$my_pages_stmt = $db->prepare("SELECT * FROM pages WHERE owner_id = ?");
$my_pages_stmt->execute([$me['id']]);
$my_pages = $my_pages_stmt->fetchAll();

// Handle messages from query params
$error_msg = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$success_msg = isset($_GET['success']) ? sanitize($_GET['success']) : '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="max-width: 600px; margin: 0 auto; padding-bottom: 100px; padding-top: 24px;">
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

    <!-- 1. Managed Pages List Card -->
    <div class="frest-card" style="margin-bottom: 24px; padding: 24px;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            <i class="fa-solid fa-rectangle-list" style="color: var(--accent-primary);"></i> Trang của bạn (<?php echo count($my_pages); ?>)
        </h3>

        <?php if (empty($my_pages)): ?>
            <div style="text-align: center; padding: 24px 0;">
                <i class="fa-solid fa-flag" style="font-size: 40px; color: var(--text-muted); opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                <p style="font-size: 13.5px; color: var(--text-muted); font-style: italic; margin: 0;">Bạn chưa sở hữu Trang nào. Hãy tạo một Trang ở bên dưới!</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($my_pages as $p): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-tertiary); border: 1px solid var(--border-color); padding: 12px 16px; border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                            <img src="<?php echo AVATARS_URL . '/' . sanitize($p['avatar_filename']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                            <div style="min-width: 0;">
                                <a href="<?php echo SITE_URL; ?>/page.php?username=<?php echo urlencode($p['page_username']); ?>" style="font-size: 14px; font-weight: 700; color: var(--text-primary); text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo sanitize($p['page_name']); ?>
                                </a>
                                <span style="font-size: 11.5px; color: var(--text-muted);">@<?php echo sanitize($p['page_username']); ?> • <?php echo sanitize($p['category']); ?></span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <a href="<?php echo SITE_URL; ?>/switch_identity.php?type=page&id=<?php echo $p['id']; ?>" class="btn-primary" style="font-size: 11px; padding: 6px 12px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); width: auto; font-weight: 700; text-decoration: none;">
                                Chuyển
                            </a>
                            <form action="<?php echo SITE_URL; ?>/delete_page.php" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA vĩnh viễn Trang này? Tất cả bài viết, phản hồi và người theo dõi của Trang sẽ bị xóa vĩnh viễn và không thể khôi phục!');" style="margin: 0;">
                                <input type="hidden" name="page_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-primary" style="font-size: 11px; padding: 6px 12px; height: 30px; border-radius: var(--radius-sm); width: auto; font-weight: 700; background: rgba(239, 68, 68, 0.12); border: 1px solid var(--danger); color: var(--danger); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s;">
                                    Xóa
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. Create Page Form Card -->
    <div id="create-page-section" class="frest-card" style="padding: 24px;">
        <h3 style="font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            <i class="fa-solid fa-circle-plus" style="color: var(--success);"></i> Tạo Trang mới
        </h3>
        
        <form action="<?php echo SITE_URL; ?>/create_page.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Tên Trang</label>
                <input type="text" name="page_name" class="form-input" placeholder="Ví dụ: Frest Việt Nam, Frest Studio..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; border-radius: var(--radius-sm); width: 100%;">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Tên người dùng của Trang (Handle)</label>
                <input type="text" name="page_username" class="form-input" placeholder="Ví dụ: frest_vn, frest_studio..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px; border-radius: var(--radius-sm); width: 100%;">
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Dùng để định danh liên kết: <?php echo SITE_URL; ?>/page.php?username=frest_vn</div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Danh mục</label>
                <select name="category" class="form-input" style="padding: 10px; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 100%;">
                    <?php foreach (getPageCategories() as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Tiểu sử (Mô tả ngắn)</label>
                <textarea name="bio" class="form-input" placeholder="Nhập mô tả về Trang của bạn..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); resize: none; height: 80px; padding: 10px; border-radius: var(--radius-sm); width: 100%;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Ảnh đại diện (Avatar)</label>
                <input type="file" name="avatar" accept="image/*" class="form-input" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px; border-radius: var(--radius-sm); width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px;">Ảnh bìa (Cover)</label>
                <input type="file" name="cover" accept="image/*" class="form-input" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px; border-radius: var(--radius-sm); width: 100%;">
            </div>
            
            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; font-weight: 700; width: 100%; border-radius: var(--radius-sm); height: 40px; font-size: 14px; margin-top: 10px; color: #fff; cursor: pointer;">
                Tạo Trang Ngay
            </button>
        </form>
    </div>
</div>

<script>
(function() {
    // If show_create is requested in URL, scroll to create section and focus input
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('show_create') === '1') {
        const createSection = document.getElementById('create-page-section');
        if (createSection) {
            createSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const nameInput = createSection.querySelector('input[name="page_name"]');
            if (nameInput) {
                nameInput.focus();
                nameInput.style.borderColor = 'var(--accent-primary)';
                nameInput.style.boxShadow = '0 0 0 3px var(--accent-glow)';
            }
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
