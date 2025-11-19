<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/session.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false]);
    exit;
}

if (isset($_GET['id'])) {
    $notification_id = (int)$_GET['id'];
    $result = mark_notification_read($notification_id);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false]);
}
?>