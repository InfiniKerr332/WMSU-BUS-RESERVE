<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

if (is_logged_in()) {
    redirect(SITE_URL . 'student/dashboard.php');
}

$errors = [];
$success = '';
$form_data = [];
$registered_email = '';
$user_first_name = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data['first_name'] = clean_input($_POST['first_name']);
    $form_data['middle_name'] = clean_input($_POST['middle_name']);
    $form_data['last_name'] = clean_input($_POST['last_name']);
    $form_data['email'] = clean_input($_POST['email']);
    $form_data['contact_no'] = clean_input($_POST['contact_no']);
    $form_data['department'] = clean_input($_POST['department']);
    $form_data['role'] = clean_input($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $full_name = trim($form_data['first_name'] . ' ' . $form_data['middle_name'] . ' ' . $form_data['last_name']);
    
    if (empty($form_data['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($form_data['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($form_data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    } elseif (!str_ends_with($form_data['email'], '@wmsu.edu.ph')) {
        $errors['email'] = 'You must use your WMSU email account (@wmsu.edu.ph)';
    }
    
    if (empty($form_data['contact_no'])) {
        $errors['contact_no'] = 'Contact number is required';
    }
    
    if (empty($form_data['role'])) {
        $errors['role'] = 'Please select your role';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } else {
        $password_errors = validate_password($password);
        if (!empty($password_errors)) {
            $errors['password'] = implode('<br>', $password_errors);
        }
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    $employee_id_front_path = null;
    if (isset($_FILES['employee_id_front']) && $_FILES['employee_id_front']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024;
        
        $file_type = $_FILES['employee_id_front']['type'];
        $file_size = $_FILES['employee_id_front']['size'];
        $file_size_mb = round($file_size / (1024 * 1024), 2);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['employee_id_front'] = 'Only JPG, JPEG, and PNG files are allowed';
        } elseif ($file_size > $max_size) {
            $errors['employee_id_front'] = "File size is {$file_size_mb}MB. Maximum allowed is 5MB";
        } else {
            $extension = pathinfo($_FILES['employee_id_front']['name'], PATHINFO_EXTENSION);
            $filename = 'emp_front_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_dir = 'uploads/employee_ids/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $employee_id_front_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['employee_id_front']['tmp_name'], $employee_id_front_path)) {
                $errors['employee_id_front'] = 'Failed to upload front ID';
                $employee_id_front_path = null;
            }
        }
    } elseif ($_FILES['employee_id_front']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['employee_id_front'] = 'Error uploading front ID';
    } else {
        $errors['employee_id_front'] = 'Front ID image is required';
    }
    
    $employee_id_back_path = null;
    if (isset($_FILES['employee_id_back']) && $_FILES['employee_id_back']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024;
        
        $file_type = $_FILES['employee_id_back']['type'];
        $file_size = $_FILES['employee_id_back']['size'];
        $file_size_mb = round($file_size / (1024 * 1024), 2);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['employee_id_back'] = 'Only JPG, JPEG, and PNG files are allowed';
        } elseif ($file_size > $max_size) {
            $errors['employee_id_back'] = "File size is {$file_size_mb}MB. Maximum allowed is 5MB";
        } else {
            $extension = pathinfo($_FILES['employee_id_back']['name'], PATHINFO_EXTENSION);
            $filename = 'emp_back_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_dir = 'uploads/employee_ids/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $employee_id_back_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['employee_id_back']['tmp_name'], $employee_id_back_path)) {
                $errors['employee_id_back'] = 'Failed to upload back ID';
                $employee_id_back_path = null;
            }
        }
    } elseif ($_FILES['employee_id_back']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['employee_id_back'] = 'Error uploading back ID';
    } else {
        $errors['employee_id_back'] = 'Back ID image is required';
    }
    
    if (empty($errors['email'])) {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id, email_verified, account_status FROM users WHERE email = :email");
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->execute();
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            if ($existing_user['email_verified'] == 0) {
                $errors['email'] = 'This email is already registered but not verified. <a href="resend_verification.php?email=' . urlencode($form_data['email']) . '" style="color: #800000; font-weight: bold;">Click here to resend verification email</a>';
            } elseif ($existing_user['account_status'] == 'pending') {
                $errors['email'] = 'This email is already registered and waiting for admin approval. Please wait for approval.';
            } elseif ($existing_user['account_status'] == 'rejected') {
                $errors['email'] = 'This email was previously rejected. Please contact admin or use a different email.';
            } else {
                $errors['email'] = 'Email already registered. Please login instead.';
            }
        }
    }
    
    if (empty($errors)) {
        $db = new Database();
        $conn = $db->connect();
        
        $hashed_password = hash_password($password);
        
        $position_map = [
            'employee' => 'Employee',
            'teacher' => 'Teacher'
        ];
        $position = $position_map[$form_data['role']] ?? 'Employee';
        
        $verification_token = bin2hex(random_bytes(32));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $sql = "INSERT INTO users (
            name, email, contact_no, department, position, password, role, 
            employee_id_image, employee_id_back_image, 
            account_status, email_verified, verification_token, verification_expires
        ) VALUES (
            :name, :email, :contact_no, :department, :position, :password, :role,
            :employee_id_front, :employee_id_back,
            'pending', 0, :verification_token, :verification_expires
        )";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $full_name);
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->bindParam(':contact_no', $form_data['contact_no']);
        $stmt->bindParam(':department', $form_data['department']);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $form_data['role']);
        $stmt->bindParam(':employee_id_front', $employee_id_front_path);
        $stmt->bindParam(':employee_id_back', $employee_id_back_path);
        $stmt->bindParam(':verification_token', $verification_token);
        $stmt->bindParam(':verification_expires', $verification_expires);
        
        if ($stmt->execute()) {
            $verification_link = SITE_URL . "verify_email.php?token=" . $verification_token;
            
            $user_email_content = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; }
                        .email-wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; }
                        .email-header { background: #800000; padding: 40px 30px; text-align: center; }
                        .email-header img { height: 80px; margin-bottom: 20px; }
                        .email-header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; }
                        .email-body { padding: 50px 40px; }
                        .email-body h2 { color: #333; font-size: 24px; margin: 0 0 20px 0; font-weight: 600; }
                        .email-body p { color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0; }
                        .verify-button { display: inline-block; padding: 16px 40px; background: #800000; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 16px; margin: 30px 0; }
                        .verify-button:hover { background: #600000; }
                        .link-box { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #800000; }
                        .link-box p { margin: 0 0 10px 0; font-size: 14px; color: #666; }
                        .link-box code { background: #fff; padding: 10px; display: block; word-break: break-all; color: #333; border: 1px solid #ddd; border-radius: 3px; font-size: 13px; }
                        .info-box { background: #fff3cd; padding: 20px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 20px 0; }
                        .info-box p { color: #856404; margin: 0; font-size: 14px; }
                        .steps-list { background: #f8f9fa; padding: 25px; border-radius: 5px; margin: 30px 0; }
                        .steps-list h3 { color: #800000; margin: 0 0 20px 0; font-size: 18px; }
                        .step-item { display: flex; margin-bottom: 15px; align-items: flex-start; }
                        .step-number { background: #800000; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; margin-right: 15px; flex-shrink: 0; }
                        .step-text { color: #666; font-size: 14px; line-height: 1.6; padding-top: 5px; }
                        .email-footer { background: #2c2c2c; color: #ffffff; padding: 30px; text-align: center; }
                        .email-footer p { margin: 5px 0; font-size: 14px; color: #ccc; }
                    </style>
                </head>
                <body>
                    <div class='email-wrapper'>
                        <div class='email-header'>
                            <img src='" . SITE_URL . "images/wmsu.png' alt='WMSU Logo'>
                            <h1>WMSU Bus Reserve System</h1>
                        </div>
                        
                        <div class='email-body'>
                            <h2>Welcome " . htmlspecialchars($form_data['first_name']) . "!</h2>
                            <p>Thank you for registering with the WMSU Bus Reserve System.</p>
                            <p>To complete your registration, please verify your email address by clicking the button below:</p>
                            
                            <center>
                                <a href='{$verification_link}' class='verify-button'>Verify Email Address</a>
                            </center>
                            
                            <div class='link-box'>
                                <p><strong>Can't click the button? Copy and paste this link:</strong></p>
                                <code>{$verification_link}</code>
                            </div>
                            
                            <div class='info-box'>
                                <p><strong>Important:</strong> This verification link expires in 24 hours.</p>
                            </div>
                            
                            <div class='steps-list'>
                                <h3>What happens next?</h3>
                                <div class='step-item'>
                                    <div class='step-number'>1</div>
                                    <div class='step-text'>Click the verification button to confirm your email</div>
                                </div>
                                <div class='step-item'>
                                    <div class='step-number'>2</div>
                                    <div class='step-text'>Admin reviews your employee/teacher ID</div>
                                </div>
                                <div class='step-item'>
                                    <div class='step-number'>3</div>
                                    <div class='step-text'>You receive approval notification via email</div>
                                </div>
                                <div class='step-item'>
                                    <div class='step-number'>4</div>
                                    <div class='step-text'>Login and start making bus reservations</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class='email-footer'>
                            <p><strong>Western Mindanao State University</strong></p>
                            <p>Normal Road, Baliwasan, Zamboanga City, Philippines 7000</p>
                            <p style='margin-top: 20px; font-size: 12px;'>This is an automated email. Please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            send_email($form_data['email'], 'Verify Your Email - WMSU Bus Reserve System', $user_email_content);
            
            $success = true;
            $registered_email = $form_data['email'];
            $user_first_name = $form_data['first_name'];
            $form_data = [];
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
            if ($employee_id_front_path && file_exists($employee_id_front_path)) {
                unlink($employee_id_front_path);
            }
            if ($employee_id_back_path && file_exists($employee_id_back_path)) {
                unlink($employee_id_back_path);
            }
        }
    } else {
        if ($employee_id_front_path && file_exists($employee_id_front_path)) {
            unlink($employee_id_front_path);
        }
        if ($employee_id_back_path && file_exists($employee_id_back_path)) {
            unlink($employee_id_back_path);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
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
            object-fit: contain;
        }
        
        .nav-logo-text {
            color: #800000;
            font-size: 18px;
            font-weight: 700;
        }
        
        .nav-back {
            color: #800000;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 20px;
            border: 2px solid #800000;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-back:hover {
            background: #800000;
            color: white;
        }
        
        .register-container {
            max-width: 650px;
            margin: 50px auto;
            padding: 20px;
        }
        
        /* SUCCESS PAGE STYLES */
        .success-container {
            max-width: 700px;
            margin: 80px auto;
            padding: 20px;
        }
        
        .success-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 40px;
            color: #28a745;
        }
        
        .success-header h1 {
            font-size: 32px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }
        
        .success-header p {
            font-size: 18px;
            opacity: 0.95;
            margin: 0;
        }
        
        .success-body {
            padding: 50px 40px;
        }
        
        .email-sent-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 25px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .email-sent-box h3 {
            color: #1565c0;
            font-size: 18px;
            margin: 0 0 15px 0;
        }
        
        .email-sent-box p {
            color: #0d47a1;
            margin: 0 0 10px 0;
            line-height: 1.6;
        }
        
        .email-address {
            font-weight: 600;
            color: #800000;
        }
        
        .steps-box {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin: 30px 0;
        }
        
        .steps-box h3 {
            color: #800000;
            font-size: 20px;
            margin: 0 0 25px 0;
            text-align: center;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .step-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .step-number {
            background: #800000;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            margin-right: 15px;
            font-size: 16px;
        }
        
        .step-content {
            flex: 1;
            padding-top: 6px;
        }
        
        .step-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .step-desc {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            flex: 1;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .helper-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        /* EXISTING FORM STYLES */
        .logo-center {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-center h2 {
            color: #800000;
            margin-bottom: 5px;
            font-size: 28px;
        }
        
        .logo-center p {
            color: #666;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #666;
        }
        
        .name-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .name-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        .file-upload-container {
            border: 2px dashed #800000;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .file-upload-container:hover {
            background: #f0f0f0;
            border-color: #600000;
        }
        
        .file-upload-container input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #800000;
            margin-bottom: 10px;
        }
        
        .upload-text {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .upload-hint {
            color: #999;
            font-size: 12px;
        }
        
        .preview-container {
            margin-top: 15px;
            display: none;
        }
        
        .preview-container.show {
            display: block;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 10px;
        }
        
        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .file-info-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 13px;
        }
        
        .file-info-label {
            color: #666;
            font-weight: 600;
        }
        
        .file-info-value {
            color: #333;
        }
        
        .file-size-ok {
            color: #28a745;
        }
        
        .file-size-error {
            color: #dc3545;
        }
        
        .remove-file {
            margin-top: 10px;
            padding: 8px 15px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-file:hover {
            background: #c82333;
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
            
            <a href="index.php" class="nav-back">
                Back to Home
            </a>
        </div>
    </nav>

    <?php if ($success): ?>
    <!-- SUCCESS PAGE -->
    <div class="success-container">
        <div class="success-card">
            <div class="success-header">
                <div class="success-icon">✓</div>
                <h1>Registration Successful!</h1>
                <p>Welcome to WMSU Bus Reserve System</p>
            </div>
            
            <div class="success-body">
                <div class="email-sent-box">
                    <h3>Verify Your Email</h3>
                    <p>We've sent a verification email to <span class="email-address"><?php echo htmlspecialchars($registered_email); ?></span></p>
                    <p>Please check your inbox and click the verification link to continue.</p>
                    <p style="margin-top: 15px; font-size: 13px; color: #666;">Didn't receive the email? Check your spam folder or <a href="resend_verification.php?email=<?php echo urlencode($registered_email); ?>" style="color: #800000; font-weight: 600;">click here to resend</a></p>
                </div>
                
                <div class="steps-box">
                    <h3>Next Steps</h3>
                    
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Check Your Email</div>
                            <div class="step-desc">Open the verification email we just sent to <?php echo htmlspecialchars($registered_email); ?></div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Verify Your Email Address</div>
                            <div class="step-desc">Click the verification button in the email to confirm your account</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Wait for Admin Approval</div>
                            <div class="step-desc">Our admin will review your employee/teacher ID for verification</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Get Approval Notification</div>
                            <div class="step-desc">You'll receive an email once your account is approved</div>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <div class="step-title">Start Using the System</div>
                            <div class="step-desc">Login and begin making bus reservations for your trips</div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">Go to Login Page</a>
                    <a href="index.php" class="btn btn-secondary">Back to Home</a>
                </div>
                
                <div class="helper-text">
                    <p>Need help? Contact us at <strong>admin@wmsu.edu.ph</strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- REGISTRATION FORM -->
    <div class="register-container">
        <div class="card">
            <div class="logo-center">
                <h2>Create Your Account</h2>
                <p>Register to reserve WMSU buses (Teachers & Employees Only)</p>
            </div>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <?php echo $errors['general']; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
                <div class="name-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" 
                               placeholder="Juan"
                               required>
                        <?php if (isset($errors['first_name'])): ?>
                            <span class="error-text"><?php echo $errors['first_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name <small>(Optional)</small></label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>" 
                               placeholder="Santos">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" 
                               placeholder="Dela Cruz"
                               required>
                        <?php if (isset($errors['last_name'])): ?>
                            <span class="error-text"><?php echo $errors['last_name']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">WMSU Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                           placeholder="your.email@wmsu.edu.ph"
                           required>
                    <small style="color: #666;">Must be your official WMSU email (@wmsu.edu.ph)</small>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="contact_no">Contact Number <span class="required">*</span></label>
                    <input type="text" id="contact_no" name="contact_no" class="form-control" 
                           placeholder="09XXXXXXXXX" 
                           value="<?php echo htmlspecialchars($form_data['contact_no'] ?? ''); ?>" 
                           required>
                    <?php if (isset($errors['contact_no'])): ?>
                        <span class="error-text"><?php echo $errors['contact_no']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="department">Department <span class="required">*</span></label>
                    <input type="text" id="department" name="department" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['department'] ?? ''); ?>"
                           placeholder="e.g., CCS, CLA, COE"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="role">I am a <span class="required">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">-- Select Your Role --</option>
                        <option value="employee" <?php echo (isset($form_data['role']) && $form_data['role'] == 'employee') ? 'selected' : ''; ?>>Employee</option>
                        <option value="teacher" <?php echo (isset($form_data['role']) && $form_data['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                    <small style="color: #666;">Only teachers and employees can register</small>
                    <?php if (isset($errors['role'])): ?>
                        <span class="error-text"><?php echo $errors['role']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- FRONT ID -->
                <div class="form-group">
                    <label>Employee/Teacher ID - FRONT SIDE <span class="required">*</span></label>
                    <div class="file-upload-container" id="frontUploadArea">
                        <input type="file" id="employee_id_front" name="employee_id_front" accept="image/jpeg,image/jpg,image/png" required>
                        <div class="upload-icon">↑</div>
                        <div class="upload-text">Click to upload FRONT of your ID</div>
                        <div class="upload-hint">JPG, PNG - Max 5MB</div>
                    </div>
                    <div class="preview-container" id="frontPreview">
                        <img class="preview-image" id="frontImage" src="" alt="Front ID Preview">
                        <div class="file-info" id="frontFileInfo"></div>
                        <button type="button" class="remove-file" onclick="removeFrontFile()">Remove</button>
                    </div>
                    <?php if (isset($errors['employee_id_front'])): ?>
                        <span class="error-text"><?php echo $errors['employee_id_front']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- BACK ID -->
                <div class="form-group">
                    <label>Employee/Teacher ID - BACK SIDE <span class="required">*</span></label>
                    <div class="file-upload-container" id="backUploadArea">
                        <input type="file" id="employee_id_back" name="employee_id_back" accept="image/jpeg,image/jpg,image/png" required>
                        <div class="upload-icon">↑</div>
                        <div class="upload-text">Click to upload BACK of your ID</div>
                        <div class="upload-hint">JPG, PNG - Max 5MB</div>
                    </div>
                    <div class="preview-container" id="backPreview">
                        <img class="preview-image" id="backImage" src="" alt="Back ID Preview">
                        <div class="file-info" id="backFileInfo"></div>
                        <button type="button" class="remove-file" onclick="removeBackFile()">Remove</button>
                    </div>
                    <?php if (isset($errors['employee_id_back'])): ?>
                        <span class="error-text"><?php echo $errors['employee_id_back']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter password"
                           required>
                    <small style="color: #666;">Min 8 characters, 1 uppercase, 1 special character</small>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error-text"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Re-enter password"
                           required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error-text"><?php echo $errors['confirm_password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div style="text-align: center;">
                <p>Already have an account? <a href="login.php" style="color: #800000; font-weight: 600;">Login here</a></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <footer style="margin-top: 50px;">
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
    <script src="js/validation.js"></script>
    <script>
        // Handle FRONT ID upload
        document.getElementById('employee_id_front').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileUpload(file, 'front');
            }
        });
        
        // Handle BACK ID upload
        document.getElementById('employee_id_back').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileUpload(file, 'back');
            }
        });
        
        function handleFileUpload(file, side) {
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, JPEG, and PNG files are allowed');
                resetFileInput(side);
                return;
            }
            
            // Check file size
            if (file.size > maxSize) {
                alert(`File size is ${fileSizeMB}MB. Maximum allowed is 5MB. Please choose a smaller file.`);
                resetFileInput(side);
                return;
            }
            
            // Hide upload area, show preview
            document.getElementById(side + 'UploadArea').style.display = 'none';
            document.getElementById(side + 'Preview').classList.add('show');
            
            // Show image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(side + 'Image').src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Show file info
            const sizeClass = file.size > maxSize ? 'file-size-error' : 'file-size-ok';
            const fileInfo = `
                <div class="file-info-item">
                    <span class="file-info-label">File name:</span>
                    <span class="file-info-value">${file.name}</span>
                </div>
                <div class="file-info-item">
                    <span class="file-info-label">File size:</span>
                    <span class="file-info-value ${sizeClass}">${fileSizeMB} MB</span>
                </div>
                <div class="file-info-item">
                    <span class="file-info-label">File type:</span>
                    <span class="file-info-value">${file.type}</span>
                </div>
            `;
            document.getElementById(side + 'FileInfo').innerHTML = fileInfo;
        }
        
        function removeFrontFile() {
            resetFileInput('front');
        }
        
        function removeBackFile() {
            resetFileInput('back');
        }
        
        function resetFileInput(side) {
            document.getElementById('employee_id_' + side).value = '';
            document.getElementById(side + 'UploadArea').style.display = 'block';
            document.getElementById(side + 'Preview').classList.remove('show');
            document.getElementById(side + 'Image').src = '';
            document.getElementById(side + 'FileInfo').innerHTML = '';
        }
    </script>
</body>
</html>