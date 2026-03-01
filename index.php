<?php
require_once 'api/session.php';
require_once 'api/config.php';

// Fetch active packages for the landing page
$packages = [];
$settings = [];
$activeMemberCount = 0;
try {
    $conn = getDBConnection();
    
    // Fetch packages
    $result = $conn->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }
    
    // Fetch gym settings
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // Fetch real active members count (users with role 'user')
    $countResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $activeMemberCount = $row['total'];
    }

    // Fetch exercises for featured plans
    $featuredPlans = [];
    $planResult = $conn->query("
        SELECT p.id as package_id, p.name as package_name, e.name as exercise_name, e.category, e.image_url, pe.sets, pe.reps 
        FROM packages p
        JOIN package_exercises pe ON p.id = pe.package_id
        JOIN exercises e ON pe.exercise_id = e.id
        WHERE p.is_active = 1
        ORDER BY p.id, e.name
    ");
    if ($planResult) {
        while ($row = $planResult->fetch_assoc()) {
            $featuredPlans[$row['package_id']]['name'] = $row['package_name'];
            $featuredPlans[$row['package_id']]['exercises'][] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching data for index: " . $e->getMessage());
}

// Helper to get setting with fallback
function getSetting($key, $default = '', $settings = []) {
    return $settings[$key] ?? $default;
}

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
            <?php 
            $nameParts = explode(' ', getSetting('gym_name', 'MARTINEZ GYM', $settings));
            echo htmlspecialchars($nameParts[0]);
            if (isset($nameParts[1])) echo '<br>' . htmlspecialchars($nameParts[1]);
            ?>
        </div>
        <div class="nav-group">
            <nav>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#packages">Packages</a></li>
                    <li><a href="#about">About</a></li>
                </ul>
            </nav>
            <button class="login-btn-nav" onclick="openModal('login')">Login</button>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-content">
            <?php 
            $gymName = getSetting('gym_name', 'MARTINEZ Fitness GYM', $settings);
            $nameParts = explode(' ', $gymName);
            ?>
            <h1 class="big-text"><?php echo htmlspecialchars($nameParts[0] ?? 'MARTINEZ'); ?></h1>
            <div class="script-text"><?php echo htmlspecialchars($nameParts[1] ?? 'Fitness'); ?></div>
            <h1 class="big-text"><?php echo htmlspecialchars($nameParts[2] ?? ($nameParts[1] ?? 'GYM')); ?></h1>
            
            <button class="main-cta" onclick="openModal('signup')">
                Join Now 
                <div class="icon-circle"><i class="fa-solid fa-arrow-right"></i></div>
            </button>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section" id="services">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Premium Services</h2>
                <p class="section-subtitle">Beyond just equipment, we provide the tools and plans you need to succeed</p>
            </div>

            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>Personalized Exercise Plans</h3>
                    <p>Every membership includes access to curated exercise plans designed for your specific goals—whether it's muscle gain, weight loss, or endurance.</p>
                    <div class="service-feature-preview">
                        <div class="preview-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Sets & Reps Guidance</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Equipment Instructions</span>
                        </div>
                    </div>
                    <button class="package-btn" onclick="scrollToSection('featured-plans')" style="margin-top: 20px; width: auto; padding: 10px 20px; font-size: 0.8rem;">
                        View Our Plans
                    </button>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Elite Equipment</h3>
                    <p>Train with the best. Our facility features top-of-the-line Smith machines, plate-loaded leg presses, and a comprehensive free-weight area.</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Supportive Community</h3>
                    <p>Join a community of dedicated fitness enthusiasts who motivate each other to reach new heights in a positive, high-energy environment.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Plans Showcase -->
    <section class="featured-plans-section" id="featured-plans">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Membership Workout Plans</h2>
                <p class="section-subtitle">Take a sneak peek at the routines we've prepared for you</p>
            </div>

            <?php if (empty($featuredPlans)): ?>
                <div style="text-align: center; color: #888; padding: 40px;">
                    <i class="fas fa-dumbbell" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <p>Plans are being curated. Join now to get notified!</p>
                </div>
            <?php else: ?>
                <div class="plans-showcase-grid">
                    <?php foreach ($featuredPlans as $pkgId => $plan): ?>
                        <div class="plan-card">
                            <div class="plan-header">
                                <h3 class="plan-title"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <span class="exercise-count"><?php echo count($plan['exercises']); ?> Exercises</span>
                            </div>
                            
                            <div class="plan-exercises-preview">
                                <?php foreach (array_slice($plan['exercises'], 0, 3) as $ex): ?>
                                    <div class="plan-exercise-item">
                                        <div class="ex-thumb">
                                            <?php if ($ex['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars(strpos($ex['image_url'], 'http') === 0 ? $ex['image_url'] : 'assets/uploads/exercises/'.basename($ex['image_url'])); ?>" alt="<?php echo htmlspecialchars($ex['exercise_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ex-info">
                                            <h4><?php echo htmlspecialchars($ex['exercise_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($ex['sets']); ?> Sets × <?php echo htmlspecialchars($ex['reps']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($plan['exercises']) > 3): ?>
                                    <div class="more-exercises">
                                        + <?php echo count($plan['exercises']) - 3; ?> more exercises
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="plan-footer">
                                <button class="view-full-plan-btn" onclick="showHomePlanModal(<?php echo $pkgId; ?>)">
                                    View Full Routine
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="packages-section" id="packages">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Membership Plans</h2>
                <p class="section-subtitle">Choose the perfect plan for your fitness goals</p>
            </div>

            <div class="packages-grid">
                <?php if (empty($packages)): ?>
                    <p>No active packages available at the moment.</p>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <div class="package-card <?php echo $package['tag'] === 'Popular' ? 'popular' : ''; ?>">
                            <?php if ($package['tag']): ?>
                                <span class="package-tag"><?php echo htmlspecialchars($package['tag']); ?></span>
                            <?php endif; ?>
                            <h3 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h3>
                            <div class="package-price">
                                <span class="currency">₱</span>
                                <span class="amount"><?php echo number_format($package['price'], 0); ?></span>
                                <span class="duration">/ <?php echo htmlspecialchars($package['duration']); ?></span>
                            </div>
                            <ul class="package-features">
                                <?php 
                                $description = $package['description'] ?? '';
                                if (!empty($description)) {
                                    // Split by newline and filter out empty lines
                                    $features = array_filter(array_map('trim', explode("\n", $description)));
                                    if (!empty($features)) {
                                        foreach ($features as $feature): ?>
                                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                        <?php endforeach;
                                    } else {
                                        // Fallback if description is just empty spaces
                                        echo '<li><i class="fas fa-check"></i> Full Equipment Access</li>';
                                        echo '<li><i class="fas fa-check"></i> Locker Room Access</li>';
                                        echo '<li><i class="fas fa-check"></i> Expert Guidance</li>';
                                    }
                                } else {
                                    // Default features if no description provided
                                    echo '<li><i class="fas fa-check"></i> Full Equipment Access</li>';
                                    echo '<li><i class="fas fa-check"></i> Locker Room Access</li>';
                                    echo '<li><i class="fas fa-check"></i> Expert Guidance</li>';
                                }
                                ?>
                            </ul>
                            <button class="package-btn" onclick="openModal('signup')">Get Started</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2 class="section-title">About <?php echo htmlspecialchars(getSetting('gym_name', 'Martinez Fitness', $settings)); ?></h2>
                    <p><?php echo htmlspecialchars(getSetting('about_text', 'Martinez Fitness Gym is more than just a place to work out. We are a community dedicated to helping you reach your peak physical condition through elite training, state-of-the-art equipment, and a supportive environment.', $settings)); ?></p>
                    <p><?php echo htmlspecialchars(getSetting('mission_text', 'Founded with the mission to provide high-quality fitness access to everyone, we offer flexible membership plans and expert guidance to ensure you get the most out of every session.', $settings)); ?></p>
                    
                    <div class="stats-mini">
                        <div class="stat-item">
                            <h4><?php echo htmlspecialchars(getSetting('years_experience', '10+', $settings)); ?></h4>
                            <p>Years Experience</p>
                        </div>
                        <div class="stat-item">
                            <h4><?php echo number_format($activeMemberCount); ?>+</h4>
                            <p>Active Members</p>
                        </div>
                        <div class="stat-item">
                            <h4>24/7</h4>
                            <p>Support</p>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <?php 
                    $galleryJson = getSetting('about_images', '[]', $settings);
                    $gallery = json_decode($galleryJson, true);
                    
                    if (empty($gallery)) {
                        $gallery = ['https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=2070&auto=format&fit=crop'];
                    }
                    ?>
                    <div class="slider-container" id="aboutSlider">
                        <?php foreach ($gallery as $index => $imagePath): ?>
                            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars(strpos($imagePath, 'http') === 0 ? $imagePath : $imagePath); ?>" alt="Gym Interior">
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($gallery) > 1): ?>
                            <button class="slider-btn prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="slider-btn next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
                            
                            <div class="slider-nav">
                                <?php foreach ($gallery as $index => $imagePath): ?>
                                    <div class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="logo-circle">
                        <?php 
                        $nameParts = explode(' ', getSetting('gym_name', 'MARTINEZ GYM', $settings));
                        echo htmlspecialchars($nameParts[0]);
                        if (isset($nameParts[1])) echo '<br>' . htmlspecialchars($nameParts[1]);
                        ?>
                    </div>
                    <p><?php echo htmlspecialchars(getSetting('footer_tagline', 'Pushing your limits since 2014. Join the elite fitness community today.', $settings)); ?></p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#about">About</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <ul>
                        <li><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars(getSetting('gym_address', '123 Fitness Ave, Metro Manila', $settings)); ?></li>
                        <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars(getSetting('gym_contact', '+63 917 123 4567', $settings)); ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(getSetting('gym_email', 'info@martinezfitness.com', $settings)); ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('gym_name', 'Martinez Fitness Gym', $settings)); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div class="floating-socials">
        <a href="mailto:<?php echo htmlspecialchars(getSetting('gym_email', 'info@martinezfitness.com', $settings)); ?>" class="social-icon" title="Email Us"><i class="fa-solid fa-envelope"></i></a>
        <a href="https://maps.google.com/?q=<?php echo urlencode(getSetting('gym_address', '123 Fitness Ave, Metro Manila', $settings)); ?>" target="_blank" class="social-icon" title="Our Location"><i class="fa-solid fa-location-dot"></i></a>
        <a href="tel:<?php echo htmlspecialchars(getSetting('gym_contact', '0917-123-4567', $settings)); ?>" class="social-icon" title="Call Us"><i class="fa-solid fa-phone"></i></a>
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
                <form id="signupFormElement">
                    <div class="input-group">
                        <input type="text" id="signupName" name="name" required autocomplete="name">
                        <label>Full Name</label>
                    </div>
                    <div class="input-group">
                        <input type="email" id="signupEmail" name="email" required autocomplete="email">
                        <label>Email Address</label>
                    </div>
                    <div class="input-group">
                        <input type="password" id="signupPassword" name="password" required autocomplete="new-password">
                        <label>Create Password</label>
                        <span class="toggle-password" data-target="signupPassword">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <div class="input-group">
                        <input type="password" id="signupConfirmPassword" name="confirm_password" required autocomplete="new-password">
                        <label>Confirm Password</label>
                        <span class="toggle-password" data-target="signupConfirmPassword">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <button type="submit" class="auth-btn" id="signupSubmitBtn">
                        <span id="signupBtnText">Create Account</span>
                        <span id="signupBtnLoader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Sending OTP...
                        </span>
                    </button>
                </form>
                <div class="switch-auth">
                    Already have an account? <span onclick="switchForm('login')">Log In</span>
                </div>
            </div>

            <div id="otpVerificationForm" class="form-box">
                <div class="auth-header">
                    <h2>Verify Your Email</h2>
                    <p>We've sent a 6-digit code to <strong id="displayEmail"></strong></p>
                </div>
                <div class="otp-inputs">
                    <input type="text" maxlength="1" id="otpInput1" onkeyup="moveFocus(this, 'otpInput2')">
                    <input type="text" maxlength="1" id="otpInput2" onkeyup="moveFocus(this, 'otpInput3')">
                    <input type="text" maxlength="1" id="otpInput3" onkeyup="moveFocus(this, 'otpInput4')">
                    <input type="text" maxlength="1" id="otpInput4" onkeyup="moveFocus(this, 'otpInput5')">
                    <input type="text" maxlength="1" id="otpInput5" onkeyup="moveFocus(this, 'otpInput6')">
                    <input type="text" maxlength="1" id="otpInput6">
                </div>
                <button class="auth-btn" onclick="verifyOTP()">Verify Code</button>
                <div class="resend-otp">
                    Didn't receive code? <span id="resendBtn" onclick="resendOTP()">Resend</span>
                    <p id="resendTimer" style="display: none; font-size: 0.8rem; color: #888; margin-top: 5px;"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Exercise Plan Preview Modal -->
    <div class="modal-overlay" id="homePlanModal">
        <div class="auth-card" style="max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 0;">
            <button class="close-modal" onclick="closeHomePlanModal()" style="z-index: 100;"><i class="fa-solid fa-xmark"></i></button>
            
            <div id="homePlanContent">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const featuredPlans = <?php echo json_encode($featuredPlans); ?>;
    </script>
    <script src="assets/js/main.js?v=1.2"></script>

</body>
</html>
