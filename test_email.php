<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Testing Email System</h2>";

// Step 1: Load config
try {
    require_once 'includes/config.php';
    echo "<p style='color: green;'>✅ config.php loaded</p>";
} catch (Exception $e) {
    die("<p style='color: red;'>❌ Failed to load config.php: " . $e->getMessage() . "</p>");
}

// Step 2: Load database
try {
    require_once 'includes/database.php';
    echo "<p style='color: green;'>✅ database.php loaded</p>";
} catch (Exception $e) {
    die("<p style='color: red;'>❌ Failed to load database.php: " . $e->getMessage() . "</p>");
}

// Step 3: Load email config
try {
    require_once 'includes/email_config.php';
    echo "<p style='color: green;'>✅ email_config.php loaded</p>";
    echo "<p style='color: green;'>✅ PHPMailer loaded successfully!</p>";
} catch (Exception $e) {
    die("<p style='color: red;'>❌ Failed to load email_config.php: " . $e->getMessage() . "</p>");
}

// Step 4: Test email sending
if (isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'];
    
    echo "<hr>";
    echo "<p><strong>Attempting to send email to:</strong> $test_email</p>";
    
    $subject = 'WMSU Bus System - Test Email';
    $message = '
        <div style="background: #d4edda; padding: 20px; border-left: 4px solid #28a745; border-radius: 5px;">
            <h3 style="color: #155724;">✅ Email System Working!</h3>
            <p>Congratulations! Your WMSU Bus Reserve System email configuration is working correctly.</p>
            <p>Time sent: ' . date('Y-m-d H:i:s') . '</p>
        </div>
    ';
    
    try {
        $result = send_email($test_email, $subject, $message);
        
        if ($result) {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ SUCCESS! Email sent to $test_email</p>";
            echo "<p>Check your inbox (and spam folder)</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ FAILED! Email not sent.</p>";
            echo "<p>Check Apache error log at: C:\\xampp\\apache\\logs\\error.log</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ Exception:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Email</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .form-container { background: white; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input { padding: 12px; width: 100%; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { padding: 12px 30px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #600000; }
    </style>
</head>
<body>
    <div class="form-container">
        <form method="POST">
            <label><strong>Enter your email:</strong></label>
            <input type="email" name="test_email" placeholder="your-email@gmail.com" required value="kerrzaragoza43@gmail.com">
            <button type="submit" name="send_test">📧 Send Test Email</button>
        </form>
        
        <hr style="margin: 20px 0;">
        
        <p style="font-size: 12px; color: #666;">
            <strong>Gmail Settings:</strong><br>
            Username: <?php echo SMTP_USERNAME; ?><br>
            From: <?php echo SMTP_FROM_EMAIL; ?><br>
            Host: <?php echo SMTP_HOST; ?>:<?php echo SMTP_PORT; ?>
        </p>
    </div>
</body>
</html>