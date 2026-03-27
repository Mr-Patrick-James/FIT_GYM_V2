// Trainer Management JS v2.0
let allTrainers = [];
let trainerToDelete = null;

document.addEventListener('DOMContentLoaded', function () {
    loadTrainers();
    document.getElementById('trainerForm')?.addEventListener('submit', saveTrainer);
});

// ─── Load & Render ────────────────────────────────────────────────────────────

async function loadTrainers() {
    const grid = document.getElementById('trainersGrid');
    if (!grid) return;
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;">
        <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
        <p style="margin-top:10px;">Loading trainers...</p></div>`;

    try {
        const res = await fetch('../../api/trainers/get-all.php');
        const data = await res.json();
        if (data.success) {
            allTrainers = data.data;
            updateStats(allTrainers);
            populateSpecFilter(allTrainers);
            filterTrainers();
        } else {
            grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#ef4444;">${data.message}</div>`;
        }
    } catch (e) {
        console.error(e);
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#ef4444;">Failed to load trainers.</div>`;
    }
}

function updateStats(trainers) {
    const totalTrainersEl = document.getElementById('totalTrainers');
    const activeTrainersEl = document.getElementById('activeTrainers');
    const specializationsCountEl = document.getElementById('specializationsCount');
    const totalAssignedPackagesEl = document.getElementById('totalAssignedPackages');
    const totalActiveClientsEl = document.getElementById('totalActiveClients');

    if (totalTrainersEl) totalTrainersEl.textContent = trainers.length;
    if (activeTrainersEl) activeTrainersEl.textContent = trainers.filter(t => t.is_active).length;
    
    const specs = new Set(trainers.map(t => t.specialization ? t.specialization.trim().toLowerCase() : ''));
    if (specializationsCountEl) specializationsCountEl.textContent = specs.size;
    
    if (totalAssignedPackagesEl) totalAssignedPackagesEl.textContent = trainers.reduce((s, t) => s + (t.package_count || 0), 0);
    if (totalActiveClientsEl) totalActiveClientsEl.textContent = trainers.reduce((s, t) => s + (t.active_client_count || 0), 0);
}

function populateSpecFilter(trainers) {
    const sel = document.getElementById('filterSpec');
    if (!sel) return;
    const specs = [...new Set(trainers.map(t => t.specialization.trim()))].sort();
    const current = sel.value;
    sel.innerHTML = '<option value="">All</option>' + specs.map(s => `<option value="${s}"${s === current ? ' selected' : ''}>${s}</option>`).join('');
}

function filterTrainers() {
    const query  = (document.getElementById('trainerSearch')?.value || '').toLowerCase();
    const spec   = document.getElementById('filterSpec')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';
    const sort   = document.getElementById('sortBy')?.value || 'name';

    let list = allTrainers.filter(t => {
        const matchQ = !query || t.name.toLowerCase().includes(query) || t.specialization.toLowerCase().includes(query) ||
            (t.email && t.email.toLowerCase().includes(query)) || (t.contact && t.contact.toLowerCase().includes(query));
        const matchSpec   = !spec   || t.specialization.trim() === spec;
        const matchStatus = !status || (status === 'active' ? t.is_active : !t.is_active);
        return matchQ && matchSpec && matchStatus;
    });

    if (sort === 'clients')  list.sort((a, b) => (b.active_client_count || 0) - (a.active_client_count || 0));
    else if (sort === 'packages') list.sort((a, b) => (b.package_count || 0) - (a.package_count || 0));
    else list.sort((a, b) => a.name.localeCompare(b.name));

    renderTrainers(list);
}

function renderTrainers(trainers) {
    const grid  = document.getElementById('trainersGrid');
    const noMsg = document.getElementById('noTrainersMessage');
    if (!grid) return;

    if (trainers.length === 0) {
        grid.style.display = 'none';
        noMsg.style.display = 'block';
        return;
    }
    grid.style.display = 'grid';
    noMsg.style.display = 'none';

    const active   = trainers.filter(t => t.is_active);
    const inactive = trainers.filter(t => !t.is_active);

    let html = active.map(t => trainerCardHTML(t)).join('');
    if (inactive.length) {
        html += `<div class="inactive-section-label"><span>Inactive Trainers (${inactive.length})</span></div>`;
        html += inactive.map(t => trainerCardHTML(t)).join('');
    }
    grid.innerHTML = html;
}

