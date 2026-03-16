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
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-chart-line"></i> Client Progress: <span id="progressMemberName">Member</span></h3>
                <button class="close-modal" onclick="closeProgressModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="tabs" style="display: flex; gap: 20px; border-bottom: 1px solid var(--dark-border); margin-bottom: 24px; padding-bottom: 10px;">
                    <button class="tab-btn active" onclick="switchTab('log')" id="tabLog">Log Progress</button>
                    <button class="tab-btn" onclick="switchTab('history')" id="tabHistory">History</button>
                </div>

                <!-- Log Progress Tab -->
                <div id="logTabContent">
                    <form id="progressForm">
                        <input type="hidden" id="progressBookingId">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date *</label>
                                <input type="date" id="progressDate" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" id="progressWeight" step="0.1" placeholder="e.g. 75.5">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Remarks / Recommendations</label>
                            <textarea id="progressRemarks" rows="4" placeholder="How was the session? Any improvements or advice?"></textarea>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Progress
                            </button>
                        </div>
                    </form>
                </div>

                <!-- History Tab -->
                <div id="historyTabContent" style="display: none;">
                    <div id="progressHistoryList" style="max-height: 400px; overflow-y: auto;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allClients = [];
        let activeBookingId = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadClients();
            document.getElementById('progressForm')?.addEventListener('submit', handleProgressSubmit);
        });

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
                    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                        <p style="color: #ef4444;">${data.message}</p>
                    </div>`;
                }
            } catch (error) {
                console.error('Error loading clients:', error);
                grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <p style="color: #ef4444;">Failed to load clients. Please try again.</p>
                </div>`;
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
                return `
                    <div class="content-card member-card" style="padding: 0; display: flex; flex-direction: column; transition: transform 0.3s;">
                        <div style="padding: 24px; display: flex; align-items: center; gap: 20px; border-bottom: 1px solid var(--dark-border);">
                            <div class="admin-avatar" style="width: 64px; height: 64px; font-size: 1.5rem; background: var(--glass); color: var(--primary);">
                                ${initials}
                            </div>
                            <div>
                                <h3 style="font-weight: 800; margin-bottom: 4px;">${member.name}</h3>
                                <p style="color: var(--dark-text-secondary); font-size: 0.85rem;">${member.email}</p>
                            </div>
                        </div>
                        
                        <div style="padding: 24px; flex: 1;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="color: var(--dark-text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Package</span>
                                    <span style="font-weight: 600; font-size: 0.9rem;">${member.package_name}</span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="color: var(--dark-text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Status</span>
                                    <span class="status-badge ${member.is_expired ? 'status-rejected' : 'status-verified'}" style="font-size: 0.75rem; padding: 4px 10px;">
                                        <i class="fas fa-${member.is_expired ? 'times-circle' : 'check-circle'}"></i>
                                        ${member.is_expired ? 'Expired' : 'Active Member'}
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.85rem; margin-bottom: 8px;">
                                <i class="fas fa-phone" style="width: 16px;"></i>
                                <span>${member.contact || 'Not provided'}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-calendar-alt" style="width: 16px;"></i>
                                <span>Expires: ${new Date(member.expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                        </div>
                        
                        <div style="padding: 16px 24px; border-top: 1px solid var(--dark-border); display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <button class="card-btn primary" style="justify-content: center; ${!member.is_trainer_assisted ? 'opacity: 0.5; cursor: not-allowed;' : ''}" onclick="${member.is_trainer_assisted ? `assignPlan(${member.booking_id})` : 'showRestrictionMessage()'}">
                                <i class="fas fa-clipboard-list"></i> Plan
                            </button>
                            <button class="card-btn" style="justify-content: center; border-color: var(--info); color: var(--info);" onclick="viewProgress(${member.booking_id})">
                                <i class="fas fa-chart-line"></i> Progress
                            </button>
                            <button class="card-btn" style="justify-content: center; grid-column: span 2; border-color: var(--warning); color: var(--warning);" onclick="manageClient(${member.booking_id})">
                                <i class="fas fa-user-cog"></i> Full Management
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterMembers() {
            const query = document.getElementById('memberSearch').value.toLowerCase();
            const filtered = allClients.filter(c => 
                c.name.toLowerCase().includes(query) || 
                c.email.toLowerCase().includes(query)
            );
            renderClients(filtered);
        }

        function showRestrictionMessage() {
            showNotification('This member is on a basic package which does not include trainer-managed exercise plans.', 'warning');
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

        function assignPlan(bookingId) {
            window.location.href = `plans.php?booking_id=${bookingId}`;
        }

        function manageClient(bookingId) {
            window.location.href = `client-details.php?booking_id=${bookingId}`;
        }

        async function viewProgress(bookingId) {
            const member = allClients.find(c => c.booking_id == bookingId);
            if (!member) return;
            
            activeBookingId = bookingId;
            document.getElementById('progressMemberName').textContent = member.name;
            document.getElementById('progressBookingId').value = bookingId;
            document.getElementById('progressModal').classList.add('active');
            
            // Default to Log Progress tab
            switchTab('log');
            
            // Reset form
            document.getElementById('progressForm').reset();
            document.getElementById('progressDate').value = new Date().toISOString().split('T')[0];
        }

        function closeProgressModal() {
            document.getElementById('progressModal').classList.remove('active');
            activeBookingId = null;
        }

        function switchTab(tab) {
            const logTab = document.getElementById('tabLog');
            const historyTab = document.getElementById('tabHistory');
            const logContent = document.getElementById('logTabContent');
            const historyContent = document.getElementById('historyTabContent');
            
            if (tab === 'log') {
                logTab.classList.add('active');
                historyTab.classList.remove('active');
                logContent.style.display = 'block';
                historyContent.style.display = 'none';
            } else {
                logTab.classList.remove('active');
                historyTab.classList.add('active');
                logContent.style.display = 'none';
                historyContent.style.display = 'block';
                loadProgressHistory();
            }
        }

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
                if (data.success) {
                    showNotification('Progress logged successfully!', 'success');
                    switchTab('history');
                } else {
                    showNotification(data.message, 'warning');
                }
            } catch (error) {
                console.error('Error logging progress:', error);
                showNotification('Error saving progress', 'warning');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalContent;
            }
        }

        async function loadProgressHistory() {
            const list = document.getElementById('progressHistoryList');
            list.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>';
            
            try {
                const response = await fetch(`../../api/trainers/get-progress-history.php?booking_id=${activeBookingId}`);
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    list.innerHTML = data.data.map(log => `
                        <div style="padding: 16px; border-bottom: 1px solid var(--dark-border); border-left: 4px solid var(--primary-color); margin-bottom: 10px; background: rgba(255,255,255,0.02);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <strong style="color: var(--primary-color);"><i class="fas fa-calendar-day"></i> ${new Date(log.logged_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</strong>
                                ${log.weight ? `<span style="font-weight: 800; color: white;"><i class="fas fa-weight"></i> ${log.weight} kg</span>` : ''}
                            </div>
                            <p style="font-size: 0.9rem; color: var(--dark-text-secondary); line-height: 1.5;">${log.remarks || 'No remarks provided.'}</p>
                            <div style="font-size: 0.7rem; color: #444; margin-top: 8px; text-align: right;">Logged: ${new Date(log.created_at).toLocaleString()}</div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">No progress logs found for this client.</div>';
                }
            } catch (error) {
                console.error('Error loading history:', error);
                list.innerHTML = '<div style="text-align: center; padding: 20px; color: #ef4444;">Failed to load history.</div>';
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

        document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
