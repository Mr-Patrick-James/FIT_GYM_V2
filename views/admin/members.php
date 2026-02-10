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
    <title>Members Management | FitPay Admin</title>
    
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
            <li><a href="members.php" class="active"><i class="fas fa-users"></i> <span>Members</span></a></li>
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
                <h1>Members Management</h1>
                <p>View and manage all gym members and their information</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search members...">
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalMembersTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalMembers">0</div>
                <div class="stat-label">Total Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="activeMembersTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="activeMembers">0</div>
                <div class="stat-label">Active Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalBookingsTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalBookings">0</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            
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
        </div>

        <!-- Filters and Actions -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Filter & Sort</h3>
                <div class="card-actions">
                    <button class="card-btn" onclick="exportMembers()">
                        <i class="fas fa-download"></i>
                        <span>Export CSV</span>
                    </button>
                    <button class="card-btn primary" onclick="refreshMembers()">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
            
            <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Member Status</label>
                    <select id="statusFilter" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Members</option>
                        <option value="active">Active Members</option>
                        <option value="inactive">Expired Members</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Sort By</label>
                    <select id="sortBy" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="bookings-desc">Most Bookings</option>
                        <option value="bookings-asc">Least Bookings</option>
                        <option value="revenue-desc">Highest Revenue</option>
                        <option value="revenue-asc">Lowest Revenue</option>
                        <option value="recent">Recently Joined</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Members Grid -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Members</h3>
                <div class="card-actions">
                    <span style="color: var(--dark-text-secondary); font-size: 0.9rem;">
                        Showing <strong id="showingCount">0</strong> of <strong id="totalCount">0</strong> members
                    </span>
                </div>
            </div>
            
            <div id="membersGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px; padding: 24px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noMembersMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No members found</h3>
                <p>Members will appear here once they register and make bookings.</p>
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

    <!-- Member Details Modal -->
    <div class="modal-overlay" id="memberModal">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Member Details</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--dark-border);">
                    <div class="admin-avatar" id="memberAvatar" style="width: 80px; height: 80px; font-size: 2rem;">JD</div>
                    <div>
                        <h2 id="memberName" style="margin-bottom: 8px;">-</h2>
                        <p id="memberEmail" style="color: var(--dark-text-secondary);">-</p>
                    </div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Email Address</label>
                        <div class="value" id="modalEmail">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Contact Number</label>
                        <div class="value" id="modalContact">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Address</label>
                        <div class="value" id="modalAddress">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Total Bookings</label>
                        <div class="value" id="modalTotalBookings">0</div>
                    </div>
                    <div class="detail-group">
                        <label>Verified Payments</label>
                        <div class="value" id="modalVerifiedPayments">0</div>
                    </div>
                    <div class="detail-group">
                        <label>Total Spent</label>
                        <div class="value" id="modalTotalSpent" style="font-weight: 800; color: var(--success);">₱0</div>
                    </div>
                    <div class="detail-group">
                        <label>Member Status</label>
                        <div class="value">
                            <span class="status-badge status-verified" id="modalStatus">Active</span>
                        </div>
                    </div>
                    <div class="detail-group">
                        <label>Joined Date</label>
                        <div class="value" id="modalJoinedDate">-</div>
                    </div>
                </div>
                
                <div style="margin-top: 32px;">
                    <h4 style="margin-bottom: 16px; color: var(--primary);">
                        <i class="fas fa-calendar-check"></i> Booking History
                    </h4>
                    <div id="memberBookingsList" style="max-height: 300px; overflow-y: auto;">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Members Scripts -->
    <script src="../../assets/js/members.js"></script>
</body>
</html>
