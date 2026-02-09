<?php
require_once 'api/session.php';

// If user is already logged in, redirect to appropriate dashboard
// But only if we're not in the middle of a login/signup process
if (isLoggedIn() && !isset($_GET['auth']) && !isset($_POST['auth'])) {
    $redirect = isAdmin() ? 'views/admin/dashboard.php' : 'views/user/dashboard.php';
    header("Location: $redirect");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Martinez Fitness | Elite Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@300;400;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=1.2">
</head>
<body>

    <header>
        <div class="logo-circle">
            MARTINEZ<br>GYM
        </div>
        <div class="nav-group">
            <nav>
                <ul class="nav-links">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">Classes</a></li>
                    <li><a href="#">Trainers</a></li>
                </ul>
            </nav>
            <button class="login-btn-nav" onclick="openModal('login')">Login</button>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1 class="big-text">MARTINEZ</h1>
            <div class="script-text">Fitness</div>
            <h1 class="big-text">GYM</h1>
            
            <button class="main-cta" onclick="openModal('signup')">
                Join Now 
                <div class="icon-circle"><i class="fa-solid fa-arrow-right"></i></div>
            </button>
        </div>
    </section>

    <div class="floating-socials">
        <a href="#" class="social-icon"><i class="fa-solid fa-envelope"></i></a>
        <a href="#" class="social-icon"><i class="fa-solid fa-location-dot"></i></a>
        <a href="#" class="social-icon"><i class="fa-solid fa-phone"></i></a>
    </div>

    <div class="modal-overlay" id="authModal">
        <div class="auth-card">
            <button class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>

            <div id="loginForm" class="form-box active">
                <div class="auth-header">
                    <h2>Welcome Back</h2>
                    <p>Enter your details to access your account</p>
                </div>
                <form id="loginFormElement">
                    <div class="input-group">
                        <input type="email" id="loginEmail" name="email" required autocomplete="email">
                        <label>Email Address</label>
                    </div>
                    <div class="input-group">
                                           <input type="password" id="loginPassword" name="password" required autocomplete="current-password">
                        <label>Password</label>
                        <span class="toggle-password" data-target="loginPassword">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <div style="text-align: right; margin-bottom: 15px;">
                        <a href="#" id="forgotPasswordLink" style="color: #888; font-size: 0.85rem; text-decoration: none;" onclick="showForgotPassword(); return false;">
                            Forgot Password?
                        </a>
                    </div>
                    <button type="submit" class="auth-btn" id="loginSubmitBtn">
                        <span id="loginBtnText">Log In</span>
                        <span id="loginBtnLoader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Logging in...
                        </span>
                    </button>
                </form>
                <div class="switch-auth">
                    Not a member? <span onclick="switchForm('signup')">Join Now</span>
                </div>
            </div>

            <div id="signupForm" class="form-box">
                <div class="auth-header">
                    <h2>Become a Member</h2>
                    <p>Start your fitness journey today</p>
                </div>
                <form>
                    <div class="input-group">
                        <input type="text" id="signupName" required>
                        <label>Full Name</label>
                    </div>
                    <div class="input-group">
                        <input type="email" id="signupEmail" required>
                        <label>Email Address</label>
                    </div>
                    <div class="input-group">
                        <input type="password" id="signupPassword" required>
                        <label>Create Password</label>
                        <span class="toggle-password" data-target="signupPassword">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <button type="submit" class="auth-btn" id="signupSubmitBtn">
                        <span id="signupBtnText">Sign Up</span>
                        <span id="signupBtnLoader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Signing up...
                        </span>
                    </button>
                </form>
                <div class="switch-auth">
                    Already a member? <span onclick="switchForm('login')">Log In</span>
                </div>
            </div>

            <div id="otpVerificationForm" class="form-box">
                <div class="auth-header">
                    <h2>Verify Your Email</h2>
                    <p>We've sent a 6-digit code to <strong id="otpEmailDisplay"></strong></p>
                </div>
                <form>
                    <div class="otp-input-group">
                        <input type="text" id="otpInput1" maxlength="1" pattern="[0-9]" required>
                        <input type="text" id="otpInput2" maxlength="1" pattern="[0-9]" required>
                        <input type="text" id="otpInput3" maxlength="1" pattern="[0-9]" required>
                        <input type="text" id="otpInput4" maxlength="1" pattern="[0-9]" required>
                        <input type="text" id="otpInput5" maxlength="1" pattern="[0-9]" required>
                        <input type="text" id="otpInput6" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <div style="margin-top: 20px; text-align: center;">
                        <p style="color: #888; font-size: 0.85rem; margin-bottom: 12px;">
                            Didn't receive the code? 
                            <span id="resendOtp" style="color: var(--primary); cursor: pointer; text-decoration: underline; font-weight: 600;" onclick="resendOTP()">Resend Code</span>
                            <span id="resendCooldown" style="color: #888; display: none; margin-left: 5px;"></span>
                        </p>
                        <p id="otpTimer" style="color: #888; font-size: 0.85rem;"></p>
                    </div>
                    <button type="submit" class="auth-btn">Verify Email</button>
                </form>
                <div class="switch-auth">
                    <span onclick="backToSignup()" style="cursor: pointer;">← Back to Sign Up</span>
                </div>
            </div>

        </div>
    </div>

    <script src="assets/js/main.js"></script>

</body>
</html>
