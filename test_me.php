<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
$me = getLoggedInUser();
header('Content-Type: application/json');
echo json_encode($me);
