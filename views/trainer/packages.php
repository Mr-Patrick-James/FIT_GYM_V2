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
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--glass);
            color: var(--primary);
            font-weight: 700;
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
                <h1>Package Exercise Templates</h1>
                <p>Manage default exercise routines for each membership package</p>
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

    <!-- Exercise Management Modal -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <div>
                    <h3 id="exerciseModalTitle">Manage Package Exercises</h3>
                    <p id="exerciseModalSubtitle" style="font-size: 0.85rem; color: var(--dark-text-secondary);"></p>
                </div>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; padding: 24px;">
                <!-- Add Exercise Form -->
                <div style="border-right: 1px solid var(--dark-border); padding-right: 32px;">
                    <h4 style="margin-bottom: 20px; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-plus-circle"></i> Add Exercise
                    </h4>
                    <form id="addExerciseForm">
                        <div class="form-group">
                            <label>Select Exercise</label>
                            <select id="exerciseSelect" required class="form-control" style="width: 100%;">
                                <option value="">Choose an exercise...</option>
                            </select>
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Sets</label>
                                <input type="number" id="exerciseSets" value="3" min="1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Reps / Duration</label>
                                <input type="text" id="exerciseReps" placeholder="e.g. 10-12" class="form-control">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Special Notes</label>
                            <textarea id="exerciseNotes" rows="3" placeholder="Optional instructions..." class="form-control"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add to Plan
                        </button>
                    </form>
                </div>

                <!-- Current Plan List -->
                <div>
                    <h4 style="margin-bottom: 20px; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list-ul"></i> Current Template
                    </h4>
                    <div id="packageExercisesList" style="max-height: 450px; overflow-y: auto; padding-right: 10px;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary" onclick="closeExerciseModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let allPackages = [];
        let allExercises = [];
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
                <div class="content-card package-card" style="padding: 24px; border-top: 4px solid ${pkg.is_trainer_assisted ? 'var(--primary)' : 'var(--dark-border)'};">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <div>
                            <h3 style="font-weight: 800; color: var(--primary);">${pkg.name}</h3>
                            <p style="font-size: 0.85rem; color: var(--dark-text-secondary);">${pkg.duration}</p>
                        </div>
                        ${pkg.is_trainer_assisted ? 
                            '<span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2);"><i class="fas fa-user-tie"></i> Assisted</span>' : 
                            '<span class="status-badge" style="background: var(--glass); color: var(--dark-text-secondary);">Basic</span>'
                        }
                    </div>
                    
                    <div style="margin-bottom: 24px; min-height: 60px;">
                        <p style="font-size: 0.9rem; color: var(--dark-text-secondary); line-height: 1.5;">
                            ${pkg.description ? pkg.description.split('\n')[0] : 'No description available.'}
                        </p>
                    </div>

                    <button class="card-btn primary" style="width: 100%; justify-content: center; padding: 12px;" onclick="managePackageExercises(${pkg.id}, '${pkg.name}')">
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
                        <div class="exercise-list-item">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <strong style="color: white;">${ex.name}</strong>
                                    <span class="exercise-badge">${ex.category}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">
                                    ${ex.sets} sets × ${ex.reps} ${ex.notes ? `• <span style="font-style: italic;">${ex.notes}</span>` : ''}
                                </div>
                            </div>
                            <button class="icon-btn danger" onclick="removeExercise(${ex.id})" title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-dumbbell" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i> No exercises in this template yet.</div>';
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
