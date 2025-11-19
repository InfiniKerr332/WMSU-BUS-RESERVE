<?php
// File: admin/users.php - COMPLETE FIXED VERSION
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

require_admin();

$user = get_logged_user();
$success = '';
$errors = [];

$db = new Database();
$conn = $db->connect();

// Handle user approval
if (isset($_POST['approve_account'])) {
    $user_id = clean_input($_POST['user_id']);
    
    $check_stmt = $conn->prepare("SELECT account_status FROM users WHERE id = :user_id");
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    $check_user = $check_stmt->fetch();
    
    if (!$check_user) {
        $errors[] = 'User not found';
    } elseif ($check_user['account_status'] != 'pending') {
        $errors[] = 'This account has already been processed (Status: ' . ucfirst($check_user['account_status']) . ')';
    } else {
        $stmt = $conn->prepare("UPDATE users SET account_status = 'approved', approved_by_admin = :admin_id, approved_at = NOW() WHERE id = :user_id AND account_status = 'pending'");
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $approved_user = $stmt->fetch();
            
            $email_message = "
                <div style='background: #d4edda; padding: 30px; border-left: 4px solid #28a745; border-radius: 6px;'>
                    <h2 style='color: #155724; margin-bottom: 20px;'>Account Approved</h2>
                    <p style='color: #155724; font-size: 16px;'>Dear " . htmlspecialchars($approved_user['name']) . ",</p>
                    <p style='color: #155724; font-size: 16px;'>Your account has been approved! You can now login to the WMSU Bus Reserve System.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . SITE_URL . "login.php' style='display: inline-block; padding: 15px 40px; background: #800000; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;'>Login Now</a>
                    </div>
                </div>
            ";
            
            send_email($approved_user['email'], 'Account Approved - WMSU Bus Reserve System', $email_message);
            
            $success = 'Account approved successfully!';
        } else {
            $errors[] = 'Failed to approve account';
        }
    }
}

// Handle user rejection
if (isset($_POST['reject_account'])) {
    $user_id = clean_input($_POST['user_id']);
    $rejection_reason = clean_input($_POST['rejection_reason']);
    
    $check_stmt = $conn->prepare("SELECT account_status FROM users WHERE id = :user_id");
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    $check_user = $check_stmt->fetch();
    
    if (!$check_user) {
        $errors[] = 'User not found';
    } elseif ($check_user['account_status'] != 'pending') {
        $errors[] = 'This account has already been processed (Status: ' . ucfirst($check_user['account_status']) . ')';
    } elseif (empty($rejection_reason)) {
        $errors[] = 'Rejection reason is required';
    } else {
        $stmt = $conn->prepare("UPDATE users SET account_status = 'rejected', rejection_reason = :reason, approved_by_admin = :admin_id, approved_at = NOW() WHERE id = :user_id AND account_status = 'pending'");
        $stmt->bindParam(':reason', $rejection_reason);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $rejected_user = $stmt->fetch();
            
            $email_message = "
                <div style='background: #f8d7da; padding: 30px; border-left: 4px solid #dc3545; border-radius: 6px;'>
                    <h2 style='color: #721c24; margin-bottom: 20px;'>Account Registration Rejected</h2>
                    <p style='color: #721c24; font-size: 16px;'>Dear " . htmlspecialchars($rejected_user['name']) . ",</p>
                    <p style='color: #721c24; font-size: 16px;'>Unfortunately, your account registration has been rejected.</p>
                    <div style='background: white; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <strong style='color: #721c24;'>Reason:</strong>
                        <p style='color: #333; margin-top: 10px;'>" . htmlspecialchars($rejection_reason) . "</p>
                    </div>
                </div>
            ";
            
            send_email($rejected_user['email'], 'Account Rejected - WMSU Bus Reserve System', $email_message);
            
            $success = 'Account rejected successfully!';
        } else {
            $errors[] = 'Failed to reject account';
        }
    }
}

