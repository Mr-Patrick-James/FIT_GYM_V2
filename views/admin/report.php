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
    <title>Reports | FitPay Admin</title>
    
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
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="report.php" class="active"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
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
                <h1>Reports</h1>
                <p>Comprehensive reports on your gym's performance and revenue</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn primary" onclick="generateReport()">
                    <i class="fas fa-file-pdf"></i>
                    <span>Generate Report</span>
                </button>
                
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Select Time Period</h3>
                <div class="card-actions">
                    <select id="periodSelect" class="card-btn" style="padding: 10px 16px; cursor: pointer;" onchange="updateAllCharts()">
                        <option value="7days">Last 7 Days</option>
                        <option value="30days" selected>Last 30 Days</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="6months">Last 6 Months</option>
                        <option value="year">Last Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid" style="margin-top: 32px;">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="revenueGrowth">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalRevenue">₱0</div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="bookingsGrowth">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalBookings">0</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="membersGrowth">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalMembers">0</div>
                <div class="stat-label">Active Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="avgRevenueGrowth">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="avgRevenue">₱0</div>
                <div class="stat-label">Avg. Revenue/Day</div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-top: 32px;">
            <!-- Revenue Trend Chart -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Revenue Trend</h3>
                    <div class="card-actions">
                        <button class="card-btn" onclick="exportChart('revenueChart')">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container" style="height: 350px; margin-top: 20px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <!-- Package Distribution -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Package Distribution</h3>
                </div>
                <div class="chart-container" style="height: 350px; margin-top: 20px;">
                    <canvas id="packageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 32px;">
            <!-- Booking Status -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Booking Status</h3>
                </div>
                <div class="chart-container" style="height: 300px; margin-top: 20px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Comparison -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Monthly Comparison</h3>
                </div>
                <div class="chart-container" style="height: 300px; margin-top: 20px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
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

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Reports Scripts -->
    <script src="../../assets/js/reports.js"></script>
</body>
</html>
