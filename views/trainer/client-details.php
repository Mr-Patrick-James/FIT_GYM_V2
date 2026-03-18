<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireTrainer();
$user = getCurrentUser();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    header("Location: members.php");
    exit();
}

$conn = getDBConnection();

// Get the trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainer = $trainerStmt->get_result()->fetch_assoc();
$trainerId = $trainer['id'];

// Get client and booking details
$stmt = $conn->prepare("
    SELECT b.*, u.name as member_name, u.email as member_email, u.contact as member_contact, u.id as member_id, p.name as package_name, p.is_trainer_assisted
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN packages p ON b.package_id = p.id 
    WHERE b.id = ? AND b.trainer_id = ?
");
$stmt->bind_param("ii", $booking_id, $trainerId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    header("Location: members.php");
    exit();
}

$memberId = $client['member_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management | FitPay Trainer</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

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

        .management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }
        @media (max-width: 1024px) {
            .management-grid {
                grid-template-columns: 1fr;
            }
            .full-width { grid-column: span 1; }
        }
        .full-width { grid-column: span 2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--premium-text-muted);
            font-weight: 700;
            font-size: 0.75rem;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            border-radius: 12px;
            letter-spacing: -0.2px;
        }

        .tab-btn:hover {
            color: #fff;
            background: var(--premium-input-bg);
        }

        .tab-btn.active {
            color: #000;
            background: #fff;
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
        }
        
        .content-card {
            background: var(--premium-card) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 20px !important;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3) !important;
        }

        .card-header {
            padding: 16px 24px !important;
            border-bottom: 1px solid var(--premium-border) !important;
            background: transparent !important;
        }

        .card-header h3 {
            font-size: 1rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px !important;
            color: #fff !important;
        }

        .form-control, .modern-input {
            width: 100%;
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            color: #fff !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            outline: none !important;
        }

        .form-control:focus, .modern-input:focus {
            background: var(--premium-input-hover) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
        }

        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--premium-text-muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            margin-top: 16px;
        }

        .btn-primary {
            background: #fff !important;
            color: #000 !important;
            border: none !important;
            border-radius: 14px !important;
            padding: 12px 20px !important;
            font-size: 0.85rem !important;
            font-weight: 800 !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.15);
        }

        .btn-secondary {
            background: var(--premium-input-bg) !important;
            color: #fff !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 12px !important;
            padding: 8px 16px !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            transition: all 0.2s !important;
        }

        .btn-secondary:hover {
            background: var(--premium-input-hover) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }

        /* FullCalendar Customization */
        .fc {
            --fc-border-color: rgba(255, 255, 255, 0.1);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: transparent;
            --fc-list-event-hover-bg-color: var(--premium-input-hover);
            --fc-today-bg-color: rgba(255, 255, 255, 0.05);
            font-family: 'Inter', sans-serif;
        }

        .fc .fc-toolbar-title {
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px;
            color: #fff;
        }

        .fc .fc-button-primary {
            background: var(--premium-input-bg) !important;
            border: 1px solid var(--premium-border) !important;
            border-radius: 12px !important;
            padding: 6px 12px !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            transition: all 0.2s !important;
        }

        .fc .fc-button-primary:hover {
            background: var(--premium-input-hover) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }

        .fc .fc-col-header-cell {
            padding: 8px 0 !important;
            font-size: 0.65rem !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--premium-text-muted);
        }

        .fc-theme-standard td, .fc-theme-standard th {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }

        .fc-daygrid-day-number {
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            padding: 6px !important;
            color: var(--premium-text-muted) !important;
        }

        .fc-day-today .fc-daygrid-day-number {
            color: #fff !important;
        }
        
        .history-item {
            padding: 16px;
            border-bottom: 1px solid var(--premium-border);
            background: rgba(255, 255, 255, 0.01);
            border-radius: 16px;
            margin-bottom: 12px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid transparent;
        }
        .history-item:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.03);
            border-color: var(--premium-border);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.scheduled {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .notification {
            position: fixed;
            bottom: 32px;
            right: 32px;
            background: #fff;
            color: #000;
            padding: 16px 24px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            z-index: 10001;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-weight: 800;
            font-size: 0.8rem;
        }
        .fc-event:hover {
            transform: scale(1.02);
            z-index: 10;
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            cursor: pointer;
        }
        .fc-event {
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            <li><a href="members.php" class="active"><i class="fas fa-users"></i> <span>My Clients</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercise Library</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> <span>My Profile</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $initials = '';
                foreach(explode(' ', $user['name']) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                <p>Professional Trainer</p>
            </div>
        </div>
    </aside>

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
                    <i class="fas fa-times" style="color: #fff;"></i>
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

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Managing: <?php echo htmlspecialchars($client['member_name']); ?></h1>
                <p><?php echo htmlspecialchars($client['package_name']); ?> • Expires <?php echo date('M d, Y', strtotime($client['expires_at'])); ?></p>
            </div>
            
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='members.php'">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="tabs" style="display: flex; gap: 24px; border-bottom: 1px solid var(--dark-border); margin-top: 32px; padding-bottom: 12px;">
            <button class="tab-btn active" onclick="switchMainTab('sessions')">Sessions & Calendar</button>
            <button class="tab-btn" onclick="switchMainTab('progress')">Progress Tracking</button>
            <button class="tab-btn" onclick="switchMainTab('tips')">Tips & Guidance</button>
            <button class="tab-btn" onclick="switchMainTab('food')">Food Recommendations</button>
        </div>

        <!-- Sessions Tab -->
        <div id="sessionsTab" class="tab-content active">
            <div class="management-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Schedule New Session</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form id="sessionForm">
                            <div class="form-group">
                                <label>Event Title</label>
                                <input type="text" id="sessionTitle" class="form-control" placeholder="e.g. Chest & Triceps, Leg Day, etc.">
                            </div>
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label>Event Type</label>
                                    <select id="sessionType" class="form-control" onchange="toggleSessionTime()">
                                        <option value="workout">Workout Session</option>
                                        <option value="rest_day">Rest Day</option>
                                        <option value="assessment">Physical Assessment</option>
                                        <option value="consultation">Consultation</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" id="sessionDate" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div id="timeFields" class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" id="sessionTime" class="form-control" value="08:00">
                                </div>
                                <div class="form-group">
                                    <label>Duration (mins)</label>
                                    <input type="number" id="sessionDuration" class="form-control" value="60">
                                </div>
                            </div>
                            <div id="workoutFields">
                                <div class="form-group">
                                    <label>Select Exercises for this Session</label>
                                    <div id="sessionExercisesList" style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 12px; border: 1px solid var(--dark-border);">
                                        <!-- Populated from member plan -->
                                        <p style="font-size: 0.8rem; color: var(--dark-text-secondary);">Loading member's exercise plan...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Special Instructions / Notes</label>
                                <textarea id="sessionNotes" rows="3" class="form-control" placeholder="Specific focus for this session..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Schedule on Calendar</button>
                        </form>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header">
                        <h3>Upcoming Sessions</h3>
                    </div>
                    <div id="upcomingSessionsList" style="padding: 20px; max-height: 400px; overflow-y: auto;">
                        <!-- Populated by JS -->
                    </div>
                </div>
                <div class="content-card full-width">
                    <div class="card-header">
                        <h3>Session Calendar</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Tab -->
        <div id="progressTab" class="tab-content">
            <div class="management-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Log Member Progress</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form id="progressForm">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" id="progressDate" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.1" id="progressWeight" required class="form-control" placeholder="e.g. 75.5">
                            </div>
                            <div class="form-group">
                                <label>Trainer Remarks</label>
                                <textarea id="progressRemarks" rows="3" class="form-control" placeholder="Observed improvements, strength gains, etc..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Progress Log</button>
                        </form>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header">
                        <h3>Progress History</h3>
                    </div>
                    <div id="progressHistory" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips Tab -->
        <div id="tipsTab" class="tab-content">
            <div class="management-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Add New Tip</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form id="tipForm">
                            <div class="form-group">
                                <label>Guidance / Tip Text</label>
                                <textarea id="tipText" rows="4" required class="form-control" placeholder="Drink more water, focus on form, etc..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Tip to Member</button>
                        </form>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header">
                        <h3>Previous Tips</h3>
                    </div>
                    <div id="tipsHistory" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Food Tab -->
        <div id="foodTab" class="tab-content">
            <div class="management-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recommend Meal Plan</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form id="foodForm">
                            <div class="form-group">
                                <label>Meal Type</label>
                                <select id="mealType" required class="form-control">
                                    <option value="breakfast">Breakfast</option>
                                    <option value="lunch">Lunch</option>
                                    <option value="dinner">Dinner</option>
                                    <option value="snack">Snack</option>
                                    <option value="pre-workout">Pre-Workout</option>
                                    <option value="post-workout">Post-Workout</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Food Items</label>
                                <textarea id="foodItems" rows="3" required class="form-control" placeholder="Oatmeal, Banana, Egg whites..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Estimated Calories</label>
                                <input type="number" id="calories" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Recommendation</button>
                        </form>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header">
                        <h3>Current Diet Guidance</h3>
                    </div>
                    <div id="foodHistory" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <script>
        const bookingId = <?php echo $booking_id; ?>;
        const memberId = <?php echo $memberId; ?>;
        const isTrainerAssisted = <?php echo $client['is_trainer_assisted'] ? 'true' : 'false'; ?>;
        let calendar = null;

        document.addEventListener('DOMContentLoaded', () => {
            initCalendar();
            loadSessions();
            loadTips();
            loadFood();
            loadProgress();
            loadMemberPlan();
            
            if (!isTrainerAssisted) {
                disableCoachingFeatures();
            }

            document.getElementById('sessionForm').addEventListener('submit', handleSessionSubmit);
            document.getElementById('tipForm').addEventListener('submit', handleTipSubmit);
            document.getElementById('foodForm').addEventListener('submit', handleFoodSubmit);
            document.getElementById('progressForm').addEventListener('submit', handleProgressSubmit);
        });

        let memberPlanExercises = [];

        async function loadMemberPlan() {
            try {
                const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${bookingId}`);
                const data = await response.json();
                if (data.success) {
                    memberPlanExercises = data.data.exercises;
                    populateSessionExercises();
                }
            } catch (err) { console.error(err); }
        }

        function populateSessionExercises() {
            const list = document.getElementById('sessionExercisesList');
            if (!list) return;
            
            if (!Array.isArray(memberPlanExercises) || memberPlanExercises.length === 0) {
                list.innerHTML = '<p style="font-size: 0.8rem; color: var(--dark-text-secondary);">No exercises in member\'s plan yet.</p>';
                return;
            }
            
            list.innerHTML = memberPlanExercises.map(ex => `
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; cursor: pointer;">
                    <input type="checkbox" name="session_exercises" value="${ex.id}">
                    <span style="font-size: 0.9rem;">${ex.name}</span>
                </label>
            `).join('');
        }

        function disableCoachingFeatures() {
            // Disable forms
            const forms = ['sessionForm', 'tipForm', 'foodForm', 'progressForm'];
            forms.forEach(id => {
                const form = document.getElementById(id);
                if (form) {
                    const inputs = form.querySelectorAll('input, textarea, select, button');
                    inputs.forEach(i => i.disabled = true);
                    
                    // Add lock message
                    form.insertAdjacentHTML('beforebegin', `
                        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-lock"></i>
                            <span>This feature is restricted for basic packages.</span>
                        </div>
                    `);
                }
            });
        }

        function switchMainTab(tab) {
            const event = window.event;
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tab + 'Tab').classList.add('active');
            if (event) {
                event.target.classList.add('active');
            }
            
            if (tab === 'sessions' && calendar) {
                calendar.updateSize();
            }
        }

        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            const subscriptionStartDate = '<?php echo $client['verified_at'] ?: ($client['booking_date'] ?: $client['created_at']); ?>';
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                themeSystem: 'standard',
                selectable: true,
                select: function(info) {
                    document.getElementById('sessionDate').value = info.startStr.split('T')[0];
                    document.getElementById('sessionForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    document.getElementById('sessionTitle').focus();
                },
                eventDidMount: function(info) {
                    info.el.style.borderRadius = '8px';
                    info.el.style.border = 'none';
                    info.el.style.padding = '2px 6px';
                    info.el.style.fontWeight = '700';
                    info.el.style.fontSize = '0.8rem';
                    
                    if (info.event.backgroundColor === '#3b82f6') {
                        info.el.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
                        info.el.style.color = '#3b82f6';
                        info.el.style.borderLeft = '3px solid #3b82f6';
                    } else if (info.event.backgroundColor === '#22c55e') {
                        info.el.style.backgroundColor = 'rgba(34, 197, 94, 0.2)';
                        info.el.style.color = '#22c55e';
                        info.el.style.borderLeft = '3px solid #22c55e';
                    } else if (info.event.backgroundColor === '#f59e0b') {
                        info.el.style.backgroundColor = 'rgba(245, 158, 11, 0.2)';
                        info.el.style.color = '#f59e0b';
                        info.el.style.borderLeft = '3px solid #f59e0b';
                    }
                },
                eventClick: function(info) {
                    showEventDetails(info.event);
                },
                eventSources: [
                    {
                        url: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
                        color: '#3b82f6'
                    },
                    {
                        url: '../../api/trainers/get-progress-history.php?booking_id=' + bookingId + '&calendar=1',
                        color: '#22c55e'
                    },
                    {
                        events: [
                            {
                                title: '⭐ Subscription Started',
                                start: subscriptionStartDate.split(' ')[0],
                                allDay: true,
                                color: '#f59e0b',
                                display: 'block',
                                extendedProps: { type: 'milestone' }
                            }
                        ]
                    }
                ]
            });
            calendar.render();
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
            } else if (details.type === 'milestone') {
                category.innerText = 'Milestone';
                title.innerText = 'Subscription Started';
                time.innerText = new Date(event.start).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                notes.innerText = "This is when the member's fitness journey officially began with you!";
                iconBox.style.background = '#f59e0b';
                iconBox.style.color = '#fff';
                iconBox.innerHTML = '<i class="fas fa-star"></i>';
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
            
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeEventDetailsModal() {
            const modal = document.getElementById('eventDetailsModal');
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        async function handleProgressSubmit(e) {
            e.preventDefault();
            const data = {
                booking_id: bookingId,
                logged_at: document.getElementById('progressDate').value,
                weight: document.getElementById('progressWeight').value,
                remarks: document.getElementById('progressRemarks').value
            };
            
            try {
                const response = await fetch('../../api/trainers/log-progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Progress logged successfully', 'success');
                    document.getElementById('progressForm').reset();
                    document.getElementById('progressDate').value = new Date().toISOString().split('T')[0];
                    loadProgress();
                    calendar.refetchEvents();
                }
            } catch (err) { console.error(err); }
        }

        async function loadProgress() {
            const list = document.getElementById('progressHistory');
            try {
                const resp = await fetch('../../api/trainers/get-progress-history.php?booking_id=' + bookingId);
                const data = await resp.json();
                if (!data.success || data.data.length === 0) {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-chart-line" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i> No progress logged yet.</div>';
                    return;
                }
                list.innerHTML = data.data.map(p => `
                    <div class="history-item" style="border-left: 4px solid #22c55e;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: white; font-size: 1.1rem;"><i class="fas fa-weight"></i> ${p.weight} kg</strong>
                            <span style="font-size: 0.8rem; color: var(--dark-text-secondary); font-weight: 600;">${new Date(p.logged_at).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}</span>
                        </div>
                        ${p.remarks ? `<p style="font-size: 0.9rem; color: var(--dark-text-secondary); line-height: 1.5; margin: 0;">${p.remarks}</p>` : ''}
                    </div>
                `).join('');
            } catch (err) { console.error(err); }
        }

        async function handleSessionSubmit(e) {
            e.preventDefault();
            console.log("Submitting session form...");
            
            const submitBtn = e.target.querySelector('button[type="submit"]') || document.querySelector('#sessionForm button[type="submit"]');
            if (!submitBtn) {
                console.error("Submit button not found!");
                return;
            }
            
            const originalBtnText = submitBtn.innerHTML;
            
            const selectedExercises = Array.from(document.querySelectorAll('input[name="session_exercises"]:checked'))
                .map(cb => cb.value);

            const data = {
                booking_id: bookingId,
                member_id: memberId,
                title: document.getElementById('sessionTitle').value,
                type: document.getElementById('sessionType').value,
                session_date: document.getElementById('sessionDate').value,
                session_time: document.getElementById('sessionTime').value,
                duration: document.getElementById('sessionDuration').value,
                notes: document.getElementById('sessionNotes').value,
                exercises: selectedExercises
            };
            
            console.log("Data to send:", data);
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling...';
                
                const response = await fetch('../../api/trainers/save-session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const responseText = await response.text();
                console.log("Raw Response:", responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error("JSON Parse Error:", e);
                    // If not JSON, it might be a PHP error message
                    showNotification('Server Error: ' + responseText.substring(0, 100), 'warning');
                    return;
                }
                
                if (result.success) {
                    showNotification('Event added to calendar', 'success');
                    document.getElementById('sessionForm').reset();
                    document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];
                    loadSessions();
                    if (calendar) calendar.refetchEvents();
                } else {
                    showNotification(result.message || 'Failed to schedule session', 'warning');
                }
            } catch (err) { 
                console.error("Submission error:", err);
                showNotification('An error occurred while saving the session', 'warning');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }

        function toggleSessionTime() {
            const type = document.getElementById('sessionType').value;
            const timeFields = document.getElementById('timeFields');
            const workoutFields = document.getElementById('workoutFields');
            
            if (type === 'rest_day') {
                timeFields.style.display = 'none';
                workoutFields.style.display = 'none';
                if (!document.getElementById('sessionTitle').value) {
                    document.getElementById('sessionTitle').value = 'Rest Day';
                }
            } else {
                timeFields.style.display = 'grid';
                workoutFields.style.display = 'block';
                if (document.getElementById('sessionTitle').value === 'Rest Day') {
                    document.getElementById('sessionTitle').value = '';
                }
            }
        }

        async function handleTipSubmit(e) {
            e.preventDefault();
            const data = {
                member_id: memberId,
                tip_text: document.getElementById('tipText').value
            };
            
            try {
                const response = await fetch('../../api/trainers/save-tip.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Tip sent to member', 'success');
                    document.getElementById('tipForm').reset();
                    loadTips();
                }
            } catch (err) { console.error(err); }
        }

        async function handleFoodSubmit(e) {
            e.preventDefault();
            const data = {
                member_id: memberId,
                meal_type: document.getElementById('mealType').value,
                food_items: document.getElementById('foodItems').value,
                calories: document.getElementById('calories').value
            };
            
            try {
                const response = await fetch('../../api/trainers/save-food.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Food recommendation saved', 'success');
                    document.getElementById('foodForm').reset();
                    loadFood();
                }
            } catch (err) { console.error(err); }
        }

        async function loadSessions() {
            const list = document.getElementById('upcomingSessionsList');
            try {
                const resp = await fetch('../../api/trainers/get-sessions.php?booking_id=' + bookingId + '&upcoming=1');
                const data = await resp.json();
                if (data.length === 0) {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-calendar-alt" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i> No upcoming sessions.</div>';
                    return;
                }
                list.innerHTML = data.map(s => `
                    <div class="history-item" style="border-left: 4px solid var(--primary);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: white;">${new Date(s.start).toLocaleDateString(undefined, {month: 'short', day: 'numeric'})} at ${new Date(s.start).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</strong>
                            <span class="status-badge ${s.status || 'scheduled'}" style="font-size: 0.65rem;">${s.status || 'scheduled'}</span>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--dark-text-secondary); margin: 0;">${s.title || 'Workout Session'}</p>
                    </div>
                `).join('');
            } catch (err) { console.error(err); }
        }

        async function loadTips() {
            const list = document.getElementById('tipsHistory');
            try {
                const resp = await fetch('../../api/trainers/get-tips.php?member_id=' + memberId);
                const data = await resp.json();
                if (!data.success || data.data.length === 0) {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-lightbulb" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i> No tips shared yet.</div>';
                    return;
                }
                list.innerHTML = data.data.map(t => `
                    <div class="history-item" style="border-left: 4px solid var(--warning);">
                        <p style="font-size: 0.95rem; color: white; line-height: 1.6; margin-bottom: 12px;">${t.tip_text}</p>
                        <div style="font-size: 0.75rem; color: var(--dark-text-secondary); text-align: right; font-weight: 600;">
                            <i class="far fa-clock"></i> ${new Date(t.created_at).toLocaleString(undefined, {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'})}
                        </div>
                    </div>
                `).join('');
            } catch (err) { console.error(err); }
        }

        async function loadFood() {
            const list = document.getElementById('foodHistory');
            try {
                const resp = await fetch('../../api/trainers/get-food.php?member_id=' + memberId);
                const data = await resp.json();
                if (!data.success || data.data.length === 0) {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-utensils" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i> No meal plans shared yet.</div>';
                    return;
                }
                list.innerHTML = data.data.map(f => `
                    <div class="history-item" style="border-left: 4px solid var(--info);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                            <span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.2); text-transform: uppercase; font-size: 0.7rem; font-weight: 800; padding: 4px 10px;">${f.meal_type}</span>
                            ${f.calories ? `<span style="font-size: 0.85rem; font-weight: 700; color: white;"><i class="fas fa-fire"></i> ${f.calories} kcal</span>` : ''}
                        </div>
                        <p style="font-size: 0.95rem; color: var(--dark-text-secondary); line-height: 1.6; margin: 0; background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px;">${f.food_items}</p>
                        <div style="font-size: 0.75rem; color: #555; text-align: right; margin-top: 12px; font-weight: 600;">
                            <i class="far fa-calendar-alt"></i> ${new Date(f.created_at).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}
                        </div>
                    </div>
                `).join('');
            } catch (err) { console.error(err); }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            const icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-circle' : 'info-circle');
            const bg = type === 'success' ? '#22c55e' : (type === 'warning' ? '#f59e0b' : '#3b82f6');
            
            notification.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            notification.style.cssText = `position: fixed; top: 100px; right: 32px; background: ${bg}; color: white; padding: 16px 24px; border-radius: 12px; display: flex; align-items: center; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10000; animation: slideIn 0.3s ease-out; font-weight: 600;`;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideIn 0.3s ease reverse forwards';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
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
