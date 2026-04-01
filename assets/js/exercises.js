// Exercise Management JS — v2.0
'use strict';

let allExercises  = [];
let allEquipment  = [];
let exerciseToDelete = null;
let activeCategoryFilter = 'all';

// ─────────────────────────────────────────────
//  Boot
// ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadData);

async function loadData() {
    showLoading(true);
    try {
        const [exRes, eqRes] = await Promise.all([
            fetch('../../api/exercises/get-all.php'),
            fetch('../../api/equipment/get-all.php')
        ]);

        const exData = await exRes.json();
        const eqData = await eqRes.json();

        if (exData.success) allExercises = exData.data || [];
        if (eqData.success) allEquipment = eqData.data || [];

    } catch (err) {
        console.error('Error loading data:', err);
        showNotification('Failed to load data. Please refresh.', 'warning');
    }

    buildEquipmentPickerGrid(allEquipment);
    renderExercisesGrid();
    showLoading(false);
}

function showLoading(state) {
    const loading = document.getElementById('exercisesLoading');
    const grid    = document.getElementById('exercisesGrid');
    if (loading) loading.style.display = state ? 'grid' : 'none';
    if (grid)    grid.style.display    = state ? 'none' : 'grid';
}

// ─────────────────────────────────────────────
//  Category Filter
// ─────────────────────────────────────────────
function setCategoryFilter(cat, btn) {
    activeCategoryFilter = cat;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    renderExercisesGrid();
}

