<?php
// Email Configuration and Helper Functions
require_once 'config.php';

// Check if PHPMailer is installed and load it
$phpmailerInstalled = false;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $phpmailerInstalled = class_exists('PHPMailer\PHPMailer\PHPMailer');
    } catch (Exception $e) {
        error_log("PHPMailer autoload failed: " . $e->getMessage());
    }
}

/**
 * Get email configuration from database (preferred) or .env file (fallback)
 */
function getEmailConfig() {
    // Try to get from database first
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM email_configs WHERE is_active = TRUE AND is_default = TRUE LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $config = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            return [
                'smtp_host' => $config['smtp_host'],
                'smtp_port' => (int)$config['smtp_port'],
                'smtp_username' => $config['smtp_username'],
                'smtp_password' => $config['smtp_password'],
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name']
            ];
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Database table might not exist, fall back to .env
    }
    
    // Fallback to .env file
    return [
        'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
        'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
        'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
        'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@martinezfitness.com',
        'from_name' => $_ENV['SMTP_FROM_NAME'] ?? 'Martinez Fitness'
    ];
}

/**
 * Send OTP email using PHP's built-in mail() function (simplest, no SMTP config needed)
 * This works if your server/hosting supports mail() function
 */
function sendOTPEmailSimple($email, $otp, $name = '') {
    $subject = 'Verify Your Email - Martinez Fitness';
    
    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
            .otp-box { background: white; border: 2px dashed #dc2626; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
            .otp-code { font-size: 32px; font-weight: bold; color: #dc2626; letter-spacing: 8px; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>MARTINEZ FITNESS</h1>
                <p>Email Verification</p>
            </div>
            <div class="content">
                <h2>Hello' . ($name ? ' ' . htmlspecialchars($name) : '') . '!</h2>
                <p>Thank you for signing up with Martinez Fitness. Your verification code is:</p>
                <div class="otp-box">
                    <div class="otp-code">' . $otp . '</div>
                </div>
                <p>This code will expire in <strong>5 minutes</strong>.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $plainMessage = "Hello" . ($name ? " $name" : "") . "!\n\nYour verification code is: $otp\n\nThis code will expire in 5 minutes.";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Martinez Fitness <noreply@martinezfitness.com>\r\n";
    $headers .= "Reply-To: noreply@martinezfitness.com\r\n";
    
    $result = mail($email, $subject, $htmlMessage, $headers);
    if ($result) {
        error_log("Email sent successfully to: $email via PHP mail()");
    } else {
        error_log("Failed to send email to: $email via PHP mail()");
    }
    return $result;
}

/**
 * Send OTP email to user (tries PHPMailer first, falls back to simple mail())
 */
function sendOTPEmail($email, $otp, $name = '') {
    global $phpmailerInstalled;
    
    // Try PHPMailer first (if configured)
    if ($phpmailerInstalled) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Get email configuration from database or .env
            $config = getEmailConfig();
            
            // If SMTP credentials are configured, use PHPMailer
            if (!empty($config['smtp_username']) && !empty($config['smtp_password'])) {
                error_log("Attempting SMTP send to: $email using: " . $config['smtp_username']);
                
                $mail->isSMTP();
                $mail->Host       = $config['smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $config['smtp_username'];
                $mail->Password   = $config['smtp_password'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $config['smtp_port'];
                $mail->CharSet    = 'UTF-8';
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                $mail->setFrom($config['from_email'], $config['from_name']);
                $mail->addAddress($email, $name);
                
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Email - Martinez Fitness';
                
                $htmlBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                        .otp-box { background: white; border: 2px dashed #dc2626; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #dc2626; letter-spacing: 8px; font-family: monospace; }
                        .footer { text-align: center; margin-top: 20px; color: #6b7280; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>MARTINEZ FITNESS</h1>
                            <p>Email Verification</p>
                        </div>
                        <div class="content">
                            <h2>Hello' . ($name ? ' ' . htmlspecialchars($name) : '') . '!</h2>
                            <p>Thank you for signing up with Martinez Fitness. To complete your registration, please verify your email address using the OTP code below:</p>
                            
                            <div class="otp-box">
                                <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Your verification code:</p>
                                <div class="otp-code">' . $otp . '</div>
                            </div>
                            
                            <p>This code will expire in <strong>5 minutes</strong>. If you didn\'t request this code, please ignore this email.</p>
                            
                            <p style="margin-top: 30px;">Welcome to Martinez Fitness! We\'re excited to have you join our community.</p>
                        </div>
                        <div class="footer">
                            <p>© 2023 Martinez Fitness Gym. All rights reserved.</p>
                            <p>This is an automated email, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                $mail->Body = $htmlBody;
                $mail->AltBody = "Hello" . ($name ? " $name" : "") . "!\n\nThank you for signing up with Martinez Fitness. Your verification code is: $otp\n\nThis code will expire in 5 minutes.";
                
                $mail->send();
                return true;
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("PHPMailer failed: " . $mail->ErrorInfo);
            error_log("PHPMailer Exception: " . $e->getMessage());
            // Fall through to simple mail()
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            // Fall through to simple mail()
        }
    }
    
    // Fallback to simple mail() function (no SMTP config needed)
    return sendOTPEmailSimple($email, $otp, $name);
}

?>
