<?php
$content = file_get_contents('d:/frest/profile.php');
echo "Length: " . strlen($content) . "\n";

// Tìm các dòng chứa button hoặc openCreateGroupModal hoặc modal hoặc chat
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (stripos($line, 'openCreateGroupModal') !== false || stripos($line, 'chat') !== false || stripos($line, 'button') !== false) {
        echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
    }
}
