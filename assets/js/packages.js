// Package Hub Preview Logic
let modalCalendar = null;

async function previewPackageHub(packageId) {
    const pkg = allPackages.find(p => p.id === packageId);
    if (!pkg) return;

    const modal = document.getElementById('bookingDetailsModal');
    if (!modal) return;

    // Set modal to "Preview" mode
    document.getElementById('detailRef').textContent = `PREVIEW: ${pkg.name}`;
    document.getElementById('detailPackage').querySelector('span').textContent = pkg.name;
    document.getElementById('detailAmount').querySelector('span').textContent = pkg.price;
    
    // Notes
    document.getElementById('detailNotes').textContent = pkg.description || 'No additional details.';
    
    // Load Tabs Content for Preview
    switchModalTab('info');
    
    // 1. Exercise Plan Preview
    const planContent = document.getElementById('modalPlanContent');
    planContent.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading default plan...</div>';
    
    try {
        const response = await fetch(`../../api/packages/get-exercises.php?package_id=${packageId}`);
        const data = await response.json();
        if (data.success && data.data.length > 0) {
            planContent.innerHTML = data.data.map(ex => `
                <div class="exercise-item" style="display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid var(--dark-border); align-items: center;">
                    ${ex.image_url ? 
                        `<img src="${ex.image_url}" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #1a1a1a;">` :
                        `<div style="width: 80px; height: 80px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; border: 1px dashed var(--dark-border);"><i class="fas fa-image" style="opacity: 0.2;"></i></div>`
                    }
                    <div style="flex: 1;">
                        <h4 style="margin-bottom: 4px; font-weight: 700; color: white;">${ex.name}</h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--primary);">
                            <span><i class="fas fa-redo"></i> ${ex.sets} Sets</span>
                            <span><i class="fas fa-running"></i> ${ex.reps} Reps</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            planContent.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">This package has general gym access.</div>';
        }
    } catch (e) {
        planContent.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load plan.</div>';
    }

    // 2. Calendar Preview (Sample)
    const calendarEl = document.getElementById('modalCalendar');
    if (modalCalendar) modalCalendar.destroy();
    
    modalCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
        themeSystem: 'standard',
        events: [
            { title: 'Workout A', start: new Date().toISOString().split('T')[0], color: '#3b82f6' },
            { title: 'Workout B', start: new Date(Date.now() + 86400000).toISOString().split('T')[0], color: '#3b82f6' },
            { title: 'Rest Day', start: new Date(Date.now() + 172800000).toISOString().split('T')[0], color: '#ef4444' }
        ]
    });
    modalCalendar.render();

    // 3. Diet & Tips (Sample for Preview)
    document.getElementById('modalDietContent').innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
            <i class="fas fa-utensils" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 15px; display: block;"></i>
            <h4 style="color: white; margin-bottom: 8px;">Personalized Nutrition</h4>
            <p style="font-size: 0.9rem; line-height: 1.6;">Once booked, the coach will provide a daily meal plan tailored to the goal of <strong>${pkg.goal || 'General Fitness'}</strong>.</p>
        </div>
    `;
    
    document.getElementById('modalTipsContent').innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
            <i class="fas fa-lightbulb" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 15px; display: block;"></i>
            <h4 style="color: white; margin-bottom: 8px;">Professional Guidance</h4>
            <p style="font-size: 0.9rem; line-height: 1.6;">Assigned trainers share daily tips on form, hydration, and recovery through the user portal.</p>
        </div>
    `;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function switchModalTab(tab) {
    // Hide all contents
    document.querySelectorAll('.modal-tab-content').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
    
    // Show selected
    const contentId = 'modalTab' + tab.charAt(0).toUpperCase() + tab.slice(1);
    const content = document.getElementById(contentId);
    if (content) content.style.display = 'block';
    
    // Update button
    const btn = Array.from(document.querySelectorAll('.modal-tab-btn')).find(b => b.textContent.toLowerCase().includes(tab));
    if (btn) btn.classList.add('active');

    // Re-render calendar if tab is calendar
    if (tab === 'calendar' && modalCalendar) {
        setTimeout(() => modalCalendar.render(), 100);
    }
}

