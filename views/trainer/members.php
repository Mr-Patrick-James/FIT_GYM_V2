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
            font-size: 1.5rem !important;
            font-weight: 900 !important;
            letter-spacing: -1px !important;
            color: #fff !important;
        }

        .page-title p {
            color: var(--premium-text-muted) !important;
            font-weight: 500 !important;
            font-size: 0.75rem !important;
        }

        .content-card {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .card-header {
            padding: 0 24px !important;
            border: none !important;
            margin-bottom: 24px;
        }

        .card-header h3 {
            font-size: 1rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px !important;
        }

        .search-box {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 12px !important;
            padding: 8px 16px !important;
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
            padding: 10px !important;
            border-radius: 12px !important;
            font-size: 0.75rem !important;
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
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--premium-text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }

        .modern-input {
            width: 100%;
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 14px !important;
            padding: 12px !important;
            color: #fff !important;
            font-size: 0.85rem !important;
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
            border-radius: 16px !important;
            padding: 16px !important;
            font-size: 0.95rem !important;
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

        /* Premium Event Styling */
        .fc-event {
            border: none !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            transition: transform 0.2s ease !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
            margin: 1px 2px !important;
            pointer-events: auto !important;
            z-index: 2 !important;
        }

        .fc-event:hover {
            transform: scale(1.02);
            z-index: 10 !important;
            filter: brightness(1.1);
        }

        .event-milestone-paid {
            background: rgba(34, 197, 94, 0.15) !important;
            color: #22c55e !important;
            border-left: 3px solid #22c55e !important;
        }

        .event-milestone-paid::before {
            content: '\f09d';
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
            font-weight: 900;
        }

        .event-milestone-expiry {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #ef4444 !important;
            border-left: 3px solid #ef4444 !important;
        }

        .event-milestone-expiry::before {
            content: '\f273';
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
            font-weight: 900;
        }

        .event-routine {
            background: rgba(59, 130, 246, 0.15) !important;
            color: #3b82f6 !important;
            border-left: 3px solid #3b82f6 !important;
        }

        .event-routine::before {
            content: '\f44b';
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
            font-weight: 900;
        }

        .event-progress {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-left: 3px solid #fff !important;
        }

        .event-progress::before {
            content: '\f24e';
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
            font-weight: 900;
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

        /* Session Indicator Badge */
        .session-indicator {
            position: absolute;
            top: 70px;
            right: 32px;
            background: #3b82f6;
            color: #fff;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .session-indicator i {
            font-size: 0.75rem;
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

    <!-- Event Details Modal -->
    <div class="modal-overlay" id="eventDetailsModal" style="z-index: 3000;">
        <div class="modal" style="max-width: 500px !important;">
            <div class="modal-header" style="padding: 24px 24px 12px; border: none; background: transparent; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <div id="eventIconBox" style="width: 24px; height: 24px; background: #fff; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #000;">
                            <i class="fas fa-calendar-check" style="font-size: 0.7rem;"></i>
                        </div>
                        <h4 id="eventCategoryLabel" style="font-size: 0.65rem; font-weight: 800; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 1px;">Event Details</h4>
                    </div>
                    <h3 id="eventTitleDisplay" style="font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: -0.5px;">Session Title</h3>
                </div>
                <button class="close-modal" onclick="closeEventDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 0 24px 24px;">
                <div id="eventMainDetails" style="background: var(--premium-input-bg); border: 1px solid var(--premium-border); border-radius: 16px; padding: 16px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="far fa-clock" style="color: var(--premium-text-muted); font-size: 0.8rem;"></i>
                        <span id="eventTimeDisplay" style="font-size: 0.8rem; font-weight: 600; color: #fff;">08:00 AM</span>
                    </div>
                    <div id="eventNotesBox">
                        <p style="font-size: 0.65rem; font-weight: 700; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Notes & Insights</p>
                        <p id="eventNotesDisplay" style="font-size: 0.75rem; color: rgba(255,255,255,0.8); line-height: 1.6;">No notes provided for this session.</p>
                    </div>
                </div>

                <div id="eventExercisesSection" style="display: none;">
                    <p style="font-size: 0.65rem; font-weight: 800; color: var(--premium-text-muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 12px;">Planned Exercises</p>
                    <div id="eventExercisesList" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal-overlay" id="progressModal">
        <div class="modal">
            <div class="modal-header" style="padding: 24px 24px 16px; border: none; background: transparent; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                        <div style="width: 28px; height: 28px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #000;">
                            <i class="fas fa-chart-line" style="font-size: 0.8rem;"></i>
                        </div>
                        <h3 id="progressMemberName" style="font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.8px;">Member</h3>
                    </div>
                    <p style="color: var(--premium-text-muted); font-size: 0.8rem; font-weight: 500;">Performance & Progress Tracking</p>
                </div>
                <button class="close-modal" onclick="closeProgressModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 0 24px 24px;">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('log')" id="tabLog">Log Session</button>
                    <button class="tab-btn" onclick="switchTab('history')" id="tabHistory">History</button>
                    <button class="tab-btn" onclick="switchTab('calendar')" id="tabCalendar">Calendar</button>
                    <button class="tab-btn" onclick="switchTab('plan')" id="tabPlan">Manage Plan</button>
                </div>

                <!-- Log Progress Tab -->
                <div id="logTabContent">
                    <form id="progressForm" style="display: flex; flex-direction: column; gap: 20px;">
                        <input type="hidden" id="progressBookingId">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Session Date</label>
                                <input type="date" id="progressDate" required value="<?php echo date('Y-m-d'); ?>" class="modern-input">
                            </div>
                            <div class="form-group">
                                <label>Body Weight</label>
                                <div style="position: relative;">
                                    <input type="number" id="progressWeight" step="0.1" placeholder="00.0" class="modern-input" style="padding-right: 50px !important;">
                                    <span style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--premium-text-muted); font-size: 0.65rem; font-weight: 800; letter-spacing: 1px;">KG</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Training Notes & Insights</label>
                            <textarea id="progressRemarks" rows="5" placeholder="Enter detailed session notes, performance metrics, or dietary advice..." class="modern-input" style="resize: none; line-height: 1.6; font-size: 0.8rem;"></textarea>
                        </div>
                        <button type="submit" class="save-btn" style="padding: 12px; font-size: 0.9rem;">
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

                <!-- Plan Tab -->
                <div id="planTabContent" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px; margin-top: 10px;">
                        <!-- Current Routine List (Left) -->
                        <div>
                            <h4 style="font-size: 1.1rem; font-weight: 800; color: #fff; margin-bottom: 8px;">Current Routine</h4>
                            <p style="font-size: 0.8rem; color: var(--premium-text-muted); margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-info-circle"></i> View and manage the exercises assigned to this client.
                            </p>
                            <div id="modalExerciseList" style="max-height: 480px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-right: 8px;">
                                <!-- Populated by JS -->
                            </div>
                            <div style="margin-top: 24px;">
                                <button class="save-btn" onclick="saveModalPlan()">
                                    <i class="fas fa-save"></i> Update Client Plan
                                </button>
                            </div>
                        </div>

                        <!-- Add Exercise Form (Right) -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 32px; box-shadow: inset 0 0 20px rgba(255,255,255,0.02); height: fit-content;">
                            <h4 style="font-size: 0.9rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px;">Add Exercise to Plan</h4>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Search Master Library</label>
                                <div class="search-box" style="background: var(--premium-input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px;">
                                    <i class="fas fa-search" style="color: var(--premium-text-muted);"></i>
                                    <input type="text" id="modalExSearch" placeholder="Filter exercises..." oninput="filterModalExercises()" style="background: transparent; border: none; color: #fff; width: 100%; padding: 12px; outline: none;">
                                </div>
                            </div>

                            <form id="addExerciseFormTab" onsubmit="event.preventDefault();" style="display: flex; flex-direction: column; gap: 20px;">
                                <div class="form-group">
                                    <label>Select Exercise</label>
                                    <select id="exerciseSelectTab" required class="modern-input" style="border-color: rgba(255,255,255,0.1) !important;">
                                        <option value="">Choose an exercise...</option>
                                    </select>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>Sets</label>
                                        <input type="number" id="exerciseSetsTab" value="3" min="1" class="modern-input" style="border-color: rgba(255,255,255,0.1) !important;">
                                    </div>
                                    <div class="form-group">
                                        <label>Reps</label>
                                        <input type="text" id="exerciseRepsTab" placeholder="e.g. 12" class="modern-input" style="border-color: rgba(255,255,255,0.1) !important;">
                                    </div>
                                </div>
                                <button type="button" class="action-btn-modern primary" style="width: 100%; justify-content: center; margin-top: 12px; border-radius: 12px; height: 50px;" onclick="addExerciseToTabPlan()">
                                    <i class="fas fa-plus"></i> Add to Plan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allClients = [];
        let activeBookingId = null;
        let progressCalendar = null;
        let modalCurrentPlan = [];
        let modalMasterExercises = [];

        // Helper functions for calendar milestones
        function parseDurationToDays(duration) {
            if (!duration) return 1;
            const d = duration.toLowerCase();
            if (d.includes('day')) return parseInt(d) || 1;
            if (d.includes('week')) return (parseInt(d) || 1) * 7;
            if (d.includes('month')) return (parseInt(d) || 1) * 30;
            if (d.includes('year')) return (parseInt(d) || 1) * 365;
            return 1;
        }

        function formatDateISO(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadClients();
            loadTrainerNotifications();
            document.getElementById('progressForm')?.addEventListener('submit', handleProgressSubmit);
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

        function switchTab(tab) {
            const tabs = ['log', 'history', 'calendar', 'plan'];
            tabs.forEach(t => {
                const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
                const content = document.getElementById(t + 'TabContent');
                if (btn) btn.classList.toggle('active', t === tab);
                if (content) content.style.display = (t === tab) ? 'block' : 'none';
            });

            if (tab === 'history') loadProgressHistory();
            if (tab === 'calendar') initProgressCalendar();
            if (tab === 'plan') loadModalPlan();
        }

        async function loadModalPlan() {
            const list = document.getElementById('modalExerciseList');
            list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--premium-text-muted);"><i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 12px;"></i><p>Retrieving routine...</p></div>';
            
            try {
                // Load member's current plan
                const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${activeBookingId}`);
                const data = await response.json();
                if (data.success) {
                    modalCurrentPlan = data.data.exercises;
                    renderModalPlan();
                }

                // Load master exercise library for the dropdown if not already loaded
                if (modalMasterExercises.length === 0) {
                    const exResp = await fetch('../../api/exercises/get-all.php');
                    const exData = await exResp.json();
                    if (exData.success) {
                        modalMasterExercises = exData.data;
                        renderExerciseDropdown(modalMasterExercises);
                    }
                } else {
                    renderExerciseDropdown(modalMasterExercises);
                }
            } catch (error) {
                console.error('Error loading plan:', error);
                list.innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to load plan.</p>';
            }
        }

        function renderExerciseDropdown(exercises) {
            const select = document.getElementById('exerciseSelectTab');
            if (!select) return;
            select.innerHTML = '<option value="">Choose an exercise...</option>' + 
                exercises.map(ex => `<option value="${ex.id}">${ex.name} (${ex.category})</option>`).join('');
        }

        function filterModalExercises() {
            const query = document.getElementById('modalExSearch').value.toLowerCase();
            const filtered = modalMasterExercises.filter(ex => 
                ex.name.toLowerCase().includes(query) || 
                ex.category.toLowerCase().includes(query)
            );
            renderExerciseDropdown(filtered);
        }

        function addExerciseToTabPlan() {
            const select = document.getElementById('exerciseSelectTab');
            const setsInput = document.getElementById('exerciseSetsTab');
            const repsInput = document.getElementById('exerciseRepsTab');
            
            if (!select.value) {
                showNotification('Please select an exercise', 'warning');
                return;
            }

            const ex = modalMasterExercises.find(e => e.id == select.value);
            if (ex) {
                // Check if already in plan
                if (modalCurrentPlan.some(p => p.id == ex.id)) {
                    showNotification('Exercise already in plan', 'warning');
                    return;
                }

                modalCurrentPlan.push({
                    ...ex,
                    sets: setsInput.value || 3,
                    reps: repsInput.value || '12',
                    notes: ''
                });
                renderModalPlan();
                
                // Reset form
                select.value = '';
                setsInput.value = 3;
                repsInput.value = '';
            }
        }

        function renderModalPlan() {
            const list = document.getElementById('modalExerciseList');
            if (modalCurrentPlan.length === 0) {
                list.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; color: var(--premium-text-muted);">
                        <i class="fas fa-clipboard-list" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 12px; display: block;"></i>
                        <p style="font-size: 0.85rem;">No exercises assigned yet.</p>
                    </div>`;
                return;
            }

            list.innerHTML = modalCurrentPlan.map((ex, index) => `
                <div class="template-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--premium-border); border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 12px;">
                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; background: #000;">
                    <div style="flex: 1;">
                        <h5 style="font-size: 0.9rem; font-weight: 700; color: #fff; margin-bottom: 2px;">${ex.name}</h5>
                        <div style="display: flex; gap: 8px;">
                            <input type="number" value="${ex.sets || 3}" style="width: 45px; background: rgba(255,255,255,0.05); border: 1px solid var(--premium-border); border-radius: 4px; color: #fff; font-size: 0.7rem; padding: 2px 4px; text-align: center;" onchange="updateModalEx(${index}, 'sets', this.value)">
                            <span style="color: var(--premium-text-muted); font-size: 0.7rem; align-self: center;">×</span>
                            <input type="text" value="${ex.reps || '12'}" style="width: 60px; background: rgba(255,255,255,0.05); border: 1px solid var(--premium-border); border-radius: 4px; color: #fff; font-size: 0.7rem; padding: 2px 4px; text-align: center;" onchange="updateModalEx(${index}, 'reps', this.value)">
                        </div>
                    </div>
                    <button class="action-btn-modern danger" style="padding: 0; width: 32px; height: 32px; justify-content: center; border-radius: 8px;" onclick="removeModalEx(${index})">
                        <i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i>
                    </button>
                </div>
            `).join('');
        }

        function updateModalEx(index, field, value) {
            modalCurrentPlan[index][field] = value;
        }

        function removeModalEx(index) {
            modalCurrentPlan.splice(index, 1);
            renderModalPlan();
        }

        async function saveModalPlan() {
            const saveBtn = document.querySelector('#planTabContent .save-btn');
            const originalContent = saveBtn.innerHTML;
            try {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                const response = await fetch('../../api/trainers/save-member-plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: activeBookingId,
                        exercises: modalCurrentPlan
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('Routine updated successfully!', 'success');
                } else {
                    showNotification(data.message, 'warning');
                }
            } catch (error) {
                console.error('Error saving plan:', error);
                showNotification('Error updating plan', 'warning');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalContent;
            }
        }

        async function initProgressCalendar() {
            const calendarEl = document.getElementById('progressCalendar');
            if (!calendarEl) return;
            
            const member = allClients.find(c => c.booking_id == activeBookingId);
            if (!member) return;

            // Give the browser a moment to handle the display:block change
            setTimeout(async () => {
                if (progressCalendar) {
                    progressCalendar.destroy();
                }
                
                // 1. Fetch sessions
                let sessions = [];
                try {
                    const resp = await fetch(`../../api/trainers/get-sessions.php?booking_id=${activeBookingId}`);
                    sessions = await resp.json();
                } catch (e) { console.error('Error fetching sessions:', e); }

                // 2. Fetch progress logs
                let progressLogs = [];
                try {
                    const resp = await fetch(`../../api/trainers/get-progress-history.php?booking_id=${activeBookingId}&calendar=1`);
                    const data = await resp.json();
                    if (data.success) progressLogs = data.data;
                } catch (e) { console.error('Error fetching progress:', e); }

                // 3. Generate milestones
                const milestones = [];
                let startRaw = member.verified_at || member.created_at;
                let startStr = startRaw ? startRaw.split(' ')[0] : null;
                
                if (startStr) {
                    // Paid Milestone
                    milestones.push({
                        id: `paid-${activeBookingId}`,
                        title: `Paid`,
                        start: startStr,
                        allDay: true,
                        classNames: ['event-milestone-paid'],
                        extendedProps: { type: 'milestone', category: 'paid', member: member }
                    });

                    // Expiry Milestone
                    if (member.duration) {
                        const days = parseDurationToDays(member.duration);
                        if (days > 1) {
                            const parts = startStr.split('-');
                            const startDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                            const endDate = new Date(startDate);
                            endDate.setDate(endDate.getDate() + days);
                            
                            milestones.push({
                                id: `expiry-${activeBookingId}`,
                                title: `Expiry`,
                                start: formatDateISO(endDate),
                                allDay: true,
                                classNames: ['event-milestone-expiry'],
                                extendedProps: { type: 'milestone', category: 'expiry', member: member }
                            });
                        }
                    }
                }

                // 4. Combine all events
                const allEvents = [
                    ...milestones,
                    ...progressLogs.map(l => ({
                        ...l,
                        classNames: ['event-progress'],
                        extendedProps: { ...l, type: 'progress' }
                    })),
                    ...sessions.map(s => ({
                        ...s,
                        classNames: ['event-routine'],
                        extendedProps: { ...s, type: 'session' }
                    }))
                ];

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
                    dayMaxEvents: 2,
                    events: allEvents,
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
            
            if (details.type === 'milestone') {
                category.innerText = 'Subscription Milestone';
                title.innerText = event.title === 'Paid' ? 'Subscription Paid' : 'Subscription Expiry';
                time.innerText = new Date(event.start).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                notes.innerText = event.title === 'Paid' 
                    ? `Member successfully paid for the ${details.member.package_name} on this date.`
                    : `The ${details.member.package_name} for this member expires on this date.`;
                
                iconBox.style.background = event.title === 'Paid' ? '#22c55e' : '#ef4444';
                iconBox.style.color = '#fff';
                iconBox.innerHTML = event.title === 'Paid' ? '<i class="fas fa-credit-card"></i>' : '<i class="fas fa-calendar-times"></i>';
            } else if (details.type === 'progress') {
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
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--premium-border); border-radius: 20px; padding: 16px; display: flex; gap: 16px; align-items: center;">
                            <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 60px; height: 60px; border-radius: 12px; object-fit: cover; background: #000;">
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #fff;">${ex.name}</h4>
                                    <span style="font-size: 0.75rem; font-weight: 800; color: #3b82f6; background: rgba(59, 130, 246, 0.1); padding: 4px 10px; border-radius: 8px;">${ex.sets} × ${ex.reps}</span>
                                </div>
                                <p style="font-size: 0.8rem; color: var(--premium-text-muted); line-height: 1.4;">${ex.description || 'No description'}</p>
                            </div>
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
                    
                    // For each client, fetch their upcoming session count
                    for (let client of allClients) {
                        try {
                            const sResp = await fetch(`../../api/trainers/get-sessions.php?booking_id=${client.booking_id}&upcoming=1`);
                            const sData = await sResp.json();
                            client.upcoming_count = Array.isArray(sData) ? sData.length : 0;
                        } catch (e) {
                            client.upcoming_count = 0;
                        }
                    }
                    
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

                        ${member.upcoming_count > 0 ? `
                            <div class="session-indicator" title="${member.upcoming_count} Upcoming Session(s)">
                                <i class="fas fa-dumbbell"></i>
                                ${member.upcoming_count} Session${member.upcoming_count > 1 ? 's' : ''} Scheduled
                            </div>
                        ` : ''}
                        
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

        async function assignPlan(bookingId) {
            await viewProgress(bookingId);
            switchTab('plan');
        }
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
                        <div style="background: var(--premium-input-bg); border: 1px solid var(--premium-border); border-radius: 24px; padding: 24px; transition: all 0.3s ease; position: relative;" onmouseover="this.style.borderColor='rgba(255,255,255,0.2)'" onmouseout="this.style.borderColor='var(--premium-border)'">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
                                    <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid var(--premium-border); flex-shrink:0;">
                                        <i class="fas fa-${log.logged_by === 'user' ? 'user' : 'user-tie'}"></i>
                                    </div>
                                    <div>
                                        <p style="font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -0.3px;">${new Date(log.logged_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
                                        <div style="display:flex; align-items:center; gap:8px; margin-top:3px;">
                                            <p style="font-size: 0.72rem; color: var(--premium-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin:0;">${log.logged_by === 'user' ? 'Member Self-Log' : 'Trainer Log'}</p>
                                            ${log.logged_by === 'user' ? '<span style="font-size:0.68rem;background:rgba(34,197,94,0.15);color:#22c55e;padding:2px 8px;border-radius:99px;font-weight:700;">Self-logged</span>' : ''}
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                    ${log.weight ? `<div style="background: #fff; color: #000; padding: 8px 16px; border-radius: 12px; font-weight: 900; font-size: 1.1rem; display: inline-flex; align-items: baseline; gap: 4px;">${log.weight} <span style="font-size: 0.7rem; font-weight: 700; opacity: 0.7;">KG</span></div>` : ''}
                                    ${log.height ? `<div style="background: rgba(255,255,255,0.07); color: #fff; padding: 8px 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem;">${log.height} cm</div>` : ''}
                                    ${log.photo_url ? `<img src="../../${log.photo_url}" alt="Progress" style="width:56px;height:56px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,0.1);cursor:pointer;" onclick="window.open('../../${log.photo_url}','_blank')">` : ''}
                                </div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); padding: 18px; border-radius: 18px; border: 1px solid rgba(255,255,255,0.03);">
                                <p style="font-size: 0.95rem; color: rgba(255,255,255,0.85); line-height: 1.6; font-weight: 400; margin:0;">
                                    ${log.remarks ? log.remarks : '<span style="color: var(--premium-text-muted); font-style: italic;">No notes provided.</span>'}
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
