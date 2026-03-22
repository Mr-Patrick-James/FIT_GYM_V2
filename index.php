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

    // Featured plans removed - no longer displayed on landing page

    // --- AUTO-SETUP WHO PLAN ---
    // Check if WHO plan exists, if not, create it
    $whoCheck = $conn->query("SELECT id FROM packages WHERE name = 'WHO Health & Fitness Plan'");
    if ($whoCheck && $whoCheck->num_rows === 0) {
        // Create WHO Package
        $who_name = "WHO Health & Fitness Plan";
        $who_duration = "Weekly (WHO Standard)";
        $who_price = 450.00;
        $who_tag = "Health Standard";
        $who_desc = "Scientifically designed plan based on WHO (World Health Organization) physical activity guidelines for adults. Focuses on 150-300 minutes of moderate aerobic activity and 2+ days of strength training per week.";
        
        $stmt = $conn->prepare("INSERT INTO packages (name, duration, price, tag, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssdss", $who_name, $who_duration, $who_price, $who_tag, $who_desc);
        $stmt->execute();
        $whoId = $conn->insert_id;
        
        if ($whoId) {
            // Get exercise IDs
            $ex_ids = [];
            $res = $conn->query("SELECT id, name FROM exercises");
            while($row = $res->fetch_assoc()) {
                $ex_ids[$row['name']] = $row['id'];
            }
            
            // WHO-compliant assignments
            $assignments = [
                ['Treadmill Jogging', 1, '30 mins (Aerobic)'],
                ['Stationary Cycling', 1, '20 mins (Aerobic)'],
                ['Smith Machine Squat', 3, '12-15 (Strength)'],
                ['Flat Barbell Bench Press', 3, '12-15 (Strength)'],
                ['Wide-grip Lat Pulldown', 3, '12-15 (Strength)'],
                ['Hanging Leg Raise', 3, '15-20 (Core)'],
                ['Kettlebell Swing', 3, '20 (Full Body)']
            ];
            
            $stmt_ex = $conn->prepare("INSERT INTO package_exercises (package_id, exercise_id, sets, reps, notes) VALUES (?, ?, ?, ?, 'WHO Standard')");
            foreach ($assignments as $a) {
                if (isset($ex_ids[$a[0]])) {
                    $ex_id = $ex_ids[$a[0]];
                    $stmt_ex->bind_param("iiis", $whoId, $ex_id, $a[1], $a[2]);
                    $stmt_ex->execute();
                }
            }
            // Refresh featured plans by reloading the page once
            header("Location: index.php");
            exit();
        }
    }
    // --- END AUTO-SETUP WHO PLAN ---
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
    <link rel="stylesheet" href="assets/css/style.css?v=1.3">
