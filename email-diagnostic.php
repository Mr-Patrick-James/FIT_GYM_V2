<?php
// Email Diagnostic Tool - Run this to identify exactly why emails fail
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'api/config.php';

echo '<!DOCTYPE html>
<html>
<head>
<title>Email Diagnostic - FIT GYM</title>
<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 30px; }
h2 { color: #d4af37; border-bottom: 1px solid #334155; padding-bottom: 10px; }
.ok { color: #22c55e; font-weight: bold; }
.fail { color: #ef4444; font-weight: bold; }
.warn { color: #f59e0b; font-weight: bold; }
.info { color: #60a5fa; }
.box { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 20px; margin: 16px 0; }
.row { margin: 8px 0; }
pre { background: #0f172a; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; white-space: pre-wrap; }
.btn { display: inline-block; padding: 10px 20px; background: #d4af37; color: #000; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 12px; cursor: pointer; border: none; font-family: monospace; font-size: 1rem; }
</style>
</head>
<body>
<h1 style="color:#d4af37;">📧 Email Diagnostic Tool</h1>
';

// ── 1. PHP mail() check ──────────────────────────────────────
echo '<div class="box"><h2>1. PHP mail() Function</h2>';
if (function_exists('mail')) {
    echo '<div class="row"><span class="ok">✔ mail() function exists</span></div>';
} else {
    echo '<div class="row"><span class="fail">✘ mail() function is DISABLED</span></div>';
}

// Check php.ini sendmail settings
$sendmailPath = ini_get('sendmail_path');
$smtpHost = ini_get('SMTP');
$smtpPort = ini_get('smtp_port');
echo '<div class="row"><span class="info">sendmail_path:</span> ' . (empty($sendmailPath) ? '<span class="warn">NOT SET (required on Linux)</span>' : '<span class="ok">'.htmlspecialchars($sendmailPath).'</span>') . '</div>';
echo '<div class="row"><span class="info">SMTP (Windows):</span> ' . (empty($smtpHost) ? '<span class="warn">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtpHost).'</span>') . '</div>';
echo '<div class="row"><span class="info">smtp_port:</span> ' . (empty($smtpPort) ? '<span class="warn">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtpPort).'</span>') . '</div>';

$os = strtolower(PHP_OS);
if (strpos($os, 'win') !== false) {
    echo '<div class="row"><span class="warn">⚠ Running on Windows (WAMP). PHP mail() will NOT work without SMTP relay configured in php.ini.<br>PHPMailer SMTP is required.</span></div>';
}
echo '</div>';

// ── 2. PHPMailer check ──────────────────────────────────────
echo '<div class="box"><h2>2. PHPMailer Installation</h2>';
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo '<div class="row"><span class="ok">✔ vendor/autoload.php exists</span></div>';
    require_once $autoloadPath;
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo '<div class="row"><span class="ok">✔ PHPMailer class loaded successfully</span></div>';
        $ver = \PHPMailer\PHPMailer\PHPMailer::VERSION;
        echo '<div class="row"><span class="info">Version:</span> <span class="ok">'.$ver.'</span></div>';
    } else {
        echo '<div class="row"><span class="fail">✘ PHPMailer class NOT found after autoload — run: composer install</span></div>';
    }
} else {
    echo '<div class="row"><span class="fail">✘ vendor/autoload.php NOT FOUND</span></div>';
    echo '<div class="row"><span class="warn">FIX: Open terminal in project root and run: <code>composer install</code></span></div>';
}
echo '</div>';

// ── 3. .env / SMTP config ────────────────────────────────────
echo '<div class="box"><h2>3. SMTP Credentials (.env)</h2>';
$smtp_host = $_ENV['SMTP_HOST'] ?? 'NOT SET';
$smtp_port = $_ENV['SMTP_PORT'] ?? 'NOT SET';
$smtp_user = $_ENV['SMTP_USERNAME'] ?? 'NOT SET';
$smtp_pass = $_ENV['SMTP_PASSWORD'] ?? 'NOT SET';
$smtp_from = $_ENV['SMTP_FROM_EMAIL'] ?? 'NOT SET';

echo '<div class="row"><span class="info">SMTP_HOST:</span> ' . ($smtp_host === 'NOT SET' ? '<span class="fail">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtp_host).'</span>') . '</div>';
echo '<div class="row"><span class="info">SMTP_PORT:</span> ' . ($smtp_port === 'NOT SET' ? '<span class="fail">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtp_port).'</span>') . '</div>';
echo '<div class="row"><span class="info">SMTP_USERNAME:</span> ' . ($smtp_user === 'NOT SET' ? '<span class="fail">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtp_user).'</span>') . '</div>';
echo '<div class="row"><span class="info">SMTP_PASSWORD:</span> ' . ($smtp_pass === 'NOT SET' ? '<span class="fail">NOT SET</span>' : '<span class="ok">'.str_repeat('*', strlen($smtp_pass)-4).substr($smtp_pass,-4).'</span>') . '</div>';
echo '<div class="row"><span class="info">SMTP_FROM_EMAIL:</span> ' . ($smtp_from === 'NOT SET' ? '<span class="fail">NOT SET</span>' : '<span class="ok">'.htmlspecialchars($smtp_from).'</span>') . '</div>';

// Validate app password format for Gmail
if ($smtp_host === 'smtp.gmail.com' && $smtp_pass !== 'NOT SET') {
    $cleanPass = str_replace(' ', '', $smtp_pass);
    if (strlen($cleanPass) === 16 && ctype_alnum($cleanPass)) {
        echo '<div class="row"><span class="ok">✔ Password looks like a valid Gmail App Password (16-char alphanumeric)</span></div>';
    } else {
        echo '<div class="row"><span class="warn">⚠ Password format may be wrong. Gmail App Passwords are exactly 16 alphanumeric chars (no spaces). Current length: '.strlen($smtp_pass).'</span></div>';
    }
}
echo '</div>';

// ── 4. Database email_configs table ─────────────────────────
echo '<div class="box"><h2>4. Database email_configs Table</h2>';
try {
    $conn = getDBConnection();
    $result = $conn->query("SHOW TABLES LIKE 'email_configs'");
    if ($result && $result->num_rows > 0) {
        echo '<div class="row"><span class="ok">✔ email_configs table exists</span></div>';
        $rows = $conn->query("SELECT id, smtp_host, smtp_port, smtp_username, from_email, is_active, is_default FROM email_configs");
        if ($rows && $rows->num_rows > 0) {
            echo '<div class="row"><span class="info">Rows in email_configs:</span></div>';
            echo '<pre>';
            while ($row = $rows->fetch_assoc()) {
                echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
            }
            echo '</pre>';
        } else {
            echo '<div class="row"><span class="warn">⚠ Table exists but is EMPTY — system will fall back to .env config</span></div>';
        }
    } else {
        echo '<div class="row"><span class="warn">⚠ email_configs table does NOT exist — using .env fallback</span></div>';
    }
    $conn->close();
} catch (Exception $e) {
    echo '<div class="row"><span class="fail">DB Error: '.htmlspecialchars($e->getMessage()).'</span></div>';
}
echo '</div>';

// ── 5. Live SMTP Connection Test ─────────────────────────────
echo '<div class="box"><h2>5. Live SMTP Connection Test</h2>';
if (isset($_POST['test_smtp'])) {
    $testEmail = trim($_POST['test_email'] ?? '');
    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="row"><span class="fail">✘ Invalid test email address</span></div>';
    } elseif (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo '<div class="row"><span class="fail">✘ PHPMailer not loaded — cannot test</span></div>';
    } else {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug  = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                echo '<span class="info">SMTP: '.htmlspecialchars($str).'</span><br>';
            };

            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtp_port;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom($smtp_from, 'Martinez Fitness');
            $mail->addAddress($testEmail);
            $mail->isHTML(true);
            $mail->Subject = '✅ Test Email - Martinez Fitness OTP System';
            $mail->Body    = '<h2 style="color:#d4af37;">Test Successful!</h2><p>Your OTP email system is working correctly.</p><p>Test OTP: <strong style="font-size:1.5rem;letter-spacing:8px;font-family:monospace;">123456</strong></p>';
            $mail->AltBody = 'Test email from Martinez Fitness. OTP system is working!';

            $mail->send();
            echo '<div class="row"><span class="ok" style="font-size:1.2rem;">✔✔ EMAIL SENT SUCCESSFULLY to '.htmlspecialchars($testEmail).'!</span></div>';
            echo '<div class="row"><span class="info">Check your inbox (and spam folder).</span></div>';
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            echo '<br><div class="row"><span class="fail">✘ SEND FAILED: '.htmlspecialchars($e->getMessage()).'</span></div>';
            echo '<div class="row"><span class="fail">Error Info: '.htmlspecialchars($mail->ErrorInfo).'</span></div>';
            echo '<br>';

            // Diagnose common errors
            $errMsg = strtolower($e->getMessage() . $mail->ErrorInfo);
            if (str_contains($errMsg, 'username and password not accepted') || str_contains($errMsg, '535')) {
                echo '<div class="row"><span class="warn">🔑 <strong>ROOT CAUSE: Invalid Gmail credentials</strong></span></div>';
                echo '<div class="row">The Gmail app password is rejected. Steps to fix:<br>
                1. Go to <a href="https://myaccount.google.com/security" target="_blank" style="color:#60a5fa;">Google Account Security</a><br>
                2. Enable 2-Step Verification (required for app passwords)<br>
                3. Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#60a5fa;">App Passwords</a><br>
                4. Create a new app password for "Mail" / "Other"<br>
                5. Copy the 16-character password → paste into .env as SMTP_PASSWORD</div>';
            } elseif (str_contains($errMsg, 'connection') || str_contains($errMsg, 'timed out') || str_contains($errMsg, 'could not connect')) {
                echo '<div class="row"><span class="warn">🔌 <strong>ROOT CAUSE: Cannot connect to SMTP server</strong></span></div>';
                echo '<div class="row">Your network/firewall is blocking port 587. Try:<br>
                - Check if port 587 is blocked by your ISP or Windows Firewall<br>
                - Try port 465 with SSL instead of 587 with TLS</div>';
            } elseif (str_contains($errMsg, 'less secure') || str_contains($errMsg, '534')) {
                echo '<div class="row"><span class="warn">🔒 <strong>ROOT CAUSE: Google blocked the login</strong></span></div>';
                echo '<div class="row">Google requires an App Password. Regular passwords are blocked for SMTP.</div>';
            }
        }
    }
} else {
    echo '<div class="row"><span class="info">Enter an email address below to send a live test email:</span></div>';
}
?>

<form method="POST" style="margin-top:16px;">
    <input type="hidden" name="test_smtp" value="1">
    <input type="email" name="test_email" placeholder="your@email.com"
           value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>"
           style="padding:10px 16px; border-radius:6px; border:1px solid #475569; background:#0f172a; color:#e2e8f0; font-family:monospace; font-size:1rem; width:280px;">
    <button type="submit" class="btn">📤 Send Test OTP Email</button>
</form>
</div>

<?php
// ── 6. OTP Database check ────────────────────────────────────
echo '<div class="box"><h2>6. OTP Table Check</h2>';
try {
    $conn = getDBConnection();
    $result = $conn->query("SHOW TABLES LIKE 'otps'");
    if ($result && $result->num_rows > 0) {
        echo '<div class="row"><span class="ok">✔ otps table exists</span></div>';
        $count = $conn->query("SELECT COUNT(*) as total FROM otps");
        $row   = $count->fetch_assoc();
        echo '<div class="row"><span class="info">Total OTP records:</span> '.$row['total'].'</div>';
        $recent = $conn->query("SELECT email, LEFT(code,2),'****' as code_masked, expires_at, created_at FROM otps ORDER BY created_at DESC LIMIT 5");
        if ($recent && $recent->num_rows > 0) {
            echo '<div class="row"><span class="info">Recent OTPs:</span></div><pre>';
            while ($r = $recent->fetch_assoc()) {
                $r['code'] = substr($r['code'] ?? '',0,2).'****';
                echo json_encode($r)."\n";
            }
            echo '</pre>';
        }
    } else {
        echo '<div class="row"><span class="fail">✘ otps table does NOT exist — run database migrations</span></div>';
    }
    $conn->close();
} catch (Exception $e) {
    echo '<div class="row"><span class="fail">'.htmlspecialchars($e->getMessage()).'</span></div>';
}
echo '</div>';

// ── 7. Quick Fix Summary ─────────────────────────────────────
echo '<div class="box"><h2>7. Quick Fix Options</h2>';
echo '<p>If SMTP is failing, the fastest fixes are:</p>';
echo '<ol>
<li><strong style="color:#d4af37;">Get a fresh Gmail App Password:</strong><br>
  <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#60a5fa;">myaccount.google.com/apppasswords</a>
  → then update SMTP_PASSWORD in your .env file
</li>
<li style="margin-top:12px;"><strong style="color:#d4af37;">Use a different SMTP provider (more reliable):</strong><br>
  Consider <a href="https://mailtrap.io" target="_blank" style="color:#60a5fa;">Mailtrap.io</a> (free, perfect for local dev) or 
  <a href="https://brevo.com" target="_blank" style="color:#60a5fa;">Brevo.com</a> (300 free emails/day)
</li>
</ol>';
echo '</div>';

echo '</body></html>';
