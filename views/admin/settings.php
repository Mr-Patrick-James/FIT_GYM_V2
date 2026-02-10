<?php
require_once '../../api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
    <!-- Apply theme immediately before page renders to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
                if (document.body) {
                    document.body.classList.add('light-mode');
                }
            } else {
                document.documentElement.classList.remove('light-mode');
                if (document.body) {
                    document.body.classList.remove('light-mode');
                }
            }
        })();
    </script>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <h1>FitPay</h1>
            <p>GYM MANAGEMENT</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="report.php"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $adminName = $user['name'] ?? 'Admin';
                $initials = '';
                foreach(explode(' ', $adminName) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($adminName); ?></h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Settings</h1>
                <p>Manage your gym's configuration and preferences</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Settings Container -->
        <div class="settings-container" style="display: grid; grid-template-columns: 240px 1fr; gap: 32px; margin-top: 32px; margin-bottom: 32px;">
            <!-- Settings Sidebar -->
            <div class="settings-sidebar">
                <div class="settings-nav">
                    <button class="settings-nav-item active" onclick="showSettingsTab('general')" id="nav-general">
                        <i class="fas fa-cog"></i>
                        <span>General</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSettingsTab('payment')" id="nav-payment">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payment</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSettingsTab('notifications')" id="nav-notifications">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSettingsTab('appearance')" id="nav-appearance">
                        <i class="fas fa-paint-brush"></i>
                        <span>Appearance</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSettingsTab('landing')" id="nav-landing">
                        <i class="fas fa-home"></i>
                        <span>Landing Page</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSettingsTab('account')" id="nav-account">
                        <i class="fas fa-user"></i>
                        <span>Account</span>
                    </button>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">

                <!-- General Settings -->
                <div id="settings-general" class="settings-section active">
                    <div class="settings-section-header">
                        <div>
                            <h2>General</h2>
                            <p>Configure your gym's basic information and preferences</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Gym Name</label>
                                <span class="settings-hint">The name displayed to members</span>
                            </div>
                            <input type="text" id="gymName" class="settings-input" placeholder="Martinez Fitness Gym">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Gym Address</label>
                                <span class="settings-hint">Physical location of your gym</span>
                            </div>
                            <textarea id="gymAddress" class="settings-input" rows="3" placeholder="Enter gym address..."></textarea>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Contact Number</label>
                                <span class="settings-hint">Primary contact number</span>
                            </div>
                            <input type="tel" id="gymContact" class="settings-input" placeholder="0917-123-4567">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Email Address</label>
                                <span class="settings-hint">Contact email for inquiries</span>
                            </div>
                            <input type="email" id="gymEmail" class="settings-input" placeholder="info@martinezfitness.com">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Operating Hours</label>
                                <span class="settings-hint">Gym opening and closing times</span>
                            </div>
                            <div class="settings-time-group">
                                <div class="settings-time-input">
                                    <label>Opening Time</label>
                                    <input type="time" id="openingTime" class="settings-input" value="06:00">
                                </div>
                                <div class="settings-time-input">
                                    <label>Closing Time</label>
                                    <input type="time" id="closingTime" class="settings-input" value="22:00">
                                </div>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Timezone</label>
                                <span class="settings-hint">Time zone for scheduling and reports</span>
                            </div>
                            <select id="timezone" class="settings-input">
                                <option value="Asia/Manila" selected>Asia/Manila (PHT)</option>
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York (EST)</option>
                            </select>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="saveGeneralSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div id="settings-payment" class="settings-section" style="display: none;">
                    <div class="settings-section-header">
                        <div>
                            <h2>Payment</h2>
                            <p>Configure payment methods and verification settings</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>GCash Number</label>
                                <span class="settings-hint">Mobile number for GCash payments</span>
                            </div>
                            <input type="tel" id="gcashNumber" class="settings-input" placeholder="0917-123-4567">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>GCash Account Name</label>
                                <span class="settings-hint">Name associated with the GCash account</span>
                            </div>
                            <input type="text" id="gcashName" class="settings-input" placeholder="Martinez Fitness">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>GCash QR Code</label>
                                <span class="settings-hint">Upload your GCash QR code image for users to scan</span>
                            </div>
                            <div class="qr-upload-container" style="display: flex; align-items: flex-start; gap: 20px;">
                                <div id="qr-preview" class="qr-preview" style="width: 150px; height: 150px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--bg-secondary);">
                                    <i class="fas fa-qrcode" style="font-size: 48px; color: var(--text-muted);"></i>
                                </div>
                                <div class="qr-upload-actions">
                                    <input type="file" id="gcashQR" accept="image/*" style="display: none;" onchange="previewQR(this)">
                                    <button class="btn btn-secondary" onclick="document.getElementById('gcashQR').click()">
                                        <i class="fas fa-upload"></i> Upload Image
                                    </button>
                                    <p class="settings-hint" style="margin-top: 8px;">Recommended: Square image, max 5MB</p>
                                </div>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Payment Instructions</label>
                                <span class="settings-hint">Instructions shown to members when making payments</span>
                            </div>
                            <textarea id="paymentInstructions" class="settings-input" rows="4" placeholder="Enter payment instructions for members..."></textarea>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="savePaymentSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div id="settings-notifications" class="settings-section" style="display: none;">
                    <div class="settings-section-header">
                        <div>
                            <h2>Notifications</h2>
                            <p>Manage how and when you receive notifications</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-subsection">
                            <h3>Email Notifications</h3>
                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>New booking requests</label>
                                    <span class="settings-hint">Receive email when members submit bookings</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="emailNewBooking" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Payment verifications</label>
                                    <span class="settings-hint">Receive email when payments are verified</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="emailPaymentVerified" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Daily summary reports</label>
                                    <span class="settings-hint">Receive daily email with gym statistics</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="emailDailyReport">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-subsection">
                            <h3>Browser Notifications</h3>
                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>New booking requests</label>
                                    <span class="settings-hint">Show browser notification for new bookings</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="browserNewBooking" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Payment verifications</label>
                                    <span class="settings-hint">Show browser notification for verified payments</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="browserPaymentVerified" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-subsection">
                            <h3>Sound & Alerts</h3>
                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Notification Sound</label>
                                    <span class="settings-hint">Play sound when new notifications arrive</span>
                                </div>
                                <div class="settings-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notificationSound" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="saveNotificationSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div id="settings-appearance" class="settings-section" style="display: none;">
                    <div class="settings-section-header">
                        <div>
                            <h2>Appearance</h2>
                            <p>Customize the look and feel of your dashboard</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Theme</label>
                                <span class="settings-hint">Choose between light and dark mode</span>
                            </div>
                            <div class="settings-theme-selector">
                                <button class="theme-option" data-theme="dark" onclick="setTheme('dark')">
                                    <i class="fas fa-moon"></i>
                                    <span>Dark</span>
                                </button>
                                <button class="theme-option" data-theme="light" onclick="setTheme('light')">
                                    <i class="fas fa-sun"></i>
                                    <span>Light</span>
                                </button>
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="saveAppearanceSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Landing Page Settings -->
                <div id="settings-landing" class="settings-section" style="display: none;">
                    <div class="settings-section-header">
                        <div>
                            <h2>Landing Page</h2>
                            <p>Customize the content of your public landing page</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>About Us Text</label>
                                <span class="settings-hint">The main description in the About section</span>
                            </div>
                            <textarea id="aboutText" class="settings-input" rows="4" placeholder="Enter gym description..."></textarea>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Our Mission</label>
                                <span class="settings-hint">Your gym's mission statement</span>
                            </div>
                            <textarea id="missionText" class="settings-input" rows="3" placeholder="Enter mission statement..."></textarea>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Years of Experience</label>
                                <span class="settings-hint">Displayed in the stats section (e.g., 10+)</span>
                            </div>
                            <input type="text" id="yearsExperience" class="settings-input" placeholder="10+">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Gym Interior Gallery</label>
                                <span class="settings-hint">Multiple images for the About section slider</span>
                            </div>
                            <div class="gallery-container">
                                <div class="gallery-grid" id="about-gallery-grid">
                                    <!-- Gallery items will be injected here by JS -->
                                    <div class="gallery-add-btn" onclick="document.getElementById('aboutImageInput').click()">
                                        <i class="fas fa-plus"></i>
                                        <span>Add Image</span>
                                    </div>
                                </div>
                                <input type="file" id="aboutImageInput" accept="image/*" multiple style="display: none;" onchange="handleGalleryUpload(this)">
                                <p class="file-info" style="margin-top: 10px;">Recommended size: 800x600px. Max 5MB per image.</p>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Footer Tagline</label>
                                <span class="settings-hint">Brief description shown in the footer</span>
                            </div>
                            <input type="text" id="footerTagline" class="settings-input" placeholder="Pushing your limits since 2014...">
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="saveLandingSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div id="settings-account" class="settings-section" style="display: none;">
                    <div class="settings-section-header">
                        <div>
                            <h2>Account</h2>
                            <p>Manage your admin account information and security</p>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Admin Name</label>
                                <span class="settings-hint">Your display name</span>
                            </div>
                            <input type="text" id="adminName" class="settings-input" placeholder="Admin Martinez">
                        </div>

                        <div class="settings-item">
                            <div class="settings-item-label">
                                <label>Email Address</label>
                                <span class="settings-hint">Your admin email address</span>
                            </div>
                            <input type="email" id="adminEmail" class="settings-input" placeholder="admin@martinezfitness.com">
                        </div>

                        <div class="settings-subsection">
                            <h3>Change Password</h3>
                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Current Password</label>
                                    <span class="settings-hint">Enter your current password</span>
                                </div>
                                <input type="password" id="currentPassword" class="settings-input" placeholder="Enter current password">
                            </div>

                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>New Password</label>
                                    <span class="settings-hint">Must be at least 6 characters</span>
                                </div>
                                <input type="password" id="newPassword" class="settings-input" placeholder="Enter new password">
                            </div>

                            <div class="settings-item">
                                <div class="settings-item-label">
                                    <label>Confirm New Password</label>
                                    <span class="settings-hint">Re-enter your new password</span>
                                </div>
                                <input type="password" id="confirmPassword" class="settings-input" placeholder="Confirm new password">
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" onclick="saveAccountSettings()">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="settings-danger-zone">
                        <div class="settings-section-header">
                            <div>
                                <h2 style="color: var(--danger);">Danger Zone</h2>
                                <p>Irreversible and destructive actions</p>
                            </div>
                        </div>

                        <div class="settings-group">
                            <div class="settings-danger-item">
                                <div>
                                    <h4>Clear All Data</h4>
                                    <p>Permanently delete all bookings, payments, and member data. This action cannot be undone.</p>
                                </div>
                                <button class="btn btn-danger" onclick="clearAllData()">
                                    <i class="fas fa-trash"></i>
                                    Clear All Data
                                </button>
                            </div>

                            <div class="settings-danger-item">
                                <div>
                                    <h4>Reset Settings</h4>
                                    <p>Reset all settings to their default values. Your data will not be affected.</p>
                                </div>
                                <button class="btn btn-warning" onclick="resetSettings()">
                                    <i class="fas fa-undo"></i>
                                    Reset Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer" style="margin-top: 48px;">
            <p>
                <i class="fas fa-heart" style="color: var(--primary);"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0
                <i class="fas fa-bolt" style="color: var(--primary);"></i>
            </p>
        </div>
    </main>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Settings Scripts -->
    <script src="../../assets/js/settings.js"></script>
</body>
</html>
