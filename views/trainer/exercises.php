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
    <title>Exercise List | FitPay Trainer</title>
    
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
            <li><a href="plans.php"><i class="fas fa-clipboard-list"></i> <span>Exercise Plans</span></a></li>
            <li><a href="exercises.php" class="active"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
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
                <h1>Exercise Library</h1>
                <p>Browse the master list of gym exercises</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="exerciseSearch" placeholder="Search exercises..." oninput="filterExercises()">
                </div>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Exercises</h3>
            </div>
            
            <div id="exercisesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noExercisesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-running" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No exercises found</h3>
            </div>
        </div>
    </main>

    <script>
        let allExercises = [];

        async function loadExercises() {
            try {
                const response = await fetch('../../api/exercises/get-all.php');
                const data = await response.json();
                if (data.success) {
                    allExercises = data.data;
                    renderExercises(allExercises);
                }
            } catch (error) {
                console.error('Error loading exercises:', error);
            }
        }

        function renderExercises(exercises) {
            const grid = document.getElementById('exercisesGrid');
            const noMsg = document.getElementById('noExercisesMessage');
            if (!grid) return;
            
            if (exercises.length === 0) {
                grid.style.display = 'none';
                noMsg.style.display = 'block';
                return;
            }
            
            grid.style.display = 'grid';
            noMsg.style.display = 'none';
            grid.innerHTML = exercises.map(ex => `
                <div class="content-card" style="padding: 0; overflow: hidden; height: 100%;">
                    <div style="height: 160px; background: #1a1a1a; display: flex; align-items: center; justify-content: center; position: relative;">
                        ${ex.image_url ? `<img src="${ex.image_url}" style="width: 100%; height: 100%; object-fit: cover;">` : `<i class="fas fa-running" style="font-size: 48px; color: #333;"></i>`}
                        <span class="status-badge" style="position: absolute; top: 12px; right: 12px; font-size: 0.7rem; padding: 4px 10px; background: var(--glass); border: 1px solid var(--glass-border);">${ex.category}</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4 style="font-weight: 800; margin-bottom: 8px; font-size: 1.1rem;"><?php echo htmlspecialchars(isset($ex['name']) ? $ex['name'] : ''); ?>${ex.name}</h4>
                        <p style="font-size: 0.85rem; color: var(--dark-text-secondary); line-height: 1.5; height: 3.8rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                            ${ex.description || 'No description available.'}
                        </p>
                    </div>
                </div>
            `).join('');
        }

        function filterExercises() {
            const query = document.getElementById('exerciseSearch').value.toLowerCase();
            const filtered = allExercises.filter(ex => 
                ex.name.toLowerCase().includes(query) || 
                ex.category.toLowerCase().includes(query)
            );
            renderExercises(filtered);
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

        document.addEventListener('DOMContentLoaded', loadExercises);
    </script>
</body>
</html>
