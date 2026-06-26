<?php
require_once 'config.php';
require_once 'includes/db.php';
$db = getDB();
$stories = $db->query("SELECT * FROM stories")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($stories);
