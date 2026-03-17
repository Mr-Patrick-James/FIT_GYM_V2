// Trainer Management JS
let allTrainers = [];
let trainerToDelete = null;

document.addEventListener('DOMContentLoaded', function() {
    loadTrainers();
    
    // Handle form submission
    const trainerForm = document.getElementById('trainerForm');
    if (trainerForm) {
        trainerForm.addEventListener('submit', saveTrainer);
    }
});

async function loadTrainers() {
    const grid = document.getElementById('trainersGrid');
    const noMsg = document.getElementById('noTrainersMessage');
    if (!grid) return;
    
    try {
        const response = await fetch('../../api/trainers/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            allTrainers = data.data;
            updateStats(allTrainers);
            renderTrainers(allTrainers);
        } else {
            grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                <p style="color: #ef4444;">${data.message}</p>
            </div>`;
        }
    } catch (error) {
        console.error('Error loading trainers:', error);
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;">
            <p style="color: #ef4444;">Failed to load trainers. Please try again.</p>
        </div>`;
    }
}

function updateStats(trainers) {
    document.getElementById('totalTrainers').textContent = trainers.length;
    document.getElementById('activeTrainers').textContent = trainers.filter(t => t.is_active).length;
    
    const specializations = new Set(trainers.map(t => t.specialization.trim().toLowerCase()));
    document.getElementById('specializationsCount').textContent = specializations.size;

    const totalPackages = trainers.reduce((sum, t) => sum + (t.package_count || 0), 0);
    const totalPkgEl = document.getElementById('totalAssignedPackages');
    if (totalPkgEl) totalPkgEl.textContent = totalPackages;
}

function renderTrainers(trainers) {
    const grid = document.getElementById('trainersGrid');
    const noMsg = document.getElementById('noTrainersMessage');
    if (!grid) return;
    
    if (trainers.length === 0) {
        grid.style.display = 'none';
        noMsg.style.display = 'block';
        return;
    }
    
    grid.style.display = 'grid';
    noMsg.style.display = 'none';
    
    grid.innerHTML = trainers.map(trainer => `
        <div class="content-card" style="padding: 0; display: flex; flex-direction: column; height: 100%; transition: transform 0.3s, box-shadow 0.3s;">
            <div style="height: 180px; background: #1a1a1a; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                ${trainer.photo_url ? 
                    `<img src="${trainer.photo_url}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s;">` : 
                    `<i class="fas fa-user-tie" style="font-size: 64px; color: #333;"></i>`
                }
                <div style="position: absolute; top: 16px; right: 16px;">
                    <span class="status-badge" style="background: ${trainer.is_active ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'}; color: ${trainer.is_active ? '#22c55e' : '#ef4444'}; border: 1px solid ${trainer.is_active ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)'};">
                        <i class="fas fa-${trainer.is_active ? 'check-circle' : 'times-circle'}"></i>
                        ${trainer.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
            </div>
            
            <div style="padding: 24px; flex: 1; display: flex; flex-direction: column;">
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 6px; color: var(--dark-text);">${trainer.name}</h3>
                    <div style="display: inline-block; padding: 4px 12px; background: var(--glass); border-radius: var(--radius-sm); border: 1px solid var(--glass-border); color: var(--primary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        ${trainer.specialization}
                    </div>
                </div>
                
                <div style="display: grid; gap: 12px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.9rem;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--glass); display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <span><strong>${trainer.package_count || 0}</strong> Assigned Packages</span>
                    </div>
                    ${trainer.email ? `
                        <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.9rem;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--glass); display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${trainer.email}</span>
                        </div>
                    ` : ''}
                    ${trainer.contact ? `
                        <div style="display: flex; align-items: center; gap: 12px; color: var(--dark-text-secondary); font-size: 0.9rem;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--glass); display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <i class="fas fa-phone"></i>
                            </div>
                            <span>${trainer.contact}</span>
                        </div>
                    ` : ''}
                </div>
                
                <p style="font-size: 0.9rem; color: var(--dark-text-secondary); line-height: 1.6; margin-bottom: 24px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1;">
                    ${trainer.bio || 'No professional bio provided yet. Update this trainer to add their background and expertise.'}
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding-top: 20px; border-top: 1px solid var(--dark-border);">
                    <button class="card-btn" style="justify-content: center; width: 100%; border-color: var(--info); color: var(--info);" onclick="editTrainer(${trainer.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="card-btn" style="justify-content: center; width: 100%; border-color: var(--danger); color: var(--danger);" onclick="deleteTrainer(${trainer.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
                <button class="card-btn primary" style="justify-content: center; width: 100%; margin-top: 12px;" onclick="viewTrainerMembers(${trainer.id}, '${trainer.name}')">
                    <i class="fas fa-users"></i> View Assigned Members
                </button>
            </div>
        </div>
    `).join('');
}

// Trainer Members Modal logic
let activeTrainerId = null;

