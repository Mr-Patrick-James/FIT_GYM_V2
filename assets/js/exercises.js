// Exercise Management JS
let allExercises = [];
let allEquipment = [];
let exerciseToDelete = null;

// Load exercises and equipment
async function loadData() {
    try {
        const [exRes, eqRes] = await Promise.all([
            fetch('../../api/exercises/get-all.php'),
            fetch('../../api/equipment/get-all.php')
        ]);
        
        const exData = await exRes.json();
        const eqData = await eqRes.json();
        
        if (exData.success) allExercises = exData.data;
        if (eqData.success) allEquipment = eqData.data;
        
        populateEquipmentSelect();
        populateExercisesGrid();
    } catch (error) {
        console.error('Error loading data:', error);
        showNotification('Error loading exercises', 'warning');
    }
}

function populateEquipmentSelect() {
    const select = document.getElementById('equipmentSelect');
    if (!select) return;
    
    select.innerHTML = '<option value="">No specific equipment</option>';
    allEquipment.forEach(eq => {
        const option = document.createElement('option');
        option.value = eq.id;
        option.textContent = `${eq.name} (${eq.category})`;
        select.appendChild(option);
    });
}

function populateExercisesGrid(exercises = allExercises) {
    const grid = document.getElementById('exercisesGrid');
    const noMsg = document.getElementById('noExercisesMessage');
    
    if (!grid) return;
    grid.innerHTML = '';
    
    if (exercises.length === 0) {
        grid.style.display = 'none';
        noMsg.style.display = 'block';
        return;
    }
    
    grid.style.display = 'grid';
    noMsg.style.display = 'none';
    
    exercises.forEach(ex => {
        const card = document.createElement('div');
        card.className = 'package-card'; // Reuse package card style
        card.style.padding = '0';
        card.style.overflow = 'hidden';
        
        const imageUrl = ex.image_url || 'https://via.placeholder.com/300x180?text=No+Image';
        
        card.innerHTML = `
            <div style="height: 160px; position: relative;">
                <img src="${imageUrl}" style="width: 100%; height: 100%; object-fit: cover;">
                <span class="package-tag" style="position: absolute; top: 10px; right: 10px;">${ex.category}</span>
                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; font-size: 0.75rem;">
                    <i class="fas fa-link"></i> Linked to ${ex.package_count} package${ex.package_count === 1 ? '' : 's'}
                </div>
            </div>
            <div style="padding: 16px;">
                <h3 style="color: var(--primary); margin-bottom: 8px; font-size: 1.1rem; font-weight: 700;">${ex.name}</h3>
                <div style="display: flex; gap: 8px; margin-top: 16px;">
                    <button class="btn btn-secondary" style="flex: 1; padding: 8px; font-size: 0.85rem;" onclick="editExercise(${ex.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn" style="flex: 1; padding: 8px; font-size: 0.85rem; background: rgba(239, 68, 68, 0.1); color: #ef4444;" onclick="deleteExercise(${ex.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function filterExercises() {
    const query = document.getElementById('exerciseSearch').value.toLowerCase();
    const filtered = allExercises.filter(ex => 
        ex.name.toLowerCase().includes(query) || 
        ex.category.toLowerCase().includes(query)
    );
    populateExercisesGrid(filtered);
}

function openAddExerciseModal() {
    document.getElementById('exerciseModalTitle').textContent = 'Add New Exercise';
    document.getElementById('exerciseForm').reset();
    document.getElementById('exerciseId').value = '';
    removeImagePreview();
    document.getElementById('exerciseModal').classList.add('active');
}

function handleImagePreview(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewContainer').style.display = 'block';
            document.getElementById('imageUploadArea').style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

function removeImagePreview() {
    document.getElementById('exerciseImageFile').value = '';
    document.getElementById('exerciseImageUrl').value = '';
    document.getElementById('imagePreview').src = '';
    document.getElementById('imagePreviewContainer').style.display = 'none';
    document.getElementById('imageUploadArea').style.display = 'block';
}

function closeExerciseModal() {
    document.getElementById('exerciseModal').classList.remove('active');
}

async function editExercise(id) {
    const ex = allExercises.find(e => e.id === id);
    if (!ex) return;
    
    document.getElementById('exerciseModalTitle').textContent = 'Edit Exercise';
    document.getElementById('exerciseId').value = ex.id;
    document.getElementById('exerciseName').value = ex.name;
    document.getElementById('exerciseCategory').value = ex.category;
    document.getElementById('equipmentSelect').value = ex.equipment_id || '';
    document.getElementById('exerciseImageUrl').value = ex.image_url || '';
    document.getElementById('exerciseDescription').value = ex.description || '';
    document.getElementById('exerciseInstructions').value = ex.instructions || '';
    
    if (ex.image_url) {
        document.getElementById('imagePreview').src = ex.image_url;
        document.getElementById('imagePreviewContainer').style.display = 'block';
        document.getElementById('imageUploadArea').style.display = 'none';
    } else {
        removeImagePreview();
    }
    
    document.getElementById('exerciseModal').classList.add('active');
}

async function saveExercise(event) {
    event.preventDefault();
    
    const id = document.getElementById('exerciseId').value;
    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('name', document.getElementById('exerciseName').value);
    formData.append('category', document.getElementById('exerciseCategory').value);
    formData.append('equipment_id', document.getElementById('equipmentSelect').value);
    formData.append('description', document.getElementById('exerciseDescription').value);
    formData.append('instructions', document.getElementById('exerciseInstructions').value);
    
    // Handle image - either file upload or existing URL
    const imageFile = document.getElementById('exerciseImageFile').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    } else {
        formData.append('image_url', document.getElementById('exerciseImageUrl').value);
    }
    
    const url = id ? '../../api/exercises/update.php' : '../../api/exercises/create.php';
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification(id ? 'Exercise updated!' : 'Exercise created!', 'success');
            closeExerciseModal();
            await loadData();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error saving exercise:', error);
        showNotification('Error saving exercise', 'warning');
    }
}

function deleteExercise(id) {
    const ex = allExercises.find(e => e.id === id);
    if (!ex) return;
    
    exerciseToDelete = ex;
    document.getElementById('deleteExerciseName').textContent = ex.name;
    document.getElementById('deleteExerciseModal').classList.add('active');
}

function closeDeleteExerciseModal() {
    document.getElementById('deleteExerciseModal').classList.remove('active');
}

async function confirmDeleteExercise() {
    if (!exerciseToDelete) return;
    
    const formData = new FormData();
    formData.append('id', exerciseToDelete.id);
    
    try {
        const response = await fetch('../../api/exercises/delete.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('Exercise deleted!', 'success');
            closeDeleteExerciseModal();
            await loadData();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error deleting exercise:', error);
        showNotification('Error deleting exercise', 'warning');
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

function handleLogout() {
    if (confirm('Logout from admin panel?')) {
        window.location.href = '../../api/auth/logout.php';
    }
}

// Mobile Menu Toggle
document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});

document.addEventListener('DOMContentLoaded', loadData);
