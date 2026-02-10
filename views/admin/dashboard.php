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
    <title>FitPay Admin | Martinez Fitness</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
<body class="dark-mode">
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="report.php"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar">AM</div>
            <div class="admin-info">
                <h4>Admin Martinez</h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>Monitor bookings, payments, and gym performance in real-time</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search bookings, members...">
                </div>
                
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>12%</span>
                    </div>
                </div>
                <div class="stat-value">24</div>
                <div class="stat-label">Total Bookings This Month</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="trend down">
                        <i class="fas fa-arrow-down"></i>
                        <span>8%</span>
                    </div>
                </div>
                <div class="stat-value">8</div>
                <div class="stat-label">Pending Verifications</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>24%</span>
                    </div>
                </div>
                <div class="stat-value">₱12,450</div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>5%</span>
                    </div>
                </div>
                <div class="stat-value">156</div>
                <div class="stat-label">Active Members</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-card" style="margin-top: 24px; margin-bottom: 32px;">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="quick-actions-grid">
                <a href="bookings.php?action=walkin" class="quick-action-card">
                    <i class="fas fa-person-walking"></i>
                    <h4>New Walk-in</h4>
                    <p>Register a walk-in client</p>
                </a>
                <a href="bookings.php?status=pending" class="quick-action-card">
                    <i class="fas fa-check-circle"></i>
                    <h4>Verify Payments</h4>
                    <p id="pendingVerificationsText">0 pending verifications</p>
                </a>
                <a href="packages.php" class="quick-action-card">
                    <i class="fas fa-dumbbell"></i>
                    <h4>Manage Packages</h4>
                    <p>Edit gym membership plans</p>
                </a>
                <a href="report.php" class="quick-action-card">
                    <i class="fas fa-chart-bar"></i>
                    <h4>Generate Report</h4>
                    <p>View gym performance</p>
                </a>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Recent Bookings -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Booking Requests</h3>
                        <div class="card-actions">
                            <button class="card-btn">
                                <i class="fas fa-filter"></i>
                                <span>Filter</span>
                            </button>
                            <button class="card-btn primary">
                                <i class="fas fa-download"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Package</th>
                                    <th>Date</th>
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
                
                <!-- Revenue Chart -->
                <div class="content-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3>Revenue Reports</h3>
                        <div class="card-actions">
                            <select class="card-btn" style="padding: 10px 16px; cursor: pointer;">
                                <option>Last 6 Months</option>
                                <option>Last 3 Months</option>
                                <option>This Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="right-column">
                <!-- GCash Payment -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>GCash Payment</h3>
                        <button class="card-btn">
                            <i class="fas fa-sync-alt"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                    
                    <div class="qr-section">
                        <div class="qr-container">
                            <div class="qr-code">
                                <i class="fas fa-qrcode"></i>
                            </div>
                        </div>
                        <div class="qr-info">
                            <p><strong>GCash Number:</strong> 0917-123-4567</p>
                            <p><strong>Account Name:</strong> Martinez Fitness</p>
                            <p style="margin-top: 16px; font-size: 0.9rem;">
                                Share this QR code with clients for payment
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Gym Packages -->
                <div class="content-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3>Active Packages</h3>
                        <button class="card-btn primary">
                            <i class="fas fa-plus"></i>
                            <span>Add New</span>
                        </button>
                    </div>
                    
                    <div class="packages-list" id="packagesList">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Quick Actions moved to top; section removed here -->
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-heart" style="color: var(--primary);"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0
                <i class="fas fa-bolt" style="color: var(--primary);"></i>
            </p>
        </div>
    </main>

    <!-- Booking Details Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Booking Details</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Client Name</label>
                        <div class="value" id="modalClientName">Juan Dela Cruz</div>
                    </div>
                    <div class="detail-group">
                        <label>Contact Number</label>
                        <div class="value" id="modalContact">0917-123-4567</div>
                    </div>
                    <div class="detail-group">
                        <label>Email Address</label>
                        <div class="value" id="modalEmail">juan.delacruz@email.com</div>
                    </div>
                    <div class="detail-group">
                        <label>Package Selected</label>
                        <div class="value" id="modalPackage">Monthly Membership</div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Date</label>
                        <div class="value" id="modalDate">February 10, 2026</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Amount</label>
                        <div class="value" id="modalAmount">₱1,500.00</div>
                    </div>
                </div>
                
                <div class="receipt-section" id="receiptSection" style="display: none;">
                    <h4><i class="fas fa-receipt"></i> Payment Receipt</h4>
                    <img src="" 
                         alt="Payment Receipt" 
                         class="receipt-image" 
                         id="modalReceipt">
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="rejectPayment()">
                        <i class="fas fa-times"></i>
                        Reject Payment
                    </button>
                    <button class="btn btn-primary" onclick="verifyPayment()">
                        <i class="fas fa-check"></i>
                        Verify Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Dashboard Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
