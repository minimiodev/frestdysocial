<?php
/**
 * Create Story Page - Frest App
 */
require_once __DIR__ . '/includes/header.php';

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit;
}

$me = getLoggedInUser();
$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_story'])) {
    $media_type = trim($_POST['media_type'] ?? 'image'); // 'image', 'video', 'text'
    $text_content = trim($_POST['text_content'] ?? '');
    $bg_color = trim($_POST['bg_color'] ?? 'linear-gradient(135deg, #8b5cf6, #ec4899)'); // default gradient
    
    $media_filename = null;
    
    try {
        $db = getDB();
        
        $stories_dir = getUserUploadPath($me['username'], 'stories');
        $db_save_prefix = 'users/' . $me['username'] . '/';
        
        if ($media_type === 'image' || $media_type === 'video') {
            if (isset($_FILES['story_file']) && $_FILES['story_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['story_file']['tmp_name'];
                $file_name = $_FILES['story_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $video_exts = ['mp4', 'webm', 'mov'];
                
                if (in_array($file_ext, $image_exts)) {
                    $media_type = 'image';
                    $new_name = 'story_' . uniqid() . '.' . $file_ext;
                    $dest = $stories_dir . $new_name;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $media_filename = $db_save_prefix . $new_name;
                    } else {
                        throw new Exception("Không thể di chuyển file ảnh vào thư mục stories.");
                    }
                } elseif (in_array($file_ext, $video_exts)) {
                    $media_type = 'video';
                    $new_name = 'story_' . uniqid() . '.' . $file_ext;
                    $dest = $stories_dir . $new_name;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $media_filename = $db_save_prefix . $new_name;
                    } else {
                        throw new Exception("Không thể di chuyển file video vào thư mục stories.");
                    }
                } else {
                    throw new Exception("Định dạng file không hợp lệ. Chỉ chấp nhận ảnh (jpg, png, webp, gif) hoặc video (mp4, webm, mov).");
                }
            } else {
                throw new Exception("Vui lòng chọn tệp tin ảnh hoặc video để tải lên.");
            }
        } elseif ($media_type === 'text') {
            if (empty($text_content)) {
                throw new Exception("Nội dung văn bản câu chuyện không được để trống.");
            }
        } else {
            throw new Exception("Kiểu câu chuyện không hợp lệ.");
        }
        
        // Insert into database with 24 hours expiry
        $stmt = $db->prepare("
            INSERT INTO stories (user_id, media_type, media_filename, text_content, bg_color, expires_at) 
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([
            $me['id'],
            $media_type,
            $media_filename,
            $text_content ?: null,
            $bg_color
        ]);
        
        $success_msg = "Câu chuyện của bạn đã được đăng thành công!";
        echo "<script>
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 1500);
        </script>";
        
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}
?>

