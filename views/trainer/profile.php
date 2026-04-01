<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireTrainer();
$user = getCurrentUser();

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM trainers WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | FitPay Trainer</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
    <style>
        /* Modern Notification Modal Styling */
        #notificationsModal .modal {
            background: var(--dark-card) !important;
            border: 1px solid var(--dark-border) !important;
            border-radius: 32px !important;
            overflow: hidden;
            box-shadow: var(--shadow-xl) !important;
            color: var(--dark-text);
        }

        #notificationsModal .modal-header {
            padding: 32px 32px 16px !important;
            background: transparent !important;
        }

        #notificationsModal .modal-header h3 {
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary);
        }

        #notificationsModal .notif-tabs {
            display: flex;
            gap: 8px;
            padding: 0 32px 24px;
            border-bottom: 1px solid var(--dark-border);
        }

        .notif-tab-btn {
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--dark-text-secondary);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notif-tab-btn:hover {
            color: var(--primary);
            background: var(--glass);
        }

        .notif-tab-btn.active {
            color: var(--primary);
            background: var(--glass);
            border-color: var(--dark-border);
            box-shadow: var(--shadow-sm);
        }

        .notif-item {
            padding: 20px 32px;
            border-bottom: 1px solid var(--dark-border);
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            background: var(--dark-card);
        }

        .notif-item:hover {
            background: var(--glass);
        }

        .notif-item.unread::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: #3b82f6;
            border-radius: 50%;
            box-shadow: 0 0 10px #3b82f6;
        }

        .schedule-item {
            padding: 16px 32px;
            display: flex;
            gap: 20px;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.2s ease;
        }

        .schedule-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .date-tile {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
        }

        .date-tile.today {
            background: #fff;
            color: #000;
            border-color: #fff;
        }

        /* Floating Notification Icon */
        .trainer-notif-float {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 48px;
            height: 48px;
            background: #fff;
            color: #000;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
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
            font-size: 0.6rem;
            font-weight: 800;
            width: 18px;
            height: 18px;
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>My Clients</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercise Library</span></a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> <span>My Profile</span></a></li>
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
                <h1>My Profile</h1>
                <p>Manage your trainer information and account settings</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px; max-width: 800px;">
            <div class="card-header">
                <h3>Trainer Details</h3>
            </div>
            
            <div style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--dark-border);">
                    <div class="admin-avatar" style="width: 64px; height: 64px; font-size: 1.5rem; background: var(--glass); color: var(--primary);">
                        <?php 
                            $initials = '';
                            foreach(explode(' ', $user['name']) as $word) {
                                if (!empty($word)) $initials .= strtoupper($word[0]);
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div>
                        <h2 style="font-weight: 800; margin-bottom: 4px; font-size: 1.2rem;"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p style="color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem;"><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                        <p style="color: var(--dark-text-secondary); margin-top: 4px; font-size: 0.75rem;"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">Contact Number</span>
                        <span style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($user['contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">Trainer ID</span>
                        <span style="font-weight: 600; font-size: 0.85rem;">TRN-<?php echo str_pad($trainer['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div style="grid-column: 1/-1; display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">Professional Bio</span>
                        <p style="line-height: 1.6; color: var(--dark-text-secondary); font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($trainer['bio'] ?: 'No bio provided. Update your profile via the admin panel.')); ?></p>
                    </div>
                </div>
                
                <div style="margin-top: 32px; padding: 20px; background: var(--glass); border-radius: 12px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 12px; font-size: 0.9rem;"><i class="fas fa-lock" style="margin-right: 8px;"></i> Security</h4>
                    <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-bottom: 16px;">Want to change your password or update your contact information? Please contact the gym administrator.</p>
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
        <div class="modal" style="max-width: 540px;">
            <div class="modal-header">
                <h3><i class="fas fa-bell" style="color: var(--primary);"></i> Notifications</h3>
                <button class="close-modal" onclick="toggleNotifications()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="notif-tabs">
                <button class="notif-tab-btn active" onclick="switchNotifTab('notifs')" id="notifTabNotifs">Updates</button>
                <button class="notif-tab-btn" onclick="switchNotifTab('schedule')" id="notifTabSchedule">My Schedule</button>
            </div>

            <div class="modal-body" style="padding: 0;">
                <div id="notifsTabContent" style="max-height: 480px; overflow-y: auto;">
                    <div id="notificationsList">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <div id="scheduleTabContent" style="max-height: 480px; overflow-y: auto; display: none;">
                    <div id="upcomingSessionsList">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 24px 32px; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2);">
                <button class="action-btn-modern primary" onclick="markAllAsRead()" style="width: 100%; justify-content: center; height: 48px; border-radius: 16px;">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadTrainerNotifications();
        });

        async function loadTrainerNotifications() {
            const list = document.getElementById('notificationsList');
            const sessionList = document.getElementById('upcomingSessionsList');
            const badgeFloat = document.getElementById('notifBadgeFloat');
            
            try {
                // 1. Fetch Notifications
                const response = await fetch('../../api/notifications/get-all.php');
                const data = await response.json();
                
                // 2. Fetch Upcoming Sessions
                const sResp = await fetch('../../api/trainers/get-sessions.php?upcoming=1');
                const sessions = await sResp.json();
                
                if (data.success) {
                    const unreadCount = data.data.filter(n => !n.is_read).length;
                    const upcomingCount = sessions.length;
                    const totalBadgeCount = unreadCount + upcomingCount;
                    
                    if (badgeFloat) {
                        badgeFloat.textContent = totalBadgeCount;
                        badgeFloat.style.display = totalBadgeCount > 0 ? 'flex' : 'none';
                    }

                    // Render Notifications
                    if (list) {
                        if (data.data.length === 0) {
                            list.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--premium-text-muted);">No notifications yet.</p>';
                        } else {
                            list.innerHTML = data.data.map(n => `
                                <div style="padding: 15px; border-bottom: 1px solid var(--premium-border); position: relative; cursor: pointer; ${!n.is_read ? 'background: rgba(59, 130, 246, 0.05);' : ''}" onclick="markAsRead(${n.id})">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <strong style="font-size: 0.9rem; color: ${n.type === 'assignment' ? '#3b82f6' : 'white'};">${n.title}</strong>
                                        <span style="font-size: 0.7rem; color: var(--premium-text-muted);">${new Date(n.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--premium-text-muted); line-height: 1.4;">${n.message}</p>
                                    ${!n.is_read ? '<div style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #3b82f6;"></div>' : ''}
                                </div>
                            `).join('');
                        }
                    }

                    // Render Upcoming Sessions
                    if (sessionList) {
                        if (sessions.length === 0) {
                            sessionList.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--premium-text-muted);">No upcoming sessions scheduled.</p>';
                        } else {
                            sessionList.innerHTML = sessions.map(s => {
                                const date = new Date(s.start);
                                const isToday = date.toDateString() === new Date().toDateString();
                                return `
                                    <div style="padding: 15px; border-bottom: 1px solid var(--premium-border); display: flex; gap: 15px; align-items: center;">
                                        <div style="width: 45px; height: 45px; border-radius: 12px; background: ${isToday ? '#3b82f6' : 'rgba(255,255,255,0.05)'}; color: ${isToday ? '#fff' : '#3b82f6'}; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid ${isToday ? '#3b82f6' : 'var(--premium-border)'};">
                                            <span style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase;">${date.toLocaleDateString('en-US', { month: 'short' })}</span>
                                            <span style="font-size: 1rem; font-weight: 900; line-height: 1;">${date.getDate()}</span>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <h4 style="font-size: 0.9rem; font-weight: 800; color: #fff;">${s.title}</h4>
                                                ${isToday ? '<span style="font-size: 0.6rem; background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Today</span>' : ''}
                                            </div>
                                            <p style="font-size: 0.8rem; color: #3b82f6; font-weight: 700; margin: 2px 0;">Client: ${s.member_name}</p>
                                            <p style="font-size: 0.75rem; color: var(--premium-text-muted); display: flex; align-items: center; gap: 5px;">
                                                <i class="far fa-clock"></i> ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </p>
                                        </div>
                                    </div>
                                `;
                            }).join('');
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading trainer notifications:', error);
            }
        }

        function toggleNotifications() {
            const modal = document.getElementById('notificationsModal');
            modal.classList.toggle('active');
            if (modal.classList.contains('active')) {
                loadTrainerNotifications();
            }
        }

        function switchNotifTab(tab) {
            const isNotifs = tab === 'notifs';
            document.getElementById('notifTabNotifs').classList.toggle('active', isNotifs);
            document.getElementById('notifTabSchedule').classList.toggle('active', !isNotifs);
            document.getElementById('notifsTabContent').style.display = isNotifs ? 'block' : 'none';
            document.getElementById('scheduleTabContent').style.display = isNotifs ? 'none' : 'block';
        }

        async function markAsRead(id) {
            try {
                await fetch('../../api/notifications/mark-as-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadTrainerNotifications();
            } catch (e) { console.error('Error marking as read:', e); }
        }

        async function markAllAsRead() {
            try {
                await fetch('../../api/notifications/mark-all-as-read.php', { method: 'POST' });
                loadTrainerNotifications();
            } catch (e) { console.error('Error marking all as read:', e); }
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
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
