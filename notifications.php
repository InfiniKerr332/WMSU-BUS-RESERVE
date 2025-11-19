<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email_config.php';

/**
 * Create a notification in the database
 */
function create_notification($user_id, $type, $title, $message, $link = null) {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // If user_id is null, send to all admins
        if ($user_id === null) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($admins as $admin_id) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$admin_id, $type, $title, $message, $link]);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $title, $message, $link]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for a user
 */
function get_unread_notifications($user_id) {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 */
function get_unread_count($user_id) {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND is_read = 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark notification as read
 */
function mark_notification_read($notification_id) {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notification_id]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function mark_all_notifications_read($user_id) {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete old notifications (older than 3 days)
 */
function cleanup_old_notifications() {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Cleanup failed: " . $e->getMessage());
        return false;
    }
}

// ============================================
// SPECIFIC NOTIFICATION FUNCTIONS
// ============================================

/**
 * Notify admin of new reservation request
 */
function notify_new_reservation($reservation_id, $user_name, $reservation_date) {
    $title = "🔔 New Reservation Request";
    $message = "$user_name has requested a bus for " . format_date($reservation_date);
    $link = "admin/reservations.php?view=$reservation_id";
    
    create_notification(null, 'reservation', $title, $message, $link);
}

/**
 * Notify admin of new user registration
 */
function notify_new_registration($user_id, $user_name, $user_email) {
    $title = "👤 New User Registration";
    $message = "$user_name ($user_email) has registered and needs approval";
    $link = "admin/users.php?view=$user_id";
    
    create_notification(null, 'approval', $title, $message, $link);
}

/**
 * Notify admin of reservation cancellation
 */
function notify_reservation_cancelled($reservation_id, $user_name, $reservation_date) {
    $title = "❌ Reservation Cancelled";
    $message = "$user_name cancelled their reservation for " . format_date($reservation_date);
    $link = "admin/reservations.php?view=$reservation_id";
    
    create_notification(null, 'cancellation', $title, $message, $link);
}

/**
 * Notify user of account approval
 */
function notify_account_approved($user_id, $user_name, $user_email) {
    $title = "✅ Account Approved";
    $message = "Your account has been approved. You can now login and make reservations.";
    $link = "login.php";
    
    create_notification($user_id, 'approval', $title, $message, $link);
    
    // Send email
    $email_content = "
        <div class='success-box'>
            <h3>✅ Account Approved!</h3>
            <p>Dear $user_name,</p>
            <p>Great news! Your WMSU Bus Reserve System account has been approved by the administrator.</p>
            <p>You can now log in and start making bus reservations for your official trips.</p>
            <a href='" . SITE_URL . "login.php' class='button'>Login Now</a>
        </div>
    ";
    
    send_email($user_email, "Account Approved - WMSU Bus Reserve System", $email_content);
}

/**
 * Notify user of account rejection
 */
function notify_account_rejected($user_id, $user_name, $user_email, $reason) {
    $title = "❌ Account Rejected";
    $message = "Your account registration was rejected. Reason: $reason";
    $link = null;
    
    create_notification($user_id, 'rejection', $title, $message, $link);
    
    // Send email
    $email_content = "
        <div class='warning-box'>
            <h3>❌ Account Registration Rejected</h3>
            <p>Dear $user_name,</p>
            <p>Unfortunately, your account registration has been rejected by the administrator.</p>
            <p><strong>Reason:</strong> $reason</p>
            <p>If you believe this is an error, please contact the administrator at " . ADMIN_EMAIL . "</p>
        </div>
    ";
    
    send_email($user_email, "Account Rejected - WMSU Bus Reserve System", $email_content);
}

/**
 * Notify user of reservation approval
 */
function notify_reservation_approved($reservation) {
    $title = "✅ Reservation Approved";
    $message = "Your reservation for " . format_date($reservation['reservation_date']) . " has been approved";
    $link = "student/my_reservations.php?view=" . $reservation['id'];
    
    create_notification($reservation['user_id'], 'approval', $title, $message, $link);
    
    // Send email
    $return_info = '';
    if ($reservation['return_date']) {
        $return_info = "<p><strong>Return:</strong> " . format_date($reservation['return_date']) . " at " . format_time($reservation['return_time']) . "</p>";
    }
    
    $email_content = "
        <div class='success-box'>
            <h3>✅ Reservation Approved!</h3>
            <p>Dear " . htmlspecialchars($reservation['user_name']) . ",</p>
            <p>Your bus reservation has been approved!</p>
        </div>
        
        <div class='info-box'>
            <h4>📋 Reservation Details:</h4>
            <p><strong>Reservation ID:</strong> #" . $reservation['id'] . "</p>
            <p><strong>Departure:</strong> " . format_date($reservation['reservation_date']) . " at " . format_time($reservation['reservation_time']) . "</p>
            $return_info
            <p><strong>Destination:</strong> " . htmlspecialchars($reservation['destination']) . "</p>
            <p><strong>Bus:</strong> " . htmlspecialchars($reservation['bus_name']) . " (" . htmlspecialchars($reservation['plate_no']) . ")</p>
            <p><strong>Driver:</strong> " . htmlspecialchars($reservation['driver_name']) . "</p>
            <p><strong>Passengers:</strong> " . $reservation['passenger_count'] . "</p>
        </div>
        
        <a href='" . SITE_URL . "student/my_reservations.php' class='button'>View Reservation</a>
    ";
    
    send_email($reservation['email'], "Reservation Approved - WMSU Bus Reserve", $email_content);
}

/**
 * Notify user of reservation rejection
 */
function notify_reservation_rejected($reservation, $reason) {
    $title = "❌ Reservation Rejected";
    $message = "Your reservation for " . format_date($reservation['reservation_date']) . " was rejected";
    $link = "student/my_reservations.php?view=" . $reservation['id'];
    
    create_notification($reservation['user_id'], 'rejection', $title, $message, $link);
    
    // Send email
    $email_content = "
        <div class='warning-box'>
            <h3>❌ Reservation Rejected</h3>
            <p>Dear " . htmlspecialchars($reservation['user_name']) . ",</p>
            <p>Your bus reservation request has been rejected.</p>
            <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
        </div>
        
        <div class='info-box'>
            <h4>📋 Reservation Details:</h4>
            <p><strong>Departure:</strong> " . format_date($reservation['reservation_date']) . " at " . format_time($reservation['reservation_time']) . "</p>
            <p><strong>Destination:</strong> " . htmlspecialchars($reservation['destination']) . "</p>
            <p><strong>Purpose:</strong> " . htmlspecialchars($reservation['purpose']) . "</p>
        </div>
        
        <p>You can submit a new reservation request with different dates or contact the administrator for more information.</p>
        
        <a href='" . SITE_URL . "student/reserve.php' class='button'>Make New Reservation</a>
    ";
    
    send_email($reservation['email'], "Reservation Rejected - WMSU Bus Reserve", $email_content);
}

/**
 * Send 24-hour reminder for upcoming trips
 */
function send_trip_reminders() {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Get approved reservations happening in 24-48 hours that haven't been reminded
        $stmt = $conn->prepare("
            SELECT r.*, u.name as user_name, u.email, b.bus_name, b.plate_no, d.name as driver_name, d.contact_no as driver_contact
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN buses b ON r.bus_id = b.id
            LEFT JOIN drivers d ON r.driver_id = d.id
            WHERE r.status = 'approved'
            AND r.reminder_sent = 0
            AND r.reservation_date BETWEEN DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
        ");
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reservations as $res) {
            // Create notification
            $title = "⏰ Trip Reminder - Tomorrow!";
            $message = "Your trip to " . $res['destination'] . " is scheduled for tomorrow";
            $link = "student/my_reservations.php?view=" . $res['id'];
            
            create_notification($res['user_id'], 'reminder', $title, $message, $link);
            
            // Send email
            $return_info = '';
            if ($res['return_date']) {
                $return_info = "<p><strong>Return:</strong> " . format_date($res['return_date']) . " at " . format_time($res['return_time']) . "</p>";
            }
            
            $email_content = "
                <div class='info-box'>
                    <h3>⏰ Trip Reminder - Your trip is tomorrow!</h3>
                    <p>Dear " . htmlspecialchars($res['user_name']) . ",</p>
                    <p>This is a friendly reminder that your bus reservation is scheduled for <strong>tomorrow</strong>.</p>
                </div>
                
                <div class='info-box'>
                    <h4>🚌 Trip Details:</h4>
                    <p><strong>Date:</strong> " . format_date($res['reservation_date']) . "</p>
                    <p><strong>Departure Time:</strong> " . format_time($res['reservation_time']) . "</p>
                    $return_info
                    <p><strong>Pickup Location:</strong> 📍 WMSU Campus, Normal Road, Baliwasan</p>
                    <p><strong>Destination:</strong> " . htmlspecialchars($res['destination']) . "</p>
                    <p><strong>Bus:</strong> " . htmlspecialchars($res['bus_name']) . " (" . htmlspecialchars($res['plate_no']) . ")</p>
                    <p><strong>Driver:</strong> " . htmlspecialchars($res['driver_name']) . "</p>
                    <p><strong>Driver Contact:</strong> " . htmlspecialchars($res['driver_contact']) . "</p>
                </div>
                
                <div class='warning-box'>
                    <p><strong>⚠️ Important Reminders:</strong></p>
                    <ul>
                        <li>Please arrive 15 minutes before departure time</li>
                        <li>Bring valid ID for verification</li>
                        <li>Contact the driver if you need any assistance</li>
                    </ul>
                </div>
            ";
            
            send_email($res['email'], "Trip Reminder - Tomorrow at " . format_time($res['reservation_time']), $email_content);
            
            // Mark as reminded
            $stmt = $conn->prepare("UPDATE reservations SET reminder_sent = 1 WHERE id = ?");
            $stmt->execute([$res['id']]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to send reminders: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and auto-reject overdue pending reservations
 */
function auto_reject_overdue_reservations() {
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Get pending reservations where reservation time is less than 72 hours away
        $stmt = $conn->prepare("
            SELECT r.*, u.name as user_name, u.email
            FROM reservations r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.status = 'pending'
            AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(r.reservation_date, ' ', r.reservation_time)) < 72
        ");
        $stmt->execute();
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($overdue as $res) {
            // Auto-reject
            $reject_reason = "Automatically rejected: Not approved within 72-hour advance booking requirement.";
            $stmt = $conn->prepare("UPDATE reservations SET status = 'rejected', admin_remarks = ? WHERE id = ?");
            $stmt->execute([$reject_reason, $res['id']]);
            
            // Notify user
            notify_reservation_rejected($res, $reject_reason);
            
            // Notify admin
            $title = "⚠️ Auto-Rejected Reservation";
            $message = "Reservation #" . $res['id'] . " for " . $res['user_name'] . " was auto-rejected (missed 72-hour deadline)";
            $link = "admin/reservations.php?view=" . $res['id'];
            create_notification(null, 'rejection', $title, $message, $link);
        }
        
        return count($overdue);
    } catch (Exception $e) {
        error_log("Auto-reject failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Notify driver of trip assignment
 */
function notify_driver_assignment($reservation, $driver_email, $driver_name) {
    if (!$driver_email) return;
    
    $return_info = '';
    if ($reservation['return_date']) {
        $return_info = "<p><strong>Return:</strong> " . format_date($reservation['return_date']) . " at " . format_time($reservation['return_time']) . "</p>";
    }
    
    $email_content = "
        <div class='info-box'>
            <h3>🚗 New Trip Assignment</h3>
            <p>Dear $driver_name,</p>
            <p>You have been assigned to a new bus trip.</p>
        </div>
        
        <div class='info-box'>
            <h4>📋 Trip Details:</h4>
            <p><strong>Date:</strong> " . format_date($reservation['reservation_date']) . "</p>
            <p><strong>Departure Time:</strong> " . format_time($reservation['reservation_time']) . "</p>
            $return_info
            <p><strong>Pickup Location:</strong> 📍 WMSU Campus, Normal Road, Baliwasan</p>
            <p><strong>Destination:</strong> " . htmlspecialchars($reservation['destination']) . "</p>
            <p><strong>Purpose:</strong> " . htmlspecialchars($reservation['purpose']) . "</p>
            <p><strong>Passengers:</strong> " . $reservation['passenger_count'] . "</p>
            <p><strong>Requester:</strong> " . htmlspecialchars($reservation['user_name']) . "</p>
            <p><strong>Contact:</strong> " . htmlspecialchars($reservation['contact_no']) . "</p>
        </div>
        
        <div class='warning-box'>
            <p><strong>⚠️ Important:</strong></p>
            <ul>
                <li>Please confirm vehicle readiness before departure</li>
                <li>Arrive 30 minutes before scheduled departure</li>
                <li>Contact the trip coordinator if you have any concerns</li>
            </ul>
        </div>
    ";
    
    send_email($driver_email, "New Trip Assignment - " . format_date($reservation['reservation_date']), $email_content);
}
?>