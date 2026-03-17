<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireTrainer();
$user = getCurrentUser();

$conn = getDBConnection();

// Fetch trainer-specific data
$trainerId = null;
$stmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $trainerId = $row['id'];
}
$stmt->close();

// Stats for trainer
$totalMembers = 0;
if ($trainerId) {
    $res = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE trainer_id = $trainerId AND status = 'verified'");
    if ($res) $totalMembers = $res->fetch_assoc()['count'];
}

// Active Plans count
$activePlans = 0;
if ($trainerId) {
    $res = $conn->query("SELECT COUNT(DISTINCT booking_id) as count FROM member_exercise_plans mep JOIN bookings b ON mep.booking_id = b.id WHERE b.trainer_id = $trainerId");
    if ($res) $activePlans = $res->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Dashboard | FitPay</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
    <style>
        /* Floating Notification Icon */
        .trainer-notif-float {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 64px;
            height: 64px;
            background: #fff;
            color: #000;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
            z-index: 999;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
        }

        .trainer-notif-float:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 255, 255, 0.3);
        }

        .trainer-notif-float i {
            animation: pulse 2s infinite;
        }

        .notif-badge-float {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes bounceIn {
            from { opacity: 0; transform: scale(0.3); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
    
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
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <aside class="sidebar">
        <div class="logo">
            <h1>FitPay</h1>
            <p>TRAINER PANEL</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>My Clients</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercise Library</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> <span>My Profile</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $name = $user['name'] ?? 'Trainer';
                $initials = '';
                foreach(explode(' ', $name) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($name); ?></h4>
                <p>Professional Trainer</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Welcome, Coach <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h1>
                <p>Manage your clients and their fitness journeys</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notifBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $totalMembers; ?></div>
                <div class="stat-label">Active Clients</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                </div>
                <div class="stat-value" id="assignedPkgCount">0</div>
                <div class="stat-label">Assigned Packages</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $activePlans; ?></div>
                <div class="stat-label">Active Plans</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 32px;">
            <!-- Recent Client Progress -->
            <div class="content-card" style="margin-top: 0;">
                <div class="card-header">
                    <h3>Recent Client Progress</h3>
                </div>
                <div style="padding: 24px;">
                    <?php
                    $recentActivity = [];
                    if ($trainerId) {
                        $res = $conn->query("
                            SELECT mp.*, u.name as member_name 
                            FROM member_progress mp 
                            JOIN bookings b ON mp.booking_id = b.id 
                            JOIN users u ON b.user_id = u.id 
                            WHERE mp.trainer_id = $trainerId 
                            ORDER BY mp.created_at DESC LIMIT 5
                        ");
                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $recentActivity[] = $row;
                            }
                        }
                    }
                    
                    if (empty($recentActivity)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--dark-text-secondary);">
                            <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 166px; opacity: 0.3;"></i>
                            <p>No recent activity recorded.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $act): ?>
                            <div style="padding: 16px; border-bottom: 1px solid var(--dark-border); display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="font-weight: 700; margin-bottom: 4px;"><?php echo htmlspecialchars($act['member_name']); ?></h4>
                                    <p style="font-size: 0.85rem; color: var(--dark-text-secondary);"><?php echo htmlspecialchars($act['remarks']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--primary);"><?php echo $act['weight'] ? $act['weight'] . ' kg' : ''; ?></span>
                                    <span style="font-size: 0.75rem; color: #555;"><?php echo date('M d, Y', strtotime($act['logged_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Assigned Packages -->
            <div class="content-card" style="margin-top: 0;">
                <div class="card-header">
                    <h3>My Assigned Packages</h3>
                </div>
                <div id="assignedPackagesList" style="padding: 24px;">
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                        <p style="margin-top: 10px;">Loading packages...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <!-- Trainer Notification FAB -->
    <button class="trainer-notif-float" id="trainerNotifFloat" onclick="toggleNotifications()" title="View Notifications">
        <i class="fas fa-bell"></i>
        <span class="notif-badge-float" id="notifBadgeFloat">0</span>
    </button>

    <!-- Notifications Modal -->
    <div class="modal-overlay" id="notificationsModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-bell"></i> Notifications</h3>
                <button class="close-modal" onclick="toggleNotifications()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="notificationsList" style="max-height: 400px; overflow-y: auto; padding: 10px;">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary btn-sm" onclick="markAllAsRead()">Mark all as read</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadAssignedPackages();
            loadNotifications();
        });

        async function loadAssignedPackages() {
            const list = document.getElementById('assignedPackagesList');
            const countEl = document.getElementById('assignedPkgCount');
            
            try {
                const response = await fetch('../../api/trainers/get-assigned-packages.php');
                const data = await response.json();
                
                if (data.success) {
                    countEl.textContent = data.data.length;
                    if (data.data.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--dark-text-secondary);"><i class="fas fa-dumbbell" style="font-size: 3rem; opacity: 0.2; margin-bottom: 15px; display: block;"></i> No packages assigned to you yet.</div>';
                        return;
                    }
                    
                    list.innerHTML = data.data.map(pkg => `
                        <div style="padding: 16px; border-bottom: 1px solid var(--dark-border); display: flex; align-items: center; gap: 15px;">
                            <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--glass); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="font-weight: 700;">${pkg.name}</h4>
                                <p style="font-size: 0.8rem; color: var(--dark-text-secondary);">${pkg.duration} • ₱${parseFloat(pkg.price).toLocaleString()}</p>
                            </div>
                            <div class="status-badge status-verified" style="font-size: 0.7rem;">Active</div>
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading packages:', error);
                list.innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to load packages.</p>';
            }
        }

        async function loadNotifications() {
            const list = document.getElementById('notificationsList');
            const badge = document.getElementById('notifBadge');
            const badgeFloat = document.getElementById('notifBadgeFloat');
            
            try {
                const response = await fetch('../../api/notifications/get-all.php');
                const data = await response.json();
                
                if (data.success) {
                    const unreadCount = data.data.filter(n => !n.is_read).length;
                    
                    // Update header badge
                    badge.textContent = unreadCount;
                    badge.style.display = unreadCount > 0 ? 'flex' : 'none';
                    
                    // Update float badge
                    if (badgeFloat) {
                        badgeFloat.textContent = unreadCount;
                        badgeFloat.style.display = unreadCount > 0 ? 'flex' : 'none';
                    }
                    
                    if (data.data.length === 0) {
                        list.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--dark-text-secondary);">No notifications yet.</p>';
                        return;
                    }
                    
                    list.innerHTML = data.data.map(n => `
                        <div style="padding: 15px; border-bottom: 1px solid var(--dark-border); position: relative; ${!n.is_read ? 'background: rgba(59, 130, 246, 0.05);' : ''}" onclick="markAsRead(${n.id})">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <strong style="font-size: 0.9rem; color: ${n.type === 'assignment' ? 'var(--primary)' : 'white'};">${n.title}</strong>
                                <span style="font-size: 0.7rem; color: #555;">${new Date(n.created_at).toLocaleDateString()}</span>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--dark-text-secondary); line-height: 1.4;">${n.message}</p>
                            ${!n.is_read ? '<div style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--primary);"></div>' : ''}
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        async function markAsRead(id) {
            try {
                await fetch('../../api/notifications/mark-as-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        async function markAllAsRead() {
            try {
                await fetch('../../api/notifications/mark-as-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: 0 })
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        function toggleNotifications() {
            document.getElementById('notificationsModal').classList.toggle('active');
        }

        async function handleLogout() {
            if (!confirm('Are you sure you want to logout?')) return;
            try {
                const response = await fetch('../../api/auth/logout.php', { method: 'POST' });
                window.location.href = '../../index.php';
            } catch (error) {
                window.location.href = '../../index.php';
            }
        }

        document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
