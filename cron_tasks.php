<?php
$secret_key = 'WMSU2025';
if (!isset($_GET['secret']) || $_GET['secret'] !== $secret_key) {
    die('Unauthorized');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notifications.php';

echo "<h2>WMSU Cron Tasks</h2>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

echo "<p>Cleaning up old notifications...</p>";
cleanup_old_notifications();
echo "<p style='color:green;'>✓ Done</p>";
?>