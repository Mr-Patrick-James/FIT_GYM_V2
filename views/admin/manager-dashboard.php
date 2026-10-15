<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireManagerAccess();

// Pure admins go to their own dashboard
if (isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();

$settings = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

function getSetting($key, $default = '', $settings = []) {
    return $settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | <?php echo htmlspecialchars(getSetting('gym_name', 'Martinez Fitness', $settings)); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=3.1">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
                if (document.body) document.body.classList.add('light-mode');
            } else {
                document.documentElement.classList.remove('light-mode');
                if (document.body) document.body.classList.remove('light-mode');
            }
        })();
    </script>
</head>
<body class="role-manager">

    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar">
        <div class="logo">
            <h1>FitPay</h1>
            <p>GYM MANAGEMENT</p>
        </div>
        <ul class="nav-links">
            <li><a href="manager-dashboard.php" class="active"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
        </ul>
        <div class="admin-profile">
            <div class="admin-avatar"><?php
                $name = $user['name'] ?? 'Manager';
                $initials = '';
                foreach (explode(' ', $name) as $w) {
                    if (!empty($w)) $initials .= strtoupper($w[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($name); ?></h4>
                <p>Gym Manager</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>Monitor gym operations and manage daily tasks</p>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search bookings, members...">
                </div>
                <button class="action-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="action-btn notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
                </button>
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Tab bar — only manager-relevant sections -->
        <div class="control-bar-container">
            <div class="control-bar">
                <button class="control-tab active" data-tab="overview" onclick="switchTab('overview', this)">
                    <i class="fas fa-chart-pie"></i> Overview
                </button>
                <button class="control-tab" data-tab="bookings" onclick="switchTab('bookings', this)">
                    <i class="fas fa-calendar-check"></i> Bookings
                </button>
                <button class="control-tab" data-tab="payments" onclick="switchTab('payments', this)">
                    <i class="fas fa-money-bill-wave"></i> Payments
                </button>
                <button class="control-tab" data-tab="members" onclick="switchTab('members', this)">
                    <i class="fas fa-users"></i> Members
                </button>
                <button class="control-tab" data-tab="equipment" onclick="switchTab('equipment', this)">
                    <i class="fas fa-tools"></i> Equipment
                </button>
            </div>
        </div>

        <!-- Stats Grid — populated per tab -->
        <div class="stats-grid" id="statsGrid"></div>

        <!-- Quick Actions -->
        <div class="content-card" style="margin-top: 24px; margin-bottom: 32px;">
            <div class="card-header"><h3>Quick Actions</h3></div>
            <div class="quick-actions-grid">
                <a href="bookings.php?status=pending" class="quick-action-card">
                    <i class="fas fa-check-circle"></i>
                    <h4>Verify Payments</h4>
                    <p id="pendingText">Loading...</p>
                </a>
                <a href="bookings.php" class="quick-action-card">
                    <i class="fas fa-calendar-check"></i>
                    <h4>All Bookings</h4>
                    <p>View and manage bookings</p>
                </a>
                <a href="members.php" class="quick-action-card">
                    <i class="fas fa-users"></i>
                    <h4>Members</h4>
                    <p>Manage gym members</p>
                </a>
                <a href="equipment.php" class="quick-action-card">
                    <i class="fas fa-tools"></i>
                    <h4>Equipment</h4>
                    <p>Track gym equipment</p>
                </a>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="content-card" style="margin-bottom: 32px;">
            <div class="card-header">
                <h3>Recent Booking Requests</h3>
                <a href="bookings.php" class="card-btn primary">
                    <i class="fas fa-arrow-right"></i>
                    <span>View All</span>
                </a>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Package</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recentBookingsTable">
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--dark-text-secondary);">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer">
            <p>
                <i class="fas fa-heart"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System
                <i class="fas fa-bolt"></i>
            </p>
        </div>
    </main>

    <!-- Notifications Modal -->
    <div class="modal-overlay" id="notificationsModal">
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header">
                <h3><i class="fas fa-bell" style="color:var(--primary);margin-right:10px;"></i>Notifications</h3>
                <button class="close-modal" onclick="toggleNotifications()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="notificationsList" style="max-height:440px;overflow-y:auto;padding:8px;"></div>
            </div>
            <div class="modal-footer" style="padding:16px 24px;border-top:1px solid var(--dark-border);display:flex;justify-content:flex-end;">
                <button class="btn btn-secondary" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/theme.js"></script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script>
    // ─── State ────────────────────────────────────────────────────────────────
    let allBookings  = [];
    let allPayments  = [];
    let allMembers   = [];
    let allEquipment = [];
    let currentTab   = 'overview';

    // ─── Init ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        await Promise.all([
            loadBookings(),
            loadPayments(),
            loadMembers(),
            loadEquipment(),
            loadNotifications()
        ]);
        renderTab('overview');

        // Poll every 15 seconds for new bookings + notifications
        setInterval(async () => {
            await loadBookings();
            await loadNotifications();
            renderTab(currentTab);
        }, 15000);
    });

    // ─── Data loaders ─────────────────────────────────────────────────────────
    async function loadBookings() {
        try {
            const r = await fetch('../../api/bookings/get-all.php', { credentials: 'include' });
            const d = await r.json();
            if (d.success) allBookings = d.data || [];
            updatePendingBadge();
        } catch(e) { console.error('loadBookings:', e); }
    }

    async function loadPayments() {
        try {
            const r = await fetch('../../api/payments/get-all.php', { credentials: 'include' });
            const d = await r.json();
            if (d.success) allPayments = d.data || [];
        } catch(e) { console.error('loadPayments:', e); }
    }

    async function loadMembers() {
        try {
            const r = await fetch('../../api/bookings/get-all.php?status=verified', { credentials: 'include' });
            const d = await r.json();
            if (d.success) {
                const seen = new Set();
                allMembers = (d.data || []).filter(b => {
                    if (seen.has(b.user_id)) return false;
                    seen.add(b.user_id); return true;
                });
            }
        } catch(e) { console.error('loadMembers:', e); }
    }

    async function loadEquipment() {
        try {
            const r = await fetch('../../api/equipment/get-all.php', { credentials: 'include' });
            const d = await r.json();
            if (d.success) allEquipment = d.data || [];
        } catch(e) { console.error('loadEquipment:', e); }
    }

    function updatePendingBadge() {
        const pending = allBookings.filter(b => b.status === 'pending').length;
        const badge = document.getElementById('bookingsBadge');
        if (badge) { badge.textContent = pending; badge.style.display = pending ? '' : 'none'; }
        const pt = document.getElementById('pendingText');
        if (pt) pt.textContent = `${pending} pending verification${pending !== 1 ? 's' : ''}`;
    }

    // ─── Tab switching ────────────────────────────────────────────────────────
    function switchTab(tab, btn) {
        currentTab = tab;
        document.querySelectorAll('.control-tab').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        renderTab(tab);
    }

    function renderTab(tab) {
        const grid = document.getElementById('statsGrid');
        const cards = buildStatCards(tab);
        grid.innerHTML = cards;
        renderRecentBookings();
    }

    function buildStatCards(tab) {
        const pending  = allBookings.filter(b => b.status === 'pending').length;
        const verified = allBookings.filter(b => b.status === 'verified').length;
        const expired  = allBookings.filter(b => b.status === 'expired').length;
        const totalRev = allPayments.reduce((s, p) => s + parseFloat(p.amount || 0), 0);
        const todayStr = new Date().toISOString().split('T')[0];
        const todayBookings = allBookings.filter(b => (b.created_at || '').startsWith(todayStr)).length;

        const statMap = {
            overview: [
                { icon: 'fa-calendar-check', bg: 'rgba(59,130,246,.15)',  color: '#3b82f6', value: allBookings.length,  label: 'Total Bookings' },
                { icon: 'fa-clock',          bg: 'rgba(245,158,11,.15)',  color: '#f59e0b', value: pending,             label: 'Pending Verification' },
                { icon: 'fa-check-circle',   bg: 'rgba(34,197,94,.15)',   color: '#22c55e', value: verified,            label: 'Active Members' },
                { icon: 'fa-peso-sign',      bg: 'rgba(168,85,247,.15)',  color: '#a855f7', value: '₱' + totalRev.toLocaleString('en-PH', {minimumFractionDigits:2}), label: 'Total Revenue' },
            ],
            bookings: [
                { icon: 'fa-list',           bg: 'rgba(59,130,246,.15)',  color: '#3b82f6', value: allBookings.length,  label: 'All Bookings' },
                { icon: 'fa-clock',          bg: 'rgba(245,158,11,.15)',  color: '#f59e0b', value: pending,             label: 'Pending' },
                { icon: 'fa-check',          bg: 'rgba(34,197,94,.15)',   color: '#22c55e', value: verified,            label: 'Verified' },
                { icon: 'fa-calendar-day',   bg: 'rgba(99,102,241,.15)',  color: '#6366f1', value: todayBookings,       label: "Today's Bookings" },
            ],
            payments: [
                { icon: 'fa-peso-sign',      bg: 'rgba(168,85,247,.15)',  color: '#a855f7', value: '₱' + totalRev.toLocaleString('en-PH', {minimumFractionDigits:2}), label: 'Total Collected' },
                { icon: 'fa-receipt',        bg: 'rgba(34,197,94,.15)',   color: '#22c55e', value: allPayments.length,  label: 'Payment Records' },
                { icon: 'fa-clock',          bg: 'rgba(245,158,11,.15)',  color: '#f59e0b', value: pending,             label: 'Awaiting Verification' },
                { icon: 'fa-calendar-check', bg: 'rgba(59,130,246,.15)',  color: '#3b82f6', value: verified,            label: 'Verified Payments' },
            ],
            members: [
                { icon: 'fa-users',          bg: 'rgba(59,130,246,.15)',  color: '#3b82f6', value: allMembers.length,   label: 'Unique Members' },
                { icon: 'fa-check-circle',   bg: 'rgba(34,197,94,.15)',   color: '#22c55e', value: verified,            label: 'Active Memberships' },
                { icon: 'fa-times-circle',   bg: 'rgba(239,68,68,.15)',   color: '#ef4444', value: expired,             label: 'Expired' },
                { icon: 'fa-clock',          bg: 'rgba(245,158,11,.15)',  color: '#f59e0b', value: pending,             label: 'Pending Approval' },
            ],
            equipment: [
                { icon: 'fa-tools',          bg: 'rgba(99,102,241,.15)',  color: '#6366f1', value: allEquipment.length, label: 'Total Equipment' },
                { icon: 'fa-check',          bg: 'rgba(34,197,94,.15)',   color: '#22c55e', value: allEquipment.filter(e => e.status === 'good' || !e.status).length, label: 'In Good Condition' },
                { icon: 'fa-exclamation-triangle', bg: 'rgba(245,158,11,.15)', color: '#f59e0b', value: allEquipment.filter(e => e.status === 'maintenance').length, label: 'Under Maintenance' },
                { icon: 'fa-times-circle',   bg: 'rgba(239,68,68,.15)',   color: '#ef4444', value: allEquipment.filter(e => e.status === 'broken').length, label: 'Broken / Out of Order' },
            ],
        };

        return (statMap[tab] || statMap.overview).map(s => `
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background:${s.bg};color:${s.color};">
                        <i class="fas ${s.icon}"></i>
                    </div>
                </div>
                <div class="stat-value">${s.value}</div>
                <div class="stat-label">${s.label}</div>
            </div>
        `).join('');
    }

    // ─── Recent bookings table ────────────────────────────────────────────────
    function renderRecentBookings() {
        const tbody = document.getElementById('recentBookingsTable');
        if (!tbody) return;

        const recent = [...allBookings]
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 10);

        if (recent.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--dark-text-secondary);">No bookings yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = recent.map(b => {
            const statusClass = b.status === 'verified' ? 'status-verified'
                              : b.status === 'expired'  ? 'status-expired'
                              : 'status-pending';
            const statusLabel = b.status.charAt(0).toUpperCase() + b.status.slice(1);
            const date = b.booking_date
                ? new Date(b.booking_date).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'})
                : '—';
            const amount = b.amount ? '₱' + parseFloat(b.amount).toLocaleString('en-PH', {minimumFractionDigits:2}) : '—';

            return `
                <tr>
                    <td data-label="Client">
                        <div style="font-weight:700;">${escHtml(b.name || b.user_name || '—')}</div>
                        <div style="font-size:0.75rem;color:var(--dark-text-secondary);">${escHtml(b.email || '')}</div>
                    </td>
                    <td data-label="Package">${escHtml(b.package_name || '—')}</td>
                    <td data-label="Date">${date}</td>
                    <td data-label="Amount" style="font-weight:800;">${amount}</td>
                    <td data-label="Status"><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                    <td data-label="Actions">
                        <a href="bookings.php" class="btn btn-secondary" style="padding:6px 12px;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                            <i class="fas fa-arrow-right"></i> Manage
                        </a>
                    </td>
                </tr>`;
        }).join('');
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Notifications ────────────────────────────────────────────────────────
    async function loadNotifications() {
        try {
            const r = await fetch('../../api/notifications/get-all.php', { credentials: 'include' });
            const d = await r.json();
            if (!d.success) return;

            const unread = d.data.filter(n => !n.is_read);
            const badge  = document.getElementById('notificationBadge');
            if (badge) {
                badge.textContent = unread.length;
                badge.style.display = unread.length ? 'flex' : 'none';
            }

            const list = document.getElementById('notificationsList');
            if (!list) return;
            if (d.data.length === 0) {
                list.innerHTML = `<p style="text-align:center;padding:32px;color:var(--dark-text-secondary);">No notifications yet.</p>`;
                return;
            }
            list.innerHTML = d.data.slice(0, 30).map(n => `
                <div onclick="markRead(${n.id})" style="padding:14px 16px;border-bottom:1px solid var(--dark-border);cursor:pointer;border-radius:8px;${!n.is_read ? 'background:rgba(59,130,246,0.06);' : ''}" class="notif-item">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                        <strong style="font-size:0.85rem;color:${!n.is_read ? 'var(--primary)' : 'var(--dark-text)'};">${escHtml(n.title)}</strong>
                        <span style="font-size:0.7rem;color:var(--dark-text-secondary);white-space:nowrap;margin-left:8px;">${new Date(n.created_at).toLocaleDateString()}</span>
                    </div>
                    <p style="font-size:0.8rem;color:var(--dark-text-secondary);line-height:1.4;">${escHtml(n.message)}</p>
                    ${!n.is_read ? '<div style="width:6px;height:6px;background:#3b82f6;border-radius:50%;margin-top:6px;"></div>' : ''}
                </div>`).join('');
        } catch(e) { console.error('loadNotifications:', e); }
    }

    function toggleNotifications() {
        const m = document.getElementById('notificationsModal');
        m.classList.toggle('active');
        if (m.classList.contains('active')) loadNotifications();
    }

    async function markRead(id) {
        await fetch('../../api/notifications/mark-as-read.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadNotifications();
    }

    async function markAllRead() {
        const r = await fetch('../../api/notifications/get-all.php', { credentials: 'include' });
        const d = await r.json();
        if (d.success) {
            for (const n of d.data.filter(x => !x.is_read)) {
                await fetch('../../api/notifications/mark-as-read.php', {
                    method: 'POST', credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: n.id })
                });
            }
        }
        loadNotifications();
    }

    // ─── Logout ───────────────────────────────────────────────────────────────
    async function handleLogout() {
        if (!confirm('Are you sure you want to logout?')) return;
        try { await fetch('../../api/auth/logout.php', { method: 'POST', credentials: 'include' }); } catch(e) {}
        window.location.href = '../../index.php';
    }
    </script>
</body>
</html>
