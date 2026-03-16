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
            <li><a href="plans.php"><i class="fas fa-clipboard-list"></i> <span>Exercise Plans</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
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
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px; max-width: 800px;">
            <div class="card-header">
                <h3>Trainer Details</h3>
            </div>
            
            <div style="padding: 32px;">
                <div style="display: flex; align-items: center; gap: 32px; margin-bottom: 40px; padding-bottom: 32px; border-bottom: 1px solid var(--dark-border);">
                    <div class="admin-avatar" style="width: 100px; height: 100px; font-size: 2.5rem; background: var(--glass); color: var(--primary);">
                        <?php 
                            $initials = '';
                            foreach(explode(' ', $user['name']) as $word) {
                                if (!empty($word)) $initials .= strtoupper($word[0]);
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div>
                        <h2 style="font-weight: 800; margin-bottom: 8px;"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p style="color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem;"><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                        <p style="color: var(--dark-text-secondary); margin-top: 4px;"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px;">
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">Contact Number</span>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($user['contact'] ?: 'Not provided'); ?></span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">Trainer ID</span>
                        <span style="font-weight: 600;">TRN-<?php echo str_pad($trainer['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div style="grid-column: 1/-1; display: flex; flex-direction: column; gap: 8px;">
                        <span style="color: var(--dark-text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">Professional Bio</span>
                        <p style="line-height: 1.6; color: var(--dark-text-secondary);"><?php echo nl2br(htmlspecialchars($trainer['bio'] ?: 'No bio provided. Update your profile via the admin panel.')); ?></p>
                    </div>
                </div>
                
                <div style="margin-top: 48px; padding: 24px; background: var(--glass); border-radius: 12px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 16px;"><i class="fas fa-lock" style="margin-right: 8px;"></i> Security</h4>
                    <p style="font-size: 0.9rem; color: var(--dark-text-secondary); margin-bottom: 20px;">Want to change your password or update your contact information? Please contact the gym administrator.</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <script>
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
</body>
</html>
