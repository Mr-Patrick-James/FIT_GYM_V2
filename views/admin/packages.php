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
    <title>Packages Management | FitPay Admin</title>
    
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
            <li><a href="packages.php" class="active"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
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
                <h1>Packages Management</h1>
                <p>Create, edit, and manage gym membership packages</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn primary" onclick="openAddPackageModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Package</span>
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalPackagesTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalPackages">0</div>
                <div class="stat-label">Total Packages</div>
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
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="popularPackageTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="popularPackage">-</div>
                <div class="stat-label">Most Popular</div>
            </div>
        </div>

        <!-- Packages Grid -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Packages</h3>
                <div class="card-actions">
                    <span style="color: var(--dark-text-secondary); font-size: 0.9rem;">
                        <strong id="showingCount">0</strong> packages available
                    </span>
                </div>
            </div>
            
            <div id="packagesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; padding: 20px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noPackagesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-dumbbell" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No packages found</h3>
                <p>Click "Add New Package" to create your first membership package.</p>
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

    <!-- Add/Edit Package Modal -->
    <div class="modal-overlay" id="packageModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Package</h3>
                <button class="close-modal" onclick="closePackageModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="packageForm" onsubmit="savePackage(event)">
                    <div class="form-group">
                        <label>Package Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packageName" required placeholder="e.g., Monthly Membership">
                    </div>
                    
                    <div class="form-group">
                        <label>Duration <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packageDuration" required placeholder="e.g., 30 Days">
                    </div>
                    
                    <div class="form-group">
                        <label>Price <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packagePrice" required placeholder="e.g., ₱1,500" onfocus="prependPesoSymbol()">
                    </div>
                    
                    <div class="form-group">
                        <label>Tag/Badge</label>
                        <select id="packageTag">
                            <option value="Basic">Basic</option>
                            <option value="Popular">Popular</option>
                            <option value="Best Value">Best Value</option>
                            <option value="Premium">Premium</option>
                            <option value="VIP">VIP</option>
                            <option value="">None</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description / Features</label>
                        <textarea id="packageDescription" rows="4" placeholder="Enter features line by line (e.g.,&#10;Full Equipment Access&#10;Locker Room Access&#10;Expert Guidance)"></textarea>
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 4px;">Each line will appear as a bullet point with a checkmark on the landing page.</p>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closePackageModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Package
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Delete Package</h3>
                <button class="close-modal" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <p style="margin-bottom: 24px; color: var(--dark-text-secondary);">
                    Are you sure you want to delete <strong id="deletePackageName">this package</strong>? 
                    This action cannot be undone.
                </p>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Packages Scripts -->
    <script src="../../assets/js/packages.js"></script>
</body>
</html>
