<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireLogin();
$user = getCurrentUser();

// Fetch gym settings
$settings = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Helper to get setting with fallback
function getSetting($key, $default = '', $settings = []) {
    return $settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | <?php echo htmlspecialchars(getSetting('gym_name', 'Martinez Fitness', $settings)); ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/user-dashboard/base.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/dashboard.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/packages.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/bookings.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/payments.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/profile.css?v=1.6">

    <!-- FullCalendar CDN for user calendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <style>
        /* Survey & Recommendation Modal Styles */
        .survey-modal .modal, .recommendation-modal .modal {
            max-width: 600px;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            animation: modalFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .survey-header, .recommendation-header {
            padding: 40px 40px 20px;
            text-align: center;
        }

        .survey-header i, .recommendation-header i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.3));
        }

        .survey-header h2, .recommendation-header h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #888 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .survey-header p, .recommendation-header p {
            color: var(--dark-text-secondary);
            font-size: 1rem;
        }

        .survey-body, .recommendation-body {
            padding: 0 40px 40px;
        }

        .survey-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .survey-step.active {
            display: block;
        }

        .question-label {
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: #fff;
            text-align: center;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--dark-border);
            padding: 24px 16px;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .option-card i {
            font-size: 1.5rem;
            color: var(--dark-text-secondary);
            transition: var(--transition);
        }

        .option-card span {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .option-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: #666;
            transform: translateY(-4px);
        }

        .option-card.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--dark-bg);
        }

        .option-card.selected i {
            color: var(--dark-bg);
        }

        .survey-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: var(--dark-border);
            border-radius: 3px;
            margin-right: 24px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 33%;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .survey-nav-btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-next {
            background: var(--primary);
            color: var(--dark-bg);
        }

        .btn-next:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .btn-next:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Recommendation Specific */
        .recommended-package-preview {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--primary);
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .recommended-package-preview h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .recommended-package-preview .price {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .recommended-package-preview .duration {
            color: var(--dark-text-secondary);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .recommended-actions {
            display: flex;
            gap: 16px;
        }

        .recommended-actions button {
            flex: 1;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* User Calendar View */
        .user-view-toggle {
            display: flex;
            background: var(--dark-card);
            padding: 4px;
            border-radius: var(--radius-md);
            border: 1px solid var(--dark-border);
            margin-left: auto;
            gap: 6px;
        }
        .user-view-btn {
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--dark-text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .user-view-btn.active {
            background: var(--primary);
            color: var(--dark-bg);
        }
        #userCalendarView {
            display: none;
            margin: 16px 24px 24px;
            background: var(--dark-card);
            border-radius: var(--radius-lg);
            padding: 12px;
            border: 1px solid var(--dark-border);
        }
        /* FullCalendar Dark Theme Tweaks */
        .fc {
            --fc-border-color: var(--dark-border);
            --fc-daygrid-event-dot-width: 8px;
            --fc-neutral-bg-color: var(--dark-card);
            --fc-page-bg-color: var(--dark-card);
            --fc-today-bg-color: rgba(255, 255, 255, 0.05);
            font-family: 'Inter', sans-serif;
        }
        .fc .fc-toolbar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-text);
        }
        .fc .fc-button-primary {
            background-color: var(--dark-card);
            border-color: var(--dark-border);
            color: var(--dark-text);
            font-weight: 600;
            padding: 6px 12px;
        }
        .fc .fc-button-primary:hover {
            background-color: var(--dark-border);
            border-color: var(--dark-text-secondary);
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--dark-bg);
        }
        .fc-theme-standard td, .fc-theme-standard th {
            border: 1px solid var(--dark-border);
        }
        .event-status-pending { background-color: var(--warning) !important; color: #000 !important; }
        .event-status-verified { background-color: var(--success) !important; color: #fff !important; }
        .event-status-rejected { background-color: #ef4444 !important; color: #fff !important; }
    </style>
</head>
<body class="dark-mode">
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <?php 
                $gymName = getSetting('gym_name', 'MARTINEZ FITNESS GYM', $settings);
                $nameParts = explode(' ', $gymName);
            ?>
            <h1><?php echo htmlspecialchars($nameParts[0]); ?></h1>
            <p><?php echo htmlspecialchars(implode(' ', array_slice($nameParts, 1)) ?: 'FITNESS GYM'); ?></p>
        </div>
        
        <ul class="nav-links">
            <li><a href="#" class="active" onclick="showSection('dashboard', event)"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="#" onclick="showSection('packages', event)"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="#" onclick="showSection('bookings', event)"><i class="fas fa-calendar-check"></i> <span>My Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="#" onclick="showSection('payments', event)"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="#" onclick="showSection('profile', event)"><i class="fas fa-user"></i> <span>Profile</span></a></li>
        </ul>
        
        <div class="user-profile">
            <div class="user-avatar" id="userAvatar">JD</div>
            <div class="user-info">
                <div class="user-name-wrapper">
                    <h4 id="userName">Juan Dela Cruz</h4>
                    <span id="sidebarMemberBadge" class="member-badge" style="display: none;" title="Active Member">
                        <i class="fas fa-crown"></i>
                    </span>
                </div>
                <p id="userEmail">juan.delacruz@email.com</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1 id="pageTitle">Dashboard</h1>
                <p id="pageSubtitle">Welcome back! Manage your gym membership and bookings</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn notification-btn" onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationCount">2</span>
                </button>
                
                <button class="action-btn" onclick="logout()" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboardSection" class="content-section active">
            <div class="dashboard-grid-layout">
                <div class="dashboard-main-col">
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="activeBookingsCount">0</div>
                            <div class="stat-label">Active Bookings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="pendingBookingsCount">0</div>
                            <div class="stat-label">Pending Verifications</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="verifiedBookingsCount">0</div>
                            <div class="stat-label">Verified Bookings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="membershipStatus">None</div>
                            <div class="stat-label">Membership Status</div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="quick-actions-grid">
                            <button class="quick-action-card" onclick="showSection('packages', event)">
                                <i class="fas fa-dumbbell"></i>
                                <h4>Browse Packages</h4>
                                <p>View available membership plans</p>
                            </button>
                            <button class="quick-action-card" onclick="openBookingModal()">
                                <i class="fas fa-plus-circle"></i>
                                <h4>New Booking</h4>
                                <p>Create a new booking request</p>
                            </button>
                            <button class="quick-action-card" onclick="showSection('bookings', event)">
                                <i class="fas fa-list"></i>
                                <h4>View Bookings</h4>
                                <p>Check your booking status</p>
                            </button>
                            <button class="quick-action-card" onclick="showSection('payments', event)">
                                <i class="fas fa-receipt"></i>
                                <h4>Payment History</h4>
                                <p>View past transactions</p>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dashboard-side-col">
                    <!-- GCash QR Code Card -->
                    <div class="content-card gcash-qr-card">
                        <div class="card-header">
                            <h3><i class="fas fa-wallet" style="color: #22c55e;"></i> GCash Payment</h3>
                        </div>
                        <div class="qr-container-dash">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:09171234567" alt="GCash QR Code" class="qr-image-dash">
                        </div>
                        <div class="qr-info-dash">
                            <p><strong>Account Name:</strong> Martinez Fitness</p>
                            <p><strong>GCash Number:</strong> 0917-123-4567</p>
                            <span class="qr-instruction-dash">Scan this QR code using your GCash app to pay for your membership.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="content-card" style="margin-top: 32px;">
                <div class="card-header">
                    <h3>Recent Bookings</h3>
                    <button class="card-btn" onclick="showSection('bookings', event)">
                        <span>View All</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentBookingsTable">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);" data-label="Info">
                                    No bookings yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Packages Section -->
        <div id="packagesSection" class="content-section">
            <div class="content-card">
                <div class="card-header">
                    <h3>Available Packages</h3>
                    <p style="color: var(--dark-text-secondary);">Choose a membership plan that fits your fitness goals</p>
                </div>
                <div class="packages-grid" id="packagesGrid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Bookings Section -->
        <div id="bookingsSection" class="content-section">
            <div class="content-card">
                <div class="card-header">
                    <h3>My Bookings</h3>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="user-view-toggle">
                            <button class="user-view-btn active" id="userTableViewBtn" title="Table View">
                                <i class="fas fa-table"></i>
                                <span>Table</span>
                            </button>
                            <button class="user-view-btn" id="userCalendarViewBtn" title="Calendar View">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Calendar</span>
                            </button>
                        </div>
                        <button class="card-btn primary" onclick="openBookingModal()">
                            <i class="fas fa-plus"></i>
                            <span>New Booking</span>
                        </button>
                    </div>
                </div>
                <div id="userCalendarView">
                    <div id="userCalendar"></div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsTable">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payments Section -->
        <div id="paymentsSection" class="content-section">
            <div class="content-card">
                <div class="card-header">
                    <h3>Payment History</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTable">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profileSection" class="content-section">
            <div class="profile-layout-grid">
                <!-- Left Column: User Profile Info -->
                <div class="profile-main-col">
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-circle" style="margin-right: 12px; color: var(--primary);"></i>Profile Information</h3>
                        </div>
                        <div class="profile-form">
                            <!-- Membership Status Badge -->
                            <div id="profileMembershipBadge" class="membership-badge-container" style="display: none;">
                                <div class="membership-status-card">
                                    <div class="membership-icon">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <div class="membership-details">
                                        <span class="membership-label">MEMBERSHIP STATUS</span>
                                        <div class="membership-value-row">
                                            <h4 id="profileMembershipValue">Active Member</h4>
                                            <span class="status-badge status-verified" id="profileMembershipStatus">Active</span>
                                        </div>
                                        <p id="profileMembershipPlan">Monthly Membership Plan</p>
                                        <div class="membership-expiry" id="profileMembershipExpiryRow" style="margin-top: 8px; font-size: 0.85rem; color: var(--dark-text-secondary);">
                                            <i class="far fa-calendar-alt" style="margin-right: 6px;"></i>
                                            <span>Expires on: <strong id="profileMembershipExpiryDate" style="color: var(--primary);">--</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" id="profileName" value="Juan Dela Cruz" placeholder="Enter your full name">
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" id="profileEmail" value="juan.delacruz@email.com" placeholder="Enter your email">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="tel" id="profileContact" value="0917-123-4567" placeholder="09XX-XXX-XXXX">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Address</label>
                                <textarea id="profileAddress" rows="3" placeholder="Enter your complete address">Manila, Philippines</textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button class="btn btn-primary" id="updateProfileBtn" onclick="updateProfile()">
                                    <i class="fas fa-save"></i>
                                    <span>Save Profile Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Security & Payment Info -->
                <div class="profile-side-col">
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt" style="margin-right: 12px; color: var(--primary);"></i>Security</h3>
                        </div>
                        <div class="security-form" style="padding: 28px;">
                            <p style="color: var(--dark-text-secondary); margin-bottom: 24px; font-size: 0.9rem; line-height: 1.5;">
                                Ensure your account is secure by using a strong password.
                            </p>
                            <form id="changePasswordForm" onsubmit="changePassword(event)">
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="currentPassword" required placeholder="••••••••">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="newPassword" required minlength="6" placeholder="At least 6 characters">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="confirmNewPassword" required minlength="6" placeholder="Repeat new password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-secondary" id="changePasswordBtn" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-key"></i>
                                    <span>Update Password</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-qrcode" style="margin-right: 12px; color: var(--primary);"></i>GCash Payment</h3>
                        </div>
                        <div class="gcash-info">
                            <div class="qr-container">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:09171234567" alt="GCash QR Code" style="width: 100%; height: 100%; object-fit: contain;">
                            </div>
                            <div class="qr-info">
                                <div class="payment-detail-item">
                                    <span class="detail-label">ACCOUNT NAME</span>
                                    <span class="detail-value">Martinez Fitness</span>
                                </div>
                                <div class="payment-detail-item">
                                    <span class="detail-label">GCASH NUMBER</span>
                                    <span class="detail-value">0917-123-4567</span>
                                </div>
                                <p style="margin-top: 20px; font-size: 0.85rem; color: var(--dark-text-secondary); line-height: 1.4;">
                                    Scan to pay for bookings. Don't forget to save your receipt for verification!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Booking Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Create New Booking</h3>
                <button class="close-modal" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-layout">
                    <div class="modal-form-col">
                        <form id="bookingForm" onsubmit="submitBooking(event)">
                            <div class="form-group">
                                <label>Select Package <span style="color: var(--warning);">*</span></label>
                                <select id="bookingPackage" required>
                                    <option value="">Choose a package...</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Booking Date <span style="color: var(--warning);">*</span></label>
                                <input type="date" id="bookingDate" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Number <span style="color: var(--warning);">*</span></label>
                                <input type="tel" id="bookingContact" placeholder="0917-123-4567" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Receipt (GCash) <span style="color: var(--warning);">*</span></label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload or drag and drop</p>
                                    <span>PNG, JPG, PDF up to 5MB</span>
                                    <input type="file" id="receiptFile" accept="image/*,.pdf" required style="display: none;" onchange="handleFileSelect(event)">
                                </div>
                                <div id="filePreview" style="display: none; margin-top: 16px;">
                                    <div class="file-preview-item">
                                        <i class="fas fa-file-image"></i>
                                        <span id="fileName"></span>
                                        <button type="button" onclick="removeFile()" class="remove-file-btn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Additional Notes (Optional)</label>
                                <textarea id="bookingNotes" rows="3" placeholder="Any special requests or notes..."></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeBookingModal()">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i>
                                    <span>Submit Booking</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="modal-side-col">
                        <div class="payment-instruction-card">
                            <h4><i class="fas fa-info-circle"></i> Payment Instructions</h4>
                            <p>1. Scan the QR code below using your GCash app.</p>
                            <p>2. Enter the amount for your package.</p>
                            <p>3. Take a screenshot of the receipt.</p>
                            <p>4. Upload the receipt below.</p>
                            
                            <div class="qr-container-dash" style="margin: 20px auto; width: 180px; height: 180px; border-radius: 15px;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:09171234567" alt="GCash QR Code" class="qr-image-dash">
                            </div>
                            
                            <div class="payment-details" style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 12px; margin-top: 10px;">
                                <p style="font-size: 0.85rem; margin-bottom: 5px;"><strong>GCash:</strong> 0917-123-4567</p>
                                <p style="font-size: 0.85rem;"><strong>Name:</strong> Martinez Fitness</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal-overlay" id="bookingDetailsModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Booking Details</h3>
                <button class="close-modal" onclick="closeBookingDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Package</label>
                        <div class="value" id="detailPackage">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Date</label>
                        <div class="value" id="detailDate">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Amount</label>
                        <div class="value" id="detailAmount">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Status</label>
                        <div class="value" id="detailStatus">-</div>
                    </div>
                </div>
                
                <div class="receipt-section" id="receiptSection" style="display: none;">
                    <h4><i class="fas fa-receipt"></i> Payment Receipt</h4>
                    <img id="detailReceipt" src="" alt="Payment Receipt" class="receipt-image">
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeBookingDetailsModal()">
                        <i class="fas fa-times"></i>
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Survey Modal -->
    <div class="modal-overlay survey-modal" id="surveyModal">
        <div class="modal">
            <button class="close-modal" onclick="skipSurvey()" style="top: 20px; right: 20px;">
                <i class="fas fa-times"></i>
            </button>
            <div class="survey-header">
                <i class="fas fa-dumbbell"></i>
                <h2>Personalize Your Journey</h2>
                <p>Help us find the perfect package for your fitness goals!</p>
            </div>
            
            <div class="survey-body">
                <!-- Step 1: Goal -->
                <div class="survey-step active" data-step="1">
                    <label class="question-label">What is your primary fitness goal?</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal', 'weight_loss')">
                            <i class="fas fa-weight"></i>
                            <span>Weight Loss</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal', 'muscle_gain')">
                            <i class="fas fa-fist-raised"></i>
                            <span>Muscle Gain</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal', 'endurance')">
                            <i class="fas fa-running"></i>
                            <span>Endurance</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal', 'general')">
                            <i class="fas fa-heartbeat"></i>
                            <span>General Fitness</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Frequency -->
                <div class="survey-step" data-step="2">
                    <label class="question-label">How often do you plan to work out?</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'frequency', 'daily')">
                            <i class="fas fa-calendar-day"></i>
                            <span>Almost Daily</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'frequency', 'few_times')">
                            <i class="fas fa-calendar-week"></i>
                            <span>3-4 Times / Week</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'frequency', 'weekends')">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Weekends Only</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'frequency', 'occasional')">
                            <i class="fas fa-clock"></i>
                            <span>Occasional</span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Commitment -->
                <div class="survey-step" data-step="3">
                    <label class="question-label">What's your preferred commitment length?</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'commitment', 'long_term')">
                            <i class="fas fa-award"></i>
                            <span>1 Year (VIP)</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'commitment', 'medium_term')">
                            <i class="fas fa-calendar-check"></i>
                            <span>30-90 Days</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'commitment', 'short_term')">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Weekly</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'commitment', 'trial')">
                            <i class="fas fa-ticket-alt"></i>
                            <span>One-time / Trial</span>
                        </div>
                    </div>
                </div>

                <div class="survey-footer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="surveyProgress"></div>
                    </div>
                    <button class="survey-nav-btn btn-next" id="surveyNextBtn" disabled onclick="nextSurveyStep()">
                        <span>Next Step</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendation Modal -->
    <div class="modal-overlay recommendation-modal" id="recommendationModal">
        <div class="modal">
            <div class="recommendation-header">
                <i class="fas fa-star"></i>
                <h2>Your Perfect Match!</h2>
                <p>Based on your survey, we recommend this package for you:</p>
            </div>
            
            <div class="recommendation-body">
                <div class="recommended-package-preview" id="recommendedPackagePreview">
                    <h3 id="recPackageName">-</h3>
                    <div class="price" id="recPackagePrice">-</div>
                    <div class="duration" id="recPackageDuration">-</div>
                    <p id="recPackageDesc" style="color: var(--dark-text-secondary); margin-bottom: 0;">-</p>
                </div>

                <div class="recommended-actions">
                    <button class="btn btn-secondary" onclick="closeRecommendationModal()">
                        <i class="fas fa-times"></i>
                        <span>Maybe Later</span>
                    </button>
                    <button class="btn btn-primary" id="bookRecommendedBtn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book This Now</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Scripts -->
    <script src="../../assets/js/user-dashboard.js"></script>
</body>
</html>
