<?php
// Save as: includes/email_config.php (IMPROVED - No Emojis, Professional Design)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'kerrzaragoza43@gmail.com');
define('SMTP_PASSWORD', 'pefg egpe iwyj lcno');
define('SMTP_FROM_EMAIL', 'kerrzaragoza43@gmail.com');
define('SMTP_FROM_NAME', 'WMSU Bus Reserve System');

function send_email_phpmailer($to, $subject, $message, $fromName = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // PRODUCTION: Disable debug output
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, $fromName ?: SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = get_email_template($message);
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        log_email($to, $subject, 'sent', null);
        return true;
        
    } catch (Exception $e) {
        log_email($to, $subject, 'failed', $mail->ErrorInfo);
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function get_email_template($content) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #800000 0%, #600000 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }
        .email-header p {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-footer {
            text-align: center;
            padding: 30px;
            font-size: 13px;
            color: #666;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
        }
        .email-footer p {
            margin: 8px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 40px;
            background: #800000;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
        }
        .info-section {
            background: #f0f8ff;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .warning-section {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .success-section {
            background: #f1f8f4;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .error-section {
            background: #fef5f5;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .detail-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-table td:first-child {
            font-weight: 600;
            color: #666;
            width: 150px;
        }
        hr {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 30px 0;
        }
        ul {
            margin: 15px 0;
            padding-left: 25px;
        }
        ul li {
            margin: 8px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h2>WMSU Bus Reserve System</h2>
            <p>Western Mindanao State University</p>
        </div>
        <div class="email-body">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p><strong>Western Mindanao State University</strong></p>
            <p>Normal Road, Baliwasan, Zamboanga City, Philippines 7000</p>
            <hr style="margin: 20px 0; border-top: 1px solid #ddd;">
            <p>&copy; ' . date('Y') . ' WMSU. All rights reserved.</p>
            <p style="font-size: 12px; color: #999; margin-top: 15px;">This is an automated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>';
}

function log_email($recipient, $subject, $status, $error = null) {
    try {
        require_once __DIR__ . '/database.php';
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status, error_message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$recipient, $subject, $status, $error]);
    } catch (Exception $e) {
        error_log("Email log failed: " . $e->getMessage());
    }
}

// Main send_email function
function send_email($to, $subject, $message, $fromName = null) {
    return send_email_phpmailer($to, $subject, $message, $fromName);
}
?>