</head>
<body>

    <header>
        <div class="logo-circle">
            <?php 
            $gymName = getSetting('gym_name', 'MARTINEZ GYM', $settings);
            $nameParts = explode(' ', $gymName);
            echo htmlspecialchars($nameParts[0]);
            if (isset($nameParts[1])) echo '<br>' . htmlspecialchars($nameParts[1]);
            ?>
        </div>
        <div class="nav-group">
            <nav>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#packages">Packages</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
            <button class="login-btn-nav" onclick="openModal('login')">Login</button>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-slider">
            <?php 
            $heroImagesJson = $settings['hero_images'] ?? '[]';
            $heroImages = json_decode($heroImagesJson, true);
            
            // If the key is not in settings, or the array is empty, we show nothing (solid color background via CSS)
            if (!empty($heroImages)) {
                foreach ($heroImages as $index => $image) {
                    $activeClass = ($index === 0) ? 'active' : '';
                    // Ensure path is relative to root or absolute
                    $imageUrl = (strpos($image, 'http') === 0) ? $image : $image . '?v=' . time();
                    echo '<div class="slide ' . $activeClass . '" style="background-image: url(\'' . htmlspecialchars($imageUrl) . '\')"></div>';
                }
            }
            ?>
        </div>
        <div class="hero-overlay"></div>
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
                        $gallery = [
                            'assets/uploads/gym/IMG_20230402_094207.webp',
                            'assets/uploads/gym/IMG_20230402_094327.webp',
                            'assets/uploads/gym/IMG_20230402_094345.webp',
                            'assets/uploads/gym/received_1255449418403705.webp',
                            'assets/uploads/gym/received_243674931470652.webp',
                            'assets/uploads/gym/received_252530697123903.webp',
                            'assets/uploads/gym/received_253763833748985.webp',
                            'assets/uploads/gym/received_3639542122989348.webp',
                            'assets/uploads/gym/received_618114340334349.webp',
                        ];
                    }
                    ?>
                    <div class="slider-container" id="aboutSlider">
                        <?php foreach ($gallery as $index => $imagePath): ?>
                            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Martinez Fitness Gym">
                            </div>
                        <?php endforeach; ?>

                        <button class="slider-btn prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
                        <button class="slider-btn next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>

                        <div class="slider-nav">
                            <?php foreach ($gallery as $index => $imagePath): ?>
                                <div class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></div>
                            <?php endforeach; ?>
                        </div>

                        <div class="slide-counter">
                            <span id="slideCurrentNum">1</span> / <span><?php echo count($gallery); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Join Section -->
    <section class="why-section">
        <div class="container">
            <div class="why-grid">
                <div class="why-text">
                    <span class="section-eyebrow">Why Choose Us</span>
                    <h2 class="section-title">Built to Push<br>You Further</h2>
                    <p class="section-subtitle">More than a gym — a community where every rep counts and every goal matters.</p>
                    <button class="main-cta" style="margin-top:36px;" onclick="openModal('signup')">
                        Start Today <div class="icon-circle"><i class="fa-solid fa-arrow-right"></i></div>
                    </button>
                </div>
                <div class="why-features">
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-dumbbell"></i></div>
                        <div>
                            <h4>Complete Equipment</h4>
                            <p>Free weights, machines, cardio — everything you need under one roof in Naujan, Oriental Mindoro.</p>
                        </div>
                    </div>
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-user-tie"></i></div>
                        <div>
                            <h4>Expert Trainers</h4>
                            <p>Our certified coaches build personalized programs tailored to your body and your goals.</p>
                        </div>
                    </div>
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-star"></i></div>
                        <div>
                            <h4>5-Star Rated</h4>
                            <p>Consistently rated 5 stars by our members on Google. Real results, real community.</p>
                        </div>
                    </div>
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-clock"></i></div>
                        <div>
                            <h4>Open Daily</h4>
                            <p>We're open every day so your schedule never gets in the way of your progress.</p>
                        </div>
                    </div>
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div>
                            <h4>Online Booking</h4>
                            <p>Book sessions, track progress, and manage your membership right from this platform.</p>
                        </div>
                    </div>
                    <div class="why-item">
                        <div class="why-icon"><i class="fas fa-heart"></i></div>
                        <div>
                            <h4>Supportive Community</h4>
                            <p>A welcoming environment for all fitness levels — beginners to advanced athletes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow">Reviews &bull; 5.0 ★★★★★</span>
                <h2 class="section-title">What Members Say</h2>
            </div>
            <div class="testimonials-grid" id="testimonialsGrid">
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"What I like about this gym is mura sya unlike other gyms na 50, 60 minsan nga 100, sa gym ng Martinez kasi 35 lang which is so okay para sakin and sa mga beginners also. Sa mga equipment, goods denn, malinis and quality din. Hoping na mag dadagdag kayu ng equipments para mas mapaganda yung gym."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar"><img src="assets/testimonies/img1.jpg" alt="Jeux Burn Martinez" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
                        <div>
                            <strong>Jeux Burn Martinez</strong>
                            <span>Gym Member</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"5-star rating from a Local Guide with 82 reviews and 482 photos across Google Maps."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">AU</div>
                        <div>
                            <strong>AU TU MN</strong>
                            <span>Local Guide &bull; 82 reviews</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"One of the gym's loyal members who keeps coming back."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">TS</div>
                        <div>
                            <strong>Tramz Suison</strong>
                            <span>Gym Member</span>
                        </div>
                    </div>
                </div>
                <!-- Hidden extra cards — shown when "See All Reviews" is clicked -->
            </div>
            <div style="text-align:center; margin-top: 40px;">
                <button class="directions-btn" id="seeAllReviewsBtn" onclick="toggleAllReviews()">
                    See All Reviews
                </button>
            </div>
        </div>
    </section>

    <!-- Location & Contact Section -->
    <section class="location-section" id="contact">
        <div class="container">
            <div class="location-grid">
                <div class="location-info">
                    <span class="section-eyebrow">Find Us</span>
                    <h2 class="section-title">Visit the Gym</h2>
                    <div class="contact-items">
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <strong>Address</strong>
                                <p>Apitong Proper B, Naujan,<br>5204 Oriental Mindoro</p>
                            </div>
                        </div>
                    
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-clock"></i></div>
                            <div>
                                <strong>Hours</strong>
                                <p>Open Daily &bull; Closes 10 PM</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-star"></i></div>
                            <div>
                                <strong>Rating</strong>
                                <p>5.0 ★★★★★ on Google Maps</p>
                            </div>
                        </div>
                    </div>
                    <a href="https://maps.app.goo.gl/kiaoEoL586Bnzzi57" target="_blank" class="directions-btn">
                        <i class="fas fa-map-marked-alt"></i> Get Directions
                    </a>
                </div>
                <div class="map-embed">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d241.5!2d121.1975408!3d13.2357103!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bc9551816b9149%3A0x15997831d4a777f1!2sMartinez%20Fitness%20Gym!5e0!3m2!1sen!2sph!4v1"
                        width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
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
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
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
                    <p>We've sent a 6-digit code to <strong id="otpEmailDisplay"></strong></p>
                </div>
                <form id="otpFormElement">
                    <div class="otp-input-group">
                        <input type="text" maxlength="1" id="otpInput1">
                        <input type="text" maxlength="1" id="otpInput2">
                        <input type="text" maxlength="1" id="otpInput3">
                        <input type="text" maxlength="1" id="otpInput4">
                        <input type="text" maxlength="1" id="otpInput5">
                        <input type="text" maxlength="1" id="otpInput6">
                    </div>
                    <button type="submit" class="auth-btn">Verify Code</button>
                </form>
                <div class="resend-otp">
                    Didn't receive code? <span id="resendOtp" onclick="resendOTP()">Resend</span> <span id="resendCooldown"></span>
                    <p id="otpTimer" style="display: none; font-size: 0.8rem; color: #888; margin-top: 5px;"></p>
                </div>
            </div>
        </div>
    </div>


    <script src="assets/js/main.js?v=1.2"></script>
    <script>
        function toggleAllReviews() {
            const grid = document.getElementById('testimonialsGrid');
            const btn = document.getElementById('seeAllReviewsBtn');
            const hidden = grid.querySelectorAll('.testimonial-card.extra');
            const isExpanded = btn.dataset.expanded === 'true';

            if (isExpanded) {
                hidden.forEach(c => c.style.display = 'none');
                btn.textContent = 'See All Reviews';
                btn.dataset.expanded = 'false';
            } else {
                hidden.forEach(c => c.style.display = 'flex');
                btn.textContent = 'Show Less';
                btn.dataset.expanded = 'true';
            }
        }
    </script>

</body>
</html>