// ─────────────────────────────────────────────
//  Exercises Grid
// ─────────────────────────────────────────────
function renderExercisesGrid() {
    const query = (document.getElementById('exerciseSearch')?.value || '').toLowerCase();

    let list = allExercises;

    // category filter
    if (activeCategoryFilter !== 'all') {
        list = list.filter(ex => ex.category === activeCategoryFilter);
    }

    // text search
    if (query) {
        list = list.filter(ex =>
            ex.name.toLowerCase().includes(query) ||
            ex.category.toLowerCase().includes(query) ||
            (ex.description || '').toLowerCase().includes(query)
        );
    }

    const grid  = document.getElementById('exercisesGrid');
    const noMsg = document.getElementById('noExercisesMessage');
    const count = document.getElementById('exerciseCount');

    if (!grid) return;
    grid.innerHTML = '';

    if (count) count.textContent = `(${list.length})`;

    if (list.length === 0) {
        grid.style.display = 'none';
        noMsg.style.display = 'block';
        return;
    }

    grid.style.display = 'grid';
    noMsg.style.display = 'none';

    list.forEach(ex => {
        const equipName = getEquipmentName(ex.equipment_id);

        const imgHtml = ex.image_url
            ? `<img class="exercise-card-img" src="${ex.image_url}" alt="${escapeHtml(ex.name)}" onerror="this.parentElement.innerHTML='<div class=exercise-card-img-placeholder><i class=fas fa-dumbbell></i></div>'">`
            : `<div class="exercise-card-img-placeholder"><i class="fas fa-dumbbell"></i></div>`;

        const catColor = getCategoryColor(ex.category);

        const card = document.createElement('div');
        card.className = 'exercise-card';
        card.innerHTML = `
            <div style="position: relative;">
                ${imgHtml}
                <span style="position: absolute; top: 10px; left: 10px; background: ${catColor}22; color: ${catColor}; border: 1px solid ${catColor}55; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">
                    ${escapeHtml(ex.category)}
                </span>
                <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.65); color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem;">
                    <i class="fas fa-link"></i> ${ex.package_count} pkg${ex.package_count !== 1 ? 's' : ''}
                </span>
            </div>
            <div class="exercise-card-body">
                <div class="exercise-card-title">${escapeHtml(ex.name)}</div>
                <div class="exercise-card-meta">
                    ${equipName ? `<span><i class="fas fa-tools" style="color: var(--primary);"></i> ${escapeHtml(equipName)}</span>` : '<span style="opacity:0.5"><i class="fas fa-hand-paper"></i> No equipment</span>'}
                </div>
                ${ex.description ? `<div class="exercise-card-desc">${escapeHtml(ex.description)}</div>` : ''}
                <div class="exercise-card-actions">
                    <button class="btn btn-secondary" onclick="editExercise(${ex.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn" style="background: rgba(239,68,68,0.1); color: #ef4444;" onclick="deleteExercise(${ex.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function filterExercises() {
    renderExercisesGrid();
}

function getEquipmentName(id) {
    if (!id) return null;
    const eq = allEquipment.find(e => e.id === parseInt(id));
    return eq ? eq.name : null;
}

function getCategoryColor(cat) {
    const colors = {
        Chest: '#f59e0b', Back: '#3b82f6', Legs: '#10b981',
        Shoulders: '#8b5cf6', Arms: '#ec4899', Core: '#f97316',
        Cardio: '#ef4444', 'Full Body': '#d4af37'
    };
    return colors[cat] || '#d4af37';
}

// ─────────────────────────────────────────────
//  Equipment Visual Picker
// ─────────────────────────────────────────────
function buildEquipmentPickerGrid(items) {
    const grid = document.getElementById('equipmentPickerGrid');
    if (!grid) return;

    grid.innerHTML = '';

    // "None" option
    const noneEl = document.createElement('div');
    noneEl.className = 'eq-none-opt selected'; // default selected
    noneEl.id = 'eqNoneOpt';
    noneEl.style.gridColumn = '1 / -1';
    noneEl.innerHTML = `<i class="fas fa-hand-paper"></i> No specific equipment (bodyweight / free)`;
    noneEl.onclick = () => selectEquipment(null, null, noneEl);
    grid.appendChild(noneEl);

    items.forEach(eq => {
        const el = document.createElement('div');
        el.className = 'eq-item';
        el.dataset.eqId   = eq.id;
        el.dataset.eqName = eq.name;

        const imgHtml = eq.image_url
            ? `<img src="${eq.image_url}" alt="${escapeHtml(eq.name)}" onerror="this.outerHTML='<div class=eq-item-no-img><i class=fas\\ fa-tools></i></div>'">`
            : `<div class="eq-item-no-img"><i class="fas fa-tools"></i></div>`;

        el.innerHTML = `${imgHtml}<div class="eq-item-label">${escapeHtml(eq.name)}</div>`;
        el.onclick = () => selectEquipment(eq.id, eq.name, el);
        grid.appendChild(el);
    });

    updateEqCount(items.length);
}

function selectEquipment(id, name, clickedEl) {
    // deselect all
    document.querySelectorAll('.eq-item.selected, .eq-none-opt.selected').forEach(el => el.classList.remove('selected'));
    clickedEl.classList.add('selected');

    document.getElementById('selectedEquipmentId').value = id || '';

    const badge   = document.getElementById('eqSelectedBadge');
    const badgeTxt = document.getElementById('eqSelectedName');
    if (id) {
        badge.style.display = 'flex';
        badgeTxt.textContent  = name;
    } else {
        badge.style.display = 'none';
    }
}

function clearEquipmentSelection() {
    const noneEl = document.getElementById('eqNoneOpt');
    if (noneEl) selectEquipment(null, null, noneEl);
}

function filterEquipmentPicker() {
    const q    = document.getElementById('eqSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('#equipmentPickerGrid .eq-item');
    const noneOpt = document.getElementById('eqNoneOpt');
    let visible = 0;

    items.forEach(el => {
        const name = (el.dataset.eqName || '').toLowerCase();
        const show = name.includes(q);
        el.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // show "none" option only when search is empty
    if (noneOpt) noneOpt.style.display = q ? 'none' : '';

    updateEqCount(visible);
}

function updateEqCount(n) {
    const span = document.getElementById('eqResultCount');
    if (span) span.textContent = n > 0 ? `${n} item${n !== 1 ? 's' : ''}` : 'No results';
}

// Pre-select equipment item in picker (used when editing)
function preselectEquipment(id, name) {
    // deselect all first
    document.querySelectorAll('.eq-item.selected, .eq-none-opt.selected').forEach(el => el.classList.remove('selected'));

    if (!id) {
        const noneEl = document.getElementById('eqNoneOpt');
        if (noneEl) noneEl.classList.add('selected');
        document.getElementById('selectedEquipmentId').value = '';
        document.getElementById('eqSelectedBadge').style.display = 'none';
        return;
    }

    const el = document.querySelector(`#equipmentPickerGrid .eq-item[data-eq-id="${id}"]`);
    if (el) {
        el.classList.add('selected');
        el.scrollIntoView({ block: 'nearest' });
    }
    document.getElementById('selectedEquipmentId').value = id;
    document.getElementById('eqSelectedBadge').style.display = 'flex';
    document.getElementById('eqSelectedName').textContent = name || '';
}

// ─────────────────────────────────────────────
//  Modal Open / Close
// ─────────────────────────────────────────────
function openAddExerciseModal() {
    document.getElementById('exerciseModalTitle').innerHTML =
        '<i class="fas fa-plus-circle" style="color: var(--primary); margin-right: 8px;"></i>Add New Exercise';
    document.getElementById('exerciseForm').reset();
    document.getElementById('exerciseId').value = '';
    document.getElementById('eqSearchInput').value = '';
    filterEquipmentPicker(); // reset filter
    clearEquipmentSelection();
    removeImagePreview();
    document.getElementById('exerciseModal').classList.add('active');
    document.getElementById('exerciseName').focus();
}

function closeExerciseModal() {
    document.getElementById('exerciseModal').classList.remove('active');
}

function closeDeleteExerciseModal() {
    document.getElementById('deleteExerciseModal').classList.remove('active');
}

// ─────────────────────────────────────────────
//  Image Handling
// ─────────────────────────────────────────────
function handleImagePreview(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        showNotification('Image must be under 5MB', 'warning');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('imagePreviewContainer').style.display = 'block';
        document.getElementById('imageUploadArea').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function handleImageDrop(event) {
    event.preventDefault();
    document.getElementById('imageUploadArea').style.borderColor = 'var(--dark-border)';
    const file = event.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('exerciseImageFile').files = dt.files;
        handleImagePreview({ target: { files: dt.files } });
    }
}

function removeImagePreview() {
    document.getElementById('exerciseImageFile').value = '';
    document.getElementById('exerciseImageUrl').value  = '';
    document.getElementById('imagePreview').src = '';
    document.getElementById('imagePreviewContainer').style.display = 'none';
    document.getElementById('imageUploadArea').style.display = 'block';
}

// ─────────────────────────────────────────────
//  Edit Exercise
// ─────────────────────────────────────────────
function editExercise(id) {
    const ex = allExercises.find(e => e.id === id);
    if (!ex) return;

    document.getElementById('exerciseModalTitle').innerHTML =
        '<i class="fas fa-edit" style="color: var(--primary); margin-right: 8px;"></i>Edit Exercise';
    document.getElementById('exerciseId').value          = ex.id;
    document.getElementById('exerciseName').value        = ex.name;
    document.getElementById('exerciseCategory').value    = ex.category;
    document.getElementById('exerciseDescription').value = ex.description  || '';
    document.getElementById('exerciseInstructions').value = ex.instructions || '';
    document.getElementById('exerciseImageUrl').value    = ex.image_url    || '';

    // Image preview
    if (ex.image_url) {
        document.getElementById('imagePreview').src = ex.image_url;
        document.getElementById('imagePreviewContainer').style.display = 'block';
        document.getElementById('imageUploadArea').style.display = 'none';
    } else {
        removeImagePreview();
    }

    // Equipment picker
    document.getElementById('eqSearchInput').value = '';
    filterEquipmentPicker();
    if (ex.equipment_id) {
        const eqName = getEquipmentName(ex.equipment_id);
        preselectEquipment(ex.equipment_id, eqName);
    } else {
        clearEquipmentSelection();
    }

    document.getElementById('exerciseModal').classList.add('active');
}

// ─────────────────────────────────────────────
//  Save Exercise (Create / Update)
// ─────────────────────────────────────────────
async function saveExercise(event) {
    event.preventDefault();

    const id   = document.getElementById('exerciseId').value;
    const name = document.getElementById('exerciseName').value.trim();

    if (!name) {
        showNotification('Exercise name is required', 'warning');
        return;
    }

    const btn = document.getElementById('saveExerciseBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData();
    if (id) formData.append('id', id);

    formData.append('name',         name);
    formData.append('category',     document.getElementById('exerciseCategory').value);
    formData.append('equipment_id', document.getElementById('selectedEquipmentId').value);
    formData.append('description',  document.getElementById('exerciseDescription').value.trim());
    formData.append('instructions', document.getElementById('exerciseInstructions').value.trim());

    // Image — prefer new file upload, fall back to existing URL
    const imageFile = document.getElementById('exerciseImageFile').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    } else {
        formData.append('image_url', document.getElementById('exerciseImageUrl').value);
    }

    const url = id ? '../../api/exercises/update.php' : '../../api/exercises/create.php';

    try {
        const res  = await fetch(url, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showNotification(id ? 'Exercise updated successfully!' : 'Exercise created successfully!', 'success');
            closeExerciseModal();
            await loadData();
        } else {
            showNotification(data.message || 'Failed to save exercise', 'warning');
        }
    } catch (err) {
        console.error('Save error:', err);
        showNotification('Server error while saving. Try again.', 'warning');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Exercise';
    }
}

// ─────────────────────────────────────────────
//  Delete Exercise
// ─────────────────────────────────────────────
function deleteExercise(id) {
    const ex = allExercises.find(e => e.id === id);
    if (!ex) return;
    exerciseToDelete = ex;
    document.getElementById('deleteExerciseName').textContent = ex.name;
    document.getElementById('deleteExerciseModal').classList.add('active');
}

async function confirmDeleteExercise() {
    if (!exerciseToDelete) return;

    const formData = new FormData();
    formData.append('id', exerciseToDelete.id);

    try {
        const res  = await fetch('../../api/exercises/delete.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showNotification('Exercise deleted!', 'success');
            closeDeleteExerciseModal();
            await loadData();
        } else {
            showNotification(data.message || 'Failed to delete exercise', 'warning');
        }
    } catch (err) {
        console.error('Delete error:', err);
        showNotification('Error deleting exercise', 'warning');
    }
    exerciseToDelete = null;
}

// ─────────────────────────────────────────────
//  Notification Toast
// ─────────────────────────────────────────────
function showNotification(message, type = 'info') {
    const icons = { success: 'check-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const colors = { success: '#22c55e', warning: '#f59e0b', info: '#3b82f6' };

    const el = document.createElement('div');
    el.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${message}</span>`;

    Object.assign(el.style, {
        position: 'fixed', top: '88px', right: '28px',
        background: colors[type] || '#3b82f6', color: '#fff',
        padding: '14px 22px', borderRadius: '12px',
        display: 'flex', alignItems: 'center', gap: '10px',
        boxShadow: '0 10px 28px rgba(0,0,0,0.25)', zIndex: '9999',
        fontWeight: '600', fontSize: '0.9rem',
        animation: 'slideIn 0.3s ease-out',
        maxWidth: '340px'
    });

    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; }, 4000);
    setTimeout(() => el.remove(), 4500);
}

// ─────────────────────────────────────────────
//  Logout
// ─────────────────────────────────────────────
async function handleLogout() {
    if (!confirm('Logout from admin panel?')) return;
    try {
        await fetch('../../api/auth/logout.php', { method: 'POST' });
    } catch (e) {}
    localStorage.clear();
    window.location.href = '../../index.php';
}

// ─────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Mobile sidebar toggle
document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close modals on overlay click
document.getElementById('exerciseModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExerciseModal();
});
document.getElementById('deleteExerciseModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteExerciseModal();
});