function closeBookingDetailsModal() {
    const modal = document.getElementById('bookingDetailsModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// All packages data
let allPackages = [];
let lastPackagesJSON = '';
let lastStatsJSON = '';
let currentEditingPackage = null;
let packageToDelete = null;
let trainersList = [];

async function loadTrainers() {
    try {
        const response = await fetch('../../api/trainers/get-all.php');
        const data = await response.json();
        if (data.success) {
            trainersList = data.data.filter(t => t.is_active);
            renderTrainerCheckboxes();
        }
    } catch (error) {
        console.error('Error loading trainers:', error);
    }
}

function renderTrainerCheckboxes() {
    const list = document.getElementById('packageTrainersList');
    if (!list) return;
    
    if (trainersList.length === 0) {
        list.innerHTML = '<p style="font-size: 0.8rem; color: #ef4444; grid-column: 1/-1;">No active trainers found. Please add trainers first.</p>';
        return;
    }
    
    list.innerHTML = trainersList.map(trainer => `
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; user-select: none;">
            <input type="checkbox" name="package_trainer" value="${trainer.id}" style="width: 16px; height: 16px;">
            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${trainer.name}">${trainer.name}</span>
        </label>
    `).join('');
}

function toggleTrainerSelection() {
    const isAssisted = document.getElementById('isTrainerAssisted').checked;
    const group = document.getElementById('trainerSelectionGroup');
    if (group) {
        group.style.display = isAssisted ? 'block' : 'none';
        
        if (isAssisted && trainersList.length === 0) {
            loadTrainers();
        }
    }
}

function setTrainerCheckboxes(selectedIds) {
    const checkboxes = document.querySelectorAll('input[name="package_trainer"]');
    checkboxes.forEach(cb => {
        cb.checked = selectedIds.includes(parseInt(cb.value));
    });
}

// Manage Exercises Logic
let currentPackageId = null;

async function openExerciseModal(packageId, packageName) {
    currentPackageId = packageId;
    document.getElementById('exerciseModalSubtitle').textContent = `Package: ${packageName}`;
    document.getElementById('exerciseModal').classList.add('active');
    
    await loadAllExercises();
    await loadPackageExercises(packageId);
}

function closeExerciseModal() {
    document.getElementById('exerciseModal').classList.remove('active');
    currentPackageId = null;
}

async function loadAllExercises() {
    try {
        const response = await fetch('../../api/exercises/get-all.php');
        const data = await response.json();
        
        const select = document.getElementById('exerciseSelect');
        select.innerHTML = '<option value="">Choose an exercise...</option>';
        
        if (data.success) {
            data.data.forEach(ex => {
                const option = document.createElement('option');
                option.value = ex.id;
                option.textContent = `${ex.name} (${ex.category})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading exercises:', error);
    }
}

async function loadPackageExercises(packageId) {
    const list = document.getElementById('packageExercisesList');
    list.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    try {
        const response = await fetch(`../../api/packages/get-exercises.php?package_id=${packageId}`);
        const data = await response.json();
        
        list.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(ex => {
                const item = document.createElement('div');
                item.style.cssText = `
                    background: rgba(255,255,255,0.03);
                    border: 1px solid var(--dark-border);
                    border-radius: 8px;
                    padding: 12px;
                    margin-bottom: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                `;
                
                item.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        ${ex.image_url ? `
                            <img src="${ex.image_url}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--dark-border);">
                        ` : `
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 1px dashed var(--dark-border);">
                                <i class="fas fa-image" style="font-size: 0.8rem; opacity: 0.5;"></i>
                            </div>
                        `}
                        <div>
                            <div style="font-weight: 700; color: var(--primary);">${ex.name}</div>
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">
                                ${ex.sets} Sets × ${ex.reps}
                                ${ex.notes ? `<div style="font-style: italic; font-size: 0.75rem; margin-top: 4px;">Note: ${ex.notes}</div>` : ''}
                            </div>
                        </div>
                    </div>
                    <button class="icon-btn danger" onclick="removeExerciseFromPackage('${ex.id}')" title="Remove from plan">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                list.appendChild(item);
            });
        } else {
            list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--dark-text-secondary);">No exercises in this plan yet.</div>';
        }
    } catch (error) {
        console.error('Error loading package exercises:', error);
        list.innerHTML = '<div style="text-align: center; padding: 20px; color: #ef4444;">Error loading plan.</div>';
    }
}

async function addExerciseToPackage(event) {
    event.preventDefault();
    
    const exerciseId = document.getElementById('exerciseSelect').value;
    const sets = document.getElementById('exerciseSets').value;
    const reps = document.getElementById('exerciseReps').value;
    const notes = document.getElementById('exerciseNotes').value;
    
    if (!exerciseId) return;
    
    const formData = new FormData();
    formData.append('package_id', currentPackageId);
    formData.append('exercise_id', exerciseId);
    formData.append('sets', sets);
    formData.append('reps', reps);
    formData.append('notes', notes);
    
    try {
        const response = await fetch('../../api/packages/add-exercise.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('Exercise added!', 'success');
            document.getElementById('addExerciseForm').reset();
            loadPackageExercises(currentPackageId);
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error adding exercise:', error);
        showNotification('Error adding exercise', 'warning');
    }
}

async function removeExerciseFromPackage(exerciseId) {
    if (!confirm('Remove this exercise from the package plan?')) return;
    
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
            showNotification('Exercise removed!', 'success');
            loadPackageExercises(currentPackageId);
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error removing exercise:', error);
        showNotification('Error removing exercise', 'warning');
    }
}


// Load packages from database
async function loadPackages() {
    try {
        const response = await fetch('../../api/packages/get-all.php');
        
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Format prices to include ₱ if they are numbers
            const formattedData = data.data.map(pkg => ({
                ...pkg,
                price: typeof pkg.price === 'number' ? `₱${pkg.price.toLocaleString()}` : pkg.price
            }));
            
            const newPackagesJSON = JSON.stringify(formattedData);
            const hasChanged = newPackagesJSON !== lastPackagesJSON;
            
            if (hasChanged) {
                allPackages = formattedData;
                lastPackagesJSON = newPackagesJSON;
                return true; // Data changed
            }
            return false; // Data unchanged
        } else {
            console.error('API Error:', data.message);
            showNotification(data.message || 'Failed to load packages', 'warning');
            allPackages = [];
            lastPackagesJSON = '';
            return true;
        }
    } catch (error) {
        console.error('Fetch Error:', error);
        showNotification('Connection error: Could not reach the server', 'warning');
        allPackages = [];
        lastPackagesJSON = '';
        return true;
    }
}

// Save packages to database
async function savePackageToDB(packageData) {
    try {
        const url = packageData.id ? 
            '../../api/packages/update.php' : 
            '../../api/packages/create.php';
            
        const method = packageData.id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(packageData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Network error saving package:', error);
        throw error;
    }
}

// Delete package from database
async function deletePackageFromDB(packageId) {
    try {
        const response = await fetch('../../api/packages/delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: packageId })
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Network error deleting package:', error);
        throw error;
    }
}

// Update packages data across the app
function updatePackagesInApp() {
    // This will be used by other parts of the app to get current packages
    // The packages are stored in localStorage with key 'gymPackages'
}

// Get package statistics
async function getPackageStats() {
    try {
        const response = await fetch('../../api/packages/get-stats.php');
        const data = await response.json();
        
        if (data.success) {
            const newStatsJSON = JSON.stringify(data.data);
            const hasChanged = newStatsJSON !== lastStatsJSON;
            lastStatsJSON = newStatsJSON;
            return { data: data.data, changed: hasChanged };
        } else {
            console.error('Error loading package stats:', data.message);
            const defaultStats = {
                totalBookings: 0,
                totalRevenue: 0,
                packageBookings: {},
                packageRevenue: {},
                popularPackage: '-',
                pendingBookings: 0
            };
            return { data: defaultStats, changed: true };
        }
    } catch (error) {
        console.error('Network error loading package stats:', error);
        const defaultStats = {
            totalBookings: 0,
            totalRevenue: 0,
            packageBookings: {},
            packageRevenue: {},
            popularPackage: '-',
            pendingBookings: 0
        };
        return { data: defaultStats, changed: true };
    }
}

// Populate packages grid
async function populatePackagesGrid(forcedStats = null) {
    const grid = document.getElementById('packagesGrid');
    const noPackagesMessage = document.getElementById('noPackagesMessage');
    
    if (!grid) {
        console.error('packagesGrid element not found');
        return;
    }
    
    if (!noPackagesMessage) {
        console.error('noPackagesMessage element not found');
        return;
    }
    
    grid.innerHTML = '';
    
    if (allPackages.length === 0) {
        grid.style.display = 'none';
        noPackagesMessage.style.display = 'block';
        const showingCount = document.getElementById('showingCount');
        if (showingCount) {
            showingCount.textContent = '0';
        }
        return;
    }
    
    grid.style.display = 'grid';
    noPackagesMessage.style.display = 'none';
    
    const statsResult = forcedStats || await getPackageStats();
    const stats = statsResult.data;
    
    allPackages.forEach(pkg => {
        const packageCard = document.createElement('div');
        packageCard.className = 'package-card';
        packageCard.style.cssText = `
            position: relative;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            height: 100%;
        `;
        
        const bookingsCount = stats.packageBookings[pkg.name] || 0;
        const revenue = stats.packageRevenue[pkg.name] || 0;
        
        // Find assigned trainer names
        let trainerNames = [];
        if (pkg.trainer_ids) {
            const trainerIds = Array.isArray(pkg.trainer_ids) ? pkg.trainer_ids : 
                              (typeof pkg.trainer_ids === 'string' ? JSON.parse(pkg.trainer_ids) : []);
            
            trainerNames = trainersList
                .filter(t => trainerIds.includes(t.id))
                .map(t => t.name);
        }
        
        packageCard.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div style="flex: 1; padding-right: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <span class="package-tag" style="font-size: 0.65rem; padding: 4px 10px; background: var(--primary); color: #000; font-weight: 800; border-radius: 4px; text-transform: uppercase;">${pkg.tag || 'Standard'}</span>
                        <span style="font-size: 0.65rem; padding: 4px 10px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2); font-weight: 700;">
                            <i class="fas fa-bullseye"></i> ${pkg.goal || 'General Fitness'}
                        </span>
                    </div>
                    <h3 style="margin: 0; color: #fff; font-size: 1.25rem; font-weight: 800; line-height: 1.2;">${pkg.name}</h3>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-bottom: 2px;">Price</div>
                    <div style="font-weight: 900; font-size: 1.5rem; color: var(--primary);">${pkg.price}</div>
                </div>
            </div>

            <div style="flex: 1; margin-bottom: 24px;">
                <p style="color: var(--dark-text-secondary); font-size: 0.9rem; margin-bottom: 16px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    ${pkg.description || 'Full gym access with all facilities'}
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 12px; background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; color: var(--dark-text-secondary); font-size: 0.85rem;">
                        <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span style="font-weight: 600;">${pkg.duration}</span>
                    </div>
                    
                    ${pkg.is_trainer_assisted ? `
                    <div style="display: flex; align-items: center; gap: 10px; color: var(--dark-text-secondary); font-size: 0.85rem;">
                        <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span style="font-weight: 600; color: #fff;">${trainerNames.length > 0 ? trainerNames.join(', ') : 'No Trainers Assigned'}</span>
                    </div>
                    ` : `
                    <div style="display: flex; align-items: center; gap: 10px; color: var(--dark-text-secondary); font-size: 0.85rem; opacity: 0.5;">
                        <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <span>Self-Guided Session</span>
                    </div>
                    `}
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                <button class="btn btn-secondary" style="padding: 10px; font-size: 0.8rem; background: rgba(255,255,255,0.05);" onclick="previewPackageHub(${pkg.id})">
                    <i class="fas fa-th-large"></i> Hub
                </button>
                <button class="btn btn-secondary" style="padding: 10px; font-size: 0.8rem; background: rgba(255,255,255,0.05);" onclick="openExerciseModal(${pkg.id}, '${pkg.name}')">
                    <i class="fas fa-list-ul"></i> Plan
                </button>
                <button class="btn btn-secondary" style="padding: 10px; font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6;" onclick="editPackage(${pkg.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-secondary" style="padding: 10px; font-size: 0.8rem; background: rgba(239, 68, 68, 0.1); color: #ef4444;" onclick="deletePackage(${pkg.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        `;
        
        grid.appendChild(packageCard);
    });
    
    document.getElementById('showingCount').textContent = allPackages.length;
}

// Update stats
async function updateStats(forcedStats = null) {
    const statsResult = forcedStats || await getPackageStats();
    const stats = statsResult.data;
    
    document.getElementById('totalPackages').textContent = allPackages.length;
    document.getElementById('totalBookings').textContent = stats.totalBookings;
    document.getElementById('totalRevenue').textContent = `₱${Math.round(stats.totalRevenue).toLocaleString()}`;
    document.getElementById('popularPackage').textContent = stats.popularPackage;

    // Update trends (simplified for UI)
    const trends = ['totalPackagesTrend', 'totalBookingsTrend', 'totalRevenueTrend', 'popularPackageTrend'];
    trends.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '+12%';
    });
    
    // Update pending bookings badge
    const pendingCount = stats.pendingBookings || 0;
    
    const bookingsBadge = document.getElementById('bookingsBadge');
    if (bookingsBadge) {
        bookingsBadge.textContent = pendingCount || '';
    }
    
    const notificationBadge = document.getElementById('notificationBadge');
    if (notificationBadge) {
        notificationBadge.textContent = pendingCount || '';
    }
}

// Open add package modal
function openAddPackageModal() {
    try {
        currentEditingPackage = null;
        const modal = document.getElementById('packageModal');
        const modalTitle = document.getElementById('modalTitle');
        const packageForm = document.getElementById('packageForm');
        
        if (!modal) {
            console.error('Package modal not found');
            showNotification('Error: Modal not found. Please refresh the page.', 'warning');
            return;
        }
        
        if (modalTitle) {
            modalTitle.textContent = 'Add New Package';
        }
        
        if (packageForm) {
            packageForm.reset();
        }
        
        const packageGoal = document.getElementById('packageGoal');
        if (packageGoal) {
            packageGoal.value = 'General Fitness';
        }
        
        const isTrainerAssisted = document.getElementById('isTrainerAssisted');
        const group = document.getElementById('trainerSelectionGroup');
        if (isTrainerAssisted) {
            isTrainerAssisted.checked = false;
        }
        if (group) {
            group.style.display = 'none';
        }
        
        // Clear trainer checkboxes
        const checkboxes = document.querySelectorAll('input[name="package_trainer"]');
        checkboxes.forEach(cb => cb.checked = false);
        
        modal.classList.add('active');
    } catch (error) {
        console.error('Error opening package modal:', error);
        showNotification('Error opening modal. Please refresh the page.', 'warning');
    }
}

// Edit package
function editPackage(packageId) {
    const pkg = allPackages.find(p => p.id === packageId);
    if (!pkg) {
        showNotification('Package not found', 'warning');
        return;
    }
    
    currentEditingPackage = pkg;
    document.getElementById('modalTitle').textContent = 'Edit Package';
    document.getElementById('packageName').value = pkg.name;
    document.getElementById('packageDuration').value = pkg.duration;
    document.getElementById('packagePrice').value = pkg.price;
    document.getElementById('packageTag').value = pkg.tag || '';
    document.getElementById('packageDescription').value = pkg.description || '';
    
    const packageGoal = document.getElementById('packageGoal');
    if (packageGoal) {
        packageGoal.value = pkg.goal || 'General Fitness';
    }
    
    const isTrainerAssisted = document.getElementById('isTrainerAssisted');
    const group = document.getElementById('trainerSelectionGroup');
    const isAssisted = pkg.is_trainer_assisted || false;
    
    if (isTrainerAssisted) {
        isTrainerAssisted.checked = isAssisted;
    }
    if (group) {
        group.style.display = isAssisted ? 'block' : 'none';
    }
    
    // Set trainer checkboxes
    const trainerIds = Array.isArray(pkg.trainer_ids) ? pkg.trainer_ids : 
                      (pkg.trainer_ids ? JSON.parse(pkg.trainer_ids) : []);
    
    if (trainersList.length === 0) {
        loadTrainers().then(() => setTrainerCheckboxes(trainerIds));
    } else {
        setTrainerCheckboxes(trainerIds);
    }
    
    document.getElementById('packageModal').classList.add('active');
}

// Save package
async function savePackage(event) {
    event.preventDefault();
    
    const name = document.getElementById('packageName').value.trim();
    const duration = document.getElementById('packageDuration').value.trim();
    const price = document.getElementById('packagePrice').value.trim();
    const tag = document.getElementById('packageTag').value;
    const description = document.getElementById('packageDescription').value.trim();
    const goal = document.getElementById('packageGoal') ? document.getElementById('packageGoal').value : 'General Fitness';
    const isTrainerAssisted = document.getElementById('isTrainerAssisted') ? document.getElementById('isTrainerAssisted').checked : false;
    
    const selectedTrainers = [];
    if (isTrainerAssisted) {
        document.querySelectorAll('input[name="package_trainer"]:checked').forEach(cb => {
            selectedTrainers.push(parseInt(cb.value));
        });
        
        if (selectedTrainers.length === 0) {
            showNotification('Please select at least one trainer for this package', 'warning');
            return;
        }
    }

    // Validate price format
    if (!price.startsWith('₱')) {
        showNotification('Price must start with ₱', 'warning');
        return;
    }
    
    try {
        const packageData = {
            name,
            duration,
            price,
            tag: tag || '',
            description,
            goal,
            is_trainer_assisted: isTrainerAssisted,
            trainer_ids: selectedTrainers
        };
        
        // Add ID if editing existing package
        if (currentEditingPackage) {
            packageData.id = currentEditingPackage.id;
        }
        
        const result = await savePackageToDB(packageData);
        
        if (result.success) {
            showNotification(result.message, 'success');
            await loadPackages(); // Reload from database
            populatePackagesGrid();
            updateStats();
            closePackageModal();
        } else {
            showNotification(result.message, 'warning');
        }
    } catch (error) {
        console.error('Error saving package:', error);
        showNotification('Error saving package. Please try again.', 'warning');
    }
}

// Delete package
function deletePackage(packageId) {
    const pkg = allPackages.find(p => p.id === packageId);
    if (!pkg) {
        showNotification('Package not found', 'warning');
        return;
    }
    
    packageToDelete = pkg;
    document.getElementById('deletePackageName').textContent = pkg.name;
    document.getElementById('deleteModal').classList.add('active');
}

// Confirm delete
async function confirmDelete() {
    if (!packageToDelete) return;
    
    try {
        const result = await deletePackageFromDB(packageToDelete.id);
        
        if (result.success) {
            showNotification(result.message, 'success');
            await loadPackages(); // Reload from database
            populatePackagesGrid();
            updateStats();
        } else {
            showNotification(result.message, 'warning');
        }
    } catch (error) {
        console.error('Error deleting package:', error);
        showNotification('Error deleting package. Please try again.', 'warning');
    }
    
    closeDeleteModal();
}

// Close package modal
function closePackageModal() {
    try {
        const modal = document.getElementById('packageModal');
        if (modal) {
            modal.classList.remove('active');
        }
        currentEditingPackage = null;
        const packageForm = document.getElementById('packageForm');
        if (packageForm) {
            packageForm.reset();
        }
    } catch (error) {
        console.error('Error closing package modal:', error);
    }
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    packageToDelete = null;
}

// Function to automatically add ₱ symbol
function prependPesoSymbol() {
    const priceField = document.getElementById('packagePrice');
    if (priceField.value === '' || !priceField.value.startsWith('₱')) {
        priceField.value = '₱' + priceField.value;
        // Move cursor to end of text
        priceField.setSelectionRange(priceField.value.length, priceField.value.length);
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 32px;
        background: ${type === 'success' ? '#22c55e' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        font-weight: 600;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Handle logout
async function handleLogout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }
    
    try {
        // Call logout API to clear PHP session
        const response = await fetch('../../api/auth/logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        // Clear localStorage
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        
        // Redirect to login page
        window.location.href = '../../index.php';
    } catch (error) {
        console.error('Logout error:', error);
        // Still clear localStorage and redirect even if API fails
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        window.location.href = '../../index.php';
    }
}

// Initialize page
async function initPage() {
    console.log('Packages page initializing...');
    
    try {
        await loadTrainers(); // Load trainers first
        const changed = await loadPackages();
        console.log('Packages loaded:', allPackages.length, allPackages);
        
        await populatePackagesGrid();
        console.log('Packages grid populated');
        
        await updateStats();
        console.log('Stats updated');
    } catch (error) {
        console.error('Error initializing packages page:', error);
        showNotification('Error loading packages. Please refresh the page.', 'warning');
    }
    
    // Add exercise form listener
    const form = document.getElementById('addExerciseForm');
    if (form) {
        form.addEventListener('submit', addExerciseToPackage);
    }
    
    // Close modals on outside click
    document.getElementById('packageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePackageModal();
        }
    });
    
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    const isTrainerAssisted = document.getElementById('isTrainerAssisted');
    if (isTrainerAssisted) {
        isTrainerAssisted.addEventListener('change', toggleTrainerSelection);
    }
    
    // Notification button
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', async function() {
            const statsResult = await getPackageStats();
            const stats = statsResult.data;
            const pendingCount = stats.pendingBookings || 0;
            showNotification(`You have ${pendingCount} pending booking${pendingCount !== 1 ? 's' : ''} to verify`, 'info');
        });
    }
    
    // Refresh packages every 3 seconds - ONLY if data changed
    setInterval(async () => {
        try {
            const packagesChanged = await loadPackages();
            const statsResult = await getPackageStats();
            
            if (packagesChanged || statsResult.changed) {
                console.log('Data changed, re-rendering packages grid...');
                await populatePackagesGrid(statsResult);
                await updateStats(statsResult);
            }
        } catch (error) {
            console.error('Error refreshing packages:', error);
        }
    }, 3000);
}

// Mobile menu toggle functionality
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const sidebar = document.querySelector('.sidebar');

if (mobileMenuToggle && sidebar) {
    mobileMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        
        // Change icon based on state
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && 
            e.target !== mobileMenuToggle && 
            !mobileMenuToggle.contains(e.target) &&
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
}

// Initialize the page
document.addEventListener('DOMContentLoaded', initPage);
