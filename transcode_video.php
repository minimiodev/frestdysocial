<?php
/**
 * Video Transcoder - Frest App
 * Chạy nền (exec background) để transcode video sang nhiều quality variants
 * Sử dụng FFmpeg
 * 
 * Usage: php transcode_video.php <video_filename>
 * hoặc được gọi nội bộ từ index.php / profile.php khi upload video
 */

// Ngăn chạy trực tiếp qua browser nếu không có token
if (php_sapi_name() !== 'cli') {
    $secret = 'frest_transcode_' . date('Y-m-d');
    if (($_GET['token'] ?? '') !== md5($secret)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/includes/header.php';

// ─── Config ──────────────────────────────────────────────────────────────────
$UPLOAD_PATH  = UPLOAD_DIR . 'posts/';
$FFMPEG       = 'ffmpeg'; // hoặc đường dẫn đầy đủ: 'C:/ffmpeg/bin/ffmpeg.exe'
$LOG_FILE     = __DIR__ . '/sessions/transcode.log';

// Chất lượng variants cần tạo: [height, crf, label]
// crf: 18=lossless, 23=default, 28=low quality
$QUALITY_VARIANTS = [
    ['height' => 360,  'crf' => 28, 'bitrate' => '500k',  'label' => '360p'],
    ['height' => 480,  'crf' => 26, 'bitrate' => '800k',  'label' => '480p'],
    ['height' => 720,  'crf' => 23, 'bitrate' => '2500k', 'label' => '720p'],
    ['height' => 1080, 'crf' => 21, 'bitrate' => '5000k', 'label' => '1080p'],
    ['height' => 1440, 'crf' => 20, 'bitrate' => '8000k', 'label' => '1440p'],
    ['height' => 2160, 'crf' => 18, 'bitrate' => '15000k','label' => '2160p'],
];

function transcodeLog($msg) {
    global $LOG_FILE;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function transcodeVideo($filename) {
    global $UPLOAD_PATH, $FFMPEG, $QUALITY_VARIANTS;

    $input = $UPLOAD_PATH . $filename;
    if (!file_exists($input)) {
        transcodeLog("ERROR: File không tồn tại: $input");
        return false;
    }

    // Lấy thông tin video gốc bằng ffprobe
    $ffprobe_cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 " . escapeshellarg($input) . " 2>&1";
    $probe_out = shell_exec($ffprobe_cmd);
    if (empty($probe_out)) {
        transcodeLog("ERROR: Không thể đọc thông tin video: $filename");
        return false;
    }

    [$orig_width, $orig_height] = array_map('intval', explode(',', trim($probe_out)));
    transcodeLog("START: $filename ({$orig_width}x{$orig_height})");

    $base = $UPLOAD_PATH . pathinfo($filename, PATHINFO_FILENAME);

    foreach ($QUALITY_VARIANTS as $variant) {
        $target_h = $variant['height'];
        $label    = $variant['label'];
        $crf      = $variant['crf'];
        $bitrate  = $variant['bitrate'];
        $out_file = "{$base}_{$label}.mp4";

        // Bỏ qua nếu file đã tồn tại
        if (file_exists($out_file)) {
            transcodeLog("SKIP (exists): $label");
            continue;
        }

        // Bỏ qua nếu video gốc thấp hơn target resolution
        if ($orig_height < $target_h * 0.7) {
            transcodeLog("SKIP (too low res): $label (orig: {$orig_height}p)");
            continue;
        }

        // Giữ nguyên aspect ratio, scale về height = target_h (chẵn)
        // -vf scale=-2:{target_h} : scale giữ tỉ lệ, width chia hết cho 2
        $scale = "scale=-2:{$target_h}";

        $cmd = implode(' ', [
            $FFMPEG,
            '-i',    escapeshellarg($input),
            '-vf',   escapeshellarg($scale),
            '-c:v',  'libx264',
            '-crf',  $crf,
            '-maxrate', $bitrate,
            '-bufsize',  (intval($bitrate) * 2) . 'k',
            '-preset', 'fast',
            '-c:a',  'aac',
            '-b:a',  '128k',
            '-movflags', '+faststart', // web streaming optimization
            '-y',                       // overwrite
            escapeshellarg($out_file),
            '2>&1'
        ]);

        transcodeLog("TRANSCODE [{$label}]: $filename → " . basename($out_file));
        $output = shell_exec($cmd);

        if (file_exists($out_file) && filesize($out_file) > 1024) {
            transcodeLog("OK [{$label}]: " . round(filesize($out_file) / 1024 / 1024, 2) . " MB");
        } else {
            transcodeLog("FAILED [{$label}]: " . substr($output ?? '', -200));
            // Xóa file lỗi nếu có
            if (file_exists($out_file)) unlink($out_file);
        }
    }

    transcodeLog("DONE: $filename");
    return true;
}

// ─── Entrypoint ──────────────────────────────────────────────────────────────

if (php_sapi_name() === 'cli') {
    // Chạy từ CLI: php transcode_video.php <filename>
    $filename = $argv[1] ?? null;
    if (!$filename) {
        echo "Usage: php transcode_video.php <video_filename>\n";
        exit(1);
    }
    transcodeVideo($filename);
} else {
    // Chạy qua HTTP với token
    $filename = $_GET['file'] ?? null;
    if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+\.mp4$/', $filename)) {
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }
    header('Content-Type: application/json');
    $result = transcodeVideo($filename);
    echo json_encode(['success' => $result, 'file' => $filename]);
}
