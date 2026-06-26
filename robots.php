<?php
/**
 * Dynamic robots.txt Generator - Frest App
 */
require_once __DIR__ . '/config.php';

// Thiết lập định dạng trả về là Plain Text
header("Content-Type: text/plain; charset=utf-8");
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /sessions/
Disallow: /uploads/original/
Disallow: /block_action
Disallow: /bookmark_action
Disallow: /delete_account
Disallow: /delete_conversation
Disallow: /delete_group
Disallow: /delete_page
Disallow: /delete_post
Disallow: /delete_story
Disallow: /follow
Disallow: /leave_group
Disallow: /logout
Disallow: /react
Disallow: /react_message
Disallow: /react_story
Disallow: /recall_message
Disallow: /report_action
Disallow: /repost
Disallow: /search_mention
Disallow: /search_suggest
Disallow: /send_message
Disallow: /submit_complaint
Disallow: /vote_poll_action

Sitemap: <?php echo SITE_URL; ?>/sitemap.xml
