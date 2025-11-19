<?php
// Save as: verify_email.php (FIXED VERSION)
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// CRITICAL FIX: Disable all debug output
error_reporting(0);
ini_set('display_errors', 0);

$success = false;
$error = '';
$user_name = '';
$user_email = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = clean_input($_GET['token']);
    
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'Invalid or expired verification link.';
    } elseif ($user['email_verified'] == 1) {
        $error = 'Email already verified. Please wait for admin approval.';
        $success = true;
        $user_name = $user['name'];
    } elseif (strtotime($user['verification_expires']) < time()) {
        $error = 'Verification link expired. Please request a new one.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW(), verification_token = NULL WHERE id = :id");
        $stmt->bindParam(':id', $user['id']);
        
        if ($stmt->execute()) {
            $success = true;
            $user_name = $user['name'];
            $user_email = $user['email'];
            
            notify_new_registration($user['id'], $user['name'], $user['email']);
            
            $admin_email_message = "
                <div style='background: #fff; padding: 30px; max-width: 600px;'>
                    <h3 style='color: #800000; margin-bottom: 20px;'>New User Pending Approval</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600; width: 150px;'>Name:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($user['name']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;'>Email:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($user['email']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;'>Role:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd;'>" . ucfirst($user['role']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;'>Department:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($user['department']) . "</td>
                        </tr>
                    </table>
                    <div style='margin-top: 30px; text-align: center;'>
                        <a href='" . SITE_URL . "admin/users.php?view=" . $user['id'] . "' style='display: inline-block; padding: 15px 40px; background: #800000; color: #fff; text-decoration: none; border-radius: 5px; font-weight: 600;'>Review & Approve Account</a>
                    </div>
                </div>
            ";
            
            send_email(ADMIN_EMAIL, 'New User Pending Approval - WMSU Bus Reserve', $admin_email_message);
        } else {
            $error = 'Failed to verify email. Please try again.';
        }
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .top-nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .top-nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .nav-logo img {
            height: 50px;
            width: 50px;
        }
        
        .nav-logo-text {
            color: #800000;
            font-size: 20px;
            font-weight: 700;
        }
        
        .nav-back {
            color: #800000;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border: 2px solid #800000;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-back:hover {
            background: #800000;
            color: white;
        }
        
        .verification-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .verification-card {
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .status-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
        }
        
        .status-icon.success {
            background: #d4edda;
            color: #28a745;
        }
        
        .status-icon.error {
            background: #f8d7da;
            color: #dc3545;
        }
        
        .verification-card h1 {
            color: #800000;
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .verification-card .subtitle {
            color: #666;
            font-size: 18px;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .steps-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }
        
        .steps-section h3 {
            color: #800000;
            margin-bottom: 20px;
            font-size: 20px;
            text-align: center;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .step-item:last-child {
            border-bottom: none;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
            margin-right: 15px;
        }
        
        .step-number.done {
            background: #28a745;
            color: white;
        }
        
        .step-number.pending {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-content .step-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .step-content.done .step-title {
            color: #28a745;
        }
        
        .step-content.pending .step-title {
            color: #6c757d;
        }
        
        .step-content .step-desc {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 40px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .error-message {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 6px;
            color: #721c24;
            margin: 20px 0;
            text-align: left;
        }
        
        footer {
            background: #2c2c2c;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 0;
        }
        
        @media (max-width: 768px) {
            .verification-card {
                padding: 30px 20px;
            }
            
            .verification-card h1 {
                font-size: 24px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="top-nav-content">
            <a href="index.php" class="nav-logo">
                <img src="images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
                <span class="nav-logo-text">WMSU Bus Reserve</span>
            </a>
            <a href="login.php" class="nav-back">Back to Login</a>
        </div>
    </nav>

    <div class="verification-container">
        <div class="verification-card">
            <?php if ($success): ?>
                <div class="status-icon success">✓</div>
                <h1>Email Verified Successfully</h1>
                <p class="subtitle">Thank you, <strong><?php echo htmlspecialchars($user_name); ?></strong>. Your email has been verified.</p>
                
                <div class="steps-section">
                    <h3>What Happens Next?</h3>
                    
                    <div class="step-item">
                        <div class="step-number done">✓</div>
                        <div class="step-content done">
                            <div class="step-title">Email Verified</div>
                            <div class="step-desc">Your email address has been confirmed</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number pending">2</div>
                        <div class="step-content pending">
                            <div class="step-title">Admin Review</div>
                            <div class="step-desc">Administrator will verify your employee/teacher ID card</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number pending">3</div>
                        <div class="step-content pending">
                            <div class="step-title">Email Notification</div>
                            <div class="step-desc">You'll receive an email once your account is approved</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number pending">4</div>
                        <div class="step-content pending">
                            <div class="step-title">Start Using System</div>
                            <div class="step-desc">Login and begin making bus reservations</div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                    <a href="index.php" class="btn btn-secondary">Back to Home</a>
                </div>
                
            <?php else: ?>
                <div class="status-icon error">✕</div>
                <h1>Verification Failed</h1>
                
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="resend_verification.php" class="btn btn-primary">Request New Link</a>
                    <a href="register.php" class="btn btn-secondary">Register Again</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>
</body>
</html>