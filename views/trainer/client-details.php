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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

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
            color: var(--dark-text-secondary);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 8px 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .tab-btn.active {
            color: var(--primary);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -13px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
        }
        
        .fc-theme-standard .fc-scrollgrid { border-color: var(--dark-border); }
        .fc-theme-standard td, .fc-theme-standard th { border-color: var(--dark-border); }
        .fc .fc-daygrid-day-number { color: var(--dark-text-secondary); }
        .fc .fc-col-header-cell-cushion { color: white; padding: 10px; }
        .light-mode .fc .fc-col-header-cell-cushion { color: #333; }
        
        .history-item {
            padding: 16px;
            border-bottom: 1px solid var(--dark-border);
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            margin-bottom: 16px;
            transition: transform 0.2s ease;
        }
        .history-item:hover {
            transform: translateX(5px);
            background: rgba(255,255,255,0.04);
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
            <li><a href="plans.php"><i class="fas fa-clipboard-list"></i> <span>Exercise Plans</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
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
            
            if (memberPlanExercises.length === 0) {
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
                    switchMainTab('sessions');
                    document.getElementById('sessionTitle').focus();
                },
                eventSources: [
                    {
                        url: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
                        color: '#3b82f6'
                    },
                    {
                        url: '../../api/trainers/get-progress-history.php?booking_id=' + bookingId + '&calendar=1',
                        color: '#22c55e'
                    }
                ]
            });
            calendar.render();
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
            
            try {
                const response = await fetch('../../api/trainers/save-session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Event added to calendar', 'success');
                    document.getElementById('sessionForm').reset();
                    document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];
                    loadSessions();
                    calendar.refetchEvents();
                }
            } catch (err) { console.error(err); }
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
