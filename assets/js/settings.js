// Settings data
let settingsData = {
    general: {},
    payment: {},
    notifications: {},
    landing: {
        gallery: [],
        heroImages: []
    },
    account: {}
};

// State for new gallery uploads
let pendingGalleryFiles = [];
let pendingHeroFiles = [];

// Helper to compress images before upload
async function compressImage(file, maxWidth = 1920, quality = 0.8) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                if (width > maxWidth) {
                    height = (maxWidth / width) * height;
                    width = maxWidth;
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob((blob) => {
                    const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".webp", {
                        type: 'image/webp',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/webp', quality);
            };
        };
    });
}

// Handle Gallery Uploads
async function handleGalleryUpload(input) {
    const files = input.files || input;
    if (files) {
        showNotification('Processing images...', 'info');
        for (const file of Array.from(files)) {
            const compressed = await compressImage(file, 1200); // Gallery images can be smaller
            pendingGalleryFiles.push(compressed);
        }
        renderGallery();
    }
}

// Handle Hero Uploads
async function handleHeroUpload(input) {
    const files = input.files || input;
    if (files) {
        showNotification('Optimizing hero backgrounds...', 'info');
        for (const file of Array.from(files)) {
            const compressed = await compressImage(file, 1920); // Hero needs full HD
            pendingHeroFiles.push(compressed);
        }
        renderHeroGallery();
    }
}

