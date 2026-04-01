<?php
require_once '../../api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management | FitPay Admin</title>
    
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
            <p>GYM MANAGEMENT</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="trainers.php"><i class="fas fa-user-tie"></i> <span>Trainers</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="equipment.php" class="active"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
            <li><a href="report.php"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $adminName = $user['name'] ?? 'Admin';
                $initials = '';
                foreach(explode(' ', $adminName) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($adminName); ?></h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Equipment Management</h1>
                <p>Add and manage gym equipment for exercise planning</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="action-btn primary" onclick="openAddEquipmentModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add Equipment</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Equipment</h3>
                <div class="search-box" style="width: 300px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="equipmentSearch" placeholder="Search equipment..." oninput="filterEquipment()">
                </div>
            </div>
            
            <div id="equipmentGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
                <!-- Populated by JS -->
            </div>
            
            <div id="noEquipmentMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-tools" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No equipment found</h3>
                <p>Click "Add Equipment" to create your first entry.</p>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal-overlay" id="equipmentModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Equipment</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="equipmentForm" onsubmit="saveEquipment(event)">
                    <input type="hidden" id="equipmentId">
                    <div class="form-group">
                        <label>Equipment Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="equipmentName" required placeholder="e.g. Barbell, Dumbbell">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="equipmentCategory">
                            <option value="Free Weights">Free Weights</option>
                            <option value="Machines">Machines</option>
                            <option value="Cardio">Cardio</option>
                            <option value="Accessories">Accessories</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="equipmentStatus">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="out_of_order">Out of Order</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" id="equipmentImage" accept="image/*" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="equipmentDescription" rows="3" class="form-control" placeholder="Brief details about the equipment..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let allEquipment = [];

        document.addEventListener('DOMContentLoaded', loadEquipment);

        async function loadEquipment() {
            try {
                const response = await fetch('../../api/equipment/get-all.php');
                const data = await response.json();
                if (data.success) {
                    allEquipment = data.data;
                    renderEquipment();
                }
            } catch (err) { console.error(err); }
        }

        function renderEquipment(equipment = allEquipment) {
            const grid = document.getElementById('equipmentGrid');
            const noMsg = document.getElementById('noEquipmentMessage');
            
            grid.innerHTML = '';
            if (equipment.length === 0) {
                grid.style.display = 'none';
                noMsg.style.display = 'block';
                return;
            }

            grid.style.display = 'grid';
            noMsg.style.display = 'none';

            grid.innerHTML = equipment.map(eq => `
                <div class="content-card" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: #2a2a2a; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        ${eq.image_url ? `<img src="../../${eq.image_url}" style="width: 100%; height: 100%; object-fit: cover;">` : `<i class="fas fa-tools" style="font-size: 3rem; color: #444;"></i>`}
                    </div>
                    <div style="padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h4 style="font-weight: 700; color: var(--primary);">${eq.name}</h4>
                            <span class="status-badge ${eq.status}">${eq.status}</span>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-top: 4px;">${eq.category}</p>
                        <div style="display: flex; gap: 8px; margin-top: 16px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 6px;" onclick="editEquipment(${eq.id})"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn" style="flex: 1; padding: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444;" onclick="deleteEquipment(${eq.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function filterEquipment() {
            const query = document.getElementById('equipmentSearch').value.toLowerCase();
            const filtered = allEquipment.filter(eq => eq.name.toLowerCase().includes(query) || eq.category.toLowerCase().includes(query));
            renderEquipment(filtered);
        }

        function openAddEquipmentModal() {
            document.getElementById('modalTitle').textContent = 'Add New Equipment';
            document.getElementById('equipmentForm').reset();
            document.getElementById('equipmentId').value = '';
            document.getElementById('equipmentModal').classList.add('active');
        }

        function editEquipment(id) {
            const eq = allEquipment.find(e => e.id === id);
            if (!eq) return;
            document.getElementById('modalTitle').textContent = 'Edit Equipment';
            document.getElementById('equipmentId').value = eq.id;
            document.getElementById('equipmentName').value = eq.name;
            document.getElementById('equipmentCategory').value = eq.category;
            document.getElementById('equipmentStatus').value = eq.status || 'active';
            document.getElementById('equipmentDescription').value = eq.description || '';
            document.getElementById('equipmentModal').classList.add('active');
        }

        async function saveEquipment(e) {
            e.preventDefault();
            const id = document.getElementById('equipmentId').value;
            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('name', document.getElementById('equipmentName').value);
            formData.append('category', document.getElementById('equipmentCategory').value);
            formData.append('status', document.getElementById('equipmentStatus').value);
            formData.append('description', document.getElementById('equipmentDescription').value);
            
            const fileInput = document.getElementById('equipmentImage');
            if (fileInput.files[0]) formData.append('image', fileInput.files[0]);

            const url = id ? '../../api/equipment/update.php' : '../../api/equipment/create.php';
            
            try {
                const response = await fetch(url, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    closeModal();
                    loadEquipment();
                } else {
                    alert(result.message);
                }
            } catch (err) { console.error(err); }
        }

        async function deleteEquipment(id) {
            if (!confirm('Are you sure you want to delete this equipment?')) return;
            const formData = new FormData();
            formData.append('id', id);
            try {
                const response = await fetch('../../api/equipment/delete.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) loadEquipment();
            } catch (err) { console.error(err); }
        }

        function closeModal() {
            document.getElementById('equipmentModal').classList.remove('active');
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
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
