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
    <title>Packages Management | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    <style>
        .package-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary) !important;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .package-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        #packagesGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding: 24px;
        }
        .modal-body .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark-text);
        }
        .modal-body .form-control, 
        .modal-body input[type="text"],
        .modal-body input[type="number"],
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: 12px;
            background: var(--dark-bg);
            border: 1px solid var(--dark-border);
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
        }

        /* Modal Tab Styling */
        .modal-tabs {
            display: flex;
            gap: 12px;
            padding: 0 24px 16px;
            border-bottom: 1px solid var(--dark-border);
        }
        .modal-tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--dark-border);
            color: var(--dark-text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-tab-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .modal-tab-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #000;
        }
    </style>
    
    <!-- Apply theme immediately before page renders to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
                if (document.body) {
                    document.body.classList.add('light-mode');
                }
            } else {
                document.documentElement.classList.remove('light-mode');
                if (document.body) {
                    document.body.classList.remove('light-mode');
                }
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
            <li><a href="trainers.php"><i class="fas fa-user-tie"></i> <span>Trainers</span></a></li>
            <li><a href="packages.php" class="active"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Package Management</h1>
                <p>Create, edit, and manage gym membership plans</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn primary" onclick="openAddPackageModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New Package</span>
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

        <!-- Packages Grid -->
        <div class="content-card" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Packages</h3>
                <div class="card-actions">
                    <span style="color: var(--dark-text-secondary); font-size: 0.9rem;">
                        <strong id="showingCount">0</strong> packages available
                    </span>
                </div>
            </div>
            
            <div id="packagesGrid">
                <!-- Populated by JavaScript -->
            </div>
            
            <div id="noPackagesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-dumbbell" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No packages found</h3>
                <p>Click "Add New Package" to create your first membership package.</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-heart" style="color: var(--primary);"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0
                <i class="fas fa-bolt" style="color: var(--primary);"></i>
            </p>
        </div>
    </main>

    <!-- Add/Edit Package Modal -->
    <div class="modal-overlay" id="packageModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Package</h3>
                <button class="close-modal" onclick="closePackageModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="packageForm" onsubmit="savePackage(event)">
                    <div class="form-group">
                        <label>Package Name <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packageName" required placeholder="e.g., Monthly Membership">
                    </div>
                    
                    <div class="form-group">
                        <label>Duration <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packageDuration" required placeholder="e.g., 30 Days">
                    </div>
                    
                    <div class="form-group">
                        <label>Price <span style="color: var(--warning);">*</span></label>
                        <input type="text" id="packagePrice" required placeholder="e.g., ₱1,500" onfocus="prependPesoSymbol()">
                    </div>
                    
                    <div class="form-group">
                        <label>Tag/Badge</label>
                        <select id="packageTag">
                            <option value="Basic">Basic</option>
                            <option value="Popular">Popular</option>
                            <option value="Best Value">Best Value</option>
                            <option value="Premium">Premium</option>
                            <option value="VIP">VIP</option>
                            <option value="">None</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description / Features</label>
                        <textarea id="packageDescription" rows="4" placeholder="Enter features line by line (e.g.,&#10;Full Equipment Access&#10;Locker Room Access&#10;Expert Guidance)"></textarea>
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 4px;">Each line will appear as a bullet point with a checkmark on the landing page.</p>
                    </div>

                    <div class="form-group">
                        <label>Target Goal <span style="color: var(--warning);">*</span></label>
                        <select id="packageGoal" required class="form-control">
                            <option value="General Fitness">General Fitness</option>
                            <option value="Muscle Building">Muscle Building</option>
                            <option value="Body Toning">Body Toning</option>
                            <option value="Weight Loss">Weight Loss</option>
                            <option value="Strength & Power">Strength & Power</option>
                            <option value="Endurance">Endurance</option>
                            <option value="Flexibility">Flexibility</option>
                        </select>
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 4px;">
                            The primary objective this package is designed for.
                        </p>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 12px; margin-top: 10px; cursor: pointer;">
                        <input type="checkbox" id="isTrainerAssisted" onchange="toggleTrainerSelection()" style="width: 20px; height: 20px; cursor: pointer;">
                        <label for="isTrainerAssisted" style="margin-bottom: 0; font-weight: 700; color: var(--primary);">Personal Trainer Assisted Membership</label>
                    </div>

                    <!-- Trainer Selection (Hidden by default) -->
                    <div id="trainerSelectionGroup" style="display: none; margin-left: 28px; padding: 15px; background: rgba(255,255,255,0.02); border: 1px solid var(--dark-border); border-radius: 8px; margin-top: 10px;">
                        <label style="font-size: 0.85rem; font-weight: 700; margin-bottom: 10px; display: block; color: var(--primary);">
                            Select Available Trainers for this Package
                        </label>
                        <div id="packageTrainersList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 150px; overflow-y: auto; padding-right: 5px;">
                            <!-- Populated by JS -->
                            <p style="font-size: 0.8rem; color: var(--dark-text-secondary); grid-column: 1/-1;">Loading trainers...</p>
                        </div>
                        <p style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 10px;">
                            Only selected trainers will be available for assignment when members book this package.
                        </p>
                    </div>
                    
                    <div class="modal-footer" style="padding: 24px 0 0; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--dark-border); margin-top: 24px;">
                        <button type="button" class="btn btn-secondary" onclick="closePackageModal()" style="padding: 10px 24px;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                            <i class="fas fa-save" style="margin-right: 8px;"></i>
                            Save Package
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Exercise Management Modal -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <div>
                    <h3 id="exerciseModalTitle">Manage Package Details</h3>
                    <p id="exerciseModalSubtitle" style="font-size: 0.85rem; color: var(--dark-text-secondary);"></p>
                </div>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-tabs">
                <button class="modal-tab-btn active" onclick="switchExerciseTab('exercises')" id="pkgTabExercises">Exercises</button>
                <button class="modal-tab-btn" onclick="switchExerciseTab('diet')" id="pkgTabDiet">Nutrition & Diet</button>
                <button class="modal-tab-btn" onclick="switchExerciseTab('guidance')" id="pkgTabGuidance">Tips & Guidance</button>
            </div>
            
            <div class="modal-body" id="exercisesTabContent" style="padding: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 32px;">
                    <!-- Current Exercises List -->
                    <div>
                        <h4 style="margin-bottom: 16px; color: var(--primary);">Current Package Plan</h4>
                        <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> View and manage the exercises assigned to this package.
                        </p>
                        <div id="packageExercisesList" style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                            <!-- Exercises populated by JS -->
                        </div>
                    </div>

                    <!-- Add Exercise Form -->
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--dark-border); border-radius: 12px; padding: 20px; height: fit-content;">
                        <h4 style="margin-bottom: 20px; color: #fff;">Add Exercise to Plan</h4>
                        <form id="addExerciseForm">
                            <div class="form-group">
                                <label>Select Exercise</label>
                                <select id="exerciseSelect" required class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 10px; border-radius: 8px;">
                                    <option value="">Choose an exercise...</option>
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="form-group">
                                    <label>Sets</label>
                                    <input type="number" id="exerciseSets" required min="1" value="3" class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 10px; border-radius: 8px;">
                                </div>
                                <div class="form-group">
                                    <label>Reps</label>
                                    <input type="text" id="exerciseReps" required placeholder="e.g. 12" class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 10px; border-radius: 8px;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea id="exerciseNotes" rows="3" placeholder="Special instructions..." class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 10px; border-radius: 8px;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                                <i class="fas fa-plus"></i> Add to Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Diet Tab Content -->
            <div class="modal-body" id="dietTabContent" style="display: none; padding: 24px;">
                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--dark-border); border-radius: 12px; padding: 24px;">
                    <h4 style="margin-bottom: 12px; color: #fff;">Package Nutrition & Diet</h4>
                    <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-bottom: 20px;">
                        <i class="fas fa-utensils"></i> Define the default nutrition plan for this package.
                    </p>
                    <div class="form-group">
                        <label>Nutrition Details</label>
                        <textarea id="packageModalDietInfo" rows="12" placeholder="Enter meal plan, nutrition tips, etc..." class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 15px; border-radius: 8px; resize: vertical; min-height: 250px;"></textarea>
                    </div>
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                        <button onclick="savePackageDetailsFromModal()" class="btn btn-primary" style="padding: 12px 32px;">
                            <i class="fas fa-save"></i> Save Nutrition Info
                        </button>
                    </div>
                </div>
            </div>

            <!-- Guidance Tab Content -->
            <div class="modal-body" id="guidanceTabContent" style="display: none; padding: 24px;">
                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--dark-border); border-radius: 12px; padding: 24px;">
                    <h4 style="margin-bottom: 12px; color: #fff;">Package Tips & Guidance</h4>
                    <p style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-bottom: 20px;">
                        <i class="fas fa-lightbulb"></i> Define the default tips and professional guidance for this package.
                    </p>
                    <div class="form-group">
                        <label>Guidance & Tips</label>
                        <textarea id="packageModalGuidanceInfo" rows="12" placeholder="Enter professional tips, recovery guidance, etc..." class="form-control" style="width: 100%; background: var(--dark-bg); border: 1px solid var(--dark-border); color: #fff; padding: 15px; border-radius: 8px; resize: vertical; min-height: 250px;"></textarea>
                    </div>
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                        <button onclick="savePackageDetailsFromModal()" class="btn btn-primary" style="padding: 12px 32px;">
                            <i class="fas fa-save"></i> Save Guidance Info
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary" onclick="closeExerciseModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Delete Package</h3>
                <button class="close-modal" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <p style="margin-bottom: 24px; color: var(--dark-text-secondary);">
                    Are you sure you want to delete <strong id="deletePackageName">this package</strong>? 
                    This action cannot be undone.
                </p>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Package Hub Modal (Preview) -->
    <div class="modal-overlay" id="bookingDetailsModal">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header" style="padding: 24px 32px; border-bottom: 1px solid var(--dark-border);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--dark-border);">
                        <i class="fas fa-file-invoice" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0;">Package Hub</h3>
                        <span id="detailRef" style="font-size: 0.75rem; color: var(--dark-text-secondary); font-weight: 700;">PREVIEW</span>
                    </div>
                </div>
                <button class="close-modal" onclick="closeBookingDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-tabs" style="display: flex; gap: 24px; border-bottom: 1px solid var(--dark-border); margin: 0 24px 20px; padding-bottom: 12px;">
                <button class="modal-tab-btn active" onclick="switchModalTab('info')" style="background: transparent; border: none; color: var(--dark-text-secondary); font-weight: 600; font-size: 0.9rem; padding: 8px 4px; cursor: pointer; transition: all 0.3s; position: relative;">Overview</button>
                <button class="modal-tab-btn" onclick="switchModalTab('plan')" style="background: transparent; border: none; color: var(--dark-text-secondary); font-weight: 600; font-size: 0.9rem; padding: 8px 4px; cursor: pointer; transition: all 0.3s; position: relative;">Exercise Plan</button>
                <button class="modal-tab-btn" onclick="switchModalTab('diet')" style="background: transparent; border: none; color: var(--dark-text-secondary); font-weight: 600; font-size: 0.9rem; padding: 8px 4px; cursor: pointer; transition: all 0.3s; position: relative;">Nutrition & Diet</button>
                <button class="modal-tab-btn" onclick="switchModalTab('tips')" style="background: transparent; border: none; color: var(--dark-text-secondary); font-weight: 600; font-size: 0.9rem; padding: 8px 4px; cursor: pointer; transition: all 0.3s; position: relative;">Tips & Guidance</button>
            </div>
            
            <div class="modal-body" style="padding: 0 32px 32px; max-height: 60vh; overflow-y: auto;">
                <!-- Info Tab -->
                <div id="modalTabInfo" class="modal-tab-content active">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--dark-text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Package Name</span>
                            <div class="detail-value" id="detailPackage" style="display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 600; color: var(--dark-text);">
                                <i class="fas fa-dumbbell" style="color: var(--primary);"></i>
                                <span>-</span>
                            </div>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--dark-text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Status</span>
                            <div id="detailStatus">
                                <span class="status-badge status-pending">Preview</span>
                            </div>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--dark-text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Start Date</span>
                            <div class="detail-value" id="detailDate" style="display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 600; color: var(--dark-text);">
                                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                                <span>Starts upon booking</span>
                            </div>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--dark-text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Standard Price</span>
                            <div class="detail-value" id="detailAmount" style="display: flex; align-items: center; gap: 10px; font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                                <i class="fas fa-tag"></i>
                                <span>₱0.00</span>
                            </div>
                        </div>
                    </div>

                    <div id="detailNotesSection" class="notes-section" style="margin-top: 24px; padding: 16px; background: rgba(245, 158, 11, 0.05); border-left: 4px solid var(--warning); border-radius: 8px;">
                        <span style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--warning); margin-bottom: 6px; text-transform: uppercase;">Package Description</span>
                        <p id="detailNotes" style="font-size: 0.9rem; color: var(--dark-text); line-height: 1.5; font-style: italic;">-</p>
                    </div>
                </div>

                <!-- Plan Tab -->
                <div id="modalTabPlan" class="modal-tab-content" style="display: none;">
                    <div id="modalPlanContent" style="padding-top: 20px;">
                        <!-- Exercises list -->
                    </div>
                </div>

                <!-- Diet Tab -->
                <div id="modalTabDiet" class="modal-tab-content" style="display: none;">
                    <div id="modalDietContent" style="padding-top: 20px;">
                        <!-- Diet preview -->
                    </div>
                </div>

                <!-- Tips Tab -->
                <div id="modalTabTips" class="modal-tab-content" style="display: none;">
                    <div id="modalTipsContent" style="padding-top: 20px;">
                        <!-- Tips preview -->
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 24px 32px; border-top: 1px solid var(--dark-border); display: flex; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeBookingDetailsModal()" style="padding: 10px 24px;">
                    <i class="fas fa-times" style="margin-right: 8px;"></i>
                    <span>Close Preview</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Packages Scripts -->
    <script src="../../assets/js/packages.js"></script>
</body>
</html>