// FIXED: Handle individual reservation deletion
if (isset($_POST['delete_single_reservation'])) {
    $reservation_id = clean_input($_POST['reservation_id']);
    $deletion_reason = clean_input($_POST['deletion_reason']);
    
    if (empty($deletion_reason)) {
        $errors[] = 'Deletion reason is required';
    } else {
        try {
            // Get reservation details
            $stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, b.bus_name, d.name as driver_name 
                                   FROM reservations r 
                                   LEFT JOIN users u ON r.user_id = u.id
                                   LEFT JOIN buses b ON r.bus_id = b.id
                                   LEFT JOIN drivers d ON r.driver_id = d.id
                                   WHERE r.id = :reservation_id");
            $stmt->bindParam(':reservation_id', $reservation_id);
            $stmt->execute();
            $reservation = $stmt->fetch();
            
            if ($reservation) {
                // Delete the reservation
                $stmt = $conn->prepare("DELETE FROM reservations WHERE id = :reservation_id");
                $stmt->bindParam(':reservation_id', $reservation_id);
                
                if ($stmt->execute()) {
                    $success = 'Reservation #' . $reservation_id . ' deleted successfully. Bus and driver are now available.';
                    
                    // Notify user
                    $email_message = "
                        <div style='background: #fff3cd; padding: 30px; border-left: 4px solid #ffc107; border-radius: 6px;'>
                            <h2 style='color: #856404; margin-bottom: 20px;'>Reservation Deleted by Admin</h2>
                            <p style='color: #856404; font-size: 16px;'>Dear " . htmlspecialchars($reservation['user_name']) . ",</p>
                            <p style='color: #856404; font-size: 16px;'>Your reservation has been deleted by the administrator.</p>
                            <div style='background: white; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                                <p><strong>Reservation ID:</strong> #" . $reservation_id . "</p>
                                <p><strong>Date:</strong> " . format_date($reservation['reservation_date']) . "</p>
                                <p><strong>Destination:</strong> " . htmlspecialchars($reservation['destination']) . "</p>
                                <p><strong>Bus:</strong> " . htmlspecialchars($reservation['bus_name']) . "</p>
                                <p><strong>Driver:</strong> " . htmlspecialchars($reservation['driver_name']) . "</p>
                            </div>
                            <div style='background: white; padding: 15px; border-radius: 6px;'>
                                <strong style='color: #856404;'>Reason for Deletion:</strong>
                                <p style='color: #333; margin-top: 10px;'>" . htmlspecialchars($deletion_reason) . "</p>
                            </div>
                        </div>
                    ";
                    
                    send_email($reservation['email'], 'Reservation Deleted - WMSU Bus Reserve', $email_message);
                } else {
                    $errors[] = 'Failed to delete reservation';
                }
            } else {
                $errors[] = 'Reservation not found';
            }
        } catch (Exception $e) {
            $errors[] = 'Error deleting reservation: ' . $e->getMessage();
        }
    }
}

