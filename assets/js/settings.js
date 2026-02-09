// Settings data
let settingsData = {
    general: {},
    payment: {},
    notifications: {},
    account: {}
};

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
}

// Show settings tab
function showSettingsTab(tab) {
    document.querySelectorAll('.settings-section').forEach(section => {
        section.style.display = 'none';
    });
    document.querySelectorAll('.settings-nav-item').forEach(navItem => {
        navItem.classList.remove('active');
    });
    const section = document.getElementById(`settings-${tab}`);
    if (section) section.style.display = 'block';
    const navItem = document.getElementById(`nav-${tab}`);
    if (navItem) navItem.classList.add('active');
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

// Save general settings
function saveGeneralSettings() {
    const formData = new FormData();
    formData.append('gym_name', document.getElementById('gymName').value.trim());
    formData.append('gym_address', document.getElementById('gymAddress').value.trim());
    formData.append('gym_contact', document.getElementById('gymContact').value.trim());
    formData.append('gym_email', document.getElementById('gymEmail').value.trim());
    formData.append('opening_time', document.getElementById('openingTime').value);
    formData.append('closing_time', document.getElementById('closingTime').value);
    formData.append('timezone', document.getElementById('timezone').value);
    
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
});
