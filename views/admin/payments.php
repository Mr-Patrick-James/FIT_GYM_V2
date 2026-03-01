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
    <title>Payments Management | FitPay Admin</title>
    
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
            <li><a href="payments.php" class="active"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
            <li><a href="report.php"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                <h1>Payments Management</h1>
                <p>View and manage all verified payments and revenue</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search payments, members...">
                </div>
                
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalRevenueTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalRevenue">₱0</div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="monthlyRevenueTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="monthlyRevenue">₱0</div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalPaymentsTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalPayments">0</div>
                <div class="stat-label">Total Payments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="averagePaymentTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="averagePayment">₱0</div>
                <div class="stat-label">Average Payment</div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Revenue Overview</h3>
                <div class="card-actions">
                    <select class="card-btn" id="chartPeriod" style="padding: 10px 16px; cursor: pointer;">
                        <option value="6months">Last 6 Months</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>
            
            <div class="chart-container" style="height: 300px; margin-top: 20px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Filter & Sort</h3>
                <div class="card-actions">
                    <button class="card-btn" onclick="exportPayments()">
                        <i class="fas fa-download"></i>
                        <span>Export CSV</span>
                    </button>
                    <button class="card-btn primary" onclick="refreshPayments()">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
            
            <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Package Filter</label>
                    <select id="packageFilter" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Packages</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Sort By</label>
                    <select id="sortBy" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="date-desc">Date (Newest First)</option>
                        <option value="date-asc">Date (Oldest First)</option>
                        <option value="amount-desc">Amount (High to Low)</option>
                        <option value="amount-asc">Amount (Low to High)</option>
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Date Range</label>
                    <select id="dateRange" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Payments</h3>
                <div class="card-actions">
                    <span style="color: var(--dark-text-secondary); font-size: 0.9rem;">
                        Showing <strong id="showingCount">0</strong> of <strong id="totalCount">0</strong> payments
                    </span>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Package</th>
                            <th>Payment Date</th>
                            <th>Amount</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTable">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div id="noPaymentsMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-money-bill-wave" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No payments found</h3>
                <p>Verified payments will appear here once bookings are verified.</p>
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

    <!-- Payment Details Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Payment Details</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Client Name</label>
                        <div class="value" id="modalClientName">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Contact Number</label>
                        <div class="value" id="modalContact">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Email Address</label>
                        <div class="value" id="modalEmail">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Package Selected</label>
                        <div class="value" id="modalPackage">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Date</label>
                        <div class="value" id="modalDate">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Amount</label>
                        <div class="value" id="modalAmount" style="font-size: 1.5rem; font-weight: 800; color: var(--success);">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Status</label>
                        <div class="value">
                            <span class="status-badge status-verified">Verified</span>
                        </div>
                    </div>
                    <div class="detail-group" id="notesGroup" style="display: none;">
                        <label>Notes</label>
                        <div class="value" id="modalNotes">-</div>
                    </div>
                </div>
                
                <div class="receipt-section" id="receiptSection" style="display: none;">
                    <h4><i class="fas fa-receipt"></i> Payment Receipt</h4>
                    <img src="" alt="Payment Receipt" class="receipt-image" id="modalReceipt">
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                        Close
                    </button>
                    <button class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print"></i>
                        Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Payments Scripts -->
    <script src="../../assets/js/payments.js"></script>
</body>
</html>