<div class="container" style="max-width: 600px; padding-top: 24px; padding-bottom: 80px;">
    
    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 18px;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 22px; color: var(--text-primary); margin: 0;">Tạo tin mới (Story)</h2>
    </div>
    
    <?php if (!empty($error_msg)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 14px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 14px;">
            <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <div class="glassmorphism-card" style="padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary);">
        
        <form action="create_story.php" method="POST" enctype="multipart/form-data" id="story-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action_create_story" value="1">
            <input type="hidden" name="media_type" id="story-media-type" value="image">
            <input type="hidden" name="bg_color" id="story-bg-color" value="linear-gradient(135deg, #8b5cf6, #ec4899)">
            
            <!-- Tab switches for story type -->
            <div style="display: flex; background: var(--bg-tertiary); padding: 4px; border-radius: 10px; border: 1px solid var(--border-color);">
                <button type="button" class="story-tab-btn active" data-type="image" style="flex: 1; padding: 10px; border: none; background: transparent; color: var(--text-secondary); font-weight: 700; font-size: 13px; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                    <i class="fa-regular fa-image" style="margin-right: 6px;"></i> Ảnh / Video
                </button>
                <button type="button" class="story-tab-btn" data-type="text" style="flex: 1; padding: 10px; border: none; background: transparent; color: var(--text-secondary); font-weight: 700; font-size: 13px; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                    <i class="fa-solid fa-font" style="margin-right: 6px;"></i> Văn bản
                </button>
            </div>
            
            <!-- IMAGE/VIDEO SECTION -->
            <div id="story-media-section" class="story-section-content">
                <div style="border: 2px dashed var(--border-color); padding: 30px; border-radius: var(--radius-sm); text-align: center; cursor: pointer; background: var(--bg-tertiary); position: relative;" id="story-upload-box">
                    <input type="file" name="story_file" id="story-file-input" accept="image/*,video/*" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 40px; color: var(--accent-primary); margin-bottom: 12px;"></i>
                    <p style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0 0 6px 0;">Chọn hình ảnh hoặc video ngắn</p>
                    <p style="font-size: 11.5px; color: var(--text-muted); margin: 0;">Chấp nhận file định dạng JPG, PNG, WEBP, MP4, WEBM (Tối đa 15MB)</p>
                    
                    <!-- Preview inside upload box -->
                    <div id="story-file-preview" style="display: none; margin-top: 15px; position: relative; border-radius: 8px; overflow: hidden; max-height: 250px; background: #000; align-items: center; justify-content: center;">
                        <!-- Img or Video tag will be injected here by JS -->
                    </div>
                </div>
            </div>
            
            <!-- TEXT SECTION -->
            <div id="story-text-section" class="story-section-content" style="display: none; flex-direction: column; gap: 16px;">
                <!-- Color palette selection -->
                <div>
                    <label class="form-label" style="font-size: 11.5px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; display: block;">Màu nền câu chuyện</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;" id="bg-color-picker">
                        <button type="button" class="color-dot active" data-color="linear-gradient(135deg, #8b5cf6, #ec4899)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #fff; cursor: pointer; background: linear-gradient(135deg, #8b5cf6, #ec4899); box-shadow: 0 0 4px rgba(0,0,0,0.15);"></button>
                        <button type="button" class="color-dot" data-color="linear-gradient(135deg, #3b82f6, #8b5cf6)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: linear-gradient(135deg, #3b82f6, #8b5cf6);"></button>
                        <button type="button" class="color-dot" data-color="linear-gradient(135deg, #06b6d4, #3b82f6)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: linear-gradient(135deg, #06b6d4, #3b82f6);"></button>
                        <button type="button" class="color-dot" data-color="linear-gradient(135deg, #10b981, #059669)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: linear-gradient(135deg, #10b981, #059669);"></button>
                        <button type="button" class="color-dot" data-color="linear-gradient(135deg, #f59e0b, #e11d48)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: linear-gradient(135deg, #f59e0b, #e11d48);"></button>
                        <button type="button" class="color-dot" data-color="linear-gradient(135deg, #ef4444, #f43f5e)" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: linear-gradient(135deg, #ef4444, #f43f5e);"></button>
                        <button type="button" class="color-dot" data-color="#18181b" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: #18181b;"></button>
                    </div>
                </div>
                
                <!-- Text Editor -->
                <div style="position: relative;">
                    <label for="story-text" class="form-label" style="font-size: 11.5px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; display: block;">Nội dung câu chuyện</label>
                    <div id="story-text-preview-container" style="border-radius: 12px; padding: 40px 20px; text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #ec4899); box-shadow: inset 0 0 100px rgba(0,0,0,0.2);">
                        <textarea name="text_content" id="story-text" placeholder="Nhập nội dung tin của bạn ở đây..." style="background: transparent; border: none; color: #ffffff; text-align: center; font-size: 20px; font-weight: 800; width: 100%; resize: none; outline: none; font-family: var(--font-heading); text-shadow: 0 2px 4px rgba(0,0,0,0.3); height: 120px;" maxlength="300"></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="background: var(--accent-gradient); border: none; color: #fff; font-weight: 800; font-size: 14.5px; border-radius: var(--radius-full); height: 44px; margin-top: 10px; box-shadow: 0 4px 12px var(--accent-glow); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa-solid fa-paper-plane"></i> Đăng câu chuyện
            </button>
        </form>
        
    </div>

</div>

<script>
(function() {
    const tabBtns = document.querySelectorAll('.story-tab-btn');
    const mediaSection = document.getElementById('story-media-section');
    const textSection = document.getElementById('story-text-section');
    const mediaTypeInput = document.getElementById('story-media-type');
    
    // Tab switching logic
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const type = btn.getAttribute('data-type');
            mediaTypeInput.value = type;
            
            if (type === 'image' || type === 'video') {
                mediaSection.style.display = 'block';
                textSection.style.display = 'none';
            } else {
                mediaSection.style.display = 'none';
                textSection.style.display = 'flex';
            }
        });
    });

    // File input preview logic
    const fileInput = document.getElementById('story-file-input');
    const filePreview = document.getElementById('story-file-preview');
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const fileType = file.type;
        const reader = new FileReader();
        
        filePreview.innerHTML = '';
        filePreview.style.display = 'flex';
        
        if (fileType.startsWith('image/')) {
            reader.onload = function(event) {
                const img = document.createElement('img');
                img.src = event.target.result;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '240px';
                img.style.objectFit = 'contain';
                filePreview.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else if (fileType.startsWith('video/')) {
            reader.onload = function(event) {
                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.controls = true;
                video.style.maxWidth = '100%';
                video.style.maxHeight = '240px';
                filePreview.appendChild(video);
            };
            reader.readAsDataURL(file);
        }
    });

    // Color picker logic for text story background
    const colorDots = document.querySelectorAll('.color-dot');
    const textPreviewContainer = document.getElementById('story-text-preview-container');
    const bgColorInput = document.getElementById('story-bg-color');
    
    colorDots.forEach(dot => {
        dot.addEventListener('click', function() {
            colorDots.forEach(d => {
                d.classList.remove('active');
                d.style.borderColor = 'transparent';
            });
            dot.classList.add('active');
            dot.style.borderColor = '#fff';
            
            const color = dot.getAttribute('data-color');
            textPreviewContainer.style.background = color;
            bgColorInput.value = color;
        });
    });
})();
</script>

<style>
/* CSS overrides for colors tabs */
.story-tab-btn {
    border: 1px solid transparent !important;
}
.story-tab-btn.active {
    background: var(--bg-secondary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
    box-shadow: var(--shadow-sm);
}
.color-dot.active {
    box-shadow: 0 0 10px var(--accent-glow) !important;
}
</style>

<?php 
require_once __DIR__ . '/includes/footer.php';
?>
