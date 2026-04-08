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

    <style>
        /* ── Category Filter Tabs ── */
        .category-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 0 20px 16px;
        }
        .cat-tab {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1.5px solid var(--dark-border);
            background: transparent;
            color: var(--dark-text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .cat-tab:hover { border-color: var(--primary); color: var(--primary); }
        .cat-tab.active { background: var(--primary); border-color: var(--primary); color: #000; }

        /* ── Exercise Cards ── */
        .exercise-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .exercise-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        }
        .exercise-card-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: var(--dark-bg);
        }
        .exercise-card-img-placeholder {
            width: 100%;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(212,175,55,0.08), rgba(212,175,55,0.02));
            color: var(--primary);
            font-size: 2.5rem;
        }
        .exercise-card-body { padding: 16px; flex: 1; display: flex; flex-direction: column; }
        .exercise-card-title { font-size: 1rem; font-weight: 700; color: var(--dark-text); margin-bottom: 6px; }
        .exercise-card-meta { font-size: 0.78rem; color: var(--dark-text-secondary); margin-bottom: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .exercise-card-desc { font-size: 0.82rem; color: var(--dark-text-secondary); line-height: 1.5; margin-bottom: 12px; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .exercise-card-actions { display: flex; gap: 8px; margin-top: auto; }
        .exercise-card-actions .btn { flex: 1; padding: 8px 10px; font-size: 0.8rem; border-radius: 10px; }

        /* ── Equipment Picker inside modal ── */
        .eq-picker-wrap { border: 1.5px solid var(--dark-border); border-radius: 12px; overflow: hidden; }
        .eq-search-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--dark-border); background: var(--dark-bg); }
        .eq-search-bar input { background: transparent; border: none; outline: none; color: var(--dark-text); font-size: 0.88rem; flex: 1; font-family: 'Inter', sans-serif; }
        .eq-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; padding: 12px; max-height: 240px; overflow-y: auto; }
        .eq-none-opt { padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--dark-border); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--dark-text-secondary); transition: all 0.18s; }
        .eq-none-opt:hover, .eq-none-opt.selected { border-color: var(--primary); color: var(--primary); background: rgba(212,175,55,0.07); }
        .eq-item { border: 1.5px solid var(--dark-border); border-radius: 10px; cursor: pointer; overflow: hidden; transition: all 0.18s; display: flex; flex-direction: column; }
        .eq-item:hover { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(212,175,55,0.15); }
        .eq-item.selected { border-color: var(--primary); background: rgba(212,175,55,0.07); box-shadow: 0 0 0 2px rgba(212,175,55,0.25); }
        .eq-item img { width: 100%; height: 70px; object-fit: cover; }
        .eq-item-no-img { width: 100%; height: 70px; display: flex; align-items: center; justify-content: center; background: rgba(212,175,55,0.05); color: var(--primary); font-size: 1.5rem; }
        .eq-item-label { padding: 6px 8px; font-size: 0.75rem; font-weight: 600; color: var(--dark-text); text-align: center; line-height: 1.2; }
        .eq-item.selected .eq-item-label { color: var(--primary); }
        .eq-selected-badge { margin-top: 8px; font-size: 0.82rem; color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 6px; }

        /* scrollbar */
        .eq-grid::-webkit-scrollbar { width: 5px; }
        .eq-grid::-webkit-scrollbar-track { background: transparent; }
        .eq-grid::-webkit-scrollbar-thumb { background: var(--dark-border); border-radius: 3px; }

        /* form groups */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 520px) { .form-row { grid-template-columns: 1fr; } }
    </style>
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
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
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
                <p><?php echo ($user['role'] === 'manager') ? 'Gym Manager' : 'Administrator'; ?></p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Exercise Management</h1>
                <p>Add and manage the master list of exercises available to packages</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
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
            <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
                <h3><i class="fas fa-running" style="color: var(--primary); margin-right: 8px;"></i>All Exercises <span id="exerciseCount" style="font-size: 0.85rem; font-weight: 400; color: var(--dark-text-secondary); margin-left: 8px;"></span></h3>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div class="search-box" style="width: 240px;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="exerciseSearch" placeholder="Search exercises..." oninput="filterExercises()">
                    </div>
                    <button class="btn btn-primary" id="addExerciseBtn" onclick="openAddExerciseModal()" style="white-space: nowrap;">
                        <i class="fas fa-plus"></i> Add Exercise
                    </button>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="category-tabs" id="categoryTabs">
                <button class="cat-tab active" data-cat="all" onclick="setCategoryFilter('all', this)">All</button>
                <button class="cat-tab" data-cat="Chest" onclick="setCategoryFilter('Chest', this)"><i class="fas fa-circle" style="font-size:0.6rem;"></i> Chest</button>
                <button class="cat-tab" data-cat="Back" onclick="setCategoryFilter('Back', this)">Back</button>
                <button class="cat-tab" data-cat="Legs" onclick="setCategoryFilter('Legs', this)">Legs</button>
                <button class="cat-tab" data-cat="Shoulders" onclick="setCategoryFilter('Shoulders', this)">Shoulders</button>
                <button class="cat-tab" data-cat="Arms" onclick="setCategoryFilter('Arms', this)">Arms</button>
                <button class="cat-tab" data-cat="Core" onclick="setCategoryFilter('Core', this)">Core</button>
                <button class="cat-tab" data-cat="Cardio" onclick="setCategoryFilter('Cardio', this)">Cardio</button>
                <button class="cat-tab" data-cat="Full Body" onclick="setCategoryFilter('Full Body', this)">Full Body</button>
            </div>
            
            <div id="exercisesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; padding: 0 20px 20px;">
                <!-- Populated by JavaScript -->
            </div>
            
            <!-- Loading skeleton -->
            <div id="exercisesLoading" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; padding: 0 20px 20px;">
                <?php for($i = 0; $i < 6; $i++): ?>
                <div style="background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 16px; overflow: hidden; animation: pulse 1.5s infinite;">
                    <div style="height: 160px; background: var(--dark-border);"></div>
                    <div style="padding: 16px;">
                        <div style="height: 14px; background: var(--dark-border); border-radius: 6px; margin-bottom: 8px; width: 70%;"></div>
                        <div style="height: 10px; background: var(--dark-border); border-radius: 6px; width: 40%;"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div id="noExercisesMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-running" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                <h3 style="margin-bottom: 8px;">No exercises found</h3>
                <p style="margin-bottom: 20px;">Click "Add Exercise" to create your first exercise.</p>
                <button class="btn btn-primary" onclick="openAddExerciseModal()">
                    <i class="fas fa-plus"></i> Add First Exercise
                </button>
            </div>
        </div>
    </main>

    <!-- ══════════════════════════════════════════
         Add / Edit Exercise Modal
    ══════════════════════════════════════════ -->
    <div class="modal-overlay" id="exerciseModal">
        <div class="modal" style="max-width: 680px; max-height: 92vh; overflow-y: auto;">
            <div class="modal-header" style="position: sticky; top: 0; z-index: 10; background: var(--dark-card);">
                <h3 id="exerciseModalTitle">
                    <i class="fas fa-plus-circle" style="color: var(--primary); margin-right: 8px;"></i>Add New Exercise
                </h3>
                <button class="close-modal" onclick="closeExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="exerciseForm" onsubmit="saveExercise(event)" enctype="multipart/form-data">
                    <input type="hidden" id="exerciseId">

                    <!-- Name & Category Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Exercise Name <span style="color: var(--warning);">*</span></label>
                            <input type="text" id="exerciseName" required placeholder="e.g., Bench Press" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Category <span style="color: var(--warning);">*</span></label>
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
                    </div>

                    <!-- Equipment Visual Picker -->
                    <div class="form-group">
                        <label><i class="fas fa-tools" style="color: var(--primary); margin-right: 6px;"></i>Equipment Used</label>
                        <input type="hidden" id="selectedEquipmentId" value="">

                        <div class="eq-picker-wrap">
                            <div class="eq-search-bar">
                                <i class="fas fa-search" style="color: var(--dark-text-secondary); font-size: 0.85rem;"></i>
                                <input type="text" id="eqSearchInput" placeholder="Search equipment..." oninput="filterEquipmentPicker()" autocomplete="off">
                                <span id="eqResultCount" style="font-size: 0.75rem; color: var(--dark-text-secondary); white-space: nowrap;"></span>
                            </div>
                            <div class="eq-grid" id="equipmentPickerGrid">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                        <div id="eqSelectedBadge" class="eq-selected-badge" style="display: none;">
                            <i class="fas fa-check-circle"></i>
                            <span id="eqSelectedName"></span>
                            <button type="button" onclick="clearEquipmentSelection()" style="background: none; border: none; color: var(--dark-text-secondary); cursor: pointer; font-size: 0.75rem; margin-left: 4px;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>

                    <!-- Exercise Image -->
                    <div class="form-group">
                        <label><i class="fas fa-image" style="color: var(--primary); margin-right: 6px;"></i>Exercise Image</label>
                        <div id="imageUploadArea" class="file-upload-area" style="padding: 24px; border: 2px dashed var(--dark-border); border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.3s;" onclick="document.getElementById('exerciseImageFile').click()" ondragover="event.preventDefault(); this.style.borderColor='var(--primary)'" ondragleave="this.style.borderColor='var(--dark-border)'" ondrop="handleImageDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px; display: block;"></i>
                            <p style="font-size: 0.9rem; margin-bottom: 4px; font-weight: 600;">Click or drag to upload</p>
                            <span style="font-size: 0.75rem; color: var(--dark-text-secondary);">JPG, PNG or WebP • Max 5MB</span>
                            <input type="file" id="exerciseImageFile" accept="image/*" style="display: none;" onchange="handleImagePreview(event)">
                        </div>
                        <div id="imagePreviewContainer" style="display: none; margin-top: 12px; position: relative;">
                            <img id="imagePreview" src="" style="width: 100%; height: 200px; object-fit: cover; border-radius: 12px; border: 1px solid var(--dark-border);">
                            <button type="button" onclick="removeImagePreview()" style="position: absolute; top: 10px; right: 10px; background: rgba(239,68,68,0.9); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" id="exerciseImageUrl">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label><i class="fas fa-align-left" style="color: var(--primary); margin-right: 6px;"></i>Description</label>
                        <textarea id="exerciseDescription" rows="2" placeholder="Briefly describe what this exercise targets..."></textarea>
                    </div>

                    <!-- Instructions -->
                    <div class="form-group">
                        <label><i class="fas fa-list-ol" style="color: var(--primary); margin-right: 6px;"></i>Instructions</label>
                        <textarea id="exerciseInstructions" rows="4" placeholder="Step 1: ...&#10;Step 2: ...&#10;Step 3: ..."></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeExerciseModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveExerciseBtn">
                            <i class="fas fa-save"></i> Save Exercise
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteExerciseModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h3 style="color: #ef4444;"><i class="fas fa-trash" style="margin-right: 8px;"></i>Delete Exercise</h3>
                <button class="close-modal" onclick="closeDeleteExerciseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 28px; text-align: center;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(239,68,68,0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444;"></i>
                </div>
                <h4 style="margin-bottom: 10px;">Are you sure?</h4>
                <p>Delete <strong id="deleteExerciseName" style="color: var(--primary);">this exercise</strong>?</p>
                <p style="font-size: 0.82rem; color: var(--dark-text-secondary); margin-top: 8px;">
                    It will also be removed from any packages it's currently assigned to.
                </p>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; display: flex; gap: 12px;">
                <button class="btn btn-secondary" style="flex: 1;" onclick="closeDeleteExerciseModal()">Cancel</button>
                <button class="btn" style="flex: 1; background: #ef4444; color: white;" onclick="confirmDeleteExercise()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/theme.js"></script>
    <script src="../../assets/js/exercises.js?v=2.0"></script>
</body>
</html>
