<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireTrainer();
$user = getCurrentUser();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exercise Plan | FitPay Trainer</title>
    
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
        .exercise-item {
            background: var(--dark-card-bg);
            border: 1px solid var(--dark-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            align-items: center;
        }
        .light-mode .exercise-item { background: #f9f9f9; border-color: #eee; }
        
        .exercise-img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            background: #1a1a1a;
        }
        
        .exercise-details h4 { margin-bottom: 8px; font-weight: 700; }
        .exercise-details p { font-size: 0.85rem; color: var(--dark-text-secondary); }
        
        .exercise-inputs {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .exercise-inputs .form-group { margin-bottom: 0; }
        .exercise-inputs input { width: 80px; text-align: center; padding: 8px; }
        
        .floating-actions {
            position: fixed;
            bottom: 32px;
            right: 32px;
            display: flex;
            gap: 16px;
            z-index: 100;
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
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="plans.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Exercise Plans</span></a></li>
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
                <h1>Exercise Plan</h1>
                <p id="planSubtitle">Customize the fitness routine for your member</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <?php if ($booking_id <= 0): ?>
            <div class="content-card" style="margin-top: 32px; text-align: center; padding: 60px 20px;">
                <i class="fas fa-user-check" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>Select a client first</h3>
                <p style="margin-bottom: 24px;">Please go to "My Clients" and select a member to manage their exercise plan.</p>
                <a href="members.php" class="btn btn-primary">Go to My Clients</a>
            </div>
        <?php else: ?>
            <div class="content-card" style="margin-top: 32px;">
                <div class="card-header">
                    <h3>Member Plan: <span id="memberName" class="text-primary">Loading...</span></h3>
                    <div class="card-actions">
                        <button class="card-btn primary" onclick="addExercise()">
                            <i class="fas fa-plus"></i> Add Exercise
                        </button>
                    </div>
                </div>
                
                <div id="exerciseList" style="padding: 24px;">
                    <!-- Populated by JavaScript -->
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                        <p style="margin-top: 10px;">Loading exercise plan...</p>
                    </div>
                </div>
            </div>

            <div class="floating-actions">
                <button class="btn btn-secondary" onclick="window.history.back()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" id="savePlanBtn" onclick="savePlan()">
                    <i class="fas fa-save"></i> Save Exercise Plan
                </button>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <!-- Add Exercise Modal -->
    <div class="modal-overlay" id="addExerciseModal">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Add Exercise to Plan</h3>
                <button class="close-modal" onclick="closeAddExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="search-box" style="margin-bottom: 20px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="exerciseSearch" placeholder="Search exercises..." oninput="filterExercises()">
                </div>
                <div id="allExercisesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; max-height: 400px; overflow-y: auto; padding: 10px;">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const bookingId = <?php echo $booking_id; ?>;
        let currentPlan = [];
        let allExercises = [];

        async function loadPlan() {
            if (bookingId <= 0) return;
            
            try {
                // Get member info first (from get-clients API)
                const clientResp = await fetch('../../api/trainers/get-clients.php');
                const clientData = await clientResp.json();
                if (clientData.success) {
                    const member = clientData.data.find(c => c.booking_id == bookingId);
                    if (member) {
                        document.getElementById('memberName').textContent = member.name;
                        document.getElementById('planSubtitle').textContent = `Customizing plan for ${member.name} (${member.package_name})`;
                    }
                }

                // Get plan
                const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${bookingId}`);
                const data = await response.json();
                
                if (data.success) {
                    currentPlan = data.data.exercises;
                    const isAssisted = data.data.is_trainer_assisted;
                    
                    if (!isAssisted) {
                        document.getElementById('savePlanBtn').disabled = true;
                        document.getElementById('savePlanBtn').innerHTML = '<i class="fas fa-lock"></i> Package Restriction';
                        document.getElementById('planSubtitle').innerHTML += ' <span style="color: var(--danger); font-weight: 700;">(View Only - Basic Package)</span>';
                        
                        // Show warning in the list
                        document.getElementById('exerciseList').insertAdjacentHTML('afterbegin', `
                            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 16px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>This member is on a basic package. You can view their exercises but cannot customize them.</span>
                            </div>
                        `);
                    }
                    
                    renderPlan(isAssisted);
                }
            } catch (error) {
                console.error('Error loading plan:', error);
            }
        }

        function renderPlan(isAssisted = true) {
            const list = document.getElementById('exerciseList');
            if (!list) return;
            
            if (currentPlan.length === 0) {
                list.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                        <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>This member doesn't have any exercises in their plan yet.</p>
                        ${isAssisted ? `
                            <button class="btn btn-primary" style="margin-top: 16px;" onclick="addExercise()">
                                <i class="fas fa-plus"></i> Add First Exercise
                            </button>
                        ` : ''}
                    </div>
                `;
                return;
            }
            
            list.innerHTML += currentPlan.map((ex, index) => `
                <div class="exercise-item">
                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" class="exercise-img" onerror="this.src='../../assets/img/exercise-placeholder.jpg'">
                    <div class="exercise-details">
                        <h4>${ex.name}</h4>
                        <p><i class="fas fa-tag"></i> ${ex.category}</p>
                        <div style="margin-top: 10px;">
                            <input type="text" class="form-control" style="width: 100%;" placeholder="Add specific notes..." value="${ex.notes || ''}" onchange="updateExData(${index}, 'notes', this.value)" ${!isAssisted ? 'disabled' : ''}>
                        </div>
                    </div>
                    <div class="exercise-inputs">
                        <div class="form-group">
                            <label style="font-size: 0.7rem; display: block; margin-bottom: 4px;">SETS</label>
                            <input type="number" class="form-control" value="${ex.sets || 3}" onchange="updateExData(${index}, 'sets', this.value)" ${!isAssisted ? 'disabled' : ''}>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.7rem; display: block; margin-bottom: 4px;">REPS</label>
                            <input type="text" class="form-control" value="${ex.reps || '10-12'}" onchange="updateExData(${index}, 'reps', this.value)" ${!isAssisted ? 'disabled' : ''}>
                        </div>
                        ${isAssisted ? `
                            <button class="icon-btn danger" style="margin-top: 20px;" onclick="removeExercise(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        function updateExData(index, field, value) {
            currentPlan[index][field] = value;
        }

        function removeExercise(index) {
            if (confirm('Remove this exercise from the plan?')) {
                currentPlan.splice(index, 1);
                renderPlan();
            }
        }

        async function addExercise() {
            document.getElementById('addExerciseModal').classList.add('active');
            if (allExercises.length === 0) {
                try {
                    const response = await fetch('../../api/exercises/get-all.php');
                    const data = await response.json();
                    if (data.success) {
                        allExercises = data.data;
                        renderExerciseSelection(allExercises);
                    }
                } catch (error) {
                    console.error('Error loading exercises:', error);
                }
            }
        }

        function closeAddExerciseModal() {
            document.getElementById('addExerciseModal').classList.remove('active');
        }

        function renderExerciseSelection(exercises) {
            const grid = document.getElementById('allExercisesGrid');
            grid.innerHTML = exercises.map(ex => `
                <div class="content-card" style="padding: 12px; cursor: pointer; transition: transform 0.2s;" onclick="selectExercise(${ex.id})">
                    <div style="height: 100px; background: #1a1a1a; display: flex; align-items: center; justify-content: center; border-radius: 8px; overflow: hidden; margin-bottom: 10px;">
                        ${ex.image_url ? `<img src="${ex.image_url}" style="width: 100%; height: 100%; object-fit: cover;">` : `<i class="fas fa-running" style="font-size: 24px; color: #333;"></i>`}
                    </div>
                    <h5 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${ex.name}</h5>
                    <span style="font-size: 0.7rem; color: var(--primary); font-weight: 700; text-transform: uppercase;">${ex.category}</span>
                </div>
            `).join('');
        }

        function selectExercise(id) {
            const ex = allExercises.find(e => e.id == id);
            if (ex) {
                // Check if already in plan
                if (currentPlan.some(p => p.id == id)) {
                    showNotification('This exercise is already in the plan', 'warning');
                    return;
                }
                
                currentPlan.push({
                    ...ex,
                    sets: 3,
                    reps: '10-12',
                    notes: ''
                });
                renderPlan();
                closeAddExerciseModal();
                showNotification(`Added ${ex.name} to the plan`, 'success');
            }
        }

        function filterExercises() {
            const query = document.getElementById('exerciseSearch').value.toLowerCase();
            const filtered = allExercises.filter(ex => 
                ex.name.toLowerCase().includes(query) || 
                ex.category.toLowerCase().includes(query)
            );
            renderExerciseSelection(filtered);
        }

        async function savePlan() {
            const saveBtn = document.getElementById('savePlanBtn');
            const originalContent = saveBtn.innerHTML;
            
            try {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                const response = await fetch('../../api/trainers/save-member-plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        exercises: currentPlan
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification('Exercise plan saved successfully!', 'success');
                    setTimeout(() => window.location.href = 'members.php', 1500);
                } else {
                    showNotification(data.message, 'warning');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Error saving plan:', error);
                showNotification('Error saving exercise plan', 'warning');
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalContent;
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            notification.style.cssText = `
                position: fixed; top: 100px; right: 32px;
                background: ${type === 'success' ? '#22c55e' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
                color: white; padding: 16px 24px; border-radius: 12px;
                display: flex; align-items: center; gap: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10000;
                animation: slideIn 0.3s ease-out; font-weight: 600;
            `;
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 5000);
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', loadPlan);
    </script>
</body>
</html>
