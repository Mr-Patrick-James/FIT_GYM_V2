<?php
require_once '../../api/session.php';
requireTrainer();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Exercises | FitPay Trainer</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
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
    <style>
        .package-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .exercise-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--dark-border);
            transition: background 0.2s;
        }
        .exercise-list-item:hover {
            background: rgba(255,255,255,0.02);
        }
        .exercise-badge {
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Modern Input Styles */
        .modern-input {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 14px !important;
            padding: 12px 16px !important;
            color: #fff !important;
            font-size: 0.8rem !important;
            font-weight: 500 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            width: 100%;
            outline: none !important;
        }

        .modern-input:focus {
            border-color: rgba(255, 255, 255, 0.3) !important;
            background: var(--premium-input-hover) !important;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05) !important;
        }

        .modern-input::placeholder {
            color: var(--premium-text-muted);
            opacity: 0.6;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--premium-text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            margin-left: 4px;
        }

        .action-btn-modern {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .action-btn-modern.primary {
            background: #fff;
            color: #000;
        }

        .action-btn-modern.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 255, 255, 0.1);
            filter: brightness(0.9);
        }

        .action-btn-modern.danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.2);
        }

        .action-btn-modern.danger:hover {
            background: #ef4444;
            color: #fff;
        }

        .template-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--premium-border);
            border-radius: 20px;
            padding: 16px;
            display: flex;
            gap: 16px;
            align-items: center;
            transition: all 0.3s ease;
        }

        .template-card:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        /* Modern Notification Modal Styling */
        #notificationsModal .modal {
            background: #000 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 32px !important;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
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
        }

        #notificationsModal .notif-tabs {
            display: flex;
            gap: 8px;
            padding: 0 32px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .notif-tab-btn {
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--premium-text-muted);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notif-tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        .notif-tab-btn.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .notif-item {
            padding: 20px 32px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .notif-item:hover {
            background: rgba(255, 255, 255, 0.02);
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
            <li><a href="packages.php" class="active"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
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
                <h1>Packages & Training Programs</h1>
                <p>Manage default workout templates for each membership tier</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Gym Packages</h3>
                <div class="card-actions">
                    <button class="card-btn primary" onclick="loadPackages()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div id="packagesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; padding: 24px;">
                <!-- Populated by JS -->
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                    <p style="margin-top: 10px;">Loading packages...</p>
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

    <!-- Exercise Management Modal -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 960px !important;">
            <div class="modal-header" style="padding: 32px 32px 16px; border: none; background: transparent;">
                <div>
                    <h3 id="exerciseModalTitle" style="font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.5px;">Manage Package Exercises</h3>
                    <p id="exerciseModalSubtitle" style="font-size: 0.75rem; font-weight: 500; color: var(--premium-text-muted); margin-top: 4px;"></p>
                </div>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 32px; padding: 0 32px 32px;">
                <!-- Current Plan List (Left) -->
                <div>
                    <h4 style="font-size: 0.95rem; font-weight: 800; color: #fff; margin-bottom: 8px;">Current Package Plan</h4>
                    <p style="font-size: 0.7rem; color: var(--premium-text-muted); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-info-circle"></i> View and manage the exercises assigned to this package.
                    </p>
                    <div id="packageExercisesList" style="max-height: 480px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 8px;">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Add Exercise Form (Right) -->
                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--premium-border); border-radius: 20px; padding: 24px;">
                    <h4 style="font-size: 0.8rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;">Add Exercise to Plan</h4>
                    <form id="addExerciseForm" style="display: flex; flex-direction: column; gap: 16px;">
                        <div class="form-group">
                            <label>Select Exercise</label>
                            <select id="exerciseSelect" required class="modern-input">
                                <option value="">Choose an exercise...</option>
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Sets</label>
                                <input type="number" id="exerciseSets" value="3" min="1" class="modern-input">
                            </div>
                            <div class="form-group">
                                <label>Reps</label>
                                <input type="text" id="exerciseReps" placeholder="e.g. 12" class="modern-input">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea id="exerciseNotes" rows="4" placeholder="Special instructions..." class="modern-input" style="resize: none; font-size: 0.8rem;"></textarea>
                        </div>
                        <button type="submit" class="action-btn-modern primary" style="width: 100%; justify-content: center; margin-top: 8px; border-radius: 12px;">
                            <i class="fas fa-plus"></i> Add to Plan
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 32px; border-top: 1px solid var(--premium-border); text-align: right; background: rgba(0,0,0,0.1);">
                <button class="action-btn-modern" style="background: var(--premium-input-bg); color: #fff;" onclick="closeExerciseModal()">Close Window</button>
            </div>
        </div>
    </div>

    <script>
        let allPackages = [];
        let allExercises = [];
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

        let currentPackageId = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadPackages();
            loadAllExercises();
            document.getElementById('addExerciseForm').addEventListener('submit', handleAddExercise);
        });

        async function loadPackages() {
            const grid = document.getElementById('packagesGrid');
            try {
                const response = await fetch('../../api/packages/get-all.php');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                
                const data = await response.json();
                if (data.success) {
                    allPackages = data.data;
                    renderPackages();
                } else {
                    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--danger);">
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>${data.message || 'Failed to load packages'}</p>
                    </div>`;
                }
            } catch (err) { 
                console.error(err); 
                grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--danger);">
                    <i class="fas fa-wifi" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>Connection error. Please check your network.</p>
                </div>`;
            }
        }

        async function loadAllExercises() {
            try {
                const response = await fetch('../../api/exercises/get-all.php');
                const data = await response.json();
                if (data.success) {
                    allExercises = data.data;
                    const select = document.getElementById('exerciseSelect');
                    select.innerHTML = '<option value="">Choose an exercise...</option>' + 
                        allExercises.map(ex => `<option value="${ex.id}">${ex.name} (${ex.category})</option>`).join('');
                }
            } catch (err) { console.error(err); }
        }

        function renderPackages() {
            const grid = document.getElementById('packagesGrid');
            if (allPackages.length === 0) {
                grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--dark-text-secondary);">No packages found.</p>';
                return;
            }

            grid.innerHTML = allPackages.map(pkg => `
                <div class="content-card package-card" style="padding: 20px; border-top: 4px solid ${pkg.is_trainer_assisted ? 'var(--primary)' : 'var(--dark-border)'};">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <h3 style="font-weight: 800; color: var(--primary); font-size: 0.95rem;">${pkg.name}</h3>
                            <p style="font-size: 0.7rem; color: var(--dark-text-secondary);">${pkg.duration}</p>
                        </div>
                        ${pkg.is_trainer_assisted ? 
                            '<span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); font-size: 0.6rem; padding: 2px 8px;"><i class="fas fa-user-tie"></i> Assisted</span>' : 
                            '<span class="status-badge" style="background: var(--glass); color: var(--dark-text-secondary); font-size: 0.6rem; padding: 2px 8px;">Basic</span>'
                        }
                    </div>
                    
                    <div style="margin-bottom: 20px; min-height: 50px;">
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); line-height: 1.5;">
                            ${pkg.description ? pkg.description.split('\n')[0] : 'No description available.'}
                        </p>
                    </div>

                    <button class="card-btn primary" style="width: 100%; justify-content: center; padding: 10px; font-size: 0.75rem;" onclick="managePackageExercises(${pkg.id}, '${pkg.name}')">
                        <i class="fas fa-tasks"></i> Manage Exercises
                    </button>
                </div>
            `).join('');
        }

        async function managePackageExercises(id, name) {
            currentPackageId = id;
            document.getElementById('exerciseModalTitle').textContent = `Manage Exercises: ${name}`;
            document.getElementById('exerciseModalSubtitle').textContent = `Editing the default routine for this package.`;
            document.getElementById('exerciseModal').classList.add('active');
            loadPackageExercises();
        }

        async function loadPackageExercises() {
            const list = document.getElementById('packageExercisesList');
            list.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
            
            try {
                const response = await fetch(`../../api/packages/get-exercises.php?package_id=${currentPackageId}`);
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    list.innerHTML = data.data.map(ex => `
                        <div class="template-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--premium-border); border-radius: 12px; padding: 10px; display: flex; align-items: center; gap: 10px;">
                            <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 36px; height: 36px; border-radius: 6px; object-fit: cover; background: #000;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.8rem; font-weight: 700; color: #fff; margin-bottom: 2px;">${ex.name}</h5>
                                <p style="font-size: 0.65rem; color: var(--premium-text-muted); font-weight: 500;">${ex.sets} Sets × ${ex.reps} reps</p>
                            </div>
                            <button class="action-btn-modern danger" style="padding: 0; width: 28px; height: 28px; justify-content: center; border-radius: 6px;" onclick="removeExercise(${ex.id})" title="Remove">
                                <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; color: var(--premium-text-muted);">
                            <i class="fas fa-clipboard-list" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 12px; display: block;"></i>
                            <p style="font-size: 0.85rem;">No exercises assigned yet.</p>
                        </div>`;
                }
            } catch (err) { console.error(err); }
        }

        async function handleAddExercise(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('package_id', currentPackageId);
            formData.append('exercise_id', document.getElementById('exerciseSelect').value);
            formData.append('sets', document.getElementById('exerciseSets').value);
            formData.append('reps', document.getElementById('exerciseReps').value);
            formData.append('notes', document.getElementById('exerciseNotes').value);

            try {
                const response = await fetch('../../api/packages/add-exercise.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('Exercise added successfully', 'success');
                    document.getElementById('addExerciseForm').reset();
                    loadPackageExercises();
                } else {
                    showNotification(data.message, 'warning');
                }
            } catch (err) { console.error(err); }
        }

        async function removeExercise(exerciseId) {
            if (!confirm('Remove this exercise from the package template?')) return;
            
            const formData = new FormData();
            formData.append('package_id', currentPackageId);
            formData.append('exercise_id', exerciseId);

            try {
                const response = await fetch('../../api/packages/remove-exercise.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('Exercise removed', 'success');
                    loadPackageExercises();
                }
            } catch (err) { console.error(err); }
        }

        function closeExerciseModal() {
            document.getElementById('exerciseModal').classList.remove('active');
            currentPackageId = null;
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            notification.style.cssText = `position: fixed; top: 100px; right: 32px; background: ${type === 'success' ? '#22c55e' : '#3b82f6'}; color: white; padding: 16px 24px; border-radius: 12px; display: flex; align-items: center; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10000; animation: slideIn 0.3s ease-out; font-weight: 600;`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 5000);
        }

        async function handleLogout() {
            if (!confirm('Logout?')) return;
            await fetch('../../api/auth/logout.php', { method: 'POST' });
            window.location.href = '../../index.php';
        }

        document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
