<?php
// Save as: resend_verification.php (FIXED VERSION)
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// CRITICAL: Disable debug output
error_reporting(0);
ini_set('display_errors', 0);

$success = '';
$error = '';
$email = '';

if (isset($_GET['email']) && !empty($_GET['email'])) {
    $email = clean_input($_GET['email']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } else {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'No account found with this email address.';
        } elseif ($user['email_verified'] == 1) {
            $error = 'Your email is already verified. Please wait for admin approval or try logging in.';
        } else {
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $stmt = $conn->prepare("UPDATE users SET verification_token = :token, verification_expires = :expires WHERE id = :id");
            $stmt->bindParam(':token', $verification_token);
            $stmt->bindParam(':expires', $verification_expires);
            $stmt->bindParam(':id', $user['id']);
            
            if ($stmt->execute()) {
                $verification_link = SITE_URL . "verify_email.php?token=" . $verification_token;
                
                $email_content = "
                    <div style='background: #fff; padding: 30px; max-width: 600px;'>
                        <h2 style='color: #800000; margin-bottom: 20px;'>Verify Your Email Address</h2>
                        <p style='color: #333; font-size: 16px; line-height: 1.6;'>Dear " . htmlspecialchars($user['name']) . ",</p>
                        <p style='color: #333; font-size: 16px; line-height: 1.6;'>You requested a new verification link for your WMSU Bus Reserve System account.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$verification_link}' style='display: inline-block; padding: 15px 40px; background: #800000; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;'>Verify Email Address</a>
                        </div>
                        <p style='color: #666; font-size: 14px; margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px;'>This link expires in 24 hours.</p>
                    </div>
                ";
                
                if (send_email($email, 'Verify Your Email - WMSU Bus Reserve System', $email_content)) {
                    $success = 'Verification email sent successfully! Please check your inbox.';
                } else {
                    $error = 'Failed to send email. Please try again or contact admin.';
                }
            } else {
                $error = 'Failed to generate new verification link. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email - <?php echo SITE_NAME; ?></title>
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
        
        .resend-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .resend-card {
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 550px;
            width: 100%;
        }
        
        .resend-card h1 {
            color: #800000;
            font-size: 32px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .resend-card .subtitle {
            color: #666;
            font-size: 16px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 6px;
        }
        
        .info-box h3 {
            color: #1565c0;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            color: #666;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .form-hint {
            color: #6c757d;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }
        
        .btn {
            padding: 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
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
        
        .alert {
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #dee2e6;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #6c757d;
            font-size: 14px;
        }
        
        .links {
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .links a {
            color: #800000;
            text-decoration: none;
            font-weight: 600;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        footer {
            background: #2c2c2c;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 0;
        }
        
        @media (max-width: 768px) {
            .resend-card {
                padding: 30px 20px;
            }
            
            .resend-card h1 {
                font-size: 24px;
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

    <div class="resend-container">
        <div class="resend-card">
            <h1>Resend Verification Email</h1>
            <p class="subtitle">Didn't receive your verification email?</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                    <a href="index.php" class="btn btn-secondary">Back to Home</a>
                </div>
                
            <?php else: ?>
                
                <div class="info-box">
                    <h3>Why didn't I receive the email?</h3>
                    <ul>
                        <li>Check your spam or junk folder</li>
                        <li>Verification links expire after 24 hours</li>
                        <li>Ensure you used your WMSU email address</li>
                        <li>Wait a few minutes for email delivery</li>
                    </ul>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Your Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="your.email@wmsu.edu.ph"
                               required autofocus>
                        <span class="form-hint">Enter the email you used during registration</span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Resend Verification Email</button>
                </form>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <div class="links">
                    <p>Already verified? <a href="login.php">Login here</a></p>
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>
</body>
</html>