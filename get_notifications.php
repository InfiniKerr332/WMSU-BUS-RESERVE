<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/session.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Auto-mark as read when fetched
if (isset($_GET['mark_read']) && $_GET['mark_read'] == '1') {
    mark_all_notifications_read($user_id);
}

$notifications = get_unread_notifications($user_id);
$count = get_unread_count($user_id);

echo json_encode([
    'success' => true,
    'count' => $count,
    'notifications' => $notifications
]);
?>