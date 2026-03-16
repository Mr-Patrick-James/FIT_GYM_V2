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
                <p>Manage your clients' fitness progress and plans</p>
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
                <h3>Active Members</h3>
            </div>
            
            <div id="membersGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px; padding: 24px;">
                <?php if (empty($members)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                        <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                        <h3>No clients found</h3>
                        <p>Members will appear here once they have verified bookings.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                        <?php 
                            $initials = '';
                            foreach(explode(' ', $member['name']) as $word) {
                                if (!empty($word)) $initials .= strtoupper($word[0]);
                            }
                            $initials = substr($initials, 0, 2);
                            
                            $isExpired = false;
                            if ($member['latest_expiry']) {
                                $isExpired = strtotime($member['latest_expiry']) < time();
                            }
                        ?>
                        <div class="content-card member-card" style="padding: 0; display: flex; flex-direction: column; transition: transform 0.3s;">
                            <div style="padding: 24px; display: flex; align-items: center; gap: 20px; border-bottom: 1px solid var(--dark-border);">
                                <div class="admin-avatar" style="width: 64px; height: 64px; font-size: 1.5rem; background: var(--glass); color: var(--primary);">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div>
                                    <h3 style="font-weight: 800; margin-bottom: 4px;"><?php echo htmlspecialchars($member['name']); ?></h3>
                                    <p style="color: var(--dark-text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($member['email']); ?></p>
                                </div>
                            </div>
                            
                            <div style="padding: 24px; flex: 1;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span style="color: var(--dark-text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Member Since</span>
                                        <span style="font-weight: 600; font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($member['created_at'])); ?></span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span style="color: var(--dark-text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Status</span>
                                        <span class="status-badge <?php echo $isExpired ? 'status-rejected' : 'status-verified'; ?>" style="font-size: 0.75rem; padding: 4px 10px;">
                                            <i class="fas fa-<?php echo $isExpired ? 'times-circle' : 'check-circle'; ?>"></i>
                                            <?php echo $isExpired ? 'Expired' : 'Active Member'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.85rem; margin-bottom: 8px;">
                                    <i class="fas fa-phone" style="width: 16px;"></i>
                                    <span><?php echo htmlspecialchars($member['contact'] ?: 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.85rem;">
                                    <i class="fas fa-calendar-alt" style="width: 16px;"></i>
                                    <span>Expires: <?php echo $member['latest_expiry'] ? date('M d, Y', strtotime($member['latest_expiry'])) : 'No booking'; ?></span>
                                </div>
                            </div>
                            
                            <div style="padding: 16px 24px; border-top: 1px solid var(--dark-border); display: flex; gap: 12px;">
                                <button class="card-btn primary" style="flex: 1; justify-content: center;" onclick="assignPlan(<?php echo $member['id']; ?>)">
                                    <i class="fas fa-clipboard-list"></i> Assign Plan
                                </button>
                                <button class="card-btn" style="flex: 1; justify-content: center;" onclick="viewProgress(<?php echo $member['id']; ?>)">
                                    <i class="fas fa-chart-line"></i> Progress
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Martinez Fitness Gym • Trainer Portal v1.0</p>
        </div>
    </main>

    <script>
        function filterMembers() {
            const query = document.getElementById('memberSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.member-card');
            
            cards.forEach(card => {
                const name = card.querySelector('h3').textContent.toLowerCase();
                const email = card.querySelector('p').textContent.toLowerCase();
                if (name.includes(query) || email.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
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

        function assignPlan(memberId) {
            alert('This feature (Assign Exercise Plan) is coming soon!');
        }

        function viewProgress(memberId) {
            alert('This feature (Client Progress Monitoring) is coming soon!');
        }

        document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