// FIXED: Handle user deletion with ALL reservations
if (isset($_POST['delete_user_confirm'])) {
    $user_id = clean_input($_POST['user_id']);
    $deletion_reason = clean_input($_POST['deletion_reason']);
    
    if ($user_id == $_SESSION['user_id']) {
        $errors[] = 'You cannot delete your own account';
    } elseif (empty($deletion_reason)) {
        $errors[] = 'Deletion reason is required';
    } else {
        try {
            $conn->beginTransaction();
            
            // Get user info
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $deleted_user = $stmt->fetch();
            
            if (!$deleted_user) {
                throw new Exception('User not found');
            }
            
            // Count reservations
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $reservation_count = $stmt->fetch()['count'];
            
            // Delete all user's reservations
            $stmt = $conn->prepare("DELETE FROM reservations WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $conn->commit();
            
            $success = "User account and {$reservation_count} reservation(s) deleted successfully. All buses and drivers are now available.";
            
            // Send notification email to user
            $email_message = "
                <div style='background: #fff3cd; padding: 30px; border-left: 4px solid #ffc107; border-radius: 6px;'>
                    <h2 style='color: #856404; margin-bottom: 20px;'>Account Deleted</h2>
                    <p style='color: #856404; font-size: 16px;'>Dear " . htmlspecialchars($deleted_user['name']) . ",</p>
                    <p style='color: #856404; font-size: 16px;'>Your account and all reservations have been deleted from the WMSU Bus Reserve System.</p>
                    <div style='background: white; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <strong style='color: #856404;'>Reason:</strong>
                        <p style='color: #333; margin-top: 10px;'>" . htmlspecialchars($deletion_reason) . "</p>
                    </div>
                    <p style='color: #856404; font-size: 14px;'>Total reservations deleted: {$reservation_count}</p>
                </div>
            ";
            
            send_email($deleted_user['email'], 'Account Deleted - WMSU Bus Reserve System', $email_message);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Failed to delete user: ' . $e->getMessage();
        }
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'pending';

if ($status_filter == 'all') {
    $where = "role != 'admin'";
} else {
    $where = "account_status = :status AND role != 'admin'";
}

$stmt = $conn->prepare("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC");
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->execute();
$users = $stmt->fetchAll();

// Get status counts
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN account_status = 'pending' AND role != 'admin' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN account_status = 'approved' AND role != 'admin' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN account_status = 'rejected' AND role != 'admin' THEN 1 ELSE 0 END) as rejected_count
    FROM users");
$stmt->execute();
$counts = $stmt->fetch();

// FIXED: Get view user and their reservations
$view_user = null;
$user_reservations = [];
if (isset($_GET['view'])) {
    $view_id = clean_input($_GET['view']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $view_id);
    $stmt->execute();
    $view_user = $stmt->fetch();
    
    if ($view_user) {
        $stmt = $conn->prepare("SELECT r.*, b.bus_name, b.plate_no, d.name as driver_name 
                                FROM reservations r 
                                LEFT JOIN buses b ON r.bus_id = b.id 
                                LEFT JOIN drivers d ON r.driver_id = d.id 
                                WHERE r.user_id = :user_id 
                                ORDER BY r.reservation_date DESC");
        $stmt->bindParam(':user_id', $view_id);
        $stmt->execute();
        $user_reservations = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 12px 24px;
            background: white;
            border: 2px solid #800000;
            color: #800000;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-tab.active {
            background: #800000;
            color: white;
        }
        
        .filter-tab:hover {
            background: #600000;
            color: white;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-content {
            background-color: white;
            margin: 30px auto;
            padding: 0;
            border-radius: 12px;
            max-width: 900px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: #800000;
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }
        
        .close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .detail-section {
            margin-bottom: 30px;
        }
        
        .detail-section h3 {
            color: #800000;
            margin-bottom: 20px;
            font-size: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 15px;
            margin-bottom: 10px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-grid:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            color: #333;
        }
        
        .reservation-list {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 0;
            margin-top: 20px;
        }
        
        .reservation-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .reservation-item:hover {
            background: #f0f8ff;
        }
        
        .reservation-item:last-child {
            border-bottom: none;
            border-radius: 0 0 8px 8px;
        }
        
        .reservation-info {
            flex: 1;
        }
        
        .reservation-date {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .reservation-details {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        
        .reservation-actions {
            margin-left: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #800000;
            color: white;
        }
        
        .btn-primary:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-block {
            width: 100%;
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .warning-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 6px;
            color: #721c24;
            margin: 20px 0;
        }
        
        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 6px;
            color: #0c5460;
            margin: 20px 0;
        }
        
        .id-images-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .id-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            background: #fafafa;
        }
        
        .id-card-header {
            background: #800000;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .id-card-body {
            padding: 15px;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .id-card-body img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .no-image {
            color: #999;
            text-align: center;
            font-style: italic;
        }
        
        .delete-reservation-form {
            display: none;
            margin-top: 10px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 6px;
        }
        
        .delete-reservation-form.show {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo">
                <h1><?php echo SITE_NAME; ?> - Admin</h1>
            </div>
            <div class="user-info">
                <span class="user-name">Admin: <?php echo htmlspecialchars($user['name']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="buses.php">Buses</a></li>
            <li><a href="drivers.php">Drivers</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="users.php" class="active">Users</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Manage Users & Approvals</h2>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <div class="filter-tabs">
                <a href="users.php?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $counts['pending_count']; ?>)
                </a>
                <a href="users.php?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $counts['approved_count']; ?>)
                </a>
                <a href="users.php?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $counts['rejected_count']; ?>)
                </a>
                <a href="users.php?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    All Users
                </a>
            </div>
            
            <?php if (count($users) > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['department'] ?: 'N/A'); ?></td>
                            <td><?php echo ucfirst($u['role']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $u['account_status']; ?>">
                                    <?php echo ucfirst($u['account_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <a href="users.php?view=<?php echo $u['id']; ?>&status=<?php echo $status_filter; ?>" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">
                                    <?php echo $u['account_status'] == 'pending' ? 'Review' : 'View'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No users found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View/Review Modal -->
    <?php if ($view_user): ?>
    <div class="modal show" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo $view_user['account_status'] == 'pending' ? 'Review Account Registration' : 'User Details'; ?></h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- User Information Section -->
                <div class="detail-section">
                    <h3>User Information</h3>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Full Name:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($view_user['name']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($view_user['email']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Email Verified:</div>
                        <div class="detail-value">
                            <?php if ($view_user['email_verified']): ?>
                                <span style="color: #28a745; font-weight: 600;">Yes</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: 600;">No</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Contact Number:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($view_user['contact_no']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Department:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($view_user['department']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Role:</div>
                        <div class="detail-value"><?php echo ucfirst($view_user['role']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Account Status:</div>
                        <div class="detail-value">
                            <span class="badge badge-<?php echo $view_user['account_status']; ?>">
                                <?php echo ucfirst($view_user['account_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Registered On:</div>
                        <div class="detail-value"><?php echo date('F d, Y g:i A', strtotime($view_user['created_at'])); ?></div>
                    </div>
                </div>

                <!-- ID Verification Section -->
                <div class="detail-section">
                    <h3>Employee/Teacher ID Verification</h3>
                    
                    <?php if (!$view_user['employee_id_image'] && !$view_user['employee_id_back_image']): ?>
                        <div class="warning-box">
                            <strong>No ID images uploaded.</strong> Cannot approve without verification.
                        </div>
                    <?php else: ?>
                        <div class="id-images-grid">
                            <div class="id-card">
                                <div class="id-card-header">Front Side</div>
                                <div class="id-card-body">
                                    <?php if ($view_user['employee_id_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($view_user['employee_id_image']); ?>" 
                                             alt="Employee ID Front"
                                             onclick="window.open('../<?php echo htmlspecialchars($view_user['employee_id_image']); ?>', '_blank')">
                                    <?php else: ?>
                                        <div class="no-image">No image uploaded</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="id-card">
                                <div class="id-card-header">Back Side</div>
                                <div class="id-card-body">
                                    <?php if ($view_user['employee_id_back_image']): ?>
                                        <img src="../<?php echo htmlspecialchars($view_user['employee_id_back_image']); ?>" 
                                             alt="Employee ID Back"
                                             onclick="window.open('../<?php echo htmlspecialchars($view_user['employee_id_back_image']); ?>', '_blank')">
                                    <?php else: ?>
                                        <div class="no-image">No image uploaded</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Reservations Section -->
                <?php if (count($user_reservations) > 0): ?>
                <div class="detail-section">
                    <h3>User Reservations (<?php echo count($user_reservations); ?>)</h3>
                    
                    <div class="info-box">
                        <strong>Manage Reservations:</strong> You can delete individual reservations below, or delete the entire user account (which will delete all reservations).
                    </div>
                    
                    <div class="reservation-list">
                        <?php foreach ($user_reservations as $res): ?>
                        <div class="reservation-item">
                            <div class="reservation-info">
                                <div class="reservation-date">
                                    Reservation #<?php echo $res['id']; ?> - <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($res['reservation_time'])); ?>
                                </div>
                                <div class="reservation-details">
                                    <strong>Status:</strong> <?php echo get_status_badge($res['status']); ?><br>
                                    <strong>Destination:</strong> <?php echo htmlspecialchars($res['destination']); ?><br>
                                    <strong>Bus:</strong> <?php echo htmlspecialchars($res['bus_name'] ?: 'Not assigned'); ?> | 
                                    <strong>Driver:</strong> <?php echo htmlspecialchars($res['driver_name'] ?: 'Not assigned'); ?><br>
                                    <strong>Purpose:</strong> <?php echo htmlspecialchars(substr($res['purpose'], 0, 60)) . '...'; ?>
                                </div>
                            </div>
                            <div class="reservation-actions">
                                <button type="button" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;" onclick="showDeleteReservationForm(<?php echo $res['id']; ?>)">
                                    Delete
                                </button>
                                <div id="deleteResForm<?php echo $res['id']; ?>" class="delete-reservation-form">
                                    <form method="POST" onsubmit="return confirm('Delete this reservation? Bus and driver will become available.');">
                                        <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <label style="font-size: 13px;">Reason for deletion:</label>
                                            <textarea name="deletion_reason" class="form-control" rows="2" style="font-size: 12px;" required></textarea>
                                        </div>
                                        <div style="display: flex; gap: 5px;">
                                            <button type="submit" name="delete_single_reservation" class="btn btn-danger" style="font-size: 11px; padding: 5px 10px;">Confirm Delete</button>
                                            <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;" onclick="hideDeleteReservationForm(<?php echo $res['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Actions Section -->
                <div class="detail-section">
                    <h3>Account Actions</h3>
                    
                    <?php if ($view_user['account_status'] == 'pending'): ?>
                        <?php if (!$view_user['employee_id_image'] || !$view_user['employee_id_back_image']): ?>
                            <div class="info-box">
                                Cannot approve this account - both front and back ID images are required.
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-secondary btn-block" onclick="closeModal()">Close</button>
                            </div>
                        <?php elseif (!$view_user['email_verified']): ?>
                            <div class="warning-box">
                                <strong>Email not verified!</strong> User must verify their email before account can be approved.
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-secondary btn-block" onclick="closeModal()">Close</button>
                            </div>
                        <?php else: ?>
                            <div class="info-box">
                                Review the ID images above and approve or reject this account.
                            </div>
                            
                            <div class="action-buttons">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                                    <button type="submit" name="approve_account" class="btn btn-success btn-block" 
                                            onclick="return confirm('Approve this account? The user will be able to login.');">
                                        Approve Account
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-danger btn-block" onclick="showRejectForm();">
                                    Reject Account
                                </button>
                            </div>
                            
                            <div id="rejectForm" style="display: none; margin-top: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                                    <div class="form-group">
                                        <label for="rejection_reason">Rejection Reason:</label>
                                        <textarea name="rejection_reason" class="form-control" rows="3" 
                                                  placeholder="Provide a clear reason for rejection..." required></textarea>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="reject_account" class="btn btn-danger" style="flex: 1;"
                                                onclick="return confirm('Reject this account? The user will be notified via email.');">
                                            Confirm Rejection
                                        </button>
                                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="hideRejectForm();">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($view_user['account_status'] == 'rejected'): ?>
                        <div class="warning-box">
                            This account has been rejected.
                            <?php if ($view_user['rejection_reason']): ?>
                                <br><strong>Reason:</strong> <?php echo htmlspecialchars($view_user['rejection_reason']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" class="btn btn-danger btn-block" onclick="showDeleteForm();">
                                Delete User & All Reservations
                            </button>
                            <button type="button" class="btn btn-secondary btn-block" onclick="closeModal();">
                                Close
                            </button>
                        </div>
                    
                    <?php else: ?>
                        <!-- Approved users -->
                        <div class="action-buttons">
                            <button type="button" class="btn btn-danger btn-block" onclick="showDeleteForm();">
                                Delete User & All Reservations
                            </button>
                            <button type="button" class="btn btn-secondary btn-block" onclick="closeModal();">
                                Close
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Delete Form Section -->
                <div id="deleteForm" style="display: none; margin-top: 30px; border-top: 2px solid #e0e0e0; padding-top: 30px;">
                    <div class="warning-box">
                        <strong>Warning:</strong> Deleting this user will permanently remove their account and ALL <?php echo count($user_reservations); ?> reservation(s). All buses and drivers will become available. This action cannot be undone.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                        
                        <div class="form-group">
                            <label for="deletion_reason">Reason for Deletion:</label>
                            <textarea name="deletion_reason" class="form-control" rows="4" 
                                      placeholder="Provide a reason for deleting this account..." required></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="delete_user_confirm" class="btn btn-danger" style="flex: 1;"
                                    onclick="return confirm('FINAL WARNING: Delete this user and ALL <?php echo count($user_reservations); ?> reservations? This cannot be undone!');">
                                Confirm Permanent Deletion
                            </button>
                            <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="hideDeleteForm();">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
    <script>
        function closeModal() {
            window.location.href = 'users.php?status=<?php echo $status_filter; ?>';
        }
        
        function showRejectForm() {
            document.getElementById('rejectForm').style.display = 'block';
        }
        
        function hideRejectForm() {
            document.getElementById('rejectForm').style.display = 'none';
        }
        
        function showDeleteForm() {
            document.getElementById('deleteForm').style.display = 'block';
            document.querySelector('.action-buttons').style.display = 'none';
        }
        
        function hideDeleteForm() {
            document.getElementById('deleteForm').style.display = 'none';
            document.querySelector('.action-buttons').style.display = 'flex';
        }
        
        function showDeleteReservationForm(reservationId) {
            document.getElementById('deleteResForm' + reservationId).classList.add('show');
        }
        
        function hideDeleteReservationForm(reservationId) {
            document.getElementById('deleteResForm' + reservationId).classList.remove('show');
        }
    </script>
</body>
</html>