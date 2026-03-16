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
    <title>Trainer Management | FitPay Admin</title>
    
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
            } else {
                document.documentElement.classList.remove('light-mode');
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
            <li><a href="trainers.php" class="active"><i class="fas fa-user-tie"></i> <span>Trainers</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
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
                <h1>Trainer Management</h1>
                <p>Manage gym trainers and instructors</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="trainerSearch" placeholder="Search trainers..." oninput="filterTrainers()">
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
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalTrainers">0</div>
                <div class="stat-label">Total Trainers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-value" id="activeTrainers">0</div>
                <div class="stat-label">Active Trainers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="stat-value" id="specializationsCount">0</div>
                <div class="stat-label">Specializations</div>
            </div>

            <div class="stat-card" style="cursor: pointer;" onclick="openAddTrainerModal()">
                <div class="stat-header">
                    <div class="stat-icon" style="background: var(--primary-color); color: white;">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
                <div class="stat-value">Add New</div>
                <div class="stat-label">Click to add trainer</div>
            </div>
        </div>

        <!-- Trainers Content -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Trainers</h3>
                <div class="card-actions">
                    <button class="card-btn primary" onclick="loadTrainers()">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
            
            <div id="trainersGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; padding: 24px;">
                <!-- Trainers will be loaded here via JS -->
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p style="margin-top: 10px;">Loading trainers...</p>
                </div>
            </div>

            <div id="noTrainersMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No trainers found</h3>
                <p>Click "Add New" to create your first trainer entry.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-heart" style="color: var(--primary);"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0
            </p>
        </div>
    </main>

    <!-- Add/Edit Trainer Modal -->
    <div class="modal-overlay" id="trainerModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Trainer</h3>
                <button class="close-modal" onclick="closeTrainerModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="trainerForm">
                    <input type="hidden" id="trainerId">
                    
                    <div class="form-group">
                        <label>Full Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="trainerName" required placeholder="e.g. John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label>Specialization <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="trainerSpecialization" required placeholder="e.g. Strength & Conditioning">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label>Email Address <span style="color: var(--warning);">*</span></label>
                            <input type="email" id="trainerEmail" required placeholder="e.g. john@example.com">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" id="trainerContact" placeholder="e.g. 0912 345 6789">
                        </div>
                    </div>

                    <div class="form-group">
                        <label id="passwordLabel">Login Password</label>
                        <input type="password" id="trainerPassword" placeholder="Leave blank to keep current (or default 'trainer123')">
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 4px;">
                            Trainers will use their email and this password to login.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>Short Bio</label>
                        <textarea id="trainerBio" rows="3" placeholder="Tell us about the trainer's experience and background..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                            <input type="checkbox" id="trainerActive" checked style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="font-weight: 600; font-size: 0.95rem;">Active Trainer</span>
                        </label>
                        <p style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-top: 4px; margin-left: 28px;">
                            Inactive trainers won't be visible to users but their data remains in the system.
                        </p>
                    </div>
                    
                    <div class="modal-actions" style="margin-top: 32px; display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" class="btn btn-secondary" onclick="closeTrainerModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Trainer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteTrainerModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 style="color: #ef4444;">Delete Trainer</h3>
                <button class="close-modal" onclick="closeDeleteTrainerModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 16px;"></i>
                <p>Are you sure you want to delete <strong id="deleteTrainerName">this trainer</strong>?</p>
                <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-top: 8px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; display: flex; gap: 12px; border-top: 1px solid var(--dark-border);">
                <button class="btn btn-secondary" style="flex: 1;" onclick="closeDeleteTrainerModal()">Cancel</button>
                <button class="btn" style="flex: 1; background: #ef4444; color: white;" onclick="confirmDeleteTrainer()">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/trainers.js"></script>
</body>
</html>
