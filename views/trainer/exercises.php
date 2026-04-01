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
    <title>Exercise Library | FitPay Trainer</title>
    
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
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <h1>FitPay</h1>
            <p>TRAINER PANEL</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>My Clients</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php" class="active"><i class="fas fa-running"></i> <span>Exercise Library</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> <span>My Profile</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $trainerName = $user['name'] ?? 'Trainer';
                $initials = '';
                foreach(explode(' ', $trainerName) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($trainerName); ?></h4>
                <p>Professional Trainer</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Exercise Library</h1>
                <p>Browse and manage the master list of gym exercises</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn primary" onclick="openAddExerciseModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Exercise</span>
                </button>
                <button class="action-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
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

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Exercises</h3>
                <div class="search-box" style="width: 300px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="exerciseSearch" placeholder="Search exercises..." oninput="filterExercises()">
                </div>
            </div>
            
            <div id="exercisesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noExercisesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-running" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No exercises found</h3>
                <p>Click "Add New Exercise" to create your first exercise entry.</p>
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

    <!-- Add/Edit Exercise Modal -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="exerciseModalTitle">Add New Exercise</h3>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="exerciseForm" onsubmit="saveExercise(event)">
                    <input type="hidden" id="exerciseId">
                    <div class="form-group">
                        <label>Exercise Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="exerciseName" required placeholder="e.g., Bench Press">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select id="exerciseCategory">
                            <option value="Chest">Chest</option>
                            <option value="Back">Back</option>
                            <option value="Legs">Legs</option>
                            <option value="Shoulders">Shoulders</option>
                            <option value="Arms">Arms</option>
                            <option value="Core">Core</option>
                            <option value="Cardio">Cardio</option>
                            <option value="Full Body">Full Body</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Equipment</label>
                        <select id="equipmentSelect">
                            <option value="">No specific equipment</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Exercise Image</label>
                        <div id="imageUploadArea" class="file-upload-area" style="padding: 20px; border: 2px dashed var(--dark-border); border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.3s;" onclick="document.getElementById('exerciseImageFile').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;">Click to upload exercise image</p>
                            <span style="font-size: 0.75rem; color: var(--dark-text-secondary);">JPG, PNG or WebP</span>
                            <input type="file" id="exerciseImageFile" accept="image/*" style="display: none;" onchange="handleImagePreview(event)">
                        </div>
                        <div id="imagePreviewContainer" style="display: none; margin-top: 15px; position: relative;">
                            <img id="imagePreview" src="" style="width: 100%; height: 180px; object-fit: cover; border-radius: 12px; border: 1px solid var(--dark-border);">
                            <button type="button" onclick="removeImagePreview()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.9); color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" id="exerciseImageUrl">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeExerciseModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Exercise
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteExerciseModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 style="color: #ef4444;">Delete Exercise</h3>
                <button class="close-modal" onclick="closeDeleteExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 16px;"></i>
                <p>Are you sure you want to delete <strong id="deleteExerciseName">this exercise</strong>?</p>
                <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-top: 8px;">
                    This will also remove it from any membership package plans it's currently assigned to.
                </p>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; display: flex; gap: 12px;">
                <button class="btn btn-secondary" style="flex: 1;" onclick="closeDeleteExerciseModal()">Cancel</button>
                <button class="btn" style="flex: 1; background: #ef4444; color: white;" onclick="confirmDeleteExercise()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/exercises.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadTrainerNotifications();
        });

        async function loadTrainerNotifications() {
            const list = document.getElementById('notificationsList');
            const sessionList = document.getElementById('upcomingSessionsList');
            const badge = document.getElementById('notificationBadge');
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
                    
                    if (badge) {
                        badge.textContent = totalBadgeCount;
                        badge.style.display = totalBadgeCount > 0 ? 'flex' : 'none';
                    }
                    if (badgeFloat) {
                        badgeFloat.textContent = totalBadgeCount;
                        badgeFloat.style.display = totalBadgeCount > 0 ? 'flex' : 'none';
                    }

                    // Render Notifications
                    if (list) {
                        if (data.data.length === 0) {
                            list.innerHTML = '<p style="text-align: center; padding: 16px; color: var(--premium-text-muted); font-size: 0.75rem;">No notifications yet.</p>';
                        } else {
                            list.innerHTML = data.data.map(n => `
                                <div style="padding: 12px; border-bottom: 1px solid var(--premium-border); position: relative; cursor: pointer; ${!n.is_read ? 'background: rgba(59, 130, 246, 0.05);' : ''}" onclick="markAsRead(${n.id})">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <strong style="font-size: 0.8rem; color: ${n.type === 'assignment' ? '#3b82f6' : 'white'};">${n.title}</strong>
                                        <span style="font-size: 0.65rem; color: var(--premium-text-muted);">${new Date(n.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p style="font-size: 0.75rem; color: var(--premium-text-muted); line-height: 1.4;">${n.message}</p>
                                    ${!n.is_read ? '<div style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #3b82f6;"></div>' : ''}
                                </div>
                            `).join('');
                        }
                    }

                    // Render Upcoming Sessions
                    if (sessionList) {
                        if (sessions.length === 0) {
                            sessionList.innerHTML = '<p style="text-align: center; padding: 16px; color: var(--premium-text-muted); font-size: 0.75rem;">No upcoming sessions scheduled.</p>';
                        } else {
                            sessionList.innerHTML = sessions.map(s => {
                                const date = new Date(s.start);
                                const isToday = date.toDateString() === new Date().toDateString();
                                return `
                                    <div style="padding: 12px; border-bottom: 1px solid var(--premium-border); display: flex; gap: 12px; align-items: center;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: ${isToday ? '#3b82f6' : 'rgba(255,255,255,0.05)'}; color: ${isToday ? '#fff' : '#3b82f6'}; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid ${isToday ? '#3b82f6' : 'var(--premium-border)'};">
                                            <span style="font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">${date.toLocaleDateString('en-US', { month: 'short' })}</span>
                                            <span style="font-size: 0.85rem; font-weight: 900; line-height: 1;">${date.getDate()}</span>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <h4 style="font-size: 0.8rem; font-weight: 800; color: #fff;">${s.title}</h4>
                                                ${isToday ? '<span style="font-size: 0.55rem; background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Today</span>' : ''}
                                            </div>
                                            <p style="font-size: 0.75rem; color: #3b82f6; font-weight: 700; margin: 2px 0;">Client: ${s.member_name}</p>
                                            <p style="font-size: 0.7rem; color: var(--premium-text-muted); display: flex; align-items: center; gap: 4px;">
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

        // Custom logout for trainer context if different
        async function handleLogout() {
            if (!confirm('Logout?')) return;
            await fetch('../../api/auth/logout.php', { method: 'POST' });
            window.location.href = '../../index.php';
        }
    </script>
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