async function viewTrainerMembers(id, name) {
    activeTrainerId = id;
    const modalTitle = document.getElementById('viewMembersModalTitle');
    if (modalTitle) modalTitle.textContent = `Members Assigned to ${name}`;
    
    const modal = document.getElementById('trainerMembersModal');
    if (modal) modal.classList.add('active');
    
    const list = document.getElementById('trainerMembersList');
    if (!list) return;
    
    list.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i><p style="margin-top: 10px;">Loading assigned members...</p></div>';
    
    try {
        const response = await fetch(`../../api/admin/get-trainer-clients.php?trainer_id=${id}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            list.innerHTML = data.data.map(member => {
                const weightDiff = member.latest_weight && member.starting_weight ? (member.latest_weight - member.starting_weight).toFixed(1) : null;
                const weightColor = weightDiff ? (weightDiff < 0 ? '#22c55e' : '#ef4444') : 'inherit';
                const weightIcon = weightDiff ? (weightDiff < 0 ? 'fa-arrow-down' : 'fa-arrow-up') : '';
                
                return `
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h4 style="font-weight: 700; color: #fff; margin-bottom: 4px;">${member.name}</h4>
                                <p style="font-size: 0.8rem; color: var(--dark-text-secondary);">${member.package_name}</p>
                            </div>
                            <span class="status-badge ${member.is_expired ? 'status-rejected' : 'status-verified'}" style="font-size: 0.7rem;">
                                ${member.is_expired ? 'Expired' : 'Active'}
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <p style="font-size: 0.7rem; color: var(--dark-text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Latest Weight</p>
                                <p style="font-weight: 800; color: #fff;">${member.latest_weight ? member.latest_weight + ' kg' : 'N/A'}</p>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--dark-text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Progress</p>
                                <p style="font-weight: 800; color: ${weightColor};">
                                    ${weightDiff ? `<i class="fas ${weightIcon}"></i> ${Math.abs(weightDiff)} kg` : 'No logs yet'}
                                </p>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--dark-text-secondary);">
                            <span><i class="fas fa-history"></i> ${member.log_count} progress logs</span>
                            <span>Expires: ${new Date(member.expires_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            list.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>No members currently assigned to this trainer.</p>
                </div>`;
        }
    } catch (error) {
        console.error('Error loading trainer members:', error);
        list.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;"><p>Failed to load members.</p></div>';
    }
}

function closeTrainerMembersModal() {
    const modal = document.getElementById('trainerMembersModal');
    if (modal) modal.classList.remove('active');
    activeTrainerId = null;
}

function filterTrainers() {
    const query = document.getElementById('trainerSearch').value.toLowerCase();
    const filtered = allTrainers.filter(t => 
        t.name.toLowerCase().includes(query) || 
        t.specialization.toLowerCase().includes(query) ||
        (t.email && t.email.toLowerCase().includes(query)) ||
        (t.contact && t.contact.toLowerCase().includes(query))
    );
    renderTrainers(filtered);
}

function openAddTrainerModal() {
    document.getElementById('modalTitle').textContent = 'Add New Trainer';
    document.getElementById('trainerForm').reset();
    document.getElementById('trainerId').value = '';
    document.getElementById('trainerActive').checked = true;
    document.getElementById('trainerPassword').placeholder = "Set initial password (default: trainer123)";
    document.getElementById('passwordLabel').innerHTML = 'Initial Password <span style="color: var(--warning);">*</span>';
    document.getElementById('trainerModal').classList.add('active');
}

function closeTrainerModal() {
    document.getElementById('trainerModal').classList.remove('active');
}

function editTrainer(id) {
    const trainer = allTrainers.find(t => t.id === id);
    if (!trainer) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Trainer';
    document.getElementById('trainerId').value = trainer.id;
    document.getElementById('trainerName').value = trainer.name;
    document.getElementById('trainerSpecialization').value = trainer.specialization;
    document.getElementById('trainerEmail').value = trainer.email || '';
    document.getElementById('trainerContact').value = trainer.contact || '';
    document.getElementById('trainerBio').value = trainer.bio || '';
    document.getElementById('trainerActive').checked = trainer.is_active;
    document.getElementById('trainerPassword').placeholder = "Leave blank to keep current password";
    document.getElementById('passwordLabel').textContent = 'Change Password';
    
    document.getElementById('trainerModal').classList.add('active');
}

async function saveTrainer(event) {
    event.preventDefault();
    
    const id = document.getElementById('trainerId').value;
    const password = document.getElementById('trainerPassword').value;
    
    const trainerData = {
        name: document.getElementById('trainerName').value,
        specialization: document.getElementById('trainerSpecialization').value,
        email: document.getElementById('trainerEmail').value,
        contact: document.getElementById('trainerContact').value,
        bio: document.getElementById('trainerBio').value,
        is_active: document.getElementById('trainerActive').checked ? 1 : 0
    };
    
    if (password) {
        trainerData.password = password;
    }
    
    if (id) {
        trainerData.id = id;
    }
    
    const url = id ? '../../api/trainers/update.php' : '../../api/trainers/create.php';
    const method = id ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(trainerData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(id ? 'Trainer updated successfully' : 'Trainer added successfully', 'success');
            closeTrainerModal();
            loadTrainers();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error saving trainer:', error);
        showNotification('An error occurred while saving the trainer.', 'warning');
    }
}

function deleteTrainer(id) {
    const trainer = allTrainers.find(t => t.id === id);
    if (!trainer) return;
    
    trainerToDelete = id;
    document.getElementById('deleteTrainerName').textContent = trainer.name;
    document.getElementById('deleteTrainerModal').classList.add('active');
}

function closeDeleteTrainerModal() {
    document.getElementById('deleteTrainerModal').classList.remove('active');
    trainerToDelete = null;
}

async function confirmDeleteTrainer() {
    if (!trainerToDelete) return;
    
    try {
        const response = await fetch('../../api/trainers/delete.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: trainerToDelete })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Trainer deleted successfully', 'success');
            closeDeleteTrainerModal();
            loadTrainers();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (error) {
        console.error('Error deleting trainer:', error);
        showNotification('An error occurred while deleting the trainer.', 'warning');
    }
}

// Helper for notifications
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

async function handleLogout() {
    if (!confirm('Logout from admin panel?')) return;
    
    try {
        const response = await fetch('../../api/auth/logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        window.location.href = '../../index.php';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = '../../index.php';
    }
}

// Mobile Menu Toggle
document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
}
