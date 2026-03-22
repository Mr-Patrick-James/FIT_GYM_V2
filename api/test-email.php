<?php
ob_start();
require_once 'config.php';
require_once 'email.php';
ob_end_clean();

// Only allow admin access
require_once 'session.php';
requireAdmin();

$testTo = $_GET['to'] ?? '';
if (empty($testTo)) {
    sendResponse(false, 'Provide ?to=youremail@gmail.com');
}

global $phpmailerInstalled;
$config = getEmailConfig();

$result = [
    'phpmailer_installed' => $phpmailerInstalled,
    'smtp_host'     => $config['smtp_host'],
    'smtp_port'     => $config['smtp_port'],
    'smtp_username' => $config['smtp_username'],
    'smtp_password' => !empty($config['smtp_password']) ? '***SET***' : '***EMPTY***',
    'from_email'    => $config['from_email'],
    'sending_to'    => $testTo,
];

if (!$phpmailerInstalled) {
    sendResponse(false, 'PHPMailer not installed', $result);
}

if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
    sendResponse(false, 'SMTP credentials missing', $result);
}

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_username'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->SMTPDebug  = 0;

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($testTo);
    $mail->isHTML(true);
    $mail->Subject = 'FitPay Email Test';
    $mail->Body    = '<p>This is a test email from <strong>Martinez Fitness FitPay</strong>. If you received this, SMTP is working correctly.</p>';
    $mail->AltBody = 'FitPay email test - SMTP is working.';

    $mail->send();
    sendResponse(true, 'Test email sent successfully!', $result);
} catch (\PHPMailer\PHPMailer\Exception $e) {
    $result['error'] = $mail->ErrorInfo;
    sendResponse(false, 'PHPMailer failed: ' . $mail->ErrorInfo, $result);
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    sendResponse(false, 'Error: ' . $e->getMessage(), $result);
}
?>