// QR Preview Function
function previewQR(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('qr-preview');
            preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove Gallery Item
function removeGalleryItem(index, isExisting = false) {
    if (isExisting) {
        settingsData.landing.gallery.splice(index, 1);
    } else {
        pendingGalleryFiles.splice(index, 1);
    }
    renderGallery();
}

// Remove Hero Item
function removeHeroItem(index, isExisting = false) {
    if (isExisting) {
        settingsData.landing.heroImages.splice(index, 1);
    } else {
        pendingHeroFiles.splice(index, 1);
    }
    renderHeroGallery();
}

// Render Gallery Grid
function renderGallery() {
    const grid = document.getElementById('about-gallery-grid');
    if (!grid) return;

    // Clear existing items but keep the add button
    const addBtn = grid.querySelector('.gallery-add-btn');
    grid.innerHTML = '';

    // Render existing images from server
    settingsData.landing.gallery.forEach((path, index) => {
        const fullPath = path.startsWith('http') ? path : `../../${path}`;
        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <img src="${fullPath}" alt="Gym Interior">
            <button class="remove-btn" onclick="removeGalleryItem(${index}, true)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        grid.appendChild(item);
    });

    // Render pending uploads
    pendingGalleryFiles.forEach((file, index) => {
        const reader = new FileReader();
        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <img src="" alt="Pending Upload">
            <button class="remove-btn" onclick="removeGalleryItem(${index}, false)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        grid.appendChild(item);

        reader.onload = (e) => {
            item.querySelector('img').src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Add the "Add" button back
    grid.appendChild(addBtn);
}

// Render Hero Gallery Grid
function renderHeroGallery() {
    const grid = document.getElementById('hero-gallery-grid');
    if (!grid) return;

    // Clear existing items but keep the add button
    const addBtn = grid.querySelector('.gallery-add-btn');
    grid.innerHTML = '';

    // Render existing images from server
    settingsData.landing.heroImages.forEach((path, index) => {
        const fullPath = path.startsWith('http') ? path : `../../${path}`;
        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <img src="${fullPath}" alt="Hero Background">
            <button class="remove-btn" onclick="removeHeroItem(${index}, true)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        grid.appendChild(item);
    });

    // Render pending uploads
    pendingHeroFiles.forEach((file, index) => {
        const reader = new FileReader();
        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.innerHTML = `
            <img src="" alt="Pending Upload">
            <button class="remove-btn" onclick="removeHeroItem(${index}, false)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        grid.appendChild(item);

        reader.onload = (e) => {
            item.querySelector('img').src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Add the "Add" button back
    grid.appendChild(addBtn);
}

// Load settings from Database
async function loadSettings() {
    try {
        const response = await fetch('../../api/settings/get.php');
        const result = await response.json();
        
        if (result.success) {
            const data = {};
            result.data.forEach(item => {
                data[item.setting_key] = item.setting_value;
            });
            
            // Map flat API data to the structured settingsData
            settingsData.general = {
                gymName: data.gym_name || '',
                gymAddress: data.gym_address || '',
                gymContact: data.gym_contact || '',
                gymEmail: data.gym_email || '',
                openingTime: data.opening_time || '06:00',
                closingTime: data.closing_time || '22:00',
                timezone: data.timezone || 'Asia/Manila'
            };
            settingsData.payment = {
                gcashNumber: data.gcash_number || '',
                gcashName: data.gcash_name || '',
                gcashQRPath: data.gcash_qr_path || '',
                paymentInstructions: data.payment_instructions || '',
                autoVerify: data.auto_verify === 'true'
            };
            settingsData.landing = {
                aboutText: data.about_text || '',
                missionText: data.mission_text || '',
                yearsExperience: data.years_experience || '',
                gallery: JSON.parse(data.about_images || '[]'),
                heroImages: JSON.parse(data.hero_images || '[]'),
                footerTagline: data.footer_tagline || ''
            };
            settingsData.account = {
                adminName: data.admin_name || '',
                adminEmail: data.admin_email || ''
            };
            
            pendingGalleryFiles = []; // Reset pending uploads
            pendingHeroFiles = [];
            populateSettings();
        } else {
            showNotification('Error loading settings: ' + result.message, 'error');
        }
    } catch (e) {
        console.error('Error fetching settings:', e);
        showNotification('Failed to connect to server', 'error');
    }
}

// Populate settings forms
function populateSettings() {
    // General settings
    const gymNameEl = document.getElementById('gymName');
    if (gymNameEl) gymNameEl.value = settingsData.general.gymName;
    const gymAddressEl = document.getElementById('gymAddress');
    if (gymAddressEl) gymAddressEl.value = settingsData.general.gymAddress;
    const gymContactEl = document.getElementById('gymContact');
    if (gymContactEl) gymContactEl.value = settingsData.general.gymContact;
    const gymEmailEl = document.getElementById('gymEmail');
    if (gymEmailEl) gymEmailEl.value = settingsData.general.gymEmail;
    const openingTimeEl = document.getElementById('openingTime');
    if (openingTimeEl) openingTimeEl.value = settingsData.general.openingTime;
    const closingTimeEl = document.getElementById('closingTime');
    if (closingTimeEl) closingTimeEl.value = settingsData.general.closingTime;
    const timezoneEl = document.getElementById('timezone');
    if (timezoneEl) timezoneEl.value = settingsData.general.timezone;
    
    // Payment settings
    const gcashNumberEl = document.getElementById('gcashNumber');
    if (gcashNumberEl) gcashNumberEl.value = settingsData.payment.gcashNumber;
    const gcashNameEl = document.getElementById('gcashName');
    if (gcashNameEl) gcashNameEl.value = settingsData.payment.gcashName;
    const paymentInstructionsEl = document.getElementById('paymentInstructions');
    if (paymentInstructionsEl) paymentInstructionsEl.value = settingsData.payment.paymentInstructions;
    
    // QR Preview
    if (settingsData.payment.gcashQRPath) {
        const preview = document.getElementById('qr-preview');
        if (preview) {
            preview.innerHTML = `<img src="../../${settingsData.payment.gcashQRPath}" style="width: 100%; height: 100%; object-fit: cover;">`;
        }
    }

    // Landing settings
    const aboutTextEl = document.getElementById('aboutText');
    if (aboutTextEl) aboutTextEl.value = settingsData.landing.aboutText;
    const missionTextEl = document.getElementById('missionText');
    if (missionTextEl) missionTextEl.value = settingsData.landing.missionText;
    const yearsExperienceEl = document.getElementById('yearsExperience');
    if (yearsExperienceEl) yearsExperienceEl.value = settingsData.landing.yearsExperience;
    const footerTaglineEl = document.getElementById('footerTagline');
    if (footerTaglineEl) footerTaglineEl.value = settingsData.landing.footerTagline;

    // Account settings
    const adminNameEl = document.getElementById('adminName');
    if (adminNameEl) adminNameEl.value = settingsData.account.adminName;
    const adminEmailEl = document.getElementById('adminEmail');
    if (adminEmailEl) adminEmailEl.value = settingsData.account.adminEmail;

    // Load sub-admins
    loadAdmins();

    // Render gallery
    renderGallery();
    renderHeroGallery();
}

// Tab switching logic
function showSettingsTab(tabName) {
    // Update nav items
    document.querySelectorAll('.settings-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.getElementById(`nav-${tabName}`).classList.add('active');

    // Update sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById(`settings-${tabName}`).style.display = 'block';
}

// Export database backup
function exportDatabase() {
    Swal.fire({
        title: 'Export Database?',
        text: 'This will download a complete SQL backup of your gym database.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#333',
        confirmButtonText: '<i class="fas fa-file-export"></i> Yes, Export!',
        background: '#1a1a1a',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = '../../api/settings/export-db.php';
            link.download = ''; // Let server decide filename
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            Swal.fire({
                title: 'Export Started',
                text: 'Your database backup is being generated. Please wait for the download to finish.',
                icon: 'success',
                timer: 3000,
                showConfirmButton: false,
                background: '#1a1a1a',
                color: '#fff'
            });
        }
    });
}

// Save settings to Database
async function saveToDB(formData) {
    try {
        const response = await fetch('../../api/settings/update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showNotification('Settings saved successfully!', 'success');
            loadSettings(); // Reload to get updated paths/values
        } else {
            showNotification('Error saving settings: ' + result.message, 'error');
        }
    } catch (e) {
        console.error('Error saving settings:', e);
        showNotification('Failed to connect to server', 'error');
    }
}

// Save general settings (includes Landing Page content)
function saveGeneralSettings() {
    const formData = new FormData();
    
    // Gym Info
    formData.append('gym_name', document.getElementById('gymName').value.trim());
    formData.append('gym_address', document.getElementById('gymAddress').value.trim());
    formData.append('gym_contact', document.getElementById('gymContact').value.trim());
    formData.append('gym_email', document.getElementById('gymEmail').value.trim());
    formData.append('opening_time', document.getElementById('openingTime').value);
    formData.append('closing_time', document.getElementById('closingTime').value);
    formData.append('timezone', document.getElementById('timezone').value);
    
    // Landing Page Info
    formData.append('about_text', document.getElementById('aboutText').value.trim());
    formData.append('mission_text', document.getElementById('missionText').value.trim());
    formData.append('years_experience', document.getElementById('yearsExperience').value.trim());
    formData.append('footer_tagline', document.getElementById('footerTagline').value.trim());
    
    // Send existing gallery paths as JSON
    formData.append('existing_gallery', JSON.stringify(settingsData.landing.gallery));
    
    // Append new gallery files
    pendingGalleryFiles.forEach((file, index) => {
        formData.append(`gallery_file_${index}`, file);
    });

    // Send existing hero paths as JSON
    formData.append('existing_hero_images', JSON.stringify(settingsData.landing.heroImages));

    // Append new hero files
    pendingHeroFiles.forEach((file, index) => {
        formData.append(`hero_file_${index}`, file);
    });
    
    saveToDB(formData);
}

// Save payment settings
function savePaymentSettings() {
    const formData = new FormData();
    formData.append('gcash_number', document.getElementById('gcashNumber').value.trim());
    formData.append('gcash_name', document.getElementById('gcashName').value.trim());
    formData.append('payment_instructions', document.getElementById('paymentInstructions').value.trim());
    
    const qrFile = document.getElementById('gcashQR').files[0];
    if (qrFile) {
        formData.append('qr_image', qrFile);
    }
    
    saveToDB(formData);
}

// Save account settings
async function saveAccountSettings() {
    const adminName = document.getElementById('adminName').value.trim();
    const adminEmail = document.getElementById('adminEmail').value.trim();
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    const formData = new FormData();
    formData.append('admin_name', adminName);
    formData.append('admin_email', adminEmail);

    // Only include password fields if user is trying to change it
    if (currentPassword || newPassword || confirmPassword) {
        if (!currentPassword) {
            showNotification('Current password is required to change password', 'warning');
            return;
        }
        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'warning');
            return;
        }
        if (newPassword.length < 6) {
            showNotification('New password must be at least 6 characters', 'warning');
            return;
        }
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
    }

    saveToDB(formData);
    
    // Clear password fields after attempt
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
}

// Save appearance settings
function saveAppearanceSettings() {
    const activeThemeBtn = document.querySelector('.theme-option.active');
    if (activeThemeBtn) {
        const theme = activeThemeBtn.dataset.theme;
        localStorage.setItem('theme', theme);
        
        if (theme === 'light') {
            document.documentElement.classList.add('light-mode');
            document.body.classList.add('light-mode');
        } else {
            document.documentElement.classList.remove('light-mode');
            document.body.classList.remove('light-mode');
        }
        
        showNotification('Appearance settings saved locally', 'success');
    }
}

// Save notification settings
function saveNotificationSettings() {
    const formData = new FormData();
    formData.append('email_new_booking', document.getElementById('emailNewBooking').checked);
    formData.append('email_payment_verified', document.getElementById('emailPaymentVerified').checked);
    formData.append('email_daily_report', document.getElementById('emailDailyReport').checked);
    formData.append('browser_new_booking', document.getElementById('browserNewBooking').checked);
    formData.append('browser_payment_verified', document.getElementById('browserPaymentVerified').checked);
    formData.append('notification_sound', document.getElementById('notificationSound').checked);
    
    saveToDB(formData);
}

// Other UI functions remain the same but use showNotification...
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

async function handleLogout() {
    if (!confirm('Are you sure you want to logout?')) return;
    try {
        await fetch('../../api/auth/logout.php', { method: 'POST' });
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        window.location.href = '../../index.php';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = '../../index.php';
    }
}

// Manage Admins logic
async function loadAdmins() {
    const list = document.getElementById('admins-list');
    if (!list) return;

    try {
        const response = await fetch('../../api/settings/manage-admins.php');
        const result = await response.json();

        if (result.success) {
            if (result.data.length === 0) {
                list.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px dashed var(--premium-border);">
                        <i class="fas fa-users-cog" style="font-size: 2.5rem; color: var(--premium-text-muted); opacity: 0.2; margin-bottom: 16px; display: block;"></i>
                        <p style="color: var(--premium-text-muted);">No sub-admins found. Add one to help manage the gym.</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = result.data.map(admin => {
                const initials = admin.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                return `
                    <div class="admin-item-card">
                        <div class="admin-item-avatar">${initials}</div>
                        <div class="admin-item-info">
                            <div class="admin-item-name">${admin.name}</div>
                            <div class="admin-item-email">${admin.email}</div>
                            <div class="admin-item-badge">Sub-Admin</div>
                        </div>
                        <div class="admin-item-actions">
                            <button class="admin-action-btn" onclick="removeAdmin(${admin.id}, '${admin.name}')" title="Remove Admin">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
    } catch (e) {
        console.error('Error loading admins:', e);
    }
}

function openAddAdminModal() {
    document.getElementById('addAdminModal').classList.add('active');
    document.getElementById('addAdminForm').reset();
}

function closeAddAdminModal() {
    document.getElementById('addAdminModal').classList.remove('active');
}

async function handleAddAdmin(e) {
    e.preventDefault();
    const name = document.getElementById('newAdminName').value.trim();
    const email = document.getElementById('newAdminEmail').value.trim();
    const password = document.getElementById('newAdminPassword').value;

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

        const response = await fetch('../../api/settings/manage-admins.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });

        const result = await response.json();
        if (result.success) {
            showNotification('Admin account created successfully!', 'success');
            closeAddAdminModal();
            loadAdmins();
        } else {
            showNotification(result.message, 'warning');
        }
    } catch (e) {
        console.error('Error adding admin:', e);
        showNotification('Failed to create admin account', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

async function removeAdmin(id, name) {
    if (!confirm(`Are you sure you want to remove ${name} as an administrator? They will no longer be able to access the admin panel.`)) return;

    try {
        const response = await fetch('../../api/settings/manage-admins.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const result = await response.json();
        if (result.success) {
            showNotification('Admin removed successfully', 'success');
            loadAdmins();
        } else {
            showNotification(result.message, 'warning');
        }
    } catch (e) {
        console.error('Error removing admin:', e);
        showNotification('Failed to remove admin', 'error');
    }
}

// Mobile menu toggle
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const sidebar = document.querySelector('.sidebar');

if (mobileMenuToggle && sidebar) {
    mobileMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.replace('fa-bars', 'fa-times');
        } else {
            icon.classList.replace('fa-times', 'fa-bars');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    
    // Update theme selector
    setTimeout(() => {
        const currentTheme = localStorage.getItem('theme') || 'dark';
        document.querySelectorAll('.theme-option').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.theme === currentTheme) btn.classList.add('active');
        });
    }, 100);

    // Setup Drag and Drop
    setupDragAndDrop('hero-upload-area', 'heroImageInput', handleHeroUpload);
    setupDragAndDrop('about-upload-area', 'aboutImageInput', handleGalleryUpload);
});

function setupDragAndDrop(areaId, inputId, uploadHandler) {
    const area = document.getElementById(areaId);
    if (!area) return;

    const dropzone = area.querySelector('.upload-dropzone');
    const input = document.getElementById(inputId);

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('highlight');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('highlight');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        input.files = files; // This triggers handleHeroUpload or handleGalleryUpload via onchange? No, need to call it manually.
        uploadHandler({ files: files });
    }, false);
}
