<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireTrainer();
$user = getCurrentUser();

$conn = getDBConnection();

// Fetch all verified gym members
$query = "
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.contact, 
        u.address,
        u.created_at,
        (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status = 'verified') as booking_count,
        (SELECT MAX(expires_at) FROM bookings b WHERE b.user_id = u.id AND b.status = 'verified') as latest_expiry
    FROM users u 
    WHERE u.role = 'user' 
    AND EXISTS (SELECT 1 FROM bookings b WHERE b.user_id = u.id AND b.status = 'verified')
    ORDER BY u.name ASC
";

$result = $conn->query($query);
$members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clients | FitPay Trainer</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">

    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <style>
        :root {
            --premium-bg: #050505;
            --premium-card: #0f0f0f;
            --premium-border: rgba(255, 255, 255, 0.06);
            --premium-accent: #ffffff;
            --premium-text-muted: rgba(255, 255, 255, 0.4);
            --premium-input-bg: rgba(255, 255, 255, 0.02);
            --premium-input-hover: rgba(255, 255, 255, 0.04);
        }

        body {
            background-color: var(--premium-bg) !important;
            color: #fff !important;
            font-family: 'Inter', sans-serif !important;
        }

        .main-content {
            background-color: var(--premium-bg) !important;
            min-height: 100vh;
        }

        .sidebar {
            background: #000 !important;
            border-right: 1px solid var(--premium-border) !important;
        }

        .top-bar {
            background: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--premium-border) !important;
            padding: 24px 40px !important;
        }

        .page-title h1 {
            font-size: 2rem !important;
            font-weight: 900 !important;
            letter-spacing: -1px !important;
            color: #fff !important;
        }

        .page-title p {
            color: var(--premium-text-muted) !important;
            font-weight: 500 !important;
        }

        .content-card {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .card-header {
            padding: 0 24px !important;
            border: none !important;
            margin-bottom: 32px;
        }

        .card-header h3 {
            font-size: 1.5rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px !important;
        }

        .search-box {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 16px !important;
            padding: 12px 20px !important;
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: rgba(255, 255, 255, 0.2) !important;
            background: var(--premium-input-hover) !important;
        }

        .search-box input {
            color: #fff !important;
            font-weight: 500 !important;
        }

        .search-box i {
            color: var(--premium-text-muted) !important;
        }

        /* Modal Overlay Premium */
        .modal-overlay {
            background: rgba(0, 0, 0, 0.85) !important;
            backdrop-filter: blur(12px) !important;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px) scale(0.98); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-overlay.active .modal {
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Modal Structure Refinement */
        .modal {
            background: var(--premium-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 32px !important;
            overflow: hidden;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8) !important;
            width: 100%;
            max-width: 720px !important;
        }

        /* Modal Tabs Modern */
        .tabs {
            background: var(--premium-input-bg) !important;
            padding: 8px !important;
            border-radius: 20px !important;
            display: flex;
            gap: 6px;
            border: 1px solid var(--premium-border) !important;
            margin-bottom: 32px !important;
        }

        .tab-btn {
            flex: 1;
            padding: 14px !important;
            border-radius: 14px !important;
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            color: var(--premium-text-muted) !important;
            background: transparent !important;
            border: none !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            text-transform: none !important;
            letter-spacing: -0.2px;
        }

        .tab-btn:hover {
            color: #fff !important;
            background: var(--premium-input-hover) !important;
        }

        .tab-btn.active {
            background: #fff !important;
            color: #000 !important;
            box-shadow: 0 8px 24px rgba(255, 255, 255, 0.15) !important;
        }

        .tab-btn.active::after { display: none !important; }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--premium-text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }

        .modern-input {
            width: 100%;
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 18px !important;
            padding: 18px !important;
            color: #fff !important;
            font-size: 1rem !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            outline: none !important;
        }

        .modern-input:focus {
            background: var(--premium-input-hover) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .save-btn {
            width: 100%;
            background: #fff !important;
            color: #000 !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 20px !important;
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
        }

        .save-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 30px rgba(255, 255, 255, 0.15);
            background: #fff !important;
        }

        .save-btn:active {
            transform: scale(0.98);
        }

        .close-modal {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            width: 44px;
            height: 44px;
            border-radius: 16px;
            color: #fff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(239, 68, 68, 0.1) !important;
            color: #ef4444 !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
        }

        /* FullCalendar Customization */
        .fc {
            --fc-border-color: rgba(255, 255, 255, 0.15);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: transparent;
            --fc-list-event-hover-bg-color: var(--premium-input-hover);
            --fc-today-bg-color: rgba(255, 255, 255, 0.05);
            font-family: 'Inter', sans-serif;
        }

        .fc .fc-toolbar-title {
            font-size: 1.2rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px;
            color: #fff;
        }

        .fc .fc-button-primary {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 12px !important;
            padding: 8px 16px !important;
            font-weight: 700 !important;
            font-size: 0.85rem !important;
            transition: all 0.2s !important;
        }

        .fc .fc-button-primary:hover {
            background: var(--premium-input-hover) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }

        .fc .fc-col-header-cell {
            padding: 12px 0 !important;
            font-size: 0.75rem !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--premium-text-muted);
        }

        .fc-theme-standard td, .fc-theme-standard th {
            border-color: rgba(255, 255, 255, 0.05) !important;
        }

        .fc-daygrid-day-number {
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            padding: 8px !important;
            color: var(--premium-text-muted) !important;
        }

        .fc-day-today .fc-daygrid-day-number {
            color: #fff !important;
        }

        /* Member Card Premium */
        .member-card {
            background: var(--premium-card);
            border: 1px solid var(--premium-border);
            border-radius: 28px;
            padding: 32px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .member-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.5);
        }

        .member-avatar-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .member-avatar {
            width: 64px;
            height: 64px;
            background: #fff;
            color: #000;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 900;
            box-shadow: 0 10px 20px rgba(255, 255, 255, 0.1);
        }

        .status-pill {
            position: absolute;
            top: 32px;
            right: 32px;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-pill.active {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-pill.expired {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .member-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: rgba(255, 255, 255, 0.02);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid var(--premium-border);
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--premium-text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
        }

        .member-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .action-btn-modern {
            padding: 14px;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--premium-border);
            color: #fff;
            background: rgba(255, 255, 255, 0.03);
        }

        .action-btn-modern:hover {
            background: #fff;
            color: #000;
            border-color: #fff;
        }

        .action-btn-modern.primary {
            background: #fff;
            color: #000;
            border-color: #fff;
        }

        .action-btn-modern.primary:hover {
            background: #f0f0f0;
            transform: scale(1.02);
        }

        .manage-btn {
            grid-column: span 2;
            background: transparent;
            color: var(--premium-text-muted);
            border: 1px dashed var(--premium-border);
        }

        .manage-btn:hover {
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            background: transparent;
        }
    </style>

    <!-- Event Details Modal -->
    <div class="modal-overlay" id="eventDetailsModal">
        <div class="modal" style="max-width: 500px !important;">
            <div class="modal-header" style="padding: 32px 32px 16px; border: none; background: transparent; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                        <div id="eventIconBox" style="width: 28px; height: 28px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #000;">
                            <i class="fas fa-calendar-check" style="font-size: 0.8rem;"></i>
                        </div>
                        <h4 id="eventCategoryLabel" style="font-size: 0.75rem; font-weight: 800; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 1px;">Event Details</h4>
                    </div>
                    <h3 id="eventTitleDisplay" style="font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -0.5px;">Session Title</h3>
                </div>
                <button class="close-modal" onclick="closeEventDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 0 32px 32px;">
                <div id="eventMainDetails" style="background: var(--premium-input-bg); border: 1px solid var(--premium-border); border-radius: 20px; padding: 20px; margin-bottom: 24px; display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="far fa-clock" style="color: var(--premium-text-muted);"></i>
                        <span id="eventTimeDisplay" style="font-size: 0.95rem; font-weight: 600; color: #fff;">08:00 AM</span>
                    </div>
                    <div id="eventNotesBox">
                        <p style="font-size: 0.75rem; font-weight: 700; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Notes & Insights</p>
                        <p id="eventNotesDisplay" style="font-size: 0.9rem; color: rgba(255,255,255,0.8); line-height: 1.6;">No notes provided for this session.</p>
                    </div>
                </div>

                <div id="eventExercisesSection" style="display: none;">
                    <p style="font-size: 0.75rem; font-weight: 800; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 16px;">Planned Exercises</p>
                    <div id="eventExercisesList" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            <li><a href="members.php" class="active"><i class="fas fa-users"></i> <span>My Clients</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="plans.php"><i class="fas fa-clipboard-list"></i> <span>Exercise Plans</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
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
                <h1>My Clients</h1>
                <p>Manage your assigned clients' fitness progress and plans</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="memberSearch" placeholder="Search clients..." oninput="filterMembers()">
                </div>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Assigned Members</h3>
            </div>
            
            <div id="membersGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px; padding: 24px;">
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p style="margin-top: 10px;">Loading assigned clients...</p>
                </div>
            </div>

            <div id="noMembersMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3>No assigned clients found</h3>
                <p>Members will appear here once they are assigned to you by the administrator.</p>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <!-- Progress Modal -->
    <div class="modal-overlay" id="progressModal">
        <div class="modal">
            <div class="modal-header" style="padding: 40px 40px 24px; border: none; background: transparent; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <div style="width: 32px; height: 32px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #000;">
                            <i class="fas fa-chart-line" style="font-size: 0.9rem;"></i>
                        </div>
                        <h3 id="progressMemberName" style="font-size: 1.75rem; font-weight: 800; color: #fff; letter-spacing: -0.8px;">Member</h3>
                    </div>
                    <p style="color: var(--premium-text-muted); font-size: 0.95rem; font-weight: 500;">Performance & Progress Tracking</p>
                </div>
                <button class="close-modal" onclick="closeProgressModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 0 40px 40px;">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('log')" id="tabLog">Log Session</button>
                    <button class="tab-btn" onclick="switchTab('history')" id="tabHistory">History</button>
                    <button class="tab-btn" onclick="switchTab('calendar')" id="tabCalendar">Calendar</button>
                </div>

                <!-- Log Progress Tab -->
                <div id="logTabContent">
                    <form id="progressForm" style="display: flex; flex-direction: column; gap: 28px;">
                        <input type="hidden" id="progressBookingId">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label>Session Date</label>
                                <input type="date" id="progressDate" required value="<?php echo date('Y-m-d'); ?>" class="modern-input">
                            </div>
                            <div class="form-group">
                                <label>Body Weight</label>
                                <div style="position: relative;">
                                    <input type="number" id="progressWeight" step="0.1" placeholder="00.0" class="modern-input" style="padding-right: 60px !important;">
                                    <span style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); color: var(--premium-text-muted); font-size: 0.75rem; font-weight: 800; letter-spacing: 1px;">KG</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Training Notes & Insights</label>
                            <textarea id="progressRemarks" rows="5" placeholder="Enter detailed session notes, performance metrics, or dietary advice..." class="modern-input" style="resize: none; line-height: 1.6;"></textarea>
                        </div>
                        <button type="submit" class="save-btn">
                            Save Progress Log
                        </button>
                    </form>
                </div>

                <!-- History Tab -->
                <div id="historyTabContent" style="display: none;">
                    <div id="progressHistoryList" style="max-height: 480px; overflow-y: auto; padding-right: 12px; display: flex; flex-direction: column; gap: 20px;">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Calendar Tab -->
                <div id="calendarTabContent" style="display: none;">
                    <div style="background: var(--premium-input-bg); padding: 24px; border-radius: 24px; border: 1px solid var(--premium-border);">
                        <div id="progressCalendar" style="height: 450px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allClients = [];
        let activeBookingId = null;
        let progressCalendar = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadClients();
            document.getElementById('progressForm')?.addEventListener('submit', handleProgressSubmit);
        });

        function switchTab(tab) {
            const tabs = ['log', 'history', 'calendar'];
            tabs.forEach(t => {
                const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
                const content = document.getElementById(t + 'TabContent');
                if (btn) btn.classList.toggle('active', t === tab);
                if (content) content.style.display = (t === tab) ? 'block' : 'none';
            });

            if (tab === 'history') loadProgressHistory();
            if (tab === 'calendar') initProgressCalendar();
        }

        function initProgressCalendar() {
            const calendarEl = document.getElementById('progressCalendar');
            if (!calendarEl) return;
            
            // Give the browser a moment to handle the display:block change
            setTimeout(() => {
                if (progressCalendar) {
                    progressCalendar.destroy();
                }
                
                progressCalendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    themeSystem: 'standard',
                    height: 'auto',
                    contentHeight: 450,
                    eventSources: [
                        {
                            url: `../../api/trainers/get-progress-history.php?booking_id=${activeBookingId}&calendar=1`,
                            color: '#ffffff'
                        },
                        {
                            url: `../../api/trainers/get-sessions.php?booking_id=${activeBookingId}`,
                            color: '#3b82f6'
                        }
                    ],
                    eventDidMount: function(info) {
                        info.el.style.borderRadius = '6px';
                        info.el.style.padding = '2px 4px';
                        info.el.style.fontWeight = '700';
                        info.el.style.fontSize = '0.75rem';

                        if (info.event.extendedProps.type === 'progress') {
                            info.el.style.backgroundColor = '#fff';
                            info.el.style.borderColor = '#fff';
                            info.el.style.color = '#000';
                            info.el.title = info.event.extendedProps.remarks || 'Progress log';
                        } else {
                            // It's a session event
                            info.el.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
                            info.el.style.color = '#3b82f6';
                            info.el.style.borderLeft = '3px solid #3b82f6';
                            info.el.title = info.event.title;
                        }
                    },
                    eventClick: function(info) {
                        showEventDetails(info.event);
                    }
                });

                progressCalendar.render();
                
                // Force a size update after render
                setTimeout(() => {
                    progressCalendar.updateSize();
                }, 100);
            }, 50);
        }

        function showEventDetails(event) {
            const modal = document.getElementById('eventDetailsModal');
            const title = document.getElementById('eventTitleDisplay');
            const category = document.getElementById('eventCategoryLabel');
            const time = document.getElementById('eventTimeDisplay');
            const notes = document.getElementById('eventNotesDisplay');
            const exSection = document.getElementById('eventExercisesSection');
            const exList = document.getElementById('eventExercisesList');
            const iconBox = document.getElementById('eventIconBox');
            
            // Reset state
            exSection.style.display = 'none';
            exList.innerHTML = '';
            
            const details = event.extendedProps;
            
            if (details.type === 'progress') {
                category.innerText = 'Progress Log';
                title.innerText = event.title.replace('⚖️ ', '') + ' KG';
                time.innerText = new Date(event.start).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                notes.innerText = details.remarks || 'No notes provided for this log.';
                iconBox.style.background = '#fff';
                iconBox.style.color = '#000';
                iconBox.innerHTML = '<i class="fas fa-weight"></i>';
            } else {
                category.innerText = (details.type || 'Workout').toUpperCase() + ' SESSION';
                title.innerText = event.title;
                time.innerText = event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' • ' + event.start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                notes.innerText = details.notes || 'No notes provided for this session.';
                iconBox.style.background = '#3b82f6';
                iconBox.style.color = '#fff';
                iconBox.innerHTML = '<i class="fas fa-dumbbell"></i>';
                
                if (details.exercise_details && details.exercise_details.length > 0) {
                    exSection.style.display = 'block';
                    exList.innerHTML = details.exercise_details.map(ex => `
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--premium-border); border-radius: 14px; padding: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.95rem; font-weight: 700; color: #fff;">${ex.name}</span>
                            <span style="font-size: 0.8rem; font-weight: 800; color: #fff; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 8px;">${ex.sets} × ${ex.reps}</span>
                        </div>
                    `).join('');
                }
            }
            
            modal.classList.add('active');
        }

        function closeEventDetailsModal() {
            document.getElementById('eventDetailsModal').classList.remove('active');
        }

        async function loadClients() {
            const grid = document.getElementById('membersGrid');
            const noMsg = document.getElementById('noMembersMessage');
            
            try {
                const response = await fetch('../../api/trainers/get-clients.php');
                const data = await response.json();
                
                if (data.success) {
                    allClients = data.data;
                    renderClients(allClients);
                } else {
                    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;"><p style="color: #ef4444;">${data.message}</p></div>`;
                }
            } catch (error) {
                console.error('Error loading clients:', error);
                grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;"><p style="color: #ef4444;">Failed to load clients.</p></div>`;
            }
        }

        function renderClients(clients) {
            const grid = document.getElementById('membersGrid');
            const noMsg = document.getElementById('noMembersMessage');
            if (clients.length === 0) {
                grid.style.display = 'none';
                noMsg.style.display = 'block';
                return;
            }
            grid.style.display = 'grid';
            noMsg.style.display = 'none';
            
            grid.innerHTML = clients.map(member => {
                const initials = member.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                const expiryDate = new Date(member.expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                return `
                    <div class="member-card">
                        <div class="status-pill ${member.is_expired ? 'expired' : 'active'}">
                            <i class="fas fa-${member.is_expired ? 'times-circle' : 'check-circle'}"></i>
                            ${member.is_expired ? 'Expired' : 'Active'}
                        </div>
                        
                        <div class="member-avatar-wrapper">
                            <div class="member-avatar">${initials}</div>
                            <div>
                                <h3 style="font-weight: 800; color: #fff; font-size: 1.15rem; letter-spacing: -0.5px;">${member.name}</h3>
                                <p style="color: var(--premium-text-muted); font-size: 0.8rem; font-weight: 500;">${member.email}</p>
                            </div>
                        </div>

                        <div class="member-info-grid">
                            <div class="info-item">
                                <span class="info-label">Current Plan</span>
                                <span class="info-value">${member.package_name}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact</span>
                                <span class="info-value">${member.contact || 'Not set'}</span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Subscription Expiry</span>
                                <span class="info-value" style="display: flex; align-items: center; gap: 6px;">
                                    <i class="far fa-calendar-alt" style="opacity: 0.4;"></i>
                                    ${expiryDate}
                                </span>
                            </div>
                        </div>

                        <div class="member-actions">
                            <div class="action-btn-modern primary" onclick="viewProgress(${member.booking_id})" style="grid-column: span 2;">
                                <i class="fas fa-chart-line"></i> Track Progress & Performance
                            </div>
                            <div class="action-btn-modern manage-btn" onclick="manageClient(${member.booking_id})">
                                <i class="fas fa-user-cog"></i> Client Management
                            </div>
                        </div>
                    </div>`;
            }).join('');
        }

        function filterMembers() {
            const query = document.getElementById('memberSearch').value.toLowerCase();
            const filtered = allClients.filter(c => c.name.toLowerCase().includes(query) || c.email.toLowerCase().includes(query));
            renderClients(filtered);
        }

        function showRestrictionMessage() { showNotification('This member is on a basic package which does not include trainer-managed exercise plans.', 'warning'); }

        async function handleLogout() {
            if (!confirm('Are you sure you want to logout?')) return;
            try { await fetch('../../api/auth/logout.php', { method: 'POST' }); window.location.href = '../../index.php'; } catch (error) { window.location.href = '../../index.php'; }
        }

        function assignPlan(bookingId) { window.location.href = `plans.php?booking_id=${bookingId}`; }
        function manageClient(bookingId) { window.location.href = `client-details.php?booking_id=${bookingId}`; }

        async function viewProgress(bookingId) {
            const member = allClients.find(c => c.booking_id == bookingId);
            if (!member) return;
            activeBookingId = bookingId;
            document.getElementById('progressMemberName').textContent = member.name;
            document.getElementById('progressBookingId').value = bookingId;
            document.getElementById('progressModal').classList.add('active');
            switchTab('log');
            document.getElementById('progressForm').reset();
            document.getElementById('progressDate').value = new Date().toISOString().split('T')[0];
        }

        function closeProgressModal() { document.getElementById('progressModal').classList.remove('active'); activeBookingId = null; }

        async function handleProgressSubmit(e) {
            e.preventDefault();
            const saveBtn = e.target.querySelector('button[type="submit"]');
            const originalContent = saveBtn.innerHTML;
            try {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                const response = await fetch('../../api/trainers/log-progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: document.getElementById('progressBookingId').value,
                        weight: document.getElementById('progressWeight').value,
                        remarks: document.getElementById('progressRemarks').value,
                        logged_at: document.getElementById('progressDate').value
                    })
                });
                const data = await response.json();
                if (data.success) { showNotification('Progress logged successfully!', 'success'); switchTab('history'); } else { showNotification(data.message, 'warning'); }
            } catch (error) { console.error('Error logging progress:', error); showNotification('Error saving progress', 'warning'); } finally { saveBtn.disabled = false; saveBtn.innerHTML = originalContent; }
        }

        async function loadProgressHistory() {
            const list = document.getElementById('progressHistoryList');
            list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--premium-text-muted);"><i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 12px;"></i><p>Retrieving performance logs...</p></div>';
            try {
                const response = await fetch(`../../api/trainers/get-progress-history.php?booking_id=${activeBookingId}`);
                const data = await response.json();
                if (data.success && data.data.length > 0) {
                    list.innerHTML = data.data.map(log => `
                        <div style="background: var(--premium-input-bg); border: 1px solid var(--premium-border); border-radius: 24px; padding: 24px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); cursor: default; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='rgba(255,255,255,0.2)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--premium-border)'; this.style.transform='translateY(0)'">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid var(--premium-border);">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <p style="font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -0.3px;">${new Date(log.logged_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
                                        <p style="font-size: 0.75rem; color: var(--premium-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Session Log</p>
                                    </div>
                                </div>
                                ${log.weight ? `
                                <div style="text-align: right;">
                                    <div style="background: #fff; color: #000; padding: 8px 16px; border-radius: 12px; font-weight: 900; font-size: 1.1rem; box-shadow: 0 8px 20px rgba(255,255,255,0.15); display: inline-flex; align-items: baseline; gap: 4px;">
                                        ${log.weight} <span style="font-size: 0.7rem; font-weight: 700; opacity: 0.7;">KG</span>
                                    </div>
                                </div>` : ''}
                            </div>
                            <div style="background: rgba(255,255,255,0.02); padding: 18px; border-radius: 18px; border: 1px solid rgba(255,255,255,0.03);">
                                <p style="font-size: 0.95rem; color: rgba(255,255,255,0.85); line-height: 1.6; font-weight: 400;">
                                    ${log.remarks ? log.remarks : '<span style="color: var(--premium-text-muted); font-style: italic;">No session notes provided.</span>'}
                                </p>
                            </div>
                        </div>`).join('');
                } else { 
                    list.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; color: var(--premium-text-muted);">
                            <div style="width: 64px; height: 64px; background: var(--premium-input-bg); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem;">
                                <i class="fas fa-history"></i>
                            </div>
                            <h4 style="color: #fff; font-weight: 800; margin-bottom: 8px;">No records yet</h4>
                            <p style="font-size: 0.9rem;">Start logging sessions to see the history here.</p>
                        </div>`; 
                }
            } catch (error) { console.error('Error loading history:', error); list.innerHTML = '<div style="text-align: center; padding: 20px; color: #ef4444;">Failed to load history.</div>'; }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            const icon = type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-circle' : 'info-circle';
            const bg = type === 'success' ? '#fff' : type === 'warning' ? '#f59e0b' : '#3b82f6';
            const color = type === 'success' ? '#000' : '#fff';
            
            notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
            notification.style.cssText = `
                position: fixed; 
                bottom: 32px; 
                right: 32px; 
                background: ${bg}; 
                color: ${color}; 
                padding: 20px 32px; 
                border-radius: 20px; 
                display: flex; 
                align-items: center; 
                gap: 16px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
                z-index: 10001; 
                animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); 
                font-weight: 800;
                font-size: 0.95rem;
                letter-spacing: -0.2px;
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'fadeIn 0.3s ease reverse forwards';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        document.getElementById('mobileMenuToggle')?.addEventListener('click', () => { document.querySelector('.sidebar').classList.toggle('active'); });
        window.onclick = function(event) { if (event.target.classList.contains('modal-overlay')) { event.target.classList.remove('active'); } }
    </script>
</body>
</html>
