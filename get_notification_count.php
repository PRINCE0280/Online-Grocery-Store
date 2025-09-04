<?php
require_once 'config.php';
require_once 'notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in', 'count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];
$count = getUnreadNotificationCount($user_id);

echo json_encode(['count' => $count]);
?>