function trainerCardHTML(trainer) {
    const active      = trainer.active_client_count || 0;
    const max         = trainer.max_clients || 10;
    const loadPct     = Math.min(100, Math.round((active / max) * 100));
    const loadColor   = loadPct >= 90 ? '#ef4444' : loadPct >= 70 ? '#f59e0b' : '#22c55e';

    const certs = trainer.certifications
        ? trainer.certifications.split(',').map(c => c.trim()).filter(Boolean)
            .map(c => `<span class="cert-tag">${c}</span>`).join('')
        : '';

    let availHTML = '';
    if (trainer.availability) {
        try {
            const av = JSON.parse(trainer.availability);
            if (av.days && av.days.length) {
                availHTML = av.days.map(d => `<span class="avail-pill">${d}</span>`).join('');
                if (av.from && av.until) availHTML += `<span class="avail-pill">${av.from}–${av.until}</span>`;
            }
        } catch (e) { /* ignore */ }
    }

    return `
    <div class="content-card trainer-card${trainer.is_active ? '' : ' inactive'}" style="padding:0;display:flex;flex-direction:column;height:100%;transition:transform 0.3s,box-shadow 0.3s;">
        <div style="height:160px;background:#1a1a1a;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;">
            ${trainer.photo_url
                ? `<img src="../../${trainer.photo_url}" style="width:100%;height:100%;object-fit:cover;">`
                : `<i class="fas fa-user-tie" style="font-size:56px;color:#333;"></i>`}
            <div style="position:absolute;top:12px;right:12px;">
                <span class="status-badge" style="background:${trainer.is_active ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)'};color:${trainer.is_active ? '#22c55e' : '#ef4444'};border:1px solid ${trainer.is_active ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)'};">
                    <i class="fas fa-${trainer.is_active ? 'check-circle' : 'times-circle'}"></i>
                    ${trainer.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>
        </div>

        <div style="padding:20px;flex:1;display:flex;flex-direction:column;">
            <div style="margin-bottom:12px;">
                <h3 style="font-size:1.2rem;font-weight:800;margin-bottom:6px;">${trainer.name}</h3>
                <div style="display:inline-block;padding:3px 10px;background:var(--glass);border-radius:var(--radius-sm);border:1px solid var(--glass-border);color:var(--primary);font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                    ${trainer.specialization}
                </div>
                ${certs ? `<div class="cert-tags">${certs}</div>` : ''}
            </div>

            <!-- Performance Stats -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
                <div style="background:var(--glass);border-radius:var(--radius-md);padding:10px;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:900;color:#3b82f6;">${trainer.total_clients_handled || 0}</div>
                    <div style="font-size:0.62rem;color:var(--dark-text-secondary);font-weight:600;">Total Clients</div>
                </div>
                <div style="background:var(--glass);border-radius:var(--radius-md);padding:10px;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:900;color:#22c55e;">${active}</div>
                    <div style="font-size:0.62rem;color:var(--dark-text-secondary);font-weight:600;">Active Now</div>
                </div>
                <div style="background:var(--glass);border-radius:var(--radius-md);padding:10px;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:900;color:#8b5cf6;">${trainer.package_count || 0}</div>
                    <div style="font-size:0.62rem;color:var(--dark-text-secondary);font-weight:600;">Packages</div>
                </div>
            </div>

            <!-- Client Load Bar -->
            <div class="load-bar-wrap">
                <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--dark-text-secondary);margin-bottom:4px;">
                    <span>Client Load</span>
                    <span style="color:${loadColor};font-weight:700;">${active}/${max} (${loadPct}%)</span>
                </div>
                <div class="load-bar-track">
                    <div class="load-bar-fill" style="width:${loadPct}%;background:${loadColor};"></div>
                </div>
            </div>

            ${availHTML ? `<div style="margin-top:10px;"><div style="font-size:0.68rem;color:var(--dark-text-secondary);font-weight:700;text-transform:uppercase;margin-bottom:4px;">Schedule</div><div class="avail-pills">${availHTML}</div></div>` : ''}

            <p style="font-size:0.82rem;color:var(--dark-text-secondary);line-height:1.6;margin:14px 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1;">
                ${trainer.bio || 'No bio provided.'}
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding-top:16px;border-top:1px solid var(--dark-border);">
                <button class="card-btn" style="justify-content:center;border-color:var(--info);color:var(--info);" onclick="openProfileDrawer(${trainer.id})">
                    <i class="fas fa-id-card"></i> Profile
                </button>
                <button class="card-btn" style="justify-content:center;border-color:var(--warning);color:var(--warning);" onclick="editTrainer(${trainer.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="card-btn" style="justify-content:center;border-color:var(--danger);color:var(--danger);" onclick="deleteTrainer(${trainer.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
            <button class="card-btn primary" style="justify-content:center;width:100%;margin-top:8px;" onclick="viewTrainerMembers(${trainer.id}, '${trainer.name.replace(/'/g, "\\'")}')">
                <i class="fas fa-users"></i> View Assigned Members
            </button>
        </div>
    </div>`;
}

