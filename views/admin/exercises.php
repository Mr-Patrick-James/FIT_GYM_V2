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
    <title>Exercise Management | FitPay Admin</title>
    
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
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
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
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="exercises.php" class="active"><i class="fas fa-running"></i> <span>Exercises</span></a></li>
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Exercise Management</h1>
                <p>Add and manage the master list of exercises and equipment</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn primary" onclick="openAddExerciseModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Exercise</span>
                </button>
                
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Exercises</h3>
                <div class="search-box" style="width: 300px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="exerciseSearch" placeholder="Search exercises..." oninput="filterExercises()">
                </div>
            </div>
            
            <div id="exercisesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noExercisesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-running" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No exercises found</h3>
                <p>Click "Add New Exercise" to create your first exercise entry.</p>
            </div>
        </div>
    </main>

    <!-- Add/Edit Exercise Modal -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="exerciseModalTitle">Add New Exercise</h3>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="exerciseForm" onsubmit="saveExercise(event)">
                    <input type="hidden" id="exerciseId">
                    <div class="form-group">
                        <label>Exercise Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="exerciseName" required placeholder="e.g., Bench Press">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select id="exerciseCategory">
                            <option value="Chest">Chest</option>
                            <option value="Back">Back</option>
                            <option value="Legs">Legs</option>
                            <option value="Shoulders">Shoulders</option>
                            <option value="Arms">Arms</option>
                            <option value="Core">Core</option>
                            <option value="Cardio">Cardio</option>
                            <option value="Full Body">Full Body</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Equipment (Optional)</label>
                        <select id="equipmentSelect">
                            <option value="">No specific equipment</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Exercise Image</label>
                        <div id="imageUploadArea" class="file-upload-area" style="padding: 20px; border: 2px dashed var(--dark-border); border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.3s;" onclick="document.getElementById('exerciseImageFile').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;">Click to upload exercise image</p>
                            <span style="font-size: 0.75rem; color: var(--dark-text-secondary);">JPG, PNG or WebP</span>
                            <input type="file" id="exerciseImageFile" accept="image/*" style="display: none;" onchange="handleImagePreview(event)">
                        </div>
                        <div id="imagePreviewContainer" style="display: none; margin-top: 15px; position: relative;">
                            <img id="imagePreview" src="" style="width: 100%; height: 180px; object-fit: cover; border-radius: 12px; border: 1px solid var(--dark-border);">
                            <button type="button" onclick="removeImagePreview()" style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.9); color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" id="exerciseImageUrl">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="exerciseDescription" rows="3" placeholder="Briefly describe the exercise..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea id="exerciseInstructions" rows="4" placeholder="Step-by-step instructions..."></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeExerciseModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Exercise
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteExerciseModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 style="color: #ef4444;">Delete Exercise</h3>
                <button class="close-modal" onclick="closeDeleteExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 16px;"></i>
                <p>Are you sure you want to delete <strong id="deleteExerciseName">this exercise</strong>?</p>
                <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-top: 8px;">
                    This will also remove it from any membership package plans it's currently assigned to.
                </p>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; display: flex; gap: 12px;">
                <button class="btn btn-secondary" style="flex: 1;" onclick="closeDeleteExerciseModal()">Cancel</button>
                <button class="btn" style="flex: 1; background: #ef4444; color: white;" onclick="confirmDeleteExercise()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/exercises.js"></script>
</body>
</html>
