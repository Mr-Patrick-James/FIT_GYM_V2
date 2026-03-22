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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.7">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') document.documentElement.classList.add('light-mode');
            else document.documentElement.classList.remove('light-mode');
        })();
    </script>
    <style>
        .trainer-card.inactive { opacity: 0.55; filter: grayscale(40%); }
        .trainer-card.inactive:hover { opacity: 0.85; filter: grayscale(20%); }
        .load-bar-wrap { margin: 10px 0 4px; }
        .load-bar-track { height: 6px; background: var(--glass); border-radius: 99px; overflow: hidden; }
        .load-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s ease; }
        .cert-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .cert-tag { padding: 3px 10px; background: rgba(139,92,246,0.12); border: 1px solid rgba(139,92,246,0.25); border-radius: 99px; font-size: 0.7rem; font-weight: 700; color: #a78bfa; }
        .avail-pills { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
        .avail-pill { padding: 2px 8px; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 99px; font-size: 0.68rem; font-weight: 600; color: #60a5fa; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--dark-border); background: var(--glass); }
        .filter-bar select { padding: 8px 14px; background: var(--dark-card); border: 1.5px solid var(--dark-border); border-radius: var(--radius-md); color: var(--dark-text); font-size: 0.8rem; font-weight: 500; outline: none; }
        .filter-bar select:focus { border-color: var(--primary); }
        .filter-bar label { font-size: 0.75rem; color: var(--dark-text-secondary); font-weight: 600; }
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; }
        .drawer-overlay.active { display: block; }
        .profile-drawer { position: fixed; top: 0; right: -520px; width: 500px; max-width: 95vw; height: 100vh; background: var(--dark-card); border-left: 2px solid var(--dark-border); z-index: 2001; overflow-y: auto; transition: right 0.35s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; }
        .profile-drawer.open { right: 0; }
        .drawer-header { padding: 24px; border-bottom: 1px solid var(--dark-border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--dark-card); z-index: 1; }
        .drawer-body { padding: 24px; flex: 1; }
        .drawer-section { margin-bottom: 28px; }
        .drawer-section-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--dark-text-secondary); margin-bottom: 12px; }
        .stat-mini-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .stat-mini { background: var(--glass); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 14px; text-align: center; }
        .stat-mini-val { font-size: 1.4rem; font-weight: 900; }
        .stat-mini-lbl { font-size: 0.65rem; color: var(--dark-text-secondary); font-weight: 600; margin-top: 2px; }
        .photo-upload-wrap { display: flex; align-items: center; gap: 16px; margin-bottom: 8px; }
        .photo-preview { width: 72px; height: 72px; border-radius: var(--radius-lg); border: 2px solid var(--dark-border); background: var(--glass); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .photo-preview i { font-size: 2rem; color: var(--dark-text-secondary); }
        .day-checks { display: flex; flex-wrap: wrap; gap: 8px; }
        .day-check-label { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1.5px solid var(--dark-border); border-radius: var(--radius-full); cursor: pointer; font-size: 0.78rem; font-weight: 600; transition: all 0.2s; user-select: none; }
        .day-check-label input { display: none; }
        .day-check-label.checked { border-color: #3b82f6; background: rgba(59,130,246,0.1); color: #60a5fa; }
        .inactive-section-label { grid-column: 1/-1; display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px; }
        .inactive-section-label::before, .inactive-section-label::after { content: ''; flex: 1; height: 1px; background: var(--dark-border); }
    </style>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuToggle"><i class="fas fa-bars"></i></button>

    <aside class="sidebar">
        <div class="logo"><h1>FitPay</h1><p>GYM MANAGEMENT</p></div>
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
                foreach(explode(' ', $adminName) as $word) { if (!empty($word)) $initials .= strtoupper($word[0]); }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($adminName); ?></h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Trainer Management</h1>
                <p>Manage gym trainers, credentials, schedules, and client loads</p>
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

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
            <div class="stat-card">
                <div class="stat-header"><div class="stat-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-user-tie"></i></div></div>
                <div class="stat-value" id="totalTrainers">0</div>
                <div class="stat-label">Total Trainers</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><div class="stat-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-user-check"></i></div></div>
                <div class="stat-value" id="activeTrainers">0</div>
                <div class="stat-label">Active Trainers</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><div class="stat-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fas fa-star"></i></div></div>
                <div class="stat-value" id="specializationsCount">0</div>
                <div class="stat-label">Specializations</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><div class="stat-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-dumbbell"></i></div></div>
                <div class="stat-value" id="totalAssignedPackages">0</div>
                <div class="stat-label">Assigned Packages</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><div class="stat-icon" style="background:rgba(34,197,94,0.1);color:#22c55e;"><i class="fas fa-users"></i></div></div>
                <div class="stat-value" id="totalActiveClients">0</div>
                <div class="stat-label">Total Active Clients</div>
            </div>
            <div class="stat-card" style="cursor:pointer;" onclick="openAddTrainerModal()">
                <div class="stat-header"><div class="stat-icon" style="background:var(--glass);color:var(--primary);"><i class="fas fa-plus"></i></div></div>
                <div class="stat-value">Add New</div>
                <div class="stat-label">Click to add trainer</div>
            </div>
        </div>

        <div class="content-card" style="margin-top:32px;">
            <div class="card-header">
                <h3>All Trainers</h3>
                <div class="card-actions">
                    <button class="card-btn" onclick="exportTrainers()"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <button class="card-btn primary" onclick="loadTrainers()"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
            </div>

            <div class="filter-bar">
                <div style="display:flex;align-items:center;gap:8px;">
                    <label>Specialization</label>
                    <select id="filterSpec" onchange="filterTrainers()"><option value="">All</option></select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <label>Status</label>
                    <select id="filterStatus" onchange="filterTrainers()">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <label>Sort by</label>
                    <select id="sortBy" onchange="filterTrainers()">
                        <option value="name">Name</option>
                        <option value="clients">Active Clients</option>
                        <option value="packages">Packages</option>
                    </select>
                </div>
            </div>

            <div id="trainersGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;padding:24px;">
                <div style="grid-column:1/-1;text-align:center;padding:40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
                    <p style="margin-top:10px;">Loading trainers...</p>
                </div>
            </div>
            <div id="noTrainersMessage" style="display:none;text-align:center;padding:60px 20px;color:var(--dark-text-secondary);">
                <i class="fas fa-user-slash" style="font-size:3rem;margin-bottom:16px;opacity:0.5;"></i>
                <h3 style="margin-bottom:8px;">No trainers found</h3>
                <p>Click "Add New" to create your first trainer entry.</p>
            </div>
        </div>

        <div class="footer">
            <p><i class="fas fa-heart" style="color:var(--primary);"></i> &copy; <?php echo date('Y'); ?> Martinez Fitness Gym &bull; FitPay Management System v2.0</p>
        </div>
    </main>

    <!-- Add/Edit Trainer Modal -->
    <div class="modal-overlay" id="trainerModal">
        <div class="modal" style="max-width:680px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Trainer</h3>
                <button class="close-modal" onclick="closeTrainerModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="max-height:80vh;overflow-y:auto;">
                <form id="trainerForm">
                    <input type="hidden" id="trainerId">
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <div class="photo-upload-wrap">
                            <div class="photo-preview" id="photoPreview"><i class="fas fa-user-tie"></i></div>
                            <div style="flex:1;">
                                <input type="file" id="trainerPhotoFile" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
                                <button type="button" class="card-btn" onclick="document.getElementById('trainerPhotoFile').click()">
                                    <i class="fas fa-camera"></i> Choose Photo
                                </button>
                                <p style="font-size:0.72rem;color:var(--dark-text-secondary);margin-top:6px;">JPG, PNG, WEBP &mdash; max 5MB</p>
                            </div>
                        </div>
                        <input type="hidden" id="trainerPhotoUrl">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label>Full Name <span style="color:var(--warning);">*</span></label>
                            <input type="text" id="trainerName" required placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group">
                            <label>Specialization <span style="color:var(--warning);">*</span></label>
                            <input type="text" id="trainerSpecialization" required placeholder="e.g. Strength and Conditioning">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label>Email Address <span style="color:var(--warning);">*</span></label>
                            <input type="email" id="trainerEmail" required placeholder="e.g. john@example.com">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="tel" id="trainerContact" placeholder="e.g. 09123456789" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label id="passwordLabel">Login Password</label>
                            <input type="password" id="trainerPassword" placeholder="Leave blank to keep current">
                            <p style="font-size:0.72rem;color:var(--dark-text-secondary);margin-top:4px;">Default: trainer123</p>
                        </div>
                        <div class="form-group">
                            <label>Max Client Capacity</label>
                            <input type="number" id="trainerMaxClients" min="1" max="100" value="10">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Certifications / Credentials</label>
                        <input type="text" id="trainerCertifications" placeholder="e.g. NSCA-CPT, ACE, NASM (comma-separated)">
                        <p style="font-size:0.72rem;color:var(--dark-text-secondary);margin-top:4px;">Separate multiple certifications with commas.</p>
                    </div>
                    <div class="form-group">
                        <label>Available Days</label>
                        <div class="day-checks" id="availDays">
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Mon"> Mon</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Tue"> Tue</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Wed"> Wed</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Thu"> Thu</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Fri"> Fri</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Sat"> Sat</label>
                            <label class="day-check-label" onclick="toggleDay(this)"><input type="checkbox" value="Sun"> Sun</label>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label>Available From</label>
                            <input type="time" id="availFrom" value="06:00">
                        </div>
                        <div class="form-group">
                            <label>Available Until</label>
                            <input type="time" id="availUntil" value="18:00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Short Bio</label>
                        <textarea id="trainerBio" rows="3" placeholder="Trainer experience and background..."></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;">
                            <input type="checkbox" id="trainerActive" checked style="width:18px;height:18px;cursor:pointer;">
                            <span style="font-weight:600;">Active Trainer</span>
                        </label>
                        <p style="font-size:0.78rem;color:var(--dark-text-secondary);margin-top:4px;margin-left:28px;">Inactive trainers will not be visible to members.</p>
                    </div>
                    <div class="modal-actions" style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px;">
                        <button type="button" class="btn btn-secondary" onclick="closeTrainerModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveTrainerBtn"><i class="fas fa-save"></i> Save Trainer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteTrainerModal">
        <div class="modal" style="max-width:400px;">
            <div class="modal-header">
                <h3 style="color:#ef4444;">Delete Trainer</h3>
                <button class="close-modal" onclick="closeDeleteTrainerModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:24px;text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ef4444;margin-bottom:16px;"></i>
                <p>Are you sure you want to delete <strong id="deleteTrainerName">this trainer</strong>?</p>
                <p style="font-size:0.85rem;color:var(--dark-text-secondary);margin-top:8px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="padding:16px 24px;display:flex;gap:12px;border-top:1px solid var(--dark-border);">
                <button class="btn btn-secondary" style="flex:1;" onclick="closeDeleteTrainerModal()">Cancel</button>
                <button class="btn" style="flex:1;background:#ef4444;color:white;" onclick="confirmDeleteTrainer()">Delete</button>
            </div>
        </div>
    </div>

    <!-- View Trainer Members Modal -->
    <div class="modal-overlay" id="trainerMembersModal">
        <div class="modal" style="max-width:700px;">
            <div class="modal-header">
                <h3 id="viewMembersModalTitle">Assigned Members</h3>
                <button class="close-modal" onclick="closeTrainerMembersModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div id="trainerMembersList" style="display:flex;flex-direction:column;gap:16px;max-height:500px;overflow-y:auto;padding-right:8px;"></div>
            </div>
            <div class="modal-footer" style="padding:16px 24px;border-top:1px solid var(--dark-border);text-align:right;">
                <button class="btn btn-secondary" onclick="closeTrainerMembersModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Profile Drawer -->
    <div class="drawer-overlay" id="drawerOverlay" onclick="closeProfileDrawer()"></div>
    <div class="profile-drawer" id="profileDrawer">
        <div class="drawer-header">
            <h3 id="drawerTitle">Trainer Profile</h3>
            <button class="close-modal" onclick="closeProfileDrawer()"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body" id="drawerBody"></div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/trainers.js?v=2.0"></script>
</body>
</html>