// ─── Profile Drawer ───────────────────────────────────────────────────────────

function openProfileDrawer(id) {
    const trainer = allTrainers.find(t => t.id === id);
    if (!trainer) return;

    document.getElementById('drawerTitle').textContent = trainer.name;
    document.getElementById('drawerOverlay').classList.add('active');
    document.getElementById('profileDrawer').classList.add('open');

    let certs = '';
    if (trainer.certifications) {
        certs = trainer.certifications.split(',').map(c => c.trim()).filter(Boolean)
            .map(c => `<span class="cert-tag">${c}</span>`).join('');
    }

    let availText = 'Not specified';
    if (trainer.availability) {
        try {
            const av = JSON.parse(trainer.availability);
            if (av.days && av.days.length) {
                availText = av.days.join(', ');
                if (av.from && av.until) availText += ` &bull; ${av.from}–${av.until}`;
            }
        } catch (e) { /* ignore */ }
    }

    const loadPct = Math.min(100, Math.round(((trainer.active_client_count || 0) / (trainer.max_clients || 10)) * 100));
    const loadColor = loadPct >= 90 ? '#ef4444' : loadPct >= 70 ? '#f59e0b' : '#22c55e';

    document.getElementById('drawerBody').innerHTML = `
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
            <div style="width:80px;height:80px;border-radius:var(--radius-lg);overflow:hidden;border:2px solid var(--dark-border);flex-shrink:0;background:var(--glass);display:flex;align-items:center;justify-content:center;">
                ${trainer.photo_url
                    ? `<img src="../../${trainer.photo_url}" style="width:100%;height:100%;object-fit:cover;">`
                    : `<i class="fas fa-user-tie" style="font-size:2rem;color:var(--dark-text-secondary);"></i>`}
            </div>
            <div>
                <h3 style="font-size:1.3rem;font-weight:800;">${trainer.name}</h3>
                <div style="font-size:0.8rem;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">${trainer.specialization}</div>
                <span class="status-badge" style="margin-top:6px;background:${trainer.is_active ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)'};color:${trainer.is_active ? '#22c55e' : '#ef4444'};border:1px solid ${trainer.is_active ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)'};">
                    <i class="fas fa-${trainer.is_active ? 'check-circle' : 'times-circle'}"></i>
                    ${trainer.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Performance</div>
            <div class="stat-mini-grid">
                <div class="stat-mini"><div class="stat-mini-val" style="color:#3b82f6;">${trainer.total_clients_handled || 0}</div><div class="stat-mini-lbl">Total Clients</div></div>
                <div class="stat-mini"><div class="stat-mini-val" style="color:#22c55e;">${trainer.active_client_count || 0}</div><div class="stat-mini-lbl">Active Now</div></div>
                <div class="stat-mini"><div class="stat-mini-val" style="color:#8b5cf6;">${trainer.package_count || 0}</div><div class="stat-mini-lbl">Packages</div></div>
            </div>
            <div style="margin-top:14px;">
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--dark-text-secondary);margin-bottom:5px;">
                    <span>Client Load</span>
                    <span style="color:${loadColor};font-weight:700;">${trainer.active_client_count || 0}/${trainer.max_clients || 10} (${loadPct}%)</span>
                </div>
                <div class="load-bar-track"><div class="load-bar-fill" style="width:${loadPct}%;background:${loadColor};"></div></div>
            </div>
        </div>

        <div class="drawer-section">
            <div class="drawer-section-title">Contact</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                ${trainer.email ? `<div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;"><i class="fas fa-envelope" style="width:18px;color:var(--dark-text-secondary);"></i> ${trainer.email}</div>` : ''}
                ${trainer.contact ? `<div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;"><i class="fas fa-phone" style="width:18px;color:var(--dark-text-secondary);"></i> ${trainer.contact}</div>` : ''}
            </div>
        </div>

        ${certs ? `<div class="drawer-section"><div class="drawer-section-title">Certifications</div><div class="cert-tags">${certs}</div></div>` : ''}

        <div class="drawer-section">
            <div class="drawer-section-title">Schedule / Availability</div>
            <p style="font-size:0.85rem;color:var(--dark-text-secondary);">${availText}</p>
        </div>

        ${trainer.bio ? `<div class="drawer-section"><div class="drawer-section-title">Bio</div><p style="font-size:0.85rem;color:var(--dark-text-secondary);line-height:1.7;">${trainer.bio}</p></div>` : ''}

        <div style="display:flex;gap:10px;margin-top:8px;">
            <button class="card-btn" style="flex:1;justify-content:center;border-color:var(--warning);color:var(--warning);" onclick="closeProfileDrawer();editTrainer(${trainer.id})">
                <i class="fas fa-edit"></i> Edit Trainer
            </button>
            <button class="card-btn primary" style="flex:1;justify-content:center;" onclick="closeProfileDrawer();viewTrainerMembers(${trainer.id},'${trainer.name.replace(/'/g, "\\'")}')">
                <i class="fas fa-users"></i> View Members
            </button>
        </div>
    `;
}

function closeProfileDrawer() {
    document.getElementById('drawerOverlay').classList.remove('active');
    document.getElementById('profileDrawer').classList.remove('open');
}

// ─── Members Modal ────────────────────────────────────────────────────────────

async function viewTrainerMembers(id, name) {
    const modal = document.getElementById('trainerMembersModal');
    document.getElementById('viewMembersModalTitle').textContent = `Members Assigned to ${name}`;
    modal.classList.add('active');

    const list = document.getElementById('trainerMembersList');
    list.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i><p style="margin-top:10px;">Loading...</p></div>';

    try {
        const res  = await fetch(`../../api/admin/get-trainer-clients.php?trainer_id=${id}`);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            list.innerHTML = data.data.map(m => {
                const diff  = m.latest_weight && m.starting_weight ? (m.latest_weight - m.starting_weight).toFixed(1) : null;
                const color = diff ? (diff < 0 ? '#22c55e' : '#ef4444') : 'inherit';
                const icon  = diff ? (diff < 0 ? 'fa-arrow-down' : 'fa-arrow-up') : '';
                return `
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <h4 style="font-weight:700;margin-bottom:4px;">${m.name}</h4>
                            <p style="font-size:0.8rem;color:var(--dark-text-secondary);">${m.package_name}</p>
                        </div>
                        <span class="status-badge ${m.is_expired ? 'status-rejected' : 'status-verified'}" style="font-size:0.7rem;">${m.is_expired ? 'Expired' : 'Active'}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.05);">
                        <div>
                            <p style="font-size:0.7rem;color:var(--dark-text-secondary);text-transform:uppercase;font-weight:700;margin-bottom:4px;">Latest Weight</p>
                            <p style="font-weight:800;">${m.latest_weight ? m.latest_weight + ' kg' : 'N/A'}</p>
                        </div>
                        <div>
                            <p style="font-size:0.7rem;color:var(--dark-text-secondary);text-transform:uppercase;font-weight:700;margin-bottom:4px;">Progress</p>
                            <p style="font-weight:800;color:${color};">${diff ? `<i class="fas ${icon}"></i> ${Math.abs(diff)} kg` : 'No logs yet'}</p>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--dark-text-secondary);">
                        <span><i class="fas fa-history"></i> ${m.log_count} progress logs</span>
                        <span>Expires: ${new Date(m.expires_at).toLocaleDateString()}</span>
                    </div>
                </div>`;
            }).join('');
        } else {
            list.innerHTML = `<div style="text-align:center;padding:40px;color:var(--dark-text-secondary);">
                <i class="fas fa-users-slash" style="font-size:3rem;margin-bottom:16px;opacity:0.3;"></i>
                <p>No members currently assigned to this trainer.</p></div>`;
        }
    } catch (e) {
        list.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;"><p>Failed to load members.</p></div>';
    }
}

function closeTrainerMembersModal() {
    document.getElementById('trainerMembersModal').classList.remove('active');
}

// ─── Add / Edit Modal ─────────────────────────────────────────────────────────

function openAddTrainerModal() {
    document.getElementById('modalTitle').textContent = 'Add New Trainer';
    document.getElementById('trainerForm').reset();
    document.getElementById('trainerId').value = '';
    document.getElementById('trainerActive').checked = true;
    document.getElementById('trainerMaxClients').value = 10;
    document.getElementById('trainerPhotoUrl').value = '';
    document.getElementById('photoPreview').innerHTML = '<i class="fas fa-user-tie"></i>';
    document.getElementById('passwordLabel').innerHTML = 'Initial Password <span style="color:var(--warning);">*</span>';
    document.getElementById('trainerPassword').placeholder = 'Set initial password (default: trainer123)';
    resetDayChecks([]);
    document.getElementById('availFrom').value = '06:00';
    document.getElementById('availUntil').value = '18:00';
    document.getElementById('trainerModal').classList.add('active');
}

function closeTrainerModal() {
    document.getElementById('trainerModal').classList.remove('active');
}

function editTrainer(id) {
    const t = allTrainers.find(t => t.id === id);
    if (!t) return;

    document.getElementById('modalTitle').textContent = 'Edit Trainer';
    document.getElementById('trainerId').value = t.id;
    document.getElementById('trainerName').value = t.name;
    document.getElementById('trainerSpecialization').value = t.specialization;
    document.getElementById('trainerEmail').value = t.email || '';
    document.getElementById('trainerContact').value = t.contact || '';
    document.getElementById('trainerBio').value = t.bio || '';
    document.getElementById('trainerActive').checked = t.is_active;
    document.getElementById('trainerMaxClients').value = t.max_clients || 10;
    document.getElementById('trainerCertifications').value = t.certifications || '';
    document.getElementById('trainerPassword').placeholder = 'Leave blank to keep current password';
    document.getElementById('passwordLabel').textContent = 'Change Password';

    // Photo
    document.getElementById('trainerPhotoUrl').value = t.photo_url || '';
    const prev = document.getElementById('photoPreview');
    prev.innerHTML = t.photo_url
        ? `<img src="../../${t.photo_url}" style="width:100%;height:100%;object-fit:cover;">`
        : '<i class="fas fa-user-tie"></i>';

    // Availability
    let days = [], from = '06:00', until = '18:00';
    if (t.availability) {
        try {
            const av = JSON.parse(t.availability);
            days  = av.days  || [];
            from  = av.from  || '06:00';
            until = av.until || '18:00';
        } catch (e) { /* ignore */ }
    }
    resetDayChecks(days);
    document.getElementById('availFrom').value  = from;
    document.getElementById('availUntil').value = until;

    document.getElementById('trainerModal').classList.add('active');
}

function toggleDay(label) {
    const cb = label.querySelector('input[type=checkbox]');
    cb.checked = !cb.checked;
    label.classList.toggle('checked', cb.checked);
}

function resetDayChecks(selectedDays) {
    document.querySelectorAll('#availDays .day-check-label').forEach(label => {
        const cb = label.querySelector('input');
        cb.checked = selectedDays.includes(cb.value);
        label.classList.toggle('checked', cb.checked);
    });
}

function getSelectedDays() {
    return [...document.querySelectorAll('#availDays input[type=checkbox]:checked')].map(cb => cb.value);
}

// ─── Photo Upload ─────────────────────────────────────────────────────────────

function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoPreview').innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
}

async function uploadTrainerPhoto(file) {
    const fd = new FormData();
    fd.append('photo', file);
    const res  = await fetch('../../api/upload/trainer-photo.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) return data.data.url;
    throw new Error(data.message || 'Photo upload failed');
}

// ─── Save Trainer ─────────────────────────────────────────────────────────────

async function saveTrainer(event) {
    event.preventDefault();
    const btn = document.getElementById('saveTrainerBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const id       = document.getElementById('trainerId').value;
        const password = document.getElementById('trainerPassword').value;
        const photoFile = document.getElementById('trainerPhotoFile').files[0];

        let photoUrl = document.getElementById('trainerPhotoUrl').value;
        if (photoFile) {
            photoUrl = await uploadTrainerPhoto(photoFile);
        }

        const days = getSelectedDays();
        const availability = JSON.stringify({
            days,
            from:  document.getElementById('availFrom').value,
            until: document.getElementById('availUntil').value
        });

        const payload = {
            name:           document.getElementById('trainerName').value,
            specialization: document.getElementById('trainerSpecialization').value,
            email:          document.getElementById('trainerEmail').value,
            contact:        document.getElementById('trainerContact').value,
            bio:            document.getElementById('trainerBio').value,
            is_active:      document.getElementById('trainerActive').checked ? 1 : 0,
            max_clients:    parseInt(document.getElementById('trainerMaxClients').value) || 10,
            certifications: document.getElementById('trainerCertifications').value,
            availability,
            photo_url:      photoUrl
        };

        if (password) payload.password = password;
        if (id) payload.id = id;

        const url    = id ? '../../api/trainers/update.php' : '../../api/trainers/create.php';
        const method = id ? 'PUT' : 'POST';

        const res  = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await res.json();

        if (data.success) {
            showNotification(id ? 'Trainer updated successfully' : 'Trainer added successfully', 'success');
            closeTrainerModal();
            loadTrainers();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (e) {
        console.error(e);
        showNotification('An error occurred: ' + e.message, 'warning');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Trainer';
    }
}

// ─── Delete ───────────────────────────────────────────────────────────────────

function deleteTrainer(id) {
    const t = allTrainers.find(t => t.id === id);
    if (!t) return;
    trainerToDelete = id;
    document.getElementById('deleteTrainerName').textContent = t.name;
    document.getElementById('deleteTrainerModal').classList.add('active');
}

function closeDeleteTrainerModal() {
    document.getElementById('deleteTrainerModal').classList.remove('active');
    trainerToDelete = null;
}

async function confirmDeleteTrainer() {
    if (!trainerToDelete) return;
    try {
        const res  = await fetch('../../api/trainers/delete.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: trainerToDelete }) });
        const data = await res.json();
        if (data.success) {
            showNotification('Trainer deleted successfully', 'success');
            closeDeleteTrainerModal();
            loadTrainers();
        } else {
            showNotification(data.message, 'warning');
        }
    } catch (e) {
        showNotification('An error occurred while deleting the trainer.', 'warning');
    }
}

// ─── Export ───────────────────────────────────────────────────────────────────

function exportTrainers() {
    window.open('../../api/trainers/export.php', '_blank');
}

// ─── Notifications & Helpers ──────────────────────────────────────────────────

function showNotification(message, type = 'info') {
    const n = document.createElement('div');
    n.style.cssText = `position:fixed;top:100px;right:32px;background:${type === 'success' ? '#22c55e' : type === 'warning' ? '#f59e0b' : '#3b82f6'};color:white;padding:16px 24px;border-radius:12px;display:flex;align-items:center;gap:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:10000;animation:slideIn 0.3s ease-out;font-weight:600;`;
    n.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 5000);
}

async function handleLogout() {
    if (!confirm('Logout from admin panel?')) return;
    try { await fetch('../../api/auth/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } }); } catch (e) { /* ignore */ }
    window.location.href = '../../index.php';
}

// Mobile menu
document.getElementById('mobileMenuToggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close modals on overlay click
window.onclick = function (event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
};
